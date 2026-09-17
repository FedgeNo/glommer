<?php

declare(strict_types=1);

class TranslatorFallbackTest extends TestCase
{
    private function translator(): Translator
    {
        return new class extends Translator {
            public static array $calls = [];
            public static array $answers = [];

            protected static function byGoogle(string $text, string $source, string $target): ?string
            {
                self::$calls[] = ['google', $text, $source, $target];

                return self::$answers['google'] ?? null;
            }

            protected static function bySmall100(string $text, string $source, string $target): ?string
            {
                self::$calls[] = ['small100', $text, $source, $target];

                return self::$answers['small100'] ?? null;
            }

            protected static function byArgos(string $text, string $source, string $target): ?string
            {
                self::$calls[] = ['argos', $text, $source, $target];

                return self::$answers['argos'] ?? null;
            }

            protected static function byModel(string $text, string $source, string $target): ?string
            {
                self::$calls[] = ['model', $text, $source, $target];

                return self::$answers['model'] ?? null;
            }
        };
    }

    public function testGoogleIsFirstAndEachFailureFallsThroughInOrder(): void
    {
        $translator = $this -> translator();
        $providers = ['google', 'small100', 'argos', 'model'];

        foreach ($providers as $index => $provider) {
            $translator::$calls = [];
            $translator::$answers = [$provider => 'Hello'];
            $this -> assertSame('Hello', $translator::translate('Hallo', 'en-GB', 'de-DE'));
            $this -> assertSame(array_slice($providers, 0, $index + 1), array_column($translator::$calls, 0));

            foreach ($translator::$calls as $call) {
                $this -> assertSame(['Hallo', 'de', 'en'], array_slice($call, 1));
            }
        }

        $translator::$calls = [];
        $translator::$answers = [];
        $this -> assertNull($translator::translate('Hallo', 'en', 'de'));
        $this -> assertSame($providers, array_column($translator::$calls, 0));
    }

    public function testUntranslatableInputNeverReachesAnyProvider(): void
    {
        $translator = $this -> translator();
        $translator::$calls = [];

        foreach ([['', 'en', 'de'], ['Hallo', 'de', 'de'], ['Hallo', 'en', null]] as $args) {
            $this -> assertNull($translator::translate(...$args));
        }

        $this -> assertSame([], $translator::$calls);
    }

    public function testGoogleDoesNotRequireLocalLanguagePackages(): void
    {
        $this -> assertTrue(Translator::canTranslate());
        $this -> assertNull(Translator::refusalFor('Bonjour', 'en', 'fr'));
    }
}
