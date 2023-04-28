<?php
namespace Models;
use Models\ElementModel as Model;
class Crew extends Model {
    const IBLOCK_ID = 7;

    public function employee(): Staff
    {
        $staff = new Staff();
        return $staff->find($this->getField('EMPLOYEE'));
    }
}
