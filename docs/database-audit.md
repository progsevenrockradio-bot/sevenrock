# Database Audit - SevenRockRadio

Source reviewed:
- `C:\Users\JOSE FONT\Desktop\7RR LOCAL\00.- New Web 7RR\u531780502_BD_7rr.sql\u531780502_BD_7rr.sql`

This dump mixes:
- WordPress core tables
- plugin tables
- custom radio/app tables
- staging/clone artifacts

## 1. Tables worth keeping

### Core content / app tables
- `wpsr_posts`
- `wpsr_postmeta`
- `wpsr_terms`
- `wpsr_term_taxonomy`
- `wpsr_term_relationships`
- `wpsr_users`
- `wpsr_usermeta`
- `wpsr_srr_audio`
- `wpsr_connections`
- `wpsr_connections_address`
- `wpsr_connections_date`
- `wpsr_connections_email`
- `wpsr_connections_link`
- `wpsr_connections_messenger`
- `wpsr_connections_meta`
- `wpsr_connections_phone`
- `wpsr_connections_social`
- `wpsr_connections_terms`
- `wpsr_connections_term_taxonomy`
- `wpsr_connections_term_relationships`
- `wpsr_ulike`
- `wpsr_ulike_meta`
- `wpsr_pmpro_membership_levels`
- `wpsr_pmpro_groups`
- `wpsr_pmpro_memberships_users`
- `wpsr_pmpro_subscriptions`
- `wpsr_pmpro_membership_ordermeta`
- `wpsr_pmpro_subscriptionmeta`

### Custom app tables already used by the Laravel rebuild
- `posts`
- `band_profiles`
- `bands`
- `radio_programs`
- `tracks`
- `now_playing`
- `settings`
- `subscriptions`
- `plans`
- `home_media`
- `social_links`
- `users`

## 2. Tables with real data but likely legacy-only

These tables have content in the dump and may still be useful for migration or reference:

- `bands` (1 row)
- `band_profiles` (2 rows)
- `radio_programs` (1 row)
- `tracks` (1 row)
- `now_playing` (1 row)
- `settings` (1 row)
- `subscriptions` (1 row)
- `plans` (1 row)
- `home_media` (1 row)
- `social_links` (1 row)

## 3. Empty or near-empty app tables

These were present but had little or no usable data in the dump:

- `play_history`
- `programs`
- `songs`
- `albums`
- `videos`
- `gallery_images`
- `events`
- `theme_settings`
- `products`
- `news`
- `notifications`
- `contact_messages`

## 4. Plugin / vendor tables to remove from the Laravel migration

These are not useful for the new Laravel app and can be archived or dropped after validation:

- `wpsr_aioseo_*`
- `wpsr_rank_math_*`
- `wpsr_cerber_*`
- `wpsr_litespeed_*`
- `wpsr_nextend2_*`
- `wpsr_masterslider_*`
- `wpsr_duplicator_*`
- `wpsr_b2s_*`
- `wpsr_hostinger_reach_*`
- `wpsr_wpforms_*`
- `wpsr_depicter_*`
- `wpsr_e_*`
- `wpsr_fea_*`
- `wpsr_pvc_*`
- `wpsr_sms_*`
- `wpsr_tm_*`
- `wpsr_userfeedback_*`
- `wpsr_odb_logs`
- `wpsr_page_visit_*`
- `wpsr_yoast_*`
- `wpsr_wc_*`
- `wpsr_woocommerce_*`
- `wpsr_actionscheduler_*`
- `wpsr_popularposts*`
- `wpsr_pmpro_*` if memberships are no longer part of the product

## 5. Staging / clone noise

Drop after validation:

- `wpstg0_*`
- `uibnVeTf*`

## 6. Practical migration priority

### Priority 1
- `wpsr_posts`
- `wpsr_postmeta`
- `wpsr_terms`
- `wpsr_term_taxonomy`
- `wpsr_term_relationships`
- `wpsr_users`
- `wpsr_usermeta`
- `wpsr_srr_audio`
- `wpsr_connections*`
- `wpsr_ulike*`

### Priority 2
- `band_profiles`
- `bands`
- `radio_programs`
- `tracks`
- `now_playing`
- `settings`
- `home_media`
- `social_links`

### Priority 3
- `pmpro` tables only if memberships remain active
- `subscriptions`
- `plans`

## 7. Recommended cleanup strategy

1. Keep the dump as archive.
2. Create a whitelist of tables to migrate.
3. Build a separate SQL export containing only those tables.
4. Validate foreign keys and row counts after import.
5. Drop vendor/staging tables only after a backup is confirmed.

## 8. Notes

- The dump contains multiple table families from the old WordPress site plus plugin leftovers.
- The Laravel app should not inherit plugin tables unless they are still needed by a feature.
- The main risk is deleting `wp`-style content tables before mapping them to the Laravel models.

