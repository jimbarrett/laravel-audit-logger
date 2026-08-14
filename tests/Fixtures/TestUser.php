<?php

namespace JimBarrett\LaravelAuditLogger\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    protected $table = 'test_users';

    public $timestamps = false;

    protected $fillable = ['name'];
}
