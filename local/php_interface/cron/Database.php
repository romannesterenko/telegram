<?php

class Database
{
    private string $host = 'localhost';
    private string $dbname = 'sitemanager';
    private string $username = 'bitrix0';
    private string $password = 'xpA[!LCeEx@JnoAuxl6%';
    private $connection;
    public function __construct()
    {
        try {
            $this->connection = mysqli_connect($this->host, $this->username, $this->password, $this->dbname);//new PDO("mysql:host=$this->host;dbname=$this->dbname", $this->username, $this->password);
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    public function query($query):array{
        $result_array = [];
        $result = mysqli_query($this->connection, $query);
        if(!is_bool($result)) {
            while ($row = $result->fetch_assoc()) {
                $result_array[] = $row;
            }
        }
        return $result_array;
    }
    public function getUncompletedTasks($limit = 0){
        $limit_string = '';
        if(is_int($limit)&&$limit>0)
            $limit_string = " LIMIT ".$limit;
        return $this->query("SELECT * FROM task_manager WHERE NOT UF_IS_COMPLETED=1".$limit_string);
    }
    public function setCompleteTask($ID)
    {
        $date = date('Y-m-d H:i:s');
        $sql = "UPDATE task_manager SET UF_IS_COMPLETED = 1, UF_COMPLETED_AT = CAST('".$date."' AS DATETIME) WHERE ID = ".$ID;
        $this->query($sql);
    }
}