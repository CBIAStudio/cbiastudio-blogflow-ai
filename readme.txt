=== CBIAStudio BlogFlow with AI ===
Contributors: webgoh
Requires at least: 6.9
Tested up to: 6.9.4
Stable tag: 1.2.2
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

= 1.2.2 =
Release metadata update for WordPress 6.9.4 compatibility and naming consistency.

= 1.2.1 =
Improved the public WordPress.org listing with better documentation and branded assets.

= 1.2.0 =
Improved provider compatibility, preview behavior, and usage/cost reporting. Recommended update for all users.

= 1.1.7 =
* Replaced inline `<script>`/`<style>` output with WordPress enqueue APIs (`wp_add_inline_script`, `wp_add_inline_style`).
* Hardened nonce validation in AJAX stream/start handlers by enforcing `check_ajax_referer()`.
* Improved POST input handling/sanitization in Config/Blog/Oldposts services.
* Removed plugin header `Domain Path` entry to match WordPress.org guidance.
* Clarified external services section with explicit API domains (`api.openai.com`, `generativelanguage.googleapis.com`).
