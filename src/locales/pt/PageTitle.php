<?php

declare(strict_types=1);

/**
 * The words a page's browser tab and <h1> heading carry, and the meta
 * description search engines and link previews read, in Portuguese. See
 * src/locales/en/PageTitle.php for what this file is.
 */

return [
    'PageTitle' => [
        'about' => 'Sobre',
        'adminBanned' => 'Utilizadores banidos',
        'adminModSettings' => 'Definições de Moderação',
        'adminReports' => 'Denúncias',
        'adminSettings' => 'Definições de Administração',
        'adminTests' => 'Testes',
        'authGoogleCallbackAccountDeleted' => 'Conta eliminada',
        'authGoogleCallbackDeleteAccount' => 'Eliminar conta',
        'authGoogleCallbackLogin' => 'Iniciar sessão',
        'bookmarks' => 'Marcadores',
        'checkInbox' => 'Verifica a tua caixa de entrada',
        'drafts' => 'Rascunhos e Agendados',
        'draftsEditDraft' => 'Editar rascunho',
        'draftsEditScheduled' => 'Editar publicação agendada',
        'forgotPassword' => 'Esqueci-me da palavra-passe',
        'friendsFeed' => 'Feed de Amigos',
        'help' => 'Ajuda',
        'helpDescription' => 'Guias e respostas para usar o site.',
        'locations' => 'Localizações',
        'locationsDescription' => 'Publicações dos locais mais próximos de ti.',
        'locationsPlaceDescription' => 'Publicações perto de {place}.',
        'login' => 'Iniciar sessão',
        'loginVerificationCode' => 'Código de verificação',
        'map' => 'Mapa',
        'mapDescription' => 'Um mapa de publicações de todo o mundo - encontra pessoas e coisas perto de ti.',
        'messages' => 'Mensagens',
        'messagesWithUser' => 'Mensagens com {name}',
        'notifications' => 'Notificações',
        'privacy' => 'Política de Privacidade',
        // Verb, not the noun 'Citação' - quote.php is the composer page
        // (needsEditor), matching the verb form every other action/composer
        // title in this file uses (Eliminar conta, Editar rascunho, Repor
        // palavra-passe, Criar conta, Verificar email...).
        'quote' => 'Citar',
        'relayFeed' => 'Feed de Retransmissão',
        'relayFeedDescription' => 'Publicações públicas vindas dos retransmissores que este servidor subscreve.',
        'resetPassword' => 'Repor palavra-passe',
        'revertEmail' => 'Reverter alteração de email',
        'search' => 'Pesquisa',
        'signup' => 'Criar conta',
        'tags' => 'Hashtags',
        'tagsDescription' => 'Explora as hashtags em tendência e populares em {siteTitle}.',
        'tagsTagDescription' => 'Publicações marcadas com {tag} em {siteTitle}.',
        'terms' => 'Termos de Serviço',
        'topics' => 'Tendências',
        'topicsDescription' => 'Do que as pessoas estão a falar em {siteTitle} neste momento.',
        'topicsEntityDescription' => '{typeLabel} - publicações que mencionam {entityTitle} em {siteTitle}.',
        // "de que" ("about which") rather than an adjective ending, because
        // {typePlural} is an untranslated English noun (see EntityType) with
        // no Portuguese gender for anything here to agree with.
        'topicsTypeDescription' => '{typePlural} de que as pessoas estão a falar em {siteTitle} neste momento.',
        'unsubscribe' => 'Resumos por email',
        // Portuguese has no possessive 's, so this rebuilds around "de
        // {name}" - mirrors Post.pageTitleByAuthor ('Publicação de {name}').
        'userFriends' => 'Amigos de {name}',
        'userFriendsDescription' => 'Amigos de {name} em {siteTitle}',
        'userSettings' => 'Definições do Utilizador',
        'users' => 'Utilizadores',
        'verifyEmail' => 'Verificar email',
    ],
];
