# Cyber Security Incident Management Database Design

## 1. Project Database Design Overview

This database supports a Cyber Security Incident Management Platform built with Laravel 12. The platform is designed to handle incident reporting, triage, analyst assignment, investigation notes, indicators of compromise, evidence tracking, response actions, audit logging, and security reporting.

Laravel default authentication tables will be reused where suitable, especially for users, password reset tokens, and sessions. This keeps the authentication foundation consistent with Laravel conventions while allowing the application to add domain-specific security workflow tables around it.

Because this system stores security-critical operational records, physical deletion should generally be avoided. Incidents, evidence metadata, investigation notes, assignments, and audit events should preserve historical context. Status flags, `is_active` fields, append-only audit logs, and carefully scoped retention policies should be preferred over destructive deletion.

## 2. Core Authentication Tables

### users

The `users` table stores authenticated application users, including administrators, security managers, SOC analysts, and employee reporters. Laravel's default users table can be extended where needed.

Future useful columns include:

- `id`
- `name`
- `email`
- `password`
- `email_verified_at`
- `is_active`
- `created_at`
- `updated_at`

Recommended notes:

- `email` should remain unique.
- `password` should store only hashed values.
- `is_active` should be used to deactivate users without deleting historical associations.
- Users should be related to incidents, assignments, notes, IOCs, response actions, reports, and audit logs.

### password_reset_tokens

The `password_reset_tokens` table supports Laravel's password reset workflow.

Recommended columns:

- `email`
- `token`
- `created_at`

Recommended notes:

- This table should follow Laravel defaults.
- Tokens should be temporary and invalidated according to the configured password reset expiration policy.

### sessions

The `sessions` table supports database-backed Laravel sessions.

Recommended columns:

- `id`
- `user_id`
- `ip_address`
- `user_agent`
- `payload`
- `last_activity`

Recommended notes:

- `user_id` should be indexed for user session lookup.
- `last_activity` should be indexed for session cleanup.
- Session storage can support operational visibility into active application usage.

## 3. Role & Permission Management

### roles

The `roles` table defines high-level responsibility groups.

Recommended columns:

- `id`
- `name`
- `slug`
- `description`
- `is_active`
- `created_at`
- `updated_at`

Example roles:

- Super Admin
- Security Manager
- SOC Analyst
- Reporter / Employee

### permissions

The `permissions` table defines granular application abilities.

Recommended columns:

- `id`
- `name`
- `slug`
- `description`
- `created_at`
- `updated_at`

Example permissions:

- `incident.view`
- `incident.create`
- `incident.assign`
- `incident.investigate`
- `report.view`

### role_user

The `role_user` table is a many-to-many pivot table between `users` and `roles`.

Recommended columns:

- `id`
- `user_id`
- `role_id`
- `created_at`
- `updated_at`

Recommended notes:

- Add a unique composite index on `user_id` and `role_id`.
- Use foreign keys to preserve referential integrity.

### permission_role

The `permission_role` table is a many-to-many pivot table between `roles` and `permissions`.

Recommended columns:

- `id`
- `role_id`
- `permission_id`
- `created_at`
- `updated_at`

Recommended notes:

- Add a unique composite index on `role_id` and `permission_id`.
- Permissions should be assigned to roles rather than directly to users in the initial design.

## 4. Incident Setup / Master Data

Master data tables provide controlled values for incident classification, dashboard filtering, reporting, and workflow consistency.

### incident_categories

Recommended columns:

- `id`
- `name`
- `slug`
- `description` nullable
- `color` nullable
- `sort_order`
- `is_active`
- `created_at`
- `updated_at`

Example records:

- Malware
- Phishing
- Unauthorized Access
- Data Breach
- Network Attack
- Policy Violation

### severity_levels

Recommended columns:

- `id`
- `name`
- `slug`
- `description` nullable
- `color` nullable
- `sort_order`
- `is_active`
- `created_at`
- `updated_at`

Example records:

- Low
- Medium
- High
- Critical

### priority_levels

Recommended columns:

- `id`
- `name`
- `slug`
- `description` nullable
- `color` nullable
- `sort_order`
- `is_active`
- `created_at`
- `updated_at`

Example records:

- Low
- Medium
- High
- Urgent

### incident_statuses

Recommended columns:

- `id`
- `name`
- `slug`
- `description` nullable
- `color` nullable
- `sort_order`
- `is_active`
- `created_at`
- `updated_at`

Example records:

- Reported
- Triaged
- Assigned
- Investigating
- Contained
- Resolved
- Closed

Recommended notes for all master data tables:

- `slug` should be unique.
- `sort_order` should control display ordering.
- `is_active` should be used instead of deleting records that may be linked to historical incidents.

## 5. Incident Reporting

### incidents

The `incidents` table is the central operational table for reported cyber security incidents.

Recommended columns:

- `id`
- `incident_number` unique
- `title`
- `description`
- `category_id`
- `severity_level_id`
- `priority_level_id`
- `status_id`
- `reported_by_id`
- `current_assigned_to_id` nullable
- `detection_source` nullable
- `affected_asset` nullable
- `occurred_at` nullable
- `reported_at`
- `resolved_at` nullable
- `closed_at` nullable
- `created_at`
- `updated_at`

Relationships:

- An incident belongs to an incident category.
- An incident belongs to a severity level.
- An incident belongs to a priority level.
- An incident belongs to an incident status.
- An incident belongs to a reporter user through `reported_by_id`.
- An incident may belong to the current assigned analyst user through `current_assigned_to_id`.

Useful indexes:

- Unique index on `incident_number`
- Index on `status_id`
- Index on `severity_level_id`
- Index on `priority_level_id`
- Index on `category_id`
- Index on `reported_by_id`
- Index on `current_assigned_to_id`
- Index on `reported_at`

Recommended notes:

- `incident_number` should be human-readable and unique, such as `CSI-2026-000001`.
- `reported_at` should represent when the incident entered the platform.
- `occurred_at` should represent the known or estimated occurrence time, if available.
- `resolved_at` and `closed_at` should support lifecycle reporting.

## 6. Analyst Assignment Workflow

### incident_assignments

The `incident_assignments` table stores assignment history for incidents.

Recommended columns:

- `id`
- `incident_id`
- `assigned_to_id`
- `assigned_by_id`
- `note` nullable
- `assigned_at`
- `created_at`
- `updated_at`

Recommended notes:

- `incident_id` references `incidents`.
- `assigned_to_id` references the analyst user receiving the assignment.
- `assigned_by_id` references the user who made the assignment.
- This table keeps full assignment history.
- `incidents.current_assigned_to_id` keeps the latest analyst for quick filtering and dashboards.

## 7. Investigation Notes

### investigation_notes

The `investigation_notes` table stores the SOC analyst investigation timeline for each incident.

Recommended columns:

- `id`
- `incident_id`
- `user_id`
- `note`
- `visibility` default internal
- `created_at`
- `updated_at`

Recommended notes:

- `incident_id` references `incidents`.
- `user_id` references the analyst or user who wrote the note.
- `visibility` can support values such as `internal`, `manager`, or `reporter`.
- Notes should generally be retained because they represent investigation history.

## 8. IOC Management

### incident_iocs

The `incident_iocs` table stores indicators of compromise connected to incidents.

Recommended columns:

- `id`
- `incident_id`
- `type`
- `value` string length 2048
- `description` nullable text
- `confidence` nullable string
- `first_seen_at` nullable timestamp
- `last_seen_at` nullable timestamp
- `created_by_id`
- `created_at`
- `updated_at`

Implemented IOC type values:

- `ip_address`
- `domain`
- `url`
- `file_hash`
- `email_address`
- `malware_filename`
- `process_name`
- `registry_key`
- `other`

Recommended notes:

- `incident_id` references `incidents`.
- `created_by_id` references the user who recorded the IOC.
- `type` should be constrained by application validation or a controlled list.
- `value` can store long URL or hash-style indicators up to 2048 characters.
- Implemented indexes include `incident_id`, `type`, `value`, `created_by_id`, `first_seen_at`, `last_seen_at`, and a non-unique composite index on `incident_id`, `type`, and `value`.
- `incident_iocs.incident_id` uses cascade delete in the current migration, matching the implemented incident child-resource convention.
- Eloquent relationships are `Incident::iocs()`, `IncidentIoc::incident()`, `IncidentIoc::createdBy()`, and `User::createdIocs()`.

Implemented authorization:

- `ioc.view` allows viewing the IOC panel on the incident detail page.
- `ioc.manage` allows creating, updating, and deleting IOC records.
- Security Manager and SOC Analyst roles receive both IOC permissions.
- Reporter / Employee does not receive IOC permissions.
- Super Admin passes IOC authorization through the global authorization override.

Implemented nested incident routes:

- `POST /incidents/{incident}/iocs` named `incidents.iocs.store`
- `PATCH /incidents/{incident}/iocs/{incidentIoc}` named `incidents.iocs.update`
- `DELETE /incidents/{incident}/iocs/{incidentIoc}` named `incidents.iocs.destroy`

Implemented UI and tests:

- The incident show page includes an IOC panel visible to users with `ioc.view`.
- IOC create, edit, and delete controls are visible only to users with `ioc.manage`.
- IOC coverage includes `IncidentIocDataModelTest`, `IncidentIocAuthorizationTest`, `IncidentIocWorkflowTest`, and `IncidentIocUiTest`.

## 9. Evidence / Attachment Tracking

### incident_evidences

The `incident_evidences` table stores metadata for files and evidence attached to incidents.

Recommended columns:

- `id`
- `incident_id`
- `uploaded_by_id`
- `title`
- `description` nullable
- `original_filename`
- `stored_path`
- `mime_type` nullable
- `file_size` nullable
- `checksum_sha256` nullable
- `created_at`
- `updated_at`

Recommended notes:

- Actual files should be stored in Laravel storage.
- The database should store metadata needed for tracking, review, integrity checks, and audit history.
- `checksum_sha256` can help verify evidence integrity.
- Evidence metadata should be retained even if file storage handling changes later.

## 10. Response Actions

### response_actions

The `response_actions` table records containment, eradication, recovery, communication, monitoring, lessons learned, and other response work performed for an incident.

Recommended columns:

- `id`
- `incident_id`
- `performed_by`
- `action_type`
- `status`
- `title`
- `description` nullable
- `started_at` nullable
- `completed_at` nullable
- `created_at`
- `updated_at`

Implemented action type values:

- `containment`
- `eradication`
- `recovery`
- `communication`
- `monitoring`
- `lessons_learned`
- `other`

Implemented status values:

- `planned`
- `in_progress`
- `completed`
- `cancelled`

Recommended notes:

- `response_actions.incident_id` references `incidents.id`.
- `response_actions.performed_by` references `users.id`.
- `performed_by` is nullable so historical response records can remain readable if an account is removed under exceptional circumstances.
- `action_type` and `status` are stored as strings for workflow flexibility, with application-level validation controlling allowed values.
- `started_at` and `completed_at` capture operational timing separately from record creation and update timestamps.
- These records support post-incident review, response accountability, and operational reporting.
- Eloquent relationships are `Incident::responseActions()`, `ResponseAction::incident()`, `ResponseAction::performedBy()`, and `User::performedResponseActions()`.

Implemented authorization:

- `response-action.view` allows viewing the Response Actions panel on the incident detail page.
- `response-action.manage` allows creating, updating, and deleting response action records.
- Security Manager and SOC Analyst roles receive both Response Action permissions.
- Reporter / Employee does not receive Response Action permissions.
- Super Admin passes Response Action authorization through the global authorization override.

Implemented nested incident routes:

- `POST /incidents/{incident}/response-actions` named `incidents.response-actions.store`
- `PATCH /incidents/{incident}/response-actions/{responseAction}` named `incidents.response-actions.update`
- `DELETE /incidents/{incident}/response-actions/{responseAction}` named `incidents.response-actions.destroy`

Implemented UI and tests:

- The incident show page includes a Response Actions panel visible to users with `response-action.view`.
- Response Action create, edit, and delete controls are visible only to users with `response-action.manage`.
- Response Action coverage includes `ResponseActionDataModelTest`, `ResponseActionAuthorizationTest`, `ResponseActionWorkflowTest`, and `ResponseActionUiTest`.

## 11. Audit Logs

### audit_logs

The `audit_logs` table tracks security-relevant changes across the platform.

Recommended columns:

- `id`
- `user_id` nullable
- `event`
- `auditable_type` nullable
- `auditable_id` nullable
- `old_values` json nullable
- `new_values` json nullable
- `ip_address` nullable
- `user_agent` nullable
- `created_at`

Recommended notes:

- `user_id` references the user who performed the action, when available.
- `auditable_type` and `auditable_id` support polymorphic tracking of changed records.
- `old_values` and `new_values` should use JSON columns for structured change data.
- Audit logs should be append-only in normal operation.
- This table is important for accountability, investigations, compliance review, and security monitoring.

## 12. Security Reports

### security_reports

The `security_reports` table stores metadata for generated reports.

Recommended columns:

- `id`
- `generated_by_id`
- `title`
- `report_type`
- `filters` json nullable
- `file_path` nullable
- `generated_at`
- `created_at`
- `updated_at`

Recommended notes:

- `generated_by_id` references the user who generated the report.
- `filters` should store the report criteria as JSON.
- `file_path` can reference a generated PDF, CSV, or export stored in Laravel storage.
- Reports can be generated from incidents, statuses, severity, category, analyst workload, and date range.

## 13. Relationship Summary

- A user can report many incidents.
- A user can be assigned to many incidents through `incidents.current_assigned_to_id`.
- A user can have many historical assignments through `incident_assignments`.
- A user can write many investigation notes.
- A user can upload many evidence records.
- A user can perform many response actions.
- A user can generate many security reports.
- A user can have many roles through `role_user`.
- A role can have many permissions through `permission_role`.
- An incident belongs to one category, one severity level, one priority level, and one status.
- An incident can have many assignment history records.
- An incident can have many investigation notes.
- An incident can have many IOCs.
- An incident can have many evidence records.
- An incident can have many response actions.
- Audit logs can reference users and auditable records across the platform.

## 14. Delete and Retention Strategy

Master data should usually use `is_active` instead of deletion. This protects historical records that depend on categories, severity levels, priority levels, statuses, roles, or other controlled values.

Users should generally be deactivated, not deleted. Deactivation preserves ownership and accountability for reported incidents, assignments, notes, evidence uploads, response actions, generated reports, and audit events.

Incidents and investigation records should not be physically deleted in normal operation. They represent security history and may be needed for trend analysis, compliance, legal review, lessons learned, or future investigations.

Evidence records should preserve metadata even if file handling changes. If files are archived, relocated, or removed under a retention policy, the database should still retain enough metadata to explain what evidence existed and how it was handled.

Audit logs should be append-only. Updates and deletions should be avoided except under strictly controlled administrative or legal retention procedures.

## 15. Migration Implementation Notes

- Use foreign keys for relationships between incidents, users, master data, assignments, notes, IOCs, evidence records, response actions, reports, and audit logs.
- Use unique slugs where needed, especially for roles, permissions, incident categories, severity levels, priority levels, and incident statuses.
- Use indexes for common filters and dashboards, including status, severity, priority, category, reporter, assigned analyst, and report date ranges.
- Use JSON columns for audit `old_values`, audit `new_values`, and security report `filters`.
- Use timestamps consistently across application tables.
- Use `created_at` only for append-only audit records unless update tracking is explicitly required.
- Consider soft deletes only where it makes sense, but avoid hiding security-critical historical records from normal operational review.
- Keep migration names clear and grouped by domain so the schema remains easy to review in a portfolio or recruiter walkthrough.
