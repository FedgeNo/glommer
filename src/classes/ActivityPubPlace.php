<?php

declare(strict_types=1);

/**
 * Where a post was written, for the rest of the network.
 *
 * ActivityStreams has had a place for this since the beginning - a `location`
 * holding a Place with a latitude and a longitude - so that is what goes out,
 * as coordinates rather than as prose somebody would have to parse back.
 *
 * Almost nothing shows it. Mastodon neither reads the property nor renders it,
 * and most implementations follow Mastodon, so a post whose only mention of
 * where it was written is that property arrives somewhere it cannot be seen.
 * A link to the map here goes in the content as well, which is the part a
 * person actually reads - and it leads back to the same map a reader here
 * would open, at the same point.
 *
 * Every post carrying a location sends it. Everything written here is public,
 * and a location is already shown on the map to anybody who looks; a post that
 * should not say where it came from is one written without a location.
 */
class ActivityPubPlace
{
    /**
     * The location property for a post, or null where it has none.
     *
     * @return array<string, mixed>|null
     */
    public static function forPost(int $post_id): ?array
    {
        $coordinates = PostLocation::forPosts([$post_id])[$post_id] ?? null;

        if ($coordinates === null) {
            return null;
        }

        $place = [
            'type' => 'Place',
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
        ];

        // What the gazetteer calls the nearest place, where there is one close
        // enough to name it. A Place has a name in the vocabulary, and a
        // reader is being told where this was written - which a town answers
        // and two decimal figures do not.
        $named = Place::nearest($coordinates['latitude'], $coordinates['longitude']) ?-> label();

        if ($named !== null && $named !== '') {
            $place['name'] = $named;
        }

        return $place;
    }

    /**
     * The same point as a link, appended to a post's content.
     *
     * Its own paragraph, so it reads as a note about the post rather than as
     * the end of the last sentence of it.
     */
    /**
     * The same point as a link, to go in the post's content.
     *
     * Handed back as something to render rather than as markup: it is built
     * into the same document the body is, in the same pass, so the escaping
     * and the shape of an element stay the render system's to know.
     */
    public static function linkFor(array $place): HTMLObject
    {
        $paragraph = new Paragraph();
        $paragraph -> addContent(new Anchor(
            ServerURL::absolute('/map?lat=' . (string) $place['latitude'] . '&lng=' . (string) $place['longitude']),
            self::label($place)
        ));

        return $paragraph;
    }

    /**
     * What the link says. The place's name where the gazetteer had one, and
     * the coordinates otherwise - somewhere out at sea or far from any named
     * thing still has to say where it was.
     */
    private static function label(array $place): string
    {
        if (is_string($place['name'] ?? null) && $place['name'] !== '') {
            return '📍 ' . $place['name'];
        }

        return '📍 ' . number_format((float) $place['latitude'], 4)
            . ', ' . number_format((float) $place['longitude'], 4);
    }
}
