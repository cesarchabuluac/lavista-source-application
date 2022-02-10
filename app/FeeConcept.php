<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FeeConcept extends Model
{
    protected $table = 'cat_fees_concepts';
    protected $primaryKey = 'id_concept';
    // public $incrementing = false;
    public $timestamps = false;
}
