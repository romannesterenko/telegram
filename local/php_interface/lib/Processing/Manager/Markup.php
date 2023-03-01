<?php
namespace Processing\Manager;
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

    /** Шаг №5 ввод комментария*/
    public static function getComentMarkup($text, $id)
    {
        $response['message'] = $text;
        $response['message'] = "Шаг №5.\nВведите <b>Комментарий к заявке</b>  (Шаг можно пропустить)";
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


}