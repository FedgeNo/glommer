<?php

declare(strict_types=1);

class ReportListTest extends DatabaseTestCase
{
    public function testTheTargetLookupUsesTheTargetIndex(): void
    {
        $plan = DB::row('EXPLAIN SELECT `reportId` FROM `Reports` WHERE `type` = ? AND `targetId` = ?',
            'stdClass', 'si', 'post', 2147483000);
        $this -> assertSame('type_targetId', $plan -> key);
    }

    public function testADeletedReporterDoesNotHideTheReportOrOfferABanForAMissingAccount(): void
    {
        $reporter = self::createUser();
        $target = self::createUser();
        DB::run('INSERT INTO `Reports` (`reporterId`, `type`, `targetId`, `reason`) VALUES (?, ?, ?, ?)',
            'isis', $reporter, 'user', $target, 'Still needs review');
        $report_id = (int) mysqli_insert_id(DB::connection());

        try {
            User::delete($reporter);
            $reports = array_values(array_filter((new ReportList()) -> items,
                static fn (Report $report): bool => $report -> reportId === $report_id));
            $this -> assertCount(1, $reports);
            $this -> assertNull($reports[0] -> reporterUsername);
            $element = $reports[0] -> toDOM();
            $xpath = new \DOMXPath($element -> ownerDocument);
            $this -> assertSame(0, $xpath -> query('.//a[contains(@href, "/users//")]', $element) -> length);
            $this -> assertSame(0, $xpath -> query('.//button[@data-user-id="' . $reporter . '"]', $element) -> length);
            $this -> assertTrue(str_contains($element -> textContent, 'Still needs review'));
            $this -> assertTrue(str_contains($element -> textContent, (string) Strings::for('BlockedServerCard')['deletedAccount']));
        } finally {
            DB::run('DELETE FROM `Reports` WHERE `reportId` = ?', 'i', $report_id);
        }
    }
}
