<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigureEditorialAutoPublishCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_status_shows_configuration(): void
    {
        ThemeSetting::clearCache();
        $settings = ThemeSetting::firstOrCreate([], ThemeSetting::defaults());
        $settings->email_auto_publish = false;
        $settings->save();
        ThemeSetting::clearCache();

        $this->artisan('editorial:configure-auto-publish --status')
            ->assertExitCode(0);
    }

    public function test_command_enable_activates_in_database(): void
    {
        $this->artisan('editorial:configure-auto-publish --enable')
            ->assertExitCode(0);

        ThemeSetting::clearCache();
        $this->assertTrue((bool) ThemeSetting::current()->email_auto_publish);
    }

    public function test_command_disable_deactivates_in_database(): void
    {
        ThemeSetting::clearCache();
        $settings = ThemeSetting::firstOrCreate([], ThemeSetting::defaults());
        $settings->email_auto_publish = true;
        $settings->save();
        ThemeSetting::clearCache();

        $this->artisan('editorial:configure-auto-publish --disable')
            ->assertExitCode(0);

        ThemeSetting::clearCache();
        $this->assertFalse((bool) ThemeSetting::current()->email_auto_publish);
    }
}
