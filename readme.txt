=== CBIAStudio BlogFlow with AI ===
Contributors: webgoh
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 1.1.1
Requires PHP: 8.2
Network: true
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

CBIAStudio BlogFlow with AI: generacion de entradas con IA (texto + imagen destacada), con reanudacion por checkpoint y flujo de preview.

== Description ==

Plugin para crear entradas con IA (texto + imagen destacada) sin bloquear la pantalla. Procesa con reanudacion por checkpoint, asigna categorias/tags por reglas y mantiene un flujo de preview. No requiere otro plugin.

Key features:
* 1 featured image (sin imagenes en contenido)
* [IMAGE: ...] markers inserted automatically if missing; extra markers are removed
* Live log and safe STOP
* Costs: quick estimate and REAL cost per call (optional fixed image price)
* Quick environment/plugin diagnostics
* Optional Yoast SEO support. The plugin does not require Yoast; if active, it syncs meta and hooks.

== Installation ==
1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate the plugin from "Plugins".
3. Go to Settings -> CBIAStudio BlogFlow with AI, add your API Key, and configure.

== Frequently Asked Questions ==

= How does resume work? =
It uses a checkpoint that stores queue, index, and totals. The "Create Blogs" button enqueues an event; each event processes N posts (default 1) and re-schedules if the queue remains.

= What happens if an image fails? =
It is replaced by a hidden "pending" marker. You can click "Fill pending" or let CRON handle it.

= Why does the real cost not match? =
Enable "fixed price per image", adjust mini/full rates and, if needed, add the text/SEO call overhead and the real-cost multiplier.

== External services ==

This plugin can connect to third-party AI services only when the site administrator configures API keys and starts generation actions.

= OpenAI API =
- Service purpose: text generation and image generation for posts.
- Data sent: post title, prompt/template text, generation parameters, and image prompts.
- When sent: when creating preview, creating posts, or generating images manually.
- Terms: https://openai.com/policies/terms-of-use
- Privacy: https://openai.com/policies/privacy-policy

= Google Gemini / Imagen API =
- Service purpose: text generation (Gemini) and image generation (Imagen/Gemini image models).
- Data sent: post title, prompt/template text, generation parameters, and image prompts.
- When sent: when creating preview, creating posts, or generating images manually.
- Terms: https://ai.google.dev/terms
- Privacy: https://policies.google.com/privacy

= DeepSeek API =
- Service purpose: text generation.
- Data sent: post title, prompt/template text, and generation parameters.
- When sent: when creating preview or creating posts.
- Terms: https://platform.deepseek.com/terms
- Privacy: https://platform.deepseek.com/privacy
