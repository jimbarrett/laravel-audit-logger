<?php

namespace JimBarrett\LaravelAuditLogger\Tests;

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use JimBarrett\LaravelAuditLogger\Exceptions\AccessLogMisconfigured;
use JimBarrett\LaravelAuditLogger\Http\Middleware\LogSubjectAccess;
use JimBarrett\LaravelAuditLogger\Models\AccessLog;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestPatient;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestUser;

class LogSubjectAccessTest extends TestCase
{
    protected TestUser $user;
    protected TestPatient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = TestUser::create(['name' => 'Staff']);
        $this->patient = TestPatient::create(['name' => 'Patient']);

        Route::middleware([SubstituteBindings::class, LogSubjectAccess::class])
            ->get('/patients/{patient}', function (TestPatient $patient) {
                return 'ok';
            });

        Route::middleware([SubstituteBindings::class, LogSubjectAccess::class.':chart_view'])
            ->get('/patients/{patient}/chart', function (TestPatient $patient) {
                return 'ok';
            });

        // Deliberately missing SubstituteBindings, so the subject arrives unbound.
        Route::middleware(LogSubjectAccess::class)
            ->get('/patients/{patient}/unbound', function ($patient) {
                return 'ok';
            });

        Route::middleware([SubstituteBindings::class, LogSubjectAccess::class])
            ->get('/patients/{patient}/gone', function (TestPatient $patient) {
                abort(404);
            });
    }

    public function test_it_logs_a_successful_authenticated_page_load(): void
    {
        $this->actingAs($this->user)->get("/patients/{$this->patient->id}")->assertOk();

        $log = AccessLog::first();
        $this->assertNotNull($log);
        $this->assertSame('TestPatient', $log->subject_type);
        $this->assertSame($this->patient->id, $log->subject_id);
        $this->assertSame('view', $log->access_type);
    }

    public function test_the_access_type_can_be_overridden_per_route(): void
    {
        $this->actingAs($this->user)->get("/patients/{$this->patient->id}/chart")->assertOk();

        $this->assertSame('chart_view', AccessLog::first()->access_type);
    }

    public function test_it_does_not_log_unauthenticated_requests(): void
    {
        $this->get("/patients/{$this->patient->id}")->assertOk();

        $this->assertSame(0, AccessLog::count());
    }

    public function test_it_does_not_log_unsuccessful_responses(): void
    {
        $this->actingAs($this->user)->get("/patients/{$this->patient->id}/gone")->assertNotFound();

        $this->assertSame(0, AccessLog::count());
    }

    public function test_it_fails_loudly_when_the_subject_arrives_unbound(): void
    {
        // Silently logging nothing forever is the failure mode worth being noisy about.
        $this->withoutExceptionHandling();
        $this->expectException(AccessLogMisconfigured::class);

        $this->actingAs($this->user)->get("/patients/{$this->patient->id}/unbound");
    }

    public function test_it_does_not_log_when_access_logging_is_disabled(): void
    {
        config(['audit-logger.access_log.enabled' => false]);

        $this->actingAs($this->user)->get("/patients/{$this->patient->id}")->assertOk();

        $this->assertSame(0, AccessLog::count());
    }
}
