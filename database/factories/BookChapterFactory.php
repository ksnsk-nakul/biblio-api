<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BookChapter>
 */
class BookChapterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'index' => 0,
            'title' => fake()->sentence(2),
            'spine_href' => 'chapter1.xhtml',
        ];
    }
}
