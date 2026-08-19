# Provider URL security policy

Provider API requests use only the official endpoints supported by the plugin. Custom proxies and arbitrary OpenAI-compatible endpoints are not supported:

- OpenAI: `api.openai.com`
- Google Gemini: `generativelanguage.googleapis.com`
- DeepSeek: `api.deepseek.com`
- Anthropic: `api.anthropic.com`

Base URLs must use HTTPS, contain no user information, use no port other than 443, and contain no path, query, or fragment. Every resolved A and AAAA address must be public; private, loopback, link-local, multicast, and reserved addresses are rejected.

Validation occurs both when an administrator submits a base URL and immediately before every provider request that carries a credential. Existing unsafe stored values are preserved for recovery but cannot be used. The administration screen reports that the provider URL must be corrected without displaying the stored value.

Credentialed requests use WordPress's safe HTTP transport with TLS verification and unsafe-URL rejection enabled. Redirect following is disabled, so credentials cannot be forwarded from an authorized provider host to another destination. Credentials are added only after the destination passes validation.

DNS results are not cached by this policy. The destination is resolved for each request and the WordPress safe transport performs its own destination safety check, reducing the window for DNS rebinding.
