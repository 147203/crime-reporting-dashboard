<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReferenceItemRequest;
use App\Models\CrimeCategory;
use App\Models\Station;
use App\Models\SubCounty;
use App\Models\Ward;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reference lists are added to and renamed, never deleted, so historical incidents keep their meaning; categories and
 * stations can be deactivated instead.
 */
final class ReferenceDataController extends Controller
{
    private const TYPES = [
        'categories' => CrimeCategory::class,
        'sub-counties' => SubCounty::class,
        'wards' => Ward::class,
        'stations' => Station::class,
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TYPES) ? (string) $request->query('tab') : 'categories';

        return view('admin.reference.index', [
            'tab' => $tab,
            'categories' => CrimeCategory::query()->withCount('incidents')->orderBy('name')->get(),
            'subCounties' => SubCounty::query()->withCount(['wards', 'stations'])->orderBy('name')->get(),
            'wards' => Ward::query()->with('subCounty:id,name')->orderBy('name')->get(),
            'stations' => Station::query()->with('subCounty:id,name')->orderBy('name')->get(),
            'subCountyOptions' => SubCounty::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function store(ReferenceItemRequest $request, string $type): RedirectResponse
    {
        $model = new (self::TYPES[$type]);
        $model->forceFill($this->values($request))->save();
        $this->audit->record('reference.created', $model, ['type' => $type]);

        return redirect()->route('admin.reference.index', ['tab' => $type])->with('status', 'Item added.');
    }

    public function update(ReferenceItemRequest $request, string $type, int $item): RedirectResponse
    {
        /** @var Model $model */
        $model = (self::TYPES[$type])::query()->findOrFail($item);
        $model->forceFill($this->values($request));
        $changed = array_keys($model->getDirty());
        $model->save();
        $this->audit->record('reference.updated', $model, ['type' => $type, 'fields' => $changed]);

        return redirect()->route('admin.reference.index', ['tab' => $type])->with('status', 'Item updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function values(ReferenceItemRequest $request): array
    {
        $values = $request->validated();

        if (array_key_exists('is_active', $request->rules())) {
            $values['is_active'] = $request->boolean('is_active');
        }

        return $values;
    }
}
