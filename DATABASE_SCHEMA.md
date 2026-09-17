# Database schema

40 tables, MySQL 5.7+/MariaDB 10.3+ (`utf8mb4` / `utf8mb4_unicode_ci` throughout so
Gujarati, Hindi and emoji all store and sort correctly). The same migrations also run
on SQLite, which is what lets the test suite and a local copy run without a database
server — every DDL statement is generated for both dialects from one definition in
`app/Core/Blueprint.php`.

Conventions used everywhere:

| Convention | Applied as |
|---|---|
| Primary key | `id` — `BIGINT UNSIGNED AUTO_INCREMENT` |
| Foreign keys | `<entity>_id`, with a real `FOREIGN KEY` and an explicit `ON DELETE` rule |
| Timestamps | `created_at`, `updated_at` (`TIMESTAMP NULL`), written by the application, not by `ON UPDATE` |
| Soft delete | `deleted_at TIMESTAMP NULL` on user-facing content; every read filters `deleted_at IS NULL` |
| JSON columns | stored as `JSON`/`LONGTEXT`, decoded by the repository (`$jsonColumns`) so callers see arrays |
| Money/limits | integers (bytes, counts, percentages) — no floats for anything that must be exact |
| Booleans | `TINYINT(1)` with an explicit default |

Prefixing is supported: everything goes through `Database::table()`, so an install
into a shared database can use a prefix such as `sk_` without touching a query.

---

## Authentication and access control

### `roles`
| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| slug | varchar(60) | unique — `super-admin`, `admin`, `editor`, `user` |
| name | varchar(120) | |
| description | varchar(300) | |
| level | int | higher outranks lower; used for "can this user act on that user" |
| is_system | tinyint | system roles cannot be deleted |
| created_at / updated_at | timestamp | |

### `permissions`
`slug` (unique, e.g. `templates.edit`), `name`, `group_name` (for the admin matrix),
`description`, timestamps. 51 permissions are seeded.

### `role_permissions`
Composite primary key `(role_id, permission_id)`, both cascading. The super-admin role
is not listed here at all — it is granted everything in code, so a permission added by
a future update is never accidentally withheld.

### `users`
| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| name | varchar(120) | |
| email | varchar(191) | unique |
| email_verified_at | timestamp | null until verified |
| phone | varchar(20) | normalised to digits |
| password | varchar(255) | `password_hash()` output, bcrypt/argon2 depending on PHP |
| remember_token | varchar(100) | selector/validator pair, hashed |
| role_id | bigint | → `roles.id` (restrict) |
| plan_id | bigint | → `plans.id` (set null) |
| avatar | varchar(255) | relative upload path |
| locale | varchar(5) | `gu`, `hi`, `en` |
| city | varchar(80) | |
| status | enum | `active`, `pending`, `suspended` |
| storage_used | bigint | bytes, kept current on upload and delete |
| invitation_count | int | denormalised counter for the admin list |
| last_login_at / last_login_ip_hash | timestamp / varchar(32) | the address is hashed, never stored raw |
| verify_token, reset_token_hash | varchar | single-use, hashed |
| created_at / updated_at / deleted_at | timestamp | |

Indexes: `email` (unique), `role_id`, `status`, `created_at`, `deleted_at`.

### `user_roles`
Composite `(user_id, role_id)` for the rare case of a second role; the primary role
stays on `users.role_id` so the common lookup is one join-free read. A second role is
additive: `Auth::permissions()` reads the union of the primary role and every row here,
so an extra role only ever grants. Rows are added directly in the database — the admin
screens assign the primary role only.

### `password_resets`
`selector` (unique), `validator_hash`, `user_id`, `email`, `expires_at`, `ip_hash`,
`used_at`. The selector goes in the URL, the validator is hashed — a stolen database
row cannot be turned back into a working link.

### `auth_otp_codes`
One-time login codes for the optional second factor: `user_id`, `purpose`, `channel`,
`code_hash` (a password hash, never the code), `sent_to`, `attempts`, `ip_hash`,
`expires_at`, `consumed_at`. Indexed on `(user_id, purpose, expires_at)`. The attempt
counter is on the row rather than in the session, so a new session cannot be used to
get another five guesses. `users.two_factor_enabled` is the per-user opt-in.

### `sessions`
`id` (the session id), `user_id`, `ip_hash`, `user_agent`, `payload`, `last_activity`.
Used when the database session driver is enabled; the file driver is the default.

### `api_tokens`
`user_id`, `name`, `token_hash` (unique), `abilities` (JSON), `last_used_at`,
`expires_at`. Only the hash is stored; the plaintext token is shown once at creation.

### `plans`
`slug` (unique), `name`, `price`, `currency`, `interval`, `limits` (JSON),
`features` (JSON), `is_default`, `is_active`, `sort_order`. Seeded with a free default
plan and a placeholder paid plan. **Nothing in the application enforces a paid limit
today** — monetization is wired but switched off, as specified.

---

## Taxonomy

### `categories`
`slug` (unique), `name`, `name_gu`, `name_hi`, `description`, `icon`, `cover_image`,
`template_count` (denormalised), `sort_order`, `is_active`, `meta_title`,
`meta_description`, timestamps, `deleted_at`. Five are seeded: Wedding, Religious &
Pooja, Business & Opening, Birthday & Anniversary, Party & Social.

### `subcategories`
As above plus `category_id` (→ `categories.id`, cascade) and `theme_tags` (JSON).
68 are seeded, covering the Gujarati and wider Indian occasion list (Lagna, Sagai,
Mameru, Simant, Garba, Satyanarayan Katha, Vastu Pooja, Dukan Opening, Munjan and so on).

---

## Templates

### `templates` (48 columns)
The heart of the "thousands of templates without hardcoding" design: a template is a
row, not a file.

| Group | Columns |
|---|---|
| Identity | `code` (unique), `slug` (unique), `name`, `description` |
| Taxonomy | `category_id`, `subcategory_id`, `tags` (JSON), `search_keywords` |
| Rendering | `layout_key` (one of ten PHP renderers), `type` (`static`, `kankotri`, `multi_page`, `animated`, `three_d`, `interactive`, `video`), `page_count`, `orientation` |
| Design | `theme` (JSON: every design token), `palette`, `font_pair`, `color_primary`, `color_secondary`, `color_background`, `font_heading`, `font_body` |
| Custom | `custom_html`, `custom_css`, `custom_js` — optional, sanitised on save and again on render |
| Capability flags | `supports_music`, `supports_gallery`, `supports_countdown`, `supports_rsvp`, `supports_map`, `has_animation` |
| Media | `thumbnail`, `preview_images` (JSON) |
| Catalogue | `language` (`gu`/`hi`/`en`/`multi`), `is_active`, `is_featured`, `is_premium`, `sort_order`, `use_count`, `view_count`, `rating`, `rating_count` |
| Provenance | `generated_by` (marks generator output so only it can be bulk-removed), `created_by`, `updated_by` |
| SEO | `meta_title`, `meta_description` |
| Audit | `created_at`, `updated_at`, `deleted_at` |

Indexes: `slug`/`code` unique; `(is_active, category_id, sort_order)` for the gallery;
`(is_active, is_featured)`; `language`; `use_count`; plus a `FULLTEXT` index
`ft_tpl_search (name, description, search_keywords)` used by search on MySQL. SQLite
falls back to `LIKE`, which the repository handles transparently.

### `template_fields` (21 columns)
What the builder renders for a given template, and what validation each value gets.

`template_id`, `field_key`, `label`, `label_gu`, `label_hi`,
`type` (one of 20: text, textarea, richtext, date, time, datetime, number, image,
gallery, phone, email, url, location, color, font, select, multiselect, checkbox,
social, music), `section` (main, people, schedule, venue, message, contact, media),
`placeholder`, `help_text`, `default_value`, `options` (JSON), `validation`,
`max_length`, `is_required`, `is_editable`, `is_visible`, `is_ai_generatable`,
`sort_order`, timestamps. Unique on `(template_id, field_key)`.

### `template_components`, `template_assets`
Optional per-template building blocks (`page`, `section`, `heading`, `names`,
`divider`, `image`, …) and attached files. Present for bespoke templates that need
more than the layout's own structure.

---

## Invitations

### `invitations` (35 columns)
`user_id`, `template_id`, `category_id`, `subcategory_id`, `title`,
`slug` (unique — the public URL), `short_code` (unique — the `/i/XXXX` QR target),
`status` (`draft`, `published`, `unpublished`, `archived`), `language`,
`event_date`, `event_time`, `event_at` (kept in sync from the fields, so countdown and
sorting need one column), `theme_overrides` (JSON, validated against a whitelist),
`settings` (JSON booleans: countdown, RSVP, gallery, share, QR, music autoplay,
skip animation, watermark), `password_hash` (optional passphrase),
`wizard_step`, `view_count`, `unique_view_count`, `share_count`, `download_count`,
`rsvp_count`, `last_viewed_at`, `published_at`, `expires_at`, `qr_path`,
`meta_title`, `meta_description`, `og_image`, timestamps, `deleted_at`.

Indexes: `slug` and `short_code` unique; `(user_id, status, updated_at)` for the
dashboard; `(status, event_at)` for reminders and the sitemap; `deleted_at`.

### `invitation_data`
`invitation_id`, `field_key`, `value` (text), `value_json`, timestamps. Unique on
`(invitation_id, field_key)` and written with an upsert, so saving the builder twice
cannot duplicate a value. Storing content as rows rather than columns is what lets a
template introduce a new field without a migration.

### `invitation_sections`
`invitation_id`, `section_key`, `is_visible`, `sort_order`, `title_override`,
timestamps. Unique on `(invitation_id, section_key)`.

### `invitation_photos`
`invitation_id`, `path`, `thumb_path`, `webp_path`, `caption`, `role` (`hero`/`gallery`),
`width`, `height`, `size`, `sort_order`, timestamps.

### `invitation_music`
`invitation_id` (unique — one track per card), `path`, `title`, `source`
(`upload`/`library`), `autoplay`, `loop_track`, `volume`, `size`, timestamps.

---

## Engagement and analytics

Deliberately minimal: enough to answer "how is my invitation doing", with nothing that
identifies a guest.

### `invitation_views`
`invitation_id`, `visitor_hash` (HMAC-SHA256 of address+user agent+invitation id,
truncated to 32 chars — not reversible and different for every invitation),
`device_type`, `browser`, `os`, `referrer_host`, `viewed_at`.
**There is no IP address column.** Index `(invitation_id, viewed_at)`.

### `invitation_shares`, `invitation_downloads`
`invitation_id`, `channel` / `format`, `visitor_hash`, `created_at`.

### `invitation_daily_stats`
`invitation_id`, `stat_date`, `views`, `unique_views`, `shares`, `downloads`,
`qr_scans`, `rsvps`, timestamps. Unique on `(invitation_id, stat_date)`. The cron
aggregates raw rows into this table and then prunes them, so a card with a million
views still charts instantly and the raw tables stay small.

### `rsvp`
`invitation_id`, `name`, `phone`, `email`, `response` (`yes`/`maybe`/`no`), `guests`,
`message`, `visitor_hash`, `ip_hash`, `is_read`, timestamps. Indexes
`(invitation_id, response)` and `(invitation_id, created_at)`.

---

## System

| Table | Purpose |
|---|---|
| `settings` | `group_name`, `setting_key` (unique), `setting_value`, `value_type`, `is_public`, `label`, `description`, `updated_by`. Secrets stored here are encrypted (`value_type = encrypted`). |
| `feature_flags` | `flag_key` (unique), `name`, `description`, `is_enabled`, `rollout` (0–100), `meta`. Rollout buckets users deterministically by id. |
| `media` | The shared library: `path`, `thumb_path`, `webp_path`, `original_name`, `mime`, `extension`, `size`, `width`, `height`, `kind`, `alt_text`, `title`, `usage_count`, `is_public`, `is_library`, `content_hash`, `folder`, `user_id`. |
| `fonts` | `slug` (unique), `name`, `family`, `script` (latin/gujarati/devanagari/multi), `path`, `format`, `weight`, `pdf_capable`, `is_active`, `is_default`, `sort_order`, licence fields. |
| `pages` | CMS pages: `slug` (unique), `title`, `content`, `excerpt`, `status`, `locale`, `show_in_footer`, `show_in_header`, `sort_order`, SEO fields. |
| `ai_settings` | One row: `provider`, `model`, `endpoint`, `api_key` (encrypted), `temperature`, `top_p`, `max_tokens`, `timeout`, `daily_limit`, `system_prompt`, `is_enabled`, `allow_recommendations`. |
| `ai_logs` | `action`, `model`, `locale`, `prompt_tokens`, `completion_tokens`, `total_tokens`, `latency_ms`, `status`, `error_message`. **No prompt or response text is stored.** |
| `audit_logs` | `user_id`, `actor_name`, `action`, `entity_type`, `entity_id`, `description`, `changes` (JSON), `ip_hash`, `user_agent`, `created_at`. Append-only. |
| `notifications` | `user_id` (null = all admins), `type`, `title`, `body`, `url`, `icon`, `is_read`, `read_at`. |
| `backups` | `name`, `type` (`full`/`database`/`files`/`update`), `path`, `size`, `table_count`, `file_count`, `status`, `checksum` (SHA-256), `note`, `app_version`, `created_by`, `started_at`, `completed_at`, `error_message`. |
| `update_logs` | `from_version`, `to_version`, `repository`, `branch`, `commit_sha`, `commit_message`, `commit_author`, `status` (`checking` → … → `success`/`failed`/`rolled_back`), `step`, `backup_path`, `database_backup_path`, `changed_files`, `migration_result`, `health_result`, `steps_log`, `duration_ms`, `error_message`, `initiated_by`, `started_at`, `finished_at`. |
| `system_health_logs` | `status`, `php_version`, `db_version`, `app_version`, `disk_free`, `disk_total`, `results` (JSON), `note`, `context`, `checked_at`. |
| `cron_runs` | `task`, `status` (`success`/`failed`/`skipped`), `output`, `duration_ms`, `ran_at`. |
| `migrations` | `migration` (unique), `batch`, `checksum`, `duration_ms`, `ran_at`. |

---

## Migrations

```
database/migrations/
  2026_01_01_000001_create_auth_tables.php          roles, permissions, plans, users, tokens
  2026_01_01_000002_create_taxonomy_tables.php      categories, subcategories
  2026_01_01_000003_create_template_tables.php      templates, fields, components, assets
  2026_01_01_000004_create_invitation_tables.php    invitations, data, sections, photos, music
  2026_01_01_000005_create_engagement_tables.php    views, shares, downloads, daily stats, rsvp
  2026_01_01_000006_create_system_tables.php        settings, flags, media, fonts, pages, ai, audit,
                                                    notifications, backups, updates, health, cron
  2026_01_02_000001_add_update_database_backup_path.php
  2026_01_02_000002_create_auth_otp_codes.php       second-factor codes, users.two_factor_enabled
```

Each file returns an anonymous class with `up(Database)` and `down(Database)`. The
runner records the file's SHA-256 so an edited migration is visible after the fact.
Migrations are idempotent where they can be (`Schema::addColumn()` and
`Schema::addIndex()` no-op if the column or index already exists), because an update
may re-run them on a database that a previous attempt partly migrated.

```bash
php bin/console migrate            # apply what is pending
php bin/console migrate:status     # applied vs pending
php bin/console migrate:rollback   # roll back the last batch
```

The update system runs `migrate` itself after deploying files, and restores the
pre-update database dump if anything after that fails.
