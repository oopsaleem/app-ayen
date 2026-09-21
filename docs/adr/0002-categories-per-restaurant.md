# Categories are scoped per-restaurant, not global

The original system had a single global Category tree shared by every Restaurant on the platform
— the source docs flag this as reading more like an oversight than a considered choice. We
deliberately deviated: each Category now belongs to exactly one Restaurant (`restaurant_id` FK).
This matches how multi-tenant restaurant platforms normally behave (a restaurant curates its own
category list) and avoids one restaurant's categories leaking into another's menu. The cost is
that restaurants can no longer share a curated category taxonomy, but nothing in the source
material suggested that was ever an intended benefit of the global design.
