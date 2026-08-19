<?php

declare(strict_types=1);

class HashtagBackfillTest extends DatabaseTestCase
{
    public function testBackfillCrossesMoreThanOneBatch(): void
    {
        $user_id = self::createUser();
        $first_id = 0;
        $last_id = 0;

        DB::transaction(static function () use ($user_id, &$first_id, &$last_id): void {
            for ($index = 0; $index <= 500; $index++) {
                $tag = $index === 0 ? 'firstbatch' : ($index === 500 ? 'secondbatch' : '');
                $text = $tag === '' ? "plain\n" : '#' . $tag . "\n";

                DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?)
', 'iss', $user_id, trim($text), json_encode(['ops' => [['insert' => $text]]]));

                $post_id = (int) mysqli_insert_id(DB::connection());
                $first_id = $first_id === 0 ? $post_id : $first_id;
                $last_id = $post_id;
            }
        });

        Hashtag::backfill();

        $rows = DB::rows('
SELECT `PostHashtags`.`postId`, `Hashtags`.`slug`
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    WHERE `PostHashtags`.`postId` IN (?, ?)
    ORDER BY `PostHashtags`.`postId`
', \stdClass::class, 'ii', $first_id, $last_id);

        $this -> assertSame($first_id, (int) ($rows[0] -> postId ?? 0));
        $this -> assertSame('firstbatch', $rows[0] -> slug ?? null);
        $this -> assertSame($last_id, (int) ($rows[1] -> postId ?? 0));
        $this -> assertSame('secondbatch', $rows[1] -> slug ?? null);
    }
}
