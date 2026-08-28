=== CBIAStudio BlogFlow with AI ===
Contributors: webgoh
Requires at least: 6.9.2
Tested up to: 7.1
Stable tag: 2.3.1
Requires PHP: 8.2
Network: true
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create AI-assisted WordPress posts with preview drafts, featured images, resumable batches, usage tracking, and optional Yoast SEO sync.

== Description ==

CBIAStudio BlogFlow with AI helps WordPress site owners generate AI-assisted article content and a featured image while retaining control over what WordPress saves or publishes.

For individual posts, the `Posts > Create with AI` flow creates a reviewable preview draft. You can then publish it, keep it as a draft, or schedule it. For title queues, the `Blog` screen supports checkpointed batches with STOP, resume, and live logs.

= Main features =

* AI-assisted article generation
* One generated featured image per post
* Reviewable preview drafts with publish, draft, and scheduling choices
* Checkpointed batch generation with STOP, resume, and live logs
* Automatic category and tag assignment from configured rules and title/content signals
* Usage and locally calculated cost tracking with provider/model filters
* Rolling 12-month usage and cost trends
* Environment and plugin diagnostics
* Optional Yoast SEO metadata synchronization

= Supported AI providers =

Text generation supports:

* OpenAI
* Google Gemini
* DeepSeek
* Anthropic

Image generation supports image-capable models from:

* OpenAI Images
* Google Gemini / Imagen

DeepSeek and Anthropic are text-only providers in the free plugin. You supply the API keys for the providers you use, and provider charges are governed by their own terms and pricing.

= Typical workflow =

1. Open `CBIAStudio BlogFlow with AI > Settings`.
2. Select the text and image providers and models you want to use, then save the corresponding API keys.
3. Configure content options and any category or tag rules you need.
4. For one post, open `Posts > Create with AI`, generate a preview draft, review the content and metadata, and then choose whether to publish, keep the draft, or schedule it.
5. For multiple titles, open `CBIAStudio BlogFlow with AI > Blog`, start the batch, and monitor its live log and checkpoint status.

= Usage and cost reporting =

The `Usage` screen records generation activity and locally calculated costs. Events and charts can be filtered by provider and model. The monthly view always covers the latest 12 calendar months, including months with no recorded usage.

Cost figures depend on the usage evidence returned by each provider and the plugin's local pricing catalog. They are operational estimates or calculations, not a replacement for the provider's invoice.

= Free and Pro =

The free plugin works as a standalone plugin and includes the individual `Posts > Create with AI` workflow, title-based batches, one featured image per generated post, essential Usage reporting, and optional Yoast SEO synchronization.

CBIAStudio BlogFlow Pro separately adds internal-image workflows, old-post regeneration, advanced Usage and cost controls, and additional automation. Yoast SEO is optional in both workflows.

== Installation ==

1. Install and activate CBIAStudio BlogFlow with AI from `Plugins > Add New`, or upload the plugin folder to `wp-content/plugins/`.
2. Open `CBIAStudio BlogFlow with AI > Settings` in the WordPress admin menu.
3. Select a text provider and model. If you want a generated featured image, also select an image provider and model.
4. Add the API key for each provider you use and save the settings.
5. Configure the generation, category, and tag options you need.
6. Use `Posts > Create with AI` for a reviewable preview draft, or open `CBIAStudio BlogFlow with AI > Blog` for checkpointed title batches.

Yoast SEO is optional and is not required to use the plugin.

== Frequently Asked Questions ==

= Which AI providers are supported? =
OpenAI, Google Gemini, DeepSeek, and Anthropic are supported for text generation. Featured-image generation supports image-capable OpenAI and Google Gemini / Imagen models. DeepSeek and Anthropic do not provide image generation in this plugin.

= Do I need my own API keys? =
Yes. A site administrator configures the API key for each provider the site uses. The WordPress site sends requests directly to the selected provider; CBIA Studio does not sell or intermediate the provider's API usage.

= Do I need another plugin to use CBIAStudio BlogFlow with AI? =
No. CBIAStudio BlogFlow with AI works as a standalone plugin and does not require another CBIA plugin.

= Do I need Yoast SEO to use the plugin? =
No. Yoast SEO is optional. When it is installed and active, the plugin can synchronize supported metadata such as the meta description and focus keyphrase.

= Does the plugin automatically publish content? =
Generating an individual preview does not publish the post. The preview flow creates or reuses a temporary draft for review; the final action can publish it, keep it as a draft, or schedule it. A configured title batch can publish immediately or schedule posts according to its date and interval settings.

= Can I stop and resume a batch? =
Yes. The title-batch workflow records live logs and checkpoint state. STOP pauses the queue, and a later run can resume from the saved checkpoint. Blocking provider or configuration errors can also pause the queue until the issue is corrected.

= Does the plugin generate images? =
Yes. The free plugin can generate one featured image per generated post with a configured OpenAI or Google image model. Images inside the article content are a Pro feature.

= How is AI usage and cost tracked? =
The plugin records operational usage evidence such as provider, model, request type, token data when available, and image settings. It uses that evidence with its local pricing catalog to show exact, estimated, or unknown cost states. These figures may differ from the provider's final invoice.

= What does the Pro version add? =
CBIAStudio BlogFlow Pro adds internal-image workflows, old-post regeneration, advanced Usage and cost controls, and additional automation. The free plugin already includes the individual `Posts > Create with AI` workflow.

== External services ==

This plugin sends requests from the WordPress site directly to a third-party AI provider only after a site administrator configures that provider and runs a connection test or an action that uses it. The configured API key is sent to the selected provider for authentication. CBIA Studio does not proxy these provider requests.

The content sent depends on the selected action. Connection tests use authentication and minimal test/model parameters. Generation actions can send titles, system instructions, prompt templates or custom instructions, image prompts, selected models, and generation settings. Provider usage is billed under the provider's own terms.

= OpenAI API =
* Service purpose: text generation, featured-image generation, and connection tests.
* Data sent for generation: system instructions, article prompts that can include the title and configured content options, image prompts, selected model, and generation parameters.
* When sent: when OpenAI is selected and an administrator tests the connection or starts a preview, post, batch, or image-generation action.
* API domain: `api.openai.com`
* Terms: https://openai.com/policies/terms-of-use
* Privacy: https://openai.com/policies/privacy-policy

= Google Gemini / Imagen API =
* Service purpose: text generation with Gemini, featured-image generation with compatible Gemini / Imagen models, and connection tests.
* Data sent for generation: system instructions, article prompts that can include the title and configured content options, image prompts, selected model, and generation parameters.
* When sent: when Google is selected and an administrator tests the connection or starts a preview, post, batch, or image-generation action.
* API domain: `generativelanguage.googleapis.com`
* Terms: https://ai.google.dev/terms
* Privacy: https://policies.google.com/privacy

= DeepSeek API =
* Service purpose: text generation and connection tests. DeepSeek is not used for image generation.
* Data sent for generation: system instructions, article prompts that can include the title and configured content options, selected model, reasoning options, and generation parameters.
* When sent: when DeepSeek is selected and an administrator tests the connection or starts a preview, post, or batch-generation action.
* API domain: `api.deepseek.com`
* Open Platform Terms: https://cdn.deepseek.com/policies/en-US/deepseek-open-platform-terms-of-service.html
* Privacy: https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html

= Anthropic API =
* Service purpose: text generation through the Anthropic Messages API and connection tests. Anthropic is not used for image generation.
* Data sent for generation: system instructions, article prompts that can include the title and configured content options, selected model, and generation parameters.
* When sent: when Anthropic is selected and an administrator tests the connection or starts a preview, post, or batch-generation action.
* API domain: `api.anthropic.com`
* Terms: https://www.anthropic.com/legal/commercial-terms
* Privacy: https://www.anthropic.com/legal/privacy

== Changelog ==

Older entries are retained as release history. Current free-plugin availability is described above; historical references to gated or Pro-compatible code do not make those capabilities available in the free plugin.

= 2.3.0 =
* Improved the relevance of automatic category selection using configured rules and title/content signals.
* Improved Usage filter placement and range defaults, with Last year selected by default and Last 2 years still available.
* Added a rolling 12-calendar-month usage and cost view with zero-filled months and provider/model filtering.
* Retained WordPress 7.1 compatibility metadata.
* Carried forward security hardening for external-provider requests and generated-content handling.

= 2.2.3 =
* Declared compatibility with WordPress 7.1 after completing the automated compatibility and regression gates.
* Refreshed release metadata while preserving existing generation, scheduling, provider, and security behavior.

= 2.2.2 =
* Hardened validation for supported AI provider endpoints.
* Improved sanitization of AI-generated WordPress content.
* Improved AI Composer snapshot sanitization.
* Improved cleanup of plugin credentials and internal data on uninstall.
* Improved cleanup of scheduled background events during plugin lifecycle changes.

= 2.2.1 =
* Raised the Medium first-pass target to 1900-2050 words while retaining the 1800-2000 nominal setting.
* Added explicit in-response word budgets for enabled FAQ and practical-example modules.
* Added a 20% normal tolerance band and a separate 1200-word critical floor for complete Medium articles.
* Limited length-based expansion to critically short output; truncation, incomplete output and structural errors remain expansion triggers.
* Allowed checkpoint batches to continue after a manual-review draft while keeping provider and configuration failures blocking.
* Aligned Blog and Preview length acceptance, telemetry and final validation.
* Rebuilds and reconciles the Usage event store after an update so historical post, orphan and trashed-post events remain visible.

= 2.2.0 =
* Replaced tall provider panels with compact, accessible connection rows and on-demand credential/details panels.
* Centralized approved OpenAI, Google, DeepSeek and Anthropic models, capabilities and dated prices in one server-side catalog.
* Added Anthropic as a text-only provider through its native Messages API, with isolated credentials and no cross-provider fallback.
* Added Anthropic Usage accounting for input, output, cache reads and 5-minute/1-hour cache writes using the event-time price period.
* Preserved `gpt-5-mini` as the global default/recommended model and preserved existing provider, model and credential selections.

= 2.1.7 =
* Added dedicated OpenAI, Google and DeepSeek connection cards with explicit connect, test and disconnect actions.
* Credentials are stored in the canonical non-autoloaded per-provider vault and are not rewritten when saving general settings or changing models.
* Existing legacy credentials are migrated once without destructive cleanup; canonical credentials take precedence at runtime.
* Connection tests use a non-generative provider endpoint and never expose credentials in markup, JavaScript, logs or AJAX responses.
* Disconnect affects only the selected provider and preserves all unrelated settings and operational data.
* Medium keeps its 1800-2000 nominal range and 1530 effective minimum while guiding the first response toward 1650-1850 visible words.
* Complete articles from 1530 to 1799 words are accepted without expansion and recorded as accepted with tolerance.
* FAQ and practical-example instructions are included only when enabled; profiles use flexible, substantial single-pass structures.
* Provider completion states are normalized and expansion cause is separated from expansion result.
* Text generation uses at most two remote requests per article. Small safe deficits use an incremental HTML fragment instead of rewriting the full article.
* Blog and Preview share visible-word counting, deterministic validation, length policy, Usage metadata and batch efficiency logs.

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

= 2.3.0 =
Improves category selection and Usage reporting, including a rolling 12-month view, while retaining WordPress 7.1 compatibility and security hardening. Updating is recommended.

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
