<?php

declare(strict_types=1);

/**
 * Topics, nested by kind.
 *
 * /topics/ is everything trending, /topics/{type}/ is one kind of it, and
 * /topics/{type}/{slug} is one topic. The parts that can be wrong without
 * anything failing loudly are the ones held here: that a kind the extractor
 * cannot produce is refused rather than served empty, and that a chip's link
 * actually addresses the page it means.
 */
class TopicRouteTest extends DatabaseTestCase
{
    private static function trend(string $type, string $value, float $score = 1.0): void
    {
        $computed_at = date('Y-m-d H:i:s');

        DB::run('
INSERT INTO `TrendingEntities` (`type`, `slug`, `title`, `score`, `postCount`, `userCount`, `computedAt`)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `score` = VALUES(`score`), `computedAt` = VALUES(`computedAt`)
', 'sssdiis', $type, mb_strtolower($value), $value, $score, 3, 3, $computed_at);
    }

    /** Every kind a URL can name is one the extractor can actually produce. */
    public function testOnlyKindsTheExtractorProducesAreRoutable(): void
    {
        foreach (['hashtag', 'person', 'org', 'gpe', 'work_of_art'] as $type) {
            $this -> assertTrue(EntityType::isKnown($type), $type);
        }

        foreach (['nonsense', 'user', '', 'PERSON'] as $type) {
            $this -> assertFalse(EntityType::isKnown($type), $type);
        }
    }

    /** spaCy's vocabulary is not English, so a page never shows it raw. */
    public function testEachKindHasWordsAReaderKnows(): void
    {
        $this -> assertSame('Organization', EntityType::label('org'));
        $this -> assertSame('People', EntityType::plural('person'));
        $this -> assertSame('Places', EntityType::plural('gpe'));

        // Anything unlisted shows as itself rather than vanishing.
        $this -> assertSame('newthing', EntityType::label('newthing'));
    }

    public function testOneKindIsListedWithoutTheOthers(): void
    {
        self::trend('person', 'RouteTestPerson' . bin2hex(random_bytes(3)));
        self::trend('org', 'RouteTestOrg' . bin2hex(random_bytes(3)));

        $people = Trending::ofType('person', 200);
        $orgs = Trending::ofType('org', 200);

        $this -> assertTrue($people !== [], 'the people are there');
        $this -> assertTrue($orgs !== [], 'and so are the organizations');

        foreach ($people as $chip) {
            $this -> assertSame('person', $chip -> type);
        }
    }

    /** One topic, found by the pair that names it. */
    public function testATopicIsFoundByItsKindAndItsSlug(): void
    {
        $name = 'RouteTestTopic' . bin2hex(random_bytes(3));
        self::trend('org', $name);

        $found = Trending::entity('org', mb_strtolower($name));

        $this -> assertNotNull($found);
        $this -> assertSame($name, $found -> title);

        // The same name under a kind it was never filed under is nothing.
        $this -> assertNull(Trending::entity('person', mb_strtolower($name)));
    }

    /**
     * A chip's link has to address the page the router serves, or the whole
     * list leads to 404s and nothing says so.
     */
    public function testAChipLinksAtThePageItsTopicLivesOn(): void
    {
        $chip = new TrendingEntityChip();
        $chip -> type = 'work_of_art';
        $chip -> slug = 'a thing';
        $chip -> title = 'A Thing';

        $this -> assertSame(
            ServerURL::absolute('/topics/work_of_art/a%20thing'),
            $chip -> url(),
            'the slug is escaped, since it can carry spaces and accents'
        );
    }

    /**
     * A topic page is open to anyone, and this site does not represent other
     * servers' writing to the world - so a logged-out visitor sees only what
     * was written here, the same rule the tag page follows.
     */
    public function testALoggedOutVisitorIsShownNothingFromAnotherServer(): void
    {
        $word = 'Topicvisibility' . bin2hex(random_bytes(4));
        $author = self::createUser();

        foreach ([null, 'https://elsewhere.test/notes/' . bin2hex(random_bytes(4))] as $remote) {
            DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?)
', 'isss', $author, $word . ' appears here', (string) json_encode([['insert' => $word . " appears here\n"]]), $remote);
        }

        $was = $_SESSION['userId'] ?? null;

        try {
            unset($_SESSION['userId']);
            Auth::clearUserCache();

            $signed_out = new SearchFeedList(['query' => $word]);

            foreach ($signed_out -> items as $post) {
                $this -> assertNull($post -> remoteObjectURI, 'nothing from elsewhere');
            }

            $this -> assertSame(1, count($signed_out -> items), 'and the local one is still there');

            $_SESSION['userId'] = $author;
            Auth::clearUserCache();

            $this -> assertSame(2, count(new SearchFeedList(['query' => $word]) -> items), 'a member sees both');
        } finally {
            if ($was === null) {
                unset($_SESSION['userId']);
            } else {
                $_SESSION['userId'] = $was;
            }

            Auth::clearUserCache();
        }
    }

    /** The slug travels with the chip, or the link above has nothing to build from. */
    public function testTheSlugComesBackWithTheChip(): void
    {
        $name = 'RouteTestSlug' . bin2hex(random_bytes(3));
        self::trend('gpe', $name);

        $found = Trending::entity('gpe', mb_strtolower($name));

        $this -> assertSame(mb_strtolower($name), $found -> slug);
    }
}
