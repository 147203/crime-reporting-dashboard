<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\QualityReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class QualityController extends Controller
{
    public function index(Request $request, QualityReport $report): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('quality.index', [...$report->for($user), 'labels' => ImportController::RULE_LABELS]);
    }
}
