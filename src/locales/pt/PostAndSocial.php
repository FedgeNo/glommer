<?php

declare(strict_types=1);

/**
 * The words the composer, poll and feed-context classes say, in Portuguese.
 * See src/locales/en/PostAndSocial.php for what these classes are.
 */

return [
    'MoreLocationsLink' => [
        'moreLocations' => ['before' => 'Ver ', 'link' => 'mais localizações', 'after' => ''],
    ],

    'NearbyLocationPrompt' => [
        'heading' => 'Publicações perto de ti',
        'description' => 'Isto mostra as publicações mais próximas de um ponto - onde quer que haja atividade, por mais longe que esteja. Partilha a tua localização para começares de onde estás, ou escolhe antes um ponto no mapa.',
        'useMyLocation' => 'Usar a minha localização',
        'pickOnMap' => 'Escolher no mapa',
        'searchPlaceholder' => 'Ou escreve o nome de um local…',
        'searchLabel' => 'Procurar um local',
        'locating' => 'A localizar…',
        'noGeolocation' => 'O teu navegador não consegue partilhar uma localização.',
        'locationError' => 'Não foi possível obter a tua localização. Verifica a permissão de localização do teu navegador.',
    ],

    'PollDeadline' => [
        'final' => 'Resultado final',
        'closes' => ['before' => 'Termina ', 'after' => ''],
        'days' => ['one' => 'daqui a {count} dia', 'other' => 'daqui a {count} dias'],
        'hours' => ['one' => 'daqui a {count} hora', 'other' => 'daqui a {count} horas'],
        'minutes' => ['one' => 'daqui a {count} minuto', 'other' => 'daqui a {count} minutos'],
        'underMinute' => 'daqui a menos de um minuto',
    ],

    'PollTally' => [
        // 'one' now also covers zero, so it cannot hardcode the digit 1 -
        // {count} has to stay in the phrasing here, unlike the English it
        // replaces.
        'voters' => ['one' => '{count} pessoa votou ', 'other' => '{count} pessoas votaram '],
    ],

    'PostComposer' => [
        'prompt' => ['before' => '', 'link' => 'Iniciar sessão', 'after' => ' para publicar.'],
    ],

    'ReplyComposer' => [
        'prompt' => ['before' => '', 'link' => 'Iniciar sessão', 'after' => ' para responder.'],
    ],

    'RepostAttribution' => [
        'attribution' => ['before' => '', 'after' => ' republicou'],
    ],

    'ThreadContext' => [
        'response' => ['before' => 'Em resposta a ', 'after' => ''],
        'untitled' => 'esta publicação',
        'jumpToStart' => 'Saltar para o início',
    ],

    'TopicSummaryCard' => [
        'label' => 'Resumo gerado por IA',
    ],

    'WelcomeBanner' => [
        'heading' => ['before' => 'Boas-vindas a ', 'after' => ''],
        'paragraphs' => [
            'Escreve alguma coisa na caixa abaixo e ela é publicada no teu feed. Qualquer pessoa pode responder, e uma resposta é apenas uma publicação que responde a outra, por isso as conversas ramificam-se tão fundo quanto for preciso.',
            'Adiciona pessoas como amigos e as publicações delas juntam-se ao teu feed. O feed Global - o nome do site, no canto superior esquerdo - mostra tudo o que é escrito aqui, e é o sítio certo para encontrar alguém para adicionar.',
            'Este site faz parte do Fediverso: podes seguir contas no Mastodon e noutros servidores pelo identificador completo, e o que publicas chega às pessoas que te seguem lá. Procura um identificador como @alguem@example.social e este servidor vai encontrá-lo.',
            'Marca uma publicação com #hashtags e ela aparece na página dessa hashtag, e em Tendências se houver gente suficiente a escrever sobre o assunto.',
            'As mensagens entre membros são cifradas de ponta a ponta - o servidor guarda texto cifrado que não consegue ler. Ativa isso nas Definições.',
            'Não precisas de publicar de imediato: guarda um rascunho, ou define uma hora e a publicação faz-se sozinha. Ambos ficam em Rascunhos e Agendados.',
        ],
        'more' => ['before' => 'Há mais nas ', 'link' => 'páginas de ajuda', 'after' => ', incluindo como mudar uma conta para aqui a partir de outro lugar.'],
        'dontShowAgain' => 'Não mostrar isto novamente',
    ],
];
