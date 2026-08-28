<?php

namespace Database\Factories;

use App\Models\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        $hash = fake()->unique()->sha256();

        return [
            'folder_id' => Folder::factory(),
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'series_name' => null,
            'volume_number' => null,
            'cover_path' => null,
            'file_path' => "epubs/{$hash}.epub",
            'file_hash' => $hash,
            'chapter_count' => 0,
            'embedding_status' => 'none',
        ];
    }

    public function readyForEmbedding(): static
    {
        return $this->state(fn () => ['embedding_status' => 'ready']);
    }

    public function withCover(string $extension = 'jpg'): static
    {
        return $this->state(fn (array $attrs) => [
            'cover_path' => "covers/{$attrs['file_hash']}.{$extension}",
        ]);
    }
}
