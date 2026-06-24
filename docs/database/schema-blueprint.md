# Cyber Security Incident Management Schema Blueprint

## 1. Schema Blueprint Overview

This document converts the database design for the Cyber Security Incident Management Laravel 12 project into migration-ready table definitions. It is intended to bridge the planning documents and the future migration implementation work.

Actual migrations will be created later after this blueprint is reviewed. This keeps the database implementation deliberate, easier to audit, and aligned with the security-focused workflow of the application.

Delete behavior is intentionally conservative because this is a security incident management system. Incident records, investigation timelines, assignments, evidence metadata, response actions, and audit logs may be needed for accountability, compliance, post-incident review, and long-term security analysis.

## 2. Laravel Default Tables

The project keeps Laravel default tables mostly unchanged:

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

The `users` table may later be extended with:

- `is_active` boolean default true

The `password_reset_tokens`, `sessions`, cache, and queue tables should remain close to Laravel defaults unless a specific project requirement justifies a change.

## 3. Naming Conventions

- Table names use `snake_case` plural names.
- Foreign keys use the singular model name plus `_id`.
- Slugs are unique where records are used in code, permissions, URLs, filters, or seeded configuration.
- Timestamps are used consistently across operational tables.
- Audit logs use `created_at` only because logs are append-only.
- JSON fields are used only where flexible snapshots or filter criteria are needed.

## 4. roles Table Blueprint

### Columns

- `id` big integer primary key
- `name` string
- `slug` string unique
- `description` text nullable
- `is_active` boolean default true
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Indexes

- Unique index on `slug`
- Index on `is_active`

### Delete Behavior

Roles should not normally be physically deleted after assignment. If a role is no longer needed, it should usually be marked inactive so historical user-role context remains understandable.

## 5. permissions Table Blueprint

### Columns

- `id` big integer primary key
- `name` string
- `slug` string unique
- `group_name` string nullable
- `description` text nullable
- `is_active` boolean default true
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Indexes

- Unique index on `slug`
- Index on `group_name`
- Index on `is_active`

## 6. role_user Pivot Table Blueprint

### Columns

- `id` big integer primary key
- `role_id` foreign id
- `user_id` foreign id
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Constraints

- Unique composite constraint on `role_id` and `user_id`
- `role_id` references `roles`
- `user_id` references `users`

### Delete Behavior

For local and development cleanup, pivot rows may cascade when a role or user is deleted. In production, users and roles should normally be deactivated instead of deleted.

## 7. permission_role Pivot Table Blueprint

### Columns

- `id` big integer primary key
- `permission_id` foreign id
- `role_id` foreign id
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Constraints

- Unique composite constraint on `permission_id` and `role_id`
- `permission_id` references `permissions`
- `role_id` references `roles`

### Delete Behavior

Pivot rows may cascade when a role or permission is deleted during controlled local and development cleanup.

## 8. incident_categories Table Blueprint

### Columns

- `id` big integer primary key
- `name` string
- `slug` string unique
- `description` text nullable
- `color` string nullable
- `sort_order` unsigned integer default 0
- `is_active` boolean default true
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Indexes

- Unique index on `slug`
- Index on `is_active`
- Index on `sort_order`

## 9. severity_levels Table Blueprint

### Columns

- `id` big integer primary key
- `name` string
- `slug` string unique
- `description` text nullable
- `color` string nullable
- `sort_order` unsigned integer default 0
- `is_active` boolean default true
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Indexes

- Unique index on `slug`
- Index on `is_active`
- Index on `sort_order`

## 10. priority_levels Table Blueprint

### Columns

- `id` big integer primary key
- `name` string
- `slug` string unique
- `description` text nullable
- `color` string nullable
- `sort_order` unsigned integer default 0
- `is_active` boolean default true
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Indexes

- Unique index on `slug`
- Index on `is_active`
- Index on `sort_order`

## 11. incident_statuses Table Blueprint

### Columns

- `id` big integer primary key
- `name` string
- `slug` string unique
- `description` text nullable
- `color` string nullable
- `sort_order` unsigned integer default 0
- `is_active` boolean default true
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Indexes

- Unique index on `slug`
- Index on `is_active`
- Index on `sort_order`

## 12. incidents Table Blueprint

### Columns

- `id` big integer primary key
- `incident_number` string unique
- `title` string
- `description` longText
- `category_id` foreign id
- `severity_level_id` foreign id
- `priority_level_id` foreign id
- `status_id` foreign id
- `reported_by_id` foreign id
- `current_assigned_to_id` nullable foreign id
- `detection_source` string nullable
- `affected_asset` string nullable
- `occurred_at` nullable datetime
- `reported_at` datetime
- `resolved_at` nullable datetime
- `closed_at` nullable datetime
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Migration Syntax Reference

Plain-text reference lines:

- incident_number string unique
- description longText

Laravel-style examples:

- `$table->string('incident_number')->unique();`
- `$table->longText('description');`

### Constraints

- `category_id` references `incident_categories`
- `severity_level_id` references `severity_levels`
- `priority_level_id` references `priority_levels`
- `status_id` references `incident_statuses`
- `reported_by_id` references `users`
- `current_assigned_to_id` references `users` nullable

### Delete Behavior

- Restrict deletion of referenced master data while incidents exist.
- Restrict deletion of reporter users while incidents exist.
- `current_assigned_to_id` may be set null if an analyst user is removed, but normal production behavior should be user deactivation.

### Indexes

- Unique index on `incident_number`
- Index on `category_id`
- Index on `severity_level_id`
- Index on `priority_level_id`
- Index on `status_id`
- Index on `reported_by_id`
- Index on `current_assigned_to_id`
- Index on `reported_at`
- Index on `occurred_at`
- Index on `resolved_at`
- Index on `closed_at`

## 13. incident_assignments Table Blueprint

### Columns

- `id` big integer primary key
- `incident_id` foreign id
- `assigned_to_id` foreign id
- `assigned_by_id` foreign id
- `note` text nullable
- `assigned_at` datetime
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Constraints

- `incident_id` references `incidents`
- `assigned_to_id` references `users`
- `assigned_by_id` references `users`

### Delete Behavior

- Restrict incident deletion when assignment history exists.
- Restrict user deletion to preserve accountability.

### Indexes

- Index on `incident_id`
- Index on `assigned_to_id`
- Index on `assigned_by_id`
- Index on `assigned_at`

## 14. investigation_notes Table Blueprint

### Columns

- `id` big integer primary key
- `incident_id` foreign id
- `user_id` foreign id
- `note` longText
- `visibility` string default internal
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Constraints

- `incident_id` references `incidents`
- `user_id` references `users`

### Indexes

- Index on `incident_id`
- Index on `user_id`
- Index on `visibility`
- Index on `created_at`

### Delete Behavior

Restrict deletion to preserve the investigation timeline.

## 15. incident_iocs Table Blueprint

### Columns

- `id` big integer primary key
- `incident_id` foreign id
- `type` string
- `value` string length 2048
- `description` text nullable
- `confidence` string nullable
- `first_seen_at` nullable timestamp
- `last_seen_at` nullable timestamp
- `created_by_id` foreign id
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Constraints

- `incident_id` references `incidents`
- `created_by_id` references `users`

### Implemented Type Values

- `ip_address`
- `domain`
- `url`
- `file_hash`
- `email_address`
- `malware_filename`
- `process_name`
- `registry_key`
- `other`

### Indexes

- Index on `incident_id`
- Index on `type`
- Index on `value`
- Index on `created_by_id`
- Index on `first_seen_at`
- Index on `last_seen_at`
- Non-unique composite index on `incident_id`, `type`, and `value`

### Optional Uniqueness

Do not enforce global uniqueness on `value`, because the same IOC may appear in different incidents. The implemented composite index on `incident_id`, `type`, and `value` supports incident-level IOC lookup and correlation without enforcing uniqueness. The `value` field supports long URL or hash-style values up to 2048 characters; MySQL implementations may use prefix indexing for this long string column.

### Delete Behavior

The implemented migration uses cascade delete from `incident_iocs.incident_id` to `incidents.id`, matching the current incident child-resource convention. The `created_by_id` column references `users`, preserving the user who recorded the IOC; production operation should prefer user deactivation over physical deletion.

### Eloquent Relationships

- `Incident::iocs()`
- `IncidentIoc::incident()`
- `IncidentIoc::createdBy()`
- `User::createdIocs()`

### Authorization, Routes, UI, and Tests

- Permissions: `ioc.view` and `ioc.manage`
- Role access: Security Manager and SOC Analyst have both IOC permissions; Reporter / Employee has none; Super Admin passes through global authorization behavior.
- Routes: `POST /incidents/{incident}/iocs` named `incidents.iocs.store`, `PATCH /incidents/{incident}/iocs/{incidentIoc}` named `incidents.iocs.update`, and `DELETE /incidents/{incident}/iocs/{incidentIoc}` named `incidents.iocs.destroy`
- UI: the incident show page displays the IOC panel for `ioc.view`; create, edit, and delete controls require `ioc.manage`
- Tests: `IncidentIocDataModelTest`, `IncidentIocAuthorizationTest`, `IncidentIocWorkflowTest`, and `IncidentIocUiTest`

## 16. incident_evidences Table Blueprint

### Columns

- `id` big integer primary key
- `incident_id` foreign id
- `uploaded_by_id` foreign id
- `title` string
- `description` text nullable
- `original_filename` string
- `stored_path` string
- `mime_type` string nullable
- `file_size` unsigned big integer nullable
- `checksum_sha256` string nullable
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Constraints

- `incident_id` references `incidents`
- `uploaded_by_id` references `users`

### Indexes

- Index on `incident_id`
- Index on `uploaded_by_id`
- Index on `checksum_sha256`
- Index on `created_at`

### Delete Behavior

Evidence metadata should be preserved. Files should be stored in Laravel storage, not directly in the database.

## 17. response_actions Table Blueprint

### Columns

- `id` big integer primary key
- `incident_id` foreign id
- `performed_by` nullable foreign id
- `action_type` string
- `status` string default planned
- `title` string
- `description` text nullable
- `started_at` timestamp nullable
- `completed_at` timestamp nullable
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Constraints

- `incident_id` references `incidents`
- `performed_by` references `users`

### Indexes

- Index on `incident_id`
- Index on `action_type`
- Index on `status`
- Index on `performed_by`

### Implementation Notes

- `action_type` and `status` are strings rather than database enums so workflow options can evolve without database-specific enum changes.
- Allowed `action_type` values are validated at the application layer: `containment`, `eradication`, `recovery`, `communication`, `monitoring`, `lessons_learned`, and `other`.
- Allowed `status` values are validated at the application layer: `planned`, `in_progress`, `completed`, and `cancelled`.
- Eloquent relationships are `Incident::responseActions()`, `ResponseAction::incident()`, `ResponseAction::performedBy()`, and `User::performedResponseActions()`.

## 18. audit_logs Table Blueprint

### Columns

- `id` big integer primary key
- `user_id` nullable foreign id
- `event` string
- `auditable_type` string nullable
- `auditable_id` unsigned big integer nullable
- `old_values` json nullable
- `new_values` json nullable
- `ip_address` string nullable
- `user_agent` text nullable
- `created_at` timestamp nullable

### Migration Syntax Reference

Plain-text reference lines:

- old_values json nullable
- new_values json nullable

Laravel-style examples:

- `$table->json('old_values')->nullable();`
- `$table->json('new_values')->nullable();`

### Constraints

- `user_id` references `users` nullable
- `auditable_type` and `auditable_id` should not use strict foreign keys because they support polymorphic-style references.

### Indexes

- Index on `user_id`
- Index on `event`
- Composite index on `auditable_type` and `auditable_id`
- Index on `created_at`

### Delete Behavior

- Audit logs should be append-only.
- Audit logs should not have `updated_at`.

## 19. security_reports Table Blueprint

### Columns

- `id` big integer primary key
- `generated_by_id` foreign id
- `title` string
- `report_type` string
- `filters` json nullable
- `file_path` string nullable
- `generated_at` datetime
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

### Migration Syntax Reference

Plain-text reference line:

- filters json nullable

Laravel-style example:

- `$table->json('filters')->nullable();`

### Constraints

- `generated_by_id` references `users`

### Indexes

- Index on `generated_by_id`
- Index on `report_type`
- Index on `generated_at`

## 20. Foreign Key Delete Behavior Summary

| Reference Type | Recommended Behavior | Notes |
| --- | --- | --- |
| Pivot tables | Cascade acceptable for local/dev cleanup | Production should normally deactivate users, roles, and permissions instead of deleting them. |
| Master data referenced by incidents | Restrict | Categories, severity levels, priority levels, and statuses should remain available for historical incidents. |
| Incident workflow records | Mixed by implemented table | Assignments, notes, evidences, and response actions are part of the security history. IOC records currently cascade with incident deletion according to the implemented `incident_iocs.incident_id` migration. |
| User references in security history | Mostly restrict | Accountability should be preserved for reporters, analysts, responders, and report generators. |
| Nullable optional references | `nullOnDelete` acceptable only when accountability is not broken | Example: `incidents.current_assigned_to_id` may be nullable for operational convenience. |
| Audit logs `user_id` | `nullOnDelete` acceptable only if user removal is forced | User deactivation is preferred so audit accountability remains intact. |

## 21. Implementation Notes for Future Migrations

- Prefer `foreignId()->constrained()` where table names follow Laravel conventions.
- Use `constrained('custom_table_name')` where needed.
- Use `restrictOnDelete()` for security-critical references.
- Use `nullOnDelete()` only for optional references.
- Use unique constraints for slugs and incident numbers.
- Use indexes for filters, dashboards, and reports.
- Keep migrations small and domain grouped.
