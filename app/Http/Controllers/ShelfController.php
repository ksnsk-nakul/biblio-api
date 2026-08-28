<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Shelf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShelfController extends Controller
{
    public function store(Request $request, Book $book): Response
    {
        Shelf::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
        ]);

        return response()->noContent();
    }

    public function destroy(Request $request, Book $book): Response
    {
        Shelf::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->delete();

        return response()->noContent();
    }
}
