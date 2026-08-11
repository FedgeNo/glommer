<?php

declare(strict_types=1);

/**
 * The words the notification and direct-messaging classes not already
 * covered by MessagingAndStatus.php say, in Portuguese. See
 * src/locales/en/MessagingExtras.php for what these classes are.
 */

return [
    'Notification' => [
        'postReady' => 'O teu conteúdo multimédia terminou o processamento e já está no ar',
        'scheduledPostLive' => 'A tua publicação agendada já está no ar',
        'uploadPartlyFailed' => 'A tua publicação está no ar, mas um ou mais dos seus ficheiros não puderam ser processados',
        'uploadFailed' => 'Um dos teus carregamentos falhou o processamento e não foi publicado',
        'mailerFailed' => 'O envio de email falhou - o serviço de correio pode estar em baixo. Verifica a tua configuração de email.',
        'mailFromNotConfigured' => 'Não está configurado nenhum endereço de "remetente", por isso não é possível enviar emails. Define um nas Definições de Administração (secção "Correio de saída") ou através de bin/install.php.',
        'systemError' => 'Ocorreu um erro no servidor. Verifica o registo de erros para mais detalhes.',
        'passwordRemovedGoogle' => 'A tua palavra-passe foi removida quando iniciaste sessão com a Google. Usa "Esqueci-me da palavra-passe" se quiseres definir uma nova.',
        'like' => '{name} gostou da tua publicação',
        'repost' => '{name} republicou a tua publicação',
        'reply' => '{name} respondeu à tua publicação',
        'friendRequest' => '{name} enviou-te um pedido de amizade',
        'friendAccepted' => '{name} aceitou o teu pedido de amizade',
        'message' => '{name} enviou-te uma mensagem',
        'mention' => '{name} mencionou-te numa publicação',
        'follow' => '{name} começou a seguir-te a partir de outro servidor',
        'default' => '{name} fez alguma coisa',
    ],

    'NotificationList' => [
        'emptyNotice' => 'Ainda não há notificações.',
    ],

    'NotificationsNavLink' => [
        'label' => 'Notificações',
        'unseen' => 'Notificações não vistas',
    ],

    'NotificationTestPanel' => [
        'intro' => 'Envia uma notificação de teste a ti mesmo (o administrador). Deve aparecer de imediato num aviso temporário e na lista de notificações.',
        'button' => 'Enviar notificação de teste',
        'sending' => 'A enviar…',
        'sent' => 'Enviada',
        'failed' => 'Falhou',
    ],

    'MessageDot' => [
        'label' => 'Mensagens não lidas',
    ],

    'NavAlertDot' => [
        'label' => 'Novidades no menu',
    ],

    'Message' => [
        'encrypted' => 'Mensagem cifrada',
        'decryptionFailed' => 'Esta mensagem foi cifrada com chaves que já não existem.',
    ],

    'MessageComposer' => [
        'bodyLabel' => 'Mensagem',
        'bodyPlaceholder' => 'Escreve uma mensagem',
        'send' => 'Enviar',
    ],

    'MessageList' => [
        'emptyNotice' => 'Ainda não há mensagens.',
    ],

    'MessageKeyFingerprint' => [
        'explanation' => 'Lê este código um ao outro por outro meio - em voz alta, pessoalmente, numa chamada. Se corresponder dos dois lados, ninguém está entre vocês.',
        'changed' => 'Este código mudou desde que o verificaste. Isso acontece quando um de vocês repõe as suas chaves de cifra - mas é também o aspeto que teria alguém a ler esta conversa. Verifica o novo código com essa pessoa antes de confiares nele.',
        'verified' => 'Já verificaste este código.',
    ],

    'MessageKeyVerifyButton' => [
        'label' => 'Marcar como verificado',
    ],

    'MessageUnlockForm' => [
        'passphraseLabel' => 'Frase-passe',
        'passphrasePlaceholder' => 'Frase-passe para abrir esta conversa',
        'submit' => 'Abrir',
    ],

    'Conversation' => [
        'lastMessage' => ['before' => 'Última mensagem ', 'after' => ''],
    ],

    'SensitiveMedia' => [
        'summary' => 'Conteúdo sensível',
    ],

    'SensitiveMediaSetting' => [
        'toggle' => 'Mostrar conteúdo sensível por predefinição',
    ],
];
