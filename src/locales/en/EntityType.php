<?php

declare(strict_types=1);

/**
 * What each kind of trending topic is called, in English.
 *
 * Keyed by the raw label the extractor produces, which stays in the URL and the
 * table and is never translated - only what a reader is shown is.
 *
 * The two lists are separate rather than one counted phrasing: nothing here is
 * counted. A heading says "People" because the page is about people, not
 * because there are several, so a language picks its plural by meaning here and
 * not by a number the code could hand it.
 */

return [
    'EntityType' => [
        'singular' => [
            'hashtag' => 'Hashtag',
            'person' => 'Person',
            'org' => 'Organization',
            'gpe' => 'Place',
            'loc' => 'Region',
            'fac' => 'Landmark',
            'product' => 'Product',
            'event' => 'Event',
            'work_of_art' => 'Work',
            'law' => 'Law',
            'language' => 'Language',
            'norp' => 'Group',
        ],
        'plural' => [
            'hashtag' => 'Hashtags',
            'person' => 'People',
            'org' => 'Organizations',
            'gpe' => 'Places',
            'loc' => 'Regions',
            'fac' => 'Landmarks',
            'product' => 'Products',
            'event' => 'Events',
            'work_of_art' => 'Works',
            'law' => 'Laws',
            'language' => 'Languages',
            'norp' => 'Groups',
        ],
    ],
];
