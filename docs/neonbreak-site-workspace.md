# Neonbreak website chat — Workspace account

## Owner

The public Neonbreak assistant is managed by the Workspace account `gsambolic@gmail.com` after migration. Account lookup uses the email on each installation; a local numeric user ID is never deployed to live.

## What changes

- The floating website assistant and `[neonbreak_chat]` demo load the owner's regular Workspace widget.
- Appearance, business knowledge, Fireworks model/key, domains and enabled status come from that account.
- Existing Advanced documents, Search credentials and conversation-history preferences remain in the account.
- History is captured under that account when its recording setting is enabled.
- The old public `neonbreak_chat` action returns HTTP 410 after migration. Refresh cached pages.
- Other accounts and their existing embed codes are unchanged.

## Live deployment

1. Back up the current plugin files and database.
2. Upload the complete `wp-content/plugins/neonbreak-chat/` directory from the package, including the new `site-workspace.php` file.
3. Upload the included `wp-content/themes/neonbreak/page-ai-chat.php` template.
4. In live WordPress open Settings → Neonbreak Chat.
5. Confirm `gsambolic@gmail.com` and click **Prenesi Neonbreak chat u Workspace**.
6. The action copies the current live knowledge, model and connection into the selected account. It does not import local data.
7. Clear page/CDN caches and refresh the website. Check EN/HR pages and the embedded demo.
8. Sign in as the selected owner and manage the assistant at Workspace → AI Chat.
9. Review the existing Knowledge Base documents. They are preserved and may influence Advanced answers.
10. Enable history under Workspace → Conversations if desired, after updating the privacy information; refresh the widget afterward.

The owner must not already manage another active business widget: there is one chat configuration per account. This was confirmed for the selected account before implementation.

## Backup and keys

Migration stores the previous account presentation, encrypted account key and public ID in the private `nb_site_workspace_backup` WordPress option. No key is displayed or exported in the package. Keep database backups private.

The previous global `neonbreak_chat` settings and server-side key are retained for recovery but are not used for the migrated public assistant. Remove an unused wp-config/environment key only after confirming that no other code needs it.

Binding is stored in `nb_site_workspace_owner`. If the owner disables the chat or is deleted, the site does not silently fall back to the old assistant.

## Validation

- Local migration completed for the selected owner.
- Migration, encrypted backup, idempotence and preservation of documents/history were tested with a temporary account.
- Local homepage loads one managed floating widget without legacy page-level chat configuration.
- Inline demo and Croatian widget controls return HTTP 200 locally.
- Conversation persistence and AI Search regression tests pass with mocked providers.
- Live migration and one real answer must still be checked after upload.
