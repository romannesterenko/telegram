<?php
namespace Api;

use Bitrix\Main\UI\Uploader\Log;
use danog\MadelineProto\Exception;
use Helpers\LogHelper;
use Models\Applications;
use Models\Tasks;

class Sender
{
    public static function getMadelineFile():string{
        return $_SERVER["DOCUMENT_ROOT"].'/local/php_interface/madeline_session/session.madeline';
    }

    public static function send(Applications $app, $message)
    {
        (new Tasks())->addSendMessageTask($app->getPhone(), $message, $app->getField('AGENT_NAME'));
    }

    public static function sendCommonMessage($phone, $message)
    {
        $MadelineProto = new \danog\MadelineProto\API(self::getMadelineFile());
        $inputPhoneContact = ['_' => 'inputPhoneContact', 'client_id' => rand(10000, 99999), 'phone' => $phone, 'first_name' => 'test', 'last_name' => 'test'];
        try {
            $contacts = $MadelineProto->contacts->importContacts(['contacts' => [$inputPhoneContact]]);
            if( $contacts['users'][0]['id']>0 ) {
                try {
                    $MadelineProto->messages->sendMessage(['peer' => $contacts['users'][0]['id'], 'message' => $message]);
                } catch ( Exception $e ) {
                    \Helpers\LogHelper::write("Отправка сообщения на номер ".$phone.". Текст ошибки: ".$e->getMessage());
                }
            }
        } catch (Exception $e) {
            \Helpers\LogHelper::write("Добавление контакта с номером ".$phone.". Текст ошибки: ".$e->getMessage());
        }
        unset($MadelineProto);
    }

}