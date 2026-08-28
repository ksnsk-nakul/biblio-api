<?php

namespace App\Http\Controllers;

use App\Actions\Folder\CreateFolder;
use App\Actions\Folder\DeleteFolder;
use App\Actions\Folder\RenameOrMoveFolder;
use App\Http\Requests\Folder\StoreFolderRequest;
use App\Http\Requests\Folder\UpdateFolderRequest;
use App\Http\Resources\FolderResource;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FolderController extends Controller
{
    public function index(Request $request)
    {
        $query = Folder::withCount(['children', 'books']);

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->input('parent_id'));
        }

        return FolderResource::collection($query->get());
    }

    public function show(Folder $folder): FolderResource
    {
        $folder->load(['children', 'books']);

        return new FolderResource($folder);
    }

    public function store(StoreFolderRequest $request, CreateFolder $createFolder): FolderResource
    {
        $folder = $createFolder->execute($request->user(), $request->validated());

        return new FolderResource($folder);
    }

    public function update(UpdateFolderRequest $request, Folder $folder, RenameOrMoveFolder $renameOrMoveFolder): FolderResource
    {
        $folder = $renameOrMoveFolder->execute($folder, $request->validated());

        return new FolderResource($folder);
    }

    public function destroy(Folder $folder, DeleteFolder $deleteFolder): Response
    {
        $deleteFolder->execute($folder);

        return response()->noContent();
    }
}
