<?php

declare(strict_types=1);

/**
 * The markdown this site writes and reads, turned into HTML for HTMLToDelta
 * to finish. Markdown mode and inbound federation therefore share the second
 * half of the journey, and a post typed as markdown ends up the same delta a
 * post typed in the rich editor does.
 *
 * A dialect, not CommonMark, and deliberately so on two points:
 *
 * - __text__ is underline. It is the one thing the editor can do that
 *   markdown has no spelling for, and CommonMark spends the marker on strong,
 *   which ** already says here.
 * - One line is one block. A delta ends a block at every newline and the
 *   editor makes one every time somebody presses Enter, so joining wrapped
 *   lines into a paragraph the way CommonMark does would merge two blocks
 *   into one and stop the round trip coming back the way it went.
 *
 * Everything the editor can produce has a spelling here and every spelling
 * maps back, so switching modes is lossless in the direction that matters:
 * the delta that goes out comes back identical. See DeltaToMarkdown.
 */
class Markdown
{
    /** Characters a backslash may make literal, and that are escaped on the way out. */
    public const ESCAPABLE = '\\`*_~[]()#>$';

    public static function toHTML(string $markdown): string
    {
        $html = '';
        $lines = explode(chr(10), str_replace(chr(13), '', $markdown));
        $fence = null;
        $fenced = [];

        foreach ($lines as $line) {
            if (str_starts_with(ltrim($line), '```')) {
                if ($fence === null) {
                    $fence = true;
                    $fenced = [];
                } else {
                    $html .= '<pre><code>' . htmlspecialchars(implode(chr(10), $fenced), ENT_QUOTES) . '</code></pre>';
                    $fence = null;
                }

                continue;
            }

            if ($fence !== null) {
                $fenced[] = $line;

                continue;
            }

            $html .= self::blockHTML($line);
        }

        // A fence nobody closed still holds writing, and losing it would lose
        // the rest of the post with it.
        if ($fence !== null && $fenced !== []) {
            $html .= '<pre><code>' . htmlspecialchars(implode(chr(10), $fenced), ENT_QUOTES) . '</code></pre>';
        }

        return $html;
    }

    /** One line, as the block it spells. */
    private static function blockHTML(string $line): string
    {
        $trimmed = ltrim($line);

        if (trim($trimmed) === '') {
            return '';
        }

        if (str_starts_with($trimmed, '>')) {
            return '<blockquote>' . self::inlineHTML(ltrim(substr($trimmed, 1))) . '</blockquote>';
        }

        $heading = 0;

        while ($heading < 6 && substr($trimmed, $heading, 1) === '#') {
            $heading++;
        }

        if ($heading > 0 && substr($trimmed, $heading, 1) === ' ') {
            // Three is the smallest this site renders, so smaller ones become it.
            $level = min($heading, 3);

            return '<h' . $level . '>' . self::inlineHTML(ltrim(substr($trimmed, $heading))) . '</h' . $level . '>';
        }

        foreach (['- ', '* ', '+ '] as $bullet) {
            if (str_starts_with($trimmed, $bullet)) {
                return '<ul><li>' . self::inlineHTML(substr($trimmed, 2)) . '</li></ul>';
            }
        }

        $digits = 0;

        while (ctype_digit(substr($trimmed, $digits, 1))) {
            $digits++;
        }

        if ($digits > 0 && substr($trimmed, $digits, 2) === '. ') {
            return '<ol><li>' . self::inlineHTML(substr($trimmed, $digits + 2)) . '</li></ol>';
        }

        return '<p>' . self::inlineHTML($trimmed) . '</p>';
    }

    /**
     * A line's markers, read left to right. Hand-scanned rather than matched:
     * a backslash makes the next marker ordinary, and that has to be decided
     * character by character or an escaped marker closes a run it should have
     * been text inside.
     */
    private static function inlineHTML(string $text): string
    {
        $html = '';
        $length = strlen($text);

        for ($index = 0; $index < $length; $index++) {
            $character = $text[$index];
            $next_two = substr($text, $index, 2);

            if ($character === '\\' && $index + 1 < $length && str_contains(self::ESCAPABLE, $text[$index + 1])) {
                $html .= htmlspecialchars($text[$index + 1], ENT_QUOTES);
                $index++;

                continue;
            }

            // Bold and italic share a character, so both at once spells ***.
            // Read whole rather than as ** followed by a stray *.
            if (substr($text, $index, 3) === '***') {
                $close = self::findClosing($text, $index + 3, '***');

                if ($close !== null) {
                    $html .= '<strong><em>' . self::inlineHTML(substr($text, $index + 3, $close - $index - 3)) . '</em></strong>';
                    $index = $close + 2;

                    continue;
                }
            }

            foreach (['**' => 'strong', '__' => 'u', '~~' => 's'] as $marker => $tag) {
                if ($next_two === $marker) {
                    $close = self::findClosing($text, $index + 2, $marker);

                    if ($close !== null) {
                        $html .= '<' . $tag . '>' . self::inlineHTML(substr($text, $index + 2, $close - $index - 2)) . '</' . $tag . '>';
                        $index = $close + 1;

                        continue 2;
                    }
                }
            }

            if ($character === '*' || $character === '_') {
                $close = self::findClosing($text, $index + 1, $character);

                if ($close !== null) {
                    $html .= '<em>' . self::inlineHTML(substr($text, $index + 1, $close - $index - 1)) . '</em>';
                    $index = $close;

                    continue;
                }
            }

            // Code and formulas hold their contents literally - a marker
            // inside either is part of what somebody wrote.
            foreach (['`' => 'code', '$' => 'formula'] as $marker => $kind) {
                if ($character !== $marker) {
                    continue;
                }

                $close = self::findClosing($text, $index + 1, $marker);

                if ($close === null) {
                    continue;
                }

                $inner = substr($text, $index + 1, $close - $index - 1);
                $escaped = htmlspecialchars($inner, ENT_QUOTES);

                $html .= $kind === 'code'
                    ? '<code>' . $escaped . '</code>'
                    : '<span class="PostFormula" data-formula="' . $escaped . '">' . $escaped . '</span>';

                $index = $close;

                continue 2;
            }

            if ($character === '[') {
                $link = self::readLink($text, $index);

                if ($link !== null) {
                    $html .= '<a href="' . htmlspecialchars($link['url'], ENT_QUOTES) . '">' . self::inlineHTML($link['text']) . '</a>';
                    $index = $link['end'];

                    continue;
                }
            }

            $html .= htmlspecialchars($character, ENT_QUOTES);
        }

        return $html;
    }

    /** Where the closing marker starts, or null if it never comes. */
    private static function findClosing(string $text, int $from, string $marker): ?int
    {
        $length = strlen($text);
        $width = strlen($marker);

        for ($index = $from; $index + $width <= $length; $index++) {
            if ($text[$index] === '\\') {
                $index++;

                continue;
            }

            if (substr($text, $index, $width) === $marker) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The [text](url) starting at $start.
     *
     * @return array{text: string, url: string, end: int}|null
     */
    private static function readLink(string $text, int $start): ?array
    {
        $close = self::findClosing($text, $start + 1, ']');

        if ($close === null || substr($text, $close + 1, 1) !== '(') {
            return null;
        }

        $url_end = self::findClosing($text, $close + 2, ')');

        if ($url_end === null) {
            return null;
        }

        return [
            'text' => substr($text, $start + 1, $close - $start - 1),
            'url' => substr($text, $close + 2, $url_end - $close - 2),
            'end' => $url_end,
        ];
    }
}
