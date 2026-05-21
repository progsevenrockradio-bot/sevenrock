# 🎯 QUICK REFERENCE - SEVEN ROCK RADIO AUDIT

## 📍 Ubicación de Archivos Auditados

### Documentación de Auditoría
- `AUDIT_FINDINGS.md` - Hallazgos detallados + fixes recomendados
- `ARCHITECTURE.md` - Arquitectura de la aplicación
- Session workspace files (ver abajo)

### Session Workspace (en tu computadora)
- `REPORTE_AUDITORIA_COMPLETO.md` - Reporte completo (19KB)
- `RESUMEN_AUDITORIA.md` - Resumen visual rápido (8KB)
- `plan.md` - Plan de implementación

---

## 🔴 Los 3 Issues Críticos (FIX AHORA)

### 1. XSS en Post Content
**Archivo**: `app/Support/WordPressContent.php` y `resources/views/pages/single-post.blade.php:22`

**Problema**: Admin puede insertar JavaScript que se ejecuta en navegadores de usuarios

**Fix Rápido** (15 min):
```bash
composer require stevebauman/purify
```

Luego en `app/Http/Controllers/Admin/PostController.php` (línea 47):
```php
public function store(Request $request): RedirectResponse
{
    $data = $this->validated($request);
    // Agregar después de validar:
    $data['content'] = Purify::clean($data['content']);
    // ... resto del código
}
```

---

### 2. Security Headers Faltando
**Archivo**: `routes/web.php` y `config/app.php`

**Problema**: No hay CSP, X-Frame-Options, etc. → abre a XSS, clickjacking

**Fix Rápido** (20 min):
1. Crear archivo: `app/Http/Middleware/SecurityHeaders.php`
2. Copiar código del `AUDIT_FINDINGS.md`
3. Registrar en `app/Http/Kernel.php` en `$middleware`

---

### 3. Sin Rate Limiting en Login
**Archivo**: `routes/web.php:47`

**Problema**: Admin vulnerable a brute force

**Fix Rápido** (5 min):
```php
// ANTES:
Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');

// DESPUÉS:
Route::post('/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:5,1')  // 5 intentos por minuto
    ->name('login.store');
```

---

## 🟡 Important Issues (Haz Este Mes)

### 4. User Management
**What**: No puedes crear otros admins desde UI
**Where**: `routes/web.php` (no hay ruta), `app/Http/Controllers/Admin/` (no hay UserController)
**Impact**: Medium (necesitas acceso DB para agregar admins)

### 5. Email Features
**What**: Sin verificación de email, sin password reset
**Where**: Missing features
**Impact**: Medium (usabilidad)

### 6. SEO
**What**: Sin meta tags dinámicos, robots.txt, sitemap
**Where**: Everywhere
**Impact**: Low (discoverability)

---

## ✅ Lo Que Está Bien (No Toques)

| Componente | Ubicación | Status |
|-----------|----------|--------|
| Password hashing | `app/Models/User.php` | ✅ Perfecto |
| SQL injection prevention | All controllers | ✅ Seguro |
| CSRF protection | `config/session.php` | ✅ Enabled |
| Session security | `config/session.php` | ✅ HTTP-only |
| Audit logging | `app/Services/AuditTrailService.php` | ✅ Working |
| Webhook validation | `app/Http/Controllers/Api/RadioWebhookController.php:52` | ✅ hash_equals() |

---

## 📚 Archivos Principales

### Controllers
```
app/Http/Controllers/
├─ SiteController.php ................... Páginas públicas
├─ Admin/
│  ├─ AuthController.php ............... Login/Logout ⚠️ (agregar throttle)
│  ├─ PostController.php ............... Blog CRUD ⚠️ (agregar sanitization)
│  └─ ... 7 más CRUD controllers
└─ Api/
   ├─ RadioWebhookController.php ....... Webhook de radio ✅
   └─ PlayerStatusController.php ....... Estado del player
```

### Models
```
app/Models/
├─ User.php ............................. ✅ Seguro
├─ Post.php ............................. ⚠️ (revisar content field)
├─ Album.php ............................ ✅ OK
├─ Event.php ............................ ✅ OK
├─ Song.php ............................. ✅ OK
├─ RadioProgram.php .................... ✅ OK
└─ ThemeSetting.php .................... ✅ Tema dinámico
```

### Middleware
```
app/Http/Middleware/
├─ RequireAdmin.php ..................... ✅ Auth middleware
├─ TrackAdminAuditTrail.php ........... ✅ Logging
└─ SecurityHeaders.php ................ ❌ CREAR (missing)
```

### Views
```
resources/views/
├─ pages/ .............................. Público
│  ├─ single-post.blade.php ........... ⚠️ XSS en {!! $block !!}
│  └─ ... otras páginas
├─ admin/ ............................. Admin panel
│  ├─ posts/_form.blade.php .......... Editor de posts
│  └─ ... otras forms
└─ components/ ......................... Blade components
   ├─ ui/ .............................. UI reusables
   └─ layouts/ ........................ Site + admin layouts
```

### Config
```
config/
├─ app.php ............................ ✅ OK, revisar APP_DEBUG en prod
├─ session.php ........................ ✅ OK, HTTP-only + JSON
├─ database.php ....................... ✅ SQLite (dev)
├─ player.php ......................... ✅ Radio config
└─ auth.php ........................... ✅ OK
```

### Routes
```
routes/
├─ web.php ............................ Public + admin routes
│  ├─ Line 47: POST /admin/login .... ⚠️ (agregar throttle)
│  └─ Line 49: middleware(['admin', 'audit'])
└─ api.php ........................... API routes
   ├─ GET /api/player/status ........ ✅ throttle:60,1
   └─ POST /api/radio/metadata ...... ✅ throttle:30,1
```

---

## 🔍 Cómo Revisar una Característica Completa

### Ejemplo: Post Creation

**Step 1**: Revisar ruta
```php
// routes/web.php línea 112-118
Route::get('/posts/create', ...);
Route::post('/posts', ...);        // ← POST endpoint
Route::get('/posts/{post}/edit', ...);
Route::put('/posts/{post}', ...);  // ← PUT endpoint
```

**Step 2**: Revisar validación
```php
// app/Http/Controllers/Admin/PostController.php
// Line 47: $data = $this->validated($request);
// ← Verifica qué se valida
```

**Step 3**: Revisar almacenamiento
```php
// app/Http/Controllers/Admin/PostController.php
// Line 57: Post::query()->create($data);
// ← Check el model fillable
```

**Step 4**: Revisar vistas
```php
// resources/views/admin/posts/create.blade.php
// Ver cómo se renderiza el form
```

**Step 5**: Probar en navegador
```
1. Navega a /admin/posts/create
2. Completa el form
3. Envía
4. Verifica en BD
```

---

## 🧪 Testing Security Fixes

### Para XSS Fix:
```html
<!-- Test input en post content -->
<img src=x onerror="alert('XSS')">
<!-- ✅ Debe sanatizarse después del fix -->
```

### Para Rate Limiting Fix:
```bash
# Test 6 logins en rápida sucesión
for i in {1..6}; do
  curl -X POST http://localhost/admin/login \
    -d "email=admin@test.com&password=wrong"
done
# ✅ Debería bloquear después del 5to intento
```

### Para Security Headers Fix:
```bash
# Check headers
curl -I http://localhost
# ✅ Debe ver: X-Content-Type-Options, X-Frame-Options, etc.
```

---

## 📋 Checklist Pre-Deploy

- [ ] Fase 1 completa (security fixes)
- [ ] APP_DEBUG=false en .env producción
- [ ] SESSION_ENCRYPT=true
- [ ] SESSION_SECURE_COOKIE=true
- [ ] SSL certificate configurado
- [ ] Backup database policy
- [ ] Email SMTP configurado
- [ ] Monitored (Sentry, New Relic, etc.)
- [ ] Logs rotando
- [ ] Database indexes OK

---

## 🆘 Debugging Tips

### "Post not saving"
Check:
1. `app/Http/Controllers/Admin/PostController.php:47` → validation
2. `app/Models/Post.php` → $fillable
3. Browser console → network errors
4. Laravel logs: `storage/logs/`

### "Admin can't login"
Check:
1. User exists in DB: `SELECT * FROM users`
2. Email/password correct
3. `is_admin` flag = true
4. Check `app/Http/Middleware/RequireAdmin.php`

### "Radio metadata not updating"
Check:
1. Webhook URL correct in RadioBoss config
2. API key matches in `.env`: RADIOBOSS_WEBHOOK_KEY
3. Check `app/Http/Controllers/Api/RadioWebhookController.php`
4. Logs: `storage/logs/laravel.log`

---

## 📞 Common Questions

**Q: ¿Dónde cambio los colores del tema?**
A: Entra al admin panel `/admin/settings` y edita los valores. Están guardados en BD (tabla `theme_settings`).

**Q: ¿Puedo hacer el sitio en otro idioma?**
A: Sí, Laravel tiene i18n built-in. Edita `config/app.php` y crea archivos en `resources/lang/`.

**Q: ¿Cómo agregó la integración de radio?**
A: Webhook en `RadioWebhookController`, servicio en `RadioPlayerService`, almacenamiento en `PlayHistory`.

**Q: ¿Por qué no hay comentarios en posts?**
A: Feature no implementado. El form es simulado. Necesitarías agregar modelo `Comment` + lógica CRUD.

---

## 🚀 Deploy Steps

### Local to Production

1. **Prepare**
   ```bash
   git status
   composer audit
   npm audit
   php artisan test
   ```

2. **Tag Release**
   ```bash
   git tag -a v1.0.0-beta -m "Beta release"
   git push origin v1.0.0-beta
   ```

3. **Deploy Script** (example with Forge/etc)
   ```bash
   # Pull latest
   git pull

   # Install dependencies
   composer install --no-dev
   npm ci
   npm run build

   # Migrate database
   php artisan migrate --force

   # Cache configuration
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache

   # Restart queue (if using)
   php artisan queue:restart
   ```

4. **Verify**
   - Sitio accesible
   - Admin login funciona
   - Radio metadata updating
   - Logs clean

---

**Generated**: May 2026
**For**: Development Team
**Questions**: Refer to AUDIT_FINDINGS.md or ARCHITECTURE.md
