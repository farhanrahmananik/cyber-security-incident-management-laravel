<?php

namespace App\Http\Controllers\AuditLog;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\AuditLogFilterRequest;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    /**
     * Display the read-only audit log index.
     */
    public function index(AuditLogFilterRequest $request, AuditLogService $auditLogService): View
    {
        $filters = $request->validated();

        return view('audit-logs.index', [
            'auditLogs' => $auditLogService->paginate($filters),
            'filters' => $filters,
        ]);
    }
}
