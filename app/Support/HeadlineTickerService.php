<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Notice;
use App\Support\PublicMediaUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class HeadlineTickerService
{
    public function __construct(
        private readonly ProgramScheduleService $programScheduleService,
    ) {
    }

    public function resolve(int $limit = 8): array
    {
        $items = collect()
            ->merge($this->programItems())
            ->merge($this->noticeItems())
            ->merge($this->postItems());

        if ($items->isEmpty()) {
            $fallback = $this->programScheduleService->fallback();

            return [
                'label' => 'Editorial feed',
                'subtitle' => 'Latest headlines',
                'items' => [
                    [
                        'label' => 'PROGRAM',
                        'title' => (string) data_get($fallback, 'show', 'Seven Rock Radio'),
                        'meta' => (string) data_get($fallback, 'subtitle', 'Programación editorial'),
                        'url' => route('events'),
                    ],
                ],
            ];
        }

        return [
            'label' => 'Editorial feed',
            'subtitle' => 'Latest headlines',
            'items' => $items->take($limit)->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array{label:string,title:string,meta:string,url:?string,tone:string,image:string}>
     */
    private function programItems(): Collection
    {
        $program = $this->programScheduleService->resolve(1);
        $show = trim((string) data_get($program, 'show', ''));

        if ($show === '') {
            return collect();
        }

        return collect([[
            'label' => 'PROGRAM',
            'title' => $show,
            'meta' => trim(implode(' · ', array_filter([
                (string) data_get($program, 'schedule', ''),
                (string) data_get($program, 'host', ''),
            ]))),
            'url' => data_get($program, 'button.url', route('events')),
            'tone' => 'accent',
            'image' => PublicMediaUrl::normalizePublicUrl((string) data_get($program, 'image', '')),
        ]]);
    }

    /**
     * @return Collection<int, array{label:string,title:string,meta:string,url:?string,tone:string,image:string}>
     */
    private function noticeItems(): Collection
    {
        if (! Schema::hasTable('notices')) {
            return collect();
        }

        return Notice::query()
            ->active()
            ->orderBy('sort_order')
            ->latest('id')
            ->limit(2)
            ->get()
            ->map(function (Notice $notice): array {
                $meta = trim(Str::limit(strip_tags((string) $notice->content), 80, ''));

                return [
                    'label' => strtoupper($notice->type ?: 'ALERT'),
                    'title' => $notice->title,
                    'meta' => $meta !== '' ? $meta : 'Aviso editorial activo',
                    'url' => null,
                    'tone' => $notice->type === 'warning' ? 'warning' : 'muted',
                    'image' => '',
                ];
            });
    }

    /**
     * @return Collection<int, array{label:string,title:string,meta:string,url:?string,tone:string,image:string}>
     */
    private function postItems(): Collection
    {
        if (! Schema::hasTable('posts')) {
            return collect();
        }

        return DB::table('posts')
            ->where('status', 'published')
            ->latest('published_at')
            ->limit(4)
            ->get()
            ->map(function (object $post): array {
                $publishedAt = ! empty($post->published_at)
                    ? Carbon::parse((string) $post->published_at)
                    : null;
                $summary = $this->summaryFromContent($post->content ?? '');

                return [
                    'label' => 'NEWS',
                    'title' => (string) $post->title,
                    'meta' => trim(implode(' · ', array_filter([
                        $publishedAt?->format('d M Y') ?? '',
                        'admin',
                    ]))),
                    'url' => route('posts.single', [
                        'year' => $publishedAt?->format('Y') ?? now()->format('Y'),
                        'month' => $publishedAt?->format('m') ?? now()->format('m'),
                        'day' => $publishedAt?->format('d') ?? now()->format('d'),
                        'slug' => (string) $post->slug,
                    ]),
                    'summary' => $summary,
                    'tone' => 'story',
                    'image' => PublicMediaUrl::normalizePublicUrl((string) ($post->featured_image_path ?? '')),
                ];
            });
    }

    private function summaryFromContent(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return 'Última publicación editorial disponible en el sitio.';
        }

        $blocks = json_decode($content, true);
        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                $html = data_get($block, 'content.html');
                if (! is_string($html)) {
                    continue;
                }

                $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
                if ($text !== '') {
                    return Str::limit($text, 120, '');
                }
            }
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');

        return $text !== '' ? Str::limit($text, 120, '') : 'Última publicación editorial disponible en el sitio.';
    }
}
