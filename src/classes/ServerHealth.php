<?php

declare(strict_types=1);

/** Current machine and application-database readings, visible only on the admin page. */
class ServerHealth extends Div
{
    public ?string $class = 'ServerHealth';
    public ?array $readings = null;

    public function toDOM(): \DOMElement
    {
        $this -> id ??= 'ServerHealth';
        $this -> addContent(new Heading2((string) (Strings::for(self::class)['heading'] ?? '')));

        foreach ($this -> readings ?? self::readings() as $reading) {
            $line = new Paragraph($reading['text']);
            $line -> attributes['data-reading'] = $reading['id'];
            $line -> attributes['data-state'] = $reading['state'];
            $this -> addContent($line);
        }

        return parent::toDOM();
    }

    /** An injected snapshot is used as-is, with no live I/O for missing readings. */
    public static function readings(?array $metrics = null): array
    {
        $metrics ??= self::collect();
        $words = Strings::for(self::class);
        $unavailable = (string) (Strings::for(StatusBoard::class)['unavailable'] ?? '');
        $readings = [];
        $add = static function (string $id, string $key, ?array $values, string $state = 'neutral') use (&$readings, $words, $unavailable): void {
            $readings[] = [
                'id' => $id,
                'text' => $values === null ? strtr((string) ($words[$key] ?? ''), array_fill_keys(self::tokens((string) ($words[$key] ?? '')), '—')) . ' · ' . $unavailable
                    : strtr((string) ($words[$key] ?? ''), $values),
                'state' => $values === null ? 'unknown' : $state,
            ];
        };

        $add('ip', 'ip', isset($metrics['ip']) ? ['{address}' => $metrics['ip']] : null);
        $load = $metrics['load'] ?? null;
        $cpus = (int) ($metrics['cpus'] ?? 0);
        $add('load', 'load', is_array($load) && count($load) >= 3 ? [
            '{one}' => self::number((float) $load[0], 2),
            '{five}' => self::number((float) $load[1], 2),
            '{fifteen}' => self::number((float) $load[2], 2),
            '{cpus}' => $cpus > 0 ? self::number($cpus) : '—',
        ] : null, $cpus > 0 && is_array($load) && $load[0] > $cpus ? 'warning' : 'neutral');

        $memory = $metrics['memory'] ?? null;
        $add('memory', 'memory', is_array($memory) ? [
            '{available}' => self::bytes($memory['available']), '{total}' => self::bytes($memory['total']),
        ] : null, self::capacityState($memory));

        foreach (['root' => '/', 'uploads' => 'uploads'] as $id => $label) {
            $disk = $metrics['disks'][$id] ?? null;
            $add('disk-' . $id, 'disk', is_array($disk) ? [
                '{free}' => self::bytes($disk['available']), '{total}' => self::bytes($disk['total']), '{path}' => $label,
            ] : null, self::capacityState($disk));
        }

        $database = $metrics['database'] ?? null;
        $add('database', 'database', is_array($database) ? [
            '{running}' => self::number($database['Threads_running']),
            '{connected}' => self::number($database['Threads_connected']),
            '{lockWaits}' => self::number($database['Innodb_row_lock_current_waits']),
            '{uptime}' => self::duration($database['Uptime']),
        ] : null, ($database['Innodb_row_lock_current_waits'] ?? 0) > 0 ? 'warning' : 'neutral');
        $add('queries', 'longQueries', isset($metrics['longQueries']) ? ['{count}' => self::number($metrics['longQueries'])] : null,
            ($metrics['longQueries'] ?? 0) > 0 ? 'warning' : 'neutral');

        return $readings;
    }

    private static function collect(): array
    {
        $address = $_SERVER['SERVER_ADDR'] ?? null;
        $metrics = [
            'ip' => is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false ? $address : null,
            'load' => function_exists('sys_getloadavg') ? (sys_getloadavg() ?: null) : null,
            'cpus' => preg_match_all('/^processor\s*:/m', (string) @file_get_contents('/proc/cpuinfo')),
            'memory' => self::memory((string) @file_get_contents('/proc/meminfo')),
        ];

        foreach (['root' => '/', 'uploads' => dirname(__DIR__, 2) . '/uploads'] as $key => $path) {
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            $metrics['disks'][$key] = $free === false || $total === false ? null : ['available' => (int) $free, 'total' => (int) $total];
        }

        try {
            $names = ['Threads_running', 'Threads_connected', 'Uptime', 'Innodb_row_lock_current_waits'];
            $status = [];

            foreach (DB::rows('SHOW GLOBAL STATUS WHERE `Variable_name` IN (?, ?, ?, ?)', 'stdClass', 'ssss', ...$names) as $row) {
                $status[$row -> Variable_name] = (int) $row -> Value;
            }

            if (count(array_intersect_key($status, array_flip($names))) === count($names)) {
                $metrics['database'] = $status;
            }

            // The runtime account sees its own application queries. Query
            // bodies can contain private text; only the count leaves the server.
            $metrics['longQueries'] = (int) DB::row('
SELECT COUNT(*) AS `total`
    FROM `information_schema`.`PROCESSLIST`
    WHERE `DB` = DATABASE() AND `COMMAND` <> ? AND `TIME` >= ? AND `ID` <> CONNECTION_ID()
', 'PostCountData', 'si', 'Sleep', 5) -> total;
        } catch (\Throwable $exception) {
            error_log('Admin database health could not report: ' . $exception -> getMessage());
        }

        return $metrics;
    }

    public static function memory(string $text): ?array
    {
        preg_match_all('/^(MemAvailable|MemTotal):\s+(\d+) kB/m', $text, $matches, PREG_SET_ORDER);
        $values = [];

        foreach ($matches as $match) {
            $values[$match[1]] = (int) $match[2] * 1024;
        }

        return isset($values['MemAvailable'], $values['MemTotal']) && $values['MemTotal'] > 0
            ? ['available' => $values['MemAvailable'], 'total' => $values['MemTotal']] : null;
    }

    private static function capacityState(?array $capacity): string
    {
        if ($capacity === null || $capacity['total'] <= 0) {
            return 'unknown';
        }

        $share = $capacity['available'] / $capacity['total'];

        return $share < 0.05 ? 'bad' : ($share < 0.1 ? 'warning' : 'neutral');
    }

    public static function number(int|float $number, int $decimals = 0): string
    {
        $format = new \NumberFormatter(Strings::locale(), \NumberFormatter::DECIMAL);
        $format -> setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $format -> setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return (string) $format -> format($number);
    }

    private static function bytes(int $bytes): string
    {
        foreach (['GiB' => 1024 ** 3, 'MiB' => 1024 ** 2, 'KiB' => 1024] as $unit => $size) {
            if ($bytes >= $size) {
                return self::number($bytes / $size, 1) . ' ' . $unit;
            }
        }

        return self::number($bytes) . ' B';
    }

    private static function duration(int $seconds): string
    {
        return sprintf('%s:%02d:%02d', self::number(intdiv($seconds, 3600)), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    private static function tokens(string $text): array
    {
        preg_match_all('/\{[a-zA-Z]+\}/', $text, $matches);

        return $matches[0];
    }
}
