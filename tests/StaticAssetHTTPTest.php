<?php

declare(strict_types=1);

/** Proves Apache serves the application's public files at their browser URLs. */
class StaticAssetHTTPTest extends TestCase
{
    public function testPublicFilesAreServedWithTheirExpectedBytesAndTypes(): void
    {
        $assets = [
            '/scripts/main.js' => ['scripts/main.js', ['application/javascript', 'text/javascript']],
            '/scripts/Runtime.js' => ['scripts/Runtime.js', ['application/javascript', 'text/javascript']],
            '/scripts/HTMLObjects.js' => ['scripts/HTMLObjects.js', ['application/javascript', 'text/javascript']],
            '/scripts/Controllers.js' => ['scripts/Controllers.js', ['application/javascript', 'text/javascript']],
            '/scripts/emoji-picker-init.js' => ['scripts/emoji-picker-init.js', ['application/javascript', 'text/javascript']],
            '/scripts/database.js' => ['scripts/database.js', ['application/javascript', 'text/javascript']],
            '/scripts/index.js' => ['scripts/index.js', ['application/javascript', 'text/javascript']],
            '/scripts/picker.js' => ['scripts/picker.js', ['application/javascript', 'text/javascript']],
            '/scripts/data.json' => ['scripts/data.json', ['application/json']],
            '/styles/themes.css' => ['styles/themes.css', ['text/css']],
            '/styles/base.css' => ['styles/base.css', ['text/css']],
            '/styles/utilities.css' => ['styles/utilities.css', ['text/css']],
            '/styles/components.css' => ['styles/components.css', ['text/css']],
            '/styles/layout.css' => ['styles/layout.css', ['text/css']],
            '/styles/mobile.css' => ['styles/mobile.css', ['text/css']],
            '/locales/en.json' => ['locales/en.json', ['application/json']],
            '/sw.js' => ['sw.js', ['application/javascript', 'text/javascript']],
            '/favicon.ico' => ['favicon.ico', ['image/vnd.microsoft.icon', 'image/x-icon']],
            '/robots.txt' => ['robots.txt', ['text/plain']],
        ];

        foreach ($assets as $url_path => [$file_path, $types]) {
            $response = $this -> request($url_path);
            $this -> assertPublicResponse($url_path, $response, $types);
            $this -> assertSame(
                (string) file_get_contents(__DIR__ . '/../' . $file_path),
                $response['body'],
                $url_path . ' did not return the tracked file'
            );
        }
    }

    public function testGeneratedBrowserAssetsAreServedWithTheirExpectedTypes(): void
    {
        $manifest = $this -> request('/manifest.webmanifest');
        $this -> assertPublicResponse('/manifest.webmanifest', $manifest, ['application/manifest+json']);
        $manifest_data = json_decode($manifest['body'], true);
        $this -> assertTrue(is_array($manifest_data), 'The manifest is not valid JSON');
        $this -> assertTrue(isset($manifest_data['icons'][0]['src']), 'The manifest has no icon URL');

        $shortcodes = $this -> request('/emoji-shortcodes.js');
        $this -> assertPublicResponse('/emoji-shortcodes.js', $shortcodes, ['application/javascript', 'text/javascript']);
        $this -> assertTrue(
            str_contains($shortcodes['body'], 'export const EMOJI_SHORTCODES'),
            'The emoji shortcode response is not the expected JavaScript module'
        );
    }

    public function testRepositoryFilesOutsideThePublicAssetListStayPrivate(): void
    {
        foreach (['/README.md', '/package-lock.json', '/src/classes/Page.php', '/tests/TestCase.php', '/claude/README.md'] as $url_path) {
            $response = $this -> request($url_path);

            $this -> assertTrue(
                in_array($response['status'], [403, 404], true),
                $url_path . ' should not be public; it answered ' . $response['status']
            );
            $this -> assertFalse(
                str_contains($response['body'], '<?php') || str_contains($response['body'], '# AGENTS.md'),
                $url_path . ' exposed repository contents'
            );
        }
    }

    /** @param string[] $expected_types */
    private function assertPublicResponse(string $url_path, array $response, array $expected_types): void
    {
        $detail = $url_path . ' answered ' . $response['status'] . ' as ' . ($response['contentType'] ?: '(no content type)')
            . '; body begins ' . $this -> bodyPreview($response['body']);

        $this -> assertSame(200, $response['status'], $detail);
        $this -> assertTrue(in_array($response['contentType'], $expected_types, true), $detail);
        $this -> assertFalse(isset($response['headers']['location']), $url_path . ' redirected unexpectedly');
        $this -> assertSame(
            'nosniff',
            strtolower($response['headers']['x-content-type-options'][0] ?? ''),
            $url_path . ' was served without X-Content-Type-Options: nosniff'
        );
        $this -> assertFalse(
            str_starts_with(ltrim($response['body']), '<!DOCTYPE html') || str_starts_with(ltrim($response['body']), '<html'),
            $url_path . ' returned an HTML document'
        );
    }

    /** @return array{status: int, contentType: string, headers: array<string, string[]>, body: string} */
    private function request(string $path): array
    {
        $site_url = rtrim((string) Config::get('siteURL'), '/');

        if ($site_url === '') {
            throw new TestSkippedException('siteURL is not configured, so the installed site cannot be checked');
        }

        $headers = [];
        $curl = curl_init($site_url . $path);
        $this -> assertTrue($curl !== false, 'curl_init failed for ' . $path);

        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['User-Agent: StaticAssetHTTPTest'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADERFUNCTION => static function ($curl_handle, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))][] = trim($value);
                }

                return strlen($line);
            },
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $content_type = strtolower(trim((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE)));
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new TestSkippedException('The installed site is not answering at ' . $site_url . ' - ' . $error);
        }

        $content_type = trim(explode(';', $content_type, 2)[0]);

        return [
            'status' => $status,
            'contentType' => $content_type,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    private function bodyPreview(string $body): string
    {
        $preview = preg_replace('/[\x00-\x1F\x7F]+/', ' ', substr($body, 0, 100)) ?? '';

        return json_encode($preview, JSON_UNESCAPED_SLASHES) ?: '(unprintable)';
    }
}
