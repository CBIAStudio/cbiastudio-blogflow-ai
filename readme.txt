=== CBIAStudio BlogFlow with AI ===
Contributors: webgoh
Requires at least: 6.9.2
Tested up to: 7.0
Stable tag: 2.1.6
Requires PHP: 8.2
Network: true
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create AI-assisted blog posts in WordPress with preview, featured image, resumable batches, live logs, and optional Yoast SEO sync.

== Description ==

CBIAStudio BlogFlow with AI helps WordPress site owners create and manage blog content with a controlled AI workflow.

This edition generates AI text plus one featured image, lets you preview the result before creating the post, and runs large batches safely with resumable checkpoints and live logs.

= Who this plugin is for =

This plugin is built for:

* site owners who want to generate blog drafts faster
* editorial teams that need safer batch generation with STOP / resume behavior
* WordPress users who want cost visibility and simple diagnostics
* users who may optionally work with Yoast SEO, without making Yoast mandatory

= Main features =

* AI text generation for blog posts
* One featured image per generated post in this edition
* Live preview workflow before creating the final post
* Resumable batch generation with checkpoint logic
* Live logs and safe STOP controls
* Category and tag assignment rules
* Cost dashboard with quick estimate and real-cost tracking
* Environment and plugin diagnostics
* Optional Yoast SEO integration for meta syncing when Yoast is installed

= Plugin behavior =

CBIAStudio BlogFlow with AI is standalone and does not require another CBIA plugin.

It supports:

* featured image generation
* preview-first workflow
* batch generation with recovery logic
* AI cost visibility

It does not include the advanced in-editor composer and old-post regeneration tools from the Pro edition.

== Installation ==
1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate the plugin from "Plugins".
3. Go to `Settings -> CBIAStudio BlogFlow with AI`.
4. Add your API key and choose your text/image provider settings.
5. Open the `Blog` screen to generate previews and create posts.

== Usage Guide ==

= Quick start =

1. Open the plugin settings and save a valid API key.
2. Choose the provider and model you want to use.
3. Go to the `Blog` tab.
4. Enter or import titles.
5. Generate a preview.
6. Review the generated text, featured image, categories, tags, and SEO fields.
7. Create the post when the preview looks correct.

= Batch generation =

1. Prepare multiple titles.
2. Start the generation batch.
3. Watch the live log while the plugin processes the queue.
4. Use STOP if needed.
5. Resume later from the saved checkpoint when required.

= Cost tracking =

The `Usage` area lets you review:

* calls by type
* costs by post
* provider/model usage
* estimated versus real cost tracking
* monthly cost tendencies

= Optional Yoast workflow =

If Yoast SEO is active, the plugin can synchronize:

* meta description
* focus keyphrase
* related Yoast metadata/hooks used by the plugin flow

== Frequently Asked Questions ==

= Do I need to buy credits from CBIA Studio? =
No. This plugin works with your own API key from supported AI providers. You pay the provider directly for your usage.

= Which AI providers are supported? =
CBIAStudio BlogFlow with AI supports OpenAI, Google Gemini / Imagen, and DeepSeek, depending on the task. Text and image availability depends on the provider and model you configure in the plugin settings.

= Do I need another plugin to use CBIAStudio BlogFlow with AI? =
No. CBIAStudio BlogFlow with AI works as a standalone plugin and does not require another CBIA plugin.

= Can I preview the content before creating the post? =
Yes. CBIAStudio BlogFlow with AI includes a preview-first workflow so you can review the generated text, featured image, categories, tags, and SEO-related fields before creating the final post.

= Does CBIAStudio BlogFlow with AI generate internal images inside the content? =
No. CBIAStudio BlogFlow with AI focuses on AI text plus one featured image per post. The advanced internal-image workflow belongs to CBIAStudio BlogFlow Pro.

= Can I generate multiple posts safely in batches? =
Yes. The batch system supports live logs, STOP controls, and resumable checkpoints so long runs can be continued safely.

= What happens if a generation step fails? =
The plugin records the failure in the live log, keeps the batch state, and can resume from the saved checkpoint. If an image step fails, it can be left as a pending item for later completion.

= Do I need Yoast SEO to use the plugin? =
No. Yoast is optional. If Yoast SEO is installed and active, the plugin can sync supported metadata as part of the generation flow.

= Why can the real cost differ from the quick estimate? =
Real cost depends on the provider, model, tokens, and image pricing rules in your configuration. You can also enable fixed image pricing and adjust real-cost settings for closer tracking.

= What does the Pro version add? =
The Pro edition adds advanced in-editor generation, internal-image workflows, old-post regeneration tools, and broader editorial controls beyond the base preview-first flow.

== External services ==

This plugin can connect to third-party AI services only when the site administrator configures API keys and starts generation actions.

= OpenAI API =
- Service purpose: text generation and image generation for posts.
- Data sent: post title, prompt/template text, generation parameters, and image prompts.
- When sent: when creating preview, creating posts, or generating images manually.
- API domain used by the plugin: `api.openai.com`
- Terms: https://openai.com/policies/terms-of-use
- Privacy: https://openai.com/policies/privacy-policy

= Google Gemini / Imagen API =
- Service purpose: text generation (Gemini) and image generation (Imagen/Gemini image models).
- Data sent: post title, prompt/template text, generation parameters, and image prompts.
- When sent: when creating preview, creating posts, or generating images manually.
- API domain used by the plugin: `generativelanguage.googleapis.com`
- Terms: https://ai.google.dev/terms
- Privacy: https://policies.google.com/privacy

= DeepSeek API =
- Service purpose: text generation.
- Data sent: post title, prompt/template text, and generation parameters.
- When sent: when creating preview or creating posts.
- Terms: https://platform.deepseek.com/terms
- Privacy: https://platform.deepseek.com/privacy

== Changelog ==

= 2.1.6 =
* Provider API keys now use a canonical per-provider store and remain stable when changing providers or models.
* API-key saves are scoped to the selected text/image provider so hidden browser autofill values cannot replace another credential.
* Key fields use a consistent safe mask and configured/missing status without exposing credential fragments.
* Auto by title always sends the generated per-title profile and cannot be replaced by a fixed editable prompt block.
* Medium first-pass prompts target 1950-2000 words and budget FAQ and practical examples inside the same response.
* A 15% effective lower tolerance avoids expansion at 1530 words or more; truncation still triggers one guarded completion.
* Blog, Preview, final validation and expansion telemetry use the same effective minimum.

= 2.1.5 =
* DeepSeek tests now separate the /models connection check, a basic 32-token chat with thinking disabled, and an optional advanced thinking test.
* HTTP 200 with reasoning but no final content is classified as an incomplete chat while connection and authentication remain valid; reasoning content is discarded.
* Text generation logs configured, calculated and effective token budgets and no longer silently reduces a configured 6000-token limit to 4620.
* Blog and Preview retain finish reason, completion/reasoning/visible-token metrics and use at most one expansion after cleanup and word counting.
* Disabled FAQ and practical-example modules are not requested by expansion prompts and are removed defensively if returned.
* Batch logs summarize first-pass efficiency; the first OpenAI Images HTTP 401 skips later remote image calls in that batch with exact zero skipped cost.
* API key fields now use one consistent bullet mask and show a clear configured or missing status without exposing credentials.
* Provider credentials now use a canonical provider-only store that model and provider selection saves cannot overwrite; legacy stores remain synchronized for compatibility.
* Provider API keys are isolated by provider, reject masks/control characters, and preserve the previous valid value when a submission is invalid.
* Diagnostics and settings expose configured state only; provider errors are sanitized before logs, AJAX and Usage storage.
* Authentication rejections are exact zero-cost Usage events, and Oldposts v3 preflights credentials before destructive image operations.
* Configuration Test now uses the exact saved text provider, provider-specific model and API key with one attempt and no cross-provider fallback.
* Configuration Test reports text and local-only image checks separately, preserves STOP, and records its single text attempt in Usage V2.
* The API-key-only save action updates only explicitly entered keys; empty, absent and masked fields preserve existing provider credentials.
* OpenAI text attempts are recorded immediately in Usage V2, including failed, timed-out, incomplete, preview, and pre-publication requests.
* Initial OpenAI generation is limited to the selected model plus one safe fallback for temporary errors only.
* OpenAI text uses 120-second initial and 150-second expansion timeouts, with request IDs and idempotent attempt tracking.
* Medium prompts target 1900-2100 words internally while enforcing the selected 1800-word minimum consistently in Blog and Preview.
* A single guarded expansion is allowed; insufficient text is kept as a draft and no images are generated.
* Provider API keys remain isolated and preserved when switching or saving text/image providers.
* Blog and Preview preflight text and image credentials separately; requests blocked locally are stored with exact zero cost.
* DeepSeek V4 Flash Medium without FAQ uses a 5200-token output budget and records first-pass and expansion metrics.
* Usage displays independent text and image provider/model/key status.

= 2.1.4 =
* Added DeepSeek V4 Flash and DeepSeek V4 Pro with explicit optional reasoning controls.
* Safely migrates legacy DeepSeek Chat and Reasoner settings without changing API keys or content settings.
* Usage V2 records effective model, cache hit/miss tokens, reasoning metadata, retries, request details, and evidence-aware local costs.
* DeepSeek requests use provider-specific timeouts and retry only temporary failures.

= 2.1.3 =
* OpenAI Image responses now keep requested and effective quality/size separately.
* Automatic quality uses a returned effective quality for local output estimates when token usage is unavailable.
* Usage detail includes effective image response fields and image output token details.
* Historical events without evidence remain unknown and are not inferred.
* Blog/cron, preview, pending-image, Oldposts, and manual regeneration routes retain the same image response evidence.

= 2.1.2 =
* Usage costs now distinguish exact, estimated, unknown, and officially reconciled evidence.
* OpenAI image usage tokens, retries, timeouts, orphan attempts, request IDs, HTTP status, and elapsed time are tracked without treating unknown cost as zero.
* Added explicit historical recalculation simulation, confirmation, backup, coverage reporting, and extended Usage filters.
* Per-event detail explains unknown costs, and the historical recalculation endpoint uses its dedicated nonce.



= 2.1.1 =
Fixes persistence of the default, featured, and content OpenAI image quality selectors after saving Settings.

= 2.1.0 =
* Added default, featured and content OpenAI image quality settings with safe inheritance.
* Live image cost estimates now resolve each effective quality and Usage stores the final quality, size and image type.
* Automatic quality omits the OpenAI quality parameter; GPT Image 2 transparent backgrounds are reported and changed to opaque.

= 2.0.10 =
* Added an OpenAI image quality selector with Automatic, Low, Medium, and High values and backward-compatible Automatic default.
* Image requests now use one validated PHP service for model, quality, size, payload preparation, and output-price estimates.
* Added live per-image and per-article estimates using model, quality, real slot sizes, and selected image count; Automatic quality remains explicitly variable.
* Usage and operational logs now retain image quality/size context, estimated output cost, HTTP status, and OpenAI request ID when available.
* Added strict base64 validation and safe opaque fallback if GPT Image 2 receives a transparent-background request.
* Updated historical image-cost compatibility while preserving previously stored settings and API keys.
* Comparison vs 2.0.9: keeps Auto by title compatibility, scheduling, Usage visibility, and image placement while adding configurable OpenAI image quality and centralized size-aware pricing.

= 2.0.9 =
* Added Pro-compatible Auto by title prompt profile support with protected base fallback and Pro-only activation through `auto_prompt_profile`.
* Auto by title now resolves each title to Editorial / Discover, SEO Balanced, or How-to / Practical using a small English + Spanish pattern map with an extension filter.
* Blog batch logs the resolved prompt profile for each title, improving validation and editorial traceability.
* Added a Blog profile column in the WordPress posts list and stores configured/resolved profile metadata on newly generated posts.
* Comparison vs 2.0.8: keeps usage/cost accounting and proportional image placement while adding profile automation, traceability, and posts-list visibility for generated content.

= 2.0.8 =
* Usage now records billable API calls even when generation stops or fails before a WordPress post is created, including text, expansion, failed attempts, and image calls.
* Usage totals include no-post calls without inflating the created-post counter, improving real cost visibility for interrupted batches.
* Internal image markers are rebalanced proportionally by selected image count so one image lands near the middle and multiple images distribute through the article body.
* Text, expansion, and image cost rows now keep their own prompt context, avoiding inaccurate failed-attempt estimation.
* Spanish translation files regenerated for the new Usage labels.
* Release metadata updated with WordPress `Tested up to: 7.0`.
* Comparison vs 2.0.7: keeps API-key persistence, checkpoint error pausing, Gutenberg fallback, API modal recovery, and Oldposts checkbox reliability while adding no-post usage accounting and proportional internal-image placement.
= 2.0.7 =
* Hardened API key persistence so partial Blog/runtime saves no longer overwrite saved provider secrets with empty values.
* Added a dedicated "Save API keys" action and rehydrated provider keys from both settings stores so model/configuration saves cannot clear existing keys.
* Blog checkpoint handling now pauses on blocking provider/API errors without consuming queued titles, so progress reflects real created/skipped items.
* Usage cost recalculation now updates already stored OpenAI image usage rows to the current high-quality image pricing assumptions.
* Fixed Gutenberg Create with AI insert when WordPress reports a provisional new-post ID by retrying safely through the draft-creation path.
* Restored Configure text API / Configure image API modal behavior in Gutenberg, including Save key and Test connection fallbacks.
* Fixed Update Older Posts card selection when clicking directly on the checkbox square.
* Comparison vs 2.0.6: keeps the controlled insert, FAQ-off, batch chunk and GPT-5 temperature fixes while adding API-key persistence, isolated API-key saving, checkpoint error pausing, stored usage recalculation, Gutenberg insert fallback, API modal recovery, and Oldposts checkbox reliability.

= 2.0.6 =
* Create with AI insert now applies content, featured image, categories, tags, and Yoast metadata through a server-side save and controlled editor refresh.
* The controlled editor refresh suppresses the browser leave-page prompt after a successful plugin-driven insert.
* FAQ disabled state is enforced in preview and final insert, including localized FAQ headings such as Portuguese, German, French, Italian, and Dutch variants.
* Blog batch chunk size is configurable so long runs can process more than one post per event when hosting timeouts allow it.
* OpenAI temperature handling now avoids sending unsupported temperature parameters to GPT-5 reasoning-style models.
* Comparison vs 2.0.5: keeps the previous metadata/scheduling/Oldposts fixes and adds FAQ-off hardening, controlled insert refresh, configurable batch chunk size, and GPT-5 temperature compatibility.

= 2.0.5 =
* Composer insert now persists content, featured image, categories, tags, and Yoast metadata by saving the editor draft after the server-side apply step.
* Reopening the editor composer now prefers the saved post state so existing content/images/SEO can be modified safely.
* Blog scheduling now uses a checkpoint schedule cursor so the configured first date and publication interval are respected across queued titles.
* Update Older Posts now keeps multi-card selection stable, sends exact selected IDs to the AJAX queue, and marks running/processed cards visually.
* Spanish translations updated for scheduling controls, Oldposts labels, and changed runtime messages.
* Comparison vs 2.0.4: keeps the header-safety patch and adds editor metadata persistence, deterministic scheduling intervals, and Oldposts queue/selection reliability.

= 2.0.4 =
* Removed UTF-8 BOM from PHP entrypoint/router files to prevent premature output and WordPress 'Cannot modify header information' warnings during redirects or admin actions.
* Regenerated clean release packages after byte-level validation.


= 2.0.3 =
* Fixed the editor `Complete missing` action so generated internal images are injected into the final HTML before applying the post, instead of only updating the featured image.
* Hardened first-time editor insertion: created drafts now persist content, featured image, categories, tags, and Yoast metadata through the server-side apply flow.
* FAQ headings now normalize to the selected post language in preview and insert flows.
* Blog scheduling now ignores stale past publication dates and uses the current run date instead.
* Usage in the base edition keeps Pro-only cost displays hidden while retaining operational usage data.
* Packaging cleanup: regenerated WordPress-ready ZIP with normalized `/` paths and no Git/dev artifacts.
* Comparison vs 2.0.2: fixes missing-image completion and first-insert metadata persistence while keeping the 2.0.2 cost/key hardening.
= 2.0.2 =
* Release prep for repository publication with explicit version alignment across plugin headers/constants/UI fallbacks.
* Cost optimization in medium length flow: soft threshold for `Medium` without FAQ to reduce unnecessary expansion calls.
* OpenAI key-resolution hardening: prioritize valid main settings key over stale provider-side values; stricter key-shape validation.
* Log redaction hardened to avoid leaking key-like values while keeping readable provider error messages.
* Packaging cleanup: distribution zips now exclude Git/dev artifacts for WordPress-ready upload.
* Comparison vs 2.0.1: lower token/call overhead in near-target medium articles, safer key handling in runtime, cleaner release packaging/documentation for repo handoff.

= 2.0.1 =
* Major base-edition consolidation: this plugin is now the official base for the Base + Pro Add-on model.
* Pro gating refined in base UI (clear upgrade paths for advanced modules without breaking base flows).
* Usage and Update Older Posts base screens refreshed for cleaner upgrade messaging and safer onboarding.
* Internal image slot handling hardened in Pro-compatible runtime paths while preserving base behavior.
* Release traceability and packaging flow updated for GitHub + WordPress.org publication readiness.

= 1.2.2 =
* Release metadata alignment for WordPress 6.9.4 (`Tested up to`) and brand naming consistency.
* Updated public wording to use `CBIAStudio BlogFlow with AI` as the standard edition name.
* Documentation refresh to keep release/pre-release traceability aligned with current operational flow.

= 1.2.1 =
* Improved the WordPress.org plugin page content and public assets.
* Added plugin banner and icons for a cleaner listing presentation.
* Expanded the public documentation with clearer description, usage guide, FAQ, and upgrade notice.

= 1.2.0 =
* Switched to the standard WordPress.org bootstrap file `cbiastudio-blogflow-ai.php` and aligned standard/pro dependency metadata.
* Refined Usage/Costs calculations with updated provider pricing, improved provider/model compatibility, and more reliable real-cost summaries.
* Updated OpenAI / Gemini / DeepSeek model handling and aliases, with DeepSeek kept as text-only and image scope kept provider-aware.
* Improved preview/create flows by returning real preview URLs for scheduled drafts and stabilizing admin-side usage rendering.

== Upgrade Notice ==

= 2.0.9 =
Recommended editorial automation update. Adds Pro-compatible Auto by title profile detection, per-title profile logs, and Blog profile visibility in the posts list.

= 2.0.8 =
Recommended cost-tracking update. Adds accounting for failed/stopped API calls before post creation, improves real Usage totals, and rebalances internal image placement.
= 2.0.7 =
Recommended reliability update. Fixes API key persistence during runtime saves, Gutenberg Create with AI insert authorization fallback, API key modal actions, stored Usage recalculation, and Oldposts checkbox selection.

= 2.0.6 =
Recommended workflow update. Fixes controlled Create with AI insert refresh, FAQ-off enforcement, configurable batch chunk size, and GPT-5 temperature compatibility.

= 2.0.5 =
Recommended workflow reliability update. Fixes Create with AI insert persistence, Blog scheduling intervals, and Update Older Posts card selection/queue execution.

= 2.0.4 =
Recommended header-safety update. Fixes premature PHP output that can break redirects or admin actions on some hosts.


= 2.0.3 =
Recommended update. Fixes editor completion of missing internal images, improves first-insert metadata persistence, and keeps generated packages clean for WordPress upload.
= 2.0.2 =
Recommended update. This release improves runtime API-key reliability, reduces extra generation calls in medium no-FAQ flows, and finalizes repository-ready packaging/docs.

= 2.0.1 =
Recommended update. This release formalizes the base role in the Base + Pro Add-on model and aligns upgrade flows for advanced modules.

= 1.2.2 =
Release metadata update for WordPress 6.9.4 compatibility and naming consistency.

= 1.2.1 =
Improved the public WordPress.org listing with better documentation and branded assets.

= 1.2.0 =
Improved provider compatibility, preview behavior, and usage/cost reporting. Recommended update for all users.

= 1.1.7 =
Plugin hardening release: moved inline assets to enqueue APIs, tightened AJAX nonces, and improved admin input validation/sanitization.
