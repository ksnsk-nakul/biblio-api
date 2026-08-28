<?php

namespace App\Jobs;

use App\Models\Book;
use App\Services\OpenAiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pgvector\Laravel\Vector;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

class EmbedBookChunks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** Approximate token proxy: ~750 words per chunk (~500-800 token window). */
    protected const WORDS_PER_CHUNK = 750;

    /** Chunks per OpenAI embeddings request, to keep payloads reasonable. */
    protected const EMBED_BATCH_SIZE = 100;

    public function __construct(public int $bookId) {}

    public function handle(OpenAiClient $openAi): void
    {
        $book = Book::findOrFail($this->bookId);

        DB::table('book_chunks')->where('book_id', $book->id)->delete();

        try {
            $chunks = $this->extractChunks($book);

            if (empty($chunks)) {
                throw new RuntimeException("No extractable text found for book {$book->id}.");
            }

            foreach (array_chunk($chunks, self::EMBED_BATCH_SIZE) as $batch) {
                $embeddings = $openAi->embed(array_column($batch, 'content'));

                $rows = [];

                foreach ($batch as $i => $chunk) {
                    $rows[] = [
                        'book_id' => $book->id,
                        'chapter_index' => $chunk['chapter_index'],
                        'content' => $chunk['content'],
                        'embedding' => (string) new Vector($embeddings[$i]),
                    ];
                }

                DB::table('book_chunks')->insert($rows);
            }

            $book->update(['embedding_status' => 'ready']);
        } catch (Throwable $e) {
            Log::error('Embedding book chunks failed.', [
                'book_id' => $book->id,
                'error' => $e->getMessage(),
            ]);

            $book->update(['embedding_status' => 'failed']);

            throw $e;
        }
    }

    /**
     * @return array<int, array{chapter_index: int, content: string}>
     */
    protected function extractChunks(Book $book): array
    {
        $zip = new ZipArchive();
        $path = Storage::disk('local')->path($book->file_path);

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open epub at {$path}.");
        }

        try {
            $opfDir = $this->locateOpfDir($zip);
            $chunks = [];

            foreach ($book->chapters()->orderBy('index')->get() as $chapter) {
                $text = $this->extractChapterText($zip, $opfDir, $chapter->spine_href);

                if ($text === '') {
                    continue;
                }

                foreach ($this->chunkText($text) as $chunkText) {
                    $chunks[] = [
                        'chapter_index' => $chapter->index,
                        'content' => $chunkText,
                    ];
                }
            }

            return $chunks;
        } finally {
            $zip->close();
        }
    }

    /**
     * Mirrors IngestEpubBook's container.xml/OPF resolution so chapter hrefs
     * resolve the same way here as they did at ingest time.
     */
    protected function locateOpfDir(ZipArchive $zip): string
    {
        $containerXml = $zip->getFromName('META-INF/container.xml');

        if (! $containerXml) {
            throw new RuntimeException('Epub is missing META-INF/container.xml.');
        }

        $container = $this->loadXml($containerXml);
        $container->registerXPathNamespace('c', 'urn:oasis:names:tc:opendocument:xmlns:container');
        $rootfiles = $container->xpath('//c:rootfile[@full-path]');

        if (empty($rootfiles)) {
            throw new RuntimeException('Epub container.xml has no rootfile.');
        }

        $opfPath = (string) $rootfiles[0]['full-path'];
        $opfDir = dirname($opfPath);

        return $opfDir === '.' ? '' : $opfDir;
    }

    protected function extractChapterText(ZipArchive $zip, string $opfDir, string $spineHref): string
    {
        $bareHref = explode('#', $spineHref)[0];
        $zipPath = $this->resolvePath($opfDir, $bareHref);
        $html = $zip->getFromName($zipPath);

        if (! $html) {
            return '';
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text ?? '');
    }

    /**
     * @return string[]
     */
    protected function chunkText(string $text): array
    {
        $words = array_values(array_filter(explode(' ', $text), fn ($w) => $w !== ''));

        if (empty($words)) {
            return [];
        }

        return array_map(
            fn (array $group) => implode(' ', $group),
            array_chunk($words, self::WORDS_PER_CHUNK)
        );
    }

    protected function resolvePath(string $baseDir, string $relative): string
    {
        $combined = $baseDir === '' ? $relative : $baseDir.'/'.$relative;
        $parts = [];

        foreach (explode('/', $combined) as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }

            if ($segment === '..') {
                array_pop($parts);
                continue;
            }

            $parts[] = $segment;
        }

        return implode('/', $parts);
    }

    /**
     * Same ampersand-escaping tolerance as IngestEpubBook, for consistency
     * with real-world epubs that contain invalid bare `&` characters.
     */
    protected function loadXml(string $xml): SimpleXMLElement
    {
        $sanitized = preg_replace('/&(?!(?:amp|lt|gt|apos|quot|#\d+|#x[0-9a-fA-F]+);)/', '&amp;', $xml);

        return new SimpleXMLElement($sanitized);
    }
}
