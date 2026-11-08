<?php

namespace Tests\Unit;

use App\Models\ThemeSetting;
use Tests\TestCase;

class ContactEmailSettingsTest extends TestCase
{
    public function test_theme_setting_defaults_use_sevenrockradio_contact_email(): void
    {
        $defaults = ThemeSetting::defaults();
        $this->assertSame('sevenrockradio@gmail.com', $defaults['contact_email']);
    }

    public function test_theme_setting_contact_helper_falls_back_to_sevenrockradio_email(): void
    {
        $setting = new ThemeSetting([
            'contact_email' => null,
        ]);

        $this->assertSame('sevenrockradio@gmail.com', $setting->contact()['email']);

        $settingEmpty = new ThemeSetting([
            'contact_email' => '',
        ]);

        $this->assertSame('sevenrockradio@gmail.com', $settingEmpty->contact()['email']);
    }

    public function test_theme_setting_contact_helper_respects_custom_email(): void
    {
        $setting = new ThemeSetting([
            'contact_email' => 'custom@sevenrockradio.com',
        ]);

        $this->assertSame('custom@sevenrockradio.com', $setting->contact()['email']);
    }
}

