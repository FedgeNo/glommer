<?php

declare(strict_types=1);

class PostLocationPrivacyTest extends TestCase
{
    public function testAnonymousLinksAndPayloadsUseMapPrecisionWhileMembersKeepExactCoordinates(): void
    {
        $session = $_SESSION ?? [];
        try {
            foreach ([false, true] as $member) {
                $_SESSION = $member ? ['userId' => 7] : [];
                $post = new Post(['postId' => 12, 'latitude' => 49.1234567, 'longitude' => -123.7654321,
                    'liked' => false, 'bookmarked' => false, 'reposted' => false]);
                $expected_lat = $member ? 49.1234567 : 49.12;
                $expected_lng = $member ? -123.7654321 : -123.77;
                $payload = $post -> toPayload(false, false);
                $this -> assertSame($expected_lat, $payload['latitude']);
                $this -> assertSame($expected_lng, $payload['longitude']);
                $element = (new PostLocationLink($post -> latitude, $post -> longitude)) -> toDOM();
                parse_str((string) parse_url($element -> getAttribute('href'), PHP_URL_QUERY), $query);
                $this -> assertSame($expected_lat, (float) $query['lat']);
                $this -> assertSame($expected_lng, (float) $query['lng']);
                $this -> assertSame(49.1234567, $post -> latitude, 'rendering must not change the stored location');
            }
        } finally {
            $_SESSION = $session;
        }
    }
}
