<?php

declare(strict_types=1);

class ReportResolutionTest extends DatabaseTestCase
{
    public function testALateAuditFailureRollsBackTheDecisionAndKeepsTheReport(): void
    {
        $session = $_SESSION ?? [];
        $user = self::createUser();
        $_SESSION = ['userId' => $user];
        DB::run('INSERT INTO `Posts` (`userId`, `description`) VALUES (?, ?)', 'is', $user, 'Reported text');
        $post_id = (int) mysqli_insert_id(DB::connection());
        ReportManager::create($user, 'post', $post_id, 'Review this');
        $report_id = (int) mysqli_insert_id(DB::connection());

        mysqli_query(DB::connection(), '
CREATE TRIGGER `ReportResolutionFailAudit` BEFORE INSERT ON `ModerationActions` FOR EACH ROW
SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'forced moderation log failure\'');
        try {
            foreach (['dismissReport', 'classifyReportedContent'] as $action) {
                try {
                    ReportManager::resolve($report_id, $action, static function (ReportData $report) use ($action): void {
                        if ($action === 'dismissReport') {
                            ReportManager::markContentDismissed($report -> type, (int) $report -> targetId);
                        } else {
                            Post::classify((int) $report -> targetId, true);
                        }
                    });
                    $this -> assertTrue(false, 'the injected failure must escape');
                } catch (\mysqli_sql_exception $exception) {
                    $this -> assertTrue(str_contains($exception -> getMessage(), 'forced moderation log failure'));
                }
                $this -> assertNotNull(ReportManager::find($report_id));
                $this -> assertFalse(ReportManager::isContentDismissed('post', $post_id));
                $post = DB::row('SELECT `sensitive` FROM `Posts` WHERE `postId` = ?', 'Post', 'i', $post_id);
                $this -> assertSame(0, $post -> sensitive);
            }
        } finally {
            mysqli_query(DB::connection(), 'DROP TRIGGER `ReportResolutionFailAudit`');
            $_SESSION = $session;
            ReportManager::delete($report_id);
        }
    }

    public function testAResolvedReportCannotApplyTheDecisionTwice(): void
    {
        $session = $_SESSION ?? [];
        $id = self::createUser();
        $_SESSION = ['userId' => $id];
        ReportManager::create($id, 'user', $id, 'Review account');
        $report_id = (int) mysqli_insert_id(DB::connection());
        $decisions = 0;
        $decide = static function (ReportData $report) use (&$decisions): void { $decisions++; };
        try {
            $this -> assertTrue(ReportManager::resolve($report_id, 'dismissReport', $decide));
            $this -> assertFalse(ReportManager::resolve($report_id, 'dismissReport', $decide));
            $this -> assertSame(1, $decisions);
            $this -> assertNotNull(DB::row('SELECT `actionId` FROM `ModerationActions` WHERE `reportId` = ?',
                'ModerationAction', 'i', $report_id));
        } finally {
            $_SESSION = $session;
            ReportManager::delete($report_id);
            DB::run('DELETE FROM `ModerationActions` WHERE `reportId` = ?', 'i', $report_id);
        }
    }
}
