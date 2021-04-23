<?php
$now_tiem = time();
//返水记录入库脚本
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';

ini_set('max_execution_time', '0');
ini_set('memory_limit','1024M');
 
while (1){
    $redis = initCacheRedis();
    $LotteryTypeIds = $redis->lRange('LotteryTypeIds',0 ,-1);
    //获取DB连接
    foreach ($LotteryTypeIds as $lotteryType){
        $key = 'pc_lottery_type:'.$lotteryType;
      	#$key = 'pc_lottery_type:11';
      	
        $res = $redis->hgetall($key);
        foreach ($res as $k=>$v){
          	 
            $issue = $k;
            if($v==1){
                $val['status'] = 0;
                $val['uid'] = 0;
            }else{
                $val = decode($v);
            }
          	
          	echo $key.'执行派奖';
          	var_dump($val);
            //执行派奖
            $shell_str='';
            if(in_array($lotteryType,array(12))){
                $shell_str = 'php '.__DIR__.'/paicai.php '.$lotteryType.' '.$issue.' '.$val['status'].' '.$val['uid'].' '.$val['bi_feng'].' '.$val['room_id'].' '.$val['type'].' '.$val['time'].' >/dev/null 2>&1 &';
                lg('redo_paicai_football','执行SHELL'.var_export(array('$shel_str'=>$shell_str),1));
            }else{
              	echo  'php '.__DIR__.'/paicai.php '.$lotteryType.' '.$issue.' '.$val['status'].' '.$val['uid'];
                $shell_str = 'php '.__DIR__.'/paicai.php '.$lotteryType.' '.$issue.' '.$val['status'].' '.$val['uid'].' >/dev/null 2>&1 &';
                lg('shell_paicai_log','执行SHELL'.var_export(array('$shel_str'=>$shell_str),1));
            }
            shell_exec($shell_str);
            $redis->hdel($key,$issue); //删除对应的期号
            lg('shell_paicai_log',"\n\n--------------------------------\n\n\n");
        }
    }
    deinitCacheRedis($redis);
    sleep(3);
}
