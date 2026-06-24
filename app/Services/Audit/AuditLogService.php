<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * Record an append-only audit log entry.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $event,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
        ?Request $request = null,
    ): AuditLog {
        $actor = $user ?? auth()->user();

        return AuditLog::query()->create([
            'user_id' => $actor instanceof User ? $actor->getKey() : null,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Return filtered audit logs for the read-only index page.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user')
            ->when($filters['event'] ?? null, function (Builder $query, string $event): void {
                $query->where('event', $event);
            })
            ->when($filters['user_id'] ?? null, function (Builder $query, int|string $userId): void {
                $query->where('user_id', $userId);
            })
            ->when($filters['auditable_type'] ?? null, function (Builder $query, string $auditableType): void {
                $query->where('auditable_type', $auditableType);
            })
            ->when($filters['auditable_id'] ?? null, function (Builder $query, int|string $auditableId): void {
                $query->where('auditable_id', $auditableId);
            })
            ->when($filters['date_from'] ?? null, function (Builder $query, string $dateFrom): void {
                $query->whereDate('audit_logs.created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, string $dateTo): void {
                $query->whereDate('audit_logs.created_at', '<=', $dateTo);
            })
            ->when($filters['keyword'] ?? null, function (Builder $query, string $keyword): void {
                $query->where(function (Builder $query) use ($keyword): void {
                    $query->where('event', 'like', "%{$keyword}%")
                        ->orWhere('auditable_type', 'like', "%{$keyword}%")
                        ->orWhereHas('user', function (Builder $query) use ($keyword): void {
                            $query->where('name', 'like', "%{$keyword}%")
                                ->orWhere('email', 'like', "%{$keyword}%");
                        });
                });
            })
            ->latest('audit_logs.created_at')
            ->paginate(15)
            ->withQueryString();
    }
}
