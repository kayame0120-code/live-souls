<?php

namespace App\Http\Controllers;

use App\Models\IdolGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IdolGroupController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $group = IdolGroup::firstOrCreate(['name' => $validated['name']]);

        Auth::user()->idolGroups()->syncWithoutDetaching([$group->id]);

        return redirect()->route('identities.index')
            ->with('success', 'グループを追加しました');
    }
}
