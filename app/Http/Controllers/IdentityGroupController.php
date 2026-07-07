<?php

namespace App\Http\Controllers;

use App\Models\IdentityGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IdentityGroupController extends Controller
{
    public function index()
    {
        $groups = IdentityGroup::withCount('fcMemberships')
            ->orderBy('sort_order')
            ->get();

        return view('identity-groups.index', compact('groups'));
    }

    public function create()
    {
        return view('identity-groups.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $maxOrder = IdentityGroup::max('sort_order') ?? -1;

        IdentityGroup::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()->route('identity-groups.index')
            ->with('success', 'グループを作成しました');
    }

    public function edit(IdentityGroup $identityGroup)
    {
        return view('identity-groups.edit', compact('identityGroup'));
    }

    public function update(Request $request, IdentityGroup $identityGroup)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $identityGroup->update($validated);

        return redirect()->route('identity-groups.index')
            ->with('success', 'グループ名を変更しました');
    }

    public function destroy(IdentityGroup $identityGroup)
    {
        // E1未決: 配下名義がある場合は削除不可
        if ($identityGroup->fcMemberships()->exists()) {
            return back()->with('error', 'このグループには名義が含まれているため削除できません。先に名義を移動してください。');
        }

        $identityGroup->delete();

        return redirect()->route('identity-groups.index')
            ->with('success', 'グループを削除しました');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:identity_groups,id'],
        ]);

        foreach ($validated['order'] as $index => $id) {
            IdentityGroup::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['ok' => true]);
    }
}
