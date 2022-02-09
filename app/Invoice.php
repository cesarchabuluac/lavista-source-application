<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed date
 * @property Collection feeStatements
 * @property double balance
 * @property double amount
 */
class Invoice extends Model
{
    protected $primaryKey = 'id_invoice';

    protected $appends = ['print_date', 'has_rate', 'credit', 'amount', 'label'];

    public function getPrintDateAttribute() {
        if($this->date == null) return '';
        $date = Carbon::createFromFormat('Y-m-d', $this->date);
        return $date->format('d/m/Y');
    }

    public function getHasRate() {
        return $this->feeStatements->whereInNotNull('rate')->first() !== null;
    }

    public function getCreditAttribute() {
        $credit = 0;
        if($this->balance < 0) {
            $credit = 0 - $this->balance;
            if($credit > $this->amount){
                $credit = $this->amount;
            }
        }
        return $credit;
    }

    public function getLabelAttribute() {
        $final_balance = $this->balance + $this->amount;
        if($final_balance > 0) {
            $message_es = "Total pendiente por pagar";
            $message_en = "Pending Balance to pay";
        } else {
            $message_es = "Saldo total a favor";
            $message_en = "Balance in favor";
        }
        return $message_es . " / " .$message_en;
    }

    public function getAmountAttribute() {
        return $this->feeStatements->sum('absolute_amount');
    }

    public function owner() {
        return $this->belongsTo(Owner::class, 'id_owner', 'id_owner');
    }

    public function feeStatements() {
        return $this->hasMany(FeeStatement::class, 'id_invoice', 'id_invoice');
    }
}
