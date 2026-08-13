<?php

declare(strict_types=1);

/**
 * OpenRouter API access, for AI features that need one (trending-topic
 * summaries, etc.). Optional: features built on this treat a blank API key as
 * "not configured" and skip themselves.
 *
 * Defaults to the Free Models Router (openrouter/free), which OpenRouter picks
 * at random from whatever's currently free and can never incur cost - unlike
 * appending :free to a model or router slug, which does NOT restrict routing to
 * free models and can still bill. neverSpend() is a second, independent guard
 * for whenever a caller changes the model to something paid: it's read by the
 * caller and enforced as max_price: 0 in the request, so a request fails
 * outright rather than being deprioritized if no free provider is available.
 * Defaults on - spending money is something an admin opts into, not out of.
 */
class OpenRouter
{
    public const API_KEY_SETTING = 'openRouterAPIKey';
    public const MODEL_SETTING = 'openRouterModel';
    public const NEVER_SPEND_SETTING = 'openRouterNeverSpend';

    public const DEFAULT_MODEL = 'openrouter/free';

    public static function apiKey(): string
    {
        return (string) Settings::get(self::API_KEY_SETTING, '');
    }

    public static function model(): string
    {
        $model = (string) Settings::get(self::MODEL_SETTING, '');

        return $model !== '' ? $model : self::DEFAULT_MODEL;
    }

    // Defaults true when never set, so a fresh install starts guarded rather
    // than trusting whatever model an admin later types in.
    public static function neverSpend(): bool
    {
        return (string) Settings::get(self::NEVER_SPEND_SETTING, '1') === '1';
    }

    public static function isEnabled(): bool
    {
        return self::apiKey() !== '';
    }

    /**
     * How a moderation model labels its answer.
     *
     * The router picks whatever is free at the moment, and some of what it
     * picks classifies the message instead of doing as it was asked - back
     * comes "User Safety: safe", or a list of categories, and nothing else.
     * Every caller here would otherwise publish that as its answer: a
     * translation nobody can read, a topic summary sitting on a tag page.
     *
     * Matched by the label a verdict opens with, because the verdict itself is
     * whatever that model felt like saying. New ones turn up as the free set
     * changes; this is the list to add them to.
     */
    private const VERDICT_LABELS = [
        'user safety:',
        'safety:',
        'safety categories:',
        'content safety:',
        'classification:',
    ];

    /**
     * One chat completion.
     *
     * Three outcomes, and the difference matters to a caller deciding whether
     * to ask again: the answer, or the empty string when a model answered with
     * nothing but a verdict (a bad roll of the router - asking again lands
     * somewhere else), or null when there was no answer at all (no key, no
     * network, nothing to try differently).
     *
     * Never an exception: a caller here is always a background job or a
     * request someone chose to wait on.
     *
     * @param array<int, array{role: string, content: string}> $messages
     */
    public static function chat(array $messages, int $max_tokens = 400): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }

        $body = [
            'model' => self::model(),
            'messages' => $messages,
            'max_tokens' => $max_tokens,
        ];

        // The independent guard the class docblock promises: with neverSpend
        // on, a request routed toward any paid provider fails outright rather
        // than billing - even if the admin's chosen model slug is paid.
        if (self::neverSpend()) {
            $body['provider'] = ['max_price' => ['prompt' => 0, 'completion' => 0]];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . self::apiKey(),
                    'Content-Type: application/json',
                ]),
                'content' => (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents('https://openrouter.ai/api/v1/chat/completions', false, $context);

        if ($response === false) {
            error_log('OpenRouter: the request did not complete (no response, or the connection failed).');

            return null;
        }

        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;

        // ignore_errors is what lets the body of a refusal be read at all, and
        // the cost of it is that a refusal looks exactly like an answer until
        // something checks. Logged with the status and what the service said,
        // because an expired key and a model with nothing to say are the same
        // silence from the outside - and this is the fallback the translate
        // button leans on, so it can be broken for weeks without a page
        // looking any different.
        if (!is_string($content) || trim($content) === '') {
            $status = self::statusFrom($http_response_header ?? []);

            error_log(
                'OpenRouter: no usable answer'
                . ($status === null ? '' : ' (HTTP ' . $status . ')')
                . ': ' . substr((string) ($decoded['error']['message'] ?? $response), 0, 300)
            );

            return null;
        }

        return self::withoutVerdict(trim($content));
    }

    /**
     * The status out of the headers file_get_contents leaves behind, or null
     * where there is no status line to read.
     *
     * @param string[] $headers
     */
    private static function statusFrom(array $headers): ?int
    {
        foreach ($headers as $header) {
            if (preg_match('#\AHTTP/[0-9.]+ ([0-9]{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * The answer with any verdict taken off, front or back - and the empty
     * string when the verdict was the whole of it.
     *
     * Stripped here rather than by each caller so a model that classifies
     * cannot be published by whichever feature forgot to look.
     */
    public static function withoutVerdict(string $answer): string
    {
        $lines = explode(chr(10), $answer);

        while ($lines !== [] && self::isVerdict($lines[0])) {
            array_shift($lines);
        }

        while ($lines !== [] && self::isVerdict($lines[count($lines) - 1])) {
            array_pop($lines);
        }

        return trim(implode(chr(10), $lines));
    }

    /**
     * Whether a line opens with one of those labels.
     *
     * Only what stands before the colon is compared. The verdict after it is
     * whatever the model decided - "safe", or a list of categories - and will
     * read differently every time, while the label naming it does not.
     */
    private static function isVerdict(string $line): bool
    {
        $colon = strpos($line, ':');

        if ($colon === false) {
            return false;
        }

        $label = strtolower(trim(substr($line, 0, $colon))) . ':';

        return in_array($label, self::VERDICT_LABELS, true);
    }
}
