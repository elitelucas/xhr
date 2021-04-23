<?php

/**
 * 红包数据模型
 * Date: 2017-11-03
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

/**
 * 红包数据模型类
 * 2017-11-03
 */
class RedpacketModel extends CommonModel
{
    // protected $redpacket_table = 'un_redpacket';
    // protected $gain_log_table = 'un_redpacket_gain_log';

    /**
     * 首页查询红包信息接口用
     * 2017-11-03
     * @param number $user_id 用户id
     */
    public function checkRedpacketByUser($user_id)
    {

        //判断是否有正在开启的活动
        if ($this->isRedpacketUnderway() === false) {
            $redis = initCacheRedis();
            $json_data = $redis->hGet('Config:redpacket_setting', 'value');
            $json_obj = json_decode($json_data, true);
            if ($json_obj['begin_order_id'] != '0') {
                $json_obj['begin_order_id'] = '0';
                $new_json_str = json_encode($json_obj, JSON_UNESCAPED_UNICODE);

                //修改配置项，并更新redis
                $this->setRedpacketConf($new_json_str, $redis);
            }
            //关闭redis链接
            deinitCacheRedis($redis);

            return false;
        }

        //返回数据数组
        $new_rt_arr = [];

        $now_time = time();
        //查询用户是否满足条件
        $rt_arr = $this->fetchRedpacketCondition($user_id);

        //判断是否有资格参加红包活动
        $user_condition_bool = ($rt_arr['betting_val_bool'] || $rt_arr['recharge_money_val_bool'] || $rt_arr['recharge_times_val_bool']);
//        if ($rt_arr['betting_val_bool'] && $rt_arr['recharge_money_val_bool'] && $rt_arr['recharge_times_val_bool']) {
//            $new_rt_arr['rules_allowed'] = 1;
//        } else {
//            $new_rt_arr['rules_allowed'] = 0;
//        }
//        $user_condition_bool = true;

        // //记录日志
        // lg('hb_check_join_sql', var_export([
        //     'user_id' => $user_id,
        //     'rt_arr' => $rt_arr,
        //     'user_condition_bool' => $user_condition_bool,
        // ], true));

//        if (($rt_arr['user_group_limit_bool'] && $user_condition_bool && $rt_arr['has_redpacket'])
//            || $rt_arr['is_reserved_user']) {
//            $new_rt_arr['can_user_join'] = 1;
//
//        } else {
//            $new_rt_arr['can_user_join'] = 0;
//        }
        if (($rt_arr['user_group_limit_bool'] && $user_condition_bool) || $rt_arr['is_reserved_user']) {
            $new_rt_arr['can_user_join'] = 1;

        } else {
            $new_rt_arr['can_user_join'] = 0;
        }

        //当前活动正在进行中，固定值为1
        $new_rt_arr['redpacket_status'] = 1;

        //查询用户是否参与过活动
        $has_join_info = D('RedpacketGainLog')->checkUserHasJoinRedpacket($user_id, $rt_arr['redpacket_id']);
        $new_rt_arr['is_user_already_join'] = intval($has_join_info['flag']);

        //红包标题和红包图片，注意，此处红包标题改了接口字段为 redpacket_title ，跟表字段 activity_title 略有不同
        $new_rt_arr['redpacket_id'] = $rt_arr['redpacket_id'];
        $new_rt_arr['redpacket_title'] = $rt_arr['activity_title'];
        $new_rt_arr['redpacket_pic'] = $rt_arr['redpacket_pic'];

        //是否还有红包剩余
        $new_rt_arr['has_redpacket'] = intval($rt_arr['has_redpacket']);
//        dump($new_rt_arr);

        return $new_rt_arr;
    }

    /**
     * 设置红包配置项（config表中nid为redpacket_setting的值），并更新redis
     * 2018-03-06
     */
    public function setRedpacketConf($new_json_str, $redis)
    {
        if (! $redis) {
            lg('hb_setRedpacketConf_err', var_export(['不合法的redis' => $redis, '更新值' => $new_json_str], true));
            return false;
        }
        $redis->hSet('Config:redpacket_setting', 'value', $new_json_str);
        $update_sql = "UPDATE un_config SET value = '{$new_json_str}' WHERE nid = 'redpacket_setting' ";
        $this->db->query($update_sql);
        return true;
    }

    /**
     * 查询当时是否有活动正在进行
     * 2018-03-06
     * @param boolean 是/否有活动正在进行
     */
    public function isRedpacketUnderway($redpacket_id = null)
    {
        $now_time = time();

        //查询活动配置
        $field = 'start_time, end_time';

        if (! $redpacket_id) {
            $where = ' is_underway = 1 ';
        } else {
            $where = " id = {$redpacket_id} ";
        }

        $fetch_conf_sql = "SELECT {$field} FROM un_redpacket WHERE {$where} LIMIT 1";
        $redpacket_conf = $this->db->getOne($fetch_conf_sql);
        if (! $redpacket_conf) {
            return false;
        }

        //按时间段，查询当前是否有活动正在进行
        if ($redpacket_conf['start_time'] < $now_time && $redpacket_conf['end_time'] > $now_time) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 查询用户满足条件信息
     * 2017-11-03
     * @param number $user_id 用户id
     */
    public function fetchRedpacketCondition($user_id, $redpacket_id = null){
        //返回数据
        $rt_data = [];

        //查询活动配置
        $field = 'id, activity_title, activity_stage, is_underway, start_time, end_time, admin_id, rules_json, user_group_limit, redpacket_pic, is_show_others_log, redpacket_divided_type, redpacket_max_money, redpacket_all_count, redpacket_reserved_count, redpacket_reserved_ids, already_gain_count, already_gain_count_reserved, already_gain_sum, last_updatetime';
        if (! $redpacket_id) {
            $where = ' is_underway = 1 ';
        } else {
            $where = " id = {$redpacket_id}";
        }
        $fetch_conf_sql = "SELECT {$field} FROM un_redpacket WHERE {$where} LIMIT 1";
        $redpacket_conf = $this->db->getOne($fetch_conf_sql);

        $rules_arr = json_decode($redpacket_conf['rules_json'], true);

        //活动时间段
        $start_time = intval($redpacket_conf['start_time']);
        $end_time = intval($redpacket_conf['end_time']);

        //起始订单表id，从redis里取
        $redis = initCacheRedis();
        $json_data = $redis->hGet('Config:redpacket_setting', 'value');
        $json_obj = json_decode($json_data, true);

        //当前时间
        $now_time = time();

        //取出活动开启时，当时的订单表的主键id
        if ($json_obj['begin_order_id'] == '0') {
            //查询活动开启后，第一笔订单ID
            $begin_order_id = $this->db->result("SELECT id FROM un_orders WHERE addtime >= {$start_time} LIMIT 1");

            //如果没有第一笔订单ID（$begin_order_id），并且有正在进行中的活动，则取订单表最大id值再加一
            if (! $begin_order_id && $this->isRedpacketUnderway() === true) {
                $begin_order_id = $this->db->result("SELECT MAX(id) FROM un_orders");
                $begin_order_id += 1;
            }

            //记录到redis，并更新数据库
            $json_obj['begin_order_id'] = $begin_order_id;
            $new_json_str = json_encode($json_obj, JSON_UNESCAPED_UNICODE);

            //修改配置项，并更新redis
            $this->setRedpacketConf($new_json_str, $redis);

        } else {
            $begin_order_id = $json_obj['begin_order_id'];
        }

        //关闭redis链接
        deinitCacheRedis($redis);

        //原版本的sql
        $fetch_bet_sql_old = "SELECT SUM(`money`) AS bet FROM un_orders WHERE award_state > 0 AND state = 0 AND addtime >= {$start_time} AND addtime <= {$end_time} AND user_id = {$user_id} ";

        //改版后的sql
        //$fetch_bet_sql = "SELECT SUM(`money`) AS bet FROM un_orders WHERE award_state > 0 AND state = 0 AND id >= {$begin_order_id} AND user_id = {$user_id} ";
        $bet_info = $this->db->getOne($fetch_bet_sql_old);

        //活动时间段
        $rt_data['start_time'] = $start_time;
        $rt_data['end_time'] = $end_time;

        //判断用户是否在预留名单内，根据这个状态分别判断是否有机会参与
        $redpacket_reserved_ids_arr = explode(',', $redpacket_conf['redpacket_reserved_ids']);

        //因为in_array方法在没有采用严格模式时，会把数值0视为等同于空字符串，所以此处多加了这层判断
        if (! $user_id) {
            $is_reserved_user = false;
        } else {
            $is_reserved_user = in_array($user_id, $redpacket_reserved_ids_arr);
        }

        $rt_data['is_reserved_user'] = $is_reserved_user;

        //普通用户可用红包是否已经领完标识：【红包总数 - 预留红包个数 - 已领红包个数】
        if ($redpacket_conf['redpacket_all_count'] - $redpacket_conf['redpacket_reserved_count'] - $redpacket_conf['already_gain_count'] > 0) {
            $rt_data['has_redpacket'] = true;
        } else {
            //如果此用户在预留名单内，则该字段值为true
            if ($is_reserved_user) {
                $rt_data['has_redpacket'] = true;
            } else {
                $rt_data['has_redpacket'] = false;
            }
        }

        //有效打码量是否满足
        $rt_data['betting_val_user'] = $bet_info['bet'] ? : 0;
        $rt_data['betting_val_conf'] = $rules_arr['betting_val'];
        $rt_data['betting_val_bool'] = $rt_data['betting_val_user'] >= $rt_data['betting_val_conf'];

        $fetch_recharge_sql = "SELECT SUM(`money`) AS recharge, COUNT(*) AS times FROM `un_account_recharge` WHERE user_id = {$user_id} AND addtime >= {$start_time} AND addtime <= {$end_time} AND status = 1";
        $recharge_info = $this->db->getOne($fetch_recharge_sql);

        //充值额度是否满足
        $rt_data['recharge_money_val_user'] = $recharge_info['recharge'] ? : 0;
        $rt_data['recharge_money_val_conf'] = $rules_arr['recharge_money_val'];
        $rt_data['recharge_money_val_bool'] = $rt_data['recharge_money_val_user'] >= $rt_data['recharge_money_val_conf'];

        //充值次数是否满足
        $rt_data['recharge_times_val_user'] = $recharge_info['times'] ? : 0;
        $rt_data['recharge_times_val_conf'] = $rules_arr['recharge_times_val'];
        $rt_data['recharge_times_val_bool'] = $rt_data['recharge_times_val_user'] >= $rt_data['recharge_times_val_conf'];

        $fetch_user_group_sql = "SELECT group_id FROM un_user WHERE id = {$user_id}";
        $group_data = $this->db->getOne($fetch_user_group_sql);

        //所属会员组是否满足
        $rt_data['user_group_limit_user'] = $group_data['group_id'];
        $rt_data['user_group_limit_conf'] = explode(',', $redpacket_conf['user_group_limit']);
        $rt_data['user_group_limit_bool'] = in_array($rt_data['user_group_limit_user'], $rt_data['user_group_limit_conf']);

        //红包活动id、标题、图片
        $rt_data['redpacket_id'] = $redpacket_conf['id'];
        $rt_data['activity_title'] = $redpacket_conf['activity_title'];
        $rt_data['redpacket_pic'] = $redpacket_conf['redpacket_pic'];

        //记录日志
        $lg_data = [
            'rt_data' => $rt_data,
            '开始活动order_id' => $begin_order_id,
            '结束活动order_id' => $end_order_id,
            '查询配置sql' => $fetch_conf_sql,
            '查询充值sql' => $fetch_recharge_sql,
            '查询会员组数据sql' => $fetch_user_group_sql,
            '原版本的订单查询sql' => $fetch_bet_sql_old,
            '改版后的订单查询sql' => $fetch_bet_sql,
        ];
        lg('hb_zige', var_export($lg_data, true));

        return $rt_data;
    }

    /**
     * 红包领取列表接口
     * 2017-11-04
     */
    public function gainRedpacket($user_id, $redpacket_id, $has_join_info = null)
    {

        $gain_log_model = D('RedpacketGainLog');

        $user_id = intval($user_id);
        $redpacket_id = intval($redpacket_id);

        if ($has_join_info === null) {
            //检查用户是否参与过红包活动
            $has_join_info = $gain_log_model->checkUserHasJoinRedpacket($user_id, $redpacket_id);
        }


        //用户信息
        $user_info = $this->db->getOne("SELECT id,username,reg_type,avatar FROM un_user WHERE id = {$user_id}");

        $rt_info = [];

        //参与过了的，则显示记录，不走抢红包流程。但此处需要带入 redpacket_money,gain_time,is_best_lucky 字段
        if ($has_join_info['flag'] == true) {
            $rt_info['redpacket_money'] = $has_join_info['redpacket_money'];
            $rt_info['gain_time'] = $has_join_info['gain_time'];
            $rt_info['is_best_lucky'] = $has_join_info['is_best_lucky'];
        }
        //没参与过的，则走抢红包流程
        else {
            //走抢红包流程，并通过返回值确定是否有机会得到红包，
            $rt_info = $this->runRedpacketGainLog($user_info, $redpacket_id);
        }

        //活动信息
        $redpacket_info = $this->fetchInfoById($redpacket_id, 'activity_title, is_show_others_log, redpacket_pic');

        //显示全部红包领取者（第一页的数据）
        $page = 1;
        if ($redpacket_info['is_show_others_log'] == '1') {
            $first_page_data_where = " redpacket_id = {$redpacket_id} ";
        } else {
            $first_page_data_where = " user_id = {$user_id} AND redpacket_id = {$redpacket_id} ";
        }
        $page_data = $gain_log_model->gainLogPageData($first_page_data_where, $page);

        //返回的数据
        $rt_data = [
            'redpacket_title' => $redpacket_info['activity_title'],
            'redpacket_id' => $redpacket_id,
            'redpacket_pic' => $redpacket_info['redpacket_pic'],
            'self_pic' => $user_info['avatar'],
            'self_gain_money' => $rt_info['redpacket_money'],
            'self_gain_date' => date('m-d H:i', $rt_info['gain_time']),

            //在api中，手气最佳字段，1为是 0为否
            'is_best_lucky' => $rt_info['is_best_lucky'],
            'all_gain_list' => $page_data,
        ];

        return $rt_data;
    }

    public function redPacketGainList($packetId)
    {
        $sql = "select username, redpacket_money, last_updatetime as time from un_redpacket_gain_log where redpacket_id = {$packetId} order by id desc limit 5";
        $list = $this->db->getAll($sql);
        return $list;
    }

    /**
     * 抢红包逻辑
     * 2017-11-08
     */
    public function divideRedpacket($user_id, $redpacket_id)
    {
        $now_time = time();
        
        $redpacket_info = $this->fetchInfoById($redpacket_id);

        $redpacket_reserved_ids = $redpacket_info['redpacket_reserved_ids'];
        $redpacket_reserved_ids_arr = explode(',', $redpacket_reserved_ids);
        
        //如果不在预留名单内，则需要去判断剩余红包个数
        $is_reserved_user = in_array($user_id, $redpacket_reserved_ids_arr);
        if (! $is_reserved_user) {
            //普通用户可用红包个数 = 总红包个数 - 预留红包个数 - 已领红包个数
            $usable_redpacket_count = $redpacket_info['redpacket_all_count'] - $redpacket_info['redpacket_reserved_count'] - $redpacket_info['already_gain_count'];

            if ($usable_redpacket_count <= 0) {
                return [
                    'flag' => false,
                    'msg' => 'Red envelope has been grabbed',
                    'redpacket_money' => 0,
                    'gain_time' => $now_time,
                    'is_reserved_user' => $is_reserved_user,
                ];
            }
        }


        //1.固定值 2.随即值
        if ($redpacket_info['redpacket_divided_type'] == '1') {
            $rand_redpacket_money = $redpacket_info['redpacket_max_money'];
        } else {
            //随机的红包值，最高最低值都乘以100，避免出现浮点数精度失真问题
            $rand_redpacket_money = mt_rand(0.1 * 100, $redpacket_info['redpacket_max_money'] * 100);
            $rand_redpacket_money = number_format($rand_redpacket_money / 100, 2, '.', '');
        }


        return [
            'flag' => true,
            'msg' => '',
            'redpacket_money' => $rand_redpacket_money,
            'gain_time' => $now_time,
            'is_reserved_user' => $is_reserved_user,
        ];
    }

    /**
     * 根据红包活动id查询红包配置
     * 2017-11-08
     */
    public function fetchInfoById($redpacket_id, $field = '')
    {
        if ($field == '') {
            $fetch_field = 'id, activity_title, activity_stage, is_underway, start_time, end_time, admin_id, rules_json, user_group_limit, redpacket_pic, is_show_others_log, redpacket_divided_type, redpacket_max_money, redpacket_all_count, redpacket_reserved_count, redpacket_reserved_ids, already_gain_count, already_gain_count_reserved, already_gain_sum, last_updatetime';
        } else {
            $fetch_field = $field;
        }

        $redpacket_id = intval($redpacket_id);

        $sql = "SELECT {$fetch_field} FROM un_redpacket WHERE id = {$redpacket_id}";

        return $this->db->getOne($sql);
    }

    /**
     * 根据红包期数查询红包配置
     * 2017-11-29 update
     */
    public function fetchInfoByActivityStage($activity_stage, $field = '')
    {
        $fetch_redpacket_sql = "SELECT id FROM un_redpacket WHERE activity_stage = {$activity_stage} LIMIT 1";
        $redpacket_id = intval($this->db->result($fetch_redpacket_sql));
        return $this->fetchInfoById($redpacket_id, $field);
    }

    /**
     * 查询指定会员在指定红包活动中的活动记录（包括充值、投注）[查询分页数据]
     * 2017-11-29 update
     */
    public function fetchUserPlayOrderSnPageData($user_id, $redpacket_info, $page_start = 0, $page_size = 10)
    {
        $redpacket_id = $redpacket_info['id'];

        //活动时间段
        $start_time = intval($redpacket_info['start_time']);
        $end_time = intval($redpacket_info['end_time']);

        //合并流水号字段
        $fetch_bet_and_recharge_sql = "
            (
                SELECT order_sn AS water_sn,`addtime` AS tm FROM `un_account_recharge`
                    WHERE user_id = {$user_id}
                    AND ADDTIME >= {$start_time}
                    AND ADDTIME <= {$end_time}
                    AND STATUS = 1
            )
                UNION ALL
            (
                SELECT order_no AS water_sn,`addtime` AS tm FROM un_orders
                    WHERE award_state > 0
                    AND state = 0
                    AND ADDTIME >= {$start_time}
                    AND ADDTIME <= {$end_time}
                    AND user_id = {$user_id} 
            )
            ORDER BY tm DESC
            LIMIT {$page_start}, {$page_size}";
        $final_water_sn_arr = $this->db->getAll($fetch_bet_and_recharge_sql);

        return $final_water_sn_arr;
    }

    /**
     * 查询指定会员在指定红包活动中的活动记录（包括充值、投注）[查询结果条数]
     * 2017-11-29 update
     */
    public function fetchUserPlayOrderSnCount(array $condition_arr)
    {
        $user_id = $condition_arr['user_id'];
        $start_time = $condition_arr['start_time'];
        $end_time = $condition_arr['end_time'];

        //统计条数
        $fetch_bet_and_recharge_count_sql = "
            SELECT COUNT(*) FROM (
            (
                SELECT id FROM `un_account_recharge`
                    WHERE user_id = {$user_id}
                    AND ADDTIME >= {$start_time}
                    AND ADDTIME <= {$end_time}
                    AND STATUS = 1
            )
                UNION ALL
            (
                SELECT id FROM un_orders
                    WHERE award_state > 0
                    AND state = 0
                    AND ADDTIME >= {$start_time}
                    AND ADDTIME <= {$end_time}
                    AND user_id = {$user_id} 
            )) AS main_table ";
        $count_num = $this->db->result($fetch_bet_and_recharge_count_sql);
        return $count_num;
    }

    /**
     * 查询流水记录详情
     * 2017-11-29 update
     */
    public function fetchUserWaterSnDetail($where, $arg_field = null)
    {
        if ($arg_field == null) {
            $fetch_field = 'id,money,remark,addtime,order_num';
        } else {
            $fetch_field = $arg_field;
        }
        $fetch_user_water_sn_sql = "SELECT {$fetch_field} FROM un_account_log {$where}";
        return $this->db->getAll($fetch_user_water_sn_sql);
    }

    /**
     * 红包规则接口数据源
     * 2017-11-04
     */
    public function redpacketRules($user_id, $redpacket_id)
    {
        /*
        $rt_data = [
            'redpacket_duration' => '2017-09-02 10:00到2017-12-03 10:00',
            'redpacket_group_limit_arr' => ['新会员', 'VIP-1', 'VIP-2', 'VIP-3'],
            'redpacket_rules_arr' => [
                '有效打码量大于等于' . '1000',
                '充值次数大于等于' . '3',
                '充值额度大于等于' . '100',
            ],
            'self_group' => 'VIP-2',
            'self_is_group_reach' => 1,
            'self_reach_arr' => [
                [
                    'rules_txt' => '有效打码量 ' . '1000',
                    'is_reach' => 0,
                ],
                [
                    'rules_txt' => '充值次数 ' . '8' . '次',
                    'is_reach' => 1,
                ],
                [
                    'rules_txt' => '充值额度 ' . '100',
                    'is_reach' => 0,
                ],
            ],
        ];
        */
        
        //查询红包活动信息
        $fetch_redpacket_sql = "SELECT start_time,end_time,user_group_limit,rules_json FROM un_redpacket WHERE id = {$redpacket_id}";
        $redpacket_info = $this->db->getOne($fetch_redpacket_sql);

        $start_date = date('Y-m-d H:i', $redpacket_info['start_time']);
        $end_date = date('Y-m-d H:i', $redpacket_info['end_time']);

        //查询会员组信息（取redis中的数据）
        $group_ids_arr = explode(',', $redpacket_info['user_group_limit']);
        $redis = initCacheRedis();
        $group_txt_arr = [];
        foreach ($group_ids_arr as $k => $each_id) {
            $group_name = $redis->hGet("group:{$each_id}", 'name');
            //过滤掉已经删除了的会员组
            if (! $group_name) {
                continue;
            }
            $group_txt_arr[] = $group_name;
        }

        //红包活动规则
        $rules_json_arr = json_decode($redpacket_info['rules_json'], true);

        //用户所属组数据
        $fetch_user_sql = "SELECT group_id FROM un_user WHERE id = {$user_id}";
        $user_group_id = $this->db->result($fetch_user_sql);
        $self_group = $redis->hGet("group:{$user_group_id}", 'name');

        //所属组是否达到条件
        $self_is_group_reach = in_array($user_group_id, $group_ids_arr);

        //关闭redis链接
        deinitCacheRedis($redis);

        //查询用户是否满足条件
        $rt_arr = $this->fetchRedpacketCondition($user_id, $redpacket_id);

        $rt_data = [
            'redpacket_duration' => "{$start_date}到{$end_date}",
            'redpacket_group_limit_arr' => $group_txt_arr,
            'redpacket_rules_arr' => [
                '有效打码量大于等于' . $rules_json_arr['betting_val'],
                '充值次数大于等于' . $rules_json_arr['recharge_times_val'],
                '充值额度大于等于' . $rules_json_arr['recharge_money_val'],
            ],
            'self_group' => $self_group,
            'self_is_group_reach' => intval($self_is_group_reach),
            'self_reach_arr' => [
                [
                    'rules_txt' => '有效打码量 ' . $rt_arr['betting_val_user'],
                    'is_reach' => $rt_arr['betting_val_bool'],
                ],
                [
                    'rules_txt' => '充值次数 ' . $rt_arr['recharge_times_val_user'] . '次',
                    'is_reach' => $rt_arr['recharge_times_val_bool'],
                ],
                [
                    'rules_txt' => '充值额度 ' . $rt_arr['recharge_money_val_user'],
                    'is_reach' => $rt_arr['recharge_money_val_bool'],
                ],
            ],
        ];

        return $rt_data;
    }

    /**
     * 抢红包后的相关操作，新增红包记录后，并同步操作用户账户、资金交易明细等表
     * @param array $user_info 用户相关信息关联数组，需要具备id,username,reg_type,avatar等字段
     * @param number $redpacket_id 红包活动id
     * @return array 抽红包相关数据
     * 2017-11-08
     */
    public function runRedpacketGainLog($user_info, $redpacket_id)
    {
        $user_id = $user_info['id'];

        //分到的红包，调用抢红包方法
        $user_gain = $this->divideRedpacket($user_id, $redpacket_id);

        //如果红包已经抢完，则退出以下操作
        if (! $user_gain['flag']) {
            $lg_data = [
                'user_id' => $user_id,
                'redpacket_id' => $redpacket_id,
                'user_gain' => $user_gain,
            ];
            lg('hb_divide', var_export($lg_data, true));

            return $user_gain;
        }

        $now_time = time();

        //获取活动信息，并查询活动红包是否已经派完，此处计算需包含已领取的预留红包个数
        $redpacket_info = $this->fetchInfoById($redpacket_id, /*$field = */ 'activity_title,activity_stage, redpacket_all_count,redpacket_reserved_count,already_gain_count,already_gain_count_reserved');

        $remaining_redpacket_count = $redpacket_info['redpacket_all_count'] - $redpacket_info['already_gain_count'] - $redpacket_info['already_gain_count_reserved'];

        //查询截止当前的最佳手气红包值
        $max_redpacket_money_info = D('RedpacketGainLog')->fetchMaxRedpacketMoneyInfo($redpacket_id);

        //事物开启
        $this->db->query('BEGIN');

        //如果是最后一个红包，则需要将本轮红包活动的手气最佳者标注上（设置 is_best_lucky 字段为1）
        if ($remaining_redpacket_count == 1) {
            //如果该用户本次获得的红包金额大于当前最大红包值，则标注该用户自己为手气最佳者
            if ($user_gain['redpacket_money'] >= $max_redpacket_money_info['redpacket_money']) {
                $is_best_lucky = 1;
                $res_0 = true;
            }
            //反之，则标注查询出来的最大红包拥有者为手气最佳者
            else {
                $update_best_lucky_sql = "UPDATE un_redpacket_gain_log SET is_best_lucky = 1, last_updatetime = {$now_time} WHERE id = {$max_redpacket_money_info['id']}";
                $res_0 = $this->db->query($update_best_lucky_sql);
                $is_best_lucky = 2;
            }
        
        } else {
            $res_0 = true;
            $is_best_lucky = 2;
        }

        //将是否手气最佳标志写入返回值数组中
        $user_gain['is_best_lucky'] = $is_best_lucky;

        //写入红包记录表
        $remark_str = "用户{$user_info['username']}在" . date('Y-m-d H:i:s') . "抽中红包{$user_gain['redpacket_money']}元";
        $order_num = 'HB' . date('YmdHis') . rand(100, 999);
        $insert_gain_log_data = [
            'user_id' => $user_id,
            'username' => $user_info['username'],
            'redpacket_id' => $redpacket_id,
            'activity_stage' => $redpacket_info['activity_stage'],
            'redpacket_money' => $user_gain['redpacket_money'],
            'gain_time' => $now_time,
            'order_num' => $order_num,
            'remark' => $remark_str,
            'last_updatetime' => $now_time,
            //前台领取红包为1
            'gain_type' => 1,
            'is_best_lucky' => $is_best_lucky,
            'admin_id' => $this->admin['userid'],
            'admin_username' => $this->admin['username'],
        ];
        $res_1 = $this->db->insert('un_redpacket_gain_log', $insert_gain_log_data);


        //如果不是预留用户，则红包已领取的数量要加上1
        if (! $user_gain['is_reserved_user']) {
            $update_sql = "UPDATE un_redpacket SET already_gain_count = already_gain_count + 1, already_gain_sum = already_gain_sum + {$user_gain['redpacket_money']}, last_updatetime = {$now_time} WHERE id = {$redpacket_id} ";
        } else {
            $update_sql = "UPDATE un_redpacket SET already_gain_count_reserved = already_gain_count_reserved + 1, already_gain_sum = already_gain_sum + {$user_gain['redpacket_money']}, last_updatetime = {$now_time} WHERE id = {$redpacket_id} ";
        }
        $res_2 = $this->db->query($update_sql);


        //当前余额
//        $sql = "SELECT `money` FROM `un_account` WHERE user_id = {$user_id} FOR UPDATE";
        //查余额
        if(!empty(C('db_port'))){ //使用mycat时 查主库数据
            $sql="/*#mycat:db_type=master*/ SELECT `money` FROM `un_account` WHERE user_id = {$user_id} FOR UPDATE";
        }else{
            $sql="SELECT `money` FROM `un_account` WHERE user_id = {$user_id} FOR UPDATE";
        }
        $use_money = $this->db->result($sql);

        //为红包添加一个活动类别
        $insert_money_data = [
            'user_id' => $user_id,
            'order_num' => $order_num,
            //红包的类别为997
            'type' => 997,
            'money' => $user_gain['redpacket_money'],
            'use_money' => bcadd($use_money, $user_gain['redpacket_money'], 2),
            'remark' => "红包活动[{$redpacket_info['activity_title']}]中，用户{$user_info['username']}在" . date('Y-m-d H:i:s') . "抽到红包：{$user_gain['redpacket_money']}元",
            'verify' => 0,
            'addtime' => $now_time,
            'addip' => ip(),
            'admin_money' => 0,
            'reg_type' => $user_info['reg_type'],
        ];
        $res_3 = $this->db->insert('un_account_log', $insert_money_data);

        //添加彩金到用户账户
        $update_account_sql = "UPDATE un_account SET `money` = `money` + {$user_gain['redpacket_money']}
            WHERE user_id = {$user_id} ";
        $res_4 = $this->db->query($update_account_sql);

        //全部sql执行无误，才提交执行，否则回滚
        if ($res_0 && $res_1 && $res_2 && $res_3 && $res_4) {
            $this->db->query('COMMIT');
        } else {
            $this->db->query('ROLLBACK');
        }

        $lg_data = [
            'res_info_0' => ['rt' => $res_0, 'sql_or_data' => $update_best_lucky_sql],
            'res_info_1' => ['rt' => $res_1, 'sql_or_data' => $insert_gain_log_data],
            'res_info_2' => ['rt' => $res_2, 'sql_or_data' => $update_sql],
            'res_info_3' => ['rt' => $res_3, 'sql_or_data' => $insert_money_data],
            'res_info_4' => ['rt' => $res_4, 'sql_or_data' => $update_account_sql],
            'use_money' => $use_money,
            'max_redpacket_money_info' => $max_redpacket_money_info,
            'remaining_redpacket_count' => $remaining_redpacket_count,
            'redpacket_info' => $redpacket_info,
            'user_gain' => $user_gain,
            'user_info' => $user_info,
        ];
        lg('hb_divide', var_export($lg_data, true));

        return $user_gain;
    }

}
