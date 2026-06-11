<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\NewRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewReleaseValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_new_release_without_audio(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.new-releases.store'), [
            'title' => 'Test Release Without Audio',
            'artist_name' => 'Test Artist',
            'cover_image' => 'catalog/releases/covers/portada.jpg',
            // No audio_path or audio_file is provided
        ]);

        $response->assertRedirect(route('admin.new-releases.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('new_releases', [
            'title' => 'Test Release Without Audio',
            'artist_name' => 'Test Artist',
            'audio_path' => null,
        ]);
    }
}
