<?php

namespace App\Http\Controllers\API;

use App\Facades\Dispatcher;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\AnalyzeSongsRequest;
use App\Jobs\AnalyzeSongsJob;
use App\Models\Song;
use App\Models\User;
use App\Services\AudioAnalysisService;
use Illuminate\Contracts\Auth\Authenticatable;

class AnalyzeSongsController extends Controller
{
    /** @param User $user */
    public function __invoke(AnalyzeSongsRequest $request, Authenticatable $user)
    {
        abort_unless(AudioAnalysisService::available(), 503, 'The audio analysis is not set up on this server.');

        $ids = Song::query()
            ->findMany($request->songs)
            ->filter(static fn (Song $song) => $user->can('edit', $song))
            ->pluck('id')
            ->all();

        Dispatcher::dispatch(new AnalyzeSongsJob($ids));

        return response()->json(['queued' => count($ids)], 202);
    }
}
