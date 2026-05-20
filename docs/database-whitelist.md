# Database Whitelist - SevenRockRadio

Use this list to export only the tables that are worth keeping for the Laravel rebuild.

## 1. Keep from WordPress core

- `wpsr_posts`
- `wpsr_postmeta`
- `wpsr_terms`
- `wpsr_term_taxonomy`
- `wpsr_term_relationships`
- `wpsr_users`
- `wpsr_usermeta`

## 2. Keep from radio / content modules

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

## 3. Keep from membership / monetization if still used

- `wpsr_pmpro_groups`
- `wpsr_pmpro_membership_levels`
- `wpsr_pmpro_memberships_users`
- `wpsr_pmpro_subscriptions`
- `wpsr_pmpro_membership_ordermeta`
- `wpsr_pmpro_subscriptionmeta`

## 4. Keep from the old custom app

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

## 5. Optional tables

Only keep these if you confirm the feature still matters:

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

## 6. Ignore / drop

Do not migrate these into Laravel unless a specific feature still depends on them:

- `wpstg0_*`
- `uibnVeTf*`
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
- `wpsr_yoast_*`
- `wpsr_wc_*`
- `wpsr_woocommerce_*`
- `wpsr_actionscheduler_*`
- `wpsr_popularposts*`

## 7. My recommended export set

If you want the shortest safe export, use this:

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
- `wpsr_pmpro_groups`
- `wpsr_pmpro_membership_levels`
- `wpsr_pmpro_memberships_users`
- `wpsr_pmpro_subscriptions`
- `wpsr_pmpro_membership_ordermeta`
- `wpsr_pmpro_subscriptionmeta`
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

## 8. Export command template

Use `mysqldump` with only the tables above.

Example:

```powershell
mysqldump -u root -p your_database `
  wpsr_posts wpsr_postmeta wpsr_terms wpsr_term_taxonomy wpsr_term_relationships `
  wpsr_users wpsr_usermeta wpsr_srr_audio `
  wpsr_connections wpsr_connections_address wpsr_connections_date wpsr_connections_email `
  wpsr_connections_link wpsr_connections_messenger wpsr_connections_meta `
  wpsr_connections_phone wpsr_connections_social wpsr_connections_terms `
  wpsr_connections_term_taxonomy wpsr_connections_term_relationships `
  wpsr_ulike wpsr_ulike_meta `
  wpsr_pmpro_groups wpsr_pmpro_membership_levels wpsr_pmpro_memberships_users `
  wpsr_pmpro_subscriptions wpsr_pmpro_membership_ordermeta wpsr_pmpro_subscriptionmeta `
  posts band_profiles bands radio_programs tracks now_playing settings subscriptions plans home_media social_links users `
  > sevenrock_whitelist.sql
```

If you want, I can turn this into a PowerShell script that reads your DB credentials and exports the whitelist automatically.

