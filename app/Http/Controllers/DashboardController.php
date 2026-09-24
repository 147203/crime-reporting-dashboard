<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 3 overview; the full analytics dashboard (A1 to A10) replaces the body in Phase 5.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $base = fn () => Incident::query()->visibleTo($user)->countable();
        $byStatus = $base()->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('dashboard', [
            'total' => $base()->count(),
            'byStatus' => collect(IncidentStatus::cases())->mapWithKeys(fn (IncidentStatus $s) => [$s->label() => (int) ($byStatus[$s->value] ?? 0)]),
            'recent' => $base()->with(['category:id,name', 'ward:id,name'])->latest('occurred_at')->limit(8)->get(),
        ]);
    }
}
