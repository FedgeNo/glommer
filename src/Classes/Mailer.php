<?php

declare(strict_types=1);

class Mailer
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_MICROSECONDS = 250000;

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

    private static function attempt(string $to_address, string $to_name, string $subject, string $text_body, string $html_body): bool
    {
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

        return mail($to, $encoded_subject, $body, implode($eol, $headers));
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
