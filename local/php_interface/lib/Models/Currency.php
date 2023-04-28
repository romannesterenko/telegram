<?php
namespace Models;
use Models\ElementModel as Model;
class Currency extends Model {
    const IBLOCK_ID = 9;

    public function getGenitive(){
        return $this->getField('GENITIVE_TITLE');
    }
}
