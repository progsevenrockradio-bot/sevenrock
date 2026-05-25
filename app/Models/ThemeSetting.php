<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Support\PublicMediaUrl;

class ThemeSetting extends Model
{
    use Auditable;
    protected $casts = [
        'hero_video_disabled' => 'bool',
        'featured_stories' => 'array',
        'latest_podcasts' => 'array',
        'home_headings' => 'array',
        'ui_texts' => 'array',
        'admin_texts' => 'array',
    ];

    protected $fillable = [
        'site_name',
        'brand_mark',
        'brand_mark_font',
        'brand_display_mode',
        'logo_path',
        'background_path',
        'hero_slide_primary_path',
        'hero_slide_secondary_path',
        'home_album_cover_path',
        'home_video_image_path',
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
        'featured_stories',
        'latest_podcasts',
        'home_headings',
        'ui_texts',
        'admin_texts',
        'hero_video_path',
        'hero_video_url',
        'hero_video_disabled',
        'social_facebook',
        'social_instagram',
        'social_youtube',
        'social_tiktok',
        'social_x',
        'body_font',
        'heading_font',
        'accent_color',
        'nav_color',
        'surface_color',
        'body_color',
        'heading_color',
        'line_color',
    ];

    public static function defaults(): array
    {
        return [
            'site_name' => 'Seven Rock Radio',
            'brand_mark' => 'Seven Rock Radio',
            'brand_mark_font' => 'Rock Salt',
            'brand_display_mode' => 'mark',
            'logo_path' => 'assets/lucille/logo.png',
            'background_path' => 'assets/lucille/dark-background.jpg',
            'hero_slide_primary_path' => 'assets/lucille/audience_opt.jpg',
            'hero_slide_secondary_path' => 'assets/lucille/live-slider-bg.jpg',
            'home_album_cover_path' => 'assets/lucille/album3.jpg',
            'home_video_image_path' => 'assets/lucille/freedom-at-21-header.jpg',
            'contact_form_title' => 'Envíanos un mensaje',
            'contact_info_title' => 'Donde encontrarnos',
            'contact_description' => 'Whether you like our music or you just would like to say hello, we would love to hear from you. Follow us on social media or simply use this contact form to send us an email.',
            'contact_address' => 'PO Box 16122 Collins Street West Victoria 8007 Australia',
            'contact_email' => 'prog.sevenrockradio@gmail.com',
            'notification_email' => 'prog.sevenrockradio@gmail.com',
            'notification_copy_email' => 'contact@sevenrockradio.shop',
            'notification_from_email' => 'prog.sevenrockradio@gmail.com',
            'notification_reply_to_email' => 'prog.sevenrockradio@gmail.com',
            'notification_mailer' => null,
            'contact_phone_primary' => '+88 (0) 101 1010 101',
            'contact_phone_secondary' => '+88 (0) 101 1010 100',
            'featured_stories' => [
                'headline' => 'Historias destacadas',
                'subtitle' => 'Perfiles con mayor impacto en los ultimos 30 dias',
                'featured' => [
                    'title' => 'Neon Wolves',
                    'type' => 'Banda',
                    'location' => 'Madrid, España',
                    'summary' => 'Sonido rock alternativo con enfoque en directo. Perfil monitorizado con señal editorial estable.',
                    'plays' => '87 plays 30d',
                    'searches' => '10 busquedas',
                    'image' => 'assets/lucille/hipster-869222_1920.jpg',
                ],
                'stories' => [
                    [
                        'title' => 'Storm Velvet',
                        'location' => 'México',
                        'type' => 'Banda',
                        'image' => 'assets/lucille/new-york-1209232_1920.jpg',
                        'signal' => '86 plays',
                        'searches' => '8 busquedas',
                    ],
                    [
                        'title' => 'Midnight Rust',
                        'location' => 'Chile',
                        'type' => 'Banda',
                        'image' => 'assets/lucille/guitarist-407212_1920.jpg',
                        'signal' => '89 plays',
                        'searches' => '6 busquedas',
                    ],
                    [
                        'title' => 'Echo Riot',
                        'location' => 'Argentina',
                        'type' => 'Banda',
                        'image' => 'assets/lucille/fashion-1636868_1920.jpg',
                        'signal' => '82 plays',
                        'searches' => '7 busquedas',
                    ],
                ],
            ],
            'latest_podcasts' => [
                'headline' => 'Últimos Podcasts',
                'subtitle' => 'Escucha los episodios más recientes',
                'featured' => [
                    'title' => 'METAL ADICTO',
                    'episode' => 'Ep. 019',
                    'date' => '17/02/2026',
                    'host' => 'Juan Carlos Armijo',
                    'image' => 'assets/lucille/freedom_at_21.jpg',
                    'summary' => 'Programa radial dedicado íntegramente al metal, con contexto, riffs intensos y una narrativa editorial sólida.',
                ],
                'episodes' => [
                    [
                        'title' => 'LA VIEJA GUARDIA',
                        'episode' => 'Ep. 151',
                        'date' => '16/02/2026',
                        'image' => 'assets/lucille/man-597179_1920.jpg',
                    ],
                    [
                        'title' => 'ABRIENDO CABEZAS',
                        'episode' => 'Ep. 012',
                        'date' => '16/02/2026',
                        'image' => 'assets/lucille/guitar-1758005_1920.jpg',
                    ],
                    [
                        'title' => 'TARDES LITERARIAS',
                        'episode' => 'Ep. 179',
                        'date' => '16/02/2026',
                        'image' => 'assets/lucille/sunrise-1239727.jpg',
                    ],
                    [
                        'title' => 'ZONA DEL METAL',
                        'episode' => 'Ep. 087',
                        'date' => '12/02/2026',
                        'image' => 'assets/lucille/pedalboard-1511069_1920.jpg',
                    ],
                    [
                        'title' => 'LOS ARCHIVOS SECRETOS',
                        'episode' => 'Ep. 031',
                        'date' => '12/12/2025',
                        'image' => 'assets/lucille/string-555070.jpg',
                    ],
                    [
                        'title' => 'ESTACION ROCK',
                        'episode' => 'Ep. 137',
                        'date' => '21/01/2026',
                        'image' => 'assets/lucille/music-1284505_1920.jpg',
                    ],
                ],
            ],
            'home_headings' => [
                'featured_stories' => [
                    'title' => 'Historias destacadas',
                    'subtitle' => 'Perfiles con mayor impacto en los ultimos 30 dias',
                ],
                'next_program' => [
                    'title' => 'Próximo programa',
                    'subtitle' => 'Avance editorial del siguiente bloque en parrilla',
                ],
                'latest_podcasts' => [
                    'title' => 'Últimos Podcasts',
                    'subtitle' => 'Escucha los episodios más recientes',
                ],
                'upcoming_shows' => [
                    'title' => 'Próximos',
                    'accent' => 'Conciertos',
                    'subtitle' => 'Fechas de gira 2026',
                ],
                'new_album_release' => [
                    'title' => 'Nuevo',
                    'accent' => 'Lanzamiento',
                    'subtitle' => 'Álbum destacado',
                ],
                'featured_gallery_images' => [
                    'title' => 'Galería',
                    'accent' => 'Destacada',
                    'subtitle' => 'Imágenes recientes',
                ],
                'featured_video' => [
                    'title' => 'Video',
                    'subtitle' => 'Destacado',
                ],
                'latest_news' => [
                    'title' => 'Últimas',
                    'accent' => 'Noticias',
                    'subtitle' => 'Blog',
                ],
                'send_message' => [
                    'title' => 'Envíanos un',
                    'accent' => 'Mensaje',
                    'subtitle' => 'Contacto',
                ],
            ],
            'ui_texts' => [
                'search_placeholder' => 'buscar...',
                'search_button_label' => 'Buscar',
                'recent_posts' => 'Posts recientes',
                'recent_comments' => 'Comentarios recientes',
                'archives' => 'Archivos',
                'categories' => 'Categorías',
                'tags' => 'Etiquetas',
                'meta' => 'Meta',
                'read_more' => 'Leer más',
                'more_images' => 'Más imágenes',
                'add_to_cart' => 'Añadir al carrito',
                'related_products' => 'Productos relacionados',
                'description' => 'Descripción',
                'reviews' => 'Opiniones (0)',
                'leave_a_reply' => 'Deja un comentario',
                'submit' => 'Enviar',
                'quantity' => 'Cantidad',
                'category' => 'Categoria:',
                'no_reviews' => 'There are no reviews yet.',
                'be_first_review' => 'Be the first to review “:title”',
                'your_name' => 'Tu nombre *',
                'email_address' => 'Correo electronico *',
                'website' => 'Sitio web',
                'write_comment' => 'Escribe tu comentario aqui',
                'post_comment' => 'Publicar comentario',
                'send_email' => 'Enviar correo',
                'phone' => 'Telefono',
                'share' => 'Compartir:',
                'featured_video' => 'Video destacado',
            ],
            'admin_texts' => [
                'dashboard_title' => 'Dashboard',
                'dashboard_copy' => 'Update branding, colors, fonts, and main media without touching Blade or CSS.',
                'admin_suffix' => 'Admin',
                'current_theme' => 'Current theme',
                'theme_settings' => 'Theme settings',
                'theme_settings_copy' => 'Everything here is stored in the database and reflected across the public theme.',
                'json_edit_note' => 'Edit these blocks as JSON. Keep the structure, and change only text, links, and image paths.',
                'branding_section' => 'Branding',
                'typography_section' => 'Typography & colors',
                'main_media_section' => 'Main media',
                'home_editorial_section' => 'Home editorial',
                'contact_section' => 'Contact page',
                'social_links_section' => 'Social links',
                'brand_preview' => 'Brand preview',
                'header_mode' => 'Header mode',
                'wordmark_font' => 'Wordmark font',
                'admin_login_title' => 'Admin Login',
                'admin_login_copy' => 'Access the theme settings and media controls.',
                'brand_mark_label' => 'Brand mark',
                'brand_mark_font_label' => 'Brand mark font',
                'brand_display_mode_label' => 'Brand display mode',
                'site_name_label' => 'Site name',
                'logo_label' => 'Logo',
                'background_label' => 'Background',
                'hero_video_file_label' => 'Hero video file',
                'hero_video_url_label' => 'Hero video URL',
                'disable_hero_video_label' => 'Disable hero video',
                'current_label' => 'Current',
                'not_set' => 'Not set',
                'surface_label' => 'Surface',
                'body_text_label' => 'Body text',
                'heading_text_label' => 'Heading text',
                'body_font_label' => 'Body font',
                'heading_font_label' => 'Heading font',
                'accent_color_label' => 'Accent color',
                'nav_color_label' => 'Nav color',
                'hero_slide_1_label' => 'Hero slide 1',
                'hero_slide_2_label' => 'Hero slide 2',
                'album_cover_label' => 'Album cover',
                'featured_video_image_label' => 'Featured video image',
                'post_taxonomy' => 'Post taxonomy',
                'taxonomy_description' => 'Separate categories from tags so the public blog and single post can render them independently.',
                'title_label' => 'Title',
                'slug_label' => 'Slug',
                'author_label' => 'Author',
                'published_at_label' => 'Published at',
                'excerpt_label' => 'Excerpt',
                'content_paragraphs_label' => 'Content paragraphs',
                'quote_label' => 'Quote',
                'categories_label' => 'Categories',
                'tags_label' => 'Tags',
                'featured_image_path_label' => 'Featured image path',
                'featured_image_file_label' => 'Featured image file',
                'published_label' => 'Published',
                'open_site' => 'Open site',
                'view_site' => 'View site',
                'logout' => 'Logout',
                'save_settings' => 'Save settings',
                'back_to_dashboard' => 'Back to dashboard',
                'back_to_albums' => 'Back to albums',
                'back_to_events' => 'Back to events',
                'back_to_posts' => 'Back to posts',
                'back_to_products' => 'Back to products',
                'back_to_videos' => 'Back to videos',
                'back_to_gallery' => 'Back to gallery',
                'edit' => 'Edit',
                'delete' => 'Delete',
                'new_album' => 'New album',
                'new_event' => 'New event',
                'new_post' => 'New post',
                'new_product' => 'New product',
                'new_video' => 'New video',
                'new_image' => 'New image',
                'edit_album' => 'Edit album',
                'edit_event' => 'Edit event',
                'edit_post' => 'Edit post',
                'edit_product' => 'Edit product',
                'edit_video' => 'Edit video',
                'edit_image' => 'Edit image',
                'delete_confirm_album' => 'Delete this album?',
                'delete_confirm_event' => 'Delete this event?',
                'delete_confirm_post' => 'Delete this post?',
                'delete_confirm_product' => 'Delete this product?',
                'delete_confirm_video' => 'Delete this video?',
                'delete_confirm_image' => 'Delete this image?',
                'no_albums' => 'No albums yet.',
                'no_events' => 'No events yet.',
                'no_posts' => 'No posts yet.',
                'no_products' => 'No products yet.',
                'no_videos' => 'No videos yet.',
                'no_gallery' => 'No gallery images yet.',
                'users_label' => 'Users',
                'admins_label' => 'Admins',
                'albums_label' => 'Albums',
                'videos_gallery_label' => 'Videos / Gallery',
                'posts_label' => 'Posts',
                'products_label' => 'Products',
                'posts_heading' => 'Posts',
                'products_heading' => 'Products',
                'albums_heading' => 'Albums',
                'videos_heading' => 'Videos',
                'events_heading' => 'Events',
                'gallery_heading' => 'Gallery',
                'posts_copy' => 'Blog articles used by the public blog sections.',
                'products_copy' => 'Editable shop catalog used by the public store pages.',
                'albums_copy' => 'Editable discography items used across the public theme.',
                'videos_copy' => 'Featured videos and video listings.',
                'events_copy' => 'Public schedule used by the home page and upcoming shows block.',
                'gallery_copy' => 'Public gallery images ordered by sort position.',
                'dashboard_albums_button' => 'Albums',
                'dashboard_videos_button' => 'Videos',
                'dashboard_gallery_button' => 'Gallery',
                'dashboard_events_button' => 'Events',
                'dashboard_posts_button' => 'Posts',
                'dashboard_products_button' => 'Products',
                'dashboard_logo_label' => 'Logo',
                'dashboard_background_label' => 'Background',
                'dashboard_fonts_label' => 'Fonts',
                'dashboard_accent_label' => 'Accent',
                'brand_display_mode_help' => 'Choose whether the public header shows the wordmark or the uploaded logo.',
                'brand_mark_help' => 'Text visible on the public header logo.',
                'brand_preview_accent_label' => 'Accent',
                'brand_preview_nav_label' => 'Nav',
                'brand_preview_surface_label' => 'Surface',
                'brand_preview_body_head_label' => 'Body / Head',
                'home_headings_json_label' => 'Home headings JSON',
                'featured_stories_json_label' => 'Featured stories JSON',
                'latest_podcasts_json_label' => 'Latest podcasts JSON',
                'ui_texts_json_label' => 'UI texts JSON',
                'admin_texts_json_label' => 'Admin texts JSON',
                'contact_form_title_label' => 'Form title',
                'contact_info_title_label' => 'Info title',
                'contact_description_label' => 'Description',
                'address_label' => 'Address',
                'contact_email_label' => 'Email',
                'notification_email_label' => 'Correo de notificación',
                'notification_copy_email_label' => 'Correo copia',
                'notification_from_email_label' => 'Correo remitente',
                'notification_reply_to_email_label' => 'Responder a',
                'notification_mailer_label' => 'Mailer',
                'contact_phone_primary_label' => 'Phone primary',
                'contact_phone_secondary_label' => 'Phone secondary',
                'facebook_label' => 'Facebook',
                'instagram_label' => 'Instagram',
                'youtube_label' => 'YouTube',
                'tiktok_label' => 'TikTok',
                'x_label' => 'X',
                'table_title' => 'Title',
                'table_artist' => 'Artist',
                'table_published' => 'Published',
                'table_categories' => 'Categories',
                'table_status' => 'Status',
                'table_actions' => 'Actions',
                'table_cover' => 'Cover',
                'table_starts_at' => 'Starts at',
                'table_location' => 'Location',
                'table_venue' => 'Venue',
                'table_released' => 'Released',
                'table_sort' => 'Sort',
                'table_caption' => 'Caption',
                'table_image' => 'Image',
                'table_category' => 'Category',
                'table_price' => 'Price',
                'table_slug' => 'Slug',
                'table_youtube_url' => 'Youtube URL',
                'status_published' => 'Published',
                'status_draft' => 'Draft',
                'form_title_label' => 'Title',
                'form_slug_label' => 'Slug',
                'form_artist_label' => 'Artist',
                'form_released_at_label' => 'Released at',
                'cover_image_path_label' => 'Cover image path',
                'cover_image_file_label' => 'Cover image file',
                'summary_label' => 'Summary',
                'tracks_label' => 'Tracks',
                'buy_links_label' => 'Buy links',
                'tracks_placeholder' => 'Title|3:58',
                'buy_links_placeholder' => 'Label|https://example.com',
                'price_label' => 'Price',
                'regular_price_label' => 'Regular price',
                'category_field_label' => 'Category',
                'sort_order_label' => 'Sort order',
                'image_path_label' => 'Image path',
                'image_file_label' => 'Image file',
                'description_label' => 'Description',
                'sale_label' => 'Sale',
                'published_state_label' => 'Published',
                'youtube_url_label' => 'YouTube URL',
                'starts_at_label' => 'Starts at',
                'ends_at_label' => 'Ends at',
                'location_label' => 'Location',
                'venue_label' => 'Venue',
                'ticket_url_label' => 'Ticket URL',
                'ticket_label_label' => 'Ticket label',
                'caption_label' => 'Caption',
                'login_email_label' => 'Email',
                'login_password_label' => 'Password',
                'login_button' => 'Log in',
                'remember_me' => 'Remember me',
                'create_album_copy' => 'Create a discography entry used on the public site.',
                'update_album_copy' => 'Update the album used by the public theme.',
                'create_product_copy' => 'Create a shop item used by the public store pages.',
                'update_product_copy' => 'Update the shop item content and metadata.',
                'create_gallery_copy' => 'Create a tile used by the public gallery.',
                'create_post_copy' => 'Create a blog post used by public blog views.',
                'update_post_copy' => 'Update the blog post content and metadata.',
                'create_event_copy' => 'Create a public schedule item for the home page and events listing.',
                'update_event_copy' => 'Update the data used by the public schedule.',
                'create_video_copy' => 'Create a featured video entry.',
                'update_video_copy' => 'Update the featured video data.',
            ],
            'hero_video_path' => null,
            'hero_video_url' => null,
            'hero_video_disabled' => false,
            'social_facebook' => null,
            'social_instagram' => null,
            'social_youtube' => null,
            'social_tiktok' => null,
            'social_x' => null,
            'body_font' => 'Open Sans',
            'heading_font' => 'Oswald',
            'brand_mark_font' => 'Rock Salt',
            'accent_color' => '#c32720',
            'nav_color' => '#081a24',
            'surface_color' => '#101012',
            'body_color' => '#7b7b7b',
            'heading_color' => '#dcdcdc',
            'line_color' => '#757575',
        ];
    }

    public static function current(): self
    {
        if (! filter_var(env('THEME_SETTINGS_FROM_DB', true), FILTER_VALIDATE_BOOLEAN)) {
            return new static(static::defaults());
        }

        try {
            if (! Schema::hasTable('theme_settings')) {
                return new static(static::defaults());
            }

            return static::query()->first() ?? static::query()->create(static::defaults());
        } catch (\Throwable) {
            return new static(static::defaults());
        }
    }

    public function visual(): array
    {
        return [
            'site_name' => $this->site_name,
            'brand_mark' => $this->brand_mark ?: ($this->site_name ?: 'Seven Rock Radio'),
            'brand_mark_font' => $this->brand_mark_font ?: 'Rock Salt',
            'brand_display_mode' => $this->brand_display_mode ?: 'mark',
            'body_font' => $this->body_font,
            'heading_font' => $this->heading_font,
            'accent_color' => $this->accent_color,
            'nav_color' => $this->nav_color,
            'surface_color' => $this->surface_color,
            'body_color' => $this->body_color,
            'heading_color' => $this->heading_color,
            'line_color' => $this->line_color,
        ];
    }

    public function media(): array
    {
        return [
            'logo_path' => $this->logo_path,
            'background_path' => $this->background_path,
            'hero_slide_primary_path' => $this->hero_slide_primary_path,
            'hero_slide_secondary_path' => $this->hero_slide_secondary_path,
            'home_album_cover_path' => $this->home_album_cover_path,
            'home_video_image_path' => $this->home_video_image_path,
            'contact_form_title' => $this->contact_form_title,
            'contact_info_title' => $this->contact_info_title,
            'contact_description' => $this->contact_description,
            'contact_address' => $this->contact_address,
            'contact_email' => $this->contact_email,
            'contact_phone_primary' => $this->contact_phone_primary,
            'contact_phone_secondary' => $this->contact_phone_secondary,
            'hero_video_path' => $this->hero_video_path,
            'hero_video_url' => $this->hero_video_url,
            'hero_video_disabled' => (bool) $this->hero_video_disabled,
        ];
    }

    public function links(): array
    {
        return [
            'facebook' => $this->social_facebook,
            'instagram' => $this->social_instagram,
            'youtube' => $this->social_youtube,
            'tiktok' => $this->social_tiktok,
            'x' => $this->social_x,
        ];
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->resolveAsset($this->logo_path, 'assets/lucille/logo.png');
    }

    public function getBackgroundUrlAttribute(): string
    {
        return $this->resolveAsset($this->background_path, 'assets/lucille/dark-background.jpg');
    }

    public function getHeroSlidePrimaryUrlAttribute(): string
    {
        return $this->resolveAsset($this->hero_slide_primary_path, 'assets/lucille/audience_opt.jpg');
    }

    public function getHeroSlideSecondaryUrlAttribute(): string
    {
        return $this->resolveAsset($this->hero_slide_secondary_path, 'assets/lucille/live-slider-bg.jpg');
    }

    public function getHomeAlbumCoverUrlAttribute(): string
    {
        return $this->resolveAsset($this->home_album_cover_path, 'assets/lucille/album3.jpg');
    }

    public function getHomeVideoImageUrlAttribute(): string
    {
        return $this->resolveAsset($this->home_video_image_path, 'assets/lucille/freedom-at-21-header.jpg');
    }

    public function getHeroVideoMediaUrlAttribute(): string
    {
        return $this->resolveAsset($this->hero_video_path, null);
    }

    public function getGoogleFontsUrlAttribute(): string
    {
        $fonts = collect([$this->body_font, $this->heading_font, $this->brand_mark_font ?: 'Rock Salt'])->filter()->unique()->values();

        $query = $fonts->map(function (string $font): string {
            return $font === 'Rock Salt'
                ? 'family=Rock+Salt:wght@400'
                : 'family='.str_replace(' ', '+', $font).':wght@300;400;700';
        })->implode('&');

        return 'https://fonts.googleapis.com/css2?'.$query.'&display=swap';
    }

    public function resolvedVisual(): array
    {
        return array_merge($this->visual(), [
            'google_fonts_url' => $this->google_fonts_url,
        ]);
    }

    public function resolvedMedia(): array
    {
        return array_merge($this->media(), [
            'logo_url' => $this->logo_url,
            'background_url' => $this->background_url,
            'hero_slide_primary_url' => $this->hero_slide_primary_url,
            'hero_slide_secondary_url' => $this->hero_slide_secondary_url,
            'home_album_cover_url' => $this->home_album_cover_url,
            'home_video_image_url' => $this->home_video_image_url,
            'hero_video_media_url' => $this->hero_video_media_url,
        ]);
    }

    public function contact(): array
    {
        $defaults = static::defaults();

        return [
            'form_title' => $this->contact_form_title ?: $defaults['contact_form_title'],
            'info_title' => $this->contact_info_title ?: $defaults['contact_info_title'],
            'description' => $this->contact_description ?: $defaults['contact_description'],
            'address' => $this->contact_address ?: $defaults['contact_address'],
            'email' => $this->contact_email ?: $defaults['contact_email'],
            'phone_primary' => $this->contact_phone_primary ?: $defaults['contact_phone_primary'],
            'phone_secondary' => $this->contact_phone_secondary ?: $defaults['contact_phone_secondary'],
        ];
    }

    public function featuredStories(): array
    {
        return $this->mergeSectionDefaults('featured_stories');
    }

    public function latestPodcasts(): array
    {
        return $this->mergeSectionDefaults('latest_podcasts');
    }

    public function homeHeadings(): array
    {
        return $this->mergeSectionDefaults('home_headings');
    }

    public function uiTexts(): array
    {
        return $this->mergeSectionDefaults('ui_texts');
    }

    public function adminTexts(): array
    {
        return $this->mergeSectionDefaults('admin_texts');
    }

    public function resolvedLinks(): array
    {
        $socialLinks = array_values(array_filter([
            ['network' => 'facebook', 'url' => $this->social_facebook, 'icon' => 'facebook'],
            ['network' => 'instagram', 'url' => $this->social_instagram, 'icon' => 'instagram'],
            ['network' => 'youtube', 'url' => $this->social_youtube, 'icon' => 'youtube-play'],
            ['network' => 'tiktok', 'url' => $this->social_tiktok, 'icon' => 'music'],
            ['network' => 'x', 'url' => $this->social_x, 'icon' => 'twitter'],
        ], static fn (array $item): bool => trim((string) $item['url']) !== ''));

        return array_merge($this->links(), [
            'social_links' => $socialLinks,
        ]);
    }

    public function resolveAsset(?string $path, ?string $fallback): string
    {
        if (! $path) {
            return $fallback ? asset($fallback) : '';
        }

        if ($resolved = PublicMediaUrl::normalizePublicUrl($path)) {
            return $resolved;
        }

        return $fallback ? asset($fallback) : '';
    }

    private function mergeSectionDefaults(string $key): array
    {
        $defaults = static::defaults();
        $stored = $this->{$key};
        $default = $defaults[$key] ?? [];

        if (! is_array($stored) || $stored === []) {
            return $this->normalizeSectionMedia($default);
        }

        return $this->normalizeSectionMedia(array_replace_recursive($default, $stored));
    }

    private function normalizeSectionMedia(array $payload): array
    {
        if (isset($payload['featured']['image'])) {
            $payload['featured']['image'] = $this->resolveAsset((string) $payload['featured']['image'], (string) $payload['featured']['image']);
        }

        foreach (['stories', 'episodes'] as $listKey) {
            if (! isset($payload[$listKey]) || ! is_array($payload[$listKey])) {
                continue;
            }

            $payload[$listKey] = array_map(function (array $item): array {
                if (isset($item['image'])) {
                    $item['image'] = $this->resolveAsset((string) $item['image'], (string) $item['image']);
                }

                return $item;
            }, $payload[$listKey]);
        }

        return $payload;
    }
}
