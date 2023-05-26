<?php

namespace Models;
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;
use Helpers\LogHelper;

abstract class HigloadElementModel {
    protected $HL_ID = null;
    public string $dataClass;
    public $array= [];
    protected array $filter= [];
    protected array $select= [];
    protected int $limit = 0;
    protected array $order= [];

    public function __construct()
    {
        \CModule::IncludeModule('highloadblock');
        $hl_block = HLBT::getById($this->HL_ID)->fetch();
        $entity = HLBT::compileEntity($hl_block);
        $this->dataClass = $entity->getDataClass();
    }
    protected function update($ID, array $array)
    {
        $this->dataClass::update($ID, $array);
    }


    protected function create(array $array)
    {
        $this->dataClass::add($array);
    }


    protected function delete($id)
    {
        $this->dataClass::delete($id);
    }

    public function find($id){
        $this->array = $this->dataClass::getList(['filter' => ['ID'=>$id]])->fetch();
        return $this;
    }
    public function where($name, $value): HigloadElementModel
    {
        $this->filter[$name] = $value;

        return $this;
    }
    public function filter($filter): HigloadElementModel
    {
        $this->filter = $filter;
        return $this;
    }
    public function select($select): HigloadElementModel
    {
        if(is_array($select))
            $this->select = array_merge($this->select, $select);
        else
            $this->select[] = $select;
        return $this;
    }
    public function orderByDesc($field){
        $this->order[$field] = 'DESC';
        return $this;
    }
    public function orderByAsc($field){
        $this->order[$field] = 'ASC';
        return $this;
    }
    public function limit($int)
    {
        if($int>0){
            $this->limit = $int;
        }
        return $this;
    }
    public function get(): HigloadElementModel
    {
        $params['filter'] = $this->filter;
        if(\Helpers\ArrayHelper::checkFullArray($this->select))
            $params['select'] = $this->select;
        if($this->limit>0)
            $params['limit'] = $this->limit;
        if(\Helpers\ArrayHelper::checkFullArray($this->order))
            $params['order'] = $this->order;
        else
            $params['order'] = ['ID' => 'DESC'];
        $this->array = $this->dataClass::getList($params)->fetchAll();
        return $this;
    }
    public function first(): HigloadElementModel
    {
        $params['filter'] = $this->filter;
        if(\Helpers\ArrayHelper::checkFullArray($this->select))
            $params['select'] = $this->select;
        if(\Helpers\ArrayHelper::checkFullArray($this->order))
            $params['order'] = $this->order;
        else
            $params['order'] = ['ID' => 'DESC'];
        $this->array = $this->dataClass::getList($params)->fetch();
        return $this;
    }
    public function getField($field){
        return $this->array[$field];
    }
    public function getArray():array
    {
        return is_array($this->array)?$this->array:[];
    }
}
