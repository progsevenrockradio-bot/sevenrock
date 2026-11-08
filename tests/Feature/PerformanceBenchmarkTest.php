<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_and_dom_optimizations_are_present(): void
    {
        // 1. Muro index contains <picture> with webp and png fallback, and aspect-ratio
        $muroResponse = $this->get('/muro-del-rock');
        $muroResponse->assertStatus(200);
        $muroContent = $muroResponse->getContent();
        $this->assertStringContainsString('muro-del-rock-banner.webp', $muroContent);
        $this->assertStringContainsString('muro-del-rock-banner.png', $muroContent);
        $this->assertStringContainsString('aspect-ratio: 1024 / 361', $muroContent);

        // 2. Home contains eager LCP candidate for slide 0, and preload
        $homeResponse = $this->get('/');
        $homeResponse->assertStatus(200);
        $homeContent = $homeResponse->getContent();
        $this->assertStringContainsString('fetchpriority="high"', $homeContent);
        $this->assertStringContainsString('loading="eager"', $homeContent);

        // 3. Multimedia audio preload="none"
        $multimediaResponse = $this->get('/multimedia');
        $multimediaResponse->assertStatus(200);
        $multimediaContent = $multimediaResponse->getContent();
        $this->assertStringContainsString('preload="none"', $multimediaContent);

        // 4. Events page responds 200
        $eventsResponse = $this->get('/events');
        $eventsResponse->assertStatus(200);
    }
}
