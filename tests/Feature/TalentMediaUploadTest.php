<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Talent;
use App\Models\TalentMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TalentMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backblaze');
    }

    public function test_free_plan_enforces_max_three_songs_limit(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'FreeBand',
            'email' => 'freeband@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
        ]);

        // Upload 3 songs (the limit for free plan)
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->actingAs($talent, 'talent')
                ->post(route('talents.media.upload'), [
                    'type' => 'mp3',
                    'title' => "Song {$i}",
                    'file' => UploadedFile::fake()->create("song{$i}.mp3", 100), // 100 KB
                ]);
            $response->assertRedirect(route('talents.media.index'));
        }

        // Try uploading a 4th song (which should exceed the limit)
        $response = $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Song 4',
                'file' => UploadedFile::fake()->create('song4.mp3', 100),
            ]);

        $response->assertSessionHasErrors(['type']);
        $this->assertEquals(3, TalentMedia::query()->where('talent_id', $talent->id)->where('type', 'mp3')->count());
    }

    public function test_free_plan_enforces_twelve_megabytes_limit_per_file(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'FreeSizeBand',
            'email' => 'freesize@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
        ]);

        // Trying to upload 13MB file (exceeds 12MB free plan limit)
        $response = $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Heavy Song',
                'file' => UploadedFile::fake()->create('heavy.mp3', 13 * 1024), // 13 MB
            ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertEquals(0, TalentMedia::query()->where('talent_id', $talent->id)->count());
    }

    public function test_basic_plan_enforces_fifteen_megabytes_limit_per_file(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'BasicSizeBand',
            'email' => 'basicsize@example.com',
            'password' => 'password123',
            'plan' => 'basic',
            'subscription_status' => 'active',
        ]);

        // Trying to upload 16MB file (exceeds 15MB basic plan limit)
        $response = $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Heavy Song Basic',
                'file' => UploadedFile::fake()->create('heavy.mp3', 16 * 1024), // 16 MB
            ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertEquals(0, TalentMedia::query()->where('talent_id', $talent->id)->count());
    }
}
