<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

// Only the primary admin can promote/demote moderators - not mods
// themselves, to avoid a mod-promotes-mod escalation chain.
if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$mysqli = DB::connection();
$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$user_id = (int) ($payload['userId'] ?? 0);
$is_mod = (bool) ($payload['isMod'] ?? false);

if ($user_id === 0 || $user_id === 1) {
    JSONResponse::error('Invalid target', 422) -> send();
}

if (User::load($user_id) === null) {
    JSONResponse::error('User not found', 404) -> send();
}

$is_mod_value = $is_mod ? 1 : 0;

$stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `isMod` = ?
    WHERE `userId` = ?
');
mysqli_stmt_bind_param($stmt, 'ii', $is_mod_value, $user_id);
mysqli_stmt_execute($stmt);

ModerationAction::log($is_mod ? 'setMod' : 'unsetMod', $user_id);

JSONResponse::success(['isMod' => $is_mod]) -> send();
