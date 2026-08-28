<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\ReadingProgressController;
use App\Http\Controllers\ShelfController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/folders', [FolderController::class, 'index']);
    Route::get('/folders/{folder}', [FolderController::class, 'show']);

    Route::get('/books', [BookController::class, 'index']);
    Route::get('/search', [BookController::class, 'search']);
    Route::get('/books/{book}', [BookController::class, 'show']);
    Route::get('/books/{book}/file', [BookController::class, 'file']);
    Route::post('/books/{book}/embed', [BookController::class, 'embed']);
    Route::post('/books/{book}/chat', [BookController::class, 'chat']);

    Route::get('/books/{book}/progress', [ReadingProgressController::class, 'show']);
    Route::patch('/books/{book}/progress', [ReadingProgressController::class, 'update']);

    Route::post('/shelf/{book}', [ShelfController::class, 'store']);
    Route::delete('/shelf/{book}', [ShelfController::class, 'destroy']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::middleware('admin')->group(function () {
        Route::post('/folders', [FolderController::class, 'store']);
        Route::patch('/folders/{folder}', [FolderController::class, 'update']);
        Route::delete('/folders/{folder}', [FolderController::class, 'destroy']);

        Route::post('/books', [BookController::class, 'store']);
        Route::patch('/books/{book}', [BookController::class, 'update']);
        Route::delete('/books/{book}', [BookController::class, 'destroy']);
        Route::post('/books/bulk-import', [BookController::class, 'bulkImport']);
    });
});
