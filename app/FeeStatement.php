<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property mixed date_from
 * @property double amount
 * @property BelongsTo concept
 */
class FeeStatement extends Model
{
    protected $table = 'fees_statements';

    protected $appends = ['print_date', 'absolute_amount'];

    public function getPrintDateAttribute() {
        if($this->date_from == null) return '';
        $newDate = Carbon::createFromFormat('Y-m-d', $this->date_from);
        return $newDate->format('d/m/Y');
    }

    public function getAbsoluteAmountAttribute() {
        $concept_type = $this->concept->concept_type;
        if($concept_type == 'i'){
            return $this->amount;
        } else {
            return 0 - $this->amount;
        }
    }

    public function concept() {
        return $this->belongsTo(CatFeeConcept::class, 'id_concept', 'id_concept');
    }
}
