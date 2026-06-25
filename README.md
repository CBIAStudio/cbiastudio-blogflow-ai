# CBIAStudio BlogFlow with AI (WordPress) v2.0.8

CBIAStudio BlogFlow with AI provides a controlled AI workflow for WordPress posts:

- AI text generation
- one featured image per post
- preview-first flow before create
- resumable batches with checkpoints
- live logs and STOP controls
- optional Yoast SEO sync

## What's New in 2.0.8
- Usage now records API calls even when a generation stops or fails before a WordPress post exists.
- No-post Usage rows are counted for calls, tokens, and cost without increasing the created-post counter.
- Expansion calls and failed attempts are tracked in the same real-cost pipeline as successful post creation.
- Internal image markers are distributed proportionally by selected count for cleaner article structure.
- Text, expansion, and image rows keep separate prompt context for more accurate failed-attempt cost estimation.
- Spanish translation catalogs were updated and compiled.
- Release metadata updated to WordPress `Tested up to: 7.0`.

## Comparative: 2.0.8 vs 2.0.7
- Keeps the 2.0.7 API-key persistence, checkpoint error pausing, Gutenberg insert fallback, API modal recovery, and Oldposts checkbox reliability.
- Adds no-post usage accounting, expansion/failure cost visibility, and proportional internal-image placement.

## What's New in 2.0.7
- API key persistence hardened so partial Blog/runtime saves do not overwrite saved provider secrets with empty values.
- A dedicated "Save API keys" action stores provider keys without touching prompts, length, categories, or other generation settings.
- Blog checkpoints now pause on blocking provider/API errors without consuming queued titles.
- Stored Usage rows for OpenAI image calls are recalculated to the current high-quality image pricing assumptions.
- Gutenberg Create with AI insert now retries safely through draft creation when WordPress rejects a provisional new-post ID.
- Configure text/image API buttons and Save/Test key actions work again even when Gutenberg moves the composer markup.
- Update Older Posts card selection now works when clicking directly on the checkbox square.
## Comparative: 2.0.7 vs 2.0.6
- Keeps the 2.0.6 controlled insert, FAQ-off, configurable batch chunk and GPT-5 temperature fixes.
- Adds API-key persistence hardening, isolated API-key saving, checkpoint error pausing, stored usage recalculation, Gutenberg insert fallback, API modal recovery, and Oldposts checkbox reliability.
## What's New in 2.0.6
- Create with AI insert applies content, featured image, categories, tags, and Yoast metadata through a server-side save and controlled editor refresh.
- The controlled editor refresh suppresses the browser leave-page prompt after a successful plugin-driven insert.
- FAQ disabled state is enforced in preview and final insert, including localized FAQ headings.
- Blog batch chunk size is configurable to reduce checkpoint resumes when hosting timeouts allow larger chunks.
- OpenAI temperature handling avoids unsupported temperature parameters for GPT-5 reasoning-style models.

## Comparative: 2.0.6 vs 2.0.5
- Keeps the 2.0.5 metadata/scheduling/Oldposts persistence fixes.
- Adds FAQ-off hardening, controlled insert refresh, configurable batch chunk size, and GPT-5 temperature compatibility.

## What's New in 2.0.5
- Composer insert now persists content, featured image, categories, tags, and Yoast metadata by saving the editor draft after the server-side apply step.
- Reopening the editor composer now prefers the saved post state, so existing content/images/SEO can be modified instead of starting from an empty snapshot.
- Blog scheduling now uses a checkpoint schedule cursor so the configured first date and interval are respected across queued titles.
- Update Older Posts keeps multi-card selection stable, sends the exact selected IDs to the AJAX queue, and marks running/processed cards visually.
- Spanish translations updated for scheduling controls, Oldposts labels, and changed runtime messages.

## Comparative: 2.0.5 vs 2.0.4
- Keeps the 2.0.4 header-safety patch that prevents premature output and WordPress header warnings.
- Adds workflow persistence fixes for editor metadata, deterministic scheduling intervals, and Oldposts queue/selection reliability.

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
