<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

Auth::requireLogin();

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$post_id = (int) ($payload['postId'] ?? 0);

if ($post_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

$user_id = (int) $current_user -> userId;

if (PinnedPost::isPinned($user_id, $post_id)) {
    PinnedPost::unpin($user_id, $post_id);
    $pinned = false;
} else {
    // Refuses rather than silently doing nothing, so a person is told why -
    // it is either not their post or they are already at the cap.
    if (!PinnedPost::pin($user_id, $post_id)) {
        JSONResponse::error('You can pin up to ' . PinnedPost::MAX_PINNED . ' of your own posts.', 422) -> send();
    }

    $pinned = true;
}

// Add/Remove names the post that changed, which is what a server needs to act
// on the pin without re-reading anything. The actor Update goes too, for the
// implementations that treat the featured collection as part of the profile
// and refetch it when the profile is restated.
PinnedPost::publish($current_user, $post_id, $pinned);
FediversePublisher::profileUpdated($current_user);

JSONResponse::success(['pinned' => $pinned]) -> send();
