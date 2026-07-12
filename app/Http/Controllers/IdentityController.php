<?php

namespace App\Http\Controllers;

use App\Models\FcMembership;
use App\Models\GroupMember;
use App\Models\IdolGroup;
use App\Services\IdentityService;
use App\Support\JoinedMonthConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IdentityController extends Controller
{
    public function __construct(private IdentityService $service)
    {
    }

    public function index(Request $request)
    {
        $memberships = FcMembership::with(['person', 'group'])->get();
        $myGroups = Auth::user()->idolGroups;
        $currentGroupId = $request->get('group');

        return view('identities.index', compact('myGroups', 'currentGroupId', 'memberships'));
    }

    public function create()
    {
        return view('identities.create');
    }

    public function store(Request $request)
    {
        [$personData, $membershipData] = $this->validatedData($request);

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
        return view('identities.edit', compact('fcMembership'));
    }

    public function update(Request $request, FcMembership $fcMembership)
    {
        [$personData, $membershipData] = $this->validatedData($request);

        $this->service->update($fcMembership, $personData, $membershipData);

        return redirect()->route('identities.show', $fcMembership)
            ->with('success', '名義を更新しました');
    }

    public function duplicate(FcMembership $fcMembership)
    {
        $fcMembership->load('person');

        return view('identities.duplicate', compact('fcMembership'));
    }

    public function storeDuplicate(Request $request, FcMembership $fcMembership)
    {
        $validated = $request->validate([
            'group_id' => ['required', 'exists:idol_groups,id'],
            'group_member_id' => ['required', 'exists:group_members,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'member_no' => ['nullable', 'string', 'max:255', 'starts_with:e2e:'],
            'member_no_hint' => ['nullable', 'string', 'max:3'],
            'login_id' => ['nullable', 'string', 'max:255', 'starts_with:e2e:'],
            'email' => ['nullable', 'email', 'max:255'],
            'fc_password' => ['nullable', 'string', 'max:255', 'starts_with:e2e:'],
            'joined_month_input' => ['nullable', 'regex:' . JoinedMonthConverter::FORMAT_PATTERN],
            'oshi_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'group_member_id.required' => '担当メンバーを選択してください',
            'member_no.starts_with' => '会員番号はクライアント側で暗号化してから送信してください',
            'login_id.starts_with' => 'ログインIDはクライアント側で暗号化してから送信してください',
            'fc_password.starts_with' => 'パスワードはクライアント側で暗号化してから送信してください',
        ]);

        $member = GroupMember::find($validated['group_member_id']);

        $newMembership = FcMembership::create([
            'user_id' => Auth::id(),
            'person_id' => $fcMembership->person_id,
            'group_id' => $validated['group_id'],
            'artist_name' => $member->name,
            'label' => $validated['label'] ?? null,
            'member_no' => $validated['member_no'] ?? null,
            'member_no_hint' => IdentityService::resolveMemberNoHint(
                $validated['member_no'] ?? null, $validated['member_no_hint'] ?? null
            ),
            'login_id' => $validated['login_id'] ?? null,
            'email' => $validated['email'] ?? null,
            'password' => $validated['fc_password'] ?? null,
            'joined_on' => isset($validated['joined_month_input'])
                ? JoinedMonthConverter::toDate($validated['joined_month_input'])
                : null,
            'oshi_color' => $validated['oshi_color'] ?? null,
            'group_member_id' => $validated['group_member_id'] ?? null,
        ]);

        return redirect()->route('identities.show', $newMembership)
            ->with('success', '名義を複製しました');
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

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'person_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'group_id' => ['required', 'exists:idol_groups,id'],
            'group_member_id' => ['required', 'exists:group_members,id'],
            'artist_name' => ['nullable', 'string', 'max:255'],
            'member_no' => ['nullable', 'string', 'max:255', 'starts_with:e2e:'],
            'member_no_hint' => ['nullable', 'string', 'max:3'],
            'login_id' => ['nullable', 'string', 'max:255', 'starts_with:e2e:'],
            'email' => ['nullable', 'email', 'max:255'],
            'fc_password' => ['nullable', 'string', 'max:255', 'starts_with:e2e:'],
            'joined_month_input' => ['nullable', 'regex:' . JoinedMonthConverter::FORMAT_PATTERN],
            'oshi_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'group_member_id.required' => '担当メンバーを選択してください',
            'member_no.starts_with' => '会員番号はクライアント側で暗号化してから送信してください',
            'login_id.starts_with' => 'ログインIDはクライアント側で暗号化してから送信してください',
            'fc_password.starts_with' => 'パスワードはクライアント側で暗号化してから送信してください',
        ]);

        $member = GroupMember::find($validated['group_member_id']);

        $personData = [
            'name' => $validated['person_name'],
            'birth_date' => $validated['birth_date'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
        ];

        $membershipData = [
            'group_id' => $validated['group_id'],
            'artist_name' => $member->name,
            'label' => $validated['label'] ?? null,
            'member_no' => $validated['member_no'] ?? null,
            'member_no_hint' => $validated['member_no_hint'] ?? null,
            'login_id' => $validated['login_id'] ?? null,
            'email' => $validated['email'] ?? null,
            'password' => $validated['fc_password'] ?? null,
            'joined_on' => isset($validated['joined_month_input'])
                ? JoinedMonthConverter::toDate($validated['joined_month_input'])
                : null,
            'oshi_color' => $validated['oshi_color'] ?? null,
            'group_member_id' => $validated['group_member_id'] ?? null,
        ];

        return [$personData, $membershipData];
    }
}
