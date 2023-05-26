<?php

use Api\Telegram;

const NEED_AUTH = true;
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");?>

<?php
//echo \Settings\Common::getCreatingOperationProcess(1646);    //getCreatingOperationProcess(1646);
//\Settings\Common::resetDuringAppByResponsible();
//\Settings\Common::resetCloseDaySession();
//Telegram::sendMessageToResp("Заявка №1941\n От контрагента Охрана поступили деньги: 176 500 руб");
$types_array = [
    2 => 'Входящее',
    3 => 'Исходящее',
];
$types_entities_array = [
    4 => 'Операция',
    5 => 'Заявка',
    6 => 'Поиск',
];

$headers = [
    'UF_PARAMS' => 'Текст',
    'UF_ID' => 'Telegram Id',
    'UF_NAME' => 'Имя сотрудника',
    'UF_DATE' => 'Дата',
    'UF_ROLE' => 'Роль',
    'UF_TYPE' => 'Тип действия',
    'UF_TYPE_ENTITTY' => 'Сущность',
    'UF_ENTITY_ID' => 'ID сущности',
];
$roles_array = [
    "Менеджер",
    "Кассир",
    "Ответственный за инкассацию",
    "Ответственный за учет",
    "Инкассатор",
    "Старший смены (касса)",
];
$names_array = [];
foreach((new \Models\Staff())->get()->getArray() as $employee)
    $names_array[] = $employee['NAME'];
$list = (new \Models\Log())->where('>ID', 0);
if(!empty($_REQUEST['role'])&&$_REQUEST['role']!='Выбрать роль'){
    $list->where('UF_ROLE', $_REQUEST['role']);
}
if(!empty($_REQUEST['name'])&&$_REQUEST['name']!='Выбрать имя сотрудника'){
    $list->where('UF_NAME', $_REQUEST['name']);
}
if(!empty($_REQUEST['tea'])&&$_REQUEST['tea']!='0'){
    $list->where('UF_TYPE_ENTITTY', $_REQUEST['tea']);
}
if(!empty($_REQUEST['period'])&&$_REQUEST['period']!='0'){
    if($_REQUEST['period']=='today')
        $list->where('>=UF_DATE', date('d.m.Y 00:00:01'))->where('<=UF_DATE', date('d.m.Y 23:59:59'));
    if($_REQUEST['period']=='yesterday')
        $list->where('>=UF_DATE', date('d.m.Y 00:00:01', strtotime('yesterday')))->where('<=UF_DATE', date('d.m.Y 23:59:59', strtotime('yesterday')));
    if($_REQUEST['period']=='this_week')
        $list->where('>=UF_DATE', date('d.m.Y 00:00:01', strtotime('this monday')))->where('<=UF_DATE', date('d.m.Y 23:59:59'));
    if($_REQUEST['period']=='last_week')
        $list->where('>=UF_DATE', date('d.m.Y 00:00:01', strtotime('last monday')))->where('<=UF_DATE', date('d.m.Y 23:59:59', strtotime('last sunday')));
}
$list = $list->limit(500)->get()->getArray();

?>
<div class="row p-5 ">
    <form class="row g-3" method="get">
        <div class="col-auto">
            <select class="form-select" aria-label="Default select example" name="role">
                <option selected>Выбрать роль</option>
                <?php foreach ($roles_array as $role){?>
                    <option value="<?=$role?>"<?=$_REQUEST['role']==$role?' selected':''?>><?=$role?></option>
                <?php }?>
            </select>
        </div>
        <div class="col-auto">
            <select class="form-select" aria-label="Default select example" name="name">
                <option selected>Выбрать имя сотрудника</option>
                <?php foreach ($names_array as $name){?>
                    <option value="<?=$name?>"<?=$_REQUEST['name']==$name?' selected':''?>><?=$name?></option>
                <?php }?>
            </select>
        </div>
        <div class="col-auto">
            <select class="form-select" aria-label="Default select example" name="tea">
                <option selected value="0">Сущность</option>
                <?php foreach ($types_entities_array as $tea_key => $tea){?>
                    <option value="<?=$tea_key?>"<?=$_REQUEST['tea']==$tea_key?' selected':''?>><?=$tea?></option>
                <?php }?>
            </select>
        </div>
        <div class="col-auto">
            <select class="form-select" aria-label="Default select example" name="period">
                <option value="0">Период</option>
                <option value="today"<?=$_REQUEST['period']=="today"?' selected':''?>>Сегодня</option>
                <option value="yesterday"<?=$_REQUEST['period']=="yesterday"?' selected':''?>>Вчера</option>
                <option value="this_week"<?=$_REQUEST['period']=="this_week"?' selected':''?>>Текущая неделя</option>
                <option value="last_week"<?=$_REQUEST['period']=="last_week"?' selected':''?>>Прошедшая неделя</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary mb-3">Применить фильтр</button>
            <a href="/" class="btn btn-primary mb-3">Сбросить фильтр</a>
        </div>
    </form>
</div>
<div class="row p-5 ">
    <table class="table table-striped">
        <thead>
            <tr>
                <?php foreach ($list[0] as $name=>$value){
                    //if($name=='ID') continue;?>
                    <th scope="col" class=" text-center" <?=$name=='UF_PARAMS'?'style=" width:25%"':''?>><?=$headers[$name]??$name?></th>
                <?php }?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $item) {?>
                <tr>
                    <?php foreach ($item as $item_name => $item_value){
                        $item_value = str_replace('<button>', '<button class="btn btn-success w-100 my-1">', $item_value);
                        $item_value = str_replace('</b>', '</b><br/>', $item_value);
                        if($item_name=='UF_TYPE') {
                            $item_value = $types_array[$item_value];
                        }
                        if($item_name=='UF_TYPE_ENTITTY') {
                            $item_value = $types_entities_array[$item_value];
                        }?>
                        <td scope="row" class="text-center" ><?=$item_value?></td>
                    <?php }?>
                </tr>
            <?php }?>
        </tbody>
    </table>
</div>

<?/*$APPLICATION->IncludeComponent("bitrix:highloadblock.list","logs",Array(
        "BLOCK_ID" => "2",
        "CHECK_PERMISSIONS" => "Y",
        "DETAIL_URL" => "detail.php?BLOCK_ID=#BLOCK_ID#&ROW_ID=#ID#",
        "FILTER_NAME" => "myfilter",
        "PAGEN_ID" => "page",
        "ROWS_PER_PAGE" => "20"
    )
);*/?>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>