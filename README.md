# CBIAStudio BlogFlow with AI (WordPress) v2.0.3

CBIAStudio BlogFlow with AI provides a controlled AI workflow for WordPress posts:

- AI text generation
- one featured image per post
- preview-first flow before create
- resumable batches with checkpoints
- live logs and STOP controls
- optional Yoast SEO sync

## What's New in 2.0.3
- Fixed `Complete missing` in the editor so missing internal images are applied to the final post HTML, not only generated in memory.
- Hardened first-time editor insertion so content, featured image, categories, tags, and Yoast metadata persist when the draft did not exist yet.
- FAQ heading normalization now follows the selected post language in preview and insert flows.
- Blog scheduling ignores stale past publication dates and starts from the current run date.
- Base Usage keeps Pro-only cost values hidden while preserving operational usage rows.
- WordPress-ready package regenerated with clean `/` ZIP paths and without Git/dev artifacts.

## Comparative: 2.0.3 vs 2.0.2
- Fixes editor completion/insertion regressions around images and metadata.
- Improves localized FAQ output and stale-date handling in Blog generation.
- Keeps the 2.0.2 API-key, redaction, and cost-optimization hardening.

## What's New in 2.0.2
- Lower operating cost in common `Medium` no-FAQ runs due to fewer expansion retries.
- More reliable API key usage in background batches (less risk of stale/invalid key selection).
- Cleaner release packaging and docs for publication in repositories.

## What's New in 2.0.1
- Base edition consolidation for the new Base + Pro Add-on model.
- Upgrade UX polished in base-only screens (advanced modules clearly marked as Pro paths).
- Release-prep alignment for GitHub + WordPress.org handoff with updated operational traceability.

## What's New in 1.2.2
- Release metadata alignment for WordPress 6.9.4 (`Tested up to`).
- Naming cleanup across release-facing docs to keep `CBIAStudio BlogFlow with AI` as the standard edition name.
- Operational release traceability update (handoff/history sync for this release cycle).

## What's New in 1.2.1
- WordPress.org listing content and assets improvements.
- Expanded public docs (description, usage guide, FAQ, upgrade notice).

## What's New in 1.2.0
- Standardized WordPress.org bootstrap file `cbiastudio-blogflow-ai.php`.
- Refined Usage/Costs calculations and provider/model compatibility.
- Improved preview/create flow with stable preview URL handling.
