<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BudgetCategory extends Model
{
    protected $table = 'cat_budget_categories';
    protected $primaryKey = 'id_budget_category';
    // public $incrementing = false;
    public $timestamps = false;
}
