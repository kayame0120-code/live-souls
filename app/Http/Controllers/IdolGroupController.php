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
        ], [
            'name.required' => 'グループ名を入力してください',
        ]);

        $trimmedName = trim($validated['name']);
        $existing = IdolGroup::whereRaw('TRIM(name) = ?', [$trimmedName])->first();
        $group = $existing ?? IdolGroup::create(['name' => $trimmedName]);

        if (! Auth::user()->idolGroups()->where('idol_group_id', $group->id)->exists()) {
            $maxOrder = Auth::user()->idolGroups()->max('sort_order') ?? 0;
            Auth::user()->idolGroups()->attach($group->id, ['sort_order' => $maxOrder + 1]);
        }

        return redirect()->route('identities.index')
            ->with('success', 'グループを追加しました');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $user = Auth::user();
        foreach ($validated['order'] as $i => $groupId) {
            $user->idolGroups()->updateExistingPivot($groupId, ['sort_order' => $i]);
        }

        return response()->json(['ok' => true]);
    }
}
