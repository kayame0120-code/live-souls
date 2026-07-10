<?php

namespace App\Http\Controllers;

use App\Models\IdolGroup;
use Illuminate\Http\Request;

class IdolGroupController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        IdolGroup::firstOrCreate(['name' => $validated['name']]);

        return redirect()->route('identities.index')
            ->with('success', 'グループを追加しました');
    }
}
