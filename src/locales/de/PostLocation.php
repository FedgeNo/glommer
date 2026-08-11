<?php

declare(strict_types=1);

/**
 * German for the place line under a post's timestamp. See
 * src/locales/en/PostLocation.php for the source and the shape each entry is
 * built to.
 */

return [
    'PostLocationLink' => [
        'title' => 'Diesen Ort auf der Karte zeigen',
        // Safe to keep as a comma: PostLocationLink::coordinates() calls
        // number_format() with only the decimals argument, which always uses
        // '.' as the decimal point regardless of locale - the pair a German
        // reader sees never has a decimal comma of its own to collide with.
        'between' => ', ',
    ],
];
