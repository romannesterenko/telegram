<?php
namespace Models;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\HigloadElementModel as Model;
class Log extends Model {
    protected $HL_ID = 2;

    public function addLog($params){
        $reply_markup = json_decode($params['reply_markup'], true);
        if(ArrayHelper::checkFullArray($reply_markup['inline_keyboard'])){
            foreach ($reply_markup['inline_keyboard'] as $buttons){
                if (ArrayHelper::checkFullArray($buttons)){
                    foreach ($buttons as $button){
                        $button_html = '<button>';
                        $button_html.= $button['text']." [".$button['callback_data']."]";
                        $button_html.= '</button>';
                        $params['text'].=$button_html;
                    }
                }

            }
        }
        $employee = (new Staff())->getByChatId($params['chat_id']);
        $this->create([
            'UF_PARAMS' => $params['text'],
            'UF_NAME' => $employee->getField('NAME'),
            'UF_DATE' => date('d.m.Y H:i:s'),
            'UF_ROLE' => $employee->getField('ROLE'),
            'UF_ID' => $params['chat_id'],
            'UF_TYPE' => $params['type_log'],
            'UF_TYPE_ENTITTY' => $params['type_entity']??false,
            'UF_ENTITY_ID' => $params['entity_id']??false,
        ]);
    }


}
