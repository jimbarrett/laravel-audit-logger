<?php

namespace JimBarrett\LaravelAuditLogger\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestPatient extends Model
{
    protected $table = 'test_patients';

    public $timestamps = false;

    protected $fillable = ['name', 'password'];
}
