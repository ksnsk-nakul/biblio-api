<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Builds small, deterministic epub fixtures on the fly (rather than
 * committing binary blobs) for use in upload/ingest tests.
 */
class EpubBuilder
{
    /**
     * A minimal-but-valid single-chapter EPUB3 archive, with an optional
     * cover image, for happy-path ingest tests.
     */
    public static function valid(array $options = []): UploadedFile
    {
        $title = $options['title'] ?? 'Test Book';
        $author = $options['author'] ?? 'Test Author';
        $withCover = $options['with_cover'] ?? false;
        $filename = $options['filename'] ?? 'test-book.epub';
        $seed = $options['seed'] ?? uniqid('epub_', true);

        $path = tempnam(sys_get_temp_dir(), 'epub_').'.epub';

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('mimetype', 'application/epub+zip');

        $zip->addFromString('META-INF/container.xml', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
              <rootfiles>
                <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
              </rootfiles>
            </container>
            XML);

        $manifestItems = '<item id="chap1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>'
            .'<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>';

        if ($withCover) {
            $manifestItems .= '<item id="cover-img" href="cover.jpg" media-type="image/jpeg" properties="cover-image"/>';
        }

        $zip->addFromString('OEBPS/content.opf', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
              <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
                <dc:title>{$title}</dc:title>
                <dc:creator>{$author}</dc:creator>
                <dc:identifier id="bookid">urn:uuid:{$seed}</dc:identifier>
              </metadata>
              <manifest>
                {$manifestItems}
              </manifest>
              <spine>
                <itemref idref="chap1"/>
              </spine>
            </package>
            XML);

        $zip->addFromString('OEBPS/nav.xhtml', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
              <body>
                <nav epub:type="toc">
                  <ol>
                    <li><a href="chapter1.xhtml">Chapter One</a></li>
                  </ol>
                </nav>
              </body>
            </html>
            XML);

        $zip->addFromString('OEBPS/chapter1.xhtml', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <html xmlns="http://www.w3.org/1999/xhtml">
              <body>
                <h1>Chapter One</h1>
                <p>Once upon a time, in a test suite far away, there lived some words.</p>
              </body>
            </html>
            XML);

        if ($withCover) {
            // 1x1 pixel JPEG.
            $jpegBytes = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAj/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=');
            $zip->addFromString('OEBPS/cover.jpg', $jpegBytes);
        }

        $zip->close();

        return new UploadedFile($path, $filename, 'application/epub+zip', null, true);
    }

    /**
     * An epub-extension file missing META-INF/container.xml entirely.
     */
    public static function missingContainer(string $filename = 'broken.epub'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'epub_').'.epub';

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('readme.txt', 'not a real epub');
        $zip->close();

        return new UploadedFile($path, $filename, 'application/epub+zip', null, true);
    }

    /**
     * A plain non-zip, non-epub file with an .epub extension rejected purely
     * by the isValidEpub() zip-open check (belt and suspenders alongside the
     * `extensions:epub` rule).
     */
    public static function notAZip(string $filename = 'not-a-zip.epub'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'epub_').'.epub';
        file_put_contents($path, 'This is just plain text, not a zip archive.');

        return new UploadedFile($path, $filename, 'application/epub+zip', null, true);
    }

    /**
     * A file with a disallowed extension, to exercise the `extensions:epub`
     * validation rule.
     */
    public static function wrongExtension(string $filename = 'not-a-book.txt'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'txt_').'.txt';
        file_put_contents($path, 'plain text content');

        return new UploadedFile($path, $filename, 'text/plain', null, true);
    }
}
