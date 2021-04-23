<?php
/**
 *
 * 服务脚本，用来监测要处理的数据
 *
 */


//引用系统的功能
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set('memory_limit','1024M');
while (1){
    lg('back_log','server端进入执行操作');
    $redis = initCacheRedis();
    $model = $redis->get('back_water_server_model');
    lg('back_log','server端,model值::'.$model);
    if($model==2){
        lg('back_log','server端,追加模式');
        $pid = (int)($redis->get('redo_back_pid')); //redis中存放的pid
        $current_pid = (int)shell_exec('ps -ef |grep redo_back.php | grep -v "grep" | awk \'{print $2}\''); //当前正在运行的PID
        lg('back_log','server端,从内存中取出来的PID'.$current_pid);
        if($current_pid === 0){ //进程停掉
            $if_done = $redis->get('back_water_success');
            lg('back_log','server端,判断是否完成'.$if_done);
            if($if_done==1){ //执行完成
                lg('back_log','执行完');
            }else{
                $do_str = 'php '.__DIR__.'/redo_back.php 2 >/dev/null 2>&1 &';
                var_dump($do_str);
                shell_exec($do_str);
                lg('back_log','启动脚本::'.$do_str);
            }
        }
    }elseif($model==1){
//        $do_str = 'php '.__DIR__.'/redo_back.php 1';
//        var_dump($do_str);
//        shell_exec($do_str);
        lg('back_log','server端,删除模式');
    }
    deinitCacheRedis($redis);
    sleep(2);
}