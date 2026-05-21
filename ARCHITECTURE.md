# 🏗️ SEVEN ROCK RADIO - ARQUITECTURA & ESTADO ACTUAL

## Diagrama de Aplicación

```
┌─────────────────────────────────────────────────────────────────┐
│                    SEVEN ROCK RADIO APP                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  FRONTEND (Public Site + Admin)                             ││
│  ├─────────────────────────────────────────────────────────────┤│
│  │                                                               ││
│  │  PUBLIC SITE                      │  ADMIN PANEL             ││
│  │  ──────────────────────────────────┼──────────────────────── ││
│  │  / (Home)                          │  /admin (Dashboard)     ││
│  │  /events                           │  /admin/events (CRUD)   ││
│  │  /discography                      │  /admin/albums (CRUD)   ││
│  │  /videos                           │  /admin/videos (CRUD)   ││
│  │  /gallery                          │  /admin/songs (CRUD)    ││
│  │  /blog                             │  /admin/posts (CRUD)    ││
│  │  /shop                             │  /admin/products (CRUD) ││
│  │  /contact                          │  /admin/gallery (CRUD)  ││
│  │  /player/popup                     │  /admin/settings (theme)││
│  │  + Radio Player                    │  /admin/audit-logs      ││
│  │                                    │  /admin/login           ││
│  │                                                               ││
│  │  Stack: Blade + Tailwind CSS 4 + Alpine.js                 ││
│  │  Assets: Vite 8.0 bundler                                   ││
│  │                                                               ││
│  └─────────────────────────────────────────────────────────────┘│
│                              ↓                                    │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  BACKEND (Laravel 13)                                        ││
│  ├─────────────────────────────────────────────────────────────┤│
│  │                                                               ││
│  │  HTTP ROUTES                      │  MIDDLEWARE              ││
│  │  ───────────────────────────────────────────────────────── ││
│  │  GET  /                          │  VerifyCsrfToken        ││
│  │  POST /admin/login               │  RequireAdmin           ││
│  │  POST /admin/logout              │  TrackAdminAuditTrail   ││
│  │  GET  /admin/dashboard           │  EncryptCookies         ││
│  │  CRUD /admin/events              │  SecurityHeaders (NEW)  ││
│  │  CRUD /admin/albums              │  throttle (webhooks)    ││
│  │  ... (8 more CRUD resources)     │                          ││
│  │                                  │                          ││
│  │  API ROUTES                       │                          ││
│  │  ──────────────────────────────  │                          ││
│  │  GET  /api/player/status          │                          ││
│  │  GET  /api/player/band-info       │                          ││
│  │  POST /api/radio/metadata         │                          ││
│  │                                                               ││
│  │  Controllers:                                               ││
│  │  ├─ SiteController (public pages)                          ││
│  │  ├─ Admin/*Controller (8 resources)                        ││
│  │  ├─ Api/PlayerStatusController                            ││
│  │  ├─ Api/RadioWebhookController                            ││
│  │  └─ Api/BandInfoController                                ││
│  │                                                               ││
│  └─────────────────────────────────────────────────────────────┘│
│                              ↓                                    │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  SERVICES & BUSINESS LOGIC                                   ││
│  ├─────────────────────────────────────────────────────────────┤│
│  │                                                               ││
│  │  RadioPlayerService .................. Reproduce state mgmt  ││
│  │  PlayerWarmupService ..................Metadata pre-loading  ││
│  │  AuditTrailService .....................Admin action logging ││
│  │  ArchiveOrgService .....................Archive.org sync     ││
│  │  ArchiveOrgPodcastService .............Podcast sync to API  ││
│  │  HeadlineTickerService .................Headline generation  ││
│  │  ProgramScheduleService ................Program scheduling   ││
│  │  BandInfoAggregator ...................Band metadata search  ││
│  │  ExternalHttp .........................Rate-limited HTTP calls││
│  │                                                               ││
│  └─────────────────────────────────────────────────────────────┘│
│                              ↓                                    │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  MODELS & DATABASE                                           ││
│  ├─────────────────────────────────────────────────────────────┤│
│  │                                                               ││
│  │  User                         │ AuditLog                      ││
│  │  Post                         │ PlayHistory                   ││
│  │  Album                        │ Notice                        ││
│  │  Event                        │ PostTaxonomy                  ││
│  │  Video                        │ ThemeSetting                  ││
│  │  Product                      │                              ││
│  │  GalleryImage                 │                              ││
│  │  Song                         │                              ││
│  │  BandProfile                  │                              ││
│  │  RadioProgram                 │                              ││
│  │  MasterProgram                │                              ││
│  │                                                               ││
│  │  Database: SQLite (dev) → MySQL/Postgres (prod)            ││
│  │  ORM: Eloquent                                              ││
│  │  Migrations: Yes               │  Seeders: Yes              ││
│  │                                                               ││
│  └─────────────────────────────────────────────────────────────┘│
│                              ↓                                    │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  EXTERNAL INTEGRATIONS                                       ││
│  ├─────────────────────────────────────────────────────────────┤│
│  │                                                               ││
│  │  RadioBoss API                                              ││
│  │  ├─ Live metadata webhook                                  ││
│  │  ├─ Stream URLs                                            ││
│  │  └─ FTP for uploads                                        ││
│  │                                                               ││
│  │  Archive.org                                               ││
│  │  ├─ Podcast sync                                           ││
│  │  └─ Audio storage                                          ││
│  │                                                               ││
│  │  External APIs (Enrichment)                                ││
│  │  ├─ Discogs (music metadata)                               ││
│  │  ├─ Last.fm (artist info)                                  ││
│  │  ├─ Genius (lyrics)                                        ││
│  │  └─ MusiXMatch (lyrics + metadata)                         ││
│  │                                                               ││
│  │  Google Fonts (dynamic typography)                         ││
│  │                                                               ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Stack Tecnológico

### Backend
```
Language:       PHP 8.3+
Framework:      Laravel 13.7
ORM:            Eloquent
Cache:          File (configurable to Redis)
Queue:          Database (configurable to Redis)
Database:       SQLite (dev), MySQL/Postgres (prod)
```

### Frontend
```
Templating:     Blade (server-side)
CSS:            Tailwind CSS 4.0
JS:             Alpine.js 3.15.12
Bundler:        Vite 8.0
Icons:          None (using text/unicode)
Font:           Google Fonts (dynamic)
```

### Tools & Services
```
Testing:        PHPUnit 12.5.12 (backend)
Linting:        Laravel Pint (PHP)
API Docs:       None (internal endpoints)
Auth:           Session-based (Laravel default)
File Storage:   Local disk (configurable to S3)
```

---

## Fluxo de Datos

### Página Pública (Ejemplo: Blog Post)

```
User Browser
    ↓
GET /{year}/{month}/{day}/{slug}
    ↓
SiteController::singlePost()
    ├─ Post::published()->where('slug', $slug)->first()
    ├─ WordPressContent::toRenderableBlocks($post->content)
    └─ view('pages.single-post', [...])
    ↓
Blade rendering
    ├─ Titulo: {{ $post['title'] }} (escaped)
    ├─ Contenido: {!! $block !!} (⚠️ potentially unsafe)
    ├─ CSS variables: --lucille-* (from ThemeSetting)
    └─ Alpine.js: Radio player logic
    ↓
HTML + Tailwind CSS + Alpine
    ↓
Browser render
```

### Admin Panel (Ejemplo: Crear Post)

```
Admin Browser
    ↓
GET /admin/login
    ↓
POST /admin/login
    ├─ Validate credentials
    ├─ Hash password comparison
    ├─ session()->regenerate()
    ├─ Log attempt (success/fail)
    └─ Redirect to dashboard
    ↓
GET /admin/posts/create
    ├─ RequireAdmin middleware ✅
    ├─ TrackAdminAuditTrail middleware
    └─ Render form
    ↓
POST /admin/posts
    ├─ RequireAdmin middleware ✅
    ├─ TrackAdminAuditTrail middleware
    ├─ Validate input
    ├─ Process image upload
    ├─ Save post to DB
    ├─ Log action (AuditTrailService)
    ├─ Sync taxonomies
    └─ Redirect with message
    ↓
GET /admin/posts
    └─ Show updated list
```

### Radio Webhook

```
RadioBoss Server
    ↓
POST /api/radio/metadata
    ├─ Validate key with hash_equals()
    ├─ Throttle: 30 requests/minute
    ├─ Parse metadata
    ├─ Find/create Song record
    ├─ Find/create Program record
    ├─ Store track in PlayerService
    ├─ Trigger PlayerWarmupService
    ├─ Save to PlayHistory
    ├─ Increment song play_count
    └─ Return JSON response
    ↓
Frontend (API call)
    └─ Update player UI
```

---

## Seguridad - Capas de Protección

```
┌────────────────────────────────────────────────────────────┐
│                    SECURITY LAYERS                         │
├────────────────────────────────────────────────────────────┤
│                                                              │
│  Layer 1: HTTP/TLS                                         │
│  ├─ HTTPS only (production)                               │
│  ├─ HSTS header ❌ (missing)                              │
│  └─ SSL certificate                                        │
│                                                              │
│  Layer 2: Request Validation                               │
│  ├─ CSRF token verification ✅                            │
│  ├─ HTTP method restrictions ✅                           │
│  ├─ Input validation ✅                                   │
│  └─ Rate limiting ⚠️ (webhooks only)                      │
│                                                              │
│  Layer 3: Authentication                                   │
│  ├─ Session-based auth ✅                                 │
│  ├─ Password hashing (bcrypt) ✅                          │
│  ├─ Session regeneration ✅                               │
│  ├─ HTTP-only cookies ✅                                  │
│  └─ SameSite=lax ✅                                        │
│                                                              │
│  Layer 4: Authorization                                    │
│  ├─ Admin middleware ✅                                    │
│  ├─ Role check (is_admin flag) ✅                         │
│  ├─ Policies/Gates ❌ (missing)                          │
│  └─ Resource ownership ⚠️ (not enforced)                  │
│                                                              │
│  Layer 5: Output Encoding                                  │
│  ├─ Blade {{ }} escaping ✅                               │
│  ├─ e() function ✅                                        │
│  ├─ {!! !!} (unescaped) ⚠️ (used carefully)              │
│  └─ DOMPurify ❌ (not used)                               │
│                                                              │
│  Layer 6: Query Security                                   │
│  ├─ Parameterized queries ✅                              │
│  ├─ ORM protection ✅                                      │
│  ├─ $fillable whitelist ✅                                │
│  └─ No raw SQL concat ✅                                  │
│                                                              │
│  Layer 7: Logging & Audit                                  │
│  ├─ Admin action logging ✅                               │
│  ├─ Failed login logging ✅                               │
│  ├─ IP tracking ✅                                         │
│  └─ Retention policy ❌ (missing)                         │
│                                                              │
│  Layer 8: Security Headers                                 │
│  ├─ CSP ❌ (missing)                                       │
│  ├─ X-Frame-Options ❌ (missing)                          │
│  ├─ X-Content-Type-Options ❌ (missing)                   │
│  └─ Referrer-Policy ❌ (missing)                          │
│                                                              │
│  Layer 9: Configuration                                    │
│  ├─ APP_DEBUG=false (prod) ✅                             │
│  ├─ APP_KEY set ✅                                        │
│  ├─ .env in .gitignore ✅                                 │
│  └─ Secrets not in code ✅                                │
│                                                              │
└────────────────────────────────────────────────────────────┘
```

---

## Componentes Críticos

### 1. Authentication (`RequireAdmin` Middleware)
```php
// ✅ Valida auth
if (! Auth::check()) {
    redirect to login
}

// ✅ Valida admin role
if (! Auth::user()?->hasAdminAccess()) {
    abort 403
}
```

### 2. Audit Trail (`AuditTrailService`)
```php
// Logs:
- Failed login attempts (email + IP)
- Successful logins
- HTTP exceptions
- Admin actions (CRUD)
```

### 3. Radio Integration (`RadioWebhookController`)
```php
// ✅ Webhook validation
hash_equals(config('player.webhook.key'), $validated['key'])

// ✅ Throttling
throttle:30,1 (30 per minute)

// ✅ Metadata processing
- Resolves song from metadata
- Stores play history
- Updates UI via API
```

### 4. Theme System (`ThemeSetting`)
```php
// Dynamic theme from database
- Colors (6 variables)
- Fonts (3 levels)
- Media (logo, backgrounds)
- Social links
- UI texts
```

---

## Database Schema (Key Tables)

```sql
-- Authentication
users
├─ id (PK)
├─ name
├─ email (unique)
├─ password (hashed)
├─ is_admin
└─ timestamps

-- Content
posts
├─ id (PK)
├─ title
├─ slug
├─ content (JSON array of blocks)
├─ featured_image
├─ published_at
├─ is_published
└─ timestamps

albums, events, videos, products (similar structure)

-- Relationships
post_taxonomy_post
├─ post_id (FK)
├─ post_taxonomy_id (FK)
└─ type (category|tag)

-- Logging
audit_logs
├─ id (PK)
├─ user_id (FK)
├─ action
├─ data (JSON)
├─ ip_address
└─ timestamps

-- Theme
theme_settings
├─ id (PK)
├─ key (unique)
├─ value (JSON)
└─ timestamps
```

---

## Puntos de Extensión

### Para Agregar Features

1. **New Content Type** (e.g., "Discounts")
   - Create `Discount` model
   - Create `DiscountController` (admin CRUD)
   - Add routes
   - Create views

2. **New External Integration** (e.g., Spotify)
   - Create service class
   - Add controller for webhook/callback
   - Update existing services if needed
   - Add config variables

3. **New Admin Feature** (e.g., Analytics)
   - Create `AnalyticsController`
   - Create queries/services
   - Add dashboard view
   - Add route under admin

---

## Deployment Checklist

- [ ] Verify `APP_DEBUG=false`
- [ ] Set `SESSION_ENCRYPT=true`
- [ ] Set `SESSION_SECURE_COOKIE=true` (HTTPS)
- [ ] Enable security headers middleware
- [ ] Configure SSL certificate
- [ ] Set up database backup
- [ ] Configure email (SMTP)
- [ ] Set storage disk (S3 if needed)
- [ ] Run database migrations
- [ ] Seed default theme settings
- [ ] Set up log rotation
- [ ] Configure monitoring (Sentry, etc.)
- [ ] Set up queue worker if using async jobs
- [ ] Configure Redis if using cache/queue

---

**Architecture Version**: 1.0
**Last Updated**: May 2026
**Maintainability**: ⭐⭐⭐⭐ (Good structure, minor improvements needed)
