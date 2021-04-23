<?php

/**
 * @copyright			(C) 2013 CHENGHUITONG.COM
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'page.php';

class TurntableAction extends Action {

    /**
     * 数据表
     */
    private $turntable_model;

    public function __construct()
    {
        parent::__construct();
        $this->turntable_model = D('turntable');
    }



    /**
     * 活动列表
     * 2017-09-12
     */
    public function activity_list () {
        $field = 'turntable.id,activity_title,activity_stage,is_underway,start_time,end_time,admin_id,admin.username AS admin_user';

        //分页带上的条件
        $params = $_REQUEST;
        unset($params['m']);
        unset($params['c']);
        unset($params['a']);

        // $sql = "SELECT {$field} FROM un_turntable";
        $sql = 'SELECT COUNT(*) AS rows_cnt FROM un_turntable AS turntable
                LEFT JOIN un_admin AS admin ON admin.userid = turntable.admin_id';

        $countInfo = $this->db->getOne($sql);
        $listCnt = $countInfo['rows_cnt'];
        $pagesize = 10;
        $url = '?m=admin&c=turntable&a=activity_list';
        $page = new page($listCnt, $pagesize, $url, $params);
        $show = $page->show();

        $page_start = $page->offer;
        $page_size = $pagesize;

        $limit = "limit {$page_start},{$page_size}";

        //查询活动列表
        $sql2 = "SELECT {$field} FROM un_turntable AS turntable
            LEFT JOIN un_admin AS admin ON admin.userid = turntable.admin_id
            ORDER BY activity_stage DESC {$limit}";
        $activity_list = $this->db->getAll($sql2);

        //查询当前是否在用户个人中心显示大转盘入口
        $sql_is_nav_show = "SELECT value FROM un_config WHERE nid = 'turntable_setting' ";
        $data = $this->db->getOne($sql_is_nav_show);
        $is_show_in_profile = json_decode($data['value'], true)['is_show_in_profile'];


        include template('turntable-activity_list');
    }

    /**
     * 修改是否在用户中心显示大转盘入口
     */
    public function save_turntable_setting () {
        $is_show_in_profile = intval($_POST['is_show_in_profile']);

        //先读后写，查询当前是否在用户个人中心显示大转盘入口
        $sql_is_nav_show = "SELECT value FROM un_config WHERE nid = 'turntable_setting' ";
        $data = $this->db->getOne($sql_is_nav_show);
        
        $json_arr = json_decode($data['value'], true);
        $json_arr['is_show_in_profile'] = $is_show_in_profile;

        $new_json_str = json_encode($json_arr, JSON_UNESCAPED_UNICODE);

        $update_sql = "UPDATE un_config SET value = '{$new_json_str}' WHERE nid = 'turntable_setting' ";
        $rows = $this->db->query($update_sql);

        //保存后更新redis缓存
        $this->refreshRedis('config', 'all');

        echo json_encode(['rt' => 1, 'msg' => 'OK', 'rows' => $rows]);
    }

    /**
     * 查看、编辑活动
     * 2017-09-13
     * 
     * 方式一投注规则json键值详细说明：
     *      every_topup : 每次得到抽奖次数的阀值（充值）
     *      every_topup_val : 每次（充值）达到阀值后得到的抽奖次数
     * 
     *      every_bet : 每次得到抽奖次数的阀值（投注）
     *      every_bet_val : 每次（投注）达到阀值后得到的抽奖次数
     * 
     *      every_win : 每次得到抽奖次数的阀值（赢）
     *      every_win_val : 每次（赢）达到阀值后得到的抽奖次数
     * 
     *      every_lose : 每次得到抽奖次数的阀值（输）
     *      every_lose_val : 每次（输）达到阀值后得到的抽奖次数
     * 
     */
    public function activity_edit () {
        //查看类型：1.新增 2.编辑 3.查看（暂不启用）
        $view_type = intval($_GET['view_type']);

        //活动id
        $id = intval($_GET['id']);

        $now_time = time();

        //默认配置数据
        $default_rules_data = [
            'rules_type' => 1,
            'every_topup'     => 1000,
            'every_topup_val' => 1,
            'every_bet'       => 1000,
            'every_bet_val'   => 1,
            'every_win'       => 1000,
            'every_win_val'   => 1,
            'every_lose'      => 1000,
            'every_lose_val'  => 1,

            'every_topup_a'   => 1001,
            'every_topup_b'   => 5000,
            'every_bet_a'     => 1001,
            'every_bet_b'     => 5000,
            'every_win_a'     => 1001,
            'every_win_b'     => 5000,
            'every_lose_a'    => 1001,
            'every_lose_b'    => 5000,
        ];

        //新增逻辑
        if ($view_type == '1') {
            //活动规则数据--[新增时的默认数据]
            $activity_rules_data = $default_rules_data;

            //奖品设置数据--[新增时的默认数据]
            $prize_setting_data = [];

            //活动开始时间--[新增时的默认数据]
            $s_date = date('Y-m-d H:i:s', $now_time);
            $e_date = date('Y-m-d H:i:s', $now_time);

            //层级限制--[新增时的默认数据]
            $user_group_limit_arr = [];
        } else {

            $field = 'id,activity_title,activity_stage,is_underway,start_time,end_time,admin_id,rules_json,user_group_limit,turn_num,turn_pic,activity_setting_json,last_updatetime,activity_details,activity_statement';
            $activity_info = $this->db->getOne("SELECT {$field} FROM un_turntable WHERE id = {$id}");

            //活动规则数据
            $activity_rules_data = (array)json_decode($activity_info['rules_json'], true);

            $activity_rules_data = array_merge($default_rules_data, $activity_rules_data);

            //奖品设置数据
            $prize_setting_data = json_decode($activity_info['activity_setting_json'], true);

            //活动开始时间
            $s_date = date('Y-m-d H:i:s', $activity_info['start_time']);
            $e_date = date('Y-m-d H:i:s', $activity_info['end_time']);

            //层级限制
            $user_group_limit_arr = explode(',', $activity_info['user_group_limit']);

        }


        //取用户组配置数据
        $group_info_arr = $this->db->getAll('SELECT id,name FROM un_user_group WHERE state = 0');

        //转盘格数缓存数组
        $turn_num_arr = [4,8,10,12];

        //转盘图片位置对应的文字解释缓存数组
        $content_in_pic_arr = [
            '谢谢参与',
            '一等奖',
            '二等奖',
            '三等奖',
            '四等奖',
            '五等奖',
            '六等奖',
            '七等奖',
            '八等奖',
            '九等奖',
            '十等奖',
            '十一等奖',
        ];

        // $test = [
        //     'every_topup' => 100,
        //     'every_topup_val' => 1,
        //     'every_bet' => 200,
        //     'every_bet_val' => 2,
        //     'every_win' => 300,
        //     'every_win_val' => 3,
        //     'every_lose' => 400,
        //     'every_lose_val' => 4,
        // ];

        include template('turntable-activity_edit');
    }

    /**
     * 保存活动详情
     * 2017-09-13
     */
    public function activity_save () {
        // $get_data = $_GET;
        $post_data = $_POST;

        $save_data = [];
        $save_data['id'] = intval($post_data['id']);
        $view_type = intval($post_data['view_type']);

        //判断是新增还是编辑
        if (! $save_data['id'] && $view_type == '1') {
            $save_type = 'add';
            $save_data['id'] = null;
            $save_data['turn_num'] = intval($post_data['turn_num']);
            $save_data['turn_pic'] = trim($post_data['turn_pic']);
            $save_data['admin_id'] = $this->admin['userid'];
        } else {
            $save_type = 'update';
        }
        $save_data['start_time'] = strtotime($post_data['start_time']);
        $save_data['end_time'] = strtotime($post_data['end_time']);
        $save_data['activity_title'] = $post_data['activity_title'];
        $save_data['is_underway'] = intval($post_data['is_underway']) ? : 0;

        //判断是否有两个活动同时在进行
        if ($save_data['is_underway'] == '1') {
            $check_data = $this->db->getOne('SELECT id,is_underway FROM un_turntable WHERE is_underway = 1');
            if ($check_data && $check_data['id'] != $save_data['id']) {
                echo json_encode(['rt' => 0, 'msg' => '不能同时开启两个活动！']);
                exit;
            }
        }

        $save_data['user_group_limit'] = $post_data['user_group_limit'];
        $save_data['activity_details'] = $post_data['activity_details'];
        $save_data['activity_statement'] = $post_data['activity_statement'];

        //规则设置
        $save_data['rules_json'] = json_encode($post_data['rules_json'], JSON_UNESCAPED_UNICODE);

        //奖品设置
        $save_data['activity_setting_json'] = json_encode($post_data['activity_setting_json'], JSON_UNESCAPED_UNICODE);

        //更新时间
        $save_data['last_updatetime'] = time();

        $where = [
            'id' => $save_data['id'],
        ];
        if ($save_type == 'add') {
            $max_id = $this->fetch_insert_activity_stage();
            $save_data['activity_stage'] = $max_id;
            $flag = $this->db->insert('un_turntable', $save_data);
            $log_remark = "新增辛运大转盘活动--活动名称:".$save_data['activity_title'].'--期数:'.$save_data['activity_stage'];
        } else {
            $flag = $this->db->update('un_turntable', $save_data, $where);
            $arr = [1 => '开启', 2 => '停止'];
            $actInfo = $this->db->getone("select * from un_turntable where id = ".$save_data['id']);
            $log_remark = $arr[$save_data['is_underway']]."辛运大转盘活动--活动名称:".$save_data['activity_title'].'--期数:'.$actInfo['activity_stage'];
        }

        if($flag) admin_operation_log($this->admin['userid'], 120, $log_remark);
        echo json_encode(['rt' => $flag]);
        exit;
    }

    /**
     * 获取新增活动期数，最大活动期数+1
     */
    public function fetch_insert_activity_stage () {

        //从redis里取彩种类别数据
        $redis = initCacheRedis();
        $json_data = $redis->hGet('Config:turntable_setting','value');

        $json_obj = json_decode($json_data, true);

        //取出当前活动期数的最大值
        if (! $json_obj['max_activity_stage']) {
            $max_activity_stage = $this->db->result("SELECT MAX(activity_stage) AS max_activity_stage FROM un_turntable LIMIT 1");
        } else {
            $max_activity_stage = $json_obj['max_activity_stage'];
        }

        //当前最大期数加1
        $max_activity_stage++;

        //重写最大活动期数值
        $json_obj['max_activity_stage'] = $max_activity_stage;

        $new_json_str = json_encode($json_obj, JSON_UNESCAPED_UNICODE);

        $update_sql = "UPDATE un_config SET value = '{$new_json_str}' WHERE nid = 'turntable_setting' ";
        $rows = $this->db->query($update_sql);

        //保存后更新redis缓存
        $this->refreshRedis('config', 'all');

        //关闭redis链接
        deinitCacheRedis($redis);

        return $max_activity_stage;
    }

    /**
     * 删除活动
     * 2017-09-16 update
     */
    public function activity_del () {
        $id = intval($_POST['id']);
        $actInfo = $this->db->getone("select * from un_turntable where id = $id");
        if(!$actInfo) {
            exit(json_encode(['rt' => 0]));
        }
        $where = [
            'id' => $id,
        ];
        $flag = $this->db->delete('un_turntable', $where);
        if($flag) {
            $log_remark = '删除辛运大转盘活动'.'--活动名称:'.$actInfo['activity_title'].'--活动期数:'.$actInfo['activity_stage'];
            admin_operation_log($this->admin['userid'], 120, $log_remark, $id);
        }
        echo json_encode(['rt' => $flag]);
    }

    /**
     * 停止活动
     * 2017-09-16 update
     */
    public function activity_stop () {
        $id = intval($_POST['id']);
        $actInfo = $this->db->getone("select * from un_turntable where id = $id");
        if(!$actInfo) {
            exit(json_encode(['rt' => 0]));
        }
        $save_data = [
            'is_underway' => 2,
        ];
        $where = [
            'id' => $id,
        ];
        //活动中心逻辑里，大转盘活动的 act_type 值为2，此值为固定值
        D('Actcenter')->updateActIsUnderway(2);

        $flag = $this->db->update('un_turntable', $save_data, $where);
        if($flag) {
            $log_remark = '停止辛运大转盘活动'.'--活动名称:'.$actInfo['activity_title'].'--活动期数:'.$actInfo['activity_stage'];
            admin_operation_log($this->admin['userid'], 120, $log_remark, $id);
        }
        echo json_encode(['rt' => $flag]);
    }

    /**
     * 中奖者名单
     * 2017-09-19 update
     */
    public function award_list () {

        $data = $_REQUEST;

        $where_arr = [];
        $activity_stage = intval($data['activity_stage']);
        if ($activity_stage > 0) {
            $where_arr[] = " t.activity_stage = {$activity_stage} ";
        }

        $reg_type = intval($data['reg_type']);
        if ($reg_type == 1) {
            $where_arr[] = "  u.reg_type NOT IN (0,8,9,11)";
        } elseif ($reg_type == 2) {
            $where_arr[] = "  u.reg_type = 11 ";
        }

        //是否含谢谢参与
        $prize_id = intval($data['prize_id']);
        if ($prize_id == '1') {
            $where_arr[] = " t.prize_id <> 1 ";
        }

        //奖品类型
        $prize_type = intval($data['prize_type']);
        if ($prize_type > 0) {
            $where_arr[] = " t.prize_type = {$prize_type} ";
        }

        $gain_time_begin = strtotime($data['start_time'] . ' 00:00:00');
        $gain_time_end = strtotime($data['end_time'] . ' 23:59:59');

        //该时间段查询，分以下几种情况：
        //a.有开始时间，无结束时间
        if ($gain_time_begin != false) {
            $where_arr[] = " t.award_time >= {$gain_time_begin} ";
        }

        //b.无开始时间，有结束时间
        if ($gain_time_end != false) {
            $where_arr[] = " t.award_time <= {$gain_time_end} ";
        }

        $username = trim($data['username']);
        if ($username != false) {
            $where_arr[] = " t.username LIKE '%{$username}%' ";
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

        $sql = "SELECT COUNT(*) AS award_cnt FROM un_turntable_award_log as t LEFT JOIN un_user  as u on t.user_id = u.id {$where_str}";

        $countInfo = $this->db->getOne($sql);
        $listCnt = $countInfo['award_cnt'];
        $pagesize = 10;
        $url = '?m=admin&c=turntable&a=award_list';
        $page = new page($listCnt, $pagesize, $url, $params);
        $show = $page->show();

        $page_start = $page->offer;
        $page_size = $pagesize;

        $limit = "limit {$page_start},{$page_size}";

        //查询中奖名单
        $sql = "SELECT t.id,t.user_id,t.username,t.turntable_id,t.activity_stage,t.prize_id,t.prize_type,t.prize_money,t.prize_name,t.giving_status,t.award_time,t.remark,t.last_updatetime FROM un_turntable_award_log as t left join un_user as u on t.user_id = u.id {$where_str} ORDER BY id DESC {$limit}";
        $award_data = $this->db->getAll($sql);

        //当前页数据
        $prize_money_arr = array_column($award_data, 'prize_money');
        $sum_current_page = array_sum($prize_money_arr);

        //查询期数列表，并倒序排列
        $sql2 = 'SELECT activity_stage FROM un_turntable';
        $activity_stage_data = $this->db->getAll($sql2);
        $activity_stage_arr = array_column($activity_stage_data, 'activity_stage');
        rsort($activity_stage_arr);

        //统计总共的中奖金额
        $count_data = $this->count_award_sum($where_str);

        $sum_all = $count_data['sum_all'];

        include template('turntable-award_list');
    }

    /**
     * 查询中奖名单页面中奖数额
     * 2017-09-21
     */
    public function count_award_sum ($where_str) {

        $sql = "SELECT SUM(prize_money) AS award_sum FROM un_turntable_award_log as t left join un_user as u on t.user_id = u.id {$where_str}";

        $sum_all = $this->db->result($sql);

        return [
            'sum_all' => $sum_all,
        ];


    }

    /**
     * 更新实物奖品派发状态
     */
    public function update_award_status () {
        $id = intval($_POST['id']);
        if (! $id) {
            echo json_encode(['rt' => 0, 'msg' => '参数非法，请确认！']);
            exit;
        }

        $save_data = [
            'giving_status' => 1,
            'last_updatetime' => time(),
        ];
        $where = ['id' => $id];
        $flag = $this->db->update('un_turntable_award_log', $save_data, $where);
        echo json_encode(['rt' => $flag]);
        exit;
    }

    /**
     * 复制本期
     */
    public function copy_this_activity () {

        $id = intval($_POST['id']);

        $sql = "select * from un_turntable where id = {$id}";
        $list = $this->db->getone($sql);
        $list['is_underway'] = 0;
        $list['last_updatetime'] = time();
        $list['admin_id'] = $this->admin['userid'];
        $list['activity_stage'] = $this->fetch_insert_activity_stage();

        unset($list['id']);
        $config = json_decode($list['activity_setting_json'],true);
        foreach ($config as $key=>$val) {
            $config[$key]['rest_prize_num'] = $val['prize_num'];
        }
        $list['activity_setting_json'] = json_encode($config,JSON_UNESCAPED_UNICODE);
        $flag = $this->db->insert("un_turntable",$list);
        if($flag) {
            $log_remark = '新增辛运大转盘活动'.'--活动名称:'.$list['activity_title'].'--活动期数:'.$list['activity_stage'];
            admin_operation_log($this->admin['userid'], 120, $log_remark);
        }
        echo json_encode(['rt' => $flag]);
    }

    /**
     * 后台查看用户次数
     */
    public function userTimesCheck()
    {
        $turntable_model = D('turntable');

        //活动期数下拉列表
        $activity_stage_list = $turntable_model->fetch_activity_stage();

        //按条件搜索
        $activity_stage = trim($_REQUEST['activity_stage']);
        $username = trim($_REQUEST['username']);

        $id_key = array_search($activity_stage, $activity_stage_list);

        $id_key = intval($id_key);

        //获取该用户还剩下多少次数
        $user_times_info = $turntable_model->fetch_user_times($username, $id_key);

        include template('turntable-userTimesCheck');
    }

    /**
     * 设置用户抽奖次数表单页
     * 2017-11-06
     */
    public function setUserTimesForm()
    {
        include template('turntable-setUserTimesForm');
    }

    /**
     * 设置用户抽奖次数
     * 2017-11-06
     */
    public function setUserTimesDone()
    {
        $post_data = $_POST;
        $username = trim($post_data['username']);
        $times = intval($post_data['times']);

        //检查次数是否为数字
        if ($times == 0) {
            echo json_encode(['rt' => 10003, 'msg' => '请输入正确的抽奖调整次数']);
            exit;
        }

        $now_time = time();

        //检查是否有正在进行中的活动
        $sql_1 = 'SELECT id FROM un_turntable WHERE is_underway = 1 LIMIT 1';
        $turntable_id = $this->db->result($sql_1);
        if (! $turntable_id) {
            echo json_encode(['rt' => 10001, 'msg' => '暂无正在进行中的活动']);
            exit;
        }

        //检查用户是否存在
        $sql_2 = "SELECT id FROM un_user WHERE username = \"{$username}\" LIMIT 1";
        $user_id = $this->db->result($sql_2);
        if (! $user_id) {
            echo json_encode(['rt' => 10002, 'msg' => '用户名不存在']);
            exit;
        }

        //先查询该用户是否有参加过活动，再进行添加/减少次数操作
        $sql_3 = "SELECT id,join_count FROM un_turntable_join_log
            WHERE user_id = {$user_id}
            AND turntable_id = {$turntable_id}";
        $join_info = $this->db->getOne($sql_3);
        $join_id = $join_info['id'];

        //把要操作的参与次数值转换成正值，并拼接操作sql
        $abs_times = abs($times);
        if ($times >= 0) {
            $setting_sql = 'join_count - ' . $abs_times;
            $setting_times = '-' . $abs_times;
        } else {
            $setting_sql = 'join_count + ' . $abs_times;
            $setting_times = $abs_times;
        }

        if (! $join_id) {
            $query_sql = "INSERT INTO un_turntable_join_log VALUES (NULL, \"{$user_id}\", \"{$setting_times}\", \"{$turntable_id}\", 0, {$now_time})";
        } else {
            $query_sql = "UPDATE un_turntable_join_log SET join_count = {$setting_sql} WHERE id = {$join_id}";
        }


        //记录日志
        $lg_data = [
            'admin_id' => $this->admin['userid'],
            'admin_username' => $this->admin['username'],
            'settting_username' => $username,
            'join_info' => $join_info,
            '$post_data[times]' => $post_data['times'],
            'times' => $times,
            'sql_1' => $sql_1,
            'sql_2' => $sql_2,
            'sql_2' => $sql_2,
            'query_sql' => $query_sql,
            'setting_sql' => $setting_sql,
        ];
        lg('zp_setTimes', var_export($lg_data, true));

        $this->db->query($query_sql);
        echo json_encode(['rt' => 0, 'msg' => 'OK']);
        exit;
    }

    /**
     * 上传奖品图片（新）
     * @method GET
     * @return json
     */
    public function uploadImg()
    {
        $dirPath = '/turntable/prize_pic/';
        return $this->newUploadImg($dirPath);
    }


    // /**
    //  * 上传奖品图片（旧，暂不启用）
    //  * @method GET
    //  * @return json
    //  */
    // public function uploadImg() {
    //     $error = array();
    //     if ($_FILES['file']['error'] > 0) {
    //         jsonReturn(array('status' => 200000, 'data' => '图片上传失败'));
    //     } else {
    //         if ($_FILES['file']['size'] > 2097152) { // 图片大于2MB
    //             ErrorCode::errorResponse(ErrorCode::AVATAR_TOO_BIG);
    //         } else {
    //             $suffix = '';
    //             switch ($_FILES['file']['type']) {
    //                 case 'image/gif':
    //                     $suffix = 'gif';
    //                     break;
    //                 case 'image/jpeg':
    //                 case 'image/pjpeg':
    //                     $suffix = 'jpg';
    //                     break;
    //                 case 'image/bmp':
    //                     $suffix = 'bmp';
    //                     break;
    //                 case 'image/png':
    //                 case 'image/x-png':
    //                     $suffix = 'png';
    //                     break;
    //                 default:
    //                     jsonReturn(array('status' => 200001, 'data' => '图片格式不正确'));
    //             }

    //             $FileName = md5(time()) . "." . $suffix;

    //             $path = $this->getAvatarUrl($FileName, 0);

    //             if (!move_uploaded_file($_FILES['file']['tmp_name'], $path)) {
    //                 jsonReturn(array('status' => 200001, 'data' => '图片上传失败'));
    //             }
    //             jsonReturn(array('status' => 0, 'data' => "/" . C('upfile_path') . '/turntable/prize_pic/' . $FileName));
    //         }
    //     }
    // }

    // private function getAvatarUrl($avatarFileName, $isRand = 1) {
    //     if (empty($avatarFileName)) {
    //         return '';
    //     }
    //     $avatarUrl = S_ROOT . C('upfile_path') . '/turntable/prize_pic/';
    //     if ($isRand) {
    //         $avatarUrl .= ('?rand=' . time());
    //     }
    //     if (!file_exists($avatarUrl)) {
    //         @mkdir($avatarUrl, 0777, true);
    //     }

    //     return $avatarUrl . $avatarFileName;
    // }

}
