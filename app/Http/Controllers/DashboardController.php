<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsFilterRequest;
use App\Models\User;
use App\Services\Analytics\Analytics;
use App\Services\Analytics\DashboardViewModel;
use App\Support\ReferenceOptions;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function index(AnalyticsFilterRequest $request, ReferenceOptions $options): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $filters = $request->filters();

        return view('dashboard', [
            'vm' => new DashboardViewModel((new Analytics($user, $filters))->all(), $filters),
            'filters' => $request->validated(),
            'options' => $options,
            'stationScoped' => $user->role->isStationScoped(),
        ]);
    }
}
