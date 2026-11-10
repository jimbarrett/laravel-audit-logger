<?php

namespace JimBarrett\LaravelAuditLogger\Tests;

use JimBarrett\LaravelAuditLogger\Models\AccessLog;
use JimBarrett\LaravelAuditLogger\Models\AuditLog;
use JimBarrett\LaravelAuditLogger\Services\AccessLogService;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestPatient;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestSoftDeletingUser;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestUser;

/**
 * A log row names whoever acted, even after that user has been removed.
 */
class AuditUserRelationTest extends TestCase
{
    private function useSoftDeletingUsers(): TestSoftDeletingUser
    {
        config(['audit-logger.user_model' => TestSoftDeletingUser::class]);

        return TestSoftDeletingUser::create(['name' => 'Removed Staff']);
    }

    private function auditRowBy(int $userId): AuditLog
    {
        return AuditLog::create([
            'model_type' => 'TestPatient',
            'model_id' => 1,
            'action' => 'updated',
            'user_id' => $userId,
        ]);
    }

    public function test_an_audit_row_still_names_a_removed_user(): void
    {
        $user = $this->useSoftDeletingUsers();
        $audit = $this->auditRowBy($user->id);
        $user->delete();

        $this->assertSame('Removed Staff', $audit->fresh()->user?->name);
        $this->assertSame('Removed Staff', AuditLog::with('user')->find($audit->id)->user?->name);
    }

    public function test_an_access_log_row_still_names_a_removed_user(): void
    {
        $user = $this->useSoftDeletingUsers();
        $patient = TestPatient::create(['name' => 'Patient']);
        app(AccessLogService::class)->log($patient, 'profile_view', $user->id);
        $user->delete();

        $this->assertSame('Removed Staff', AccessLog::with('user')->first()->user?->name);
    }

    public function test_a_user_model_without_soft_deletes_still_resolves(): void
    {
        // The default fixture has no SoftDeletes, so withTrashed() must not be applied.
        $user = TestUser::create(['name' => 'Staff']);
        $audit = $this->auditRowBy($user->id);

        $this->assertSame('Staff', $audit->fresh()->user?->name);
    }
}
