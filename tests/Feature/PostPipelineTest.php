<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Webklex\PHPIMAP\ClientManager;

class PostPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $reflection = new \ReflectionClass(ThemeSetting::class);
        $property = $reflection->getProperty('currentSettings');
        $property->setAccessible(true);
        $property->setValue(null);

        $settings = ThemeSetting::current();
        $settings->update([
            'email_processing_enabled' => true,
            'email_auto_publish' => true,
            'email_whitelist_senders' => 'redaccion@sevenrockradio.com',
            'email_daily_posts_limit' => 2,
            'post_duplicate_similarity_threshold' => 0.82,
            'imap_username' => 'test@test.com',
            'imap_password' => 'secret',
        ]);
        $settings->gemini_api_key = 'fake_key';
        $settings->save();
        config(['services.gemini.api_key' => 'fake_key']);
    }

    private function mockImapMessages(array $messages)
    {
        $mockFolder = \Mockery::mock(\Webklex\PHPIMAP\Folder::class);
        $mockQuery = \Mockery::mock(\Webklex\PHPIMAP\Query\WhereQuery::class);
        
        $mockFolder->shouldReceive('query')->andReturn($mockQuery);
        $mockQuery->shouldReceive('unseen')->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn(new \Webklex\PHPIMAP\Support\MessageCollection($messages));

        $mockClient = new class extends \Webklex\PHPIMAP\Client {
            public ?\Webklex\PHPIMAP\Folder $mockFolder = null;
            public function __construct() {}
            public function connect(): \Webklex\PHPIMAP\Client { return $this; }
            public function getFolder(string $folder_name = 'INBOX', ?string $delimiter = null, bool $utf7 = false): ?\Webklex\PHPIMAP\Folder { return $this->mockFolder; }
        };
        $mockClient->mockFolder = $mockFolder;

        $mockManager = \Mockery::mock(ClientManager::class);
        $mockManager->shouldReceive('make')->andReturn($mockClient);

        $this->app->instance(ClientManager::class, $mockManager);
    }

    private function createMockMessage(string $id, string $subject, string $sender, string $bodyHtml = '')
    {
        $mockMsg = \Mockery::mock(\Webklex\PHPIMAP\Message::class);
        $mockMsg->shouldReceive('getMessageId')->andReturn($id);
        $mockMsg->shouldReceive('getSubject')->andReturn($subject);
        
        $mockAddress = \Mockery::mock(\Webklex\PHPIMAP\Address::class);
        $mockAddress->mail = $sender;
        
        $mockFrom = \Mockery::mock();
        $mockFrom->shouldReceive('first')->andReturn($mockAddress);
        
        $mockMsg->shouldReceive('getFrom')->andReturn($mockFrom);
        $mockMsg->shouldReceive('getAttachments')->andReturn(new \Webklex\PHPIMAP\Support\AttachmentCollection([]));
        $mockMsg->shouldReceive('getHTMLBody')->andReturn($bodyHtml);
        $mockMsg->shouldReceive('getTextBody')->andReturn(strip_tags($bodyHtml));
        $mockMsg->shouldReceive('setFlag')->with('SEEN')->andReturn(true);
        
        return $mockMsg;
    }

    public function test_post_pipeline()
    {
        $this->withoutExceptionHandling();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*:generateContent*' => function ($request) {
                $body = json_decode($request->body(), true);
                $text = $body['contents'][0]['parts'][0]['text'] ?? '';
                
                // Extraer el título del prompt (Asumimos que el subject viene en el prompt)
                preg_match('/Asunto del correo: (.*?)\n/', $text, $matches);
                $title = isset($matches[1]) ? trim($matches[1]) : 'Generico de prueba';
                file_put_contents('scratch/test_titles.txt', $title . "\n", FILE_APPEND);
                
                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => json_encode([
                                        'type' => 'post',
                                        'title' => $title,
                                        'importance' => 5,
                                        'content' => '<p>Content</p>',
                                        'excerpt' => 'Excerpt',
                                        'categories' => []
                                    ])]
                                ]
                            ]
                        ]
                    ]
                ], 200);
            },
            'generativelanguage.googleapis.com/v1beta/models*' => Http::response([
                'models' => [
                    ['name' => 'models/gemini-2.5-flash']
                ]
            ], 200)
        ]);

        $msg1 = $this->createMockMessage('111', 'Nevermore anuncia su gira europea de reunión', 'redaccion@sevenrockradio.com', '<p>Detalles gira.</p>');
        $msg2 = $this->createMockMessage('222', 'Hoy en el Rock - 23 septiembre 2026', 'dark.vader.agent@gmail.com', '1. 1942 - Nace Ronnie James Dio en Portsmouth, NH.');
        $msg3 = $this->createMockMessage('333', 'Otra noticia genérica', 'otro@dominio.com', '<p>Algo genérico 1.</p>');
        $msg4 = $this->createMockMessage('444', 'Otra noticia genérica mas', 'otro@dominio.com', '<p>Algo genérico 2.</p>');
        $msg5 = $this->createMockMessage('555', 'Tercera genérica excediendo límite', 'otro@dominio.com', '<p>Exceso.</p>');
        
        // Mensaje con duplicado simulado
        $msg6 = $this->createMockMessage('666', 'Fallece Chad Gilbert, guitarrista y fundador de New Found Glory', 'redaccion@sevenrockradio.com', '<p>Texto.</p>');
        $msg7 = $this->createMockMessage('777', 'Fallece Chad Gilbert, guitarrista y cofundador de New Found Glory', 'redaccion@sevenrockradio.com', '<p>Texto reescrito.</p>');

        $this->mockImapMessages([$msg1, $msg2, $msg3, $msg4, $msg5, $msg6, $msg7]);

        \Illuminate\Support\Facades\Cache::forget('theme_settings');

        $this->artisan('emails:process')->assertExitCode(0);

        // Assert 1: Noticia de redacción -> ["Noticias Rock"]
        $postRedaccion = Post::where('title', 'Nevermore anuncia su gira europea de reunion')->orWhere('title', 'Nevermore anuncia su gira europea de reunión')->first();
        $this->assertNotNull($postRedaccion, 'La noticia de la redacción debería crearse.');
        $this->assertEquals(['Noticias Rock'], $postRedaccion->categories);

        // Assert 2: Efeméride -> ["Hoy en el Rock"]
        $postEfem = Post::where('title', '1942 - Nace Ronnie James Dio en Portsmouth, NH')->first();
        $this->assertNotNull($postEfem, 'La efeméride debería crearse.');
        $this->assertEquals(['Hoy en el Rock'], $postEfem->categories);

        // Assert 3: Correo genérico sin categoría -> ["Noticias Rock"] por fallback
        $postGen = Post::where('title', 'Otra noticia genérica')->first();
        $this->assertNotNull($postGen, 'El correo genérico debería crearse.');
        $this->assertEquals(['Noticias Rock'], $postGen->categories);

        // Assert 4: Duplicado de redacción
        $postDup1 = Post::where('title', 'Fallece Chad Gilbert, guitarrista y fundador de New Found Glory')->first();
        $this->assertNotNull($postDup1);
        $postDup2 = Post::where('title', 'Fallece Chad Gilbert, guitarrista y cofundador de New Found Glory')->first();
        $this->assertNull($postDup2, 'El segundo post duplicado no debería crearse.');

        // Assert 5: Límite diario bloquea el tercer post genérico (límite=2), pero la redacción pasa
        $this->assertEquals(2, Post::where('author_email', 'otro@dominio.com')->count(), 'Solo deberían pasar 2 posts genéricos por el límite.');
        // redacción tiene 2 (Nevermore y Chad Gilbert) + efeméride 1.
    }
}
