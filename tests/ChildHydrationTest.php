<?php

declare(strict_types=1);

class ChildHydrationTest extends TestCase
{
    private function element(HTMLObject $object): \DOMElement
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        return $object -> toDOM();
    }

    public function testFeedItemHydratesTheChosenSubclassWithoutCopyingItsIdentity(): void
    {
        $row = new FeedItemData();
        $row -> itemId = 42;
        $row -> postId = 7;
        $row -> type = 'ImageItem';
        $row -> remoteURL = 'https://remote.example/cat.jpg';
        $row -> altText = 'A cat';

        $item = FeedItem::fromRow($row);
        $this -> assertTrue($item instanceof ImageItem);
        $this -> assertSame(42, $item -> itemId);
        $this -> assertSame(7, $item -> postId);
        $this -> assertSame($row -> remoteURL, $item -> remoteURL);
        $element = $this -> element($item);
        $this -> assertSame('figure', $element -> tagName);
        $this -> assertSame('FeedItem ImageItem', $element -> getAttribute('class'));
        $this -> assertSame('A cat', $element -> getElementsByTagName('img') -> item(0) -> getAttribute('alt'));
    }

    public function testDeferredImageOwnsItsProxyAndDoesNotInheritTheFigureId(): void
    {
        $item = new ImageItem([
            'id' => 'parent-image', 'itemId' => 42,
            'remoteURL' => 'https://remote.example/cat.jpg', 'deferred' => true,
        ]);
        $image = $this -> element(new FeedImage($item));

        $this -> assertFalse($image -> hasAttribute('src'));
        $this -> assertFalse($image -> hasAttribute('id'));
        $this -> assertSame(RemoteMedia::proxyURL(42), $image -> getAttribute('data-src'));
        $this -> assertSame(RemoteMedia::proxyURL(42), $image -> getAttribute('data-full-src'));
        $this -> assertSame('Image', $image -> getAttribute('alt'));
    }

    public function testLinkCardSelectsItsFirstImageAndDoesNotInheritThePostIdAttribute(): void
    {
        $post = new Post([
            'id' => 'parent-post', 'postId' => 7, 'linkURL' => 'https://example.com/',
            'title' => 'A link', 'description' => 'Its description',
        ]);
        $post -> items = [new AudioItem(['itemId' => 41]), new ImageItem(['itemId' => 42]), new ImageItem(['itemId' => 43])];
        $link = $this -> element(new LinkItem($post));
        $images = $link -> getElementsByTagName('img');

        $this -> assertSame(1, $images -> length);
        $this -> assertSame($post -> items[1] -> imageURL(), $images -> item(0) -> getAttribute('src'));
        $this -> assertFalse($link -> hasAttribute('id'));
        $this -> assertFalse($link -> hasAttribute('data-item-id'));
        $this -> assertSame('A link', $link -> getElementsByTagName('h3') -> item(0) -> textContent);
    }

    public function testRemoteLinkPreviewDoesNotInventAThumbnail(): void
    {
        $image = $this -> element(new LinkItemImage(new ImageItem([
            'itemId' => 42, 'remoteURL' => 'https://remote.example/cat.jpg',
        ])));

        $this -> assertFalse($image -> hasAttribute('src'));
        $this -> assertSame('LinkItemImage', $image -> getAttribute('class'));
    }

    public function testInputVariantsOwnTheirAttributesAfterHydration(): void
    {
        foreach (['text', 'email', 'password'] as $type) {
            $field = new InputField('address', 'Address', $type, 'Enter address', 80);
            $field -> id = 'parent-field';
            $field -> value = 'retained';
            $field -> autocomplete = 'off';
            $field -> error = 'Try again';
            $field -> labelVisible = false;
            $element = $this -> element($field);
            $input = $element -> getElementsByTagName('input') -> item(0);
            $label = $element -> getElementsByTagName('label') -> item(0);

            $this -> assertSame($type, $input -> getAttribute('type'));
            $this -> assertSame('retained', $input -> getAttribute('value'));
            $this -> assertSame('80', $input -> getAttribute('maxlength'));
            $this -> assertSame('Enter address', $input -> getAttribute('placeholder'));
            $this -> assertSame('off', $input -> getAttribute('autocomplete'));
            $this -> assertSame('addressError', $input -> getAttribute('aria-describedby'));
            $this -> assertSame('address', $input -> getAttribute('id'));
            $this -> assertSame('address', $label -> getAttribute('for'));
            $this -> assertSame('visually-hidden', $label -> getAttribute('class'));
            $this -> assertFalse($label -> hasAttribute('id'));
        }
    }

    public function testTextareaAndCheckboxOwnTheirValuesAndState(): void
    {
        $field = new TextareaField('body', 'Body', 'Write here', 100);
        $field -> value = '<b>plain text</b>';
        $field -> error = 'Too long';
        $textarea = $this -> element($field) -> getElementsByTagName('textarea') -> item(0);
        $this -> assertSame('<b>plain text</b>', $textarea -> textContent);
        $this -> assertSame(0, $textarea -> getElementsByTagName('b') -> length);
        $this -> assertSame('100', $textarea -> getAttribute('maxlength'));
        $this -> assertSame('Write here', $textarea -> getAttribute('placeholder'));
        $this -> assertSame('bodyError', $textarea -> getAttribute('aria-describedby'));

        foreach ([false, true] as $checked) {
            $field = new CheckboxField('remember', 'Remember');
            $field -> checked = $checked;
            $checkbox = $this -> element($field) -> getElementsByTagName('input') -> item(0);
            $this -> assertSame($checked, $checkbox -> hasAttribute('checked'));
            $this -> assertSame('1', $checkbox -> getAttribute('value'));
            $this -> assertSame('remember', $checkbox -> getAttribute('id'));
        }
    }

    public function testActionBarHydratesPostDataAndOwnsItsPermalink(): void
    {
        $post = new Post([
            'id' => 'parent-post', 'postId' => 7, 'userId' => 2,
            'author' => new User(['slug' => 'someone']), 'replyCount' => 0,
            'likeCount' => 3, 'liked' => true, 'standalone' => true,
        ]);
        $bar = new PostActionBar($post);
        $this -> assertSame(3, $bar -> likeCount);
        $this -> assertTrue($bar -> liked);
        $this -> assertTrue($bar -> standalone);
        $element = $this -> element($bar);
        $xpath = new \DOMXPath(HTMLObject::currentDocument());
        $share = $xpath -> query('.//button[contains(@class, "PostShareButton")]', $element) -> item(0);
        $this -> assertSame(ServerURL::absolute('/users/someone/7'), $share -> getAttribute('data-share-url'));
        $this -> assertFalse($element -> hasAttribute('id'));
    }

    public function testExistingPollOptionKeepsItsOwnIdentityWhenGlommingListState(): void
    {
        $option = new PollOption(['pollOptionId' => 5, 'title' => 'First', 'localVoteCount' => 2]);
        $option -> id = 'option-5';
        $option -> glom((object) [
            'id' => 'parent-list', 'pollId' => 4, 'showResults' => true,
            'multiple' => true, 'totalVotes' => 4, 'contents' => ['wrong'],
        ]);

        $this -> assertSame('option-5', $option -> id);
        $this -> assertSame(5, $option -> pollOptionId);
        $this -> assertSame('First', $option -> title);
        $this -> assertSame(50, $option -> share());
        $this -> assertTrue($option -> showResults);
        $this -> assertTrue($option -> multiple);
        $this -> assertSame([], $option -> contents);
    }
}
