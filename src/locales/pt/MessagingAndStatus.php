<?php

declare(strict_types=1);

/**
 * The words the messaging and admin-status classes say, in Portuguese. See
 * src/locales/en/MessagingAndStatus.php for what these classes are.
 */

return [
    'MessageKeySetupForm' => [
        'resetWarning' => 'Esqueceste-te da tua frase-passe? Repor cria novas chaves associadas a uma nova frase-passe - mas as mensagens cifradas com as chaves antigas nunca mais podem ser lidas, por ninguém.',
        'requirements' => 'Pelo menos 12 caracteres, e não a palavra-passe da tua conta - essa é enviada a este servidor, e a tua frase-passe nunca deve ser.',
        'passphraseLabel' => 'Frase-passe',
        'resetPassphraseLabel' => 'Nova frase-passe',
        'confirmLabel' => 'Confirmar frase-passe',
        'accountPasswordLabel' => 'Palavra-passe da conta',
        'submitLabel' => 'Ativar mensagens cifradas',
        'resetSubmitLabel' => 'Repor chaves de cifra',
    ],

    'EncryptedMessagesSetting' => [
        'explanation' => 'As mensagens cifradas de ponta a ponta são fechadas e abertas no teu navegador: este servidor retransmite-as e guarda-as sem conseguir lê-las. A tua chave está protegida por uma frase-passe, e essa mesma frase-passe abre as tuas mensagens a partir de qualquer navegador. As conversas ficam cifradas assim que as duas pessoas ativarem isto; as mensagens para pessoas noutros servidores continuam sem cifra, porque a federação não tem outra forma de as transportar.',
        'noRecovery' => 'Não há forma de recuperar uma frase-passe perdida - nem para o administrador. Perdê-la significa perder as tuas mensagens cifradas.',
        'enabledStatus' => 'As mensagens cifradas estão ativadas.',
    ],

    'MessagePrivacyButton' => [
        'encrypted' => [
            'label' => '🔒 Cifrada',
            'explanation' => 'As mensagens nesta conversa são cifradas de ponta a ponta: são abertas com a tua frase-passe e lidas nos vossos navegadores, e o que este servidor guarda é texto cifrado. Verifica o código de segurança no fundo da conversa com a outra pessoa para teres a certeza de que ninguém está entre vocês. As mensagens enviadas antes de a cifra ser ativada continuam legíveis como estavam.',
        ],
        'awaiting-theirs' => [
            'label' => '🔓 Não cifrada',
            'explanation' => 'As mensagens aqui ficarão cifradas de ponta a ponta assim que {handle} ativar as mensagens cifradas nas suas definições.',
        ],
        'awaiting-yours' => [
            'label' => '🔓 Não cifrada',
            'explanation' => 'As mensagens aqui não são cifradas de ponta a ponta. Ativa as mensagens cifradas nas Definições para proteger esta conversa.',
        ],
        'federated' => [
            'label' => '🔓 Não cifrada',
            'explanation' => '{handle} está noutro servidor. As mensagens nesta conversa ficam guardadas nesse servidor além deste, e o respetivo administrador pode lê-las - o protocolo entre servidores não tem forma de as cifrar. Guarda o que for sensível para conversas dentro deste site.',
        ],
    ],

    'RemoteFollowsForm' => [
        'legend' => 'Seguir contas do Fediverso',
        'notice' => 'Cola um ou mais identificadores, por exemplo @utilizador@example.social - qualquer separador entre eles funciona.',
        'handlesLabel' => 'Identificadores do Fediverso a seguir',
        'submit' => 'Seguir',
        'statusPending' => 'pendente',
        'statusAccepted' => 'aceite',
    ],

    'ServerBlockForm' => [
        'legend' => 'Bloquear um servidor',
        'description' => 'Recusa tudo o que vier desse servidor e de tudo o que está por baixo dele: não há entregas a entrar nem a sair, e as relações de seguir já existentes em ambas as direções são eliminadas.',
        'serverLabel' => 'Servidor',
        'serverPlaceholder' => 'example.social',
        'reasonLabel' => 'Motivo',
        'reasonPlaceholder' => 'Porque é que este servidor está bloqueado',
        'submit' => 'Bloquear servidor',
    ],

    'VideoCallTestPanel' => [
        'intro' => 'Executa as partes da configuração de chamada que podem ser verificadas a partir de um único navegador. Tudo até à ligação ponto-a-ponto propriamente dita pode ser testado aqui; ligar a outra pessoa precisa dessa pessoa.',
    ],

    'VideoCallTestButton' => [
        'label' => 'Executar verificação',
    ],

    'WebSocketStatus' => [
        'ok' => 'Servidor WebSocket: A funcionar',
        'failed' => 'Servidor WebSocket: {detail}',
        'clientTesting' => 'Ligação do navegador: A testar…',
        'clientConnecting' => 'Ligação do navegador: A ligar…',
        'clientConnected' => 'Ligação do navegador: Ligado',
        'clientDisconnecting' => 'Ligação do navegador: A desligar…',
        'clientNotConnected' => 'Ligação do navegador: Não ligado',
    ],

    'UploadWorkerStatus' => [
        'running' => 'Processador de carregamentos: A funcionar',
        'stopped' => 'Processador de carregamentos: Não está a funcionar - os carregamentos em espera nunca serão convertidos até ser reiniciado',
        'unknown' => 'Processador de carregamentos: Desconhecido - ou o systemctl não está disponível neste anfitrião, ou o SELinux está a negar a própria consulta de estado do servidor web (executa bin/install.php como root para corrigir isso)',
        'queue' => 'Fila: {staging} em preparação, {pending} pendentes, {processing} em processamento',
    ],

    'TrendingTimerStatus' => [
        'running' => 'Cronómetro de tendências: A funcionar',
        'stopped' => 'Cronómetro de tendências: Não está a funcionar - os tópicos em tendência só serão atualizados pela autorregeneração no caminho de leitura (Trending::current()), não por agendamento. Executa bin/install.php como root para o configurar.',
        'unknown' => 'Cronómetro de tendências: Desconhecido - ou o systemctl não está disponível neste anfitrião, ou o SELinux está a negar a própria consulta de estado do servidor web (executa bin/install.php como root para corrigir isso)',
    ],
];
