<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folder_id' => $this->folder_id,
            'title' => $this->title,
            'author' => $this->author,
            'series_name' => $this->series_name,
            'volume_number' => $this->volume_number,
            'cover_url' => $this->cover_path ? route('books.cover', $this->id, absolute: false) : null,
            'chapter_count' => $this->chapter_count,
            'embedding_status' => $this->embedding_status,
            'folder' => $this->whenLoaded('folder', fn () => new FolderResource($this->folder)),
            'chapters' => BookChapterResource::collection($this->whenLoaded('chapters')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
