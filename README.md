# CBIAStudio BlogFlow with AI (WordPress) v2.0.2

CBIAStudio BlogFlow with AI provides a controlled AI workflow for WordPress posts:

- AI text generation
- one featured image per post
- preview-first flow before create
- resumable batches with checkpoints
- live logs and STOP controls
- optional Yoast SEO sync

## What's New in 2.0.2
- Release version alignment for repository handoff (header, constants, and admin fallback version labels).
- Medium length cost optimization (`FAQ Off`): near-target outputs can be accepted without forced expansion, reducing extra calls.
- OpenAI runtime key resolution hardened to prefer valid primary settings keys.
- Redaction and packaging cleanup for safer logs and WordPress-ready distribution artifacts.

## Comparative: 2.0.2 vs 2.0.1
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
