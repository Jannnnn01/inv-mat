<?php

declare(strict_types=1);

use App\Services\LocalAttachmentStorage;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class LocalAttachmentStorageTest extends CIUnitTestCase
{
    public function testPrivateObjectCanBeStoredReadAndDiscarded(): void
    {
        $root = WRITEPATH . 'tests/storage-' . bin2hex(random_bytes(5));
        $testDirectory = WRITEPATH . 'tests';
        if (! is_dir($testDirectory)) {
            mkdir($testDirectory, 0700, true);
        }
        $source = tempnam($testDirectory, 'attachment-');
        $this->assertNotFalse($source);
        file_put_contents($source, 'private-document');

        $storage = new LocalAttachmentStorage($root);
        $storage->put('movements/1/2026/07/test.pdf', $source, 'application/pdf');
        $this->assertSame('private-document', $storage->read('movements/1/2026/07/test.pdf'));
        $storage->discardUncommitted('movements/1/2026/07/test.pdf');

        $this->assertFileDoesNotExist($root . '/movements/1/2026/07/test.pdf');
        @unlink($source);
    }

    public function testPathTraversalIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);

        (new LocalAttachmentStorage(WRITEPATH . 'tests/storage'))->read('../secret.pdf');
    }
}
