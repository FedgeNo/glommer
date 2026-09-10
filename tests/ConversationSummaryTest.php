<?php

declare(strict_types=1);

class ConversationSummaryTest extends DatabaseTestCase
{
    private static function latest(int $user, int $partner): ?int
    {
        return DB::row('SELECT `lastMessageId` FROM `Conversations` WHERE `userId` = ? AND `partnerId` = ?', 'stdClass', 'ii', $user, $partner) ?-> lastMessageId;
    }

    public function testSendsMaintainBothInboxesAndTheirOrder(): void
    {
        $a = self::createUser();
        $b = self::createUser();
        $c = self::createUser();

        try {
            Message::create($a, $b, 'first');
            $reply = Message::create($b, $a, null, 'opaque ciphertext', 'commitment');
            $newest = Message::create($c, $a, 'new conversation');
            $this -> assertSame($reply, self::latest($a, $b));
            $this -> assertSame($reply, self::latest($b, $a));
            $this -> assertSame($newest, self::latest($a, $c));
            $this -> assertSame([$c, $b], array_map(static fn (Conversation $row): int => $row -> userId, (new ConversationList(['userId' => $a])) -> items));

            DB::run('UPDATE `Users` SET `banned` = 1 WHERE `userId` = ?', 'i', $c);
            $this -> assertSame([$b], array_map(static fn (Conversation $row): int => $row -> userId, (new ConversationList(['userId' => $a])) -> items));
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?, ?)', 'iii', $a, $b, $c);
        }
    }

    public function testDeletingTheLatestMessageRestoresThePreviousOne(): void
    {
        $a = self::createUser();
        $b = self::createUser();

        try {
            $first = Message::create($a, $b, 'first');
            $middle = Message::create($b, $a, 'middle');
            $last = Message::create($a, $b, 'last');
            Message::delete($middle);
            $this -> assertSame($last, self::latest($a, $b));
            Message::delete($last);
            Message::delete($last);
            $this -> assertSame($first, self::latest($a, $b));
            $this -> assertSame($first, self::latest($b, $a));
            Message::delete($first);
            $this -> assertNull(self::latest($a, $b));
            $this -> assertNull(self::latest($b, $a));
            $this -> assertCount(0, (new ConversationList(['userId' => $a])) -> items);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?)', 'ii', $a, $b);
        }
    }

    public function testDuplicateFederationMessageDoesNotChangeTheSummary(): void
    {
        $a = self::createUser();
        $b = self::createUser();
        $uri = 'https://messages.example/' . bin2hex(random_bytes(8));

        try {
            Message::create($a, $b, 'remote', remote_uri: $uri);
            $last = Message::create($b, $a, 'reply');
            $duplicate = false;

            try {
                Message::create($a, $b, 'remote', remote_uri: $uri);
            } catch (\mysqli_sql_exception $exception) {
                $duplicate = $exception -> getCode() === 1062;
            }

            $this -> assertTrue($duplicate);
            $this -> assertSame($last, self::latest($a, $b));
            $this -> assertSame($last, self::latest($b, $a));
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?)', 'ii', $a, $b);
        }
    }

    public function testInstallerBackfillsHistoryAndAccountDeletionRemovesBothEntries(): void
    {
        $a = self::createUser();
        $b = self::createUser();

        try {
            self::createMessage($a, $b);
            $last = self::createMessage($b, $a);
            $this -> assertNull(self::latest($a, $b));

            for ($pass = 0; $pass < 2; $pass++) {
                SchemaInstaller::runMaintenance(DB::connection());
                $this -> assertSame($last, self::latest($a, $b));
                $this -> assertSame($last, self::latest($b, $a));
            }

            User::delete($b);
            $this -> assertNull(self::latest($a, $b));
            $this -> assertNull(self::latest($b, $a));
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?)', 'ii', $a, $b);
        }
    }
}
