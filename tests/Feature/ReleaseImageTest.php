<?php

namespace Tests\Feature;

use Tests\TestCase;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Support\AttachmentCollection;
use App\Services\PostImageResolver;
use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReleaseImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        \Illuminate\Support\Facades\DB::table('theme_settings')->insert([
            'email_default_cover_path' => 'assets/lucille/default-test.jpg',
            'press_feeds_extra' => "",
            'site_name' => 'Test',
        ]);
        
        $reflection = new \ReflectionClass(ThemeSetting::class);
        $property = $reflection->getProperty('currentSettings');
        $property->setAccessible(true);
        $property->setValue(null);

        Storage::fake('public');
    }

    private function createMockMessage(array $attachmentsData)
    {
        $message = \Mockery::mock(Message::class);
        $attachments = new AttachmentCollection();
        
        foreach ($attachmentsData as $data) {
            $attachment = \Mockery::mock(Attachment::class);
            $attachment->shouldReceive('getName')->andReturn($data['name']);
            $attachment->shouldReceive('getContent')->andReturn($data['content']);
            $attachments->push($attachment);
        }

        $message->shouldReceive('getAttachments')->andReturn($attachments);
        return $message;
    }

    private function createDummyImage(int $width, int $height, string $format = 'jpeg'): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        if ($format === 'png') {
            imagepng($image);
        } else {
            imagejpeg($image);
        }
        $content = ob_get_clean();
        imagedestroy($image);
        
        return str_pad($content, 50000, '0');
    }

    public function test_detects_image_without_extension()
    {
        $resolver = new PostImageResolver();
        
        $imageContent = $this->createDummyImage(300, 300);
        
        $message = $this->createMockMessage([
            [
                'name' => '7bc8e21b',
                'content' => $imageContent
            ]
        ]);

        $result = $resolver->resolveForPost([
            'message' => $message,
            'body' => '',
            'clean_title' => 'Test Release',
            'is_dark_vader' => false,
        ]);

        $this->assertEquals('attachment', $result['source']);
        $this->assertStringContainsString('.jpg', $result['url']);
    }

    public function test_prioritizes_square_image()
    {
        $resolver = new PostImageResolver();
        
        $bannerContent = $this->createDummyImage(1572, 263); 
        $squareContent = $this->createDummyImage(300, 300, 'png'); 

        $message = $this->createMockMessage([
            [
                'name' => 'banner_no_ext',
                'content' => $bannerContent
            ],
            [
                'name' => 'cover_no_ext',
                'content' => $squareContent
            ]
        ]);

        $result = $resolver->resolveForPost([
            'message' => $message,
            'body' => '',
            'clean_title' => 'Test Release',
            'is_dark_vader' => false,
        ]);

        $this->assertEquals('attachment', $result['source']);
        // If it picked the square one, it should be png, since we passed 'png' for it
        $this->assertStringContainsString('.png', $result['url']);
    }

    public function test_ignores_text_without_extension()
    {
        $resolver = new PostImageResolver();
        
        $message = $this->createMockMessage([
            [
                'name' => 'readme_no_ext',
                'content' => str_pad('This is just a text file.', 50000, '0')
            ]
        ]);

        $result = $resolver->resolveForPost([
            'message' => $message,
            'body' => '',
            'clean_title' => 'Test Release',
            'is_dark_vader' => false,
        ]);

        $this->assertEquals('default', $result['source']);
    }

    public function test_ignores_small_icon()
    {
        $resolver = new PostImageResolver();
        
        $iconContent = $this->createDummyImage(64, 64);
        
        $message = $this->createMockMessage([
            [
                'name' => 'icon_no_ext',
                'content' => $iconContent
            ]
        ]);

        $result = $resolver->resolveForPost([
            'message' => $message,
            'body' => '',
            'clean_title' => 'Test Release',
            'is_dark_vader' => false,
        ]);

        $this->assertEquals('default', $result['source']);
    }
}
