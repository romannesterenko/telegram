<?php
namespace Processing\Responsible;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\Applications;
use Models\CashRoom;
use Models\Crew;
use Models\Currency;
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
                if($app->isPayment()) {
                    $markup = self::getRespGiveAfterMarkup($app_id);
                } else {
                    $markup = self::getRespAddSumMarkup('', $app_id);
                }
                break;
            case 1:
                if($app->isPayment()){
                    if($app->hasBeforeApps()&&$app->getStatus()==44) {
                        $last_apps = (new Applications())->whereNot('PROPERTY_STATUS', 27)->where('ID', $app->getField('GIVE_AFTER'))->get()->getArray();
                        $markup['message'] = "Заявка №".$app->getId()." (".$app->contragent().") оформлена и ожидает выполнения других заявок\n";
                        if(ArrayHelper::checkFullArray($last_apps)){
                            $markup['message'].= "Список оставшихся заявок для выполнения текущей заявки\n";
                            foreach ($last_apps as $last_app) {
                                $markup['message'] .= "\n===================\n";
                                $markup['message'] .= "Заявка №" . $last_app['ID'] . ". Контрагент - " . $last_app['PROPERTY_AGENT_OFF_NAME_VALUE'];
                            }
                            $markup['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => "Ждать",
                                            'callback_data' => 'waitMore_'.$app->getId()
                                        ],
                                        [
                                            'text' => "Выдать деньги",
                                            'callback_data' => 'GiveMoney_'.$app->getId()
                                        ],

                                    ]
                                ]
                            ]);
                        }else{
                            $markup['message'].= "Все связанные заявки выполнены, продолжайте оформление\n";
                            $markup['buttons'] = json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => "Выдать деньги",
                                            'callback_data' => 'GiveMoney_'.$app->getId()
                                        ],
                                    ]
                                ]
                            ]);
                        }
                    } else {
                        if((int)$app->getField('SUM_ENTER_STEP')==0) {
                            $text = '';
                            if(count($app->getCurrencies())>0&&count($app->getCurrencies())==count($app->getSum())){
                                $cash = $app->getCash();
                                $text = "Введенная сумма: ".implode(", ", $cash)."\n";
                            }
                            $markup = self::getRespAddCurrencyMarkup($text, $app_id);
                        } else {
                            $currencies_array = $app->getCurrencies();
                            $sums_array = $app->getSum();
                            $need_currency_key = array_diff(array_keys($currencies_array), array_keys($sums_array));
                            if(ArrayHelper::checkFullArray($need_currency_key)) {
                                $currency_id = $currencies_array[current($need_currency_key)];
                                $currency = (new Currency())->find($currency_id);
                                $text = '';
                                $cash = $app->getCash();
                                if(ArrayHelper::checkFullArray($cash))
                                    $text = "Введенная сумма: ".implode(", ", $cash)."\n";
                                $markup['message'] = $text.'Введите сумму в валюте ' . $currency->getName();
                                $inline_keys[] = [
                                    [
                                        'text' => "Сброс заявки",
                                        "callback_data" => 'ResetRespApp_' . $app->getId()
                                    ]
                                ];
                                $inline_keys[] = [
                                    [
                                        'text' => 'В черновик',
                                        "callback_data" => "SetToDraftApp_".$app->getId()
                                    ]
                                ];
                                $markup['buttons'] = json_encode([
                                    'resize_keyboard' => true,
                                    'inline_keyboard' => $inline_keys
                                ]);
                            }
                        }
                    }
                } else {
                    $markup = self::getRespAddTimeMarkup('');
                }
                break;
            case 2:
                if ($app->isPayment()) {
                    if ($app->hasBeforeApps()) {
                        if((int)$app->getField('SUM_ENTER_STEP')==0)
                            $markup = self::getRespAddCurrencyMarkup('', $app_id);
                        else
                            $markup = self::getRespAddSumMarkup('', $app_id);
                    } else {
                        $text="";
                        $cash_room_cash = $app->cash_room()->getCash();
                        if($cash_room_cash['free']<$app->getSum()){
                            $text="Свободная сумма в кассе меньше суммы в заявке\n\n";
                        }
                        $markup = self::getRespAddComentMarkup($text, $app_id);
                    }
                } else {
                    $markup = self::getRespAddComentMarkup('', $app_id);
                }
                break;
            case 3:
                if ($app->isPayment()) {
                    if($app->hasBeforeApps()) {
                        $text="";
                        $cash_room_cash = $app->cash_room()->getCash();
                        if($cash_room_cash['free']<$app->getSum()){
                            $text="Свободная сумма в кассе меньше суммы в заявке\n\n";
                        }
                        $markup = self::getRespAddComentMarkup($text, $app_id);
                    } else {
                        $markup = self::getRespAddComentMarkup('', $app_id);
                        //$markup = self::getRespAddAddressMarkup('', '', $app_id);
                    }
                } else {
                    $markup = self::getRespCrewListMarkup('', $app_id);
                }
                break;
            case 4:
                if($app->isPayment()) {
                    if($app->hasBeforeApps()) {
                        $markup = self::getRespCompleteAppMarkup('');
                    } else {
                        $markup = self::getRespCompleteAppMarkup('');
                        //$markup = self::getRespAddComentMarkup('', $app_id);
                    }
                } else {

                }
                break;
            case 5:
                if($app->isPayment()){
                    if($app->hasBeforeApps()) {
                        $markup = self::getRespAddSumMarkup('', $app_id);
                    } else {
                        //$markup = self::getRespCompleteAppMarkup('');
                    }

                } else {
                    //$markup = self::getRespAddComentMarkup('', $app_id);
                }
                break;
            case 6:
                if($app->isPayment()){

                }else{
                    //$markup = self::getRespCompleteAppMarkup('');
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
        $app = (new Applications())->find($app_id);
        if($app->isPayment()&&(int)$app->getField('RESP_STEP')==0) {
            $response['buttons'] = json_encode([
                'resize_keyboard' => true,
                'inline_keyboard' => [
                    [
                        [
                            'text' => "Одобрить выдачу",
                            'callback_data' => 'allowAppByResp_' . $app_id
                        ],
                        [
                            'text' => Common::getButtonText('resp_denie_app'),
                            'callback_data' => 'RespCancelApp_' . $app_id
                        ],
                    ]
                ],
            ]);
        } else {
            $response['buttons'] = json_encode([
                'resize_keyboard' => true,
                'inline_keyboard' => [
                    [
                        [
                            'text' => Common::getButtonText('resp_allow_app'),
                            'callback_data' => 'setToRefinement_' . $app_id
                        ],
                        [
                            'text' => Common::getButtonText('resp_denie_app'),
                            'callback_data' => 'RespCancelApp_' . $app_id
                        ],
                    ]
                ],
            ]);
        }
        return $response;
    }
    //Выдача. Шаг №1. Сумма сделки
    public static function getRespAddSumMarkup($error='', $app_id=0): array
    {
        $response['message'] = $error."Шаг №2. \nВведите <b>сумму сделки</b>";
        if($app_id>0){
            $applications = new Applications();
            $app = $applications->find($app_id);
            if(!$app->isPayment())
                $response['message'] = $error."Шаг №1. \nВведите <b>сумму сделки</b>";
            else {
                $currencies = $app->getCurrencies();
                $sums = $app->getSum();
                $array_diff = array_diff(array_keys($currencies), array_keys($sums));
                $currency = (new Currency())->find($currencies[current($array_diff)]);
                $response['message'] = "Шаг №2. Введенная сумма: ".implode(", ", $app->getCash())."\nВведите <b>сумму в ".$currency->getGenitive()."</b>";
            }
            $response['buttons'] = json_encode([
                'resize_keyboard' => true,
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Сброс заявки',
                            "callback_data" => "ResetRespApp_".$app_id
                        ],
                        [
                            'text' => 'В черновик',
                            "callback_data" => "SetToDraftApp_".$app_id
                        ],
                    ]
                ]
            ]);
        }

        return $response;
    }
    public static function getRespAddCurrencyMarkup($error='', $app_id=0): array
    {
        $response['message'] = $error."Шаг №2. \nВыберите <b>валюту сделки</b>";
        if($app_id>0){
            $applications = new Applications();
            $app = $applications->find($app_id);
            $list = $app->cash_room()->getCurrencies();
            $currencies_ = $app->getCurrencies();
            $inline_keys = [];
            foreach ($list as $item) {
                if(!in_array($item, $currencies_)) {
                    $currencies = new Currency();
                    $item = $currencies->find($item)->getArray();
                    $inline_keys[] = [
                        [
                            'text' => $item['NAME'],
                            "callback_data" => 'SetCurrencyToApp_' . $app->getId() . "_" . $item['ID']
                        ]
                    ];
                }
            }
            $inline_keys[] = [
                [
                    'text' => "Сброс заявки",
                    "callback_data" => 'ResetRespApp_' . $app->getId()
                ]
            ];
            $inline_keys[] = [
                [
                    'text' => "В черновик",
                    "callback_data" => 'SetToDraftApp_' . $app->getId()
                ]
            ];
            $response['buttons'] = json_encode([
                'resize_keyboard' => true,
                'inline_keyboard' => $inline_keys
            ]);
        }

        return $response;
    }



    public static function getRespAddTimeMarkup($text, $error=''): array
    {
        $response['message'] = $text;
        $response['message'].= "\n\n".$error."Шаг №2. \nВведите <b>время</b>";
        return $response;
    }
    public static function getRespAddAddressMarkup($text, $error='', $app_id): array
    {
        $response['message'] = $text;
        $response['message'] = "Шаг №3. \nВведите <b>адрес</b> контактного лица";
        if($app_id>0){
            $applications = new Applications();
            $app = $applications->find($app_id);
            if($app->hasBeforeApps()){
                $response['message'] = "Шаг №3. \nВведите <b>адрес</b> контактного лица";
            }
        }

        return $response;
    }
    public static function getRespAddComentMarkup($text, $app_id): array
    {
        $applications = new Applications();
        $app = $applications->find($app_id);
        $step = $app->isPayment()?3:3;
        if($app->hasBeforeApps())
            $step = 3;
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
                ]
            ]
        ]);
        return $response;
    }
    public static function getRespAddComentInCreateMarkup($app_id): array
    {
        $response['message'] = "Введите <b>Комментарий к заявке</b>  (Шаг можно пропустить)";
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Пропустить шаг',
                        "callback_data" => "NotSetRespCommentAdd_".$app_id
                    ],
                    [
                        'text' => "Отменить создание",
                        "callback_data" => "CancelCreateApp_".$app_id
                    ]
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
    public static function getRespCashRoomListMarkup(): array
    {
        $response['message'] = "";
        $cash_rooms = new CashRoom();
        $list = $cash_rooms->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();
        if (ArrayHelper::checkFullArray($list)) {
            foreach ($list as $cash_room) {
                $response['message'] .= CREMarkup::getCashRoomInfoMarkup($cash_room['ID']);
            }
        } else {
            $response['message'] = 'Список касс пуст';
        }
        return $response;
    }
    public static function getRespCashRoomListMarkupInProcess($id): array
    {
        $cash_room_list = [];
        $cash_rooms = new CashRoom();
        $applications = new Applications();
        $app = $applications->find($id);
        $list = $cash_rooms->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();
        $response['message'] = "Выберите <b>Кассу</b>";
        if (ArrayHelper::checkFullArray($list)) {
            foreach ($list as $cash_room) {
                //$response['message'] .= CREMarkup::getCashRoomInfoMarkup($cash_room['ID']);
                $cash_room_list[] = [
                    'text' => $cash_room['NAME'],
                    "callback_data" => "setCashRoomToApp_".$app->getId().'_'.$cash_room['ID']
                ];
            }
        }
        $cash_room_list[] = [
            'text' => "Отменить создание",
            "callback_data" => "CancelCreateApp_".$id
        ];
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [$cash_room_list]
        ]);
        return $response;
    }
    private static function getRespCrewListMarkup($text, $id): array
    {
        $applications = new Applications();
        $app = $applications->find($id);
        $crew_list = [];
        $response['message'] = $text;
        if($app->isPayment())
            $response['message'].= "\n\nШаг №3. \nВыберите <b>экипаж</b>";
        else
            $response['message'].= "\n\nШаг №4. \nВыберите <b>экипаж</b>";
        $crews = new Crew();
        $list = $crews->where('ACTIVE', 'Y')->select(['ID', 'NAME'])->get()->getArray();

        if (ArrayHelper::checkFullArray($list)) {

            foreach ($list as $crew) {
                $crew_list[] = [

                    'text' => $crew['NAME'],
                    "callback_data" => "setCrewToApp_".$id.'_'.$crew['ID']

                ];
            }
        }
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [$crew_list]
        ]);
        return $response;
    }

    private static function getRespGiveAfterMarkup($id): array
    {
        $applications = new Applications();
        $applications1 = new Applications();
        $applications_exists = new Applications();
        $pp = $applications1->find($id);
        $response['message'] = "Шаг №1. \nВыбрать заявку, по завершению которой выдать деньги или продолжить оформление.\n";
        $already_exists = $pp->getField('GIVE_AFTER');
        if(ArrayHelper::checkFullArray($already_exists)) {
            $already_exists_list = $applications_exists
                ->where('ID', $already_exists)
                ->select(['ID', 'NAME', 'PROPERTY_SUMM', 'PROPERTY_AGENT_NAME'])
                ->buildQuery()
                ->getArray();
            if (ArrayHelper::checkFullArray($already_exists_list)) {
                $response['message'] .= "\n\nСписок уже привязанных заявок:";
                foreach ($already_exists_list as $already_exists_app) {
                    $response['message'] .= "\n===================\n";
                    $response['message'] .= $already_exists_app['PROPERTY_AGENT_OFF_NAME_VALUE'].". №" . $already_exists_app['ID'];
                }
            }
        }
        if (ArrayHelper::checkFullArray($already_exists)) {
            $app_list[] = [
                [
                    'text' => "Больше не выбирать и продолжить оформление",
                    "callback_data" => "NotSetAfterApp_" . $id
                ]
            ];
        }else{
            $app_list[] = [
                [
                    'text' => "Не выбирать и продолжить оформление",
                    "callback_data" => "NotSetAfterApp_" . $id
                ]
            ];
        }
        $list = $applications->getForLink($already_exists);
        if (ArrayHelper::checkFullArray($list)) {
            foreach ($list as $app) {
                $app_list[] = [
                    [
                        'text' => $app['PROPERTY_AGENT_OFF_NAME_VALUE']. ". №".$app['ID'],
                        "callback_data" => "setAfterApp_".$id.'_'.$app['ID']
                    ]
                ];
            }
        }else{
            if(ArrayHelper::checkFullArray($already_exists)){
                $response['message'].= "\n\nДоступных к привязке заявок больше нет";
            } else {
                $response['message'].= "\n\nДоступных к привязке заявок нет";
            }
        }
        $app_list[] = [
            [
                'text' => 'Сброс заявки',
                "callback_data" => "ResetRespApp_".$id
            ]
        ];
        $app_list[] = [
            [
                'text' => 'В черновик',
                "callback_data" => "SetToDraftApp_".$id
            ]
        ];
        $response['buttons'] = json_encode(['inline_keyboard' => $app_list]);
        return $response;
    }

    public static function getMoreRespGiveAfterMarkup($id): array
    {
        $applications = new Applications();
        $applications1 = new Applications();
        $applications_exists = new Applications();
        $pp = $applications1->find($id);
        $already_exists = $pp->getField('GIVE_AFTER');
        $response['message'] = "Успешно. \nШаг №1. \nВыбрать еще заявку, по завершению которой выдать деньги или продолжить оформление";
        $app_list=[];
        $app_list[] = [
            [
                'text' => "Больше не привязывать и продолжить оформление",
                "callback_data" => "NotSetAfterApp_".$id
            ]
        ];
        $list = $applications->getForLink($already_exists);

        $already_exists_list = $applications_exists
            ->where('ID', $already_exists)
            ->select(['ID', 'NAME', 'PROPERTY_SUMM', 'PROPERTY_AGENT_OFF_NAME'])
            ->buildQuery()
            ->getArray();

        if(ArrayHelper::checkFullArray($already_exists_list)){
            $response['message'].= "\n\nСписок уже привязанных заявок:";
            foreach ($already_exists_list as $already_exists_app){
                $response['message'].="\n===================\n";
                $response['message'].= $already_exists_app['PROPERTY_AGENT_OFF_NAME_VALUE']. ". №".$already_exists_app['ID'];
            }
        }


        if (ArrayHelper::checkFullArray($list)) {
            foreach ($list as $app) {
                $app_list[] = [
                    [
                        'text' => "Заявка №".$app['ID'].". Контрагент - ".$app['PROPERTY_AGENT_OFF_NAME_VALUE'],
                        "callback_data" => "setAfterApp_".$id.'_'.$app['ID']
                    ]
                ];
            }
        }else{
            if(ArrayHelper::checkFullArray($already_exists)){
                $response['message'].= "\n\nДоступных к привязке заявок больше нет";
            } else {
                $response['message'].= "\n\nДоступных к привязке заявок нет";
            }
        }
        $app_list[] = [
            [
                'text' => "Сброс заявки",
                "callback_data" => 'ResetRespApp_' . $id
            ]
        ];
        $app_list[] = [
            [
                'text' => 'В черновик',
                "callback_data" => "SetToDraftApp_".$id
            ]
        ];
        $response['buttons'] = json_encode(['inline_keyboard' => $app_list]);
        return $response;
    }

    public static function getRespAppsListMarkup(array $list)
    {
        if (ArrayHelper::checkFullArray($list)) {
            $inline_keyboard = [];
            foreach ($list as $application) {
                $text = '';
                if($application['PROPERTY_OPERATION_TYPE_VALUE'])
                    $text.=$application['PROPERTY_OPERATION_TYPE_VALUE'] . ". ";
                $inline_keyboard[] = [
                    [
                        "text" => $application['PROPERTY_AGENT_OFF_NAME_VALUE'].'. '.$application['PROPERTY_OPERATION_TYPE_VALUE'].' №'.$application['ID'],
                        "callback_data" => "showApplicationForResponse_" . $application['ID']
                    ]
                ];
            }
            $message = 'Выберите заявку из списка для просмотра или управления';
            $keyboard = array("inline_keyboard" => $inline_keyboard);
            $buttons = json_encode($keyboard);
        } else {
            $message = 'Заявок пока нет';
        }
    }

    /** Шаг №1 ввод имени */
    public static function getAgentNameMarkup($app_id)
    {
        $response['message'] = "Введите <b>Имя контрагента</b> в учете";
        $response['buttons']  = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => "Отменить создание",
                        "callback_data" => "CancelCreateApp_".$app_id
                    ],
                ]
            ]
        ]);
        return $response;
    }

    /** Шаг №2 ввод типа данных Выдача/Забор */
    public static function getOperationTypeMarkup($id)
    {
        $response['message'] = "Выберите <b>Тип операции</b> (Выдача/Забор)";
        $response['buttons'] = json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Выдача',
                        "callback_data" => "setOperationTypeToApp_".$id.'_8'
                    ],

                    [
                        'text' => 'Забор',
                        "callback_data" => "setOperationTypeToApp_".$id.'_7'
                    ],
                    [
                        'text' => "Отменить создание",
                        "callback_data" => "CancelCreateApp_".$id
                    ],

                ]
            ]
        ]);
        return $response;
    }
    public static function getCreateAppMarkup($id)
    {
        $app = (new Applications())->find($id);
        switch ($app->getField('DRAFT_STEP')){
            case 0:
                return self::getAgentNameMarkup($id);
                break;
            case 1:
                return self::getOperationTypeMarkup($id);
                break;
            case 2:
                return self::getRespCashRoomListMarkupInProcess($id);
                break;
            case 3:
                return self::getRespAddComentInCreateMarkup($id);
                break;
        }
    }
}