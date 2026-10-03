<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Album\AlbumGenresRequest;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\SongResource;
use App\Models\Album;
use App\Models\User;
use App\Repositories\AlbumRepository;
use App\Services\AlbumGenreService;
use Illuminate\Contracts\Auth\Authenticatable;

class AlbumGenreController extends Controller
{
    /** @param User $user */
    public function __construct(
        private readonly AlbumGenreService $service,
        private readonly AlbumRepository $repository,
        private readonly Authenticatable $user,
    ) {}

    public function suggest(Album $album)
    {
        $this->authorize('update', $album);

        return response()->json(['genres' => $this->service->suggest($album)]);
    }

    public function store(Album $album, AlbumGenresRequest $request)
    {
        $this->authorize('update', $album);

        $songs = $this->service->addToSongs($album, $request->genres, $this->user);

        $albums = $this->repository->getMany([$album->id]);
        $this->repository->loadGenres($albums);

        // Same shape as the song update response, so the client can handle both alike.
        return response()->json([
            'songs' => SongResource::collection($songs),
            'albums' => AlbumResource::collection($albums),
            'artists' => [],
            'removed' => ['album_ids' => [], 'artist_ids' => []],
        ]);
    }
}
