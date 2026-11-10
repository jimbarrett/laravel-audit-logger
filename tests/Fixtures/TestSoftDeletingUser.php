<?php

namespace JimBarrett\LaravelAuditLogger\Tests\Fixtures;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A user model that soft-deletes, as most applications' staff models do.
 */
class TestSoftDeletingUser extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'test_soft_deleting_users';

    public $timestamps = false;

    protected $fillable = ['name'];
}
