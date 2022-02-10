<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $table = 'exchange_rates';
    protected $primaryKey = 'id_exchange_rate';
    // public $incrementing = false;
    public $timestamps = false;
}
