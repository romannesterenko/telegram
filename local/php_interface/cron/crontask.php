<?php use danog\MadelineProto\Exception;
$_SERVER["DOCUMENT_ROOT"] = "/home/bitrix/www";
const NO_KEEP_STATISTIC = true;
const NO_AGENT_STATISTIC = true;
const NOT_CHECK_PERMISSIONS = true;
require_once ($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/composer/vendor/autoload.php");
require_once "Database.php";
$db = new Database();
$session_file = $_SERVER["DOCUMENT_ROOT"].'/local/php_interface/madeline_session/session.madeline';
$log = $_SERVER["DOCUMENT_ROOT"].'/file.txt';
$string = 'Дата записи: '.date('d.m.Y H:i:s').PHP_EOL.PHP_EOL;
$tasks = $db->getUncompletedTasks();
if( is_array($tasks) && count($tasks)>0 ){
    $MadelineProto = new \danog\MadelineProto\API($session_file);
    $exists_contacts = $MadelineProto->contacts->getContacts();
    foreach ($tasks as $task){
        if($task['UF_ACTION'] == 1) {
            $already_user = 0;
            foreach ($exists_contacts['users'] as $user){
                if(str_replace('+', '', $task['UF_USER_PHONE'])==$user['phone'])
                    $already_user = $user['id'];
            }
            $inputPhoneContact = ['_' => 'inputPhoneContact', 'client_id' => time(), 'phone' => $task['UF_USER_PHONE'], 'first_name' => $task['UF_FIRST_NAME'], 'last_name' => $task['UF_FIRST_NAME']];
            try {
                if($already_user!=0)
                    $contacts['users'][0]['id'] = $already_user;
                else
                    $contacts = $MadelineProto->contacts->importContacts(['contacts' => [$inputPhoneContact]]);
                if($contacts['users'][0]['id']>0) {
                    try {
                        $MadelineProto->messages->sendMessage(['peer' => $contacts['users'][0]['id'], 'message' => $task['UF_TEXT']]);
                        $db->setCompleteTask($task['ID']);
                    } catch (Exception $e) {
                        $expr = "Отправка сообщения на номер ".$task['UF_USER_PHONE'].". Текст ошибки: ".$e->getMessage();
                        file_put_contents($log, $string.print_r($expr, 1)."\n", FILE_APPEND);
                    }
                }
            } catch (Exception $e) {
                if($e->getCode()==8) {
                    $db->setCompleteTask($task['ID']);
                    $expr = "Добавление контакта с номером " . $task['UF_USER_PHONE'] . ". Текст ошибки: " . $e->getMessage() . ". Код ошибки " . $e->getCode();
                    file_put_contents($log, $string . print_r($expr, 1) . "\n", FILE_APPEND);
                }
            }
        }
    }
}?>