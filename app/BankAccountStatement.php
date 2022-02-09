<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BankAccountStatement extends Model
{
    protected $table = 'bank_account_statements';
    protected $primaryKey = 'id_bank_account_statement';
    // public $incrementing = false;
    public $timestamps = false;
}
