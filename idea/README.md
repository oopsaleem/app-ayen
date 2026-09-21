# Yemen Oasis — Concept Documentation

This directory documents the **concepts, domain model, and business rules** of the Yemen Oasis
restaurant ordering platform, independent of the current implementation stack (NestJS + Prisma +
GraphQL/REST on the backend, Next.js on the frontend). It exists so the system can be
**rebuilt in a different technology stack** (e.g. Django, Rails, Laravel, Spring Boot, Express)
while preserving the same product behavior.

The current codebase is a **partially implemented, multi-tenant restaurant ordering system**:
companies own one or more restaurants; restaurants organize their menu into kitchens, categories,
and dishes; customers browse a restaurant's menu, build a cart, and place an order for pickup or
delivery; managers track and progress orders through a fixed status lifecycle; and admins verify
restaurants before they go live. There is **no table-reservation feature** — "orders" means
food orders (pickup/delivery), not seat bookings. There is **no inventory/stock tracking**.
Payments are handled via Stripe (card) or a manual "cash" flag; Stripe integration is minimal
and has known gaps (see [03-implementation-guide.md](./03-implementation-guide.md)).

Read in this order:

1. **[00-overview.md](./00-overview.md)** — what the app does, who the actors are, the core
   concepts in plain language.
2. **[01-domain-model.md](./01-domain-model.md)** — every entity, its fields, relationships, and
   the invariants/constraints the current schema encodes.
3. **[02-features.md](./02-features.md)** — feature-by-feature breakdown: what's fully built,
   what's partial, what's missing entirely, and the workflow/business rules for each.
4. **[03-implementation-guide.md](./03-implementation-guide.md)** — a stack-agnostic guide for
   rebuilding the system: module boundaries, rules to enforce, known bugs/gaps to fix (not
   blindly copy), and the auth/authorization model.

None of these documents describe Prisma/NestJS/GraphQL syntax as the target — they describe
**behavior**. Code identifiers (field names, enum values) are referenced only because they are
the clearest unambiguous name for a concept, not because the new stack must use them literally.
