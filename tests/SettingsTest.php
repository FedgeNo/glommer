<?php

declare(strict_types=1);

class SettingsTest extends DatabaseTestCase
{
    public function testTheSettingsTableIsReadOncePerRequest(): void
    {
        DB::run('
INSERT INTO `Settings` (`name`, `value`)
    VALUES (?, ?), (?, ?)
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
', 'ssss', 'test-first', 'one', 'test-second', 'two');

        (new \ReflectionProperty(Settings::class, 'cache')) -> setValue(null, []);
        (new \ReflectionProperty(Settings::class, 'loaded')) -> setValue(null, false);
        $before = DB::queryCount();

        $this -> assertSame('one', Settings::get('test-first'));
        $this -> assertSame('two', Settings::get('test-second'));
        $this -> assertSame('fallback', Settings::get('test-missing', 'fallback'));
        $this -> assertSame(1, DB::queryCount() - $before);
    }
}
