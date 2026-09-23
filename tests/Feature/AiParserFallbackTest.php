<?php

namespace Tests\Feature;

use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Services\AiParserManager;
use Tests\TestCase;

class AiParserFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        ThemeSetting::create([
            'gemini_api_key' => 'fake_gemini_key',
            'openrouter_api_key' => 'fake_or_key',
            'ai_fallback_enabled' => true,
            'ai_provider_chain' => 'gemini,openrouter',
            'email_processing_enabled' => true,
            'email_auto_publish' => true,
        ]);
        
        config([
            'services.gemini.api_key' => 'fake_gemini_key',
            'services.openrouter.api_key' => 'fake_or_key',
            'services.ai.provider_chain' => ['gemini', 'openrouter'],
        ]);
    }

    public function test_gemini_402_triggers_openrouter_fallback()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('payment_required', 402),
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'type' => 'post',
                                'importance' => 4,
                                'title' => 'OpenRouter parsed this',
                                'excerpt' => 'Short summary',
                                'content' => 'Full content'
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $manager = app(AiParserManager::class);
        $result = $manager->parse('Subject', 'Body text');

        $this->assertNotNull($result);
        $this->assertEquals('post', $result['type']);
        $this->assertEquals('OpenRouter parsed this', $result['title']);
        $this->assertEquals('openrouter', $manager->lastProvider);
    }

    public function test_both_providers_fail_returns_null()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('payment_required', 402),
            'openrouter.ai/*' => Http::response('server_error', 500)
        ]);

        $manager = app(AiParserManager::class);
        $result = $manager->parse('Subject', 'Body text');

        $this->assertNull($result);
        $this->assertStringContainsString('gemini: payment_required', $manager->lastError);
        $this->assertStringContainsString('openrouter: http_500', $manager->lastError);
    }
}
