<?php

declare(strict_types=1);

class UploadProcessorTest extends TestCase
{
    public function testATypeWithoutADisplayExtensionIsRefused(): void
    {
        try {
            UploadProcessor::srcPath(1, 'UnknownItem');
        } catch (\InvalidArgumentException $exception) {
            $this -> assertTrue(str_contains($exception -> getMessage(), 'UnknownItem'));

            return;
        }

        $this -> assertTrue(false, 'Expected an InvalidArgumentException');
    }
}
