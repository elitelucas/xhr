<?php

//聊天室重启
$chatArr = array('/data/www/sina28_red/kf','/data/www/sina28_red/chat');
foreach ($chatArr as $chat) {
	$fp = @fopen($chat.'/workerman.log', 'r');
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
		$msg = shell_exec("sh {$chat}/do.sh");
		@file_put_contents('/data/www/sina28_red/scripts/workerman_restart.log', var_export($msg, TRUE)."\n", FILE_APPEND);
	}	
}



