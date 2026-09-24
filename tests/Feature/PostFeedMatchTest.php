<?php

namespace Tests\Feature;

use App\Models\ThemeSetting;
use App\Services\PostImageResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Support\AttachmentCollection;

/**
 * Tests para el emparejamiento por entidades entre titulares en español
 * y feeds RSS en inglés (Blabbermouth y similares).
 */
class PostFeedMatchTest extends TestCase
{
    private const FEED_URL = 'https://blabbermouth.net/feed/';

    private const FEED_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>Blabbermouth</title>
    <item>
      <title>DEAD KENNEDYS Guitarist EAST BAY RAY Diagnosed With Parkinson's Disease</title>
      <link>https://blabbermouth.net/news/east-bay-ray-parkinsons</link>
      <media:content url="https://blabbermouth.net/img/east-bay-ray.jpg" medium="image"/>
    </item>
    <item>
      <title>RUSH Members And Fans Raise $1 Million For NEIL PEART Memorial In St. Catharines</title>
      <link>https://blabbermouth.net/news/neil-peart-memorial</link>
      <media:content url="https://blabbermouth.net/img/neil-peart.jpg" medium="image"/>
    </item>
    <item>
      <title>ROSE TATTOO Frontman ANGRY ANDERSON Dead At 79</title>
      <link>https://blabbermouth.net/news/angry-anderson-dead</link>
    </item>
  </channel>
</rss>
XML;

    protected function setUp(): void
    {
        parent::setUp();

        // Inyectamos ThemeSetting directamente en el cache estático sin tocar la BD.
        // Así evitamos RefreshDatabase (que internamente usa PendingCommand/mockConsoleOutput
        // y crea un Mockery mock de OutputStyle que choca en tearDown).
        $settings = new ThemeSetting();
        $settings->forceFill([
            'email_default_cover_path' => 'assets/lucille/album3.jpg',
            'press_feeds_extra'        => 'blabbermouth.net=' . self::FEED_URL,
            'site_name'                => 'Seven Rock Radio Test',
        ]);

        $reflection = new \ReflectionClass(ThemeSetting::class);
        $property   = $reflection->getProperty('currentSettings');
        $property->setAccessible(true);
        $property->setValue(null, $settings);  // Inject directo, sin query DB

        Cache::flush();
    }

    protected function tearDown(): void
    {
        // Limpiar el cache estático para no contaminar otros tests
        $reflection = new \ReflectionClass(ThemeSetting::class);
        $property   = $reflection->getProperty('currentSettings');
        $property->setAccessible(true);
        $property->setValue(null, null);

        Mockery::close();
        parent::tearDown();
    }

    private function makeMessage(): Message
    {
        /** @var Message&\Mockery\MockInterface $message */
        $message = Mockery::mock(Message::class);
        $message->shouldReceive('getAttachments')->andReturn(AttachmentCollection::make([]));
        return $message;
    }

    private function resolver(): PostImageResolver
    {
        return app(PostImageResolver::class);
    }

    /**
     * Titular en español con entidades fuertes ("East Bay Ray", "Dead Kennedys")
     * debe emparejar con el ítem en inglés del feed.
     */
    public function test_spanish_title_matches_english_feed_item(): void
    {
        Http::fake([
            self::FEED_URL => Http::response(self::FEED_XML, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $result = $this->resolver()->resolveForPost([
            'message'       => $this->makeMessage(),
            'body'          => 'Creditos: blabbermouth.net',
            'subject'       => 'East Bay Ray (Dead Kennedys), diagnosticado de Parkinson',
            'clean_title'   => 'East Bay Ray (Dead Kennedys), diagnosticado de Parkinson',
            'is_dark_vader' => true,
            'artist_name'   => null,
        ]);

        $this->assertEquals('rss', $result['source'], 'Debe resolver desde el feed RSS');
        $this->assertEquals(
            'https://blabbermouth.net/img/east-bay-ray.jpg',
            $result['url'],
            'Debe devolver la imagen del ítem de East Bay Ray'
        );
        $this->assertStringContainsString('east-bay-ray', $result['article_url']);
    }

    /**
     * Un titular con solo una entidad débil de una palabra que coincide por azar
     * NO debe emparejar con ningún ítem del feed.
     */
    public function test_single_weak_entity_does_not_match(): void
    {
        Http::fake([
            self::FEED_URL => Http::response(self::FEED_XML, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $result = $this->resolver()->resolveForPost([
            'message'       => $this->makeMessage(),
            'body'          => 'Creditos: blabbermouth.net',
            'subject'       => 'Rock In Rio 2026 supera las 700.000 personas',
            'clean_title'   => 'Rock In Rio 2026 supera las 700.000 personas',
            'is_dark_vader' => true,
            'artist_name'   => null,
        ]);

        $this->assertNotEquals('rss', $result['source'], 'No debe resolver desde RSS con entidad débil');
        $this->assertEquals('default', $result['source'], 'Debe caer al cover por defecto');
    }

    /**
     * Cuando el ítem del feed no trae imagen directa pero sí URL de artículo,
     * se debe extraer el og:image de esa página.
     */
    public function test_item_without_image_falls_back_to_og_image(): void
    {
        $articleUrl = 'https://blabbermouth.net/news/angry-anderson-dead';
        $ogImageUrl = 'https://blabbermouth.net/og/angry-anderson.jpg';

        Http::fake([
            self::FEED_URL => Http::response(self::FEED_XML, 200, ['Content-Type' => 'application/rss+xml']),
            $articleUrl    => Http::response(
                '<html><head><meta property="og:image" content="' . $ogImageUrl . '"></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = $this->resolver()->resolveForPost([
            'message'       => $this->makeMessage(),
            'body'          => 'Creditos: blabbermouth.net',
            'subject'       => 'Muere Angry Anderson, vocalista de Rose Tattoo',
            'clean_title'   => 'Muere Angry Anderson, vocalista de Rose Tattoo',
            'is_dark_vader' => true,
            'artist_name'   => null,
        ]);

        $this->assertEquals('rss', $result['source'], 'Debe resolver desde RSS vía og:image de la página');
        $this->assertEquals($ogImageUrl, $result['url'], 'La imagen debe ser el og:image extraído');
        $this->assertEquals($articleUrl, $result['article_url']);
    }
}
