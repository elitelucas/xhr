<?php

//聊天室重启
$chat = __DIR__ . '/workerman.log';
$fp = @fopen($chat, 'r');
@fseek($fp,-1,SEEK_END);
$log = '';
while(($c = fgetc($fp)) !== false) {
    if($c == "\n" && $log) {
        break;
    }
    $log = $c . $log;
    @fseek($fp, -2, SEEK_CUR);
}
@fclose($fp);

if (strpos($log, 'exit') !== false) { //存在则执行重启sh脚本
    $msg = shell_exec('sh do.sh');
    @file_put_contents('/tmp/workerman_restart.log', date('Y-m-d H:i:s').'----'.var_export($msg, TRUE)."\n", FILE_APPEND);
}


