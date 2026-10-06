# Neonbreak Chat

Reusable WordPress assistant backed by Fireworks AI. Version 0.4 includes the Neonbreak demo and an account-based client workspace with a central iframe service. It is a local MVP, with no billing, document retrieval or production deployment included.

## Setup

1. Activate **Neonbreak Chat** in WordPress Plugins.
2. Set `FIREWORKS_API_KEY` in the PHP server environment, or add `define( 'NEONBREAK_FIREWORKS_API_KEY', 'your-key' );` to `wp-config.php` before WordPress loads. Never commit a real key.
3. Open **Settings → Neonbreak Chat**. Paste the exact model ID from your Fireworks deployment, review the public business knowledge, and save.
4. Enable the floating widget or place `[neonbreak_chat]` in a page.
5. Set a Fireworks account spending cap and test real questions before launch.

The widget stays hidden until a key and model are configured. The key is never included in browser configuration. Business context is provided with each request; no document indexing or fine-tuning is performed. The system instruction is a behavioral guide, not a security boundary: do not include confidential context.

## Limits and data

- Maximum 5 valid requests per minute per directly connected IP and a configurable site-wide UTC daily cap, default 100. Requests reserve quota before the provider call; failed provider requests also consume quota.
- Maximum 11 conversation messages from the widget, bounded request content and 700 output tokens for general models. GPT OSS uses low reasoning effort and a 4096-token generation budget shared by reasoning and the final answer. The server rejects system/tool messages from visitors.
- WordPress nonces protect request freshness; they are not anonymous user authentication. Public chat can consume the shared quota. For large deployments add edge abuse protection and authenticated client quotas. Reverse proxies may cause visitors to share an IP limit; forwarded headers are deliberately not trusted.
- No conversations are saved by this plugin. Browser history stays in memory until refresh or New chat. Fireworks receives the messages and context. Existing server/provider logging is governed by its configuration and policies.
- Rate counters use hashed IP identifiers and are cleaned daily by WP-Cron; sites with no traffic may clean them later. Daily quota counts requests, not money; set provider billing controls too.
- Exclude chat pages from stale full-page caching, or refresh nonce delivery in the caching integration.
- Accent color can be overridden with `.nb-chat { --nb-chat-accent: #yourcolor; }`.

API reference: https://docs.fireworks.ai/api-reference/post-chatcompletions

## Topic scope and Neonbreak knowledge

Before answering, a separate model call classifies the current question using business context and prior user turns. The server accepts only a boolean decision and supported language; invalid classification fails closed. Rejected questions receive a fixed redirection and never reach answer generation. Relevant project discovery and product questions are allowed. The answer prompt rejects unrelated tasks, fabricated offers and claims of performing actions. This model-based gate reduces off-topic behavior but does not guarantee immunity to all prompt injections. Only public information belongs in the knowledge field.

Relevant questions now use two provider calls, increasing latency and token costs. Request quotas count the visitor request, not individual provider calls. The browser timeout accommodates both calls.

`knowledge/neonbreak.txt` is the reviewed initial context. On the Neonbreak installation only, run `php tools/import-neonbreak-knowledge.php` from the plugin directory to import it. Previous context is backed up once in `neonbreak_chat_knowledge_before_import`. Client installations should populate their own Business knowledge through admin settings. Imports are manual; website updates do not automatically synchronize this text. This is curated prompt context, not document retrieval/RAG.

## Client workspace and shared widget

Run `php tools/setup-panel.php` once. The `/my-ai-chat/` page provides a sign-in link, email-based account creation, appearance preview, business context, exact allowed origins, client Fireworks key, model, request cap, enable/disable control and embed code. Registration uses WordPress password-set emails; configure and verify email delivery before inviting customers. No plaintext password is emailed. Existing WordPress accounts can sign in directly. One widget per user is supported in this MVP.

The panel always saves to the signed-in user's metadata; no client-supplied user ID controls ownership. Keys are encrypted with AES-256-GCM using a key derived from the WordPress auth salt. Keep WordPress salts stable and store backups securely: rotating salts requires clients to reconnect their keys. A changed provider key/model is tested before saving. The saved key is never prefilled or returned in markup. The Neonbreak server key is never used as fallback for an embedded client chat.

The embed script mounts an iframe on the central service. The iframe's public widget ID only selects the configuration. Exact origins and `frame-ancestors` constrain browser embedding; signed one-hour tokens authorize requests, and current origin/enabled/key settings are checked again for each reply. These controls are not proof against non-browser callers who spoof origins and acquire public tokens; per-client daily caps and per-client/IP minute limits also apply. Add edge abuse protection before broad public use. Verification requests and account creation also need production abuse monitoring.

Each valid question may make two Fireworks calls. Provider costs are borne by the client's key. No conversation storage is added. Knowledge and history are supplied to that client's provider; public iframe markup contains neither the key nor knowledge. Clients should publish appropriate privacy disclosures.

Publish the service at a public HTTPS address before external embedding. Localhost snippets work only on the same computer. Embed URLs reflect the current WordPress base URL. Changing the service's hostname requires replacing existing snippets or preserving old URLs. Config changes at the same URL require only refreshing the widget. Exclude the workspace and AJAX iframe responses from caching. Cross-site widgets do not depend on login cookies; owner previews do. Host-site CSP must permit the service script and frame origin.

Tests: `php tests/smoke.php`, `php tests/panel.php`, `php tests/service.php`, `php tests/frame.php`. Panel/service/frame checks create temporary test accounts and remove them. Service tests mock provider calls; the existing `tests/live.php` performs one billed demo request. Browser rendering and external deployment need separate QA.
