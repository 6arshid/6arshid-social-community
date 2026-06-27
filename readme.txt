===6Arshid Social Community===
Contributors: 6arshid, hassantafreshi , aminkhadivar
Tags: social network, community, members, activity, groups, buddypress, messaging, profiles
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 1.5.7
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
* `[sn_trending_hashtags]` shortcode

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
* `[sn_bookmarks]` shortcode

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
* Extensible gateway layer — additional gateways can be registered via the `sixarshidsc_payment_gateways` filter

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
* Shortcodes — `[arshid6social_activity]`, `[arshid6social_members]`, `[arshid6social_groups]`, `[arshid6social_messages]`, `[arshid6social_notifications]`, `[arshid6social_profile]`, `[arshid6social_login_form]`, `[arshid6social_register_form]`, `[sn_bookmarks]`, `[sn_trending_hashtags]`

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

1. Upload the `social-network-6` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Follow the setup wizard that appears after activation
4. Configure components and settings at **Social Network → Settings**

== Frequently Asked Questions ==

= Can I disable specific features? =
Yes. Go to **Social Network → Settings → Components** and toggle each feature on or off.

= How do I override a template? =
Copy the template file from `social-network-6/templates/` into your theme at `{your-theme}/social-network/` with the same relative path.

= Is it compatible with BuddyPress? =
6Arshid Social Community is an independent plugin and does not require BuddyPress. A data migration tool from BuddyPress is included in the admin Tools page.

= Does it work in RTL? =
Yes. Persian and Arabic are fully supported with a dedicated `rtl.css` and Jalali calendar date formatting.

= How do I add custom profile fields? =
Developers can use the `arshid6social_xprofile_groups` and `arshid6social_xprofile_fields` tables directly or via the provided PHP API. An admin UI for field management is planned for a future release.

= Can I extend it with my own components? =
Yes. Hook into `arshid6social_loaded` to register custom components, and use `arshid6social_settings_tabs` to add your own settings tab.

= What shortcodes are available? =
`[arshid6social_activity]`, `[arshid6social_members]`, `[arshid6social_groups]`, `[arshid6social_messages]`, `[arshid6social_notifications]`, `[arshid6social_profile]`, `[arshid6social_login_form]`, `[arshid6social_register_form]`, `[sn_bookmarks]`, `[sn_trending_hashtags]`

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

== External Services ==

This plugin connects to the following third-party services:

= Gravatar =
Member profile photos fall back to Gravatar (gravatar.com) when no custom avatar has been uploaded. The user's email hash is sent to Gravatar's servers. This happens when viewing a member profile.
* Service: https://gravatar.com
* Privacy Policy: https://automattic.com/privacy/
* Terms of Service: https://gravatar.com/site/terms-of-service

= GIPHY =
When GIF comments are enabled, this plugin queries the GIPHY API to display trending and searchable GIFs. The user's search query is sent to GIPHY's servers. This happens when a user opens the GIF picker in comments.
* Service: https://giphy.com
* Privacy Policy: https://support.giphy.com/hc/en-us/articles/360032872931
* Terms of Service: https://support.giphy.com/hc/en-us/articles/360020027752


= Social Embeds (YouTube, Vimeo, X / Twitter, Instagram, Facebook, TikTok, Spotify, SoundCloud, Pinterest, Reddit, Twitch, Dailymotion, Apple Music / Podcasts, LinkedIn, Telegram, Threads, Bluesky, Aparat) =
When Social Embeds are enabled and a user pastes a supported URL into a post, comment, or message, this plugin fetches oEmbed data or Open Graph metadata from the respective platform. The URL is sent to the platform's servers only when an embed is requested. Each provider can be individually enabled or disabled in Settings → Engagement.
* Facebook oEmbed: https://developers.facebook.com/docs/plugins/oembed
  * Privacy Policy: https://www.facebook.com/privacy/policy/
  * Terms of Service: https://www.facebook.com/terms.php
* Instagram oEmbed: https://developers.facebook.com/docs/instagram/oembed
  * Privacy Policy: https://privacycenter.instagram.com/policy/
  * Terms of Service: https://help.instagram.com/581066165581870
* Vimeo oEmbed: https://developer.vimeo.com/api/oembed/videos
  * Privacy Policy: https://vimeo.com/privacy
  * Terms of Service: https://vimeo.com/terms
* Telegram Embeds: https://core.telegram.org/widgets/post
  * Privacy Policy: https://telegram.org/privacy
  * Terms of Service: https://telegram.org/tos
* Aparat oEmbed: https://www.aparat.com
  * Privacy Policy: https://www.aparat.com/
  * Terms of Service: https://www.aparat.com/
* YouTube: https://www.youtube.com — Privacy Policy: https://policies.google.com/privacy
* X / Twitter: https://publish.twitter.com/oembed — Privacy Policy: https://twitter.com/en/privacy
* TikTok: https://www.tiktok.com/oembed — Privacy Policy: https://www.tiktok.com/legal/privacy-policy
* Spotify: https://open.spotify.com/oembed — Privacy Policy: https://www.spotify.com/legal/privacy-policy/
* SoundCloud: https://soundcloud.com/oembed — Privacy Policy: https://soundcloud.com/pages/privacy
* Pinterest: https://www.pinterest.com/oembed.json — Privacy Policy: https://policy.pinterest.com/en/privacy-policy
* Reddit: https://www.reddit.com/oembed — Privacy Policy: https://www.reddit.com/policies/privacy-policy
* Twitch: https://www.twitch.tv — Privacy Policy: https://www.twitch.tv/p/legal/privacy-notice/
* Dailymotion: https://www.dailymotion.com/services/oembed — Privacy Policy: https://www.dailymotion.com/legal/privacy
* Apple Music / Podcasts: https://music.apple.com — Privacy Policy: https://www.apple.com/legal/privacy/
* LinkedIn: https://www.linkedin.com — Privacy Policy: https://www.linkedin.com/legal/privacy-policy
* Threads: https://www.threads.net/oembed/ — Privacy Policy: https://privacycenter.instagram.com/policy/ — Terms of Service: https://help.instagram.com/581066165581870
* Bluesky: https://bsky.app — Privacy Policy: https://bsky.social/about/support/privacy-policy — Terms of Service: https://bsky.social/about/support/tos

= QR Code Generation (WeChat Sharing) =
When a user opens the WeChat share option in the Social Share widget, this plugin fetches a QR code image from the QRServer API. The shared page URL is sent to QRServer's servers at that moment to generate the QR code image. No personal user data is sent — only the public page URL.
* Service: https://goqr.me/api/
* Privacy Policy: https://goqr.me/privacy-policy/
* Terms of Service: https://goqr.me/terms-of-service/

= WhatsApp, Social Sharing (AOL Mail, Papaly, Twiddla, and 80+ networks) =
The social sharing feature generates links that open third-party social networks or email clients in a new browser tab/window when the user explicitly clicks a share button. No data is sent automatically by the plugin — all sharing actions are user-initiated and subject to each platform's own privacy policy. Networks include (but are not limited to): WhatsApp, Facebook, Twitter/X, Telegram, LinkedIn, Reddit, Pinterest, Tumblr, AOL Mail, Papaly, Twiddla, and many others.
* WhatsApp: https://www.whatsapp.com
  * Privacy Policy: https://www.whatsapp.com/legal/privacy-policy
  * Terms of Service: https://www.whatsapp.com/legal/terms-of-service
* AOL Mail: https://mail.aol.com — Privacy Policy: https://legal.oath.com/us/en/oath/privacy/products/aolmail/index.html
* Papaly: https://papaly.com — Privacy Policy: https://papaly.com/privacy
* Twiddla: https://www.twiddla.com — Privacy Policy: https://www.twiddla.com/Privacy.aspx

= Stripe (Monetization) =
When the Monetization module is enabled, creator onboarding and payment processing are handled by Stripe Connect. The plugin communicates with the Stripe API to create and manage Stripe Connect accounts, subscriptions, and payment intents. The plugin does NOT store raw bank details — Stripe handles creator identity verification (KYC) and bank payouts directly.
* Service: https://stripe.com
* Privacy Policy: https://stripe.com/privacy
* Terms of Service: https://stripe.com/legal

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade path required.
