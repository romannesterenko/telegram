<?php
namespace Processing\Manager;
use Api\Mattermost;
use Api\Telegram;
use Bitrix\Main\Config\Option;
use CFile;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\Operation;
use Models\Staff;
use Processing\Manager\Markup;
use Settings\Common;

class Buttons
{
    public static function process($data)
    {
        $message = \Settings\Common::getWrongCallBackData();
        $array_data = explode('_', $data);
        switch ($array_data[0]){
            case 'showApplicationForManager':
                if((int)$array_data[1]>0){
                    $apps = new Applications();
                    $message = $apps->prepareAppDataMessage((int)$array_data[1]);
                    $app = $apps->find((int)$array_data[1]);
                    if($app->isAllowToCancelByManager()) {
                        $response['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => \Settings\Common::getButtonText('manager_cancel_app'),
                                        "callback_data" => "ManagerCancelApp_" . (int)$array_data[1]
                                    ],
                                ]
                            ]
                        ]);
                    }
                }
                break;
            case 'CancelOperationByManager':
                if($array_data[1]) {
                    Operation::delete($array_data[1]);
                    $message = "Создание операции отменено";
                }
                break;
            case 'NotSetOperationFile':
                if($array_data[1]) {
                    $operations = new Operation();
                    $operation = $operations->find($array_data[1]);
                    $operation->setField('STEP', 1);
                    $markup = \Processing\Manager\Markup::getOperationsMarkup(1, $operation->getId());
                    $message=$markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            case 'NotSetCommentOperation':
                if($array_data[1]) {
                    $operations = new Operation();
                    $operation = $operations->find($array_data[1]);
                    $operation->setField('STATUS', 59);
                    $operation_m = (new Operation())->find($operation->getId())->getArray();
                    $message_to_resp = "Новая операция №".$operation->getId()."\n";
                    $message_to_resp .= "Дата - ".$operation_m['CREATED_DATE']."\n";
                    $message_to_resp .= "Кто - ".$operation_m['PROPERTY_WHO_VALUE']."\n";
                    $message_to_resp .= "Кому - ".$operation_m['PROPERTY_WHOM_VALUE']."\n";
                    $message_to_resp .= "Ставка кто - ".$operation_m['PROPERTY_ST_WHO_VALUE']."\n";
                    $message_to_resp .= "Ставка кому - ".$operation_m['PROPERTY_ST_WHOM_VALUE']."\n";
                    $message_to_resp .= "Менеджер - ".(new Staff())->find($operation_m['PROPERTY_MANAGER_VALUE'])->getName()."\n";
                    if(ArrayHelper::checkFullArray($operation_m['PROPERTY_FILES_VALUE'])){
                        $message_to_resp .= "Ссылки на файлы:\n";
                        foreach ($operation_m['PROPERTY_FILES_VALUE'] as $file){
                            $message_to_resp .= "https://ci01.amg.pw/".CFile::GetPath($file)."\n";
                        }
                    }
                    if($operation_m['PROPERTY_COMENT_VALUE'])
                        $message_to_resp .= "Комментарий - ".$operation_m['PROPERTY_COMENT_VALUE']."\n";
                    Telegram::sendMessageToResp($message_to_resp);
                    Mattermost::send($message_to_resp);
                    $message="Операция создана";
                }
                break;
            case 'createOperation':
                if($array_data[1]) {
                    $staff = new Staff();
                    $operations = new Operation();
                    $manager = $staff->getByChatId($array_data[1]);
                    $drafted_operation = $operations->getDrafted($manager->getId());
                    if ($drafted_operation->getId() > 0) {
                        $message = "Невозможно. У вас есть недооформленная операция\n";
                        $markup = \Processing\Manager\Markup::getOperationsMarkup($drafted_operation->getStep(), $drafted_operation->getId());
                        $message.=$markup['message'];
                        $response['buttons'] = $markup['buttons'];
                    } else {
                        $app_id = $drafted_operation->createNewDraft($manager->getId());
                        $markup = \Processing\Manager\Markup::getOperationsMarkup(0, $app_id);
                        $message = $markup['message'];
                        $response['buttons'] = $markup['buttons'];
                    }
                }
                break;
            case 'operationList':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0){
                    $all_list = (new Operation())->where('PROPERTY_STATUS', 59)->buildQuery()->getArray();
                    $list = (new Operation())->where('PROPERTY_STATUS', 59)->setLimit($array_data[1])->setPage($array_data[2])->buildQuery()->getArray();
                    $full_keyboard = [];
                    if(ArrayHelper::checkFullArray($list)){
                        $message = "Список операций\n";
                        foreach ($list as $operation) {
                            $full_keyboard[] = [
                                [
                                    'text' => $operation['PROPERTY_WHO_VALUE'].' - '.$operation['PROPERTY_WHOM_VALUE'].'. '.$operation['CREATED_DATE'],
                                    "callback_data" => "operationDetail_".$operation['ID']
                                ]
                            ];

                            /*$message .= "================\n";
                            $message .= "Операция №".$operation['ID']."\n";
                            $message .= "Кто - ".$operation['PROPERTY_WHO_VALUE']."\n";
                            $message .= "Кому - ".$operation['PROPERTY_WHOM_VALUE']."\n";
                            $message .= "Ставка кто - ".$operation['PROPERTY_ST_WHO_VALUE']."\n";
                            $message .= "Ставка кому - ".$operation['PROPERTY_ST_WHOM_VALUE']."\n";
                            if($operation['PROPERTY_COMENT_VALUE'])
                                $message .= "Комментарий - ".$operation['PROPERTY_COMENT_VALUE']."\n";*/
                        }
                        $all_count = count($all_list);
                        $current_count = count($list);
                        if($current_count<$array_data[1]){
                            if($array_data[2]>1){
                                $prev_step = (int)$array_data[2]-1;
                                $next_step = (int)$array_data[2]+1;
                                $menu = $array_data[1]==10?100:10;
                                if($array_data[1]>count($list)){
                                    $keyboard[] = [
                                            'text' => "Предыдущие ".$array_data[1],
                                            "callback_data" => "operationList_".$array_data[1]."_".$prev_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Показать ".$menu,
                                        "callback_data" => "operationList_".$menu."_1"
                                    ];
                                    $full_keyboard[] = $keyboard;
                                    $response['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $full_keyboard
                                    ]);
                                } else {
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "operationList_".$array_data[1]."_".$prev_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Следующие ".$array_data[1],
                                        "callback_data" => "operationList_".$array_data[1]."_".$next_step
                                    ];
                                    $keyboard[] = [
                                        'text' => "Показать ".$menu,
                                        "callback_data" => "operationList_".$menu."_1"
                                    ];
                                    $full_keyboard[] = $keyboard;
                                    $response['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $full_keyboard
                                    ]);
                                }
                            } else {
                                /*$menu = $array_data[1]==10?100:10;
                                $keyboard[] = [
                                    'text' => "Показать ".$menu,
                                    "callback_data" => "operationList_".$menu."_1"
                                ];
                                $full_keyboard[] = $keyboard;*/
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            }
                        } else {
                            if($all_count>$current_count){
                                $menu = $array_data[1]==10?100:10;
                                $next_step = $array_data[2]+1;
                                $keyboard = [];
                                if($array_data[2]>1){
                                    $prev_step = $array_data[2]-1;
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "operationList_".$array_data[1]."_".$prev_step
                                    ];
                                }
                                $keyboard[] = [
                                    'text' => "Следующие ".$array_data[1],
                                    "callback_data" => "operationList_".$array_data[1]."_".$next_step
                                ];
                                $keyboard[] = [
                                    'text' => "Показать ".$menu,
                                    "callback_data" => "operationList_".$menu."_1"
                                ];
                                $full_keyboard[] = $keyboard;
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            } else {
                                $response['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $full_keyboard
                                ]);
                            }
                        }
                        //$ost = count($all_list)%($array_data[2]*count($list));
                    } else {
                        $message = "Операций нет";
                    }
                    //$response['buttons'] = $markup['buttons'];
                }
                break;
            case 'operationDetail':
                if((int)$array_data[1]>0){
                    $response = [];
                    $operation = (new Operation())->find((int)$array_data[1])->getArray();
                    $message = "Операция №".$operation['ID']."\n";
                    $message .= "Дата - ".$operation['CREATED_DATE']."\n";
                    $message .= "Кто - ".$operation['PROPERTY_WHO_VALUE']."\n";
                    $message .= "Кому - ".$operation['PROPERTY_WHOM_VALUE']."\n";
                    $message .= "Ставка кто - ".$operation['PROPERTY_ST_WHO_VALUE']."\n";
                    $message .= "Ставка кому - ".$operation['PROPERTY_ST_WHOM_VALUE']."\n";
                    if($operation['PROPERTY_COMENT_VALUE'])
                        $message .= "Комментарий - ".$operation['PROPERTY_COMENT_VALUE']."\n";
                    if(ArrayHelper::checkFullArray($operation['PROPERTY_FILES_VALUE'])){
                        foreach ($operation['PROPERTY_FILES_VALUE'] as $file){
                            $response['send_photo'] = true;
                            $response['photo'][] = \CFile::GetByID($file)->Fetch();
                        }
                    }
                }
                break;
            case 'operationsSearch':
                if((int)$array_data[1]) {
                    $message = "Введите фразу для поиска";
                    Option::set('main', 'search_'.(int)$array_data[1], 'Y');
                }
                break;
            case 'setFieldToApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if ($app->getField('OPERATION_TYPE')!=false){
                        $markup['message'] = \Settings\Common::getWrongAppActionText();
                    }else {
                        $app->setField('OPERATION_TYPE', (int)$array_data[2]);
                        $app->setField('DRAFT_STEP', 2);
                        $app->updateName();
                        $markup = Markup::getAgentSecondNameMarkup($app->prepareAppDataMessage($app->getField('ID')));
                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            case 'startNewApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $apps->removeDrafted((int)$array_data[1]);
                    $apps->createNewDraft((int)$array_data[1]);
                    $markup = Markup::getAgentNameMarkup();
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            case 'restoreDraftedApp':
                if((int)$array_data[1]>0) {
                    $applications = new Applications();
                    if ($applications->getDrartedByManager((int)$array_data[1]) > 0) {
                        $response = $applications->restoreProcessDraft((int)$array_data[1]);
                        $message = $response['message'];
                        if ($response['buttons'])
                            $buttons = $response['buttons'];
                    } else {
                        $message = 'Действующих заявок в статусе черновик нет, создайте новую из меню ниже';
                    }
                }
                break;
            case 'NotSetComment':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    if($app->getStatus() != 3){
                        $markup['message'] = \Settings\Common::getWrongAppActionText();
                    } else {
                        $app->setField('DRAFT_STEP', 7);
                        $app->setReadyToWorkStatus();
                        $markup['message'] = "Заявка №".$app->getId()." создана";
                        $markup['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'keyboard' => [
                                [
                                    [
                                        'text' => "Операции"
                                    ],
                                    [
                                        'text' => \Settings\Common::getButtonText('manager_app_list')
                                    ],
                                    [
                                        'text' => \Settings\Common::getButtonText('manager_new_app')
                                    ],
                                ]
                            ]
                        ]);

                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            //установка кассы
            case 'setCashRoomToApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    $markup['message'] = Common::getWrongAppActionText();
                    if ($app->isPayment()) {
                        if( $app->getStatus() == 3 && $app->getField('DRAFT_STEP') == 4) {
                            $app->setField('CASH_ROOM', (int)$array_data[2]);
                            $app->setField('DRAFT_STEP', 5);
                            //$markup = \Processing\Manager\Markup::getRespAddAddressMarkup('', '', $app->getField('ID'));
                            $markup = \Processing\Manager\Markup::getComentMarkup('', $app->getField('ID'));
                        }
                    } else {
                        if( $app->getStatus() == 3 && $app->getField('DRAFT_STEP') == 4) {
                            $app->setField('CASH_ROOM', (int)$array_data[2]);
                            $app->setField('DRAFT_STEP', 5);
                            $markup = \Processing\Manager\Markup::getComentMarkup('', $app->getField('ID'));
                        }
                    }
                    $message = $markup['message'];
                    $response['buttons'] = $markup['buttons'];
                }
                break;
            case 'ManagerCancelApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    $app->setToManagerCancelComent();
                    $message = "Введите причину отмены заявки №" . (int)$array_data[1] . ", или отмените это действие";
                    $buttons = json_encode([
                        'resize_keyboard' => true,
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => \Settings\Common::getButtonText('manager_reset_cancel_app'),
                                    'callback_data' => 'resetManagerCancelApp_'.(int)$array_data[1]
                                ],

                            ]
                        ]
                    ]);
                    $response['buttons'] = $buttons;
                }
                break;
            //сброс отмены заявки
            case 'resetManagerCancelApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1])->get();
                    //возвращаем статус, который был на момент отмены
                    $app->setField('STATUS', $app->getField('BEFORE_MANAGER_CANCEL_STATUS'));
                    $message = "Процесс отмены заявки №" . (int)$array_data[1]. " был сброшен";
                }
                break;
        }
        $response['message'] = $message;
        return $response;
    }
}