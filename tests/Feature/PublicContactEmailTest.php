<?php

namespace Tests\Feature;

use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContactEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ThemeSetting::query()->delete();
    }

    public function test_privacy_policy_displays_public_contact_email(): void
    {
        ThemeSetting::create([
            'contact_email' => 'sevenrockradio@gmail.com',
        ]);

        $response = $this->get(route('privacy-policy'));

        $response->assertStatus(200);
        $response->assertSee('sevenrockradio@gmail.com');
        $response->assertDontSee('prog.sevenrockradio@gmail.com');
    }

    public function test_privacy_policy_falls_back_to_sevenrockradio_when_setting_empty(): void
    {
        ThemeSetting::create([
            'contact_email' => null,
        ]);

        $response = $this->get(route('privacy-policy'));

        $response->assertStatus(200);
        $response->assertSee('sevenrockradio@gmail.com');
        $response->assertDontSee('prog.sevenrockradio@gmail.com');
    }

    public function test_copyright_policy_displays_public_contact_email(): void
    {
        ThemeSetting::create([
            'contact_email' => 'sevenrockradio@gmail.com',
        ]);

        $response = $this->get(route('copyright-policy'));

        $response->assertStatus(200);
        $response->assertSee('sevenrockradio@gmail.com');
        $response->assertDontSee('prog.sevenrockradio@gmail.com');
    }

    public function test_copyright_policy_falls_back_to_sevenrockradio_when_setting_empty(): void
    {
        ThemeSetting::create([
            'contact_email' => null,
        ]);

        $response = $this->get(route('copyright-policy'));

        $response->assertStatus(200);
        $response->assertSee('sevenrockradio@gmail.com');
        $response->assertDontSee('prog.sevenrockradio@gmail.com');
    }

    public function test_plan_basico_displays_public_contact_email(): void
    {
        ThemeSetting::create([
            'contact_email' => 'sevenrockradio@gmail.com',
        ]);

        $response = $this->get(route('talents.plans.basic'));

        $response->assertStatus(200);
        $response->assertSee('sevenrockradio@gmail.com');
        $response->assertDontSee('prog.sevenrockradio@gmail.com');
    }

    public function test_plan_basico_falls_back_to_sevenrockradio_when_setting_empty(): void
    {
        ThemeSetting::create([
            'contact_email' => null,
        ]);

        $response = $this->get(route('talents.plans.basic'));

        $response->assertStatus(200);
        $response->assertSee('sevenrockradio@gmail.com');
        $response->assertDontSee('prog.sevenrockradio@gmail.com');
    }

    public function test_plan_pro_displays_public_contact_email(): void
    {
        ThemeSetting::create([
            'contact_email' => 'sevenrockradio@gmail.com',
        ]);

        $response = $this->get(route('talents.plans.pro'));

        $response->assertStatus(200);
        $response->assertSee('sevenrockradio@gmail.com');
        $response->assertDontSee('prog.sevenrockradio@gmail.com');
    }

    public function test_plan_pro_falls_back_to_sevenrockradio_when_setting_empty(): void
    {
        ThemeSetting::create([
            'contact_email' => null,
        ]);

        $response = $this->get(route('talents.plans.pro'));

        $response->assertStatus(200);
        $response->assertSee('sevenrockradio@gmail.com');
        $response->assertDontSee('prog.sevenrockradio@gmail.com');
    }

    public function test_contact_page_displays_public_contact_email(): void
    {
        ThemeSetting::create([
            'contact_email' => 'sevenrockradio@gmail.com',
        ]);

        $response = $this->get(route('contact'));

        $response->assertStatus(200);
        $response->assertSee('sevenrockradio@gmail.com');
        $response->assertDontSee('prog.sevenrockradio@gmail.com');
    }

    public function test_contact_page_falls_back_to_sevenrockradio_when_setting_empty(): void
    {
        ThemeSetting::create([
            'contact_email' => null,
        ]);

        $response = $this->get(route('contact'));

        $response->assertStatus(200);
        $response->assertSee('sevenrockradio@gmail.com');
        $response->assertDontSee('prog.sevenrockradio@gmail.com');
    }
}
