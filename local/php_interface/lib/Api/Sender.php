<?php
namespace Api;

use danog\MadelineProto\Exception;
use Models\Applications;

class Sender
{
    public static function getMadelineFile():string{
        return $_SERVER["DOCUMENT_ROOT"].'/local/php_interface/session.madeline';
    }

    public static function send(Applications $app, $message)
    {

        $MadelineProto = new \danog\MadelineProto\API(self::getMadelineFile());

        $inputPhoneContact = ['_' => 'inputPhoneContact', 'client_id' => $app->getId(), 'phone' => $app->getPhone(), 'first_name' => $app->getField('AGENT_NAME'), 'last_name' => $app->getField('AGENT_NAME')];

        try {

            $contacts = $MadelineProto->contacts->importContacts(['contacts' => [$inputPhoneContact]]);

            if($contacts['users'][0]['id']>0) {
                try {

                    $MadelineProto->messages->sendMessage(['peer' => $contacts['users'][0]['id'], 'message' => $message]);

                } catch (Exception $e) {

                    \Helpers\LogHelper::write("Отправка сообщения на номер ".$app->getPhone().". Текст ошибки: ".$e->getMessage());

                }
            }
        } catch (Exception $e) {

            \Helpers\LogHelper::write("Добавление контакта с номером ".$app->getPhone().". Текст ошибки: ".$e->getMessage());

        }
        unset($MadelineProto);
    }
    public static function sendCommonMessage($phone, $message)
    {

        $MadelineProto = new \danog\MadelineProto\API(self::getMadelineFile());

        $inputPhoneContact = ['_' => 'inputPhoneContact', 'client_id' => rand(10000, 99999), 'phone' => $phone, 'first_name' => 'test', 'last_name' => 'test'];

        try {

            $contacts = $MadelineProto->contacts->importContacts(['contacts' => [$inputPhoneContact]]);

            if($contacts['users'][0]['id']>0) {
                try {

                    $MadelineProto->messages->sendMessage(['peer' => $contacts['users'][0]['id'], 'message' => $message]);

                } catch (Exception $e) {

                    \Helpers\LogHelper::write("Отправка сообщения на номер ".$app->getPhone().". Текст ошибки: ".$e->getMessage());

                }
            }
        } catch (Exception $e) {

            \Helpers\LogHelper::write("Добавление контакта с номером ".$app->getPhone().". Текст ошибки: ".$e->getMessage());

        }
        unset($MadelineProto);
    }

}