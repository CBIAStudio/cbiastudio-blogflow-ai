=== CBIAStudio BlogFlow with AI ===
Contributors: webgoh
Requires at least: 6.9.2
Tested up to: 6.9
Stable tag: 2.0.4
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
