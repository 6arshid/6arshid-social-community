# 6Arshid Social Community

**Contributors:** 6arshid, hassantafreshi, aminkhadivar
**Tags:** social network, community, members, activity, groups, buddypress, messaging, profiles
**Requires at least:** 6.5
**Tested up to:** 6.7
**Stable tag:** 1.0.0
**Requires PHP:** 8.1
**License:** MIT
**License URI:** https://opensource.org/licenses/MIT

A complete, secure, responsive, multilingual social network plugin for WordPress.

---

## Description

6Arshid Social Community transforms your WordPress site into a fully-featured social community. Every component is built with security, performance, and developer extensibility at its core — no BuddyPress dependency required.

### Member Profiles

- Extended profile fields (xProfile) — text, textarea, select, checkbox, date, URL, social links
- Field groups with per-field visibility controls (public, friends only, only me)
- Avatar upload with EXIF-stripping and re-encoding
- Cover photo upload and management
- Profile completion progress widget
- Verified badge system
- Per-user settings persistence
- GDPR data export and erasure hooks

### Activity Stream

- Activity composer with text, images, GIFs, emoji, and link previews
- Privacy levels per post — public, friends, private
- Inline editing and deletion of posts
- Infinite scroll and basic pagination (switchable)
- @mentions with autocomplete and notifications
- Hashtags with dedicated archive pages
- Reactions — heart, thumbs-up/down, haha, wow, sad, angry, celebrate, fire, clap, pray, love, 💯, strong, cool
- Comments with nested replies (threaded)
- Comment like and dislike reactions with animated buttons
- Comment attachments — images and documents
- GIFs in comments via GIPHY
- Share activity to new post or private message
- Sticky posts — pin any post to the top of the feed
- Bookmark / save posts with user collections
- Report posts, comments, and profiles
- Akismet spam filtering
- Single activity permalink pages

### Polls

- Create polls with multiple options directly in the activity composer
- Poll voting with live result distribution
- Poll expiration with auto-close
- Quiz mode — reveal correct answer after voting
- Advanced Polls — suggest options, import/export, templates (save, load, delete)
- Poll participation history per user
- Scheduled hourly expiration check

### Hashtags

- Auto-extract and link hashtags in posts
- Hashtag archive pages (`/hashtag/{slug}/`)
- Trending hashtags feed with scheduled cache refresh
- Follow and unfollow hashtags
- Hashtag autocomplete in the composer
- `[sn_trending_hashtags]` shortcode

### Tag Friends

- @mention autocomplete in posts and comments
- Photo tagging with approval and rejection workflow
- Tag removal and privacy controls
- Mention notifications

### Bookmarks

- Save any activity post to bookmarks
- User-created bookmark collections
- Bookmark search and filtering
- Dedicated bookmarks page with infinite scroll
- `[sn_bookmarks]` shortcode

### Share Posts

- Share activity to a new post
- Share activity to a private message
- Share count tracking and history

### Sticky Posts

- Pin posts to the top of the feed
- Sticky post expiration with scheduled cleanup

### Groups

- Public, private, and hidden groups
- Group roles — admin, moderator, member
- Join requests and invitations
- Group activity feed and member list
- Group avatar and cover photo
- Hierarchical (parent/child) groups
- Group search and directory listing

### Friends & Follow

- Mutual friend requests — send, accept, reject, remove
- One-way follow system
- Block and unblock users
- Friend suggestions based on mutual connections
- Friendship status queries

### Private Messaging

- One-to-one and group conversations
- Message attachments — images and documents
- Read receipts and unread count badge
- Real-time updates via WordPress Heartbeat API
- Thread deletion and spam reporting
- Dedicated message URLs (`/messages/compose/`, `/messages/thread/{id}/`)

### Notifications

- On-site notification centre with unread counter
- Notification types — friend requests, friendship accepted, reactions, comments, @mentions, messages, group invitations, new followers
- Per-user notification preferences by type
- Email notifications with daily and weekly digest
- Bulk delete and mark-all-read
- Scheduled cron jobs for digest delivery

### Moderation

- Report content — activity, comments, messages, profiles, groups
- Admin moderation queue with resolve/dismiss
- Auto-suspension after configurable number of reports
- Banned words filter
- Member suspension with audit log

### Comment Attachments

- Upload images and documents directly to comments
- File type and MIME validation
- Per-comment upload directory
- Attachment deletion by owner or admin
- EXIF stripping from uploaded images

### Message Attachments

- Upload images and documents inside message threads
- File type and MIME validation
- Attachment serving with access control
- Deletion by owner or admin

### GIFs in Comments

- GIPHY integration — trending, keyword search, recently used
- GIF insertion into comments
- GIF usage analytics

### Developer & REST API

- Full REST API at `/wp-json/arshid6social/v1/`
- Endpoints for activity, members, friends, groups, messages, notifications, bookmarks, hashtags, polls, tags, share, sticky, attachments
- 50+ AJAX actions for every front-end operation
- Action and filter hooks on every major operation
- Component-based architecture — enable or disable each feature independently
- Template override support — copy any template to `{theme}/social-network/`
- Gutenberg blocks — Activity Feed, Member Directory, Group List
- Shortcodes — `[arshid6social_activity]`, `[arshid6social_members]`, `[arshid6social_groups]`, `[arshid6social_messages]`, `[arshid6social_notifications]`, `[arshid6social_profile]`, `[arshid6social_login_form]`, `[arshid6social_register_form]`, `[sn_bookmarks]`, `[sn_trending_hashtags]`

### Multilingual

- Full i18n with `.pot`, `fa_IR` (Persian), and `da_DK` (Danish) included
- RTL support for Persian and Arabic with a dedicated `rtl.css`
- Compatible with WPML, Polylang, and TranslatePress
- Jalali (Shamsi) calendar option for Persian dates

### Security

- All input sanitized, all output escaped
- Prepared statements on every database query
- Nonces on every form and AJAX action
- Capability checks before every privileged operation
- Rate limiting on posts, messages, and friend requests
- Strict MIME type and extension validation for uploads
- Image re-encoding to strip EXIF data and embedded payloads
- Honeypot on forms with optional reCAPTCHA / Cloudflare Turnstile
- Akismet integration for spam filtering
- CSRF, XSS, SQL injection, and IDOR protection throughout

### Performance

- Conditional asset loading — JS and CSS only on plugin pages, never site-wide
- `filemtime()`-based JS version string for automatic cache busting
- Object cache and transient caching throughout
- Proper database indexes on all custom tables
- Mobile-first CSS with zero jQuery dependency in JS
- Deferred script loading strategy

### Accessibility

- WCAG 2.1 AA compliant
- ARIA labels and keyboard navigation
- Touch-friendly UI (tap targets ≥ 44 × 44 px)
- Dark mode support — system preference and manual toggle

### GDPR

- Data export and erasure hooks integrated with WP Privacy Tools
- Per-user email opt-out for notifications

---

## Installation

1. Upload the `social-network-6` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Follow the setup wizard that appears after activation
4. Configure components and settings at **Social Network → Settings**

---

## Plugin Settings Reference

All options are available at **Social Network → Settings**. Settings are grouped into tabs.

---

### Tab: General

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Allow Registration | `arshid6social_allow_registration` | checkbox | `true` | Allow new users to register on the social network. |
| Date Format | `arshid6social_date_format` | select | `relative` | `relative` = "5 minutes ago", `absolute` = "June 12, 2026", `jalali` = Persian/Shamsi calendar. |
| Invitation Limit | `arshid6social_invitation_limit` | number | `20` | Maximum invitations a member can send. `0` = unlimited. |

---

### Tab: Components

#### Active Components

Toggle each major feature on or off. The Members component is always active.

| Component | Key (in `arshid6social_enabled_components[]`) | Description |
|---|---|---|
| Activity Streams | `activity` | News feed, posts, reactions, comments |
| Groups | `groups` | Public, private, and hidden groups |
| Friends & Follow | `friends` | Friend requests, follow, block |
| Private Messages | `messages` | One-to-one and group messaging |
| Notifications | `notifications` | On-site and email notifications |
| Moderation | `moderation` | Reports, bans, audit log |

#### Engagement Pack

Optional standalone features stored in their own option keys.

| Feature | Key | Default | Description |
|---|---|---|---|
| Stories | `arshid6social_stories_enabled` | `false` | 24-hour ephemeral photo, video, and text stories |
| Verification Badges | `arshid6social_verification_enabled` | `false` | Verified badge + user request flow and admin queue |
| Block System | `arshid6social_blocking_enabled` | `false` | Block / unblock users with optional reason |
| Activity Stats Bar | `arshid6social_activity_stats_bar` | `false` | Show engagement counts (comments, reposts, likes, views) below each post |

---

### Tab: Members

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Members Per Page | `arshid6social_members_per_page` | number | `20` | Number of members shown per page in the directory (5–100). |
| Avatar Size | `arshid6social_profile_photo_size` | number (px) | `150` | Max dimension for uploaded profile photos (50–500 px). |
| Cover Photo Width | `arshid6social_cover_photo_width` | number (px) | `1200` | Max width for cover photo uploads (400–3840 px). |
| Cover Photo Height | `arshid6social_cover_photo_height` | number (px) | `350` | Max height for cover photo uploads (100–1000 px). |
| Max Upload Size | `arshid6social_max_upload_size_mb` | number (MB) | `5` | Maximum file size for profile/cover uploads in MB (1–100). |

#### Verification Badges (sub-section)

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable Verification | `arshid6social_verification_enabled` | checkbox | `false` | Show verified badge on profiles, posts, and stories. |
| Require Document Upload | `arshid6social_verification_require_doc` | checkbox | `false` | Make document upload mandatory in the verification request form. |
| Badge Expiry | `arshid6social_verification_expiry_months` | number | `0` | Months until a badge expires. `0` = never expires. |
| Auto-Purge Documents | `arshid6social_verification_doc_purge` | checkbox | `true` | Delete uploaded identity documents after approval or rejection. |
| Rate Limit | `arshid6social_verification_rate_limit` | number | `3` | Max verification requests a user can submit per hour (1–20). |
| Badge Image | `arshid6social_verification_badge_image` | attachment ID | — | Custom image for the verified badge (PNG/SVG, 32–64 px square). Leave empty to use the text badge character. |
| Verification Types | `arshid6social_verification_types` | JSON array | `[]` | Array of badge type objects: `{ "key": "general", "label": "Verified", "badge": "✓", "color": "#2563eb" }` |

---

### Tab: Activity

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Activity Items Per Page | `arshid6social_activity_per_page` | number | `20` | Number of posts loaded per page/batch (5–100). |
| Pagination Style | `arshid6social_activity_pagination_type` | select | `infinite_scroll` | `infinite_scroll` = auto-load on scroll; `pagination` = numbered pages. |
| Allow Comments | `arshid6social_activity_allow_comments` | checkbox | `true` | Allow members to comment on activity posts. |
| Allow Media Uploads | `arshid6social_activity_allow_media` | checkbox | `true` | Allow members to attach files to activity posts. |
| Allowed Media Types | `arshid6social_activity_allowed_media_types` | checkboxes | `[image]` | Which file types members can upload: `image` (JPEG, PNG, GIF, WebP), `video` (MP4, WebM, OGG), `audio` (MP3, WAV, OGG), `document` (PDF). |

#### Stories (sub-section)

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable Stories | `arshid6social_stories_enabled` | checkbox | `false` | Show 24-hour ephemeral stories tray on the activity page and profiles. |
| Story Expiry | `arshid6social_stories_expiry_hours` | number | `24` | How many hours a story stays visible before auto-expiring (1–72). |
| Max Video Length | `arshid6social_stories_max_video_secs` | number | `30` | Maximum video story duration in seconds (5–300). |
| Allow Video Stories | `arshid6social_stories_allow_video` | checkbox | `true` | Members can upload short video stories. |
| Enable Highlights | `arshid6social_stories_highlights` | checkbox | `true` | Allow members to save expired stories as permanent Highlights on their profile. |
| Rate Limit | `arshid6social_stories_rate_limit` | number | `20` | Max stories a user can post per hour (1–200). |
| Show Bottom Bar | `arshid6social_stories_bottom_bar` | checkbox | `false` | Show a fixed stories bar at the bottom of every page on the site. |
| Show in Marketplace | `arshid6social_stories_bottom_bar_marketplace` | checkbox | `false` | Show the bottom stories bar on the Marketplace page. |
| Show in Messages & Inbox | `arshid6social_stories_bottom_bar_messages` | checkbox | `false` | Show the bottom stories bar on the Messages and Inbox pages. |

---

### Tab: Groups

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Groups Per Page | `arshid6social_groups_per_page` | number | `20` | Number of groups shown per page in the directory (5–100). |

---

### Tab: Messages

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Messages Per Page | `arshid6social_messages_per_page` | number | `20` | Number of message threads shown per page (5–100). |
| Story Reply in Messages | `arshid6social_messages_story_enabled` | checkbox | `false` | Allow users to reply to stories via private message. When disabled, the reply input is hidden from the story viewer. |

---

### Tab: Notifications

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Email Notifications | `arshid6social_email_notifications` | checkbox | `true` | Send email notifications to members. |
| Email Digest | `arshid6social_email_digest` | select | `daily` | `none` = disabled, `daily` = daily digest, `weekly` = weekly digest. |

---

### Tab: Security

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Akismet Integration | `arshid6social_enable_akismet` | checkbox | `true` | Use Akismet to filter activity and message spam (requires Akismet plugin). |
| reCAPTCHA / Turnstile | `arshid6social_enable_recaptcha` | checkbox | `false` | Enable CAPTCHA on registration and contact forms. |
| CAPTCHA Site Key | `arshid6social_recaptcha_site_key` | text | — | Public site key from reCAPTCHA or Cloudflare Turnstile. |
| CAPTCHA Secret Key | `arshid6social_recaptcha_secret_key` | password | — | Secret key from reCAPTCHA or Cloudflare Turnstile. |
| Moderate New Members | `arshid6social_new_member_moderation` | checkbox | `false` | Hold new members for admin approval before they can post. |
| Auto-Suspend Threshold | `arshid6social_auto_suspend_threshold` | number | `5` | Number of reports before a user is auto-suspended. `0` = disabled. |
| Banned Words | `arshid6social_banned_words` | textarea | — | One word or phrase per line. Matched content will be blocked. |
| Rate Limit: Posts | `arshid6social_rate_limit_posts` | number | `10` | Max activity posts a user can create per hour (1–500). |
| Rate Limit: Messages | `arshid6social_rate_limit_messages` | number | `20` | Max private messages a user can send per hour (1–500). |
| Rate Limit: Friend Requests | `arshid6social_rate_limit_friends` | number | `50` | Max friend requests a user can send per hour (1–500). |

#### User Blocking (sub-section)

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable Block System | `arshid6social_blocking_enabled` | checkbox | `true` | Allow members to block other members. |
| Allow Block Reasons | `arshid6social_blocking_show_reason` | checkbox | `true` | Show an optional reason field when blocking (private, visible only to the blocker). |

#### Reporting (sub-section)

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Report Reasons | `arshid6social_report_reasons` | textarea | *(see defaults)* | One reason per line. Shown to users when reporting a profile, post, or group. |
| Suspension Reasons | `arshid6social_suspend_reasons` | textarea | *(see defaults)* | One reason per line. Shown to admins when suspending a user. |
| Allow File Attachments in Reports | `arshid6social_report_allow_attachments` | checkbox | `false` | Allow users to attach a screenshot when submitting a report (images only). |

**Default Report Reasons:** Spam, Harassment or bullying, Hate speech, Inappropriate content, False information, Impersonation, Other

**Default Suspension Reasons:** Spam activity, Harassment, Hate speech or discrimination, Inappropriate content, Multiple violations, Violation of community guidelines, Other

---

### Tab: Emails

Email templates can be overridden by placing them in your theme at `/social-network/emails/`.

| Template | Override Path |
|---|---|
| New Friendship Request | `{theme}/social-network/emails/new-friendship-request.php` |
| New Message | `{theme}/social-network/emails/new-message.php` |
| Activity Mention | `{theme}/social-network/emails/activity-mention.php` |

No configurable options in this tab — templates are overridden via the filesystem.

---

### Tab: Appearance

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Primary Colour | `arshid6social_primary_color` | color | `#2563eb` | Accent color used throughout the plugin UI. |
| Dark Mode | `arshid6social_dark_mode` | select | `auto` | `off` = always light, `auto` = follow system preference, `on` = always dark. |

---

### Tab: Permalinks

After saving, rewrite rules are flushed automatically. No need to visit Settings → Permalinks.

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Tag Base | `arshid6social_permalink_tag_base` | text | `tag` | URL prefix for hashtag archive pages (e.g. `tag` → `/tag/php/`). Lowercase letters, digits, hyphens only. |
| Activity Base | `arshid6social_permalink_activity_base` | text | `activity` | URL prefix for single activity pages (e.g. `activity` → `/activity/123/`). |
| Activity ID Format | `arshid6social_activity_uid_enabled` | checkbox | `false` | Use a 13-character hex unique ID instead of a numeric ID in activity URLs (e.g. `/activity/64c3f4a2b1e8f/`). Numeric links to older posts continue to work. |

---

### Tab: Engagement

Accessible at **Settings → Engagement**. Each feature can be toggled independently. Disabled features unload all their hooks, REST routes, and assets.

#### Hashtags

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable Hashtags | `arshid6social_eng_hashtags` | checkbox | `false` | Auto-link hashtags in posts, create archive pages, and trending feed. |
| Banned Hashtags | `arshid6social_eng_hashtag_banned` | textarea | — | One hashtag per line. Banned hashtags cannot be used or followed. |

#### Tag Friends / @Mentions

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable Tag Friends | `arshid6social_eng_tag_friends` | checkbox | `false` | Enable @mention autocomplete and photo tagging. |
| Allow Photo Hotspot Tagging | `arshid6social_eng_tag_photo_tags` | checkbox | `false` | Allow users to tag others at specific spots on an image. |
| Require Tag Approval | `arshid6social_eng_tag_review` | checkbox | `false` | A tag must be approved by the tagged person before appearing on their profile. |
| Default Taggability | `arshid6social_eng_tag_privacy` | select | `everyone` | Who can tag a user by default: `everyone`, `friends` (friends only), `nobody`. |

#### Bookmarks

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable Bookmarks | `arshid6social_eng_bookmarks` | checkbox | `false` | Allow members to save posts to a bookmark list. |
| Allow Collections / Folders | `arshid6social_eng_bookmark_collections` | checkbox | `true` | Allow members to organise bookmarks into named collections. |

#### Sticky Posts

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable Sticky Posts | `arshid6social_eng_sticky_posts` | checkbox | `false` | Allow admins/mods to pin posts to the top of the feed. |
| Allow Multiple Sticky Posts | `arshid6social_eng_sticky_multiple` | checkbox | `false` | Allow more than one site-wide sticky post to be active at once. |

#### Share / Repost

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable Share / Repost | `arshid6social_eng_share_posts` | checkbox | `false` | Allow members to repost activity or share to a private message. |
| External Share Buttons | `arshid6social_eng_share_external` | checkbox | `true` | Show quick external share buttons (X, Facebook, WhatsApp, Telegram) on posts. |

#### Polls

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable Polls | `arshid6social_eng_polls` | checkbox | `false` | Add a poll creator to the activity composer. |
| Enable Advanced Polls | `arshid6social_eng_advanced_polls` | checkbox | `false` | Unlocks image polls, quiz mode, ranked-choice voting, and CSV export. |
| Max Options Per Poll | `arshid6social_eng_polls_max_options` | number | `10` | Maximum number of choices allowed per poll (2–50). |
| Allow Voter Suggestions | `arshid6social_eng_polls_allow_voter_suggest` | checkbox | `false` | Let voters suggest new options (goes to the moderation queue). |

#### GIFs in Comments & Messages

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable GIF Picker | `arshid6social_eng_comments_gifs` | checkbox | `false` | Add a GIPHY-powered GIF picker to comment and message inputs. |
| GIPHY API Key | `arshid6social_eng_giphy_api_key` | text | — | Your GIPHY API key. Required for GIF search to work. |
| Cache GIF URLs Locally | `arshid6social_eng_gif_cache` | checkbox | `false` | Store GIPHY response URLs in a local transient to save bandwidth. |

#### Comment Attachments

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Allow Comment Attachments | `arshid6social_eng_comments_attachments` | checkbox | `false` | Allow members to attach files to comments. |
| Max File Size | `arshid6social_eng_comment_att_max_mb` | number (MB) | `5` | Maximum size per comment attachment in MB (1–100). |
| Allowed Types | `arshid6social_eng_comment_att_types` | checkboxes | `[image]` | Allowed file types: `image` (JPEG, PNG, GIF, WebP), `document` (PDF). |

#### Message Attachments

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Allow Message Attachments | `arshid6social_eng_messages_attachments` | checkbox | `false` | Allow members to attach files to private messages. |
| Max File Size | `arshid6social_eng_msg_att_max_mb` | number (MB) | `10` | Maximum size per message attachment in MB (1–100). |
| Allowed Types | `arshid6social_eng_msg_att_types` | checkboxes | `[image, audio]` | Allowed file types: `image`, `audio` (voice notes / audio), `document` (PDF). |

#### External Social Sharing

Let visitors share activity posts to 80+ external networks.

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable External Social Sharing | `arshid6social_eng_social_share_external` | checkbox | `false` | Show a share modal with buttons for external social networks. |
| Networks | `arshid6social_eng_social_share_networks` | checkboxes | *(default list)* | Which networks to display. Drag to reorder within the admin UI. |
| Network Order | `arshid6social_eng_social_share_network_order` | hidden (comma-separated) | — | Saved drag-and-drop order of networks (managed via the UI). |
| Button Position | `arshid6social_eng_social_share_position` | select | `bottom` | `bottom` = in the post actions bar, `top` = above post content, `floating` = fixed on screen. |
| Show On Pages | `arshid6social_eng_social_share_pages` | checkboxes | `[feed, single, profile, group]` | Pages where sharing buttons appear: Activity Feed, Single Activity, Member Profiles, Group Pages. |
| Button Style | `arshid6social_eng_social_share_style` | select | `icon_text` | `icon_text` = icon + label, `icon_only` = icon only, `text_only` = text only. |
| Visible Networks Before "More…" | `arshid6social_eng_social_share_max_visible` | number | `8` | How many networks are shown before collapsing into a "More" button. `0` = show all. |
| Native Share on Mobile | `arshid6social_eng_social_share_native` | checkbox | `true` | Use the native iOS/Android share sheet instead of the modal on mobile devices. |

---

### Tab: Marketplace

#### General

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Enable Marketplace | `arshid6social_marketplace_enabled` | checkbox | `false` | Activate the Marketplace module. Disabling removes all hooks, assets, REST routes, and cron jobs. |
| Marketplace Slug | `arshid6social_marketplace_slug` | text | `marketplace` | URL slug for the Marketplace page (e.g. `marketplace` → `/marketplace/`). |

#### Social Sharing

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| External Social Sharing | `arshid6social_marketplace_social_share` | checkbox | `true` | Show social sharing buttons on marketplace listings. Requires External Social Sharing to be enabled in the Engagement tab. |

#### Categories

Hierarchical listing categories managed through an inline AJAX UI (add, edit, delete, drag to reorder). Stored in the `{prefix}arshid6social_categories` database table — not a settings option.

#### Currency

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Currency Symbol | `arshid6social_marketplace_currency_symbol` | text | `$` | Symbol shown next to prices (e.g. `$`, `€`, `£`, `﷼`). Max 5 characters. |
| Symbol Position | `arshid6social_marketplace_currency_position` | select | `before` | `before` = `$100`, `after` = `100$`. |
| Decimal Places | `arshid6social_marketplace_currency_decimals` | number | `2` | Number of decimal digits in displayed prices (0–4). |
| Thousands Separator | `arshid6social_marketplace_currency_thousands` | text | `,` | Character used as a thousands separator (e.g. `,` or `.`). Leave blank to omit. |

#### Listings

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Max Photos Per Listing | `arshid6social_marketplace_max_photos` | number | `10` | Maximum number of photos a seller can upload per listing (1–30). |
| Max Photo Size | `arshid6social_marketplace_max_photo_size_mb` | number (MB) | `5` | Maximum size per listing photo in MB (1–50). |
| Listing Expiry | `arshid6social_marketplace_expiry_days` | number | `30` | Auto-archive listings after this many days. `0` = never expire. |

#### Moderation

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Moderation Mode | `arshid6social_marketplace_moderation` | select | `auto` | `auto` = listings go live immediately; `manual` = admin must approve before listing is visible. |
| Require Verified Account | `arshid6social_marketplace_require_verified` | checkbox | `false` | Only users with a verified badge can create listings. |
| Auto-hide After N Reports | `arshid6social_marketplace_auto_hide_threshold` | number | `3` | Set a listing to "pending review" after this many reports. `0` = disabled. |
| Banned Words / Phrases | `arshid6social_marketplace_banned_words` | textarea | — | One word or phrase per line. Listings matching these are held for moderation. |

#### Access & Limits

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Allow Guests to Browse | `arshid6social_marketplace_allow_guests` | checkbox | `true` | Logged-out visitors can view listings. Contacting a seller always requires login. |
| Max Active Listings Per User | `arshid6social_marketplace_max_active_listings` | number | `20` | Maximum number of active listings a single user can have at once. |
| Max New Listings Per Day | `arshid6social_marketplace_daily_new_listings` | number | `5` | Maximum new listings a user can create per day. |

#### Policy & Safety Content

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Safety Tips | `arshid6social_marketplace_safety_tips` | textarea | — | Shown to buyers before their first message to a seller. Plain text only. |
| Prohibited Items Policy | `arshid6social_marketplace_prohibited_policy` | textarea | — | Shown (with a required checkbox) at the start of the Create Listing wizard. |

#### Homepage Placement

| Option | Key | Type | Default | Description |
|---|---|---|---|---|
| Set Marketplace as Site Landing Page | `arshid6social_marketplace_as_homepage` | checkbox | `false` | Use the Marketplace page as the front page. Reversible — uncheck to restore the previous front page. |

> **Payment & Liability Notice:** This plugin does NOT process, hold, or facilitate payments. All transactions are arranged directly between buyer and seller via private messages (peer-to-peer). The plugin operator assumes no liability for any transaction.

---

## Frequently Asked Questions

**Can I disable specific features?**
Yes. Go to **Social Network → Settings → Components** and toggle each feature on or off.

**How do I override a template?**
Copy the template file from `social-network-6/templates/` into your theme at `{your-theme}/social-network/` with the same relative path.

**Is it compatible with BuddyPress?**
6Arshid Social Community is an independent plugin and does not require BuddyPress. A data migration tool from BuddyPress is included in the admin Tools page.

**Does it work in RTL?**
Yes. Persian and Arabic are fully supported with a dedicated `rtl.css` and Jalali calendar date formatting.

**How do I add custom profile fields?**
Developers can use the `arshid6social_xprofile_groups` and `arshid6social_xprofile_fields` tables directly or via the provided PHP API. An admin UI for field management is planned for a future release.

**Can I extend it with my own components?**
Yes. Hook into `arshid6social_loaded` to register custom components, and use `arshid6social_settings_tabs` to add your own settings tab.

**What shortcodes are available?**
`[arshid6social_activity]`, `[arshid6social_members]`, `[arshid6social_groups]`, `[arshid6social_messages]`, `[arshid6social_notifications]`, `[arshid6social_profile]`, `[arshid6social_login_form]`, `[arshid6social_register_form]`, `[sn_bookmarks]`, `[sn_trending_hashtags]`

**Is the REST API available to external apps?**
Yes. The REST API at `/wp-json/arshid6social/v1/` covers activity, members, friends, groups, messages, notifications, bookmarks, hashtags, polls, tags, sharing, sticky posts, and attachments.

---

## Screenshots

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

---

## Changelog

### 1.0.0

- Initial release

---

## Upgrade Notice

### 1.0.0

Initial release. No upgrade path required.

---

## Third-Party Notices

### Bootstrap Icons

This plugin bundles icon SVG data from [Bootstrap Icons](https://icons.getbootstrap.com/) v1.11.3.

**Copyright** © The Bootstrap Authors
**License:** MIT — https://opensource.org/licenses/MIT

The icon data is pre-processed at build time into `assets/icons/bootstrap-icons.json` via `build/download-bootstrap-icons.php` and committed to the repository so no runtime download is required.


## Developers

<table>
  <tr>
    <td align="center">
      <a href="https://github.com/6arshid">
        <img src="https://github.com/6arshid.png" width="80" height="80" style="border-radius:50%" alt="6arshid"/><br/>
        <sub><b>6arshid</b></sub>
      </a>
    </td>
    <td align="center">
      <a href="https://github.com/hassantafreshi">
        <img src="https://github.com/hassantafreshi.png" width="80" height="80" style="border-radius:50%" alt="hassantafreshi"/><br/>
        <sub><b>Hassan Tafreshi</b></sub>
      </a>
    </td>
    <td align="center">
      <a href="https://github.com/aminkhadivar">
        <img src="https://github.com/aminkhadivar.png" width="80" height="80" style="border-radius:50%" alt="aminkhadivar"/><br/>
        <sub><b>Amin Khadivar</b></sub>
      </a>
    </td>
  </tr>
</table>