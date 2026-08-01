<?php

declare(strict_types=1);

/**
 * Paging the published collections. A popular account's followers do not fit in
 * one response, and a collection that tries anyway gets slower the more it has
 * to say.
 */
class ActivityPubCollectionTest extends TestCase
{
    public function testACollectionDescribesItselfAndPointsAtItsFirstPage(): void
    {
        $document = ActivityPubCollection::describe('https://example.test/users/x/followers', 57);

        $this -> assertSame('OrderedCollection', $document['type']);
        $this -> assertSame(57, $document['totalItems']);
        $this -> assertSame('https://example.test/users/x/followers?page=1', $document['first']);
    }

    public function testAnEmptyCollectionPointsNowhere(): void
    {
        // A first link into an empty page says there is something to read when
        // there is not.
        $document = ActivityPubCollection::describe('https://example.test/users/x/followers', 0);

        $this -> assertSame(0, $document['totalItems']);
        $this -> assertFalse(isset($document['first']));
    }

    public function testAPageCarriesItsItemsAndSaysWhatItIsPartOf(): void
    {
        $document = ActivityPubCollection::page('https://example.test/users/x/followers', 57, 2, ['a', 'b']);

        $this -> assertSame('OrderedCollectionPage', $document['type']);
        $this -> assertSame('https://example.test/users/x/followers', $document['partOf']);
        $this -> assertSame(['a', 'b'], $document['orderedItems']);
    }

    public function testAMiddlePageLinksBothWays(): void
    {
        $document = ActivityPubCollection::page('https://example.test/c', 57, 2, []);

        $this -> assertSame('https://example.test/c?page=3', $document['next']);
        $this -> assertSame('https://example.test/c?page=1', $document['prev']);
    }

    public function testTheFirstPageHasNoPrevious(): void
    {
        $this -> assertFalse(isset(ActivityPubCollection::page('https://example.test/c', 57, 1, [])['prev']));
    }

    public function testTheLastPageHasNoNext(): void
    {
        // A next link leading to an empty page makes a crawler walk forever.
        $total = ActivityPubCollection::PAGE_SIZE * 3;
        $document = ActivityPubCollection::page('https://example.test/c', $total, 3, []);

        $this -> assertFalse(isset($document['next']));
    }

    public function testAPageExactlyFillingTheCollectionHasNoNext(): void
    {
        // The boundary: page 1 of exactly PAGE_SIZE items is the whole thing.
        $document = ActivityPubCollection::page('https://example.test/c', ActivityPubCollection::PAGE_SIZE, 1, []);

        $this -> assertFalse(isset($document['next']));
    }

    public function testOneMoreThanAPageHasASecond(): void
    {
        $document = ActivityPubCollection::page('https://example.test/c', ActivityPubCollection::PAGE_SIZE + 1, 1, []);

        $this -> assertSame('https://example.test/c?page=2', $document['next']);
    }

    public function testAPageBelowOneIsTreatedAsTheFirst(): void
    {
        $document = ActivityPubCollection::page('https://example.test/c', 57, -3, []);

        $this -> assertSame('https://example.test/c?page=1', $document['id']);
        $this -> assertFalse(isset($document['prev']));
    }
}
