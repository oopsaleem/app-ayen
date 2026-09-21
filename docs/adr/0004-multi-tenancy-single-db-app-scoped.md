# Multi-tenancy via single database + app-level scoping

Considered a dedicated tenancy package (e.g. stancl/tenancy) and database-per-tenant, and rejected
both for Phase 1. Chose: one database, shared tables with `company_id`/`restaurant_id`-style
foreign keys throughout, tenant isolation enforced via Eloquent global scopes and Laravel
policies. This mirrors the original system's authorization model, which resolves ownership via a
live relationship walk (e.g. Dish → Kitchen → Restaurant → Company) rather than a static/cached
permission table — reproducing that pattern here as policies/scopes keeps the two systems
conceptually aligned. A tenancy package or database-per-tenant would give stronger isolation
guarantees but adds setup/operational complexity not justified at this stage; revisit if
compliance or scale requirements change.
