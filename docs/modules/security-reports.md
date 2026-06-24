# Security Reports Module

## Purpose

The Security Reports module provides a filtered, permission-protected reporting page for reviewing incident activity across the Cyber Security Incident Management platform. It summarizes existing incident data without creating separate reporting records, charts, or exports stored on disk.

This module is designed for operational review by security leadership and SOC users. It uses the same incident visibility rules as the production dashboard so report totals, grouped sections, recent incidents, and CSV exports stay aligned with each user's role.

## Routes

| Method | URI | Route Name | Purpose |
| --- | --- | --- | --- |
| GET | `/reports/security` | `reports.security.index` | Display the filtered Security Reports page. |
| GET | `/reports/security/export` | `reports.security.export` | Download a filtered CSV export. |

Both routes are protected by the authenticated web guard and the `report.view` permission.

## Required Permission

- `report.view`

Users without `report.view` are blocked from both the report page and CSV export.

## Role-Based Visibility

- Super Admin and Security Manager users can view organization-wide report data.
- SOC Analyst users can view assigned incident report data when they have `report.view`.
- Reporter / Employee users do not receive `report.view` by default and are blocked unless the permission is explicitly granted.

The report service applies visibility before filters, aggregates, recent incident queries, and CSV export rows are generated.

## Available Filters

The report page and CSV export support the same validated filters:

- `date_from`
- `date_to`
- `status`
- `severity`
- `priority`
- `category`
- `assigned analyst`

Filters are applied consistently across summary cards, grouped report sections, analyst workload, recent incidents, and CSV exports.

## Report Sections

The Security Reports page includes:

- Summary cards
- Incidents by status
- Incidents by severity
- Incidents by priority
- Incidents by category
- Analyst workload
- Recent incidents

All report sections are calculated from existing incident records and related master data.

## CSV Export

The CSV export preserves the current report filters by passing the active query string to `reports.security.export`.

CSV export behavior:

- Uses the same `report.view` permission as the report page.
- Uses the same role-based visibility rules as the report page.
- Uses the same validated filters as the report page.
- Streams the CSV response directly to the browser.
- Does not store generated files in Laravel storage.

CSV columns:

- Incident Number
- Title
- Status
- Category
- Severity
- Priority
- Reporter
- Assigned Analyst
- Reported At / Created At
- Updated At

The generated filename follows this pattern:

```text
security-reports-YYYY-MM-DD-HHMMSS.csv
```

## Implementation Notes

- `SecurityReportController` stays thin and delegates report preparation to services.
- `ReportFilterRequest` validates report and export filters.
- `SecurityReportService` builds the shared role-aware and filter-aware incident query.
- `SecurityReportExportService` reuses the shared incident query for CSV rows.
- No external reporting package is required.
- No charts or stored report files are included in this scope.

## Test Coverage

`SecurityReportTest` covers:

- Authorized access to the report page.
- Guest redirection to login.
- Permission denial for users without `report.view`.
- Accepted and rejected filters.
- Role-aware report visibility.
- Filtered summary, grouped sections, and recent incident consistency.
- CSV export access control.
- CSV export headers.
- CSV export filtered inclusion and exclusion.
- SOC Analyst assigned-only CSV export visibility.

Useful verification commands:

```bash
php artisan test --filter=SecurityReportTest
php artisan test
```
