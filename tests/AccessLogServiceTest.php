<?php

namespace JimBarrett\LaravelAuditLogger\Tests;

use JimBarrett\LaravelAuditLogger\Models\AccessLog;
use JimBarrett\LaravelAuditLogger\Services\AccessLogService;
use JimBarrett\LaravelAuditLogger\Support\AuditContext;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestPatient;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestUser;

class AccessLogServiceTest extends TestCase
{
    protected AccessLogService $service;
    protected TestUser $user;
    protected TestPatient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AccessLogService::class);
        $this->user = TestUser::create(['name' => 'Staff']);
        $this->patient = TestPatient::create(['name' => 'Patient']);
    }

    public function test_it_records_an_access(): void
    {
        $written = $this->service->log($this->patient, 'profile_view', $this->user->id, '10.0.0.1');

        $this->assertTrue($written);

        $log = AccessLog::first();
        $this->assertSame('TestPatient', $log->subject_type);
        $this->assertSame($this->patient->id, $log->subject_id);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('profile_view', $log->access_type);
        $this->assertSame('10.0.0.1', $log->ip_address);
    }

    public function test_it_stores_the_short_class_name_as_subject_type(): void
    {
        // Matches what AuditObserver writes to model_audits, so both tables share one
        // morph map and neither bakes a namespace into permanent rows.
        $this->service->log($this->patient, 'profile_view', $this->user->id);

        $this->assertSame('TestPatient', AccessLog::first()->subject_type);
        $this->assertStringNotContainsString('\\', AccessLog::first()->subject_type);
    }

    public function test_it_suppresses_repeat_access_inside_the_dedupe_window(): void
    {
        $this->assertTrue($this->service->log($this->patient, 'notes', $this->user->id));
        $this->assertFalse($this->service->log($this->patient, 'notes', $this->user->id));

        $this->assertSame(1, AccessLog::count());
    }

    public function test_a_different_access_type_is_not_deduped(): void
    {
        $this->service->log($this->patient, 'notes', $this->user->id);
        $this->service->log($this->patient, 'invoices', $this->user->id);

        $this->assertSame(2, AccessLog::count());
    }

    public function test_a_different_user_is_not_deduped(): void
    {
        $other = TestUser::create(['name' => 'Other']);

        $this->service->log($this->patient, 'notes', $this->user->id);
        $this->service->log($this->patient, 'notes', $other->id);

        $this->assertSame(2, AccessLog::count());
    }

    public function test_access_outside_the_dedupe_window_is_recorded(): void
    {
        $this->service->log($this->patient, 'notes', $this->user->id);

        $this->travel(6)->minutes();

        $this->assertTrue($this->service->log($this->patient, 'notes', $this->user->id));
        $this->assertSame(2, AccessLog::count());
    }

    public function test_dedupe_can_be_disabled(): void
    {
        config(['audit-logger.access_log.dedupe_minutes' => 0]);

        $this->service->log($this->patient, 'notes', $this->user->id);
        $this->service->log($this->patient, 'notes', $this->user->id);

        $this->assertSame(2, AccessLog::count());
    }

    public function test_it_resolves_context_from_the_session(): void
    {
        $this->startSession();
        session(['practice_id' => 4, 'current_location' => 9]);

        $this->service->log($this->patient, 'profile_view', $this->user->id);

        $log = AccessLog::first();
        $this->assertSame(4, $log->context_id);
        $this->assertSame(9, $log->sub_context_id);
    }

    public function test_it_resolves_context_from_an_explicit_override(): void
    {
        // The seeder and queue-job case: no session, context set by hand.
        app(AuditContext::class)->override(11, 22);

        $this->service->log($this->patient, 'profile_view', $this->user->id);

        $log = AccessLog::first();
        $this->assertSame(11, $log->context_id);
        $this->assertSame(22, $log->sub_context_id);
    }

    public function test_explicit_arguments_beat_the_resolved_context(): void
    {
        app(AuditContext::class)->override(11, 22);

        $this->service->log($this->patient, 'profile_view', $this->user->id, null, 77, 88);

        $log = AccessLog::first();
        $this->assertSame(77, $log->context_id);
        $this->assertSame(88, $log->sub_context_id);
    }

    public function test_it_does_not_log_without_a_user(): void
    {
        $this->assertFalse($this->service->log($this->patient, 'profile_view'));
        $this->assertSame(0, AccessLog::count());
    }

    public function test_it_falls_back_to_the_authenticated_user(): void
    {
        $this->actingAs($this->user);

        $this->assertTrue($this->service->log($this->patient, 'profile_view'));
        $this->assertSame($this->user->id, AccessLog::first()->user_id);
    }

    public function test_it_uses_a_user_set_explicitly_outside_a_request(): void
    {
        app(AuditContext::class)->actingAs($this->user->id);

        $this->assertTrue($this->service->log($this->patient, 'profile_view'));
        $this->assertSame($this->user->id, AccessLog::first()->user_id);
    }

    public function test_the_for_subject_scope_finds_a_records_access(): void
    {
        $other = TestPatient::create(['name' => 'Other']);

        $this->service->log($this->patient, 'profile_view', $this->user->id);
        $this->service->log($other, 'profile_view', $this->user->id);

        $this->assertSame(1, AccessLog::forSubject($this->patient)->count());
    }
}
