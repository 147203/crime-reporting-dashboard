# 03. Architecture and data model

Design source for Chapter 4 and for the code. Names below are proposals; the migrations become the source of truth once they exist (project rules).

## 1. Layers (proposal 3.7)

```mermaid
flowchart LR
    subgraph Client[Presentation layer: browser]
        U1[Administrator]
        U2[Reporting Officer]
        U3[Analyst]
    end
    subgraph App[Application layer: Laravel]
        MW[Middleware: auth, throttle, CSRF, headers]
        POL[Policies: role and station scope]
        CTL[Controllers]
        SVC[Services: incidents, quality, analytics, reports, import]
        REQ[Form Requests: validation]
    end
    subgraph Data[Data layer]
        DB[(MariaDB 10.4, XAMPP)]
        FS[[Private storage: import files, generated exports]]
    end
    U1 & U2 & U3 --> MW --> POL --> CTL
    CTL --> REQ --> SVC
    SVC --> DB
    SVC --> FS
    SVC -->|audit| DB
```

## 2. Code layout (Laravel 12, target)

```
app/
  Http/Controllers/        thin controllers
  Http/Requests/           Form Requests (validation)
  Http/Middleware/         security headers, station scope
  Models/                  User, Incident, Category, SubCounty, Ward, Station, AuditLog, ImportBatch
  Policies/                IncidentPolicy, UserPolicy, ...
  Services/                IncidentService, DuplicateDetector, PiiGuard, ImportService,
                           Analytics/ (one class per metric group), ReportService
  Support/                 value objects (Period, Filters)
config/analytics.php       hotspot and duplicate parameters, read from env
database/migrations/       schema
database/seeders/          reference data, admin (from env), sample data (deterministic)
resources/views/           Blade views and chart partials
tests/                     Unit, Feature, Security, Performance, oracle fixtures
```

## 3. Database schema (proposed)

| Table | Key columns | Notes |
|---|---|---|
| `users` | id, name, email (unique), password (hash), role (`admin`, `officer`, `analyst`), station_id (nullable), is_active, failed_attempts, locked_until, last_login_at | No self-registration |
| `sub_counties` | id, name (unique) | Reference |
| `wards` | id, sub_county_id (FK), name; unique (sub_county_id, name) | Reference |
| `stations` | id, sub_county_id (FK), name (unique) | Reference |
| `crime_categories` | id, name (unique), is_active | Theft, robbery, burglary, assault, other |
| `incidents` | id, reference_no (unique), category_id, occurred_at, reported_at, sub_county_id, ward_id, station_id, location_description, description, status, closed_at, duplicate_status, duplicate_of_id, is_synthetic, created_by, updated_by, created_at, updated_at, deleted_at | Never a coordinate column |
| `import_batches` | id, user_id, file_name, rows_total, rows_accepted, rows_rejected, error_report (json), created_at | Import history |
| `audit_logs` | id, user_id (nullable), action, entity_type, entity_id, meta (json, no sensitive content), ip_address, created_at | Append-only |
| `report_exports` | id, user_id, type, filters (json), created_at | Export history |

Indexes (named explicitly): `incidents(occurred_at)`, `incidents(category_id, occurred_at)`, `incidents(ward_id, occurred_at)`, `incidents(sub_county_id, occurred_at)`, `incidents(station_id, occurred_at)`, `incidents(status)`, `incidents(duplicate_status)`, `audit_logs(created_at)`, `audit_logs(user_id, created_at)`. Confirm with `EXPLAIN` on the analytics queries and record the plans in the test log.

```mermaid
erDiagram
    SUB_COUNTIES ||--o{ WARDS : contains
    SUB_COUNTIES ||--o{ STATIONS : hosts
    STATIONS ||--o{ USERS : employs
    CRIME_CATEGORIES ||--o{ INCIDENTS : classifies
    WARDS ||--o{ INCIDENTS : located_in
    STATIONS ||--o{ INCIDENTS : records
    USERS ||--o{ INCIDENTS : creates
    USERS ||--o{ AUDIT_LOGS : performs
    USERS ||--o{ IMPORT_BATCHES : runs
    INCIDENTS ||--o| INCIDENTS : duplicate_of
    USERS {
        bigint id PK
        string email
        string role
        bigint station_id FK
        boolean is_active
        int failed_attempts
        datetime locked_until
    }
    INCIDENTS {
        bigint id PK
        string reference_no
        bigint category_id FK
        datetime occurred_at
        datetime reported_at
        bigint sub_county_id FK
        bigint ward_id FK
        bigint station_id FK
        string status
        string duplicate_status
        bigint duplicate_of_id FK
        boolean is_synthetic
    }
    WARDS {
        bigint id PK
        bigint sub_county_id FK
        string name
    }
    SUB_COUNTIES {
        bigint id PK
        string name
    }
    STATIONS {
        bigint id PK
        bigint sub_county_id FK
        string name
    }
    CRIME_CATEGORIES {
        bigint id PK
        string name
        boolean is_active
    }
    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK
        string action
        string entity_type
        bigint entity_id
    }
    IMPORT_BATCHES {
        bigint id PK
        bigint user_id FK
        int rows_total
        int rows_accepted
        int rows_rejected
    }
```

## 4. Use cases (Chapter 4 draft)

```mermaid
flowchart LR
    ADM([Administrator]) --> UC1((Manage users))
    ADM --> UC2((Manage reference data))
    ADM --> UC3((Import CSV))
    ADM --> UC4((View audit log))
    OFF([Reporting Officer]) --> UC5((Report incident))
    OFF --> UC6((Search own station records))
    ANA([Analyst]) --> UC7((View dashboards))
    ANA --> UC8((Review duplicates))
    ANA --> UC9((Export report))
    ADM --> UC7
    ADM --> UC8
    ADM --> UC9
    OFF --> UC7
    UC1 -.->|include| UC0((Log in))
    UC5 -.->|include| UC0
    UC7 -.->|include| UC0
    UC5 -.->|include| UC10((Validate record))
    UC3 -.->|include| UC10
    UC9 -.->|extend| UC7
```

Draw the final versions as UML use case diagrams in Visual Paradigm with a system boundary, per the faculty guide.

## 5. Key sequences (Chapter 4 drafts)

Report an incident:

```mermaid
sequenceDiagram
    participant O as Reporting Officer
    participant W as Browser
    participant C as IncidentController
    participant R as StoreIncidentRequest
    participant S as IncidentService
    participant Q as PiiGuard and DuplicateDetector
    participant D as MySQL
    O->>W: fill form, submit
    W->>C: POST /incidents (session, CSRF)
    C->>C: authorize (policy: officer, own station)
    C->>R: validate
    R-->>C: errors or validated data
    C->>S: create(validated)
    S->>Q: check PII, find duplicate candidates
    Q-->>S: ok, duplicate_status
    S->>D: insert incident, audit log
    D-->>S: id, reference_no
    S-->>C: incident
    C-->>W: redirect with confirmation
```

Analytics request:

```mermaid
sequenceDiagram
    participant A as Analyst
    participant W as Browser
    participant C as DashboardController
    participant F as Filters (validated)
    participant S as Analytics services
    participant D as MySQL
    A->>W: choose period and filters
    W->>C: GET /dashboard?...
    C->>C: authorize and apply role scope
    C->>F: validate filters
    C->>S: A1 to A10 with filters
    S->>D: indexed aggregate queries
    D-->>S: rows
    S-->>C: metrics with period, filters, n
    C-->>W: view with charts and table alternatives
```

## 6. Activity: bulk import (Chapter 4 draft)

```mermaid
flowchart TD
    A[Admin uploads CSV] --> B{Header and size valid?}
    B -- no --> X[Reject file, show reason]
    B -- yes --> C[Dry run: validate each row V1 to V8]
    C --> D[Show summary and per-row errors]
    D --> E{Admin confirms commit?}
    E -- no --> Y[Discard]
    E -- yes --> F[Insert accepted rows in a transaction]
    F --> G[Flag suspected duplicates]
    G --> H[Write import batch and audit log]
    H --> I[Show result and error report download]
```

## 7. Conceptual framework redraw (Figure 2.1 replacement)

Keep the input, process, output, outcome structure but add what the marker asked for and remove all GIS elements:

| Element | Content |
|---|---|
| Actors | Administrator, Reporting Officer, Analyst (with the access each has) |
| Inputs | Incident report (category, date and time, sub-county, ward, description); reference data; user accounts; sample or historical data via CSV |
| Process | Authenticate and authorise; validate; PII guard; duplicate detection; store; aggregate and analyse (A1 to A10); render dashboards and reports |
| Storage boundary | Central MySQL database; private file storage for imports and exports |
| Outputs | Dashboards, charts, area rankings and hotspot flags, PDF and CSV reports, audit log |
| Outcomes | Better record quality, faster insight into trends and peak times, better resource allocation, evidence-based decisions |
| Feedback loop | Data-quality summary and analyst findings improve reporting practice and validation rules |
| Removed | GPS coordinates, hotspot boundaries, crime maps, spatial analysis |

## 8. Security architecture

| Concern | Design |
|---|---|
| Authentication | Session auth with Laravel Fortify and own Bootstrap views (D-19), no public registration, preflight middleware refuses every request when a runtime secret is invalid (D-23), lockout counter on the user record, login throttling by email and IP |
| Authorization | Policies per model plus route middleware; station scope applied in a query scope so a missed check cannot leak rows |
| Secrets | Environment only; preflight refuses to boot on missing or short values |
| Input | Form Requests; query builder bindings; Blade escaping; CSV import parsed with strict header check and row limits |
| Transport and headers | HTTPS in any shared deployment; CSP and other headers by middleware, tested |
| Audit | Append-only table; no sensitive field content |
| Files | Uploads stored privately, size and type checked, deleted after processing |

## 9. Performance and caching

Aggregates are computed in SQL with the indexes above. If dashboards miss the target, cache filter-keyed aggregate results for a short time; the invalidation rule (any incident create, update, delete, duplicate resolution, or import clears the affected keys) is written first and covered by a test before the cache ships. Record `EXPLAIN` plans and timings in `logs/TEST_LOG.md`.

## 10. Wireframes and interface plan

Screens: login; dashboard; incident list with filters; incident create and edit; incident detail; duplicate review queue; import (upload, dry-run result, commit); reports and export; data-quality summary; admin (users, reference data); audit log. Produce low-fidelity wireframes in Phase 2 and show them to the supervisor before building views (Chapter 4 wireframes, marker "interface design").
