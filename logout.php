<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::logout();

header('Location: ' . URL::absolute('/'));
exit;
