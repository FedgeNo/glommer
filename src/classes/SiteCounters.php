<?php

declare(strict_types=1);

/**
 * How the site is doing, in numbers, beside the daemons that keep it running.
 *
 * Every figure says exactly what it counts rather than something rounder that
 * would need trusting - who was here is who was here, and who posted is who
 * posted, because on a quiet site those are very different numbers and an
 * admin reading one for the other would draw the wrong conclusion.
 *
 * The delivery figures come from Statistics rather than from the queue. A
 * queue holds only what has not been dealt with yet, so counting it says
 * nothing about how much got through; those are counted as they happen and
 * read back over a window. The queue depth is still worth showing beside
 * them, as the backlog rather than the outcome.
 */
class SiteCounters extends Div
{
    public ?string $class = 'SiteCounters';
    public array $mixins = ['d-flex', 'flex-column', 'gap-1'];

    /** What counts as recent, for every figure that says "this week". */
    private const RECENT_DAYS = 7;

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $counts = self::counts();
        $waiting_to_read = RelayFetch::pendingCount();

        $this -> addLine(str_replace(
            ['{count}', '{joined}', '{days}'],
            [(string) $counts -> members, (string) $counts -> joinedThisWeek, (string) self::RECENT_DAYS],
            (string) ($words['members'] ?? '')
        ));

        $this -> addLine(str_replace(
            ['{days}', '{count}', '{posted}'],
            [(string) self::RECENT_DAYS, (string) User::activeSince(self::RECENT_DAYS), (string) $counts -> postedThisWeek],
            (string) ($words['activeMembers'] ?? '')
        ));

        $this -> addLine(str_replace(
            ['{count}', '{recent}', '{days}'],
            [(string) $counts -> posts, (string) $counts -> postsThisWeek, (string) self::RECENT_DAYS],
            (string) ($words['posts'] ?? '')
        ));

        $delivered = Statistic::since(Statistic::DELIVERED, self::RECENT_DAYS);
        $undeliverable = Statistic::since(Statistic::UNDELIVERABLE, self::RECENT_DAYS);

        $delivery_line = $this -> addLine(str_replace(
            ['{days}', '{delivered}', '{undeliverable}'],
            [(string) self::RECENT_DAYS, (string) $delivered, (string) $undeliverable],
            (string) ($words['deliveries'] ?? '')
        ));

        // Given up on more than got through: something is wrong with this
        // server's signing, its network, or the servers it talks to - and it
        // is not the sort of thing anybody notices without being told.
        if ($undeliverable > $delivered) {
            $delivery_line -> class = 'Error';
        }

        $this -> addLine(str_replace(
            ['{count}', '{failing}'],
            [(string) $counts -> deliveriesQueued, (string) $counts -> deliveriesFailing],
            (string) ($words['queued'] ?? '')
        ));

        $this -> addLine(str_replace('{count}', (string) $waiting_to_read, (string) ($words['pendingReads'] ?? '')));

        return parent::toDOM();
    }

    private function addLine(string $text): Paragraph
    {
        $line = new Paragraph($text);
        $this -> addContent($line);

        return $line;
    }

    /**
     * Every figure in one query. Correlated subselects rather than a query
     * apiece: this is a panel somebody opens, not a hot path, but it is also
     * seven round trips that do not need to be.
     */
    private static function counts(): SiteCountersData
    {
        $since = date('Y-m-d H:i:s', time() - self::RECENT_DAYS * 86400);

        return DB::row('
SELECT
    (SELECT COUNT(*) FROM `Users` WHERE `remoteActorURI` IS NULL) AS `members`,
    (SELECT COUNT(*) FROM `Users` WHERE `remoteActorURI` IS NULL AND `createdAt` >= ?) AS `joinedThisWeek`,
    (SELECT COUNT(DISTINCT `Posts`.`userId`)
        FROM `Posts`
        JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
        WHERE `Users`.`remoteActorURI` IS NULL AND `Posts`.`createdAt` >= ?) AS `postedThisWeek`,
    (SELECT COUNT(*) FROM `Posts` WHERE `remoteObjectURI` IS NULL) AS `posts`,
    (SELECT COUNT(*) FROM `Posts` WHERE `remoteObjectURI` IS NULL AND `createdAt` >= ?) AS `postsThisWeek`,
    (SELECT COUNT(*) FROM `FediverseDeliveries`) AS `deliveriesQueued`,
    (SELECT COUNT(*) FROM `FediverseDeliveries` WHERE `attempts` > 0) AS `deliveriesFailing`
', 'SiteCountersData', 'sss', $since, $since, $since) ?? new SiteCountersData();
    }
}
