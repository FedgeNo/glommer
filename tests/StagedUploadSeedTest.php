<?php

declare(strict_types=1);

/**
 * A staged link-preview image exists before the post that will own it, so the
 * only thing tying it to anyone is its name. Without that, any signed-in
 * member who learned a name could discard someone else's staged file or
 * attach it to their own post.
 */
class StagedUploadSeedTest extends TestCase
{
    private function configured(): bool
    {
        $secret = (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', '');

        return strlen($secret) === 64;
    }

    public function testASeedKeepsTheShapeTheUploadPipelineNamesFilesBy(): void
    {
        $this -> assertSame(1, preg_match('/^lp-[a-f0-9]{32}$/', StagedUploadSeed::issue(7)));
    }

    public function testTheMemberWhoStagedItIsRecognised(): void
    {
        $this -> assertTrue(StagedUploadSeed::belongsTo(StagedUploadSeed::issue(7), 7));
    }

    public function testAnotherMemberIsRefused(): void
    {
        if (!$this -> configured()) {
            return;
        }

        $this -> assertFalse(StagedUploadSeed::belongsTo(StagedUploadSeed::issue(7), 8));
    }

    /**
     * Two seeds for the same member must not be interchangeable either - the
     * random half is what makes each name its own file.
     */
    public function testTwoSeedsForOneMemberDiffer(): void
    {
        $this -> assertFalse(StagedUploadSeed::issue(7) === StagedUploadSeed::issue(7));
    }

    public function testAMalformedOrForgedNameIsNobodys(): void
    {
        $this -> assertFalse(StagedUploadSeed::belongsTo('lp-nothexadecimal', 7));
        $this -> assertFalse(StagedUploadSeed::belongsTo('', 7));
        $this -> assertFalse(StagedUploadSeed::belongsTo('../../etc/passwd', 7));

        if ($this -> configured()) {
            $this -> assertFalse(StagedUploadSeed::belongsTo('lp-' . str_repeat('a', 32), 7));
        }
    }
}
