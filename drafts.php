<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$draft_id = (int) ($_GET['draft'] ?? 0);

// /drafts - everything staged, waiting.
if ($draft_id === 0) {
    $page = new Page(['title' => (string) (Strings::for('PageTitle')['drafts'] ?? '')]);
    $page -> addContent(new StagedPostList(['userId' => (int) Auth::id()]));
    $page -> send();

    exit;
}

// /drafts/{id} - the composer itself, holding this draft. The whole composer,
// so a draft is edited with everything a post is written with rather than a
// lesser form standing in for it.
$draft = StagedPost::load($draft_id);

if ($draft === null || (int) $draft -> userId !== (int) Auth::id()) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page([
    'title' => $draft -> publishAt === null
        ? (string) (Strings::for('PageTitle')['draftsEditDraft'] ?? '')
        : (string) (Strings::for('PageTitle')['draftsEditScheduled'] ?? ''),
    'needsEditor' => true,
    'needsEmoji' => true,
    'needsMath' => true,
]);

$composer = new PostComposer();
$composer -> attributes['data-staged-post-id'] = (string) $draft -> stagedPostId;
$composer -> attributes['data-title'] = (string) ($draft -> title ?? '');
$composer -> attributes['data-description-delta'] = (string) ($draft -> descriptionDelta ?? '');
$composer -> attributes['data-link-url'] = (string) ($draft -> linkURL ?? '');

if ($draft -> publishAt !== null) {
    $composer -> attributes['data-publish-at-epoch'] = (string) strtotime((string) $draft -> publishAt);
}

if ($draft -> latitude !== null && $draft -> longitude !== null) {
    $composer -> attributes['data-latitude'] = (string) $draft -> latitude;
    $composer -> attributes['data-longitude'] = (string) $draft -> longitude;
}

$page -> addContent($composer);

$page -> send();
