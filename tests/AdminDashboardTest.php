<?php

declare(strict_types=1);

class AdminDashboardTest extends TestCase
{
    private function fixture(): array
    {
        $counts = new SiteCountersData();
        $counts -> members = 12;
        $counts -> joinedThisWeek = 2;
        $counts -> postedThisWeek = 3;
        $counts -> posts = 45;
        $counts -> postsThisWeek = 6;
        $counts -> deliveriesQueued = 7;
        $counts -> deliveriesFailing = 0;

        return [
            'counts' => $counts,
            'services' => array_fill_keys(['uploads', 'federation', 'notifications', 'trending', 'backups'], true),
            'deliveries' => ['delivered' => 100, 'undeliverable' => 1],
            'pendingReads' => 8,
            'uploads' => ['staging' => 1, 'pending' => 2, 'processing' => 3],
            'trending' => date('Y-m-d H:i:s'),
            'reports' => 0,
            'activeMembers' => 4,
            'websocket' => ['ok' => true, 'message' => ''],
            'backups' => ['readable' => true, 'time' => time()],
            'health' => ServerHealth::readings([]),
        ];
    }

    private function tiles(array $provided, bool $overview = true): array
    {
        Strings::useLocale('en');

        try {
            return array_column(AdminStatus::snapshot($overview, $provided)['tiles'], null, 'id');
        } finally {
            Strings::useLocale(null);
        }
    }

    public function testOverviewKeepsTheCommunityAndQueueFigures(): void
    {
        $tiles = $this -> tiles($this -> fixture());
        $this -> assertCount(8, $tiles);
        $this -> assertSame('12', $tiles['members']['value']);
        $this -> assertSame('45', $tiles['posts']['value']);
        $this -> assertTrue(str_contains($tiles['members']['detail'], '4'));
        $this -> assertTrue(str_contains($tiles['members']['detail'], '3'));
        $this -> assertTrue(str_contains($tiles['federation']['detail'], '100'));
        $this -> assertTrue(str_contains($tiles['federation']['detail'], '8'));
        $this -> assertSame('good', $tiles['federation']['state']);
        $this -> assertSame('good', $tiles['notifications']['state']);
        $this -> assertSame('good', $tiles['backups']['state']);
        $this -> assertSame('/admin/reports', $tiles['moderation']['href']);
    }

    public function testFastRefreshLeavesTheSlowerOverviewTilesAlone(): void
    {
        $fixture = $this -> fixture();
        unset($fixture['activeMembers'], $fixture['websocket'], $fixture['backups']);
        $tiles = $this -> tiles($fixture, false);
        $this -> assertSame(['federation', 'uploads', 'trending', 'moderation'], array_keys($tiles));
        $this -> assertSame('good', $tiles['federation']['state']);
    }

    public function testMissingReadingsAreUnknownInsteadOfHealthyZeroes(): void
    {
        $tiles = $this -> tiles([]);

        foreach ($tiles as $tile) {
            $this -> assertSame('unknown', $tile['state'], $tile['id']);
            $this -> assertFalse($tile['value'] === '0', $tile['id']);
        }

        foreach (ServerHealth::readings([]) as $reading) {
            $this -> assertSame('unknown', $reading['state']);
            $this -> assertFalse(str_contains($reading['text'], '{'));
        }

        $fixture = $this -> fixture();
        $fixture['trending'] = null;
        $fixture['backups'] = ['readable' => false, 'time' => null];
        $fixture['counts'] = null;
        $tiles = $this -> tiles($fixture);
        foreach (['trending', 'backups', 'federation'] as $key) {
            $this -> assertSame('unknown', $tiles[$key]['state']);
            $this -> assertSame('Could not report', $tiles[$key]['detail']);
        }
    }

    public function testStaleDataAndFailedServicesGetAttention(): void
    {
        $fixture = $this -> fixture();
        $fixture['services']['uploads'] = false;
        $fixture['counts'] -> deliveriesFailing = 2;
        $fixture['trending'] = date('Y-m-d H:i:s', time() - 3600);
        $fixture['backups']['time'] = time() - 48 * 3600;
        $fixture['reports'] = 2;
        $fixture['websocket'] = ['ok' => false, 'message' => 'Connection refused'];
        $tiles = $this -> tiles($fixture);
        $this -> assertSame('bad', $tiles['uploads']['state']);
        $this -> assertSame('bad', $tiles['notifications']['state']);

        foreach (['federation', 'trending', 'backups', 'moderation'] as $key) {
            $this -> assertSame('warning', $tiles[$key]['state'], $key);
        }

        $fixture['deliveries']['undeliverable'] = 101;
        $fixture['backups']['time'] = null;
        $tiles = $this -> tiles($fixture);
        $this -> assertSame('bad', $tiles['federation']['state']);
        $this -> assertSame('bad', $tiles['backups']['state']);
    }

    public function testMemoryAndCapacityReadingsDescribeTheMachine(): void
    {
        $this -> assertSame(['available' => 2048, 'total' => 10240], ServerHealth::memory("MemTotal: 10 kB\nMemAvailable: 2 kB\nMemFree: 1 kB\n"));
        $this -> assertNull(ServerHealth::memory('MemFree: 1 kB'));
        $readings = array_column(ServerHealth::readings([
            'ip' => '127.0.0.1', 'load' => [5, 3, 1], 'cpus' => 4,
            'memory' => ['available' => 4, 'total' => 100],
            'disks' => ['root' => ['available' => 7, 'total' => 100], 'uploads' => ['available' => 20, 'total' => 100]],
            'database' => ['Threads_running' => 2, 'Threads_connected' => 3, 'Innodb_row_lock_current_waits' => 1, 'Uptime' => 3601],
            'longQueries' => 0,
        ]), null, 'id');
        $this -> assertSame('warning', $readings['load']['state']);
        $this -> assertSame('bad', $readings['memory']['state']);
        $this -> assertSame('warning', $readings['disk-root']['state']);
        $this -> assertSame('neutral', $readings['disk-uploads']['state']);
        $this -> assertSame('warning', $readings['database']['state']);
        $this -> assertSame('neutral', $readings['queries']['state']);
        $this -> assertTrue(str_contains($readings['database']['text'], '1:00:01'));
    }

    public function testServiceStatusDistinguishesStoppedFromUnreadable(): void
    {
        $states = ServiceStatus::parse("Id=glommer-upload-worker.service\nLoadState=loaded\nActiveState=active\n\nId=glommer-federation-worker.service\nLoadState=loaded\nActiveState=failed\n\nId=glommer-trending.timer\nLoadState=not-found\nActiveState=inactive\n");
        $this -> assertSame(true, $states['uploads']);
        $this -> assertSame(false, $states['federation']);
        $this -> assertNull($states['trending']);
        $this -> assertNull($states['backups']);
        $this -> assertSame(array_fill_keys(array_keys($states), null), ServiceStatus::parse(''));
    }

    public function testBackupStatusRequiresBothNonemptyArchiveFiles(): void
    {
        $root = sys_get_temp_dir() . '/glommer-dashboard-' . bin2hex(random_bytes(6));
        mkdir($root);
        $directories = ['2026-01-01_000000', '2026-01-02_000000', '2026-01-03_000000', 'unrelated'];

        try {
            $this -> assertSame(['readable' => true, 'time' => null], Backup::archiveStatus($root));
            foreach ($directories as $name) {
                mkdir($root . '/' . $name);
                file_put_contents($root . '/' . $name . '/database.sql.gz', 'fixture');
            }
            file_put_contents($root . '/' . $directories[0] . '/uploads.tar.gz', 'fixture');
            touch($root . '/' . $directories[0] . '/database.sql.gz', 1000);
            touch($root . '/' . $directories[0] . '/uploads.tar.gz', 1001);
            file_put_contents($root . '/' . $directories[2] . '/uploads.tar.gz', '');
            file_put_contents($root . '/unrelated/uploads.tar.gz', 'fixture');
            clearstatcache();
            $this -> assertSame(['readable' => true, 'time' => 1001], Backup::archiveStatus($root));
            file_put_contents($root . '/' . $directories[1] . '/uploads.tar.gz', 'fixture');
            touch($root . '/' . $directories[1] . '/database.sql.gz', 2000);
            touch($root . '/' . $directories[1] . '/uploads.tar.gz', 2001);
            clearstatcache();
            $this -> assertSame(['readable' => true, 'time' => 2001], Backup::archiveStatus($root));
        } finally {
            foreach ($directories as $name) {
                foreach (glob($root . '/' . $name . '/*') ?: [] as $file) unlink($file);
                rmdir($root . '/' . $name);
            }
            rmdir($root);
        }

        $this -> assertSame(['readable' => false, 'time' => null], Backup::archiveStatus($root));
    }

    public function testDashboardRendersLinkedTilesAndEscapesDiagnosticText(): void
    {
        $fixture = $this -> fixture();
        $fixture['websocket'] = ['ok' => false, 'message' => '<script>alert(1)</script>'];
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
        $element = (new AdminDashboard(['snapshot' => AdminStatus::snapshot(true, $fixture)])) -> toDOM();
        $document = HTMLObject::currentDocument();
        $document -> appendChild($element);
        $xpath = new \DOMXPath($document);
        $this -> assertSame(8, $xpath -> query('//a[@data-tile]') -> length);
        $this -> assertSame(7, $xpath -> query('//p[@data-reading]') -> length);
        $this -> assertSame(0, $xpath -> query('//script') -> length);
        $this -> assertTrue(str_contains($element -> textContent, '<script>alert(1)</script>'));
        $this -> assertSame(1, $xpath -> query('//*[@role="status"]') -> length);
    }
}
