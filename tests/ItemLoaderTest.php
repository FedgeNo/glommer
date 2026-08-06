<?php

declare(strict_types=1);

/**
 * What a self-loading list hands the client to grow itself with. The offset is
 * the client's own count of what it already shows, so everything else the next
 * page needs - which feed, whose profile, which tag - has to be in the rendered
 * scroll config or the endpoint has no way to know what was asked for.
 *
 * Each list here is subclassed to supply its own rows, so these assert on the
 * rendered markup without a database behind them.
 */
class ItemLoaderTest extends TestCase
{
    /**
     * Renders a list into a throwaway document, standing in for the shared one
     * the app builds a real page into.
     */
    private function elementFor(ItemLoader $list): \DOMElement
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        return $list -> toDOM();
    }

    /** @return array<string, mixed> */
    private function scrollConfigFor(ItemLoader $list): array
    {
        $config = json_decode($this -> elementFor($list) -> getAttribute('data-infinite-scroll'), true);

        return is_array($config) ? $config : [];
    }

    /**
     * A full page, so the list advertises that there's another one to fetch.
     * Public because each list below supplies its rows from an anonymous
     * subclass, which is its own scope.
     */
    public static function fullPage(): array
    {
        return array_fill(0, ItemLoader::PAGE_SIZE + 1, 'item');
    }

    public function testAProfileFeedPagesWithTheProfileItBelongsTo(): void
    {
        $feed = new class(['userId' => 7]) extends ProfileFeedList {
            protected function rows(): array
            {
                return ItemLoaderTest::fullPage();
            }
        };

        $config = $this -> scrollConfigFor($feed);

        $this -> assertSame('user', $config['feedType'] ?? null);
        $this -> assertSame(7, $config['userId'] ?? null, 'api/feed.php rejects a user feed with no userId');
    }

    public function testATagFeedPagesWithItsTag(): void
    {
        $feed = new class(['tag' => 'php']) extends TagFeedList {
            protected function rows(): array
            {
                return ItemLoaderTest::fullPage();
            }
        };

        $config = $this -> scrollConfigFor($feed);

        $this -> assertSame('tag', $config['feedType'] ?? null);
        $this -> assertSame('php', $config['tag'] ?? null, 'api/feed.php rejects a tag feed with no tag');
    }

    public function testAPagingUserListStillNamesWhichListItIs(): void
    {
        $owner = new User();
        $owner -> userId = 3;

        $list = new class(['user' => $owner]) extends FriendList {
            protected function rows(): array
            {
                return ItemLoaderTest::fullPage();
            }
        };

        $element = $this -> elementFor($list);

        // Accepting a friend request moves the card from the incoming list to
        // the friends list, and finds both by this attribute.
        $this -> assertSame('friends', $element -> getAttribute('data-list-type'));
        $this -> assertTrue($element -> hasAttribute('data-infinite-scroll'), 'a full page should still advertise the next one');
    }

    public function testAListWithNothingFurtherToFetchAdvertisesNoScroll(): void
    {
        $feed = new class(['userId' => 7]) extends ProfileFeedList {
            protected function rows(): array
            {
                return ['only', 'a', 'few'];
            }
        };

        $this -> assertFalse($this -> elementFor($feed) -> hasAttribute('data-infinite-scroll'));
    }

    public function testAMessageThreadPagesWithWhoItIsWith(): void
    {
        $thread = new class(['otherUserId' => 9]) extends MessageList {
            protected function rows(): array
            {
                return ItemLoaderTest::fullPage();
            }
        };

        $this -> assertSame(9, $this -> scrollConfigFor($thread)['otherUserId'] ?? null);
    }

    public function testAnEmptyListIsNotRenderedAtAll(): void
    {
        // "No messages yet." is not a message, so it is not a row - and with no
        // rows there is no list for one to sit in.
        $empty = new class() extends MessageList {
            protected function rows(): array
            {
                return [];
            }
        };

        $element = $this -> elementFor($empty);

        $this -> assertSame('p', $element -> tagName);
        $this -> assertSame('No messages yet.', trim($element -> textContent));
    }

    public function testAListWithRowsIsStillAList(): void
    {
        $filled = new class() extends MessageList {
            protected function rows(): array
            {
                return ItemLoaderTest::fullPage();
            }
        };

        $this -> assertSame('ul', $this -> elementFor($filled) -> tagName);
    }

    public function testASearchFeedLeavesItsPagingToTheClient(): void
    {
        // The next page depends on what's currently typed, so Search.js owns it;
        // a server-side config here would bind a second scroller to the list.
        $feed = new class() extends SearchFeedList {
            protected function rows(): array
            {
                return ItemLoaderTest::fullPage();
            }
        };

        $this -> assertFalse($this -> elementFor($feed) -> hasAttribute('data-infinite-scroll'));
    }
}
