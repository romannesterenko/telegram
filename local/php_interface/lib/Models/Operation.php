<?php
namespace Models;
use Api\Mattermost;
use Api\Telegram;
use Bitrix\Main\UI\Uploader\Log;
use CFile;
use CIBlockElement;
use danog\MadelineProto\messages;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use Models\ElementModel as Model;
use Settings\Common;

class Operation extends Model {
    const IBLOCK_ID = 10;

    public function getDrafted($manager)
    {
        return $this->where("PROPERTY_STATUS", 58)->where('MANAGER', $manager)->first();
    }

    public function createNewDraft($manager_id)
    {
        $tmstmp = time();
        $date = date('d.m.Y H:i:s', $tmstmp);
        $fields['NAME'] = 'Менеджер с id '.$manager_id.' - '.$date;
        $properties['STATUS'] = 58;
        $properties['MANAGER'] = $manager_id;
        $properties['STEP'] = 0;
        $fields['PROPERTY_VALUES'] = $properties;
        return self::create($fields);
    }

    public function getStep()
    {
        return (int)$this->getField('STEP');
    }

    public function setFieldToDraft($app_id, $text)
    {
        $draft_operation = $this->find($app_id);
        switch($draft_operation->getStep()){
            case 0:
                break;
            case 1:
                $markup = \Processing\Manager\Markup::getOperationsMarkup($draft_operation->getStep(), $draft_operation->getId());
                if($text['media_group_id']>0){
                    if($draft_operation->manager()->getMediaGroupSession()==$text['media_group_id']){
                        $draft_operation->setFilesMultiple($text);
                        $message = "Вложение сохранено\n";
                        $markup = \Processing\Manager\Markup::getOperationsMarkup($draft_operation->getStep(), $draft_operation->getId());
                    } else {
                        $draft_operation->manager()->setMediaGroupSession($text['media_group_id']);
                        $draft_operation->setFilesMultiple($text);
                        $message = "Вложение сохранено\n";
                        $markup = \Processing\Manager\Markup::getOperationsMarkup($draft_operation->getStep(), $draft_operation->getId());
                    }
                } else {
                    if(ArrayHelper::checkFullArray($text['photo'])||ArrayHelper::checkFullArray($text['document'])) {
                        $draft_operation->setFilesMultiple($text);
                        $message = "Вложение сохранено\n";
                    } else {
                        if(!empty(trim($text['text']))){
                            $draft_operation->setField('FILE_TEXT', trim($text['text']));
                            $draft_operation->setField('STEP', 2);
                            $message = "Информация сохранена\n";
                            $markup = \Processing\Manager\Markup::getOperationsMarkup(2, $draft_operation->getId());
                        }
                    }

                }
                $markup['message'] = $message.$markup['message'];
                break;
            case 2:
                $step = $draft_operation->getStep()+1;
                $draft_operation->setField('WHO', $text['text']);
                $draft_operation->setField('STEP', $step);
                $markup = \Processing\Manager\Markup::getOperationsMarkup($step, $draft_operation->getId());
                break;
            case 3:
                $step = $draft_operation->getStep()+1;
                $draft_operation->setField('WHOM', $text['text']);
                $draft_operation->setField('STEP', $step);
                $markup = \Processing\Manager\Markup::getOperationsMarkup($step, $draft_operation->getId());
                break;
            case 4:
                $step = $draft_operation->getStep()+1;
                $draft_operation->setField('ST_WHO', $text['text']);
                $draft_operation->setField('STEP', $step);
                $markup = \Processing\Manager\Markup::getOperationsMarkup($step, $draft_operation->getId());
                break;
            case 5:
                $step = $draft_operation->getStep()+1;
                $draft_operation->setField('ST_WHOM', $text['text']);
                $draft_operation->setField('STEP', $step);
                $markup = \Processing\Manager\Markup::getOperationsMarkup($step, $draft_operation->getId());
                break;
            case 6:
                $draft_operation->setField('COMENT', $text['text']);
                $draft_operation->setField('STATUS', 59);
                $operation = (new Operation())->find($draft_operation->getId())->getArray();
                Common::resetCreatingOperationProcess($operation['PROPERTY_MANAGER_VALUE']);
                $message_to_resp = "Новая операция №".$draft_operation->getId()."\n";
                $message_to_resp .= "Дата - ".$operation['CREATED_DATE']."\n";
                $message_to_resp .= "Кто - ".$operation['PROPERTY_WHO_VALUE']."\n";
                $message_to_resp .= "Кому - ".$operation['PROPERTY_WHOM_VALUE']."\n";
                $message_to_resp .= "Ставка кто - ".$operation['PROPERTY_ST_WHO_VALUE']."\n";
                $message_to_resp .= "Ставка кому - ".$operation['PROPERTY_ST_WHOM_VALUE']."\n";
                $message_to_resp .= "Менеджер - ".(new Staff())->find($operation['PROPERTY_MANAGER_VALUE'])->getName()."\n";
                if(ArrayHelper::checkFullArray($operation['PROPERTY_FILES_VALUE'])){
                    $message_to_resp .= "Ссылки на файлы:\n";
                    foreach ($operation['PROPERTY_FILES_VALUE'] as $file){
                        $message_to_resp .= "https://ci01.amg.pw".CFile::GetPath($file)."\n";
                    }
                }
                if($operation['PROPERTY_COMENT_VALUE'])
                    $message_to_resp .= "Комментарий - ".$operation['PROPERTY_COMENT_VALUE']."\n";
                //Telegram::sendMessageToResp($message_to_resp);
                Mattermost::send($message_to_resp, $draft_operation->cash_room()->getMatterMostOperationChannel());
                $markup['message'] = "Операция создана";
                break;
        }
        return $markup;
    }

    public function setFilesMultiple($param)
    {
        if(ArrayHelper::checkFullArray($param['photo'])){
            $max_size = 0;
            foreach($param['photo'] as $photo){
                if($photo['width']>$max_size){
                    $file = $photo;
                    $max_size = $photo['width'];
                }
            }

            $url = "https://api.telegram.org/bot". Common::getTGToken().'/';
            $file_info = json_decode(file_get_contents($url.'getFile?file_id='.$file['file_id']), true);
            $file_url = 'https://api.telegram.org/file/bot'.Common::getTGToken().'/'.$file_info['result']['file_path'];
            $arFile = CFile::MakeFileArray($file_url);
            $property_values = array(
                'VALUE' => $arFile,
                'DESCRIPTION' => $param['caption']
            );
            CIBlockElement::SetPropertyValueCode($this->getId(), "FILES" , $property_values);

        }
        if(ArrayHelper::checkFullArray($param['document'])){
            $file = $param['document'];
            $url = "https://api.telegram.org/bot". Common::getTGToken().'/';
            $file_info = json_decode(file_get_contents($url.'getFile?file_id='.$file['file_id']), true);
            $file_url = 'https://api.telegram.org/file/bot'.Common::getTGToken().'/'.$file_info['result']['file_path'];
            $arFile = CFile::MakeFileArray($file_url);
            $property_values = array(
                'VALUE' => $arFile,
                'DESCRIPTION' => $param['caption']
            );
            CIBlockElement::SetPropertyValueCode($this->getId(), "FILES" , $property_values);
        }
    }

    public function manager()
    {
        $staff = new Staff();
        return $staff->find($this->getField('MANAGER'));
    }

    public function cash_room(): CashRoom
    {
        $cash_rooms = new CashRoom();
        return $cash_rooms->find((int)$this->getField('CASH_ROOM'));
    }

    public function setFileText(string $text)
    {

    }
    public function getStatus()
    {
        return $this->getField('PROPERTY_STATUS_ENUM_ID');
    }

}
