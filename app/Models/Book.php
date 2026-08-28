<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'folder_id',
        'title',
        'author',
        'series_name',
        'volume_number',
        'cover_path',
        'file_path',
        'file_hash',
        'chapter_count',
        'embedding_status',
    ];

    protected function casts(): array
    {
        return [
            'volume_number' => 'integer',
            'chapter_count' => 'integer',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(BookChapter::class)->orderBy('index');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(BookChunk::class);
    }

    public function shelves(): HasMany
    {
        return $this->hasMany(Shelf::class);
    }

    public function readingProgress(): HasMany
    {
        return $this->hasMany(ReadingProgress::class);
    }
}
