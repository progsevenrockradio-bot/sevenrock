<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RadioArtist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBandProfileEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_load_edit_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $artist = RadioArtist::query()->create([
            'name' => 'Metallica',
            'editorial_summary' => 'Heavy metal band.',
            'source' => 'test',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.radio-artists.edit', $artist));

        $response->assertOk();
        $response->assertSee('Metallica');
    }

    public function test_admin_can_update_profile(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $artist = RadioArtist::query()->create([
            'name' => 'Metallica',
            'editorial_summary' => 'Heavy metal band.',
            'source' => 'test',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.radio-artists.update', $artist), [
            'name' => 'Metallica Renamed',
            'editorial_summary' => 'New summary.',
            'source' => 'test-updated',
        ]);

        $response->assertRedirect(route('admin.radio-artists.index'));

        $this->assertDatabaseHas('radio_artists', [
            'id' => $artist->id,
            'name' => 'Metallica Renamed',
        ]);
    }
}
