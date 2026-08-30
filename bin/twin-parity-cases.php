<?php

declare(strict_types=1);

/**
 * Emits stable, database-free PHP renderings and the payloads their browser
 * twins consume. TwinParityTest.js renders each payload immediately, so a
 * synchronized change passes without regenerating fixtures and one-sided DOM
 * drift fails at the first different node.
 */

$site_url = $argv[1] ?? 'http://localhost';

if (filter_var($site_url, FILTER_VALIDATE_URL) === false) {
    fwrite(STDERR, "Twin parity needs a valid site URL.\n");
    exit(1);
}

putenv('SITE_URL=' . $site_url);

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/../src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/../src/functions.php';

Strings::useLocale('en');
$_SESSION = [];

if (isset($argv[2]) && ctype_digit($argv[2])) {
    $_SESSION['userId'] = (int) $argv[2];
    $viewer = new User([
        'userId' => (int) $argv[2],
        'isMod' => ($argv[3] ?? '') === '1' ? 1 : 0,
    ]);
    (new \ReflectionProperty(Auth::class, 'cachedUser')) -> setValue(null, $viewer);
    (new \ReflectionProperty(Auth::class, 'userCacheFilled')) -> setValue(null, true);
}

/** The bare document used for each independent server rendering. */
function useBareTwinDocument(): void
{
    (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
}

/** @return ToggleButton */
function parityToggle(array $labels, ?string $showing = null): ToggleButton
{
    $button = new ToggleButton();
    $button -> class .= ' ParityToggleButton';
    (new \ReflectionProperty(ToggleButton::class, 'labels')) -> setValue($button, $labels);
    (new \ReflectionProperty(ToggleButton::class, 'showing')) -> setValue($button, $showing);

    return $button;
}

function parityPost(): Post
{
    $author = new User([
        'userId' => 31,
        'slug' => 'river',
        'title' => 'River',
        'hasAvatar' => 0,
    ]);

    return new Post([
        'postId' => 44,
        'userId' => 31,
        'title' => 'A mirrored post',
        'createdAt' => '2026-08-11 15:04:00',
        'author' => $author,
        'replyCount' => 0,
        'likeCount' => 0,
        'liked' => false,
        'bookmarked' => false,
        'reposted' => false,
        'repostCount' => 0,
        'pinned' => false,
    ]);
}

function parityMessage(): Message
{
    $sender_id = Auth::id() ?? 52;
    $sender = new User([
        'userId' => $sender_id,
        'slug' => 'sender',
        'title' => 'Sender',
        'hasAvatar' => 0,
    ]);

    return new Message([
        'messageId' => 61,
        'senderId' => $sender_id,
        'recipientId' => 53,
        'body' => 'A mirrored message.',
        'createdAt' => '2026-08-11 15:04:00',
        'sender' => $sender,
    ]);
}

function parityNotification(): Notification
{
    return new Notification([
        'notificationId' => 71,
        'userId' => Auth::id() ?? 52,
        'actorId' => 54,
        'type' => 'mailerFailed',
        'postId' => null,
        'createdAt' => '2026-08-11 15:04:00',
        'actorUsername' => 'helper',
        'actorDisplayName' => 'Helper',
        'actorHasAvatar' => 0,
    ]);
}

function parityOtherUser(): OtherUser
{
    return new OtherUser([
        'userId' => Auth::id() ?? 81,
        'slug' => 'neighbour',
        'title' => 'Neighbour',
        'description' => null,
        'hasAvatar' => 0,
        'createdAt' => '2026-08-11 15:04:00',
    ]);
}

function parityReceivedFriendRequest(): ReceivedFriendRequest
{
    return new ReceivedFriendRequest([
        'userId' => Auth::id() ?? 82,
        'slug' => 'requester',
        'title' => 'Requester',
        'description' => null,
        'hasAvatar' => 0,
        'createdAt' => '2026-08-11 15:04:00',
        'friendshipId' => 83,
    ]);
}

function parityReport(): Report
{
    return new Report([
        'reportId' => 91,
        'reporterId' => 92,
        'reporterUsername' => 'reporter',
        'type' => 'post',
        'targetId' => 93,
        'reason' => 'A stable test reason.',
        'createdAt' => '2026-08-11 15:04:00',
        'targetKind' => 'missing',
        'targetData' => 'postMissing',
        'targetLive' => false,
    ]);
}

/** A primitive with the same runtime class, attributes, and text on both sides. */
function parityPrimitive(HTMLObject $object, array $attributes = [], array $content = []): HTMLObject
{
    $object -> class = 'ParityState';
    $object -> attributes = $attributes;

    if ($content !== []) {
        $object -> addContents($content);
    }

    return $object;
}

class TwinParityPollOptionList extends PollOptionList
{
    public function toDOM(): \DOMElement
    {
        $element = parent::toDOM();
        $classes = array_filter(
            explode(' ', $element -> getAttribute('class')),
            static fn (string $class): bool => $class !== self::class
        );
        $element -> setAttribute('class', implode(' ', $classes));

        return $element;
    }

    protected function rows(): array
    {
        return [
            new PollOption([
                'pollOptionId' => 101,
                'pollId' => 100,
                'position' => 0,
                'title' => 'First answer',
                'remoteVoteCount' => 2,
                'chosen' => 1,
            ]),
            new PollOption([
                'pollOptionId' => 102,
                'pollId' => 100,
                'position' => 1,
                'title' => 'Second answer',
                'remoteVoteCount' => 1,
                'chosen' => 0,
            ]),
        ];
    }
}

class TwinParityPoll extends Poll
{
    public function toDOM(): \DOMElement
    {
        $element = parent::toDOM();
        $classes = array_filter(
            explode(' ', $element -> getAttribute('class')),
            static fn (string $class): bool => $class !== self::class
        );
        $element -> setAttribute('class', implode(' ', $classes));

        return $element;
    }

    protected function optionList(bool $show_results, int $total_votes): PollOptionList
    {
        return new TwinParityPollOptionList([
            'pollId' => (int) $this -> pollId,
            'viewerId' => $this -> viewerId,
            'showResults' => $show_results,
            'multiple' => (int) $this -> multiple === 1,
            'totalVotes' => $total_votes,
        ]);
    }
}

/**
 * @var array<string, array{
 *     class: string,
 *     build: callable(): HTMLObject,
 *     payload: callable(HTMLObject): array<string, mixed>
 * }>
 */
$definitions = [
    'Anchor' => [
        'class' => Anchor::class,
        'build' => static fn (): HTMLObject => parityPrimitive(
            new Anchor('/parity-target'),
            ['data-kind' => 'primitive'],
            ['Anchor text']
        ),
        'payload' => static fn (): array => [
            'properties' => ['href' => '/parity-target'],
            'className' => 'ParityState',
            'attributes' => ['data-kind' => 'primitive'],
            'content' => ['Anchor text'],
        ],
    ],
    'Article' => [
        'class' => Article::class,
        'build' => static fn (): HTMLObject => parityPrimitive(new Article(), [], ['Article text']),
        'payload' => static fn (): array => [
            'properties' => [],
            'className' => 'ParityState',
            'content' => ['Article text'],
        ],
    ],
    'AvatarImage' => [
        'class' => AvatarImage::class,
        'build' => static fn (): HTMLObject => Avatar::create(true, '/uploads/avatars/7-thumb.jpg', 'Åsa', 7),
        'payload' => static fn (AvatarImage $avatar): array => [
            'imageURL' => $avatar -> imageURL,
            'name' => $avatar -> name,
            'userId' => $avatar -> userId,
        ],
    ],
    'AvatarInitial' => [
        'class' => AvatarInitial::class,
        'build' => static fn (): HTMLObject => Avatar::create(false, null, '🦊 Fox', 9),
        'payload' => static fn (AvatarInitial $avatar): array => [
            'name' => $avatar -> name,
            'userId' => $avatar -> userId,
        ],
    ],
    'BannedUser' => [
        'class' => BannedUser::class,
        'build' => static fn (): HTMLObject => new BannedUser([
            'userId' => 84,
            'slug' => 'banned',
            'title' => 'Banned User',
            'hasAvatar' => 0,
        ]),
        'payload' => static fn (BannedUser $user): array => BannedUser::payloadFor($user),
    ],
    'Button' => [
        'class' => Button::class,
        'build' => static function (): HTMLObject {
            $button = new Button(['type' => 'submit']);

            return parityPrimitive($button, ['data-kind' => 'primitive'], ['Button text']);
        },
        'payload' => static fn (): array => [
            'properties' => ['type' => 'submit'],
            'className' => 'ParityState',
            'attributes' => ['data-kind' => 'primitive'],
            'content' => ['Button text'],
        ],
    ],
    'DeltaRenderer' => [
        'class' => DeltaRenderer::class,
        'build' => static fn (): HTMLObject => new DeltaRenderer([
            ['insert' => "A bold line\n", 'attributes' => ['bold' => true]],
            ['insert' => "Second line\n"],
        ]),
        'payload' => static fn (): array => [
            'ops' => [
                ['insert' => "A bold line\n", 'attributes' => ['bold' => true]],
                ['insert' => "Second line\n"],
            ],
            'customEmoji' => [],
            'mentionsAreLocal' => true,
        ],
    ],
    'Div' => [
        'class' => Div::class,
        'build' => static fn (): HTMLObject => parityPrimitive(new Div(), [], ['Div text']),
        'payload' => static fn (): array => [
            'properties' => [],
            'className' => 'ParityState',
            'content' => ['Div text'],
        ],
    ],
    'EntityCounted' => [
        'class' => Entity::class,
        'build' => static fn (): HTMLObject => new Entity([
            'entityId' => 4,
            'type' => 'hashtag',
            'slug' => 'testing',
            'title' => 'Testing',
            'postCount' => 12,
        ]),
        'payload' => static fn (Entity $entity): array => $entity -> payload(),
    ],
    'EntityBare' => [
        'class' => Entity::class,
        'build' => static fn (): HTMLObject => new Entity([
            'entityId' => 5,
            'type' => 'person',
            'slug' => 'someone',
            'title' => 'Someone',
            'postCount' => null,
        ]),
        'payload' => static fn (Entity $entity): array => $entity -> payload(),
    ],
    'Image' => [
        'class' => Image::class,
        'build' => static function (): HTMLObject {
            $image = new Image(['src' => '/images/parity.png', 'alt' => 'Parity image']);

            return parityPrimitive($image, ['data-kind' => 'primitive']);
        },
        'payload' => static fn (): array => [
            'properties' => ['src' => '/images/parity.png', 'alt' => 'Parity image'],
            'className' => 'ParityState',
            'attributes' => ['data-kind' => 'primitive'],
        ],
    ],
    'Poll' => [
        'class' => Poll::class,
        'build' => static fn (): HTMLObject => new TwinParityPoll([
            'pollId' => 100,
            'multiple' => 0,
            'endsAt' => '2000-01-01 00:00:00',
            'remoteVotersCount' => 3,
            'viewerId' => null,
        ]),
        'payload' => static fn (TwinParityPoll $poll): array => $poll -> toPayload(),
    ],
    'RelativeTimeFull' => [
        'class' => RelativeTime::class,
        'build' => static fn (): HTMLObject => new RelativeTime('2026-08-11 15:04:00'),
        'payload' => static fn (): array => [
            'dateString' => '2026-08-11 15:04:00',
            'fallbackFormat' => RelativeTime::FULL,
        ],
    ],
    'RelativeTimeDateOnly' => [
        'class' => RelativeTime::class,
        'build' => static fn (): HTMLObject => new RelativeTime('2026-08-11 15:04:00', RelativeTime::DATE_ONLY),
        'payload' => static fn (): array => [
            'dateString' => '2026-08-11 15:04:00',
            'fallbackFormat' => RelativeTime::DATE_ONLY,
        ],
    ],
    'ToggleButtonDefault' => [
        'class' => ToggleButton::class,
        'build' => static fn (): HTMLObject => parityToggle(['Follow', 'Following']),
        'payload' => static fn (): array => [
            'labels' => ['Follow', 'Following'],
            'showing' => null,
            'className' => 'ParityToggleButton',
            'pressable' => true,
        ],
    ],
    'ToggleButtonPressed' => [
        'class' => ToggleButton::class,
        'build' => static fn (): HTMLObject => parityToggle(['Follow', 'Following'], 'Following'),
        'payload' => static fn (): array => [
            'labels' => ['Follow', 'Following'],
            'showing' => 'Following',
            'className' => 'ParityToggleButton',
            'pressable' => true,
        ],
    ],
    'User' => [
        'class' => User::class,
        'build' => static fn (): HTMLObject => new User([
            'userId' => 21,
            'slug' => 'asa',
            'title' => 'Åsa',
            'description' => null,
            'hasAvatar' => 0,
            'createdAt' => '2026-08-11 15:04:00',
        ]),
        'payload' => static fn (User $user): array => $user -> jsonSerialize(),
    ],
    'Post' => [
        'class' => Post::class,
        'build' => static fn (): HTMLObject => parityPost(),
        'payload' => static fn (Post $post): array => $post -> toPayload(0, 0, false, false),
    ],
    'Message' => [
        'class' => Message::class,
        'build' => static fn (): HTMLObject => parityMessage(),
        'payload' => static fn (Message $message): array => $message -> jsonSerialize(),
    ],
    'Notification' => [
        'class' => Notification::class,
        'build' => static fn (): HTMLObject => parityNotification(),
        'payload' => static fn (Notification $notification): array => Notification::rowsToPayload([$notification])[0],
    ],
    'OtherUser' => [
        'class' => OtherUser::class,
        'build' => static fn (): HTMLObject => parityOtherUser(),
        'payload' => static fn (OtherUser $user): array => OtherUser::payloadFor($user, null),
    ],
    'ReceivedFriendRequest' => [
        'class' => ReceivedFriendRequest::class,
        'build' => static fn (): HTMLObject => parityReceivedFriendRequest(),
        'payload' => static fn (ReceivedFriendRequest $user): array => array_merge(
            OtherUser::payloadFor($user, null),
            ['friendshipId' => $user -> friendshipId]
        ),
    ],
    'Report' => [
        'class' => Report::class,
        'build' => static fn (): HTMLObject => parityReport(),
        'payload' => static fn (Report $report): array => $report -> toPayload(),
    ],
    'Span' => [
        'class' => Span::class,
        'build' => static fn (): HTMLObject => parityPrimitive(new Span(), [], ['Span text']),
        'payload' => static fn (): array => [
            'properties' => [],
            'className' => 'ParityState',
            'content' => ['Span text'],
        ],
    ],
    'Time' => [
        'class' => Time::class,
        'build' => static function (): HTMLObject {
            $time = new Time(['datetime' => '2026-08-11T15:04:00+00:00']);

            return parityPrimitive($time, [], ['Time text']);
        },
        'payload' => static fn (): array => [
            'properties' => ['datetime' => '2026-08-11T15:04:00+00:00'],
            'className' => 'ParityState',
            'content' => ['Time text'],
        ],
    ],
    'UserBio' => [
        'class' => UserBio::class,
        'build' => static fn (): HTMLObject => new UserBio([
            'description' => 'Find https://example.com and #Testing.',
        ]),
        'payload' => static fn (): array => [
            'description' => 'Find https://example.com and #Testing.',
        ],
    ],
];

$cases = [];

foreach ($definitions as $name => $definition) {
    $payload_object = $definition['build']();
    $payload = $definition['payload']($payload_object);
    $render_object = $definition['build']();
    useBareTwinDocument();

    $cases[$name] = [
        'class' => $definition['class'],
        'payload' => $payload,
        'canonical' => DOMCanonicalForm::lines($render_object -> toDOM()),
    ];
}

echo json_encode($cases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
