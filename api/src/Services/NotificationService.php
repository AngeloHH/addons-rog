<?php

/**
 * Addons ROG - Encrypted notification queue
 *
 * - Queue encrypted email payloads with stable deduplication keys.
 * - Claim delivery work, retry failures, and update parent staff messages.
 */

declare(strict_types=1);

namespace Rog\Services;

use Rog\Support\Mailer;
use RuntimeException;
use Throwable;

class NotificationService extends Service {

    // =========================================================================
    // ENCRYPTED PAYLOADS AND QUEUE INSERTION
    // =========================================================================

    public function email(
        string $email, string $subject, string $body, string $key,
        ?int $recipient = null, ?int $parent = null
    ): int {
        $nonce = random_bytes(12); $tag = '';
        $payload = $this->encode(['to' => $email, 'subject' => $subject, 'body' => $body]);
        $cipher = openssl_encrypt(
            $payload, 'aes-256-gcm', hash('sha256', $this->app->config['secret'], true),
            OPENSSL_RAW_DATA, $nonce, $tag
        );
        if ($cipher === false) throw new RuntimeException('Cannot encrypt mail');
        // Persist the nonce, authentication tag, and ciphertext together; keep
        // the key in configuration.
        $data = ['dedupeKey' => $key, 'cipher' => base64_encode($nonce . $tag . $cipher), 'attempts' => 0];
        $query = 'INSERT INTO messages(kind,recipient_id,parent_id,status,data) ';
        $query .= "VALUES ('notification',:recipient,:parent,'queued',CAST(:data AS jsonb)) ";
        $query .= 'ON CONFLICT DO NOTHING RETURNING id';
        $row = $this->db()->one($query, [
            'recipient' => $recipient, 'parent' => $parent, 'data' => $this->encode($data),
        ]);
        return (int)($row['id'] ?? $this->db()->value(
            "SELECT id FROM messages WHERE data->>'dedupeKey'=:key", ['key' => $key]
        ));
    }

    public function account(int $id, string $subject, string $body, string $key): int {
        $account = $this->row('SELECT email FROM accounts WHERE id=:id', ['id' => $id]);
        return $this->email($account['email'], $subject, $body, $key, $id);
    }

    /**
     * Authenticate and decrypt the queued payload before returning its mail
     * fields.
     */
    public function decode(array $data): array {
        $raw = base64_decode($data['cipher'], true);
        if ($raw === false || strlen($raw) < 29) throw new RuntimeException('Invalid encrypted mail');
        $plain = openssl_decrypt(
            substr($raw, 28), 'aes-256-gcm', hash('sha256', $this->app->config['secret'], true),
            OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16)
        );
        if ($plain === false) throw new RuntimeException('Cannot decrypt mail');
        return $this->json($plain);
    }

    // =========================================================================
    // DELIVERY CLAIMS AND RETRIES
    // =========================================================================

    public function work(int $limit = 100): int {
        $count = 0;
        while ($count < $limit) {
            // Claim one due message and lease it for ten minutes before
            // performing external delivery.
            $row = $this->db()->transaction(function () {
                $query = "SELECT * FROM messages WHERE kind='notification' AND status IN ('queued','processing') ";
                $query .= 'AND available_at<=now() ORDER BY id FOR UPDATE SKIP LOCKED LIMIT 1';
                $row = $this->db()->one($query);
                if ($row) $this->db()->query(
                    "UPDATE messages SET status='processing',available_at=now()+interval '10 minutes' WHERE id=:id",
                    ['id' => $row['id']]
                );
                return $row;
            });
            if ($row === null) break;
            $data = $this->json($row['data']);
            try {
                $mail = $this->decode($data);
                if ($this->app->config['mail_transport'] === 'spool') {
                    $dir = $this->app->config['storage'] . '/mail'; $error = 'Cannot create spool';
                    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException($error);
                    if (
                        file_put_contents($dir . '/' . $row['id'] . '.json', $this->encode($mail), LOCK_EX) === false
                    ) throw new RuntimeException('Cannot spool mail');
                } elseif ($this->app->config['mail_transport'] === 'smtp') {
                    (new Mailer($this->app->config))->send($mail, 'rog-' . $row['id']);
                } else throw new RuntimeException('Unknown mail transport');
                $this->db()->query("UPDATE messages SET status='sent',completed_at=now() WHERE id=:id", [
                    'id' => $row['id'],
                ]);
                if ($row['parent_id'] !== null) $this->db()->query(
                    "UPDATE messages SET status='sent',completed_at=now() WHERE id=:id AND kind='staff'",
                    ['id' => $row['parent_id']]
                );
            } catch (Throwable $error) {
                // Retry after five minutes and mark the message failed after
                // the fifth unsuccessful attempt.
                $data['attempts']++; $data['lastError'] = 'DELIVERY_FAILED';
                $query = "UPDATE messages SET status=:status,data=CAST(:data AS jsonb),";
                $query .= "available_at=now()+interval '5 minutes' WHERE id=:id";
                $this->db()->query($query, [
                    'status' => $data['attempts'] >= 5 ? 'failed' : 'queued',
                    'data' => $this->encode($data), 'id' => $row['id'],
                ]);
                if ($data['attempts'] >= 5 && $row['parent_id'] !== null) $this->db()->query(
                    "UPDATE messages SET status='failed' WHERE id=:id AND kind='staff' AND status<>'sent'",
                    ['id' => $row['parent_id']]
                );
                error_log('Mail delivery failed for message ' . $row['id']);
            }
            $count++;
        }
        return $count;
    }
}
