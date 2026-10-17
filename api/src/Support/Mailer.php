<?php

/**
 * Addons ROG - SMTP mail delivery
 *
 * - Send UTF-8 plain-text messages through verified STARTTLS connections.
 * - Validate SMTP replies and close the socket after success or failure.
 */

declare(strict_types=1);

namespace Rog\Support;

use RuntimeException;

class Mailer {
    public function __construct(private array $config) {}

    /**
     * Deliver one queued message with a stable Message-ID supplied by the
     * worker.
     */
    public function send(array $mail, string $message_id): void {

        // =====================================================================
        // DESTINATION VALIDATION AND CONNECTION
        // =====================================================================

        $host = $this->config['smtp_host'];
        if (!preg_match('/^[A-Za-z0-9.-]+$/D', $host)) throw new RuntimeException('Invalid SMTP host');
        foreach ([$mail['to'], $this->config['mail_from']] as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid mail address');
        }
        $context = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host],
        ]);
        $socket = stream_socket_client(
            'tcp://' . $host . ':' . $this->config['smtp_port'], $errno, $message, 15, STREAM_CLIENT_CONNECT, $context
        );
        if ($socket === false) throw new RuntimeException('SMTP connection failed');
        stream_set_timeout($socket, 15);
        // Consume multiline replies and require an expected completion status.
        $read = function (array $codes) use ($socket) {
            do {
                $line = fgets($socket, 4096);
                if ($line === false) throw new RuntimeException('SMTP timeout');
            } while (isset($line[3]) && $line[3] === '-');
            if (!in_array((int)substr($line, 0, 3), $codes, true)) throw new RuntimeException('SMTP rejected command');
        };
        $command = function (string $text, array $codes) use ($socket, $read) {
            if (fwrite($socket, $text . "\r\n") === false) throw new RuntimeException('SMTP write failed');
            $read($codes);
        };

        // =====================================================================
        // TLS NEGOTIATION AND AUTHENTICATION
        // =====================================================================

        try {
            $read([220]); $command('EHLO rog.local', [250]); $command('STARTTLS', [220]);
            $error = 'SMTP TLS failed';
            if (
                !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
            ) throw new RuntimeException($error);
            $command('EHLO rog.local', [250]);
            if ($this->config['smtp_user'] !== '') {
                $command('AUTH LOGIN', [334]); $command(base64_encode($this->config['smtp_user']), [334]);
                $command(base64_encode($this->config['smtp_password']), [235]);
            }
            $command('MAIL FROM:<' . $this->config['mail_from'] . '>', [250]);
            $command('RCPT TO:<' . $mail['to'] . '>', [250, 251]); $command('DATA', [354]);

            // =================================================================
            // MESSAGE HEADERS AND ENCODED BODY
            // =================================================================

            $headers = [
                'From: ' . $this->config['mail_from'], 'To: ' . $mail['to'],
                'Subject: =?UTF-8?B?' . base64_encode($mail['subject']) . '?=', 'Date: ' . gmdate('r'),
                'Message-ID: <' . $message_id . '@' . $host . '>', 'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: base64',
            ];
            $command(
                implode("\r\n", $headers) . "\r\n\r\n"
                    . rtrim(chunk_split(base64_encode($mail['body']), 76, "\r\n")) . "\r\n.", [250]
            );
            $command('QUIT', [221]);
        } finally { fclose($socket); }
    }
}
