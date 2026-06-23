# Cyber Security Incident Management Migration Plan

## 1. Migration Plan Overview

This document defines the safe implementation order for database migrations in the Cyber Security Incident Management Laravel 12 project. It is intended to act as a clear implementation blueprint before any custom migrations are created.

Tables with no dependencies must be created before tables that contain foreign keys. This keeps migrations predictable, avoids failed constraints during setup, and makes rollback planning easier.

Laravel default authentication migrations already provide several foundational tables depending on the project setup, including `users`, `password_reset_tokens`, `sessions`, and cache or job-related tables. Custom security incident management tables should build on top of those defaults instead of duplicating core framework responsibilities.

## 2. Existing Laravel Default Tables

Default Laravel tables that may already exist include:

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

Implementation notes:

- `users` will be extended later with `is_active` if needed.
- `password_reset_tokens` should remain close to Laravel defaults.
- `sessions` should remain close to Laravel defaults unless a specific security or operational requirement justifies changes.
- Cache and queue tables should follow Laravel's generated structure so framework features continue to work reliably.

## 3. Recommended Custom Migration Order

### Phase 1: Role and Permission Foundation

1. `create_roles_table`
2. `create_permissions_table`
3. `create_role_user_table`
4. `create_permission_role_table`

### Phase 2: Incident Master Data

5. `create_incident_categories_table`
6. `create_severity_levels_table`
7. `create_priority_levels_table`
8. `create_incident_statuses_table`

### Phase 3: Main Incident Record

9. `create_incidents_table`

### Phase 4: Incident Workflow Tables

10. `create_incident_assignments_table`
11. `create_investigation_notes_table`
12. `create_incident_iocs_table`
13. `create_incident_evidences_table`
14. `create_response_actions_table`

### Phase 5: Audit and Reporting

15. `create_audit_logs_table`
16. `create_security_reports_table`

## 4. Foreign Key Dependency Notes

- `role_user` depends on `users` and `roles`.
- `permission_role` depends on `roles` and `permissions`.
- `incidents` depends on `users`, `incident_categories`, `severity_levels`, `priority_levels`, and `incident_statuses`.
- `incident_assignments` depends on `incidents` and `users`.
- `investigation_notes` depends on `incidents` and `users`.
- `incident_iocs` depends on `incidents` and `users`.
- `incident_evidences` depends on `incidents` and `users`.
- `response_actions` depends on `incidents` and `users`.
- `audit_logs` optionally depends on `users`, but `auditable_type` and `auditable_id` should be polymorphic-style fields without strict foreign keys.
- `security_reports` depends on `users`.

Foreign keys should be added only after the referenced parent tables exist. For optional user references, nullable foreign keys should be used where system-generated events or unauthenticated activity may need to be recorded.

## 5. Indexing Plan

Recommended indexes:

- `roles.slug` unique
- `permissions.slug` unique
- Master data `slug` unique
- `incidents.incident_number` unique
- `incidents.status_id`
- `incidents.severity_level_id`
- `incidents.priority_level_id`
- `incidents.category_id`
- `incidents.reported_by_id`
- `incidents.current_assigned_to_id`
- `incidents.reported_at`
- `incident_assignments.incident_id`
- `investigation_notes.incident_id`
- `incident_iocs.incident_id`
- `incident_iocs.type`
- `incident_iocs.value`
- `incident_evidences.incident_id`
- `response_actions.incident_id`
- `audit_logs.user_id`
- `audit_logs.event`
- `audit_logs.auditable_type` and `audit_logs.auditable_id`
- `security_reports.generated_by_id`
- `security_reports.report_type`
- `security_reports.generated_at`

These indexes support common dashboards, filters, detail pages, incident timelines, audit searches, and generated reports. Additional composite indexes can be added later after real query patterns are known.

## 6. Seeder Planning Notes

Future seeders should include:

- `RoleSeeder`
- `PermissionSeeder`
- `RolePermissionSeeder`
- `IncidentCategorySeeder`
- `SeverityLevelSeeder`
- `PriorityLevelSeeder`
- `IncidentStatusSeeder`
- `SuperAdminSeeder`

Seeders should be created after migrations are ready. This ensures every seeded record has a stable table structure, required constraints, and predictable relationships. Initial seed data should focus on production-ready defaults such as core roles, permission mappings, incident categories, severity levels, priority levels, incident statuses, and a controlled first administrator account.

## 7. Rollback Safety Notes

Child tables should be dropped before parent tables. For example, incident workflow tables should be dropped before `incidents`, and pivot tables should be dropped before `roles` or `permissions`.

Migration `down` methods should reverse the creation order. This prevents foreign key errors during rollback and keeps local development workflows reliable.

Avoid dropping audit or security history in production. Rollbacks are mainly for local development before production release, test environments, or controlled pre-release correction. Once the application is live, schema changes should be handled through forward-only corrective migrations whenever possible.

## 8. Common Mistakes to Avoid

- Creating `incidents` before master data tables.
- Forgetting indexes for dashboard filters.
- Using cascade delete on security-critical incident history.
- Physically deleting users and breaking historical accountability.
- Storing evidence files directly in the database instead of Laravel storage.
- Making audit logs editable like normal records.

## 9. Final Implementation Rule

This document is the migration blueprint for the Cyber Security Incident Management Laravel 12 project.

Actual migrations should be implemented in the next database implementation step only after this plan is reviewed.
