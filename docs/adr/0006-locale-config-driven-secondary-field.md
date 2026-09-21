# Bilingual content fields derive the secondary locale from config, not a hardcoded literal

Domain content (Restaurant/Category/Dish names and descriptions) uses parallel columns per the
original design — e.g. `name_en`, `name_ar` as physical migration-time column names. But
application code that needs to pick "the secondary-language field" must derive which locale that
is from `config('app.available_locales')` (this repo's existing en/ar config) rather than
hardcoding the literal string `'ar'` throughout the codebase. This is deliberately separate from
the app's existing i18next-based UI-chrome translation system (user-facing strings, `users.locale`,
RTL) — that system translates interface text; this pattern stores bilingual domain data. The two
coexist and should not be conflated. Rationale: if the platform's second language ever changes,
the column names still need a migration, but application logic that reasons about "the secondary
field" won't need a find-and-replace of `'ar'` across the codebase.
