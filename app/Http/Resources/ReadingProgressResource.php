<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReadingProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [
                'book_id' => null,
                'chapter_index' => null,
                'cfi' => null,
                'updated_at' => null,
            ];
        }

        return [
            'book_id' => $this->book_id,
            'chapter_index' => $this->chapter_index,
            'cfi' => $this->cfi,
            'updated_at' => $this->updated_at,
        ];
    }
}
