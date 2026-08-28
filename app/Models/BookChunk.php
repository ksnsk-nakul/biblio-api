<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookChunk extends Model
{
    const UPDATED_AT = null;

    const CREATED_AT = null;

    public $timestamps = false;

    protected $fillable = [
        'book_id',
        'chapter_index',
        'content',
        'embedding',
    ];

    // NOTE: no cast wired for `embedding` yet — Pgvector\Vector doesn't implement
    // Laravel's CastsAttributes contract, and embeddings aren't written/read until
    // the RAG chat phase. Revisit when that phase lands.

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
