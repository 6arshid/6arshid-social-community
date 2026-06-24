# 6Arshid Social Community

**Contributors:** 6arshid, hassantafreshi, aminkhadivar
**Requires at least:** WordPress 6.5 · PHP 8.1
**License:** MIT

A complete, secure, responsive, multilingual social network plugin for WordPress — no BuddyPress required.

---

## Features

### Member Profiles
- Extended profile fields (text, select, checkbox, date, URL, social links)
- Field visibility controls (public / friends only / only me)
- Avatar & cover photo upload with EXIF stripping
- Profile completion progress, verified badge, GDPR export/erasure

### Activity Stream
- Composer with text, images, GIFs, emoji, link previews
- Privacy levels per post (public / friends / private)
- Inline edit & delete, infinite scroll, single activity permalink
- @mentions, hashtags, 14 reaction types
- Threaded comments with likes, attachments, and GIFs
- Share, bookmark, sticky/pinned posts, Akismet spam filter

### Polls
- Multi-option polls in the composer with live results
- Quiz mode, expiration, voter suggestions, import/export

### Groups
- Public, private, and hidden groups
- Roles: admin, moderator, member
- Join requests, invitations, hierarchical (parent/child) groups

### Friends & Follow
- Mutual friend requests and one-way follow
- Block/unblock, friend suggestions

### Private Messaging
- One-to-one and group conversations
- Attachments, read receipts, real-time updates (Heartbeat API)

### Notifications
- On-site notification centre with unread counter
- Email notifications with daily/weekly digest
- Per-user preferences by notification type

### Hashtags
- Auto-link hashtags, archive pages, trending feed
- Follow/unfollow hashtags, autocomplete in composer

### Bookmarks
- Save posts to collections, search & filter, infinite scroll

### Social Embeds
- Auto-embed URLs from 19 platforms (YouTube, Spotify, TikTok, Instagram, X, Aparat, …)
- Lazy load (privacy-first), tracking param stripping, OG fallback

### Marketplace
- Peer-to-peer listings with categories, photos, and expiry
- Moderation queue, banned words, guest browsing

### Monetization
- Creator subscriptions and pay-per-view posts via Stripe Connect
- Platform fee configuration, 13 currencies, test/live mode

### Moderation
- Report content (posts, comments, profiles, groups)
- Admin queue, auto-suspension, banned words, member audit log

### Developer & REST API
- Full REST API at `/wp-json/arshid6social/v1/`
- 50+ AJAX actions, action/filter hooks on every major operation
- Component-based — enable or disable each feature independently
- Template overrides, Gutenberg blocks, shortcodes

### Multilingual & RTL
- `.pot`, Persian (`fa_IR`), Danish (`da_DK`) included
- RTL support, Jalali calendar, compatible with WPML / Polylang / TranslatePress

### Security & Performance
- Nonces, capability checks, prepared statements, rate limiting
- Strict MIME validation, EXIF stripping, reCAPTCHA / Turnstile support
- Conditional asset loading, object cache, zero jQuery dependency

---

## Installation

1. Upload `social-network-6` to `/wp-content/plugins/`
2. Activate via **Plugins** in WordPress admin
3. Follow the setup wizard
4. Configure at **Social Network → Settings**

---

## Developers

| | | |
|---|---|---|
| [6arshid](https://github.com/6arshid) | [Hassan Tafreshi](https://github.com/hassantafreshi) | [Amin Khadivar](https://github.com/aminkhadivar) |
