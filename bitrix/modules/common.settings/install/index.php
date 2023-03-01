<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;

class common_settings extends CModule{
    public function __construct(){

        if(file_exists(__DIR__."/version.php")){

            $arModuleVersion = array();

            include(__DIR__."/version.php");

            $this->MODULE_ID            = 'common.settings';
            $this->MODULE_VERSION       = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
            $this->MODULE_NAME          = 'Управление';
            $this->MODULE_DESCRIPTION  = 'Основные настройки';
            $this->PARTNER_NAME     = 'partner';
            $this->PARTNER_URI      = '';
        }

        return false;
    }
    public function DoInstall(){

        global $APPLICATION;

        if(CheckVersion(ModuleManager::getVersion("main"), "14.00.00")){

            //$this->InstallFiles();
            //$this->InstallDB();

            ModuleManager::registerModule($this->MODULE_ID);

            //$this->InstallEvents();
        }else{

            $APPLICATION->ThrowException(
                Loc::getMessage("COMMON_SETTINGS_INSTALL_ERROR_VERSION")
            );
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage("COMMON_SETTINGS_INSTALL_TITLE")." \"".Loc::getMessage("COMMON_SETTINGS_NAME")."\"",
            __DIR__."/step.php"
        );

        return false;
    }
    public function DoUninstall(){

        global $APPLICATION;

        $this->UnInstallFiles();
        $this->UnInstallDB();
        $this->UnInstallEvents();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage("FALBAR_TOTOP_UNINSTALL_TITLE")." \"".Loc::getMessage("COMMON_SETTINGS_NAME")."\"",
            __DIR__."/unstep.php"
        );

        return false;
    }

    public function InstallFiles()
    {
    }

    public function InstallDB()
    {
    }

    public function InstallEvents()
    {
    }

    public function UnInstallFiles()
    {
    }

    public function UnInstallDB()
    {
        Option::delete($this->MODULE_ID);

        return false;
    }

    public function UnInstallEvents()
    {
    }
}
