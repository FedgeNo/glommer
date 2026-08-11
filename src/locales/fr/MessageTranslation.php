<?php

declare(strict_types=1);

/** French for reading a received message in the reader's own language. */

return [
    'MessageTranslateButton' => [
        'name' => 'Traduire ce message',
    ],

    'MessageTranslationNotice' => [
        'heading' => 'À propos de la traduction des messages',
        'body' => 'Pour traduire un message, son texte est envoyé à ce serveur et traduit ici. Rien n\'est conservé : la traduction n\'est pas écrite dans la base de données et le message lui-même reste inchangé. Mais un message traduit de cette façon a été lu par le serveur, il n\'est donc pas chiffré de bout en bout comme l\'est un message non traduit. Seuls les messages que vous recevez peuvent être traduits, et uniquement lorsque vous le demandez.',
        'confirm' => 'Le traduire',
        'cancel' => 'Pas maintenant',
    ],
];
