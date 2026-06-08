<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\BandProfile;
use App\Support\BandInfoAggregator;
use App\Support\BandInfoResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BandInfoResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->app->instance(BandInfoAggregator::class, new class
        {
            public function aggregate(string $artist): array
            {
                return [
                    'summary' => '',
                    'thumbnail' => '',
                    'social_links' => [],
                    'formed_year' => null,
                    'formed_label' => '',
                    'facts' => [],
                ];
            }
        });
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_it_uses_a_fuzzy_local_profile_when_external_enrichment_fails(): void
    {
        BandProfile::query()->create([
            'name' => 'Metallica',
            'biography' => '',
            'editorial_summary' => 'Founded in 1981 in Los Angeles.',
            'featured_facts' => ['Thrash metal pioneers'],
            'related_artists' => ['Metallica'],
            'official_links' => [],
            'source' => 'test',
        ]);

        $payload = app(BandInfoResolver::class)->resolve('Metallicaa');

        $this->assertSame('Founded in 1981 in Los Angeles.', $payload['summary']);
        $this->assertSame(1981, $payload['formed_year']);
        $this->assertSame('Se formó en 1981', $payload['formed_label']);
    }
}
