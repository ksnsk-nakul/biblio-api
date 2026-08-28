<?php

use App\Jobs\BulkImportBooks;
use App\Models\Book;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EpubBuilder;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->create();
});

function bulkImportTempDir(): string
{
    $dir = sys_get_temp_dir().'/bulk_import_'.uniqid();
    mkdir($dir, recursive: true);

    return $dir;
}

it('imports each epub into a series subfolder under Light Novels, and skips duplicates', function () {
    $dir = bulkImportTempDir();

    $bookA = EpubBuilder::valid(['title' => 'Series Book 1', 'seed' => 'series-a-1']);
    $bookC = EpubBuilder::valid(['title' => 'Other Book', 'seed' => 'series-b-1']);

    copy($bookA->getRealPath(), $dir.'/one.epub');
    copy($bookA->getRealPath(), $dir.'/one-duplicate.epub'); // byte-identical copy -> same file_hash, must be skipped
    copy($bookC->getRealPath(), $dir.'/two.epub');

    (new BulkImportBooks($dir, $this->admin->id))->handle(app(\App\Actions\Book\IngestEpubBook::class));

    expect(Book::count())->toBe(2);

    $lightNovels = Folder::where('name', 'Light Novels')->whereNull('parent_id')->first();
    expect($lightNovels)->not->toBeNull();

    // Every imported book lives in a subfolder of Light Novels.
    foreach (Book::all() as $book) {
        expect($book->folder->parent_id)->toBe($lightNovels->id);
    }
});
