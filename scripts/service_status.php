<?php
/**
 *
 * 写文件脚本
 * 只在后台运行
 * $fileName  这个是文件路径,布署时要记得改
 *
 */

if(!extension_loaded('swoole')){
    exit("Please install swoole extension!\npecl install swoole\n");
}

$serv = new swoole_http_server("0.0.0.0", 8732);

//设置异步任务的工作进程数量
$serv->set(array(
    'daemonize' => 1, //上线时这里要改成1
));

$serv->on('Request', function($request, $response) {
    if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
        return $response->end();
    }else{
        $html = '<meta charset="utf-8">';
        $fileName = '/tmp/t.json';
        $data = @file_get_contents($fileName);
        if($data === false ){
            shell_exec('touch '.$fileName);
        }
        $data = json_decode($data,1);
        $inputData = $request->get;
        $ip = $inputData['ip'];
        $status = $inputData['st'];
        if(!in_array($status,array(1,2))){ //1开机 2停机
            return $response->end("{$html}<h2>非法数据!</h2>");
        }
        if($status == null || $ip == null){
            return $response->end("{$html}<h2>说明:st当1时开机，2时停机</h2>");
        }
        $data[$ip] = $status;
        if(file_put_contents($fileName,json_encode($data,JSON_UNESCAPED_UNICODE))){
            var_dump($data);
            return $response->end("{$html}<h2>已更改</h2>");
        }else{
            return $response->end("{$html}<h2>保存错误请联系运维协助处理!</h2>");
        }
    }
});

$serv->start();