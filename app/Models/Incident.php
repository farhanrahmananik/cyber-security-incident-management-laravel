<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'incident_number',
        'reporter_id',
        'current_assigned_to_id',
        'incident_category_id',
        'severity_level_id',
        'priority_level_id',
        'title',
        'description',
        'impact_summary',
        'affected_system',
        'occurred_at',
        'detected_at',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'detected_at' => 'datetime',
        ];
    }

    /**
     * Reporter who submitted the incident.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * User currently assigned to handle the incident.
     */
    public function currentAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_assigned_to_id');
    }

    /**
     * Assignment history for this incident.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(IncidentAssignment::class);
    }

    /**
     * Investigation notes recorded for this incident.
     */
    public function investigationNotes(): HasMany
    {
        return $this->hasMany(InvestigationNote::class);
    }

    /**
     * Indicators of compromise recorded for this incident.
     */
    public function iocs(): HasMany
    {
        return $this->hasMany(IncidentIoc::class);
    }

    /**
     * Evidence records attached to this incident.
     */
    public function evidences(): HasMany
    {
        return $this->hasMany(IncidentEvidence::class);
    }

    /**
     * Category assigned to the incident.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'incident_category_id');
    }

    /**
     * Severity assigned to the incident.
     */
    public function severity(): BelongsTo
    {
        return $this->belongsTo(SeverityLevel::class, 'severity_level_id');
    }

    /**
     * Priority assigned to the incident.
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(PriorityLevel::class, 'priority_level_id');
    }

    /**
     * Scope incidents by most recently reported.
     */
    public function scopeLatestReported(Builder $query): Builder
    {
        return $query->latest('created_at');
    }

    /**
     * Scope incidents visible to the given user.
     */
    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['super-admin', 'security-manager', 'soc-analyst'])) {
            return $query;
        }

        return $query->where('reporter_id', $user->getKey());
    }
}
