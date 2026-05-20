<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeSetting;
use App\Support\ThemeAppearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ThemeSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = ThemeSetting::current();

        return view('admin.settings', [
            'settings' => $settings,
            'fonts' => ThemeAppearance::fonts(),
            'featuredStoriesJson' => json_encode($settings->featuredStories(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'latestPodcastsJson' => json_encode($settings->latestPodcasts(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'homeHeadingsJson' => json_encode($settings->homeHeadings(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'uiTextsJson' => json_encode($settings->uiTexts(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'adminTextsJson' => json_encode($settings->adminTexts(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'activeNotificationState' => [
                'mailer' => $this->resolveActiveNotificationMailer($settings),
                'primary' => $this->resolveActiveNotificationPrimaryRecipient($settings),
                'copy' => $this->resolveActiveNotificationCopyRecipient($settings),
                'from' => $this->resolveActiveNotificationFromAddress($settings),
                'reply_to' => $this->resolveActiveNotificationReplyToAddress($settings),
                'contact_email' => trim((string) ($settings->contact_email ?? '')) ?: null,
            ],
            'archiveOrgState' => [
                'configured' => $this->hasArchiveOrgCredentials(),
                'access_key_set' => trim((string) config('services.archive_org.access_key', '')) !== '',
                'secret_key_set' => trim((string) config('services.archive_org.secret_key', '')) !== '',
                'endpoint' => trim((string) config('services.archive_org.endpoint', '')),
                'bucket' => trim((string) config('services.archive_org.bucket', '')),
                'collection' => trim((string) config('services.archive_org.collection', '')),
                'default_sync' => (bool) config('services.podcast_ingest.default_sync_archive_org', true),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = ThemeSetting::current();

        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'brand_mark' => ['nullable', 'string', 'max:120'],
            'brand_mark_font' => ['required', 'string', 'in:'.implode(',', array_keys(ThemeAppearance::fonts()['brand_mark']))],
            'brand_display_mode' => ['required', 'string', 'in:logo,mark'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'background' => ['nullable', 'image', 'max:6144'],
            'hero_slide_primary' => ['nullable', 'image', 'max:6144'],
            'hero_slide_secondary' => ['nullable', 'image', 'max:6144'],
            'home_album_cover' => ['nullable', 'image', 'max:6144'],
            'home_video_image' => ['nullable', 'image', 'max:6144'],
            'contact_form_title' => ['nullable', 'string', 'max:255'],
            'contact_info_title' => ['nullable', 'string', 'max:255'],
            'contact_description' => ['nullable', 'string'],
            'contact_address' => ['nullable', 'string'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'notification_email' => ['nullable', 'email', 'max:255'],
            'notification_copy_email' => ['nullable', 'email', 'max:255'],
            'notification_from_email' => ['nullable', 'email', 'max:255'],
            'notification_reply_to_email' => ['nullable', 'email', 'max:255'],
            'notification_mailer' => ['nullable', 'string', 'max:50', 'in:'.implode(',', array_keys(config('mail.mailers', [])))],
            'contact_phone_primary' => ['nullable', 'string', 'max:255'],
            'contact_phone_secondary' => ['nullable', 'string', 'max:255'],
            'featured_stories_json' => ['nullable', 'string'],
            'latest_podcasts_json' => ['nullable', 'string'],
            'home_headings_json' => ['nullable', 'string'],
            'ui_texts_json' => ['nullable', 'string'],
            'admin_texts_json' => ['nullable', 'string'],
            'hero_video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:102400'],
            'hero_video_url' => ['nullable', 'url', 'max:2048'],
            'hero_video_disabled' => ['nullable', 'boolean'],
            'social_facebook' => ['nullable', 'url', 'max:2048'],
            'social_instagram' => ['nullable', 'url', 'max:2048'],
            'social_youtube' => ['nullable', 'url', 'max:2048'],
            'social_tiktok' => ['nullable', 'url', 'max:2048'],
            'social_x' => ['nullable', 'url', 'max:2048'],
            'body_font' => ['required', 'string', 'in:'.implode(',', array_keys(ThemeAppearance::fonts()['body']))],
            'heading_font' => ['required', 'string', 'in:'.implode(',', array_keys(ThemeAppearance::fonts()['heading']))],
            'accent_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'nav_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'surface_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'body_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'heading_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'line_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
        ]);

        $settings->fill(collect($validated)->except([
            'logo',
            'background',
            'hero_slide_primary',
            'hero_slide_secondary',
            'home_album_cover',
            'home_video_image',
            'brand_mark',
            'brand_mark_font',
            'brand_display_mode',
            'contact_form_title',
            'contact_info_title',
            'contact_description',
            'contact_address',
            'contact_email',
            'notification_email',
            'notification_copy_email',
            'notification_from_email',
            'notification_reply_to_email',
            'notification_mailer',
            'contact_phone_primary',
            'contact_phone_secondary',
            'featured_stories_json',
            'latest_podcasts_json',
            'home_headings_json',
            'ui_texts_json',
            'admin_texts_json',
            'hero_video',
        ])->all());

        foreach ([
            'logo' => 'logo_path',
            'background' => 'background_path',
            'hero_slide_primary' => 'hero_slide_primary_path',
            'hero_slide_secondary' => 'hero_slide_secondary_path',
            'home_album_cover' => 'home_album_cover_path',
            'home_video_image' => 'home_video_image_path',
            'hero_video' => 'hero_video_path',
        ] as $input => $column) {
            if ($request->hasFile($input)) {
                $this->removeIfUploaded($settings->{$column});
                $settings->{$column} = $request->file($input)->store('theme', 'public');
            }
        }

        $settings->hero_video_url = trim((string) ($validated['hero_video_url'] ?? '')) ?: null;
        $settings->hero_video_disabled = $request->boolean('hero_video_disabled');
        $settings->notification_email = trim((string) ($validated['notification_email'] ?? '')) ?: null;
        $settings->notification_copy_email = trim((string) ($validated['notification_copy_email'] ?? '')) ?: null;
        $settings->notification_from_email = trim((string) ($validated['notification_from_email'] ?? '')) ?: null;
        $settings->notification_reply_to_email = trim((string) ($validated['notification_reply_to_email'] ?? '')) ?: null;
        $settings->notification_mailer = trim((string) ($validated['notification_mailer'] ?? '')) ?: null;
        $settings->social_facebook = trim((string) ($validated['social_facebook'] ?? '')) ?: null;
        $settings->social_instagram = trim((string) ($validated['social_instagram'] ?? '')) ?: null;
        $settings->social_youtube = trim((string) ($validated['social_youtube'] ?? '')) ?: null;
        $settings->social_tiktok = trim((string) ($validated['social_tiktok'] ?? '')) ?: null;
        $settings->social_x = trim((string) ($validated['social_x'] ?? '')) ?: null;
        $settings->featured_stories = $this->decodeJsonSection($validated['featured_stories_json'] ?? '', 'featured_stories_json', $settings->featuredStories());
        $settings->latest_podcasts = $this->decodeJsonSection($validated['latest_podcasts_json'] ?? '', 'latest_podcasts_json', $settings->latestPodcasts());
        $settings->home_headings = $this->decodeJsonSection($validated['home_headings_json'] ?? '', 'home_headings_json', $settings->homeHeadings());
        $settings->ui_texts = $this->decodeJsonSection($validated['ui_texts_json'] ?? '', 'ui_texts_json', $settings->uiTexts());
        $settings->admin_texts = $this->decodeJsonSection($validated['admin_texts_json'] ?? '', 'admin_texts_json', $settings->adminTexts());

        $settings->save();

        return redirect()->route('admin.settings.edit')->with('status', 'Theme settings updated.');
    }

    private function removeIfUploaded(?string $path): void
    {
        if (! $path || str_starts_with($path, 'assets/')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * @param mixed $fallback
     * @return array<string, mixed>
     */
    private function decodeJsonSection(string $value, string $field, array $fallback): array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $fallback;
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'The '.$field.' field must contain valid JSON.',
            ]);
        }

        return $decoded;
    }

    private function hasArchiveOrgCredentials(): bool
    {
        return trim((string) config('services.archive_org.access_key', '')) !== ''
            && trim((string) config('services.archive_org.secret_key', '')) !== '';
    }

    private function resolveActiveNotificationMailer(ThemeSetting $settings): string
    {
        $mailer = trim((string) ($settings->notification_mailer ?: config('services.notifications.mailer', '')));
        if ($mailer !== '') {
            return $mailer;
        }

        return (string) config('mail.default', 'log');
    }

    private function resolveActiveNotificationPrimaryRecipient(ThemeSetting $settings): ?string
    {
        $value = trim((string) ($settings->notification_email ?: $settings->contact_email ?: config('mail.from.address', '')));

        return $value !== '' ? $value : null;
    }

    private function resolveActiveNotificationCopyRecipient(ThemeSetting $settings): ?string
    {
        $value = trim((string) ($settings->notification_copy_email ?: $settings->contact_email ?: ''));

        return $value !== '' ? $value : null;
    }

    private function resolveActiveNotificationFromAddress(ThemeSetting $settings): ?string
    {
        $value = trim((string) ($settings->notification_from_email ?: config('mail.from.address', '')));

        return $value !== '' ? $value : null;
    }

    private function resolveActiveNotificationReplyToAddress(ThemeSetting $settings): ?string
    {
        $value = trim((string) ($settings->notification_reply_to_email ?: $settings->notification_email ?: $settings->contact_email ?: config('mail.from.address', '')));

        return $value !== '' ? $value : null;
    }
}
