<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeSetting;
use App\Services\FileUploadService;
use App\Support\ThemeAppearance;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ThemeSettingsController extends Controller
{
    public function manual(): View
    {
        return view('admin.settings-manual', $this->manualViewData());
    }

    public function manualPdf(): \Symfony\Component\HttpFoundation\Response
    {
        $options = new Options();
        $options->setIsRemoteEnabled(true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('admin.settings-manual-pdf', $this->manualViewData())->render());
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="seven-rock-radio-admin-settings-manual.pdf"',
        ]);
    }

    public function edit(): View
    {
        $settings = ThemeSetting::current();
        $featuredAlbums = $this->featuredAlbumOptions();

        return view('admin.settings', [
            'settings' => $settings,
            'fonts' => ThemeAppearance::fonts(),
            'featuredAlbums' => $featuredAlbums,
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
            'brand_display_mode' => ['required', 'string', 'in:logo,mark,both'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'logo_height' => ['required', 'integer', 'min:30', 'max:200'],
            'background' => ['nullable', 'image', 'max:6144'],
            'hero_slide_primary' => ['nullable', 'image', 'max:6144'],
            'hero_slide_secondary' => ['nullable', 'image', 'max:6144'],
            'hero_slides' => ['nullable', 'array'],
            'home_album_cover' => ['nullable', 'image', 'max:6144'],
            'featured_album_slug' => ['nullable', 'string', 'max:255'],
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
            'hero_slides_interval' => ['nullable', 'integer', 'min:2', 'max:30'],
            'hero_slides_transition' => ['nullable', 'string', 'in:fade,slide,zoom'],
            'body_font' => ['required', 'string', 'in:'.implode(',', array_keys(ThemeAppearance::fonts()['body']))],
            'heading_font' => ['required', 'string', 'in:'.implode(',', array_keys(ThemeAppearance::fonts()['heading']))],
            'accent_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'nav_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'surface_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'body_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'heading_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'line_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'email_auto_publish' => ['nullable', 'boolean'],
            'email_processing_enabled' => ['nullable', 'boolean'],
            'email_min_importance' => ['nullable', 'integer', 'min:1', 'max:5'],
            'email_whitelist_senders' => ['nullable', 'string'],
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'archive_access_key' => ['nullable', 'string', 'max:255'],
            'archive_secret_key' => ['nullable', 'string', 'max:255'],
            'email_default_cover' => ['nullable', 'image', 'max:4096'],
            'email_background_color' => ['nullable', 'string', 'max:20'],
            'email_title_verified_podcast' => ['nullable', 'string', 'max:255'],
            'email_label_streaming' => ['nullable', 'string', 'max:255'],
            'email_label_podcast' => ['nullable', 'string', 'max:255'],
            'email_footer_notification' => ['nullable', 'string'],
            'email_title_new_release_published' => ['nullable', 'string', 'max:255'],
            'email_heading_new_release_published' => ['nullable', 'string', 'max:255'],
            'email_title_post_published' => ['nullable', 'string', 'max:255'],
            'email_heading_post_published' => ['nullable', 'string', 'max:255'],
        ]);

        $settings->fill(collect($validated)->except([
            'logo',
            'background',
            'hero_slide_primary',
            'hero_slide_secondary',
            'hero_slides',
            'home_album_cover',
            'featured_album_slug',
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
            'email_auto_publish',
            'email_processing_enabled',
            'email_min_importance',
            'email_whitelist_senders',
            'gemini_api_key',
            'archive_access_key',
            'archive_secret_key',
            'email_default_cover',
            'email_background_color',
            'email_title_verified_podcast',
            'email_label_streaming',
            'email_label_podcast',
            'email_footer_notification',
            'email_title_new_release_published',
            'email_heading_new_release_published',
            'email_title_post_published',
            'email_heading_post_published',
        ])->all());

        foreach ([
            'logo' => 'logo_path',
            'background' => 'background_path',
            'hero_slide_primary' => 'hero_slide_primary_path',
            'hero_slide_secondary' => 'hero_slide_secondary_path',
            'home_album_cover' => 'home_album_cover_path',
            'home_video_image' => 'home_video_image_path',
            'hero_video' => 'hero_video_path',
            'email_default_cover' => 'email_default_cover_path',
        ] as $input => $column) {
            if ($request->hasFile($input)) {
                $this->removeIfUploaded($settings->{$column});
                $settings->{$column} = app(FileUploadService::class)->upload($request->file($input), 'theme')['url'];
            }
        }

        $settings->hero_video_url = trim((string) ($validated['hero_video_url'] ?? '')) ?: null;
        $settings->hero_video_disabled = $request->boolean('hero_video_disabled');
        $settings->featured_album_slug = trim((string) ($validated['featured_album_slug'] ?? '')) ?: null;
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
        $settings->brand_mark = trim((string) ($validated['brand_mark'] ?? '')) ?: '';
        $settings->brand_mark_font = trim((string) ($validated['brand_mark_font'] ?? '')) ?: null;
        $settings->brand_display_mode = trim((string) ($validated['brand_display_mode'] ?? '')) ?: null;
        $settings->contact_form_title = trim((string) ($validated['contact_form_title'] ?? '')) ?: null;
        $settings->contact_info_title = trim((string) ($validated['contact_info_title'] ?? '')) ?: null;
        $settings->contact_description = trim((string) ($validated['contact_description'] ?? '')) ?: null;
        $settings->contact_address = trim((string) ($validated['contact_address'] ?? '')) ?: null;
        $settings->contact_email = trim((string) ($validated['contact_email'] ?? '')) ?: null;
        $settings->contact_phone_primary = trim((string) ($validated['contact_phone_primary'] ?? '')) ?: null;
        $settings->contact_phone_secondary = trim((string) ($validated['contact_phone_secondary'] ?? '')) ?: null;
        $settings->email_auto_publish = $request->boolean('email_auto_publish');
        $settings->email_processing_enabled = $request->boolean('email_processing_enabled');
        $settings->email_min_importance = isset($validated['email_min_importance']) ? (int) $validated['email_min_importance'] : 1;
        $settings->email_whitelist_senders = trim((string) ($validated['email_whitelist_senders'] ?? '')) ?: null;
        $settings->gemini_api_key = trim((string) ($validated['gemini_api_key'] ?? '')) ?: null;
        $settings->archive_access_key = trim((string) ($validated['archive_access_key'] ?? '')) ?: null;
        $settings->archive_secret_key = trim((string) ($validated['archive_secret_key'] ?? '')) ?: null;
        $settings->featured_stories = $this->decodeJsonSection($validated['featured_stories_json'] ?? '', 'featured_stories_json', $settings->featuredStories());
        $settings->latest_podcasts = $this->decodeJsonSection($validated['latest_podcasts_json'] ?? '', 'latest_podcasts_json', $settings->latestPodcasts());
        $settings->home_headings = $this->decodeJsonSection($validated['home_headings_json'] ?? '', 'home_headings_json', $settings->homeHeadings());

        $uiTexts = $this->decodeJsonSection($validated['ui_texts_json'] ?? '', 'ui_texts_json', $settings->uiTexts());
        if ($request->has('email_background_color')) {
            $uiTexts['email_background_color'] = $validated['email_background_color'];
        }
        if ($request->has('email_title_verified_podcast')) {
            $uiTexts['email_title_verified_podcast'] = $validated['email_title_verified_podcast'];
        }
        if ($request->has('email_label_streaming')) {
            $uiTexts['email_label_streaming'] = $validated['email_label_streaming'];
        }
        if ($request->has('email_label_podcast')) {
            $uiTexts['email_label_podcast'] = $validated['email_label_podcast'];
        }
        if ($request->has('email_footer_notification')) {
            $uiTexts['email_footer_notification'] = $validated['email_footer_notification'];
        }
        if ($request->has('email_title_new_release_published')) {
            $uiTexts['email_title_new_release_published'] = $validated['email_title_new_release_published'];
        }
        if ($request->has('email_heading_new_release_published')) {
            $uiTexts['email_heading_new_release_published'] = $validated['email_heading_new_release_published'];
        }
        if ($request->has('email_title_post_published')) {
            $uiTexts['email_title_post_published'] = $validated['email_title_post_published'];
        }
        if ($request->has('email_heading_post_published')) {
            $uiTexts['email_heading_post_published'] = $validated['email_heading_post_published'];
        }
        $settings->ui_texts = $uiTexts;

        $settings->admin_texts = $this->decodeJsonSection($validated['admin_texts_json'] ?? '', 'admin_texts_json', $settings->adminTexts());

        $settings->hero_slides_interval = ((int) ($validated['hero_slides_interval'] ?? 7)) * 1000;
        $settings->hero_slides_transition = trim((string) ($validated['hero_slides_transition'] ?? 'fade')) ?: 'fade';

        $settings->save();

        $heroSlides = $validated['hero_slides'] ?? [];
        $slidesData = [];
        foreach ($heroSlides as $index => $slideData) {
            $image = $slideData['image'] ?? '';
            $file = $request->file("hero_slides.{$index}.file");
            if ($file && $file->isValid()) {
                $uploaded = app(FileUploadService::class)->upload($file, 'theme');
                $image = $uploaded['url'] ?? ($uploaded['key'] ?? $image);
            }
            if ($image) {
                $slidesData[] = ['image' => $image];
            }
        }
        $settings->hero_slides = ! empty($slidesData) ? $slidesData : null;
        $settings->save();

        return redirect()->route('admin.settings.edit')->with('status', 'Theme settings updated.');
    }

    private function removeIfUploaded(?string $path): void
    {
        if (! $path || str_starts_with($path, 'assets/')) {
            return;
        }

        app(FileUploadService::class)->delete($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function manualViewData(): array
    {
        $settings = ThemeSetting::current();

        return [
            'themeSettings' => $settings,
            'themeAppearance' => ThemeAppearance::resolved(),
        ];
    }

    /**
     * @return array<int, array{label:string,slug:string}>
     */
    private function featuredAlbumOptions(): array
    {
        $talentAlbums = \App\Models\TalentAlbum::query()
            ->where('is_published', true)
            ->with('talent')
            ->orderByDesc('release_date')
            ->get()
            ->map(fn ($album) => [
                'label' => trim(($album->talent->band_name ?? 'Artista') . ' - ' . $album->title),
                'slug' => $album->slug,
            ]);

        $adminAlbums = \App\Models\Album::query()
            ->whereNotNull('title')
            ->orderByDesc('released_at')
            ->get()
            ->map(fn ($album) => [
                'label' => trim(($album->artist ?? 'Artista') . ' - ' . $album->title),
                'slug' => $album->slug,
            ]);

        return $talentAlbums->concat($adminAlbums)->values()->all();
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

    public function sendTestEmail(Request $request): RedirectResponse
    {
        $email = $request->input('test_email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->with('error', 'El correo de prueba no es válido.');
        }

        $mockEpisode = new \App\Models\RadioProgram([
            'titulo_programa' => 'Metal Storm Show',
            'numero_episodio' => 42,
            'live_title' => 'Especial de Thrash Metal de los 80s',
            'fecha_emision' => now(),
            'archivo_mp3' => 'test-episode-42.mp3',
        ]);
        $mockEpisode->id = 999;

        try {
            \Illuminate\Support\Facades\Mail::to($email)->send(
                new \App\Mail\PodcastUploadedMail(
                    episode: $mockEpisode,
                    localPath: '/tmp/test-episode-42.mp3',
                    remotePath: '/music/test-episode-42.mp3',
                    radiobossVerified: true,
                    archiveVerified: true,
                    deliveryStatus: 'delivery_verified'
                )
            );

            return redirect()->back()->with('status', 'Correo de prueba enviado correctamente a ' . $email);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Error al enviar el correo: ' . $e->getMessage());
        }
    }
}
