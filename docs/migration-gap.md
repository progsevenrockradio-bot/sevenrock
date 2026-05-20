# Migration Gap - Old Project vs SevenRockRadio

Reviewed sources:
- Old app: `C:\laragon\www\sevenrockradio-web`
- New app: `C:\laragon\www\Plantilla\SevenRockRadio`

## What the new app already covers

- Public pages: home, events, discography, videos, gallery, blog, shop, contact
- Admin CRUD: albums, videos, gallery, events, posts, products, band profiles, songs, theme settings
- Player: play/pause, volume, mute, details modal, band profile lookup, warmup hooks
- Brand/theme settings in admin

## What the old app had that is still missing or only partial in the new app

### 1. Live metadata pipeline
Old app modules:
- `Api\PlayerStatusController`
- `Api\StreamMetadataController`
- `Api\WarmupController`
- `Livewire\RadioPlayer`
- `Livewire\LyricsNewsDrawer`
- `Support\NowPlayingService`
- `Support\BandFingerprintService`
- `Support\BandProfileHydrator`
- `Jobs\SyncRadioBossMetadata`

Value to port:
- robust now-playing sync
- background warmup without blocking requests
- lyrics/band-info drawer behavior
- stronger fallback when metadata is incomplete

### 2. Program scheduling / editorial spotlight
Old app modules:
- `Support\ProgramScheduleService`
- `Support\DrawerShowInfoResolver`
- `Support\HeadlineTickerService`
- `Support\EditorialSignalService`
- `Support\ProgramScheduleService::presentProgram()`, `nextProgram()`, `previewNews()`

Value to port:
- next program block on home
- live show info drawer
- editorial/news ticker
- program scheduling logic tied to episodes

### 3. Podcast / archive workflow
Old app modules:
- `PodcastPublicController`
- `StaticPageControllerWithPodcasts`
- `ArchiveOrgPodcastService`
- `UploadMp3Job`
- `UploadRadioProgramToArchiveJob`
- `IngestPodcastInbox`

Status:
- confirmed in scope
- keep podcast/public archive flow alive in the new app

Value to port:
- public podcast catalog
- podcast ingestion workflow
- Archive.org upload pipeline

### 4. Social publishing stack
Old app modules:
- `Social\DashboardController`
- `Social\AccountController`
- `Social\PostController`
- `Social\MetaWebhookController`
- `SocialAuthController`
- `Services\Social\FacebookService`
- `InstagramService`
- `WhatsAppService`
- `XService`
- `SocialPublisherService`

Value to port:
- queued social publishing
- account token refresh
- webhook sync from Meta

### 5. Creator / marketplace stack
Old app modules:
- `Creator\DashboardController`
- `Creator\BandClaimController`
- `MarketplaceController`
- `MarketplaceLeadController`
- `BandClaimResource`
- `MarketplaceProductResource`
- `ProductLeadResource`
- `ProductSaleResource`

Value to port only if the business model still needs it:
- band claims
- creator accounts
- product sales/leads
- creator dashboard

### 6. Security / admin hardening
Old app modules:
- `EnsureTotpSatisfied`
- `SecurityHeaders`
- `TurnstileVerifier`
- `TotpService`
- `FormAntiSpamService`

Value to port:
- better admin login hardening
- anti-spam on contact forms
- security headers

## Recommended migration order

### Phase A - Core radio behavior
1. `ProgramScheduleService` + next program home block
2. now-playing / history sync
3. band-info warmup and lyrics persistence

### Phase B - Editorial surface
1. headline ticker
2. drawer show info
3. news preview blocks for programs

### Phase C - Content pipelines
1. podcasts
2. Archive.org upload
3. social publishing only if the team still uses it

### Phase D - Optional business modules
1. creator/marketplace
2. band claims / moderation
3. advanced security hardening

## What should stay out

Do not port blindly:
- moderation queues and creator modules if the new site is only a radio + magazine front-end
- social posting stack if the team no longer publishes from the CMS
- marketplace / ecommerce complexity if shop is only a lightweight catalog

## Quick conclusion

The old app is broader. The new app is cleaner.
To keep momentum, port only:
- live metadata pipeline
- program scheduling
- news/editorial ticker
- podcast/archive workflow because the radio still publishes episodes
