<?php

/**
 * 大转盘数据模型
 * Date: 2017-10-24
 */
!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'model' . DS . 'common.php');

/**
 * 大转盘数据模型类
 * 2017-10-24
 */
class TurntableModel extends CommonModel
{

    /**
     * 计算用户总共的抽奖次数
     * 2017-09-15
     */
    public function calculate_join_times(array $arg_arr)
    {
        $user_id = $arg_arr['user_id'];
        $turntable_id = $arg_arr['turntable_id'];

        //是否为后台调用
        $is_from_admin = boolval($arg_arr['is_from_admin']);

        //缓存日志数据数组
        $lg_data = [
            '__user_id' => $user_id,
            '__turntable_id' => $turntable_id,
            '__is_from_admin' => $is_from_admin,
        ];

        //缓存可抽奖次数
        $play_times = 0;

        //查询活动规则，注意，此处的所有规则，都建立在配置项不为空的情况下
        $turntable_info = $this->db->getOne("SELECT start_time,end_time,rules_json FROM un_turntable WHERE id = {$turntable_id}");
        $rules_arr = json_decode($turntable_info['rules_json'], true);

        $start_time = intval($turntable_info['start_time']);
        $end_time = intval($turntable_info['end_time']);

        //log
        $lg_data['___a_start_time'] = date('Y-m-d H:i:s', $start_time);
        $lg_data['___b_end_time'] = date('Y-m-d H:i:s', $end_time);


        //旧的方案
        // //查询投注、输赢
        // $field = "SUM(money) AS bet,SUM(award) AS win";

        //新的方案
        //查询投注
        $field = "SUM(money) AS bet";

        $order_data = $this->db->getOne("SELECT {$field} FROM un_orders WHERE award_state > 0 AND state = 0 AND addtime >= {$start_time} AND addtime <= {$end_time} AND user_id = {$user_id} ");

        //只有当产生投注时，才会计算满足了多少抽奖次数
        if ($order_data['bet']) {
            $bet_money = $order_data['bet'];

            //旧的方案
            // $win_money = $order_data['win'] - $bet_money;
            // $lose_money = $bet_money - $order_data['win'];

            //a.投注
            if ($rules_arr['every_bet'] && $rules_arr['every_bet_val']) {
                $times_bet_got = floor($bet_money / $rules_arr['every_bet']) * $rules_arr['every_bet_val'];
                $play_times += $times_bet_got;

                $lg_data['_bet_money'] = $bet_money;
                $lg_data['times_bet_got'] = $times_bet_got;
            }

            //旧的方案
            // //b.赢分
            // if ($rules_arr['every_win'] && $rules_arr['every_win_val'] && $win_money > 0) {
            //     $times_win_got = floor($win_money / $rules_arr['every_win']) * $rules_arr['every_win_val'];
            //     $play_times += $times_win_got;
            //     $lg_data['_win_money'] = $win_money;
            //     $lg_data['times_win_got'] = $times_win_got;

            // }
            // //c.输分
            // if ($rules_arr['every_lose'] && $rules_arr['every_lose_val'] && $lose_money > 0) {
            //     $times_lose_got = floor($lose_money / $rules_arr['every_lose']) * $rules_arr['every_lose_val'];
            //     $play_times += $times_lose_got;
            //     $lg_data['_lose_money'] = $lose_money;
            //     $lg_data['times_lose_got'] = $times_lose_got;
            // }

            //c+d 赢分输分合并成盈亏计算 2017-10-17 new needs
            $win_lose_count = $this->db->result("SELECT win_lose_count FROM un_turntable_join_log WHERE turntable_id = {$turntable_id} AND user_id = {$user_id} ");
            $win_lose_count = intval($win_lose_count);
            $lg_data['times_win_lose_got'] = $win_lose_count;
            $play_times += $win_lose_count;


        }

        //d.充值
        if ($rules_arr['every_topup'] && $rules_arr['every_topup_val']) {
            //todo: addtime[添加时间] 可能会换成用 verify_time[审核时间]
            $recharge_data = $this->db->getOne("SELECT SUM(money) AS recharge FROM `un_account_recharge` WHERE user_id = {$user_id} AND addtime >= {$start_time} AND addtime <= {$end_time} AND status = 1");
            $times_topup_got = floor($recharge_data['recharge'] / $rules_arr['every_topup']) * $rules_arr['every_topup_val'];
            $play_times += $times_topup_got;

            $lg_data['_recharge'] = $recharge_data['recharge'];
            $lg_data['times_topup_got'] = $times_topup_got;
        }


        //排序并记录抽奖次数的组成
        ksort($lg_data);
        $lg_data['times_all'] = $play_times;


        lg('zp_cal_user_times', var_export(['lg_data'=>$lg_data], 1));


        return $play_times;
    }

    /**
     * 获取历史活动期数列表
     * 2017-11-28 update
     */
    public function fetch_activity_stage()
    {
        $fetch_sql = 'SELECT id,activity_stage FROM un_turntable ORDER BY activity_stage DESC';
        $activity_stage_info = $this->db->getAll($fetch_sql);

        //返回一个以id为键，activity_stage为值
        return array_column($activity_stage_info, 'activity_stage', 'id');
    }

    /**
     * 根据活动期数和用户信息，查询该用户的抽奖次数
     * 2017-11-28 update
     */
    public function fetch_user_times($username, $turntable_id)
    {
        $user_id = $this->fetch_user_id_by_username($username);
        if (! $user_id) {
            return [
                'code' => 10000,
                'msg' => 'Username does not exist',
                'user_id' => 0,
                'user_times' => false,
            ];
        }

        $arg_arr = [
            'user_id' => $user_id,
            'turntable_id' => $turntable_id,
            'is_from_admin' => true,
        ];

        //查询出该用户总共有多少
        $user_times = $this->calculate_join_times($arg_arr);

        //查询出该用户参与过多少次
        $fetch_join_log_sql = "SELECT join_count FROM un_turntable_join_log
            WHERE user_id = {$user_id} AND turntable_id = {$turntable_id} LIMIT 1";
        $join_log_info = $this->db->getOne($fetch_join_log_sql);

        //再减去参与过的次数，得出最终次数
        $finally_user_times = $user_times - floatval($join_log_info['join_count']);

        return [
            'code' => 0,
            'msg' => '',
            'user_id' => $user_id,
            'user_times' => $finally_user_times,
        ];
    }

    /**
     * 根据用户名，查询用户id
     * 2017-11-28 update
     */
    public function fetch_user_id_by_username($username)
    {
        $fetch_sql = "SELECT id FROM un_user WHERE username = '{$username}' LIMIT 1";
        return $this->db->result($fetch_sql);
    }
}
