<?php

declare(strict_types=1);

/** Polish for reading a received message in the reader's own language. */

return [
    'MessageTranslateButton' => [
        'name' => 'Przetłumacz tę wiadomość',
    ],

    'MessageTranslationNotice' => [
        'heading' => 'O tłumaczeniu wiadomości',
        'body' => 'Aby przetłumaczyć wiadomość, jej tekst jest wysyłany na ten serwer i tu tłumaczony. Nic nie jest zachowywane: tłumaczenie nie jest zapisywane w bazie danych, a sama wiadomość pozostaje niezmieniona. Wiadomość przetłumaczona w ten sposób została jednak odczytana przez serwer, więc nie jest szyfrowana od końca do końca tak, jak wiadomość nieprzetłumaczona. Tłumaczyć można tylko wiadomości, które otrzymujesz, i tylko wtedy, gdy o to poprosisz.',
        'confirm' => 'Przetłumacz',
        'cancel' => 'Nie teraz',
    ],
];
