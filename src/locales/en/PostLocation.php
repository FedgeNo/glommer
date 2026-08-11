<?php

declare(strict_types=1);

/** The place line under a post's timestamp. */

return [
    'PostLocationLink' => [
        'title' => 'Show this place on the map',
        // What goes between the two numbers when there is no name to use
        // instead. Its own entry because a language that writes decimals with
        // a comma cannot also separate the pair with one.
        'between' => ', ',
    ],
];
