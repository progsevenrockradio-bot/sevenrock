<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\CommunityPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityWallTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_community_pages(): void
    {
        $response = $this->get(route('comunidad.muro'));
        $response->assertRedirect(route('afiliados.login'));

        $response = $this->get(route('comunidad.exclusivos'));
        $response->assertRedirect(route('afiliados.login'));
    }

    public function test_fan_can_register_and_access_muro(): void
    {
        $response = $this->post(route('afiliados.register.store'), [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertRedirect(route('comunidad.muro'));
        $this->assertDatabaseHas('users', [
            'email' => 'johndoe@example.com',
        ]);

        $this->assertAuthenticated('web');
    }

    public function test_authenticated_fan_can_post_to_muro(): void
    {
        $fan = User::factory()->create();

        $response = $this->actingAs($fan, 'web')->post(route('comunidad.muro.post'), [
            'content' => 'Rock and Roll will never die!',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('community_posts', [
            'user_id' => $fan->id,
            'content' => 'Rock and Roll will never die!',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }

    public function test_exclusive_media_is_protected_from_guests(): void
    {
        $band = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'The Metalheads',
            'email' => 'metalheads@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
        ]);

        $media = TalentMedia::query()->create([
            'talent_id' => $band->id,
            'type' => 'mp3',
            'filename' => 'unreleased_track.mp3',
            'backblaze_key' => 'key123',
            'url' => 'https://b2.com/file/unreleased_track.mp3',
            'title' => 'Exclusive Song Title',
            'mime_type' => 'audio/mpeg',
            'size' => 1024 * 1024,
            'is_exclusive' => true,
        ]);

        // 1. Guest profile visit: Should be locked
        $response = $this->get(route('talents.show', $band->band_name));
        $response->assertStatus(200);
        $response->assertSee('Exclusivo para Afiliados');
        $response->assertDontSee('unreleased_track.mp3');

        // 2. Authenticated fan profile visit: Should be unlocked
        $fan = User::factory()->create();
        $response = $this->actingAs($fan, 'web')->get(route('talents.show', $band->band_name));
        $response->assertStatus(200);
        $response->assertDontSee('Exclusivo para Afiliados');
        $response->assertSee('Exclusive Song Title');
    }
}
