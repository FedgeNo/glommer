<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

// Only the primary admin can promote/demote moderators - not mods
// themselves, to avoid a mod-promotes-mod escalation chain.
if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::localizedError('notAuthorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$user_id = (int) ($payload['userId'] ?? 0);
$is_mod = (bool) ($payload['isMod'] ?? false);

if ($user_id === 0 || $user_id === 1) {
    JSONResponse::localizedError('invalidTarget', 422) -> send();
}

$target = User::load($user_id);

if ($target === null) {
    JSONResponse::localizedError('userNotFound', 404) -> send();
}

// Moderating is done by signing in here, which nobody on another server can
// do - the row is a shadow of somebody else's account, not a person with a
// password. Refused rather than left to the button being absent.
if ($target -> remoteActorURI !== null) {
    JSONResponse::localizedError('thatAccountIsOnAnotherServerSoItCannotModerateThisOne', 422) -> send();
}

$is_mod_value = $is_mod ? 1 : 0;

DB::run('
UPDATE `Users`
    SET `isMod` = ?
    WHERE `userId` = ?
', 'ii', $is_mod_value, $user_id);

ModerationAction::log($is_mod ? 'setMod' : 'unsetMod', $user_id);

JSONResponse::success(['isMod' => $is_mod]) -> send();
