<?php

/**
 * Addons ROG - Contact tickets
 *
 * - Resolve founders, the shared moderation destination, and configured mailboxes.
 * - Expose recipient availability and recent contact queue statistics.
 * - Validate contact messages, retain permitted attachments, and queue delivery.
 */

declare(strict_types=1);

namespace Rog\Services;

use finfo;
use Rog\Http\{ApiException, Validator as V};

class ContactService extends Service {

    // =========================================================================
    // STAFF ACCOUNTS AND CONFIGURED MAILBOXES
    // =========================================================================

    /**
     * Resolve current group members again during delivery, locking their accounts.
     */
    private function staff_accounts(bool $lock = false): array {
        $query = 'SELECT id,username,email,profile,access_level FROM accounts WHERE access_level IN (1,2) ';
        $query .= 'AND verified_at IS NOT NULL AND banned_at IS NULL ORDER BY access_level DESC,id';
        if ($lock) $query .= ' FOR UPDATE';
        return $this->db()->all($query);
    }

    /**
     * Keep delivery addresses private; public responses select only display fields.
     */
    private function recipients(): array {
        $staff = $this->staff_accounts(); $recipients = [];
        foreach ($staff as $account) {
            if ((int)$account['access_level'] !== 2) continue;
            $id = 'account:' . $account['id']; $profile = $this->json($account['profile']);
            $recipients[$id] = [
                'id' => $id, 'name' => $account['username'], 'kind' => 'founder',
                'username' => $account['username'], 'color' => $profile['color'] ?? '#c8a24a',
                'description' => '', 'email' => $account['email'], 'replyHours' => 24,
                'accountId' => (int)$account['id'],
            ];
        }
        if ($staff) $recipients['group:moderation'] = [
            'id' => 'group:moderation', 'name' => null, 'kind' => 'moderation',
            'username' => null, 'color' => '#c8a24a', 'description' => '',
            'email' => null, 'replyHours' => 24, 'accountId' => null,
        ];
        foreach ($this->config('contactRecipients') as $key => $mailbox) {
            if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', (string)$key) || !is_array($mailbox)) continue;
            $email = $mailbox['email'] ?? null;
            if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $hours = filter_var($mailbox['replyHours'] ?? 24, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 720],
            ]);
            if ($hours === false) continue;
            $id = 'mailbox:' . $key; $name = $mailbox['name'] ?? $mailbox['username'] ?? $key;
            $description = $mailbox['description'] ?? '';
            $recipients[$id] = [
                'id' => $id, 'name' => is_string($name) && trim($name) !== '' ? $name : $key,
                'kind' => 'mailbox', 'username' => null, 'color' => '#c8a24a',
                'description' => is_string($description) ? $description : '',
                'email' => strtolower($email), 'replyHours' => $hours, 'accountId' => null,
            ];
        }
        return $recipients;
    }

    // =========================================================================
    // RECIPIENT AVAILABILITY AND QUEUE STATISTICS
    // =========================================================================

    public function context(): array {
        $queue = (int)$this->db()->value("SELECT count(*) FROM messages WHERE kind='contact' AND status='open'");
        $recipients = [];
        foreach ($this->recipients() as $id => $recipient) {
            $query = "SELECT count(*) FROM messages WHERE kind='contact' AND status='open' ";
            $query .= "AND data->>'recipientId'=:id";
            $count = (int)$this->db()->value($query, ['id' => $id]); $hours = $recipient['replyHours'];
            $recipients[] = [
                'id' => $id, 'name' => $recipient['name'], 'kind' => $recipient['kind'],
                'username' => $recipient['username'], 'color' => $recipient['color'],
                'description' => $recipient['description'],
                'status' => $count > 10 ? 'reviewing' : 'normal',
                'replyHours' => ['min' => $hours, 'max' => $hours * 2],
                'load' => min(100, $count * 5), 'estimatedReplyHours' => $hours,
                'availabilityPercent' => max(0, 100 - $count * 5),
            ];
        }
        $query = "SELECT count(*)::int AS received,count(*) FILTER(WHERE status='answered')::int AS answered,";
        $query .= 'coalesce(round(avg(extract(epoch FROM completed_at-created_at)/3600)),0)::int ';
        $query .= 'AS "averageReplyHours" ';
        $query .= "FROM messages WHERE kind='contact' AND created_at>now()-interval '7 days'";
        $week = $this->row($query);
        return [
            'queue' => $queue, 'queueCount' => $queue, 'recipients' => $recipients, 'week' => $week,
            'topicIds' => ['broken', 'rejected', 'takedown', 'account', 'report', 'other'],
        ];
    }

    // =========================================================================
    // CONTACT MESSAGE VALIDATION
    // =========================================================================

    public function send(): array {
        $this->rate('contact', 3, 3600); $body = $this->request->body;
        $recipient_id = V::text($body['recipientId'] ?? null, 'recipientId', 1, 100);
        $recipient = $this->recipients()[$recipient_id] ?? V::fail('recipientId');
        $topic = V::choice($body['topicId'] ?? null, 'topicId', [
            'broken', 'rejected', 'takedown', 'account', 'report', 'other',
        ]);
        $name = V::text($body['name'] ?? null, 'name', 2, 60);
        $email = V::email($body['email'] ?? $this->app->user['email'] ?? null);
        $subject = V::text($body['subject'] ?? null, 'subject', 3, 120);
        $message = V::text($body['message'] ?? null, 'message', 20, 5000); $files = [];

        // Normalize the single-file and multipart array forms before enforcing
        // attachment limits.
        if (isset($this->request->files['attachment'])) $files[] = $this->request->files['attachment'];
        if (isset($this->request->files['attachments'])) {
            $uploads = $this->request->files['attachments'];
            if (!is_array($uploads['name'] ?? null)) V::fail('attachments');
            foreach ($uploads['name'] as $i => $filename) {
                $file = [];
                foreach (['name', 'tmp_name', 'error', 'size', 'type'] as $key) {
                    $file[$key] = $uploads[$key][$i] ?? null;
                }
                $files[] = $file;
            }
        }
        if (
            count($files) > 1 || array_sum(array_column($files, 'size')) > (2 * 1024 * 1024)
        ) throw new ApiException(413, 'ATTACHMENTS_TOO_LARGE');

        // Contact attachments use the stricter image allowlist before generic
        // asset storage.
        $assets = [];
        foreach ($files as $file) {
            if (
                ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
                && !in_array(
                    (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']), ['image/png', 'image/jpeg'], true
                )
            ) throw new ApiException(415, 'UNSUPPORTED_MEDIA_TYPE');
            $asset = (new FileService($this->app))->store($file, 'attachment');
            if ($asset['status'] === 'blocked') V::fail('attachments', 'INVALID_ARCHIVE');
            $assets[] = $asset;
        }

        // =====================================================================
        // TICKET PERSISTENCE AND DELIVERY
        // =====================================================================

        return $this->db()->transaction(function () use (
            $recipient, $topic, $name, $email, $subject, $message, $assets
        ) {
            $data = [
                'recipientId' => $recipient['id'], 'topicId' => $topic, 'name' => $name,
                'email' => $email, 'subject' => $subject, 'body' => $message,
            ];
            // Resolve all group members at send time, including every founder.
            $targets = [$recipient];
            if ($recipient['kind'] === 'moderation') {
                $targets = array_map(fn($account) => [
                    'accountId' => (int)$account['id'], 'email' => $account['email'],
                ], $this->staff_accounts(true));
                if (!$targets) V::fail('recipientId');
            } elseif ($recipient['accountId'] !== null) {
                $query = 'SELECT email FROM accounts WHERE id=:id AND access_level=2 ';
                $query .= 'AND verified_at IS NOT NULL AND banned_at IS NULL FOR UPDATE';
                $account = $this->db()->one($query, ['id' => $recipient['accountId']]);
                if (!$account) V::fail('recipientId');
                $targets[0]['email'] = $account['email'];
            }
            $query = 'INSERT INTO messages(kind,sender_id,recipient_id,status,data) ';
            $query .= "VALUES ('contact',:sender,:recipient,'open',CAST(:data AS jsonb)) RETURNING *";
            $row = $this->row($query, [
                'sender' => $this->user_id() ?: null, 'recipient' => $recipient['accountId'],
                'data' => $this->encode($data),
            ]);
            // Link retained attachments to the ticket and remove their
            // temporary upload expiry.
            foreach ($assets as $i => $asset) {
                $query = 'INSERT INTO asset_links(asset_id,message_id,slot,position) ';
                $query .= "VALUES (:asset,:message,'attachment',:position)";
                $this->db()->query($query, ['asset' => $asset['id'], 'message' => $row['id'], 'position' => $i + 1]);
                $this->db()->query('UPDATE assets SET expires_at=NULL WHERE id=:id', ['id' => $asset['id']]);
            }
            $links = array_map(fn($asset) => (new FileService($this->app))->url($asset), $assets);
            $mail_body = $message . "\nReply to: " . $email . '; ticket ' . $row['id'];
            $mail_body .= "\nAttachments (staff login required):\n" . implode("\n", $links);
            $notifications = new NotificationService($this->app);
            // Each recipient needs a distinct queue key under the same ticket.
            foreach ($targets as $target) $notifications->email(
                $target['email'], $subject, $mail_body,
                'contact:' . $row['id'] . ':recipient:' . ($target['accountId'] ?? 'mailbox'),
                $target['accountId'], (int)$row['id']
            );
            $hours = $recipient['replyHours'];
            return [
                'ticketId' => $this->external_id($row['id']), 'messageId' => $this->external_id($row['id']),
                'status' => 'queued', 'createdAt' => $this->date($row['created_at']),
                'estimatedReplyHours' => $hours, 'estimatedReplyAt' => gmdate('c', time() + $hours * 3600),
                'attachments' => array_map(fn($a) => [
                    'id' => $this->external_id($a['id']), 'name' => $a['filename'],
                    'sizeBytes' => (int)$a['size_bytes'], 'mediaType' => $a['mime_type'],
                ], $assets),
            ];
        });
    }
}
