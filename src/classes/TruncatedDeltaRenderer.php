<?php

declare(strict_types=1);

/**
 * A DeltaRenderer that first truncates the ops to a visible-character budget
 * (for the short rich previews on feed posts), then renders them, appending a
 * "See More" link when it actually cut something. Delta ops are linear,
 * formula embeds are atomic single ops, and each text run is one contiguous
 * string - so the only care needed is not slicing through a typed math
 * delimiter pair ($...$, $$...$$, \[...\], \(...\)), which would hand KaTeX
 * half a formula.
 *
 * Truncation is server-only: the truncated ops are what both this server
 * render and the feed payload carry, so the client never re-truncates.
 */
class TruncatedDeltaRenderer extends DeltaRenderer
{
    public const DEFAULT_MAX_LENGTH = 500;

    // A formula embed counts as this many characters toward the budget.
    private const FORMULA_WEIGHT = 1;

    // Opener => closer for the math delimiter forms render_math() recognises.
    private const MATH_DELIMITERS = [
        '$$' => '$$',
        '\\[' => '\\]',
        '\\(' => '\\)',
        '$' => '$',
    ];

    private bool $truncated;

    /** @var array[] the ops after truncation - what the feed payload also carries */
    private array $truncatedOps;

    public function __construct(array $ops = [], private readonly ?string $seeMoreURL = null, int $max_length = self::DEFAULT_MAX_LENGTH)
    {
        [$this -> truncatedOps, $this -> truncated] = self::truncate($ops, $max_length);

        parent::__construct($this -> truncatedOps);
    }

    /** @return array[] */
    public function ops(): array
    {
        return $this -> truncatedOps;
    }

    public function toDOM(): \DOMElement
    {
        $root = parent::toDOM();

        if ($this -> truncated && $this -> seeMoreURL !== null) {
            $root -> appendChild((new SeeMore($this -> seeMoreURL)) -> toDOM());
        }

        return $root;
    }

    public function wasTruncated(): bool
    {
        return $this -> truncated;
    }

    /**
     * @param array[] $ops
     * @return array{0: array[], 1: bool} [truncated ops, whether anything was cut]
     */
    public static function truncate(array $ops, int $max_chars): array
    {
        $result = [];
        $budget = $max_chars;

        foreach ($ops as $op) {
            $insert = $op['insert'] ?? null;

            if (is_string($insert)) {
                $length = mb_strlen($insert);

                if ($length <= $budget) {
                    $result[] = $op;
                    $budget -= $length;

                    continue;
                }

                $cut = self::cutText($insert, $budget);

                if ($cut !== '') {
                    $op['insert'] = $cut . '…';
                    $result[] = $op;
                }

                return [$result, true];
            }

            if (is_array($insert) && isset($insert['formula'])) {
                if ($budget < self::FORMULA_WEIGHT) {
                    return [$result, true];
                }

                $result[] = $op;
                $budget -= self::FORMULA_WEIGHT;
            }
        }

        return [$result, false];
    }

    /**
     * Cuts $text to at most $budget visible chars, backed off to a clean word
     * boundary and, crucially, never ending inside an unclosed math delimiter
     * (which would leave KaTeX half a formula). Returns the kept prefix.
     */
    private static function cutText(string $text, int $budget): string
    {
        if ($budget <= 0) {
            return '';
        }

        $cut = mb_substr($text, 0, $budget);
        $cut = self::backOffFromOpenMath($cut);

        // Prefer the last whitespace so a word isn't sliced in half (but only
        // if that leaves a reasonable amount - a single long token stays whole).
        $last_space = mb_strrpos($cut, ' ');

        if ($last_space !== false && $last_space > 0) {
            $word_trimmed = mb_substr($cut, 0, $last_space);

            // Only take the word-boundary trim when it doesn't slice into a
            // formula that was fully closed within $cut. If the last space
            // falls inside a complete formula, trimming there re-opens its
            // delimiter - and backing that off would throw away a whole
            // formula that fit the budget. In that case keep $cut as is: it
            // already ends cleanly on the closed formula (the word trim only
            // exists to avoid ending mid-word in plain text, never to discard
            // a formula that fit).
            if (self::backOffFromOpenMath($word_trimmed) === $word_trimmed) {
                $cut = $word_trimmed;
            }
        }

        return rtrim($cut);
    }

    /**
     * If $cut ends inside an opened-but-unclosed math delimiter, trims back to
     * just before that opener so the preview never contains half a formula.
     */
    private static function backOffFromOpenMath(string $cut): string
    {
        // Find the earliest unmatched opener (scanning longest delimiters first
        // so "$$" wins over "$"). An opener is "open" if its closer doesn't
        // appear after it within $cut.
        $earliest_open = null;

        foreach (self::MATH_DELIMITERS as $opener => $closer) {
            $open_pos = mb_strpos($cut, $opener);

            while ($open_pos !== false) {
                $after = $open_pos + mb_strlen($opener);
                $close_pos = mb_strpos($cut, $closer, $after);

                if ($close_pos === false) {
                    // Unclosed within the cut - a candidate cut point.
                    if ($earliest_open === null || $open_pos < $earliest_open) {
                        $earliest_open = $open_pos;
                    }

                    break;
                }

                // Skip past this matched pair and keep looking in the same run.
                $open_pos = mb_strpos($cut, $opener, $close_pos + mb_strlen($closer));
            }
        }

        return $earliest_open !== null ? mb_substr($cut, 0, $earliest_open) : $cut;
    }
}
