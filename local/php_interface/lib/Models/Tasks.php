<?php
namespace Models;
use Models\HigloadElementModel as Model;
class Tasks extends Model {
    protected $HL_ID = 1;

    public function addSendMessageTask($phone, $text, $contact_name){
        $this->create([
            'UF_USER_PHONE' => $phone,
            'UF_FIRST_NAME' => $contact_name,
            'UF_TEXT' => $text,
            'UF_ACTION' => 1,
            'UF_CREATED_AT' => date('d.m.Y H:i:s'),
            'UF_IS_COMPLETED' => 0,
        ]);
    }

    public function setComplete($id){
        $this->update($id, ['UF_IS_COMPLETED' => 1, 'UF_COMPLETED_AT' => date('d.m.Y H:i:s')]);
    }
}
