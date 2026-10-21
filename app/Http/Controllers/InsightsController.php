<?php

namespace App\Http\Controllers;

use App\Services\InsightsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsightsController extends Controller
{
    public function __construct(
        private readonly InsightsService $insights,
    ) {}

    public function __invoke(Request $request): Response
    {
        $weeks = in_array((int) $request->query('weeks'), InsightsService::RANGES, true)
            ? (int) $request->query('weeks')
            : InsightsService::RANGES[1];

        return Inertia::render('Insights', [
            'insights' => $this->insights->forUser($request->user(), $weeks),
            'ranges' => InsightsService::RANGES,
        ]);
    }
}
