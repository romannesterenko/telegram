<?php
namespace Processing\Manager;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\CashRoom;
use Models\ElementModel;
use Processing\CashRoomEmployee\Markup as CREMarkup;

class Markup
{
    /** Шаг №1 ввод имени */
    public static function getAgentNameMarkup()
    {
        $response['message'] = "Шаг №1. \nВведите <b>Имя контрагента</b> в учете";
        $response['buttons'] = json_encode([
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
                        'text' => \Settings\Common::getButtonText('manager_cancel_new_app'),
                    ],
                ]
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
    public static function getAgentSecondNameMarkup($text='')
    {
        $response['message'] = $text;
        $response['message'] = "Шаг №3. \nВведите <b>Имя с которым обращаться к контрагенту</b>";
        $response['buttons'] = json_encode([
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
                        'text' => \Settings\Common::getButtonText('manager_cancel_new_app'),
                    ],
                ]
            ]
        ]);
        return $response;
    }

    /** Шаг №4 номер телефона*/
    public static function getAgentPhoneMarkup($text='', $error='')
    {
        $response['message'] = $text;
        $response['message'] = $error."\nШаг №4. \nВведите <b>Номер телефона контрагента</b> (обязательно должен содержать код через +, например +79901234567)";
        $response['buttons'] = json_encode([
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
                        'text' => \Settings\Common::getButtonText('manager_cancel_new_app'),
                    ],
                ]
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
        $list = $cash_rooms->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();
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
        /*if($id>0){
            $applications = new Applications();
            $app = $applications->find($id);
            if($app->isPayment())
                $step = 7;
        }*/
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
        switch ($step){
            case 0:
                $markup = self::operationsMarkupGetFileContext($next_step, $app_id);
                break;
            case 1:
                $markup = self::operationsMarkupGetWho($next_step, $app_id);
                break;
            case 2:
                $markup = self::operationsMarkupGetWhom($next_step, $app_id);
                break;
            case 3:
                $markup = self::operationsMarkupGetStWho($next_step, $app_id);
                break;
            case 4:
                $markup = self::operationsMarkupGetStWhom($next_step, $app_id);
                break;
            case 5:
                $markup = self::operationsMarkupGetComment($next_step, $app_id);
                break;
        }
        return $markup;
    }

    private static function operationsMarkupGetFileContext($step, $app_id)
    {
        $return_array['message'] = "Шаг №$step\n";
        $return_array['message'].= "Добавьте файл и описание (описание не обязательно)";
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


}