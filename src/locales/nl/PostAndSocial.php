<?php

declare(strict_types=1);

/**
 * Dutch for the composer, poll and feed-context classes. See
 * src/locales/en/PostAndSocial.php for the source and the shape each entry is
 * built to.
 */

return [
    'MoreLocationsLink' => [
        'moreLocations' => ['before' => 'Bekijk ', 'link' => 'meer locaties', 'after' => ''],
    ],

    'NearbyLocationPrompt' => [
        'heading' => 'Posts bij jou in de buurt',
        'description' => 'Dit toont de posts die het dichtst bij een punt liggen - waar er ook activiteit is, hoe ver weg dan ook. Deel je locatie om te beginnen vanaf waar je bent, of kies in plaats daarvan een plek op de kaart.',
        'useMyLocation' => 'Mijn locatie gebruiken',
        'pickOnMap' => 'Kiezen op de kaart',
        'searchPlaceholder' => 'Of typ een plaatsnaam…',
        'searchLabel' => 'Zoek naar een plaats',
        'locating' => 'Locatie bepalen…',
        'noGeolocation' => 'Je browser kan geen locatie delen.',
        'locationError' => 'Kon je locatie niet bepalen. Controleer de locatietoestemming van je browser.',
    ],

    'PollDeadline' => [
        'final' => 'Eindresultaat',
        'closes' => ['before' => 'Sluit ', 'after' => ''],
        'days' => ['one' => 'over {count} dag', 'other' => 'over {count} dagen'],
        'hours' => ['one' => 'over {count} uur', 'other' => 'over {count} uur'],
        'minutes' => ['one' => 'over {count} minuut', 'other' => 'over {count} minuten'],
        'underMinute' => 'over minder dan een minuut',
    ],

    'PollTally' => [
        'voters' => ['one' => '1 persoon heeft gestemd ', 'other' => '{count} personen hebben gestemd '],
    ],

    'PostComposer' => [
        'prompt' => ['before' => '', 'link' => 'Log in', 'after' => ' om te posten.'],
    ],

    'ReplyComposer' => [
        'prompt' => ['before' => '', 'link' => 'Log in', 'after' => ' om te reageren.'],
    ],

    'RepostAttribution' => [
        'attribution' => ['before' => '', 'after' => ' heeft dit gerepost'],
    ],

    'ThreadContext' => [
        'response' => ['before' => 'Als reactie op ', 'after' => ''],
        'untitled' => 'deze post',
        'jumpToStart' => 'Naar het begin',
    ],

    'TopicSummaryCard' => [
        'label' => 'Door AI gegenereerde samenvatting',
    ],

    'WelcomeBanner' => [
        'heading' => ['before' => 'Welkom bij ', 'after' => ''],
        'paragraphs' => [
            'Schrijf iets in het vak hieronder en het gaat naar je feed. Iedereen kan reageren, en een reactie is gewoon een post met een bovenliggende post, dus gesprekken nesten zo diep als nodig is.',
            'Voeg mensen toe als vriend en hun posts verschijnen in je feed. De globale feed - de naam van de site, linksboven - toont alles wat hier is geschreven, en is de plek om iemand te vinden om toe te voegen.',
            'Deze site maakt deel uit van de Fediverse: je kunt accounts op Mastodon en andere servers volgen via hun volledige handle, en wat je post bereikt de mensen die je daar volgen. Zoek naar een handle zoals @iemand@example.social en deze server zoekt diegene voor je op.',
            'Tag een post met #hashtags en hij verschijnt op de pagina van die tag, en bij Trending als genoeg mensen erover schrijven.',
            'Berichten tussen leden zijn end-to-end versleuteld - de server slaat cijfertekst op die hij niet kan lezen. Schakel dat in bij Instellingen.',
            'Je hoeft niet meteen te posten: sla een concept op, of stel een tijd in en het publiceert zichzelf. Beide vind je onder Concepten & gepland.',
        ],
        'more' => ['before' => 'Meer vind je in ', 'link' => 'de hulppagina\'s', 'after' => ', waaronder hoe je een account van elders hierheen verhuist.'],
        'dontShowAgain' => 'Dit niet meer tonen',
    ],
];
