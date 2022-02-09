<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CreditNote extends Model
{
    protected $table = 'credit_notes';
    protected $primaryKey = 'id_credit_note';
    public $incrementing = false;
    public $timestamps = false;
}
