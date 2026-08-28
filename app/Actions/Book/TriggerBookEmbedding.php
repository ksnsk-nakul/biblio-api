<?php

namespace App\Actions\Book;

use App\Jobs\EmbedBookChunks;
use App\Models\Book;

class TriggerBookEmbedding
{
    /**
     * Idempotently kicks off embedding for a book. Returns the resulting
     * status and whether a new job was dispatched by this call.
     *
     * @return array{status: string, triggered: bool}
     */
    public function execute(Book $book): array
    {
        if (in_array($book->embedding_status, ['processing', 'ready'], true)) {
            return ['status' => $book->embedding_status, 'triggered' => false];
        }

        $book->update(['embedding_status' => 'processing']);

        EmbedBookChunks::dispatch($book->id);

        return ['status' => 'processing', 'triggered' => true];
    }
}
