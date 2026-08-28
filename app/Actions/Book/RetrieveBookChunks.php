<?php

namespace App\Actions\Book;

use App\Models\Book;
use App\Models\BookChunk;
use App\Services\OpenAiClient;
use Illuminate\Database\Eloquent\Collection;
use Pgvector\Laravel\Vector;

class RetrieveBookChunks
{
    public function __construct(protected OpenAiClient $openAi) {}

    /**
     * Retrieves the top-k chunks for a single book, ranked by cosine
     * distance to the embedded query. Never crosses book boundaries.
     */
    public function execute(Book $book, string $query, int $topK = 5): Collection
    {
        [$embedding] = $this->openAi->embed([$query]);

        $queryVector = new Vector($embedding);

        return BookChunk::query()
            ->where('book_id', $book->id)
            ->orderByRaw('embedding <=> ?', [$queryVector])
            ->limit($topK)
            ->get();
    }
}
