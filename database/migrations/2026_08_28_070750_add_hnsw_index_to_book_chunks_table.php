<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// HNSW over IVFFlat: no training-data-present-at-build-time requirement, and
// this is a small-to-medium single-library dataset. Requires pgvector >= 0.5.0
// (installed extension is 0.8.6, confirmed via `SELECT extversion FROM pg_extension`).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX book_chunks_embedding_hnsw_idx ON book_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS book_chunks_embedding_hnsw_idx');
    }
};
