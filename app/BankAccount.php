<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $table = 'bank_accounts';
    protected $primaryKey = 'id_bank_account';
    public $incrementing = false;
    public $timestamps = false;
}
