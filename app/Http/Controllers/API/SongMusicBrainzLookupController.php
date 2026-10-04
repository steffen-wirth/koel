<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\SongMusicBrainzLookupRequest;
use App\Services\Integrations\MusicBrainzService;

class SongMusicBrainzLookupController extends Controller
{
    public function __invoke(SongMusicBrainzLookupRequest $request, MusicBrainzService $musicBrainz)
    {
        return response()->json([
            'matches' => $musicBrainz->searchRecordings($request->title, $request->artist, $request->album),
        ]);
    }
}
