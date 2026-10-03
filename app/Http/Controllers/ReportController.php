<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsFilterRequest;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Analytics\Analytics;
use App\Services\Analytics\DashboardViewModel;
use App\Services\ReportService;
use App\Support\ReferenceOptions;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(AnalyticsFilterRequest $request, ReferenceOptions $options): View
    {
        $user = $this->user($request);
        $filters = $request->filters();
        $analytics = new Analytics($user, $filters);
        $n = $analytics->a1()['n'];

        return view('reports.index', [
            'vm' => new DashboardViewModel(['A1' => ['n' => $n], 'A2' => $analytics->a2($n)], $filters),
            'filters' => $request->validated(),
            'options' => $options,
            'stationScoped' => $user->role->isStationScoped(),
            'realRecords' => $this->reports->realRecords($user, $filters),
            'recent' => ReportExport::query()->where('user_id', $user->id)->latest('id')->limit(5)->get(),
        ]);
    }

    public function csv(AnalyticsFilterRequest $request): StreamedResponse
    {
        return $this->reports->csv($this->user($request), $request->filters());
    }

    public function xlsx(AnalyticsFilterRequest $request): BinaryFileResponse
    {
        return $this->reports->xlsx($this->user($request), $request->filters());
    }

    public function pdf(AnalyticsFilterRequest $request): Response
    {
        return $this->reports->pdf($this->user($request), $request->filters());
    }

    private function user(AnalyticsFilterRequest $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
