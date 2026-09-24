<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DuplicateStatus;
use App\Models\Incident;
use App\Models\User;
use App\Services\DuplicateDetector;
use App\Services\DuplicateReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class DuplicateController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $suspected = Incident::query()->visibleTo($user)
            ->where('duplicate_status', DuplicateStatus::Suspected->value)
            ->with(['category:id,name', 'ward:id,name', 'station:id,name', 'duplicateOf' => fn ($q) => $q->withTrashed()->with(['category:id,name', 'ward:id,name', 'station:id,name'])])
            ->orderBy('occurred_at')
            ->paginate(10);

        return view('duplicates.index', [
            'suspected' => $suspected,
            'similarity' => fn (Incident $a, ?Incident $b): int => $b === null ? 0 : (int) round(100 * DuplicateDetector::similarity(DuplicateDetector::normalise($a->location_description), DuplicateDetector::normalise($b->location_description))),
            'window' => (int) config('analytics.duplicates.window_minutes'),
            'threshold' => (float) config('analytics.duplicates.similarity'),
        ]);
    }

    public function resolve(Request $request, Incident $incident, DuplicateReviewService $review): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $validated = $request->validate(['decision' => ['required', Rule::in([DuplicateStatus::Confirmed->value, DuplicateStatus::Dismissed->value])]]);

        $decision = DuplicateStatus::from($validated['decision']);
        $review->resolve($incident, $decision, $user);

        return redirect()->route('duplicates.index')->with('status', "{$incident->reference_no} marked as {$decision->label()} duplicate.");
    }
}
