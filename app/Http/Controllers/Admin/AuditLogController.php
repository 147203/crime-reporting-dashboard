<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\View\View;

final class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): View
    {
        $filters = $request->validated();

        $entries = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['action'] ?? null, fn ($q, string $action) => $q->where('action', 'like', addcslashes($action, '%_\\').'%'))
            ->when($filters['user_id'] ?? null, fn ($q, int|string $id) => $q->where('user_id', (int) $id))
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit.index', [
            'entries' => $entries,
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action')->all(),
        ]);
    }
}
