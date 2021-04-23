<?php

/**

 * User: Alan

 * Date: 2016/06/14

 * desc: 处理workerman的请求

 */



!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');



class WorkermanAction extends Action{



    public function getRoomInfo()

    {

        $roomid = $_REQUEST['roomid'];

        O('Gateway');

        $total =  Gateway::getAllUidByRoomID($roomid);

        echo encode($total);

    }



    public function getRoomOnline()

    {

        O('Gateway');

        $total =  Gateway::getAllUidByRoom();

        echo encode($total);

    }



    //进入房间对应的workerman连接

    public function http2room(){

        //获取连接房间接口

        $domain = $_REQUEST['domain']; //顶级域名

        $redis = initCacheRedis();

        $lists = decode($redis->hget('Config:room_conn_set','value'));

        deinitCacheRedis($redis);

        $list = array();

        foreach ($lists as $v){

            foreach ($v as $kk=>$vv){

                $list[$kk] = $vv;

            }

        }

        if(empty($list)){

            echo 'Please add a list of domain names in the background';

        }else{

            $conn = $list[$domain];

            if(empty($conn)){

                echo 'No room connection data is available for the current domain name';

            }else{

                echo $conn;

            }

        }

        return false;

    }



    /**

     * 获取期号

     */

    public static function getQihaoAll()

    {

        $redis = initCacheRedis();

        $lotteryTypeList = $redis->lRange('LotteryTypeIds',0,-1);

        deinitCacheRedis($redis);

        $room = 0;

        foreach ($lotteryTypeList as $lotteryType){

            $res = D('workerman')->getQihao($lotteryType,time(),$room);

            dump($res);

        }

    }



    //今日充值人数

    public function todayRechargeUser() {

        $today = strtotime(date('Y-m-d'));

        $sql = "select count(distinct user_id) as cnt from un_account_recharge where `status` = 1 and addtime >= $today";

        $rt = $this->db->getone($sql);

        return $rt['cnt'];

    }



    //今日历史首冲人数

    public function todayHistoryFirstRechargeUser() {

        $today = strtotime(date('Y-m-d'));

        $todayRechargeSql = "select user_id from un_account_recharge where `status` = 1 and addtime >= $today group by user_id";

        $userRechargeNumSql = "SELECT infos.user_id,SUM(IF(uar.id,1,0)) as cnt FROM ($todayRechargeSql) infos LEFT JOIN un_account_recharge uar ON infos.user_id = uar.user_id AND `status` =  1 AND addtime < $today GROUP BY infos.user_id";

        $cSql = "select count(*) as cnt from ($userRechargeNumSql) d LEFT JOIN un_user uu ON d.user_id = uu.id where  cnt = 0 AND reg_type not in (0,8,9,11)";

        $rt = $this->db->getone($cSql);

        return $rt['cnt'];

    }



    //在线人数   统计10分钟之内操作过的用户

    public function OnlineUsers($type=0){

        $now = time();

        if($type==0){

            $where = " and reg_type not in(8,9,11)";

        }else{

            $where = " and reg_type in(8,11)";

        }

        $rt = $this->db->getone("select count(*) as cnt from un_session a LEFT JOIN un_user b on a.user_id=b.id where is_admin=0 $where");

        return $rt['cnt'];

    }



    //后台统计在线人 在线人数 (监控同一账号登录多机器)

    public function onlineUser() {

        $code = 0;

        $cnt = $this->OnlineUsers(0);

        $cnt1 = $this->OnlineUsers(1);

        $cnt3 = $this->todayHistoryFirstRechargeUser();



        //改成统计所有前台workerman

        $total=0;

        foreach (C('home_arr') as $k=>$v){

            if(is_home($k)){  //要陫除后台统计的

                //组装URL

                $url = $v . "/?m=api&c=lobby&a=getRealUserTotal";

                $data = array('s' => 'a8fce04d58c1f06f30da6d33c7523abc');

                $total += curl_post($url, $data);

            }

        }

        $pay_type = $this->getPayType(10);

        $paymentIdStr = $pay_type['tranTypeIds'];

        $paymentIdStr = implode(',', $paymentIdStr);

        $cash_count = $this->db->result("select count(cash.id) from un_account_cash as cash left join un_user as user on cash.user_id = user.id where (cash.status = 0 OR cash.status = 5 OR  cash.status = 6) AND user.reg_type not in(8,9,11)");

        $charge_count = $this->db->result("select count(r.id) from un_account_recharge as r LEFT JOIN un_payment_config as pc on pc.id = r.payment_id LEFT JOIN un_user as u on r.user_id = u.id where status = 0 AND (pc.type IN ({$paymentIdStr}) OR r.pay_type IN ({$paymentIdStr})) AND u.reg_type NOT IN (8,9,11)");

        $json = encode(array("cnt" => $cnt, "code" => $code, 'cnt1' => $cnt1, 'cnt2' => $total, 'cnt3' => $cnt3,'cash_count'=>$cash_count,'charge_count'=>$charge_count));

        //改成存redis里, 然后异步获取

        $redis = initCacheRedis();

        $redis->set('header_online_data',$json);

        deinitCacheRedis($redis);

    }



    function getPayType($type){

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

        return array('tranTypeIds'=>$LTrade,'tranType'=>$tranType);

    }



    //最近投注的5个彩种

    public function recently_lottery_beting(){

        $uid = $_REQUEST['uid'];



        $redis = initCacheRedis();

        $index_lottery_list = $redis->HMGet('Config:index_lottery_list',['value']);

        $lottery_list = json_decode($index_lottery_list['value'], true);

        $noneShowLottery = [12];          //不显示的彩种

        foreach($lottery_list as $lottery) {

            if($lottery['is_show'] == 0) $noneShowLottery[] = $lottery['lottery_type'];

        }

        $noneShowLottery = array_unique($noneShowLottery);



        $where = '';

        if($noneShowLottery) $where = " and lottery_type not in (".implode(',', $noneShowLottery).")";



        $btime = strtotime('Yesterday'); //开始时间

        $sql = "SELECT DISTINCT lottery_type FROM `un_orders` WHERE user_id={$uid} AND ADDTIME >= '{$btime}' $where ORDER BY ADDTIME DESC LIMIT 5";

        $res = $this->db->getall($sql);

        $len = count($res); //统计数量

        $val = $redis->hget("Config:recommend_lottery_list",'value');

        $list = decode($val);

        if($noneShowLottery) {

            foreach($list as $k=>$v) {

                if(in_array($v['lottery_type'], $noneShowLottery))

                    unset($list[$k]);            //去除首页不显示的彩种

            }

        }



        $lids = $data = $json = array();

        if(!empty($res)){

            foreach ($res as $v){

                $logo = '';

                $sql = "SELECT room_no  FROM `un_orders` WHERE user_id={$uid} and lottery_type = {$v['lottery_type']} AND ADDTIME >='{$btime}' ORDER BY ADDTIME DESC LIMIT 1";

                $room_id = $this->db->result($sql);

                $room_name = $redis->hGet("allroom:{$room_id}",'title');



                $lotteryName = $redis->hGet("LotteryType:{$v['lottery_type']}",'name');

                $lArr = decode($redis->hget('Config:index_lottery_list','value'));

                foreach ($lArr as $lv){

                    if($lv['lottery_type'] == $v['lottery_type']){

                        $logo = $lv['pic_url_pc_logo'];

                        break;

                    }

                }

                $lids[] = $v['lottery_type']; //收集, 给下面用的

                $data[] = array(

                    'lottery'=>array(

                        'id'=>$v['lottery_type'],

                        'name'=>$lotteryName,

                        'logo'=>$logo,

                        'is_recommend'=>0,

                    ),

                    'room'=>array(

                        'id'=>$room_id,

                        'name'=>$room_name,

                        'is_recommend'=>0,

                    ),

                );

            }

            if($len<5){

                foreach ($list as $vv){

                    if(!in_array($vv['lottery_type'],$lids)){

                        $sql = "SELECT id,title FROM `un_room` WHERE lottery_type={$vv['lottery_type']} LIMIT 1";

                        $roomInfo = $this->db->getone($sql);



                        $data[] = array(

                            'lottery'=>array(

                                'id'=>$vv['lottery_type'],

                                'name'=>$vv['lottery_name'],

                                'logo'=>$vv['pc_pic'],

                                'is_recommend'=>1,

                            ),

                            'room'=>array(

                                'id'=>$roomInfo['id'],

                                'name'=>$roomInfo['title'],

                                'is_recommend'=>1,

                            ),

                        );

                        if(count($data)>=5){ //只存5条记录

                            break;

                        }

                    }

                }

            }



        }else{

            foreach ($list as $vv){

                $sql = "SELECT id,title FROM `un_room` WHERE lottery_type={$vv['lottery_type']} LIMIT 1";

                $roomInfo = $this->db->getone($sql);



                $data[] = array(

                    'lottery'=>array(

                        'id'=>$vv['lottery_type'],

                        'name'=>$vv['lottery_name'],

                        'logo'=>$vv['pc_pic'],

                        'is_recommend'=>1,

                    ),

                    'room'=>array(

                        'id'=>$roomInfo['id'],

                        'name'=>$roomInfo['title'],

                        'is_recommend'=>1,

                    ),

                );

                if(count($data)>=5){ //只存5条记录

                    break;

                }

            }

        }



        deinitCacheRedis($redis);

        echo encode($data);

        return false;

    }



    //足彩取消订单机器人

    public function auto_concal_order(){



        //$time = $_REQUEST['time'];

        $nowTime = time();



        $redis  = initCacheRedis();



        //获取后台设置的订单生效时间

        $delayTime = $redis->hGet('Config:football_order_delay_time','value');

        if($delayTime == 0){

            return false;

        }

        $time = $nowTime-$delayTime;

        //防止刷单

        $co_str = implode(':',$_REQUEST);

        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去

            $redis->expire($co_str,60); //设置它的超时

            deinitCacheRedis($redis);

        }else{

            deinitCacheRedis($redis);

            return false;

        }



        //var_dump(array('$time'=>$time,'$_REQUEST'=>$_REQUEST));

        //查订单

        $sql = "SELECT o.id,o.order_no, o.user_id, o.way, o.money,o.single_money,o.chase_number, o.reg_type, o.room_no,o.win_stop,o.addtime,o.whats_val,of.`bi_feng`,of.`pan_kou`,of.`odds` FROM un_orders o,un_orders_football of WHERE o.id=of.order_id AND o.reg_type != 9 AND o.lottery_type = 12 AND o.state = 0 AND o.award_state = 0 AND o.addtime >= {$time}";

        $list = $this->db->getall($sql);

        if(empty($list)){

            return false;

        }else{



            $lottery_type = 12;

            $bi_feng = 0;

            $redis = initCacheRedis();

            //获取房间的信息

            $PRoomIds = $redis->lRange("PublicRoomIds{$lottery_type}", 0, -1);

            $SRoomIds = $redis->lRange("PrivateRoomIds{$lottery_type}", 0, -1);

            $rooms = array_merge($PRoomIds,$SRoomIds);

            $odds_arr=array();

	        $match_states = array();

            foreach ($rooms as $rk=>$rv){

                $match_id = $redis->hget('allroom:'.$rv,'match_id');

                $odds_info = decode($redis->hGet("fb_odds",$match_id));

                $against = decode($redis->hGet("fb_against",$match_id));

                $bi_feng = $against[0]['match_score'];

                $match_states[$rv] = $against[0]['match_state']; //当前状态

                //组装赔率数据

                foreach ($odds_info as $ok=>$ov){

                    $odds_arr[$rv][$ov['way']] = array('odds'=>$ov['odds'],'handicap'=>$ov['handicap'],'bi_feng'=>$bi_feng);

                }

            }

            deinitCacheRedis($redis);

            try {

                foreach ($list as $k=>$v) {

                    //开启事务

                    O('model')->db->query('BEGIN');

                    $uid = $v['user_id'];

                    $isCancalOder = 0;

                    $content = "【{$v['way']}】";

                    $str = "";

                    //投注X秒之后, 有比分变动、锁盘状态、赔率变为0的, 自动撤单 20180619 update

	                if($match_states[$v['room_no']] > 0 &&  $match_states[$v['room_no']] < 9){ //进行中

	                	if($odds_arr[$v['room_no']][$v['way']]['bi_feng'] != ''){ //不为空

			                if($v['bi_feng'] !== $odds_arr[$v['room_no']][$v['way']]['bi_feng']){ //比分变了

				                $isCancalOder = 1;

				                $str .= "The score change system cancels the order.Score when betting 【{$v['bi_feng']}】, current score 【{$odds_arr[$v['room_no']][$v['way']]['bi_feng']}】;";

			                }



			                if($odds_arr[$v['room_no']][$v['way']]['odds'] == ''){

				                $str .= "The system cancels the order. Current method【{$v['way']}】 odds are empty;";

				                $isCancalOder = 1;

			                }



			                if($odds_arr[$v['room_no']][$v['way']]['odds'] == '0' || $odds_arr[$v['room_no']][$v['way']]['odds'] == '0.00'){

				                $str .= "The system cancels the order. Current method【{$v['way']}】 is locked;";

				                $isCancalOder = 1;

			                }

		                }

	                }

                    if($isCancalOder==1){ //要撤单



                        $remark ="ID:{$v['id']}, Serial number:{$v['order_no']}, ".$str;

                        $content .=$str;

                        $money = $v['money'];

                        $sqla="select money from un_account WHERE  user_id={$uid}";

                        $re = $this->db->getone($sqla);



                        //查注册类型

                        $sqlua="select reg_type from un_user where id=".$uid;

                        $re_reg=$this->db->getone($sqlua);

                        $ye = bcadd($money,$re['money'],2);

                        $order_nos = "CD" . date("YmdHis") . rand(100, 999);

                        $message['commandid'] =3015;

                        $log_data = array(

                            'order_num' => $order_nos,

                            'user_id' => $uid,

                            'type' => 14,

                            'addtime' => $nowTime,

                            'money' => $money,

                            'use_money' => $ye,

                            'remark'=>$remark,

                            'reg_type' => $re_reg['reg_type'],

                        );



                        //插入资金交易明细

                        $inid = $this->db->insert('un_account_log',$log_data);

                       if (empty($inid)) throw new Exception('Update failed!2');



                        //更新帐户余额表

                        $ret = $this->db->query("update un_account set money=money+{$money} WHERE user_id={$uid}");

                        if (empty($ret)) throw new Exception('Update failed!3');



                        //改订单状态

                        $sqlu="update un_orders set state=1 WHERE id={$v['id']}";

                        $ret = $this->db->query($sqlu);

                        if (empty($ret)){

                            throw new Exception('Update failed!1');

                        }

                        //提交事务

                        O('model')->db->query('COMMIT');



                        $message2 = array('commandid' => 3010, 'money' => $this->convert($ye));



                        //发送信息给前台, 这里传的是UID,前台对这个UID单独处理

                        $data['type']="double_cancel_order";

                        $data['id']=$uid;

                        $data['roomid'] = $v['room_no'];

                        $message['money'] = $money;

                        $message['order_no'] = $v['order_no'];

                        $message['content'] = $content;

                        $data['json']=encode($message);

                        $data['json2']=encode($message2);

                        send_home_data($data);

                    }

                }



                //删除撤单间隔标识

                $redis = initCacheRedis();

                $redis->del($co_str);

                deinitCacheRedis($redis);

                return false;

            }catch (Exception $e){

                //回滚事务

                O('model')->db->query('ROLLBACK');

                return false;

            }

        }

    }







    //获取ws的端口号

    public function getWsPort(){

        //域名跨域问题

        header("Access-Control-Allow-Origin: *");

        //取端口号

        echo '7272';

        return '7272';

    }



    //房间发布信息

    public function room_sent(){

        $Gateway = O('Gateway');

        $Gateway::$registerAddress = C('Gateway');



        $model = D('workerman');



        $nowtime = time();

        //获取配置信息

        $redis = initCacheRedis();

        $list = decode($redis->get('messageconfig'));

        $lotteryType = $redis->lRange('LotteryTypeIds',0,-1);

        $info = array();

        foreach ($lotteryType as $v){

            $info[$v] = $model->getQihao($v,$nowtime);

        }

        foreach ($list as $v) {

            if($info[$v['lottery_type']]['stopOrSell'] == 2){  //如果停售, 不执行下面的

                continue;

            }



            $re = $this->sendMessage($v, $info[$v['lottery_type']]);

            if(!empty($re)){

                $Gateway::sendToGroup($v['room_id'],$re);

            }

        }

        unset($list);

        $Ids = $redis->lRange('allroomIds',0,-1);

        $key_str = str_replace("Ids", ':', 'allroomIds');

        $room = array();

        foreach ($Ids as $v) {

            $room[$v] = $redis->hGetAll($key_str . $v);

        }



        $list = $Gateway::getAllClientSessions();

        if ($list) {

            foreach ($list as $k => $v) {

                if (empty($v)) {

                    continue;

                }

            }

        }

        deinitCacheRedis($redis);

    }



    /**

     * 定时任务每秒

     * @param $client_id

     */

    public function sendMessage($v, $info)

    {

        $redis = initCacheRedis();

        $lottery_type = $redis->hGet("allroom:{$v['room_id']}",'lottery_type');

        deinitCacheRedis($redis);

        if ($lottery_type == 12) {

            if($v['release_time'] == time()){

                $qihao = $info['issue'];

                if (strpos($v['content'], '{期号}') !== false) {

                    $v['content'] = str_replace("{期号}", $qihao, $v['content']);

                }

                if (strpos($v['content'], '{下注核对}') !== false) {

                    $sql = "select U.nickname,D.way,D.money,U.id from un_orders D LEFT JOIN  un_user U ON D.user_id=U.id where D.issue='" . $qihao . "' AND D.room_id=" . $v['room_id'];

                    $ret = $this->db->getall($sql);

                    $str = '';

                    if ($ret) {

                        $redis = initCacheRedis();

                        $RmbRatio = $redis->hget('Config:rmbratio','value');

                        $xianshigeshi = $redis->hget("Config:dandianshuzi", 'value');

                        deinitCacheRedis($redis);

                        if ($xianshigeshi == 'space') $xianshigeshi = " ";

                        $userlist = array();

                        foreach ($ret as $val) {

                            $ljstr = is_numeric($val['way']) ? $xianshigeshi : '';

                            $val['money'] = $val['money'] * $RmbRatio;

                            if (isset($userlist[$val['id']])) {

                                $userlist[$val['id']] .= "  " . $val['way'] . $ljstr . $val['money'];

                            } else {

                                $userlist[$val['id']] = $val['nickname'] . "[" . $val['way'] . $ljstr . $val['money'];

                            }

                        }

                        if (!empty($userlist)) {

                            $str = implode("]\n", $userlist) . "]";

                        }

                    }

                    $v['content'] = str_replace("{下注核对}", $str, $v['content']);

                }

                return encode(array('commandid' => 3004, 'nickname' => '', 'content' => $v['content']));

            }

        }

        if ($info['time'] == $v['release_time']) {

            $qihao = $info['issue'];

            if (strpos($v['content'], '{期号}') !== false) {

                $v['content'] = str_replace("{期号}", $qihao, $v['content']);

            }

            if (strpos($v['content'], '{下注核对}') !== false) {

                $sql = "select U.nickname,D.way,D.money,U.id from un_orders D LEFT JOIN  un_user U ON D.user_id=U.id where D.issue='" . $qihao . "' AND D.room_id=" . $v['room_id'];

                $ret = $this->db->getall($sql);

                $str = '';

                if ($ret) {

                    $redis = initCacheRedis();

                    $RmbRatio = $redis->hget('Config:rmbratio','value');

                    $xianshigeshi = $redis->hget("Config:dandianshuzi", 'value');

                    deinitCacheRedis($redis);

                    if ($xianshigeshi == 'space') $xianshigeshi = " ";

                    $userlist = array();

                    foreach ($ret as $val) {

                        $ljstr = is_numeric($val['way']) ? $xianshigeshi : '';

                        $val['money'] = $val['money'] * $RmbRatio;

                        if (isset($userlist[$val['id']])) {

                            $userlist[$val['id']] .= "  " . $val['way'] . $ljstr . $val['money'];

                        } else {

                            $userlist[$val['id']] = $val['nickname'] . "[" . $val['way'] . $ljstr . $val['money'];

                        }

                    }

                    if (!empty($userlist)) {

                        $str = implode("]\n", $userlist) . "]";

                    }

                }

                $v['content'] = str_replace("{下注核对}", $str, $v['content']);

            }

            return encode(array('commandid' => 3004, 'nickname' => '', 'content' => $v['content']));

        }

        return false;

    }



    //自主彩种

    public function self_lottery_data(){

        if (C('is_host_admin') !== '1') {

            return false;

        }

        //验证签名

        $res = verificationSignature();

        $res['status'] = "success";

        if($res['status'] !== "success"){

            if($res['code'] == 3){

                ErrorCode::errorResponse(999998,"Request timed out, please make sure your mobile phone system time is: Beijing (China) time, refresh and try again!");

            }

            ErrorCode::errorResponse(999999,"Signing failed, please make sure the app is the latest version and try again!");

        }

        $td = array();

        $table ='';

        $type = '';

        $time = '';

        $qihaoIds = '';

        $lottery_type = $_REQUEST['lottery_type'];

        $pre_open = $td['pre_open']?$td['pre_open']:0;

        if($lottery_type == 6){

            $table = 'un_ssc';

            $td = $_REQUEST;

            $qihaoIds = @file_get_contents('sfc_qihao.json'); //获取数据;

            if(preg_match('/480$/',$td['issue'])){ //最后一期

                $type = 'last';

                $time = strtotime('today');

            }elseif(preg_match('/001$/',$td['issue'])){ //第一期

                $type = 'first';

                $time = strtotime('today')+180;

            }

        }

        if($lottery_type == 8){

            $table = 'un_lhc';

            $td = $_REQUEST;

            $qihaoIds = @file_get_contents('jslhc_qihao.json'); //获取数据;

            if(preg_match('/288$/',$td['issue'])){ //最后一期

                $type = 'last';

                $time = strtotime('today');

            }elseif(preg_match('/001$/',$td['issue'])){

                $type = 'first';

                $time = strtotime('today')+300;

            }

        }

        if($lottery_type == 9){

            $table = 'un_bjpk10';

            $td = $_REQUEST;

            $qihaoIds = @file_get_contents('jssc_qihao.json'); //获取数据;

            if(preg_match('/480/',$td['qihao'])){ //最后一期

                $type = 'last';

                $time = date('Y-m-d H:i:s',strtotime('today'));

            }elseif(preg_match('/001$/',$td['qihao'])){

                $type = 'first';

                $time = date('Y-m-d H:i:s',(strtotime('today')+180));

            }

        }

        if($lottery_type == 10){

            $table = 'un_nn';

            $td = $_REQUEST;

            $qihaoIds = @file_get_contents('nn_qihao.json'); //获取数据;

            if(preg_match('/288$/',$td['issue'])){ //最后一期

                $type = 'last';

                $time = strtotime('today');

            }elseif(preg_match('/001$/',$td['issue'])){

                $type = 'first';

                $time = strtotime('today')+300;

            }

        }

        if($lottery_type == 11){

            $table = 'un_ssc';

            $td = $_REQUEST;

            $qihaoIds = @file_get_contents('ffc_qihao.json'); //获取数据;

            if(preg_match('/1440$/',$td['issue'])){ //最后一期

                $type = 'last';

                $time = strtotime('today');

            }elseif(preg_match('/0001$/',$td['issue'])){ //第一期

                $type = 'first';

                $time = strtotime('today')+60;

            }

        }

        if($lottery_type == 13){

            $table = 'un_sb';

            $td = $_REQUEST;

            $qihaoIds = @file_get_contents('sb_qihao.json'); //获取数据;

            if(preg_match('/288$/',$td['issue'])){ //最后一期

                $type = 'last';

                $time = strtotime('today');

            }elseif(preg_match('/001$/',$td['issue'])){ //第一期

                $type = 'first';

                $time = strtotime('today')+300;

            }

        }

        if($lottery_type == 14){

            $table = 'un_ffpk10';

            $td = $_REQUEST;

            $qihaoIds = @file_get_contents('ffpk10_qihao.json'); //获取数据;

            if(preg_match('/1440$/',$td['issue'])){ //最后一期

                $type = 'last';

                $time = strtotime('today');

            }elseif(preg_match('/0001$/',$td['issue'])){ //第一期

                $type = 'first';

                $time = strtotime('today')+60;

            }

        }

        $is = '';

        if(!empty($qihaoIds)){

            $jsonData = json_decode($qihaoIds,true);

            $list = json_decode($jsonData['txt'],true);

            if($lottery_type==9){

                $is = 'qihao';

            }else{

                $is = 'issue';

            }

            if(in_array($td[$is],array_column($list['list'],'issue'))){  //二维转一维

                foreach ($list['list'] as $v){

                    if($lottery_type==9){

                        if($v['issue'] == $td[$is]){

                            $td['kaijiangshijian'] = date('Y-m-d H:i:s',$v['date']);

                        }

                    }else{

                        if($v['issue'] == $td[$is]){

                            $td['lottery_time'] = $v['date'];

                        }

                    }

                }

            }else{


                if(!empty($type) && !empty($time)){

                    if($lottery_type == 9){

                        $td['kaijiangshijian'] = date('Y-m-d H:i:s',$time); //第一期和最后一期时间校验

                    }else{

                        $td['lottery_time'] = $time; //第一期和最后一期时间校验

                    }

                }

            }

        }


        if($_REQUEST['pre_open'] == 1){

            unset($td['is_call_back'],$td['call_back_uid'],$td['pre_open']);

            //查询当前封盘的期有多少预开奖数据

            $sql = "SELECT COUNT(*) FROM un_pre_open WHERE lottery_type={$td['lottery_type']} AND issue={$td['issue']}";

            //查询当前封盘的期是否有手动预开奖数据

            $sql2 = "SELECT id FROM un_pre_open WHERE lottery_type={$td['lottery_type']} AND issue={$td['issue']} AND user_id > 0";

            //查询当期预开奖结果已写入多少条数据

            $re = $this->db->result($sql);



            //查询当期是否有手动预开奖结果, 没有结果, 则返回false

            $re2 = $this->db->result($sql2);


            //限制只生成15条记录

            if($re < 15 && $re2 == false){

                //如果是急速赛车, 需要做表结构的兼容处理

                if ($lottery_type == '9') {

                    // $td['issue'] = $td['qihao'];

                    $td['lottery_time'] = strtotime($td['kaijiangshijian']);

                    $td['insert_time'] = strtotime($td['insert_time']);

                    $td['lottery_result'] = $td['kaijianghaoma'];

                    unset($td['qihao'], $td['kaijiangshijian'], $td['kaijianghaoma']);

                }

                //写入开奖数据

                $this->insertPreOpenData($td, $td['lottery_type']);

            }

            return false;

        }else{

            //计算开奖结果

            $result_data = $this->calculateSelfLotteryResult($td['lottery_type'], $td[$is]);

            //采用预开奖数据

            if ($result_data['flag'] == 'auto_pre_open') {

                $td['lottery_result'] = $result_data['data']['lottery_result'];

            }

            //整个彩种不采用预开奖数据的情况, 则走正常逻辑

            else if ($result_data['flag'] == 'stop_lottery_open_award') {

                if ($lottery_type == '9') {



                    $td['lottery_result'] = $_REQUEST['kaijianghaoma'];

                } else {

                    $td['lottery_result'] = $_REQUEST['lottery_result'];

                }

            } 

            //当期不采用预开奖数据的情况, 则中断逻辑, 需要手动补单

            else if ($result_data['flag'] == 'stop_one_issue_open_award') {

                return false;

            }

            if(!empty($res)){

                if($lottery_type==9){

                    if (preg_match('/001$/', $td['qihao'])) {

                        //临时解决方案, 急速赛车每天的第一期走正常逻辑

                        $td['kaijianghaoma'] = $_REQUEST['kaijianghaoma'];

                    } else {

                        //急速赛车开奖结果取 $td 变量的 lottery_result 键

                        $td['kaijianghaoma'] = $td['lottery_result'];

                    }

                    unset($td['lottery_result']);

                }

            }

            if($lottery_type == 10){

            	if(time() < strtotime('00:05:00') && strpos($td['issue'],'001') != false){

            		return false;

	            }

            }

            $update_res=$this->db->insert($table,$td);

            if($update_res){

                //开奖数据存redis 给算奖用的

                $redis = initCacheRedis();

                if($lottery_type==9){

                    $redis->hmset('lastIssueinfo:'.$lottery_type,array('issue'=>$_REQUEST['qihao'],'lottery_time'=>strtotime($_REQUEST['kaijiangshijian'])));

                }else{

                    $redis->hmset('lastIssueinfo:'.$lottery_type,array('issue'=>$_REQUEST['issue'],'lottery_time'=>$_REQUEST['lottery_time']));

                }

                switch ($lottery_type){

                    case 6:

                        $redis->expire('lastIssueinfo:'.$lottery_type,2);

                        break;

                    case 8:

                        $redis->expire('lastIssueinfo:'.$lottery_type,2);

                        break;

                    case 9:

                        $redis->expire('lastIssueinfo:'.$lottery_type,2);

                        break;

                    case 10:

                        $redis->expire('lastIssueinfo:'.$lottery_type,2);

                        break;

                    case 11:

                        $redis->expire('lastIssueinfo:'.$lottery_type,2);

                        break;

                    case 13:

                        $redis->expire('lastIssueinfo:'.$lottery_type,2);

                        break;

                    case 14:

                        $redis->expire('lastIssueinfo:'.$lottery_type,2);

                        break;

                }


                //shell派奖 ===============

                $redis->hsetnx('pc_lottery_type:'.$lottery_type,!empty($_REQUEST['issue'])?$_REQUEST['issue']:$_REQUEST['qihao'],1);  //存开奖数据

                deinitCacheRedis($redis);

            }

            if ($lottery_type == 9) {

                $qihao = $td['qihao'];

            } else {

                $qihao = $td['issue'];

            }

            D('workerman')->longDragon($lottery_type, $qihao);

        }

    }



    /**

     * 批量入库预开奖数据

     * 2018-03-27

     * 

     * 自开型彩种类别 lottery_type 值对应如下：

     *  三分彩      : 6 

     *  急速六合彩  : 8 

     *  急速赛车    : 9 

     *  百人牛牛    : 10

     */

    public function insertPreOpenData($pre_data, $lottery_type)

    {

        $workerman_model = D('workerman');

        $now_time = time();

        $lottery_result_arr = $pre_data['lottery_result_arr'];

        $table_name = 'un_pre_open';

        foreach ($lottery_result_arr as $k => $v) {

            $tmp_data = $pre_data;

            unset($tmp_data['lottery_result_arr']);

            //计算杀率

            $tmp_data['lottery_result'] = $v;

            $sha_lv_info = $workerman_model->theLotteryWithoutPaicai($pre_data['issue'], $tmp_data, $lottery_type);

            //组合入库数据

            $tmp_data['sha_lv'] = $sha_lv_info['sha_lv'];

            $insert_flag = $this->db->insert($table_name, $tmp_data);

        }

    }



    //查看redis数据

    public function getCacheVal(){

        return false;

        $key = $_REQUEST['k'];

        if(!empty($key)){

            $redis = initCacheRedis();

            if($key == 'kk'){

                $keys = $redis->keys('*');

                dump($keys);

                deinitCacheRedis($redis);

                return false;

            }

            $type = $redis->type($key);


            $val = '';

            switch ($type){

                case 1:

                    $val = $redis->get($key);

                    break;

                case 3:

                    $val = $redis->lRange($key,0,-1);

                    break;

                case 5:

                    $val = $redis->hgetall($key);

                    break;

                default :

                    $val = 'Data type not found';

            }

            deinitCacheRedis($redis);

            if(empty($val)){

                echo 'No data can be found, please check the key';

            }else{

                dump($val);

            }

        }else{

            echo 'Please input key';

        }

        return false;

    }



    /**

     * 结合系统配置, 计算自开型彩种的杀率, 获得预开奖结果

     * 2018-03-28

     */

    public function calculateSelfLotteryResult($lottery_type = 0, $issue = 0)

    {

        //从redis里取配置数据

        $redis = initCacheRedis();

        $json_data = $redis->hGet('Config:pre_open_setting','value');

        //关闭redis链接

        deinitCacheRedis($redis);

        $sha_lv_key = 'sha_lv_' . $lottery_type;

        $json_obj = json_decode($json_data, true);

        if ($json_obj[$sha_lv_key] == false) {

            return false;

        }

        $pre_open_model = D('preopen');

        //查询预开奖数据

        $sql = "SELECT id,lottery_result,sha_lv,lottery_time,user_id FROM un_pre_open WHERE lottery_type = {$lottery_type} AND issue = {$issue} ORDER BY user_id DESC";

        $pre_lottery_result = $this->db->getall($sql);

        //通过判断第一个值的user_id字段是否大于0, 来确定是否有手动预开奖记录

        if ($pre_lottery_result[0]['user_id'] > 0) {

            $final_result = [];

            $final_result['id'] = $pre_lottery_result[0]['id'];

            $final_result['lottery_time'] = $pre_lottery_result[0]['lottery_time'];

            $final_result['lottery_result'] = $pre_lottery_result[0]['lottery_result'];

            //标志该条记录为正式开奖记录

            $where = " id = {$final_result['id']} ";

            $pre_open_model->updateUseFlag($where, '1');

            //记录当时历史配置数据

            $history_insert_data = [

                'setting_type_then' => ($json_obj[$sha_lv_key]['setting_type'] == 'max_val') ? '1' : '2',

                'is_preopen_running_then' => ($json_obj[$sha_lv_key]['is_preopen_running'] == '0') ? '0' : '1',

                'percent_then' => $json_obj[$sha_lv_key]['percent'],

                'lottery_type' => $lottery_type,

                'issue' => $issue,

            ];

            if(isset($json_obj[$sha_lv_key]['cal_range'])) $history_insert_data['cal_range'] = $json_obj[$sha_lv_key]['cal_range'];

            $pre_open_model->addHistory($history_insert_data);

            return [

                'data' => $final_result,

                'flag' => 'auto_pre_open',

            ];

        }

        //当前彩种停用预开奖的逻辑

        if ($pre_open_model->checkLotteryStop($lottery_type) === false) {

            // //记录当时历史配置数据

            return [

                'data' => '',

                'flag' => 'stop_lottery_open_award',

            ];

        }

        //当期停用预开奖的逻辑

        if ($pre_open_model->checkIssueStop($lottery_type, $issue) === false) {

            //采取手动开奖

            return [

                'data' => '',

                'flag' => 'stop_one_issue_open_award',

            ];

        }

        if (! $pre_lottery_result) {

            return false;

        }

        //排序字段数组

        $sha_lv_arr = array_column($pre_lottery_result, 'sha_lv');


        ///////////////////////////////////////////////////////////////////////////////////////////////////杀率的接近模式

        //最终结果

        $final_result = [];

        //按照杀率值, 从小到大排序 ASC

        array_multisort($sha_lv_arr, SORT_ASC, $pre_lottery_result);

        $setting_percent = ($json_obj[$sha_lv_key]['percent_1']+$json_obj[$sha_lv_key]['percent_2'])>>1;

        foreach ($pre_lottery_result as $each_key => $each_result) {

            //如果有满足条件的开奖结果, 则取其数据, 并跳出循环

            if ($each_result['sha_lv'] >= $setting_percent) {

                //第一个就超过, 则取第一个的值

                if ($each_key == 0) {

                    $fit_key = 0;

                } else {

                    //计算配置值与数组前一个杀率值的算数差

                    $diff_val_a = $setting_percent - $pre_lottery_result[$each_key - 1]['sha_lv'];

                    //计算配置值与数组当前杀率值的算数差

                    $diff_val_b = $each_result['sha_lv'] - $setting_percent;

                    //比较两个差值, 取最接近的杀率值；若相等, 则取较大的杀率值

                    $fit_key = ($diff_val_b <= $diff_val_a) ? $each_key : ($each_key - 1);

                }

                $final_result['id'] = $pre_lottery_result[$fit_key]['id'];

                $final_result['lottery_time'] = $pre_lottery_result[$fit_key]['lottery_time'];

                $final_result['lottery_result'] = $pre_lottery_result[$fit_key]['lottery_result'];

                break;

            }

        }



        //如果5个预开奖结果都不满足, 则取结果中的最大值, 即最后一个数据（已升序排列）

        if (empty($final_result)) {

            $last_result = end($pre_lottery_result);

            $final_result['id'] = $last_result['id'];

            $final_result['lottery_time'] = $last_result['lottery_time'];

            $final_result['lottery_result'] = $last_result['lottery_result'];

        }



        //标志该条记录为正式开奖记录

        //记录当时历史配置数据

        $history_insert_data = [

            'setting_type_then' => ($json_obj[$sha_lv_key]['setting_type'] == 'max_val') ? '1' : '2',

            'is_preopen_running_then' => ($json_obj[$sha_lv_key]['is_preopen_running'] == '0') ? '0' : '1',

            'percent_then' => $json_obj[$sha_lv_key]['percent'],

            'lottery_type' => $lottery_type,

            'issue' => $issue,

        ];

        if(isset($json_obj[$sha_lv_key]['cal_range'])) $history_insert_data['cal_range'] = $json_obj[$sha_lv_key]['cal_range'];

        $pre_open_model->addHistory($history_insert_data);

        if($json_obj[$sha_lv_key]['cal_range']=='0') {

            //todo 取杀率最接近配置值的开奖结果

            //最终结果

            $final_result = [];

            //按照杀率值, 从小到大排序 ASC

            array_multisort($sha_lv_arr, SORT_ASC, $pre_lottery_result);

            $start_percent = $json_obj[$sha_lv_key]['percent_1'];

            $end_percent = $json_obj[$sha_lv_key]['percent_2'];

            $list = quickSort($pre_lottery_result, 'sha_lv', true);

            foreach ($list as $k => $i) {

                $percent = $i['sha_lv'];

                if ($percent > $start_percent && $percent < $end_percent) {

                    $final_result = $i;

                    break;

                }

            }

            if (empty($final_result)) {

                $left = [];

                $right = [];

                foreach ($list as $i) {

                    $percent = $i['sha_lv'];

                    if ($start_percent >= $percent && $percent > 0) {

                        $left = $i;

                        break;

                    }

                }

                $list = array_reverse($list);

                foreach ($list as  $i) {

                    $percent = $i['sha_lv'];

                    if ($percent >= $end_percent) {

                        $right = $i;

                        break;

                    }

                }

                if (!empty($left) && !empty($right)) {

                    if (($right['sha_lv'] - $end_percent) > ($start_percent - $left['sha_lv'])) {

                        $final_result = $left;

                    } else $final_result = $right;

                } elseif (!empty($left)) $final_result = $left;

                elseif(!empty($right)) $final_result = $right;

            }


            //如果5个预开奖结果都不满足, 则取结果中的最大值, 即最后一个数据（已升序排列）

            if (empty($final_result)) {

                $last_result = end($pre_lottery_result);

                $final_result['id'] = $last_result['id'];

                $final_result['lottery_time'] = $last_result['lottery_time'];

                $final_result['lottery_result'] = $last_result['lottery_result'];

            }

            unset($final_result['sha_lv']);

            unset($final_result['user_id']);

            //标志该条记录为正式开奖记录

            $where = " id = {$final_result['id']} ";

            $pre_open_model->updateUseFlag($where, '1');



            //记录当时历史配置数据

            $history_insert_data = [

                'setting_type_then' => ($json_obj[$sha_lv_key]['setting_type'] == 'max_val') ? '1' : '2',

                'is_preopen_running_then' => ($json_obj[$sha_lv_key]['is_preopen_running'] == '0') ? '0' : '1',

                'percent_then' => $json_obj[$sha_lv_key]['percent'],

                'lottery_type' => $lottery_type,

                'issue' => $issue,

            ];

            if(isset($json_obj[$sha_lv_key]['cal_range'])) $history_insert_data['cal_range'] = $json_obj[$sha_lv_key]['cal_range'];



            $pre_open_model->addHistory($history_insert_data);

            return [

                'data' => $final_result,

                'flag' => 'auto_pre_open',

            ];

        }



//         setting_type 的值分为 max_val 和 near_val 两种, max_val 为取最大值, near_val 为取接近值

//        最大模式

        if ($json_obj[$sha_lv_key]['setting_type'] == 'max_val') {

            //按照杀率值, 从大到小排序 DESC

            array_multisort($sha_lv_arr, SORT_DESC, $pre_lottery_result);

            //最终结果

            $final_result = [];

            $final_result['id'] = $pre_lottery_result[0]['id'];

            $final_result['lottery_time'] = $pre_lottery_result[0]['lottery_time'];

            $final_result['lottery_result'] = $pre_lottery_result[0]['lottery_result'];



            //标志该条记录为正式开奖记录

            $where = " id = {$final_result['id']} ";

            $pre_open_model->updateUseFlag($where, '1');



            //记录当时历史配置数据

            $history_insert_data = [

                'setting_type_then' => ($json_obj[$sha_lv_key]['setting_type'] == 'max_val') ? '1' : '2',

                'is_preopen_running_then' => ($json_obj[$sha_lv_key]['is_preopen_running'] == '0') ? '0' : '1',

                'percent_then' => $json_obj[$sha_lv_key]['percent'],

                'lottery_type' => $lottery_type,

                'issue' => $issue,

            ];

            if(isset($json_obj[$sha_lv_key]['cal_range'])) $history_insert_data['cal_range'] = $json_obj[$sha_lv_key]['cal_range'];

            $pre_open_model->addHistory($history_insert_data);

            return [

                'data' => $final_result,

                'flag' => 'auto_pre_open',

            ];

        }

        //接近模式

        elseif ($json_obj[$sha_lv_key]['setting_type'] == 'near_val') {

            //todo 取杀率最接近配置值的开奖结果

            //最终结果

            $final_result = [];

            //按照杀率值, 从小到大排序 ASC

            array_multisort($sha_lv_arr, SORT_ASC, $pre_lottery_result);

            $setting_percent = $json_obj[$sha_lv_key]['percent'];

            $list = quickSort($pre_lottery_result, 'sha_lv', true);

            $left = [];

            $right = [];

            foreach ($list as $i) {

                $percent = $i['sha_lv'];

                if ($setting_percent >= $percent && $percent > 0) {

                    $left = $i;

                    break;

                }

            }



            $list = array_reverse($list);

            foreach ($list as $i) {

                $percent = $i['sha_lv'];

                if ($percent >= $setting_percent) {

                    $right = $i;

                    break;

                }

            }



            if (!empty($left) && !empty($right)) {

                if (($right['sha_lv'] - $setting_percent) > ($setting_percent - $left['sha_lv'])) {

                    $final_result = $left;

                } else $final_result = $right;

            } elseif (!empty($left)) $final_result = $left;

            elseif(!empty($right)) $final_result = $right;





            //如果5个预开奖结果都不满足, 则取结果中的最大值, 即最后一个数据（已升序排列）

            if (empty($final_result)) {

                $last_result = end($pre_lottery_result);

                $final_result['id'] = $last_result['id'];

                $final_result['lottery_time'] = $last_result['lottery_time'];

                $final_result['lottery_result'] = $last_result['lottery_result'];

            }

            unset($final_result['sha_lv']);

            unset($final_result['user_id']);

            //标志该条记录为正式开奖记录

            $where = " id = {$final_result['id']} ";

            $pre_open_model->updateUseFlag($where, '1');



            //记录当时历史配置数据

            $history_insert_data = [

                'setting_type_then' => ($json_obj[$sha_lv_key]['setting_type'] == 'max_val') ? '1' : '2',

                'is_preopen_running_then' => ($json_obj[$sha_lv_key]['is_preopen_running'] == '0') ? '0' : '1',

                'percent_then' => $json_obj[$sha_lv_key]['percent'],

                'lottery_type' => $lottery_type,

                'issue' => $issue,

            ];

            if(isset($json_obj[$sha_lv_key]['cal_range'])) $history_insert_data['cal_range'] = $json_obj[$sha_lv_key]['cal_range'];

            $pre_open_model->addHistory($history_insert_data);

            return [

                'data' => $final_result,

                'flag' => 'auto_pre_open',

            ];

        }

        else {

            return false;

        }



    }



    /**

     *

     * 双活接口

     * 接收后台过来的数据,要确保这机器上有开启workerman

     * 主要是开奖信息

     *

     */

    function get_admin_data(){


        $input_data = file_get_contents('php://input',true);

        $data = json_decode($input_data,true);

        $key='DCCdPke3boPWr2Wp2Qb4yWF9MuiYq@9f';

        $sign=md5($key.$data['timestamp']);

        if($sign==$data['sign']){

            //连接workerman

            $Gateway = O('Gateway');

            $Gateway::$registerAddress = C('Gateway');

            $type = $data['type']; //要处理的类型 开奖数据

            switch ($type){

                case 'lottery_stop_sale': //调试

                    Gateway::sendToGroup(1,$data['json']);

                    break;

                case 'betting_group': //投注信息

                    Gateway::sendToGroup($data['id'],$data['json']);

                    break;

                case 'open_lottery': //开奖数据

                    if($data['isOpen'] == 1){

                        $redis = initCacheRedis();

                    ////接收参数

                        $co_str = 'open_lottery_by_rooms:'.$data['ids'];

                        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去

                            $redis->expire($co_str,15); //设置它的超时

                            foreach (explode('_',$data['ids']) as $rv){

                                Gateway::sendToGroup($rv,encode(decode($data['jsonIssueInfo'])[$rv])); //每个房间有单独的配置

                                usleep(200000); //0.2秒

                                Gateway::sendToGroup($rv,$data['json']);

                            }

                            deinitCacheRedis($redis);

                        }else{

                            deinitCacheRedis($redis);

                            return false;

                        }

                    }

                    break;

                case 'update_account_by_uid': //派完奖后给更新客人的帐户余额

                    Gateway::sendToUid($data['id'],$data['json']);

                    break;

                case 'double_cancel_order': //这个接口要单独处理, 因为这里传的是UID到前台要用连接id来推信息给用户

                     //撤单信息只能在投注房间显示

                    $roomid=$data['roomid'];

                    $cidArr= Gateway::getClientIdByUid($data['id']);  //先获取这个人的ClientID

                    if(!empty($cidArr)){

                        foreach ($cidArr as $cv){

                            $uinfo = Gateway::getSession($cv);

                            $wmRoomID = $uinfo['roomid'];

                            if($wmRoomID  == $roomid){

                                Gateway::sendToClient($cv, $data['json']);

                                Gateway::sendToClient($cv, $data['json2']);

                            }

                        }

                    }

                    break;

                case 'double_user_award_info':

                    $cidArr= Gateway::getClientIdByUid($data['id']);  //先获取这个人的ClientID

                    if(!empty($cidArr)){

                        foreach ($cidArr as $cv){

                            $uinfo = Gateway::getSession($cv);

                            $wmRoomID = $uinfo['roomid'];

                            $datav = decode($data['data']);

                            if(in_array($wmRoomID,array_keys($datav)) && !empty($datav[$wmRoomID])){

                                $tmp_award_money = convert($datav[$wmRoomID]);

                                //用户余额信息

                                $sql = "select money from un_account where user_id={$data['id']}";

                                $use_money = $this->db->result($sql);

                                $message=array(

                                    'commandid'=>3026,

                                    'content'=>'恭喜您中奖'. $tmp_award_money .'coins.',

                                    'money' =>  $tmp_award_money ,

                                    'use_money' => $use_money,

                                );

                                Gateway::sendToClient($cv, encode($message));

                            }

                        }

                    }

                    break;

                case 'barrage':

                    $issue = $data['issue'];

                    $lottery_type = $data['lottery_type'];

                    $Gateway = O('Gateway');

                    $Gateway::$registerAddress = C('Gateway');

                    $sql = "select way,award,room_no,lottery_type,user_id from #@_orders where issue = {$issue} and lottery_type = {$lottery_type} and award_state = 2";

                    $list = $this->db->getall($sql);

                    $data = [];

                    $redis = initCacheRedis();

                    foreach ($list as $value) {

                        $data[$value['user_id']."_".$value['room_no']."_".$value['way']] = [

                            "money" => 0,

                            'way' => $value['way'],

                            'lottery_type' => $value['lottery_type'],

                            'user_id' => $value['user_id'],

                        ];

                    }

                    foreach ($data as $key => $val) {

                        foreach ($list as $value) {

                            if ($value['user_id']."_".$value['room_no']."_".$value['way'] == $key) {

                                $data[$key]['money'] += $value['award'];

                            }

                        }

                    }

                    $barrage_conf = json_decode($redis->hGet("Config:"."barrage_config","value"),true);

                    foreach ($barrage_conf as $config) {

                        foreach ($data as $info) {

                            if ($info['money'] >= $config['start_money'] && $info['money'] <= $config['end_money']) {

                                $user_info = $this->db->getone("select nickname,avatar from #@_user where id = {$info['user_id']}");

                                $tmp = [

                                    'win_time' => time(),

                                    'event_num' => $issue,

                                    'user_id' => $info['user_id'],

                                    'win_money' => $info['money'],

                                    'alert_type' => $config['name'],

                                    'lottery_type' => $info['lottery_type'],

                                    'nickname' => $user_info['nickname']

                                ];

                                $rows = $this->db->insert("#@_barrage_win", $tmp);

                                if ($rows > 0) {

                                    $msg_data['lottery_name'] = $redis->hGet("LotteryType:{$info['lottery_type']}",'name');

                                    $msg_data['nickname'] = D('workerman')->getNickname($user_info['nickname']);

                                    $msg_data['avatar'] = $user_info['avatar'];

                                    $msg_data['way'] = $info['way'];

                                    $msg_data['money'] = $info['money'];

                                    $msg_data['name'] = $config['name'];

                                    $send_str = encode(['commandid' => 3023, 'data' => $msg_data]);

                                    Gateway::sendToAll($send_str);

                                    sleep(5);

                                }

                            }

                        }

                    }

                    deinitCacheRedis($redis);





                    //再博一次功能 distinct

                    $sql = "select way,money,award_state,lottery_type,room_no,user_id from #@_orders where award_state !=0 and state = 0 and issue = {$issue} and lottery_type = {$lottery_type} and chase_number = ''";

                    $order_list = $this->db->getall($sql);

                    $list = [];

                    if (!empty($order_list)) {

                        foreach ($order_list as $val) {

                            $list[$val['user_id']][$val['room_no']][] = $val;

                        }

                    }

                    if (!empty($list)) {

                        foreach ($list as $user_id => $order_info) {

                            $cidArr= Gateway::getClientIdByUid($user_id);  //先获取这个人的ClientID

                            if(!empty($cidArr)){

                                foreach ($cidArr as $cv){

                                    $uinfo = Gateway::getSession($cv);

                                    $wmRoomID = $uinfo['roomid'];

                                    foreach ($order_info as $room_id => $info) {

                                        if (count($info) == 1) {

                                            if ($info[0]['award_state'] == 1 && $wmRoomID == $room_id) {

                                                $send_message['way'] = $info[0]['way'];

                                                $send_message['money'] = $info[0]['money'];

                                                $send_message['commandid'] = 3025;

                                                if (in_array($info[0]['lottery_type'],['7','8'])) {

                                                    $wArr= explode('_',$info[0]['way']);

                                                    $len = count(explode(',',$wArr[1]));

                                                    if($len > 1){

                                                        $preArr = array(

                                                            '三中二'=>3,  '三全中'=>3,  '二全中'=>2,  '二中特'=>2,  '特串'=>2,

                                                            '二肖连中'=>2,  '三肖连中'=>3,  '四肖连中'=>4,  '二肖连不中'=>2,  '三肖连不中'=>3,  '四肖连不中'=>4,

                                                            '五不中'=>5,  '六不中'=>6,  '七不中'=>7,  '八不中'=>8,  '九不中'=>9,  '十不中'=>10,

                                                            '二尾连中'=>2,  '三尾连中'=>3,  '四尾连中'=>4,  '二尾连不中'=>2,  '三尾连不中'=>3,  '四尾连不中'=>4,

                                                        );

                                                        $send_message['zushu'] = $this->zushu($len,$preArr[$wArr[0]]);

                                                    } else {

                                                        $send_message['zushu'] = 1;

                                                    }

                                                } else {

                                                    $send_message['zushu'] = 1;

                                                }

                                                $send_message['single_money'] = $send_message['money'] / $send_message['zushu'];

                                                Gateway::sendToClient($cv, encode($send_message));

                                            }

                                        }

                                    }

                                }

                            }

                        }

                    }

                    break;

                case 'betting':

                    Gateway::sendToUid($data['id'],$data['json']);

                    break;

                case 'clear_user_cash_passwd': //清除玩家密码次数限制

                    //初始化redis

                    $redis = initCacheRedis();

                    $redis->del("user_cash:".$data['id']);

                    //关闭redis链接

                    deinitCacheRedis($redis);

                    break;

                case 'update_odds': //根据房间更新赔率

                    Gateway::sendToGroup($data['id'],$data['json']);

                    break;

                case 'update_against': //接收世界杯推送数据接口

                    Gateway::sendToGroup($data['id'],$data['json']);

                    break;

                case 'update_current_beting': //通知更新投注

                    Gateway::sendToGroup($data['id'],$data['json']);

                    break;



            }

        }

    }





    /**

     * 接收二次开奖数据

     * 主要是做订单回滚

     */

    public function check_lottery_data(){

        $json = file_get_contents('php://input', 'r');

        $ojson=$json;

        $jsonData = json_decode($json,true);

        //实际的开奖数据

        $key = "MR3mi3o5QceTpn3KNnsvo5iwNCzszro1";

        $data=$jsonData['data'];

        $json_final=$jsonData['data'];

        //兼容新老

        if(!is_array($data)){

            $data=json_decode($data,1);

            $sign = md5($jsonData['data'].$jsonData['noiStr'].$jsonData['time'].$key);

        }else{

            $sign = md5(json_encode($jsonData['data'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).$jsonData['noiStr'].$jsonData['time'].$key);

        }

        $lottery_name=$data['name'];

        $lt=0;

        $file='';

        $issue=$data['qihao'];

        if($sign == $jsonData['sign']){

            switch ($lottery_name) {

                case '幸运28': //幸运28,这个彩种比较特殊, 所以用了字符串

                    $file='xy28.txt';

                    $lt=1;

                    $sql = "update un_open_award set is_call_back=1 WHERE lottery_type=1 and issue='{$issue}'";

                    break;

                case '北京PK10': //北京pk10

                    $file='bjpk10.txt';

                    $lt=2;

                    $sql = "update un_bjpk10 set is_call_back=1 WHERE lottery_type=2 and qihao='{$issue}'";

                    break;

                case '加拿大28': //加拿大28

                    $file='jnd28.txt';

                    $lt=3;

                    $sql = "update un_open_award set is_call_back=1 WHERE lottery_type=3 and issue='{$issue}'";

                    break;

                case '幸运飞艇': //幸运飞艇

                    $file='xyft.txt';

                    $lt=4;

                    $sql = "update un_xyft set is_call_back=1 WHERE qihao='{$issue}'";

                    break;

                case '重庆时时彩': //幸运28,这个彩种比较特殊, 所以用了字符串

                    $file='cqssc.txt';

                    $lt=5;

                    $sql = "update un_ssc set is_call_back=1 WHERE lottery_type=5 and issue='{$issue}'";

                    break;

                case '六合彩': //幸运28,这个彩种比较特殊, 所以用了字符串

                    $file='lhc.txt';

                    $lt=7;

                    $sql = "update un_lhc set is_call_back=1 WHERE lottery_type=7 and issue='{$issue}'";

                    break;

            }


            if($file!='' && $lt!=0 && $data['qihao']!='' && ($data['haoma'] || $data['3x9'] || $data['28'])){

                echo json_encode(array('status'=>200,'msg'=>'success'));

            }else{

                echo json_encode(array('status'=>500,'msg'=>'Abnormal data'),JSON_UNESCAPED_UNICODE);

                return false;

            }

            $re = $this->db->query($sql);

            if($re){

                signa(C('app_home').'?m=api&c=order&a=ordersCallBack',array('lottery_type'=>$lt,'issue'=>$issue));

            }

        }else{

            $json = json_encode(array('err'=>1,'code'=>500,'msg'=>'Signature failed'),JSON_UNESCAPED_UNICODE); //签名失败

            echo $json;

            return false;

        }

    }



    //撤单接口

    public function cancal_orders(){

        //验证签名

        $res = verificationSignature();

        if($res['status'] !== "success"){

            @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 签名验证失败(撤单): '.json_encode($res,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);

            if($res['code'] == 3){

                ErrorCode::errorResponse(999998,"Request timed out, please make sure your mobile phone system time is: Beijing (China) time, refresh and try again!");

            }

            ErrorCode::errorResponse(999999,"Signing failed, please make sure the app is the latest version and try again!");

        }

        $query_time = time(); //防止撤后期的订单

        //接收参数

        $roomid = (int)$_REQUEST['room_id'];

        $api_id = (int)$_REQUEST['api_id'];

        $lotteryType = (int)$_REQUEST['lottery_type'];

        $uid = (int)$_REQUEST['uid'];

        $is_admin = (int)$_REQUEST['is_admin'];

        $is_supper = (int)$_REQUEST['is_supper'];

        $order_no = $_REQUEST['order_no']?:'';

        //实例化Gateway

        $Gateway = O('Gateway');

        $Gateway::$registerAddress = C('Gateway');

        if($lotteryType==12){

            if(empty($is_admin)){ //非管理人员 不能撤单

                $message = array('commandid' => 3005, 'content' => "Football Lottery can not cancel the order");

                Gateway::sendToGroup($roomid,encode($message));

                return false;

            }

        }

        //判断参数

        $paramFlag = false;

        if(empty($roomid)) $paramFlag = true;

        if(empty($lotteryType)) $paramFlag = true;

        if(empty($uid)) $paramFlag = true;

        $roomInfo = D('workerman')->getRedisHashValues("allroom:".$roomid,array('lottery_type'));

        //判断参数
        if($paramFlag){

            return;

        }

        //获取期号

        $time = time();

        $info =  D('workerman')->getQihao($lotteryType,$time,$roomid);

        $issue = $_REQUEST['issue'];

        //防止刷单

        $redis = initCacheRedis();

        $co_str = implode(':',$_REQUEST);

        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去

            $redis->expire($co_str,60); //设置它的超时

            deinitCacheRedis($redis);

        }else{

            deinitCacheRedis($redis);

            return false;

        }

        if ($is_admin==1) { //后台客服提交

            if($is_supper!=1){ //非超级管理员

                if ($issue > $info['issue']) {

                    //下面返回的信息给后台请求接口用的

                    echo json_encode(['err'=>1,'msg'=>'The order can not be cancelled before the betting time'],JSON_UNESCAPED_UNICODE);

                    return;

                }else if ($issue == $info['issue'] && $info['sealingTim'] >= $info['time'] ) { //如果当前已封盘

                    //下面返回的信息给后台请求接口用的

                    echo json_encode(['err'=>1,'msg'=>'The order has been closed and can not be rolled back.'],JSON_UNESCAPED_UNICODE);

                    return;

                }else if ($issue < $info['issue']) {

                    //下面返回的信息给后台请求接口用的

                    echo json_encode(['err'=>1,'msg'=>'Lottery result is drawn and the order can not be rolled back.'],JSON_UNESCAPED_UNICODE);

                    return;

                }

                //只能撤当前期

                $where="user_id={$uid} AND room_no={$roomid} AND issue='{$info['issue']}' AND state=0";

            }else{ //超级管理员可以撤消所有单

                $where="user_id={$uid} AND room_no={$roomid} AND state=0";

            }

        }else{ //前台客人提出的申请

            //可以撤未来期

            $where="user_id={$uid} AND room_no={$roomid} AND issue>='{$info['issue']}' AND state=0";

        }



        if($is_supper!=1) { //非超级管理员

            //如果未获取到期号

            if ($info['issue'] == 0 || $info['stopOrSell'] == 2) {

                return;

            }

        }





        $len = strlen($order_no);

        //如果处于封盘

        if($info['sealingTim'] >= $info['time'] ){

            if ($len==6) { //前台客人自己提出撤单,只能撤未来单

                //只撤消未来单, 不撤消当前单

                $where = "user_id={$uid} AND room_no={$roomid} AND issue>'{$info['issue']}' AND state=0";

            }else{ //封盘期不能撤消当前期

                if($is_supper!=1) { //非超级管理员

                    echo json_encode(['err' => 1, 'msg' => 'The lottery is currently closed and bets can not be cancelled.'], JSON_UNESCAPED_UNICODE);

                    $message = array('commandid' => 3005, 'content' => "The market is currently closed and bets can not be cancelled.");

                    //撤单信息只能在投注房间显示

                    $cidArr= Gateway::getClientIdByUid($uid);  //先获取这个人的ClientID

                    if(!empty($cidArr)){ //用户在线时

                        foreach ($cidArr as $cv){

                            $uinfo = Gateway::getSession($cv);

                            $wmRoomID = $uinfo['roomid'];

                            if($wmRoomID  == $roomid){

                                $data['type']="double_cancel_order";

                                $data['id']=$cv;

                                $data['json']=encode($message);

                                send_home_data($data);

                            }

                        }

                    }

                    return false;

                }

            }

        }

        if($len>0){

            if($len==6){ //追号撤单

                $where.=" AND chase_number = '{$order_no}'";

            }else{ //当前期正常单独撤单

                $where.=" AND order_no = '{$order_no}'";

            }

        }else{

            $where.=" AND issue='{$info['issue']}' AND chase_number=''"; //当前期的所有投注

        }

        $where .= " AND addtime<={$query_time}"; //防止撤后面投的订单

        $sql = "select id,money,order_no,issue,way,room_no from un_orders WHERE  {$where}";

        $list = $this->db->getall($sql);

        if (!empty($list)){

            $money=0;

            $listArr=[]; //待撤单的订单

            $arrOrder = []; //待撤单的订单号

            $ids_tmp = array(); //订单主ID集

            foreach ($list as $v){

                $money = bcadd($money,$v['money'],2);

                $listArr[]=$v['issue'];

                $ids_tmp[] = $v['id']; //收集订单主ID

                $arrOrder[] = $v['order_no']; //收集待撤销的订单号

            }

            $order_ids = implode(',',$ids_tmp);

            if (!empty($listArr)) {

                sort($listArr);

            }

            //开启事务

            O('model')->db->query('BEGIN');

            try {

                $nowtime=time();

                $sqlu="update un_orders set state=1 WHERE id in ({$order_ids})";

                $ret = $this->db->query($sqlu);

                if (empty($ret)){

                    throw new Exception('Update failed!1');

                }

                //查余额

                if(!empty(C('db_port'))){ //使用mycat时 查主库数据

                    $sqla="/*#mycat:db_type=master*/ select money from un_account WHERE user_id={$uid} LIMIT 1 for update";

                }else{

                    $sqla="select money from un_account WHERE user_id={$uid} LIMIT 1 for update";

                }

                $re = $this->db->getone($sqla);

                //查注册类型

                $sqlua="select reg_type from un_user where id=".$uid;

                $re_reg=$this->db->getone($sqlua);

                $ye = bcadd($money,$re['money'],2);

                $order_nos = "CD" . date("YmdHis") . rand(100, 999);

                $message['commandid'] =3015;

                if($is_supper==1) { //超级管理员,要查出期号

                    $issue_sql = "SELECT issue FROM un_orders WHERE user_id={$uid}  AND order_no='{$order_no}'";

                    $issue_re = $this->db->getone($issue_sql);

                    $info['issue'] = $issue_re['issue'];

                }

                if($len>0){

                    $remark="User cancel No ".$info['issue']." Serial number:".$v['order_no']." bet";

                    if ($len==6) {

                        $message['commandid'] =3022;

                        $message['content']="【Chase number】No[".$listArr[0]."]-No[".end($listArr)."] ,bet[{$list[0]['way']}],balance[{$this->convert($money)}] has been revoked.";

                    }else{

                        $message['zushu'] = 1;

                        $message['content']="No[{$info['issue']}] bet[{$list[0]['way']}],balance[{$this->convert($list[0]['money'])}] has been revoked. Single number[{$v['order_no']}]";

                        //六合彩多注多处理

                        $wArr= explode('_',$v['way']);

                        $len = count(explode(',',$wArr[1]));

                        if($len > 1){

                            $preArr = array(

                                '三中二'=>3,

                                '三全中'=>3,

                                '二全中'=>2,

                                '二中特'=>2,

                                '特串'=>2,

                                '二肖连中'=>2,

                                '三肖连中'=>3,

                                '四肖连中'=>4,

                                '二肖连不中'=>2,

                                '三肖连不中'=>3,

                                '四肖连不中'=>4,

                                '五不中'=>5,

                                '六不中'=>6,

                                '七不中'=>7,

                                '八不中'=>8,

                                '九不中'=>9,

                                '十不中'=>10,

                                '二尾连中'=>2,

                                '三尾连中'=>3,

                                '四尾连中'=>4,

                                '二尾连不中'=>2,

                                '三尾连不中'=>3,

                                '四尾连不中'=>4,

                            );

                            $zu = $this->zushu($len,$preArr[$wArr[0]]);

                            $message['zushu'] = $zu;

                        }

                    }

                    $message['order_no'] = $order_no;

                }else{



                    $remark="User cancels all bets of No ".$info['issue'];

                    $message['content']="All bets on No[{$info['issue']}] have been cancelled, balance [{$this->convert($money)}]";

                    $message['order_list'] = $arrOrder;

                }

                if ($is_admin==1) {

                    $remark.=' Operator::'.$_REQUEST['admin_name'];

                }

                $log_data = array(

                    'order_num' => $order_nos,

                    'user_id' => $uid,

                    'type' => 14,

                    'addtime' => $nowtime,

                    'money' => $money,

                    'use_money' => $ye,

                    'remark'=>$remark,

                    'reg_type' => $re_reg['reg_type'],

                );

                //插入资金交易明细

                $inid = $this->db->insert('un_account_log',$log_data);

                if (empty($inid)) throw new Exception('Update failed!2');

                //更新帐户余额表

                $sql = "update un_account set money=money+{$money} WHERE user_id={$uid}";

                $ret = $this->db->query($sql);

                if (empty($ret)) throw new Exception('Update failed!3');

                //提交事务

                O('model')->db->query('COMMIT');

                $message2 = array('commandid' => 3010, 'money' => $this->convert($ye));

                //发送信息给前台, 这里传的是UID,前台对这个UID单独处理

                $data['type']="double_cancel_order";

                $data['id']=$uid;

                $data['issue'] = $issue;

                $data['roomid'] = $roomid;

                $message['money'] = $money;

                $data['json']=encode($message);

                $data['json2']=encode($message2);

                send_home_data($data);

                //下面返回的信息给后台请求接口用的

                echo json_encode(['err'=>0,'msg'=>'Successful cancellation'],JSON_UNESCAPED_UNICODE);

                //删除撤单间隔标识

                $redis = initCacheRedis();

                $redis->del($co_str);

                deinitCacheRedis($redis);

                return ;

            }catch (Exception $e){

                //回滚事务

                O('model')->db->query('ROLLBACK');

                $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "System error, please contact customer service.");

                $data['type']="double_cancel_order";

                $data['api']="sendToUid";

                $data['id']=$uid;

                $data['json']=encode($message);

                send_home_data($data);

                //下面返回的信息给后台请求接口用的

                json_encode(['err'=>1,'msg'=>'Cancellation failed'],JSON_UNESCAPED_UNICODE);

                return ;

            }

        }

    }



    //机器人投注接口

    public function robot(){

        $list = D('workerman')->get_robot_data();

        if(!empty($list)) {

            foreach ($list as $val)

            {

                $msgData['userid'] = $val['user_id'];

                $msgData['lottery_type'] = $val['lottery_type'];

                $msgData['username'] = $val['username'];

                $msgData['roomid'] = $val['room_id'];

                $msgData['way'] = [$val['way']];

                $msgData['money'] = [$val['bet_money']];

                $msgData['avatar'] = $val['avatar'];

                $msgData['nickname'] = $val['nickname'];

                $msgData['conf_id'] = $val['conf_id'];

                $this->person($msgData);

            }

        }

    }



    /**

     * @param $msgData 投注数据

     *

     */

    public function person($msgData){

        $lotteryType = $msgData['lottery_type'];

        $uid = $msgData['userid'];

        $roomid = $msgData['roomid'];

        $way = $msgData['way'];

        $money = $msgData['money'];

        //判断房间彩种

        $roomInfo = D('workerman')->getRedisHashValues("allroom:".$roomid,array('lottery_type','special_way'));

        if($roomInfo['lottery_type'] != $lotteryType) $paramFlag = true;

        //特殊玩法判断

        $whereSql = "";

        $special_way = json_decode($roomInfo['special_way'],true);

        if($special_way['status'] != '1'){

            $whereSql = " AND type <> 3";

        }

        //实例化Gateway

        $Gateway = O('Gateway');

        $Gateway::$registerAddress = C('Gateway');

        //查询用户信息(昵称,头像,注册类型)

        $sql = "SELECT username,nickname,avatar,group_id,reg_type FROM `un_user` WHERE `id` = '{$uid}'";

        $res = $this->db->getone($sql);

        if(empty($res) || $paramFlag){

            return;

        };

        $username = empty($res['nickname'])?$res['username']:$res['nickname'];

        //控制昵称显示

        $username = D('workerman')->getNickname($username);

        $avatar = empty($res['avatar'])?'/up_files/room/avatar.png':$res['avatar'];

        $groupid = $res['group_id'];


        //查询用户荣誉等级

        $honor = get_level_honor_robot($uid);

        //获取期号

        $time = time();

        $info = $res = D('workerman')->getQihao($lotteryType,$time,$roomid);

        //返回信息

        $message = array(

            'commandid' => 3004,

            'nickname' => '',

            'content' => $info['msg'],

            'avatar'=>'',

            'status'=>'1',

        );

        //如果未获取到期号

        if($info['issue'] == 0){

            return false;

        }

        //判断上一期是否开出来

        $issue = $info['issue']-1;

        switch ($lotteryType){

            case 1:

                $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$lotteryType} AND issue = '{$issue}' AND state in (0,1)";

                break;

            case 2:

                $sql = "SELECT qihao AS issue, status FROM `un_bjpk10` WHERE `qihao` = '{$issue}' AND  lottery_type={$lotteryType} AND status in (0,1)";

                break;

            case 3:

                $sql = "SELECT issue, state AS status FROM `un_open_award` WHERE `lottery_type` = {$lotteryType} AND issue = '{$issue}' AND state in (0,1)";

                break;

            case 4:

                if(strpos($info['issue'],'001') !== false)

                {

                    $issue = date('Ymd',strtotime('-1 day')).'180';

                }

                $sql = "SELECT qihao AS issue, status FROM `un_xyft` WHERE `qihao` = '{$issue}' AND status in (0,1)";

                break;

            case 5:

                if(strpos($info['issue'],'001') !== false)

                {

                    $issue = date('Ymd',strtotime('-1 day')).'120';

                }

                $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$issue}' AND lottery_type={$lotteryType} AND status in (0,1)";

                break;

            case 6:

                if(preg_match('/000|1$/',$info['issue']))

                {

                    $issue = date('Ymd',strtotime('-1 day')).'01480';

                }

                $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$issue}' AND lottery_type={$lotteryType} AND status in (0,1)";

                break;

            case 7:

                $sql = "SELECT issue, status FROM `un_lhc` WHERE `issue` = '{$issue}' AND lottery_type={$lotteryType} AND status in (0,1)";

                break;

            case 8:

                if(strpos($info['issue'],'000') !== false || strpos($info['issue'],'001') !== false)

                {

                    $issue = date('Ymd',strtotime('-1 day')).'03288';

                }

                $sql = "SELECT issue, status FROM `un_lhc` WHERE `issue` = '{$issue}' AND lottery_type={$lotteryType} AND status in (0,1)";

                break;

            case 9:

                if(strpos($info['issue'],'000') !== false || strpos($info['issue'],'001') !== false)

                {

                    $issue = date('Ymd',strtotime('-1 day')).'02480';

                }

                $sql = "SELECT qihao AS issue, status FROM `un_bjpk10` WHERE `qihao` = '{$issue}' AND  lottery_type={$lotteryType} AND status in (0,1)";

                break;

            case 10:

                if(strpos($info['issue'],'000') !== false || strpos($info['issue'],'001') !== false)

                {

                    $issue = date('Ymd',strtotime('-1 day')).'04288';

                }

                $sql = "SELECT issue, status FROM `un_nn` WHERE `issue` = '{$issue}' AND lottery_type={$lotteryType} AND status in (0,1)";

                break;

            case 11:

                if(strpos($info['issue'],'000') !== false || strpos($info['issue'],'001') !== false)

                {

                    $issue = date('Ymd',strtotime('-1 day')).'051440';

                }

                $sql = "SELECT issue, status FROM `un_ssc` WHERE `issue` = '{$issue}' AND lottery_type={$lotteryType} AND status in (0,1)";

                break;

            case 13:

                if(strpos($info['issue'],'000') !== false || strpos($info['issue'],'001') !== false)

                {

                    $issue = date('Ymd',strtotime('-1 day')).'06288';

                }

                $sql = "SELECT issue, status FROM `un_sb` WHERE `issue` = '{$issue}' AND lottery_type={$lotteryType} AND status in (0,1)";

                break;

            case 14:

                if(strpos($info['issue'],'000') !== false || strpos($info['issue'],'001') !== false)

                {

                    $issue = date('Ymd',strtotime('-1 day')).'071440';

                }

                $sql = "SELECT issue, status FROM `un_ffpk10` WHERE `issue` = '{$issue}' AND lottery_type={$lotteryType} AND status in (0,1)";

                break;

        }

        $res = $this->db->getone($sql);

        //如果处于封盘

        if($info['sealingTim'] >= ($info['date']- $time)){

            $message['content'] = 'It is not currently within the betting time and can not be placed.';

            $message['is_popup_msg'] = '1';

            return;

        }

        //判断投注的玩法是否是正确的

        $sql = "select way from un_odds where lottery_type='{$lotteryType}' and room='{$roomid}'{$whereSql}";

        $res = $this->db->getall($sql);

        if(empty($res)){

            $message['content'] = 'No related method for this lottery, Stop betting.';

            $message['is_popup_msg'] = '1';

            return;

        }


        $allway = array();

        $preArr = array(

            '三中二' => 3,

            '三全中' => 3,

            '二全中' => 2,

            '二中特' => 2,

            '特串' => 2,

            '二肖连中' => 2,

            '三肖连中' => 3,

            '四肖连中' => 4,

            '二肖连不中' => 2,

            '三肖连不中' => 3,

            '四肖连不中' => 4,

            '五不中' => 5,

            '六不中' => 6,

            '七不中' => 7,

            '八不中' => 8,

            '九不中' => 9,

            '十不中' => 10,

            '二尾连中' => 2,

            '三尾连中' => 3,

            '四尾连中' => 4,

            '二尾连不中' => 2,

            '三尾连不中' => 3,

            '四尾连不中' => 4,

        );

        $except_way = array_keys($preArr);

        foreach ($res as $k=>$v){

            $allway[] = $v['way'];

        }

        foreach ($way as $k=>$v) {

            $arr = explode("_",$v);

            if(count($arr)==3){

                $_1 =(int)$arr[1];

                $_2 =(int)$arr[2];

                if($_1<11 && $_2<11 && $_1>0 && $_2>0){

                    $v = $arr[0];

                }

            }

            if (in_array($lotteryType, [7,8])) {

                if (in_array($arr[0], $except_way)) {

                    $check_data = $this->lhcCheck($arr[0], count(explode(',', $arr[1]))); //检验个数

                    if ($check_data !== false) {

                        $message['content'] = $check_data['msg'];

                        $message['is_popup_msg'] = '1';

                        return;

                    }

                } else {

                    if(!in_array($v, $allway)){

                        $message['content'] = 'There are illegal bets in your bet, betting is prohibited.';

                        $message['is_popup_msg'] = '1';

                        return;

                    }

                }

            } else {

                if(!in_array($v, $allway)){

                    $message['content'] = 'There are illegal bets in your bet, betting is prohibited.';

                    $message['is_popup_msg'] = '1';

                    return;

                }

            }

        }

        //游戏币比例

        $RmbRatio = D('workerman')->getConfig("rmbratio",'value');

        //投注判断

        $res = $this->getReverseBetting($uid,$groupid,$roomid,$info['issue'],$way,$money,$RmbRatio);

        if($res['control']){

            $message['content'] = $res['content'];

            $message['is_popup_msg'] = '1';

            return;

        }

        //投注加荣誉积分

        $message['commandid'] = 3007;

        $message['uid'] = $uid;

        $message['nickname'] = $username;

        $message['avatar'] = '/'.ltrim($avatar,'/');

        $message['way'] = $way;

        $message['issue'] = $info['issue'];

        $message['time'] = date('Y-m-d H:i:s', $time);

        $message['money'] = $money;

        $message['order_no'] = [];

        $message['lose'] = '';

        $message['won'] = '';

        $message['content'] = '';

        $message['honor_status'] = $honor['honor_status'];

        $message['sort']  = $honor['sort'];

        $message['total_zushu']  = 1;

        $message['total_money']  = array_sum($money);



        if (in_array($lotteryType,[7,8])) {

            $way_array = explode("_", $way[0]);

            if (in_array($way_array[0], $except_way)) {

                $a = count(explode(",",$way_array[1]));

                $b = $preArr[$way_array[0]];

                $zu = $this->zushu($a,$b);

                $message['total_money']  = array_sum($money) * $zu;

                $message['money'] = [$message['total_money']];

                $message['single_money'] = [array_sum($money)];

                $message['total_zushu']  = $zu;

            } else {

                $message['single_money'] = [array_sum($money)];

            }

        }

        $get_person_type_sql = "SELECT type FROM un_person_config WHERE id = ".$msgData['conf_id'];

        $person_type_arr = $this->db->getone($get_person_type_sql);

        if($person_type_arr['type'] == 3) {     //投注假人  记录至假人订单表

            $encodeval_model = D('Encodeval');

            $order_no_arr = [];

            foreach($way as $k=>$v) {

                $order_no  = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);

                if(in_array($order_no, $order_no_arr)) {

                    sleep(1);

                    $order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);

                }

                $order_no_arr[] = $order_no;

                $tzje = bcdiv($money[$k], $RmbRatio, 2); //投注金额

                $ordersDataArr[] = [

                    'order_no' => $order_no,

                    'user_id' => $message['uid'],

                    'lottery_type' => $lotteryType,

                    'room_no' => $roomid,

                    'issue' => $message['issue'],

                    'way' => $v,

                    'money' => $tzje,

                    'single_money' => 0,

                    'addtime' => $time,

                    'whats_val' => $encodeval_model->mixVal($order_no, $message['total_money'], $time, $message['way']),

                ];

            }

            $this->db->insert('un_orders_dummy', $ordersDataArr);

            return;

        }

		echo 1;

        Gateway::sendToGroup($roomid, json_encode($message));

    }



	function LgErr($f,$s) {

        $dirname=__DIR__.'/Log';

        if(!file_exists($dirname)){

            mkdir($dirname,0777,true);//创建目录

        }

        $fp = fopen($dirname.'/'.date('Y_m_d').'_'.$f,"a");

        fwrite($fp, date('Y-m-d H:i:s').'--------->'.$s."\n");

        fclose($fp);

    }



	/**

     * 接收抓奖平台过来的开奖数据

     */

    public function get_lottery_data(){

        if (C('is_host_admin') !== '1') {

            return false;

        }

        $json = file_get_contents('php://input', 'r');

		$ojson=$json;

        $jsonData = json_decode($json,true);

		//实际的开奖数据

        $key = "MR3mi3o5QceTpn3KNnsvo5iwNCzszro1";

        $data=$jsonData['data'];

		$json_final=$jsonData['data'];

        //兼容新老
        if(!is_array($data)){

            $data=json_decode($data,1);

            $json_final=json_decode($jsonData['data'],true);

            $sign = md5($jsonData['data'].$jsonData['noiStr'].$jsonData['time'].$key);

        }else{

            $sign = md5(json_encode($jsonData['data'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).$jsonData['noiStr'].$jsonData['time'].$key);

        }

        $lottery_type=$data['name'];

        $lt=0;

        $file='';

        $table='';

        if($sign != $jsonData['sign']){

            switch ($lottery_type) {

                case '幸运28': //幸运28

                    $file='pcdd.txt';

                    $lt=1;

                    $table='un_open_award';

                    break;

                case '加拿大28': //加拿大28

                    $file='pcdd.txt';

                    $lt=3;

                    $table='un_open_award';

                    break;

                case '北京PK10': //北京pk10

                    $file='bjpk10.txt';

                    $lt=2;

                    $table='un_bjpk10';

                    break;

                case '幸运飞艇': //幸运飞艇

                    $file='xyft.txt';

                    $lt=4;

                    $table='un_xyft';

                    break;

                case '重庆时时彩': //重庆时时彩

                    $file='cqssc.txt';

                    $lt=5;

                    $table='un_ssc';

                    break;

                case '六合彩': //六合彩

                    $file='lhc.txt';

                    $lt=7;

                    $table='un_lhc';

                    break;

            }

            $redis = initCacheRedis();

            if($lt>0){

                //更新提示音操作,防止开奖超时一直提示

                $sql = "SELECT id FROM `un_music_tips` WHERE `type`=3 AND `record_id`='{$lt}_{$data['qihao']}' AND `status`=0";

                $re = $this->db->getone($sql);

                if(!empty($re)){

                    $sql = "UPDATE `un_music_tips` SET `status`=1 WHERE id={$re['id']}";

                    $this->db->query($sql);

                }

            }


            if($file!='' && $lt!=0 && $data['qihao']!='' && ($data['haoma'] || $data['3x9'] || $data['28'])){

                echo json_encode(array('status'=>200,'msg'=>'success'));

            }else{

                echo json_encode(array('status'=>500,'msg'=>'Abnormal data'),JSON_UNESCAPED_UNICODE);

                return false;

            }

            //判断是否有手动开奖期号与推送期号不同,不同时生成提示音

            $lottery_result = '';

            if ($lt == 2) {

                $sql = "SELECT id,kaijianghaoma as lottery_result FROM " . $table . " WHERE status= 1 AND qihao = '" . $data['qihao'] . "' AND lottery_type = {$lt}";

                $ret_lottery = $this->db->getone($sql);

                $lottery_result = $data['haoma'];

            }elseif ($lt == 4) {

                $sql = "SELECT id,kaijianghaoma as lottery_result FROM " . $table . " WHERE status= 1 AND qihao = '" . $data['qihao'] . "'";

                $ret_lottery = $this->db->getone($sql);

                $lottery_result = $data['haoma'];

            }elseif ($lt == 1 || $lt == 3) {

                $sql = "SELECT id,spare_1 as lottery_result FROM " . $table . " WHERE state = 1 AND issue = '" . $data['qihao'] . "' AND lottery_type = {$lt}";

                $ret_lottery = $this->db->getone($sql);

                $lottery_result = $data['3x9'];

            }else {

                $sql = "SELECT id,lottery_result FROM " . $table . " WHERE status= 1 AND issue = '" . $data['qihao'] . "' AND lottery_type = {$lt}";

                $ret_lottery = $this->db->getone($sql);

                $lottery_result = $data['haoma'];

            }

            if (!empty($ret_lottery['id']) && $ret_lottery['lottery_result'] != $lottery_result) {
                
                $tips = array(

                    'type' => 3,

                    'tip'  => 'Different Issue',

                    'url'  => '?m=admin&c=openAward&a=diff_issue&lottery_type=' . $lt . '&issue=' . $data['qihao'] . '&lottery_result=' . $lottery_result,

                    'time' =>time(),

                    'status' => 0,

                    'uids' =>'',

                    'msg'  => $lottery_type . ' lottery No ' . $data['qihao'] . ', The manual draw result is inconsistent with the official draw result, please deal with it immediately! ! !',

                    'record_id' => $lt . '_' . $data['qihao'],

                    'remark'    =>date('Y-m-d H:i:s').' prompt ' . $data['qihao']

                );

                if (!preventSupervene($lt . '_' . $data['qihao'], 1)) {

                    $sql = "SELECT `id` FROM `un_music_tips` WHERE `record_id` = '" . $lt . '_' . $data['qihao'] . "'";

                    $music_tips = $this->db->getone($sql);

                    if (empty($music_tips['id'])) {

                        $this->db->insert('un_music_tips', $tips);

                    }

                }

            }

            //28类

            if(in_array($lt,array(1,3))){

                $td=array(

                    'lottery_type'=>$lt,

                    'issue'=>$data['qihao'],

                    'open_no'=>$data['haoma'],

                    'open_time'=>$data['time'],

                    'insert_time'=>time(),

                    'open_result'=>$data['28'],

                    'spare_1'=>$data['3x9'],

                    'spare_2'=>$data['kjjg'],

                    'spare_3'=>$data['tj'],

                    'state'=>2,

                    'user_id'=>0,

                );

                //28类的入库和派奖

                $update_res=$this->db->insert($table,$td);


                if($update_res){

                    $redis->hsetnx('pc_lottery_type:'.$lt,$data['qihao'],1);  //存开奖数据


                    //此处进入开奖派彩的逻辑

                    //int 期号, array 号码, int 时间, int 彩种, int 状态开奖状态 0自动, 1手动, 2未开, int 开奖人 0表示自动, array 其它 frequency 开奖次数

                    D('workerman')->theLottery($data['qihao'],[$data['3x9'],$data['28'],$data['kjjg'],$data['tj']],$data['time'],$lt,0,0,array('frequency'=>1));

                }


            }else if(in_array($lt,array(2,4))){ //北京PK10 幸运飞艇

                $final['qihao']=$json_final['qihao'];

                $td=array(

                    'lottery_type'=>$lt,

                    'issue'=>$json_final['qihao']

                );

                //将开奖结果字符串处理成没有前置'0'字符串的值, 再入库

                $tmp_haoma_arr = explode(',', $json_final['haoma']);

                $new_haoma_arr = array_map('intval', $tmp_haoma_arr);

                $new_haoma_str = implode(',', $new_haoma_arr);

                $final['kaijianghaoma'] = $new_haoma_str;

                $final['kaijiangshijian']=date('Y-m-d H:i:s',$json_final['time']);

                $final['insert_time']=date('Y-m-d H:i:s',time());

                $update_res=$this->db->insert($table,$final);


                if($update_res){

                    $redis->hsetnx('pc_lottery_type:'.$lt,$final['qihao'],1);  //存开奖数据

                    //此处进入开奖派彩的逻辑

                    //int 期号, array 号码, int 时间, int 彩种, int 状态开奖状态 0自动, 1手动, 2未开, int 开奖人 0表示自动, array 其它 frequency 开奖次数

                    D('workerman')->theLottery($final['qihao'],$final,$json_final['time'],$lt,0,0,array('frequency'=>1));

                }


            }else if(in_array($lt,array(5))){ //重庆时时彩

                $td=array(

                    'lottery_type'=>$lt,

                    'issue'=>$data['qihao'],

                    'lottery_time'=>$data['time'],

                    'insert_time'=>time(),

                    'lottery_result'=>$data['haoma'],

                    'status'=>2,

                    'user_id'=>0,

                    'is_call_back'=>0,

                    'call_back_uid'=>0,

                );

                $update_res=$this->db->insert($table,$td);

                if($update_res){

                    $redis->hsetnx('pc_lottery_type:'.$lt,$td['issue'],1);  //存开奖数据


                    //此处进入开奖派彩的逻辑

                    //int 期号, array 号码, int 时间, int 彩种, int 状态开奖状态 0自动, 1手动, 2未开, int 开奖人 0表示自动, array 其它 frequency 开奖次数

                    //                                  $issue,                 $data,          $openTime, $lotteryType, $status, $uid,$other

                    D('workerman')->theLottery($td['issue'],$td,$data['time'],$lt,0,0,array('frequency'=>1));

                }


            }else if(in_array($lt,array(7))){ //六合彩

                $tmpArr=array();

                foreach (explode(',',$data['haoma']) as $v){ //去0

                    $tmpArr[] = (int)$v;

                }

                $data['haoma'] = implode(',',$tmpArr);

                $td=array(

                    'lottery_type'=>$lt,

                    'issue'=>$data['qihao'],

                    'lottery_time'=>$data['time'],

                    'insert_time'=>time(),

                    'lottery_result'=>$data['haoma'],

                    'status'=>2,

                    'user_id'=>0,

                    'is_call_back'=>0,

                    'call_back_uid'=>0,

                );

                $update_res=$this->db->insert($table,$td);


                if($update_res){

                    $redis->hsetnx('pc_lottery_type:'.$lt,$td['issue'],1);  //存开奖数据

                    $redis->set('lhc_issue',$td['issue']);


                    //此处进入开奖派彩的逻辑

                    //int 期号, array 号码, int 时间, int 彩种, int 状态开奖状态 0自动, 1手动, 2未开, int 开奖人 0表示自动, array 其它 frequency 开奖次数

                    D('workerman')->theLottery($td['issue'],$td,$data['time'],$lt,0,0,array('frequency'=>1));

                }

            }

            deinitCacheRedis($redis);

            D('workerman')->longDragon($td['lottery_type'],$td['issue']);

        }else{

            echo json_encode(array('err'=>1,'code'=>500));//签名失败

            return false;

        }

    }



    /**

     * 接收集中平台推送过来的期号和开奖时间（目前只接收北京PK10, 幸运28, 加拿大28, 幸运飞艇）

     */

    public function qihao_schedule(){

        header('content-type:text/html;charset=utf-8');

        $json = file_get_contents('php://input', 'r');

        //将数据写入文件, 做个详细的备份

        $data=json_decode($json,true);

        $redis = initCacheRedis();

        switch ($data['name']) {

            case 'jnd28':

                @file_put_contents('jnd28_qihao.json', json_encode($data,JSON_UNESCAPED_UNICODE));

                $list = json_decode($data['txt'],true);

                $redis ->del("QiHaoFirst3");

                $redis ->del("QiHaoLast3");

                $redis ->del("QiHaoIds3");

                //最后一期

                $last = json_encode(end($list['list']));

                $redis -> set("QiHaoLast3",$last);

                //第一期

                $first = json_encode(reset($list['list']));

                $redis -> set("QiHaoFirst3",$first);

                //一天的期号

                foreach ($list['list'] as $v){

                    $key = json_encode($v);

                    //将对应的键存入队列中

                    $redis -> RPUSH("QiHaoIds3", $key);

                }

                //如果自动推过来, 自动更新提示音状态

                $sql = "UPDATE `un_music_tips` SET `status`=1 WHERE `type`=3 AND `record_id`='3_push_award_fail' AND `status`=0";

                $this->db->query($sql);

                echo json_encode(array('status'=>200,'msg'=>'success'));

            break;

        }

        //关闭redis链接

        deinitCacheRedis($redis);

    }



    /**

     * 获取最新一期的期号、开奖时间、封盘倒计时、开奖倒计时

     * $roomid  房间ID

     * $type 1为接口   2方法调用

     * $isGetQihao 1为获取倒计时   2获取期号

     * 这个方法作为接口给workerman传递数据

     */

    public function get_bjpk10_lastest($roomid=0,$type=1,$isGetQihao=1){

        if(empty($roomid)){

            $roomid=$_REQUEST['roomid'];

        }

        if($_REQUEST['isGetQihao']!=null){

            $isGetQihao=$_REQUEST['isGetQihao'];

        }

        if($isGetQihao==1){

            if(empty($roomid)){

                $data['status']=0;

                $data['msg']='Unpassed room ID, this room is no longer for play';

                if($type==1){

                    echo json_encode($data,JSON_UNESCAPED_UNICODE);

                    return;

                }else{

                    return $data;

                }

            }

        }





        //如果不在售彩时间段

        $lottery_res=$this->db->getone('select config from un_lottery_type where id=2');

        $lottery_config=json_decode($lottery_res['config'],true);

        if(!(time()>strtotime($lottery_config['start_time']) && time()<strtotime($lottery_config['end_time']))){

            $data['status']=0;

            $data['msg']='Discontinuing';

            $tip = $this->db->result("select tip from un_lottery_type WHERE id = $type");

            if($tip!="") $data['msg'] = $tip;

            if($type==1){

                echo json_encode($data,JSON_UNESCAPED_UNICODE);

                return;

            }else{

                return $data;

            }

        }



        //如果后台设置停止售彩

        $config_res=$this->db->getone('select value from un_config where nid="bjpk10_stop_or_sell"');

        $config_config=json_decode($config_res['value'],true);

        if($config_config['status']==2){

            $data['status']=0;

            $data['msg']=$config_config['title'];

            if($type==1){

                echo json_encode($data,JSON_UNESCAPED_UNICODE);

                return;

            }else{

                return $data;

            }

        }



        $json=@file_get_contents('bjpk10_qihao.json');

        $temp=json_decode($json,true);

        $final=json_decode($temp['txt'],true)['list'];

        $data=array();

        //根据平台返回的开奖期号表得到当前时间的期号信息



        foreach ($final as $k=>$v){

            //如果当前时间小于, 则取这一期, 并终止循环

            if(time()<$v['date']){

                //如果传了房间ID, 则返回封盘时间

                if($isGetQihao==1){

                    $room_closetime=$this->db->getone('select closure_time from un_room where id='.$roomid);

                    if($room_closetime){

                        $data['status']=1;

                        $data['qihao']=$v['issue'];

                        $data['kaijiangshijian']=$v['date'];

                        $data['kaijiangshijian_geshi']=date('Y-m-d H:i:s',$v['date']);

                        $data['kj_daojishi']=$v['date']-time();

                        $data['fp_daojishi']=$data['kj_daojishi']-$room_closetime['closure_time'];

                        if($data['fp_daojishi']<0){

                            $data['fp_daojishi']=0;

                        }

                        $data['fengpanshijian']=$room_closetime['closure_time'];

                    }else{

                        $data['status']=0;

                        $data['msg']='No room information is obtained, this room is no longer exist';

                    }

                }else{

                    $data['status']=1;

                    $data['qihao']=$v['issue'];

                    $data['kaijiangshijian']=$v['date'];

                    $data['kaijiangshijian_geshi']=date('Y-m-d H:i:s',$v['date']);

                    $data['kj_daojishi']=$v['date']-time();

                }



                break;

            }

        }

        if($type==1){

            echo json_encode($data,JSON_UNESCAPED_UNICODE);

            return;

        }else{

            return $data;

        }

    }



    /**

     * 北京PK10投注

     * $lottery_type 彩种ID

     * $roomid 房间ID

     */

        public function bjpk10_touzhu(){

            //接收参数

            $lottery_type = $_REQUEST['lottery_type'];

            $user_id=$_REQUEST['user_id'];

            $room_id=$_REQUEST['room_id'];

            $nickname=$_REQUEST['nickname'];

            $wanfa = json_decode(stripslashes(stripslashes($_REQUEST['wanfa'])),true);

            $touzhujine = json_decode(stripslashes($_REQUEST['touzhujine']), true);

            $reg_type=$_REQUEST['reg_type'];

            $touxiang=stripslashes($_REQUEST['touxiang']);



//         $lottery_type = 2;

//         $user_id=453;

//         $room_id=16;

//         $nickname='aaaaaa';

//         $wanfa = array(1);

//         $touzhujine = array(5000);

//         $reg_type=1;

//         $touxiang='';



        $Gateway = O('Gateway');

        $Gateway::$registerAddress = C('Gateway');



//         if(empty($lottery_type)||empty($user_id)||empty($room_id)||empty($wanfa)||empty($touzhujine)){

//             $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => '缺少参数, 投注失败');

//             Gateway::sendToUid($user_id, json_encode($message));

//             return;

//         }



        $qihao_info=$this->get_bjpk10_lastest($room_id,2,1);



        //如果未获取到期号

        if(!$qihao_info['status']){

            $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => $qihao_info['msg']);

            Gateway::sendToUid($user_id, json_encode($message));

            return;

        }



        $qihao = $qihao_info['qihao'];



        //如果处于封盘

        if($qihao_info['fp_daojishi']<=0){

            $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "It is not currently within the betting time and can not be placed.");

            Gateway::sendToUid($user_id, json_encode($message));

            return;

        }



        //禁止投注错误玩法-start-Alan

        $allway=$this->db->getall('select way from un_odds where lottery_type='.$lottery_type.' and room='.$room_id);

        if($allway){

            $flag=array();

            foreach ($allway as $kk=>$vv){

                array_push($flag, $vv['way']);

            }

            foreach ($wanfa as $key=>$val) {

                if(!in_array($val, $flag)){

                    $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "There are illegal bets in your bet, betting is prohibited");

                    Gateway::sendToUid($user_id, json_encode($message));

                    return;

                }

            }

        }else{

            $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "No related method for this lottery, Stop betting");

            Gateway::sendToUid($user_id, json_encode($message));

            return;

        }

        //禁止投注错误玩法-end-Alan



        //逆向投注过滤（先查找在此房间内此期下注的订单）

        $myWayArr = $this->db->getall("select way from un_orders where user_id=$user_id and room_no = $room_id and issue = $qihao and state = 0 GROUP BY way");

        //赋值给临时数组

        $tempWayArr = $wanfa;



        //投注本期单点数字个数

        $numPk10 = 0;

        $arrPk10 = [];

        foreach ($myWayArr as $v) {

            $tempWayArr[] = $v['way'];

            if (is_numeric($v['way'])) {

                if (!in_array($v['way'], $arrPk10)) {

                    $arrPk10[] = $v['way'];

                    $numPk10++;

                }

            }

        }



        $room=$this->db->getone('select reverse,upper,lower from un_room where id='.$room_id);

        //针对不同房间进行逆向投注控制

        if ($room['reverse'] != '') {

            $reverse = json_decode($room['reverse'],1);

            if ($reverse[1]['state'] == 1 && in_array('大', $tempWayArr) && in_array('小', $tempWayArr)) {

                $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your bet content does not comply with the betting rules of this period, you can not bet on big and small at the same time");

                Gateway::sendToUid($user_id, json_encode($message));

                return;

            }

            if ($reverse[2]['state'] == 1 && in_array('单', $tempWayArr) && in_array('双', $tempWayArr)) {

                $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your bet content does not comply with the betting rules of this issue, and you can not bet on both single and double");

                Gateway::sendToUid($user_id, json_encode($message));

                return;

            }

            if ($reverse[3]['state'] == 1 && in_array('大单', $tempWayArr) && in_array('小单', $tempWayArr) && in_array('大双', $tempWayArr) && in_array('小双', $tempWayArr)) {

                $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your betting content does not comply with the betting rules of this issue, and you can not bet on big, small, big and small doubles at the same time");

                Gateway::sendToUid($user_id, json_encode($message));

                return;

            }

        }



        $user =$this->db->getone("select nickname,group_id,username from un_user WHERE id=$user_id");

        if (!empty($user) && ($user['nickname']==$nickname || $user['username']==$nickname)){

            $RmbRatio_temp =  $this->db->getone('select value from un_config where nid ="rmbratio"');

            $RmbRatio=$RmbRatio_temp['value'];

            $accout_money_temp = $this->db->getone("select money from un_account where user_id=$user_id");

            if($accout_money_temp){

                $accout_money=$accout_money_temp['money'];

            }else{

                $accout_money=0;

            }

            $huiyuanzhu = $this->db->getone('select * from un_user_group where id='.$user['group_id']); //会员组

            $tzlimit = json_decode($room['upper'],1);   //总注



            //判断总注限额

            $tMoneyYb = 0;

            foreach ($touzhujine as $v){

                $tMoneyYb = bcadd($tMoneyYb,$v,2);

            }



            //这里是判断本次投注的投注总额是否超过了总注限制

            if (isset($tzlimit['general_note']) && $tzlimit['general_note']){

                $tMoney = round($tMoneyYb / $RmbRatio, 2);

                $zztj_temp = $this->db->getone("select sum(money) as money from un_orders where user_id=$user_id and room_no = $room_id and issue = $qihao and state = 0");

                $zztj=$zztj_temp['money'];

                if (bccomp(bcadd($zztj,$tMoney,2), $tzlimit['general_note'], 2) == 1 &&$tzlimit['general_note']!=0){

                    $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your bet amount can not be greater than the total bet limit：".$tzlimit['general_note'] * $RmbRatio."coins");

                    Gateway::sendToUid($user_id, json_encode($message));

                    return;

                }

            }



            //这里是判断本次投注的投注总额是小于会员组的限制

            if ($huiyuanzhu['lower'] && bccomp($tMoneyYb, $huiyuanzhu['lower'], 2) == -1 && $huiyuanzhu['lower']!=0){

                $message = array('commandid' => 3005, 'content' => "Your bet amount can not be less than".($huiyuanzhu['lower'] * $RmbRatio)."coins");

                Gateway::sendToUid($user_id, json_encode($message));

                return;

            }

            if ($huiyuanzhu['upper'] && bccomp($tMoneyYb, $huiyuanzhu['upper'], 2) == 1 && $huiyuanzhu['upper']!=0){

                $message = array('commandid' => 3005, 'content' => "Your bet amount can not be greater than".($huiyuanzhu['upper'] * $RmbRatio)."coins");

                Gateway::sendToUid($user_id, json_encode($message));

                return;

            }



            //存储此次投注单点数字、大小单双、组合的投注总额, 这里是为了统计, 然后在下面的循环进行判断

            $temp_money=array();

            foreach ($wanfa as $k=>$v){

                if(is_numeric($v)){

                    $temp_money['dandian'.$v]+=$touzhujine[$k];

                    if (!in_array($v, $arrPk10)) {

                        $arrPk10[] = $v;

                        $numPk10++;

                    }

                }elseif(in_array($v, array('大','小','单','双'))){

                    $temp_money['dxds']+=$touzhujine[$k];

                }elseif(in_array($v, array('大单','小单','大双','小双'))){

                    $temp_money['zh']+=$touzhujine[$k];

                }

            }



            //判断数字个数

            $configPk10 = $this->db->getone('select value from un_config where `nid`="pk10_set_bet"');

            if (!empty($configPk10)) {

                $setPk10 = json_decode($configPk10['value'], true);

                if ($setPk10['status'] == 1) {

                    if ($setPk10['max'] < $numPk10) {

                        $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "The number of single point numbers you bet in this issue can not exceed {$numPk10}");

                        Gateway::sendToUid($user_id, json_encode($message));

                        return;

                    }

                }

            }



            //判断此次投注的大小单双, 大单小单大双小双, 组合的总额是否超过限制

            foreach ($wanfa as $k=>$v){

                if(is_numeric($v)){

                    if (bccomp($temp_money['dandian'.$v], $tzlimit['single_digit'][$v-1], 2) == 1&&($tzlimit['single_digit'][$v-1]!=0)){

                        $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your bet amount is greater than the single point number limit：".$tzlimit['single_digit'][$v-1] * $RmbRatio."coins");

                        Gateway::sendToUid($user_id, json_encode($message));

                        return;

                    }

                }elseif(in_array($v, array('大','小','单','双'))){

                    if (bccomp($temp_money['dxds'], $tzlimit['size_ds'], 2) == 1&&($tzlimit['size_ds']!=0)){

                        $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your bet amount is greater than the [large, small, odd, double] limit：".$tzlimit['size_ds'] * $RmbRatio."coins");

                        Gateway::sendToUid($user_id, json_encode($message));

                        return;

                    }

                }elseif(in_array($v, array('大单','小单','大双','小双'))){

                    if (bccomp($temp_money['zh'], $tzlimit['parts'], 2) == 1&&($tzlimit['parts']!=0)){

                        $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your bet amount is greater than the combination limit：".$tzlimit['parts'] * $RmbRatio."coins");

                        Gateway::sendToUid($user_id, json_encode($message));

                        return;

                    }

                }

            }



            //定义一个订单空数组

            $orderArr = array();

            $zTzje = 0; //总投注金额;

            foreach ($wanfa as $key=>$val) {

                $tzje = round($touzhujine[$key] / $RmbRatio, 2);

                if ($touzhujine[$key] == -1) {

                    $tzje = $accout_money;

                    $touzhujine[$key] = $tzje * $RmbRatio;

                }

                if (bccomp($tzje, 0.00, 2) != 1) {

                    $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "The bet amount can not be 0");

                    Gateway::sendToUid($user_id, json_encode($message));

                    return;

                }

                if ($tzje * $RmbRatio < $room['lower'] * $RmbRatio) {

                    $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your bet amount is less than the minimum bet limit：".$room['lower'] * $RmbRatio."coins");

                    Gateway::sendToUid($user_id, json_encode($message));

                    return;

                }

                if ($huiyuanzhu) {

                    if ($huiyuanzhu['lower'] && bccomp($tzje, $huiyuanzhu['lower'], 2) == -1){

                        $message = array('commandid' => 3005, 'content' => "Your bet amount can not be less than".($huiyuanzhu['lower'] * $RmbRatio)."coins");

                        Gateway::sendToUid($user_id, json_encode($message));

                        return;

                    }

                    if ($huiyuanzhu['upper'] && bccomp($tzje, $huiyuanzhu['upper'], 2) == 1){

                        $message = array('commandid' => 3005, 'content' => "Your bet amount can not be greater than" . ($huiyuanzhu['upper'] * $RmbRatio) . "coins");

                        Gateway::sendToUid($user_id, json_encode($message));

                        return;

                    }

                }

                if (bccomp($accout_money, $tzje, 2) == -1) {

                    $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your balance is not enough", 'has_not_money' => '1');

                    Gateway::sendToUid($user_id, json_encode($message));

                    return;

                }

                if ($tzlimit){

                    //这里是判断这一注的投注加上这个房间这一期投注的额度是否超过限制

                    if (isset($tzlimit['general_note']) && $tzlimit['general_note'] && $tzlimit['general_note']!=0){

                        $zztj_temp = $this->db->getone("select sum(money) as money from un_orders where user_id=$user_id and room_no = $room_id and issue = $qihao and state = 0");

                        $zztj=$zztj_temp['money'];

                        if (bccomp(bcadd($zztj,$tzje,2), $tzlimit['general_note'], 2) == 1){

                            $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your bet amount is greater than the total bet limit：".$tzlimit['general_note'] * $RmbRatio."coins");

                            Gateway::sendToUid($user_id, json_encode($message));

                            return;

                        }

                    }

                    if (is_numeric($val) && !empty($tzlimit['single_digit']) && $tzlimit['single_digit']!=0){

                        $ddtj_temp = $this->db->getone("select sum(money) as money from un_orders where user_id=$user_id and room_no = $room_id and issue = $qihao and way = '{$val}' and state = 0");

                        $ddtj=$ddtj_temp['money'];

                        // $val要减1   因为数组的下标由0开始

                        if (bccomp(bcadd($ddtj,$tzje,2), $tzlimit['single_digit'][$val-1], 2) == 1 && $tzlimit['single_digit'][$val-1]!=0){

                            $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your bet amount is greater than the single point number limit：".$tzlimit['single_digit'][$val-1] * $RmbRatio."coins");

                            Gateway::sendToUid($user_id, json_encode($message));

                            return;

                        }

                    }elseif (in_array($val,array('大','小','单','双')) && !empty($tzlimit['size_ds']) && $tzlimit['size_ds']!=0){

                        $ddtj_temp = $this->db->getone("select sum(money) as money from un_orders where user_id=$user_id and room_no = $room_id and issue = {$qihao} and way in ('大','小','单','双') and state = 0");

                        $ddtj=$ddtj_temp['money'];

                        if (bccomp(bcadd($ddtj,$tzje,2), $tzlimit['size_ds'], 2) == 1){

                            $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your bet amount is greater than the odd and even limit：".$tzlimit['size_ds'] * $RmbRatio."coins");

                            Gateway::sendToUid($user_id, json_encode($message));

                            return;

                        }

                    }elseif (in_array($val,array('大单','小单','大双','小双')) && !empty($tzlimit['parts']) && $tzlimit['parts']!=0){

                        $zhtj = $this->db->getone("select sum(money) as money from un_orders where user_id=$user_id and room_no = $room_id and issue = {$qihao} and way in ('大单','小单','大双','小双') and state = 0");

                        if (bccomp(bcadd($zhtj,$tzje,2), $tzlimit['parts'], 2) == 1){

                            $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Your bet amount is greater than the combination limit：".$tzlimit['parts'] * $RmbRatio."coins");

                            Gateway::sendToUid($user_id, json_encode($message));

                            return;

                        }

                    }

                }



                //后面补0,防止重复

                $order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($user_id,6,'0',STR_PAD_RIGHT);



                $orderArr[] = $order_no;

                $zTzje = bcadd($zTzje, $tzje, 2); //累计总投注金额

                $ye = bcsub($accout_money, $tzje, 2);

                $accout_money = $ye;

                $ddsqlarr[] = array('lottery_type' => $lottery_type, 'room_no' => $room_id, 'order_no' => $order_no, 'user_id' => $user_id, 'issue' => $qihao, 'addtime' => time(), 'way' => $val, 'money' => $tzje, 'reg_type' => $reg_type);

                $ddsqlarr_audit[] = array('lottery_type' => $lottery_type, 'room_no' => $room_id, 'order_no' => $order_no, 'user_id' => $user_id, 'issue' => $qihao, 'addtime' => time(), 'way' => $val, 'money' => $tzje);

                $zjsqlarr[] = array('order_num' => $order_no, 'user_id' => $user_id, 'type' => 13, 'addtime' => time(), 'money' => $tzje, 'use_money' => $ye,'remark'=>"User bets ".$tzje, 'reg_type' => $reg_type);

            }



            $updatesql = "UPDATE `un_account` SET `money` =money-'{$zTzje}' WHERE `user_id` = $user_id "; //出于并发考虑

            $this->db->query("START TRANSACTION");

            try {

                $ret = $this->db->query($updatesql);

                if (empty($ret)) throw new Exception('Update failed!1');

                $inid = $this->db->insert('un_orders', $ddsqlarr);

                $inid_audit = $this->db->insert('un_orders_audit', $ddsqlarr_audit);

                if ($inid){

                    $ret = $this->db->insert('un_account_log',$zjsqlarr );

                    if (empty($ret)) throw new Exception('Update failed!2');



                    $sql = "SELECT MAX(award_state) as state,issue FROM `un_orders` WHERE user_id=$user_id AND room_no=$room_id AND award_state<>0 GROUP by issue order by issue desc";

                    $sy = $this->db->query($sql);

                    $sya = array(1 => 0, 2 => 0);

                    if ($sy) {

                        $a = 0;

                        foreach ($sy as $v) {

                            if ($a && $v['state'] == $a) {

                                $sya[$v['state']]++;

                            } elseif ($a && $v['state'] != $a) {

                                break;

                            } else {

                                $sya[$v['state']]++;

                                $a = $v['state'];

                            }

                        }

                    }

                     $count_temp = $this->db->getone("select count(id) as total from un_orders WHERE  user_id=$user_id AND room_no=$room_id AND issue={$qihao} AND state=0 AND chase_number = ''");

                    $count=$count_temp['total'];



                    //控制昵称显示

                    $tznickanme = D('common')->getConfig("tznickname");

                    if($tznickanme['value']){

                        $strleng = mb_strlen($nickname)-1;

                        $nickname = mb_substr($nickname,0,1,'utf-8')."***".mb_substr($nickname,$strleng,1,'utf-8');

                    }



                    /*

                    $honor = D('workerman')->get_honor_level($user_id);

                    if(($honor['status1'] && $honor['status ']) || ($honor['status'] && !$honor['score'])){

                        $sendstr = json_encode(array('commandid' => 3007, 'uid'=>$user_id, 'nickname' => $nickname, 'icon'=>$honor['icon'], 'num'=>$honor['num'], 'honor'=>$honor['name'], 'way' => $wanfa, 'money' => $touzhujine, 'avatar' => $touxiang, 'lose' => $sya[1], 'won' => $sya[2],'count'=>$count,'issue'=>$qihao,'time'=>date("H:i",time()), 'order_no' => $orderArr));

                    }else{

                        $sendstr = json_encode(array('commandid' => 3007, 'uid'=>$user_id, 'nickname' => $nickname, 'way' => $wanfa, 'money' => $touzhujine, 'avatar' => $touxiang, 'lose' => $sya[1], 'won' => $sya[2],'count'=>$count,'issue'=>$qihao,'time'=>date("H:i",time()), 'order_no' => $orderArr));

                    }

                    */

                    $honor = get_level_honor($user_id);

                    $sendstr = json_encode(array(

                        'commandid' => 3007,

                        'uid'=>$user_id,

                        'nickname' => $nickname,

                        'honor_status'=>$honor['honor_status'],

                        'sort'=>$honor['sort'],

                        'way' => $wanfa,

                        'money' => $touzhujine,

                        'avatar' =>'/'.ltrim($touxiang,'/'),

                        'lose' => $sya[1],

                        'won' => $sya[2],

                        'count'=>$count,

                        'issue'=>$qihao,

                        'time'=>date("H:i",time()),

                        'order_no' => $orderArr

                     ));



//                     $sendstr = json_encode(array('commandid' => 3007,'uid'=>$user_id, 'nickname' => $nickname, 'way' => $wanfa, 'money' => $touzhujine, 'avatar' => $touxiang, 'lose' => $sya[1], 'won' => $sya[2],'count'=>$count,'issue'=>$qihao,'time'=>date("H:i",time()), 'order_no' => $orderArr));

                    Gateway::sendToGroup($room_id, $sendstr);

                    $message = array('commandid' => 3010, 'money' => number_format($ye * $RmbRatio, 2, '.', ''));

                    Gateway::sendToUid($user_id, json_encode($message));

                }else{

                    throw new Exception('Update failed!3');

                }

                $this->db->query('COMMIT');

                return;

            } catch (Exception $err) {

                $this->db->query('ROLLBACK');

                $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "System error, please contact customer service");

                Gateway::sendToUid($user_id, json_encode($message));

                return;

            }

        }else{

            $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "User information error");

            Gateway::sendToUid($user_id, json_encode($message));

            return;

        }

    }



    /**

     * 追号

     */

    public function bjpk10_zhuihao(){

        //接收参数

        $lottery_type = $_REQUEST['lottery_type'];

        $user_id=$_REQUEST['user_id'];

        $room_id=$_REQUEST['room_id'];

        $nickname=$_REQUEST['nickname'];

        $reg_type=$_REQUEST['reg_type'];

        $touxiang=stripslashes($_REQUEST['touxiang']);

        $zhuihao_data=json_decode(stripslashes(stripslashes($_REQUEST['data'])),true);



//         $lottery_type = 2;

//         $user_id=453;

//         $room_id=16;

//         $nickname='aaaaaa';

//         $reg_type=1;

//         $touxiang='asfasd';

//         $zhuihao_data=json_decode("[{\"qihao\":\"625876\",\"money\":\"800\",\"way\":\"1\",\"multiple\":\"1\"}]",true);

        $msgInf = [];

        $chaseNumber = D('workerman')->getRandomString(6);

        $RmbRatio_temp =  $this->db->getone('select value from un_config where nid ="rmbratio"');

        $RmbRatio=$RmbRatio_temp['value'];

        $room=$this->db->getone('select lower,upper,reverse from un_room where id='.$room_id);

        $Gateway = O('Gateway');

        $Gateway::$registerAddress = C('Gateway');

        $ddsqlarr=array();

        $ddsqlarr_audit=array();

        $zjsqlarr=array();

        $zTzje=0;

        foreach ($zhuihao_data as $k=> $v){

            $nowtime=time();

            $qihao_info = $this->get_bjpk10_lastest($room_id,2,1);

            //如果在非投注期或期号小于当前期号

            if(!$qihao_info['status'] || $v['qihao']<$qihao_info['qihao'] ||$qihao_info['fp_daojishi']<=0){

                $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】 Not in betting issue",'code'=>-1];

//                 @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                break;

            }

            //判断房间是否处于停售状态 start

            $res=$this->db->getone('select value from un_config where nid="bjpk10_stop_or_sell"');

            $config = json_decode($res['value'],true);

            if($config['status'] == 2){

                $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】{$config['title']}",'code'=>-1];

//                 @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                break;

            }

            //判断房间是否处于停售状态 end



            //逆向投注过滤（先查找在此房间内此期下注的订单）

            $myWayArr = $this->db->getall("select way from un_orders where user_id=$user_id and room_no = $room_id and issue = {$qihao_info['qihao']} and state = 0 GROUP BY way");

            //赋值给临时数组

            $tempWayArr = array();

            $tempWayArr[] = $v['way'];

            foreach ($myWayArr as $vvv) {

                $tempWayArr[] = $vvv['way'];

            }



            //针对不同房间进行逆向投注控制

            if ($room['reverse'] != '') {

                $reverse = json_decode($room['reverse'],1);

                if ($reverse[1]['state'] == 1 && in_array('大', $tempWayArr) && in_array('小', $tempWayArr)) {

                    $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "The content of your chase number does not comply with the betting rules of this issue, and you can not bet on both big and small");

                    Gateway::sendToUid($user_id, json_encode($message));

                    return;

                }

                if ($reverse[2]['state'] == 1 && in_array('单', $tempWayArr) && in_array('双', $tempWayArr)) {

                    $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "The content of your chase number does not comply with the betting rules of this issue, and you can not bet single and doubles at the same time");

                    Gateway::sendToUid($user_id, json_encode($message));

                    return;

                }

                if ($reverse[3]['state'] == 1 && in_array('大单', $tempWayArr) && in_array('小单', $tempWayArr) && in_array('大双', $tempWayArr) && in_array('小双', $tempWayArr)) {

                    $message = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "The content of your chase number does not comply with the betting rules of this issue, and you can not bet on big, small, big and small doubles at the same time");

                    Gateway::sendToUid($user_id, json_encode($message));

                    return;

                }

            }



            //开始业务逻辑

            $user = $this->db->getone("select nickname,group_id,username from un_user WHERE id=$user_id");

            if (!empty($user) && ($user['nickname']==$nickname || $user['username']==$nickname)){

                $accout_money_temp = $this->db->getone("select money from un_account where user_id=$user_id");

                $accout_money=$accout_money_temp['money'];

                $huiyuanzhu = $this->db->getone('select * from un_user_group where id='.$user['group_id']); //会员组

                $tzlimit = json_decode($room['upper'],1);   //总注

                //判断总注限额

                $tMoneyYb = 0;

                $tMoneyYb = bcadd($tMoneyYb,$v['money'],2);

                if (isset($tzlimit['general_note']) && $tzlimit['general_note']){

                    $tMoney = round($tMoneyYb / $RmbRatio, 2);

                    $zztj_temp = $this->db->getone("select sum(money) as money from un_orders where user_id=$user_id and room_no = $room_id and issue = {$v['qihao']} and state = 0");

                    $zztj=$zztj_temp['money'];

                    if (bccomp(bcadd($zztj,$tMoney,2), $tzlimit['general_note'], 2) == 1){

                        $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】Your bet amount can not be greater than the total bet limit：".$tzlimit['general_note'] * $RmbRatio."coins",'code'=>-1];

//                         @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                        break;

                    }

                }



                //定义一个订单空数组

                $orderArr = array();

                $tzje = round($v['money'] / $RmbRatio, 2);

                if ($v['money'] == -1) {

                    $tzje = $accout_money;

                    $v['money'] = $tzje * $RmbRatio;

                }

                if (bccomp($tzje, 0.00, 2) != 1) {

                    $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】The bet amount can not be 0",'code'=>-1];

//                     @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                    break;

                }

                if ($tzje * $RmbRatio < $room['lower'] * $RmbRatio) {

                    $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】Your bet amount is less than the minimum bet limit".$room['lower'] * $RmbRatio."coins",'code'=>-1];

//                     @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                    break;

                }

                if ($huiyuanzhu) {

                    if ($huiyuanzhu['lower'] && bccomp($tzje, $huiyuanzhu['lower'], 2) == -1){

                        $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】Your bet amount can not be less than".($huiyuanzhu['lower'] * $RmbRatio)."coins",'code'=>-1];

//                         @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                        break;

                    }

                    if ($huiyuanzhu['upper'] && bccomp($tzje, $huiyuanzhu['upper'], 2) == 1){

                        $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】Your bet amount can not be greater than" . ($huiyuanzhu['upper'] * $RmbRatio) . "coins",'code'=>-1];

//                         @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                        break;

                    }

                }

                if (bccomp($accout_money, $tzje, 2) == -1) {

                    $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】Your balance is not enough", 'code'=>-1, 'has_not_money' => '1'];

//                     @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                    break;

                }

                if ($tzlimit){

                    if (isset($tzlimit['general_note']) && $tzlimit['general_note'] && $tzlimit['general_note']!=0){

                        $zztj_temp = $this->db->getone("select sum(money) as money from un_orders where user_id=$user_id and room_no = $room_id and issue = {$v['qihao']} and state = 0");

                        $zztj=$zztj_temp['money'];

                        if (bccomp(bcadd($zztj,$tzje,2), $tzlimit['general_note'], 2) == 1){

                            $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】Your bet amount is greater than the total bet limit：".$tzlimit['general_note'] * $RmbRatio."coins",'code'=>-1];

//                             @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                            break;

                        }

                    }

                    if (is_numeric($v['way']) && !empty($tzlimit['single_digit']) && $tzlimit['single_digit']!=0){

                        $ddtj_temp = $this->db->getone("select sum(money) as money from un_orders where user_id=$user_id and room_no = $room_id and issue = {$v['qihao']} and way = '{$v['way']}' and state = 0");

                        $ddtj=$ddtj_temp['money'];

                        if (bccomp(bcadd($ddtj,$tzje,2), $tzlimit['single_digit'][$v['way']-1], 2) == 1&&$tzlimit['single_digit'][$v['way']-1]!=0){

                            $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】Your bet amount is greater than the single point number limit：".$tzlimit['single_digit'][$v['way']-1] * $RmbRatio."coins",'code'=>-1];

//                             @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                            break;

                        }

                    }elseif (in_array($v['way'],array('大','小','单','双')) && !empty($tzlimit['size_ds']) && $tzlimit['size_ds']!=0){

                        $ddtj_temp = $this->db->getone("select sum(money) as money from un_orders where user_id=$user_id and room_no = $room_id and issue = {$v['qihao']} and way in ('大','小','单','双') and state = 0");

                        $ddtj=$ddtj_temp['money'];

                        if (bccomp(bcadd($ddtj,$tzje,2), $tzlimit['size_ds'], 2) == 1){

                            $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']."【Chase number】Your bet amount is greater than the odd and even limit：".$tzlimit['size_ds'] * $RmbRatio."coins",'code'=>-1];

//                             @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                            break;

                        }

                    }elseif (in_array($v['way'],array('大单','小单','大双','小双')) && !empty($tzlimit['parts']) && $tzlimit['parts']!=0){

                        $zhtj_temp = $this->db->getone("select sum(money) as money from un_orders where user_id=$user_id and room_no =$room_id  and issue = {$v['qihao']} and way in ('大单','小单','大双','小双') and state = 0");

                        $zhtj=$zhtj_temp['money'];

                        if (bccomp(bcadd($zhtj,$tzje,2), $tzlimit['parts'], 2) == 1){

                            $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】Your bet amount is greater than the combination limit：".$tzlimit['parts'] * $RmbRatio."coins",'code'=>-1];

//                             @file_put_contents('bjpk10_award.log', json_encode($msgInf).PHP_EOL,FILE_APPEND);

                            break;

                        }

                    }

                    //后面补0,防止重复

                    $order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($user_id,6,'0',STR_PAD_RIGHT);

                    $orderArr[] = $order_no;

                    $zTzje = bcadd($zTzje, $tzje, 2); //累计总投注金额

                    $ye = bcsub($accout_money, $tzje, 2);

                    $ddsqlarr[] = array('lottery_type' => $lottery_type, 'room_no' => $room_id, 'order_no' => $order_no, 'user_id' => $user_id, 'issue' => $v['qihao'], 'addtime' => $nowtime, 'way' => $v['way'], 'money' => $tzje, 'reg_type' => $reg_type,'chase_number'=>$chaseNumber,'multiple'=>$v['multiple']);

                    $ddsqlarr_audit[] = array('lottery_type' => $lottery_type, 'room_no' => $room_id, 'order_no' => $order_no, 'user_id' => $user_id, 'issue' => $v['qihao'], 'addtime' => $nowtime, 'way' => $v['way'], 'money' => $tzje);

                    $zjsqlarr[] = array('order_num' => $order_no, 'user_id' => $user_id, 'type' => 13, 'addtime' => $nowtime, 'money' => $tzje, 'use_money' => $ye,'remark'=>"User chase betting".$tzje, 'reg_type' => $reg_type);

                    $this->db->query('START TRANSACTION');

                    try {

                        if($zhuihao_data[$k+1]==null){

                            $updatesql = "UPDATE `un_account` SET `money` =money-'{$zTzje}' WHERE `user_id` = $user_id "; //出于并发考虑

                            $ret = $this->db->exec($updatesql);

                            if (empty($ret)){

                                //                             @file_put_contents('bjpk10_award.log', $updatesql.PHP_EOL,FILE_APPEND);

                                throw new Exception('Update failed!1');

                            }



                            $inid =$this->db->insert('un_orders', $ddsqlarr);

                            $inid_audit =$this->db->insert('un_orders_audit', $ddsqlarr_audit);

                            if ($inid){

                                $ret = $this->db->insert('un_account_log',$zjsqlarr );

                                if (empty($ret)){

                                    //                                 @file_put_contents('bjpk10_award.log', $zjsqlarr.PHP_EOL,FILE_APPEND);

                                    throw new Exception('Update failed!2');

                                }

                            }else{

                                //                             @file_put_contents('bjpk10_award.log', $ddsqlarr.PHP_EOL,FILE_APPEND);

                                throw new Exception('Update failed!3');

                            }

                            $msgInf[] = ["msg"=>$v['way']."【Chase number】Bet success",'code'=>0];

                            $this->db->query('COMMIT');

                        }

                    } catch (Exception $err) {

                        $this->db->query('ROLLBACK');

                        $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】System error, please contact customer service",'code'=>-1];

//                         @file_put_contents('bjpk10_award.log', json_encode($msgInf,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);

                    }

                }

            } else {

                $msgInf[] = ["msg"=>$v['qihao']." ".$v['way']." ".$v['money']." 【Chase number】User information error",'code'=>-1];

//                 @file_put_contents('bjpk10_award.log', json_encode($msgInf,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);

                break;

            }

        }

        $check = false;

        foreach ($msgInf as $val) {

            if($val['code'] == 0){

                $check = true;

            }

            $message = array('commandid' => 3004, 'nickname' => '', 'content' => $val['msg']);

            Gateway::sendToUid($user_id, json_encode($message));

        }

        $accout_money = $this->db->getone("select money from un_account where user_id=$user_id");

        $message = array('commandid' => 3010, 'money' => number_format($accout_money['money'] * $RmbRatio, 2, '.', ''));

        Gateway::sendToUid($user_id, json_encode($message));

        if($check == true){

            $message = array('commandid' => 3019, 'nickname' => "", 'content' => '');

            Gateway::sendToUid($user_id, json_encode($message));

        }

        return;

    }



    /*

     * 定时任务-判断是否有未开的期号、晚开的期号

     */

    public function checkIsOpen(){

    }





    /*

     * 北京PK10开奖和下期的期号写入（跟workerman分离, 即使workerman没启动, 也不影响开奖）

     * (暂时不用)

     */

    public function bjpk10_award(){

        //获取接口数据

        $jiekou_res=file_get_contents('http://112.74.37.53:8080/lottery/bjpk10/latest?key=HjieI69yXVceXkuy02E0WroV');

        //如果有取到数据, 则将json转换成数组

        if($jiekou_res){

            $result=json_decode($jiekou_res,true);

        }else{

            //如果没拿到数据, 则终止程序

            return false;

        }

//         $redis = initCacheRedis();//初始化redis



        //最新一期的数据

        $lastest_data=$this->db->getone('select qihao,kaijiangshijian from un_bjpk10 where  !ISNULL(kaijianghaoma) order by id desc');

        if(!$lastest_data){

            //判断数据库如果是空的话, 则将接口返回的最新一期的数据写入（主要防止bjpk10表被清空--这里只是预防操作）

            $insert_data['qihao']=$result[0]['qihao'];

            $insert_data['kaijiangshijian']=date('Y-m-d H:i:s',$result[0]['time']);

            $insert_data['kaijianghaoma']=$result[0]['haoma'];

            $insert_data['insert_time']=date('Y-m-d H:i:s',time());

            $db_res=$this->db->insert('un_bjpk10',$insert_data);



            //将下一期期号先写入数据库, 等待下次开奖

            unset($insert_data);

            $insert_data['qihao']=$result[0]['qihao']+1;

            $db_res=$this->db->insert('un_bjpk10',$insert_data);



        }else{

            //如果接口返回数据为比当前期号大, 则将数据写入最新一期

            if($result[0]['qihao']>$lastest_data['qihao']){

                $insert_data['kaijiangshijian']=date('Y-m-d H:i:s',$result[0]['time']);

                $insert_data['kaijianghaoma']=$result[0]['haoma'];

                $insert_data['insert_time']=date('Y-m-d H:i:s',time());

                $db_res=$this->db->update('un_bjpk10',$insert_data,array('qihao'=>$result[0]['qihao']));

                echo '更新';

                echo $db_res.'<br>';



                if($db_res){



                    //进入派彩的逻辑

                    echo '<br>The logic of entering the payout<br>';

                }



                //将下一期期号先写入数据库, 等待下次开奖

                unset($insert_data);

                try {

                    $insert_data['qihao']=$result[0]['qihao']+1;

                    $db_res=$this->db->insert('un_bjpk10',$insert_data);

                } catch (Exception $e) {

                    //暂不处理异常

                }



                //防止漏期, 写入期号并提示手动开奖

                echo $result[0]['qihao'].'<br>';

                echo $lastest_data['qihao'].'<br>';

                $geqi=$result[0]['qihao']-$lastest_data['qihao'];

                if($geqi>=2){

                    $temp_qihao=$lastest_data['qihao']+1;

                    for($i=1;$i<$geqi;$i++){

                        $temp_qihao++;

                        try {

                            $insert_data['qihao']=$temp_qihao;

                            $db_res=$this->db->insert('un_bjpk10',$insert_data);

                        } catch (Exception $e) {

                            //暂不处理异常

                        }



                    }

                }



            }

        }

//         deinitCacheRedis($redis);

    }



    /**

     * 获取期号 直接用新接口方式

     */

    public static function getQihaoNew()

    {

        $btime = microtime(1);

        //验证签名

        $hbtime = microtime(1);

        $res = verificationSignature();

        if($res['status'] !== "success"){

            @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 签名验证失败(获取期号): '.json_encode($res,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);

            if($res['code'] == 3){

                ErrorCode::errorResponse(999998,"Request timed out, please make sure your mobile phone system time is: Beijing (China) time, refresh and try again!");

            }

            ErrorCode::errorResponse(999999,"Signing failed, please make sure the app is the latest version and try again!");

        }

        $client_id = $_REQUEST['client_id'];

        $hetime = microtime(1);

        $room = (int)$_REQUEST['roomid'];

        $lotteryType = (int)$_REQUEST['lotteryType'];

        $hbtime = microtime(1);

        $info = D('workerman')->getQihao($lotteryType,time(),$room);

        $hetime = microtime(1);

        $data = array(

            'commandid' => 3001,

            'time' => $info['time'],

            'issue' => $info['issue'],

            'sealingTim' => $info['sealingTim'],

            'stopOrSell' => $info['stopOrSell'],

            'stopMsg' => $info['msg'],

            'lotteryType' => $info['lotteryType'],

			

        );

        O('Gateway');

        $hbtime = microtime(1);

        Gateway::sendToClient($client_id, encode($data));

        $hetime = microtime(1);

        $etime = microtime(1);

    }









    /**

     * 获取期号

     */

    public static function getQihao()

    {

        //验证签名

        $res = verificationSignature();

        if($res['status'] !== "success"){

            @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 签名验证失败(获取期号): '.json_encode($res,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);

            if($res['code'] == 3){

                ErrorCode::errorResponse(999998,"Request timed out, please make sure your mobile phone system time is: Beijing (China) time, refresh and try again!");

            }

            ErrorCode::errorResponse(999999,"Signing failed, please make sure the app is the latest version and try again!");

        }



        $room = (int)$_REQUEST['roomid'];

        $lotteryType = (int)$_REQUEST['lotteryType'];

        $res = D('workerman')->getQihao($lotteryType,time(),$room);

        ErrorCode::successResponse(array('data' => $res));

    }



    /**

     * @param $a

     * @param $b

     * @return float|int

     *

     */

    function zushu($a, $b) {

        $topNum = 1;

        for($i=$a;$i>$a-$b;$i--){

            $topNum = $topNum*$i;

        }

        $botNum = 1;

        for($j = 1; $j <= $b; $j++){

            $botNum = $botNum*$j;

        }

        $dataSum =  $topNum / $botNum;

        return $dataSum;

    }



    //足彩投注

    public function football_beting($lotteryType,$paramFlag,$uid,$way,$money,$roomid,$time)

    {

        //实例化Gateway

        $Gateway = O('Gateway');

        $Gateway::$registerAddress = C('Gateway');

        $info['issue']=1;





        //ToDo  比赛时, 不能投单双 , 要限制



        //查询用户信息(昵称,头像,注册类型)

        $sql = "SELECT username,nickname,avatar,group_id,reg_type FROM `un_user` WHERE `id` = '{$uid}'";

        $res = $this->db->getone($sql);

        if(empty($res) || $paramFlag){

            Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "The information is missing or has been changed.")));

            return false;

        };



        $username = empty($res['nickname'])?$res['username']:$res['nickname'];

        //控制昵称显示

        $username = D('workerman')->getNickname($username);

        $avatar = empty($res['avatar'])?'/up_files/room/avatar.png':$res['avatar'];

        $regType = $res['reg_type'];

        $groupid = $res['group_id'];


        //查询用户荣誉等级

        $honor = get_level_honor($uid);





        //判断投注的玩法是否是正确的

        $redis = initCacheRedis();

        $match_id = $redis->hget('allroom:'.$roomid,'match_id');

        $odds_info = $redis->hGet("fb_odds",$match_id);

        $against = decode($redis->hGet("fb_against",$match_id));



        //获取房间比分更新时间

        $bf_uptime = $against[0]['update_time'];



        //后台变动延迟的设置时间

        $update_set_time = $redis->hGet('Config:football_odds_up_time','value'); //比分变动 延时生效时间



        $stop_sell = decode($redis->hGet('Config:sjb_stop_or_sell','value'));

        deinitCacheRedis($redis);

        if ($stop_sell['status'] == 2) {

            $message['commandid'] = 3004;

            $message['content'] = $stop_sell['title'];

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, encode($message));

            return false;

        }

        //结束后, 禁止投注

        if($against[0]['match_state']>7){

            $message['commandid'] = 3004;

            $message['content'] = '已结束, Stop betting';

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, encode($message));

            return false;

        }

        //有比分变动  整个房间不能投 add 20190620

        $now = time();

        $lenTime = $now-$bf_uptime;

        if($update_set_time>0 && $against[0]['match_state']>0 && $against[0]['match_state']<9){

            if($lenTime <= $update_set_time){ //当前时间-变动时间小于设定值

                $message['commandid'] = 3004;

                $message['content'] = "Score changes, wait to take effect, please try again in ".($update_set_time-$lenTime)." seconds";

                $message['is_popup_msg'] = '1';

                Gateway::sendToUid($uid, encode($message));

                return false;

            }

        }

        //判断投注当前场子类型

        $ctype = $against[0]['match_state'];

        //从缓存中取时时比分

        $bi_feng = $against[0]['match_score'];


        if($bi_feng == '' && $against[0]['match_state']>0 && $against[0]['match_state']<9){

            $message['commandid'] = 3004;

            $message['content'] = "Can not bet if the score is empty";

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, encode($message));

            return false;

        }



        //组装赔率数据

        $odds_info = decode($odds_info);

        $odds_arr=array();

        $ifStop=0;

        foreach ($odds_info as $ok=>$ov){

            $odds_arr[$ov['way']] = array('odds'=>$ov['odds'],'handicap'=>$ov['handicap'],'update_time'=>$ov['update_time']);

        }

        $sql = "select way,type,state from un_cup_odds where lottery_type='{$lotteryType}' and match_id='{$match_id}'";

        $res = $this->db->getall($sql);

        if(empty($res)){

            $message['commandid'] = 3004;

            $message['content'] = 'No related method for this lottery, Stop betting';

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, encode($message));

            return false;

        }

        $allway = array();

        foreach ($res as $k=>$v){

            if ($v['state'] == 0) {

                $allway[] = $v['way'];

                if ($v['type'] == 1) {

                    $num_way[] = $v['way'];

                }

            }

        }

        $totalZu = 0;

        $type=0;

        foreach ($way as $k=>$v) {

            //开赛后不能投单双

            if($against[0]['match_state']>0){

                if(strpos($v,'单') !==false || strpos($v,'双') !==false || preg_match('/^(半|全)场入球_/',$v) || preg_match('/^半\/全场_/',$v) || preg_match('/^全场比分_/',$v)){

                    $message['commandid'] = 3004;

                    $message['content'] = "Can not vote during the game【{$v}】";

                    $message['is_popup_msg'] = '1';

                    Gateway::sendToUid($uid, encode($message));

                    return false;

                }

            }

            //锁盘状态、赔率变为0的, 针对玩法不能投 add 20190620

            $lonTime = $now-$odds_arr[$v]['update_time'];

            if($update_set_time>0 && !empty($odds_arr[$v]['update_time']) && $against[0]['match_state']>0 && $against[0]['match_state']<9){



                if($odds_arr[$v]['odds'] == '0.00' || $odds_arr[$v]['odds'] == '' || $odds_arr[$v]['odds'] == '0'){

                    if($lonTime <= $update_set_time){ //未到生效时间

                        $message['commandid'] = 3004;

                        $message['content'] = "Current method【{$v}】 odds change, wait for it to take effect, please try again after ".($update_set_time-$lonTime)."seconds";

                        $message['is_popup_msg'] = '1';

                        Gateway::sendToUid($uid, encode($message));

                        return false;

                    }

                }

            }

            //判断玩法的盘口数据是否完整

            if(empty($odds_arr[$v]['odds']) || $odds_arr[$v]['odds'] == '0.00'){

                $message['commandid'] = 3004;

                $message['content'] = "Current method【{$v}】 has no odds data, please try again";

                $message['is_popup_msg'] = '1';

                Gateway::sendToUid($uid, encode($message));

                return false;

                break;

            }

            if(strpos($v,'单') !==false || strpos($v,'双') !==false || preg_match('/^(半|全)场入球_/',$v)|| preg_match('/^半\/全场_/',$v) || preg_match('/^全场比分_/',$v) || in_array($v,array('半场_A胜','半场_平局','半场_B胜','全场_A胜','全场_平局','全场_B胜'))){ //这行有用, 不能删除


            }else{

                if( !isset($odds_arr[$v]['handicap']) || $odds_arr[$v]['handicap']==''){

                    $message['commandid'] = 3004;

                    $message['content'] = "Current method【{$v}】 has no room data, please try again";

                    $message['is_popup_msg'] = '1';

                    Gateway::sendToUid($uid, encode($message));

                    return false;

                    break;

                }

            }

            $totalZu ++;

            if(!in_array($v, $allway)){

                $message['commandid'] = 3004;

                $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                $message['is_popup_msg'] = '1';

                Gateway::sendToUid($uid, encode($message));

                return false;

            }

            if (strpos($v, '半场') !== false) {

                if ($ctype >1) {

                    $message['commandid'] = 3004;

                    $message['content'] = "Method【{$v}】 Can not bet after the first half";

                    $message['is_popup_msg'] = '1';

                    Gateway::sendToUid($uid, encode($message));

                    return false;

                }

            }

            if (strpos($v, '全场') !== false) {

                if ($ctype >3) {

                    $message['commandid'] = 3004;

                    $message['content'] = "Method【{$v}】 Can not bet after the end of the game";

                    $message['is_popup_msg'] = '1';

                    Gateway::sendToUid($uid, encode($message));

                    return false;

                }

            }

            if (strpos($v, '加时') !== false) {

                if ($ctype >5) {

                    $message['commandid'] = 3004;

                    $message['content'] = "Method【{$v}】 Can not bet after the overtime ends";

                    $message['is_popup_msg'] = '1';

                    Gateway::sendToUid($uid, encode($message));

                    return false;

                }

            }

            if (strpos($v, '点球') !== false) {

                if ($ctype >6) {

                    $message['commandid'] = 3004;

                    $message['content'] = "Method【{$v}】 Can not bet at the start of a penalty";

                    $message['is_popup_msg'] = '1';

                    Gateway::sendToUid($uid, encode($message));

                    return false;

                }

            }

        }

        //游戏币比例

        $RmbRatio = D('workerman')->getConfig("rmbratio",'value');



        //投注判断

        $res = $this->getReverseBetting($uid,$groupid,$roomid,$info['issue'],$way,$money,$RmbRatio);

        if($res['control']){

            $message['commandid'] = 3004;

            $message['content'] = $res['content'];

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, encode($message));

            return false;

        }



        //判断账户余额是否小于当前投注总额

        $currentMoney = array_sum($money);

        $currentMoney = bcdiv($currentMoney,$RmbRatio,2);

        $sql = "SELECT money FROM `un_account` WHERE `user_id` = '{$uid}'";

        $account = $this->db->result($sql);

        if (bccomp($currentMoney, $account, 2) == 1) {

            $message['commandid'] = 3004;

            $message['content'] = "Your balance is not enough, please deposit";

            $message['has_not_money'] = '1';

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, encode($message));

            return false;

        }



        //生成订单

        $this->db->query("START TRANSACTION");

        try {

            $encodeval_model = D('Encodeval');



            $tempAccount = $account;

            $ddsqlarr = array();

            $ddsqlarr_audit = array();

            $zjsqlarr = array();

            $orderArr = array();

            $exOrderArr = array();

            $pkArr = array();



            foreach ($way as $k => $v) {

                if (strpos($v, '半场') !== false) {

                    $type = 2;

                }



                if (strpos($v, '全场') !== false  || preg_match('/^半\/全场_/',$v)) {

                    $type = 4;

                }



                if (strpos($v, '加时') !== false) {

                    $type = 6;

                }



                if (strpos($v, '点球') !== false) {

                    $type = 8;

                }



                //获取是否跟投标识

                $ext_a = (int)$_REQUEST['ext_a'] == 1 ? D('workerman')->getRandomString(6) : '';

                //后面补0,防止重复

                $order_no = "TZ" . date("YmdHis") . rand(100, 999) . str_pad($uid, 6, '0', STR_PAD_RIGHT);

                $orderArr[] = $order_no;  //新增

                $tzje = bcdiv($money[$k], $RmbRatio, 2);//投注金额

//                $dzje = bcdiv($single_money[$k], $RmbRatio, 2);//单注金额

                $tempAccount = bcsub($tempAccount, $tzje, 2);//当前金额

                $ddsqlarr = array(

                    'lottery_type' => $lotteryType,

                    'room_no' => $roomid,

                    'order_no' => $order_no,

                    'user_id' => $uid,

                    'issue' => $info['issue'],

                    'addtime' => $time,

                    'way' => $v,

                    'money' => $tzje,

//                    'single_money' => $dzje,

                    'reg_type' => $regType,

//                    'win_stop' => $win_stop,

                    'ext_b' => $ext_a,



                    //生成校验值

                    'whats_val' => $encodeval_model->mixVal($order_no, $tzje, $time, $v),

                );

                $inid = $this->db->insert('un_orders', $ddsqlarr);

                if (empty($inid)) throw new Exception('Update failed!4');

                $oid = $this->db->insert_id();

                $pkArr[] = $odds_arr[$v]['handicap']; //收集盘口数据

                $exOrderArr=array(

                    'order_id'=>$oid,

                    'pan_kou'=>$odds_arr[$v]['handicap'],

                    'odds'=>$odds_arr[$v]['odds'],

                    'bi_feng'=>$bi_feng,

                    'type'=>$type,

                );

                if(strpos($v,'单') !==false || strpos($v,'双') !==false || preg_match('/^(半|全)场入球_/',$v) || preg_match('/^半\/全场_/',$v) || preg_match('/^全场比分_/',$v)){

                    $exOrderArr['bi_feng']='0:0'; //重新定义这个比分

                }

                $inid = $this->db->insert('un_orders_football', $exOrderArr);

                $ddsqlarr_audit = array(

                    'lottery_type' => $lotteryType,

                    'room_no' => $roomid,

                    'order_no' => $order_no,

                    'user_id' => $uid,

                    'issue' => $info['issue'],

                    'addtime' => $time,

                    'way' => $v,

                    'odds'=>$odds_arr[$v]['odds'], //添加字段, 申计用的

                    'money' => $tzje,

                );

                $inid_audit = $this->db->insert('un_orders_audit', $ddsqlarr_audit);

                $zjsqlarr= array(

                    'order_num' => $order_no,

                    'user_id' => $uid,

                    'type' => 13,

                    'addtime' => $time,

                    'money' => $tzje,

                    'use_money' => $tempAccount,

                    'remark' => "User bets " . $tzje,

                    'reg_type' => $regType

                );

                $ret = $this->db->insert('un_account_log', $zjsqlarr);

                if (empty($ret)) throw new Exception('Update failed!2');

            }

            $sql = "UPDATE `un_account` SET `money` = money-'{$currentMoney}' WHERE `user_id` = {$uid}"; //出于并发考虑

            $ret = $this->db->query($sql);

            if (empty($ret)) throw new Exception('Update failed!1');

            //ToDo 这里可能是

            if ($inid) {

                $sql = "SELECT MAX(award_state) as state,issue FROM `un_orders` WHERE user_id={$uid} AND room_no={$roomid} AND award_state <> 0 GROUP by issue order by issue desc";

                $sy = $this->db->query($sql);

                $sya = array(1 => 0, 2 => 0);

                if ($sy) {

                    $a = 0;

                    foreach ($sy as $v) {

                        if ($a && $v['state'] == $a) {

                            $sya[$v['state']]++;

                        } elseif ($a && $v['state'] != $a) {

                            break;

                        } else {

                            $sya[$v['state']]++;

                            $a = $v['state'];

                        }

                    }

                }

                $sql = "select count(id) from un_orders WHERE  user_id={$uid} AND room_no={$roomid} AND issue={$info['issue']} AND state=0 AND chase_number = ''";

                $count = $this->db->result($sql);

                $message['commandid'] = 3007;

                $message['uid'] = $uid;

                $message['nickname'] = $username;

                $message['avatar'] = '/'.ltrim($avatar,'/');

                $message['way'] = $way;

                $message['pan_kou'] = $pkArr; //盘口

                $message['issue'] = $info['issue'];

                $message['time'] = date('Y-m-d H:i:s', $time);            //新版时间显示

                $message['money'] = $money;

                $message['total_money'] = array_sum($money);

                $message['total_zushu'] = $totalZu;

                $message['count'] = $count;

                $message['order_no'] = $orderArr;

                $message['lose'] = $honor['status'] ? $sya[1] : '';

                $message['won'] = $honor['status'] ? $sya[2] : '';

                $message['content'] = '';

                $message['sort'] = $honor['sort'];

                $message['honor_status'] = $honor['honor_status'];

                $data = array( //调用双活接口

                    'type' => 'betting_group',

                    'id' => $roomid,

                    'json' => encode($message),

                );

                $send = array('commandid' => 3010, 'money' => convert1(bcsub($account, $currentMoney, 2)));

                $data1 = array( //调用双活接口

                    'type' => 'betting',

                    'id' => $uid,

                    'json' => encode($send),

                );

                if (in_array($regType, array(9))) { //屏蔽游客和机器人

                    Gateway::sendToGroup($roomid, encode($data));

                    Gateway::sendToUid($uid, encode($data1));

                } else {

                    send_home_data($data);

                    send_home_data($data1);

                }

            } else {

                throw new Exception('Update failed!3');

            }

            $this->db->query('COMMIT');

        } catch (Exception $err) {

            $this->db->query('ROLLBACK');

            $send = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "System error, please contact customer service.");

            Gateway::sendToUid($uid, encode($send));

            return false;

        }

        return false;

    }



    /**

     * 投注

     * @param $uid int 用户id

     * @param $way array 玩法

     * @param $money array 投注金额

     * @param $roomid int 房间id

     * @param $lotteryType int 彩种id

     */

    public function http_betting()

    {

        //验证参数

        $this->checkInput($_REQUEST, array('token'));

        $jbtme = microtime(1);

        //接收参数

        $uid = (int)$_REQUEST['user_id'];

        $serTime = $_REQUEST['serTime'];


        //实例化Gateway

        O('Gateway');

        Gateway::$registerAddress = C('Gateway');

        $token = $_REQUEST['token'];

        $uniNo = $_REQUEST['uniNo'];

        $redis = initCacheRedis(); //配置

        $checkToken = $redis->hget('Config:check_token_set','value');

        deinitCacheRedis($redis);

        if(!empty($checkToken)){

            $r = checkToken($token,$uid,$this->db);

            if($r==1){

                echo encode(array(

                    'commandid' => 3038,

                    'is_popup_msg' => '1',

                    'uniNo'=>$uniNo,

                    'content' => "This bet is missing important parameters, please re-betting!",

                ));

                //不返回任何信息, 防止投注别人的帐号

                return false;

            }else if($r==2){

                echo encode(array(

                    'commandid' => 3014,

                    'is_popup_msg' => '1',

                    'uniNo'=>$uniNo,

                    'content' => "Token is invalid, please log in again!",

                ));

                return false;

            }

        }

        //更新token时间

        $now = time();

        $sql = "UPDATE `un_session` SET lastvisit={$now} WHERE user_id={$uid}";

        $this->db->query($sql);

        $way = decode(stripslashes_deep($_REQUEST['way']));

        $money = decode(stripslashes_deep($_REQUEST['money']));

        $single_money = !empty($_REQUEST['single_money'])?decode(stripslashes_deep($_REQUEST['single_money'])):array(); //单注金额

        $roomid = (int)$_REQUEST['room_id'];

        $win_stop = (int)$_REQUEST['win_stop'];

        //改成取Redis

        $redis = initCacheRedis();

        $lotteryType = $redis->hget('allroom:'.$roomid,'lottery_type');

        deinitCacheRedis($redis);

        if(empty($lotteryType)){

            echo encode(array(

                'commandid' => 3038,

                'is_popup_msg' => '1',

                'content' => "Lottery does not exist!",

            ));

        }


        $now = time();

        $timeOut = 10;

        if($now-$serTime>$timeOut){ //超时单

            echo encode(array(

                'commandid' => 3038,

                'is_popup_msg' => '1',

                'uniNo'=>$uniNo,

                'content' => "Order timed out, please try again!",

            ));

            return false;

        }

        //防止刷单

        $redis = initCacheRedis();

        $co_str = 'bet:'.$uid;

        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去

            $redis->expire($co_str,1); //设置它的超时

            deinitCacheRedis($redis);

        }else{

            echo encode(array(

                'commandid' => 3038,

                'is_popup_msg' => '1',

                'uniNo'=>$uniNo,

                'content' => "Betting too frequently, please try again later!",

            ));

            deinitCacheRedis($redis);

            return false;

        }

        //判断参数

        $paramFlag = false;

        if(empty($uid)) $paramFlag = true;

        if(empty($way)) $paramFlag = true;

        if(empty($money)) $paramFlag = true;

        if(empty($roomid)) $paramFlag = true;

        if(empty($lotteryType)) $paramFlag = true;

        //判断参数

        if($paramFlag){

            return false;

        }

        $time = time();



        //验证玩法和金额对应关系

        $lenWay = count($way);

        $lenMoney = count($money);

        $lenSingleMoney = count($single_money);

        if($lenWay != $lenMoney){

            echo json_encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Betting data is incorrect."));

            return false;

        }

        if(in_array($lotteryType,array(7,8)) && $lenWay != $lenSingleMoney){

            echo encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Betting data is incorrect."));

            return false;

        }



        //判断房间彩种

        $roomInfo = D('workerman')->getRedisHashValues("allroom:".$roomid,array('lottery_type','special_way'));

        if($roomInfo['lottery_type'] != $lotteryType) $paramFlag = true;



        //特殊玩法判断

        $whereSql = "";

        $special_way = json_decode($roomInfo['special_way'],true);

        if($special_way['status'] != '1'){

            $whereSql = " AND type <> 3";

        }



        if(in_array($lotteryType,array(7,8)) && empty($single_money)){

            echo encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Incomplete betting information."));

            return false;

        }



        //校验投注金额是否有误

        foreach ($money as $mv){

            if(strpos($mv,'.') !== false && !strpos($mv,'.00')){

                echo encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Wrong bet amount."));

                return false;

            }

        }



        if(!empty($single_money)){

            foreach ($single_money as $mv){

                if(strpos($mv,'.') !== false && !strpos($mv,'.00')){

                    echo encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Wrong bet amount."));

                    return false;

                }

            }

        }



        if(in_array($lotteryType,array(7,8)) && empty($single_money)){

            echo encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Incomplete betting information."));

            return false;

        }



        //查询用户信息(昵称,头像,注册类型)

        $sql = "SELECT username,nickname,avatar,group_id,reg_type FROM `un_user` WHERE `id` = '{$uid}'";

        $res = $this->db->getone($sql);

        if(empty($res) || $paramFlag){

            echo encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Information is missing or has been changed."));

            return;

        };



        $username = empty($res['nickname'])?$res['username']:$res['nickname'];

        //控制昵称显示

        $username = D('workerman')->getNickname($username);

        $avatar = empty($res['avatar'])?'/up_files/room/avatar.png':('/'.ltrim($res['avatar'],'/')); //防止没有/

        $regType = $res['reg_type'];

        $groupid = $res['group_id'];



        //查询用户荣誉等级

        $hbtime = microtime(1);

        $honor = get_level_honor($uid);

        $hetme = microtime(1);

        //获取期号

        $info = $res = D('workerman')->getQihao($lotteryType,$time,$roomid);

        //返回信息

        $message = array(

            'commandid' => 3038,

            'uniNo'=>$uniNo,

            'nickname' => '',

            'content' => $info['msg'],

            'avatar' => '',

            'status' => '1'

        );

        //六合彩期号验证

        if($lotteryType == 7){

            $redis = initCacheRedis();

            $lhcIssue = $redis->get('lhc_issue');

            if(empty($lhcIssue)){

                $sql_issue = "SELECT issue FROM `un_lhc` WHERE lottery_type=7 ORDER BY issue DESC LIMIT 1";

                $reIssue = $this->db->result($sql_issue);

                $redis->set('lhc_issue',$reIssue);

                $lhcIssue = $redis->get('lhc_issue');

            }

            deinitCacheRedis($redis);

            //跨年
            if(strpos(substr($info['issue'], 4),'001')!==false){

                $lhcIssue = date('Y').'000';

            }
            if(($lhcIssue+1) != $info['issue']){ //期号有误

                $message['content'] = 'This betting issue is incorrect, please try again!';

                $message['is_popup_msg'] = '1';

                echo encode($message);

                return false;

            }

        }


        //如果未获取到期号

        if($info['issue'] == 0){

            echo encode($message);

            return;

        }



        //如果处于封盘

        if($info['sealingTim'] >= ($info['date']- $time)){

            $message['content'] = 'Betting time out, you can not bet!';

            $message['is_popup_msg'] = '1';

            echo encode($message);

            return;

        }



        //判断投注的玩法是否是正确的

        $sql = "select way,type from un_odds where lottery_type='{$lotteryType}' and room='{$roomid}'{$whereSql}";

        $res = $this->db->getall($sql);

        if(empty($res)){

            $message['content'] = 'No related lottery is found, betting is prohibited';

            $message['is_popup_msg'] = '1';

            echo encode($message);

            return false;

        }

        $allway = array();

        $num_way = [];

        foreach ($res as $k=>$v){

            $allway[] = $v['way'];

            if ($v['type'] == 1) {

                $num_way[] = $v['way'];

            }

        }

        $hbtime = microtime(1);

        //车号数量控制

        $num_limit = $this->numLimitBetting($lotteryType,$way,$uid,$info['issue']);

        if(!empty($num_limit)){

            $message['content'] = 'Bet failed, the number of '.$num_limit['limitWayStr'].' bets in each issue cannot exceed('.$num_limit['max'].')';

            $message['is_popup_msg'] = '1';

            echo encode($message);

            return false;

        }

        $hetme = microtime(1);

        //投注本期单点数字个数

        $totalZu  = 0;

        foreach ($way as $k=>$v) {

            $totalZu ++;

            $arr =explode('_',$v); //冠亚两号一起时

            if(count($arr)==3){

                $_1 =(int)$arr[1];

                $_2 =(int)$arr[2];

                if($_1<11 && $_2<11 && $_1>0 && $_2>0){

                    $v = $arr[0];

                }

            }

            if(in_array($lotteryType,array(7,8))){ //六合彩彩种

                //常规玩法检验

                if(!in_array($arr[0],array('三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中', '五不中', '六不中', '七不中', '八不中', '九不中', '十不中','二尾连中', '三尾连中', '四尾连中', '二尾连不中', '三尾连不中', '四尾连不中'))){

                    if(!in_array($v, $allway)){

                        $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                        $message['is_popup_msg'] = '1';

                        echo encode($message);

                        return false;

                    }

                }else{ //多注玩法检验

                    //验证是否有重复号码 1,2,3 1,1,3

                    $tmpArr = explode(',',$arr[1]);


                    if(count($tmpArr) != count(array_unique($tmpArr))){

                        $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                        $message['is_popup_msg'] = '1';

                        echo encode($message);

                        return false;

                    }

                    $waysArr = explode(',', $arr[1]); //传进来的玩法

                    $len = count($waysArr);

                    $check_data = $this->lhcCheck($arr[0], $len); //检验个数

                    if (!empty($check_data)) {

                        $message['content'] = $check_data['msg'];

                        $message['is_popup_msg'] = '1';

                        echo encode($message);

                        return false;

                    }



                    if (in_array($arr[0], array('三中二', '三全中', '二全中', '二中特', '特串', '二肖连中', '三肖连中', '四肖连中', '二肖连不中', '三肖连不中', '四肖连不中', '五不中', '六不中', '七不中', '八不中', '九不中', '十不中'))) {

                        //单注金额, 总额和注数关系验证

                        $preArr = array(

                            '三中二' => 3,

                            '三全中' => 3,

                            '二全中' => 2,

                            '二中特' => 2,

                            '特串' => 2,

                            '二肖连中' => 2,

                            '三肖连中' => 3,

                            '四肖连中' => 4,

                            '二肖连不中' => 2,

                            '三肖连不中' => 3,

                            '四肖连不中' => 4,

                            '五不中' => 5,

                            '六不中' => 6,

                            '七不中' => 7,

                            '八不中' => 8,

                            '九不中' => 9,

                            '十不中' => 10,

                        );

                        $zu = $this->zushu($len, $preArr[$arr[0]]);

                        $totalZu = $totalZu + $zu - 1;

                        if ($single_money[$k] * $zu != $money[$k]) { //总额 单注 注数

                            $message['content'] = 'Wrong amount';

                            $message['is_popup_msg'] = '1';

                            echo encode($message);

                            return false;

                        }



                        foreach ($waysArr as $_wv) {

                            if (!in_array($arr[0] . '_' . $_wv, $allway)) {

                                $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                                $message['is_popup_msg'] = '1';

                                echo encode($message);

                                return false;

                            }

                        }

                    }



                    if (in_array($arr[0], array('二尾连中', '三尾连中', '四尾连中', '二尾连不中', '三尾连不中', '四尾连不中'))) { //六合彩多注

                        //单注金额, 总额和注数关系验证

                        $preArr = array(

                            '二尾连中' => 2,

                            '三尾连中' => 3,

                            '四尾连中' => 4,

                            '二尾连不中' => 2,

                            '三尾连不中' => 3,

                            '四尾连不中' => 4,

                        );

                        $zu = $this->zushu($len, $preArr[$arr[0]]);

                        $totalZu = $totalZu + $zu - 1;

                        if ($single_money[$k] * $zu != $money[$k]) { //总额 单注 注数

                            $message['content'] = 'Wrong amount';

                            $message['is_popup_msg'] = '1';

                            echo encode($message);

                            return false;

                        }



                        foreach ($waysArr as $_wv) {

                            if (!in_array($arr[0] . '_' . $_wv . '尾', $allway)) {

                                $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                                $message['is_popup_msg'] = '1';

                                echo encode($message);

                                return false;

                            }

                        }

                    }

                }

            }else{ //非六合彩彩种

                if(!in_array($v, $allway)){

                    $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                    $message['is_popup_msg'] = '1';

                    echo encode($message);

                    return false;

                }

            }

        }

        //游戏币比例

        $RmbRatio = D('workerman')->getConfig("rmbratio",'value');

        $hbtime = microtime(1);

        //投注判断

        $res = $this->getReverseBetting($uid,$groupid,$roomid,$info['issue'],$way,$money,$RmbRatio);

        if($res['control']){

            $message['content'] = $res['content'];

            $message['is_popup_msg'] = '1';

            echo encode($message);

            return;

        }

        $hetme = microtime(1);

        //判断账户余额是否小于当前投注总额

        $currentMoney = array_sum($money);

        $currentMoney = bcdiv($currentMoney,$RmbRatio,2);

        if(!empty(C('db_port'))){ //使用mycat时 查主库数据

            $sql = "/*#mycat:db_type=master*/ SELECT money FROM `un_account` WHERE `user_id` = '{$uid}'"; //要查主库

        }else{

            $sql = "SELECT money FROM `un_account` WHERE `user_id` = '{$uid}'"; //要查主库

        }

        $account = $this->db->result($sql);

        if (bccomp($currentMoney, $account, 2) == 1) {

            $message['content'] = "Your balance is insufficient, please deposit!";

            $message['has_not_money'] = '1';

            $message['is_popup_msg'] = '1';

            echo encode($message);

            return;

        }

        $hhbtime = microtime(1);

        //生成订单

        $this->db->query("START TRANSACTION");

        try {

            $encodeval_model = D('Encodeval');


            if(!empty(C('db_port'))){ //使用mycat时 查主库数据

                $sql = "/*#mycat:db_type=master*/ SELECT money FROM `un_account` WHERE `user_id` = '{$uid}' for update"; //要查主库

            }else{

                $sql = "SELECT money FROM `un_account` WHERE `user_id` = '{$uid}' for update"; //要查主库

            }

            $tempAccount = $this->db->result($sql);

            $orderArr = array();

            foreach ($way as $k => $v){

                unset($order_no); //防止重复流水号

                //获取是否跟投标识

                $ext_a = (int)$_REQUEST['ext_a'] == 1 ? D('workerman')->getRandomString(6) : '' ;

                //后面补0,防止重复

                $order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);

                if(in_array($order_no,$orderArr)){ //防止重复流水号

                    sleep(1);

                    $order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);

                }

                $orderArr[] = $order_no;  //新增

                $tzje = bcdiv($money[$k], $RmbRatio, 2); //投注金额

                $dzje = bcdiv($single_money[$k], $RmbRatio, 2); //单注金额

                $tempAccount = bcsub($tempAccount, $tzje, 2); //当前金额

                $ddsqlarr[] = array(

                    'lottery_type' => $lotteryType,

                    'room_no' => $roomid,

                    'order_no' => $order_no,

                    'user_id' => $uid,

                    'issue' => $info['issue'],

                    'addtime' => $time,

                    'way' => $v,

                    'money' => $tzje,

                    'single_money' => $dzje,

                    'reg_type' => $regType,

                    'win_stop' => $win_stop,

                    'ext_b' => $ext_a,

                    //生成校验值

                    'whats_val' => $encodeval_model->mixVal($order_no, $tzje, $time, $v),

                );

                $ddsqlarr_audit[] = array(

                    'lottery_type' => $lotteryType,

                    'room_no' => $roomid,

                    'order_no' => $order_no,

                    'user_id' => $uid,

                    'issue' => $info['issue'],

                    'addtime' => $time,

                    'way' => $v,

                    'money' => $tzje,

                );

                $zjsqlarr[] = array(

                    'order_num' => $order_no,

                    'user_id' => $uid,

                    'type' => 13,

                    'addtime' => $time,

                    'money' => $tzje,

                    'use_money' => $tempAccount,

                    'remark'=>"User bets ".$tzje,

                    'reg_type' => $regType

                );

            }


            //再查余额

            $sql_acc = "SELECT money-{$currentMoney} AS money FROM un_account WHERE user_id={$uid}";

            $acc_res = $this->db->result($sql_acc);

            if($acc_res<0) throw new Exception('Update failed!5');

            $sql = "UPDATE `un_account` SET `money` = money-'{$currentMoney}' WHERE `user_id` = {$uid}"; //出于并发考虑

            $ret = $this->db->query($sql);

            if (empty($ret)) throw new Exception('Update failed!1');

            $inid = $this->db->insert('un_orders', $ddsqlarr);

            $inid_audit = $this->db->insert('un_orders_audit', $ddsqlarr_audit);

            if ($inid){

                $ret = $this->db->insert('un_account_log',$zjsqlarr );

                if (empty($ret)) throw new Exception('Update failed!2');

                $sql = "SELECT MAX(award_state) as state,issue FROM `un_orders` WHERE user_id={$uid} AND room_no={$roomid} AND award_state <> 0 GROUP by issue order by issue desc";

                $sy = $this->db->query($sql);

                $sya = array(1 => 0, 2 => 0);

                if ($sy) {

                    $a = 0;

                    foreach ($sy as $v) {

                        if ($a && $v['state'] == $a) {

                            $sya[$v['state']]++;

                        } elseif ($a && $v['state'] != $a) {

                            break;

                        } else {

                            $sya[$v['state']]++;

                            $a = $v['state'];

                        }

                    }

                }

                if(!empty(C('db_port'))) { //使用mycat时 查主库数据

                    $sql = "/*#mycat:db_type=master*/ select count(id) from un_orders WHERE  user_id={$uid} AND room_no={$roomid} AND issue={$info['issue']} AND state=0 AND chase_number = ''";

                }else{

                    $sql = "select count(id) from un_orders WHERE  user_id={$uid} AND room_no={$roomid} AND issue={$info['issue']} AND state=0 AND chase_number = ''";

                }

                $count = $this->db->result($sql);

                $message['commandid'] = 3007;

                $message['uid'] = $uid;

                $message['uniNo'] = $uniNo;

                $message['nickname'] = $username;

                $message['avatar'] = '/'.ltrim($avatar,'/');

                $message['way'] = $way;

                $message['issue'] = $info['issue'];

                $message['time'] = date('Y-m-d H:i', $time);            //新版时间显示

                $message['money'] = $money;

                $message['total_money'] = array_sum($money);

                $message['single_money'] = $single_money;  //六合彩用的单注

                $message['total_zushu'] = $totalZu;

                $message['count'] = $count;

                $message['order_no'] = $orderArr;

                $message['lose'] = $honor['status']?$sya[1]:'';

                $message['won'] = $honor['status']?$sya[2]:'';

                $message['content'] = '';

                $message['sort'] = $honor['sort'];

                $message['honor_status'] = $honor['honor_status'];

                $data=array( //调用双活接口

                    'type'=>'betting_group',

                    'id'=>$roomid,

                    'json'=>encode($message),

                );

                $send = array('commandid' => 3010, 'money' => convert1(bcsub($account, $currentMoney, 2)));

                $data1=array( //调用双活接口

                    'type'=>'betting',

                    'id'=>$uid,

                    'json'=>encode($send),

                );

                if(in_array($regType,array(/*11, 暂不含11, 过滤掉假人*/9))){ //屏蔽游客和机器人

                    Gateway::sendToGroup($roomid, json_encode($data));

                    Gateway::sendToUid($uid, json_encode($data1));

                }else{

                    $message['current_money'] = convert1(bcsub($account, $currentMoney, 2));

                    echo encode($message);

                    unset($message['current_money']);

                    Gateway::sendToGroup($roomid, encode($message));

                }

            }else{

                throw new Exception('Update failed!3');

            }

            $this->db->query('COMMIT');

            $hetme = microtime(1);

            $jetme = microtime(1);

            //删除刷单间隔标识

            $redis = initCacheRedis();

            $redis->del($co_str);

            deinitCacheRedis($redis);

            return 1;

        } catch (Exception $err) {

            $this->db->query('ROLLBACK');

            $send = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "System error, please contact customer service.");

            echo encode($send);

            return 0;

        }

    }





    /**

     * 投注

     * @param $uid int 用户id

     * @param $way array 玩法

     * @param $money array 投注金额

     * @param $roomid int 房间id

     * @param $lotteryType int 彩种id

     */

    public function betting_new()

    {

        //验证参数

        $this->checkInput($_REQUEST, array('token'));

        $jbtme = microtime(1);

        //接收参数

        $uid = (int)$_REQUEST['user_id'];

        $serTime = $_REQUEST['serTime'];

        //实例化Gateway

        O('Gateway');

        Gateway::$registerAddress = C('Gateway');

        $token = $_REQUEST['token'];

        $uniNo = $_REQUEST['uniNo'];

        $redis = initCacheRedis(); //配置

        $checkToken = $redis->hget('Config:check_token_set','value');

        deinitCacheRedis($redis);

        if(!empty($checkToken)){

            $r = checkToken($token,$uid,$this->db);

            if($r==1){

                Gateway::sendToUid($uid, encode(array(

                    'commandid' => 3038,

                    'is_popup_msg' => '1',

                    'uniNo'=>$uniNo,

                    'content' => "This bet is missing important parameters, please re-betting",

                )));

                //不返回任何信息, 防止投注别人的帐号

                return false;

            }else if($r==2){

                Gateway::sendToUid($uid, encode(array(

                    'commandid' => 3014,

                    'is_popup_msg' => '1',

                    'uniNo'=>$uniNo,

                    'content' => "Token is invalid, please log in again",

                )));

                return false;

            }

        }

        //更新token时间

        $now = time();

        $sql = "UPDATE `un_session` SET lastvisit={$now} WHERE user_id={$uid}";

        $this->db->query($sql);

        $way = decode(stripslashes_deep($_REQUEST['way']));

        $money = decode(stripslashes_deep($_REQUEST['money']));

        $single_money = !empty($_REQUEST['single_money'])?decode(stripslashes_deep($_REQUEST['single_money'])):array(); //单注金额

        $roomid = (int)$_REQUEST['room_id'];

        $win_stop = (int)$_REQUEST['win_stop'];

        //改成取Redis

        $redis = initCacheRedis();

        $lotteryType = $redis->hget('allroom:'.$roomid,'lottery_type');

        deinitCacheRedis($redis);

        if(empty($lotteryType)){

            Gateway::sendToUid($uid, encode(array(

                'commandid' => 3038,

                'is_popup_msg' => '1',

                'content' => "彩种不存在",

            )));

        }

        $now = time();

        $uniNo = $_REQUEST['uniNo'];

        $timeOut = 10;

        if($now-$serTime>$timeOut){ //超时单

            Gateway::sendToUid($uid, encode(array(

                'commandid' => 3038,

                'is_popup_msg' => '1',

                'uniNo'=>$uniNo,

                'content' => "订单超时,请重试!",

            )));

            return false;

        }

        //防止刷单

        $redis = initCacheRedis();

        $co_str = 'bet:'.$uid;

        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去

            $redis->expire($co_str,1); //设置它的超时

            deinitCacheRedis($redis);

        }else{

            deinitCacheRedis($redis);

            return false;

        }

        //判断参数

        $paramFlag = false;

        if(empty($uid)) $paramFlag = true;

        if(empty($way)) $paramFlag = true;

        if(empty($money)) $paramFlag = true;

        if(empty($roomid)) $paramFlag = true;

        if(empty($lotteryType)) $paramFlag = true;

        //判断参数

        if($paramFlag){

            return false;

        }

        $time = time();

        //验证玩法和金额对应关系

        $lenWay = count($way);

        $lenMoney = count($money);

        $lenSingleMoney = count($single_money);

        if($lenWay != $lenMoney){

            Gateway::sendToUid($uid, json_encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Betting data is incorrect.")));

            return false;

        }

        if(in_array($lotteryType,array(7,8)) && $lenWay != $lenSingleMoney){

            Gateway::sendToUid($uid, json_encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Betting data is incorrect.")));

            return false;

        }

        //判断房间彩种

        $roomInfo = D('workerman')->getRedisHashValues("allroom:".$roomid,array('lottery_type','special_way'));

        if($roomInfo['lottery_type'] != $lotteryType) $paramFlag = true;

        //特殊玩法判断

        $whereSql = "";

        $special_way = json_decode($roomInfo['special_way'],true);

        if($special_way['status'] != '1'){

            $whereSql = " AND type <> 3";

        }

        if(in_array($lotteryType,array(7,8)) && empty($single_money)){

            Gateway::sendToUid($uid, json_encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Incomplete betting information.")));

            return false;

        }

        //校验投注金额是否有误

        foreach ($money as $mv){

            if(strpos($mv,'.') !== false && !strpos($mv,'.00')){

                Gateway::sendToUid($uid, json_encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Wrong bet amount.")));

                return false;

            }

        }

        if(!empty($single_money)){

            foreach ($single_money as $mv){

                if(strpos($mv,'.') !== false && !strpos($mv,'.00')){

                    Gateway::sendToUid($uid, json_encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Wrong bet amount.")));

                    return false;

                }

            }

        }

        if(in_array($lotteryType,array(7,8)) && empty($single_money)){

            Gateway::sendToUid($uid, json_encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Incomplete betting information.")));

            return false;

        }

        //查询用户信息(昵称,头像,注册类型)

        $sql = "SELECT username,nickname,avatar,group_id,reg_type FROM `un_user` WHERE `id` = '{$uid}'";

        $res = $this->db->getone($sql);

        if(empty($res) || $paramFlag){

            Gateway::sendToUid($uid, json_encode(array('commandid' => 3038, 'uniNo'=>$uniNo, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Information is missing or has been changed.")));

            return;

        };

        $username = empty($res['nickname'])?$res['username']:$res['nickname'];

        //控制昵称显示

        $username = D('workerman')->getNickname($username);

        $avatar = empty($res['avatar'])?'/up_files/room/avatar.png':('/'.ltrim($res['avatar'],'/')); //防止没有/

        $regType = $res['reg_type'];

        $groupid = $res['group_id'];

        //查询用户荣誉等级

        $hbtime = microtime(1);

        $honor = get_level_honor($uid);

        $hetme = microtime(1);

        //获取期号

        $info = $res = D('workerman')->getQihao($lotteryType,$time,$roomid);

        //返回信息

        $message = array(

            'commandid' => 3038,

            'uniNo'=>$uniNo,

            'nickname' => '',

            'content' => $info['msg'],

            'avatar' => '',

            'status' => '1'

        );

        //六合彩期号验证

        if($lotteryType == 7){

            $redis = initCacheRedis();

            $lhcIssue = $redis->get('lhc_issue');

            if(empty($lhcIssue)){

                $sql_issue = "SELECT issue FROM `un_lhc` WHERE lottery_type=7 ORDER BY issue DESC LIMIT 1";

                $reIssue = $this->db->result($sql_issue);

                $redis->set('lhc_issue',$reIssue);

                $lhcIssue = $redis->get('lhc_issue');

            }

            deinitCacheRedis($redis);

            //跨年

            if(strpos($info['issue'],'001')!==false){

                $lhcIssue = date('Y').'000';

            }

            if(($lhcIssue+1) != $info['issue']){ //期号有误

                $message['content'] = 'The current betting issue number is incorrect, please try again';

                $message['is_popup_msg'] = '1';

                Gateway::sendToUid($uid, json_encode($message));

                return false;

            }

        }

        //如果未获取到期号

        if($info['issue'] == 0){

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }

        //如果处于封盘

        if($info['sealingTim'] >= ($info['date']- $time)){

            $message['content'] = 'It is not within the betting time, can not bet';

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }

        //判断投注的玩法是否是正确的

        $sql = "select way,type from un_odds where lottery_type='{$lotteryType}' and room='{$roomid}'{$whereSql}";

        $res = $this->db->getall($sql);

        if(empty($res)){

            $message['content'] = 'No related method for this lottery, Stop betting';

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, json_encode($message));

            return false;

        }

        $allway = array();

        $num_way = [];

        foreach ($res as $k=>$v){

            $allway[] = $v['way'];

            if ($v['type'] == 1) {

                $num_way[] = $v['way'];

            }

        }

        $hbtime = microtime(1);

        //车号数量控制

        $num_limit = $this->numLimitBetting($lotteryType,$way,$uid,$info['issue']);

        if(!empty($num_limit)){

            $message['content'] = 'Bet failed, the number of '.$num_limit['limitWayStr'].' bets in each issue cannot exceed('.$num_limit['max'].')';

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, encode($message));

            return false;

        }

        $hetme = microtime(1);

        //投注本期单点数字个数

        $totalZu  = 0;

        foreach ($way as $k=>$v) {

            $totalZu ++;

            $arr =explode('_',$v); //冠亚两号一起时

            if(count($arr)==3){

                $_1 =(int)$arr[1];

                $_2 =(int)$arr[2];

                if($_1<11 && $_2<11 && $_1>0 && $_2>0){

                    $v = $arr[0];

                }

            }

            if(in_array($lotteryType,array(7,8))){ //六合彩彩种

                //常规玩法检验

                if(!in_array($arr[0],array('三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中', '五不中', '六不中', '七不中', '八不中', '九不中', '十不中','二尾连中', '三尾连中', '四尾连中', '二尾连不中', '三尾连不中', '四尾连不中'))){

                    if(!in_array($v, $allway)){

                        $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                        $message['is_popup_msg'] = '1';

                        Gateway::sendToUid($uid, json_encode($message));

                        return false;

                    }

                }else{ //多注玩法检验

                    //验证是否有重复号码 1,2,3 1,1,3

                    $tmpArr = explode(',',$arr[1]);

                    if(count($tmpArr) != count(array_unique($tmpArr))){

                        $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                        $message['is_popup_msg'] = '1';

                        Gateway::sendToUid($uid, json_encode($message));

                        return false;

                    }

                    $waysArr = explode(',', $arr[1]); //传进来的玩法

                    $len = count($waysArr);

                    $check_data = $this->lhcCheck($arr[0], $len); //检验个数

                    if (!empty($check_data)) {

                        $message['content'] = $check_data['msg'];

                        $message['is_popup_msg'] = '1';

                        Gateway::sendToUid($uid, json_encode($message));

                        return false;

                    }

                    if (in_array($arr[0], array('三中二', '三全中', '二全中', '二中特', '特串', '二肖连中', '三肖连中', '四肖连中', '二肖连不中', '三肖连不中', '四肖连不中', '五不中', '六不中', '七不中', '八不中', '九不中', '十不中'))) {

                        //单注金额, 总额和注数关系验证

                        $preArr = array(

                            '三中二' => 3,

                            '三全中' => 3,

                            '二全中' => 2,

                            '二中特' => 2,

                            '特串' => 2,

                            '二肖连中' => 2,

                            '三肖连中' => 3,

                            '四肖连中' => 4,

                            '二肖连不中' => 2,

                            '三肖连不中' => 3,

                            '四肖连不中' => 4,

                            '五不中' => 5,

                            '六不中' => 6,

                            '七不中' => 7,

                            '八不中' => 8,

                            '九不中' => 9,

                            '十不中' => 10,

                        );

                        $zu = $this->zushu($len, $preArr[$arr[0]]);

                        $totalZu = $totalZu + $zu - 1;

                        if ($single_money[$k] * $zu != $money[$k]) { //总额 单注 注数

                            $message['content'] = 'Wrong amount';

                            $message['is_popup_msg'] = '1';

                            Gateway::sendToUid($uid, json_encode($message));

                            return false;

                        }

                        foreach ($waysArr as $_wv) {

                            if (!in_array($arr[0] . '_' . $_wv, $allway)) {

                                $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                                $message['is_popup_msg'] = '1';

                                Gateway::sendToUid($uid, json_encode($message));

                                return false;

                            }

                        }

                    }

                    if (in_array($arr[0], array('二尾连中', '三尾连中', '四尾连中', '二尾连不中', '三尾连不中', '四尾连不中'))) { //六合彩多注

                        //单注金额, 总额和注数关系验证

                        $preArr = array(

                            '二尾连中' => 2,

                            '三尾连中' => 3,

                            '四尾连中' => 4,

                            '二尾连不中' => 2,

                            '三尾连不中' => 3,

                            '四尾连不中' => 4,

                        );

                        $zu = $this->zushu($len, $preArr[$arr[0]]);

                        $totalZu = $totalZu + $zu - 1;

                        if ($single_money[$k] * $zu != $money[$k]) { //总额 单注 注数

                            $message['content'] = 'Wrong amount';

                            $message['is_popup_msg'] = '1';

                            Gateway::sendToUid($uid, json_encode($message));

                            return false;

                        }

                        foreach ($waysArr as $_wv) {

                            if (!in_array($arr[0] . '_' . $_wv . '尾', $allway)) {

                                $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                                $message['is_popup_msg'] = '1';

                                Gateway::sendToUid($uid, json_encode($message));

                                return false;

                            }

                        }

                    }

                }

            }else{ //非六合彩彩种

                if(!in_array($v, $allway)){

                    $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                    $message['is_popup_msg'] = '1';

                    Gateway::sendToUid($uid, json_encode($message));

                    return false;

                }

            }

        }

        //游戏币比例

        $RmbRatio = D('workerman')->getConfig("rmbratio",'value');

        $hbtime = microtime(1);

        //投注判断

        $res = $this->getReverseBetting($uid,$groupid,$roomid,$info['issue'],$way,$money,$RmbRatio);

        if($res['control']){

            $message['content'] = $res['content'];

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }

        $hetme = microtime(1);

        //判断账户余额是否小于当前投注总额

        $currentMoney = array_sum($money);

        $currentMoney = bcdiv($currentMoney,$RmbRatio,2);

        if(!empty(C('db_port'))){ //使用mycat时 查主库数据

            $sql = "/*#mycat:db_type=master*/ SELECT money FROM `un_account` WHERE `user_id` = '{$uid}'"; //要查主库

        }else{

            $sql = "SELECT money FROM `un_account` WHERE `user_id` = '{$uid}'"; //要查主库

        }

        $account = $this->db->result($sql);

        if (bccomp($currentMoney, $account, 2) == 1) {

            $message['content'] = "Your balance is not enough, please deposit";

            $message['has_not_money'] = '1';

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }

        $hhbtime = microtime(1);

        //生成订单

        $this->db->query("START TRANSACTION");

        try {

            $encodeval_model = D('Encodeval');

            $tempAccount = $account;

            $orderArr = array();

            foreach ($way as $k => $v){

                unset($order_no); //防止重复流水号

                //获取是否跟投标识

                $ext_a = (int)$_REQUEST['ext_a'] == 1 ? D('workerman')->getRandomString(6) : '' ;

                //后面补0,防止重复

                $order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);

                if(in_array($order_no,$orderArr)){ //防止重复流水号

                    sleep(1);

                    $order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);

                }

                $orderArr[] = $order_no;  //新增

                $tzje = bcdiv($money[$k], $RmbRatio, 2); //投注金额

                $dzje = bcdiv($single_money[$k], $RmbRatio, 2); //单注金额

                $tempAccount = bcsub($tempAccount, $tzje, 2); //当前金额

                $ddsqlarr[] = array(

                    'lottery_type' => $lotteryType,

                    'room_no' => $roomid,

                    'order_no' => $order_no,

                    'user_id' => $uid,

                    'issue' => $info['issue'],

                    'addtime' => $time,

                    'way' => $v,

                    'money' => $tzje,

                    'single_money' => $dzje,

                    'reg_type' => $regType,

                    'win_stop' => $win_stop,

                    'ext_b' => $ext_a,

                    //生成校验值

                    'whats_val' => $encodeval_model->mixVal($order_no, $tzje, $time, $v),

                );

                $ddsqlarr_audit[] = array(

                    'lottery_type' => $lotteryType,

                    'room_no' => $roomid,

                    'order_no' => $order_no,

                    'user_id' => $uid,

                    'issue' => $info['issue'],

                    'addtime' => $time,

                    'way' => $v,

                    'money' => $tzje,

                );

                $zjsqlarr[] = array(

                    'order_num' => $order_no,

                    'user_id' => $uid,

                    'type' => 13,

                    'addtime' => $time,

                    'money' => $tzje,

                    'use_money' => $tempAccount,

                    'remark'=>"User bets ".$tzje,

                    'reg_type' => $regType

                );

            }

            //再查余额

            $sql_acc = "SELECT money-{$currentMoney} AS money FROM un_account WHERE user_id={$uid}";

            $acc_res = $this->db->result($sql_acc);

            if($acc_res<0) throw new Exception('Update failed!5');

            $sql = "UPDATE `un_account` SET `money` = money-'{$currentMoney}' WHERE `user_id` = {$uid}"; //出于并发考虑

            $ret = $this->db->query($sql);

            if (empty($ret)) throw new Exception('Update failed!1');

            $inid = $this->db->insert('un_orders', $ddsqlarr);

            $inid_audit = $this->db->insert('un_orders_audit', $ddsqlarr_audit);

            if ($inid){

                $ret = $this->db->insert('un_account_log',$zjsqlarr );

                if (empty($ret)) throw new Exception('Update failed!2');

                $sql = "SELECT MAX(award_state) as state,issue FROM `un_orders` WHERE user_id={$uid} AND room_no={$roomid} AND award_state <> 0 GROUP by issue order by issue desc";

                $sy = $this->db->query($sql);

                $sya = array(1 => 0, 2 => 0);

                if ($sy) {

                    $a = 0;

                    foreach ($sy as $v) {

                        if ($a && $v['state'] == $a) {

                            $sya[$v['state']]++;

                        } elseif ($a && $v['state'] != $a) {

                            break;

                        } else {

                            $sya[$v['state']]++;

                            $a = $v['state'];

                        }

                    }

                }

                if(!empty(C('db_port'))) { //使用mycat时 查主库数据

                    $sql = "/*#mycat:db_type=master*/ select count(id) from un_orders WHERE  user_id={$uid} AND room_no={$roomid} AND issue={$info['issue']} AND state=0 AND chase_number = ''";

                }else{

                    $sql = "select count(id) from un_orders WHERE  user_id={$uid} AND room_no={$roomid} AND issue={$info['issue']} AND state=0 AND chase_number = ''";

                }

                $count = $this->db->result($sql);

                $message['commandid'] = 3007;

                $message['uid'] = $uid;

                $message['uniNo'] = $uniNo;

                $message['nickname'] = $username;

                $message['avatar'] = '/'.ltrim($avatar,'/');

                $message['way'] = $way;

                $message['issue'] = $info['issue'];

                $message['time'] = date('Y-m-d H:i', $time);            //新版时间显示

                $message['money'] = $money;

                $message['total_money'] = array_sum($money);

                $message['single_money'] = $single_money;  //六合彩用的单注

                $message['total_zushu'] = $totalZu;

                $message['count'] = $count;

                $message['order_no'] = $orderArr;

                $message['lose'] = $honor['status']?$sya[1]:'';

                $message['won'] = $honor['status']?$sya[2]:'';

                $message['content'] = '';

                $message['sort'] = $honor['sort'];

                $message['honor_status'] = $honor['honor_status'];

                $data=array( //调用双活接口

                    'type'=>'betting_group',

                    'id'=>$roomid,

                    'json'=>encode($message),

                );

                $send = array('commandid' => 3010, 'money' => convert1(bcsub($account, $currentMoney, 2)));

                $data1=array( //调用双活接口

                    'type'=>'betting',

                    'id'=>$uid,

                    'json'=>encode($send),

                );

                if(in_array($regType,array(/*11, 暂不含11, 过滤掉假人*/9))){ //屏蔽游客和机器人

                    Gateway::sendToGroup($roomid, json_encode($data));

                    Gateway::sendToUid($uid, json_encode($data1));

                }else{

                    Gateway::sendToGroup($roomid, encode($message));

                    Gateway::sendToUid($uid, encode($send));

                }

            }else{

                throw new Exception('Update failed!3');

            }

            $this->db->query('COMMIT');

            $hetme = microtime(1);

            $jetme = microtime(1);

            //删除刷单间隔标识

            $redis = initCacheRedis();

            $redis->del($co_str);

            deinitCacheRedis($redis);

            return 1;

        } catch (Exception $err) {

            $this->db->query('ROLLBACK');

            $send = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "System error, please contact customer service.");

            Gateway::sendToUid($uid, json_encode($send));

            return 0;

        }

    }

    /**

     * 投注

     * @param $uid int 用户id

     * @param $way array 玩法

     * @param $money array 投注金额

     * @param $roomid int 房间id

     * @param $lotteryType int 彩种id

     */

    public function betting()

    {

        $jbtme = microtime(1);

        //验证签名

        $res = verificationSignature();

        if($res['status'] !== "success"){

            @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 签名验证失败(投注): '.json_encode($res,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);

            if($res['code'] == 3){

                ErrorCode::errorResponse(999998,"Request timed out, please make sure your mobile phone system time is: Beijing (China) time, refresh and try again!");

            }

            ErrorCode::errorResponse(999999,"Signing failed, please make sure the app is the latest version and try again!");

        }

        //接收参数

        $uid = (int)$_REQUEST['userid'];

        $token = $_REQUEST['token'];

        $uniNo = $_REQUEST['uniNo'];

        $redis = initCacheRedis(); //配置

        $checkToken = $redis->hget('Config:check_token_set','value');

        deinitCacheRedis($redis);

        if(!empty($checkToken)){

            $r = checkToken($token,$uid,$this->db);

            if($r==1){

                echo encode(array(

                    'commandid' => 3038,

                    'is_popup_msg' => '1',

                    'uniNo'=>$uniNo,

                    'content' => "This bet is missing important parameters, please re-betting",

                ));

                //不返回任何信息, 防止投注别人的帐号

                return false;

            }else if($r==2){

                echo encode(array(

                    'commandid' => 3014,

                    'is_popup_msg' => '1',

                    'uniNo'=>$uniNo,

                    'content' => "Token is invalid, please log in again",

                ));

                return false;

            }

        }

        //更新token时间

        $now = time();

        $sql = "UPDATE `un_session` SET lastvisit={$now} WHERE user_id={$uid}";

        $this->db->query($sql);


        $way = json_decode(stripslashes_deep($_REQUEST['way']),true);

        $money = json_decode(stripslashes_deep($_REQUEST['money']),true);

        $single_money = !empty($_REQUEST['single_money'])?decode($_REQUEST['single_money']):array(); //单注金额

        $roomid = (int)$_REQUEST['roomid'];

        $win_stop = (int)$_REQUEST['win_stop'];

        $lotteryType = (int)$_REQUEST['lotteryType'];



        //防止刷单

        $redis = initCacheRedis();


        $co_str = 'bet:'.$uid;


        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去

            $redis->expire($co_str,3); //设置它的超时

            deinitCacheRedis($redis);

        }else{

            deinitCacheRedis($redis);

            return false;

        }



        //判断参数

        $paramFlag = false;

        if(empty($uid)) $paramFlag = true;

        if(empty($way)) $paramFlag = true;

        if(empty($money)) $paramFlag = true;

        if(empty($roomid)) $paramFlag = true;

        if(empty($lotteryType)) $paramFlag = true;



        //判断参数

        if($paramFlag){

            return;

        }





        $time = time();

        //足彩走别的分支

        if($lotteryType==12){

            $this->football_beting($lotteryType,$paramFlag,$uid,$way,$money,$roomid,$time); //足彩投注

            //删除刷单间隔标识

            $redis = initCacheRedis();

            $redis->del($co_str);

            deinitCacheRedis($redis);

            return false;

        }



        //实例化Gateway

        $Gateway = O('Gateway');

        $Gateway::$registerAddress = C('Gateway');

        //验证玩法和金额对应关系

        $lenWay = count($way);

        $lenMoney = count($money);

        $lenSingleMoney = count($single_money);

        if($lenWay != $lenMoney){

            Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Betting data is incorrect.")));

            return false;

        }

        if(in_array($lotteryType,array(7,8)) && $lenWay != $lenSingleMoney){

            Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Betting data is incorrect.")));

            return false;

        }

        //判断房间彩种

        $roomInfo = D('workerman')->getRedisHashValues("allroom:".$roomid,array('lottery_type','special_way'));

        if($roomInfo['lottery_type'] != $lotteryType) $paramFlag = true;

        //特殊玩法判断

        $whereSql = "";

        $special_way = json_decode($roomInfo['special_way'],true);

        if($special_way['status'] != '1'){

            $whereSql = " AND type <> 3";

        }



        if(in_array($lotteryType,array(7,8)) && empty($single_money)){

            Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Incomplete betting information.")));

            return false;

        }



        //校验投注金额是否有误

        foreach ($money as $mv){

            if(strpos($mv,'.') !== false && !strpos($mv,'.00')){

                Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Wrong bet amount.")));

                return false;

            }

        }



        if(!empty($single_money)){

            foreach ($single_money as $mv){

                if(strpos($mv,'.') !== false && !strpos($mv,'.00')){

                    Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Wrong bet amount.")));

                    return false;

                }

            }

        }



        if(in_array($lotteryType,array(7,8)) && empty($single_money)){

            Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Incomplete betting information.")));

            return false;

        }



        //查询用户信息(昵称,头像,注册类型)

        $sql = "SELECT username,nickname,avatar,group_id,reg_type FROM `un_user` WHERE `id` = '{$uid}'";

        $res = $this->db->getone($sql);

        if(empty($res) || $paramFlag){

            Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Information is missing or has been changed.")));

            return;

        };



        $username = empty($res['nickname'])?$res['username']:$res['nickname'];

        //控制昵称显示

        $username = D('workerman')->getNickname($username);

        $avatar = empty($res['avatar'])?'/up_files/room/avatar.png':$res['avatar'];

        $regType = $res['reg_type'];

        $groupid = $res['group_id'];



        //查询用户荣誉等级

        $hbtime = microtime(1);

        $honor = get_level_honor($uid);

        $hetme = microtime(1);

        //获取期号

        $info = $res = D('workerman')->getQihao($lotteryType,$time,$roomid);

        //返回信息

        $message = array(

            'commandid' => 3004,

            'nickname' => '',

            'content' => $info['msg'],

            'avatar' => '',

            'status' => '1'

        );



        //六合彩期号验证

        if($lotteryType == 7){

            $redis = initCacheRedis();

            $lhcIssue = $redis->get('lhc_issue');

            if(empty($lhcIssue)){

                $sql_issue = "SELECT issue FROM `un_lhc` WHERE lottery_type=7 ORDER BY issue DESC LIMIT 1";

                $reIssue = $this->db->result($sql_issue);

                $redis->set('lhc_issue',$reIssue);

                $lhcIssue = $redis->get('lhc_issue');

            }

            deinitCacheRedis($redis);

            //跨年

            if(strpos($info['issue'],'001')!==false){

                $lhcIssue = date('Y').'000';

            }

            if(($lhcIssue+1) != $info['issue']){ //期号有误

                $message['content'] = 'The current betting issue number is incorrect, please try again';

                $message['is_popup_msg'] = '1';

                Gateway::sendToUid($uid, json_encode($message));

                return false;

            }

        }



        //如果未获取到期号

        if($info['issue'] == 0){

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }



        //如果处于封盘

        if($info['sealingTim'] >= ($info['date']- $time)){

            $message['content'] = 'It is not within the betting time, can not bet';

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }



        //判断投注的玩法是否是正确的

        $sql = "select way,type from un_odds where lottery_type='{$lotteryType}' and room='{$roomid}'{$whereSql}";

        $res = $this->db->getall($sql);

        if(empty($res)){

            $message['content'] = 'No related method for this lottery, Stop betting';

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, json_encode($message));

            return false;

        }



        $allway = array();

        $num_way = [];

        foreach ($res as $k=>$v){

            $allway[] = $v['way'];

            if ($v['type'] == 1) {

                $num_way[] = $v['way'];

            }

        }



        $hbtime = microtime(1);



        //车号数量控制

        $num_limit = $this->numLimitBetting($lotteryType,$way,$uid,$info['issue']);

        if(!empty($num_limit)){

            $message['content'] = 'Bet failed, the number of '.$num_limit['limitWayStr'].' bets in each issue cannot exceed('.$num_limit['max'].')';

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, encode($message));

            return false;

        }

        $hetme = microtime(1);

        //投注本期单点数字个数

        $totalZu  = 0;

        foreach ($way as $k=>$v) {

            $totalZu ++;

            $arr =explode('_',$v); //冠亚两号一起时

            if(count($arr)==3){

                $_1 =(int)$arr[1];

                $_2 =(int)$arr[2];

                if($_1<11 && $_2<11 && $_1>0 && $_2>0){

                    $v = $arr[0];

                }

            }

            if(in_array($lotteryType,array(7,8))){ //六合彩彩种

                //常规玩法检验

                if(!in_array($arr[0],array('三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中', '五不中', '六不中', '七不中', '八不中', '九不中', '十不中','二尾连中', '三尾连中', '四尾连中', '二尾连不中', '三尾连不中', '四尾连不中'))){

                    if(!in_array($v, $allway)){

                        $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                        $message['is_popup_msg'] = '1';

                        Gateway::sendToUid($uid, json_encode($message));

                        return false;

                    }

                }else{ //多注玩法检验

                    //验证是否有重复号码 1,2,3 1,1,3

                    $tmpArr = explode(',',$arr[1]);

                    if(count($tmpArr) != count(array_unique($tmpArr))){

                        $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                        $message['is_popup_msg'] = '1';

                        Gateway::sendToUid($uid, json_encode($message));

                        return false;

                    }

                    $waysArr = explode(',', $arr[1]); //传进来的玩法

                    $len = count($waysArr);

                    $check_data = $this->lhcCheck($arr[0], $len); //检验个数

                    if (!empty($check_data)) {

                        $message['content'] = $check_data['msg'];

                        $message['is_popup_msg'] = '1';

                        Gateway::sendToUid($uid, json_encode($message));

                        return false;

                    }



                    if (in_array($arr[0], array('三中二', '三全中', '二全中', '二中特', '特串', '二肖连中', '三肖连中', '四肖连中', '二肖连不中', '三肖连不中', '四肖连不中', '五不中', '六不中', '七不中', '八不中', '九不中', '十不中'))) {

                        //单注金额, 总额和注数关系验证

                        $preArr = array(

                            '三中二' => 3,

                            '三全中' => 3,

                            '二全中' => 2,

                            '二中特' => 2,

                            '特串' => 2,

                            '二肖连中' => 2,

                            '三肖连中' => 3,

                            '四肖连中' => 4,

                            '二肖连不中' => 2,

                            '三肖连不中' => 3,

                            '四肖连不中' => 4,

                            '五不中' => 5,

                            '六不中' => 6,

                            '七不中' => 7,

                            '八不中' => 8,

                            '九不中' => 9,

                            '十不中' => 10,

                        );

                        $zu = $this->zushu($len, $preArr[$arr[0]]);

                        $totalZu = $totalZu + $zu - 1;

                        if ($single_money[$k] * $zu != $money[$k]) { //总额 单注 注数

                            $message['content'] = 'Wrong amount';

                            $message['is_popup_msg'] = '1';

                            Gateway::sendToUid($uid, json_encode($message));

                            return false;

                        }



                        foreach ($waysArr as $_wv) {

                            if (!in_array($arr[0] . '_' . $_wv, $allway)) {

                                $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                                $message['is_popup_msg'] = '1';

                                Gateway::sendToUid($uid, json_encode($message));

                                return false;

                            }

                        }

                    }



                    if (in_array($arr[0], array('二尾连中', '三尾连中', '四尾连中', '二尾连不中', '三尾连不中', '四尾连不中'))) { //六合彩多注

                        //单注金额, 总额和注数关系验证

                        $preArr = array(

                            '二尾连中' => 2,

                            '三尾连中' => 3,

                            '四尾连中' => 4,

                            '二尾连不中' => 2,

                            '三尾连不中' => 3,

                            '四尾连不中' => 4,

                        );

                        $zu = $this->zushu($len, $preArr[$arr[0]]);

                        $totalZu = $totalZu + $zu - 1;

                        if ($single_money[$k] * $zu != $money[$k]) { //总额 单注 注数

                            $message['content'] = 'Wrong amount';

                            $message['is_popup_msg'] = '1';

                            Gateway::sendToUid($uid, json_encode($message));

                            return false;

                        }



                        foreach ($waysArr as $_wv) {

                            if (!in_array($arr[0] . '_' . $_wv . '尾', $allway)) {

                                $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                                $message['is_popup_msg'] = '1';

                                Gateway::sendToUid($uid, json_encode($message));

                                return false;

                            }

                        }

                    }

                }

            }else{ //非六合彩彩种

                if(!in_array($v, $allway)){

                    $message['content'] = 'There are illegal bets in your bet, betting is prohibited';

                    $message['is_popup_msg'] = '1';

                    Gateway::sendToUid($uid, json_encode($message));

                    return false;

                }

            }

        }





        //游戏币比例

        $RmbRatio = D('workerman')->getConfig("rmbratio",'value');



        $hbtime = microtime(1);



        //投注判断

        $res = $this->getReverseBetting($uid,$groupid,$roomid,$info['issue'],$way,$money,$RmbRatio);

        if($res['control']){

            $message['content'] = $res['content'];

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }



        $hetme = microtime(1);

        //判断账户余额是否小于当前投注总额

        $currentMoney = array_sum($money);

        $currentMoney = bcdiv($currentMoney,$RmbRatio,2);

        if(!empty(C('db_port'))){ //使用mycat时 查主库数据

            $sql = "/*#mycat:db_type=master*/ SELECT money FROM `un_account` WHERE `user_id` = '{$uid}'"; //要查主库

        }else{

            $sql = "SELECT money FROM `un_account` WHERE `user_id` = '{$uid}'"; //要查主库

        }

        $account = $this->db->result($sql);

        if (bccomp($currentMoney, $account, 2) == 1) {

            $message['content'] = "Your balance is not enough, please deposit";

            $message['has_not_money'] = '1';

            $message['is_popup_msg'] = '1';

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }



        $hhbtime = microtime(1);

        //生成订单

        $this->db->query("START TRANSACTION");

        try {



            if(!empty(C('db_port'))){ //使用mycat时 查主库数据

                $sql = "/*#mycat:db_type=master*/ SELECT money FROM `un_account` WHERE `user_id` = '{$uid}' for update"; //要查主库

            }else{

                $sql = "SELECT money FROM `un_account` WHERE `user_id` = '{$uid}' for update"; //要查主库

            }

            $tempAccount = $this->db->result($sql);



            $encodeval_model = D('Encodeval');

            $orderArr = array();

            foreach ($way as $k => $v){

                unset($order_no); //防止重复流水号

                //获取是否跟投标识

                $ext_a = (int)$_REQUEST['ext_a'] == 1 ? D('workerman')->getRandomString(6) : '' ;

                //后面补0,防止重复

                $order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);

                if(in_array($order_no,$orderArr)){ //防止重复流水号

                    sleep(1);

                    $order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);

                }

                $orderArr[] = $order_no;  //新增

                $tzje = bcdiv($money[$k], $RmbRatio, 2); //投注金额

                $dzje = bcdiv($single_money[$k], $RmbRatio, 2); //单注金额

                $tempAccount = bcsub($tempAccount, $tzje, 2); //当前金额

                $ddsqlarr[] = array(

                    'lottery_type' => $lotteryType,

                    'room_no' => $roomid,

                    'order_no' => $order_no,

                    'user_id' => $uid,

                    'issue' => $info['issue'],

                    'addtime' => $time,

                    'way' => $v,

                    'money' => $tzje,

                    'single_money' => $dzje,

                    'reg_type' => $regType,

                    'win_stop' => $win_stop,

                    'ext_b' => $ext_a,



                    //生成校验值

                    'whats_val' => $encodeval_model->mixVal($order_no, $tzje, $time, $v),

                );

                $ddsqlarr_audit[] = array(

                    'lottery_type' => $lotteryType,

                    'room_no' => $roomid,

                    'order_no' => $order_no,

                    'user_id' => $uid,

                    'issue' => $info['issue'],

                    'addtime' => $time,

                    'way' => $v,

                    'money' => $tzje,

                );

                $zjsqlarr[] = array(

                    'order_num' => $order_no,

                    'user_id' => $uid,

                    'type' => 13,

                    'addtime' => $time,

                    'money' => $tzje,

                    'use_money' => $tempAccount,

                    'remark'=>"User bets ".$tzje,

                    'reg_type' => $regType

                );

            }

            //再查余额

            $sql_acc = "SELECT money-{$currentMoney} AS money FROM un_account WHERE user_id={$uid}";

            $acc_res = $this->db->result($sql_acc);

            if($acc_res<0) throw new Exception('Update failed!5');

            $sql = "UPDATE `un_account` SET `money` = money-'{$currentMoney}' WHERE `user_id` = {$uid}"; //出于并发考虑

            $ret = $this->db->query($sql);

            if (empty($ret)) throw new Exception('Update failed!1');

            $inid = $this->db->insert('un_orders', $ddsqlarr);

            $inid_audit = $this->db->insert('un_orders_audit', $ddsqlarr_audit);

            if ($inid){

                $ret = $this->db->insert('un_account_log',$zjsqlarr );

                if (empty($ret)) throw new Exception('Update failed!2');



                $sql = "SELECT MAX(award_state) as state,issue FROM `un_orders` WHERE user_id={$uid} AND room_no={$roomid} AND award_state <> 0 GROUP by issue order by issue desc";

                $sy = $this->db->query($sql);

                $sya = array(1 => 0, 2 => 0);

                if ($sy) {

                    $a = 0;

                    foreach ($sy as $v) {

                        if ($a && $v['state'] == $a) {

                            $sya[$v['state']]++;

                        } elseif ($a && $v['state'] != $a) {

                            break;

                        } else {

                            $sya[$v['state']]++;

                            $a = $v['state'];

                        }

                    }

                }





                if(!empty(C('db_port'))) { //使用mycat时 查主库数据

                    $sql = "/*#mycat:db_type=master*/ select count(id) from un_orders WHERE  user_id={$uid} AND room_no={$roomid} AND issue={$info['issue']} AND state=0 AND chase_number = ''";

                }else{

                    $sql = "select count(id) from un_orders WHERE  user_id={$uid} AND room_no={$roomid} AND issue={$info['issue']} AND state=0 AND chase_number = ''";

                }

                $count = $this->db->result($sql);

                $message['commandid'] = 3007;

                $message['uid'] = $uid;

                $message['nickname'] = $username;

                $message['avatar'] = '/'.ltrim($avatar,'/');

                $message['way'] = $way;

                $message['issue'] = $info['issue'];

                $message['time'] = date('Y-m-d H:i', $time);            //新版时间显示

                $message['money'] = $money;

                $message['total_money'] = array_sum($money);

                $message['single_money'] = $single_money;  //六合彩用的单注

                $message['total_zushu'] = $totalZu;

                $message['count'] = $count;

                $message['order_no'] = $orderArr;

                $message['lose'] = $honor['status']?$sya[1]:'';

                $message['won'] = $honor['status']?$sya[2]:'';

                $message['content'] = '';

                $message['sort'] = $honor['sort'];

                $message['honor_status'] = $honor['honor_status'];

                $data=array( //调用双活接口

                    'type'=>'betting_group',

                    'id'=>$roomid,

                    'json'=>encode($message),

                );

                $send = array('commandid' => 3010, 'money' => convert1(bcsub($account, $currentMoney, 2)));

                $data1=array( //调用双活接口

                    'type'=>'betting',

                    'id'=>$uid,

                    'json'=>encode($send),

                );

                if(in_array($regType,array(/*11, 暂不含11, 过滤掉假人*/9))){ //屏蔽游客和机器人

                    Gateway::sendToGroup($roomid, json_encode($data));

                    Gateway::sendToUid($uid, json_encode($data1));

                }else{

                    send_home_data($data);

                    send_home_data($data1);

                }

            }else{

                throw new Exception('Update failed!3');

            }

            $this->db->query('COMMIT');

            $hetme = microtime(1);

            $jetme = microtime(1);

            //删除刷单间隔标识

            $redis = initCacheRedis();

            $redis->del($co_str);

            deinitCacheRedis($redis);

            return;

        } catch (Exception $err) {

            $this->db->query('ROLLBACK');

            $send = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "System error, please contact customer service.");

            Gateway::sendToUid($uid, json_encode($send));

            return;

        }

    }





    /**

     * @param $way 多注玩法前缀

     * @param $len 所投号码个数

     * @return array|bool

     *

     */

    function lhcCheck($way,$len){

        $data = array(

            '三中二'=>[3,10],

            '三全中'=>[3,10],

            '二全中'=>[2,10],

            '二中特'=>[2,10],

            '特串'=>[2,10],

            '二肖连中'=>[2,8],

            '三肖连中'=>[3,8],

            '四肖连中'=>[4,8],

            '二肖连不中'=>[2,8],

            '三肖连不中'=>[3,8],

            '四肖连不中'=>[4,8],

            '五不中'=>[5,8],

            '六不中'=>[6,9],

            '七不中'=>[7,10],

            '八不中'=>[8,11],

            '九不中'=>[9,12],

            '十不中'=>[10,13],

            '二尾连中'=>[2,8],

            '三尾连中'=>[3,8],

            '四尾连中'=>[4,8],

            '二尾连不中'=>[2,8],

            '三尾连不中'=>[3,8],

            '四尾连不中'=>[4,8],

        );

        if(in_array($way,array_keys($data))){

            if($len<$data[$way][0] || $len>$data[$way][1]){

                return array('code'=>1,'msg'=>$way.'Betting contain illegal betting, Stop betting');

            }

        }

        return false;

    }



    /**

     * @param $lotteryType 彩种

     * @param $way 当前玩法

     * @param $uid 用户ID

     * @param $issue 期号

     * @return array|bool 受限有值 , 不受限false

     *

     */

    public  function numLimitBetting($lotteryType,$way,$uid,$issue){

        $redis = initCacheRedis();



        $way = array_unique($way); //去重处理



        //车号限制

        if(in_array($lotteryType,array(7,8))){



            $nid = ($lotteryType==7)?"lhc_set_bet":"jslhc_set_bet";

            $configBetData = decode($redis->hget('Config:'.$nid,'value'));

            deinitCacheRedis($redis);



            //当前投注的玩法

            sort($way);

            $eArr = array(

                '特码A',

                '特码B',

                '正码A',

                '正码B',

                '正1特',

                '正2特',

                '正3特',

                '正4特',

                '正5特',

                '正6特',

                '尾数',

                '一肖',

                '特肖',

            );



            foreach ($configBetData as $config){

                if($config['status'] ==1 && $config['max']>0){

                    $iArr = array();

                    foreach ($way as $ck=>$cv) {

                        $wayArr = explode('_', $cv);

                        if(in_array($wayArr[0],array('尾数','一肖','特肖'))){ //'尾数','一肖','特肖'要单独处理

                            if ($wayArr[0] == $config['name']) {

                                $iArr[$wayArr[0]]++;

                                if($iArr[$wayArr[0]] > $config['max']){ //超出值

                                    return array('max'=>$config['max'],'limitWayStr'=>$wayArr[0]);

                                    break;

                                }

                            }

                        }else{

                            if(in_array($wayArr[1],range(1,49))){

                                if ($wayArr[0] == $config['name'] && in_array($wayArr[0], $eArr)) {

                                    $iArr[$wayArr[0]]++;

                                    if($iArr[$wayArr[0]] > $config['max']){ //超出值

                                        return array('max'=>$config['max'],'limitWayStr'=>$wayArr[0]);

                                        break;

                                    }

                                }

                            }

                        }



                    }

                }

            }



            //统计当前玩法个数

            $iArr=array();

            $full_arr=array();

            foreach ($way as $ck=>$cv) {

                $wayArr = explode('_', $cv);

                if(in_array($wayArr[0],array('尾数','一肖','特肖'))) { //'尾数','一肖','特肖'要单独处理

                    $iArr[$wayArr[0]]['num']++;

                    $iArr[$wayArr[0]]['way'] = $cv;

                }else{

                    if(in_array($wayArr[1],range(1,49))){

                        if (in_array($wayArr[0], $eArr)) {

                            $iArr[$wayArr[0]]['num']++;

                            $iArr[$wayArr[0]]['way'] = $cv;

                        }

                    }

                }

            }



            //库里存在的记录

            $sql_full = "select distinct way from un_orders where user_id=$uid and issue = {$issue} and state = 0 AND lottery_type = {$lotteryType} GROUP BY way";

            $re_full  = $this->db->getall($sql_full);

            foreach ($re_full as $fv){

                $full_arr[] = $fv['way'];

            }


            //合并当前玩法和历史玩法

            foreach ($iArr as $ik=>$iv){

                $sql = "select way from un_orders where user_id=$uid and issue = {$issue} and state = 0 and way REGEXP '^{$ik}_' AND  lottery_type = {$lotteryType} GROUP BY way";

                $re  = $this->db->getall($sql);

                if(!empty($re)){

                    $len = count($re);

                    $iArr[$ik]['num'] += $len;

                }

            }



            foreach ($configBetData as $config){

                if($config['status'] ==1 && $config['max']>0){

                    foreach ($iArr as $ik=>$iv){

                        if(($ik==$config['name'] && $iv['num']>$config['max'] && !in_array($iv['way'],$full_arr))){

                            return array('max'=>$config['max'],'limitWayStr'=>$ik);

                            break;

                        }

                    }

                }

            }

        }



        if(in_array($lotteryType,array(5,6,11))){

            if($lotteryType==5){

                $nid = 'qcssc_set_bet';

            }



            if($lotteryType==6){

                $nid = 'sfc_set_bet';

            }



            if($lotteryType==11){

                $nid = 'ffc_set_bet';

            }

            $configBetData = decode($redis->hget('Config:'.$nid,'value'));

            deinitCacheRedis($redis);

            if($configBetData['status'] ==1 && $configBetData['max']>0){ //开启这个功能 并且数值有限制

                //当前投注的玩法

                sort($way);

                $iArr = array();

                foreach ($way as $ck=>$cv) {

                    if(preg_match('/^第.{3}球_\d$/',$cv)){

                        $cv = preg_replace('/_\d$/','',$cv);

                        $iArr[$cv]++;

                        if($iArr[$cv] > $configBetData['max']){ //超出值

                            return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);

                            break;

                        }

                    }

                }



                //合并当前玩法和历史玩法

                $sql = "select way from un_orders where user_id=$uid and issue = {$issue} and state = 0 and way REGEXP '第.{3}球_[0-9]' AND  lottery_type = {$lotteryType} GROUP BY way";

                $re  = $this->db->getall($sql);

                foreach ($re as $ok=>$ov){

                    if(!in_array($ov['way'],$way)){

                        $way[$ov['way']] = $ov['way'];

                    }

                }

                $iArr = array();

                foreach ($way as $ck=>$cv) {

                    if(preg_match('/^第.{3}球_\d$/',$cv)){

                        $cv = preg_replace('/_\d$/','',$cv);

                        $iArr[$cv]++;

                        if($iArr[$cv] > $configBetData['max']){ //超出值

                            return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);

                            break;

                        }

                    }

                }

            }

        }





        if(in_array($lotteryType,array(2,4,9,13,14))){

            $nid='';

            if($lotteryType==2){

                $nid = 'bjpk10_set_bet';

            }



            if($lotteryType==4){

                $nid = 'xyft_set_bet';

            }



            if($lotteryType==9){

                $nid = 'jssc_set_bet';

            }





            if($lotteryType==13){

                $nid = 'hlsb_set_bet';

            }



            if($lotteryType==14){

                $nid = 'ffpk10_set_bet';

            }



            $configBetData = decode($redis->hget('Config:'.$nid,'value'));

            deinitCacheRedis($redis);

            if($configBetData['status'] ==1 && $configBetData['max']>0){ //开启这个功能 并且数值有限制

                //当前投注的玩法

                sort($way);

                $iArr = array();

                foreach ($way as $ck=>$cv) {

                    if(preg_match('/^(冠军|亚军|第.{3}名|第.{3}骰)_([1-9]|10)$/',$cv)){

                        $arrTmp = explode('_',$cv);

                        $cv = $arrTmp[0];

                        $iArr[$cv]++;

                        if($iArr[$cv] > $configBetData['max']){ //超出值

                            return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);

                            break;

                        }

                    }

                }



                //合并当前玩法和历史玩法

                $sql = "select way from un_orders where user_id=$uid and issue = {$issue} and state = 0 and way REGEXP '^(冠军|亚军|第.{3}名|第.{3}骰)_([1-9]|10)' AND  lottery_type = {$lotteryType} GROUP BY way";

                $re  = $this->db->getall($sql);

                foreach ($re as $ok=>$ov){

                    if(!in_array($ov['way'],$way)){

                        $way[$ov['way']] = $ov['way'];

                    }

                }

                $iArr = array();

                foreach ($way as $ck=>$cv) {

                    if(preg_match('/^(冠军|亚军|第.{3}名|第.{3}骰)_([1-9]|10)/',$cv)){

                        $arrTmp = explode('_',$cv);

                        $cv = $arrTmp[0];

                        $iArr[$cv]++;

                        if($iArr[$cv] > $configBetData['max']){ //超出值

                            return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);

                            break;

                        }

                    }

                }

            }

        }



        if(in_array($lotteryType,array(10))){

            $nid = ($lotteryType==10)?"nn_set_bet":"";

            $configBetDataArr = decode($redis->hget('Config:'.$nid,'value'));

            deinitCacheRedis($redis);

            foreach ($configBetDataArr as $configBetData){

                if($configBetData['status'] ==1 && $configBetData['max']>0){ //开启这个功能 并且数值有限制

                    //当前投注的玩法

                    sort($way);

                    $iArr = array();

                    foreach ($way as $ck=>$cv) {

                        //牛九

                        if($configBetData['name']=='猜牛' && preg_match('/牛/',$cv)){

                            $limitWayStr = $configBetData['name'];

                            $iArr[$limitWayStr]++;

                            if($iArr[$limitWayStr] > $configBetData['max']){ //超出值

                                return array('max'=>$configBetData['max'],'limitWayStr'=>$limitWayStr);

                                break;

                            }

                        }



                        //第一张_双

                        if($configBetData['name']=='猜牌面' && preg_match('/^第.{3}张_[2-9]|10|A|J|Q|K$/',$cv)){

                            $arrTmp = explode('_',$cv);

                            $cv = $arrTmp[0];

                            $iArr[$cv]++;

                            if($iArr[$cv] > $configBetData['max']){ //超出值

                                return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);

                                break;

                            }

                        }

                    }



                    //合并当前玩法和历史玩法

                    $sql = "select way from un_orders where user_id=$uid and issue = {$issue} and state = 0 and way REGEXP '牛|(第.{3}张_[2-9]|10|A|J|Q|K)' AND  lottery_type = {$lotteryType} GROUP BY way";

                    $re  = $this->db->getall($sql);

                    foreach ($re as $ok=>$ov){

                        if(!in_array($ov['way'],$way)){

                            $way[$ov['way']] = $ov['way'];

                        }

                    }

                    $iArr = array();

                    foreach ($way as $ck=>$cv) {

                        //牛九

                        if($configBetData['name']=='猜牛' && preg_match('/牛/',$cv)){

                            $limitWayStr = $configBetData['name'];

                            $iArr[$limitWayStr]++;

                            if($iArr[$limitWayStr] > $configBetData['max']){ //超出值

                                return array('max'=>$configBetData['max'],'limitWayStr'=>$limitWayStr);

                                break;

                            }

                        }



                        if($configBetData['name']=='猜牌面' && preg_match('/^第.{3}张_[2-9]|10|A|J|Q|K$/',$cv)){

                            $arrTmp = explode('_',$cv);

                            $cv = $arrTmp[0];

                            $iArr[$cv]++;

                            if($iArr[$cv] > $configBetData['max']){ //超出值

                                return array('max'=>$configBetData['max'],'limitWayStr'=>$cv);

                                break;

                            }

                        }

                    }

                }

            }

        }

        return false;

    }



    /**

     * 投注控制

     * @param $uid int 用户id

     * @param $groupid int 用户组id

     * @param $roomid int 房间id

     * @param $issue int 期号

     * @param $way array 玩法

     * @param $money array 投注金额

     * @param $RmbRatio int 金额比率

     */

    public function getReverseBetting($uid,$groupid,$roomid,$issue,$way,$money,$RmbRatio){

        //初始化redis

        $redis = initCacheRedis();

        $re = $redis->hget('Config:reverse_set','value');

        $data = decode($re);



        $lottery_type = $redis->hget("allroom:".$roomid,'lottery_type');

        deinitCacheRedis($redis);

        //逆向投注过滤（先查找在此彩种此期下注的订单）

        $sql = "SELECT way, money,room_no FROM un_orders WHERE user_id='{$uid}' AND lottery_type={$lottery_type} AND issue = '{$issue}' AND state = 0";

        $orders = $this->db->getall($sql);



        //总注

        $totalMoney = 0;

        //赋值给临时数组

        $temp = array();

        $tempRoom = array();

        $tempRoomCurrent = array();

        $totalTmp = array();



        $except_way = [

            "冠亚", "三中二", "三全中", "二全中", "二中特", "特串", "五不中", "六不中", "七不中", "八不中",

            "九不中", "十不中", "二尾连中", "三尾连中", "四尾连中", "二尾连不中", "三尾连不中", "四尾连不中", "二肖连中",

            "三肖连中", "四肖连中", "二肖连不中", "三肖连不中", "四肖连不中"

        ];

        //以前投的

        foreach ($orders as $v) {

            $arr = explode('_',$v['way']); //多个号码一起时

            if (in_array($arr[0],$except_way)) {

                $v['way'] = $arr[0];

            }

            $temp[$v['way']] += $v['money'];

            if($v['room_no'] == $roomid){ //当前房间的

                $tempRoom[$v['way']] += $v['money'];

            }

            $totalMoney = bcadd($totalMoney,$v['money'],2);

        }



        //当前投注

        foreach ($way as $k => $v) {

            $arr = explode('_',$v); //多个号码一起时

            if (in_array($arr[0],$except_way)) {

                $v = $arr[0];

            }

            $temp[$v] += $money[$k];

            $tempRoomCurrent[$v] += $money[$k]; //当前投注金额

            $totalMoney = bcadd($totalMoney,$money[$k],2);

        }



        //合并投注

        foreach ($tempRoomCurrent as $ck=>$cv){

            $totalTmp[$ck] = bcadd($tempRoom[$ck],$cv,2);

        }



        if(!empty($data)){

            $wayKeys = array_keys($temp);

            $zeroKey = array_search(0,$wayKeys,true); //0 特殊处理 请查看in_array int 0

            if($zeroKey !== false) $wayKeys[$zeroKey] = '0';

            foreach ($data[$lottery_type] as $k=>$v){

                if($v['state'] == 1){

                    $controlWayArr = explode(',',$v['data']);

                    $num = count($controlWayArr);

                    $jArr = array_intersect($wayKeys,$controlWayArr);

                    if(!empty($jArr) && count($jArr)==$num){

                        return array('control' => true, 'content' => "Your bet content does not comply with the betting rules of this period, and you can not bet at the same time[".implode(',',$controlWayArr)."]");

                    }

                }

            }

        }else{

            return array('control' => true, 'content' => "Unable to get data");

        }



        //获取房间投注控制

        $room = D('workerman')->getRedisHashValues("allroom:".$roomid, array('upper','lower')); //逆向投注已改到上面, 这里就不用取这个值'reverse'



        //投注总额下限限额控制

        if (!empty($room['lower'])) {

            $lower = bcmul($room['lower'],$RmbRatio,2);

            if (bccomp($lower,$totalMoney, 2) == 1) return array('control' => true, 'content' => "Your bet amount is less than the total bet limit： ".$lower."coins");

            foreach ($way as $k => $v) {

                if (bccomp($lower,$money[$k], 2) == 1) return array('control' => true, 'content' => "Your bet [{$v}] amount [{$money[$k]}] is less than the room limit： ".$lower."coins");

            }

        }



        //投注总额上限限额控制-投注玩法限额控制

        if(!empty($room['upper'])){

            $upper = json_decode($room['upper'],true);

            //投注总额上限限额控制

            if(!empty($upper['total_amount'])){

                $total_amount = bcmul($upper['total_amount'],$RmbRatio,2);

                if (bccomp($totalMoney, $total_amount, 2) == 1) return array('control' => true, 'content' => "Your bet amount is greater than the total bet limit： ".$total_amount."coins");

            }

            //玩法限额

            if(!empty($upper['limit'])){

                foreach ($upper['limit'] as $v){

                    $groupWayMoney = 0;

                    foreach ($v['contact'] as $k => $c){

                        //当前玩法

                        foreach ($tempRoomCurrent as $tk=>$tv){

                            if($c===$tk){

                                $groupWayMoney = $tv;

                                $oneSet = 0; //是否只有单条记录

                                

                                if (count($v['data']) == 1 && count($v['contact']) >= 1) {

                                    $limitMoney = bcmul($v['data'][0],$RmbRatio,2);

                                    $oneSet = 1;

                                }else {

                                    $limitMoney = bcmul($v['data'][$k],$RmbRatio,2);

                                }



                                $liArrKeys = $v['contact'];


                                $liArrKeys = array_map(function ($p){

                                    return (string)$p;

                                },$liArrKeys);


                                if (in_array($tk,$liArrKeys) && bccomp($groupWayMoney, $limitMoney, 2) == 1 && $limitMoney != 0){

                                    if($oneSet==1){

                                        return array('control' => true, 'content' => "Your bet amount is greater than({$v['remark']}) ".$limitMoney."coins");

                                    }else{

                                        return array('control' => true, 'content' => "Your bet amount is greater than({$v['remark']}:{$c}) ".$limitMoney."coins");

                                    }

                                }

                            }

                        }



                        $groupWayMoney = 0;

                        $limitMoney = 0;

                        //合并后的玩法

                        foreach ($totalTmp as $tk=>$tv){

                            if($c===$tk){

                                $groupWayMoney = $tv;

                                $oneSet = 0; //是否只有单条记录

                                if (count($v['data']) == 1 && count($v['contact']) > 1) {

                                    $oneSet = 1;

                                    $limitMoney = bcmul($v['data'][0],$RmbRatio,2);

                                    //这里写逻辑

                                    $jtmpWay = $v['contact'];

                                    $re = array_search($c,$jtmpWay);

                                    if($re !== false){

                                        unset($jtmpWay[$re]);

                                    }

                                    $jjson = '';

                                    foreach ($jtmpWay as $jk=>$jv){

                                        $jjson .= ",'".$jv."'";

                                    }

                                    $jjson = trim($jjson,',');

                                    $sql = "SELECT sum(money) as total FROM un_orders WHERE user_id='{$uid}' AND lottery_type={$lottery_type} AND issue = '{$issue}' AND state = 0 and way in ({$jjson})";

                                    $wmoney = $this->db->result($sql);

                                    $groupWayMoney = bcadd($groupWayMoney,$wmoney,2);



                                }else {

                                    if (count($v['data']) == 1 && count($v['contact']) == 1) {

                                        $oneSet = 1;

                                    }

                                    $limitMoney = bcmul($v['data'][$k],$RmbRatio,2);

                                }

                                $liArrKeys = $v['contact'];

                                $liArrKeys = array_map(function ($p){

                                    return (string)$p;

                                },$liArrKeys);

                                if (in_array($tk,$liArrKeys) && bccomp($groupWayMoney, $limitMoney, 2) == 1 && $limitMoney != 0){

                                    if($oneSet==1){

                                        return array('control' => true, 'content' => "Your bet amount is greater than({$v['remark']}) ".$limitMoney."coins");

                                    }else{

                                        return array('control' => true, 'content' => "Your bet amount is greater than({$v['remark']}:{$c}) ".$limitMoney."coins");

                                    }

                                }

                            }

                        }

                    }

                }

            }

        }

        //获取会员组投注控制

        $group = D('workerman')->getRedisHashValues("group:".$groupid, array('upper','lower','name','limit_state'));

        if($group['limit_state'] == 1){

            //投注总额下限限额控制

            if (!empty($group['lower'])) {

                $lower = bcmul($group['lower'],$RmbRatio,2);

                if (bccomp($lower,$totalMoney, 2) == 1) return array('control' => true, 'content' => "You belong to the member group({$group['name']}),your bet amount is less than the member group limit： {$lower}coins");

                foreach ($way as $k => $v) {

                    if (bccomp($lower,$money[$k], 2) == 1) return array('control' => true, 'content' => "You belong to the member group({$group['name']}),your bet[{$v}] amount [{$money[$k]}]is less than the member group limit： ".$lower."coins");

                }

            }

            //投注总额上限限额控制

            if (!empty($group['upper'])) {

                $upper = bcmul($group['upper'],$RmbRatio,2);

                if (bccomp($totalMoney, $upper, 2) == 1) return array('control' => true, 'content' => "You belong to the member group({$group['name']}),Your bet amount exceeds the member group limit： {$upper}coins");

            }

        }

    }



    /**

     * 追号投注控制

     * @param $uid int 用户id

     * @param $groupid int 用户组id

     * @param $roomid int 房间id

     * @param $data array 追号投注信息

     * @param $type int 1验证 0投注

     */

    public function chase(){

        //验证签名

        $res = verificationSignature();

        if($res['status'] !== "success"){

            @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 签名验证失败(追号投注): '.json_encode($res,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);

            if($res['code'] == 3){

                ErrorCode::errorResponse(999998,"Request timed out, please make sure your mobile phone system time is: Beijing (China) time, refresh and try again!");

            }

            ErrorCode::errorResponse(999999,"Signing failed, please make sure the app is the latest version and try again!");

        }

        //接收参数

        $uid = (int)$_REQUEST['userid'];



        //实例化Gateway

        O('Gateway');

        Gateway::$registerAddress = C('Gateway');

        $redis = initCacheRedis(); //配置

        $checkToken = $redis->hget('Config:check_token_set','value');

        deinitCacheRedis($redis);

        if(!empty($checkToken)){

            $token = $_REQUEST['token'];

            $r = checkToken($token,$uid,$this->db);

            if($r==1){

                    Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "This bet is missing important parameters, please re-betting")));

                //不返回任何信息, 防止投注别人的帐号

                return false;

            }else if($r==2){

                Gateway::sendToUid($uid, json_encode(array('commandid' => 3014, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Token is invalid, please log in again")));

                return false;

            }

        }



        //更新token时间

        $now = time();

        $sql = "UPDATE `un_session` SET lastvisit={$now} WHERE user_id={$uid}";

        $this->db->query($sql);



        $win_stop = (int)$_REQUEST['win_stop'];

        $data = json_decode(stripslashes_deep($_REQUEST['data']),true);

        $roomid = (int)$_REQUEST['roomid'];

        $lotteryType = (int)$_REQUEST['lotteryType'];

        $type = (int)$_REQUEST['type'];//1验证 0投注



        //判断参数

        $paramFlag = false;

        if(empty($uid)) $paramFlag = true;

        if(empty($data)) $paramFlag = true;

        if(empty($roomid)) $paramFlag = true;

        if(empty($lotteryType)) $paramFlag = true;

        if(!($win_stop==1 || $win_stop==0)){

            $paramFlag = true;

        }


        //判断参数

        if($paramFlag){

            return;

        }



        //判断房间彩种

        $roomInfo = D('workerman')->getRedisHashValues("allroom:".$roomid,array('lottery_type','special_way'));

        if($roomInfo['lottery_type'] != $lotteryType) $paramFlag = true;



        //特殊玩法判断

        $whereSql = "";

        $special_way = json_decode($roomInfo['special_way'],true);

        if($special_way['status'] != '1'){

            $whereSql = " AND type <> 3";

        }


        //返回信息

        $message = array(

            'commandid' => 3004,

            'nickname' => '',

            'content' => '',

            'avatar'=>''

        );



        if ($type) {

            $message['commandid'] = 3021;

            $message['content'][0] = array(

                'issue' => $data[0]['qihao'],

                'way' => $data[0]['way'],

                'money' => $data[0]['money'],

                'result' => 0,

                'msg' => "",

            );

        }

        //查询用户信息(昵称,头像,注册类型)

        $sql = "SELECT username,nickname,avatar,group_id,reg_type FROM `un_user` WHERE `id` = '{$uid}'";

        $res = $this->db->getone($sql);

        if(empty($res) || $paramFlag){

            if ($type) {

                $message['content'][0]['msg'] = "【Chase number】Information is missing or has been changed.";

                // $message['is_popup_msg'] = '1';

            }else{

                $message['content'] = "【Chase number】Information is missing or has been changed.";

                // $message['is_popup_msg'] = '1';

            }

            Gateway::sendToUid($uid, json_encode($message));

            return;

        };



        $username = empty($res['nickname'])?$res['username']:$res['nickname'];

        //控制昵称显示

        $username = D('workerman')->getNickname($username);

        $avatar = empty($res['avatar'])?'/up_files/room/avatar.png':$res['avatar'];

        $regType = $res['reg_type'];

        $groupid = $res['group_id'];



        //获取期号

        $time = time();

        $info = D('workerman')->getQihao($lotteryType,$time,$roomid);



        //如果未获取到期号

        if($info['issue'] == 0){

            if ($type) {

                $message['commandid'] = 3021;

                $message['content'][0] = array(

                    'issue' => $data[0]['qihao'],

                    'way' => $data[0]['way'],

                    'money' => $data[0]['money'],

                    'result' => 0,

                    'msg' => "【Chase number】User information error",

                );

            }

            if ($type) {

                $message['content'][0]['msg'] = $info['msg'];

            }else{

                $message['content'] = $info['msg'];

            }

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }



        //如果处于封盘

        if($info['sealingTim'] >= ($info['date']- $time)){

            if ($type) {

                $message['content'][0]['msg'] = "【Chase number】Not in the betting period, can not bet";

                // $message['is_popup_msg'] = '1';

            }else{

                $message['content'] = "【Chase number】Not in the betting period, can not bet";

                // $message['is_popup_msg'] = '1';

            }

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }



        //判断投注的玩法是否是正确的

        $sql = "select way,type from un_odds where lottery_type='{$lotteryType}' and room='{$roomid}'{$whereSql}";

        $res = $this->db->getall($sql);

        if(empty($res)){

            if ($type) {

                $message['content'][0]['msg'] = '【Chase number】No related method for this lottery, Stop betting';

                // $message['is_popup_msg'] = '1';

            }else{

                $message['content'] = '【Chase number】No related method for this lottery, Stop betting';

                // $message['is_popup_msg'] = '1';

            }

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }



        $allway = array();

        $num_way = [];

        foreach ($res as $k=>$v){

            $allway[] = $v['way'];

            $num_way[] = $v['way'];

        }



        //游戏币比例

        $RmbRatio = D('workerman')->getConfig("rmbratio",'value');



        //获取当天期号

        switch ($lotteryType){

            case 1:

                $jsonData = @file_get_contents('xy28_qihao.json'); //获取数据

                break;

            case 2:

                $jsonData = @file_get_contents('bjpk10_qihao.json'); //获取数据

                break;

            case 3:

                $jsonData = @file_get_contents('jnd28_qihao.json'); //获取数据

                break;

            case 4:

                $jsonData = @file_get_contents('xyft_qihao.json'); //获取数据

                break;

            case 5:

                $jsonData = @file_get_contents('cqssc_qihao.json'); //获取数据

                break;

            case 6:

                $jsonData = @file_get_contents('sfc_qihao.json'); //获取数据

                break;

            case 7:

                $jsonData = @file_get_contents('lhc_qihao.json'); //获取数据

                break;

            case 8:

                $jsonData = @file_get_contents('jslhc_qihao.json'); //获取数据

                break;

            case 9:

                $jsonData = @file_get_contents('jssc_qihao.json'); //获取数据

                break;

            case 10:

                $jsonData = @file_get_contents('nn_qihao.json'); //获取数据

                break;

            case 11:

                $jsonData = @file_get_contents('ffc_qihao.json'); //获取数据

                break;

            case 13:

                $jsonData = @file_get_contents('sb_qihao.json'); //获取数据

                break;

            case 14:

                $jsonData = @file_get_contents('ffpk10_qihao.json'); //获取数据

                break;

            default:

                $jsonData = 0;

        }

        $jsonData = json_decode($jsonData,true);

        $list = json_decode($jsonData['txt'],true);

        $issues = array();

        foreach ($list['list'] as $v){

            if($v['issue'] >= $info['issue']){

                $issues[] = $v['issue'];

            }

        }





        //投注判断

        foreach ($data as $k=>$v) {


            //校验投注金额是否有误

            if(strpos($v['money'],'.') !== false && !strpos($v['money'],'.00')){

                Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Wrong bet amount.")));

                return false;

            }



            if(!empty($v['single_money'])){

                foreach ($v['single_money'] as $vvm){

                    if(strpos($vvm,'.') !== false && !strpos($vvm,'.00')){

                        Gateway::sendToUid($uid, json_encode(array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "Wrong bet amount.")));

                        return false;

                    }

                }

            }



            $arr =explode('_',$v['way']); //冠亚两号一起时

            if(count($arr)==3){

                $_1 =(int)$arr[1];

                $_2 =(int)$arr[2];

                if($_1<11 && $_2<11 && $_1>0 && $_2>0){

                    $v['way'] = $arr[0];

                }

            }



            if(in_array($lotteryType,array(7,8))){ //六合彩多注



                //single_money字段验证

                if(empty($v['single_money'])){

                    $message['content'] = "【Chase number】No [{$v['qihao']}], Incomplete data, Stop betting";

                    $message['is_popup_msg'] = '1';

                    Gateway::sendToUid($uid, json_encode($message));

                    return false;

                }



                //常规玩法检验

                if(!in_array($arr[0],array('三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中', '五不中', '六不中', '七不中', '八不中', '九不中', '十不中','二尾连中', '三尾连中', '四尾连中', '二尾连不中', '三尾连不中', '四尾连不中'))){

                    if(!in_array($v['way'], $allway)){

                        $message['content'] = "【Chase number】No [{$v['qihao']}], there is illegal betting in your bet[{$v['way']}], Stop betting";

                        $message['is_popup_msg'] = '1';

                        Gateway::sendToUid($uid, json_encode($message));

                        return false;

                    }

                }else { //多注玩法检验

                    $waysArr = explode(',', $arr[1]); //传进来的玩法

                    $len = count($waysArr);

                    $check_data = $this->lhcCheck($arr[0], $len);

                    if (!empty($check_data)) {

                        $message['content'] = $check_data['msg'];

                        $message['is_popup_msg'] = '1';

                        Gateway::sendToUid($uid, json_encode($message));

                        return false;

                    }

                    if (in_array($arr[0], array('三中二', '三全中', '二全中', '二中特', '特串', '二肖连中', '三肖连中', '四肖连中', '二肖连不中', '三肖连不中', '四肖连不中', '五不中', '六不中', '七不中', '八不中', '九不中', '十不中'))) {



                        foreach ($waysArr as $_wv) {

                            if (!in_array($arr[0] . '_' . $_wv, $allway)) {

                                $message['content'] = "【Chase number】No [{$v['qihao']}], there is illegal betting in your bet[" . $arr[0] . '_' . $_wv . "], Stop betting";;

                                $message['is_popup_msg'] = '1';

                                Gateway::sendToUid($uid, json_encode($message));

                                return false;

                            }

                        }

                    }

                    if (in_array($arr[0], array('二尾连中', '三尾连中', '四尾连中', '二尾连不中', '三尾连不中', '四尾连不中'))) { //六合彩多注



                        foreach ($waysArr as $_wv) {

                            if (!in_array($arr[0] . '_' . $_wv . '尾', $allway)) {

                                $message['content'] = "【Chase number】No [{$v['qihao']}], there is illegal betting in your bet[" . $arr[0] . '_' . $_wv . '尾' . "], Stop betting";

                                $message['is_popup_msg'] = '1';

                                Gateway::sendToUid($uid, json_encode($message));

                                return false;

                            }

                        }

                    }

                }

            }elseif(!in_array($v['way'], $allway)){

                if ($type) {

                    $message['content'][0]['issue'] = $v['qihao'];

                    $message['content'][0]['way'] = $v['way'];

                    $message['content'][0]['msg'] = "【Chase number】No [{$v['qihao']}], there is illegal betting in your bet[{$v['way']}], Stop betting";

                    $message['is_popup_msg'] = '1';

                }else{

                    $message['content'] = "【Chase number】No [{$v['qihao']}], there is illegal betting in your bet[{$v['way']}], Stop betting";

                    $message['is_popup_msg'] = '1';

                }

                Gateway::sendToUid($uid, json_encode($message));

                return;

            }

            if(!in_array($v['qihao'],$issues)){

                if ($type) {

                    $message['content'][0]['issue'] = $v['qihao'];

                    $message['content'][0]['way'] = $v['way'];

                    $message['content'][0]['msg'] = '【Chase Number】Your bet on No ['.$v['qihao'].'] is not valid on the day';

                    $message['is_popup_msg'] = '1';

                }else{

                    $message['content'] = '【Chase Number】Your bet on No ['.$v['qihao'].'] is not valid on the day';

                    $message['is_popup_msg'] = '1';

                }

                Gateway::sendToUid($uid, json_encode($message));

                return;

            }



            //车号数量控制

            $num_limit = $this->numLimitBetting($lotteryType,array($v['way']),$uid,$v['qihao']);

            if(!empty($num_limit)){

                $message['content'] = 'Bet failed, the number of '.$num_limit['limitWayStr'].'个数不能超过('.$num_limit['max'].')';

                $message['is_popup_msg'] = '1';

                Gateway::sendToUid($uid, encode($message));

                return false;

            }





            $res = $this->getReverseBetting($uid,$groupid,$roomid,$info['issue'],array($v['way']),array($v['money']),$RmbRatio);

            if($res['control']){

                if ($type) {

                    $message['content'][0]['issue'] = $v['qihao'];

                    $message['content'][0]['way'] = $v['way'];

                    $message['content'][0]['msg'] = "【Chase number】No [{$v['qihao']}], method[{$v['way']}]".$res['content'];

                    $message['is_popup_msg'] = '1';

                }else{

                    $message['content'] = "【Chase number】No [{$v['qihao']}], method[{$v['way']}]".$res['content'];

                    $message['is_popup_msg'] = '1';

                }

                Gateway::sendToUid($uid, json_encode($message));

                return;

            }

            $money[] = $v['money'];

        }



        //判断账户余额是否小于当前投注总额

        $currentMoneys = array_sum($money);

        $currentMoney = bcdiv($currentMoneys,$RmbRatio,2);

        $sql = "SELECT money FROM `un_account` WHERE `user_id` = '{$uid}'";

        $account = $this->db->result($sql);

        if (bccomp($currentMoney, $account, 2) == 1) {

            if ($type) {

                $message['content'][0]['msg'] = '【Chase number】Your balance is not enough, please deposit';

                $message['has_not_money'] = '1';

                $message['is_popup_msg'] = '1';

            }else{

                $message['content'] = '【Chase number】Your balance is not enough, please deposit';

                $message['has_not_money'] = '1';

                $message['is_popup_msg'] = '1';

            }

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }



        //验证正确停止

        if ($type) {

            $message['content'][0]["msg"] = "【Chase number】Verified";

            $message['content'][0]["issue"] = $info['issue'];

            $message['content'][0]["result"] = 1;

            Gateway::sendToUid($uid, json_encode($message));

            return;

        }



        //TODO 暂时注释务删

        //查询用户荣誉等级

        //$honor = D('workerman')->getHonorLevel($uid);



        //生成订单

        $this->db->query("START TRANSACTION");



        //追号标识

        $chaseNumber = D('workerman')->getRandomString(6);

        try {

            $bet_array = [];

            $tempAccount = $account;

            $encodeval_model = D('Encodeval');

            $orderArr = array();

            foreach ($data as $k => $v){

                $bet_array[] = [

                    'money' => $v['money'],

                    'multiple' => $v['multiple'],

                    'way' => $v['way'],

                ];



                //后面补0,防止重复

                unset($order_no); //防止重复流水号

                $order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);

                if(in_array($order_no,$orderArr)){ //防止重复流水号

                    sleep(1);

                    $order_no = "TZ" . date("YmdHis") . rand(100, 999).str_pad($uid,6,'0',STR_PAD_RIGHT);

                }

                $orderArr[] = $order_no;  //新增

                $tzje = bcdiv($v['money'], $RmbRatio, 2);//投注金额



                $single_money=0;

                if(in_array($lotteryType,array(7,8))){ //六合彩多注

                    $single_money = bcdiv($v['single_money'], $RmbRatio, 2);//投注金额

                }

                $tempAccount = bcsub($tempAccount, $tzje, 2);//当前金额

                $ddsqlarr[] = array(

                    'lottery_type' => $lotteryType,

                    'room_no' => $roomid,

                    'order_no' => $order_no,

                    'user_id' => $uid,

                    'issue' => $v['qihao'],

                    'addtime' => $time,

                    'way' => $v['way'],

                    'money' => $tzje,

                    'single_money' => $single_money,

                    'reg_type' => $regType,

                    'win_stop' => $win_stop,

                    'chase_number'=>$chaseNumber,

                    'multiple'=>$v['multiple'],

                    'whats_val' => $encodeval_model->mixVal($order_no, $tzje, $time, $v['way']),

                );

                $ddsqlarr_audit[] = array(

                    'lottery_type' => $lotteryType,

                    'room_no' => $roomid,

                    'order_no' => $order_no,

                    'user_id' => $uid,

                    'issue' => $v['issue'],

                    'addtime' => $time,

                    'way' => $v['way'],

                    'money' => $tzje

                );

                $zjsqlarr[] = array(

                    'order_num' => $order_no,

                    'user_id' => $uid,

                    'type' => 13,

                    'addtime' => $time,

                    'money' => $tzje,

                    'use_money' => $tempAccount,

                    'remark'=>"User bets ".$tzje,

                    'reg_type' => $regType

                );





            }



            if($tempAccount != bcsub($account, $currentMoney, 2)){

                //TODO 调试

                @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'NOTICE 游戏类型: '.$lotteryType.' 期号：'.$info['issue'].' 用户id：'.$uid.' 投注金额有差异:'.$tempAccount.' | '.bcsub($account, $currentMoney, 2).PHP_EOL,FILE_APPEND);

            }



            $sql = "UPDATE `un_account` SET `money` = money-'{$currentMoney}' WHERE `user_id` = {$uid}"; //出于并发考虑

            $ret = $this->db->query($sql);

            if (empty($ret)){

                throw new Exception('Update failed!1');

                return;

            }

            $inid = $this->db->insert('un_orders', $ddsqlarr);

            if(empty($inid)){

                throw new Exception('Update failed!2');

                return;

            }

            $inid_audit = $this->db->insert('un_orders_audit', $ddsqlarr_audit);

            if(empty($inid_audit)){

                throw new Exception('Update failed!3');

                return;

            }

            $ret = $this->db->insert('un_account_log',$zjsqlarr );

            if (empty($ret)){

                throw new Exception('Update failed!4');

                return;

            }



            //$sql = "select count(id) from un_orders WHERE  user_id={$uid} AND room_no={$roomid} AND state=0 AND chase_number = '{$chaseNumber}'";

            //$count = $this->db->result($sql);

            $message['commandid'] = 3004;

            //TODO 暂时注释务删

            //$message['uid'] = $uid;

            //$message['nickname'] = $username;

            //$message['avatar'] = $avatar;

            $message['time'] = date("H:i:s",$time);

            $message['count'] = count($data);

            $last = end($data);

            $message['content'] = "【Chase number】No[{$data[0]['qihao']}] - No[{$last['qihao']}] bet [{$data[0]['way']}] successfully, total: {$message['count']}bets, {$currentMoneys} coins;  time [{$message['time']}]";

            //TODO 暂时注释务删

            //$message['icon'] = $honor['status']?$honor['icon']:'';

            //$message['num'] = $honor['status']?$honor['num']:'';

            //$message['honor'] = $honor['status']?$honor['name']:'';

            //$message['honor_status'] = $honor['status'];

            //Gateway::sendToGroup($roomid, json_encode($message));



            //暂时关闭系统追号成功的提示信息, 供新接口使用

            // Gateway::sendToUid($uid, json_encode($message));

            

            //获取荣誉等级

            $honor = get_level_honor($uid);



            $send = array('commandid' => 3010, 'money' => convert1(bcsub($account, $currentMoney, 2)));

            Gateway::sendToUid($uid, json_encode($send));



            $send = array(

                'commandid' => 3019,

                'content' => '',

                'data' => [

                    'begin_issue' => $data[0]['qihao'],

                    'end_issue' => $last['qihao'],

                    'way' => $data[0]['way'],

                    'count' => $message['count'],

                    'total_amount' => $currentMoneys,

                    //追号数据

                    'bet_array' => $bet_array,

                    'honor_status' => $honor['honor_status'],

                    'sort'   => $honor['sort'],

                ],

                'time' => date('Y-m-d H:i', $time)

            );

            Gateway::sendToUid($uid, json_encode($send));

            $this->db->query('COMMIT');

            return;

        } catch (Exception $err) {

            $this->db->query('ROLLBACK');

            $send = array('commandid' => 3004, 'is_popup_msg' => '1', 'nickname' => '', 'content' => "System error, please contact customer service");

            Gateway::sendToUid($uid, json_encode($send));

            return;

        }

    }

    

    /**

     * 获取当前的投注内容

     */

    public function getBettingData()

    {

        //验证签名

        $res = verificationSignature();



        if($res['status'] !== "success"){

            @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 签名验证失败(获取投注内容): '.json_encode($res,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);

            if($res['code'] == 3){

                ErrorCode::errorResponse(999998,"Request timed out, please make sure your mobile phone system time is: Beijing (China) time, refresh and try again!");

            }

            ErrorCode::errorResponse(999999,"Signing failed, please make sure the app is the latest version and try again!");

        }

//        $this->checkAuth();





        //接收参数

        $type = $_REQUEST['type'];

        $uid = (int)$_REQUEST['userid'];

        $roomid = (int)$_REQUEST['roomid'];

        $lotteryType = (int)$_REQUEST['lotteryType'];

        $offSet = !empty($_REQUEST['offSet'])?(int)$_REQUEST['offSet']:0; //偏移量

        $listLen = 10; //每次的记录条数

        $nowtime=time();

        //获取期号

        $time = time();

        $info = D('workerman')->getQihao($lotteryType,$time,$roomid);

        if($lotteryType==12){ //足彩单独处理

            $info['issue']=1;

        }

        if(empty($info['issue'])) return;



        $sql = "SELECT O.id,O.lottery_type, O.user_id as uid, O.money,O.single_money, O.issue, O.addtime, O.order_no, O.way, U.nickname, U.avatar FROM un_orders AS O LEFT JOIN un_user AS U ON O.user_id = U.id WHERE O.room_no={$roomid} AND O.issue={$info['issue']} AND O.lottery_type={$lotteryType} AND O.state=0 AND O.award_state=0 AND O.user_id = {$uid} AND O.chase_number = '' ORDER BY O.id DESC";

        $self_order = $this->db->getall($sql);



        if($offSet==0){ //刚进房间

            $sql = "SELECT O.id,O.lottery_type, O.user_id as uid, O.money, O.single_money, O.issue, O.addtime, O.order_no, O.way, U.nickname, U.avatar FROM un_orders AS O LEFT JOIN un_user AS U ON O.user_id = U.id WHERE O.room_no={$roomid} AND O.issue={$info['issue']} AND O.lottery_type={$lotteryType} AND O.state=0 AND O.award_state=0 AND O.user_id <> {$uid} AND O.chase_number = '' ORDER BY O.id DESC LIMIT 0, 5";

            $orders = $this->db->getall($sql);

            $list = array_merge($orders,$self_order);

        }else{ //下拉操作

            $list = $self_order;

        }



        $sql = "SELECT O.id FROM un_orders AS O LEFT JOIN un_user AS U ON O.user_id = U.id WHERE O.room_no={$roomid} AND O.issue={$info['issue']} AND O.lottery_type={$lotteryType} AND O.state=0 AND O.award_state=0 AND O.user_id = {$uid} AND O.chase_number = '' ORDER BY O.id DESC LIMIT {$offSet}, {$listLen}";

        $self_order2 = $this->db->getall($sql);

        $self2 = array();

        foreach ($self_order2 as $v){

            $self2[]  = $v['id'];

        }


        //实例化Gateway

        $Gateway = O('Gateway');

        $Gateway::$registerAddress = C('Gateway');



        if(empty($list)){

            $sendstr = json_encode(array('commandid' => 3018, 'data' => ''));

            Gateway::sendToUid($uid, $sendstr);

        }else {

            $rmbratio =  D('workerman')->getConfig("rmbratio", 'value');

            $data = array();

            $totalZu  = 0;

            $i=$offSet;

            foreach ($list as $k=>$v) {

                if($lotteryType==12) { //足彩单独处理

                    $sql = "SELECT pan_kou,odds,bi_feng FROM `un_orders_football` WHERE order_id={$v['id']}";

                    $fbre = $this->db->getone($sql);

                    $v['pan_kou'] = $fbre['pan_kou']; //盘口

                    $v['odds'] = $fbre['odds'];

                    $v['bi_feng'] = $fbre['bi_feng']; //比分

                }

                $zu=1;

                if(in_array($v['lottery_type'],array(7,8))){

                    $preArr = array(

                        '三中二'=>3,

                        '三全中'=>3,

                        '二全中'=>2,

                        '二中特'=>2,

                        '特串'=>2,

                        '二肖连中'=>2,

                        '三肖连中'=>3,

                        '四肖连中'=>4,

                        '二肖连不中'=>2,

                        '三肖连不中'=>3,

                        '四肖连不中'=>4,

                        '五不中'=>5,

                        '六不中'=>6,

                        '七不中'=>7,

                        '八不中'=>8,

                        '九不中'=>9,

                        '十不中'=>10,

                        '二尾连中'=>2,

                        '三尾连中'=>3,

                        '四尾连中'=>4,

                        '二尾连不中'=>2,

                        '三尾连不中'=>3,

                        '四尾连不中'=>4,

                    );

                    $wayArr = explode('_',$v['way']);

                    $len = count(explode(',',$wayArr[1]));

                    if(in_array($wayArr[0],array_keys($preArr))){

                        $zu = $this->zushu($len,$preArr[$wayArr[0]]);

                    }

                }

                $v['money'] = $this->convert($v['money'], $rmbratio);

                $v['zushu'] = $zu;

                $v['single_money'] = $this->convert(!empty($v['single_money'])?$v['single_money']:$v['money'], $rmbratio);

                // $v['addtime'] = date('H:i:s', $v['addtime']);

                $v['addtime'] = date('Y-m-d H:i:s', $v['addtime']);



                //查询用户荣誉等级

                //$honor = D('workerman')->getHonorLevel($uid);

                $honor = get_level_honor($v['uid']);

                $v['sort'] = $honor['sort'];

                $v['honor_status'] = $honor['honor_status'];



                //控制昵称显示

                $v['nickname'] = D('workerman')->getNickname($v['nickname']);

                if($v['uid'] == $uid){

                    $totalZu += $zu;

                    if(in_array($v['id'],$self2)){

                        $data[] = $v;

                        $i++;

                    }

                }else{

                    $data[] = $v;

                }

            }

            $sendstr = encode(array('commandid' => 3018, 'data' => $data,'totalZu'=>$totalZu));

            Gateway::sendToUid($uid, $sendstr);

        }

    }



    public function bayWindow(){

        $Gateway = O('Gateway');

        $Gateway::$registerAddress = C('Gateway');

        $redis = initCacheRedis();

        $list = D('workerman')->get_barrage_data();

        if(!empty($list)) {

            foreach ($list as $val) {

                $msg_data['lottery_name'] = $redis->hGet("LotteryType:{$val['lottery_type']}",'name');

                $msg_data['nickname'] = D('workerman')->getNickname($val['nickname']);

                $msg_data['avatar'] = $val['avatar'];

                $msg_data['way'] = $val['way'];

                $msg_data['money'] = $val['money'];

                $msg_data['name'] = $val['name'];

                $send_str = encode(['commandid' => 3023, 'data' => $msg_data]);

                Gateway::sendToAll($send_str);

            }

        }

        deinitCacheRedis($redis);

    }



    public function getOnceAgain(){

        //验证签名

        $res = verificationSignature();

        if($res['status'] !== "success"){

            @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 签名验证失败(撤单): '.json_encode($res,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);

            if($res['code'] == 3){

                ErrorCode::errorResponse(999998,"Request timed out, please make sure your mobile phone system time is: Beijing (China) time, refresh and try again!");

            }

            ErrorCode::errorResponse(999999,"Signing failed, please make sure the app is the latest version and try again!");

        }

        //接收参数

        $uid = (int)$_REQUEST['userid'];

        $roomid = (int)$_REQUEST['roomid'];

        $lotteryType = (int)$_REQUEST['lotteryType'];

        $Gateway = O('Gateway');

        $Gateway::$registerAddress = C('Gateway');

        if (in_array($lotteryType,['2','9'])) { //un_bjpk10

            $sql = "SELECT qihao AS issue,kaijiangshijian FROM un_bjpk10 WHERE lottery_type = {$lotteryType} order by kaijiangshijian desc";

        } elseif (in_array($lotteryType,['7','8'])) { //un_lhc

            $sql="SELECT issue FROM un_lhc WHERE lottery_type = {$lotteryType} order by lottery_time desc";

        } elseif (in_array($lotteryType,['5','6','11'])) { //un_ssc

            $sql="SELECT issue FROM un_ssc WHERE lottery_type = {$lotteryType} order by lottery_time desc";

        } elseif (in_array($lotteryType,['4'])) { //un_xyft

            $sql="SELECT qihao AS issue FROM un_xyft order by kaijiangshijian desc";

        } elseif (in_array($lotteryType,['1','3'])) { //un_open_award

            $sql = "SELECT issue FROM un_open_award WHERE lottery_type = {$lotteryType} order by open_time desc";

        } elseif (in_array($lotteryType,['10'])) { //un_nn

            $sql="SELECT issue FROM un_nn WHERE lottery_type = {$lotteryType} order by lottery_time desc";

        }

        $issue = $this->db->getone($sql)['issue'];

        $sql = "select way,money,award_state,lottery_type,room_no,user_id from #@_orders where user_id = {$uid} and room_no = {$roomid} and award_state !=0 and state = 0 and issue = {$issue} and lottery_type = {$lotteryType} and chase_number = ''";

        $order_list = $this->db->getall($sql);

        if (!empty($order_list) && count($order_list) == 1) {

            $cidArr= Gateway::getClientIdByUid($uid);

            if (!empty($cidArr)) {

                foreach ($cidArr as $cv) {

                    $uinfo = Gateway::getSession($cv);

                    $wmRoomID = $uinfo['roomid'];

                    if ($order_list[0]['award_state'] == 1 && $wmRoomID == $roomid) {

                        $send_message['way'] = $order_list[0]['way'];

                        $send_message['money'] = $order_list[0]['money'];

                        $send_message['commandid'] = 3025;

                        if (in_array($order_list[0]['lottery_type'],['7','8'])) {

                            $wArr= explode('_',$order_list[0]['way']);

                            $len = count(explode(',',$wArr[1]));

                            if($len > 1){

                                $preArr = array(

                                    '三中二'=>3,  '三全中'=>3,  '二全中'=>2,  '二中特'=>2,  '特串'=>2,

                                    '二肖连中'=>2,  '三肖连中'=>3,  '四肖连中'=>4,  '二肖连不中'=>2,  '三肖连不中'=>3,  '四肖连不中'=>4,

                                    '五不中'=>5,  '六不中'=>6,  '七不中'=>7,  '八不中'=>8,  '九不中'=>9,  '十不中'=>10,

                                    '二尾连中'=>2,  '三尾连中'=>3,  '四尾连中'=>4,  '二尾连不中'=>2,  '三尾连不中'=>3,  '四尾连不中'=>4,

                                );

                                $send_message['zushu'] = $this->zushu($len,$preArr[$wArr[0]]);

                            } else {

                                $send_message['zushu'] = 1;

                            }

                        } else {

                            $send_message['zushu'] = 1;

                        }

                        $send_message['single_money'] = $send_message['money'] / $send_message['zushu'];

                        Gateway::sendToClient($cv, encode($send_message));

                    }

                }

            }

        }

    }



    //测试代码

    public function getLongDragon(){

        D('workerman')->longDragon($_REQUEST['lottery_type'], $_REQUEST['issue']);

    }

}

