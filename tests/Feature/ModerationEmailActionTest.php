<?php

namespace Tests\Feature;

use App\Models\ModerationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ModerationEmailActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approve_with_valid_signature_returns_200_and_updates_status()
    {
        $item = ModerationItem::create(['type' => 'submission', 'title' => 'Test', 'status' => 'pending']);
        
        $url = URL::temporarySignedRoute('admin.moderation.approve-email', now()->addMinutes(10), ['item' => $item->id]);

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('moderation.result');
        $this->assertEquals('approved', $item->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $item->id, 'event' => 'moderation.approved']);
    }

    public function test_reject_with_valid_signature_returns_200_and_updates_status()
    {
        $item = ModerationItem::create(['type' => 'submission', 'title' => 'Test', 'status' => 'pending']);
        
        $url = URL::temporarySignedRoute('admin.moderation.reject-email', now()->addMinutes(10), ['item' => $item->id]);

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('moderation.result');
        $this->assertEquals('rejected', $item->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $item->id, 'event' => 'moderation.rejected']);
    }

    public function test_manipulated_signature_returns_403()
    {
        $item = ModerationItem::create(['type' => 'submission', 'title' => 'Test', 'status' => 'pending']);
        
        $url = URL::temporarySignedRoute('admin.moderation.approve-email', now()->addMinutes(10), ['item' => $item->id]);
        $url .= 'x'; // manipulate signature

        $response = $this->get($url);
        $response->assertStatus(403);
    }

    public function test_without_signature_returns_403()
    {
        $item = ModerationItem::create(['type' => 'submission', 'title' => 'Test', 'status' => 'pending']);
        
        $url = route('admin.moderation.approve-email', ['item' => $item->id]);

        $response = $this->get($url);
        $response->assertStatus(403);
    }

    public function test_expired_signature_returns_403()
    {
        $item = ModerationItem::create(['type' => 'submission', 'title' => 'Test', 'status' => 'pending']);
        
        $url = URL::temporarySignedRoute('admin.moderation.approve-email', now()->subMinutes(10), ['item' => $item->id]);

        $response = $this->get($url);
        $response->assertStatus(403);
    }

    public function test_non_existent_item_returns_403()
    {
        $url = URL::temporarySignedRoute('admin.moderation.approve-email', now()->addMinutes(10), ['item' => 9999]);

        $response = $this->get($url);
        $response->assertStatus(403);
    }

    public function test_already_decided_item_does_not_change_status_or_decided_at()
    {
        $decidedAt = now()->subDays(1);
        $item = ModerationItem::create([
            'type' => 'submission',
            'title' => 'Test',
            'status' => 'rejected',
            'decided_at' => $decidedAt,
            'decided_by' => 1
        ]);
        
        $url = URL::temporarySignedRoute('admin.moderation.approve-email', now()->addMinutes(10), ['item' => $item->id]);

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertViewHas('wasPending', false);

        $item->refresh();
        $this->assertEquals('rejected', $item->status);
        $this->assertEquals($decidedAt->timestamp, $item->decided_at->timestamp);
    }

    public function test_audit_failure_still_returns_200()
    {
        $item = ModerationItem::create(['type' => 'submission', 'title' => 'Test', 'status' => 'pending']);
        
        // Mock AuditTrailService to throw an exception
        $mock = \Mockery::mock(\App\Services\AuditTrailService::class);
        $mock->shouldReceive('recordModel')->andThrow(new \Exception('Audit failed'));
        $this->app->instance(\App\Services\AuditTrailService::class, $mock);

        $url = URL::temporarySignedRoute('admin.moderation.approve-email', now()->addMinutes(10), ['item' => $item->id]);

        $response = $this->get($url);

        // It should still return 200 and complete the action
        $response->assertStatus(200);
        $this->assertEquals('approved', $item->fresh()->status);
    }
}
