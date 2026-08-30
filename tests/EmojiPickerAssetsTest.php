<?php

declare(strict_types=1);

class EmojiPickerAssetsTest extends TestCase
{
    public function testThePickerModuleGraphIsServedLocally(): void
    {
        $init = (string) file_get_contents(__DIR__ . '/../scripts/emoji-picker-init.js');

        $this -> assertTrue(str_contains($init, '/scripts/vendor/emoji-picker-element/database.js'));
        $this -> assertTrue(str_contains($init, '/scripts/vendor/emoji-picker-element/index.js'));
        $this -> assertFalse(str_contains($init, 'cdn.jsdelivr.net'));
    }

    public function testThePickerDataIsVendoredAndReadable(): void
    {
        $data = json_decode((string) file_get_contents(__DIR__ . '/../scripts/vendor/emoji-picker-element/data.json'), true);

        $this -> assertTrue(is_array($data));
        $this -> assertTrue(count($data) > 1000);
    }
}
