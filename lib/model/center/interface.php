<?php
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class InterfaceModel extends CommonModel
{

    public function setLotteryStopOrSell($data){
        $redis  = initCacheRedis();
        $config = decode($redis->hGet("Config:{$data['nid']}",'value'));
        deinitCacheRedis($redis);
        $config['title'] = $data['title'];
        $config['status'] = $data['status'];
        $this->db->query('BEGIN');//开启事务
        $rows = $this->db->update("#@_config", ['value'=>encode($config)], ['nid'=>$data['nid']]);
        $rows1 = $this->setShowOrHide($data);

        if ($rows !== false && $rows1 !== false) {
            $this->db->query('COMMIT');//提交事务
            $this->refreshRedis("config", "all");
            return true;
        } else {
            $this->db->query('ROLLBACK');//事务回滚
            return false;
        }
    }

    public function setShowOrHide($data){
        $redis  = initCacheRedis();
        $list = decode($redis->hGet('Config:index_lottery_list','value'));
        deinitCacheRedis($redis);
        foreach ($list as $key=>$val) {
            if ($data['lottery_type'] == $val['lottery_type']) {
                $list[$key]['is_show'] = $data['is_show'];
            }
        }
        return $this->db->update("#@_config", ['value'=>encode($list)], ['nid'=>'index_lottery_list']);
    }

    public function getLotteryStopOrSell(){
        $redis  = initCacheRedis();
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:xy28_stop_or_sell','value')), 'nid'=>"xy28_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:bjpk10_stop_or_sell','value')), 'nid'=>"bjpk10_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:jnd28_stop_or_sell','value')), 'nid'=>"jnd28_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:xyft_stop_or_sell','value')), 'nid'=>"xyft_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:cqssc_stop_or_sell','value')), 'nid'=>"cqssc_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:sfc_stop_or_sell','value')), 'nid'=>"sfc_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:lhc_stop_or_sell','value')), 'nid'=>"lhc_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:jslhc_stop_or_sell','value')), 'nid'=>"jslhc_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:jssc_stop_or_sell','value')), 'nid'=>"jssc_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:nn_stop_or_sell','value')), 'nid'=>"nn_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:ffc_stop_or_sell','value')), 'nid'=>"ffc_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:sjb_stop_or_sell','value')), 'nid'=>"sjb_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:tb_stop_or_sell','value')), 'nid'=>"tb_stop_or_sell"];
        $lotterySet[] = ['config'=>decode($redis->hGet('Config:ffpk10_stop_or_sell','value')), 'nid'=>"ffpk10_stop_or_sell"];
        $list = decode($redis->hGet('Config:index_lottery_list','value'));
        deinitCacheRedis($redis);
        foreach ($lotterySet as $key=>$val) {
            foreach ($list as $value){
                if($val['config']['lottery_type'] == $value['lottery_type']){
                    $lotterySet[$key]['index'] = $value;
                }
            }
        }
        return $lotterySet;
    }
	
    public function getLotteryList($data) {
        $redis  = initCacheRedis();
        $rData = [];
        $LotteryTypeIds = $redis->lrange('LotteryTypeIds',0,-1);
        foreach ($LotteryTypeIds as $key => $value) {
            $r = $redis->hMget('LotteryType:'.$value, ['name','id']);
            $rData[] = $r;
        }
        return $rData;
    }

    public function getReportInfo($data){
        //起始时间
        $start_date = $data['start_time'];
        //结束时间
        $end_date = $data['end_time'];
        if(!empty($start_date) && !empty($end_date)){
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date." 23:59:59");
            $user_where = " WHERE reg_type NOT IN (0,8,9,11) AND regtime BETWEEN {$start_time} and {$end_time}";
            $where = " addtime BETWEEN {$start_time} and {$end_time}";
        }else{
            $start_date = date("Y-m-d");
            $end_date = date("Y-m-d");
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date." 23:59:59");
            $user_where = " WHERE reg_type NOT IN (0,8,9,11) AND regtime BETWEEN {$start_time} and {$end_time}";
            $where = " addtime BETWEEN {$start_time} and {$end_time}";
        }

        //首充值人数 首充总额
        //$sql3 = "SELECT COUNT(*) AS num, SUM(total_money) as money FROM (SELECT nums as n, total_money FROM (SELECT COUNT(l.user_id) AS nums, SUM(money) AS total_money FROM un_account_log AS l LEFT JOIN un_user AS u ON u.id = l.user_id WHERE" . $where . " AND l.type = 10 AND u.reg_type NOT IN (0,8,9,11) GROUP BY l.user_id) AS A WHERE nums = 1) as N";
        $sql3 = "SELECT COUNT(DISTINCT user_id) AS num, SUM(money) AS money FROM un_account_log AS l WHERE" . $where . " AND l.type = 10 AND `remark` LIKE '%该用户为首次充值%' AND reg_type NOT IN (0,8,9,11)";
        $recharge = O('model')->db->getOne($sql3);

        //首提现人数 首提总额
        //$sql4 = "SELECT COUNT(*) AS num, SUM(total_money) as money FROM (SELECT nums as n, total_money FROM (SELECT COUNT(l.user_id) AS nums, SUM(money) AS total_money FROM un_account_log AS l LEFT JOIN un_user AS u ON u.id = l.user_id WHERE" . $where . " AND l.type = 11 AND u.reg_type NOT IN (0,8,9,11) GROUP BY l.user_id) AS A WHERE nums = 1) as N";
        $sql4 = "SELECT COUNT(DISTINCT user_id) AS num, SUM(money) AS money FROM un_account_log AS l WHERE" . $where . " AND l.type = 11 AND `remark` LIKE '%该用户为首次提现%' AND reg_type NOT IN (0,8,9,11)";
        $cash = O('model')->db->getOne($sql4);

        //交易类型
        $trade = D('account')->getTrade();

        //交易流水
        $trades = D('account')->getTradeLog($start_date,$end_date,$trade['tranTypeIds']);
        //投注人数
        $betting_num['num'] = $this->getCntUser($start_date,$end_date,13);

        //平台金额
        $sql7 = "SELECT SUM(money) AS total_money FROM un_account  LEFT JOIN un_user AS u ON u.id = un_account.user_id  WHERE u.reg_type NOT IN (0,8,9,11)";
        $balance = O('model')->db->getOne($sql7);

        $sql = "SELECT id FROM un_user".$user_where;//统计真人 剔除假人的情况
        $user = O('model')->db->getAll($sql);
        $total_user['num'] = 0;
        $uids = array();
        if(!empty($user)) {
            foreach ($user as $v) {
                $uids[] = $v['id'];
            }
            $total_user['num'] = count($uids);
            $uids = implode($uids, ',');

            $sesionWhere = " WHERE user_id IN({$uids})";
            //查询user表 在线人数
            $sql2 = "SELECT COUNT(sessionid) AS num FROM un_session" . $sesionWhere;
            $Online_user = O('model')->db->getOne($sql2);

            //离线人数
            $Offline_user['num'] = $total_user['num'] - $Online_user['num'];

        }else{
            $total_user['num'] = 0;//注册人数
            $Online_user['num'] = 0;//在线人数
            $Offline_user['num'] = 0;//离线人数
        }
        $data = array(
            'total_num' => $total_user['num'],//注册人数
            'Online_num' => $Online_user['num'],//在线人数
            'Offline_num' => $Offline_user['num'],//离线人数
            'recharge_num' => $recharge['num'],//首存人数
            'cash_num' => $cash['num'],//首提人数
            'betting_num' => $betting_num['num'],//投注人数
            'recharge_money' => $recharge['money']? round($recharge['money'], 2):'0.00',//首存总额
            'cash_money' => $cash['money']? round($cash['money'],2) :'0.00',//首提总额
            'recharge' => round($trades['10'], 2),//充值总额
            'cash' => round($trades['11'], 2),//提现总额
            'betting_money' => round($trades['13'] - $trades['14'], 2),//投注总额
            'award_money' => round($trades['12'] - $trades['120'], 2),//中奖总额-回滚
            'selfBackwater_money' => round($trades['19'], 2),//自身投注返点总额
            'directlyBackwater_money' => round($trades['20'], 2),//直属会员投注返点总额
            'teamBackwater_money' => round($trades['21'], 2),//团体投注返点总额
            'other_money' => round($trades['18'] + $trades['32'] + $trades['1000'] + $trades['999'] + $trades['998'] + $trades['997'] + $trades['995'] + $trades['994'] + $trades['993'] + $trades['992'], 2),//其他支出 返利赠送 + 额度调整 + 大转盘 + 博饼  + 圣诞 + 红包 + 九宫格 + 平台任务 + 福袋 + 刮刮乐
            'profit_money' => bcadd(($trades['13'] - ($trades['12'] + $trades['14'] + $trades['19'] + $trades['20'] + $trades['21'] + $trades['18'] + $trades['32'] + $trades['66'] + $trades['1000'] + $trades['999'] + $trades['998'] + $trades['997'] + $trades['995'] + $trades['994'] + $trades['993'] + $trades['992'] - $trades['120'])),0,2),//盈利总额 投注-(中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+九宫格995+平台任务994+福袋993+刮刮乐992-回滚)
            'rebate_money' => round($trades['18'], 2),//天天返利 返利赠送
            'zhuan_pan_money' => $trades['1000'] ? round($trades['1000'], 2) : 0,   //大转盘
            'bo_bing_money' => $trades['999'] ? round($trades['999'], 2) : 0,      //博饼
            'christmas_money' => $trades['998'] ? round($trades['998'], 2) : 0,      //双旦
            'nine_gong_money' => $trades['995'] ? round($trades['995'], 2) : 0,     //九宫格
            'task_money' => $trades['994'] ? round($trades['994'], 2) : 0,      //平台任务
            'lucky_bag_money' => $trades['993'] ? round($trades['993'], 2) : 0,      //福袋
            'scratch_money' => $trades['992'] ? round($trades['992'], 2) : 0,      //刮刮乐
            'hong_bao_money' => $trades['997'] ? round($trades['997'], 2) : 0,     //红包
            'adjust_money' => $trades['32'] ? round($trades['32'], 2) : 0,     //额度调整总额
            'balance' => round($balance['total_money'],2), //当前平台总余额
            'betting_profit_money' => round($trades['13'] - $trades['14'] - $trades['12'], 2), //平台盈利(投注 - 撤单 - 中奖)
            'recharge_profit_money' => round($trades['10'] - $trades['11'], 2), //充值盈利(充值 - 提现 )
        );
        return $data;
    }

    /**
     * 活跃人数
     * @param $start_date string  起始日期
     * @param $end_date string  结束日期
     * @param $type array  类型
     * @return json
     */
    protected function getCntUser($start_date,$end_date,$type){
        $start_time = strtotime($start_date." 00:00:00");
        $end_time = strtotime($end_date." 23:59:59");
        $users = 0;
        if($end_time >= time()){//今天实时数据
            $start_time1 = strtotime(date("Y-m-d 00:00:00"));
            $end_time1 = strtotime(date("Y-m-d 23:59:59"));
            //人数
            $sql = "SELECT DISTINCT user_id FROM un_account_log WHERE addtime BETWEEN {$start_time1} and {$end_time1} AND `type` = {$type} AND reg_type NOT IN (0,8,9,11)";
            $num = O('model')->db->getAll($sql);
            $users += count($num);
        }

        if($start_time<strtotime(date("Y-m-d 00:00:00"))){//历史数据
            $sql = "SELECT user_id FROM `un_daily_flow` WHERE `type` = {$type} AND `addtime`  BETWEEN '{$start_date}' and '{$end_date}'";
            $num = O('model')->db->getAll($sql);
            $users += count($num);
        }
        return $users;
    }

}