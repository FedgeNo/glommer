<?php

declare(strict_types=1);

/**
 * The words the moderation queue and relay admin pages say, in Portuguese.
 * See src/locales/en/ModerationAdmin.php for what this file is.
 */

return [
    'ReportCard' => [
        'targetTypes' => [
            'post' => 'Publicação',
            'message' => 'Mensagem',
            'user' => 'Utilizador',
        ],
        // Rephrased around a noun ("Denúncia de") rather than a past
        // participle, so nothing has to agree in gender with {type} -
        // Publicação and Mensagem are feminine, Utilizador is masculine.
        'summary' => ['before' => 'Denúncia de {type} #{id} por ', 'after' => ''],
        'reasonLine' => 'Motivo: {reason}',
        'banReporterLabel' => 'Banir denunciante',
        'banReportedUserLabel' => 'Banir utilizador denunciado',
        'deleteLabel' => 'Eliminar {type}',
        'reportedImageAlt' => 'Imagem denunciada',
        'attachmentUnavailable' => 'Um anexo denunciado já não está disponível.',
        'viewAttachment' => 'Ver anexo denunciado',
        'missing' => [
            'noSnapshot' => 'O conteúdo denunciado já não está disponível.',
            'unknownType' => 'Tipo de conteúdo desconhecido.',
        ],
    ],

    'ReportList' => [
        'emptyNotice' => 'Sem denúncias.',
    ],

    'ModQueueLinks' => [
        'intro' => 'As filas são suficientemente longas para se lerem uma página de cada vez, por isso têm páginas próprias.',
        'reportsLabel' => 'Denúncias',
        'bannedUsersLabel' => 'Utilizadores banidos',
    ],

    'ModerationActionList' => [
        'emptyNotice' => 'Nenhum moderador fez nada ainda.',
    ],

    'BannedTrendingEntityList' => [
        'emptyNotice' => 'Sem entidades de tendências banidas.',
    ],

    'BlockedServerList' => [
        'emptyNotice' => 'Sem servidores bloqueados.',
    ],

    'RelayCard' => [
        'accepted' => 'Subscrito ',
        'waiting' => 'A aguardar aceitação do retransmissor - subscrito ',
    ],

    'RelayList' => [
        'emptyNotice' => 'Sem subscrição de nenhum retransmissor. Aqui só chega o que os membros seguem.',
    ],

    'RelayFeedList' => [
        'emptyNotice' => 'Ainda não chegou nada através de um retransmissor. As publicações aparecem aqui à medida que os servidores do outro lado as publicam.',
    ],

    'RelaySubscribeForm' => [
        'legend' => 'Subscrever um retransmissor',
        'explainerOne' => 'Um retransmissor é uma torrente partilhada: todas as publicações públicas de todos os outros servidores subscritos chegam aqui, e as deste servidor seguem para todos eles. É assim que uma instância nova encontra seja quem for, já que de outra forma a federação só transporta o que alguém aqui já segue.',
        'explainerTwo' => 'A carga não é algo que possas prever - é o que esses servidores publicarem, calma numa semana e milhares de publicações por hora na seguinte, e o teu armazenamento, a fila de entrega e a fila de moderação sentem tudo isso. As publicações retransmitidas ficam fora dos feeds principal e de amigos; vão para o Feed de Retransmissão, que as pessoas abrem deliberadamente.',
        'addressLabel' => 'Endereço do retransmissor',
        'addressPlaceholder' => 'https://relay.example/actor',
        'submitLabel' => 'Subscrever',
    ],

    'RelayFollowObjectField' => [
        'label' => 'Estilo de subscrição',
        'options' => [
            'public' => 'Seguir o fluxo público (o que a maioria dos retransmissores espera)',
            'actor' => 'Seguir o ator do próprio retransmissor',
        ],
        'retryNotice' => 'Se o retransmissor nunca aceitar, cancela a subscrição e tenta o outro estilo - alguns programas de retransmissor só reconhecem um deles.',
    ],

    'SiteCounters' => [
        'members' => 'Membros: {count} ({joined} aderiram nos últimos {days} dias)',
        'activeMembers' => 'Membros ativos nos últimos {days} dias: {count} ({posted} publicaram)',
        'posts' => 'Publicações escritas aqui: {count} ({recent} nos últimos {days} dias)',
        'deliveries' => 'Entregas federadas nos últimos {days} dias: {delivered} chegaram, {undeliverable} abandonadas',
        'queued' => 'À espera de ser enviadas: {count} ({failing} já recusadas pelo menos uma vez)',
        'pendingReads' => 'Publicações à espera de ser lidas de outros servidores: {count}',
    ],

    'TestSuitePanel' => [
        'intro' => 'Executa os testes do site e vê os resultados. Demora alguns segundos, por isso abre na sua própria página.',
        'runLabel' => 'Executar testes',
    ],

    'HelpArticle' => [
        'backLabel' => 'Voltar a toda a ajuda',
    ],

    'UserSearchList' => [
        'noSuggestions' => 'Sem sugestões neste momento - as sugestões vêm dos amigos das pessoas com quem já és amigo. Procura acima para encontrar alguém pelo nome.',
        'noMatches' => 'Ninguém aqui corresponde a isso.',
    ],
];
