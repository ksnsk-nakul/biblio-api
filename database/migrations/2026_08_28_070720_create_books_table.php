<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('folders')->restrictOnDelete();
            $table->string('title');
            $table->string('author');
            $table->string('series_name')->nullable();
            $table->unsignedInteger('volume_number')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('file_path');
            $table->string('file_hash')->unique();
            $table->unsignedInteger('chapter_count')->default(0);
            $table->string('embedding_status')->default('none');
            $table->timestamps();

            $table->index('folder_id');
            $table->index('series_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
