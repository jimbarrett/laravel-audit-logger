<?php

namespace JimBarrett\LaravelAuditLogger\Tests;

use JimBarrett\LaravelAuditLogger\Models\AuditLog;
use JimBarrett\LaravelAuditLogger\Observers\AuditObserver;
use JimBarrett\LaravelAuditLogger\Support\AuditContext;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestPatient;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestUser;

class AuditObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestPatient::observe(AuditObserver::class);
    }

    public function test_it_logs_model_creation(): void
    {
        $patient = TestPatient::create(['name' => 'Ada']);

        $audit = AuditLog::first();
        $this->assertSame('created', $audit->action);
        $this->assertSame('TestPatient', $audit->model_type);
        $this->assertSame($patient->id, $audit->model_id);
        $this->assertSame('Ada', $audit->entries()->where('updated_field', 'name')->first()->new_value);
    }

    public function test_it_logs_only_changed_fields_on_update(): void
    {
        $patient = TestPatient::create(['name' => 'Ada']);
        AuditLog::query()->delete();

        $patient->update(['name' => 'Grace']);

        $audit = AuditLog::first();
        $this->assertSame('updated', $audit->action);
        $this->assertSame(1, $audit->entries()->count());

        $entry = $audit->entries()->first();
        $this->assertSame('name', $entry->updated_field);
        $this->assertSame('Ada', $entry->old_value);
        $this->assertSame('Grace', $entry->new_value);
    }

    public function test_it_writes_nothing_when_an_update_changes_nothing(): void
    {
        $patient = TestPatient::create(['name' => 'Ada']);
        AuditLog::query()->delete();

        $patient->update(['name' => 'Ada']);

        $this->assertSame(0, AuditLog::count());
    }

    public function test_it_redacts_sensitive_fields(): void
    {
        TestPatient::create(['name' => 'Ada', 'password' => 'hunter2']);

        $entry = AuditLog::first()->entries()->where('updated_field', 'password')->first();

        $this->assertSame('***REDACTED***', $entry->new_value);
    }

    public function test_it_records_context_from_the_session(): void
    {
        $this->startSession();
        session(['practice_id' => 4, 'current_location' => 9]);

        TestPatient::create(['name' => 'Ada']);

        $audit = AuditLog::first();
        $this->assertSame(4, $audit->context_id);
        $this->assertSame(9, $audit->sub_context_id);
    }

    public function test_it_records_context_set_explicitly_outside_a_request(): void
    {
        // The defect this replaced: seeders, jobs and console commands have no session,
        // so every row they wrote landed with a null context.
        app(AuditContext::class)->scope(4, 9, function () {
            TestPatient::create(['name' => 'Ada']);
        });

        $audit = AuditLog::first();
        $this->assertSame(4, $audit->context_id);
        $this->assertSame(9, $audit->sub_context_id);
    }

    public function test_context_is_null_when_there_is_no_session_and_no_override(): void
    {
        TestPatient::create(['name' => 'Ada']);

        $this->assertNull(AuditLog::first()->context_id);
    }

    public function test_it_records_the_authenticated_user(): void
    {
        $user = TestUser::create(['name' => 'Staff']);
        $this->actingAs($user);

        TestPatient::create(['name' => 'Ada']);

        $this->assertSame($user->id, AuditLog::first()->user_id);
    }

    public function test_it_records_a_user_set_explicitly_outside_a_request(): void
    {
        // Queue jobs and console commands have no authenticated user, so every row they
        // wrote was previously anonymous as well as contextless.
        $user = TestUser::create(['name' => 'Staff']);

        app(AuditContext::class)->scope(4, 9, function () {
            TestPatient::create(['name' => 'Ada']);
        }, userId: $user->id);

        $audit = AuditLog::first();
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame(4, $audit->context_id);
    }

    public function test_the_user_is_null_when_nothing_identifies_one(): void
    {
        TestPatient::create(['name' => 'Ada']);

        $this->assertNull(AuditLog::first()->user_id);
    }

    public function test_it_captures_every_attribute_on_hard_delete(): void
    {
        $patient = TestPatient::create(['name' => 'Ada']);
        AuditLog::query()->delete();

        $patient->delete();

        $audit = AuditLog::first();
        $this->assertSame('deleted', $audit->action);
        $this->assertSame('Ada', $audit->entries()->where('updated_field', 'name')->first()->old_value);
    }
}
