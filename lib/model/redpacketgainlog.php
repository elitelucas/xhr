<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

/**
 * 红包领取记录模型类
 * 2017-11-09
 */
class RedpacketGainLogModel extends CommonModel
{
    // protected $gain_log_table = 'un_redpacket_gain_log';

    /**
     * 查询用户是否参与过红包活动
     * 2017-11-03 create
     * 2017-11-09 move here
     */
    public function checkUserHasJoinRedpacket($user_id, $redpacket_id)
    {
        $redpacket_id = intval($redpacket_id);
        $sql = "SELECT id,redpacket_money,gain_time,is_best_lucky FROM un_redpacket_gain_log WHERE user_id = {$user_id} AND redpacket_id = {$redpacket_id}";
        $gain_data = $this->db->getOne($sql);

        //如已经参加过了，则在返回数据中附上当时所得红包金额
        if ($gain_data['id']) {
            return [
                'flag' => true,
                'redpacket_money' => $gain_data['redpacket_money'],
                'gain_time' => $gain_data['gain_time'],
                'is_best_lucky' => $gain_data['is_best_lucky'],
            ];
        }

        return [
            'flag' => false,
            'redpacket_money' => 0,
        ];
    }

    /**
     * 红包领取记录分页方法
     * 2017-11-09
     */
    public function gainLogPageData($where, $page, $page_size = 10)
    {
        /*

            'all_gain_list' => [
                [
                    'username' => 'leeo',
                    'user_pic' => '/up_files/avatar/1035959af9619845.jpg',
                    'user_gain_date' => '08-21 12:05',
                    'user_gain_money' => 8.11,
                ],
                [
                    'username' => 'java',
                    'user_pic' => '/up_files/avatar/106594e1323cac38.jpg',
                    'user_gain_date' => '05-05 10:15',
                    'user_gain_money' => 6.45,
                ],
                [
                    'username' => 'python',
                    'user_pic' => '/up_files/avatar/106594e1323cac38.jpg',
                    'user_gain_date' => '03-15 15:00',
                    'user_gain_money' => 7.02,
                ],
                [
                    'username' => 'linux',
                    'user_pic' => '/up_files/avatar/106594e1323cac38.jpg',
                    'user_gain_date' => '12-20 18:06',
                    'user_gain_money' => 3.13,
                ],
            ],

        */

        //调整页码
        $page_code = ($page - 1) * $page_size;

        //取该活动中，各个用户的红包领取情况
        $fetch_gain_log_sql = "SELECT id,user_id,username,gain_time,redpacket_money
            FROM un_redpacket_gain_log
            WHERE {$where}
            ORDER BY gain_time DESC
            LIMIT {$page_code}, {$page_size} ";
        $gain_info = $this->db->getAll($fetch_gain_log_sql);

        if (! $gain_info) {
            $lg_data = [
                'fetch_gain_log_sql' => $fetch_gain_log_sql,
            ];
            lg('hb_no_gain_data', var_export($lg_data, true));
            return [];
        }

        //取用户头像
        $user_ids_arr = array_column($gain_info, 'user_id');
        $ids_str = implode(',', $user_ids_arr);
        $fetch_user_pic_sql = "SELECT id,avatar FROM un_user WHERE id IN ({$ids_str})";
        $user_pic_info = $this->db->getAll($fetch_user_pic_sql);
        $user_pic_hash_obj = array_column($user_pic_info, 'avatar', 'id');

        //拼接接口数据
        $new_rt_arr = [];
        foreach ($gain_info as $k => $each_gain_log) {
            $new_rt_arr[] = [
                'username' => preg_replace('/(^.).+?(.$)/u', '$1***$2', $each_gain_log['username']),
                'user_pic' => $user_pic_hash_obj[$each_gain_log['user_id']],
                'user_gain_date' => date('m-d H:i', $each_gain_log['gain_time']),
                'user_gain_money' => $each_gain_log['redpacket_money'],
            ];
        }

        return $new_rt_arr;
    }


    /**
     * 当前红包活动记录分页数据
     * 2017-11-06 create
     * 2017-11-09 move here
     */
    public function currentRedpacketList($user_id, $redpacket_id, $page)
    {
        /*
            'gain_list' => [
                [
                    'username' => 'C_' . mt_rand(10, 99),
                    'user_pic' => '/up_files/avatar/1035959af9619845.jpg',
                    'user_gain_date' => '08-21 12:05',
                    'user_gain_money' => mt_rand(10, 99) . '.11',
                ],
                [
                    'username' => 'java_' . mt_rand(10, 99),
                    'user_pic' => '/up_files/avatar/106594e1323cac38.jpg',
                    'user_gain_date' => '05-05 10:15',
                    'user_gain_money' => mt_rand(10, 99) . '.45',
                ],
                [
                    'username' => 'python_' . mt_rand(10, 99),
                    'user_pic' => '/up_files/avatar/106594e1323cac38.jpg',
                    'user_gain_date' => '03-15 15:00',
                    'user_gain_money' => mt_rand(10, 99) . '.02',
                ],
                [
                    'username' => 'linux_' . mt_rand(10, 99),
                    'user_pic' => '/up_files/avatar/106594e1323cac38.jpg',
                    'user_gain_date' => '12-20 18:06',
                    'user_gain_money' => mt_rand(10, 99) . '.13',
                ],
                [
                    'username' => 'shell_' . mt_rand(10, 99),
                    'user_pic' => '/up_files/avatar/1035959af9619845.jpg',
                    'user_gain_date' => '08-21 12:05',
                    'user_gain_money' => mt_rand(10, 99) . '.11',
                ],
                [
                    'username' => 'js_' . mt_rand(10, 99),
                    'user_pic' => '/up_files/avatar/106594e1323cac38.jpg',
                    'user_gain_date' => '05-05 10:15',
                    'user_gain_money' => mt_rand(10, 99) . '.45',
                ],
                [
                    'username' => 'lua_' . mt_rand(10, 99),
                    'user_pic' => '/up_files/avatar/106594e1323cac38.jpg',
                    'user_gain_date' => '03-15 15:00',
                    'user_gain_money' => mt_rand(10, 99) . '.02',
                ],
                [
                    'username' => 'objcet-c_' . mt_rand(10, 99),
                    'user_pic' => '/up_files/avatar/106594e1323cac38.jpg',
                    'user_gain_date' => '12-20 18:06',
                    'user_gain_money' => mt_rand(10, 99) . '.13',
                ],
                [
                    'username' => 'c++_' . mt_rand(10, 99),
                    'user_pic' => '/up_files/avatar/106594e1323cac38.jpg',
                    'user_gain_date' => '03-15 15:00',
                    'user_gain_money' => mt_rand(10, 99) . '.02',
                ],
                [
                    'username' => 'perl_' . mt_rand(10, 99),
                    'user_pic' => '/up_files/avatar/106594e1323cac38.jpg',
                    'user_gain_date' => '12-20 18:06',
                    'user_gain_money' => mt_rand(10, 99) . '.13',
                ],
            ],
        */

        //查看活动配置，是否需要显示其他人的红包记录
        $redpacket_info = D('Redpacket')->fetchInfoById($redpacket_id, /*$field = */ 'is_show_others_log');
        if ($redpacket_info['is_show_others_log'] == '1') {
            $where = " redpacket_id = {$redpacket_id} ";
        } else {
            $where = " user_id = {$user_id} AND redpacket_id = {$redpacket_id} ";
        }

        //获取分页数据（移动端分页）
        $page_data = $this->gainLogPageData($where, $page);

        $rt_data = [
            'gain_list' => $page_data,
        ];

        return $rt_data;
    }


    /**
     * 红包历史统计记录
     * 2017-11-04 create
     * 2017-11-10 move here
     */
    public function redpacketCount($user_id, $year)
    {

        //年份转时间戳
        $year_begin_timestamp = strtotime("{$year}-01-01 00:00:00");
        $year_end_timestamp = strtotime("{$year}-12-31 23:59:59");

        //查询用户红包历史统计数据
        $fetch_gain_count_sql = "SELECT 
            SUM(`redpacket_money`) AS all_gain_money,
            COUNT(*) AS gain_redpacket_num,
            SUM(IF(is_best_lucky = 1, 1, 0)) AS best_lucky_times
            FROM un_redpacket_gain_log
            WHERE user_id = {$user_id}
            AND gain_time >= {$year_begin_timestamp}
            AND gain_time <= {$year_end_timestamp} ";
        $user_join_info = $this->db->getOne($fetch_gain_count_sql);

        //用户头像
        $user_avatar = $this->fetchUserAvatar($user_id);

        //历史领取红包数据（只取第1页数据）
        $page = 1;
        $history_list_info = $this->selfRedpacketHistory($user_id, $year, $page);

        $rt_data = [
            'all_gain_money' => $user_join_info['all_gain_money'] ? : 0,
            'gain_redpacket_num' => $user_join_info['gain_redpacket_num'] ? : 0,
            'best_lucky_times' => $user_join_info['best_lucky_times'] ? : 0,
            'self_pic' => $user_avatar,
            'history_list' => $history_list_info,
        ];

        return $rt_data;
    }

    /**
     * 获取用户头像
     * 2017-11-10
     */
    public function fetchUserAvatar($user_id)
    {
        $fetch_avatar_sql = "SELECT avatar FROM un_user WHERE id = {$user_id}";
        return $this->db->result($fetch_avatar_sql);
    }

    /**
     * 个人红包历史记录分页数据
     * 2017-11-06 create
     * 2017-11-10 move here
     * 查询方案：使用非联表查询数据的方式展示
     */
    public function selfRedpacketHistory($user_id, $year, $page, $page_size = 10)
    {
        //调整页码
        $page_code = ($page - 1) * $page_size;

        //年份转时间戳
        $year_begin_timestamp = strtotime("{$year}-01-01 00:00:00");
        $year_end_timestamp = strtotime("{$year}-12-31 23:59:59");

        //查询用户历史抽红包记录
        $fetch_gain_log_sql = "SELECT id,user_id,username,gain_time,redpacket_money,redpacket_id
            FROM un_redpacket_gain_log
            WHERE user_id = {$user_id}
            AND gain_time >= {$year_begin_timestamp}
            AND gain_time <= {$year_end_timestamp}
            ORDER BY gain_time DESC
            LIMIT {$page_code}, {$page_size}";
        $gain_log_info = $this->db->getAll($fetch_gain_log_sql);

        //当没有数据时，则返回空数组
        if (! $gain_log_info) {
            return [];
        }
        //获取活动id数组，用户拼接where-in语句
        $redpacket_ids_arr = array_column($gain_log_info, 'redpacket_id');
        $redpacket_ids_str = implode(',', $redpacket_ids_arr) ? : 0;

        //查询红包活动列表
        $fetch_redpacket_sql = "SELECT id,activity_title,redpacket_pic
            FROM un_redpacket WHERE id IN ({$redpacket_ids_str})";
        $redpacket_info = $this->db->getAll($fetch_redpacket_sql);

        $new_redpacket_info = [];
        foreach ($redpacket_info as $r_key => $r_val) {
            $new_redpacket_info[$r_val['id']] = [
                'redpacket_title' => $r_val['activity_title'],
                'redpacket_pic' => $r_val['redpacket_pic'],
            ];
        }

        //将红包活动信息数组，和用户红包记录数组，添加到新数组中
        $rt_history_list = [];
        foreach ($gain_log_info as $g_val) {
            $rt_history_list[] = [
                'redpacket_id' => $g_val['redpacket_id'],
                'redpacket_title' => $new_redpacket_info[$g_val['redpacket_id']]['redpacket_title'],
                'redpacket_pic' => $new_redpacket_info[$g_val['redpacket_id']]['redpacket_pic'],
                'gain_date' => date('m-d', $g_val['gain_time']),
                'user_gain_money' => $g_val['redpacket_money'],
            ];
        }

        return $rt_history_list;
    }

    /**
     * 计算查询当前的红包最佳手气
     * 2017-11-15
     */
    public function fetchMaxRedpacketMoneyInfo($redpacket_id)
    {
        $redpacket_id = intval($redpacket_id);
        $sql = "SELECT redpacket_money, user_id, id FROM un_redpacket_gain_log
            WHERE redpacket_id = {$redpacket_id} ORDER BY redpacket_money DESC LIMIT 1";
        $max_redpacket_money_info = $this->db->getOne($sql);
        return $max_redpacket_money_info;
    }
}
