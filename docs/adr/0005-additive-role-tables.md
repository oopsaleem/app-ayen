# Roles are additive tables, not an enum column or a permission package

Considered a single `role` enum column on `users` (simplest, Laravel-idiomatic) and the
`spatie/laravel-permission` package (idiomatic RBAC), and rejected both. Chose: separate
`admins`/`managers`/`chefs` tables, each 1:1 with `users` via `user_id`, where presence of a row
*is* the role. A user can hold more than one role row at once (e.g. Admin and Manager
simultaneously) — the original system's authorization logic depends on this being possible. A
single enum column cannot express that. Spatie's permission model is polymorphic and generic,
which doesn't accommodate role-specific fields that already exist in the domain (Manager's
`company_id`/`display_name`, Chef's kitchen assignments) without bolting on extra tables anyway —
and would add a new dependency requiring approval. `Customer` remains implicit: no role row means
ordinary user, matching the original exactly.
