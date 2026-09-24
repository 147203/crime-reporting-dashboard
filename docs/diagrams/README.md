# Design diagrams (sources)

Mermaid sources for Chapter 4. GitHub renders them in place; the final figures are redrawn in StarUML (class, ERD) and Visual Paradigm (the others) per the faculty guide, using these as the specification. Each file names the figure it feeds.

| File | Figure (Chapter 4) | Source of truth |
|---|---|---|
| `use-case.md` | Use case diagram | docs/02 section 2 |
| `class.md` | Class diagram (domain and services) | `app/Models`, `app/Enums`, `app/Services` |
| `erd.md` | Entity relationship diagram | `database/migrations` |
| `sequence-report-incident.md` | Sequence: report an incident | `IncidentController`, `IncidentService` |
| `sequence-dashboard.md` | Sequence: analytics request | `DashboardController`, `Analytics\*` |
| `activity-import.md` | Activity: bulk CSV import | `ImportService` |
| `conceptual-framework.md` | Conceptual framework (Figure 2.1 redraw, no GIS) | docs/03 section 7 |
| `architecture.md` | Three-layer architecture | docs/03 section 1 |
