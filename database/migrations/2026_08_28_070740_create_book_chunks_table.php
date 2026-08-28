<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->unsignedInteger('chapter_index');
            $table->text('content');
            $table->vector('embedding', 1536);

            $table->index('book_id');
            $table->index('chapter_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_chunks');
    }
};
