<?php
use Bitrix\Main\Localization\Loc;
use Helpers\IBlockHelper;
use Helpers\UserHelper;
use Polls\ProcessPoll;
use Teaching\CourseCompletion;
use Teaching\Courses;
use Teaching\Enrollments;
use Teaching\Roles;
const NEED_AUTH = true;
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
global $APPLICATION;
$APPLICATION->SetTitle(Loc::getMessage('MAIN_TITLE'));
$list = (new \Models\Applications())
    ->where('PROPERTY_STATUS', 48)
    ->where('>DATE_CREATE', date('d.m.Y 00:00:01'))
    ->where('<DATE_CREATE', date('d.m.Y 23:59:59'))
    ->where('PROPERTY_OPERATION_TYPE', 8)
    ->where('PROPERTY_FOR_COL_RESP', 1)
    ->get()->getArray();
foreach($list as $item){
    //(new \Models\Order())->createFromAppID($item['ID']);
}
?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>