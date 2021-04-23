<?php
$now_tiem = time();

//返水记录入库脚本
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
require S_ROOT . 'lib' . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'withdrawfactory.php';

//$mima = new  mima();
//dump($mima);exit;
ini_set('max_execution_time', '0');
ini_set('memory_limit','1024M');
$redis = initCacheRedis();
$db = getconn();
//$redis ->hSet('autodrawallist',1026,"{\"account_cash_id\":\"1026\",\"dealtime\":1535445179,\"order_no\":\"TX20180910153222152\",\"payment_id\":\"731\",\"drawal_name\":\"全银代付\"}");
//$redis->hDel("autodrawallist",911);
$autoList = $redis->hGetAll("autodrawallist");
dump($autoList);
//lg('autodrawal.txt',print_r($autoList,true). "===15");
if (!$autoList) {
    exit;
}

$time = time();
$interval = 600;
$factory = new withdrawfactory();



foreach ($autoList as $k => $v) {
    $v = json_decode($v,true);
    if ($v['dealtime'] >= $interval) {
        $sql = "select id,user_id,money from un_account_cash where id = ".$v['account_cash_id'] ." and status = 8";
        $ret = $db->getone($sql);
        if (!$ret['id']) {
            $redis->hDel("autodrawallist",$k);
            continue;
        }
        $sql = "select money, money_freeze from un_account where user_id = " .$ret['user_id'];
        $account = $db->getone($sql);
        $drawalOBJ = $factory->getInterface($v['nid']);
        $curlData = $drawalOBJ->queryOrder($v);
//        $curlData = preCurl($v);
        lg('autodrawal.txt',"line==105==订单号".$k."订单查询结果：".print_r($curlData,true));
//        $curlData['code'] = 1;
//        lg('autodrawal.txt',print_r($curlData,true). "====99==");
        if ($curlData['code'] == 10) { //10 成功  1 失败  2处理中
            $verifyremark = $db->getone("select verifyremark from un_account_cash where id = " .$v['account_cash_id']);
            $verifyremark = json_decode($verifyremark['verifyremark'],true);
            if ($verifyremark['remark']) {
                array_push($verifyremark['remark'],['admin'=>$v['drawal_name'],'remark'=>"自动提现成功"]);
            } else {
                $verifyremark['remark'] = ['admin'=>$v['drawal_name'],'remark'=>"自动提现成功"];
            }
            $verifyremark['status'] = 4;
            $insertData = [
                'verifytime' => time(),
                'status' => 4,
                'verifyremark' => json_encode($verifyremark,JSON_UNESCAPED_UNICODE)
            ];
//            dump($insertData);dump($verifyremark);exit;
            $return1 = $db->update('#@_account_cash',$insertData,['id'=>$v['account_cash_id']]);

            $return2 = $db->update("#@_account", array('money_freeze' => $account['money_freeze'] - $curlData['amount']), array('user_id' => $ret['user_id']));
            lg("autodrawal.txt","line==126==订单号".$v['order_no']."更改提现表结果".$return1."更改金额表结果".$return2);
            $redis->hDel("autodrawallist",$k);
            continue;
        } elseif ($curlData['code'] == 1) {
            //检查是否产生提现手续费
//            $v['order_no'] = "TX20180910101425363";
            $sql = "select money from un_account_log where type = 154 and user_id = " .$ret['user_id'] . " and order_num = '" . $v['order_no'] ."'";
            $accountLog = $db->getone($sql);
            if($accountLog['money']) { //如有则加入account_cash中
                $result = $db->update('#@_account_cash',['extra_fee'=>$accountLog['money']],['id'=>$v['account_cash_id']]);
                $sql = "select money_freeze from un_account where user_id = " . $ret['user_id'];
                $account = $db->getone($sql);
                $return1 = $db->update("#@_account",['money_freeze'=> $account['money_freeze']+$accountLog['money']],array('user_id' => $ret['user_id']));
                lg("autodrawal.txt","line==139==订单号".$v['order_no']."失败额外手续费为".$accountLog['money']."更改金额表结果".$return1);
            }

            $delAccLog = $db->delete('#@_account_log',['user_id'=>$ret['user_id'],'order_num'=>$v['order_no']]); //删除记录
            lg('autodrawal.txt',"line===154==删除失败记录结果：".$delAccLog);
            $verifyremark = $db->getone("select verifyremark from un_account_cash where id = " .$v['account_cash_id']);
            $verifyremark = json_decode($verifyremark['verifyremark'],true);
            $verifyremark['status'] = 0;
            if ($verifyremark['remark']) {
                array_push($verifyremark['remark'],['admin'=>$v['drawal_name'],'remark'=>"自动提现失败"]);
            } else {
                $verifyremark['remark'] = ['admin'=>$v['drawal_name'],'remark'=>"自动提现失败"];
            }
            $insertData = [
                'verifytime' => time(),
                'status' => 0,
                'verifyremark' => json_encode($verifyremark,JSON_UNESCAPED_UNICODE),
                'payment_id' => 0,
            ];
            $return1 = $db->update('#@_account_cash',$insertData,['id'=>$v['account_cash_id']]);
            $redis->hDel("autodrawallist",$k);
            lg('autodrawal.txt',"line===158=="."订单".$v['order_no']."失败删除奖金变动表中数据结果".$delAccLog."");
            continue;
        } else { //处理中则更新处理时间
            if (!$v['times']) {
                $v['dealtime'] = time();
                $redis->hSet('autodrawallist',$k,json_encode($v,JSON_UNESCAPED_UNICODE));
            }
        }

    }
}

deinitCacheRedis($redis);
$db->close();

