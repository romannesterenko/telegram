<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

$request = HttpApplication::getInstance()->getContext()->getRequest();

$module_id = htmlspecialcharsbx($request["mid"] != "" ? $request["mid"] : $request["id"]);
Loader::includeModule($module_id);
$aTabs = array(
    array(
        "DIV" => "common_settings_tab",
        "TAB" => "Общие настройки",
        "TITLE" => "Общие настройки",
        "OPTIONS" => array(
            "Обработка новой заявки ответственным",
            array(
                "time_for_responsible_wait",
                'Время на обработку новой заявки ответственным, мин',
                "",
                array("text", 60)
            ),
            array(
                "interval_to_remind_for_responsible",
                'Интервал напоминания о необработанных заявках ответственному, мин',
                "",
                array("text", 60)
            ),
            array(
                "text_to_remind_for_responsible",
                'Текст сообщения о необработанных заявках (список заявок будет в кнопках с сообщением)',
                "",
                array("textarea", 3, 60)
            ),
            "Обработка заявки экипажем инкассаторов",

            array(
                "time_for_crew_wait",
                'Время на подтверждение заявки экипажем, мин',
                "",
                array("text", 60)
            ),
            array(
                "interval_to_remind_for_crew",
                'Интервал напоминания о необработанной экипажем заявке ответственному за инкассацию, мин',
                "",
                array("text", 60)
            ),
            array(
                "note" => "Значения между # - переменные для конкретной заявки, например в сообщении на место #APP_ID# подставится реальный номер заявки, а отношении которой сформировано сообщение. #RETURN# - перенос строки"
            ),
            array(
                "text_to_remind_for_crew",
                'Текст сообщения о необработанной экипажем заявке ответственному за инкассацию',
                "",
                array("textarea", 3, 60)
            ),
        )
    ),
    array(
        "DIV" => "integration",
        "TAB" => "Интеграция",
        "TITLE" => "Настройки интеграции с внешними сервисами",
        "OPTIONS" => array(
            'Подключение Telegram',
            array(
                "telegram_bot_token",
                'Токен бота телеграм',
                "",
                array("text", 60)
            ),
            'Подключение Mattermost',
            array(
                "mattermost_is_testing_mode",
                'Тестовый режим Mattermost',
                "",
                array("checkbox")
            ),
            array(
                "mattermost_webhook_token",
                'Токен вебхука Mattermost канал Касса',
                "",
                array("text", 60)
            ),
            array(
                "mattermost_webhook_operation_token",
                'Токен вебхука Mattermost канал Операция',
                "",
                array("text", 60)
            )

        )
    ),
    array(
        "DIV" => "common_messages",
        "TAB" => "Общие сообщения",
        "TITLE" => "Общие сообщения",
        "OPTIONS" => array(
            'Запрет доступа',
            array(
                "telegram_bot_denie_message",
                'Сообщение о запрете доступа (логина пользователя нет в системе)',
                "",
                array("text", 60)
            ),
            array(
                "telegram_bot_denie_rights_message",
                'Сообщение об ограничении прав (есть в системе, но не выставлена роль)',
                "",
                array("text", 60)
            ),
            'Приветственные сообщения',
            array(
                "telegram_bot_common_hello_message",
                'Приветственное сообщение после успешной регистрации',
                "",
                array("text", 60)
            ),
            'Сообщения об ошибках',
            array(
                "telegram_bot_wrong_command",
                'Сообщение об ошибке обработки действия (отправленного текста)',
                "",
                array("text", 60)
            ),
            array(
                "telegram_bot_wrong_callback",
                'Сообщение об ошибке обработки кнопки',
                "",
                array("text", 60)
            ),
            array(
                "telegram_bot_wrong_action_for_app",
                'Сообщение ошибки обработки заявки (повторный клик на кнопку)',
                "",
                array("text", 60)
            ),

        )
    ),
    array(
        "DIV" => "buttons",
        "TAB" => "Тексты кнопок",
        "TITLE" => "Тексты кнопок",
        "OPTIONS" => array(
            'Тексты кнопок для менеджера',
            array(
                "button_text_manager_new_app",
                'Создание новой заявки',
                "",
                array("text", 60)
            ),
            array(
                "button_text_manager_restore_app",
                'Восстановление процесса оформления заявки',
                "",
                array("text", 60)
            ),
            array(
                "button_text_manager_cancel_restore_app",
                'Отказ от восстановления оформления и переход к созданию новой зявки',
                "",
                array("text", 60)
            ),
            array(
                "button_text_manager_cancel_new_app",
                'Отмена создания новой заявки',
                "",
                array("text", 60)
            ),
            array(
                "button_text_manager_app_list",
                'Список заявок, созданных менеджером',
                "",
                array("text", 60)
            ),
            array(
                "button_text_manager_cancel_app",
                'Отменить созданную заявку из списка',
                "",
                array("text", 60)
            ),
            array(
                "button_text_manager_reset_cancel_app",
                'Сбросить отмену заявки',
                "",
                array("text", 60)
            ),
            'Тексты кнопок для ответственного за учет',
            array(
                "button_text_resp_apps_list_to_work",
                'Список новых заявок в работу',
                "",
                array("text", 60)
            ),
            array(
                "button_text_resp_apps_list_new",
                'Список заявок в работе',
                "",
                array("text", 60)
            ),
            array(
                "button_text_resp_cash_room_list",
                'Полная информация по кассам',
                "",
                array("text", 60)
            ),
            array(
                "button_text_resp_allow_app",
                'Одобрение заявки и взятие её в работу',
                "",
                array("text", 60)
            ),
            array(
                "button_text_resp_denie_app",
                'Отклонение заявки',
                "",
                array("text", 60)
            ),
            array(
                "button_text_resp_reset_cancel_app",
                'Отменить отклонение заявки',
                "",
                array("text", 60)
            ),
            'Тексты кнопок для кассира',
            array(
                "button_text_cre_apps_list_payment",
                'Список заявок на выдачу (Кнопка меню)',
                "",
                array("text", 60)
            ),
            array(
                "button_text_cre_apps_list_receive",
                'Список заявок на забор  (Кнопка меню)',
                "",
                array("text", 60)
            ),
            array(
                "button_text_cre_start_new_work_day",
                'Начало рабочего дня  (Кнопка меню)',
                "",
                array("text", 60)
            ),
            array(
                "button_text_cre_end_work_day",
                'Закрытие рабочего дня  (Кнопка меню)',
                "",
                array("text", 60)
            ),

            'Тексты кнопок для старшего смены',
            array(
                "button_text_crs_waiting_for_open",
                'Список касс, ожидающих открытия смены',
                "",
                array("text", 60)
            ),
            array(
                "button_text_crs_waiting_for_close",
                'Список касс, ожидающих закрытия смены',
                "",
                array("text", 60)
            ),
        )
    ),

);
if ($request->isPost() && check_bitrix_sessid()) {

    foreach ($aTabs as $aTab) {

        foreach ($aTab["OPTIONS"] as $arOption) {

            if (!is_array($arOption)) {

                continue;
            }

            if ($arOption["note"]) {

                continue;
            }

            if ($request["apply"]) {

                $optionValue = $request->getPost($arOption[0]);

                if ($arOption[0] == "switch_on") {

                    if ($optionValue == "") {

                        $optionValue = "N";
                    }
                }

                Option::set($module_id, $arOption[0], is_array($optionValue) ? implode(",", $optionValue) : $optionValue);
            } elseif ($request["default"]) {

                Option::set($module_id, $arOption[0], $arOption[2]);
            }
        }
    }

    LocalRedirect($APPLICATION->GetCurPage() . "?mid=" . $module_id . "&lang=" . LANG);
}
$tabControl = new CAdminTabControl(
    "tabControl",
    $aTabs
);

$tabControl->Begin(); ?>

<form action="<?php echo($APPLICATION->GetCurPage()); ?>?mid=<?php echo($module_id); ?>&lang=<?php echo(LANG); ?>" method="post">

    <?php
    foreach ($aTabs as $aTab) {

        if ($aTab["OPTIONS"]) {

            $tabControl->BeginNextTab();

            __AdmSettingsDrawList($module_id, $aTab["OPTIONS"]);
        }
    }

    $tabControl->Buttons();
    ?>

    <input type="submit" name="apply" value="<?php echo(Loc::GetMessage("FALBAR_TOTOP_OPTIONS_INPUT_APPLY")); ?>"
           class="adm-btn-save"/>
    <input type="submit" name="default" value="<?php echo(Loc::GetMessage("FALBAR_TOTOP_OPTIONS_INPUT_DEFAULT")); ?>"/>
    <script>
        document.addEventListener("DOMContentLoaded", function(){
            let sender_today_time = document.querySelector('input[name="sender_today_time"]');
            let sender_today_n_days = document.querySelector('input[name="sender_today_n_days"]');
            sender_today_time.setAttribute('type', 'time');
            sender_today_n_days.setAttribute('type', 'time');
        });
    </script>
    <style>
        .adm-workarea input[type="time"]{
            font-size: 13px;
            height: 25px;
            padding: 0 5px;
            margin: 0;
            background: #fff;
            border: 1px solid;
            border-color: #87919c #959ea9 #9ea7b1 #959ea9;
            border-radius: 4px;
            color: #000;
            box-shadow: 0 1px 0 0 rgb(255 255 255 / 30 %), inset 0 2px 2px -1px rgb(180 188 191 / 70 %);
            display: inline-block;
            outline: none;
            vertical-align: middle;
            -webkit-font-smoothing: antialiased;
            font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
        }
    </style>
    <?php
    echo(bitrix_sessid_post());
    ?>

</form>
<?php $tabControl->End(); ?>
