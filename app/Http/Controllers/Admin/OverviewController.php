<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminStatsService;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __construct(
        private readonly AdminStatsService $stats,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Admin/Overview', [
            'stats' => $this->stats->overview(),
            'days' => AdminStatsService::DAYS,
        ]);
    }
}
