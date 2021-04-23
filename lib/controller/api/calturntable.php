<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class CalturntableAction extends Action
{

    public function __construct() 
    {
        parent::__construct();
    }

    /**
     * 统计前一日输赢，并修改数据表 un_turntable_join_log 中的 win_lose_count 字段
     * 2017-10-17
     */
    public function do_cal_count()
    {
        $sql = 'SELECT id,end_time FROM `un_turntable` WHERE is_underway = 1';
        $turntable_info = $this->db->getOne($sql);

        $now_time = time();

        $lg_data = [];

        if (! $turntable_info || $turntable_info['end_time'] < $now_time) {
            lg('zp_crontab_cal_count', var_export(['running_flag' => 0], true) . "\n" . str_repeat('-', 30));
            return false;
        }

        //前一天一整天的时间段
        $start_time = strtotime('00:00:00 -1 day');
        $end_time = strtotime('23:59:59 -1 day');

        // //debug
        // $start_time = '1490000000';
        // $end_time   = '1510000000';

        $sql2 = "SELECT SUM(`money`) AS bet, SUM(`award`) AS win, user_id 
            FROM un_orders 
            WHERE award_state > 0 
            AND state = 0 
            AND `addtime` >= {$start_time} 
            AND `addtime` <= {$end_time} 
            GROUP BY user_id";

        // //debug
        // $sql2 .= ' LIMIT 80,5';
        $order_data = $this->db->getALL($sql2);
        if (! $order_data) {
            lg('zp_crontab_cal_count', var_export(['running_flag' => 1, 'no_bet_data' => 1], true) . "\n" . str_repeat('-', 30));
            return false;
        }

        $turntable_id = $turntable_info['id'];


        //取出活动规则输赢得到次数的配置项
        $rules_json = $this->db->getOne("SELECT rules_json FROM un_turntable WHERE id = {$turntable_id}");
        $rules_arr = json_decode($rules_json['rules_json'], true);


        //为了兼容玩法规则的老数据，此处将方式二放在方式一前面判断处理，
        //方式二
        if ($rules_arr['rules_type'] == '2') {
        }
        //方式一
        else {
            $every_win = $rules_arr['every_win'];
            $every_win_val = $rules_arr['every_win_val'];

            $every_lose = $rules_arr['every_lose'];
            $every_lose_val = $rules_arr['every_lose_val'];

            $lg_data['every_win'] = $every_win;
            $lg_data['every_win_val'] = $every_win_val;
            $lg_data['every_lose'] = $every_lose;
            $lg_data['every_lose_val'] = $every_lose_val;
        }


        foreach ($order_data as $v) {
            $user_id = $v['user_id'];
            $check_sql = "SELECT id 
                FROM un_turntable_join_log 
                WHERE user_id = {$user_id} 
                AND turntable_id = {$turntable_id}";

            $user_join_data = $this->db->getALL($check_sql);

            //盈亏，正数为输分，负数为赢分
            $win_lose = $v['bet'] - $v['win'];

            $win_lose_count = 0;

            //输分的情况
            if ($win_lose > 0 && $every_lose != 0) {
                $win_lose_count = floor($win_lose / $every_lose) * $every_lose_val;
                $lg_data[$user_id]['user_is_win'] = 0;
            }
            //赢分的情况
            elseif ($win_lose < 0 && $every_win != 0) {
                $win_lose = abs($win_lose);
                $win_lose_count = floor($win_lose / $every_win) * $every_win_val;
                $lg_data[$user_id]['user_is_win'] = 1;
            }
            //没有输赢的情况
            else {
                continue;
            }

            $lg_data[$user_id]['raw_win_lose'] = $v['bet'] - $v['win'];
            $lg_data[$user_id]['win'] = $v['win'];
            $lg_data[$user_id]['bet'] = $v['bet'];
            $lg_data[$user_id]['abs_win_lose'] = $win_lose;
            $lg_data[$user_id]['win_lose_count'] = $win_lose_count;

            //如果不满足，则跳出该层循环
            if ($win_lose_count == 0) {
                continue;
            }

            if (! $user_join_data) {
                //没有该活动记录的用户，需要新增一条记录
                $exec_sql = "INSERT INTO un_turntable_join_log 
                    (`id`, `user_id`, `join_count`, `turntable_id`, `win_lose_count`, `last_updatetime`)
                    VALUES (NULL, '{$user_id}', 0, '{$turntable_id}', {$win_lose_count}, {$now_time})";
            } else {
                $exec_sql = "UPDATE un_turntable_join_log 
                    SET win_lose_count = win_lose_count + {$win_lose_count}
                    WHERE user_id = {$user_id}
                    AND turntable_id = {$turntable_id} ";
            }

            $tmp_rt = $this->db->query($exec_sql);
            $lg_data[$user_id]['sql_info'] = [
                'sql' => $exec_sql,
                'rt' => $tmp_rt,
            ];
        }


        lg('zp_crontab_cal_count', var_export(['running_flag' => 1, 'lg_data' => $lg_data], true) . "\n" . str_repeat('-', 30));

        echo json_encode(['rt' => 1]);
        exit;
    }

    /**
     * 圣诞活动输分抽奖次数统计
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-03 11:44
     */
    public function countLoseNum(){
        $activityModel = D('admin/activity');
        $config = $activityModel->getActivityConfig(2);
        if(empty($config) || $config['end_time'] < time()){
            lg('christmas', "没有活动开启\n");
            return false;
        }
        if(!($config['rules_play']['send_start_time'] < time() && $config['rules_play']['send_end_time'] > time())){
            lg('christmas', "当前时间不在盈亏赠送抽奖次数时间范围内，赠送时间：".date("Y-m-d H:i:s",$config['rules_play']['send_start_time'])." 至 ".date("Y-m-d H:i:s",$config['rules_play']['send_end_time'])."\n");
            return false;
        }
        //获取前一天一整天的投注数据
        $start_time = strtotime('00:00:00 -1 day');
        $end_time = strtotime('23:59:59 -1 day');
        $sql2 = "SELECT SUM(`money`) AS bet, SUM(`award`) AS win, user_id 
                FROM un_orders 
                WHERE award_state > 0 
                AND state = 0 
                AND addtime between '{$start_time}' and '{$end_time}' 
                AND reg_type NOT IN (8,9) 
                GROUP BY user_id";
        $order_data = $this->db->getALL($sql2);
        if (empty($order_data)) {
            lg('christmas', "活动期间没有用户投注\n");
            return false;
        }

        foreach ($order_data as $key => $val) {
            lg('christmas', "用户ID：{$val['user_id']} 活动期间投注总额：{$val['bet']} 中奖总额总额：{$val['win']}\n");
            
            //获取已用抽奖次数
            $count  = $activityModel->getUsedNum($val['user_id'], $config['event_num'], $config['id'], 2);
            $used_num = $count['free_num'] + $count['paid_num'];

            //盈亏，正数为输分，负数为赢分
            $win_lose = $val['bet'] - $val['win'];
            $lose_num = 0;
            if ($win_lose > 0 && $config['rules_play']['every_lose'] > 0) { //输分的情况
                $lose_num = floor($win_lose / $config['rules_play']['every_lose']) * $config['rules_play']['every_lose_val'];
                $remarks = "Members lose points every day and get {$lose_num} chance to draw";
            } elseif ($win_lose < 0 && $config['rules_play']['every_win'] > 0) {  //赢分的情况
                $win_lose = abs($win_lose);
                $lose_num = floor($win_lose / $config['rules_play']['every_win']) * $config['rules_play']['every_win_val'];
                $remarks = "Members earn points every day and get {$lose_num} chances to draw";
            } else {  //没有输赢的情况
                continue;
            }

            //查询用户是否参加过当前活动
            $sql = "select * from #@_activity_num where user_id = '{$val['user_id']}' and activity_id = '{$config['id']}' and activity_type = '{$config['activity_type']}' and event_num = '{$config['event_num']}'";
            $list = $this->db->getone($sql);
            if(empty($list)){

                $this->db->query('BEGIN');//开启事务
                $check = true;
                $check1 = true;

                if($lose_num > 0){
                    $post = [
                        'user_id' => $val['user_id'],
                        'activity_id' => $config['id'],
                        'activity_type' => $config['activity_type'],
                        'event_num' => $config['event_num'],
                        'lose_num' => $lose_num,
                        'used_num' => $used_num
                    ];
                    $rows = $this->db->insert("#@_activity_num", $post);
                    $sql = "新用户参加抽奖活动，添加活动次数数据\n".$this->db->_sql();
                    lg('christmas', $sql."\n");
                }

                if($lose_num > 0){
                    $post1 = [
                        'user_id' => $val['user_id'],
                        'activity_id' => $config['id'],
                        'activity_type' => $config['activity_type'],
                        'event_num' => $config['event_num'],
                        'available_num' => $lose_num,
                        'num' => $lose_num,
                        'type' => 1,
                        'add_type' => 3,
                        'add_time' => time(),
                        'remarks' => $remarks
                    ];
                    $rows1 = $this->db->insert("#@_activity_num_log", $post1);
                    $sql = "添加输赢获得次数日志\n".$this->db->_sql();
                    lg('christmas', $sql."\n");

                    if($rows1 < 0){
                        $check = false;
                    }
                }
                $a = [$rows,$check,$check1];
                lg('christmas', var_export($a,true)."\n");
                if($rows > 0 && $check && $check1){
                    $this->db->query('COMMIT');//提交事务
                    lg('christmas', "定时任务执行成功\n");
                } else {
                    $this->db->query('ROLLBACK');//事务回滚
                    lg('christmas', "定时任务执行成功\n");
                }

            } else {

                $num = $lose_num - $list['lose_num'];
                if($lose_num > $list['lose_num']){
                    $this->db->query('BEGIN');//开启事务
                    $rows = $this->db->update("#@_activity_num", ['lose_num' => $lose_num ], ['id' => $list['id']]);
                    $sql = "更新盈亏获得的次数字段(lose_num)\n".$this->db->_sql();
                    lg('christmas', $sql."\n");
                    $post = [
                        'user_id' => $list['user_id'],
                        'activity_id' => $list['activity_id'],
                        'activity_type' => $list['activity_type'],
                        'event_num' => $list['event_num'],
                        'available_num' => $num + $list['recharge_num'] + $list['betting_num'] + $list['lose_num'] + $list['variable_num'] - $list['used_num'],
                        'num' => $num,
                        'type' => 1,
                        'add_type' => 3,
                        'add_time' => time(),
                        'remarks' => $remarks
                    ];
                    $rows1 = $this->db->insert("#@_activity_num_log", $post);
                    $sql = "添加输赢获得次数日志\n".$this->db->_sql();
                    lg('christmas', $sql."\n");
                    $a = [$rows,$rows1];
                    lg('christmas', var_export($a,true)."\n");
                    if($rows !== false && $rows1 > 0){
                        $this->db->query('COMMIT');//提交事务
                        lg('christmas', "定时任务执行成功\n");
                    } else {
                        $this->db->query('ROLLBACK');//事务回滚
                        lg('christmas', "定时任务执行失败\n");
                    }
                }
            }
        }
    }

    /**
     * 九宫格活动输分抽奖次数统计
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-03 11:44
     */
    public function countLoseNumNineGong(){
        $activityModel = D('admin/activity');
        $config = $activityModel->getActivityConfig(3);
        if(empty($config) || $config['end_time'] < time()){
            lg('nine_gong', "没有活动开启\n");
            return false;
        }
        if(!($config['rules_play']['send_start_time'] < time() && $config['rules_play']['send_end_time'] > time())){
            lg('nine_gong', "当前时间不在盈亏赠送抽奖次数时间范围内，赠送时间：".date("Y-m-d H:i:s",$config['rules_play']['send_start_time'])." 至 ".date("Y-m-d H:i:s",$config['rules_play']['send_end_time'])."\n");
            return false;
        }
        //获取前一天一整天的投注数据
        $start_time = strtotime('00:00:00 -1 day');
        $end_time = strtotime('23:59:59 -1 day');
        $sql2 = "SELECT SUM(`money`) AS bet, SUM(`award`) AS win, user_id 
                FROM un_orders 
                WHERE award_state > 0 
                AND state = 0 
                AND addtime between '{$start_time}' and '{$end_time}' 
                AND reg_type NOT IN (8,9) 
                GROUP BY user_id";
        $order_data = $this->db->getALL($sql2);
        if (empty($order_data)) {
            lg('nine_gong', "活动期间没有用户投注\n");
            return false;
        }

        foreach ($order_data as $key => $val) {
            lg('nine_gong', "用户ID：{$val['user_id']} 活动期间投注总额：{$val['bet']} 中奖总额总额：{$val['win']}\n");

            //获取已用抽奖次数
            $count  = $activityModel->getUsedNum($val['user_id'], $config['event_num'], $config['id'], 2);
            $used_num = $count['free_num'] + $count['paid_num'];

            //盈亏，正数为输分，负数为赢分
            $win_lose = $val['bet'] - $val['win'];
            $lose_num = 0;
            if ($win_lose > 0 && $config['rules_play']['every_lose'] > 0) { //输分的情况
                $lose_num = floor($win_lose / $config['rules_play']['every_lose']) * $config['rules_play']['every_lose_val'];
                $remarks = "Members lose points every day and get {$lose_num} chance to draw";
            } elseif ($win_lose < 0 && $config['rules_play']['every_win'] > 0) {  //赢分的情况
                $win_lose = abs($win_lose);
                $lose_num = floor($win_lose / $config['rules_play']['every_win']) * $config['rules_play']['every_win_val'];
                $remarks = "Members earn points every day and get {$lose_num} chance to draw";
            } else {  //没有输赢的情况
                continue;
            }

            //查询用户是否参加过当前活动
            $sql = "select * from #@_activity_num where user_id = '{$val['user_id']}' and activity_id = '{$config['id']}' and activity_type = '{$config['activity_type']}' and event_num = '{$config['event_num']}'";
            $list = $this->db->getone($sql);
            if(empty($list)){

                $this->db->query('BEGIN');//开启事务
                $check = true;
                $check1 = true;

                if($lose_num > 0){
                    $post = [
                        'user_id' => $val['user_id'],
                        'activity_id' => $config['id'],
                        'activity_type' => $config['activity_type'],
                        'event_num' => $config['event_num'],
                        'lose_num' => $lose_num,
                        'used_num' => $used_num
                    ];
                    $rows = $this->db->insert("#@_activity_num", $post);
                    $sql = "新用户参加抽奖活动，添加活动次数数据\n".$this->db->_sql();
                    lg('nine_gong', $sql."\n");
                }

                if($lose_num > 0){
                    $post1 = [
                        'user_id' => $val['user_id'],
                        'activity_id' => $config['id'],
                        'activity_type' => $config['activity_type'],
                        'event_num' => $config['event_num'],
                        'available_num' => $lose_num,
                        'num' => $lose_num,
                        'type' => 1,
                        'add_type' => 3,
                        'add_time' => time(),
                        'remarks' => $remarks
                    ];
                    $rows1 = $this->db->insert("#@_activity_num_log", $post1);
                    $sql = "添加输赢获得次数日志\n".$this->db->_sql();
                    lg('nine_gong', $sql."\n");

                    if($rows1 < 0){
                        $check = false;
                    }
                }
                $a = [$rows,$check,$check1];
                lg('christmas', var_export($a,true)."\n");
                if($rows > 0 && $check && $check1){
                    $this->db->query('COMMIT');//提交事务
                    lg('christmas', "定时任务执行成功\n");
                } else {
                    $this->db->query('ROLLBACK');//事务回滚
                    lg('nine_gong', "定时任务执行成功\n");
                }

            } else {

                $num = $lose_num - $list['lose_num'];
                if($lose_num > $list['lose_num']){
                    $this->db->query('BEGIN');//开启事务
                    $rows = $this->db->update("#@_activity_num", ['lose_num' => $lose_num ], ['id' => $list['id']]);
                    $sql = "更新盈亏获得的次数字段(lose_num)\n".$this->db->_sql();
                    lg('christmas', $sql."\n");
                    $post = [
                        'user_id' => $list['user_id'],
                        'activity_id' => $list['activity_id'],
                        'activity_type' => $list['activity_type'],
                        'event_num' => $list['event_num'],
                        'available_num' => $num + $list['recharge_num'] + $list['betting_num'] + $list['lose_num'] + $list['variable_num'] - $list['used_num'],
                        'num' => $num,
                        'type' => 1,
                        'add_type' => 3,
                        'add_time' => time(),
                        'remarks' => $remarks
                    ];
                    $rows1 = $this->db->insert("#@_activity_num_log", $post);
                    $sql = "添加输赢获得次数日志\n".$this->db->_sql();
                    lg('nine_gong', $sql."\n");
                    $a = [$rows,$rows1];
                    lg('nine_gong', var_export($a,true)."\n");
                    if($rows !== false && $rows1 > 0){
                        $this->db->query('COMMIT');//提交事务
                        lg('nine_gong', "定时任务执行成功\n");
                    } else {
                        $this->db->query('ROLLBACK');//事务回滚
                        lg('nine_gong', "定时任务执行失败\n");
                    }
                }
            }
        }
    }
}