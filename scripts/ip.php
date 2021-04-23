<?php
/**
 *
 * 前端文件
 */


//返水记录入库脚本
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';

lg('nginx_status','进入脚本');

$fileName = '/tmp/t.json';
$data = @file_get_contents($fileName);
if($data === false ){
    die();
}

$localIP = shell_exec("/sbin/ip addr | egrep -o '[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}'|grep -v '127.0.0.1\|255$'");
$localIP = trim($localIP,"\n");
$localIP = explode("\n",$localIP);
var_dump($localIP);


//ps -ef | grep nginx | grep master | awk '{print $2}'
$ngPid = (int)shell_exec('/bin/ps -ef |grep \'nginx: master\' | grep -v "grep" | awk \'{print $2}\''); //查当前的Ng进程
var_dump($ngPid);
//exit;
$data = json_decode($data,1);

lg('nginx_status',var_export(array('$data'=>$data,'$localIP'=>$localIP,'$ngPid'=>$ngPid),1));

if(is_array($localIP)){
    foreach ($localIP as $k=>$v){
        if(!empty($ngPid) && $data[$v]==2){
            var_dump('kill -15 '.$ngPid);
            lg('nginx_status','执行shell::kill -15 '.$ngPid.';php '.__DIR__.'/../chat/start.php stop');
            shell_exec('php '.__DIR__.'/../chat/start.php stop');
            shell_exec('kill -15 '.$ngPid);
        }

        if(empty($ngPid) && $data[$v]==1){
            lg('nginx_status','$k::'.$k);
            if($k==0){ //只启动一次
                if($data[$v]==1){
                    var_dump('启动Ng');
                    lg('nginx_status','执行shell::/software/nginx/sbin/nginx;php '.__DIR__.'/../chat/start.php restart -d');
                    shell_exec('php '.__DIR__.'/../chat/start.php restart -d');
                    shell_exec('/software/nginx/sbin/nginx'); //启动脚本
//                    shell_exec('/bin/sh '.__DIR__.'/../chat/do.sh');
                }
            }
        }
        sleep(3);
    }
}
/*else{
    if(!empty($ngPid) && !empty($data[$localIP])){
        if($data[$localIP]==2){
            var_dump('kill -15 '.$ngPid);
            shell_exec('kill -15 '.$ngPid);
            shell_exec('php '.__DIR__.'/../chat/start.php stop');
        }
    }else{
        if($data[$localIP]==1){
            var_dump('启动Ng');
            shell_exec('/software/nginx/sbin/nginx'); //启动脚本
            shell_exec('/bin/sh '.__DIR__.'/../chat/do.sh');
        }
    }
}*/
//var_dump($localIP);