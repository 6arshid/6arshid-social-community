=== 6Arshid Social Community ===
Contributors: 6arshid, hassantafreshi, aminkhadivar
Tags: community, buddypress, members, groups, messaging
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 1.8.3
Requires PHP: 8.1
License: MIT
License URI: https://opensource.org/licenses/MIT

A complete, secure, responsive, multilingual social network plugin for WordPress.

== Description ==

6Arshid Social Community transforms your WordPress site into a fully-featured social community. Every component is built with security, performance, and developer extensibility at its core — no BuddyPress dependency required.

= Member Profiles =

* Extended profile fields (xProfile) — text, textarea, select, checkbox, date, URL, social links
* Field groups with per-field visibility controls (public, friends only, only me)
* Avatar upload with EXIF-stripping and re-encoding
* Cover photo upload and management
* Profile completion progress widget
* Verified badge system
* Per-user settings persistence
* GDPR data export and erasure hooks

= Activity Stream =

* Activity composer with text, images, GIFs, emoji, and link previews
* Privacy levels per post — public, friends, private
* Inline editing and deletion of posts
* Infinite scroll and basic pagination (switchable)
* @mentions with autocomplete and notifications
* Hashtags with dedicated archive pages
* Reactions — heart, thumbs-up/down, haha, wow, sad, angry, celebrate, fire, clap, pray, love, 💯, strong, cool
* Comments with nested replies (threaded)
* Comment like and dislike reactions with animated buttons
* Comment attachments — images and documents
* GIFs in comments via GIPHY
* Share activity to new post or private message
* Sticky posts — pin any post to the top of the feed
* Bookmark / save posts with user collections
* Report posts, comments, and profiles
* Akismet spam filtering
* Single activity permalink pages

= Polls =

* Create polls with multiple options directly in the activity composer
* Poll voting with live result distribution
* Poll expiration with auto-close
* Quiz mode — reveal correct answer after voting
* Advanced Polls — suggest options, import/export, templates (save, load, delete)
* Poll participation history per user
* Scheduled hourly expiration check

= Hashtags =

* Auto-extract and link hashtags in posts
* Hashtag archive pages (`/hashtag/{slug}/`)
* Trending hashtags feed with scheduled cache refresh
* Follow and unfollow hashtags
* Hashtag autocomplete in the composer
* `[arshid6social_trending_hashtags]` shortcode

= Tag Friends =

* @mention autocomplete in posts and comments
* Photo tagging with approval and rejection workflow
* Tag removal and privacy controls
* Mention notifications

= Bookmarks =

* Save any activity post to bookmarks
* User-created bookmark collections
* Bookmark search and filtering
* Dedicated bookmarks page with infinite scroll
* `[arshid6social_bookmarks]` shortcode

= Share Posts =

* Share activity to a new post
* Share activity to a private message
* Share count tracking and history

= Sticky Posts =

* Pin posts to the top of the feed
* Sticky post expiration with scheduled cleanup

= Groups =

* Public, private, and hidden groups
* Group roles — admin, moderator, member
* Join requests and invitations
* Group activity feed and member list
* Group avatar and cover photo
* Hierarchical (parent/child) groups
* Group search and directory listing

= Friends & Follow =

* Mutual friend requests — send, accept, reject, remove
* One-way follow system
* Block and unblock users
* Friend suggestions based on mutual connections
* Friendship status queries

= Private Messaging =

* One-to-one and group conversations
* Message attachments — images and documents
* Read receipts and unread count badge
* Real-time updates via WordPress Heartbeat API
* Thread deletion and spam reporting
* Dedicated message URLs (`/messages/compose/`, `/messages/thread/{id}/`)

= Notifications =

* On-site notification centre with unread counter
* Notification types — friend requests, friendship accepted, reactions, comments, @mentions, messages, group invitations, new followers
* Per-user notification preferences by type
* Email notifications with daily and weekly digest
* Bulk delete and mark-all-read
* Scheduled cron jobs for digest delivery

= Moderation =

* Report content — activity, comments, messages, profiles, groups
* Admin moderation queue with resolve/dismiss
* Auto-suspension after configurable number of reports
* Banned words filter
* Member suspension with audit log

= Comment Attachments =

* Upload images and documents directly to comments
* File type and MIME validation
* Per-comment upload directory
* Attachment deletion by owner or admin
* EXIF stripping from uploaded images

= Message Attachments =

* Upload images and documents inside message threads
* File type and MIME validation
* Attachment serving with access control
* Deletion by owner or admin

= GIFs in Comments =

* GIPHY integration — trending, keyword search, recently used
* GIF insertion into comments
* GIF usage analytics

= Social Embeds =

* Auto-embed links pasted into posts, comments, and private messages
* 19 supported platforms: YouTube, Vimeo, X / Twitter, Instagram, Facebook, TikTok, Spotify, SoundCloud, Pinterest, Reddit, Twitch, Dailymotion, Apple Music / Podcasts, LinkedIn, Telegram, Threads, Bluesky, Aparat, and a generic Open Graph link preview fallback
* Methods: oEmbed (rich player), iframe (sandbox), and Open Graph preview card
* Lazy load / click-to-play mode — no third-party request until the user clicks (privacy-first)
* Tracking parameter stripping from URLs before embedding (`utm_*`, `fbclid`, etc.)
* Configurable cache duration with daily prune cron job
* Per-platform enable/disable toggles and banned-domain list
* REST endpoint `/wp-json/arshid6social/v1/embeds/preview` for live previews in the composer

= Unified Search =

* Full-text search across activity posts, members, groups, and marketplace listings from a single search page
* Tabbed results by section — All, Activity, Members, Groups, Marketplace
* Respects content privacy (guests see only public activity)
* REST endpoint at `/wp-json/arshid6social/v1/search`

= Ads =

* Admin-managed native ad campaigns — no external ad network required
* Ad types: image, video, HTML / JavaScript
* Placement options: sidebar, in-feed (auto-injected every N posts), or both
* Date-based scheduling — optional start and end dates per campaign
* Click tracking with per-campaign click counter

= Monetization (Paid Content & Creator Subscriptions) =

* Let creators monetize content with X-style monthly subscriptions and pay-per-view posts
* Stripe Connect — creators link their own Stripe account; no raw bank details stored on the server
* Platform application fee: configurable percentage + optional flat amount per transaction
* Minimum subscription price floor set by the site admin
* Stripe secret keys and webhook signing secrets stored encrypted in the database
* Supports live and test mode with separate key pairs
* Webhook handler for Stripe events (`customer.subscription.*`, `invoice.*`, `payment_intent.*`, `account.updated`)
* 13 supported currencies: USD, EUR, GBP, CAD, AUD, JPY, CHF, SEK, NOK, DKK, TRY, AED, SAR
* Extensible gateway layer — additional gateways can be registered via the `arshid6social_monetization_payment_gateways` filter

= XML Sitemaps =

* Automatic XML sitemap entries for public activity posts, member profiles, groups, and marketplace listings
* Integrates with the WordPress core sitemap API — no additional plugin required
* Sitemap index entries: `arshid6social_activity`, `arshid6social_members`, `arshid6social_groups`, `arshid6social_marketplace`

= Developer & REST API =

* Full REST API at `/wp-json/arshid6social/v1/`
* Endpoints for activity, members, friends, groups, messages, notifications, bookmarks, hashtags, polls, tags, share, sticky, attachments
* 50+ AJAX actions for every front-end operation
* Action and filter hooks on every major operation
* Component-based architecture — enable or disable each feature independently
* Template override support — copy any template to `{theme}/social-network/`
* Gutenberg blocks — Activity Feed, Member Directory, Group List
* Shortcodes — `[arshid6social_activity]`, `[arshid6social_members]`, `[arshid6social_groups]`, `[arshid6social_messages]`, `[arshid6social_notifications]`, `[arshid6social_profile]`, `[arshid6social_bookmarks]`, `[arshid6social_trending_hashtags]`, `[arshid6social_stories_tray]`, `[arshid6social_verification_request]`, `[arshid6social_block_list]`

= Multilingual =

* Full i18n with `.pot`, `fa_IR` (Persian), and `da_DK` (Danish) included
* RTL support for Persian and Arabic with a dedicated `rtl.css`
* Compatible with WPML, Polylang, and TranslatePress
* Jalali (Shamsi) calendar option for Persian dates

= Security =

* All input sanitized, all output escaped
* Prepared statements on every database query
* Nonces on every form and AJAX action
* Capability checks before every privileged operation
* Rate limiting on posts, messages, and friend requests
* Strict MIME type and extension validation for uploads
* Image re-encoding to strip EXIF data and embedded payloads
* Honeypot on forms with optional reCAPTCHA / Cloudflare Turnstile
* Akismet integration for spam filtering
* CSRF, XSS, SQL injection, and IDOR protection throughout

= Performance =

* Conditional asset loading — JS and CSS only on plugin pages, never site-wide
* `filemtime()`-based JS version string for automatic cache busting
* Object cache and transient caching throughout
* Proper database indexes on all custom tables
* Mobile-first CSS with zero jQuery dependency in JS
* Deferred script loading strategy

= Accessibility =

* WCAG 2.1 AA compliant
* ARIA labels and keyboard navigation
* Touch-friendly UI (tap targets ≥ 44 × 44 px)
* Dark mode support — system preference and manual toggle

= GDPR =

* Data export and erasure hooks integrated with WP Privacy Tools
* Per-user email opt-out for notifications

= External Services =

This plugin connects to several third-party services to deliver certain features (GIF search, profile avatars, social embeds, and sharing). Each service is optional and can be disabled via the Components settings. See the **External Services** section at the bottom of this readme for full details, privacy policies, and terms of service links.

== Installation ==

1. Upload the `6arshid-social-community` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Follow the setup wizard that appears after activation
4. Configure components and settings at **6arshid Social Community → Settings**

== Frequently Asked Questions ==

= Can I disable specific features? =
Yes. Go to **6arshid Social Community → Settings → Components** and toggle each feature on or off.

= How do I override a template? =
Copy the template file from `6arshid-social-community/templates/` into your theme at `{your-theme}/social-network/` with the same relative path.

= Is it compatible with BuddyPress? =
6Arshid Social Community is an independent plugin and does not require BuddyPress. A data migration tool from BuddyPress is included in the admin Tools page.

= Does it work in RTL? =
Yes. Persian and Arabic are fully supported with a dedicated `rtl.css` and Jalali calendar date formatting.

= How do I add custom profile fields? =
Developers can use the `arshid6social_xprofile_groups` and `arshid6social_xprofile_fields` tables directly or via the provided PHP API. An admin UI for field management is planned for a future release.

= Can I extend it with my own components? =
Yes. Hook into `arshid6social_loaded` to register custom components, and use `arshid6social_settings_tabs` to add your own settings tab.

= What shortcodes are available? =
`[arshid6social_activity]`, `[arshid6social_members]`, `[arshid6social_groups]`, `[arshid6social_messages]`, `[arshid6social_notifications]`, `[arshid6social_profile]`, `[arshid6social_bookmarks]`, `[arshid6social_trending_hashtags]`, `[arshid6social_stories_tray]`, `[arshid6social_verification_request]`, `[arshid6social_block_list]`

= Is the REST API available to external apps? =
Yes. The REST API at `/wp-json/arshid6social/v1/` covers activity, members, friends, groups, messages, notifications, bookmarks, hashtags, polls, tags, sharing, sticky posts, and attachments.

== Screenshots ==

1. Member directory with search and filters
2. User profile with cover photo, avatar, and activity feed
3. Activity composer with emoji, GIF, poll, and attachment support
4. Comment section with threaded replies, reactions, and GIF support
5. Group directory and single group view
6. Private messages inbox with real-time updates
7. On-site notification centre
8. Bookmarks with collections
9. Trending hashtags feed
10. Admin settings panel (tabbed)
11. Admin moderation queue

== External services ==

This plugin can contact the following third-party services. Calls are feature-gated and happen only when the matching feature is enabled, configured, or triggered by a user or administrator action.

= Gravatar =
Member profile photos fall back to Gravatar when no custom avatar has been uploaded. The request sends the MD5 hash of the user's email address to Gravatar when an avatar is displayed.
* Service: https://gravatar.com
* Privacy Policy: https://automattic.com/privacy/
* Terms of Service: https://wordpress.com/tos/

= GIF search APIs: GIPHY and Tenor =
When GIF comments are enabled and an API key is configured, the GIF picker requests trending GIFs or search results from the selected provider. Search requests send the user's search term, the configured API key, and normal HTTP request metadata such as IP address and user agent.
* GIPHY API: https://api.giphy.com/v1/gifs/
  * Privacy Policy: https://support.giphy.com/hc/en-us/articles/360032872931-GIPHY-Privacy-Policy
  * Terms of Service: https://support.giphy.com/hc/en-us/articles/360020027752-GIPHY-User-Terms-of-Service
* Tenor API: https://tenor.googleapis.com/v2/
  * Privacy Policy: https://policies.google.com/privacy
  * Terms of Service: https://policies.google.com/terms

= Social embeds and link previews =
When Social Embeds are enabled and a user pastes a supported URL into social content, the plugin fetches oEmbed data, iframe metadata, or Open Graph metadata for that URL. The requested URL, site server request metadata, and any administrator-configured provider token are sent to the matched provider. Rendered embeds or iframes may also cause the visitor's browser to contact the provider when the embedded content is displayed. Each provider can be disabled in Settings > Engagement.
* YouTube / Google: https://www.youtube.com/oembed
  * Privacy Policy: https://policies.google.com/privacy
  * Terms of Service: https://policies.google.com/terms
* Vimeo: https://vimeo.com/api/oembed.json
  * Privacy Policy: https://vimeo.com/privacy
  * Terms of Service: https://vimeo.com/terms
* X / Twitter: https://publish.twitter.com/oembed
  * Privacy Policy: https://x.com/en/privacy
  * Terms of Service: https://x.com/en/tos
* Instagram / Meta: https://graph.facebook.com/v18.0/instagram_oembed
  * Privacy Policy: https://privacycenter.instagram.com/policy/
  * Terms of Service: https://help.instagram.com/581066165581870
* Facebook / Meta: https://graph.facebook.com/v18.0/oembed_post
  * Privacy Policy: https://www.facebook.com/privacy/policy/
  * Terms of Service: https://www.facebook.com/terms.php
* TikTok: https://www.tiktok.com/oembed
  * Privacy Policy: https://www.tiktok.com/legal/page/us/privacy-policy/en
  * Terms of Service: https://www.tiktok.com/legal/page/us/terms-of-service/en
* Spotify: https://open.spotify.com/oembed
  * Privacy Policy: https://www.spotify.com/legal/privacy-policy/
  * Terms of Service: https://www.spotify.com/legal/end-user-agreement/
* SoundCloud: https://soundcloud.com/oembed
  * Privacy Policy: https://soundcloud.com/pages/privacy
  * Terms of Service: https://soundcloud.com/terms-of-use
* Pinterest: https://www.pinterest.com/oembed.json
  * Privacy Policy: https://policy.pinterest.com/en/privacy-policy
  * Terms of Service: https://policy.pinterest.com/en/terms-of-service
* Reddit: https://www.reddit.com/oembed
  * Privacy Policy: https://www.reddit.com/policies/privacy-policy
  * Terms of Service: https://redditinc.com/policies/user-agreement
* Twitch: https://clips.twitch.tv/embed and https://player.twitch.tv/
  * Privacy Policy: https://legal.twitch.com/en/legal/privacy-notice/
  * Terms of Service: https://legal.twitch.com/en/legal/terms-of-service/
* Dailymotion: https://www.dailymotion.com/services/oembed
  * Privacy Policy: https://legal.dailymotion.com/en/privacy-policy/
  * Terms of Service: https://legal.dailymotion.com/en/terms-of-use/
* Apple Music / Podcasts: https://music.apple.com and https://podcasts.apple.com
  * Privacy Policy: https://www.apple.com/legal/privacy/
  * Terms of Service: https://www.apple.com/legal/internet-services/itunes/
* LinkedIn: https://www.linkedin.com
  * Privacy Policy: https://www.linkedin.com/legal/privacy-policy
  * Terms of Service: https://www.linkedin.com/legal/user-agreement
* Telegram: https://t.me/
  * Privacy Policy: https://telegram.org/privacy
  * Terms of Service: https://telegram.org/tos
* Threads / Meta: https://www.threads.net/oembed/
  * Privacy Policy: https://help.instagram.com/515230437301944/
  * Terms of Service: https://help.instagram.com/769983657850450
* Bluesky: https://bsky.app
  * Privacy Policy: https://bsky.social/about/support/privacy-policy
  * Terms of Service: https://bsky.social/about/support/tos
* Aparat: https://www.aparat.com/oembed.json
  * Privacy Policy: https://www.aparat.com/privacy
  * Terms of Service: https://www.aparat.com/terms
* Generic Open Graph previews: when no named provider matches and generic previews are enabled, the plugin fetches the user-submitted target URL to read metadata. The external service is the target site chosen by the user; that site's own privacy policy and terms apply.

= Social sharing links =
The social sharing feature builds share links in the browser. No request is sent to these services until the user explicitly clicks a share button or opens a local share handler. The shared page URL and title are passed to the selected service or app.
* Facebook / Meta: https://www.facebook.com/sharer/sharer.php
  * Privacy Policy: https://www.facebook.com/privacy/policy/
  * Terms of Service: https://www.facebook.com/terms.php
* X / Twitter: https://twitter.com/intent/tweet
  * Privacy Policy: https://x.com/en/privacy
  * Terms of Service: https://x.com/en/tos
* WhatsApp: https://api.whatsapp.com/send
  * Privacy Policy: https://www.whatsapp.com/legal/privacy-policy
  * Terms of Service: https://www.whatsapp.com/legal/terms-of-service
* Telegram: https://t.me/share/url
  * Privacy Policy: https://telegram.org/privacy
  * Terms of Service: https://telegram.org/tos
* LinkedIn: https://www.linkedin.com/sharing/share-offsite/
  * Privacy Policy: https://www.linkedin.com/legal/privacy-policy
  * Terms of Service: https://www.linkedin.com/legal/user-agreement
* Reddit: https://reddit.com/submit
  * Privacy Policy: https://www.reddit.com/policies/privacy-policy
  * Terms of Service: https://redditinc.com/policies/user-agreement
* Threads / Meta: https://www.threads.net/intent/post
  * Privacy Policy: https://help.instagram.com/515230437301944/
  * Terms of Service: https://help.instagram.com/769983657850450
* Bluesky: https://bsky.app/intent/compose
  * Privacy Policy: https://bsky.social/about/support/privacy-policy
  * Terms of Service: https://bsky.social/about/support/tos
* Pinterest: https://pinterest.com/pin/create/button/
  * Privacy Policy: https://policy.pinterest.com/en/privacy-policy
  * Terms of Service: https://policy.pinterest.com/en/terms-of-service
* LINE: https://social-plugins.line.me/lineit/share
  * Privacy Policy: https://www.lycorp.co.jp/en/company/privacypolicy/
  * Terms of Service: https://terms.line.me/line_terms
* Gmail / Google: https://mail.google.com/mail/
  * Privacy Policy: https://policies.google.com/privacy
  * Terms of Service: https://policies.google.com/terms
* Yahoo Mail and AOL Mail: https://compose.mail.yahoo.com/ and https://mail.aol.com/
  * Privacy Policy: https://legal.yahoo.com/us/en/yahoo/privacy/index.html
  * Terms of Service: https://legal.yahoo.com/us/en/yahoo/terms/otos/index.html
* Outlook.com / Microsoft: https://outlook.live.com/owa/
  * Privacy Policy: https://privacy.microsoft.com/privacystatement
  * Terms of Service: https://www.microsoft.com/servicesagreement
* Viber local app handler: viber://forward
  * Privacy Policy: https://www.viber.com/en/terms/viber-privacy-policy/
  * Terms of Service: https://www.viber.com/en/terms/viber-terms-use/
* Email, SMS, copy-link, and send-as-message actions are local browser/site actions. The plugin does not make a third-party HTTP request for those actions.

= Stripe =
When the Monetization module is enabled and Stripe keys are configured, the checkout UI loads Stripe.js from Stripe and server-side monetization requests call the Stripe API to create or retrieve payment intents and process webhook events. Data sent can include amount, currency, payment intent identifiers, Stripe keys, purchaser and creator user IDs, activity IDs, and Stripe metadata required to complete the payment. The plugin does not store raw card or bank details.
* Stripe.js: https://js.stripe.com/v3/
* Stripe API: https://api.stripe.com/v1/
* Privacy Policy: https://stripe.com/privacy
* Terms of Service: https://stripe.com/legal

= Akismet =
When Akismet spam checking is enabled and the separate Akismet plugin is installed and active, activity content is sent to Akismet's spam-checking API before publication. Data sent includes the submitted content, author information, IP address, user agent, referrer, permalink, and site URL.
* Service: https://akismet.com
* Privacy Policy: https://automattic.com/privacy/
* Terms of Service: https://akismet.com/tos/

= WordPress.org theme downloads =
The setup wizard can download a WordPress.org theme ZIP when an administrator explicitly chooses to install a theme from the wizard. The requested theme slug and normal server request metadata are sent to WordPress.org.
* Service: https://downloads.wordpress.org/theme/
* Privacy Policy: https://wordpress.org/about/privacy/
* Project License and Policies: https://wordpress.org/about/license/

= Developer build helper =
The development-only Bootstrap Icons download helper in the build directory downloads the Bootstrap Icons release ZIP from GitHub only when a maintainer runs that script manually. This is not executed during normal plugin runtime.
* GitHub: https://github.com/twbs/icons/
  * Privacy Policy: https://docs.github.com/site-policy/privacy-policies/github-privacy-statement
  * Terms of Service: https://docs.github.com/site-policy/github-terms/github-terms-of-service

== Changelog ==

= 1.8.3 =
* Security hardening: tightened REST permission callbacks, social media file serving, story visibility checks, friend-request acceptance, monetization setting sanitization, and remote GIF API requests.
* External services documentation: rebuilt the section to match the currently supported services and removed obsolete sharing-provider disclosures.

= 1.8.2 =
* Plugin Check: resolved the remaining `PluginCheck.Security.DirectDB.UnescapedDBParameter` notices by annotating the safe dynamic table-name/whitelist identifiers (all query values are bound via `$wpdb->prepare()` placeholders — never raw user input) and cleared the last `PreparedSQLPlaceholders` notices.
* Hardened the dev-only CLI migration test harness with a direct-access guard; it remains excluded from the distributed package.
* Trimmed the readme tag list to five.

= 1.8.1 =
* Hardening: unslashed and type-correctly sanitized all remaining superglobal reads (passwords are unslashed but never sanitize_text_field'd; arrays sanitized per field type; uploads validated by the media handler).
* Added explicit nonce-context annotations to the Ads admin screen's read-only view routing (form processing was already nonce-verified).
* Corrected all `$wpdb->prepare()` placeholder usages flagged by Plugin Check (dynamic `IN()`/`WHERE` clauses build their placeholders at runtime and bind values via prepare()).
* Annotated safe dynamic table-name interpolations (built from `$wpdb->prefix` and in-code whitelists, never user input) and the intentional third-party cache-plugin integration hooks.

= 1.8.0 =
* Consistency: migrated all custom database tables from the legacy `sn_` secondary prefix to the plugin's standard `arshid6social_` prefix. Existing sites are upgraded automatically and losslessly via an idempotent `RENAME TABLE` migration on update; fresh installs create the new table names directly.
* Consistency: renamed the remaining client-side `sn_*` form-field names (GIF and attachment staging fields) to the `arshid6social_*` prefix. No stored data or request payloads are affected.
* Fixed broken third-party Terms/Privacy links in the External Services documentation.
* Packaging: the development-only `build/` tooling is now excluded from the distributed plugin via `.distignore` / `.gitattributes` and a `bin/make-dist.sh` build script.

= 1.7.0 =
* Renamed plugin to 6Arshid Social Community with a consistent `6arshid-social-community` slug/text domain and `arshid6social` code prefix throughout.
* Fixed remaining WordPress.org plugin review items: removed the last raw inline `<script>` output, removed the remaining remote Google Fonts dependency, corrected short/inconsistent prefixes in the Monetization module and a handful of shortcodes, and documented Akismet under External Services.

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.7.0 =
Internal prefix and slug consistency fixes; no action required for existing installs.

= 1.0.0 =
Initial release. No upgrade path required.
