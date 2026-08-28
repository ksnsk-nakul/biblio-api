<?php

namespace App\Jobs;

use App\Actions\Book\IngestEpubBook;
use App\Models\Folder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Finder\Finder;

class BulkImportBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $directory,
        public int $importedByUserId,
    ) {}

    public function handle(IngestEpubBook $ingestEpubBook): void
    {
        $lightNovels = $this->findOrCreateFolder('Light Novels', null);

        $imported = 0;
        $skipped = [];

        foreach (Finder::create()->files()->in($this->directory)->name('*.epub') as $file) {
            $realPath = $file->getRealPath();
            $originalName = $file->getFilename();

            $seriesName = $ingestEpubBook->peekSeriesName($realPath, $originalName);
            $subfolder = $this->findOrCreateFolder($seriesName ?: 'Unsorted', $lightNovels->id);

            $uploadedFile = new UploadedFile($realPath, $originalName, null, null, true);

            try {
                $ingestEpubBook->execute($uploadedFile, $subfolder->id);
                $imported++;
            } catch (ValidationException $e) {
                $skipped[] = $originalName;
                Log::warning('Bulk import skipped a file.', [
                    'file' => $originalName,
                    'reason' => $e->getMessage(),
                ]);

                continue;
            }
        }

        Log::info('Bulk import finished.', [
            'directory' => $this->directory,
            'imported' => $imported,
            'skipped' => $skipped,
        ]);
    }

    protected function findOrCreateFolder(string $name, ?int $parentId): Folder
    {
        return Folder::firstOrCreate(
            ['name' => $name, 'parent_id' => $parentId],
            ['created_by' => $this->importedByUserId],
        );
    }
}
