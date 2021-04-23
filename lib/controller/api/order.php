<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 13:34
 * desc: 天天反利 玩法介绍
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class OrderAction extends Action {

    /**
     * 数据表
     */
    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = D('order');
    }

    /**
     *
     * 订单回滚
     *
     */
    public function ordersCallBack() {
        $test = 0;  //是否开启调试模式
        if($test==1){
            $lottery_type=$_GET['lt']?:1;
            $issue=$_GET['lid']?:842309;
        }else{
            //验证
            $res = verificationSignature();
            if($res['status'] !== "success"){
                lg('orders_call_back',"签名验证失败(回滚)");
                if($res['code'] == 3){
                    ErrorCode::errorResponse(999998,"Request timed out, please make sure your mobile phone system time is: Beijing (China) time, refresh and try again!");
                }
                ErrorCode::errorResponse(999999,"Login failed, please make sure the app is the latest version and try again!");
            }
            $lottery_type=$_REQUEST['lottery_type'];
            $issue=$_REQUEST['issue'];
            $match_id=$_REQUEST['match_id']; //足彩用的
            $match_status=$_REQUEST['match_status']; //足彩用的
            $uid=$_REQUEST['uid'];
            $is_supper=$_REQUEST['is_supper'];
        }

        if(in_array($lottery_type,array(12))){
            //获取比赛名
            $redis = initCacheRedis();
            $val = $redis->hget('fb_against',$match_id);
            $against = decode($val)[0];
            $match_name= $against['event_name'].'-'.$against['team_1_name'].'vs'.$against['team_2_name'];
            deinitCacheRedis($redis);
            $sql = "SELECT COUNT(*) AS t FROM un_orders o,un_orders_football of WHERE o.lottery_type={$lottery_type} AND o.id = of.`order_id` AND o.room_no IN (SELECT id FROM `un_room` WHERE `match_id` = '{$match_id}') AND of.`type`={$match_status} AND o.state=0 AND o.award_state >0";
        }else{
            $sql = "SELECT count(*) as t FROM un_orders WHERE lottery_type={$lottery_type} AND issue='{$issue}' AND state=0 AND award_state >0";
        }
        $re = $this->db->getone($sql);
        lg('orders_call_back',var_export(array('$sql'=>$sql,'$re'=>$re),1));
        if($re['t']==0){
            $data=array(
                "msg"=>'No rewards to roll back!',
                "err"=>1,
            );
            echo json_encode($data,JSON_UNESCAPED_UNICODE);
            return false;
        }

        $now=time();
        $redis = initCacheRedis();
        $name = $redis->hget('LotteryType:'.$lottery_type,'name');
        //关闭redis链接
        deinitCacheRedis($redis);
//        return false;
        switch ($lottery_type) {
            case 1:
                $sql  = "SELECT open_time AS time,call_back_uid FROM un_open_award WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                $url ='?m=admin&c=openAward&a=LuckyList';
                $openSql = "update un_open_award set is_call_back=0 ,call_back_uid={$uid} WHERE lottery_type=1 and issue='{$issue}'";
//                $openSql = "update un_bjpk10 set is_call_back=0 ,call_back_uid={$uid} WHERE qihao='{$issue}'";
                break;
            case 2:
                $sql  = "SELECT UNIX_TIMESTAMP(insert_time) AS time,call_back_uid FROM un_bjpk10 where qihao='{$issue}' AND lottery_type={$lottery_type}";
                $url ='?m=admin&c=openAward&a=bjpk10List';
                $openSql = "update un_bjpk10 set is_call_back=0 ,call_back_uid={$uid} WHERE qihao='{$issue}'  AND lottery_type={$lottery_type}";
                break;
            case 3:
                $sql  = "SELECT open_time AS time,call_back_uid FROM un_open_award WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                $url ='?m=admin&c=openAward&a=jndList';
                $openSql = "update un_open_award set is_call_back=0 ,call_back_uid={$uid} WHERE lottery_type=3 and issue='{$issue}'";
                break;
            case 4:
                $sql  = "SELECT UNIX_TIMESTAMP(insert_time) AS time,call_back_uid FROM un_xyft where qihao='{$issue}'";
                $openSql = "update un_xyft set is_call_back=0 ,call_back_uid={$uid} WHERE qihao='{$issue}'";
                $url ='?m=admin&c=openAward&a=xyftList';
                break;
            case 5:
                $sql  = "SELECT lottery_time AS time,call_back_uid FROM un_ssc WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                $url ='?m=admin&c=openAward&a=sscList';
                $openSql = "update un_ssc set is_call_back=0 ,call_back_uid={$uid} WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                break;
            case 6:
                $sql  = "SELECT lottery_time AS time,call_back_uid FROM un_ssc WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                $url ='?m=admin&c=openAward&a=sfcList';
                $openSql = "update un_ssc set is_call_back=0 ,call_back_uid={$uid} WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                break;
            case 7:
                $sql  = "SELECT lottery_time AS time,call_back_uid FROM un_lhc WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                $url ='?m=admin&c=openAward&a=lhcList';
                $openSql = "update un_lhc set is_call_back=0 ,call_back_uid={$uid} WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                break;
            case 8:
                $sql  = "SELECT lottery_time AS time,call_back_uid FROM un_lhc WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                $url ='?m=admin&c=openAward&a=jslhcList';
                $openSql = "update un_lhc set is_call_back=0 ,call_back_uid={$uid} WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                break;
            case 9:
                $sql  = "SELECT UNIX_TIMESTAMP(insert_time) AS time,call_back_uid FROM un_bjpk10 where qihao='{$issue}' AND lottery_type={$lottery_type}";
                $url ='?m=admin&c=openAward&a=jsscList';
                $openSql = "update un_bjpk10 set is_call_back=0 ,call_back_uid={$uid} WHERE qihao='{$issue}'  AND lottery_type={$lottery_type}";
                break;
            case 10:
                $sql  = "SELECT lottery_time AS time,call_back_uid FROM un_nn WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                $url ='?m=admin&c=openAward&a=nnList';
                $openSql = "update un_nn set is_call_back=0 ,call_back_uid={$uid} WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                break;
            case 11:
                $sql  = "SELECT lottery_time AS time,call_back_uid FROM un_ssc WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                $url ='?m=admin&c=openAward&a=ffcList';
                $openSql = "update un_ssc set is_call_back=0 ,call_back_uid={$uid} WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                break;
            case 12:
//                $sql  = "SELECT lottery_time AS time FROM un_ssc WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                $url ='?m=admin&c=openAward&a=cupList&match_id='.$match_id;
//                $openSql = "update un_ssc set is_call_back=0 ,call_back_uid={$uid} WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                break;

            case 13:
                $sql  = "SELECT lottery_time AS time,call_back_uid FROM un_sb WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                $url ='?m=admin&c=openAward&a=sbList';
                $openSql = "update un_sb set is_call_back=0 ,call_back_uid={$uid} WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                break;
            case 14:
                $sql  = "SELECT lottery_time AS time,call_back_uid FROM un_ffpk10 WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                $url ='?m=admin&c=openAward&a=ffpk10List';
                $openSql = "update un_sb set is_call_back=0 ,call_back_uid={$uid} WHERE lottery_type={$lottery_type} and issue='{$issue}'";
                break;
        }
        if(in_array($lottery_type,array(12))){
            //足彩直接取值
            $tres['time'] = $now-3600;
        }else{
            $tres = $this->db->getone($sql);
        }

        lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},超过1小时回滚提示,上次开奖时间:{$tres['time']}"); //写个日志
        $arrst = array(
            '2'=>'上半场',
            '4'=>'下半场',
            '6'=>'加时',
            '8'=>'点球',
        );

        if($tres['call_back_uid']) {        //已经回滚 重复回滚
            $data=array(
                "msg"=>'Has been rolled back, please do not roll back repeatedly!',
                "err"=>1,
            );
            echo json_encode($data,JSON_UNESCAPED_UNICODE);
            return false;
        }
        if ($now > ($tres['time']+60*60) && $is_supper!=1) { //超过1小时,不自动回滚, 非超管
            if (in_array($lottery_type, array(12))) {
                $data = array(
                    'record_id' => $lottery_type . '_' . $match_id . '_' . $match_status,
                    'type' => 11,
                    'tip' => "{$name}",
                    'url' => $url,
                    'time' => time(),
                    'uids' => '',
                    'msg' => "{$name} Match{$match_name}{$arrst[$match_status]}, the award may be wrong, please confirm!",
                    'remark' => date('Y-m-d H:i:s') . ",{$name} Match {$match_name}{$arrst[$match_status]}, prompt that the award may be wrong",
                );

                lg('orders_call_back', "彩种:{$lottery_type},赛事{$match_name}{$arrst[$match_status]},超过1小时回滚提示:" . json_encode($data, JSON_UNESCAPED_UNICODE)); //写个日志

                $sql = 'select uids from un_music_tips WHERE `record_id`=\'' . $data['record_id'] . '\' and type=\'' . $data['type'] . '\' and status=0';
                $tipRes = $this->db->getone($sql);
                if (empty($tipRes)) {
                    $this->db->insert('un_music_tips', $data);
                    lg('orders_call_back', "彩种:{$lottery_type},赛事{$match_id}{$arrst[$match_status]},超过1小时回滚提示已入库:sql:{$this->db->_sql()}"); //写个日志
                }
            } else {
                $data = array(
                    'record_id' => $lottery_type . '_' . $issue,
                    'type' => 11,
//                'tip'=>"{$name}第{$issue}期,可能派奖错误，请确认!",
                    'tip' => "{$name}",
                    'url' => $url,
                    'time' => time(),
                    'uids' => '',
                    'msg' => "{$name} No {$issue},The award may be wrong, please confirm!",
                    'remark' => date('Y-m-d H:i:s') . ",{$name} No {$issue}, prompt that the award may be wrong",
                );

                lg('orders_call_back', "彩种:{$lottery_type},期号:{$issue},超过1小时回滚提示:" . json_encode($data, JSON_UNESCAPED_UNICODE)); //写个日志

                $sql = 'select uids from un_music_tips WHERE `record_id`=\'' . $data['record_id'] . '\' and type=\'' . $data['type'] . '\' and status=0';
                $tipRes = $this->db->getone($sql);
                if (empty($tipRes)) {
                    $this->db->insert('un_music_tips', $data);
                    lg('orders_call_back', "彩种:{$lottery_type},期号:{$issue},超过1小时回滚提示已入库:sql:{$this->db->_sql()}"); //写个日志
                }

                if (empty($uid)) { //非后台提交,抓奖平台过来的数据
                    return false;
                }
            }
        }

        //查出所有当前中奖的所有用户
        if(in_array($lottery_type,array(12))){
            $sql = "SELECT DISTINCT o.user_id,o.reg_type FROM un_orders o,un_orders_football of WHERE o.lottery_type={$lottery_type} AND o.id = of.`order_id` AND o.room_no IN (SELECT id FROM `un_room` WHERE `match_id` = '{$match_id}') AND of.`type`={$match_status} AND o.state=0 AND award_state=2";
        }else{
            $sql = "SELECT DISTINCT user_id,reg_type FROM un_orders WHERE lottery_type={$lottery_type} AND issue={$issue} AND state=0 AND award_state=2";
        }
        $res = $this->db->getall($sql);
        lg('orders_call_back',var_export(array('查已中奖数据','$sql'=>$sql,'$res'=>$res),1));
        foreach ($res as $k=>$v) {
            $total=0;
            //查出单个用户中奖总额
            if(in_array($lottery_type,array(12))){
                $sql = "SELECT SUM(o.award) AS total FROM un_orders o,un_orders_football of WHERE o.lottery_type={$lottery_type} AND o.id = of.`order_id` AND o.room_no IN (SELECT id FROM `un_room` WHERE `match_id` = '{$match_id}') AND of.`type`={$match_status} AND o.award_state=2 AND o.user_id={$v['user_id']}";
            }else{
                $sql = "SELECT sum(award) as total FROM un_orders WHERE lottery_type={$lottery_type} AND issue={$issue} AND award_state=2 AND user_id={$v['user_id']}";
            }
            $ure = $this->db->getone($sql);
            if (!empty($ure)) {
                $total=$ure['total']; //退款
                lg('orders_call_back',var_export(array('$lottery_type'=>$lottery_type,'$match_id'=>$match_id,'$match_status'=>$match_status,'$total'=>$total),1));
                lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},用户:{$v['user_id']},应当退款:{$total}"); //写个日志
                $this->db->query('START TRANSACTION'); //开启事务
                try {
                    //修改订单状态
                    if(in_array($lottery_type,array(12))){
                        $sql = "SELECT o.id FROM un_orders o,un_orders_football of WHERE o.lottery_type={$lottery_type} AND o.id = of.`order_id` AND o.room_no IN (SELECT id FROM `un_room` WHERE `match_id` = '{$match_id}') AND of.`type`={$match_status} AND o.award_state=2 AND o.user_id={$v['user_id']}";
                        $ids = $this->db->getall($sql);
                        $ids_str = implode(',',array_column($ids,'id'));
                        lg('orders_call_back',var_export(array('查订单id的SQL','$sql'=>$sql,'$ids'=>$ids,'$ids_str'=>$ids_str),1));
                        $sql = "UPDATE un_orders SET award_state=0,award=0,wins=0 WHERE id IN ({$ids_str})";
                        lg('orders_call_back',var_export(array('更新订单的SQL','$sql'=>$sql),1));
                        $opOrder =  $this->db->query($sql);
                    }else{
                        $opOrder = $this->db->update('un_orders',array('award_state'=>0,'award'=>0, 'wins'=>0),array('lottery_type'=>$lottery_type,'issue'=>$issue,'user_id'=>$v['user_id'],'award_state'=>2,'state'=>0));
                    }

                    if(!$opOrder){
                        lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,用户:{$v['user_id']},修改订单状态失败:sql:".$this->db->_sql()); //写个日志
                        throw new Exception('更新失败!'.$this->db->_sql());
                    }
                    lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,用户:{$v['user_id']},修改订单状态成功:sql:".$this->db->_sql()); //写个日志

                    //查用户当前资金
//                    $asql = "select money, winning from un_account WHERE user_id='{$v['user_id']}' LIMIT 1 for update";
                    if(!empty(C('db_port'))){ //使用mycat时 查主库数据
                        $asql="/*#mycat:db_type=master*/ select money, winning from un_account WHERE user_id='{$v['user_id']}' LIMIT 1 FOR UPDATE";
                    }else{
                        $asql="select money, winning from un_account WHERE user_id='{$v['user_id']}' LIMIT 1 FOR UPDATE";
                    }
                    $uares = $this->db->getone($asql);
                    if ($uares['money']<$total) {
                        lg('orders_call_back','钱不够扣,把当前的钱都扣光:应当扣款----->'.$total.'实际扣款------->'.$uares['money']); //写个日志
                        $total=$uares['money'];
                        $use_money=0;
                    }else{
                        $use_money=$uares['money']-$total;
                    }

                    lg('orders_call_back_debug',"当前余额::{$uares['money']},扣款::{$total},扣款后的余额::{$use_money}");

                    //资金交易明细
                    $log_data=array(
                        'order_num' => "HG" . date("YmdHis") . rand(100, 999), //回滚单号
                        'user_id' => $v['user_id'],
                        'type' => 120,
                        'addtime' => time(),
                        'money' => $total,
                        'use_money' => $use_money,
                        'remark' => "{$name} No {$issue} , draw error, order rollback deduction:{$total}",
                        'reg_type' => $v['reg_type']
                    );
                    if(in_array($lottery_type,array(12))){
                        $log_data['remark'] = "{$name} Match {$match_name}{$arrst[$match_status]}, draw error, Order rollback deduction:{$total}";
                    }
                    lg('orders_call_back',var_export(array('资金明细数据','$log_data'=>$log_data),1));
                    $logRes = $this->db->insert('un_account_log',$log_data);
                    if (empty($logRes)){
                        lg('orders_call_back', "彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,用户:{$v['user_id']},插入资金交易明细表失败:sql:".$this->db->_sql());
                        throw new Exception('更新失败!'.$this->db->_sql());
                    }else{
                        lg('orders_call_back', "彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,用户:{$v['user_id']},插入资金交易明细表成功:sql:".$this->db->_sql());
                    }
                    lg('orders_call_back', "彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,用户:{$v['user_id']},插入资金交易明细表成功:sql:".$this->db->_sql());

                    //给用户扣钱
                    $upAccuntSql = "update un_account set money=money-'{$total}',winning=winning-'{$total}' WHERE user_id='{$v['user_id']}'";
                    $upAccunt = $this->db->exec($upAccuntSql);
                    if(!$upAccunt){
                        lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,用户:{$v['user_id']},更改帐户表失败:sql:".$upAccuntSql);
                        throw new Exception('更新失败!'.$upAccuntSql);
                    }else{
                        lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,用户:{$v['user_id']},回滚单号:{$log_data['order_num']} 执行的:sql:{$upAccuntSql}");
                    }
                    if($opOrder!==false && $logRes!==false && $upAccunt!==false){
                        $this->db->query('COMMIT');
                    }
                    
                    //中奖金额换积分回滚
                    callbackIntegral($total, $v['user_id'], 7, $issue, $lottery_type);
                }catch (Exception $e) {
                    //设置捕获异常开奖
                    lg('orders_call_back',"用户:{$v['user_id']},彩种:,{$lottery_type}期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,订单回滚失败"); //写个日志
                    $this->db->query('ROLLBACK');
                }
            }
        }
        
        //投注金额换积分回滚
        $this->ordersBettingScroeCallBack($lottery_type, $issue,$match_id,$match_status);

        //如果没有中奖的统一改成待开奖  //这里要改

        if(in_array($lottery_type,array(12))) {
            $sql = "SELECT o.id FROM un_orders o,un_orders_football of WHERE o.lottery_type={$lottery_type} AND o.id = of.`order_id` AND o.room_no IN (SELECT id FROM `un_room` WHERE `match_id` = '{$match_id}') AND of.`type`={$match_status} AND o.award_state=1";
            $ids = $this->db->getall($sql);
            $ids_str = implode(',', array_column($ids, 'id'));
            lg('orders_call_back', var_export(array('未中奖 查订单id的SQL', '$sql' => $sql, '$ids' => $ids, '$ids_str' => $ids_str), 1));
            $sql = "UPDATE un_orders SET award_state=0,award=0,wins=0 WHERE id IN ({$ids_str})";
            lg('orders_call_back', var_export(array('更新订单的SQL', '$sql' => $sql), 1));
            $res = $this->db->query($sql);
            if($res){
                lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,更新未中奖为待开奖:sql:".$sql); //写个日志
            }else{
                lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,更新未中奖为待开奖失败:sql:".$sql); //写个日志
            }
        }else{
            $res = $this->db->update('un_orders',array('award_state'=>0,'award'=>0, 'wins'=>0),array('lottery_type'=>$lottery_type,'issue'=>$issue,'state'=>0));
            if($res){
                lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,更新未中奖为待开奖:sql:".$this->db->_sql()); //写个日志
            }else{
                lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,更新未中奖为待开奖失败:sql:".$this->db->_sql()); //写个日志
            }
        }

        $data=array();
        $data=array(
            'record_id'=>$lottery_type.'_'.$issue,
            'type'=>11,
//            'tip'=>"{$name}第{$issue}期,开奖错误订单已回滚，请手动派奖!",
            'tip'=>"{$name}",
            'url'=>$url,
            'time'=>time(),
            'uids'=>'',
            'msg'=>"{$name} No {$issue}, the lottery error order has been rolled back, please send the prize manually!",
            'remark'=>date('Y-m-d H:i:s').",{$name} No {$issue},  prompt the prize draw error",
        );
        if(in_array($lottery_type,array(12))){
            $data['record_id'] = $lottery_type.'_'.$match_id.'_'.$match_status;
            $data['msg'] = "{$name} Match {$match_name}{$arrst[$match_status]}, the lottery error order has been rolled back, please send the prize manually!";
            $data['remark'] = date('Y-m-d H:i:s').",{$name} MatchID {$match_id}{$arrst[$match_status]} prompt the prize draw error";
        }

//        if($is_supper==1){
////            $data['uids']='1';
////            $data['click_uid']='1';
////            $data['click_status']='1';
////            $data['click_time']=time();
//        }
        lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,1小时内自动回滚提示:".json_encode($data,JSON_UNESCAPED_UNICODE)); //写个日志
        $sql = 'select uids from un_music_tips WHERE `record_id`=\'' . $data['record_id'] . '\' and type=\'' . $data['type'] . '\' and status=0';
        $tipRes = $this->db->getone($sql);
        lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,1小时内自动回滚提示:\$tipRes".json_encode($tipRes,JSON_UNESCAPED_UNICODE)); //写个日志
        if (empty($tipRes)) {
            $this->db->query('START TRANSACTION'); //开启事务
            $re1 = $this->db->insert('un_music_tips', $data);
            lg('orders_call_back',"彩种:{$lottery_type},期号:{$issue},\$match_id:$match_id,\$match_status:$match_status,1小时内自动回滚提示已入库:sql:{$this->db->_sql()}"); //写个日志
            if($is_supper==1){

                if(in_array($lottery_type,array(12))){
                    $re2 = 1;
                }else{
                    $re2 = $this->db->query($openSql);
                }

                if($re1 && $re2){
                    $this->db->query('COMMIT');
                }else{
                    $this->db->query('ROLLBACK');
                }

            }else{
                if($re1){
                    $this->db->query('COMMIT');
                }else{
                    $this->db->query('ROLLBACK');
                }
            }
        }else{
            $sql = "UPDATE un_music_tips SET uids='',click_uid=0 WHERE record_id='{$data['record_id']}' AND type={$data['type']}";
            $this->db->query($sql);
        }
        
        $data=array(
            "msg"=>'Rollback complete!',
            "err"=>0,
        );
        echo json_encode($data,JSON_UNESCAPED_UNICODE);
    }


    /**
     * 投注列表
     * @method get /index.php?m=api&c=order&a=betList&token=b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @return mixed
     */
    public function betList() {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'status', 'end_time', 'start_time', 'page'));
        //验证token
        $this->checkAuth();

        //验证请求参数
        if ($_REQUEST['status'] != '' && !in_array($_REQUEST['status'], array(0, 1, 2, 3, 4,5))) {
            ErrorCode::errorResponse(200003, 'The transaction status does not exist');
        }

        //分页数据
        $page_cfg = $this->getConfig(100009); //获取每页展示多少数据
        $pageCnt = isset($page_cfg['value']) ? $page_cfg['value'] : 20;
        $page = (int) $_REQUEST['page'];
        $page = empty($page) ? 1 : $page;

        $where = array(
            'start_time' => $_REQUEST['start_time'],
            'end_time' => $_REQUEST['end_time'],
            'status' => $_REQUEST['status'],
            'type' => $_REQUEST['type'],
            'userId' => $this->userId,
            'page' => $page,
            'pageCnt' => $pageCnt
        );

//        lg('betList',var_export(array(
//            '$_REQUEST[\'start_time\']'=>$_REQUEST['start_time'],
//            '$_REQUEST[\'end_time\']'=>$_REQUEST['end_time'],
//            'strtotime($where[\'start_time\'])'=>strtotime($where['start_time']),
//            'strtotime($where[\'end_time\'])'=>strtotime($where['end_time']),
//            '(strtotime($where[\'end_time\'])-strtotime($where[\'start_time\']))'=>(strtotime($where['end_time'])-strtotime($where['start_time'])),
//            '60*60*24*31'=>60*60*24*31,
//            '((strtotime($where[\'end_time\'])-strtotime($where[\'start_time\'])) > (60*60*24*31))'=>((strtotime($where['end_time'])-strtotime($where['start_time'])) > (60*60*24*31)),
//        ),1));

        //最多只能查询31天的数据
        if((strtotime($where['end_time'])-strtotime($where['start_time'])) > (60*60*24*31)){
            ErrorCode::errorResponse(200003, 'The query time cannot exceed 31 days');
        }

        $cnt = $this->model->betListCnt($where);
        $pageNum = ceil($cnt / $pageCnt);
        $list = $this->model->betList($where);

        //彩种类型
        $gameInfo = $this->getLottery();

        //交易类型列表
//        $trantype = $this->getDictionary(2);
//
        //获取游戏币比例
        $rmbratio = $this->getConfig('rmbratio');
        $rmbratio = $rmbratio['value'];

        $total_money = 0;
        $total_award = 0;
        $lists = array();
        $redis = initCacheRedis();
        foreach ($list as $k => $v) {
            $lists[$k]['id'] = $v['id'];
            $lists[$k]['issue'] = $v['issue'];
            if($v['lottery_type'] == 12){
                $sql = "select pan_kou,odds from un_orders_football where order_id = {$v['id']}";
                $order_infos = $this->db->getone($sql);
                $lists[$k]['pan_kou'] = empty($order_infos['pan_kou']) ? "" : $order_infos['pan_kou'] ;
                $lists[$k]['odds'] = empty($order_infos['odds']) ? "" : $order_infos['odds'] ;
            }
            //获取房间名
            $lists[$k]['room_name'] = $redis->hget("allroom:{$v['room_no']}", "title")?:'';

            $lists[$k]['addtime'] = date('Y-m-d H:i', $v['addtime']);
            $lists[$k]['name'] = $gameInfo[$v['lottery_type']];
            $lists[$k]['money'] = bcmul($v['money'],$rmbratio,2);
            $total_money += $lists[$k]['money'];
            $lists[$k]['award'] = bcmul($v['award'],$rmbratio,2);
            $total_award += $lists[$k]['award'];
            $lists[$k]['way']  =$v['way'];
            if (is_numeric($v['way']) && strlen($v['way']) == 1) {
                $lists[$k]['way'] = '0' . $v['way'];
            }
            if($v['state'] == 0){
                $lists[$k]['state'] = '投注';
                if ($v['award_state'] == 0) {
                    $lists[$k]['status'] = '待开奖';
                    $lists[$k]['money_type'] = 2;
                } elseif ($v['award_state'] == 1) {
                    $lists[$k]['status'] = '未中奖';
                    $lists[$k]['money_type'] = 2;
                } elseif ($v['award_state'] == 2) {
                    $lists[$k]['status'] = '已中奖';
                    $lists[$k]['money_type'] = 2;
                } elseif ($v['award_state'] == 3) {
                    $lists[$k]['status'] = '和局';
                    $lists[$k]['money_type'] = 2;
                }
            }else{
                $lists[$k]['state'] = '撤单';
                $lists[$k]['status'] = '已撤单';
                $lists[$k]['money_type'] = 1;
            }
        }
        deinitCacheRedis($redis);

        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);
        if($start_date == "all")
        {
            $start_date = date("Y-m-d");
        }
        if($end_date == "all")
        {
            $end_date = date("Y-m-d");
        }
        if (!empty($start_date) && !empty($end_date)) {
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date . " 23:59:59");
            $where = " AND addtime BETWEEN {$start_time} and {$end_time}";
        } elseif (!empty($start_date)) {
            $start_time = strtotime($start_date);
            $where = " AND addtime >= {$start_time}";
        }elseif (!empty($end_date)) {
            $end_time = strtotime($end_date . " 23:59:59");
            $where = " AND addtime <= {$end_time}";
        } else {
            $where = "";
        }

        if(!empty($_REQUEST['type']) && $_REQUEST['type']!= "all"){
            $where .=" AND lottery_type = {$_REQUEST['type']}";
        }
        $sql = "select sum(money) as money, SUM(award)AS award from un_orders  where user_id={$this->userId} AND state = 0{$where}";

        $res = O('model')->db->getOne($sql);
        if(empty($res['money'])){
            $res['money'] = 0;
        }
        if(empty($res['award'])){
            $res['award'] = 0;
        }


        //app 反回数据
        $gameInfos = array();
        foreach ($gameInfo as $k => $v){
            $gameInfos[] = array("id"=>$k,"name"=>$v);
        }
        $data = array();
        $data['list'] = $lists;
        $data['total'] = $res;
        $data['pageNum'] = $pageNum;
        $data['gameInfo'] = $gameInfos;
        $data['trantype'] = array(
            array("id"=>1,"name"=>"已中奖"),
            array("id"=>2,"name"=>"未中奖"),
            array("id"=>3,"name"=>"待开奖"),
            array("id"=>4,"name"=>"撤单"),
            array("id"=>5,"name"=>"和局"),
        ); //交易状态

        ErrorCode::successResponse($data);
    }

    /**
     * 投注详情
     */
    public function detail() {
        //验证参数
       $this->checkInput($_REQUEST, array('token', 'id'));
        //验证token
       $this->checkAuth();
        $bi_fen = 0;
        $sql = "SELECT order_no, money, issue, addtime, state, lottery_type, way, award,award_state FROM un_orders WHERE id = ".$_REQUEST['id'];
        $res = O('model')->db->getOne($sql);
        if (empty($res)) {
            ErrorCode::errorResponse(100029,'The data does not exist');
        }
        if($res['lottery_type']==12){
            $sql = "SELECT result_bi_feng,pan_kou,odds,bi_feng FROM `un_orders_football` WHERE order_id={$_REQUEST['id']}";
            $fb_data = $this->db->getone($sql);
        }
        switch ($res['state']) {
            case 0: //投注
                $sql2 = "SELECT l.order_num, l.use_money FROM un_account_log AS l WHERE l.order_num = '" . $res['order_no'] . "' AND l.user_id = ".$this->userId." AND l.type = 13";
                $res2 = O('model')->db->getOne($sql2);

                if (!empty($res2)) {
                    $res = array_merge_recursive($res,$res2);
                }
                switch ($res['lottery_type']) {
                    case 1:
                        $sql = "SELECT a.spare_1, a.spare_2, a.open_result FROM un_open_award AS a WHERE a.issue = '" . $res['issue'] . "'";
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 2:
                        $sql = 'select kaijianghaoma from un_bjpk10 where lottery_type='.$res['lottery_type'].' and  qihao=' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 3:
                        $sql = "SELECT a.spare_1, a.spare_2, a.open_result FROM un_open_award AS a WHERE a.issue = '" . $res['issue'] . "'";
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 4:
                        $sql = 'select kaijianghaoma from un_xyft where qihao=' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 5:
                        $sql = 'select lottery_result from un_ssc where lottery_type = 5 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 6:
                        $sql = 'select lottery_result from un_ssc where lottery_type = 6 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 7:
                        $sql = 'select lottery_result from un_lhc where lottery_type = 7 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 8:
                        $sql = 'select lottery_result from un_lhc where lottery_type = 8 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 9:
                        $sql = 'select kaijianghaoma from un_bjpk10 where lottery_type='.$res['lottery_type'].' and qihao=' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 10:
                        $sql = 'select lottery_result from un_nn where lottery_type = 10 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 11:
                        $sql = 'select lottery_result from un_ssc where lottery_type = 11 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 13:
                        $sql = 'select lottery_result from un_sb where lottery_type = 13 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 14:
                        $sql = 'select lottery_result from un_ffpk10 where lottery_type = 14 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    default:
                        $res3 = null;
                }
                if (!empty($res3)) {
                    $res = array_merge_recursive($res,$res3);
                }
                if (is_numeric($res['way']) && strlen($res['way']) == 1) {
                    $res['way'] = '0' . $res['way'];
                }
                if (is_numeric($res['open_result']) && strlen($res['open_result']) == 1) {
                    $res['open_result'] = '0' . $res['open_result'];
                }
                $type = mb_substr($res['spare_2'], 0, 1, 'utf-8');
                $type2 = mb_substr($res['spare_2'], 1, 1, 'utf-8');
                if (!empty($type2)) {
                    $type2 = ', ' . $type2;
                }
                $type3 = mb_substr($res['spare_2'], 0, 2, 'utf-8');
                if (!empty($type3)) {
                    $type3 = ', ' . $type3;
                }
                $type4 = mb_substr($res['spare_2'], 2, NULL, 'utf-8');
                if (!empty($type4)) {
                    $type4 = ', ' . $type4;
                }
                if (in_array($res['lottery_type'], array(2,4,9))) {
                    $data = array(
                        array('name' => "流水号", 'value' => $res['order_no']),
                        array('name' => "投注期号", 'value' => $res['issue']),
                        array('name' => "交易金额", 'value' => $this->convert($res['money']) . " 元宝"),
                        array('name' => "投注内容", 'value' => $res['way']),
                        array('name' => "开奖结果", 'value' => empty($res['award_state']) ? '待开' : $res['kaijianghaoma']),
                        array('name' => "中奖金额", 'value' => $this->convert($res['award']) . " 元宝"),
                        array('name' => "即时余额", 'value' => $this->convert($res['use_money']) . " 元宝")
                    );
                }else if(in_array($res['lottery_type'], array(1,3))){
                    $data = array(
                        array('name' => "流水号", 'value' => $res['order_no']),
                        array('name' => "投注期号", 'value' => $res['issue']),
                        array('name' => "交易金额", 'value' => $this->convert($res['money']) . " 元宝"),
                        array('name' => "投注内容", 'value' => $res['way']),
                        array('name' => "开奖结果", 'value' => empty($res['award_state']) ? '待开' : $res['spare_1'] . " = " . $res['open_result'] . " " . $type . $type2 . $type3 . $type4),
                        array('name' => "中奖金额", 'value' => $this->convert($res['award']) . " 元宝"),
                        array('name' => "即时余额", 'value' => $this->convert($res['use_money']) . " 元宝")
                    );
                }else if(in_array($res['lottery_type'], array(12))){
                    $data = array(
                        array('name' => "流水号", 'value' => $res['order_no']),
//                        array('name' => "投注期号", 'value' => $res['issue']),
                        array('name' => "交易金额", 'value' => $this->convert($res['money']) . " 元宝"),
                        array('name' => "投注内容", 'value' => $res['way']),
                        array('name' => "投注赔率", 'value' => $fb_data['odds']),
                        array('name' => "投注比分", 'value' => $fb_data['bi_feng']),
                        array('name' => "投注盘口", 'value' => $fb_data['pan_kou']),
                        array('name' => "开奖结果", 'value' => empty($fb_data['result_bi_feng']) ? '待开' : $fb_data['result_bi_feng']),
                        array('name' => "中奖金额", 'value' => $this->convert($res['award']) . " 元宝"),
                        array('name' => "即时余额", 'value' => $this->convert($res['use_money']) . " 元宝")
                    );
                }else if (in_array($res['lottery_type'], array(5,6,7,8,10,11,13,14))) {
                    if($res['lottery_type']==10){
                        if (!empty($res['lottery_result'])) {
                            $spare_2 = getShengNiuNiu($res['lottery_result'],1);
                            $tmp = $spare_2['sheng'].','.($spare_2['sheng']=='红方胜'?$spare_2['red']['lottery_niu']:$spare_2['blue']['lottery_niu']);
                            $res['lottery_result'] = $tmp;
                        }
                    }
                    if(in_array($res['lottery_type'], [7,8])){
                        $res['lottery_result'] = preg_replace('/,(\d+)$/','+$1',$res['lottery_result']);
                    }
                    $data = array(
                        array('name' => "流水号", 'value' => $res['order_no']),
                        array('name' => "投注期号", 'value' => $res['issue']),
                        array('name' => "交易金额", 'value' => $this->convert($res['money']) . " 元宝"),
                        array('name' => "投注内容", 'value' => $res['way']),
                        array('name' => "开奖结果", 'value' => empty($res['award_state']) ? '待开' : $res['lottery_result']),
                        array('name' => "中奖金额", 'value' => $this->convert($res['award']) . " 元宝"),
                        array('name' => "即时余额", 'value' => $this->convert($res['use_money']) . " 元宝")
                    );
                }
                
                break;
            case 1://撤单
                $data = array(
                    array('name' => "流水号", 'value' => $res['order_no']),
                    array('name' => "撤单期号", 'value' => $res['issue']),
                    array('name' => "撤单金额", 'value' => $this->convert($res['money']) . " 元宝")
                );
                break;
            default:
                ErrorCode::errorResponse(100029,'The data does not exist');
        }

        ErrorCode::successResponse(array('list' =>$data));
    }

    /**
     * 获取当期所下投注
     */
    public function nowBet() {
        //验证token
        $this->checkAuth();

        //接收参数
        $issue = $_REQUEST['issue'];
        $room_no = $_REQUEST['room_no'];
        $sql = "select way,money,order_no,addtime from un_orders where user_id = $this->userId and issue = $issue and room_no = $room_no and state = 0 and chase_number = '' order by id asc";
        $list = $this->db->getall($sql);
       // $list = $this->model->getlist('way,money,order_no,addtime', array('user_id' =>, 'issue' => $issue, 'room_no' => $room_no, 'state' => 0, 'chase_number'=> "!= ''"), 'id ASC');

       foreach ($list as $k => $v) {
           $v['money'] = convert($v['money']);
           $v['addtime'] = date('H:i', $v['addtime']);
           $list[$k] = $v;
       }

        ErrorCode::successResponse(array('list' => $list));
    }


    /**
     * 字典表
     * @param $type 类型
     * @return array
     */
    protected function getDictionary($type){
        //初始化redis
        $redis = initCacheRedis();
        $LTrade = $redis->lRange('DictionaryIds'.$type, 0, -1);
        $tranType = array();
        foreach ($LTrade as $v){
            $res = $redis->hMGet("Dictionary".$type.":" . $v, array('id', 'name'));
            $tranType[$res['id']] = $res['name'];
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return array('ids'=>$LTrade,'lists'=>$tranType);
    }


    /**
     * 配置信息
     * @param $k
     * @return $config array
     */
    private function getConfig($k){
        //初始化redis
        $redis = initCacheRedis();
        $config = $redis->hGetAll("Config:$k");
        //关闭redis链接
        deinitCacheRedis($redis);
        return $config;
    }

    private function getLottery(){
        //redis里面取彩种类型
        $redis = initCacheRedis();
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
        $gameInfo = array();
        foreach ($LotteryTypeIds as $v) {
            $res = $redis->hMGet("LotteryType:" . $v, array('id', 'name'));
            $gameInfo[$res['id']] = $res['name'];
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return $gameInfo;
    }

    /*
     * 获取追号信息
     */
    public function getZhuiHaoInfo()
    {
        $this->checkAuth();
        $room_id = $_REQUEST['room_id'];
        $redis = initCacheRedis();
        $roomInfo = $redis->hGetAll('allroom:'.$room_id);
        deinitCacheRedis($redis);

        if(empty($roomInfo['lottery_type'])){
            $resources['code'] = -1;
            $resources['msg'] = "No data";
            $resources['list'] = "";
            jsonReturn($resources);
            return false;
        }
        $sql = "select issue,money,multiple,award_state,chase_number,way,addtime from un_orders where lottery_type='".$roomInfo['lottery_type']."' and state = 0 and user_id='".$this->userId."' and chase_number != '' and room_no=$room_id order by issue, addtime desc";
        $res = $this->db->getall($sql);
        $resources = [];
        if(!empty($res)) {
            $list = [];
            foreach ($res as $val) {
                if(!in_array($val['chase_number'],$list)) {
                    $list[$val['chase_number']] = [];
                }
            }
            foreach ($res as $val) {
                foreach ($list as $key=>$va) {
                    if($val['chase_number'] == $key) {
                        $list[$key]['data'][] = $val;
                        $list[$key]['way'] = $val['way'];
                        $list[$key]['time'] = date("m-d H:i",$val['addtime']);
                        $list[$key]['number'] = $val['chase_number'];
                    }
                }
            }
            $arr = [];
            foreach ($list as $key=>$val) {
                foreach ($val['data'] as $v) {
                    if($v['award_state'] == 0) {
                        if(!in_array($key,$arr)) {
                            $arr[] = $key;
                        }
                    }
                }
            }
            foreach ($list as $key=>$v) {
                if(!in_array($key,$arr)) {
                    unset($list[$key]);
                }
            }
            $list = toIndexArr($list);
            if(!empty($list)) {
                $resources['code'] = 0;
                $resources['msg'] = "Request succeeded";
                $resources['list'] = $list;

            } else {
                $resources['code'] = -1;
                $resources['msg'] = "No data";
                $resources['list'] = "";
            }
        } else {
            $resources['code'] = -1;
            $resources['msg'] = "No data";
            $resources['list'] = "";
        }
        jsonReturn($resources);

    }

    /*
     * 撤单
     */
    public function ceDanZhuiHao()
    {
        $this->checkAuth();
        $check = true;
        $chase_number = $_REQUEST['number'];
        $room_id = $_REQUEST['room_id'];
        $type = $_REQUEST['type'];
        if(!in_array($type,[0,1])){
            $type = 1;
        }
        $redis = initCacheRedis();
        $roomInfo = $redis->hGetAll('allroom:'.$room_id);
        deinitCacheRedis($redis);

        //添加北京PK10的判断 Alan 2017-6-26 如果是pk10的话，不能从open_award取数据，因为数据源不同
        if($roomInfo['lottery_type']!=2){
            $issue = $this->db->getone("select issue from un_open_award where lottery_type = '".$roomInfo['lottery_type']."' order by issue desc");
            $issue = $issue['issue']+1;
        }else{
            $issue_temp=D('workerman')->get_bjpk10_lastest_model($room_id,2);
            $issue=$issue_temp['qihao'];
        }
        
        if(!empty($chase_number)) {
            $this->db->query('BEGIN');
            if($type == 0){
                $sql = "select id,money,chase_number,user_id,order_no,issue from un_orders where state = 0 and award_state = 0 and chase_number = '".$chase_number."' and issue >= {$issue}";
            } else {
                $sql = "select id,money,chase_number,user_id,order_no,issue from un_orders where state = 0 and award_state = 0 and chase_number = '".$chase_number."' and issue > {$issue}";//获取追号数据
            }
            $zhuiHaoInfo = $this->db->getall($sql);//获取追号数据
            if(!empty($zhuiHaoInfo)) {
                foreach ($zhuiHaoInfo as $info) {
                    $kymoney = $this->db->getone("select money from un_account where user_id = {$info['user_id']}");
                    $cdOrderSql = "update un_orders set state=1 WHERE id=".$info['id']."";
                    $cdOrderRet = $this->db->query($cdOrderSql);
                    if (empty($cdOrderRet)) {
                        $check = false;
                    };
                    //更新资金表
                    $cdAccMoney = $kymoney['money']+$info['money'];
                    $cdAccSql = "update un_account set money=$cdAccMoney WHERE user_id='{$info['user_id']}'";
                    $cdAccRet = $this->db->query($cdAccSql);
                    if (empty($cdAccRet)) {
                        $check = false;
                    }
                    //添加资金明细表 增加账户日志记录
                    $order_no = "CD" . date("YmdHis") . rand(100, 999);
                    $cdLogData = array(
                        'order_num' => $order_no,
                        'user_id' => $info['user_id'],
                        'type' => 14,
                        'addtime' => time(),
                        'money' => $info['money'],
                        'use_money' => $cdAccMoney,
                        'remark' => "Cancel the order after tracking number No ".$info['issue']." ".$info['order_no']." bet"
                    );
                    $inid = D('accountlog')->aadAccountLog($cdLogData);
                    if (empty($inid)) {
                        $check = false;
                    }
                }
                if($check === false){
                    $this->db->query("ROLLBACK"); //事务回滚
                    $arr['code'] = -1;
                    $arr['msg'] = "Cancellation failed~";
                } else {
                    $this->db->query("COMMIT"); //事务提交
                    $accout_money = $this->db->getone("select money from un_account where user_id='" .$this->userId. "'");
                    $arr['code'] = 0;
                    $arr['msg'] = "Successful cancellation";
                    $arr['money'] = convert( $accout_money['money']);
                }
            } else {
                $arr['code'] = -1;
                $arr['msg'] = "Illegal operation";
            }
        } else {
            $arr['code'] = -1;
            $arr['msg'] = "Illegal operation";
        }
        jsonReturn($arr);
    }
    
    /**
     * 派奖订单回滚时客户投注金额也必须全部回滚兑换积分
     * @param 采种 $lottery_type
     * @param 期号 $issue
     */
    public function ordersBettingScroeCallBack($lottery_type, $issue,$match_id,$match_status)
    {
        //投注金额换积分回滚
        if(in_array($lottery_type,array(12))){
            return false;
            $sql = "SELECT o.user_id, SUM(o.money) AS betMoney FROM un_orders o,un_orders_football of WHERE o.lottery_type={$lottery_type} AND o.id = of.`order_id` AND o.room_no IN (SELECT id FROM `un_room` WHERE `match_id` = '{$match_id}') AND of.`type`={$match_status}  GROUP BY o.user_id";
        }else{
            $sql = "SELECT user_id, sum(money) as betMoney FROM un_orders WHERE lottery_type={$lottery_type} AND issue={$issue} GROUP BY user_id";
        }
        $userBetting = $this->db->getall($sql);
        
        foreach ($userBetting as $k => $v) {
            //投注金额换积分回滚
            callbackIntegral($v['betMoney'], $v['user_id'], 6, $issue, $lottery_type);
        }
        
        return true; 
    }
    
    
}
