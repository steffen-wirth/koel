<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Integrations\MusicBrainzService;

class RecordingCreditsController extends Controller
{
    public function __invoke(string $mbid, MusicBrainzService $musicBrainz)
    {
        return response()->json(['credits' => $musicBrainz->getRecordingCredits($mbid)]);
    }
}
