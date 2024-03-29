<?php
namespace Processing\CollectorsResponsible;
use Helpers\ArrayHelper;
use Models\Applications;
use Models\CashRoom;
use Models\Crew;
use Settings\Common;
use \Processing\CashRoomEmployee\Markup as CREMarkup;

class Markup
{
    public static function getMarkupByResp($app_id): array
    {
        $applications = new Applications();
        $app = $applications->find($app_id)->get();
        $markup = [];
        switch ((int)$app->getField('RESP_STEP')){
            case 0:
                //$markup = self::getRespAddSumMarkup();
                $markup = self::getRespAddTimeMarkup('');
                break;
            case 1:
                if($app->isPayment()){
                    $markup = self::getRespAddAddressMarkup('');
                    //$markup = self::getRespCashRoomListMarkupInProcess($app->prepareAppDataMessage($app_id), $app_id);
                }else{
                    //$markup = self::getRespAddTimeMarkup('');
                    $markup = self::getRespAddAddressMarkup('');
                }
                break;
            case 2:
                if($app->isPayment()){
                    $markup = self::getRespCrewListMarkup('', $app_id);
                }else{
                    $markup = self::getRespCrewListMarkup('', $app_id);
                }
                break;
            case 3:
                if($app->isPayment()){
                    $markup = self::getRespAddTimeMarkup('');
                }else{
                    $markup = self::getRespAddComentMarkup('', $app_id);
                }
                break;
            case 4:
                if($app->isPayment()){
                    $markup = self::getRespAddAddressMarkup('');
                }else{
                    $markup = self::getRespAddComentMarkup('', $app_id);
                }
                break;
            case 5:
                if($app->isPayment()){
                    $markup = self::getRespCrewListMarkup('', $app_id);
                }
                break;
            case 6:
                if($app->isPayment()){
                    $markup = self::getRespAddComentMarkup('', $app_id);
                }else{
                    $markup = self::getRespCompleteAppMarkup('');
                }
                break;
            case 8:
                if($app->isPayment()){

                }else{
                    $markup = self::getRespCompleteAppMarkup('');
                }
                break;
        }
        return $markup;
    }
    public static function getMessagetoRespNewAppMarkup($text, $app_id, $hello_message=true): array
    {
        $response['message'] = "Сформирована новая заявка\n\n";
        if($hello_message)
            $response['message'].= $text;
        else
            $response['message'] = $text;

        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => Common::getButtonText('resp_allow_app'),
                        'callback_data' => 'allowAppByResp_'.$app_id
                    ],
                    [
                        'text' => Common::getButtonText('resp_denie_app'),
                        'callback_data' => 'RespCancelApp_'.$app_id
                    ],
                ]
            ]
        ]);
        return $response;
    }

    public static function getRespAddTimeMarkup($text, $error=''): array
    {
        $response['message'] = $text;
        $response['message'].= "\n\n".$error."Шаг №1. \nВведите <b>время</b>";
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => "Сброс заявки",
                        "callback_data" => 'ResetRespApp_' . Common::DuringAppByCollResponsible()
                    ],
                ]
            ]
        ]);
        return $response;
    }
    public static function getRespAddAddressMarkup($text, $error=''): array
    {
        $response['message'] = $text;
        $response['message'].= "\n\n".$error."Шаг №2. \nВведите <b>адрес</b>";
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => "Сброс заявки",
                        "callback_data" => 'ResetRespApp_' . Common::DuringAppByCollResponsible()
                    ],
                ]
            ]
        ]);
        return $response;
    }
    public static function getRespAddComentMarkup($text, $app_id): array
    {
        $step = 4;
        $response['message'] = $text;
        $response['message'].= "Шаг №$step.\nВведите <b>Комментарий к заявке</b>  (Шаг можно пропустить)";
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Пропустить шаг',
                        "callback_data" => "NotSetRespComment_".$app_id
                    ],
                    [
                        'text' => "Сброс заявки",
                        "callback_data" => 'ResetRespApp_' . Common::DuringAppByCollResponsible()
                    ],
                ]
            ]
        ]);
        return $response;
    }
    public static function getRespCompleteAppMarkup($text): array
    {
        $response['message']= "Заявка оформлена\n\n";
        $response['message'].= $text;
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                [
                    [
                        'text' => Common::getButtonText('resp_apps_list_new')
                    ],
                    [
                        'text' => Common::getButtonText('resp_cash_room_list')
                    ]
                ]
            ]
        ]);
        return $response;
    }
    private static function getRespCrewListMarkup($text, $id): array
    {
        $crew_list = [];
        $response['message'] = $text;
        $response['message'].= "\n\nШаг №3. \nВыберите <b>экипаж</b>";
        $crews = new Crew();
        $list = $crews->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();

        if (ArrayHelper::checkFullArray($list)) {

            foreach ($list as $crew) {
                $crew_list[] = [
                    [

                    'text' => $crew['NAME'],
                    "callback_data" => "setCrewToApp_".$id.'_'.$crew['ID']

                    ]
                ];
            }
        }
        $crew_list[] = [
            [

                'text' => "Сброс заявки",
                "callback_data" => 'ResetRespApp_' . Common::DuringAppByCollResponsible()

            ]
        ];
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => $crew_list
        ]);
        return $response;
    }

    public static function getNeedSetCashRoomByAppMarkup($appId)
    {
        $applications = new Applications();
        $cash_room_list = [];
        $cash_rooms = new CashRoom();
        $app = $applications->find($appId);
        $response['message'] = "Заявка №".$app->getId()."\n";
        if(!$app->isPayment()){
            $response['message'].= "Для выполнения заявки по забору средств, необходимо выбрать кассу\n";
            $response['message'].= "Сумма - ".number_format($app->getSum(), 0, ', ' , ' ')."\n";
            $list = $cash_rooms->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();
            if (ArrayHelper::checkFullArray($list)) {
                foreach ($list as $cash_room) {
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
        }
        return $response;
    }
}