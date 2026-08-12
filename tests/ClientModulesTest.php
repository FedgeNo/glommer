<?php

declare(strict_types=1);

/**
 * The modules the browser reads say exactly what the server says.
 *
 * locales/*.js and emoji-shortcodes.js are files in the repository, served by
 * the web server without PHP ever running - which is what keeps a page's forty
 * modules from queueing behind one another on a session lock. The cost is that
 * they are a second copy: edit a locale file and the browser goes on serving
 * the old words, silently, until somebody writes the module out again.
 *
 * So the copy is checked here rather than left to be noticed. A failure names
 * the language and the key that drifted, since "the files differ" about six
 * hundred strings is not a thing anybody can act on.
 */
class ClientModulesTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    /**
     * Every leaf in a table, keyed by the path that reaches it.
     *
     * @param array<string, mixed> $table
     * @return array<string, string>
     */
    private static function leaves(array $table, string $prefix = ''): array
    {
        $found = [];

        foreach ($table as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $found += self::leaves($value, $path);
            } elseif (is_string($value)) {
                $found[$path] = $value;
            }
        }

        return $found;
    }

    /** The table a written module carries, or null where there is none to read. */
    private static function written(string $path): ?array
    {
        $source = @file_get_contents(self::ROOT . '/' . $path);
        $start = $source === false ? false : strpos($source, '{');
        $end = $source === false ? false : strrpos($source, '}');

        if ($start === false || $end === false) {
            return null;
        }

        $decoded = json_decode(substr((string) $source, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Every string in every module, against the server's own, both ways.
     *
     * One pass over all of them rather than a test per language: they are one
     * fact - the browser is reading what the server reads - and a language
     * added later would otherwise need somebody to remember to add a test with
     * it. Strings::available() is the list, so a new language is covered by
     * existing.
     */
    public function testEveryStringTheBrowserReadsIsTheStringTheServerReads(): void
    {
        foreach (Strings::available() as $locale) {
            $module = 'locales/' . $locale . '.js';
            $written = self::written($module);

            $this -> assertNotNull($written, $module . ' is missing or is not a readable module');

            if ($written === null) {
                continue;
            }

            // Through the same encoding that wrote the file, so a difference
            // here is a difference in the words rather than in how they were
            // spelled into JSON.
            $encoded = json_encode(Strings::all($locale), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $expected = self::leaves((array) json_decode((string) $encoded, true));
            $actual = self::leaves($written);

            foreach ($expected as $path => $text) {
                $this -> assertSame(
                    $text,
                    $actual[$path] ?? null,
                    $module . ' does not say what the locale files say for ' . $path
                );
            }

            foreach (array_keys($actual) as $path) {
                $this -> assertTrue(
                    isset($expected[$path]),
                    $module . ' still carries ' . $path . ', which the locale files no longer say'
                );
            }
        }

        // The same fact about the other written module: what the browser
        // expands a shortcode with is what the server expands it with.
        $this -> assertSame(
            EmojiShortcodeMap::javaScriptModule(),
            (string) @file_get_contents(self::ROOT . '/emoji-shortcodes.js'),
            'emoji-shortcodes.js no longer matches EmojiShortcodeMap'
        );
    }
}
