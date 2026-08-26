<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuditController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('audit.view'), 403);

        $q = AuditLog::query()->with('user:id,first_name,last_name,email');
        foreach (['user_id', 'module', 'action', 'subject_type', 'subject_id'] as $f) {
            if ($request->filled($f)) $q->where($f, $request->input($f));
        }
        if ($request->filled('from')) $q->where('created_at', '>=', $request->input('from'));
        if ($request->filled('to'))   $q->where('created_at', '<=', $request->input('to'));

        $page = $q->orderByDesc('created_at')->paginate((int) $request->integer('per_page', 50));

        $data = collect($page->items())->map(fn (AuditLog $a) => [
            'id'           => $a->id,
            'created_at'   => $a->created_at?->toIso8601String(),
            'user'         => $a->user ? [
                'id' => $a->user->id, 'full_name' => $a->user->full_name, 'email' => $a->user->email,
            ] : null,
            'module'       => $a->module,
            'action'       => $a->action instanceof \BackedEnum ? $a->action->value : (string) $a->action,
            'subject_type' => $a->subject_type,
            'subject_id'   => $a->subject_id,
            'ip'           => $a->ip,
            'user_agent'   => $a->user_agent,
            'metadata'     => $a->metadata,
        ])->all();

        return $this->ok(data: $data, message: 'Audit log entries retrieved.', meta: [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(),
            'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]);
    }
}
