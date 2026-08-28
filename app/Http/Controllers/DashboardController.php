<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookResource;
use App\Models\ReadingProgress;
use App\Models\Shelf;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $continueReading = ReadingProgress::query()
            ->where('user_id', $userId)
            ->with('book.folder')
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->pluck('book')
            ->filter()
            ->values();

        $shelf = Shelf::query()
            ->where('user_id', $userId)
            ->with('book.folder')
            ->latest('added_at')
            ->get()
            ->pluck('book')
            ->filter()
            ->values();

        return response()->json([
            'continue_reading' => BookResource::collection($continueReading),
            'shelf' => BookResource::collection($shelf),
        ]);
    }
}
