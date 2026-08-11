<?php

declare(strict_types=1);

/**
 * German for what each kind of trending topic is called. See
 * src/locales/en/EntityType.php for the source and the shape each entry is
 * built to.
 */

return [
    'EntityType' => [
        'singular' => [
            'hashtag' => 'Hashtag',
            'person' => 'Person',
            'org' => 'Organisation',
            'gpe' => 'Ort',
            'loc' => 'Region',
            'fac' => 'Wahrzeichen',
            'product' => 'Produkt',
            'event' => 'Ereignis',
            'work_of_art' => 'Werk',
            'law' => 'Gesetz',
            'language' => 'Sprache',
            'norp' => 'Gruppe',
        ],
        'plural' => [
            'hashtag' => 'Hashtags',
            'person' => 'Personen',
            'org' => 'Organisationen',
            'gpe' => 'Orte',
            'loc' => 'Regionen',
            // Invariant: 'das Wahrzeichen' and 'die Wahrzeichen' are the same
            // word, the same way 'der Kuchen'/'die Kuchen' are.
            'fac' => 'Wahrzeichen',
            'product' => 'Produkte',
            'event' => 'Ereignisse',
            'work_of_art' => 'Werke',
            'law' => 'Gesetze',
            'language' => 'Sprachen',
            'norp' => 'Gruppen',
        ],
    ],
];
