<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExRateSourceAndApp extends Model
{
    protected $table = 'e_r_source_and_app';
    protected $primaryKey = 'id_exchange_rate';
    // public $incrementing = false;
    public $timestamps = false;
}
