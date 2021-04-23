<?php
namespace Config;
/**
 * mysql配置
 * @author walkor
 */
 
//引用系统的配置
require_once __DIR__.'/../../config.php';

class Db
{
    public static $db1 = array(
        'host'    => DB_HOST,
        'user'    => DB_USER,
        'password' =>DB_PWD,
        'dbname'  => DB_NAME,
        'port'    => DB_PORT,
        'charset'    => 'utf8',
    );
}
