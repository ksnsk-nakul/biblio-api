<?php

namespace App\Actions\Book;

use App\Models\Book;
use App\Models\BookChapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class IngestEpubBook
{
    public function execute(UploadedFile $file, int $folderId): Book
    {
        $hash = hash_file('sha256', $file->getRealPath());

        if (Book::where('file_hash', $hash)->exists()) {
            throw ValidationException::withMessages([
                'file' => 'This exact epub has already been uploaded.',
            ])->status(409);
        }

        $epubPath = "epubs/{$hash}.epub";
        Storage::disk('local')->put($epubPath, fopen($file->getRealPath(), 'r'));

        try {
            $metadata = $this->parseEpub($file->getRealPath(), $file->getClientOriginalName(), $hash);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($epubPath);
            throw $e;
        }

        return DB::transaction(function () use ($folderId, $hash, $epubPath, $metadata) {
            $book = Book::create([
                'folder_id' => $folderId,
                'title' => $metadata['title'],
                'author' => $metadata['author'],
                'series_name' => $metadata['series_name'],
                'volume_number' => $metadata['volume_number'],
                'cover_path' => $metadata['cover_path'],
                'file_path' => $epubPath,
                'file_hash' => $hash,
                'chapter_count' => count($metadata['chapters']),
                'embedding_status' => 'none',
            ]);

            foreach ($metadata['chapters'] as $chapter) {
                BookChapter::create([
                    'book_id' => $book->id,
                    'index' => $chapter['index'],
                    'title' => $chapter['title'],
                    'spine_href' => $chapter['spine_href'],
                ]);
            }

            return $book->load('chapters');
        });
    }

    protected function parseEpub(string $path, string $originalFilename, string $hash): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'file' => 'Unable to open the uploaded epub file.',
            ]);
        }

        try {
            $containerXml = $zip->getFromName('META-INF/container.xml');

            if (! $containerXml) {
                throw ValidationException::withMessages([
                    'file' => 'The epub is missing META-INF/container.xml.',
                ]);
            }

            $container = $this->loadXml($containerXml);
            $container->registerXPathNamespace('c', 'urn:oasis:names:tc:opendocument:xmlns:container');
            $rootfiles = $container->xpath('//c:rootfile[@full-path]');

            if (empty($rootfiles)) {
                throw ValidationException::withMessages([
                    'file' => 'Could not locate the OPF package file inside the epub.',
                ]);
            }

            $opfPath = (string) $rootfiles[0]['full-path'];
            $opfXmlRaw = $zip->getFromName($opfPath);

            if (! $opfXmlRaw) {
                throw ValidationException::withMessages([
                    'file' => 'Could not read the OPF package file inside the epub.',
                ]);
            }

            $opf = $this->loadXml($opfXmlRaw);
            $opf->registerXPathNamespace('opf', 'http://www.idpf.org/2007/opf');
            $opf->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

            $opfDir = dirname($opfPath);
            $opfDir = $opfDir === '.' ? '' : $opfDir;

            $title = $this->firstXpathValue($opf, '//dc:title') ?? pathinfo($originalFilename, PATHINFO_FILENAME);
            $author = $this->firstXpathValue($opf, '//dc:creator') ?? 'Unknown';

            [$seriesName, $volumeNumber] = $this->extractSeriesFromOpf($opf);

            if ($seriesName === null) {
                [$seriesName, $volumeNumber] = $this->extractSeriesFromFilename($originalFilename);
            }

            $manifest = $this->buildManifestMap($opf);
            $tocTitles = $this->buildTocTitleMap($zip, $opf, $manifest, $opfDir);
            $chapters = $this->buildChapterList($opf, $manifest, $tocTitles);

            $coverPath = $this->extractCover($zip, $opf, $manifest, $opfDir, $hash);

            return [
                'title' => $title,
                'author' => $author,
                'series_name' => $seriesName,
                'volume_number' => $volumeNumber,
                'cover_path' => $coverPath,
                'chapters' => $chapters,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * Real-world epubs occasionally contain invalid XML (e.g. a bare `&` in an
     * id/href attribute). Escape stray ampersands that aren't part of a valid
     * entity reference before handing the string to SimpleXMLElement.
     */
    protected function loadXml(string $xml): SimpleXMLElement
    {
        $sanitized = preg_replace('/&(?!(?:amp|lt|gt|apos|quot|#\d+|#x[0-9a-fA-F]+);)/', '&amp;', $xml);

        return new SimpleXMLElement($sanitized);
    }

    protected function firstXpathValue(SimpleXMLElement $xml, string $expression): ?string
    {
        $nodes = $xml->xpath($expression);

        if (empty($nodes)) {
            return null;
        }

        $value = trim((string) $nodes[0]);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    protected function extractSeriesFromOpf(SimpleXMLElement $opf): array
    {
        $metaNodes = $opf->xpath('//opf:meta[@name]') ?: [];

        $series = null;
        $index = null;

        foreach ($metaNodes as $meta) {
            $name = (string) $meta['name'];
            $content = (string) $meta['content'];

            if ($name === 'calibre:series' && $content !== '') {
                $series = $content;
            }

            if ($name === 'calibre:series_index' && $content !== '') {
                $index = (int) round((float) $content);
            }
        }

        return [$series, $series !== null ? $index : null];
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    protected function extractSeriesFromFilename(string $filename): array
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        if (preg_match('/^(.*?)[\s._\-–]+vol(?:ume)?\.?\s*(\d+)/i', $name, $matches)) {
            $series = trim($matches[1], " \t\n\r\0\x0B-–_");

            return $series === '' ? [null, null] : [$series, (int) $matches[2]];
        }

        return [null, null];
    }

    /**
     * @return array<string, array{href: string, properties: string}>
     */
    protected function buildManifestMap(SimpleXMLElement $opf): array
    {
        $map = [];

        foreach ($opf->xpath('//opf:manifest/opf:item') ?: [] as $item) {
            $id = (string) $item['id'];
            $map[$id] = [
                'href' => (string) $item['href'],
                'media_type' => (string) $item['media-type'],
                'properties' => (string) $item['properties'],
            ];
        }

        return $map;
    }

    /**
     * @param array<string, array{href: string, properties: string}> $manifest
     * @return array<string, string> href (without fragment) => title
     */
    protected function buildTocTitleMap(ZipArchive $zip, SimpleXMLElement $opf, array $manifest, string $opfDir): array
    {
        $titles = [];

        // EPUB3 nav document.
        foreach ($manifest as $item) {
            if (str_contains($item['properties'], 'nav')) {
                $navPath = $this->resolvePath($opfDir, $item['href']);
                $navXml = $zip->getFromName($navPath);

                if ($navXml) {
                    $titles = $this->parseNavTitles($navXml);
                }

                break;
            }
        }

        if (! empty($titles)) {
            return $titles;
        }

        // EPUB2 NCX fallback.
        $ncxHref = null;
        $spine = $opf->xpath('//opf:spine')[0] ?? null;
        $tocId = $spine ? (string) $spine['toc'] : null;

        if ($tocId && isset($manifest[$tocId])) {
            $ncxHref = $manifest[$tocId]['href'];
        }

        if ($ncxHref) {
            $ncxPath = $this->resolvePath($opfDir, $ncxHref);
            $ncxXml = $zip->getFromName($ncxPath);

            if ($ncxXml) {
                $titles = $this->parseNcxTitles($ncxXml);
            }
        }

        return $titles;
    }

    /**
     * @return array<string, string>
     */
    protected function parseNavTitles(string $navXml): array
    {
        $titles = [];

        try {
            $nav = $this->loadXml($navXml);
            $nav->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
            $links = $nav->xpath('//x:nav[@*[local-name()="type"]="toc"]//x:a[@href]')
                ?: $nav->xpath('//x:a[@href]');

            foreach ($links ?: [] as $a) {
                $href = ltrim(explode('#', (string) $a['href'])[0], './');
                $text = trim((string) $a);

                if ($href !== '' && $text !== '' && ! isset($titles[$href])) {
                    $titles[$href] = $text;
                }
            }
        } catch (\Throwable) {
            // Best effort only.
        }

        return $titles;
    }

    /**
     * @return array<string, string>
     */
    protected function parseNcxTitles(string $ncxXml): array
    {
        $titles = [];

        try {
            $ncx = $this->loadXml($ncxXml);
            $ncx->registerXPathNamespace('n', 'http://www.daisy.org/z3986/2005/ncx/');
            $navPoints = $ncx->xpath('//n:navPoint') ?: [];

            foreach ($navPoints as $navPoint) {
                $content = $navPoint->xpath('.//n:content[@src]');
                $label = $navPoint->xpath('.//n:navLabel//n:text');

                if (! empty($content) && ! empty($label)) {
                    $href = ltrim(explode('#', (string) $content[0]['src'])[0], './');
                    $text = trim((string) $label[0]);

                    if ($href !== '' && $text !== '' && ! isset($titles[$href])) {
                        $titles[$href] = $text;
                    }
                }
            }
        } catch (\Throwable) {
            // Best effort only.
        }

        return $titles;
    }

    /**
     * @param array<string, array{href: string, properties: string}> $manifest
     * @param array<string, string> $tocTitles
     * @return array<int, array{index: int, title: string, spine_href: string}>
     */
    protected function buildChapterList(SimpleXMLElement $opf, array $manifest, array $tocTitles): array
    {
        $chapters = [];
        $itemRefs = $opf->xpath('//opf:spine/opf:itemref[@idref]') ?: [];

        foreach ($itemRefs as $i => $itemRef) {
            $idref = (string) $itemRef['idref'];
            $href = $manifest[$idref]['href'] ?? $idref;
            $bareHref = ltrim(explode('#', $href)[0], './');

            $chapters[] = [
                'index' => $i,
                'title' => $tocTitles[$bareHref] ?? ('Chapter '.($i + 1)),
                'spine_href' => $href,
            ];
        }

        return $chapters;
    }

    /**
     * @param array<string, array{href: string, properties: string}> $manifest
     */
    protected function extractCover(ZipArchive $zip, SimpleXMLElement $opf, array $manifest, string $opfDir, string $hash): ?string
    {
        $coverHref = null;

        foreach ($manifest as $item) {
            if (str_contains($item['properties'], 'cover-image')) {
                $coverHref = $item['href'];
                break;
            }
        }

        if (! $coverHref) {
            $metaNodes = $opf->xpath('//opf:meta[@name="cover"]') ?: [];

            if (! empty($metaNodes)) {
                $coverId = (string) $metaNodes[0]['content'];
                $coverHref = $manifest[$coverId]['href'] ?? null;
            }
        }

        if (! $coverHref) {
            return null;
        }

        $coverZipPath = $this->resolvePath($opfDir, $coverHref);
        $coverData = $zip->getFromName($coverZipPath);

        if (! $coverData) {
            return null;
        }

        $extension = strtolower(pathinfo($coverHref, PATHINFO_EXTENSION)) ?: 'jpg';
        $coverPath = "covers/{$hash}.{$extension}";

        Storage::disk('local')->put($coverPath, $coverData);

        return $coverPath;
    }

    protected function resolvePath(string $baseDir, string $relative): string
    {
        if ($baseDir === '') {
            $combined = $relative;
        } else {
            $combined = $baseDir.'/'.$relative;
        }

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
}
