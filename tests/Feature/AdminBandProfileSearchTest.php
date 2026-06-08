<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BandProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBandProfileSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_band_profile_search_returns_fuzzy_matches(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        BandProfile::query()->create([
            'name' => 'Metallica',
            'editorial_summary' => 'Heavy metal band.',
            'source' => 'test',
        ]);

        BandProfile::query()->create([
            'name' => 'Megadeth',
            'editorial_summary' => 'Thrash metal band.',
            'source' => 'test',
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.radio-artists.search', ['q' => 'metallika']));

        $response->assertOk();
        $response->assertJsonPath('data.results.0.text', 'Metallica');
    }
}
