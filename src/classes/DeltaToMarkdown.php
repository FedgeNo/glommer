<?php

declare(strict_types=1);

/**
 * A delta written back out as the markdown that produces it.
 *
 * The half of markdown mode that makes switching safe: everything the editor
 * can do has a spelling in the dialect (see Markdown), so a delta written out
 * and read back in is the same delta. That is the property the mode selector
 * needs, since the delta is what gets stored - what a person typed may come
 * back spelled the site's way, because *x* and _x_ mean one thing and only
 * one of them can be written.
 *
 * Every marker character in somebody's actual words is escaped on the way
 * out, which is the part that decides whether the round trip holds at all.
 */
class DeltaToMarkdown
{
    /** The marker each inline attribute is written with, innermost last. */
    private const MARKERS = [
        'underline' => '__',
        'bold' => '**',
        'italic' => '*',
        'strike' => '~~',
    ];

    /** @param array[] $ops */
    public static function convert(array $ops): string
    {
        return self::spell(self::blocks($ops));
    }

    /**
     * The delta read as the blocks it is: a run of written content and the
     * kind carried by the newline that closed it.
     *
     * @param array[] $ops
     * @return array<int, array{text: string, attributes: array}>
     */
    private static function blocks(array $ops): array
    {
        $blocks = [];
        $line = '';
        $literal = '';

        foreach ($ops as $op) {
            $insert = $op['insert'] ?? '';

            if (is_array($insert)) {
                if (isset($insert['formula'])) {
                    $line .= '$' . $insert['formula'] . '$';
                    $literal .= $insert['formula'];
                }

                continue;
            }

            $attributes = $op['attributes'] ?? [];

            foreach (explode(chr(10), (string) $insert) as $index => $piece) {
                if ($index > 0) {
                    // A code block holds its content literally, so what goes
                    // in the fence is the words themselves, unmarked.
                    $blocks[] = [
                        'text' => empty($attributes['code-block']) ? $line : $literal,
                        'attributes' => $attributes,
                    ];
                    $line = '';
                    $literal = '';
                }

                $line .= self::inline($piece, $attributes);
                $literal .= $piece;
            }
        }

        if ($line !== '') {
            $blocks[] = ['text' => $line, 'attributes' => []];
        }

        return $blocks;
    }

    /**
     * The blocks written out. Consecutive code blocks become one fence rather
     * than one apiece, since a fence is what says "this run is code".
     *
     * @param array<int, array{text: string, attributes: array}> $blocks
     */
    private static function spell(array $blocks): string
    {
        $lines = [];
        $fenced = false;

        foreach ($blocks as $block) {
            $is_code = !empty($block['attributes']['code-block']);

            if ($is_code !== $fenced) {
                $lines[] = '```';
                $fenced = $is_code;
            }

            $lines[] = $is_code ? $block['text'] : self::openBlock($block['attributes']) . $block['text'];
        }

        if ($fenced) {
            $lines[] = '```';
        }

        return implode(chr(10), $lines);
    }

    /** What a line starts with, given what its closing newline carried. */
    private static function openBlock(array $attributes): string
    {
        if (isset($attributes['header'])) {
            return str_repeat('#', (int) $attributes['header']) . ' ';
        }

        if (($attributes['list'] ?? null) === 'ordered') {
            return '1. ';
        }

        if (($attributes['list'] ?? null) === 'bullet') {
            return '- ';
        }

        if (!empty($attributes['blockquote'])) {
            return '> ';
        }

        return '';
    }

    /**
     * One run, wrapped in the markers its attributes call for.
     *
     * Code is written last and its contents are not escaped: a backtick span
     * holds what is inside it literally, so a backslash there would be a
     * backslash somebody has to read.
     */
    private static function inline(string $text, array $attributes): string
    {
        if ($text === '') {
            return '';
        }

        if (!empty($attributes['code'])) {
            return '`' . $text . '`';
        }

        $written = self::escape($text);

        foreach (self::MARKERS as $attribute => $marker) {
            if (!empty($attributes[$attribute])) {
                $written = $marker . $written . $marker;
            }
        }

        if (isset($attributes['link']) && is_string($attributes['link'])) {
            $written = '[' . $written . '](' . $attributes['link'] . ')';
        }

        return $written;
    }

    /**
     * Every character that would otherwise be read as a marker, made ordinary.
     * Without this a post that mentions *asterisks* comes back italic.
     */
    private static function escape(string $text): string
    {
        $escaped = '';

        for ($index = 0; $index < strlen($text); $index++) {
            $character = $text[$index];

            if (str_contains(Markdown::ESCAPABLE, $character)) {
                $escaped .= '\\';
            }

            $escaped .= $character;
        }

        return $escaped;
    }
}
