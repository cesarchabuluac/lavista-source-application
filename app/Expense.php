<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $table = 'expenses';
    protected $primaryKey = 'id_expense';
    // public $incrementing = false;
    public $timestamps = false;
}
