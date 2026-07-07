<?php

namespace App\Http\Controllers;

use App\Services\HomeService;

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

        return view('home', compact('nextAttendance', 'stats', 'recentAttendances'));
    }
}
