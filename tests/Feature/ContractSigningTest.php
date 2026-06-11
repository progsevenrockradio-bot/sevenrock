<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use App\Mail\ContractSignRequestMail;
use App\Mail\ContractSignedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractSigningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_guest_cannot_access_admin_contracts_routes(): void
    {
        $response = $this->get(route('admin.contracts.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_contract_and_send_mail(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.contracts.store'), [
            'signer_name' => 'Alice Cooper',
            'signer_email' => 'alice@cooper.com',
            'title' => 'Contrato de Alquiler de Espacio Digital',
            'content' => '<p>Este es el texto del acuerdo promocional.</p>',
        ]);

        $response->assertRedirect(route('admin.contracts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('contracts', [
            'signer_name' => 'Alice Cooper',
            'signer_email' => 'alice@cooper.com',
            'title' => 'Contrato de Alquiler de Espacio Digital',
            'status' => 'pending',
        ]);

        $contract = Contract::query()->first();
        $this->assertNotNull($contract->token);

        Mail::assertSent(ContractSignRequestMail::class, function ($mail) use ($contract) {
            return $mail->hasTo('alice@cooper.com') && $mail->contract->id === $contract->id;
        });
    }

    public function test_signer_can_view_contract(): void
    {
        $contract = Contract::query()->create([
            'token' => 'test-token-123',
            'signer_name' => 'Alice Cooper',
            'signer_email' => 'alice@cooper.com',
            'title' => 'Contrato de Alquiler de Espacio Digital',
            'content' => '<p>Este es el texto del acuerdo promocional.</p>',
            'status' => 'pending',
        ]);

        $response = $this->get(route('contratos.firmar', ['token' => 'test-token-123']));
        $response->assertStatus(200);
        $response->assertSee('Alice Cooper');
        $response->assertSee('Contrato de Alquiler de Espacio Digital');
    }

    public function test_signer_can_sign_contract_which_generates_pdf_and_sends_mails(): void
    {
        Mail::fake();

        $contract = Contract::query()->create([
            'token' => 'test-token-123',
            'signer_name' => 'Alice Cooper',
            'signer_email' => 'alice@cooper.com',
            'title' => 'Contrato de Alquiler de Espacio Digital',
            'content' => '<p>Este es el texto del acuerdo promocional.</p>',
            'status' => 'pending',
        ]);

        $response = $this->post(route('contratos.sign', ['token' => 'test-token-123']), [
            'nombre_completo' => 'Alice Cooper',
            'aceptar_terminos' => '1',
            'confirmar_mayoria' => '1',
            'country' => 'United States',
            'city' => 'Detroit',
        ]);

        $response->assertRedirect(route('contratos.exito', ['token' => 'test-token-123']));

        // Refresh model from DB
        $contract->refresh();

        $this->assertEquals('signed', $contract->status);
        $this->assertEquals('United States', $contract->country);
        $this->assertEquals('Detroit', $contract->city);
        $this->assertNotNull($contract->signed_at);
        $this->assertNotNull($contract->signing_ip);
        $this->assertNotNull($contract->pdf_path);

        // Check if PDF file was generated and stored in storage
        Storage::disk('local')->assertExists($contract->pdf_path);

        // Check signed mails were sent to signer and admin
        Mail::assertSent(ContractSignedMail::class, 2); // One to signer, one to admin
    }

    public function test_download_signed_contract_pdf(): void
    {
        // Place a fake PDF file in the storage
        Storage::disk('local')->put('contracts/test_contract.pdf', 'fake-pdf-content');

        $contract = Contract::query()->create([
            'token' => 'test-token-123',
            'signer_name' => 'Alice Cooper',
            'signer_email' => 'alice@cooper.com',
            'title' => 'Contrato de Alquiler de Espacio Digital',
            'content' => '<p>Texto</p>',
            'status' => 'signed',
            'pdf_path' => 'contracts/test_contract.pdf',
        ]);

        $response = $this->get(route('contratos.download', ['token' => 'test-token-123']));
        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename=Contrato_de_Alquiler_de_Espacio_Digital_firmado.pdf');
    }
}
