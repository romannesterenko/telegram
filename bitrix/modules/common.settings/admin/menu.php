<?php

use Bitrix\Main\Localization\Loc;

$aMenu = array(

    'parent_menu' => 'global_menu_settings',
    'sort' => 150,
    'text' => "Настройки бота",
    'title' => "title",
    'icon' => 'sys_menu_icon',
    'url' => 'settings.php?lang=LANGUAGE_ID&mid=common.settings',
);

return (!empty($aMenu) ? $aMenu : false);
