# API v1

A small JSON API over the same services the web interface uses, so anything the
browser can do a script can do too. Base path: `/api/v1`.

Every response is an envelope:

```json
{ "success": true,  "message": "…", "data": { … }, "meta": { … } }
{ "success": false, "message": "…", "errors": { "field": ["why"] } }
```

`message` and `meta` appear only when there is something to say. `errors` is keyed by
field name, which is what a form needs to highlight the right input.

| Status | Meaning |
|---|---|
| 200 | Done |
| 401 | No credentials, or a token that is unknown, revoked or expired |
| 403 | Authenticated, but not allowed |
| 404 | No such resource — also returned for a resource you do not own |
| 419 | CSRF token missing or invalid (session-authenticated writes) |
| 422 | Validation failed; see `errors` |
| 429 | Rate limited; see `Retry-After` |
| 503 | Maintenance mode |

---

## Authentication

Two mechanisms, both handled by the `api` middleware:

**Bearer token** — for scripts and mobile apps. Nothing else is needed.

```bash
curl -H "Authorization: Bearer inv_de6672…" https://your-domain.com/api/v1/auth/me
```

**Session cookie** — for the application's own JavaScript. A session-authenticated
write must also send the CSRF token (`X-CSRF-Token` header or `_token` field), because
a cookie travels automatically and a token does not.

### `POST /auth/login`

```json
{ "email": "you@example.com", "password": "…", "device": "my script" }
```

```json
{
  "success": true,
  "message": "Signed in.",
  "data": {
    "token": "inv_de6672145e310e60bcbda23846141ce5e11e5091aa715ae6",
    "token_type": "Bearer",
    "expires_in": 2592000,
    "user": { "id": 1, "name": "Akshay Kanani", "email": "…",
              "role": "super-admin", "locale": "gu", "plan": "free", "invitations": 2 }
  }
}
```

The token is shown **once**; only its hash is stored. Throttled to 20 attempts per
10 minutes per address, and the same message is returned for a wrong password and an
unknown account.

An account with **two-step sign-in** enabled cannot obtain a token this way: the flow
needs the emailed code, which is a browser journey (`/login/verify`). Issue a named
token from **Profile** instead and use that.

### `POST /auth/register`
`name`, `email`, `password`, `password_confirmation`, optional `phone`, `locale`.
Returns the same payload as login when e-mail verification is off, otherwise a message
asking the user to confirm their address. Throttled to 10 per hour.

### `GET /auth/me`
The signed-in user. Nothing sensitive: no password hash, no tokens, no internal ids
beyond their own.

### `POST /auth/logout`
Revokes the presented token, or ends the session.

### `POST /auth/tokens`
`name` — issues an additional named token (CSRF required if you are using a session).

### `DELETE /auth/tokens/{id}`
Revokes one of your tokens.

---

## Public reads

No authentication. Rate limited to 120 requests per minute per address.

### `GET /categories`
Every active category with its subcategories.

```json
{"success":true,"data":[{"id":1,"name":"Wedding","name_gu":"લગ્ન","name_hi":"विवाह",
  "slug":"wedding","icon":"heart-fill","color":"#C8102E","templates":28,
  "subcategories":[{"id":1,"name":"Gujarati Wedding","slug":"gujarati-wedding","templates":12}]}]}
```

### `GET /templates`
The catalogue. Query parameters:

| Parameter | Values |
|---|---|
| `q` | free text, up to 80 characters (full-text on MySQL) |
| `category`, `subcategory` | slug |
| `language` | `gu`, `hi`, `en` |
| `type` | `static`, `kankotri`, `multi_page`, `animated`, `three_d`, `interactive`, `video` |
| `animated` | `1` |
| `premium` | `0` or `1` |
| `color` | `#RRGGBB` |
| `tag` | a tag |
| `sort` | `popular` (default), `latest`, `name`, `rating`, `featured` |
| `page`, `per_page` | `per_page` capped at 48 |

```json
{"success":true,
 "data":[{"id":1,"code":"KANK-001","name":"Classic Gujarati Kankotri",
   "slug":"classic-gujarati-kankotri-kank-001","type":"kankotri","language":"gu",
   "animated":false,"premium":false,"featured":true,"pages":1,"uses":8,
   "tags":["kankotri","gujarati","traditional","red"],
   "colors":{"primary":"#C8102E","secondary":"#F0B429","background":"#FFF8EE"},
   "preview_url":"https://…/templates/classic-gujarati-kankotri-kank-001/preview",
   "detail_url":"https://…/templates/classic-gujarati-kankotri-kank-001"}],
 "meta":{"page":1,"pages":3,"per_page":24,"total":51}}
```

### `GET /templates/{slug}`
One template, plus its editable field definitions — which is what you need to know
before creating an invitation from it:

```json
{"success":true,"data":{"id":1,"name":"…","layout":"classic-kankotri",
  "supports":{"music":true,"gallery":true,"countdown":true,"rsvp":true,"map":true},
  "fields":[{"key":"invocation","label":"Opening blessing","label_gu":"શુભ પંક્તિ",
             "label_hi":"शुभ पंक्ति","type":"text","section":"main","required":false,
             "placeholder":"॥ શુભ લગ્ન ॥","help":"A short line at the very top of the card.",
             "options":[],"max_length":80,"ai":true}]}}
```

### `POST /rsvp/{slug}`
A guest response to a published invitation. `name` (required), `response`
(`yes`/`maybe`/`no`), `guests`, `phone`, `email`, `message`. Throttled to 20 per hour
per address; a repeat from the same visitor updates their answer instead of adding a
second row.

---

## Invitations

Bearer token or session. Everything here is scoped to the signed-in user: another
user's id returns **404**, never 403.

### `GET /invitations`
`status` (`draft`, `published`, `unpublished`, `archived`), `q`, `page`, `per_page`.

```json
{"success":true,"data":[{"id":2,"title":"Rahul weds Priya","slug":"rahul-weds-priya",
  "short_code":"V7Y96W","status":"published","step":8,"template_id":1,"language":"gu",
  "event_at":"2027-12-25 11:30:00",
  "public_url":"https://…/invite/rahul-weds-priya","short_url":"https://…/i/V7Y96W",
  "pdf_url":"https://…/invite/rahul-weds-priya/pdf",
  "qr_url":"https://…/invite/rahul-weds-priya/qr.png",
  "stats":{"views":328,"unique":227,"shares":26,"downloads":20,"rsvps":4},
  "created_at":"…","updated_at":"…"}]}
```

### `GET /invitations/{id}`
As above plus `fields` (every saved value), `sections`, `photos` and `music`.

### `POST /invitations`
`template_id` (required), optional `title` and `fields` (an object of field keys). The
invitation is created as a draft; unknown field keys are ignored, and a field that
fails validation is reported in `errors` while the invitation is still created.

### `PUT /invitations/{id}`
`title`, `fields`, `theme` (checked against the override whitelist), `settings`,
`sections`. Validation follows the template's own field definitions, so a `date` field
must be a date and a `phone` field must be a phone number.

### `POST /invitations/{id}/publish`
Publishes, or returns **422** with `errors.missing` listing the required fields that
are still empty.

### `POST /invitations/{id}/preview`
Renders the card with unsaved values, returning the HTML. This is what the builder's
live preview uses, which is why the preview is always what the guest will see: one
renderer, server side.

### `DELETE /invitations/{id}`
Soft deletes. The row is retained with `deleted_at` set; the account-deletion flow
purges for good.

### `GET /invitations/{id}/rsvp`
The guest list, with `summary` (`total`, `yes`, `maybe`, `no`, `guests`).

---

## Analytics

### `GET /analytics`
Everything you own: `stats`, a daily `series` and your `top` invitations.

### `GET /analytics/{id}`
One invitation. `window` = `today`, `7d`, `30d` (default), `90d`, `all`. Returns
`totals`, `lifetime`, `series`, `devices`, `browsers`, `shares`, `referrers` and
`rsvp`.

Aggregates only. No visitor is identified, no address is stored, and the visitor hash
is salted per invitation so the same person is not correlatable across cards.

---

## AI

Available when a Gemini key is configured and the feature is on; otherwise these
return a clear message rather than an error page. Rate limited per user and per
address, with a configurable daily cap.

| Endpoint | Purpose |
|---|---|
| `POST /ai/wording` | Invitation wording from the facts you supply |
| `POST /ai/message` | A short welcome line |
| `POST /ai/whatsapp` | A WhatsApp-ready sharing message |
| `POST /ai/translate` | Translate approved wording into another language |
| `POST /ai/recommend` | Template suggestions for a brief |

```json
{ "facts": { "groom_name": "Rahul", "bride_name": "Priya",
             "event_date": "2027-12-25", "venue": "Dwarkadhish Mandir" },
  "locale": "gu", "tone": "traditional" }
```

**The model may only rephrase what you send.** Every response passes through a fact
guard that removes any sentence containing a date, time or number you did not supply;
if that leaves nothing, the call is reported as unusable rather than returning invented
text. Indic and Western digits are compared as equal, so a translated date survives.

`POST /ai/recommend` never lets the model invent a template: a deterministic scorer
builds a shortlist of real templates, and the model may only reorder it. `source` in
the response says which ranking you got (`rules` or `ai`), and `reason` explains it.

---

## Cron

### `GET /cron/run?token=…`
For hosts without cron access. The token is generated at install and shown in
**Admin → System → Scheduled tasks**. Prefer
`php bin/console cron:run` where you have a shell.

---

## Notes for clients

- **Times** are Asia/Kolkata unless a field says otherwise. `event_at` is
  `YYYY-MM-DD HH:MM:SS` local.
- **Text** is UTF-8 throughout; Gujarati and Hindi need no special handling.
- **Pagination** is `meta.page`, `meta.per_page`, `meta.total`, `meta.pages`.
- **Rate limits** return 429 with `Retry-After`. Back off; do not retry in a tight loop.
- **Idempotency:** `PUT /invitations/{id}` is safe to repeat. `POST /invitations` is
  not — it creates a new draft each time.
- **Errors** are always the envelope above, never an HTML page, for any request that
  asks for JSON (`Accept: application/json` or `X-Requested-With: XMLHttpRequest`).
