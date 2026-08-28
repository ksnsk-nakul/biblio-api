<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->unsignedInteger('index');
            $table->string('title');
            $table->string('spine_href');
            $table->timestamps();

            $table->index('book_id');
            $table->unique(['book_id', 'index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_chapters');
    }
};
