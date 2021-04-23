<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

/**
 * 大转盘前台交互处理
 */
class TurntableAction extends Action{
    /**
     * 数据表
     */
    // private $turntable_model;

    // public function __construct()
    // {
    //     parent::__construct();
    //     $this->turntable_model = D('turntable');
    // }

    //根据token获取userId
    private function getUserIdByToken($token)
    {
        $sql = "SELECT user_id FROM #@_session WHERE sessionid = '{$token}' LIMIT 1";
        return $this->db->result($sql);
    }

    //访问权限控制
    public function anotherCheckAuth($token = null){
        if (! $token) {
            $token = session_id();
        }

        //验证token
        $userId = $this->getUserIdByToken($token);
        if(!$userId)
        {
            $login = url('','user','login');
            // $this->alertMsg($login,'用户信息验证失败,请重新登录!');
            header("Location: {$login}");
            exit;
        }

        //修改最后访问时间
        $data = array(
            'lastvisit' => SYS_TIME,
        );
        $where = array(
            'user_id' => $userId,
            'sessionid' => $token
        );
        $this->db->update('#@_session',$data,$where);

        $this->userId = $userId;
        return array('token'=>true);
    }

    /**
     * 大转盘页面
     * ?m=web&c=turntable&a=index
     * @return web
     */
    public function index() {
        //验证token
        $this->anotherCheckAuth($_REQUEST['token']);

        $user_id = $this->userId;

        $now_time = time();

        //取当前正在进行的活动 
        $where = "start_time < {$now_time} AND end_time > {$now_time} AND is_underway = 1";
        $field = 'id,activity_title,activity_stage,is_underway,start_time,end_time,admin_id,rules_json,user_group_limit,turn_num,turn_pic,activity_setting_json,last_updatetime,activity_details,activity_statement';
        $running_turntable = $this->db->getOne("SELECT {$field} FROM un_turntable WHERE {$where} LIMIT 1");

        $readable_start_date = date('Y年m月d日H点', $running_turntable['start_time']);
        $readable_end_date = date('Y年m月d日H点', $running_turntable['end_time']);

        //默认值（即为可抽奖）
        $return_back = '0';
        $return_msg = '';

        if (! $running_turntable) {
            $return_back = '2';     //如果设置为2，则前台将处理为自动跳转
            $return_msg = 'Sorry, there are no ongoing activities!';
            include template('turntable-index');
            return;
        }

        //奖品设置信息
        $activity_setting_json = $running_turntable['activity_setting_json'];

        $activity_setting_arr = json_decode($activity_setting_json, true);

        //查询已中奖的客户
        $turntable_id = intval($running_turntable['id']);
        $where2 = "turntable_id = {$turntable_id} ";
        $field2 = 'id,user_id,username,turntable_id,activity_stage,prize_type,giving_status,prize_name,award_time,remark,last_updatetime';
        $award_data =  $this->db->getAll("SELECT {$field2} FROM un_turntable_award_log WHERE {$where2} ORDER BY id DESC LIMIT 50");

        //计算当前用户可以抽奖的次数
        $where3 = "user_id = {$user_id} AND turntable_id = {$turntable_id}";
        $field3 = 'id,join_count';
        $join_log = $this->db->getOne("SELECT {$field3} FROM un_turntable_join_log WHERE {$where3} LIMIT 1");

        //参与表如果没有记录，则说明是第一次参加抽奖的玩家
        if (! $join_log) {
            $is_newer = '1';
            $already_join_times = 0;
        } else {
            $is_newer = '0';
            $already_join_times = $join_log['join_count'];
        }

        //计算可参与抽奖的次数
        $arg_arr = [
            // 'start_time' => $running_turntable['start_time'] ? : 0,
            // 'end_time' => $running_turntable['end_time'] ? : 0,
            'user_id' => $user_id,
            'turntable_id' => $turntable_id,
        ];
        $join_times = D('turntable')->calculate_join_times($arg_arr);

        //最后计算结果
        $final_join_times = $join_times - $already_join_times;
        if ( $final_join_times <= 0) {
            $final_join_times = 0;
            $return_back = '1';
            $return_msg = 'Sorry, you have no draws available yet!';
            // include template('turntable-index');
            // return;
        }

        $user_group_info = $this->db->getOne("SELECT group_id FROM un_user WHERE id = {$user_id}");

        $can_join_group = array_filter(explode(',', $running_turntable['user_group_limit']));
        if (! in_array($user_group_info['group_id'], $can_join_group)) {
            $return_back = '1';
            $return_msg = 'Sorry, your member group cannot participate in this activity!';
            // include template('turntable-index');
            // return;
        }

        //前台对应的转盘样式名称
        $turn_num_class_obj = [
            '4' => 'four',
            '8' => 'eight',
            '10' => 'ten',
            '12' => 'twelve',
        ];
        $turn_class_name = $turn_num_class_obj[$running_turntable['turn_num']];

        include template('turntable-index');
    }

    /**
     * 抽奖操作
     * 2017-09-15
     */
    public function luckyDrawFun () {
        $turntable_id = intval($_POST['turntable_id']);
        $user_id = intval($_POST['user_id']);

        //暂时不取消，用户记录前台玩家操作到log
        $is_newer = intval($_POST['is_newer']);

        $now_time = time();

        $this->db->query('BEGIN');

        //查询是否为第一次抽奖的玩家
        $check_join_sql = "SELECT id,join_count FROM un_turntable_join_log
        WHERE user_id = {$user_id} AND turntable_id = {$turntable_id}";
        $user_join_info = $this->db->getOne($check_join_sql);

        //第一次参加抽奖的玩家，则往参与记录表中新增一条记录，其他情况则修改
        if (! $user_join_info['id']) {
            $insert_data = [
                'user_id' => $user_id,
                'join_count' => 1,
                'turntable_id' => $turntable_id,
                'last_updatetime' => $now_time,
                'win_lose_count' => 0,
            ];
            $res_1 = $this->db->insert('un_turntable_join_log', $insert_data);
        } else {

            //计算可参与抽奖的次数
            $arg_arr = [
                'user_id' => $user_id,
                'turntable_id' => $turntable_id,
            ];
            $join_times = D('turntable')->calculate_join_times($arg_arr);

            //如果已经没有次数（判断已用次数小于0），则中断抽奖操作
            if ($join_times <= $user_join_info['join_count']) {
                //记录这个风险用户
                lg('zp_draw_bad_user', var_export([
                    'user_id' => $user_id,
                    'turntable_id' => $turntable_id,
                    'really_join_times' => $join_times,
                    'user_current_times' => $user_join_info['join_count'],
                ], true));
                echo json_encode(['rt'=>10000, 'msg' => 'Parameter or network is abnormal!'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $update_sql = "UPDATE un_turntable_join_log SET join_count = join_count + 1, last_updatetime = {$now_time} 
                WHERE id = {$user_join_info['id']} ";

            //old-sql
            // $update_sql = "UPDATE un_turntable_join_log SET join_count = join_count + 1, last_updatetime = {$now_time}
            // WHERE user_id = {$user_id} AND turntable_id = {$turntable_id} ";

            $res_1 = $this->db->query($update_sql);
        }

        //查询这个活动的奖品设置
        $turntable_info = $this->db->getOne("SELECT turn_num,activity_setting_json,activity_stage FROM un_turntable WHERE id = {$turntable_id}");
        $activity_setting_arr = json_decode($turntable_info['activity_setting_json'], true);

        //获取用户名
        $user_info = $this->db->getOne("SELECT username,reg_type FROM un_user WHERE id = {$user_id}");

        //查询当前用户在当期的历史中奖
        $where = "user_id = {$user_id} AND turntable_id = {$turntable_id}";
        $award_data =  $this->db->getAll("SELECT COUNT(*) AS all_award,prize_id FROM un_turntable_award_log WHERE {$where} GROUP BY prize_id");
        $award_data_hash = array_column($award_data, 'all_award', 'prize_id');

        //白名单列表
        $white_list_big_arr = array_column($activity_setting_arr, 'white_list');

        //当前用户的白名单奖品列表，即优先中的奖项
        $current_user_wl_arr = [];

        //缓存计算剩余奖品份额数量
        $others_award_count_arr = [];

        foreach ($white_list_big_arr as $wl_key => $wl_val) {
            $tmp_wl_arr = explode(';', trim($wl_val, ';'));
            $others_award_count_arr[$wl_key] = $activity_setting_arr[$wl_key]['rest_prize_num'];
            
            foreach ($tmp_wl_arr as $each_wl_key => $each_wl_val) {

                list($wl_name, $wl_times) = explode('-', $each_wl_val);
                if ($wl_name == $user_info['username']) {
                    //白名单里面的中奖次数，减去历史中奖次数，得到还剩下多少白名单中奖次数
                    //注意：序号是以1开始的，不是以0，所以这里的下标要加1
                    $current_user_wl_arr[$wl_key + 1] = $wl_times - intval($award_data_hash[$wl_key + 1]);
                }

                //计算需要剩余奖品份额数量
                //注意：此处的下标以0开始的，所以不需要加1
                $others_award_count_arr[$wl_key] -= $wl_times;
            }

            //兼容如果当前用户白名单份额数量还有剩余，但奖品剩余数量已为0，则将当前用户在这个奖项上的白名单份额数量置为0
            if ($others_award_count_arr[$wl_key] < 0 && $activity_setting_arr[$wl_key]['rest_prize_num'] == 0) {
                $current_user_wl_arr[$wl_key + 1] = 0;
            }

        }

        //如果还有白名单中奖次数，则优先中白名单上的奖项
        if (array_sum($current_user_wl_arr) > 0) {
            $filter_wl_arr = array_filter($current_user_wl_arr);

            //取出key值之后，要对应地减去1
            $bingo_key = array_keys($filter_wl_arr)[0] - 1;
        }
        //如果该用户在白名单里的次数都中过了，用完了，则走后台设置的概率逻辑
        else {
            //取一列中奖概率值
            $rate_arr = array_column($activity_setting_arr, 'probability');

            $$lg_data_remaining_k = [];

            foreach ($others_award_count_arr as $remaining_k => $remaining_v) {

                //如果当前奖品份额数量在上一步骤中减去白名单数量之后，没有剩余了，则将其概率数值加到“谢谢参与”奖项中，并将当前奖品的中奖几率设置为0
                //注意：此处设置小于等于0，是为了兼容白名单份额数量加起来大于奖品剩余数量的情况
                if ($remaining_v <= 0 && $remaining_k != 0) {
                    $rate_arr[0] += $rate_arr[$remaining_k];
                    $rate_arr[$remaining_k] = 0;

                    //记录日志项
                    $lg_data_remaining_k[] = $remaining_k;
                }
            }

            //按照中奖概率来计算中奖奖品
            $bingo_key = $this->cal_bingo_prize($rate_arr);
        }

        //剩余奖品份额数量大于0的数据减去1
        if ($activity_setting_arr[$bingo_key]['rest_prize_num'] > 0) {
            --$activity_setting_arr[$bingo_key]['rest_prize_num'];

            $activity_setting_json = json_encode($activity_setting_arr, JSON_UNESCAPED_UNICODE);
            $update_sql2 = "UPDATE un_turntable SET activity_setting_json = '{$activity_setting_json}', last_updatetime = {$now_time} WHERE id = {$turntable_id} ";
            $res_2 = $this->db->query($update_sql2);
        } else {
            $res_2 = true;
        }

        //记录入用户活动中奖表
        $order_num = 'ZP' . date("YmdHis") . rand(100, 999);

        //在中奖记录表里添加数据
        $award_insert_data = [
            'user_id' => $user_id,
            'username' => $user_info['username'],
            'turntable_id' => $turntable_id,
            'activity_stage' => $turntable_info['activity_stage'],
            //当 $bingo_key 为0时，则为谢谢参与奖
            'prize_id' => $bingo_key + 1,
            'prize_type' => $activity_setting_arr[$bingo_key]['prize_type'],
            'prize_name' => $activity_setting_arr[$bingo_key]['name'],
            'prize_money' => $activity_setting_arr[$bingo_key]['num'],
            'giving_status' => $activity_setting_arr[$bingo_key]['prize_type'] == '2' ? 1 : 2,
            'award_time' => $now_time,
            'order_num' => $order_num,
            'remark' => "User {$user_info['username']} at " . date('Y-m-d H:i:s') . "award:{$activity_setting_arr[$bingo_key]['name']}",
            'last_updatetime' => $now_time,
        ];
        $res_3 = $this->db->insert('un_turntable_award_log', $award_insert_data);

        //彩金数值
        $add_money = $activity_setting_arr[$bingo_key]['num'];

        //当前余额
//        $sql = "SELECT money FROM `un_account` WHERE user_id={$user_id} for update";
        if(!empty(C('db_port'))){ //使用mycat时 查主库数据
            $sql="/*#mycat:db_type=master*/ SELECT money FROM `un_account` WHERE user_id={$user_id} for update";
        }else{
            $sql="SELECT money FROM `un_account` WHERE user_id={$user_id} for update";
        }
        $use_money = $this->db->result($sql);

        if ($add_money > 0) {
            // 为大转盘添加一个活动类别
            $insert_money_data = [
                'user_id' => $user_id,
                'order_num' => $order_num,
                'type' => 1000,     //大转盘的类别为1000
                'money' => $add_money,
                //精度调整至小数点后两位
                'use_money' => bcadd($use_money, $add_money, 2),
                'remark' => "In the carousel activity, user {$user_info['username']} at " . date('Y-m-d H:i:s') . "Award: {$activity_setting_arr[$bingo_key]['name']}",
                'verify' => 0,
                'addtime' => $now_time,
                'addip' => ip(),
                'admin_money' => 0,
                'reg_type' => $user_info['reg_type'],
            ];
            $res_4 = $this->db->insert('un_account_log', $insert_money_data);

            //添加彩金到用户账户
            $res_5 = $this->db->query("UPDATE un_account SET `money` = `money` + {$add_money} WHERE user_id = {$user_id} ");

        } else {
            $res_4 = true;
            $res_5 = true;
        }

        //全部sql执行无误，才提交执行，否则回滚
        if ($res_1 && $res_2 && $res_3 && $res_4 && $res_5 ) {
            $this->db->query('COMMIT');
            //msg的值要转换成字符串
            $json_arr = ['code' => 0, 'msg' => ($bingo_key + 1) . ''];

        } else {
            $this->db->query('ROLLBACK');
            $json_arr = ['code' => 0, 'msg' => '0'];
        }

        //记录log
        lg('zp_user_draw', var_export([
            '配置数组数据'=>$activity_setting_arr,
            '是否第一次参加'=>$is_newer,
            '用户id'=>$user_id,
            '实际中但份数为空的奖项key值'=>$lg_data_remaining_k,
            '各个奖项概率分配rate_arr'=>$rate_arr,
            'bingo_key'=>$bingo_key,
            'turntable_id'=>$turntable_id,
            'white_list_big_arr'=>$white_list_big_arr,
            'current_user_wl_arr'=>$current_user_wl_arr,
            'award_insert_data'=>$award_insert_data,
            'others_award_count_arr'=>$others_award_count_arr,
            'res_1'=>$res_1,
            'res_2'=>$res_2,
            'res_3'=>$res_3,
            'res_4'=>$res_4,
            'res_5'=>$res_5,
            'check_join_sql'=>$check_join_sql,
        ],true));

        echo json_encode($json_arr);
        exit;
    }


    /**
     * 多维数组排序
     * @param $array array
     * @param $field string
     * @param $desc bool
     */
    function sortArrByField(&$array, $field, $desc = false){
        $fieldArr = array();
        foreach ($array as $k => $v) {
            $fieldArr[$k] = $v[$field];
        }
        $sort = $desc == false ? SORT_ASC : SORT_DESC;
        array_multisort($fieldArr, $sort, $array);
    }
}
