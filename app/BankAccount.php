<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $table = 'bank_accounts';
    protected $primaryKey = 'id_bank_account';
    public $incrementing = false;
    public $timestamps = false;

    /**
     * New Attributes
     *
     * @var array
     */
    protected $appends = [
        'full_name',
        'full_name_currency_code',
        'full_name_currency_full',
    ];

    public function getFullNameAttribute()
    {
        return isset($this->last_four) ? $this->name.'/ '.$this->last_four : $this->name;
    }

    public function getFullNameCurrencyCodeAttribute()
    {
        return isset($this->last_four) ? $this->name.' '.strtoupper($this->currency).'/'.$this->last_four : $this->name;
    }

    public function getFullNameCurrencyFullAttribute()
    {
        $moneda = 'Dolares';
        if($this->currency == 'MXN')
            $moneda = 'Pesos';
        return isset($this->last_four) ? $this->name.' '.$moneda.'/'.$this->last_four : $this->name;
    }

    public function currencie () {
        return $this->belongsTo(Currency::class, "currency", "id_currency");
    }
}
