<?php

namespace App\Actions\Folder;

use App\Models\Folder;
use App\Models\User;

class CreateFolder
{
    public function execute(User $creator, array $data): Folder
    {
        return Folder::create([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'created_by' => $creator->id,
        ]);
    }
}
