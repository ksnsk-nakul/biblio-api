<?php

namespace App\Actions\Folder;

use App\Models\Folder;

class RenameOrMoveFolder
{
    public function execute(Folder $folder, array $data): Folder
    {
        $folder->fill([
            'name' => $data['name'] ?? $folder->name,
            'parent_id' => array_key_exists('parent_id', $data) ? $data['parent_id'] : $folder->parent_id,
        ])->save();

        return $folder->refresh();
    }
}
