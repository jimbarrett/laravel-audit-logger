<?php

namespace JimBarrett\LaravelAuditLogger\Tests;

use JimBarrett\LaravelAuditLogger\Support\AuditContext;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestUser;

class AuditContextTest extends TestCase
{
    protected AuditContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(AuditContext::class);
    }

    public function test_it_reads_context_from_the_session_by_default(): void
    {
        $this->startSession();
        session(['practice_id' => 7, 'current_location' => 3]);

        $this->assertSame(7, $this->context->contextId());
        $this->assertSame(3, $this->context->subContextId());
    }

    public function test_it_returns_null_when_there_is_no_session(): void
    {
        // The console/queue case: no session to read, and nothing set explicitly.
        $this->assertNull($this->context->contextId());
        $this->assertNull($this->context->subContextId());
    }

    public function test_an_explicit_context_beats_the_session(): void
    {
        $this->startSession();
        session(['practice_id' => 7, 'current_location' => 3]);

        $this->context->override(99, 42);

        $this->assertSame(99, $this->context->contextId());
        $this->assertSame(42, $this->context->subContextId());
    }

    public function test_clearing_falls_back_to_the_session_again(): void
    {
        $this->startSession();
        session(['practice_id' => 7]);

        $this->context->override(99);
        $this->context->clear();

        $this->assertSame(7, $this->context->contextId());
        $this->assertFalse($this->context->isOverridden());
    }

    public function test_scope_restores_the_previous_context_afterwards(): void
    {
        $this->context->override(1, 2);

        $result = $this->context->scope(50, 60, function () {
            $this->assertSame(50, app(AuditContext::class)->contextId());
            $this->assertSame(60, app(AuditContext::class)->subContextId());

            return 'returned';
        });

        $this->assertSame('returned', $result);
        $this->assertSame(1, $this->context->contextId());
        $this->assertSame(2, $this->context->subContextId());
    }

    public function test_scope_restores_the_previous_context_even_when_the_callback_throws(): void
    {
        $this->context->override(1, 2);

        try {
            $this->context->scope(50, 60, function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(1, $this->context->contextId());
        $this->assertSame(2, $this->context->subContextId());
    }

    public function test_it_reads_the_user_from_the_auth_guard_by_default(): void
    {
        $user = TestUser::create(['name' => 'Staff']);
        $this->actingAs($user);

        $this->assertSame($user->id, $this->context->userId());
    }

    public function test_the_user_is_null_when_nobody_is_authenticated(): void
    {
        // The console/queue case: no authenticated user, nothing set explicitly.
        $this->assertNull($this->context->userId());
    }

    public function test_an_explicit_user_beats_the_auth_guard(): void
    {
        $user = TestUser::create(['name' => 'Staff']);
        $this->actingAs($user);

        $this->context->actingAs(404);

        $this->assertSame(404, $this->context->userId());
    }

    public function test_an_explicit_null_user_is_honoured_over_the_auth_guard(): void
    {
        // Recording work as genuinely unattributed is more honest than borrowing
        // whichever user happens to be authenticated.
        $user = TestUser::create(['name' => 'Staff']);
        $this->actingAs($user);

        $this->context->actingAs(null);

        $this->assertNull($this->context->userId());
        $this->assertTrue($this->context->hasUserOverride());
    }

    public function test_user_and_context_override_independently(): void
    {
        $this->startSession();
        session(['practice_id' => 7]);

        $this->context->actingAs(3);

        // Setting a user must not blank out session-derived context.
        $this->assertSame(3, $this->context->userId());
        $this->assertSame(7, $this->context->contextId());
    }

    public function test_scope_can_set_the_user_too(): void
    {
        $this->context->scope(4, 9, function () {
            $this->assertSame(3, app(AuditContext::class)->userId());
        }, userId: 3);

        $this->assertNull($this->context->userId());
    }

    public function test_clear_drops_the_user_override(): void
    {
        $this->context->actingAs(3);
        $this->context->clear();

        $this->assertNull($this->context->userId());
        $this->assertFalse($this->context->hasUserOverride());
    }

    public function test_an_explicit_null_context_is_honoured_over_the_session(): void
    {
        $this->startSession();
        session(['practice_id' => 7]);

        $this->context->override(null);

        $this->assertNull($this->context->contextId());
    }
}
