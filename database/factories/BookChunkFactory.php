<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;
use Pgvector\Laravel\Vector;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BookChunk>
 */
class BookChunkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'chapter_index' => 0,
            'content' => fake()->paragraph(),
            'embedding' => new Vector(array_fill(0, 1536, 0.01)),
        ];
    }
}
