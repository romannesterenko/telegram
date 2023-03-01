<?php
namespace Processing\Manager;
use Helpers\ArrayHelper;
use Models\Applications;
use Processing\Manager\Buttons as ManagerButtons;

class Actions
{
    public static function process(\Models\Staff $employee, $data, $is_callback)
    {
        if( $is_callback ){
            $data['chat']['id'] = $data['message']['chat']['id'];
            if( !empty( $data['data'] ) ){
                $response = ManagerButtons::process($data['data']);
                $message = $response['message'];
                if ($response['buttons'])
                    $buttons = $response['buttons'];
            }
        } else {
            switch ( $data['text'] ) {
                //мои заявки
                case \Settings\Common::getButtonText('manager_app_list'):
                    $applications = new Applications();
                    $list = $applications->getByManager((int)$employee->getField('ID'));
                    $buttons = json_encode([
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
                    if (ArrayHelper::checkFullArray($list)) {
                        $inline_keyboard = [];
                        foreach ($list as $application) {
                            $text = '';
                            if($application['PROPERTY_OPERATION_TYPE_VALUE'])
                                $text.=$application['PROPERTY_OPERATION_TYPE_VALUE'] . ". ";
                            $inline_keyboard[] = [
                                [
                                    "text" => '№'.$application['ID'].'. '.$text . $application['PROPERTY_STATUS_VALUE'] . '. Создана ' . $application['CREATED_DATE'],
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
                    $applications = new Applications();
                    $applications->removeDrafted((int)$employee->getField('ID'));
                    $message = "Создание заявки отменено";
                    $buttons = json_encode([
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
                    break;
                //отказ от продолжения оформления и переход к созданию новой
                case \Settings\Common::getButtonText('manager_cancel_restore_app'):
                    $applications = new Applications();
                    $applications->removeDrafted((int)$employee->getField('ID'));
                //оформление новой заявки
                case \Settings\Common::getButtonText('manager_new_app'):
                    $applications = new Applications();
                    if ($applications->getDrartedByManager((int)$employee->getField('ID')) > 0) {
                        $message = "У вас есть недооформленная заявка №".$applications->getDrartedByManager((int)$employee->getField('ID'))."\n\n";
                        $message.=$applications->prepareAppDataMessage($applications->getDrartedByManager((int)$employee->getField('ID')));
                        $message.= "\nВы хотите продолжить ее оформление? При нажатии кнопки 'Оформление новой заявки' существующая заявка удалится и откроется Мастер создания новой заявки";
                        $buttons = json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => \Settings\Common::getButtonText('manager_restore_app'),
                                        "callback_data" => "restoreDraftedApp_".(int)$employee->getField('ID')
                                    ],

                                    [
                                        'text' => \Settings\Common::getButtonText('manager_cancel_restore_app'),
                                        "callback_data" => "startNewApp_".(int)$employee->getField('ID')
                                    ],
                                ]
                            ]
                        ]);
                    } else {
                        $applications->createNewDraft((int)$employee->getField('ID'));
                        $markup = Markup::getAgentNameMarkup();
                        $message = $markup['message'];
                        $buttons = $markup['buttons'];
                    }
                    break;
                //успешная авторизация в приложении команда /start
                case '/start':
                    $employee->setChatID($data['chat']['id']);
                    $message = \Settings\Common::getManagerHelloMessage();
                    $message = 'Здравствуйте. Управляйте своими зявками или создайте новую';
                    $buttons = json_encode([
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
                    break;
                //другие текстовые данные
                default:
                    $applications = new Applications();
                    //если заявка в стадии черновик
                    if ($applications->getDrartedByManager((int)$employee->getField('ID')) > 0) {
                        $response = $applications->setFieldToDraft((int)$employee->getField('ID'), $data['text']);
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
        return ["chat_id" => $data['chat']['id'], "text" => $message, 'parse_mode' => 'HTML', 'reply_markup' => $buttons];
    }
}