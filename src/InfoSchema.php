<?php
/**
 * Created by PhpStorm.
 * User: zhangyi
 * Date: 2019/7/26
 * Time: 13:45
 */

namespace Mdouter;

use Medoo\Medoo;

class InfoSchema
{
    private $medoo;

    public function __construct($host, $username, $password, $port = 3306, $charset = 'utf8')
    {
        $options = [
            'database_type' => 'mysql',
            'database_name' => 'information_schema',
            'server' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'charset' => $charset,
        ];

        $this->medoo = new Medoo($options);
    }

    /**
     * 获取一个库中所有的表名
     * @param $db
     * @return array
     */
    public function getAllTables($db)
    {
        $tables = $this->medoo->select('TABLES', ['TABLE_NAME','TABLE_COMMENT'], ['TABLE_SCHEMA' => $db]);
        if (is_array($tables)) {
            $tables = array_column($tables, 'TABLE_COMMENT', 'TABLE_NAME');
            ksort($tables, SORT_STRING);
            return $tables;
        }
        return [];
    }

    /**
     * 获取一张表中所有字段信息
     * @param $db
     * @param $table
     * @return array
     */
    public function getAllFields($db, $table)
    {
        $fields = $this->medoo->select(
            'COLUMNS',
            ['COLUMN_NAME','COLUMN_TYPE','IS_NULLABLE','COLUMN_DEFAULT','COLUMN_COMMENT'],
            ['TABLE_SCHEMA' => $db, 'TABLE_NAME' => $table]
        );

        if (is_array($fields)) {
            return array_column($fields, null, 'COLUMN_NAME');
        }
        return [];
    }

    public function __destruct()
    {
        $this->medoo = null;
    }

}
