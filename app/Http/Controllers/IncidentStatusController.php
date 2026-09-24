<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Http\Requests\ChangeIncidentStatusRequest;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Http\RedirectResponse;

final class IncidentStatusController extends Controller
{
    public function __construct(private readonly IncidentService $incidents) {}

    public function update(ChangeIncidentStatusRequest $request, Incident $incident): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $target = IncidentStatus::from($request->string('status')->toString());
        $this->incidents->changeStatus($incident, $target, $user);

        return redirect()->route('incidents.show', $incident)->with('status', "Status changed to {$target->label()}.");
    }
}
