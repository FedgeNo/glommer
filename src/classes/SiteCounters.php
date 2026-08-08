<?php

declare(strict_types=1);

/**
 * How the site is doing, in numbers, beside the daemons that keep it running.
 *
 * Every figure says exactly what it counts rather than something rounder that
 * would need trusting. In particular there is no "active users" here: nothing
 * records a visit, so the closest honest thing is who has posted, and that is
 * what it is called.
 *
 * The federation figures are of the queue, not of history. A delivery that
 * succeeds is deleted, so what is left to count is what has not gone yet -
 * "failing" means a delivery still waiting that has already been refused at
 * least once, which is the number worth watching. A rate over all time is not
 * something this server keeps and is not implied.
 */
class SiteCounters extends Div
{
    public ?string $class = 'SiteCounters';
    public array $mixins = ['d-flex', 'flex-column', 'gap-1'];

    /** What counts as recent, for every figure that says "this week". */
    private const RECENT_DAYS = 7;

    public function toDOM(): \DOMElement
    {
        $counts = self::counts();
        $waiting_to_read = RelayFetch::pendingCount();

        $this -> addContent(new Heading3('Numbers'));

        $this -> addLine('Members: ' . $counts -> members
            . ' (' . $counts -> joinedThisWeek . ' joined in the last ' . self::RECENT_DAYS . ' days)');

        $this -> addLine('Members who posted in the last ' . self::RECENT_DAYS . ' days: ' . $counts -> postedThisWeek);

        $this -> addLine('Posts written here: ' . $counts -> posts
            . ' (' . $counts -> postsThisWeek . ' in the last ' . self::RECENT_DAYS . ' days)');

        $queued = 'Federation deliveries waiting: ' . $counts -> deliveriesQueued;

        if ((int) $counts -> deliveriesQueued > 0) {
            $queued .= ', ' . $counts -> deliveriesFailing . ' of them already refused once or more';
        }

        $delivery_line = $this -> addLine($queued);

        // A queue that is all failures is a server nobody can reach, or a key
        // nobody accepts - worth the eye going to it.
        if ((int) $counts -> deliveriesQueued > 0 && $counts -> deliveriesFailing === $counts -> deliveriesQueued) {
            $delivery_line -> class = 'Error';
        }

        $this -> addLine('Posts waiting to be read from other servers: ' . $waiting_to_read);

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
