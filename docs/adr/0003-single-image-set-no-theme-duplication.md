# Single image set per entity, no light/dark duplication

The original stored separate `light_images`/`dark_images` arrays (or single light/dark image
pairs) on Restaurant, Dish, and Category, requiring managers to upload two versions of every
photo. We deviated: Phase 1 stores one image set per entity, and dark-mode presentation is
handled with CSS treatment at render time. This halves ongoing content-upload work for
restaurant/company managers, at the cost of losing per-theme control over images that might
need a genuinely different asset (e.g. transparency or embedded branding) rather than a filter.
Nothing in the source material indicated that level of per-theme control was actually needed in
practice.
