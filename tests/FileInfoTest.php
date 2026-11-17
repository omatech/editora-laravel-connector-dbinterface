<?php

namespace Tests;

use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;

final class FileInfoTest extends TestCase
{
    public function test_file_size_comes_from_the_disk_without_a_public_copy(): void
    {
        Storage::fake('editora_public');
        Storage::disk('editora_public')->put('uploads/fixture.pdf', str_repeat('x', 1024));

        self::assertFileDoesNotExist(public_path('uploads/fixture.pdf'));
        self::assertSame('(PDF 1.00KB)', _fileInfo('/uploads/fixture.pdf'));
    }

    public function test_an_empty_file_has_zero_bytes_and_a_missing_file_has_no_description(): void
    {
        Storage::fake('editora_public');
        Storage::disk('editora_public')->put('uploads/empty.pdf', '');

        self::assertSame('(PDF 0.00B)', _fileInfo('/uploads/empty.pdf'));
        self::assertSame('', _fileInfo('/uploads/absent.pdf'));
    }

    public function test_metadata_uses_the_same_disk_and_prefix_as_public_media(): void
    {
        config(['editora-media.disk' => 'fixture_public', 'editora-media.prefix' => 'cms/uploads']);
        Storage::fake('fixture_public');
        Storage::disk('fixture_public')->put('cms/uploads/fixture.pdf', str_repeat('x', 2048));

        self::assertSame('(PDF 2.00KB)', _fileInfo('/uploads/fixture.pdf'));
    }
    public function test_remote_metadata_never_downloads_the_file(): void
    {
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->expects(self::once())->method('exists')->with('uploads/remote.pdf')->willReturn(true);
        $disk->expects(self::once())->method('size')->with('uploads/remote.pdf')->willReturn(1536);
        $disk->expects(self::never())->method('get');
        $disk->expects(self::never())->method('readStream');
        $manager = $this->createMock(FilesystemManager::class);
        $manager->expects(self::once())->method('disk')->with('editora_public')->willReturn($disk);
        Storage::swap($manager);

        self::assertSame('(PDF 1.50KB)', _fileInfo('uploads/remote.pdf'));
    }

    public function test_paths_outside_public_uploads_do_not_reach_storage(): void
    {
        $manager = $this->createMock(FilesystemManager::class);
        $manager->expects(self::never())->method('disk');
        Storage::swap($manager);
        foreach (['', '/private/secret.pdf', '/uploads/private/secret.pdf', '/uploads/../secret.pdf', 'https://other.invalid/file.pdf'] as $path) {
            self::assertSame('', _fileInfo($path));
        }
    }
}
