# Testing Strategy

## Testing Scope Overview

This document summarizes the testing strategy for the Cyber Security Incident Management Laravel 12 project. The testing scope focuses on protecting critical application behavior across authentication, authorization, incident workflows, embedded incident modules, reporting, and route access boundaries.

The suite is designed to be practical and recruiter-friendly: tests verify real workflows, permission boundaries, model relationships, validation behavior, and regression-prone routes without relying on mock feature claims or percentage-based coverage numbers.

## Test Suite Summary

The project uses Laravel feature tests with `RefreshDatabase` for deterministic database state. Tests create only the roles, permissions, taxonomy records, incidents, and related records needed for each scenario.

The suite covers:

- Authentication
- Authorization / RBAC
- Dashboard metrics
- Incident setup taxonomy
- Incident reporting
- Incident assignment
- Incident status regression behavior
- Incident data model relationships
- Investigation notes
- IOC management
- Evidence tracking
- Response actions
- Security reports
- Route access regression boundaries

## How to Run Tests

Run the full backend test suite with:

```bash
php artisan test
```

This is the primary backend quality gate before opening a pull request.

## How to Run Focused Tests

Focused tests are useful while working on a specific module or regression area:

```bash
php artisan test --filter=RouteAccessRegressionTest
php artisan test --filter=IncidentStatusWorkflowTest
php artisan test --filter=IncidentDataModelTest
php artisan test --filter=SecurityReportTest
```

Use focused tests during development, then run the full suite before finishing the branch.

## Frontend Build Verification

Run the production frontend build with:

```bash
npm run build
```

This verifies that Vite can compile the Bootstrap, Tabler-inspired styling, JavaScript assets, and Blade-linked frontend bundle successfully.

## Covered Areas

### Authentication

Authentication tests verify login page rendering, valid login, invalid credentials, inactive-user blocking, logout, guest redirects, and authenticated dashboard access.

### Authorization / RBAC

Authorization tests verify permission middleware, role middleware, Gate behavior, Super Admin override behavior, and permission-aware Blade rendering.

### Dashboard Metrics

Dashboard tests verify role-aware metrics, recent incidents, empty-state behavior, reporter-only visibility, assigned SOC Analyst visibility, and organization-wide Security Manager visibility.

### Incident Setup Taxonomy

Incident category, severity level, and priority level tests verify CRUD behavior, validation, permissions, and deactivate behavior for setup data.

### Incident Reporting

Incident reporting tests verify create, view, update, delete restrictions, reporter ownership boundaries, operational-role access, and incident number generation.

### Incident Assignment

Assignment tests verify current assignee storage, assignment history, assignable-user business rules, route permissions, duplicate assignment prevention, and assignment UI visibility.

### Incident Status Regression Behavior

Status regression tests verify that the current general incident update route does not silently persist status payloads and that reporter update restrictions cannot be bypassed with status input.

### Incident Data Model Relationships

Incident data model tests verify the core `Incident` relationships to reporter, taxonomy records, current assignee, assignments, investigation notes, IOCs, evidences, and response actions. They also verify important datetime casts and soft delete behavior.

### Investigation Notes

Investigation note tests verify RBAC permissions, nested incident routes, create/update/delete workflow, incident ownership checks, and incident detail page UI visibility.

### IOC Management

IOC tests verify data model relationships, permission seeding, backend workflow validation, nested incident ownership checks, and incident detail page UI integration.

### Evidence Tracking

Evidence tests verify metadata storage, permission behavior, upload/update/delete/download workflow, soft delete behavior, and incident detail page UI visibility.

### Response Actions

Response action tests verify model relationships, RBAC permissions, backend workflow validation, nested route ownership checks, and incident detail page UI integration.

### Security Reports

Security report tests verify report access, validated filters, role-aware visibility, summary and grouped report consistency, CSV export authorization, CSV headers, and filtered CSV content.

### Route Access Regression Boundaries

Route regression tests verify high-value named route boundaries for guests, permitted users, forbidden users, incident edit access, and Security Reports CSV export access.

## New Regression Coverage Added in Testing Scope

The testing scope added these focused regression files:

- `tests/Feature/RouteAccessRegressionTest.php`
- `tests/Feature/Incident/IncidentStatusWorkflowTest.php`
- `tests/Feature/Incident/IncidentDataModelTest.php`

These files strengthen confidence around application route access, incident status update behavior, and core Incident model relationships.

## Quality Gate Before Pull Request

Before opening a pull request, run:

```bash
git diff --check
php artisan test
npm run build
```

Recommended review checklist:

- Confirm no whitespace errors are present.
- Confirm the full Laravel test suite passes.
- Confirm the frontend production build passes.
- Confirm new tests are focused on implemented behavior.
- Confirm no unrelated production code was changed.
- Confirm no percentage-based coverage claims are made without a real coverage report.

## Common Testing Mistakes

- Testing behavior that has not been implemented yet.
- Weakening authorization assertions to make tests pass.
- Using broad text assertions that match unrelated page content.
- Forgetting to create required permissions before testing forbidden responses.
- Depending on seeders when the test only needs a small role or permission fixture.
- Skipping `RefreshDatabase` for tests that write records.
- Adding fragile UI assertions for markup that is not part of the behavior being tested.
- Claiming code coverage percentages without generating a coverage report.

## Current Verified Status

- Branch used: `feature/testing-suite`
- Laravel Framework: `12.62.0`
- `php artisan test`: 235 tests passed, 872 assertions
- `npm run build`: passed

This status reflects the completed Testing scope at the time this document was written.
