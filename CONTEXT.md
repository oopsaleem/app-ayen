# Yemen Oasis (Laravel rebuild)

A multi-tenant restaurant ordering platform. This context covers Phase 1: identity/roles and the
Company → Restaurant → Kitchen → Menu domain. Ordering, payments, reviews, and SLA tracking are
future phases and not yet part of this glossary.

## Language

**Company**:
The business entity that owns one or more Restaurants and has Managers. A standalone concept in
this app — distinct from the app's pre-existing generic `Team` concept, which is unrelated tenant
infrastructure this domain does not use.
_Avoid_: Team, Organization, Tenant (as a synonym for Company — "tenant" describes the multi-tenancy
mechanism, not a named domain entity).

**Restaurant**:
A single physical/branded location belonging to a Company. Has one Address, one Verification
record, and one or more Kitchens.
_Avoid_: Branch, Location, Outlet.

**Kitchen**:
A sub-unit of a Restaurant that prepares dishes (e.g. "Grill", "Bakery"). Dishes belong to exactly
one Kitchen, not directly to a Restaurant — a Dish's Restaurant is always derived by walking
Dish → Kitchen → Restaurant. Chefs are assigned to Kitchens, not Restaurants.

**Category**:
A menu category belonging to exactly one Restaurant, supporting self-referential hierarchy
(a Category may have a parent Category). Per-restaurant, not shared platform-wide.

**Dish**:
A menu item belonging to one Category and one Kitchen. Has a base price and optional add-ons:
DishOptions and ServingSizes.

**DishOption**:
An add-on/modifier for a Dish (e.g. "extra cheese"), individually priced and individually
toggleable.

**ServingSize**:
A size/portion variant of a Dish (e.g. small/medium/large), individually priced. Exactly one
ServingSize per Dish is the default.

**Admin**:
A platform-level role held by a User. Verifies Restaurants. Global scope, not tied to a Company.

**Manager**:
A role held by a User, scoped to at most one Company at a time. Manages that Company's
Restaurants and menus.

**Chef**:
A role held by a User, scoped to one or more Kitchens (not to a Restaurant directly).

**Customer**:
A User holding none of the Admin/Manager/Chef roles. Not an explicit role row — presence of no
other role row *is* being a Customer.

**Role**:
A capability a User holds, represented by the presence of a row in a role-specific table
(Admin/Manager/Chef), not by a single field on User. A User may hold more than one Role at once
(e.g. Admin and Manager simultaneously).
_Avoid_: User type, account type — these imply exclusivity, which Role explicitly does not have.

**Verification**:
An Admin's record of whether a Restaurant has been approved. Tracked per Restaurant; in Phase 1 it
does not gate anything (an unverified Restaurant is still fully browsable).
