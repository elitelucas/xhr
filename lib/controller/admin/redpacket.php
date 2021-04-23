<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'page.php';

/**
 * 后台红包活动控制器
 * 2017-11-02
 */
class RedpacketAction extends Action
{

    /**
     * 数据表
     */
    // private $redpacket_model;

    public function __construct()
    {
        parent::__construct();
        // $this->redpacket_model = D('redpacket');
    }



    /**
     * 活动管理
     * 2017-11-02
     */
    public function redpacketList()
    {

        //分页带上的条件
        $params = $_REQUEST;
        unset($params['m']);
        unset($params['c']);
        unset($params['a']);

        $sql = 'SELECT COUNT(*) AS rows_cnt FROM un_redpacket AS r
                LEFT JOIN un_admin AS admin ON admin.userid = r.admin_id';

        $countInfo = $this->db->getOne($sql);
        $listCnt = $countInfo['rows_cnt'];
        $pagesize = 20;
        $url = '?m=admin&c=redpacket&a=redpacketList';
        $page = new page($listCnt, $pagesize, $url, $params);
        $show = $page->show();

        $page_start = $page->offer;
        $page_size = $pagesize;

        $limit = "limit {$page_start},{$page_size}";

        //查询字段
        $field = 'r.id, r.activity_title, r.activity_stage, r.is_underway, r.start_time, r.end_time, r.admin_id, r.rules_json, r.user_group_limit, r.redpacket_pic, r.is_show_others_log, r.redpacket_divided_type, r.redpacket_max_money, r.redpacket_all_count, r.redpacket_reserved_count, r.redpacket_reserved_ids, r.already_gain_count, r.already_gain_count_reserved, r.already_gain_sum, r.last_updatetime,admin.username AS admin_user';

        //查询活动列表
        $sql2 = "SELECT {$field} FROM un_redpacket AS r
            LEFT JOIN un_admin AS admin ON admin.userid = r.admin_id
            ORDER BY activity_stage DESC {$limit}";
        $activity_list = $this->db->getAll($sql2);

        include template('redpacket-redpacketList');
    }

    /**
     * 红包活动详情编辑
     * 2017-11-02
     */
    public function redpacketEdit()
    {
        //活动id和编辑方式
        $id = intval($_GET['id']);
        $save_type = $_GET['save_type'];

        $now_time = time();

        //取用户组配置数据
        $group_info_arr = $this->db->getAll('SELECT id,name FROM un_user_group WHERE state = 0');

        //新增逻辑
        if ($save_type == 'add') {

            //活动详情--[新增时的默认数据]
            $activity_info = [
                'is_show_others_log' => 1,
                'is_underway' => 0,
                'redpacket_pic' => '',
                'activity_title' => '限时红包',
                'redpacket_all_count' => 100,
                'redpacket_reserved_count' => 10,
                'redpacket_divided_type' => 1,
                'redpacket_max_money' => 88,
                'redpacket_pic' => '/up_files/redpacket/default/default_redpacket.png',
            ];

            //活动规则数据--[新增时的默认数据]
            $activity_rules_data =  [
                'betting_val' => 1000,
                'recharge_times_val' => 3,
                'recharge_money_val' => 500,
            ];


            //活动开始时间--[新增时的默认数据]
            $s_date = date('Y-m-d H:i:00', $now_time);
            $e_date = date('Y-m-d H:i:00', $now_time + 7 * 86400);

            //用户组限制--[新增时的默认数据]
            $user_group_limit_arr = array_column($group_info_arr, 'id');

        } else {

            $field = 'id, activity_title, activity_stage, is_underway, start_time, end_time, admin_id, rules_json, user_group_limit, redpacket_pic, is_show_others_log, redpacket_divided_type, redpacket_max_money, redpacket_all_count, redpacket_reserved_count, redpacket_reserved_ids, already_gain_count, already_gain_sum, last_updatetime';
            $activity_info = $this->db->getOne("SELECT {$field} FROM un_redpacket WHERE id = {$id}");

            //活动规则数据
            $activity_rules_data = (array)json_decode($activity_info['rules_json'], true);

            //活动开始时间
            $s_date = date('Y-m-d H:i:s', $activity_info['start_time']);
            $e_date = date('Y-m-d H:i:s', $activity_info['end_time']);

            //层级限制
            $user_group_limit_arr = explode(',', $activity_info['user_group_limit']);
        }

        include template('redpacket-redpacketEdit');
    }


    /**
     * 保存红包活动详情
     * 2017-11-03
     */
    public function redpacketSave()
    {
        $post_data = $_POST;

        $save_data = [];
        $save_data['id'] = intval($post_data['id']);

        $lg_data = [];

        //判断是新增还是编辑
        if (! $save_data['id']) {
            $save_type = 'add';
            $save_data['id'] = null;
            $save_data['admin_id'] = $this->admin['userid'];
        } else {
            $save_type = 'update';
        }
        $save_data['redpacket_pic'] = trim($post_data['redpacket_pic']);
        $save_data['start_time'] = strtotime($post_data['start_time']);
        $save_data['end_time'] = strtotime($post_data['end_time']);
        $save_data['activity_title'] = $post_data['activity_title'];
        $save_data['is_underway'] = intval($post_data['is_underway']) ? : 0;

        $redis = initCacheRedis();

        //判断是否有两个活动同时在进行，并记录当前的订单表主键id到redis
        if ($save_data['is_underway'] == '1') {
            $check_data = $this->db->getOne('SELECT id,is_underway FROM un_redpacket WHERE is_underway = 1');
            if ($check_data && $check_data['id'] != $save_data['id']) {
                deinitCacheRedis($redis);
                echo json_encode(['rt' => 0, 'msg' => '不能同时开启两个活动！']);
                exit;
            }

            // //起始订单表id，从redis里取
            // $json_data = $redis->hGet('Config:redpacket_setting', 'value');
            // $json_obj = json_decode($json_data, true);

            // $begin_order_id = $this->db->result("SELECT id FROM un_orders WHERE addtime >= {$save_data['start_time']} LIMIT 1");
            // if (! $begin_order_id) {
            //     $lg_data['order_id是否存在'] = 'no';
            //     $begin_order_id = $this->db->result("SELECT MAX(id) FROM un_orders");
            // } else {
            //     $lg_data['order_id是否存在'] = 'yes';
            // }
            // $lg_data['活动起始的order_id'] = $begin_order_id;

            // //记录到redis，并更新数据库
            // $json_obj['begin_order_id'] = $begin_order_id;
            // $new_json_str = json_encode($json_obj, JSON_UNESCAPED_UNICODE);
            // $redis->hSet('Config:redpacket_setting', 'value', $new_json_str);
            // $update_sql = "UPDATE un_config SET value = '{$new_json_str}' WHERE nid = 'redpacket_setting' ";
            // $this->db->query($update_sql);
        }

        $save_data['user_group_limit'] = $post_data['user_group_limit'];

        //规则设置
        $save_data['rules_json'] = json_encode($post_data['rules_json'], JSON_UNESCAPED_UNICODE);

        //更新时间
        $save_data['last_updatetime'] = time();

        //是否在前台显示红包记录
        $save_data['is_show_others_log'] = intval($post_data['is_show_others_log']);

        //红包分配方式
        $save_data['redpacket_divided_type'] = intval($post_data['redpacket_divided_type']);

        //固定金额值或者最高金额值
        $save_data['redpacket_max_money'] = floatval($post_data['redpacket_max_money']);

        //红包总个数
        $save_data['redpacket_all_count'] = intval($post_data['redpacket_all_count']);

        //红包预留个数
        $save_data['redpacket_reserved_count'] = intval($post_data['redpacket_reserved_count']);

        $arr = [0 => '未开始', 1 => '开启', 2 => '停止'];
        if ($save_type == 'add') {
            $max_id = $this->fetchInsertActivityStage();
            $save_data['activity_stage'] = $max_id;
            $flag = $this->db->insert('un_redpacket', $save_data);
        } else {
            $where = [
                'id' => $save_data['id'],
            ];
            $flag = $this->db->update('un_redpacket', $save_data, $where);
            $actInfo = $this->db->getone("select * from un_turntable where id = ".$save_data['id']);
            if($actInfo['is_underway'] != 1 && $save_data['is_underway'] == 1) {
                $log_remark = "开启红包活动--活动名称:".$save_data['activity_title'].'--期数:'.$actInfo['activity_stage'];
                admin_operation_log($this->admin['userid'], 120, $log_remark);
            }
        }

        if ($save_data['is_underway'] == 1){
            $this->updateActivityFlag($save_data['start_time'], $save_data['end_time']);
        }
        
        $lg_data['红包配置数据save_data'] = $save_data;
        lg('hb_save_redpacket', var_export($lg_data, true));
        deinitCacheRedis($redis);
        echo json_encode(['rt' => $flag]);
        exit;
    }


    /**
     * 获取新增活动期数，最大活动期数+1
     * 2017-11-03
     */
    public function fetchInsertActivityStage()
    {
        //从redis里取彩种类别数据
        $redis = initCacheRedis();
        $json_data = $redis->hGet('Config:redpacket_setting','value');

        $json_obj = json_decode($json_data, true);

        //取出当前活动期数的最大值
        if (! $json_obj['max_activity_stage']) {
            $max_activity_stage = $this->db->result("SELECT MAX(activity_stage) AS max_activity_stage FROM un_redpacket LIMIT 1");
        } else {
            $max_activity_stage = $json_obj['max_activity_stage'];
        }

        //当前最大期数加1
        $max_activity_stage++;

        //重写最大活动期数值
        $json_obj['max_activity_stage'] = $max_activity_stage;

        $new_json_str = json_encode($json_obj, JSON_UNESCAPED_UNICODE);

        $update_sql = "UPDATE un_config SET value = '{$new_json_str}' WHERE nid = 'redpacket_setting' ";
        $rows = $this->db->query($update_sql);

        //保存后更新redis缓存
        $this->refreshRedis('config', 'all');

        //关闭redis链接
        deinitCacheRedis($redis);

        return $max_activity_stage;
    }


    /**
     * 删除活动
     * 2017-11-03 update
     */
    public function redpacketDel()
    {
        $id = intval($_POST['id']);
        $where = [
            'id' => $id,
        ];
        $flag = $this->db->delete('un_redpacket', $where);
        echo json_encode(['rt' => $flag]);
    }

    /**
     * 停止活动，并标注手气最佳用户
     * 2017-11-03 update
     * 2017-11-15 update
     */
    public function redpacketStop()
    {
        $id = intval($_POST['id']);

        $now_time = time();

        //查询最佳手气者的信息
        $max_redpacket_money_info = D('RedpacketGainLog')->fetchMaxRedpacketMoneyInfo($id);

        if ($max_redpacket_money_info['id'] != false) {
            //标注手气最佳用户
            $update_best_lucky_sql = "UPDATE un_redpacket_gain_log SET is_best_lucky = 1, last_updatetime = {$now_time} WHERE id = {$max_redpacket_money_info['id']}";
            $this->db->query($update_best_lucky_sql);
        }

        //保存为停止状态
        $save_data = [
            'is_underway' => 2,
        ];
        $where = [
            'id' => $id,
        ];
        $actInfo = $this->db->getone("select * from un_redpacket where id = ".$id);
        $log_remark = "停止红包活动--活动名称:".$actInfo['activity_title'].'--活动期数:'.$actInfo['activity_stage'];
        $flag = $this->db->update('un_redpacket', $save_data, $where);
        if($flag) admin_operation_log($this->admin['userid'], 120, $log_remark);

        //重置 begin_order_id 值为 0
        $redis = initCacheRedis();
        $json_data = $redis->hGet('Config:redpacket_setting', 'value');
        $json_obj = json_decode($json_data, true);
        $json_obj['begin_order_id'] = '0';
        $new_json_str = json_encode($json_obj, JSON_UNESCAPED_UNICODE);

        //修改配置项，并更新redis
        D('redpacket')->setRedpacketConf($new_json_str, $redis);

        //关闭redis链接
        deinitCacheRedis($redis);

        echo json_encode(['rt' => $flag]);
    }


    /**
     * 复制本期
     * 2017-11-03
     */
    public function copyThisActivity()
    {
        $id = intval($_POST['id']);
        $activity_stage = $this->fetchInsertActivityStage();
        $is_underway = 0;
        $admin_id = $this->admin['userid'];
        $now_time = time();
        $now_time_after_7_days = $now_time + 7 * 24 * 3600;

        $sql = "INSERT INTO un_redpacket (`activity_stage`, `is_underway`, `admin_id`, `last_updatetime`, `activity_title`, `start_time`, `end_time`, `rules_json`, `user_group_limit`, `redpacket_pic`, `is_show_others_log`, `redpacket_divided_type`, `redpacket_max_money`, `redpacket_all_count`, `redpacket_reserved_count`, `redpacket_reserved_ids`, `already_gain_count`, `already_gain_sum`)
            SELECT {$activity_stage}, {$is_underway}, {$admin_id}, {$now_time}, `activity_title`, {$now_time}, {$now_time_after_7_days}, `rules_json`, `user_group_limit`, `redpacket_pic`, `is_show_others_log`, `redpacket_divided_type`, `redpacket_max_money`, `redpacket_all_count`, `redpacket_reserved_count`, '', 0, 0
            FROM un_redpacket WHERE id = {$id}";

        $flag = $this->db->query($sql);
        echo json_encode(['rt' => $flag]);
    }

    /**
     * 上传奖品图片（新）
     * @method GET
     * @return json
     */
    public function uploadImg()
    {
        $dirPath = '/redpacket/';
        return $this->newUploadImg($dirPath);
    }

    /**
     * 红包记录名单
     * 2017-11-13
     */
    public function redpacketGainLog()
    {

        $data = $_REQUEST;

        $where_arr = [];
        $activity_stage = intval($data['activity_stage']);
        if ($activity_stage > 0) {
            $where_arr[] = " activity_stage = {$activity_stage} ";
        }

        //时间段，取抢到红包的时间。
        $gain_time_begin = strtotime($data['start_time'] . ' 00:00:00');
        $gain_time_end = strtotime($data['end_time'] . ' 23:59:59');

        //该时间段查询，分以下几种情况：
        //a.有开始时间，无结束时间
        if ($gain_time_begin != false) {
            $where_arr[] = " gain_time >= {$gain_time_begin} ";
        }

        //b.无开始时间，有结束时间
        if ($gain_time_end != false) {
            $where_arr[] = " gain_time <= {$gain_time_end} ";
        }

        $reg_type = intval($data['reg_type']);
        if ($reg_type == 1) {
            $where_arr[] = "  u.reg_type NOT IN (0,8,9,11)";
        } elseif ($reg_type == 2) {
            $where_arr[] = "  u.reg_type = 11 ";
        }

        $username = trim($data['username']);
        if ($username != false) {
            $where_arr[] = " username LIKE '%{$username}%' ";
        }

        if ($where_arr) {
            $where_str = ' WHERE ' . implode(' AND ', $where_arr);
        } else {
            $where_str = '';
        }

        //分页带上的查询条件
        $params = $data;
        unset($params['m']);
        unset($params['c']);
        unset($params['a']);

        $sql_1 = "SELECT COUNT(*) AS data_cnt FROM un_redpacket_gain_log as r left join un_user as u on r.user_id = u.id {$where_str}";

        $countInfo = $this->db->getOne($sql_1);
        $listCnt = $countInfo['data_cnt'];
        $pagesize = 10;
        $url = '?m=admin&c=redpacket&a=redpacketGainLog';
        $page = new page($listCnt, $pagesize, $url, $params);
        $show = $page->show();

        $page_start = $page->offer;
        $page_size = $pagesize;

        $limit = "LIMIT {$page_start},{$page_size}";

        //查询红包记录
        $sql_2 = "SELECT r.id, r.user_id, r.username, r.redpacket_id, r.activity_stage, r.redpacket_money, r.gain_time, r.order_num, r.remark, r.last_updatetime, r.gain_type, r.is_best_lucky, r.admin_id, r.admin_username
            FROM un_redpacket_gain_log as r left join un_user as u on r.user_id = u.id {$where_str} ORDER BY id DESC {$limit}";
        $gain_list_info = $this->db->getAll($sql_2);

        //当前页数据
        $prize_money_arr = array_column($gain_list_info, 'redpacket_money');
        $sum_current_page = array_sum($prize_money_arr);

        //查询期数列表，并倒序排列
        $sql_3 = 'SELECT activity_stage FROM un_redpacket';
        $activity_stage_data = $this->db->getAll($sql_3);
        $activity_stage_arr = array_column($activity_stage_data, 'activity_stage');
        rsort($activity_stage_arr);

        //统计总共的中奖金额
        $count_data = $this->countSum($where_str);

        $sum_all = $count_data['sum_all'];

        // lg('hb_admin_gain_log_sql', var_export([
        //     'where_arr'=>$where_arr,
        //     'sql_1'=>$sql_1,
        //     'sql_2'=>$sql_2,
        //     'sql_3'=>$sql_3,
        // ], true));

        include template('redpacket-gain_list');
    }


    /**
     * 查询红包记录页面红包数额
     * 2017-11-13
     */
    public function countSum($where_str)
    {

        $sql = "SELECT SUM(redpacket_money) AS gain_sum FROM un_redpacket_gain_log as r left join un_user as u on r.user_id = u.id {$where_str}";

        $sum_all = $this->db->result($sql);

        return [
            'sum_all' => $sum_all,
        ];
    }

    /**
     * 参与详情，默认按红包活动id查询
     * 2017-11-13
     */
    public function joinDetails()
    {

        $redpacket_id = intval($_REQUEST['redpacket_id']);

        $username = trim($_REQUEST['username']) ? : '';

        //分页带上的查询条件
        $params = $_REQUEST;
        unset($params['m']);
        unset($params['c']);
        unset($params['a']);

        //有传用户名的查询逻辑
        if ($username == true) {

            //按主表取相应的字段名（un_user 表用户id字段名为"id"）
            $_column_key = 'id';

            //主分页表查询语句
            $main_select_sql = 'SELECT id,id AS user_id,username FROM un_user';

            //排序字串
            $order_by_str = 'ORDER BY id DESC';

            //查询结果总条数
            $where_str = " WHERE username LIKE '%{$username}%' ";
            $page_count_sql = "SELECT COUNT(*) AS data_cnt FROM un_user {$where_str}";
        }
        //没有传用户名的查询逻辑
        else {

            //按主表取相应的字段名（un_redpacket_gain_log 表用户id字段名为"user_id"）
            $_column_key = 'user_id';

            //主分页表查询语句
            $main_select_sql = 'SELECT id,user_id,username,gain_time AS time_view FROM un_redpacket_gain_log';

            //排序字串
            $order_by_str = 'ORDER BY time_view DESC';

            //活动记录总条数
            $where_str = " WHERE redpacket_id = {$redpacket_id} ";
            $page_count_sql = "SELECT COUNT(*) AS data_cnt FROM un_redpacket_gain_log {$where_str}";
        }

        //根据主表查询到的记录做分页。注意，这里比较绕，需谨慎
        $countInfo = $this->db->getOne($page_count_sql);
        $listCnt = $countInfo['data_cnt'];
        $pagesize = 10;
        $url = '?m=admin&c=redpacket&a=joinDetails';
        $page = new page($listCnt, $pagesize, $url, $params);
        $show = $page->show();

        $page_start = $page->offer;
        $page_size = $pagesize;

        $limit = " LIMIT {$page_start},{$page_size}";

        //查询主表信息（带用户关键词，则查询主表为用户表；不带用户关键词，则查询主表为红包记录表）
        $fetch_main_table_sql = "{$main_select_sql} {$where_str} {$order_by_str} {$limit}";
        $list_info = $this->db->getAll($fetch_main_table_sql);

        //查询到的用户id集合
        $user_ids_arr = array_column($list_info, $_column_key);
        $user_ids_str = implode(',', $user_ids_arr) ? : 0;

        //如果是搜索用户，则将有参加过该期红包的领取时间取出
        if ($username == true) {
            $fetch_user_gain_time_sql = "SELECT user_id,gain_time AS time_view FROM un_redpacket_gain_log 
                WHERE redpacket_id = {$redpacket_id} AND user_id IN ({$user_ids_str})";
            $user_gain_time_info = $this->db->getAll($fetch_user_gain_time_sql);

            //生成一个 键为用户id，值为 红包领取时间 的数组
            $user_gain_time_arr = array_column($user_gain_time_info, 'time_view', 'user_id');

            foreach ($list_info as $key => &$each_info_0) {

                //将搜索结果中，有参与过红包活动的用户的领取时间取出来
                $each_info_0['time_view'] = $user_gain_time_arr[$each_info_0[$_column_key]];
            }
        }


        //生成一个 键为用户id，值为 红包领取时间 的数组
        $user_time_view_arr = array_column($list_info, 'time_view', 'user_id');

        //查询当前红包活动相关信息
        $fetch_redpacket_sql = "SELECT activity_stage, (redpacket_all_count - already_gain_count - already_gain_count_reserved) AS remaining_count,
            start_time,end_time,user_group_limit,rules_json,redpacket_reserved_ids FROM un_redpacket WHERE id = {$redpacket_id}";
        $redpacket_info = $this->db->getOne($fetch_redpacket_sql);

        //活动开始和结束时间
        $start_time = $redpacket_info['start_time'] ? : 0;
        $end_time = $redpacket_info['end_time'] ? : 0;

        //查询参与的红包用户在红包活动期间内各自的投注量（打码量）
        $fetch_sum_betting_sql = "SELECT user_id,SUM(`money`) AS betting FROM un_orders
            WHERE award_state > 0
            AND state = 0
            AND addtime >= {$start_time}
            AND addtime <= {$end_time}
            AND user_id IN ({$user_ids_str})
            GROUP BY user_id";
        $betting_info = $this->db->getAll($fetch_sum_betting_sql);

        //生成一个 键为用户id，值为投注额 的数组（投注额是数值）
        $user_betting_arr = array_column($betting_info, 'betting', 'user_id');

        //查询参与的红包用户在红包活动期间内各自的充值次数和充值总额
        $fetch_recharge_sql = "SELECT user_id,SUM(`money`) AS recharge, COUNT(*) AS recharge_times
            FROM `un_account_recharge`
            WHERE status = 1
            AND addtime >= {$start_time}
            AND addtime <= {$end_time}
            AND user_id IN ({$user_ids_str})
            GROUP BY user_id";
        $recharge_info = $this->db->getAll($fetch_recharge_sql);

        //生成一个 键为用户id，值为投注信息 的数组（投注信息是个一维关联数组）
        $user_recharge_arr = [];
        foreach ($recharge_info as $each_user_recharge) {
            $user_recharge_arr[$each_user_recharge['user_id']] = [
                'recharge' => $each_user_recharge['recharge'],
                'recharge_times' => $each_user_recharge['recharge_times'],
            ];
        }

        //查询用户所属用户组
        $fetch_user_group_sql = "SELECT user.id, user.group_id, group.name FROM un_user AS `user`
            LEFT JOIN un_user_group AS `group` ON user.group_id = group.id
            WHERE user.id IN ({$user_ids_str})";
        $user_group_info = $this->db->getAll($fetch_user_group_sql);

        //生成一个 键为用户id，值为用户组信息 的数组（用户组信息是个一维关联数组）
        $user_group_arr = [];
        foreach ($user_group_info as $each_user_group) {
            $user_group_arr[$each_user_group['id']] = [
                'group_id' => $each_user_group['group_id'],
                'name' => $each_user_group['name'],
            ];
        }

        //整合参与的红包用户的数据数组
        $new_user_info = [];
        foreach ($user_ids_arr as $each_id) {
            $new_user_info[$each_id] = [
                //充值相关信息
                'recharge' => $user_recharge_arr[$each_id]['recharge'] ? : 0,
                'recharge_times' => $user_recharge_arr[$each_id]['recharge_times'] ? : 0,
                
                //投注相关信息
                'betting' => $user_betting_arr[$each_id] ? : 0,

                //红包领取时间 相关信息
                'time_view' => $user_time_view_arr[$each_id] ? : 0,
                
                //用户组相关信息
                'group_id' => $user_group_arr[$each_id]['group_id'],
                'group_name' => $user_group_arr[$each_id]['name'],

            ];
        }

        //活动规则
        $redpacket_rules = json_decode($redpacket_info['rules_json'], true);

        //层级限制
        $user_group_limit_arr = explode(',', $redpacket_info['user_group_limit']);

        $reserved_ids_arr = explode(',', $redpacket_info['redpacket_reserved_ids']);

        //最后，将所有信息整合到活动记录列表中，按用户区分
        foreach ($list_info as &$each_log) {
            $each_log['activity_stage'] = $redpacket_info['activity_stage'];
            $each_log['remaining_count'] = $redpacket_info['remaining_count'];
            // $each_log['end_date'] = date('Y-m-d H:i:s', $redpacket_info['end_time']);

            //该键值为用户id
            $tmp_key = $each_log[$_column_key];

            //增加一列：该会员是否为预留会员
            $each_log['is_reserved_user'] = (int) in_array($tmp_key, $reserved_ids_arr);

            //从上文生成的数组【$new_user_info】中去取数据
            $each_log['recharge'] = $new_user_info[$tmp_key]['recharge'];
            $each_log['recharge_times'] = $new_user_info[$tmp_key]['recharge_times'];
            $each_log['betting'] = $new_user_info[$tmp_key]['betting'];
            $each_log['group_id'] = $new_user_info[$tmp_key]['group_id'];
            $each_log['group_name'] = $new_user_info[$tmp_key]['group_name'];

            //用户领取红包时间，如果是搜索出来的用户列表，则将没有参与过该期活动的用户的列置为“未领取”
            $each_log['time_view'] = ($new_user_info[$tmp_key]['time_view'] == false) ? '未领取' : date('Y-m-d H:i:s', $new_user_info[$tmp_key]['time_view']);

            //判断用户的各个条件是否满足
            //a.投注额
            if ($each_log['betting'] >= $redpacket_rules['betting_val']) {
                $each_log['betting_is_reach'] = '1';
            } else {
                $each_log['betting_is_reach'] = '0';
            }

            //b.充值次数
            if ($each_log['recharge_times'] >= $redpacket_rules['recharge_times_val']) {
                $each_log['recharge_times_is_reach'] = '1';
            } else {
                $each_log['recharge_times_is_reach'] = '0';
            }

            //c.充值金额
            if ($each_log['recharge'] >= $redpacket_rules['recharge_money_val']) {
                $each_log['recharge_is_reach'] = '1';
            } else {
                $each_log['recharge_is_reach'] = '0';
            }

            //d.用户组
            if (in_array($each_log['group_id'], $user_group_limit_arr)) {
                $each_log['group_is_reach'] = '1';
            } else {
                $each_log['group_is_reach'] = '0';
            }
        }

        include template('redpacket-joinDetails');
    }


    /**
     * 编辑红包预留人员名单
     * 2017-11-15 update
     */
    public function editReserved()
    {
        $redpacket_id = intval($_REQUEST['redpacket_id']);
        $field = 'admin_id, last_updatetime, redpacket_reserved_ids, redpacket_reserved_count, already_gain_count_reserved';
        $redpacket_info = D('redpacket')->fetchInfoById($redpacket_id, $field);

        //活动创建人 
        $fetch_admin_info_sql = "SELECT username FROM un_admin WHERE userid = {$redpacket_info['admin_id']}";
        $admin_username = $this->db->result($fetch_admin_info_sql);

        //活动最后修改时间
        if ($redpacket_info['last_updatetime'] == 0) {
            $last_updatetime = '--';
        } else {
            $last_updatetime = date('Y-m-d H:i:s', $redpacket_info['last_updatetime']);
        }

        //预留人员名单
        if ($redpacket_info['redpacket_reserved_ids'] == '') {
            $redpacket_reserved_users_str = '';
            $redpacket_reserved_users_gain_str = '';

        } else {
            //将预留人员的用户id转换为用户名
            $user_ids_str = $redpacket_info['redpacket_reserved_ids'];
            $fetch_user_sql = "SELECT username FROM un_user WHERE id IN ({$user_ids_str})";
            $user_info = $this->db->getAll($fetch_user_sql);

            $username_arr = array_column($user_info, 'username');
            $redpacket_reserved_users_str = implode('; ', $username_arr);

            //将已领红包的预留人员也查询出来
            $fetch_gain_reserved_user_sql = "SELECT username FROM un_redpacket_gain_log
                WHERE user_id IN ({$user_ids_str}) AND redpacket_id = {$redpacket_id}";
            $gain_reserved_user_info = $this->db->getAll($fetch_gain_reserved_user_sql);

            $username_arr2 = array_column($gain_reserved_user_info, 'username');
            $redpacket_reserved_users_gain_str = implode('; ', $username_arr2);
        }

        //剩余未领取的预留红包个数
        $redpacket_reserved_remaining_count = $redpacket_info['redpacket_reserved_count'] - $redpacket_info['already_gain_count_reserved'];

        //预留人数
        $redpacket_reserved_count = $redpacket_info['redpacket_reserved_count'];

        include template('redpacket-editReserved');
    }

    /**
     * 保存红包预留人员名单
     * 2017-11-15 update
     */
    public function saveReserved()
    {
        $post_data = $_POST;
        if (trim($post_data['reserved_users_str']) == '') {
            echo json_encode(['rt' => -100, 'msg' => '名单不能为空',]);
            exit;
        }

        $redpacket_id = intval($post_data['redpacket_id']);

        $username_arr = explode(';', $post_data['reserved_users_str']);

        //去除左右多余空格
        $username_arr = array_map('trim', $username_arr);

        //过滤空字符用户
        $username_arr = array_filter($username_arr);

        //拼接where-in条件
        $username_where_in_str = '"' . implode('","', $username_arr) . '"';

        //查询用户名对应的id
        $fetch_user_id_sql = "SELECT id,username FROM un_user WHERE username IN ({$username_where_in_str})";
        $user_ids_arr = $this->db->getAll($fetch_user_id_sql);

        //取数据表中查询出来的用户名为新数组
        $user_info_arr = array_column($user_ids_arr, 'username');

        //用提交过来的用户名，和数据表里查询出来的用户名做对比，得到用户表里没查到的用户名
        $user_not_in_table = array_diff($username_arr, $user_info_arr);

        //如果有用户表里没有的用户名，则返回提示操作人员
        if ($user_not_in_table) {
            echo json_encode(['rt' => -200, 'msg' => '以下用户不存在：' . implode('、', $user_not_in_table),]);
            exit;
        }

        $now_time = time();

        $user_ids_str = implode(',', array_column($user_ids_arr, 'id'));

        //执行修改操作
        $update_redpacket_sql = "UPDATE un_redpacket SET redpacket_reserved_ids = '{$user_ids_str}',
            last_updatetime = '{$now_time}' WHERE id = {$redpacket_id}";
        $this->db->query($update_redpacket_sql);

        //记录日志
        $lg_data = [
            'fetch_user_id_sql' => $fetch_user_id_sql,
            'update_redpacket_sql' => $update_redpacket_sql,
            'user_ids_arr' => $user_ids_arr,
            'user_ids_str' => $user_ids_str,
            'post_data' => $post_data,
            'admin_id' => $this->admin['userid'],
            'admin_username' => $this->admin['username'],
        ];
        lg('hb_save_reserved', var_export($lg_data, true));

        echo json_encode(['rt' => 0, 'msg' => '',]);
        exit;
    }

    /**
     * 查看参与明细记录
     * 2017-11-29 update
     */
    public function checkJoinDetail()
    {
        $activity_stage = floatval($_REQUEST['activity_stage']);
        $user_id = floatval($_REQUEST['user_id']);
        
        //带入详情页面条件
        $view_page = floatval($_REQUEST['view_page']);
        $username = trim($_REQUEST['username']);

        $redpacket_model = D('Redpacket');

        $field = 'id, start_time, end_time';
        $redpacket_info = $redpacket_model->fetchInfoByActivityStage($activity_stage, $field);

        //获取总记录条数
        $condition_arr = [
            'user_id' => $user_id,
            'start_time' => $redpacket_info['start_time'],
            'end_time' => $redpacket_info['end_time'],
        ];
        $count_num = $redpacket_model->fetchUserPlayOrderSnCount($condition_arr);


        //分页带上的条件
        $params = $_REQUEST;
        unset($params['m'], $params['c'], $params['a']);

        $pagesize = 10;
        $url = '?m=admin&c=redpacket&a=checkJoinDetail';
        $page = new page($count_num, $pagesize, $url, $params);
        $show = $page->show();

        $page_start = $page->offer;
        $page_size = $pagesize;

        $rt_data = $redpacket_model->fetchUserPlayOrderSnPageData($user_id, $redpacket_info, $page_start, $page_size);

        //没有数据
        if (! $rt_data) {
            $userWaterSnDetailInfo = [];
        } else {
            $water_sn_arr = array_column($rt_data, 'water_sn');
            //拼接where-in条件
            $water_sn_where_in_str = '"' . implode('","', $water_sn_arr) . '"';

            //10为充值 13为投注
            $condition_where = " WHERE user_id = {$user_id} AND order_num IN ({$water_sn_where_in_str}) AND `type` IN (10,13) ";

            //用流水号作为条件，查询流水详情记录
            $userWaterSnDetailInfo = $redpacket_model->fetchUserWaterSnDetail($condition_where);

        }

        include template('redpacket-checkJoinDetail');
    }
    
    //如果新增或修改红包活动的状态是开启状态，会调用本函数重新计算红包配置里面的活动投注订单ID
    /**
     * 修改红包配置打码量订单ID
     * @param int $start_time 活动开始时间
     */
    public function updateActivityFlag($start_time, $end_time)
    {
        $begin_order_id = 0;
        $now_time = time();
        
        $redis = initCacheRedis();
        
        $json_data = $redis->hGet('Config:redpacket_setting','value');
        $json_obj = json_decode($json_data, true);

        //按时间段，查询当前是否有活动正在进行
        if ($start_time <= $now_time && $end_time > $now_time) {
            //查询活动开启后，第一笔订单ID
            $begin_order_id = $this->db->result("SELECT id FROM un_orders WHERE addtime >= {$start_time} LIMIT 1");
        
            //如果没有第一笔订单ID（$begin_order_id），并且有正在进行中的活动，则取订单表最大id值再加一
            if (empty($begin_order_id)) {
                $begin_order_id = $this->db->result("SELECT MAX(id) FROM un_orders");
                $begin_order_id += 1;
            }
        }
        
        //记录到redis，并更新数据库
        $json_obj['begin_order_id'] = $begin_order_id;
        
        $new_json_str = json_encode($json_obj, JSON_UNESCAPED_UNICODE);
        
        $update_sql = "UPDATE un_config SET value = '{$new_json_str}' WHERE nid = 'redpacket_setting' ";
        $rows = $this->db->query($update_sql);
        
        //保存后更新redis缓存
        $this->refreshRedis('config', 'all');
        
        //关闭redis链接
        deinitCacheRedis($redis);
        
        return true;
    }

}
