<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $table = 'budget';
    protected $primaryKey = 'id_budget';
    // public $incrementing = false;
    public $timestamps = false;
}
