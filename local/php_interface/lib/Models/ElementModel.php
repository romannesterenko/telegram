<?php

namespace Models;
use Bitrix\Main\UI\Uploader\Log;
use CIBlock;
use CIBlockElement;
use CIBlockProperty;
use CModule;
use Helpers\ArrayHelper;
use Helpers\LogHelper;
use LogicException;

abstract class ElementModel {

    const IBLOCK_ID = null;
    private $result_array = [];
    private $id;
    private $name;
    private $filter = [];
    private $arNavStartParams = [];
    private $select = ['ID', 'IBLOCK_ID', 'NAME', 'CODE', 'ACTIVE', 'CREATED_DATE', 'TIMESTAMP_X'];
    private $update_fields = [];
    private static function includeIblockModule(){
        CModule::IncludeModule('iblock');
    }
    public function __construct()
    {
        self::includeIblockModule();
        $this->filter['IBLOCK_ID'] = self::getIBlockId();
        $this->filter['ACTIVE'] = "Y";
    }

    public function getField($field)
    {
        return $this->result_array[$field]??$this->result_array['PROPERTY_'.$field.'_VALUE'];
    }

    public function where($name, $value): ElementModel
    {
        $this->filter[$name] = $value;
        return $this;
    }

    public function setLimit($value): ElementModel
    {
        $this->arNavStartParams['nPageSize'] = $value;
        return $this;
    }

    public function setPage($value): ElementModel
    {
        $this->arNavStartParams['iNumPage'] = $value;
        return $this;
    }

    public function whereNot($name, $value): ElementModel
    {
        $name = "!".$name;
        $this->filter[$name] = $value;
        return $this;
    }

    public function filter($filter): ElementModel
    {
        $this->filter = array_merge($filter, $this->filter);
        return $this;
    }

    public function select($select): ElementModel
    {
        $this->select = $select;
        return $this;
    }
    public function get(): ElementModel
    {
        foreach (self::props() as $prop => $data){
            $this->select[] = 'PROPERTY_'.$prop;
        }
        if(count($this->arNavStartParams)==0)
            $this->arNavStartParams = null;

        $res = CIBlockElement::GetList(['ID'=>'DESC'], $this->filter, false, $this->arNavStartParams, $this->select);
        $added = [];
        while ($item = $res->fetch()) {
            if(!in_array($item['ID'], $added)) {
                $this->result_array[] = $item;
                $added[] = $item['ID'];
            }
        }
        return $this;
    }
    public function buildQuery(): ElementModel
    {
        foreach (self::props() as $prop => $data){
            $this->select[] = 'PROPERTY_'.$prop;
        }
        if(count($this->arNavStartParams)==0)
            $this->arNavStartParams = null;
        $res = CIBlockElement::GetList(['ID'=>'DESC'], $this->filter, false, $this->arNavStartParams, $this->select);
        $added = [];
        while ($item = $res->fetch()) {
            if(!in_array($item['ID'], $added)) {
                $this->result_array[] = $item;
                $added[] = $item['ID'];
            }
        }
        return $this;
    }

    public function first(): ElementModel
    {
        foreach (self::props() as $prop => $data){
            $this->select[] = 'PROPERTY_'.$prop;
        }
        $this->result_array = CIBlockElement::GetList(['ID'=>'DESC'], $this->filter, false, false, $this->select)->fetch();
        $this->id = $this->result_array['ID'];
        return $this;
    }

    public function getArray()
    {
        return ArrayHelper::checkFullArray($this->result_array)?$this->result_array:[];
    }

    public function resetFilter()
    {
        unset($this->filter);
        $this->filter['IBLOCK_ID'] = self::getIBlockId();
    }

    public function setField($string, $text, $timestamp = false){
        CIBlockElement::SetPropertyValuesEx($this->getId(), false, array($string => $text));
        if($timestamp){
            $el = new CIBlockElement;
            $el->Update($this->getId(), ['NAME' => $this->getField('NAME')]);
        }
    }

    public function find($id, $select=[]): ElementModel
    {
        if($select===[]) {
            $select = ['ID', 'NAME', 'ACTIVE', 'CODE', "CREATED_DATE"];
            foreach (self::props() as $prop => $data){
                $select[] = 'PROPERTY_'.$prop;
            }
        }else{
            $select = array_merge($select, ['ID', 'NAME']);
        }
        $this->result_array = CIBlockElement::GetList(['ID' => 'DESC'], ['IBLOCK_ID' => self::getIBlockId(), '=ID' => $id], false, false, $select)->fetch();

        $this->id = $this->result_array['ID'];
        $this->name = $this->result_array['NAME'];
        return $this;
    }

    public static function create($fields)
    {
        self::includeIblockModule();
        $el = new CIBlockElement();
        $fields = array_merge($fields, ['IBLOCK_ID' => self::getIBlockId()]);
        $id = $el->add($fields);
        return $id;
    }

    public static function delete($id)
    {
        self::includeIblockModule();
        CIBlockElement::delete($id);
    }

    public static function updateField($id, $field, $value){
        self::getIBlockId();
        CIBlockElement::SetPropertyValuesEx($id, self::getIBlockId(), array($field => $value));
    }

    public static function update($id, $fields)
    {
        self::includeIblockModule();
        $el = new CIBlockElement();
        $id = $el->update($id, $fields);
        return $id>0;
    }

    private static function props()
    {
        self::includeIblockModule();
        $props = [];
        $dbRes = CIBlock::GetProperties(self::getIBlockId(), [], []);
        while($property = $dbRes->Fetch()) {
            $props[$property['CODE']] = $property;
        }
        return $props;
    }

    private static function getIBlockId()
    {
        $id = static::IBLOCK_ID;
        if (!$id) {
            throw new LogicException('You must set IBLOCK_ID constant inside a model or override iblockId() method');
        }
        return $id;
    }

    public function getId():int
    {
        return (int)$this->id;
    }

    public function getIblock()
    {
        return static::IBLOCK_ID;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->getField('CODE');
    }

}
