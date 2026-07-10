<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\HomeService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(private HomeService $homeService)
    {
    }

    public function index()
    {
        $nextAttendance = $this->homeService->getNextAttendance();
        $stats = $this->homeService->getStats();
        $recentAttendances = $this->homeService->getRecentAttendances();
        $pendingConfirmations = $this->homeService->getPendingConfirmations();
        $renewalMemberships = $this->homeService->getRenewalMemberships();
        $ticketReminders = $this->homeService->getTicketReminders();

        return view('home', compact(
            'nextAttendance', 'stats', 'recentAttendances',
            'pendingConfirmations', 'renewalMemberships', 'ticketReminders',
        ));
    }

    /**
     * 公演日経過の「参戦した？」確認への応答（spec §5・T8）。
     * 確定操作でのみ attended / skipped に遷移する（自動遷移はしない）。
     * ルートモデルバインディングは UserScope により他人の参戦は404。
     */
    public function confirmAttendance(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:attended,skipped'],
        ]);

        // planned のものだけを対象にする（多重送信・不整合を防ぐ）
        if ($attendance->status === 'planned') {
            $attendance->update(['status' => $validated['decision']]);
        }

        return redirect()->route('home')
            ->with('success', $validated['decision'] === 'attended' ? '参戦を記録しました' : 'スキップしました');
    }
}
