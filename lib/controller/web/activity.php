<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/19
 * Time: 17:24
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class ActivityAction extends Action {

    private $user_model;
    private $activity;
    private $userInfo;
    private $config;
    private $numInfo;

    public function __construct() {
        parent::__construct();
        $this->activity = D('admin/activity');
        $this->user_model = D('user');
        $this->checkAuthNew();
        if(empty($this->config) || empty($this->userInfo)){
            $login = url('','user','my');
            header("Location: {$login}");
            exit;
        }

        //防止刷单
        lg('activity_log','接收到的所有参数::'.encode($_REQUEST).',implode::'.implode(':',$_REQUEST));
        $redis = initCacheRedis();
        $co_str = implode(':',$_REQUEST);
        lg('activity_log','组装key,uid::'.$_REQUEST['id'].$co_str.',查看是否生效::'.$redis->get($co_str));
        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去
            lg('activity_log','进行设置超时时间');
            $redis->expire($co_str,1); //设置它的超时
            lg('activity_log','超时时间::'.$redis->ttl($co_str));
            deinitCacheRedis($redis);
        }else{
            lg('activity_log','刷单操作::'.'uid::'.$_REQUEST['id']);
            deinitCacheRedis($redis);
            exit("刷新页面，请间隔1秒");
        }

        $this->activity->updateNum($this->userInfo['id'], $this->config['activity_type']);
        $this->numInfo = $this->activity->getNumByUid($this->userInfo['id'], $this->config['event_num'], $this->config['id'], $this->config['activity_type']);

    }

    public function boBinDetail(){
        $res = $this->getRankingByEventNum($this->config['event_num'],$this->config['id']);
        $uid = $this->userInfo['id'];

        /*redis里面博饼通用设置 开始*/
        $redis = initCacheRedis();
        $value = json_decode($redis->hGet("Config:"."bo_bin","value"),true);
        deinitCacheRedis($redis);
        /*redis里面博饼通用设置 结束*/
        foreach ($res as $key=>$val){
            if($value['isUserName'] == 2){
                $res[$key]['username'] = f_name($val['username']);
            }
        }
        $res = json_encode($res);
        $bonus_rule_img = $this->config['value']['bonus_rule_img'];
        include template('activity/boBinDetail');
    }

    public function boBinIndex(){

        $uid = $this->userInfo['id'];
        $bonus_rule_img = $this->config['value']['bonus_rule_img'];


        $res = $this->getRankingByEventNum($this->config['event_num'],$this->config['id']);
        foreach ($res as $key=>$val){
            if($val['integral'] == 0){
                unset($res[$key]);
            }
        }
        $res =  array_merge($res);



        $ranking = $this->getRankingByUid($res);

        $broadcastInfo = $this->getBroadcastInfo($this->config['event_num'],$this->config['id']);
        /*redis里面博饼通用设置 开始*/
        $redis = initCacheRedis();
        $value = json_decode($redis->hGet("Config:"."bo_bin","value"),true);
        deinitCacheRedis($redis);
        /*redis里面博饼通用设置 结束*/
        foreach ($broadcastInfo as $key=>$val){
            if($value['isTime'] == 1){
                $broadcastInfo[$key]['add_time'] = date("m-d H:i:s",$val['add_time']);
            } else {
                unset($broadcastInfo[$key]['add_time']);
            }
            if($value['isUserName'] == 2){
                $broadcastInfo[$key]['username'] = f_name($val['username']);
            }
            foreach ($this->config['value']['activity_config'] as $va){
                if($va['config_id'] == $val['gift_id']){
                    $broadcastInfo[$key]['prize_name'] = $va['config_name'];
                }
            }
        }

        //计算总抽奖次数
        $num = $this->numInfo['recharge_num'] + $this->numInfo['betting_num'] + $this->numInfo['lose_num'] + $this->numInfo['variable_num'] + $this->numInfo['free_num'] - $this->numInfo['used_num'];
        $usedNum = $this->numInfo['used_num'];
        $dayNum = $this->config['value']['upper_limit'] - $this->activity->getNumByToday($this->userInfo['id'], $this->config['event_num'], $this->config['id'], $this->config['activity_type']);
        $isTodayMaxNum = false;     //是否已达到今天博饼上限
        if($this->config['value']['upper_limit'] > 0) {
            $upperLimit = $this->config['value']['upper_limit'];
            if($usedNum >= $upperLimit) $isTodayMaxNum = true;
        }else{
            $upperLimit = 0;        //设置0则没有上限
            $dayNum = $num;         //
        }

        /*获取我抽奖纪录 开始*/
        $where = [
            'event_num'=>$this->config['event_num'],
            'activity_type'=>1,
            'user_id'=>$this->userInfo['id'],
            'is_winning'=>1
        ];
        $filed = '*';
        $order = "add_time desc";
        $winList = $this->activity->getListNew($filed, $where, $order, "", "un_activity_log");
        $winList = json_encode($winList);
        /*获取我的抽奖纪录 结束*/

        /*获取中奖记录 开始*/
        $where = [
            'activity_id'=>$this->config['id'],
            'event_num'=>$this->config['event_num'],
            'activity_type'=>1,
            'user_id'=>$this->userInfo['id']
        ];
        $filed = 'add_time,prize_name,ranking';
        $order = "add_time desc";
        $result = $this->activity->getListNew($filed, $where, $order, "", "un_activity_prize");
        foreach ($result as $key=>$val){
            $result[$key]['add_time'] = date("m-d",$val['add_time']);
        }
        $result = json_encode($result);
        /*获取中奖记录 结束*/

        $res = $this->getRankingByEventNum($this->config['event_num'],$this->config['id']);

        /*redis里面博饼通用设置 开始*/
        $redis = initCacheRedis();
        $value = json_decode($redis->hGet("Config:"."bo_bin","value"),true);
        deinitCacheRedis($redis);
        /*redis里面博饼通用设置 结束*/
        foreach ($res as $key=>$val){
            if($value['isUserName'] == 2){
                $res[$key]['username'] = f_name($val['username']);
            }
        }

        $res = json_encode($res);
        $bonus_rule_img = $this->config['value']['bonus_rule_img'];

        $auth = $this->checkActivityAuth();
        $auth = json_encode($auth);
        include template('activity/boBinIndex');
    }

    public function checkActivityAuth(){
        $arr = ['code'=>0,'msg'=>""];
        /*验证是否有活动正在开启 开始*/
        if(empty($this->config)){
            $arr['code'] = -1;
            $arr['msg'] = "No open activity";
            return $arr;
        } else {
            if($this->config['state'] == 2){
                $arr['code'] = -1;
                $arr['msg'] = "The activity is over";
                return $arr;
            }
        }
        /*验证是否有活动正在开启 结束*/

        /*会员是否可参与活动 开始*/
        if(empty($this->config['level_limit'])){
            $arr['code'] = -2;
            $arr['msg'] = "Sorry, you do not have permission to participate in this activity";
            return $arr;
        }
        if(!in_array($this->userInfo['group_id'],$this->config['level_limit'])){
            $arr['code'] = -3;
            $arr['msg'] = "Sorry, you do not have permission to participate in this activity";
            return $arr;
        }
        /*会员是否可参与活动 结束*/

        /*判断游客是否可以抽奖 开始*/
        if($this->userInfo['reg_type'] == 8){
            if($this->config['value']['tourist_state'] == 2 && !empty($this->config['value']['tourist_state'])){
                $arr['code'] = -4;
                $arr['msg'] = "Sorry, visitors are not allowed to parameter this activity";
                return $arr;
            }
        }
        /*判断游客是否可以抽奖 结束*/

        /*验证抽奖上限 开始*/
        if($this->config['value']['upper_limit'] > 0) {         //设置为0的时候等于不设置抽奖次数上限
            $num = $this->activity->getNumByToday($this->userInfo['id'], $this->config['event_num'], $this->config['id'], $this->config['activity_type']);  //用户已抽奖次数
            if($num >= $this->config['value']['upper_limit']) {
                $arr['code'] = -5;
                $arr['msg'] = "Sorry, you have reached the upper limit of the day's lucky draw, please come back tomorrow~";
                return $arr;
            }
        }
        /*验证抽奖上限 结束*/

        /*判断活动时间是否过期 开始*/
        $newTime = time();
        if($this->config['end_time'] < $newTime){
            $arr['code'] = -6;
            $arr['msg'] = "Activity has expired";
            return $arr;
        }
        /*判断活动时间是否过期 结束*/
        return $arr;
    }

    public function boBinEntrance(){

        $redis = initCacheRedis();
        $cacheKey = "boBinEntrance_uid_".$this->userInfo['id'].'_event_num_'.$this->config['event_num'];
        if($redis->get($cacheKey)) {
            ErrorCode::errorResponse(-1, 'Frequent operation');
        }
        $redis->set($cacheKey,1,5);

        $a = $this->checkActivityAuth();
        if($a['code'] != 0){
            $redis->del($cacheKey);
            deinitCacheRedis($redis);
            ErrorCode::errorResponse($a['code'], $a['msg']);
        }

        /*获取抽奖次数 开始*/
        $num = $this->numInfo['recharge_num'] + $this->numInfo['betting_num'] + $this->numInfo['lose_num'] + $this->numInfo['variable_num'] + $this->config['value']['free_num'] - $this->numInfo['used_num'];
        if($num <= 0){
            $redis->del($cacheKey);
            deinitCacheRedis($redis);
            ErrorCode::errorResponse(-1, 'Sorry, you have no draws');
        }
        /*获取抽奖次数 结束*/

        /*根据配置计算使用免费概率还是付费概率 开始*/
        $total = $this->activity->getUsedNum($this->userInfo['id'], $this->config['event_num'], $this->config['id'], 1);
        $isFree = 1;
        if($this->config['value']['free_num'] > 0){
            //使用免费概率
            if($total['free_num'] < $this->config['value']['free_num']) {
                foreach ($this->config['value']['activity_config'] as $val){
                    $rate_arr[$val['config_id']] = $val['config_free'];
                }
            } else {
                //使用付费概率
                $isFree = 2;
                foreach ($this->config['value']['activity_config'] as $val){
                    $rate_arr[$val['config_id']] = $val['config_paid'];
                }
            }
        } else {
            //使用付费概率
            $isFree = 2;
            foreach ($this->config['value']['activity_config'] as $val){
                $rate_arr[$val['config_id']] = $val['config_paid'];
            }
        }
        /*根据配置计算使用免费概率还是付费概率 结束*/


        /*开始抽奖 开始*/
        $prize = '';//奖品
        $rate = $this->cal_bingo_prize($rate_arr);
        foreach ($this->config['value']['activity_config'] as $val){
            if($rate == $val['config_id']){
                $prize = $val['config_reward'];
                $prizeName = $val['config_name'];
            }
        }
        /*开始抽奖 结束*/


        /*结果入库 开始*/
        $res = [
            'user_id'=>$this->userInfo['id'],
            'gift_id'=>$rate,
            'activity_type'=>1,
            'event_num'=>$this->config['event_num'],
            'add_time'=>time(),
            'is_free'=>$isFree,
            'prize_value'=>$prize,
            'prize_name'=>$prizeName,
            'activity_id'=>$this->config['id'],
        ];
        if($rate != 13) {
            $res['is_winning'] = 1;
        }
        $rows = $this->db->insert("un_activity_log",$res);

        if($rows > 0){

            $post = [
                'user_id' => $this->userInfo['id'],
                'activity_id' => $this->config['id'],
                'activity_type' => $this->config['activity_type'],
                'event_num' => $this->config['event_num'],
                'available_num' => $this->numInfo['recharge_num'] + $this->numInfo['betting_num'] + $this->numInfo['lose_num'] + $this->numInfo['variable_num'] + $this->config['value']['free_num'] - $this->numInfo['used_num'] - 1,
                'num' => 1,
                'type' => 2,
                'add_type' => 5,
                'add_time' => time(),
                'remarks' => "The member participates in the biscuits activity and consumes 1 opportunity to hit golden eggs"
            ];
            $this->db->insert("#@_activity_num_log", $post);

            $res = $this->getRankingByEventNum($this->config['event_num'],$this->config['id']);
            foreach ($res as $key=>$val){
                if($val['integral'] == 0){
                    unset($res[$key]);
                }
            }
            $ranking = $this->getRankingByUid($res);
            $integral = 0;
            foreach ($this->config['value']['activity_config'] as $val){
                if($rate == $val['config_id']){
                    $integral = $val['config_reward'];
                }
            }
            $arr['data'] = ['result'=>$rate,'prize_name'=>$prizeName,'num'=>$num-1,'ranking'=>$ranking,'integral'=>$integral];
            $arr['ret_msg'] = "Request succeeded";
            $arr['username'] = '';

            //删除撤单间隔标识
            $co_str = "web:activity:boBinIndex:{$this->userInfo['id']}:1:1";
            $co_str1 = "web:activity:boBinEntrance:{$this->userInfo['id']}:6:1";
            $co_str2 = "web:activity:boBinDetail:{$this->userInfo['id']}:1:1";

            lg('activity_log','删除间隔标识::'.$co_str."----".$co_str1."----".$co_str2);
            $a = $redis->del($co_str);
            $b = $redis->del($co_str1);
            $c = $redis->del($co_str2);
            lg('activity_log','删除撤单间隔标识结果::'.var_export($a,true)."----".var_export($b,true)."----".var_export($c,true));
            $redis->del($cacheKey);
            deinitCacheRedis($redis);

            ErrorCode::successResponse($arr);
        } else {
            $redis->del($cacheKey);
            deinitCacheRedis($redis);
            ErrorCode::errorResponse(-1, 'The system is busy, please try again~');
        }
        /*结果入库 结束*/
    }

    //访问权限控制
    public function checkAuthNew(){
        //验证uid
        $uid = $_REQUEST['id'];
        if(empty($uid)) {
            $this->checkAuth();
            $uid = $this->userId;
        }
        $sql = "SELECT u.id, u.group_id, u.reg_type,u.username,u.reg_type FROM un_user AS u LEFT JOIN un_session AS s ON u.id = s.user_id WHERE s.user_id = '{$uid}'";
        $userInfo = $this->db->getone($sql);

        //修改最后访问时间
        $this->db->update('#@_session',['lastvisit'=>SYS_TIME],['user_id'=>$userInfo['id']]);
        $this->userInfo = $userInfo;
        $this->config = $this->activity->getActivityConfig($_REQUEST['type']);
    }

    //获取当期抽奖排名
    public function getRankingByEventNum($eventNum,$activity_id){
        $sql = "SELECT SUM(prize_value) AS integral,a.user_id,event_num,activity_type,u.username,u.avatar FROM un_activity_log AS a LEFT JOIN un_user AS u ON a.user_id=u.id WHERE event_num = '{$eventNum}' AND activity_id = '{$activity_id}' GROUP BY a.user_id ORDER BY integral DESC LIMIT 0,100;";
        $res = $this->db->getall($sql);
        return $res;
    }

    //获取当前用户排名
    public function getRankingByUid($arr){
        $ranking = "0";//用户排名
        foreach ($arr as $key=>$val){
            if($val['user_id'] == $this->userInfo['id']){
                $ranking = $key+1;
            }
        }
        return $ranking;
    }

    //获取播报信息
    public function getBroadcastInfo($event_num,$activity_id){
        $sql = "SELECT res.add_time, res.gift_id, user.username FROM un_activity_log as res LEFT JOIN un_user as user on res.user_id = user.id WHERE res.event_num = $event_num AND res.is_winning = 1 AND res.activity_id = '{$activity_id}' ORDER BY add_time DESC limit 0,20";
        $res = $this->db->getall($sql);
        return $res;
    }

    /**
     * 圣诞活动首页
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:36
     */
    public function christmasIndex(){
        $prize_list = $this->activity->getListNew("prize_project,username,prize_name", ['activity_id'=>$this->config['id']], 'add_time desc','0,20', '#@_activity_prize');

        /*会员是否可参与活动 开始*/
        $attend = 0;
        if(empty($this->config['level_limit'])){
            $attend = -3;
        } else {
            if(!in_array($this->userInfo['group_id'],$this->config['level_limit']) || $this->userInfo['reg_type'] == 8){
                $attend = -3;
            }
        }


        include template('activity/christmas-index');
    }

    /**
     * 圣诞活动抽奖
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:36
     */
    public function christmasLucDraw(){
        $a = $this->checkActivityAuth();
        if($a['code'] != 0){
            ErrorCode::errorResponse($a['code'], $a['msg']);
        }

        /*获取抽奖次数 开始*/
        $num = $this->numInfo['recharge_num'] + $this->numInfo['betting_num'] + $this->numInfo['lose_num'] + $this->numInfo['variable_num'] + $this->config['value']['free_num'] - $this->numInfo['used_num'];
        if($num <= 0){
            ErrorCode::errorResponse(-1, 'Sorry, you have no draws');
        }
        /*获取抽奖次数 结束*/

        $prize_config = $this->config['value']['prize_config'];
        foreach ($prize_config as $key => $val) {
            if(!empty($val['prize_white'])) {  //白名单不为空的时候处理
                $rows = $this->activity->getReserveNum($val['prize_white'], $val['prize_id'], $this->config['activity_type'], $this->config['id']);
                $prize_remainder = 0;
                foreach ($rows as $ke=>$vv) {
                    $prize_remainder += $vv['num'] - $vv['usd_num'];
                }

                if(!empty($rows[$this->userInfo['id']])){ //当前用户在白名单上
                    $num = $rows[$this->userInfo['id']]['num']; //必中次数
                    $usd_num = $rows[$this->userInfo['id']]['usd_num']; //已经必中过的次数
                    if($num > $usd_num){ //必中次数未用光.
                        $prize_remainder = $prize_remainder - $num - $usd_num ;
                        $prize_config[$key]['win'] = 1;//必中
                    }
                }
            }
            $prize_config[$key]['prize_remainder'] = $val['prize_remainder'] - $prize_remainder;
            if($val['prize_remainder'] == 0){
                $prize_config[0]['prize_reward'] += $val['prize_reward'];
                $prize_config[$key]['prize_reward'] = 0;
            }
        }
        $rate = 0;
        foreach ($prize_config as $va){
            if(!empty($va['win']) && $va['win'] == 1){
                $rate = $va['prize_id'];
                break;
            } else {
                $rate_arr[$va['prize_id']] = $va['prize_reward'];
            }

        }
        /*开始抽奖 开始*/
        if($rate == 0){
            $rate = $this->cal_bingo_prize($rate_arr);
        }
        foreach ($prize_config as $v){
            if($rate == $v['prize_id']){

                $post_data = [
                    'user_id' => $this->userInfo['id'],
                    'gift_id' => $v['prize_id'],
                    'activity_type' => $this->config['activity_type'],
                    'event_num' => $this->config['event_num'],
                    'add_time' => time(),
                    'prize_name' => $v['prize_name'],
                    'activity_id' => $this->config['id']
                ];

                $post_data1 = [
                    'user_id' => $this->userInfo['id'],
                    'activity_id' => $this->config['id'],
                    'username' => $this->userInfo['username'],
                    'activity_id' => $this->config['id'],
                    'prize_type' => $v['prize_type'],
                    'activity_type' => $this->config['activity_type'],
                    'prize_name' => $v['prize_name'],
                    'prize_project' => $v['prize_project'],
                    'event_num' => $this->config['event_num'],
                    'add_time' => time(),
                    'use_num' => 1,
                ];

                $post_data2 = [
                    'user_id' => $this->userInfo['id'],
                    'activity_id' => $this->config['id'],
                    'activity_type' => $this->config['activity_type'],
                    'event_num' => $this->config['event_num'],
                    'available_num' => $this->numInfo['recharge_num'] + $this->numInfo['betting_num'] + $this->numInfo['lose_num'] + $this->numInfo['variable_num'] + $this->config['value']['free_num'] - $this->numInfo['used_num'] - 1,
                    'num' => 1,
                    'type' => 2,
                    'add_type' => 5,
                    'add_time' => time(),
                    'remarks' => "Members participate in the lucky draw to use 1 lucky draw opportunity"
                ];
                if($v['prize_type'] == 1){ //实物

                    $post_data['is_winning'] = 1;

                    $post_data1['remark'] = 'Users get through lottery '.$v['prize_name'];

                } elseif($v['prize_type'] == 2) { //彩金

                    $post_data['is_winning'] = 1;

                    $post_data1['remark'] = 'Users get coins through lottery activities '.$v['prize_design'];
                    $post_data1['prize_money'] = $v['prize_design'];

                } elseif($v['prize_type'] == 3) { //无

                    $post_data['is_winning'] = 2;

                }
                $this->db->query('BEGIN');//开启事务
                $rows = $this->db->insert("#@_activity_log",$post_data);
                $rows1 = $this->db->insert("#@_activity_num_log", $post_data2);
                $rows2 = true;
                if($v['prize_type'] != 3){
                    $rows2 = $this->db->insert("#@_activity_prize",$post_data1);
                    if($rows2 < 0){
                        $rows2 = false;
                    }
                }
                foreach ($this->config['value']['prize_config'] as $ke=>$value) {
                    if($value['prize_id'] == $rate){
                        $this->config['value']['prize_config'][$ke]['prize_remainder'] =  $value['prize_remainder'] - 1;
                    }
                }
                $rows3 = $this->db->update("#@_activity", ['value' => json_encode($this->config['value'],JSON_UNESCAPED_UNICODE)], ['id' => $this->config['id']]);
                $xxx = [$rows,$rows1,$rows2,$rows3];
                lg('christmas', "用户抽奖完成，数据库执行情况：".var_export($xxx,true)."\n");
                if ($rows > 0 && $rows1 > 0 && $rows2 && $rows3) {
                    $this->db->query('COMMIT');//提交事务
                    $result = [
                        'num' => $post_data2['available_num'],
                        'prize_name' => $v['prize_name'],
                        'prize_project' => $v['prize_project'],
                        'prize_img' => $v['prize_img'],
                        'prize_id' => $v['prize_id']
                    ];
                    $res = ['result' => $result, 'ret_msg' => "Request succeeded"];
		    
                    //删除撤单间隔标识
                    $co_str = "web:activity:christmasIndex:{$this->userInfo['id']}:1:2";
                    $co_str1 = "web:activity:christmasPrizeList:{$this->userInfo['id']}:2:1";
                    $co_str2 = "web:activity:christmasLucDraw:{$this->userInfo['id']}:2";
                    $redis = initCacheRedis();
                    lg('activity_log','删除间隔标识::'.$co_str."----".$co_str1."----".$co_str2);
                    $a = $redis->del($co_str);
                    $b = $redis->del($co_str1);
                    $c = $redis->del($co_str2);
                    lg('activity_log','删除撤单间隔标识结果::'.var_export($a,true)."----".var_export($b,true)."----".var_export($c,true));
                    deinitCacheRedis($redis);

                    ErrorCode::successResponse($res);
                } else {
                    $this->db->query('ROLLBACK');//事务回滚
                    ErrorCode::errorResponse(-1, 'Network error, please try again');
                }
            }
        }
    }


    /**
     * 圣诞活动我的奖品
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-08 14:22
     */
    public function christmasPrizeList(){
        //分页数据
        $page_cfg = $this->activity->getConfig(100009); //获取每页展示多少数据
        $pageCnt = isset($page_cfg['value']) ? $page_cfg['value'] : 20;
        $page = (int) $_REQUEST['page'];
        $page = empty($page) ? 1 : $page;

        $sql = "select * from #@_activity_prize where activity_id = {$this->config['id']} and user_id = {$this->userInfo['id']} limit ".($page - 1) * $pageCnt.", $pageCnt";
        $rt = $this->db->getall($sql);
        foreach ($rt as $key=>$val) {
            $rt[$key]['add_time'] = date("Y-m-d H:i:s",$val['add_time']);
        }
        $data = [
            'result' => $rt,
            'ret_msg' => "Request succeeded"
        ];
        ErrorCode::successResponse($data);
    }

    /**
     * 九宫格活动首页接口
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:36
     */
    public function nineIndex(){
        //中奖者滚动信息
        $prize_list = $this->activity->getListNew("prize_project,username,prize_name", ['activity_id'=>$this->config['id']], 'add_time desc','0,20', '#@_activity_prize');

        //活动奖品
        $prize_config = [];
        foreach ($this->config['value']['prize_config'] as $val) {
            $tmp['prize_id'] = $val['prize_id'];
            $tmp['prize_project'] = $val['prize_project'];
            $tmp['prize_name'] = $val['prize_name'];
            $tmp['prize_img'] = $val['prize_img'];
            $prize_config[] = $tmp;
        }

        //抽奖次数
        $num = $this->numInfo['recharge_num'] + $this->numInfo['betting_num'] + $this->numInfo['lose_num'] + $this->numInfo['variable_num'] + $this->config['value']['free_num'] - $this->numInfo['used_num'];
        $data = [
            'result' => array(
                'list'=>$prize_list,
                'config'=>$prize_config,
                'num'=>$num
            ),
            'ret_msg' => "Request succeeded"
        ];
        ErrorCode::successResponse($data);
    }


    /**
     * 九宫格活动规则接口
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:36
     */
    public function nineRole(){

        $page_cfg = $this->activity->getConfig(100009); //获取每页展示多少数据
        $pageCnt = isset($page_cfg['value']) ? $page_cfg['value'] : 20;
        $page = (int) $_REQUEST['page'];
        $page = empty($page) ? 1 : $page;

        $sql = "select prize_project,prize_name,add_time from #@_activity_prize where activity_id = {$this->config['id']} and user_id = {$this->userInfo['id']} limit ".($page - 1) * $pageCnt.", $pageCnt";
        $rt = $this->db->getall($sql);
        foreach ($rt as $key=>$val) {
            $rt[$key]['add_time'] = date("Y-m-d H:i:s",$val['add_time']);
        }
        $role['topup_money'] = $this->config['rules_play']['every_topup'];
        $role['topup_num'] = $this->config['rules_play']['every_topup_val'];
        $role['bet_money'] = $this->config['rules_play']['every_bet'];
        $role['bet_num'] = $this->config['rules_play']['every_bet_val'];
        $role['lose_money'] = $this->config['rules_play']['every_lose'];
        $role['lose_num'] = $this->config['rules_play']['every_lose_val'];
        $role['win_money'] = $this->config['rules_play']['every_win'];
        $role['win_num'] = $this->config['rules_play']['every_win_val'];
        $role['send_start_time'] = date("Y-m-d H:i",$this->config['rules_play']['send_start_time']);
        $role['send_end_time'] = date("Y-m-d H:i",$this->config['rules_play']['send_end_time']);
        $data = [
            'result' => array(
                'details'=>$this->config['value']['details'],
                'statement'=>$this->config['value']['statement'],
                'time'=>array("start_time"=>date("Y-m-d H:i",$this->config['start_time']),"end_time"=>date("Y-m-d H:i",$this->config['end_time'])),
                'role'=>$role,
                'list'=>$rt
            ),
            'ret_msg' => "Request succeeded"
        ];
        ErrorCode::successResponse($data);
    }

    /**
     * 九宫格活动抽奖
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:36
     */
    public function nineLucDraw(){
        $a = $this->checkActivityAuth();
        if($a['code'] != 0){
            ErrorCode::errorResponse($a['code'], $a['msg']);
        }

        /*获取抽奖次数 开始*/
        $num = $this->numInfo['recharge_num'] + $this->numInfo['betting_num'] + $this->numInfo['lose_num'] + $this->numInfo['variable_num'] + $this->config['value']['free_num'] - $this->numInfo['used_num'];
        if($num <= 0){
            ErrorCode::errorResponse(-1, 'Sorry, you have no draws');
        }
        /*获取抽奖次数 结束*/

        $prize_config = $this->config['value']['prize_config'];
        foreach ($prize_config as $key => $val) {
            if(!empty($val['prize_white'])) {  //白名单不为空的时候处理
                $rows = $this->activity->getReserveNum($val['prize_white'], $val['prize_id'], $this->config['activity_type'], $this->config['id']);
                $prize_remainder = 0;
                foreach ($rows as $ke=>$vv) {
                    $prize_remainder += $vv['num'] - $vv['usd_num'];
                }

                if(!empty($rows[$this->userInfo['id']])){ //当前用户在白名单上
                    $num = $rows[$this->userInfo['id']]['num']; //必中次数
                    $usd_num = $rows[$this->userInfo['id']]['usd_num']; //已经必中过的次数
                    if($num > $usd_num){ //必中次数未用光.
                        $prize_remainder = $prize_remainder - $num - $usd_num ;
                        $prize_config[$key]['win'] = 1;//必中
                    }
                }
            }
            $prize_config[$key]['prize_remainder'] = $val['prize_remainder'] - $prize_remainder;
            if($val['prize_remainder'] == 0){
                $prize_config[0]['prize_reward'] += $val['prize_reward'];
                $prize_config[$key]['prize_reward'] = 0;
            }
        }
        $rate = 0;
        foreach ($prize_config as $va){
            if(!empty($va['win']) && $va['win'] == 1){
                $rate = $va['prize_id'];
                break;
            } else {
                $rate_arr[$va['prize_id']] = $va['prize_reward'];
            }

        }
        /*开始抽奖 开始*/
        if($rate == 0){
            $rate = $this->cal_bingo_prize($rate_arr);
        }
        foreach ($prize_config as $v){
            if($rate == $v['prize_id']){

                $post_data = [
                    'user_id' => $this->userInfo['id'],
                    'gift_id' => $v['prize_id'],
                    'activity_type' => $this->config['activity_type'],
                    'event_num' => $this->config['event_num'],
                    'add_time' => time(),
                    'prize_name' => $v['prize_name'],
                    'activity_id' => $this->config['id']
                ];

                $post_data1 = [
                    'user_id' => $this->userInfo['id'],
                    'activity_id' => $this->config['id'],
                    'username' => $this->userInfo['username'],
                    'prize_type' => $v['prize_type'],
                    'activity_type' => $this->config['activity_type'],
                    'prize_project' => $v['prize_project'],
                    'prize_name' => $v['prize_name'],
                    'event_num' => $this->config['event_num'],
                    'add_time' => time(),
                    'use_num' => 1,
                ];

                $post_data2 = [
                    'user_id' => $this->userInfo['id'],
                    'activity_id' => $this->config['id'],
                    'activity_type' => $this->config['activity_type'],
                    'event_num' => $this->config['event_num'],
                    'available_num' => $this->numInfo['recharge_num'] + $this->numInfo['betting_num'] + $this->numInfo['lose_num'] + $this->numInfo['variable_num'] + $this->config['value']['free_num'] - $this->numInfo['used_num'] - 1,
                    'num' => 1,
                    'type' => 2,
                    'add_type' => 5,
                    'add_time' => time(),
                    'remarks' => "Members participate in the lucky draw to use 1 lucky draw opportunity"
                ];
                if($v['prize_type'] == 1){ //实物

                    $post_data['is_winning'] = 1;

                    $post_data1['remark'] = 'Users get through lottery '.$v['prize_name'];

                } elseif($v['prize_type'] == 2) { //彩金

                    $post_data['is_winning'] = 1;

                    $post_data1['remark'] = 'Users get coins through lottery activities '.$v['prize_design'];
                    $post_data1['prize_money'] = $v['prize_design'];

                } elseif($v['prize_type'] == 3) { //无

                    $post_data['is_winning'] = 2;

                }
                $this->db->query('BEGIN');//开启事务
                $rows = $this->db->insert("#@_activity_log",$post_data);
                $rows1 = $this->db->insert("#@_activity_num_log", $post_data2);
                $rows2 = true;
                $rows4 = true;
                $rows5 = true;

                if($v['prize_type'] != 3){

                    //该活动是否开启自动派奖
                    if ($this->config['money_auto'] == 1 && $v['prize_type'] == 2) {
                        //当前余额
//                        $sql = "SELECT money FROM `un_account` WHERE user_id={$this->userInfo['id']} for update";
                        if(!empty(C('db_port'))){ //使用mycat时 查主库数据
                            $sql="/*#mycat:db_type=master*/ SELECT money FROM `un_account` WHERE user_id={$this->userInfo['id']} for update";
                        }else{
                            $sql="SELECT money FROM `un_account` WHERE user_id={$this->userInfo['id']} for update";
                        }
                        $order_num = 'JGG' . date("YmdHis") . rand(100, 999);
                        $use_money = $this->db->result($sql);
                        $insert_money_data = [
                            'user_id' => $this->userInfo['id'],
                            'money' => $post_data1['prize_money'],
                            'use_money' => bcadd($use_money,$post_data1['prize_money'],2),
                            'remark' => $post_data1['remark'],
                            'verify' => 0,
                            'addtime' => time(),
                            'addip' => ip(),
                            'admin_money' => 0,
                            'reg_type' => $this->userInfo['reg_type'],
                            'order_num' => $order_num
                        ];
                        if ($this->config['activity_type'] == 3) {
                            $insert_money_data['type'] = 995;//九宫格类别为995
                        } elseif ($this->config['activity_type'] == 4) {
                            $insert_money_data['type'] = 993;//福袋类别为993
                        } elseif ($this->config['activity_type'] == 5) {
                            $insert_money_data['type'] = 992;//刮刮乐类别为992
                        }
                        $rows4 = $this->db->insert('un_account_log', $insert_money_data);
                        if ($rows4 < 0) {
                            $rows4 = false;
                        }

                        $rows5 = $this->db->query("UPDATE un_account SET `money` = `money` + '{$insert_money_data['money']}' WHERE user_id = '{$this->userInfo['id']}'");
                        if ($rows5 === false) {
                            $rows5 = false;
                        }
                        $post_data1['giving_status'] = 1;
                        $post_data1['last_updatetime'] =time();
                        $post_data1['send_people_id'] = 1;
                        $post_data1['send_people_name'] = '系统自动派奖';
                        $post_data1['order_num'] = $order_num;

                    }

                    $rows2 = $this->db->insert("#@_activity_prize",$post_data1);
                    if($rows2 < 0){
                        $rows2 = false;
                    }
                }
                foreach ($this->config['value']['prize_config'] as $ke=>$value) {
                    if($value['prize_id'] == $rate){
                        $this->config['value']['prize_config'][$ke]['prize_remainder'] =  $value['prize_remainder'] - 1;
                    }
                }
                $rows3 = $this->db->update("#@_activity", ['value' => json_encode($this->config['value'],JSON_UNESCAPED_UNICODE)], ['id' => $this->config['id']]);

                $xxx = [$rows,$rows1,$rows2,$rows3,$rows4,$rows5];
                lg('nine_gong', "用户{$this->userInfo['id']}抽奖完成，数据库执行情况：".var_export($xxx,true)."\n");
                if ($rows > 0 && $rows1 > 0 && $rows2  && $rows3 && $rows4 > 0 && $rows5) {
                    $this->db->query('COMMIT');//提交事务
                    $result = [
                        'num' => $post_data2['available_num'],
                        'prize_name' => $v['prize_name'],
                        'prize_project' => $v['prize_project'],
                        'prize_img' => $v['prize_img'],
                        'prize_id' => $v['prize_id']
                    ];
                    $res = ['result' => $result, 'ret_msg' => "Request succeeded"];

                    //删除撤单间隔标识
                    $co_str = "web:activity:christmasIndex:{$this->userInfo['id']}:1:2";
                    $co_str1 = "web:activity:christmasPrizeList:{$this->userInfo['id']}:2:1";
                    $co_str2 = "web:activity:christmasLucDraw:{$this->userInfo['id']}:2";
                    $redis = initCacheRedis();
                    lg('activity_log','删除间隔标识::'.$co_str."----".$co_str1."----".$co_str2);
                    $a = $redis->del($co_str);
                    $b = $redis->del($co_str1);
                    $c = $redis->del($co_str2);
                    lg('activity_log','删除撤单间隔标识结果::'.var_export($a,true)."----".var_export($b,true)."----".var_export($c,true));
                    deinitCacheRedis($redis);

                    ErrorCode::successResponse($res);
                } else {
                    $this->db->query('ROLLBACK');//事务回滚
                    ErrorCode::errorResponse(-1, 'Network error, please try again');
                }
            }
        }
    }


}