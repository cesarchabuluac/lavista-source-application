<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentDetail extends Model
{
    protected $table = 'payment_details';
    protected $primaryKey = 'id_payment_detail';
    // public $incrementing = false;
    public $timestamps = false;

    /**
     * New Attributes
     *
     * @var array
     */
    protected $appends = [
        'amount_usd',
        'amount_mxn',
        'amount_conversion',
    ];

    public function payment()
    {
        return $this->hasOne(Payment::class, 'id_payment', 'id_payment');
    }

    public function feesConcept()
    {
        return $this->hasOne(FeeConcept::class, 'id_concept', 'id_concept');
    }
    public function creditNote()
    {
        return $this->hasOne(CreditNote::class, 'id_credit_note', 'id_credit_note');
    }

    public function getAmountUsdAttribute()
    {
        if ($this->currency == "MXN") {
            if ($this->exchange_rate > 0) {
                return $this->amount / $this->exchange_rate;
            }
        } else return $this->amount;
    }

    public function getAmountMxnAttribute()
    {
        if ($this->currency == "MXN") {
            return $this->amount;
        } else
            return $this->amount * $this->exchange_rate;
    }

    public function getAmountConversionAttribute()
    {
        if (!$this->payment->bool_has_conversion) {
            return;
        }

        $e_r = $this->payment->conversion_exchange_rate;
        if ($e_r > 0) {
            return $this->amount / $e_r;
        }
    }

   
    
}
