<?php

namespace App\Http\Controllers\Incident;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\StoreIncidentRequest;
use App\Http\Requests\Incident\UpdateIncidentRequest;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\PriorityLevel;
use App\Models\SeverityLevel;
use App\Services\Incident\IncidentAssignmentService;
use App\Services\Incident\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    /**
     * Display incidents visible to the authenticated user.
     */
    public function index(Request $request, IncidentService $incidentService): View
    {
        return view('incidents.index', [
            'incidents' => $incidentService->listForUser($request->user()),
        ]);
    }

    /**
     * Show the incident creation form.
     */
    public function create(): View
    {
        return view('incidents.create', $this->formOptions());
    }

    /**
     * Store a newly reported incident.
     */
    public function store(StoreIncidentRequest $request, IncidentService $incidentService): RedirectResponse
    {
        $incident = $incidentService->create($request->user(), $request->validated());

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Incident reported successfully.');
    }

    /**
     * Display the specified incident.
     */
    public function show(
        Request $request,
        Incident $incident,
        IncidentService $incidentService,
        IncidentAssignmentService $assignmentService,
    ): View {
        $user = $request->user();

        abort_unless($incidentService->canView($user, $incident), 403);

        $canViewInvestigationNotes = $user->can('investigation-note.view');
        $canViewIocs = $user->can('ioc.view');
        $canViewEvidences = $user->can('evidence.view');

        $relations = [
            'reporter',
            'category',
            'severity',
            'priority',
            'currentAssignee',
            'assignments' => fn ($query) => $query
                ->with(['assignedTo', 'assignedBy'])
                ->latest('assigned_at'),
        ];

        if ($canViewInvestigationNotes) {
            $relations['investigationNotes'] = fn ($query) => $query
                ->with('author')
                ->latest();
        }

        if ($canViewIocs) {
            $relations['iocs'] = fn ($query) => $query
                ->with('createdBy')
                ->latest();
        }

        if ($canViewEvidences) {
            $relations['evidences'] = fn ($query) => $query
                ->with(['uploadedBy', 'deletedBy'])
                ->latest();
        }

        $incident->load($relations);

        return view('incidents.show', [
            'incident' => $incident,
            'assignmentHistory' => $incident->assignments,
            'assignableUsers' => $user->can('incident.assign')
                ? $assignmentService->assignableUsers()
                : collect(),
            'investigationNotes' => $canViewInvestigationNotes
                ? $incident->investigationNotes
                : collect(),
            'iocs' => $canViewIocs
                ? $incident->iocs
                : collect(),
            'evidences' => $canViewEvidences
                ? $incident->evidences
                : collect(),
        ]);
    }

    /**
     * Show the incident edit form.
     */
    public function edit(Request $request, Incident $incident, IncidentService $incidentService): View
    {
        abort_unless($incidentService->canUpdate($request->user(), $incident), 403);

        $incident->load(['category', 'severity', 'priority']);

        return view('incidents.edit', [
            'incident' => $incident,
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the specified incident.
     */
    public function update(
        UpdateIncidentRequest $request,
        Incident $incident,
        IncidentService $incidentService,
    ): RedirectResponse {
        abort_unless($incidentService->canUpdate($request->user(), $incident), 403);

        $incidentService->update($incident, $request->validated());

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Incident updated successfully.');
    }

    /**
     * Delete the specified incident.
     */
    public function destroy(Request $request, Incident $incident, IncidentService $incidentService): RedirectResponse
    {
        abort_unless($incidentService->canDelete($request->user(), $incident), 403);

        $incidentService->delete($incident);

        return redirect()
            ->route('incidents.index')
            ->with('success', 'Incident deleted successfully.');
    }

    /**
     * Get active taxonomy options for incident forms.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => IncidentCategory::query()->active()->ordered()->get(),
            'severityLevels' => SeverityLevel::query()->active()->ordered()->get(),
            'priorityLevels' => PriorityLevel::query()->active()->ordered()->get(),
        ];
    }
}
