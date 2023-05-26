<?php
namespace Processing\Manager;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\CashRoom;
use Models\ElementModel;
use Models\Operation;
use Processing\CashRoomEmployee\Markup as CREMarkup;
use Settings\Common;

class Markup
{
    /** Шаг №1 ввод имени */
    public static function getAgentNameMarkup($app_id=0)
    {
        $response['message'] = "Шаг №1. \nВведите <b>Имя контрагента</b> в учете";
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

        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [

                    $buttons_array

            ]
        ]);
        return $response;
    }

    /** Шаг №2 ввод типа данных Выдача/Забор */
    public static function getOperationTypeMarkup($text, $id, $error='')
    {
        $response['message'] = $text;
        $response['message'].= "\n\n".$error."Шаг №2. \nВыберите <b>Тип операции</b> (Выдача/Забор)";
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Выдача',
                        "callback_data" => "setFieldToApp_".$id.'_8'
                    ],

                    [
                        'text' => 'Забор',
                        "callback_data" => "setFieldToApp_".$id.'_7'
                    ],
                ]
            ]
        ]);
        return $response;
    }

    /** Шаг №3 ввод имени с которым обращаться*/
    public static function getAgentSecondNameMarkup($text='', $app_id=0)
    {
        $response['message'] = $text;
        $response['message'] = "Шаг №3. \nВведите <b>Имя с которым обращаться к контрагенту</b>";
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

        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [

                $buttons_array

            ]
        ]);
        return $response;
    }

    /** Шаг №4 номер телефона*/
    public static function getAgentPhoneMarkup($text='', $error='', $app_id=0)
    {
        $response['message'] = $text;
        $response['message'] = $error."\nШаг №4. \nВведите <b>Номер телефона контрагента</b> (обязательно должен содержать код через +, например +79901234567)";
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

        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [

                $buttons_array

            ]
        ]);
        return $response;
    }
    /** Шаг №5 (выдача) выбор кассы*/
    public static function getRespCashRoomListMarkupInProcess($id): array
    {
        $response['message'] = '';
        $cash_room_list = [];
        $cash_rooms = new CashRoom();
        $applications = new Applications();
        $app = $applications->find($id);
        $manager_cash_rooms = $app->manager()->getManagerCashRooms();
        if(count($manager_cash_rooms)==0) {
            $list = $cash_rooms->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();
        } elseif (count($manager_cash_rooms)>1) {
            $list = $cash_rooms->where('ID', $manager_cash_rooms)->select(['ID', 'NAME'])->get()->getArray();
        }
        if($app->isPayment()){
            $response['message'].= "Шаг №5. \nВыберите <b>Кассу</b>";
        } else {
            $response['message'].= "Шаг №5. \nВыберите <b>Кассу</b>";
        }
        //$response['message'].= "\nИнформация по кассам:\n";
        if (ArrayHelper::checkFullArray($list)) {
            foreach ($list as $cash_room) {
                //$response['message'] .= CREMarkup::getCashRoomInfoMarkup($cash_room['ID']);
                $cash_room_list[] = [
                    'text' => $cash_room['NAME'],
                    "callback_data" => "setCashRoomToApp_".$app->getId().'_'.$cash_room['ID']
                ];
            }
        }
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [$cash_room_list]
        ]);
        return $response;
    }
    /** Шаг №5 (забор) ввод адреса*/
    public static function getRespAddAddressMarkup($text, $error='', $app_id=0): array
    {
        $response['message'] = $text;
        $step = 5;
        if($app_id>0){
            $applications = new Applications();
            $app = $applications->find($app_id);
            if($app->isPayment())
                $step = 6;
        }
        $response['message'].= "\n\n".$error."Шаг №$step. \nВведите <b>адрес</b> контактного лица";

        return $response;
    }
    /** Шаг №6 ввод комментария*/
    public static function getComentMarkup($text, $id)
    {

        $step = 6;
        if($id>0){
            $applications = new Applications();
            $app = $applications->find($id);
            if(count($app->manager()->manager_cash_rooms())==1)
                $step = 5;
        }
        $response['message'] = $text;
        $response['message'] = "Шаг №$step.\nВведите <b>Комментарий к заявке</b>  (Шаг можно пропустить)";
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Пропустить',
                        "callback_data" => "NotSetComment_".$id
                    ],
                ]
            ]
        ]);
        return $response;
    }

    public static function getCompletedAppMarkup($text)
    {
        $response['message'] = $text;
        $response['message'] = "Заявка создана";
        $response['buttons'] = json_encode([
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
        return $response;
    }

    public static function getOperationsMarkup($step, $app_id){
        $next_step = $step+1;
        $next_step = count((new Operation())->find($app_id)->manager()->manager_cash_rooms())==1?$next_step-1:$next_step;

        switch ($step){
            case 0:
                $operation = (new Operation())->find($app_id);
                $manager_cash_rooms = $operation->manager()->manager_cash_rooms();
                if(count($manager_cash_rooms)!=1){
                    $markup = self::operationsMarkupGetCashRooms(1, $app_id);
                }
                break;
            case 1:
                $markup = self::operationsMarkupGetFileContext($next_step, $app_id);
                break;
            case 2:
                $markup = self::operationsMarkupGetWho($next_step, $app_id);
                break;
            case 3:
                $markup = self::operationsMarkupGetWhom($next_step, $app_id);
                break;
            case 4:
                $markup = self::operationsMarkupGetStWho($next_step, $app_id);
                break;
            case 5:
                $markup = self::operationsMarkupGetStWhom($next_step, $app_id);
                break;
            case 6:
                $markup = self::operationsMarkupGetComment($next_step, $app_id);
                break;
        }
        return $markup;
    }

    private static function operationsMarkupGetFileContext($step, $app_id)
    {
        $return_array['message'] = "Шаг №$step\n";
        $return_array['message'].= "Добавьте файл и описание (описание не обязательно). Или добавьте текст";
        $return_array['buttons']  = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => "Не загружать и продолжить",
                        "callback_data" => "NotSetOperationFile_".$app_id
                    ],
                    [
                        'text' => "Отменить создание",
                        "callback_data" => "CancelOperationByManager_".$app_id
                    ],
                ]
            ]
        ]);
        return $return_array;
    }

    private static function operationsMarkupGetWho($step, $app_id)
    {
        $return_array['message'] = "Шаг №$step\n";
        $return_array['message'].= "Введите Кто";
        $return_array['buttons']  = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => "Отменить создание",
                        "callback_data" => "CancelOperationByManager_".$app_id
                    ],
                ]
            ]
        ]);
        return $return_array;
    }
    private static function operationsMarkupGetWhom($step, $app_id)
    {
        $return_array['message'] = "Шаг №$step\n";
        $return_array['message'].= "Введите Кому";
        $return_array['buttons']  = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => "Отменить создание",
                        "callback_data" => "CancelOperationByManager_".$app_id
                    ],
                ]
            ]
        ]);
        return $return_array;
    }
    private static function operationsMarkupGetStWho($step, $app_id)
    {
        $return_array['message'] = "Шаг №$step\n";
        $return_array['message'].= "Введите Ставка кто";
        $return_array['buttons']  = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => "Отменить создание",
                        "callback_data" => "CancelOperationByManager_".$app_id
                    ],
                ]
            ]
        ]);
        return $return_array;
    }
    private static function operationsMarkupGetStWhom($step, $app_id)
    {
        $return_array['message'] = "Шаг №$step\n";
        $return_array['message'].= "Введите Ставка Кому";
        $return_array['buttons']  = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => "Отменить создание",
                        "callback_data" => "CancelOperationByManager_".$app_id
                    ],
                ]
            ]
        ]);
        return $return_array;
    }
    private static function operationsMarkupGetComment($step, $app_id)
    {
        $return_array['message'] = "Шаг №$step\n";
        $return_array['message'].= "Введите комментарий";
        $return_array['buttons']  = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => "Не вводить комментарий",
                        "callback_data" => "NotSetCommentOperation_".$app_id
                    ],
                    [
                        'text' => "Отменить создание",
                        "callback_data" => "CancelOperationByManager_".$app_id
                    ],
                ]
            ]
        ]);
        return $return_array;
    }

    public static function operationsMarkupGetCashRooms($step, $app_id)
    {
        if($app_id>0){
            $operations = new Operation();
            $app = $operations->find($app_id);
            if(count($app->manager()->manager_cash_rooms())==1){

            } else {
                $list = $return_array = [];
                $manager_cash_rooms = $app->manager()->manager_cash_rooms();
                if(count($manager_cash_rooms)==0) {
                    $list = (new CashRoom())->where('ACTIVE', 'Y')->select(['ID', 'NAME', 'PROPERTY_OPERATION_TYPE_NAME'])->get()->getArray();
                } elseif (count($manager_cash_rooms)>1) {
                    $ids = [];
                    foreach ($manager_cash_rooms as $manager_cash_room){
                        $ids[] = $manager_cash_room['ID'];
                    }
                    $list = (new CashRoom())->where('ID', $ids)->select(['ID', 'NAME', 'PROPERTY_OPERATION_TYPE_NAME'])->get()->getArray();
                }
                $return_array['message'].= "Шаг №1. \nВыберите <b>учет</b>";
                if (ArrayHelper::checkFullArray($list)) {
                    $cash_room_list = [];
                    foreach ($list as $cash_room) {
                        $cash_room_list[] = [
                            'text' => $cash_room['PROPERTY_OPERATION_TYPE_NAME_VALUE'],
                            "callback_data" => "setCashRoomToOperation_".$app->getId().'_'.$cash_room['ID']
                        ];
                    }
                    $cash_room_list[] = [
                        'text' => "Отменить создание",
                        "callback_data" => "CancelOperationByManager_".$app_id
                    ];
                    $return_array['buttons'] = json_encode([
                        'resize_keyboard' => true,
                        'inline_keyboard' => [$cash_room_list]
                    ]);
                }

                return $return_array;
            }
        }
    }


}