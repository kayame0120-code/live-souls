<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class InvitedRegisterController extends Controller
{
    public function show(string $code)
    {
        $invitation = $this->findValidInvitation($code);

        if (! $invitation) {
            abort(403, 'この招待は使用できません');
        }

        return view('auth.register', compact('invitation'));
    }

    public function store(Request $request, string $code)
    {
        $invitation = $this->findValidInvitation($code);

        if (! $invitation) {
            abort(403, 'この招待は使用できません');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated, $invitation) {
            // used_at で先行ロック（FKなし）。同時リクエストは1行しか更新できない
            $claimed = Invitation::where('id', $invitation->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            if (! $claimed) {
                return null;
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'invited_by' => $invitation->issued_by,
            ]);

            Invitation::where('id', $invitation->id)
                ->update(['used_by' => $user->id]);

            return $user;
        });

        if (! $user) {
            return back()->withErrors(['email' => 'この招待は既に使用されています']);
        }

        Auth::login($user);

        return redirect('/');
    }

    private function findValidInvitation(string $code): ?Invitation
    {
        return Invitation::where('code', $code)
            ->whereNull('used_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }
}
