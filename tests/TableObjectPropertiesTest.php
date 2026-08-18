<?php

declare(strict_types=1);

/** Every base-table column belongs on that table's singular domain object. */
class TableObjectPropertiesTest extends TestCase
{
    private const TABLE_OBJECTS = [
        'Users' => User::class,
        'Posts' => Post::class,
        'PostLocations' => PostLocation::class,
        'Places' => Place::class,
        'Polls' => Poll::class,
        'PollOptions' => PollOption::class,
        'Hashtags' => Hashtag::class,
        'FeedItems' => FeedItem::class,
        'RetiredUsernames' => RetiredUsername::class,
        'CustomEmojis' => CustomEmoji::class,
        'PinnedPosts' => PinnedPost::class,
        'Bookmarks' => Bookmark::class,
        'Friendships' => Friendship::class,
        'Blocks' => Block::class,
        'Messages' => Message::class,
        'Notifications' => Notification::class,
        'Reports' => Report::class,
        'EmailVerifications' => EmailVerification::class,
        'PasswordResets' => PasswordReset::class,
        'EmailChangeReverts' => EmailChangeRevert::class,
        'Relays' => Relay::class,
        'ActivityPubReplays' => ActivityPubReplay::class,
        'Timelines' => Timeline::class,
        'RemoteFollows' => RemoteFollow::class,
        'FediverseFollowers' => FediverseFollower::class,
        'FediverseDeliveries' => FediverseDelivery::class,
        'FediverseDeliveryRefusals' => FediverseDeliveryRefusal::class,
        'RemoteObjectTombstones' => RemoteObjectTombstone::class,
        'Statistics' => Statistic::class,
        'RememberTokens' => RememberToken::class,
        'LoginFingerprints' => LoginFingerprint::class,
        'ModerationActions' => ModerationAction::class,
        'Entities' => Entity::class,
        'PostTranslations' => PostTranslation::class,
        'StagedPosts' => StagedPost::class,
        'TopicSummaries' => TopicSummary::class,
        'BannedTrendingEntities' => BannedTrendingEntity::class,
    ];

    public function testTableColumnsAreDeclaredOnTheirObjects(): void
    {
        $schema = (string) file_get_contents(__DIR__ . '/../schema.sql');

        preg_match_all('/CREATE TABLE `(\w+)` \((.+?)\) ENGINE[^;]*;/s', $schema, $matches, PREG_SET_ORDER);

        $table_bodies = [];

        foreach ($matches as $match) {
            $table_bodies[$match[1]] = $match[2];
        }

        foreach (self::TABLE_OBJECTS as $table => $class) {
            $this -> assertTrue(isset($table_bodies[$table]), $table . ' is absent from schema.sql');

            preg_match_all('/^\s*`(\w+)`\s+/m', $table_bodies[$table], $columns);

            foreach ($columns[1] as $column) {
                $this -> assertTrue(
                    property_exists($class, $column),
                    $class . ' must declare ' . $table . '.' . $column
                );
            }
        }
    }
}
