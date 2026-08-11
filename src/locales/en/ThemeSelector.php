<?php

declare(strict_types=1);

/**
 * What the themes are called, in English.
 *
 * Keyed by the identifier the database stores, so renaming a theme in words
 * never touches what is saved against anybody's account.
 *
 * Most of these are colour names rather than descriptions, and a good few are
 * the proper names of published colour maps - Viridis, Mako, Cividis, YlGnBu,
 * Cubehelix and Ironbow are what those palettes are called in every language,
 * the way a font's name is. A translation should leave those as they are and
 * put its own words to the ones that describe something: matching the system,
 * the two plain palettes, and the ordinary colours.
 */

return [
    'ThemeSelector' => [
        'label' => 'Theme',
        'themes' => [
            'system' => 'Match System',
            'light' => 'Plain Light',
            'dark' => 'Plain Dark',
            'sepia' => 'Sepia',
            'midnight' => 'Midnight',
            'sunset' => 'Sunset',
            'rose' => 'Rose',
            'forest' => 'Forest',
            'ocean' => 'Ocean',
            'lavender' => 'Lavender',
            'gold' => 'Gold',
            'hacker' => 'Hacker',
            'ironbow' => 'Ironbow',
            'viridis' => 'Viridis',
            'mako' => 'Mako',
            'cividis' => 'Cividis',
            'ylgnbu' => 'YlGnBu',
            'cubehelix' => 'Cubehelix',
            'greyscale' => 'Greyscale',
        ],
    ],
];
