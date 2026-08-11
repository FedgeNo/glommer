<?php

declare(strict_types=1);

/**
 * German for what the themes are called. See
 * src/locales/en/ThemeSelector.php for the source and the shape each entry
 * is built to.
 */

return [
    'ThemeSelector' => [
        'label' => 'Design',
        'themes' => [
            'system' => 'Systemeinstellung',
            'light' => 'Einfach Hell',
            'dark' => 'Einfach Dunkel',
            'sepia' => 'Sepia',
            'midnight' => 'Mitternacht',
            'sunset' => 'Sonnenuntergang',
            'rose' => 'Rosé',
            'forest' => 'Wald',
            'ocean' => 'Ozean',
            'lavender' => 'Lavendel',
            'gold' => 'Gold',
            'hacker' => 'Hacker',
            // Left as it is like the five below rather than translated:
            // Ironbow is itself the name of a published thermal-camera
            // palette, the same kind of proper noun as Viridis or Cubehelix
            // - ThemeSelector.php's own comment calls it one of "the
            // original palettes" alongside Sunset. The task's callout named
            // only five explicitly; this is a deliberate extension of that
            // same principle, not an oversight - flagged here for review.
            'ironbow' => 'Ironbow',
            'viridis' => 'Viridis',
            'mako' => 'Mako',
            'cividis' => 'Cividis',
            'ylgnbu' => 'YlGnBu',
            'cubehelix' => 'Cubehelix',
            'greyscale' => 'Graustufen',
        ],
    ],
];
