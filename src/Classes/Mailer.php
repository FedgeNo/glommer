<?php

declare(strict_types=1);

class Mailer
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_MICROSECONDS = 250000;

    // Set by the last attempt() call: true only when the destination server
    // actively refused this specific recipient (RCPT TO rejected), as opposed
    // to our own mail transport being unreachable or misconfigured. Lets
    // EmailVerification::sendFor() tell "mail is down" (safe to auto-verify)
    // apart from "this address doesn't accept mail" (an attacker could
    // engineer this on purpose, so it must not bypass verification).
    private static bool $recipientRejected = false;

    public static function send(string $to_address, string $to_name, string $subject, string $text_body, string $html_body): bool
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            if (self::attempt($to_address, $to_name, $subject, $text_body, $html_body)) {
                return true;
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                usleep(self::RETRY_DELAY_MICROSECONDS);
            }
        }

        return false;
    }

    public static function recipientWasRejected(): bool
    {
        return self::$recipientRejected;
    }

    private static function attempt(string $to_address, string $to_name, string $subject, string $text_body, string $html_body): bool
    {
        self::$recipientRejected = false;

        $config = require __DIR__ . '/../config.php';
        $from_address = $config['mailFromAddress'];
        $from_name = $config['mailFromName'];

        $eol = chr(13) . chr(10);
        $boundary = 'boundary-' . bin2hex(random_bytes(16));

        $headers = [];
        $headers[] = 'From: ' . self::encodeHeader($from_name) . ' <' . $from_address . '>';
        $headers[] = 'Reply-To: ' . $from_address;
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . self::domainFromAddress($from_address) . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = '--' . $boundary . $eol
            . 'Content-Type: text/plain; charset=UTF-8' . $eol
            . 'Content-Transfer-Encoding: 8bit' . $eol . $eol
            . $text_body . $eol . $eol
            . '--' . $boundary . $eol
            . 'Content-Type: text/html; charset=UTF-8' . $eol
            . 'Content-Transfer-Encoding: 8bit' . $eol . $eol
            . $html_body . $eol . $eol
            . '--' . $boundary . '--';

        $to = self::encodeHeader($to_name) . ' <' . $to_address . '>';
        $encoded_subject = self::encodeHeader($subject);

        // A configured SMTP relay takes precedence over PHP's mail() (the
        // local sendmail handoff, which on a typical VPS has no reputation and
        // lands in spam - see README's deliverability section).
        if ($config['SMTPHost'] !== '') {
            $message = 'To: ' . $to . $eol
                . 'Subject: ' . $encoded_subject . $eol
                . implode($eol, $headers) . $eol . $eol
                . $body;

            return self::sendViaSMTP($config, $from_address, $to_address, $message);
        }

        return mail($to, $encoded_subject, $body, implode($eol, $headers));
    }

    /**
     * A minimal SMTP client (no dependencies, same spirit as the hand-rolled
     * WebSocket daemon): EHLO, optional STARTTLS, optional AUTH LOGIN, one
     * MAIL FROM/RCPT TO/DATA exchange, QUIT. Returns false (after logging the
     * server's complaint) on any unexpected reply, letting send()'s retry
     * loop and the callers' failure handling take over.
     */
    private static function sendViaSMTP(array $config, string $from_address, string $to_address, string $message): bool
    {
        $eol = chr(13) . chr(10);
        $timeout_seconds = 10;

        $transport = $config['SMTPEncryption'] === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client(
            $transport . $config['SMTPHost'] . ':' . $config['SMTPPort'],
            $error_code,
            $error_message,
            $timeout_seconds
        );

        if ($socket === false) {
            error_log('SMTP connection to ' . $config['SMTPHost'] . ':' . $config['SMTPPort'] . ' failed: ' . $error_message);

            return false;
        }

        stream_set_timeout($socket, $timeout_seconds);

        $expect = function (string $expected_prefix) use ($socket): bool {
            // Replies can span lines ("250-..." continuations); the final line
            // has a space after the code.
            do {
                $line = fgets($socket);

                if ($line === false) {
                    error_log('SMTP connection dropped mid-reply');

                    return false;
                }
            } while (isset($line[3]) && $line[3] === '-');

            if (!str_starts_with($line, $expected_prefix)) {
                error_log('SMTP expected ' . $expected_prefix . ' but got: ' . trim($line));

                return false;
            }

            return true;
        };

        $say = function (string $command) use ($socket, $eol): void {
            fwrite($socket, $command . $eol);
        };

        $hello_name = self::domainFromAddress($from_address);

        $conversation = function () use ($config, $from_address, $to_address, $message, $expect, $say, $socket, $eol, $hello_name): bool {
            if (!$expect('220')) {
                return false;
            }

            $say('EHLO ' . $hello_name);

            if (!$expect('250')) {
                return false;
            }

            if ($config['SMTPEncryption'] === 'tls') {
                $say('STARTTLS');

                if (!$expect('220')) {
                    return false;
                }

                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    error_log('SMTP STARTTLS negotiation failed');

                    return false;
                }

                // The session resets after STARTTLS - greet again.
                $say('EHLO ' . $hello_name);

                if (!$expect('250')) {
                    return false;
                }
            }

            if ($config['SMTPUsername'] !== '') {
                $say('AUTH LOGIN');

                if (!$expect('334')) {
                    return false;
                }

                $say(base64_encode($config['SMTPUsername']));

                if (!$expect('334')) {
                    return false;
                }

                $say(base64_encode($config['SMTPPassword']));

                if (!$expect('235')) {
                    return false;
                }
            }

            $say('MAIL FROM:<' . $from_address . '>');

            if (!$expect('250')) {
                return false;
            }

            $say('RCPT TO:<' . $to_address . '>');

            if (!$expect('25')) {
                self::$recipientRejected = true;

                return false;
            }

            $say('DATA');

            if (!$expect('354')) {
                return false;
            }

            // Dot-stuffing: a line consisting of a single '.' would end the
            // message early, so any line starting with '.' gets it doubled.
            $stuffed = preg_replace('/^\./m', '..', $message);

            fwrite($socket, $stuffed . $eol . '.' . $eol);

            if (!$expect('250')) {
                return false;
            }

            $say('QUIT');

            // The message is already accepted (the 250 above) so a missing or
            // odd QUIT reply doesn't change the outcome - but read the 221 the
            // server sends before we close, so the conversation ends cleanly
            // rather than dropping the socket mid-reply.
            $expect('221');

            return true;
        };

        $sent = $conversation();
        fclose($socket);

        return $sent;
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function domainFromAddress(string $address): string
    {
        $parts = explode('@', $address);

        return $parts[1] ?? 'localhost';
    }
}
