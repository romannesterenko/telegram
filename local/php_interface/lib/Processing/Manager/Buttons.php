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
                        if($app->getId()==$app->manager()->getCreatingApp()){
                            $response['buttons'] = json_encode([
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => \Settings\Common::getButtonText('manager_restore_app'),
                                            "callback_data" => "restoreDraftedApp_".$app->manager()->getCreatingApp()
                                        ],

                                        [
                                            'text' => "Отменить создание",
                                            "callback_data" => "cancelCreatingApp_".$app->manager()->getCreatingApp()
                                        ],
                                    ]
                                ]
                            ]);
                        }
                }
                break;
            case 'CancelOperationByManager':
                //Common::resetCreatingOperationProcess(1646);
                if($array_data[1]) {
                    $operation = (new Operation())->find($array_data[1]);
                    if($operation->getId()>0&&$operation->getStatus()==58) {
                        $manager_id = (new Operation())->find($array_data[1])->getField('MANAGER');
                        Common::resetCreatingOperationProcess($manager_id);
                        Operation::delete($array_data[1]);
                        $message = "Создание операции отменено";
                    }
                }
                break;
            case 'cancelCreatingApp':
                if($array_data[1]) {
                    $app = (new Applications())->find($array_data[1]);
                    if($app->getId()>0&&$app->isDraft()) {
                        $app->manager()->resetCreateAppSession();
                        Applications::delete($array_data[1]);
                        $message = "Создание заявки отменено";
                    }
                }
                break;
            case 'NotSetOperationFile':
                if($array_data[1]) {
                    $operations = new Operation();
                    $operation = $operations->find($array_data[1]);
                    if($operation->getId()>0&&$operation->getStatus()==58&&$operation->getField('STEP')==1) {
                        $operation->setField('STEP', 2);
                        $operation->manager()->resetMediaGroupSession();
                        $markup = \Processing\Manager\Markup::getOperationsMarkup(2, $operation->getId());
                        $message = $markup['message'];
                        $response['buttons'] = $markup['buttons'];
                    }
                }
                break;
            case 'NotSetCommentOperation':
                if($array_data[1]) {
                    $operations = new Operation();
                    $operation = $operations->find($array_data[1]);
                    $operation->setField('STATUS', 59);
                    $operation_m = (new Operation())->find($operation->getId())->getArray();
                    Common::resetCreatingOperationProcess($operation_m['PROPERTY_MANAGER_VALUE']);
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
                            $message_to_resp .= "https://ci01.amg.pw".CFile::GetPath($file)."\n";
                        }
                    }
                    if(!empty($operation_m['PROPERTY_FILE_TEXT_VALUE']['TEXT'])) {
                        $message_to_resp .= "Текст - " . $operation_m['PROPERTY_FILE_TEXT_VALUE']['TEXT'] . "\n";
                    }
                    if($operation_m['PROPERTY_COMENT_VALUE'])
                        $message_to_resp .= "Комментарий - ".$operation_m['PROPERTY_COMENT_VALUE']."\n";

                    //Telegram::sendMessageToResp($message_to_resp);
                    Mattermost::send($message_to_resp, $operation->cash_room()->getMatterMostOperationChannel());
                    $message="Операция создана";
                }
                break;
            case 'createOperation':
                if($array_data[1]) {
                    $staff = new Staff();
                    $manager = $staff->find($array_data[1]);
                    if($manager->hasSessions()){
                        if($manager->getCreatingApp()>0){
                            $message = "Невозможно. У вас есть недооформленная заявка №".$manager->getCreatingApp()."\n\n";
                            $message.=(new Applications())->prepareAppDataMessage($manager->getCreatingApp());
                            $message.= "\nВы хотите продолжить ее оформление или отменить?";
                            $response['buttons'] = json_encode([
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => \Settings\Common::getButtonText('manager_restore_app'),
                                            "callback_data" => "restoreDraftedApp_".$manager->getCreatingApp()
                                        ],

                                        [
                                            'text' => "Отменить создание",
                                            "callback_data" => "cancelCreatingApp_".$manager->getCreatingApp()
                                        ],
                                    ]
                                ]
                            ]);
                        } elseif ($manager->getCreatingOperation()){
                            $operation = (new Operation())->find($manager->getCreatingOperation());
                            $message = "Невозможно. У вас есть недооформленная операция\n";
                            $markup = \Processing\Manager\Markup::getOperationsMarkup($operation->getStep(), $operation->getId());
                            $message.=$markup['message'];
                            $response['buttons'] = $markup['buttons'];
                        } elseif ($manager->isStartedSearchSession()){
                            $message = "Запущен процесс поиска. Введите поисковую фразу или отмените поиск, затем создавайте операцию\n\n";
                            $response['buttons'] = json_encode([
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => "Отменить поиск",
                                            "callback_data" => "resetSearch_".$manager->getId()
                                        ]
                                    ]
                                ]
                            ]);
                        }
                    } else {
                        $app_id = (new Operation())->createNewDraft($manager->getId());
                        $manager->startCreateOperationSession($app_id);
                        $manager_cash_room_list = $manager->manager_cash_rooms();
                        if(count($manager_cash_room_list)!=1) {
                            $markup = \Processing\Manager\Markup::getOperationsMarkup(0, $app_id);
                        } else {
                            (new Operation())->find($app_id)->setField('STEP', 1);
                            (new Operation())->find($app_id)->setField('CASH_ROOM', $manager_cash_room_list[0]['ID']);
                            $markup = \Processing\Manager\Markup::getOperationsMarkup(1, $app_id);
                        }
                        $message = $markup['message'];
                        $response['buttons'] = $markup['buttons'];
                    }
                }
                break;
            case 'operationList':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0&&(int)$array_data[3]>0){
                    $all_list = (new Operation())->where('PROPERTY_STATUS', 59)->where('PROPERTY_MANAGER', (int)$array_data[3])->buildQuery()->getArray();
                    $list = (new Operation())->where('PROPERTY_STATUS', 59)->where('PROPERTY_MANAGER', (int)$array_data[3])->setLimit($array_data[1])->setPage($array_data[2])->buildQuery()->getArray();
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
                                            "callback_data" => "operationList_".$array_data[1]."_".$prev_step."_".(int)$array_data[3]
                                    ];
                                    $keyboard[] = [
                                        'text' => "Показать ".$menu,
                                        "callback_data" => "operationList_".$menu."_1"."_".(int)$array_data[3]
                                    ];
                                    $full_keyboard[] = $keyboard;
                                    $response['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $full_keyboard
                                    ]);
                                } else {
                                    $keyboard[] = [
                                        'text' => "Предыдущие ".$array_data[1],
                                        "callback_data" => "operationList_".$array_data[1]."_".$prev_step."_".(int)$array_data[3]
                                    ];
                                    $keyboard[] = [
                                        'text' => "Следующие ".$array_data[1],
                                        "callback_data" => "operationList_".$array_data[1]."_".$next_step."_".(int)$array_data[3]
                                    ];
                                    $keyboard[] = [
                                        'text' => "Показать ".$menu,
                                        "callback_data" => "operationList_".$menu."_1"."_".(int)$array_data[3]
                                    ];
                                    $full_keyboard[] = $keyboard;
                                    $response['buttons'] = json_encode([
                                        'resize_keyboard' => true,
                                        'inline_keyboard' => $full_keyboard
                                    ]);
                                }
                            } else {
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
                                        "callback_data" => "operationList_".$array_data[1]."_".$prev_step."_".(int)$array_data[3]
                                    ];
                                }
                                $keyboard[] = [
                                    'text' => "Следующие ".$array_data[1],
                                    "callback_data" => "operationList_".$array_data[1]."_".$next_step."_".(int)$array_data[3]
                                ];
                                $keyboard[] = [
                                    'text' => "Показать ".$menu,
                                    "callback_data" => "operationList_".$menu."_1"."_".(int)$array_data[3]
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
                    } else {
                        $message = "Операций нет";
                    }
                }
                break;
            case 'operationDetail':
                if((int)$array_data[1]>0){

                    $response = [];
                    $operation = (new Operation())->find((int)$array_data[1])->getArray();
                    $message_to_resp = "Операция №".$operation['ID']."\n";
                    $message_to_resp .= "Дата - ".$operation['CREATED_DATE']."\n";
                    $message_to_resp .= "Кто - ".$operation['PROPERTY_WHO_VALUE']."\n";
                    $message_to_resp .= "Кому - ".$operation['PROPERTY_WHOM_VALUE']."\n";
                    $message_to_resp .= "Ставка кто - ".$operation['PROPERTY_ST_WHO_VALUE']."\n";
                    $message_to_resp .= "Ставка кому - ".$operation['PROPERTY_ST_WHOM_VALUE']."\n";
                    $message_to_resp .= "Менеджер - ".(new Staff())->find($operation['PROPERTY_MANAGER_VALUE'])->getName()."\n";
                    if($operation['PROPERTY_COMENT_VALUE'])
                        $message_to_resp .= "Комментарий - ".$operation['PROPERTY_COMENT_VALUE']."\n";
                    if(ArrayHelper::checkFullArray($operation['PROPERTY_FILES_VALUE'])){
                        $message_to_resp .= "Ссылки на файлы:\n";
                        foreach ($operation['PROPERTY_FILES_VALUE'] as $file){
                            $message_to_resp .= "https://ci01.amg.pw".CFile::GetPath($file)."\n";
                        }
                    }
                    $message = $message_to_resp;

                }
                break;
            case 'operationsSearch':
                if((int)$array_data[1]) {
                    $manager = (new Staff())->find((int)$array_data[1]);
                    if($manager->getCreatingApp()>0){
                        $message = "Невозможно. У вас есть недооформленная заявка №".$manager->getCreatingApp()."\n\n";
                        $message.=(new Applications())->prepareAppDataMessage($manager->getCreatingApp());
                        $message.= "\nВы хотите продолжить ее оформление или отменить?";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => \Settings\Common::getButtonText('manager_restore_app'),
                                        "callback_data" => "restoreDraftedApp_".$manager->getCreatingApp()
                                    ],

                                    [
                                        'text' => "Отменить создание заявки",
                                        "callback_data" => "cancelCreatingApp_".$manager->getCreatingApp()
                                    ],
                                ]
                            ]
                        ]);
                    } elseif ($manager->getCreatingOperation()){
                        $operation = (new Operation())->find($manager->getCreatingOperation());
                        $message = "Невозможно. У вас есть недооформленная операция\n";
                        $markup = \Processing\Manager\Markup::getOperationsMarkup($operation->getStep(), $operation->getId());
                        $message.=$markup['message'];
                        $response['buttons'] = $markup['buttons'];
                    } else {
                        $message = "Введите фразу для поиска";
                        $response['buttons'] = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => "Отменить поиск",
                                        "callback_data" => "resetSearch_".(int)$array_data[1]
                                    ]
                                ]
                            ]
                        ]);
                        (new Staff())->find((int)$array_data[1])->startSearchSession();
                    }

                }
                break;
            case 'resetSearch':
                if((int)$array_data[1]) {
                    $message = "Отменено";
                    (new Staff())->find((int)$array_data[1])->closeSearchSession();
                }
                break;
            case 'setFieldToApp':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0){
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
                    if ($app->getField('OPERATION_TYPE')!=false){
                        $markup['message'] = \Settings\Common::getWrongAppActionText();
                    }else {
                        $app->setField('OPERATION_TYPE', (int)$array_data[2]);
                        $app->setField('DRAFT_STEP', 2);
                        $app->updateName();
                        $markup = Markup::getAgentSecondNameMarkup($app->prepareAppDataMessage($app->getField('ID')), $app->getField('ID'));
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
                    if ($applications->getDrartedById((int)$array_data[1]) > 0) {
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
                    $app = $apps->find((int)$array_data[1]);
                    if($app->getStatus() != 3){
                        $markup['message'] = \Settings\Common::getWrongAppActionText();
                    } else {
                        $app->setField('DRAFT_STEP', 7);
                        $app->setReadyToWorkStatus();
                        $markup['message'] = "Заявка №".$app->getId()." создана";
                        $app_id = $app->getId();
                        $buttons_array = [];
                        if($app_id>0){
                            if((new Applications())->find($app_id)->manager()->getField('TG_LOGIN')==Common::GetAllowCashRoomEmployee()){
                                $buttons_array[] = [
                                    'text' => "Инфо по кассам"
                                ];
                            }
                        }
                        $buttons_array[] = [
                            'text' => "Операции"
                        ];
                        $buttons_array[] = [
                            'text' => \Settings\Common::getButtonText('manager_app_list')
                        ];
                        $buttons_array[] = [
                            'text' => \Settings\Common::getButtonText('manager_cancel_new_app'),
                        ];
                        $markup['buttons'] = json_encode([
                            'resize_keyboard' => true,
                            'keyboard' => [

                                $buttons_array

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
                    $app = $apps->find((int)$array_data[1]);
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
            //установка кассы
            case 'setCashRoomToOperation':
                if((int)$array_data[1]>0&&(int)$array_data[2]>0) {
                    $apps = new Operation();
                    $app = $apps->find((int)$array_data[1]);
                    $message = Common::getWrongAppActionText();
                    if( $app->getField('STEP') == 0 ) {
                        $app->setField('CASH_ROOM', (int)$array_data[2]);
                        $app->setField('STEP', 1);
                        $markup = \Processing\Manager\Markup::getOperationsMarkup(1, $app->getId());
                        $message = $markup['message'];
                        $response['buttons'] = $markup['buttons'];
                    }
                }
                break;
            case 'ManagerCancelApp':
                if((int)$array_data[1]>0) {
                    $apps = new Applications();
                    $app = $apps->find((int)$array_data[1]);
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
                    $app = $apps->find((int)$array_data[1]);
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