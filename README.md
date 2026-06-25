# Cyber Security Incident Management – Laravel

## Short Project Summary

Cyber Security Incident Management – Laravel is a production-style Laravel 12 platform for reporting, triaging, assigning, investigating, and tracking cyber security incidents.

The system supports incident taxonomy, analyst assignment, investigation notes, IOC management, evidence and attachment tracking, response actions, status workflow, audit logs, dashboards, and security reports. It is designed as a portfolio-ready Laravel application for backend, full-stack, and security-focused development roles.

## Tech Stack

- PHP 8.x
- Laravel 12
- MySQL
- Bootstrap 5
- Tabler UI
- jQuery
- DataTables
- SweetAlert2
- Vite
- Git / GitHub

## Key Features

### Authentication & Access Control

Session-based authentication with protected application routes, guest redirects, logout handling, and permission-gated access to core modules.

### Custom RBAC

Custom role and permission foundation with Gates, middleware, Super Admin override behavior, role assignment, permission sync, and guardrails for privileged roles.

### User Management

Administrative user management with role assignment, activation/deactivation workflows, and protections against unsafe Super Admin changes.

### Incident Taxonomy

Management screens for incident categories, severity levels, and priority levels used to classify and prioritize incident reports.

### Incident Reporting

Incident creation, listing, viewing, editing, and protected visibility based on role and permission rules.

### Assignment Workflow

Dedicated incident analyst assignment workflow with assignment history and restrictions around eligible assignees.

### Investigation Notes

Internal SOC note tracking for incident triage, analysis, and response documentation.

### IOC Management

Incident-linked Indicators of Compromise including IP addresses, domains, URLs, file hashes, email addresses, malware filenames, process names, registry keys, and other IOC types.

### Evidence / Attachment Tracking

Private incident evidence metadata tracking with upload/download workflows, file metadata, SHA-256 checksum display, and permission-gated access.

### Response Actions

Incident response action tracking for containment, eradication, recovery, communication, monitoring, lessons learned, and other response work.

### Incident Status Workflow

Dedicated status transition workflow with controlled status changes, transition history, notes, and close-permission restrictions.

### Audit Logs

Read-only audit log module for important management and incident workflows, with filtering and safe value previews.

### Security Reports

Filtered security reporting page with summary metrics, breakdown tables, analyst workload, recent incidents, and CSV export.

### Dashboard

Role-aware dashboard metrics calculated from real incident data.

### Tabler Dark Mode UI

Dark-only Bootstrap/Tabler-inspired UI with polished layouts for authentication, dashboard, management pages, incident workflows, reports, and audit logs.

## User Roles

- **Super Admin**: Full system-level access through global authorization override and protected administrative guardrails.
- **Security Manager**: Operational management role for incident oversight, assignments, reporting, setup data, and security workflows.
- **SOC Analyst**: Security operations role for assigned incident work, investigation notes, IOCs, evidence, and response actions.
- **Reporter / Employee**: Limited reporting role for submitting incidents and viewing permitted incident information.

## Architecture Highlights

- Thin controllers where possible.
- Form Requests for validation.
- Service classes for business workflows and transaction-backed operations.
- Eloquent relationships across users, roles, permissions, incidents, assignments, notes, IOCs, evidence, response actions, status transitions, audit logs, and reports.
- Custom RBAC middleware and Gates.
- Audit logging for important workflows.
- Permission-gated UI and route access.
- Soft delete or deactivation patterns where appropriate.

## Security Highlights

- Guest route protection for authenticated application pages.
- Permission-based authorization across modules.
- Super Admin guardrails.
- Last active Super Admin protection.
- Reporter data visibility restrictions.
- Analyst assignment restrictions.
- Status transition restrictions.
- Evidence access restrictions.
- Sensitive values are not exposed in audit logs where applicable.

## Testing & Verification

Latest verified result:

- `npm run build`: passed
- `php artisan test`: passed
- 341 tests passed
- 1364 assertions passed

The test suite covers authentication, authorization middleware, RBAC foundation, user management, role permission management, incident setup modules, incident reporting, incident assignment, investigation notes, IOC workflow, evidence workflow, response actions, incident status workflow, dashboard metrics, security reports, audit logs, route access regression, and UI navigation visibility.

## Local Installation

Example local setup using PowerShell on Windows:

```powershell
cd E:\laravel-projects
git clone <repository-url>
cd cyber-security-incident-management-laravel

composer install
npm install

Copy-Item .env.example .env
php artisan key:generate
```

Configure the database values in `.env` for your local MySQL setup, then run:

```powershell
php artisan migrate --seed
npm run dev
php artisan serve
```

The local application will typically be available at:

```text
http://127.0.0.1:8000
```

## Default Access / Seeded Users

Check the database seeders for the latest seeded development credentials.

For security and accuracy, this README does not hardcode development passwords.

## Documentation Links

- [Final Project Audit](docs/final-project-audit.md)
- [Database Design](docs/database/database-design.md)
- [Entity Relationship Diagram](docs/database/erd.md)
- [Migration Plan](docs/database/migration-plan.md)
- [Schema Blueprint](docs/database/schema-blueprint.md)
- [Testing Strategy](docs/testing/testing-strategy.md)
- [Security Reports Module Documentation](docs/modules/security-reports.md)
- [Navigation Implementation Audit](docs/final-audit/navigation-implementation-audit.md)

## Project Status

- Core modules completed.
- UI Upgrade Tabler Dark Mode completed.
- Final verification passed.
- Documentation polish completed.

## Portfolio Note

This project demonstrates Laravel backend architecture, custom RBAC, incident workflow design, auditability, reporting, file metadata handling, and security-focused full-stack development in a practical cyber security incident management domain.
