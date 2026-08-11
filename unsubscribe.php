<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// One click, and no session: the whole point of the token is that somebody who
// has stopped visiting - and so is signed out everywhere - can still make the
// mail stop without first being asked to remember a password. It authorizes
// this one change and nothing else.
//
// Unlike the email-verification link, this does act on a bare GET. A link
// scanner that follows it turns somebody's digests off without their asking,
// which is why the page it lands on offers to turn them straight back on; the
// alternative - making them press a button first - is not one click, and the
// thing a person reaches for when unsubscribing takes work is the spam button.
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$user_id = $token === '' ? null : EmailDigestUnsubscribe::userIdFor($token);

// A mail client acting on the List-Unsubscribe header itself (RFC 8058),
// before anybody has opened the message. Answered first and answered plainly:
// there is no reader at the other end of this, only a program that wants to
// know it worked, and every POST below this line is a person pressing the
// button on the page - which asks for the opposite.
if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') {
    header('Content-Type: text/plain; charset=utf-8');

    if ($user_id === null) {
        http_response_code(400);
        echo 'That unsubscribe link is not one this site issued.' . chr(10);
        exit;
    }

    EmailDigest::setEnabled($user_id, false);
    echo 'Unsubscribed.' . chr(10);
    exit;
}

$page = new Page(['title' => (string) (Strings::for('PageTitle')['unsubscribe'] ?? '')]);

if ($user_id === null) {
    $page -> addContent(new Paragraph('That unsubscribe link is not one this site issued.'));
    $page -> addContent(new Anchor(ServerURL::absolute('/'), 'Continue'));

    $page -> send();
    exit;
}

// Turning them back on is a POST like any other state change, so it carries
// the CSRF token init.php checks.
$resubscribing = $_SERVER['REQUEST_METHOD'] === 'POST';

EmailDigest::setEnabled($user_id, $resubscribing);

$page -> addContent(new Paragraph($resubscribing
    ? 'Your digests are back on. You will hear from us when you have been away a while and there is something to tell you.'
    : 'Done - no more digests. You will still get the mail this site has to send you, like a password reset.'));

if (!$resubscribing) {
    $page -> addContent(new EmailDigestResubscribeForm($token));
}

$page -> addContent(new Anchor(ServerURL::absolute('/'), 'Continue'));

$page -> send();
