<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the application's RBAC permissions.
     */
    public function run(): void
    {
        $permissions = [
            ['Dashboard View', 'dashboard.view', 'dashboard'],
            ['User View', 'user.view', 'users'],
            ['User Create', 'user.create', 'users'],
            ['User Update', 'user.update', 'users'],
            ['User Delete', 'user.delete', 'users'],
            ['Role View', 'role.view', 'roles'],
            ['Role Create', 'role.create', 'roles'],
            ['Role Update', 'role.update', 'roles'],
            ['Role Delete', 'role.delete', 'roles'],
            ['Incident View', 'incident.view', 'incidents'],
            ['Incident Create', 'incident.create', 'incidents'],
            ['Incident Update', 'incident.update', 'incidents'],
            ['Incident Delete', 'incident.delete', 'incidents'],
            ['Incident Assign', 'incident.assign', 'incidents'],
            ['Incident Investigate', 'incident.investigate', 'incidents'],
            ['Incident Close', 'incident.close', 'incidents'],
            ['Incident Status Update', 'incident.status.update', 'incidents'],
            ['Incident Category View', 'incident-category.view', 'incident setup'],
            ['Incident Category Manage', 'incident-category.manage', 'incident setup'],
            ['Severity Level View', 'severity-level.view', 'incident setup'],
            ['Severity Level Manage', 'severity-level.manage', 'incident setup'],
            ['Priority Level View', 'priority-level.view', 'incident setup'],
            ['Priority Level Manage', 'priority-level.manage', 'incident setup'],
            ['Investigation Note View', 'investigation-note.view', 'investigations'],
            ['Investigation Note Create', 'investigation-note.create', 'investigations'],
            ['IOC View', 'ioc.view', 'indicators of compromise'],
            ['IOC Manage', 'ioc.manage', 'indicators of compromise'],
            ['Evidence View', 'evidence.view', 'evidence'],
            ['Evidence Manage', 'evidence.manage', 'evidence'],
            ['Response Action View', 'response-action.view', 'response actions'],
            ['Response Action Manage', 'response-action.manage', 'response actions'],
            ['Report View', 'report.view', 'reports'],
            ['Audit Log View', 'audit-log.view', 'audit logs'],
        ];

        foreach ($permissions as [$name, $slug, $groupName]) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'group_name' => $groupName,
                    'description' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
