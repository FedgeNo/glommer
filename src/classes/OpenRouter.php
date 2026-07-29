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
}
