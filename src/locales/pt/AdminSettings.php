<?php

declare(strict_types=1);

/**
 * The words the admin Site Settings forms say, in Portuguese. See
 * src/locales/en/AdminSettings.php for what these classes are.
 */

return [
    'MailSettingsForm' => [
        'legend' => 'Correio de saída',
        'fromAddressLabel' => 'Endereço do remetente',
        'fromAddressPlaceholder' => 'Não é possível enviar email enquanto isto não for definido',
        'fromNameLabel' => 'Nome do remetente',
        'hostLabel' => 'Anfitrião SMTP',
        'portLabel' => 'Porta SMTP',
        'usernameLabel' => 'Nome de utilizador SMTP',
        'passwordLabel' => 'Palavra-passe SMTP',
        'passwordPlaceholder' => [
            'set' => 'A palavra-passe está definida - deixar em branco para a manter',
            'unset' => 'Palavra-passe SMTP',
        ],
        'encryptionLabel' => 'Cifra',
        'encryptionOptions' => [
            'tls' => 'TLS (STARTTLS, habitual na porta 587)',
            'ssl' => 'SSL (TLS implícito, habitual na porta 465)',
            'none' => 'Nenhuma',
        ],
        'explainer' => 'Deixar o anfitrião SMTP em branco para enviar antes via mail() do PHP (não recomendado - ver a secção de entregabilidade do README). Não é possível enviar nenhum email enquanto um endereço de "remetente" não for definido.',
        'save' => 'Guardar',
    ],

    'MapSettingsForm' => [
        'legend' => 'Mosaicos do mapa',
        'notice' => 'Deixar em branco para usar o OpenStreetMap. Para usar um fornecedor com chave, cola o modelo de URL com um {apiKey} literal onde a chave deve ficar, mais a chave e a atribuição abaixo.',
        'urlLabel' => 'Modelo de URL dos mosaicos',
        'keyLabel' => 'Chave API',
        'keyPlaceholder' => 'A chave API do teu fornecedor de mosaicos',
        'attributionLabel' => 'Atribuição',
        'attributionPlaceholder' => '© Colaboradores do OpenStreetMap',
        'save' => 'Guardar',
    ],

    'GoogleAuthSettingsForm' => [
        'legend' => 'Início de sessão com a Google',
        'clientIdLabel' => 'ID de cliente',
        'clientIdPlaceholder' => 'ID de cliente OAuth da Google',
        'secretLabel' => 'Segredo do cliente',
        'secretPlaceholder' => [
            'set' => 'O segredo do cliente está definido - deixar em branco para o manter',
            'unset' => 'Segredo do cliente OAuth da Google',
        ],
        'explainer' => 'Ambos são necessários para que "Continuar com a Google" apareça no registo e no início de sessão. No teu cliente OAuth do Google Cloud, define o URI de redirecionamento autorizado como {url} - limpa o ID de cliente para desativar.',
        'save' => 'Guardar',
    ],

    'BotProtectionSettingsForm' => [
        'turnstileLegend' => 'Cloudflare Turnstile',
        'turnstileSiteKeyLabel' => 'Chave do site',
        'turnstileSiteKeyPlaceholder' => 'Chave do site do Cloudflare Turnstile',
        'turnstileSecretKeyLabel' => 'Chave secreta',
        'turnstileSecretKeyPlaceholder' => [
            'set' => 'A chave secreta está definida - deixar em branco para a manter',
            'unset' => 'Chave secreta do Cloudflare Turnstile',
        ],
        'turnstileExplainer' => 'Ambas as chaves são necessárias para que o CAPTCHA apareça no registo e no início de sessão. Limpa a chave do site para desativar.',
        'recaptchaLegend' => 'Google reCAPTCHA (recuperação de conta bloqueada)',
        'recaptchaSiteKeyLabel' => 'Chave do site',
        'recaptchaSiteKeyPlaceholder' => 'Chave do site do Google reCAPTCHA v2',
        'recaptchaSecretKeyLabel' => 'Chave secreta',
        'recaptchaSecretKeyPlaceholder' => [
            'set' => 'A chave secreta está definida - deixar em branco para a manter',
            'unset' => 'Chave secreta do Google reCAPTCHA v2',
        ],
        'recaptchaExplainer' => 'Ambas as chaves são necessárias. Quando definidas, uma conta que atinja o limite de tentativas de início de sessão pode voltar a entrar superando este desafio em vez de esperar pelo bloqueio; quando não definidas, o bloqueio é uma espera obrigatória. Usa o reCAPTCHA v2 ("Não sou um robô"). Limpa a chave do site para desativar.',
        'save' => 'Guardar',
    ],

    'OpenRouterSettingsForm' => [
        'legend' => 'OpenRouter',
        'notice' => 'Usado pelas funcionalidades de IA do site (resumos de tópicos em tendência, etc.). Deixar o modelo em branco para usar o Free Models Router, que o OpenRouter escolhe ao acaso entre o que estiver atualmente gratuito e nunca pode gerar custos.',
        'keyLabel' => 'Chave API',
        'keyPlaceholder' => [
            'set' => 'A chave API está definida - deixar em branco para a manter',
            'unset' => 'Chave API do OpenRouter',
        ],
        'clearKeyLabel' => 'Remover a chave API guardada (desativa as funcionalidades de IA)',
        'modelLabel' => 'Modelo',
        'neverSpendLabel' => 'Nunca permitir que isto gaste dinheiro (recomendado)',
        'explainer' => 'Com a proteção ativada, cada pedido limita o preço a zero, por isso falha diretamente em vez de recorrer a um modelo pago quando não há nenhum fornecedor gratuito disponível. Remove a chave API guardada para desativar por completo as funcionalidades de IA.',
        'save' => 'Guardar',
    ],

    'AboutSettingsForm' => [
        'legend' => 'Sobre',
        'description' => 'Texto simples - linhas em branco separam parágrafos. O primeiro parágrafo será usado como descrição do site.',
        'save' => 'Guardar',
    ],

    'TermsSettingsForm' => [
        'legend' => 'Termos de Serviço',
        'description' => 'Texto simples - linhas em branco separam parágrafos.',
        'save' => 'Guardar',
    ],

    'PrivacySettingsForm' => [
        'legend' => 'Política de Privacidade',
        'description' => 'Texto simples - linhas em branco separam parágrafos.',
        'save' => 'Guardar',
    ],

    'EmailDigestSettingsForm' => [
        'legend' => 'Resumo por email',
        'fieldLabel' => 'Parágrafo final',
        'notice' => 'Adicionado perto do fim de cada resumo, depois da lista do que o membro perdeu. Texto simples. Deixar em branco para voltar ao texto com que este software é distribuído.',
        'save' => 'Guardar',
    ],

    'FaviconSettingsForm' => [
        'legend' => 'Favicon',
        'currentAlt' => 'Favicon atual',
        'save' => 'Carregar favicon',
    ],

    'FrontPageImageSettingsForm' => [
        'legend' => 'Imagem da página inicial',
        'explainer' => 'Mostrada por outros sites quando alguém partilha uma ligação para este - apenas metadados Open Graph, nunca na página. Cortada para 1200×630. Sem uma, as pré-visualizações de ligações não têm imagem nenhuma.',
        'currentAlt' => 'Imagem atual da página inicial',
        'save' => 'Carregar imagem',
    ],
];
