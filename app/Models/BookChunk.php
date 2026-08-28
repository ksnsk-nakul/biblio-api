<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\Vector;

class BookChunk extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    const CREATED_AT = null;

    public $timestamps = false;

    protected $fillable = [
        'book_id',
        'chapter_index',
        'content',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'chapter_index' => 'integer',
            'embedding' => Vector::class,
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
