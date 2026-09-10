<?php

declare(strict_types=1);

/** One bounded, read-only systemd snapshot for the admin's service tiles. */
class ServiceStatus
{
    private const UNITS = [
        'uploads' => 'glommer-upload-worker.service',
        'notifications' => 'glommer-websocket.service',
        'federation' => 'glommer-federation-worker.service',
        'trending' => 'glommer-trending.timer',
        'backups' => 'glommer-backup.timer',
    ];

    /** @return array<string, ?bool> null means the status could not be established */
    public static function snapshot(): array
    {
        $unknown = array_fill_keys(array_keys(self::UNITS), null);

        if (!function_exists('shell_exec')) {
            return $unknown;
        }

        $arguments = implode(' ', array_map('escapeshellarg', array_values(self::UNITS)));
        $command = 'timeout 2s systemctl show --property=Id --property=LoadState --property=ActiveState ';
        $system = self::parse((string) @shell_exec($command . $arguments . ' 2>/dev/null'));

        if (!in_array(false, $system, true) && !in_array(null, $system, true)) {
            return $system;
        }

        $user = self::parse((string) @shell_exec($command . '--user ' . $arguments . ' 2>/dev/null'));

        foreach ($unknown as $key => $_) {
            $unknown[$key] = $system[$key] === true || $user[$key] === true
                ? true : ($system[$key] === false || $user[$key] === false ? false : null);
        }

        return $unknown;
    }

    /** A missing unit or a denied query must not be reported as a stopped service. */
    public static function parse(string $output): array
    {
        $states = array_fill_keys(array_keys(self::UNITS), null);

        foreach (preg_split('/\R\s*\R/', trim($output)) ?: [] as $block) {
            $properties = [];

            foreach (preg_split('/\R/', $block) ?: [] as $line) {
                $parts = explode('=', $line, 2);

                if (count($parts) === 2) {
                    $properties[$parts[0]] = $parts[1];
                }
            }

            $key = array_search($properties['Id'] ?? '', self::UNITS, true);

            if ($key === false || ($properties['LoadState'] ?? '') !== 'loaded') {
                continue;
            }

            $states[$key] = match ($properties['ActiveState'] ?? '') {
                'active', 'activating', 'reloading' => true,
                'inactive', 'failed', 'deactivating' => false,
                default => null,
            };
        }

        return $states;
    }
}
