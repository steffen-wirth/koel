<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SongCredit;
use Illuminate\Http\Request;

class SongCreditNameController extends Controller
{
    /** The distinct names credited in the library, optionally only in one role. */
    public function __invoke(Request $request)
    {
        $request->validate(['role' => ['sometimes', 'nullable', 'string', 'max:64']]);

        return response()->json([
            'names' => SongCredit::query()
                ->when($request->filled('role'), static fn ($query) => $query->where('role', $request->input('role')))
                ->distinct()
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }
}
