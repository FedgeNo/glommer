<?php

declare(strict_types=1);

class ThemeSelector extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'Card d-flex flex-column gap-2 ThemeSelector';

    protected const OPTIONS = [
        'system' => 'Match System',
        'light' => 'Light',
        'dark' => 'Dark',
        'sepia' => 'Sepia',
        'midnight' => 'Midnight',
        'sunset' => 'Sunset',
        'rose' => 'Rose',
        'forest' => 'Forest',
        'ocean' => 'Ocean',
        'lavender' => 'Lavender',
        'gold' => 'Gold',
        'hacker' => 'Hacker',
    ];

    public function toDOM(): \DOMElement
    {
        // Always the current user's preference - fetched here rather than
        // handed in, the same way anything needing the DB connection calls
        // DB::connection() itself.
        $selected = Auth::user() ?-> theme ?? 'system';

        $label = new Label();
        $label -> for = 'theme';
        $label -> contents[] = 'Theme';
        $this -> contents[] = $label;

        $select = new Select();
        $select -> name = 'theme';
        $select -> id = 'theme';
        $select -> class = 'ThemeSelect';

        foreach (self::OPTIONS as $value => $text) {
            $option = new SelectOption();
            $option -> value = $value;
            $option -> contents[] = $text;

            if ($value === $selected) {
                $option -> attributes['selected'] = 'selected';
            }

            $select -> addContent($option);
        }

        $this -> addContent($select);

        return parent::toDOM();
    }
}
