<?php
namespace Processing\Manager;
use Api\Telegram;
use Models\Applications;
use Processing\Manager\Markup;
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
                    $response['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => \Settings\Common::getButtonText('manager_cancel_app'),
                                    "callback_data" => "ManagerCancelApp_".(int)$array_data[1]
                                ],
                            ]
                        ]
                    ]);
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
                        $app->setField('DRAFT_STEP', 4);
                        $app->setReadyToWorkStatus();
                        if($app->isPayment()) {
                            $markup['message'] = "Заявка №".$app->getId()." создана";
                            Telegram::sendMessageToResp($app->prepareAppDataMessage($app->getField('ID')), $app->getField('ID'));
                            $markup['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'keyboard' => [
                                    [
                                        [
                                            'text' => \Settings\Common::getButtonText('manager_app_list')
                                        ],
                                        [
                                            'text' => \Settings\Common::getButtonText('manager_new_app')
                                        ],
                                    ]
                                ]
                            ]);
                        }else{
                            $markup['message'] = "Заявка №".$app->getId()." создана";
                            Telegram::sendMessageToCollResp($app->prepareAppDataMessage($app->getField('ID')), $app->getField('ID'));
                            $markup['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'keyboard' => [
                                    [
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