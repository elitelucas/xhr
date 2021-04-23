<?php
$now_tiem = time();

define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set('memory_limit','1024M');

//$lotteryType = $_REQUEST['lotteryType'];
//$issue = $_REQUEST['issue'];
//dump($lotteryType );
//dump($issue);

$lotteryType = $argv[1];    //彩种
$issue = $argv[2];          //期号
$status = $argv[3];         //
$uid = $argv[4];            //用户ID

//var_dump($argv);
//var_dump($lotteryType);
//var_dump($issue);
//exit;
echo '---------进入开奖---------';
$db = getconn();
if(in_array($lotteryType,array(1,3))){
    $sql = "SELECT `open_no`,`open_time`,`open_result`,`spare_1`,`spare_2`,`spare_3` FROM `un_open_award` WHERE lottery_type={$lotteryType} AND issue={$issue}";
    $data = $db->getone($sql);
    if(empty($data)){
        exit;
    }
    $td=array(
        'lottery_type'=>$lotteryType,
        'issue'=>$issue,
        'open_no'=>$data['open_no'],
        'open_time'=>$data['open_time'],
        'open_result'=>$data['open_result'],
        'spare_1'=>$data['spare_1'],
        'spare_2'=>$data['spare_2'],
        'spare_3'=>$data['spare_3'],
        'state'=>$status,
        'user_id'=>$uid,
    );
    lg('redo_paicai',var_export(array('$td'=>$td,'$data'=>$data),1));
    D('workerman')->theLottery($issue,[$data['spare_1'],$data['open_result'],$data['spare_2'],$data['spare_3']],$data['open_time'],$lotteryType,$status,$uid,array('frequency'=>1));
}elseif(in_array($lotteryType,array(2,4,9))){
    if($lotteryType==2 || $lotteryType==9){
        $sql = "SELECT `kaijiangshijian`,`kaijianghaoma` FROM `un_bjpk10` WHERE qihao={$issue} and lottery_type={$lotteryType}";
    }else{
        $sql = "SELECT `kaijiangshijian`,`kaijianghaoma` FROM `un_xyft` WHERE qihao={$issue}";
    }
    $data = $db->getone($sql);
    if(empty($data)){
        exit;
    }
    $final['qihao']=$issue;
    $final['kaijianghaoma'] = $data['kaijianghaoma'];
    $final['kaijiangshijian'] = $data['kaijiangshijian'];
    $final['time'] = strtotime($data['kaijiangshijian']);
    lg('redo_paicai',var_export(array('$final'=>$final,'$data'=>$data),1));
    D('workerman')->theLottery($final['qihao'],$final,$final['time'],$lotteryType,$status,$uid,array('frequency'=>1));
}elseif(in_array($lotteryType,array(5,6,11))){
  	echo '---------进入开奖1---------';
    $sql = "SELECT `lottery_time`,`lottery_result` FROM `un_ssc` WHERE lottery_type={$lotteryType} AND issue={$issue}";
    $data = $db->getone($sql);
    if(empty($data)){
        exit;
    }
    $td=array(
        'lottery_type'=>$lotteryType,
        'issue'=>$issue,
        'lottery_time'=>$data['lottery_time'],
        'lottery_result'=>$data['lottery_result'],
        'status'=>$status,
        'user_id'=>$uid,
        'is_call_back'=>0,
        'call_back_uid'=>0,
    );
    lg('redo_paicai',var_export(array('$td'=>$td,'$data'=>$data),1));
  	echo '---------进入开奖2---------';
    D('workerman')->theLottery($td['issue'],$td,$data['lottery_time'],$lotteryType,$status,$uid,array('frequency'=>1));
}elseif(in_array($lotteryType,array(7,8))){
    $sql = "SELECT `lottery_time`,`lottery_result` FROM `un_lhc` WHERE lottery_type={$lotteryType} AND issue={$issue}";
    $data = $db->getone($sql);
    if(empty($data)){
        exit;
    }
    $td=array(
        'lottery_type'=>$lotteryType,
        'issue'=>$issue,
        'lottery_time'=>$data['lottery_time'],
        'lottery_result'=>$data['lottery_result'],
        'status'=>$status,
        'user_id'=>$uid,
        'is_call_back'=>0,
        'call_back_uid'=>0,
    );
    lg('redo_paicai',var_export(array('$td'=>$td,'$data'=>$data),1));
    // D('workerman')->theLotteryWithoutPaicai($issue,$td,$lotteryType);
    D('workerman')->theLottery($issue,$td,$data['lottery_time'],$lotteryType,$status,$uid,array('frequency'=>1));
}elseif(in_array($lotteryType,array(10,13,14))) {
    switch ($lotteryType){
        case 10:
            $table = 'un_nn';
            break;
        case 13:
            $table = 'un_sb';
            break;
        case 14:
            $table = 'un_ffpk10';
            break;
    }
    $sql = "SELECT `lottery_time`,`lottery_result` FROM `{$table}` WHERE lottery_type={$lotteryType} AND issue={$issue}";
    $data = $db->getone($sql);
    if (empty($data)) {
        exit;
    }
    $td = array(
        'lottery_type' => $lotteryType,
        'issue' => $issue,
        'lottery_time' => $data['lottery_time'],
        'lottery_result' => $data['lottery_result'],
        'status' => $status,
        'user_id' => $uid,
        'is_call_back' => 0,
        'call_back_uid' => 0,
    );
    lg('redo_paicai', var_export(array('$td' => $td, '$data' => $data), 1));
    D('workerman')->theLottery($issue, $td, $data['lottery_time'], $lotteryType, $status, $uid, array('frequency' => 1));
}elseif(in_array($lotteryType,array(12))) { //足彩单独处理

    $bi_feng = $argv[5];
    $room_id = $argv[6];
    $type = $argv[7];
    $time = $argv[8];
    lg('redo_paicai_football', var_export(array('$bi_feng' => $bi_feng, '$room_id' => $room_id,'$type'=>$type,'$time'=>$time), 1));
    if(empty($bi_feng) || empty($room_id) || empty($type) || empty($time)){
        exit;
    }
    //从Redis取出主键ID号,然后取数据出来用
    $td = array(
        'bi_feng'=>$bi_feng,
        'room_id'=>$room_id,
        'type'=>$type, //场子类型
    );
    lg('redo_paicai', var_export(array('$td' => $td), 1));

//    $data = array(
//        'bi_feng'=>$bi_feng,
//        'room_id'=>230,
//        'type'=>$_REQUEST['type'], //场子类型
//    );
//
//    $model->theLotteryFootball(1,$data,$time,$lottery_type,0,0,array('frequency' => 1));

    D('workerman')->theLotteryFootball(1, $td, $time, $lotteryType, $status, $uid, array('frequency' => 1));
}
$db->close();