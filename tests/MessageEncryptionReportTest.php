<?php

declare(strict_types=1);

class MessageEncryptionReportTest extends DatabaseTestCase
{
    private function createEncryptedMessage(int $sender_id, int $recipient_id, string $envelope, ?string $tag): int
    {
        DB::run('
INSERT INTO `Messages` (`senderId`, `recipientId`, `bodyCiphertext`, `frankingTag`)
    VALUES (?, ?, ?, ?)
', 'iiss', $sender_id, $recipient_id, $envelope, $tag);

        return (int) mysqli_insert_id(DB::connection());
    }

    public function testAReportSnapshotsTheDecryptedBodyAlongsideTheCiphertext(): void
    {
        $sender = self::createUser();
        $recipient = self::createUser();

        $envelope = '{"v":1,"iv":"' . base64_encode(random_bytes(12)) . '"}';
        $message_id = $this -> createEncryptedMessage($sender, $recipient, $envelope, str_repeat('a', 64));

        $this -> assertTrue(ReportManager::create($recipient, 'message', $message_id, 'harassment', 'the decrypted body'));

        $result = mysqli_stmt_get_result(DB::run('
SELECT `snapshot`
    FROM `Reports`
    WHERE `type` = ? AND `targetId` = ?
', 'si', 'message', $message_id));
        $snapshot = json_decode((string) mysqli_fetch_assoc($result)['snapshot'], true);

        // The moderator reads the verified plaintext; the ciphertext and the
        // franking tag stay in the snapshot as the evidence it came from.
        $this -> assertSame('the decrypted body', $snapshot['body']);
        $this -> assertSame($envelope, $snapshot['bodyCiphertext']);
        $this -> assertSame(str_repeat('a', 64), $snapshot['frankingTag']);
    }

    public function testAPlaintextReportSnapshotsAsBefore(): void
    {
        $sender = self::createUser();
        $recipient = self::createUser();
        $message_id = self::createMessage($sender, $recipient);

        $this -> assertTrue(ReportManager::create($recipient, 'message', $message_id, null));

        $result = mysqli_stmt_get_result(DB::run('
SELECT `snapshot`
    FROM `Reports`
    WHERE `type` = ? AND `targetId` = ?
', 'si', 'message', $message_id));
        $snapshot = json_decode((string) mysqli_fetch_assoc($result)['snapshot'], true);

        $this -> assertNull($snapshot['bodyCiphertext']);
        $this -> assertTrue(is_string($snapshot['body']));
    }
}
