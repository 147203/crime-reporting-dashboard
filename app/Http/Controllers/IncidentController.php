<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DuplicateStatus;
use App\Enums\IncidentStatus;
use App\Http\Requests\IncidentIndexRequest;
use App\Http\Requests\IncidentRequest;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use App\Support\Filters;
use App\Support\ReferenceOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidents, private readonly ReferenceOptions $options) {}

    public function index(IncidentIndexRequest $request): View
    {
        $user = $this->user($request);
        $validated = $request->validated();
        $sort = $validated['sort'] ?? 'occurred_at';
        $direction = $validated['direction'] ?? 'desc';

        $query = Filters::fromValidated($validated)
            ->apply(Incident::query()->visibleTo($user))
            ->with(['category:id,name', 'ward:id,name', 'station:id,name', 'duplicateOf' => fn ($q) => $q->withTrashed()->select(['id', 'station_id'])])
            ->when($user->role->isStationScoped() ? null : ($validated['duplicate_status'] ?? null), fn ($q, string $status) => $q->where('duplicate_status', $status))
            ->when($validated['q'] ?? null, function ($q, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($inner) => $inner->where('reference_no', 'like', $like)->orWhere('location_description', 'like', $like));
            })
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction);

        return view('incidents.index', [
            'incidents' => $query->paginate(20)->withQueryString(),
            'filters' => $validated,
            'options' => $this->options,
            'statuses' => IncidentStatus::cases(),
            'duplicateStatuses' => DuplicateStatus::cases(),
            'stationScoped' => $user->role->isStationScoped(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('incidents.create', $this->formData($this->user($request), new Incident([
            'occurred_at' => now()->startOfHour(),
            'reported_at' => now()->startOfMinute(),
        ])));
    }

    public function store(IncidentRequest $request): RedirectResponse
    {
        $incident = $this->incidents->create($request->validated(), $this->user($request));

        return redirect()->route('incidents.show', $incident)->with('status', "Incident {$incident->reference_no} was recorded.");
    }

    public function show(Incident $incident): View
    {
        $incident->load(['category', 'subCounty', 'ward', 'station', 'creator:id,name', 'duplicateOf:id,reference_no']);

        return view('incidents.show', ['incident' => $incident]);
    }

    public function edit(Request $request, Incident $incident): View
    {
        return view('incidents.edit', $this->formData($this->user($request), $incident));
    }

    public function update(IncidentRequest $request, Incident $incident): RedirectResponse
    {
        $this->incidents->update($incident, $request->validated(), $this->user($request));

        return redirect()->route('incidents.show', $incident)->with('status', 'Changes saved.');
    }

    public function destroy(Request $request, Incident $incident): RedirectResponse
    {
        $this->incidents->delete($incident, $this->user($request));

        return redirect()->route('incidents.index')->with('status', "Incident {$incident->reference_no} was deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user, Incident $incident): array
    {
        return [
            'incident' => $incident,
            'categories' => $this->options->categories(activeOnly: true, keep: $incident->category_id),
            'subCounties' => $this->options->subCounties(),
            'wards' => $this->options->wards(),
            'stations' => $this->options->stations(activeOnly: true, keep: $incident->station_id),
            'stationScoped' => $user->role->isStationScoped(),
            'ownStation' => $user->station?->name,
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
