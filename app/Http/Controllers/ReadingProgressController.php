<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReadingProgress\UpdateReadingProgressRequest;
use App\Http\Resources\ReadingProgressResource;
use App\Models\Book;
use App\Models\ReadingProgress;
use Illuminate\Http\Request;

class ReadingProgressController extends Controller
{
    public function show(Request $request, Book $book): ReadingProgressResource
    {
        $progress = ReadingProgress::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->first();

        return new ReadingProgressResource($progress);
    }

    public function update(UpdateReadingProgressRequest $request, Book $book): ReadingProgressResource
    {
        $progress = ReadingProgress::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'book_id' => $book->id],
            $request->validated(),
        );

        return new ReadingProgressResource($progress);
    }
}
