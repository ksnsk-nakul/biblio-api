<?php

namespace App\Actions\Folder;

use App\Models\Folder;
use Illuminate\Validation\ValidationException;

class RenameOrMoveFolder
{
    public function execute(Folder $folder, array $data): Folder
    {
        $newParentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $folder->parent_id;

        if ($newParentId !== $folder->parent_id) {
            $this->guardAgainstCycle($folder, $newParentId);
        }

        $folder->fill([
            'name' => $data['name'] ?? $folder->name,
            'parent_id' => $newParentId,
        ])->save();

        return $folder->refresh();
    }

    /**
     * Walk up from the proposed new parent, following parent_id, to ensure the
     * folder is not being moved into itself or one of its own descendants.
     */
    protected function guardAgainstCycle(Folder $folder, ?int $newParentId): void
    {
        if ($newParentId === null) {
            return;
        }

        $currentId = $newParentId;
        $visited = [];

        while ($currentId !== null) {
            if ($currentId === $folder->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Cannot move a folder into itself or one of its own subfolders.',
                ]);
            }

            if (isset($visited[$currentId])) {
                // Defensive: an existing cycle elsewhere in the tree, stop walking.
                break;
            }

            $visited[$currentId] = true;

            $currentId = Folder::query()->whereKey($currentId)->value('parent_id');
        }
    }
}
