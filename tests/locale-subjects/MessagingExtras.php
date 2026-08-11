<?php

declare(strict_types=1);

/**
 * How to build one of each converted notification/direct-messaging class not
 * already covered by MessagingAndStatus.php - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 *
 * NotificationList, MessageList and NotificationsNavLink are not here: each
 * one reaches the database as soon as it is constructed - ItemLoader's
 * constructor calls rows(), and NotificationsNavLink builds a
 * NotificationDropdown, which is one - the same reasoning
 * MessagingAndStatus.php gives for leaving out EncryptedMessagesSetting.
 */
return [
    // systemError keeps targetURL() away from Auth::user() entirely (its
    // other arms all read the signed-in user, which is null outside a real
    // session) while still exercising a real, non-default translated phrase.
    Notification::class => static fn (): HTMLObject => new Notification([
        'type' => 'systemError',
        'actorUsername' => 'admin',
        'actorDisplayName' => 'Admin',
        'createdAt' => '2026-08-01 12:00:00',
    ]),

    NotificationTestPanel::class => static fn (): HTMLObject => new NotificationTestPanel(),

    MessageDot::class => static fn (): HTMLObject => new MessageDot(true),
    NavAlertDot::class => static fn (): HTMLObject => new NavAlertDot(true),

    // bodyCiphertext set so toDOM() takes the encrypted-placeholder branch -
    // the only one of the two with a locale string to prove.
    Message::class => static fn (): HTMLObject => new Message([
        'messageId' => 1,
        'senderId' => 2,
        'recipientId' => 3,
        'bodyCiphertext' => '{"v":1}',
        'createdAt' => '2026-08-01 12:00:00',
    ]),

    // recipientIsLocal: false keeps this off the video-call attributes,
    // which are a real feature but nothing this class says in words.
    MessageComposer::class => static fn (): HTMLObject => new MessageComposer(
        1,
        new MessagePrivacyButton('encrypted', 'example@example.social'),
        false
    ),

    MessageKeyFingerprint::class => static fn (): HTMLObject => new MessageKeyFingerprint(),
    MessageKeyVerifyButton::class => static fn (): HTMLObject => new MessageKeyVerifyButton(),
    MessageUnlockForm::class => static fn (): HTMLObject => new MessageUnlockForm('pub1', 'pub2', 'wrapped'),

    // Anchor's own constructor takes (href, text), not a properties array -
    // built and hydrated the way AccountForms.php stands up a fabricated
    // User for the same reason.
    Conversation::class => static function (): HTMLObject {
        $conversation = new Conversation();
        $conversation -> userId = 1;
        $conversation -> slug = 'example';
        $conversation -> title = 'Example';
        $conversation -> hasAvatar = 0;
        $conversation -> lastMessageAt = '2026-08-01 12:00:00';

        return $conversation;
    },

    SensitiveMedia::class => static fn (): HTMLObject => new SensitiveMedia(),
    SensitiveMediaSetting::class => static fn (): HTMLObject => new SensitiveMediaSetting(),
];
