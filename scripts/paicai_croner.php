<?php
$now_tiem = time();

//返水记录入库脚本
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';

$pid = (int)shell_exec('ps -ef |grep paicai_shouhu.php | grep -v "grep" | awk \'{print $2}\'');
var_dump($pid);
lg('redo_paicai','当前的PID::'.$pid);
if($pid==0){
    $do_str = 'php '.__DIR__.'/paicai_shouhu.php >/dev/null 2>&1 &';
  	lg('redo_paicai','启动脚本::'.$do_str);
    shell_exec($do_str);
  	
}
