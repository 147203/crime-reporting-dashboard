# Sequence diagram: report an incident

```mermaid
sequenceDiagram
    actor O as Reporting Officer
    participant W as Browser
    participant M as Middleware
    participant C as IncidentController
    participant R as StoreIncidentRequest
    participant P as IncidentPolicy
    participant S as IncidentService
    participant G as PiiGuard
    participant Q as DuplicateDetector
    participant L as AuditLogger
    participant D as Database
    O->>W: fill in the form, submit
    W->>M: POST /incidents (session cookie, CSRF token)
    M->>M: auth, CSRF, role, password-change checks
    M->>C: store(request)
    C->>P: create? (role, own station)
    P-->>C: allowed or 403
    C->>R: validate V1 to V7
    R->>G: description and location contain phone or ID pattern?
    G-->>R: pass or V6 error
    R-->>C: validated data or errors back to the form
    C->>S: create(data, user)
    S->>D: begin transaction; insert incident
    S->>Q: candidates (same category and ward, time window, text similarity)
    Q->>D: indexed query
    Q-->>S: duplicate status and matched id
    S->>D: set reference number and duplicate status
    S->>L: incident.created (ids only)
    L->>D: insert audit log
    S->>D: commit
    S-->>C: incident
    C-->>W: redirect to detail with confirmation
```
