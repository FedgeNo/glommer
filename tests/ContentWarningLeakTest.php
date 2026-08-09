<?php

declare(strict_types=1);

/**
 * The places a warned post's writing can escape the thing covering it.
 *
 * The gate on the card is the easy half. A post is also named in a browser
 * tab, in the heading above itself, on a share card, in structured data and in
 * a feed reader - none of which have anywhere to put a warning, and all of
 * which derive what they say from the post's own words when it has no title.
 * Every one of those is a way to publish exactly what the warning withheld,
 * and none of them looks wrong from the page the warning is on.
 */
class ContentWarningLeakTest extends DatabaseTestCase
{
    private const SPOILER = 'the butler did it';

    private static function post(?string $warning, ?string $title = null): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `title`, `description`, `descriptionDelta`, `sensitive`, `contentWarning`)
    VALUES (?, ?, ?, ?, ?, ?)
', 'isssis', self::createUser(), $title, self::SPOILER,
            json_encode([['insert' => self::SPOILER . "\n"]]),
            $warning === null ? 0 : 1,
            $warning);

        return (int) mysqli_insert_id(DB::connection());
    }

    /** @return array<string, string> tag name => text, for one rendered feed item */
    private static function tagsOf(RSSItem $item): array
    {
        (new \ReflectionProperty(XMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $tags = [];

        foreach ($item -> toDOM() -> childNodes as $child) {
            $tags[$child -> nodeName] = $child -> textContent;
        }

        return $tags;
    }

    /** @return array<string, string> the same, for a post read the way a feed reads one */
    private static function feedItem(int $post_id): array
    {
        return self::tagsOf(DB::row('
SELECT `Posts`.`postId`, `Posts`.`title`, `Posts`.`description`, `Posts`.`contentWarning`,
       `Posts`.`createdAt`, `Users`.`slug` AS `authorSlug`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ?
', 'RSSItem', 'i', $post_id));
    }

    public function testAFeedReaderIsGivenTheWarningRatherThanTheWriting(): void
    {
        $item = self::feedItem(self::post('Spoilers for the finale'));

        $this -> assertSame('Spoilers for the finale', $item['title'] ?? '');
        $this -> assertSame('Spoilers for the finale', $item['description'] ?? '');
        $this -> assertFalse(
            str_contains(implode(' ', $item), self::SPOILER),
            'the writing must not reach a reader that cannot cover it'
        );
    }

    /** Its own title is behind the gate too, so it may not stand in either. */
    public function testATitledWarnedPostGivesUpNeitherToAFeedReader(): void
    {
        $item = self::feedItem(self::post('Spoilers', 'Who dies at the end'));

        $this -> assertSame('Spoilers', $item['title'] ?? '');
        $this -> assertFalse(str_contains(implode(' ', $item), 'Who dies'));
    }

    /** An unwarned post reaches a reader whole, the way it always did. */
    public function testAnUnwarnedPostIsUnchanged(): void
    {
        $item = self::feedItem(self::post(null));

        $this -> assertSame(self::SPOILER, $item['description'] ?? '');
    }

    /**
     * The whole feed, rendered. A query that forgets to select the column
     * hydrates a null and hands the writing over while every unit below still
     * passes - so this asks the thing that actually ships.
     */
    public function testTheSiteFeedDoesNotSyndicateWhatAWarningCovers(): void
    {
        $warning = 'Spoilers ' . bin2hex(random_bytes(4));
        $post_id = self::post($warning);

        $mine = null;

        foreach ((new SiteRSSFeed()) -> items as $item) {
            if ((int) $item -> postId === $post_id) {
                $mine = $item;

                break;
            }
        }

        $this -> assertNotNull($mine, 'the post reaches the site feed at all');

        $tags = self::tagsOf($mine);

        $this -> assertSame($warning, $tags['title'] ?? '');
        $this -> assertFalse(
            str_contains(implode(' ', $tags), self::SPOILER),
            'the writing it covers is not in the feed'
        );
    }
}
