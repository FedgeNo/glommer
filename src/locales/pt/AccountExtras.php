<?php

declare(strict_types=1);

/**
 * The words the smaller account-related classes say, in Portuguese. See
 * src/locales/en/AccountExtras.php for what these classes are.
 */

return [
    'SetupForm' => [
        'siteLegend' => 'Site',
        'siteURLLabel' => 'URL do site',
        'siteTitleLabel' => 'Título do site',
        'mailFromAddressLabel' => 'Endereço de envio de email',
        'serverNameConfirmedLabel' => 'Defini "ServerName {host}" e "UseCanonicalName On" na configuração do meu servidor web (só é verificado se o teste automático em direto não conseguir concluir - ver a secção de HTTPS do README.md)',
        'databaseLegend' => 'Base de dados',
        'databaseHostLabel' => 'Anfitrião da base de dados',
        'databasePortLabel' => 'Porta da base de dados',
        'databaseNameLabel' => 'Nome da base de dados',
        'databaseAdminUsernameLabel' => 'Nome de utilizador do administrador da base de dados',
        'databaseAdminPasswordLabel' => 'Palavra-passe do administrador da base de dados',
        'webSocketTLSLegend' => 'TLS do WebSocket (opcional)',
        'certificatePathLabel' => 'Caminho do certificado',
        'certificatePathPlaceholder' => 'Deixar em branco para gerar automaticamente via mkcert',
        'keyPathLabel' => 'Caminho da chave',
        'keyPathPlaceholder' => 'Deixar em branco para gerar automaticamente via mkcert',
        'botProtectionLegend' => 'Proteção contra bots (opcional)',
        'turnstileSiteKeyLabel' => 'Chave de site do Cloudflare Turnstile',
        'turnstileSiteKeyPlaceholder' => 'Deixar em branco para ignorar',
        'turnstileSecretKeyLabel' => 'Chave secreta do Cloudflare Turnstile',
        'turnstileSecretKeyPlaceholder' => 'Deixar em branco para ignorar',
        'submit' => 'Configurar',
    ],

    'MessageKeyPassphraseForm' => [
        'currentPassphraseLabel' => 'Frase-passe atual',
        'newPassphraseLabel' => 'Nova frase-passe',
        'confirmNewPassphraseLabel' => 'Confirmar nova frase-passe',
        'accountPasswordLabel' => 'Palavra-passe da conta',
        'submit' => 'Alterar frase-passe',
    ],

    'PasswordResetForm' => [
        'legend' => 'Escolher uma nova palavra-passe',
        'newPasswordLabel' => 'Nova palavra-passe',
        'newPasswordPlaceholder' => 'Pelo menos 8 caracteres',
        'confirmPasswordLabel' => 'Confirmar nova palavra-passe',
        'submit' => 'Repor palavra-passe',
    ],

    'PasswordResetRequestForm' => [
        'legend' => 'Repor a tua palavra-passe',
        'emailLabel' => 'Email',
        'submit' => 'Enviar ligação de reposição',
    ],

    'EmailRevertForm' => [
        'submit' => 'Reverter alteração de email',
    ],

    'EmailVerifyForm' => [
        'submit' => 'Verificar endereço de email',
    ],

    'EmailDigestResubscribeForm' => [
        'submit' => 'Voltar a ativar',
    ],

    'EmailDigestSetting' => [
        'label' => 'Avisar-me por email do que perdi sempre que estiver ausente durante algum tempo',
    ],

    'RememberedDevice' => [
        'unknownDevice' => 'Dispositivo desconhecido',
        'browserOnOS' => '{browser} em {os}',
        'thisDevice' => ' (este dispositivo)',
        'lastUsed' => ['before' => 'Usado pela última vez ', 'after' => ''],
    ],

    'LogoutEverywherePanel' => [
        'explanation' => 'Termina todas as sessões ativas e esquece todos os dispositivos memorizados. Vais ser desligado de todos os navegadores, incluindo este.',
    ],

    'LogoutEverywhereButton' => [
        'label' => 'Terminar sessão em todo o lado',
    ],

    'GoogleAccountDeleteButton' => [
        'label' => 'Verificar com a Google para eliminar',
    ],

    'GoogleSignInButton' => [
        'label' => 'Continuar com a Google',
    ],

    'ProfileEditButton' => [
        'ariaLabel' => 'Editar perfil',
    ],

    'PushNotificationSetting' => [
        'explanation' => 'Recebe notificações neste dispositivo mesmo quando o site não está aberto. Esta escolha é feita por navegador - ativa-a em qualquer um onde queiras ser contactado.',
        'label' => [
            'off' => 'Ativar neste dispositivo',
            'on' => 'Desativar neste dispositivo',
        ],
        'unsupported' => 'As notificações push não são suportadas neste navegador',
    ],
];
