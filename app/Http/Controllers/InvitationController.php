<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Auth;

class InvitationController extends Controller
{
    public function __construct(private InvitationService $service)
    {
    }

    public function index()
    {
        $invitations = Invitation::where('issued_by', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return view('invitations.index', compact('invitations'));
    }

    public function store()
    {
        $this->service->issue();

        return redirect()->route('invitations.index')
            ->with('success', '招待コードを発行しました');
    }

    public function destroy(Invitation $invitation)
    {
        $this->service->revoke($invitation);

        return redirect()->route('invitations.index')
            ->with('success', '招待コードを失効させました');
    }
}
