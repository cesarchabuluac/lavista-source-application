<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string name
 * @property int condominium
 * @property integer id_owner
 * @property string lot
 */
class Owner extends Model
{
    protected $primaryKey = 'id_owner';

    protected $appends = 'unit';

    public function getUnitAttribute() {
        if($this->id_owner == 70
            || $this->id_owner == 71
            || $this->id_owner == 72
            || $this->id_owner == 73) return $this->name;
        $lot = 'L'.$this->lot;
        $condominium = $this->condominium;
        if($this->lot == null or $this->lot == ""){
            return $this->condominium;
        }
        if($this->condominium == null or $this->condominium == ""){
            return $this->lot;
        }
        if(strlen($this->condominium) < 3){
            $condominium = 'V'.$this->condominium;
            return $lot.$condominium;
        }
        return $lot.'-'.$condominium;
    }
}
