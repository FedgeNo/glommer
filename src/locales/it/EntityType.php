<?php

declare(strict_types=1);

/**
 * Italian for what each kind of trending topic is called. See
 * src/locales/en/EntityType.php for what this fragment covers. Keyed by the
 * raw label the extractor produces - only the value is translated, never the
 * key.
 */

return [
    'EntityType' => [
        'singular' => [
            'hashtag' => 'Hashtag',
            'person' => 'Persona',
            'org' => 'Organizzazione',
            'gpe' => 'Luogo',
            'loc' => 'Regione',
            'fac' => 'Punto di riferimento',
            'product' => 'Prodotto',
            'event' => 'Evento',
            'work_of_art' => 'Opera',
            'law' => 'Legge',
            'language' => 'Lingua',
            'norp' => 'Gruppo',
        ],
        'plural' => [
            // "Hashtag" repeats itself: Italian does not add -s to a loanword
            // to pluralize it ("gli hashtag", never "gli hashtags"), so the
            // singular and plural forms are genuinely the same word here.
            'hashtag' => 'Hashtag',
            'person' => 'Persone',
            'org' => 'Organizzazioni',
            'gpe' => 'Luoghi',
            'loc' => 'Regioni',
            'fac' => 'Punti di riferimento',
            'product' => 'Prodotti',
            'event' => 'Eventi',
            'work_of_art' => 'Opere',
            'law' => 'Leggi',
            'language' => 'Lingue',
            'norp' => 'Gruppi',
        ],
    ],
];
