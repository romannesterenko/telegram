<?php
namespace Processing\Manager;
use Bitrix\Main\Config\Option;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\Operation;
use Processing\Manager\Buttons as ManagerButtons;
use Processing\Responsible\Markup as RespMarkup;
use Settings\Common;

class Actions
{
    public static function process(\Models\Staff $employee, $data, $is_callback)
    {
        $send_photo = false;
        $send_document = false;
        $buttons_array = [];
        if($employee->getField('TG_LOGIN')==Common::GetAllowCashRoomEmployee()){
            $buttons_array[] = [
                'text' => "Инфо по кассам"
            ];
        }
        $buttons_array[] = [
            'text' => "Операции"
        ];
        $buttons_array[] = [
            'text' => \Settings\Common::getButtonText('manager_app_list')
        ];
        $buttons_array[] = [
            'text' => \Settings\Common::getButtonText('manager_new_app'),
        ];

        $buttons = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [

                $buttons_array

            ]
        ]);
        if( $is_callback ){
            $data['chat']['id'] = $data['message']['chat']['id'];
            if( !empty( $data['data'] ) ){
                /*if($data['data']=='createOperation')
                    $data['data']='createOperation_'.$data['chat']['id'];*/
                $response = ManagerButtons::process($data['data']);
                $message = $response['message'];
                if (!empty($response['buttons']))
                    $buttons = $response['buttons'];
                if (!empty($response['send_photo'])) {
                    $photo = $response['photo'];
                    $send_photo = true;
                }
            }
        } else {
            switch ( $data['text'] ) {
                case "Инфо по кассам":
                    if($employee->getField('TG_LOGIN')==Common::GetAllowCashRoomEmployee()){
                        $response = RespMarkup::getRespCashRoomListMarkup();
                        $message = $response['message'];
                    } else {
                        $message = 'К сожалению, вы ввели неизвестную мне команду :/';
                    }
                    break;
                case "Операции":
                    $inline_keyboard[] = [
                        [
                            "text" => "Создание операции",
                            "callback_data" => "createOperation_".$employee->getId()
                        ]
                    ];
                    $inline_keyboard[] = [
                        [
                            "text" => "Список операций",
                            "callback_data" => "operationList_10_1_".$employee->getId()
                        ]
                    ];
                    $inline_keyboard[] = [
                        [
                            "text" => "Поиск операций",
                            "callback_data" => "operationsSearch_".$employee->getId()
                        ]
                    ];
                    $message = "Меню операций";
                    $keyboard = array("inline_keyboard" => $inline_keyboard);
                    $buttons = json_encode($keyboard);
                    break;
                //мои заявки
                case \Settings\Common::getButtonText('manager_app_list'):
                    $applications = new Applications();
                    $list = $applications->getByManager((int)$employee->getField('ID'));
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $text = '';
                            if($application['PROPERTY_OPERATION_TYPE_VALUE'])
                                $text.=$application['PROPERTY_OPERATION_TYPE_VALUE'] . ". ";
                            $inline_keyboard[] = [
                                [
                                    "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
                                    "callback_data" => "showApplicationForManager_" . $application['ID']
                                ]
                            ];
                        }
                        $message = 'Выберите заявку из списка для просмотра или управления';
                        $keyboard = array("inline_keyboard" => $inline_keyboard);
                        $buttons = json_encode($keyboard);
                    } else {
                        $message = 'Действующих заявок пока нет';
                    }
                    break;
                //восстановление процесса оформления
                case \Settings\Common::getButtonText('manager_restore_app'):
                    $applications = new Applications();
                    if ($applications->getDrartedByManager((int)$employee->getField('ID')) > 0) {
                        $response = $applications->restoreProcessDraft((int)$employee->getField('ID'));
                        $message = $response['message'];
                        if ($response['buttons'])
                            $buttons = $response['buttons'];
                    } else {
                        $message = 'Действующих заявок в статусе черновик нет, создайте новую из меню ниже';
                    }
                    break;
                //отмена создания
                case \Settings\Common::getButtonText('manager_cancel_new_app'):
                    if($employee->getCreatingApp()>0){
                        Applications::delete($employee->getCreatingApp());
                        $employee->resetCreateAppSession();
                        $message = "Создание заявки отменено";
                    } else {
                        $message = 'Заявка на удаление не найдена';
                    }

                    break;
                //отказ от продолжения оформления и переход к созданию новой
                case \Settings\Common::getButtonText('manager_cancel_restore_app'):
                    $applications = new Applications();
                    $applications->removeDrafted((int)$employee->getField('ID'));
                //оформление новой заявки
                case \Settings\Common::getButtonText('manager_new_app'):
                    if(!Common::isAllowToCreateApps()){
                        $message = "Создание заявок после ".Common::getTimeForApps()." запрещено";
                    } else {
                        if ($employee->hasSessions()) {
                            if ($employee->getCreatingApp() > 0) {
                                $message = "У вас есть недооформленная заявка №" . $employee->getCreatingApp() . "\n\n";
                                $message .= (new Applications())->prepareAppDataMessage($employee->getCreatingApp());
                                $message .= "\nВы хотите продолжить ее оформление или отменить?";
                                $buttons = json_encode([
                                    'inline_keyboard' => [
                                        [
                                            [
                                                'text' => \Settings\Common::getButtonText('manager_restore_app'),
                                                "callback_data" => "restoreDraftedApp_" . $employee->getCreatingApp()
                                            ],

                                            [
                                                'text' => "Отменить создание",
                                                "callback_data" => "cancelCreatingApp_" . $employee->getCreatingApp()
                                            ],
                                        ]
                                    ]
                                ]);
                            } elseif ($employee->getCreatingOperation() > 0) {
                                $message = "У вас есть недооформленная операция. Закончите её создание, затем создавайте заявку\n\n";
                            } elseif ($employee->isStartedSearchSession()) {
                                $message = "Запущен процесс поиска. Введите поисковую фразу или отмените поиск, затем создавайте заявку\n\n";
                                $buttons = json_encode([
                                    'inline_keyboard' => [
                                        [
                                            [
                                                'text' => "Отменить поиск",
                                                "callback_data" => "resetSearch_" . $employee->getId()
                                            ]
                                        ]
                                    ]
                                ]);
                            }
                        } else {
                            $app_id = (new Applications())->createNewDraft((int)$employee->getField('ID'));
                            $employee->startCreateAppSession($app_id);
                            $markup = Markup::getAgentNameMarkup($app_id);
                            $message = $markup['message'];
                            $buttons = $markup['buttons'];
                        }
                    }
                    break;
                //успешная авторизация в приложении команда /start
                case '/start':
                    $employee->setChatID($data['chat']['id']);
                    $message = Common::getHelloCommonMessage();
                    break;
                //другие текстовые данные
                default:
                    $applications = new Applications();
                    $operations = new Operation();
                    if($employee->isStartedSearchSession()){
                        $message = 'Результаты поиска по запросу "'.$data['text'].'"'."\n";
                        $list = $operations->filter([
                            'PROPERTY_STATUS' => 59,
                            [
                                "LOGIC" => "OR",
                                ["?PROPERTY_WHO" => $data['text']],
                                ["?PROPERTY_WHOM" => $data['text']],
                                ["?PROPERTY_ST_WHO" => $data['text']],
                                ["?PROPERTY_ST_WHOM" => $data['text']],
                                ["?COMENT" => $data['text']],
                            ]
                        ]
                        )->buildQuery()->getArray();
                        foreach ($list as $operation) {
                            $message .= "================\n";
                            $message .= "Операция №".$operation['ID']."\n";
                            $message .= "Кто - ".$operation['PROPERTY_WHO_VALUE']."\n";
                            $message .= "Кому - ".$operation['PROPERTY_WHOM_VALUE']."\n";
                            $message .= "Ставка кто - ".$operation['PROPERTY_ST_WHO_VALUE']."\n";
                            $message .= "Ставка кому - ".$operation['PROPERTY_ST_WHOM_VALUE']."\n";
                            if($operation['PROPERTY_COMENT_VALUE'])
                                $message .= "Комментарий - ".$operation['PROPERTY_COMENT_VALUE']."\n";
                        }
                        $employee->closeSearchSession();
                    }
                    elseif($employee->getCreatingOperation()>0){
                        $response = $operations->setFieldToDraft($employee->getCreatingOperation(), $data);
                        $message = $response['message'];
                        if ($response['buttons'])
                            $buttons = $response['buttons'];
                    }
                    //если заявка в стадии черновик
                    elseif ($employee->getCreatingApp()>0) {
                        $response = $applications->setFieldToDraft($employee->getCreatingApp(), $data['text']);
                        $message = $response['message'];
                        if ($response['buttons'])
                            $buttons = $response['buttons'];
                    }
                    //если есть заявки, ожидающие ответа
                    elseif ($applications->getNeedCancelByManager((int)$employee->getField('ID')) > 0){
                        $response = $applications->setFieldToNeedCancelByManager((int)$employee->getField('ID'), $data['text']);
                        $message = $response['message'];
                        if ($response['buttons'])
                            $buttons = $response['buttons'];
                    } else {
                        $message = 'К сожалению, вы ввели неизвестную мне команду :/';
                    }
            }
        }
        if ($send_photo) {
            return ["chat_id" => $data['chat']['id'], "caption" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons, 'photo' => $photo];
        } else {
            return ["chat_id" => $data['chat']['id'], "text" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons];
        }

    }
}