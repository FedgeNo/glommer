<?php

declare(strict_types=1);

/** The place line under a post's timestamp. */

return [
    'PostLocationLink' => [
        'title' => 'Show this place on the map',
        // What goes between the two numbers when there is no name to use
        // instead. Its own entry because how a pair of figures is joined is a
        // thing languages differ about - not because of the decimal mark,
        // which PostLocationLink::coordinates() always renders as a point:
        // number_format() is called without separator arguments, so it does
        // not follow the locale.
        'between' => ', ',
    ],
];
