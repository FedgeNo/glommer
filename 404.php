<?php

declare(strict_types=1);

require_once __DIR__ . '/src/init.php';

$words = Strings::for(ErrorDocument::class);
ErrorDocument::send(404, (string) ($words['notFoundTitle'] ?? ''), (string) ($words['notFoundMessage'] ?? ''));
