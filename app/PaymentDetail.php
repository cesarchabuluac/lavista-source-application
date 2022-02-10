<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentDetail extends Model
{
    protected $table = 'payment_details';
    protected $primaryKey = 'id_payment_detail';
    // public $incrementing = false;
    public $timestamps = false;
}
