<?php

namespace Tests\Feature\Admin;

use App\Models\PromoterCode;
use App\Models\Talent;
use App\Models\User;
use App\Mail\PromoterCodeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromoterCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);
    }

    /** @test */
    public function test_an_admin_can_create_a_promoter_code_with_default_limit_18()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.talents.promoters.store'), [
            'owner_name' => 'John Doe',
            'owner_email' => 'john@example.com',
            'owner_type' => 'conductor',
        ]);

        $response->assertRedirect(route('admin.talents.promoters.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('promoter_codes', [
            'owner_name' => 'John Doe',
            'owner_email' => 'john@example.com',
            'owner_type' => 'conductor',
            'max_bands' => 18,
            'is_archived' => false,
        ]);

        $code = PromoterCode::first();
        $this->assertNotEmpty($code->code);
    }

    /** @test */
    public function test_the_list_shows_active_bands_count_and_limit_reached()
    {
        $promoter = PromoterCode::factory()->create(['max_bands' => 2]);
        
        // Simular dos registros
        $talent1 = Talent::factory()->create(['band_name' => 'Band 1']);
        $talent2 = Talent::factory()->create(['band_name' => 'Band 2']);
        
        $promoter->referrals()->create([
            'referred_talent_id' => $talent1->id,
            'code' => $promoter->code,
            'status' => 'active',
        ]);
        
        $promoter->referrals()->create([
            'referred_talent_id' => $talent2->id,
            'code' => $promoter->code,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.talents.promoters.index'));

        $response->assertStatus(200);
        $response->assertSee('2 / 2');
        $response->assertSee('Tope Alcanzado');
    }

    /** @test */
    public function test_expand_limit_increases_max_bands_but_fails_if_lower_than_active()
    {
        $promoter = PromoterCode::factory()->create(['max_bands' => 2]);
        
        // Simular dos registros
        $talent1 = Talent::factory()->create(['band_name' => 'Band 1']);
        $talent2 = Talent::factory()->create(['band_name' => 'Band 2']);
        
        $promoter->referrals()->create([
            'referred_talent_id' => $talent1->id,
            'code' => $promoter->code,
            'status' => 'active',
        ]);
        
        $promoter->referrals()->create([
            'referred_talent_id' => $talent2->id,
            'code' => $promoter->code,
            'status' => 'active',
        ]);

        // Intentar bajar a 1
        $response = $this->actingAs($this->admin)->post(route('admin.talents.promoters.expand', $promoter), [
            'new_limit' => 1,
        ]);
        $response->assertSessionHas('error');
        $this->assertEquals(2, $promoter->fresh()->max_bands);

        // Intentar subir a 5
        $response = $this->actingAs($this->admin)->post(route('admin.talents.promoters.expand', $promoter), [
            'new_limit' => 5,
        ]);
        $response->assertSessionHas('success');
        $this->assertEquals(5, $promoter->fresh()->max_bands);
    }

    /** @test */
    public function test_full_code_does_not_block_registration()
    {
        try {
            $promoter = PromoterCode::factory()->create(['max_bands' => 1]);
            
            // Llenar el código
            $talent1 = Talent::factory()->create(['band_name' => 'Band 1']);
            $promoter->referrals()->create([
                'referred_talent_id' => $talent1->id,
                'code' => $promoter->code,
                'status' => 'active',
            ]);

            $this->assertTrue($promoter->hasReachedLimit());

            // Intentar registro con el código lleno
            $response = $this->post(route('talents.register.store'), [
                'band_name' => 'New Band',
                'email' => 'newband@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'referral_code' => $promoter->code,
                'plan' => 'free',
                'terms' => true,
            ]);

            // Debería redirigir al éxito, no fallar por validación del código
            $response->assertRedirect(route('talents.dashboard'));
            $this->assertDatabaseHas('talents', ['email' => 'newband@test.com']);
        } catch (\Throwable $e) {
            echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
            throw $e;
        }
    }

    /** @test */
    public function test_renewing_archives_old_and_creates_new_code_with_zero_count()
    {
        $promoter = PromoterCode::factory()->create([
            'owner_email' => 'test@test.com',
            'max_bands' => 18
        ]);
        
        $oldCode = $promoter->code;

        $response = $this->actingAs($this->admin)->post(route('admin.talents.promoters.renew', $promoter));
        $response->assertSessionHas('success');

        $this->assertTrue($promoter->fresh()->is_archived);

        $newPromoter = PromoterCode::where('owner_email', 'test@test.com')
                                   ->where('is_archived', false)
                                   ->first();

        $this->assertNotNull($newPromoter);
        $this->assertNotEquals($oldCode, $newPromoter->code);
        $this->assertEquals(0, $newPromoter->activeBandsCount());
    }

    /** @test */
    public function test_email_is_sent_with_correct_code_when_clicking_button()
    {
        Mail::fake();

        $promoter = PromoterCode::factory()->create([
            'owner_email' => 'owner@test.com',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.talents.promoters.send-email', $promoter));
        $response->assertSessionHas('success');

        Mail::assertSent(PromoterCodeMail::class, function ($mail) use ($promoter) {
            return $mail->hasTo('owner@test.com') && $mail->promoter->id === $promoter->id;
        });

        $this->assertStringContainsString('Enviado el', $promoter->fresh()->notes);
    }

    /** @test */
    public function test_non_admins_cannot_access_promoter_code_screens()
    {
        $user = User::factory()->create(); // No Super Admin
        $talent = Talent::factory()->create();

        $this->actingAs($user)->get(route('admin.talents.promoters.index'))->assertForbidden();
        $this->actingAs($talent, 'talent')->get(route('admin.talents.promoters.index'))->assertForbidden();
    }
}
