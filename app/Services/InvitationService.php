<?php

namespace App\Services;

use App\Models\Invitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InvitationService
{
    public function issue(): Invitation
    {
        return Invitation::create([
            'code' => Str::random(32),
            'issued_by' => Auth::id(),
        ]);
    }

    public function revoke(Invitation $invitation): void
    {
        if ($invitation->issued_by !== Auth::id()) {
            abort(403);
        }

        if ($invitation->used_by !== null) {
            abort(422, '使用済みの招待は失効できません');
        }

        $invitation->update(['expires_at' => now()]);
    }
}
