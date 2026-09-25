<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verifies that events with status='pending' are hidden from all public surfaces
 * and that approved events continue to be visible (no regressions).
 *
 * Rules:
 *  - pending → portada, /events, /events/all, /events/upcoming  → NOT listed
 *  - pending → /js_events/{slug}                                 → 404
 *  - approved → all public routes                               → 200 / listed
 *  - cancelled (is_cancelled=true, status=approved)             → visible with cancelled flag intact
 */
class EventVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    // ─── helpers ────────────────────────────────────────────────────────────

    private function makeEvent(array $attrs = []): Event
    {
        return Event::create(array_merge([
            'title'     => 'Test Event ' . uniqid(),
            'slug'      => 'test-event-' . uniqid(),
            'status'    => 'approved',
            'starts_at' => now()->addDays(7),
            'location'  => 'Test City',
            'venue'     => 'Test Venue',
        ], $attrs));
    }

    // ─── PENDING: ficha pública ──────────────────────────────────────────────

    public function test_pending_event_single_returns_404(): void
    {
        $event = $this->makeEvent(['status' => 'pending']);

        $this->get(route('events.single', $event->slug))
             ->assertNotFound();
    }

    // ─── PENDING: listados públicos ──────────────────────────────────────────

    public function test_pending_event_absent_from_events_index(): void
    {
        $event = $this->makeEvent(['status' => 'pending']);

        Cache::flush();

        $this->get('/events')
             ->assertSuccessful()
             ->assertDontSee($event->title);
    }

    public function test_pending_event_absent_from_events_upcoming(): void
    {
        $event = $this->makeEvent(['status' => 'pending']);

        Cache::flush();

        $this->get('/events/upcoming')
             ->assertSuccessful()
             ->assertDontSee($event->title);
    }

    public function test_pending_event_absent_from_events_all(): void
    {
        $event = $this->makeEvent(['status' => 'pending']);

        Cache::flush();

        $this->get('/events/all')
             ->assertSuccessful()
             ->assertDontSee($event->title);
    }

    public function test_pending_event_absent_from_home_page(): void
    {
        $event = $this->makeEvent(['status' => 'pending']);

        Cache::flush();

        $this->get('/')
             ->assertSuccessful()
             ->assertDontSee($event->title);
    }

    // ─── APPROVED: ficha y listados ──────────────────────────────────────────

    public function test_approved_event_single_returns_200(): void
    {
        $event = $this->makeEvent(['status' => 'approved']);

        $this->get(route('events.single', $event->slug))
             ->assertOk();
    }

    public function test_approved_event_appears_in_events_index(): void
    {
        $event = $this->makeEvent(['status' => 'approved']);

        Cache::flush();

        $this->get('/events')
             ->assertSuccessful()
             ->assertSee($event->title);
    }

    public function test_approved_event_appears_in_events_all(): void
    {
        $event = $this->makeEvent(['status' => 'approved']);

        Cache::flush();

        $this->get('/events/all')
             ->assertSuccessful()
             ->assertSee($event->title);
    }

    // ─── CANCELLED: visible con su aviso ─────────────────────────────────────

    public function test_cancelled_approved_event_single_still_returns_200(): void
    {
        $event = $this->makeEvent([
            'status'       => 'approved',
            'is_cancelled' => true,
        ]);

        $this->get(route('events.single', $event->slug))
             ->assertOk();
    }

    public function test_cancelled_approved_event_appears_in_events_all(): void
    {
        $event = $this->makeEvent([
            'status'       => 'approved',
            'is_cancelled' => true,
        ]);

        Cache::flush();

        $this->get('/events/all')
             ->assertSuccessful()
             ->assertSee($event->title);
    }

    // ─── TRANSICIÓN pending → approved ──────────────────────────────────────

    public function test_event_becomes_visible_after_approval(): void
    {
        $event = $this->makeEvent(['status' => 'pending']);

        // Before approval: 404
        $this->get(route('events.single', $event->slug))->assertNotFound();

        // Approve it (simulates clicking the moderation email link)
        $event->update(['status' => 'approved']);
        Cache::flush();

        // After approval: 200
        $this->get(route('events.single', $event->slug))->assertOk();

        // Also appears in the listing
        $this->get('/events/all')
             ->assertSuccessful()
             ->assertSee($event->title);
    }
}
