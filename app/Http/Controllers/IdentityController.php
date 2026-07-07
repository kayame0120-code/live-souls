<?php

namespace App\Http\Controllers;

use App\Models\FcMembership;
use App\Models\IdentityGroup;
use App\Services\IdentityService;
use Illuminate\Http\Request;

class IdentityController extends Controller
{
    public function __construct(private IdentityService $service)
    {
    }

    public function index(Request $request)
    {
        $groups = IdentityGroup::with('fcMemberships.person')->get();
        $currentGroupId = $request->get('group');

        return view('identities.index', compact('groups', 'currentGroupId'));
    }

    public function create()
    {
        $groups = IdentityGroup::all();
        return view('identities.create', compact('groups'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'group_id' => ['required', 'exists:identity_groups,id'],
            'artist_name' => ['required', 'string', 'max:255'],
            'club_name' => ['nullable', 'string', 'max:255'],
            'member_no' => ['nullable', 'string', 'max:255'],
            'login_id' => ['nullable', 'string', 'max:255'],
            'fc_password' => ['nullable', 'string', 'max:255'],
            'joined_month' => ['nullable', 'string', 'max:7'],
            'renewal_cycle' => ['nullable', 'string', 'max:255'],
            'oshi_color' => ['nullable', 'string', 'max:7'],
        ]);

        $personData = [
            'name' => $validated['person_name'],
            'birth_date' => $validated['birth_date'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'label' => $validated['label'] ?? null,
        ];

        $membershipData = [
            'group_id' => $validated['group_id'],
            'artist_name' => $validated['artist_name'],
            'club_name' => $validated['club_name'] ?? null,
            'member_no' => $validated['member_no'] ?? null,
            'login_id' => $validated['login_id'] ?? null,
            'password' => $validated['fc_password'] ?? null,
            'joined_month' => $validated['joined_month'] ?? null,
            'renewal_cycle' => $validated['renewal_cycle'] ?? null,
            'oshi_color' => $validated['oshi_color'] ?? null,
        ];

        $membership = $this->service->create($personData, $membershipData);

        return redirect()->route('identities.show', $membership)
            ->with('success', '名義を登録しました');
    }

    public function show(FcMembership $fcMembership)
    {
        $fcMembership->load(['person', 'group', 'attendances']);
        return view('identities.show', compact('fcMembership'));
    }

    public function edit(FcMembership $fcMembership)
    {
        $fcMembership->load('person');
        $groups = IdentityGroup::all();
        return view('identities.edit', compact('fcMembership', 'groups'));
    }

    public function update(Request $request, FcMembership $fcMembership)
    {
        $validated = $request->validate([
            'person_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'group_id' => ['required', 'exists:identity_groups,id'],
            'artist_name' => ['required', 'string', 'max:255'],
            'club_name' => ['nullable', 'string', 'max:255'],
            'member_no' => ['nullable', 'string', 'max:255'],
            'login_id' => ['nullable', 'string', 'max:255'],
            'fc_password' => ['nullable', 'string', 'max:255'],
            'joined_month' => ['nullable', 'string', 'max:7'],
            'renewal_cycle' => ['nullable', 'string', 'max:255'],
            'oshi_color' => ['nullable', 'string', 'max:7'],
        ]);

        $personData = [
            'name' => $validated['person_name'],
            'birth_date' => $validated['birth_date'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'label' => $validated['label'] ?? null,
        ];

        $membershipData = [
            'group_id' => $validated['group_id'],
            'artist_name' => $validated['artist_name'],
            'club_name' => $validated['club_name'] ?? null,
            'member_no' => $validated['member_no'] ?? null,
            'login_id' => $validated['login_id'] ?? null,
            'password' => $validated['fc_password'] ?? null,
            'joined_month' => $validated['joined_month'] ?? null,
            'renewal_cycle' => $validated['renewal_cycle'] ?? null,
            'oshi_color' => $validated['oshi_color'] ?? null,
        ];

        $this->service->update($fcMembership, $personData, $membershipData);

        return redirect()->route('identities.show', $fcMembership)
            ->with('success', '名義を更新しました');
    }

    public function destroy(FcMembership $fcMembership)
    {
        $person = $fcMembership->person;
        $fcMembership->delete();

        if ($person->fcMemberships()->count() === 0) {
            $person->delete();
        }

        return redirect()->route('identities.index')
            ->with('success', '名義を削除しました');
    }
}
