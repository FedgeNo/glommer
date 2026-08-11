<?php

declare(strict_types=1);

/**
 * The words the account/auth forms say, in Portuguese. See
 * src/locales/en/AccountForms.php for what these classes are.
 */

return [
    'LoginForm' => [
        'legend' => 'Iniciar sessão',
        'identifier' => 'Nome de utilizador ou email',
        'password' => 'Palavra-passe',
        'rememberMe' => 'Lembrar-me',
        'submit' => 'Iniciar sessão',
    ],

    'SignupForm' => [
        'legend' => 'Criar conta',
        'usernameLabel' => 'Nome de utilizador',
        'usernamePlaceholder' => 'Letras minúsculas, números e _',
        'emailLabel' => 'Email',
        'emailPlaceholder' => 'Endereço de email válido',
        'displayName' => 'Nome de apresentação (opcional)',
        'bioLabel' => 'Biografia (opcional)',
        'bioPlaceholder' => 'Uma biografia curta - #hashtags, @menções e ligações tornam-se clicáveis',
        'passwordLabel' => 'Palavra-passe',
        'passwordPlaceholder' => 'Palavra-passe: pelo menos 8 caracteres',
        'rememberMe' => 'Lembrar-me',
        'submit' => 'Registar',
    ],

    'PasswordChangeForm' => [
        'legend' => 'Alterar a tua palavra-passe',
        'currentPassword' => 'Palavra-passe atual',
        'newPasswordLabel' => 'Nova palavra-passe',
        'newPasswordPlaceholder' => 'Pelo menos 8 caracteres',
        'confirmPassword' => 'Confirmar nova palavra-passe',
        'submit' => 'Alterar palavra-passe',
    ],

    'EmailChangeForm' => [
        'legend' => 'Alterar o teu endereço de email',
        'newEmail' => 'Novo endereço de email',
        'currentPassword' => 'Palavra-passe atual',
        'notice' => 'Precisas de verificar o novo endereço antes de poderes continuar a usar o site.',
        'submit' => 'Alterar email',
    ],

    'AccountDeleteForm' => [
        'legend' => 'Eliminar a tua conta',
        'warning' => 'Isto elimina permanentemente a tua conta, publicações e mensagens. Não pode ser desfeito.',
        'currentPassword' => 'Palavra-passe atual',
        'submit' => 'Eliminar conta',
    ],

    'AccountMigrationForm' => [
        'legend' => 'Mudar para outro servidor',
        'movedNotice' => 'Esta conta mudou-se para {destination}. Foi pedido aos teus seguidores que te sigam lá.',
        'explanation' => 'É pedido aos teus seguidores que te sigam na nova conta. As tuas publicações ficam aqui - os endereços dos objetos pertencem ao servidor que os criou, por isso não podem ser levados.',
        'addressNotice' => 'A conta para onde te estás a mudar tem primeiro de listar esta em "também conhecido como". O teu endereço aqui é {address}.',
        'movedToLabel' => 'Mudar para',
        'movedToPlaceholder' => 'https://example.social/users/you',
        'aliasesLegend' => 'Também conhecido como',
        'aliasesExplanation' => 'Contas noutros servidores que também és tu. Listar uma aqui permite que essa conta se mude para esta - é a permissão, não a mudança em si. Um endereço por linha.',
        'aliasesLabel' => 'As tuas outras contas',
        'aliasesPlaceholder' => 'https://example.social/users/you',
        'submit' => 'Guardar',
    ],

    'TwoFactorForm' => [
        'legend' => 'Introduzir o teu código de verificação',
        'explanation' => 'Enviámos-te um código de verificação por email. Introduz o código abaixo para terminares o início de sessão.',
        'code' => 'Código de verificação',
        'submit' => 'Verificar',
    ],

    'TwoFactorSettingsForm' => [
        'legend' => ['on' => 'A autenticação de dois fatores está ativada', 'off' => 'A autenticação de dois fatores está desativada'],
        'explanation' => [
            'on' => 'Quando inicias sessão, enviamos-te por email um código de verificação que tens de introduzir para terminares o início de sessão.',
            'off' => 'Acrescenta um segundo passo ao início de sessão: enviamos-te por email um código de verificação que tens de introduzir, para que a tua palavra-passe sozinha não seja suficiente para entrar.',
        ],
        'currentPassword' => 'Palavra-passe atual',
        'submit' => ['on' => 'Desativar a autenticação de dois fatores', 'off' => 'Ativar a autenticação de dois fatores'],
    ],

    'VerificationNotice' => [
        'instructions' => 'Verifica a tua caixa de entrada e clica na ligação de verificação que enviámos para confirmar o teu endereço de email. Se não a encontrares, verifica a pasta de spam/lixo eletrónico.',
    ],

    'VerificationResendButton' => [
        'label' => 'Reenviar email de verificação',
    ],
];
