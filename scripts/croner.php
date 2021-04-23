<?php
/**
 *
 * 监测脚本
 *
 */

//引用系统的功能
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
$pid = (int)shell_exec('ps -ef |grep do.php | grep -v "grep" | awk \'{print $2}\'');
lg('croner_log','当前的PID::'.$pid);
echo 'run';
if($pid==0){
    $do_str = 'php '.__DIR__.'/do.php 2 >/dev/null 2>&1 &';
    lg('croner_log','启动脚本::'.$do_str);
    shell_exec($do_str);
}