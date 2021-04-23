<?php
/**
 * Created by PhpStorm.
 * User: rui.wang
 * Date: 2016/12/07
 * Time: 16:06
 * desc: 报表
 */
ini_set('max_execution_time', '0');
ini_set('memory_limit','1024M');
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'pages.php';
include S_CORE . 'class' . DS . 'page.php';

class ReportingAction extends Action {
    protected $model;
    protected $model2;

    public function __construct() {
        parent::__construct();
        $this->model = D('user');
        $this->model2 = D('account');
    }

    /*
     * 报表查询最大天数设置
     * */
    public function setReportCountMaxDay() {
        $isPost = getParame('isPost',0);
        $confKey = 'report_count_max_day';
        if($isPost) {
            $value = getParame('set_value',1,'','int');

            $info = $this->db->getone("select * from un_config where nid = '".$confKey."'");
            if($info) {
                $upData = ['value' => $value];
                $res = $this->db->update('un_config',$upData,'id='.$info['id']);
                $info['value'] = $value;
            }else {
                $info = [
                    'nid' => $confKey,
                    'value' => $value,
                    'name' => '报表查询最大天数',
                    'desc' => '会员、团队、代理报表等最大查询天数限制',
                ];
                $res = $this->db->insert('un_config',$info);
            }

            if($res) {
                $redis = initCacheRedis();
                $redis->del('Config:'.$confKey);
                $redis->hMset('Config:'.$confKey,$info);
                jsonReturn(['status' => 0]);
            }
            jsonReturn(['status' => 1]);
        }

        $redis = initCacheRedis();
        $res = $redis->hMGet('Config:'.$confKey,['value']);
        deinitCacheRedis($redis);
        include template('set-report-count-max-day');
    }

    /**
     *
     * 输赢报表
     */
    public function betWinReport (){
        $data = $_REQUEST;
        $lottery_type = $data['lottery_type'];

        $start_date  = $data['start_date'] ? :date('Y-m-d',time()-86400);
        $end_date  = $data['end_date'] ? :date('Y-m-d',time()-86400);

        $redis = initCacheRedis();
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
        $lotteryArr = [];
        foreach ($LotteryTypeIds as $lottery) {
            $lottery_name = $redis->hMGet('LotteryType:'.$lottery, ['name']);
            $lotteryArr[$lottery] = $lottery_name['name'];
            unset($lottery);
        }
        //关闭redis链接
        deinitCacheRedis($redis);

        $where = " `date` >= '{$start_date}' AND  `date` <= '{$end_date}' ";
        if($lottery_type) $where .= ' and lottery_type = '.$lottery_type;
        $sql = "SELECT lottery_name,lottery_type,
                    SUM(bet_peoples) AS sum_bet_peoples,
                    SUM(bet_times) AS sum_bet_times ,
                    SUM(bet_moneys) AS sum_bet_moneys ,
                    SUM(bet_awards) AS sum_bet_awards 
                FROM un_history_gamewins WHERE {$where}
                GROUP BY lottery_type";
        // dump($sql);
        $calculation_data = $this->db->getAll($sql);

        //汇总数据计算
        $all_sum_data = [
            'sum_bet_peoples' => 0,
            'sum_bet_times' => 0,
            'sum_bet_moneys' => 0,
            'sum_bet_awards' => 0,
        ];
        foreach ($calculation_data as $k => &$each_cal_data) {
            //平台盈利
            $each_cal_data['profit_val'] = sprintf('%.2f', $each_cal_data['sum_bet_moneys'] - $each_cal_data['sum_bet_awards']);

            if ($each_cal_data['sum_bet_peoples'] == 0) {
                $each_cal_data['per_bet_val'] = 0;
                $each_cal_data['per_times_val'] = 0;
            } else {
                $each_cal_data['per_bet_val'] = sprintf('%.2f', $each_cal_data['sum_bet_moneys'] / $each_cal_data['sum_bet_peoples']);      //人均投注额，保留2位小数
                $each_cal_data['per_times_val'] = sprintf('%.2f', $each_cal_data['sum_bet_times'] / $each_cal_data['sum_bet_peoples']);      //人均投注次数，保留2位小数
            }

            $all_sum_data['sum_bet_peoples'] += $each_cal_data['sum_bet_peoples'];
            $all_sum_data['sum_bet_times'] += $each_cal_data['sum_bet_times'];
            $all_sum_data['sum_bet_moneys'] += $each_cal_data['sum_bet_moneys'];
            $all_sum_data['sum_bet_awards'] += $each_cal_data['sum_bet_awards'];
            unset($each_cal_data);
        }

        //汇总平台盈利
        $all_sum_data['profit_val'] = $all_sum_data['sum_bet_moneys'] - $all_sum_data['sum_bet_awards'];

        //汇总计算人均投注
        if ($all_sum_data['sum_bet_peoples'] == 0) {
            $all_sum_data['per_bet_val'] = 0;
            $all_sum_data['per_times_val'] = 0;
        } else {
            //人均投注额，保留2位小数
            $all_sum_data['per_bet_val'] = $all_sum_data['sum_bet_moneys'] / $all_sum_data['sum_bet_peoples'];

            //人均投注次数，保留2位小数
            $all_sum_data['per_times_val'] = $all_sum_data['sum_bet_times'] / $all_sum_data['sum_bet_peoples'];
        }

        //统一转换成2位小数
        $all_sum_data = array_map(function ($n) {
            return sprintf('%.2f', $n);
        }, $all_sum_data );

        //转换成整数
        $all_sum_data['sum_bet_peoples'] = floatval($all_sum_data['sum_bet_peoples']);
        $all_sum_data['sum_bet_times'] = floatval($all_sum_data['sum_bet_times']);

        // dump(date('Y-m-d H:i:s',$end_time));

        //交易类型
        // $trade = $this->model2->getTrade();
        // dump($trade);

        //今日交易数据
        // $res = D('Reporting')->getTradeLogB($start_time,$end_time);
        // dump($res);
        // $day_data =D('Reporting')->get_arr_diff($res,$trade['tranTypeIds']);

        //本月
        // $start_month_date = date("Y-m-01 00:00:00");
        // $end_month_date = date("Y-m-d H:i:s");
        // $start_month_time = strtotime($start_month_date);
        // $end_month_time = strtotime($end_month_date);
        // //今日交易数据
        // $res = D('Reporting')->getTradeLogB($start_month_time,$end_month_time);
        // $month_data =D('Reporting')->get_arr_diff($res,$trade['tranTypeIds']);

        //格式化查询的日期
        $date_format = $this->get_formate_date_arr();

        include template('reporting-betWinReport');
    }

    /**
     * 获取格式化查询日期
     * 2017-08-25
     */
    private function get_formate_date_arr () {
        $default_format_str = 'Y-m-d';
        $date_format = [];
        $date_format['today'] = date($default_format_str);
        $date_format['yesterday'] = date($default_format_str, strtotime('-1 day'));

        //这周
        $date_format['this_week_a'] = date($default_format_str, strtotime('this week'));
        $date_format['this_week_b'] = date($default_format_str, strtotime('this week +6 day'));

        //上周
        $date_format['last_week_a'] = date($default_format_str, strtotime('last week'));
        $date_format['last_week_b'] = date($default_format_str, strtotime('last week +6 day'));

        //这个月
        $date_format['this_month_a'] = date($default_format_str, strtotime('first day of this month'));
        $date_format['this_month_b'] = date($default_format_str, strtotime('last day of this month'));

        //上个月
        $date_format['last_month_a'] = date($default_format_str, strtotime('first day of last month'));
        $date_format['last_month_b'] = date($default_format_str, strtotime('last day of last month'));

        return $date_format;
    }

    /**
     * 输赢报表统计详情（按彩种分，旧版，不启用）
     * 2017-08-25
     */
    // public function betWinReportDetailOld () {
    //     $data = $_REQUEST;

    //     $start_date  = $data['start_date'] ? :date('Y-m-d');
    //     $end_date  = $data['end_date'] ? :date('Y-m-d');
    //     $lottery_type  = $data['lottery_type'] ? : 0;

    //     $where = " `date` >= '{$start_date}' AND  `date` <= '{$end_date}' and lottery_type = {$lottery_type} ";
    //     $sql = "SELECT lottery_name
    //                 /*,bet_ids*/
    //                 ,`date`,
    //                 SUM(bet_peoples) AS sum_bet_peoples,
    //                 SUM(bet_times) AS sum_bet_times ,
    //                 SUM(bet_moneys) AS sum_bet_moneys ,
    //                 SUM(bet_awards) AS sum_bet_awards

    //             FROM un_history_gamewins WHERE {$where}
    //             GROUP BY `date`";
    //     // dump($sql);
    //     $calculation_data = $this->db->getAll($sql);

    //     // $unique_ids_arr = array_filter(array_column($calculation_data, 'bet_ids'));
    //     // $unique_ids_arr = explode(',', implode(',', $unique_ids_arr));
    //     // $unique_ids_arr = array_unique($unique_ids_arr);

    //     foreach ($calculation_data as $k => &$each_cal_data) {
    //         //平台盈利
    //         $each_cal_data['profit_val'] = sprintf('%.2f', $each_cal_data['sum_bet_moneys'] - $each_cal_data['sum_bet_awards']);


    //         if ($each_cal_data['sum_bet_peoples'] == 0) {
    //             $each_cal_data['per_bet_val'] = 0;
    //             $each_cal_data['per_times_val'] = 0;

    //         } else {
    //             //人均投注额，保留2位小数
    //             $each_cal_data['per_bet_val'] = round($each_cal_data['sum_bet_moneys'] / $each_cal_data['sum_bet_peoples'], 2);

    //             //人均投注次数，保留2位小数
    //             $each_cal_data['per_times_val'] = round($each_cal_data['sum_bet_times'] / $each_cal_data['sum_bet_peoples'], 2);
    //         }
    //     }
    //     // dump($calculation_data);

    //     //汇总数据计算
    //     $all_sum_data = [
    //         'sum_bet_peoples' => array_sum(array_column($calculation_data, 'sum_bet_peoples')) ,
    //         'sum_bet_times' => array_sum(array_column($calculation_data, 'sum_bet_times')) ,
    //         'sum_bet_moneys' => array_sum(array_column($calculation_data, 'sum_bet_moneys')) ,
    //         'sum_bet_awards' => array_sum(array_column($calculation_data, 'sum_bet_awards')) ,
    //     ];

    //     //汇总平台盈利
    //     $all_sum_data['profit_val'] = $all_sum_data['sum_bet_moneys'] - $all_sum_data['sum_bet_awards'];

    //     //汇总计算人均投注
    //     if ($all_sum_data['sum_bet_peoples'] == 0) {
    //         $all_sum_data['per_bet_val'] = 0;
    //         $all_sum_data['per_times_val'] = 0;
    //     } else {
    //         //人均投注额，保留2位小数
    //         $all_sum_data['per_bet_val'] = round($all_sum_data['sum_bet_moneys'] / $all_sum_data['sum_bet_peoples'], 2);

    //         //人均投注次数，保留2位小数
    //         $all_sum_data['per_times_val'] = round($all_sum_data['sum_bet_times'] / $all_sum_data['sum_bet_peoples'], 2);
    //     }
    //     // dump($all_sum_data);
    //     //格式化查询的日期
    //     $date_format = $this->get_formate_date_arr();

    //     include template('reporting-betWinReportDetailOld');
    // }

    /**
     * 输赢报表统计详情（按彩种分）
     * 2017-08-25
     * 2017-08-30 update
     */
    public function betWinReportDetail () {


        $data = $_REQUEST;

        $start_date  = $data['start_date'] ? :date('Y-m-d');
        $end_date  = $data['end_date'] ? :date('Y-m-d');
        $lottery_type  = $data['lottery_type'] ? : 0;
        $lottery_name  = $data['lottery_name'] ? : '';
        $layer  = $data['layer'] ? : 1;
        $user_id  = $data['user_id'] ? : 0;


        $start_time = strtotime($start_date . '00:00:00');
        $end_time = strtotime($end_date . '23:59:59');


        // include S_CORE . 'class' . DS . 'pages.php';
        if (! $user_id) {

            //查一级代理及一级平台会员
            $sql = "SELECT COUNT(DISTINCT tree.user_id) AS lvOneCt FROM `un_user_tree` AS tree
                    LEFT JOIN `un_orders` AS orders 
                        ON tree.`user_id` = orders.`user_id`
                    WHERE tree.layer = {$layer}
                    AND orders.`addtime` >= {$start_time} AND orders.`addtime` <= {$end_time}
                    AND orders.lottery_type = {$lottery_type} AND orders.award_state > 0 AND orders.state = 0";

            //分页带上的查询条件
            $params = $data;
            unset($params['m'], $params['c'], $params['a']);

            $countInfo = $this->db->getOne($sql);
            $listCnt = $countInfo['lvOneCt'];
            $pagesize = 10;
            $url = '?m=admin&c=reporting&a=betWinReportDetail';
            $page = new pages($listCnt, $pagesize, $url, $params);
            $show = $page->show();

            $page_start = $page->offer;
            $page_size = $pagesize;

            $limit = "limit {$page_start},{$page_size}";

            //查一级代理及一级平台会员
            $sql = "SELECT tree.user_id  FROM `un_user_tree` AS tree
                    LEFT JOIN `un_orders` AS orders 
                        ON tree.`user_id` = orders.`user_id`
                    WHERE tree.layer = {$layer}
                    AND orders.`addtime` >= {$start_time} AND orders.`addtime` <= {$end_time}
                    AND orders.lottery_type = {$lottery_type} AND orders.award_state > 0 AND orders.state = 0
                    GROUP BY tree.user_id {$limit}";
            $calculation_users = $this->db->getAll($sql);
            // echo $sql;
            // dump($calculation_users);//exit;

        } else {

            //分页带上的查询条件
            $params = $data;
            unset($params['m'], $params['c'], $params['a']);

            $sql = "SELECT count(*) AS lvNextCt FROM `un_user` WHERE parent_id = {$user_id}";
            $countInfo = $this->db->getOne($sql);
            $listCnt = $countInfo['lvNextCt'];
            $pagesize = 10;
            $url = '?m=admin&c=reporting&a=betWinReportDetail';
            $page = new pages($listCnt, $pagesize, $url, $params);
            $show = $page->show();

            $page_start = $page->offer;
            $page_size = $pagesize;

            $limit = "limit {$page_start},{$page_size}";

            $sql = "SELECT id FROM `un_user` WHERE parent_id = {$user_id} {$limit}";
            $sons_data = $this->db->getAll($sql);
            $sons_arr = array_column($sons_data, 'id');

            //查询直属团队
            $calculation_users = [];

            //列表加上这个父级本身，如果后期不需要这个父级，则注释掉下一行即可
            $calculation_users[] = ['user_id' => $user_id];

            foreach ($sons_arr as $each_son) {
                $calculation_users[] = ['user_id' => $each_son];
            }

        }
        // dump($calculation_users);exit;

        //投注条件
        $bet_where = " lottery_type = {$lottery_type} AND award_state > 0 AND state = 0
                AND `addtime` >= {$start_time} AND `addtime` <= {$end_time} ";

        //中奖条件
        $award_where = " lottery_type = {$lottery_type} AND award_state = 2 AND state = 0
                AND `addtime` >= {$start_time} AND `addtime` <= {$end_time} ";

        foreach ($calculation_users as &$each_user) {

            //查询直属团队，包含自身
            $tmp_sons_data = $this->getSons($each_user['user_id']);
            if ($tmp_sons_data == '') {
                $tmp_team_string = $each_user['user_id'];
                $each_user['user_title'] = '平台会员';
                $each_user['has_link'] = '0';
            } else {
                $tmp_team_string = rtrim($each_user['user_id'] . ',' . $tmp_sons_data, ',');
                if (! $user_id) {
                    $layer_text = '1';
                } else {
                    $layer_text = (($each_user['user_id'] == $user_id) ? $layer : $layer + 1);
                }
                $each_user['user_title'] = '代理' . $layer_text;
                $each_user['has_link'] = '1';
            }
            $each_user['sons_data'] = $tmp_team_string;

            // //查询直属团队投注额 [可合并-a]
            // $tmp_sql = "SELECT SUM(`money`) AS sum_bet_moneys FROM un_orders WHERE {$bet_where} AND user_id IN ($tmp_team_string)";
            // $tmp_bet_data = $this->db->getone($tmp_sql);
            // $each_user['sum_bet_moneys'] = $tmp_bet_data['sum_bet_moneys'];
            // dump($tmp_sql);break;



            //////// 遇到有大团队的一级代理，这里的foreach处理的速度会有所减缓 ////////

            //查询直属团队中奖额
            $tmp_sql = "SELECT SUM(`award`) AS sum_bet_awards FROM un_orders WHERE {$award_where} AND user_id IN ($tmp_team_string)";

            // dump($tmp_sql);

            $tmp_award_data = $this->db->getone($tmp_sql);
            $each_user['sum_bet_awards'] = $tmp_award_data['sum_bet_awards'] ? : 0;

            //查询直属团队投注人数、投注次数、直属团队投注额
            $tmp_sql = "SELECT
                            COUNT(DISTINCT user_id) AS sum_bet_peoples,
                            COUNT(id) AS sum_bet_times,
                            SUM(`money`) AS sum_bet_moneys
                        FROM un_orders WHERE {$bet_where} AND user_id IN ($tmp_team_string)";
            $tmp_count_data = $this->db->getone($tmp_sql);
            $each_user['sum_bet_peoples'] = $tmp_count_data['sum_bet_peoples'];
            $each_user['sum_bet_times'] = $tmp_count_data['sum_bet_times'];
            $each_user['sum_bet_moneys'] = $tmp_count_data['sum_bet_moneys'];

            //平台盈利
            $each_user['profit_val'] = sprintf('%.2f', $each_user['sum_bet_moneys'] - $each_user['sum_bet_awards']);

            //人均投注额以及人均投注次数
            if ($each_user['sum_bet_peoples'] == 0) {
                $each_user['per_bet_val'] = 0;
                $each_user['per_times_val'] = 0;

            } else {
                //人均投注额，保留2位小数
                $each_user['per_bet_val'] = sprintf('%.2f', $each_user['sum_bet_moneys'] / $each_user['sum_bet_peoples']);

                //人均投注次数，保留2位小数
                $each_user['per_times_val'] = sprintf('%.2f', $each_user['sum_bet_times'] / $each_user['sum_bet_peoples']);
            }

            $tmp_sql = "SELECT username FROM un_user WHERE id = '{$each_user['user_id']}'";
            $tmp_user_data = $this->db->getone($tmp_sql);
            $each_user['username'] = $tmp_user_data['username'];

        }

        //格式化查询的日期
        $date_format = $this->get_formate_date_arr();

        include template('reporting-betWinReportDetail');

    }

    /**
     * 获取所有直属下线方法
     * 2017-08-30 update
     * @param number $pid 父级ID
     * @param string $type 返回的数据类型
     */
    public function getSons ($pid, $type = 'string') {
        $sql = "SELECT id FROM `un_user` WHERE parent_id = {$pid}";
        $sons_data = O('model')->db->getAll($sql);

        $new_sons_arr = array_column($sons_data, 'id');

        //分类型返回数据
        if ($type == 'string') {
            return implode(',', $new_sons_arr);
        }
        return $new_sons_arr;
    }
    /**
     * 总报表
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function index(){
        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);
        if(isset($_REQUEST['time_flag'])){
            switch ($_REQUEST['time_flag']){
                case 1;
                    $start_date = date("Y-m-d");
                    $end_date = date("Y-m-d");
                    break;
                case 2;
                    $start_date = date("Y-m-d",mktime(0, 0 , 0, date("m"),date("d")-date("w")+1,date("Y")));
                    $end_date = date("Y-m-d");

                    break;
                case 3;
                    $start_date = date("Y-m-d",mktime(0, 0 , 0,date("m"),1,date("Y")));
                    $end_date = date("Y-m-d");
                    break;
                case 4;
                    $start_date = date("Y-m-d",mktime(0, 0 , 0,date("m")-1,1,date("Y")));
                    $end_date = date("Y-m-d",mktime(23,59,59,date("m") ,0,date("Y")));
                    break;
            }
        }
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
//        $sql3 = "SELECT COUNT(DISTINCT user_id) AS num, SUM(money) AS money FROM un_account_log AS l WHERE" . $where . " AND l.type = 10 AND `remark` LIKE '%该用户为首次充值%' AND reg_type NOT IN (0,8,9,11)";

        $s = "select user_id,money from un_account_recharge where $where and status = 1 GROUP BY user_id";
        $sql3 = "SELECT count(*) as num,sum(money) as money from ($s) infos";
        $recharge = O('model')->db->getOne($sql3);

        //首提现人数 首提总额
        //$sql4 = "SELECT COUNT(*) AS num, SUM(total_money) as money FROM (SELECT nums as n, total_money FROM (SELECT COUNT(l.user_id) AS nums, SUM(money) AS total_money FROM un_account_log AS l LEFT JOIN un_user AS u ON u.id = l.user_id WHERE" . $where . " AND l.type = 11 AND u.reg_type NOT IN (0,8,9,11) GROUP BY l.user_id) AS A WHERE nums = 1) as N";
//        $sql4 = "SELECT COUNT(DISTINCT user_id) AS num, SUM(money) AS money FROM un_account_log AS l WHERE" . $where . " AND l.type = 11 AND `remark` LIKE '%该用户为首次提现%' AND reg_type NOT IN (0,8,9,11)";
        $s = "select user_id,money from un_account_cash where $where and status = 1 GROUP BY user_id";
        $sql4 = "SELECT count(*) as num,sum(money) as money from ($s) infos";;
        $cash = O('model')->db->getOne($sql4);

        //交易类型
        $trade = $this->model2->getTrade();

        //交易流水
        $trades = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds']);
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
        $sys = $this->getSys();
        $sysSet = empty($sys)?'1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,25,26,27,28,29,30,31,32':$sys['value'];
        $sysSet = explode(',',$sysSet);
        include template('reporting-index');
    }


    /**
     * 会员报表详情
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function getmemberDetail($id,$start_date,$end_date){
        //交易类型
        $trade = $this->model2->getTrade();
        //直属 会员
        $sql = "SELECT id FROM un_user WHERE parent_id={$id}";
        $directlyIds = O('model')->db->getAll($sql);
//        $directlyIds = array();//直属会员id
//        foreach ($c_user as $v){
//            $directlyIds[] = $v['uid'];
//        }
        //直属会员交易记录
//        $SdirectlyIds = empty($directlyIds)?0:implode($directlyIds,',');
//        $directlyTradeType = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds'],$SdirectlyIds);

        //团队会员
        $res = $this->model2->teamLists($id);
        $teamIds = array();//团队会员id
        foreach ($res as $v){
            $teamIds[] = $v['id']; //团队人数
        }

        //团队交易记录
        $STeamIds = implode($teamIds,',');
        $teamTradeType = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds'],$STeamIds);

        //自身交易记录 orders表
        $tradeType = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds'],$id);


        //初始化redis
        $redis = initCacheRedis();
        $backwater = $redis -> hMGet("Config:100012",array('value'));
        //关闭redis链接
        deinitCacheRedis($redis);

        //自身信息
        $sql3 = "SELECT u.id, u.username, u.nickname, u.weixin, u.logintime, a.money FROM un_user AS u LEFT JOIN un_account AS a ON u.id = a.user_id WHERE u.id = {$id}";
        $self = O('model')->db->getOne($sql3);
        $data = array();
        $data['uid'] = $id;
        $data['username'] = $self['username'];
        $data['offline'] = ceil((SYS_TIME-$self['logintime'])/86400);//无活跃天数
        $data['money'] = $self['money'];//账户
        $data['weixin'] = $self['weixin'];//微信
        $data['recharge'] = $tradeType['10']; //充值
        $data['cash'] = $tradeType['11']; //提现
        $data['betting'] = $tradeType['13'] - $tradeType['14']; //投注
        $data['totalBackwater'] = $tradeType['19'] + $tradeType['20'] + $tradeType['21']; //返水
        $data['directly'] = count($directlyIds); //直属会员人数
        $data['team_Betting'] = $teamTradeType['13'] - $teamTradeType['14']; //团队会员投注
        $data['profit_team'] = ($teamTradeType['12'] + $teamTradeType['14'] + $teamTradeType['19'] + $teamTradeType['20'] + $teamTradeType['21'] + $teamTradeType['18'] + $teamTradeType['32'] + $teamTradeType['66'] + $teamTradeType['1000'] + $teamTradeType['999'] + $teamTradeType['998'] + $teamTradeType['997'] + $teamTradeType['995'] + $teamTradeType['994'] + $teamTradeType['993'] + $teamTradeType['992']) - $teamTradeType['13'] - $teamTradeType['120']; //团队盈亏 (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+九宫格995+平台任务994+福袋993+刮刮乐992)-投注-回滚
        $data['profit_Betting'] = ($tradeType['12'] + $tradeType['14']) - $tradeType['13']- $tradeType['120'];//投注盈亏: (中奖+撤单)-投注-回滚
        return $data;
    }

    /**
     * 会员报表详情-优化后
     * @method POST
     * @param token string
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     */
    public function memberDetailNew(){
        $list = array();
        $total = array();
        $where = "";
        $page_size = 10;
        //账户
        $query = $_REQUEST; //搜索条件
        unset($query['m']);
        unset($query['c']);
        unset($query['a']);
        $query['start_time'] = empty($query['start_time']) ? date('Y-m-d') : $_REQUEST['start_time'];
        $query['end_time']   = empty($query['end_time']) ? date('Y-m-d') : $_REQUEST['end_time'];
        
        $page = empty(trim($_REQUEST['page'])) ? 0: trim($_REQUEST['page']);
        $page_start = $page * $page_size;
        
        $username = trim($_REQUEST['username']);
        if(!empty($username)){
            $where = " AND username = '".$username."'";
        }

        //微信
        $weixin = trim($_REQUEST['weixin']);
        if(!empty($weixin)){
            $where = $where." AND u.weixin LIKE '%".$weixin."%'";
        }

        //真实姓名
        $realname = trim($_REQUEST['realname']);
        if(!empty($realname)){
            $where = $where." AND u.realname LIKE '%".$realname."%'";
        }

        //手机号
        $mobile = trim($_REQUEST['mobile']);
        if(!empty($mobile)){
            $where = $where." AND u.mobile LIKE '%".$mobile."%'";
        }

        //银行卡号
        $account = trim($_REQUEST['account']);
        if(!empty($account)){
            $where = $where." AND b.account LIKE '%".$account."%'";
        }

        //状态
        $state = trim($_REQUEST['state']);

        if($state!==''){
            $where = $where." AND u.state = ".$state;
        }

        //分组
        $group = trim($_REQUEST['group']);
        if(!empty($group)){
            $where = $where." AND u.group_id = ".$group;
        }

        //代理等级
        $user_type = trim($_REQUEST['user_type']);
        if(!empty($user_type)){
            $where = $where." AND u.user_type = ".$user_type;
        }

        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);
        if(!empty($start_date) && !empty($end_date)){
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date." 23:59:59");
            //$where = empty($where)?" AND u.regtime BETWEEN {$start_time} and {$end_time}":$where;
        }else{
            $start_date = date("Y-m-d");
            $end_date = date("Y-m-d");
            $start_time = strtotime(date("Y-m-d"));
            $end_time   = strtotime(date("Y-m-d") . ' 23:59:59');
        }
        
        if (empty($where)) {
            $sql = "SELECT DISTINCT(u.id) AS uid FROM un_account_log ual LEFT JOIN un_user AS u ON ual.user_id = u.id WHERE u.reg_type NOT IN (0,8,9,11) AND ual.addtime BETWEEN {$start_time} AND {$end_time} ORDER BY u.id LIMIT {$page_start},{$page_size}";
            //$sql = "SELECT ual.user_id as uid FROM un_account_log ual LEFT JOIN un_user AS u ON ual.id = u.id WHERE ual.addtime BETWEEN {$start_time} AND {$end_time} GROUP BY ual.user_id ORDER BY ual.user_id LIMIT {$page_start},{$page_size}";
            $arr_user_id = O('model')->db->getAll($sql);
            //var_dump($sql);
            //var_dump($arr_user_id);
        }else {
            //$sql = "SELECT u.id AS uid FROM un_user AS u LEFT JOIN un_user_bank AS b ON u.id = b.user_id
            //        WHERE u.reg_type NOT IN (0,8,9,11) {$where} AND u.regtime BETWEEN {$start_time} and {$end_time} LIMIT {$page_start},{$page_size}";
            $sql = "SELECT DISTINCT(u.id) uid FROM un_account_log ual
            LEFT JOIN un_user u ON ual.user_id = u.id
            LEFT JOIN un_user_bank b ON u.id = b.user_id
            WHERE u.reg_type NOT IN (0,8,9,11) {$where} AND ual.addtime BETWEEN {$start_time} AND {$end_time} ORDER BY u.id LIMIT {$page_start},{$page_size}";
            $arr_user_id = O('model')->db->getAll($sql);
            //var_dump($sql);
            //var_dump($arr_user_id);
        }
        
        if(!empty($arr_user_id)){
            $arr_uid = array_column($arr_user_id, 'uid');
            $uids = array();
            foreach ($arr_uid as $uid){
                
                //if(in_array($v['uid'],$uids)){
                //    continue;
                //}
                $uids[] = $uid;
                $resData  = $this->getMemberDetails($uid,$start_date,$end_date);
                
                $total['offline'] += $resData['offline'];//无活跃天数
                $total['directly'] += $resData['directly'];//直属会员人数
                $total['money'] += $resData['money']; //账户
                $total['recharge'] += $resData['recharge']; //充值
                $total['cash'] += $resData['cash']; //提现
                $total['team_Betting'] += $resData['team_Betting']; //投注
                $total['totalBackwater'] += $resData['totalBackwater']; //返水
                $total['profit_Betting'] += $resData['profit_Betting']; //投注盈亏
                $total['profit_team'] += $resData['profit_team'];//团队盈亏
                $list[] = $resData;
            }
            
            foreach ($total as $kt => $vt) {
                $total[$kt] = round($vt, 2);
            }
        }

        $group = $this->model2->getGroup();
        //初始化redis
        $redis = initCacheRedis();
        //代理
        $agent = $redis->lRange('agentIds', 0, -1);
        //关闭redis链接
        deinitCacheRedis($redis);
        
        $show_user_info = $this->admin['show_user_info'];

        include template('reporting-memberDetail');
    }


    public function memberDetail() {
        $query = $_REQUEST; //搜索条件
        $list = array();
        $total = array();
        $where = " 1=1";
        $page_size = 10;

        unset($query['m']);
        unset($query['c']);
        unset($query['a']);

        $quick = getParame('quick',0,0);
        $time = $this->getSearchTime($quick);
        $start_time_int = $time[0];
        $end_time_int = $time[1];
        $start_date = date('Y-m-d',$start_time_int);
        $end_date = date('Y-m-d',$end_time_int);

        $where .= " and ual.addtime >= $start_time_int and ual.addtime <= $end_time_int";

        $page = empty(trim($_REQUEST['page'])) ? 1: trim($_REQUEST['page']);
        $page_start = ($page - 1) * $page_size;

        $rg_type = getParame('rg_type',0,0,'int');
        if($rg_type == 0) {
            $where .= " and uu.reg_type NOT IN (0, 8, 9, 11) ";
        }else{
            $where .= ' and uu.reg_type = '.$rg_type;
        }

        //账户
        $username = trim($_REQUEST['username']);
        if(!empty($username)) $where .= " AND uu.username = '".$username."'";

        //微信
        $weixin = trim($_REQUEST['weixin']);
        if(!empty($weixin)) $where = $where." AND uu.weixin LIKE '%".$weixin."%'";

        //真实姓名
        $realname = trim($_REQUEST['realname']);
        if(!empty($realname)) $where = $where." AND uu.realname LIKE '%".$realname."%'";

        //手机号
        $mobile = trim($_REQUEST['mobile']);
        if(!empty($mobile)) $where = $where." AND uu.mobile LIKE '%".$mobile."%'";

        //银行卡号
        $account = trim($_REQUEST['account']);
        if(!empty($account)) $where = $where." AND ub.account LIKE '%".$account."%'";

        //状态
        $state = trim($_REQUEST['state']);
        if($state!=='') $where = $where." AND uu.state = ".$state;

        //分组
        $group = trim($_REQUEST['group']);
        if(!empty($group)) $where = $where." AND uu.group_id = ".$group;

        //代理等级
        $user_type = trim($_REQUEST['user_type']);
        if(!empty($user_type)) $where = $where." AND uu.user_type = ".$user_type;

        $fsType = "19,20,21";           //反水
        $ylType = "12,14,19,20,21,18,32,66,1000,999,998,997,995,994,993,992";       //盈利
        $fields = "uu.id uid,uu.username,uu.logintime,us.sessionid,ua.money,";
        $fields .= "SUM(IF(ual.type=10,ual.money,0)) as recharge,";
        $fields .= "SUM(IF(ual.type=11,ual.money,0)) as cash,";
        $fields .= "SUM(IF(ual.type=10,ual.money,IF(ual.type=11,-ual.money,0))) as profit_recharge,";
        $fields .= "SUM(IF(ual.type=13,ual.money,IF(ual.type=14,-ual.money,0))) as betting,";
        $fields .= "SUM(IF(ual.type in ({$fsType}),ual.money,0)) as totalBackwater,";
        $fields .= "SUM(IF(ual.type in ({$ylType}),ual.money,IF(ual.type in (13,120), -ual.money, 0))) as profit_total,";
        $fields .= "SUM(IF(ual.type in (12,14),ual.money,IF(ual.type in (13,120), -ual.money, 0))) as profit_Betting";
        $sql = "select $fields FROM un_user uu LEFT JOIN un_account ua ON uu.id = ua.user_id LEFT JOIN un_account_log ual ON uu.id = ual.user_id  LEFT JOIN un_session us ON uu.id = us.user_id AND us.is_admin = 0 ";

        if($account) $sql .= " LEFT JOIN un_user_bank ub ON uu.id = ub.user_id ";

        $sql .= " where $where GROUP BY uu.id ";

        $sort = getParame('sort',0);
        switch ($sort) {
            case 1:
                $sql .= " ORDER BY us.sessionid DESC,uu.logintime DESC";
                break;
            case 2:
                $sql .= " ORDER BY uu.regtime DESC";
                break;
            case 3:
                $sql .= " ORDER BY profit_total DESC";
                break;
            default:
                break;
        }

        $sql .= " limit $page_start,$page_size";
        $list =  O('model')->db->getAll($sql);
        foreach($list as &$val) {
            $val['offline'] = ceil((SYS_TIME-$val['logintime'])/86400);//无活跃天数

            $sql = "SELECT count(*) as c FROM un_user WHERE parent_id={$val['uid']}";
            $val['directly'] = $this->db->result($sql);

            $total['offline'] += $val['offline'];
            $total['directly'] += $val['directly'];
            $total['money'] += $val['money'];
            $total['recharge'] += $val['recharge'];
            $total['cash'] += $val['cash'];
            $total['profit_recharge'] += $val['profit_recharge'];
            $total['betting'] += $val['betting'];
            $total['totalBackwater'] += $val['totalBackwater'];
            $total['profit_Betting'] += $val['profit_Betting'];
            $total['profit_total'] += $val['profit_total'];
            unset($val);
        }

        //会员组
        $group = $this->model2->getGroup();
        $show_user_info = $this->admin['show_user_info'];  //管理员有没有权限查看用户敏感信息 1，有;0，没有

        include template('reporting-memberDetail');
    }

    /**
     * 会员报表详情-优化前
     * @method POST
     * @param token string
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     */
    public function memberDetailbak()
    {
        $query = $_REQUEST; //搜索条件
        $list = array();
        $total = array();
        $where = "";
        $page_size = 10;

        unset($query['m']);
        unset($query['c']);
        unset($query['a']);
        $query['start_time'] = empty($query['start_time']) ? date('Y-m-d') : $_REQUEST['start_time'];
        $query['end_time']   = empty($query['end_time']) ? date('Y-m-d') : $_REQUEST['end_time'];

        $quick = $query['quick'];
        if($quick!="0"&&$quick!=""){
            switch ($quick){
                case 1:
                    $query['start_time'] = strtotime(date("Y-m-d",strtotime("0 day")));
                    $query['end_time'] = $query['start_time'] + 86399;
                    break;
                case 2:
                    $query['start_time'] = strtotime(date("Y-m-d",strtotime("-1 day")));
                    $query['end_time'] = $query['start_time'] + 86399;
                    break;
                case 3:
                    $query['start_time'] = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));
                    $query['end_time'] = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;
                    break;
                case 4:
                    $query['start_time'] = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                    $query['end_time'] = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;
                    break;
                case 5:
                    $query['start_time'] = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));
                    $query['end_time'] = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));
                    break;
            }
            $query['start_time'] = empty($query['start_time']) ? date('Y-m-d') : date('Y-m-d',$query['start_time']);
            $query['end_time'] = empty($query['end_time']) ? date('Y-m-d') : date('Y-m-d',$query['end_time']);
        }

        $page = empty(trim($_REQUEST['page'])) ? 1: trim($_REQUEST['page']);
        $page_start = ($page - 1) * $page_size;
        
        //账户
        $username = trim($_REQUEST['username']);
        if(!empty($username)){
            $where = " AND username = '".$username."'";
        }

        //微信
        $weixin = trim($_REQUEST['weixin']);
        if(!empty($weixin)){
            $where = $where." AND u.weixin LIKE '%".$weixin."%'";
        }

        //真实姓名
        $realname = trim($_REQUEST['realname']);
        if(!empty($realname)){
            $where = $where." AND u.realname LIKE '%".$realname."%'";
        }

        //手机号
        $mobile = trim($_REQUEST['mobile']);
        if(!empty($mobile)){
            $where = $where." AND u.mobile LIKE '%".$mobile."%'";
        }

        //银行卡号
        $account = trim($_REQUEST['account']);
        if(!empty($account)){
            $where = $where." AND b.account LIKE '%".$account."%'";
        }

        //状态
        $state = trim($_REQUEST['state']);

        if($state!==''){
            $where = $where." AND u.state = ".$state;
        }

        //分组
        $group = trim($_REQUEST['group']);
        if(!empty($group)){
            $where = $where." AND u.group_id = ".$group;
        }

        //代理等级
        $user_type = trim($_REQUEST['user_type']);
        if(!empty($user_type)){
            $where = $where." AND u.user_type = ".$user_type;
        }

        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);
        if(!empty($start_date) && !empty($end_date)){
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date." 23:59:59");
        }else{
            $start_date = date("Y-m-d");
            $end_date   = date("Y-m-d");
            $start_time = strtotime(date("Y-m-d"));
            $end_time   = strtotime(date("Y-m-d") . ' 23:59:59');
        }
        if($quick!="0"&&$quick!=""){
            switch ($quick){
                case 1:
                    $start_time = strtotime(date("Y-m-d",strtotime("0 day")));
                    $end_time = $start_time + 86399;
                    break;
                case 2:
                    $start_time = strtotime(date("Y-m-d",strtotime("-1 day")));
                    $end_time = $start_time + 86399;
                    break;
                case 3:
                    $start_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));
                    $end_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;
                    break;
                case 4:
                    $start_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;
                    break;
                case 5:
                    $start_time = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));
                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));
                    break;
            }
            $start_date = date("Y-m-d",$start_time);
            $end_date   = date("Y-m-d",$end_time);
        }
        if (empty($where)) {
            $sql = "SELECT DISTINCT(u.id) AS uid FROM un_account_log ual LEFT JOIN un_user AS u ON ual.user_id = u.id WHERE u.reg_type NOT IN (0,8,9,11) AND ual.addtime BETWEEN {$start_time} AND {$end_time} ORDER BY u.id LIMIT {$page_start},{$page_size}";
            $arr_user_id = O('model')->db->getAll($sql);
        }else {
            $sql = "SELECT DISTINCT(u.id) uid FROM un_account_log ual
            LEFT JOIN un_user u ON ual.user_id = u.id
            LEFT JOIN un_user_bank b ON u.id = b.user_id
            WHERE u.reg_type NOT IN (0,8,9,11) {$where} AND ual.addtime BETWEEN {$start_time} AND {$end_time} ORDER BY u.id LIMIT {$page_start},{$page_size}";
            $arr_user_id = O('model')->db->getAll($sql);
        }


        if(!empty($arr_user_id)){
            $arr_uid = array_column($arr_user_id, 'uid');
            $uids = array();

            foreach ($arr_uid as $uid){
                $uids[] = $uid;
                $resData  = $this->getMemberDetails($uid,$start_date,$end_date);
                $total['offline'] += $resData['offline'];//无活跃天数
                $total['directly'] += $resData['directly'];//直属会员人数
                $total['money'] += $resData['money']; //账户
                $total['recharge'] += $resData['recharge']; //充值
                $total['cash'] += $resData['cash']; //提现
                $total['profit_recharge'] += $resData['profit_recharge']; //充值盈亏
                $total['betting'] += $resData['betting']; //投注
                //$total['team_Betting'] += $resData['team_Betting']; //投注
                $total['totalBackwater'] += $resData['totalBackwater']; //返水
                $total['profit_Betting'] += $resData['profit_Betting']; //投注盈亏
                $total['profit_total'] += $resData['profit_total'];//个人盈亏
                //$total['profit_team'] += $resData['profit_team'];//团队盈亏
                $list[] = $resData;
            }
        }
        //会员组
        $group = $this->model2->getGroup();
        //初始化redis
        /*
        $redis = initCacheRedis();
        //代理
        $agent = $redis->lRange('agentIds', 0, -1);
        //关闭redis链接
        deinitCacheRedis($redis);
        */

        $show_user_info = $this->admin['show_user_info'];  //管理员有没有权限查看用户敏感信息 1，有;0，没有

        include template('reporting-memberDetail');

    }
    
    /**
     * 会员报表详情
     * @method POST
     * @param token string
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     */
    public function cntMemberDetail()
    {
        $list = array();
        $total = array();
        $query = "";
        
        $where = $_REQUEST; //搜索条件
        unset($where['m']);
        unset($where['c']);
        unset($where['a']);
        $where['start_time'] = $_REQUEST['start_time'];
        $where['end_time']   = $_REQUEST['end_time'];
        $pagesize = 10;
        $where['page_size'] = $pagesize;
        $where['page'] = empty($where['page']) ? 1 : $where['page'];
        $where['page_start'] = $where['page_size'] * ($where['page'] - 1);

        //账户
        $username = trim($_REQUEST['username']);
        if(!empty($username)){
            $query = " AND u.username = '".$username."'";
        }
    
        //微信
        $weixin = trim($_REQUEST['weixin']);
        if(!empty($weixin)){
            $query = $query . " AND u.weixin LIKE '%".$weixin."%'";
        }

        $rg_type = getParame('rg_type',0,0,'int');
        if($rg_type == 0) {
            $query .= " and u.reg_type NOT IN (0, 8, 9, 11) ";
        }else{
            $query .= ' and u.reg_type = '.$rg_type;
        }
    
        //真实姓名
        $realname = trim($_REQUEST['realname']);
        if(!empty($realname)){
            $query = $query . " AND u.realname LIKE '%".$realname."%'";
        }
    
        //手机号
        $mobile = trim($_REQUEST['mobile']);
        if(!empty($mobile)){
            $query = $query . " AND u.mobile LIKE '%".$mobile."%'";
        }
    
        //银行卡号
        $account = trim($_REQUEST['account']);
        if(!empty($account)){
            $query = $query . " AND b.account LIKE '%".$account."%'";
        }
    
        //状态
        $state = trim($_REQUEST['state']);
    
        if($state!==''){
            $query = $query . " AND u.state = ".$state;
        }
    
        //分组
        $group = trim($_REQUEST['group']);
        if(!empty($group)){
            $query = $query . " AND u.group_id = ".$group;
        }
    
        //代理等级
        $user_type = trim($_REQUEST['user_type']);
        if(!empty($user_type)){
            $query = $query . " AND u.user_type = ".$user_type;
        }
    
        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);
        if(!empty($start_date) && !empty($end_date)){
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date." 23:59:59");
            //$where_time = " AND u.regtime BETWEEN {$start_time} and {$end_time}";
        }else{
            $start_date = date("Y-m-d");
            $end_date   = date("Y-m-d");
            $start_time = strtotime(date("Y-m-d"));
            $end_time   = strtotime(date("Y-m-d") . ' 23:59:59');
        }
        
        if (empty($query)) {
            $query = ' AND 1 = 1 ';
            $sql = "SELECT count(DISTINCT(u.id)) AS count FROM un_account_log ual LEFT JOIN un_user AS u ON ual.user_id = u.id WHERE u.reg_type NOT IN (0,8,9,11) AND ual.addtime BETWEEN {$start_time} AND {$end_time}";
            $count = O('model')->db->getone($sql);
        }else {
            $sql = "SELECT count(DISTINCT(u.id)) AS count FROM un_account_log ual
            LEFT JOIN un_user u ON ual.user_id = u.id
            LEFT JOIN un_user_bank b ON u.id = b.user_id AND b.state = 1
            WHERE 1=1 {$query} AND ual.addtime BETWEEN {$start_time} AND {$end_time}";
            $count = O('model')->db->getone($sql);
        }

        $url = '?m=admin&c=reporting&a=memberDetail';
        $page = new page($count['count'], $where['page_size'], $url, $where);
        $show = $page->show();
        if(!empty($count['count'])){
            $resData  = $this->getCntTradeLog($start_date,$end_date, $query);
            $data['recharge_total'] = round($resData['10'], 2); //充值
            $data['cash_total'] = round($resData['11'], 2); //提现
            $data['profit_recharge'] = round($resData['10'] - $resData['11'], 2); //充值盈亏
            $data['betting_total'] = round($resData['13'] - $resData['14'], 2); //投注
            $data['totalBackwater'] = round($resData['19'] + $resData['20'] + $resData['21'], 2); //返水
            $data['profit_total'] = $resData['12'] + $resData['14'] + $resData['19'] + $resData['20'] + $resData['21'] + $resData['18'] + $resData['32'];
            $data['profit_total'] += (($resData['66'] + $resData['1000'] + $resData['999'] + $resData['998'] + $resData['997'] + $resData['995'] + $resData['994'] + $resData['993'] + $resData['992']) - $resData['13'] - $resData['120']); //团队盈亏 (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+九宫格995+平台任务994+福袋993+刮刮乐992)-投注-回滚
            $data['profit_Betting_total'] = round(($resData['12'] + $resData['14']) - $resData['13']- $resData['120'], 2);//投注盈亏: (中奖+撤单)-投注-回滚
            $data['profit_total'] = round($data['profit_total'], 2);
        }

        $totalPage = '<span style="float: right;margin-left: 200px;' . Session::get('style') . '">合计：';
        $totalPage .= '总充值：<b>' . (empty($data['recharge_total']) ? 0 : $data['recharge_total']) . '</b>';
        $totalPage .= '总提现：<b>' . (empty($data['cash_total']) ? 0 : $data['cash_total']) . '</b>';
        $totalPage .= '总充值盈亏：<b>' . (empty($data['profit_recharge']) ? 0 : $data['profit_recharge']) . '</b>';
        $totalPage .= '总返水：<b>' . (empty($data['totalBackwater']) ? 0 : $data['totalBackwater']) . '</b>';
        $totalPage .= '总投注：<b>' . (empty($data['betting_total']) ? 0 : $data['betting_total']) . '</b>';
        $totalPage .= '投注盈亏：<b>' . (empty($data['profit_Betting_total']) ? 0 : $data['profit_Betting_total']) . '</b>';
        $totalPage .= '总盈亏：';
        if ($data['profit_total'] == 0) {
            $totalPage .= '0';
        }else {
            if ($data['profit_total'] > 0) {
                $totalPage .= '<font style="color: #0099ff;"><b>' . $data['profit_total'] . '</b></font>';
            }else {
                $totalPage .= '<font style="color: #FF3300;"><b>' . $data['profit_total'] . '</b></font>';
            }
            $totalPage .= '</span>';
        }

        echo json_encode(['code' => 0, 'msg' => '', 'data' => ['listPage' => $totalPage, 'show' => $show]]);
        return;
    }
    


    /**
     * 流水报表
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function ledger(){

        //账户名
        $account_name = trim($_REQUEST['account_name']);
        if(!empty($account_name)){
            $where = " AND pc.config LIKE '%".$account_name."%'";
        }else{
            $where = "";
        }

        //银行卡号
        $account = trim($_REQUEST['account']);
        if(!empty($account)){
            $where = empty($where)?" AND pc.config LIKE '%".$account."%'":" AND pc.config LIKE '%".$account_name."%".$account."%'";
        }

        //流水号
        $order = trim($_REQUEST['order_num']);
        if(!empty($order)){
            $where = empty($where)?" AND l.order_num LIKE '%".$order."%'":$where." AND l.order_num LIKE '%".$order."%'";
        }

        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);
        $quick = $_REQUEST['quick'];
        if($quick!="0"&&$quick!=""){
            switch ($quick){
                case 1:
                    $start_date = strtotime(date("Y-m-d",strtotime("0 day")));
                    $end_date = $start_date + 86399;
                    break;
                case 2:
                    $start_date = strtotime(date("Y-m-d",strtotime("-1 day")));
                    $end_date = $start_date + 86399;
                    break;
                case 3:
                    $start_date = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));
                    $end_date = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;
                    break;
                case 4:
                    $start_date = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                    $end_date = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;
                    break;
                case 5:
                    $start_date = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));
                    $end_date = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));
                    break;
            }
            $start_date = empty($start_date) ? date('Y-m-d') : date('Y-m-d',$start_date);
            $end_date = empty($end_date) ? date('Y-m-d') : date('Y-m-d',$end_date);
        }
        if(!empty($start_date) && !empty($end_date)){
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date." 23:59:59");
            $where = empty($where)?" AND l.addtime BETWEEN {$start_time} and {$end_time}":$where." AND l.addtime BETWEEN {$start_time} and {$end_time}";
        }elseif(!empty($start_date)){
            $start_time = strtotime($start_date);
            $end_time = "";
            $where = empty($where)?" AND l.addtime >= {$start_time}":$where." AND l.addtime >= {$start_time}";
        }elseif(!empty($end_date)){
            $start_time = "";
            $end_time = strtotime($end_date);
            $where = empty($where)?" AND l.addtime <= {$end_time}":$where." AND l.addtime <= {$end_time}";
        }
        $cres = array();
        $rres = array();
        if(!empty($where)){
            $sql = "SELECT l.id,l.order_num,l.type,l.money,l.admin_money,l.addtime,pc.config,pc.balance FROM un_account_log AS l LEFT JOIN un_account_cash AS c ON  c.order_sn = l.order_num LEFT JOIN un_payment_config AS pc ON pc.id = c.payment_id WHERE l.type = 11".$where;
            $cres = O('model')->db->getAll($sql);
            $sql = "SELECT l.id,l.order_num,l.type,l.money,l.admin_money,l.addtime,pc.config,pc.balance FROM un_account_log AS l LEFT JOIN un_account_recharge AS r ON  r.order_sn = l.order_num LEFT JOIN un_payment_config AS pc ON pc.id = r.payment_id WHERE l.type = 10".$where;
            $rres = O('model')->db->getAll($sql);
        }

        $cmoney = "0.00";
        $clist = array();
        if(!empty($cres)){
            foreach ($cres as $v){
                $cmoney += $v['money'];
                $config = unserialize($v['config']);
                $v['account_name'] = $config['account_name'];
                $v['account'] = $config['account'];
                unset($v['config']);
                $clist[] = $v;
            }
        }

        $rmoney = "0.00";
        $rlist = array();
        if(!empty($rres)){
            foreach ($rres as $v){
                $rmoney += $v['money'];
                $config = unserialize($v['config']);
                $v['account_name'] = $config['account_name'];
                $v['account'] = $config['account'];
                unset($v['config']);
                $rlist[] = $v;
            }
        }

        include template('reporting-ledger');
    }



    /**
     * 延时团队报表
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function delayGroupDetail()
    {
        $m="admin";
        $c="reporting";
        $a="delayGroupDetail";
        $pagesize = 10;
        $show_user_info = $this->admin['show_user_info'];  //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $threeTime = SYS_TIME - (86400*3); //3小时内登录的，计算活跃人数，新注册用户
        $list = array();
        $total = array();
        $where = " ";
        $listTotal = [];  //盈亏排序使用
        $listTeamBetting = []; //团队投注排序使用

        //排序
        $flag     = $_REQUEST['flag'];
        $yk_flag  = $_REQUEST['yk_flag'];
        if (empty($yk_flag)) {
            $yk_flag = 0;
        }
        $bet_flag = $_REQUEST['bet_flag'];
        if (empty($bet_flag)) {
            $bet_flag = 0;
        }

        if (empty($flag) || !is_numeric($flag) || $flag > 8 || $flag < 0) {
            $flag = 0;
        }

        $username = trim($_REQUEST['username']);
        if(!empty($username)){
            $where .= " AND u.username = '".$username."'";
        }

        //微信
        $weixin = trim($_REQUEST['weixin']);
        if(!empty($weixin)){
            $where .= " AND u.weixin LIKE '%".$weixin."%'";
        }

        //分组
        $group = trim($_REQUEST['group']);
        if(!empty($group)){
            $where = $where." AND u.group_id = ".$group;
        }

//        dump($where);

        //状态
        $online = trim($_REQUEST['online']);
        if(!empty($online)){
            if($online == 1 ){
                $where = $where." AND s.sessionid IS NOT NULL";
            }elseif($online == 2 ){
                $where = $where." AND s.sessionid IS NULL";
            }
        }

        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);
//        $end_date = trim($_REQUEST['start_time']);
        if(!empty($start_date) && !empty($end_date)){
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date." 23:59:59");

        }else{
            $start_date = date("Y-m-d");
            $end_date   = date("Y-m-d");
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date." 23:59:59");
        }

//        dump($start_date);
//        dump($end_date);

        $page = trim($_REQUEST['page']);
        if (empty($page) || !is_numeric($page)) {
            $page = 1;
        }

        //如果是初始化刚点进来的页面，则不进行数据搜索，直接载入模版
        if ($_REQUEST['start_time'] === null && $_REQUEST['end_time'] === null) {
            include template('delay-reporting-groupDetail');
            return ;
        }

        $sql = "SELECT id FROM `un_user` WHERE username='{$username}'";
        $uid = $this->db->result($sql);
        if(empty($uid)){
            include template('delay-reporting-groupDetail');
            return ;
        }

        //查团队情况
        //直属
        $sql = "SELECT count(*) FROM `un_user_tree` WHERE pids = ',{$uid},'";
        $directly = $this->db->result($sql);

        $activeTime = SYS_TIME - (86400*3); //24小时内登录的，计算活跃人数，新注册用户
        $sql = "SELECT user_id FROM `un_user_tree` WHERE pids LIKE '%{$uid}%' OR user_id={$uid}";
        $uids = $this->db->getall($sql);
        $TeamIds = array_column($uids,'user_id');
        $teamId = implode(',',$TeamIds);
        //团队登录注册活跃度
        $sql = "SELECT SUM(IF(`logintime` > {$activeTime},1,0)) as online,SUM(IF(`regtime` > {$activeTime},1,0)) as reg FROM `un_user` WHERE `id` IN ({$teamId})";
//        dump($sql);
        $teamUser = O('model')->db->getOne($sql);
//        dump($teamUser);



        $sql = "select date,uid,username,weixin,directly,team,online,reg,team_Betting,team_award,selfBackwater,directlyBackwater,teamBackwater,profit_2,profit,create_time,type,parent_id from `un_user_team_report` WHERE uid={$uid} and UNIX_TIMESTAMP(`date`) >={$start_time} and UNIX_TIMESTAMP(`date`)<={$end_time}";
//        lg('shell_reporting_log',var_export(array('$sql'=>$sql),1));

        $resData = $this->db->getall($sql);
        $list = array();
        $list[0]['online']=$teamUser['online']; //这里要改
        $list[0]['team']=count($TeamIds); //这里要改
        $list[0]['reg']=$teamUser['reg']; //这里要改
        $list[0]['directly']=$directly; //这里要改

        $total['online'] = $list[0]['online'];//活跃人数
        $total['reg'] = $list[0]['reg'];//注册人数
        $total['team'] = $list[0]['team']; //团队人数
        foreach ($resData as $k=>$v){
            $list[0]['uid']=$v['uid'];
            $list[0]['username']=$v['username'];
            $list[0]['weixin']=$v['weixin'];
            $list[0]['team_Betting']=bcadd($list[0]['team_Betting'],$v['team_Betting'],2);
            $list[0]['team_award']=bcadd($list[0]['team_award'],$v['team_award'],2);
            $list[0]['selfBackwater']=bcadd($list[0]['selfBackwater'],$v['selfBackwater'],2);
            $list[0]['directlyBackwater']=bcadd($list[0]['directlyBackwater'],$v['directlyBackwater'],2);
            $list[0]['teamBackwater']=bcadd($list[0]['teamBackwater'],$v['teamBackwater'],2);
            $list[0]['profit_2']=bcadd($list[0]['profit_2'],$v['profit_2'],2);
            $list[0]['profit']=bcadd($list[0]['profit'],$v['profit'],2);
            $total['selfBackwater'] = bcadd($v['selfBackwater'],$total['selfBackwater'],2); //自身返水
            $total['directlyBackwater'] =bcadd($v['directlyBackwater'],$total['directlyBackwater'],2); //直属会员返水
            $total['teamBackwater'] = bcadd($v['teamBackwater'],$total['teamBackwater'],2); //团队返水
            $total['team_Betting'] = bcadd($v['team_Betting'],$total['team_Betting'],2); //团队会员投注
            $total['team_award'] = bcadd($v['team_award'],$total['team_award'],2); //团队会员中奖
            $total['profit'] = bcadd($v['profit'],$total['profit'],2);//盈利
            $total['profit_2'] = bcadd($v['profit_2'],$total['profit_2'],2);//投注盈利
        }

        //会员组
        $group = $this->model2->getGroup();
        include template('delay-reporting-groupDetail');
    }


    /**
     * 获取团队会员信息 / 获取直属会员信息
     * @method ajax
     * @param uid int 用户id
     * @param type int 1:获取直属会员信息; 2:获取团队会员信息
     * @return html
     */
    public function getDelayGroupInfo(){
        $id = $_REQUEST['uid'];
        $type = $_REQUEST['type'];
        $num = $_REQUEST['num'];  //人数
        $cpage = $_REQUEST['page']?$_REQUEST['page']:1; //当前页

        $pageSize = 10;

        $prePage = 0;
        $nextPage = 0;
        $start_date = strtotime($_REQUEST['start_time']);
        $end_date = strtotime($_REQUEST['end_time'].' 23:59:59');

        if($num < $pageSize){
            $prePage = 0;
            $nextPage = 0;
        }else{
            $totalPage = (int)($num/$pageSize);
            $yPage = $num%$pageSize;
            if($yPage!=0){
                $totalPage++;
            }
            if($cpage==1){
                if($totalPage>1){
                    $prePage=0;
                    $nextPage = $cpage+1;
                }
            }elseif ($cpage==$totalPage){
                $prePage = $cpage-1;
                $nextPage = 0;

            }else{
                $prePage = $cpage-1;
                $nextPage = $cpage+1;
            }
        }

        if($cpage==1){
            $offSet = $pageSize;
        }else{
            $offSet = $cpage*$pageSize;
        }

        lg('shell_reporting_log',var_export(array('$num'=>$num,'$cpage'=>$cpage,'$prePage'=>$prePage,'$nextPage'=>$nextPage,'$offSet'=>$offSet),1));

        if($type==2){
            //查询user表下级记录
            $sql = "select date,uid,username,weixin,directly,team,online,reg,team_Betting,team_award,selfBackwater,directlyBackwater,teamBackwater,profit_2,profit,create_time,type,parent_id from `un_user_team_report` WHERE (type=0 and  uid={$id}) or (type=1 and parent_id={$id}) and UNIX_TIMESTAMP(`date`) >={$start_date} and UNIX_TIMESTAMP(`date`)<={$end_date} order by id desc limit {$offSet},{$pageSize}";
            $res  = $this->db->getall($sql);
        }elseif ($type ==1){
            $sql = "select date,uid,username,weixin,directly,team,online,reg,team_Betting,team_award,selfBackwater,directlyBackwater,teamBackwater,profit_2,profit,create_time,type,parent_id from `un_user_team_report` WHERE type=1 and parent_id={$id} and UNIX_TIMESTAMP(`date`) >={$start_date} and UNIX_TIMESTAMP(`date`)<={$end_date}  order by id desc limit {$offSet},{$pageSize}";
            $res  = $this->db->getall($sql);
        }

        $html = '';
        foreach ($res as $v){
            $html .= "<tr class=\"c2_{$v['uid']} appendList\" style=\"background-color: #E7F5FF\">
                        <td>{$v['uid']}</td>
                        <td>{$v['username']}</td>
                        <td></td>
                        <td>{$v['directly']}</td>
                        <td>{$v['team']}</td>
                        <td>{$v['online']}</td>
                        <td>{$v['reg']}</td>
                        <td>{$v['team_Betting']}</td>
                        <td>{$v['team_award']}</td>
                        <td>{$v['selfBackwater']}</td>
                        <td>{$v['directlyBackwater']}</td>
                        <td>{$v['teamBackwater']}</td>
                        <td>{$v['profit_2']}</td>
                        <td>{$v['profit']}</td>
                    </tr>";
        }

        $str = '<tr class="appendList"><td colspan="13"><div class="sxBtnBox">';
        if($nextPage==0 && $prePage!=0){ //最后一页
            $str .= '<button class="prevBtn" data-type="'.$type.'" data-uid="'.$id.'" data-page="'.$prePage.'"  data-num="'.$num.'" >上一页</button>';
        }elseif($nextPage!=0 && $prePage==0){ //第一页
            $str .= '<button data-type="'.$type.'" data-uid="'.$id.'" data-page="'.$nextPage.'" class="nextBtn"  data-num="'.$num.'" >下一页</button>';
        }elseif($nextPage!=0 && $prePage!=0){
            $str .= '<button class="prevBtn" data-type="'.$type.'" data-uid="'.$id.'" data-page="'.$prePage.'"  data-num="'.$num.'" >上一页</button><button data-type="'.$type.'" data-uid="'.$id.'" data-page="'.$nextPage.'" data-num="'.$num.'" class="nextBtn">下一页</button>';
        }
        $str .= '</div></td></tr>';
        echo $html.$str;
    }


    //团队报表   不排序
    public function groupDetail_ns() {
        $groupList = $this->model2->getGroup();
        $isSearch = getParame('isSearch',0,0,'int');
        $m="admin";
        $c="reporting";
        $a="groupDetail_ns";
        if($isSearch) {
            $pagesize = 10;
            $page = getParame('page', 0, 1, 'int');
            $offset = ($page-1) * $pagesize;
            $group = getParame('group', 0, '');
            $username = getParame('username', 0, '');
            $stype = getParame('stype', 0, '');
            $uid = getParame('uid', 0, '');
            $td_zs = getParame('td_zs', 0, '');
            $quick = getParame('quick', 0, '');
            $time = $this->getSearchTime($quick);
            $start_time_int = $time[0];
            $end_time_int = $time[1];
            $start_date = date('Y-m-d',$start_time_int);
            $end_date = date('Y-m-d',$end_time_int);

            $where = ' where uu.reg_type NOT in (0,8,9,10,11)';
            $left = '';
            if($uid) {
                $td_zs == 1 && $where .= " and uut.pids like '%,".$uid.",'";
                $td_zs == 2 && $where .= " and uut.pids like '%,".$uid.",%'";
                $sql = "select distinct uu.id from un_user_tree uut left join un_user uu on uut.user_id=uu.id";
                $countSql = "select count(distinct uu.id) from un_user_tree uut left join un_user uu on uut.user_id=uu.id";
            }else {
                $sql = "select distinct uu.id from un_user uu";
                $countSql = "select count(distinct uu.id) from un_user uu";
            }
            if($username) {
                $where .= " and uu.username = '".$username."'";
            }

            $group && $where .= " and uu.group_id = '".$group."'";
            switch ($stype) {
                case 1:         //在线
                    $left .= ' left join un_session us on uu.id = us.user_id and is_admin = 0';
                    $where .= ' and us.sessionid is NOT NULL';         //在线
                    break;
                case 2:
                    $left .= ' left join un_session us on uu.id = us.user_id and is_admin = 0';
                    $where .= ' and us.sessionid is null';         //离线
                    break;
                case 3:             //充值
                    $left .= " left join un_account_log uala on uu.id = uala.user_id and uala.type = 10 AND uala.addtime > $start_time_int AND uala.addtime < $end_time_int";
                    $where .= ' and uala.id is not null';
                    break;
                case 4:                 //提现
                    $left .= " left join un_account_log uala on uu.id = uala.user_id and uala.type = 11 AND uala.addtime > $start_time_int AND uala.addtime < $end_time_int";
                    $where .= ' and uala.id is not null';
                    break;
            }
            $sql .= $left . $where . " limit $offset,$pagesize";
            $countSql .= $left . $where;
            $listCnt = $this->db->result($countSql);
            $userList = $this->db->getall($sql);
            if(!$userList) {
                include template('reporting-groupDetail_ns');
                return;
            }

            $userArr = array_column($userList,'id');
            $userStr = "(".implode(',',$userArr).")";

            $t_y_1 = '(12,14,19,20,21,18,32,66,1000,999,998,997,995,994,993,992)';
            $t_y_2 = '(13,120)';
            $fields = 'uu.id,uu.username,uu.regtime,uut.user_id,uu_a.id u_id,uu_a.parent_id,uu_a.logintime,uu_a.regtime regt';
            $fields .= ",SUM(if(ual.type=13,ual.money,if(ual.type=14,-ual.money,0))) AS team_Betting";
            $fields .= ",SUM(if(ual.type=12,ual.money,0)) AS team_win";
            $fields .= ",SUM(if(ual.type=19,ual.money,0)) AS person_back";
            $fields .= ",SUM(if(ual.type=20,ual.money,0)) AS zs_back";
            $fields .= ",SUM(if(ual.type=21,ual.money,0)) AS team_back";
            $fields .= ",SUM(if(ual.type in (12,14),ual.money,if(ual.type=13,-ual.money,0))) AS tz_profit";
            $fields .= ",SUM(if(ual.type in $t_y_1,ual.money,if(ual.type in $t_y_2,-ual.money,0))) AS profit";
            $sql_c = "select $fields from un_user uu left join un_user_tree uut ON uu.id = uut.user_id OR uut.pids LIKE CONCAT('%,',uu.id,',%') LEFT JOIN un_user uu_a ON uut.user_id = uu_a.id LEFT JOIN un_account_log ual ON uut.user_id = ual.user_id and ual.addtime > $start_time_int AND ual.addtime < $end_time_int WHERE uu.id in $userStr GROUP BY uu.id,uut.user_id";
            $time1 = strtotime('-3 days');      //3天前时间戳
            $fetchFields = 'id uid,regtime,username,sum(if(u_id,1,0)) td_count,SUM(IF(parent_id=id,1,0)) as zs_count,sum(team_Betting) as team_Betting,sum(profit) as profit';
            $fetchFields .= ",sum(if(regt > $time1,1,0)) new_reg,sum(if(logintime > $time1,1,0)) as active_user";       //新注册  活跃用户
            $fetchFields .= ",sum(team_win) team_win,sum(person_back) person_back,sum(zs_back) zs_back,sum(team_back) team_back,sum(tz_profit) tz_profit";
            if($username) {
                $dis = 1;
                $fetchFields .= ",SUM(IF(parent_id=id and logintime > $time1,1,0)) as zs_active_user";
                $fetchFields .= ",SUM(IF(parent_id=id and regt > $time1,1,0)) as zs_new_reg";
                $fetchFields .= ",SUM(IF(parent_id=id,team_Betting,0)) as zs_team_Betting";
                $fetchFields .= ",SUM(IF(parent_id=id,team_win,0)) as zs_team_win";
                $fetchFields .= ",SUM(IF(parent_id=id,person_back,0)) as zs_person_back";
                $fetchFields .= ",SUM(IF(parent_id=id,zs_back,0)) as zs_zs_back";
                $fetchFields .= ",SUM(IF(parent_id=id,team_back,0)) as zs_team_back";
                $fetchFields .= ",SUM(IF(parent_id=id,tz_profit,0)) as zs_tz_profit";
                $fetchFields .= ",SUM(IF(parent_id=id,profit,0)) as zs_profit";
            }
            $sql = "select $fetchFields from ($sql_c) infos group by id";
            $list = $this->db->getall($sql);
            $total = [];
            $zsTotal = [];
            foreach($list as &$value) {
                $value['regtime'] = date('Y-m-d H:i:s',$value['regtime']);
                $total['active_user'] += $value['active_user'];
                $total['new_reg'] += $value['new_reg'];
                $total['zs_count'] += $value['zs_count'];
                $total['td_count'] += $value['td_count'];
                $total['person_back'] += $value['person_back'];
                $total['zs_back'] += $value['zs_back'];
                $total['team_back'] += $value['team_back'];
                $total['team_Betting'] += $value['team_Betting'];
                $total['team_win'] += $value['team_win'];
                $total['profit'] += $value['profit'];
                $total['tz_profit'] += $value['tz_profit'];
                if($dis) {
                    $zsTotal['zs_count'] += $value['zs_count'];         //直属总人数
                    $zsTotal['zs_active_user'] += $value['zs_active_user'];
                    $zsTotal['zs_new_reg'] += $value['zs_new_reg'];
                    $zsTotal['zs_team_Betting'] += $value['zs_team_Betting'];
                    $zsTotal['zs_team_win'] += $value['zs_team_win'];
                    $zsTotal['zs_person_back'] += $value['zs_person_back'];
                    $zsTotal['zs_zs_back'] += $value['zs_zs_back'];
                    $zsTotal['zs_team_back'] += $value['zs_team_back'];
                    $zsTotal['zs_tz_profit'] += $value['zs_tz_profit'];
                    $zsTotal['zs_profit'] += $value['zs_profit'];
                }
                unset($value);
            }

            $url = '?m=admin&c=reporting&a=groupDetail_ns';
            $parameData = [
                'quick' => $quick,
                'group' => $group,
                'start_time' => $start_date,
                'end_time' => $end_date,
                'username' => $username,
                'stype' => $stype,
                'isSearch' => $isSearch,
                'td_zs' => $td_zs,
                'uid' => $uid,
            ];
            $page = new pages($listCnt, $pagesize, $url, $parameData);
            $show = $page->show();
        }
        include template('reporting-groupDetail_ns');
    }

    /*
     * 团队报表 重写
     * */
    public function groupDetailbak() {
        session_write_close();
        $groupList = $this->model2->getGroup();
        $isSearch = getParame('isSearch',0,0,'int');
        $m="admin";
        $c="reporting";
        $a="groupDetailbak";
        if($isSearch) {
            $pagesize = 10;
            $page = getParame('page', 0, 1, 'int');
            $offset = ($page-1) * $pagesize;
            $group = getParame('group', 0, '');
            $username = getParame('username', 0, '');
            $stype = getParame('stype', 0, '');
            $sort = getParame('sort', 0, '');
            $uid = getParame('uid', 0, '');
            $td_zs = getParame('td_zs', 0, '');
            $quick = getParame('quick', 0, '');
            $time = $this->getSearchTime($quick);
            $start_time_int = $time[0];
            $end_time_int = $time[1];
            $start_date = date('Y-m-d',$start_time_int);
            $end_date = date('Y-m-d',$end_time_int);

            $where = ' and uu.reg_type NOT in (0,8,9,10,11)';
            if($username) {
                $where .= ' and uu.username = "'.$username.'"';
            }elseif ($uid) {
                if($uid && $td_zs == 1) $where .= ' and uu.parent_id = '.$uid;
                if($uid && $td_zs == 2) $where .= " and uut.pids like CONCAT('%,',$uid,',%')";
            }else {
                //查询当前有流水记录
                $uu_tab = "select distinct uu.id,uu.username,uu.regtime,uu.reg_type from un_account_log ual left join un_user uu on ual.user_id = uu.id where uu.reg_type NOT IN (0, 8, 9, 10, 11) and ual.addtime >= $start_time_int and ual.addtime <= $end_time_int";
            }

            if(!empty($group)) $where .= " AND uu.group_id = $group";

            $l_where = " uut.pids like CONCAT('%,',uu.id,',%')";
            $left = '';
            $fields = 'uu.id,uu.username,uu.regtime';
            $groupby = ' group by uu.id';
            switch ($stype) {
                case 1:         //在线
                    $left .= ' left join un_session us on uu.id = us.user_id and is_admin = 0';
                    $where .= ' and us.sessionid is NOT NULL';         //在线
                    break;
                case 2:
                    $left .= ' left join un_session us on uu.id = us.user_id and is_admin = 0';
                    $where .= ' and us.sessionid is null';         //离线
                    break;
                case 3:             //充值
                    $left .= " left join un_account_log uala on uu.id = uala.user_id and uala.type = 10 AND uala.addtime > $start_time_int AND uala.addtime < $end_time_int";
                    $where .= ' and uala.id is not null';
                    break;
                case 4:                 //提现
                    $left .= " left join un_account_log uala on uu.id = uala.user_id and uala.type = 11 AND uala.addtime > $start_time_int AND uala.addtime < $end_time_int";
                    $where .= ' and uala.id is not null';
                    break;
            }
            $s_k = 'id';
            $sort == 1 && $s_k = 'team_Betting';
            $sort == 2 && $s_k = 'profit';
            $groupby .= ',uut.user_id';

            $fields .= ",uut.user_id,uua.id u_id,uua.parent_id,uua.logintime,uua.regtime regt,SUM(IF(ualb.type=13,ualb.money,if(ualb.type=14,-ualb.money,0))) as team_Betting";      //投注
            $t_y_1 = '(12,14,19,20,21,18,32,66,1000,999,998,997,995,994,993,992)';
            $t_y_2 = '(13,120)';
            $fields .= ",SUM(IF(ualb.type in $t_y_1,ualb.money,if(ualb.type in $t_y_2,-ualb.money,0))) as profit";          //盈亏
            $fields .= ",sum(if(ualb.type=12,ualb.money,0)) team_win";      //中奖
            $fields .= ",sum(if(ualb.type=19,ualb.money,0)) person_back";      //个人反水
            $fields .= ",sum(if(ualb.type=20,ualb.money,0)) zs_back";      //直属反水
            $fields .= ",sum(if(ualb.type=21,ualb.money,0)) team_back";      //团队反水
            $fields .= ",sum(if(ualb.type in (12,14),ualb.money,if(ualb.type=13,-ualb.money,0))) tz_profit";      //投注盈亏     中奖 - （投注 - 撤单）
            $left .= " left join un_user_tree uut on uu.id = uut.user_id OR $l_where";
            $left .= " left join un_account_log ualb on uut.user_id = ualb.user_id AND ualb.addtime > $start_time_int AND ualb.addtime < $end_time_int";
            $left .= " left join un_user uua on uut.user_id = uua.id";

            if(isset($uu_tab)) {
                $sql_c = "select $fields from ($uu_tab) uu $left where 1=1 $where $groupby";
            }else{
                $sql_c = "select $fields from un_user uu $left where 1=1 $where $groupby";
            }

            $time1 = strtotime('-3 days');      //3天前时间戳
            $fetchFields = 'id uid,regtime,username,sum(if(u_id,1,0)) td_count,SUM(IF(parent_id=id,1,0)) as zs_count,sum(team_Betting) as team_Betting,sum(profit) as profit';
            $fetchFields .= ",sum(if(regt > $time1,1,0)) new_reg,sum(if(logintime > $time1,1,0)) as active_user";       //新注册  活跃用户
            $fetchFields .= ",sum(team_win) team_win,sum(person_back) person_back,sum(zs_back) zs_back,sum(team_back) team_back,sum(tz_profit) tz_profit";
            if($username) {
                $dis = 1;
                $fetchFields .= ",SUM(IF(parent_id=id and logintime > $time1,1,0)) as zs_active_user";
                $fetchFields .= ",SUM(IF(parent_id=id and regt > $time1,1,0)) as zs_new_reg";
                $fetchFields .= ",SUM(IF(parent_id=id,team_Betting,0)) as zs_team_Betting";
                $fetchFields .= ",SUM(IF(parent_id=id,team_win,0)) as zs_team_win";
                $fetchFields .= ",SUM(IF(parent_id=id,person_back,0)) as zs_person_back";
                $fetchFields .= ",SUM(IF(parent_id=id,zs_back,0)) as zs_zs_back";
                $fetchFields .= ",SUM(IF(parent_id=id,team_back,0)) as zs_team_back";
                $fetchFields .= ",SUM(IF(parent_id=id,tz_profit,0)) as zs_tz_profit";
                $fetchFields .= ",SUM(IF(parent_id=id,profit,0)) as zs_profit";
            }
            $sql = "select $fetchFields from ($sql_c) infos group by id order by $s_k desc";
            $countSql = "select count(*) as countp  from ($sql) infos";
            $sql .= " limit $offset,$pagesize";
            $listCnt = $this->db->result($countSql);      //总条数
            $list = $this->db->getall($sql);
            $total = [];
            $zsTotal = [];
            foreach($list as &$value) {
                $value['regtime'] = date('Y-m-d H:i:s',$value['regtime']);
                $total['active_user'] += $value['active_user'];
                $total['new_reg'] += $value['new_reg'];
                $total['zs_count'] += $value['zs_count'];
                $total['td_count'] += $value['td_count'];
                $total['person_back'] += $value['person_back'];
                $total['zs_back'] += $value['zs_back'];
                $total['team_back'] += $value['team_back'];
                $total['team_Betting'] += $value['team_Betting'];
                $total['team_win'] += $value['team_win'];
                $total['profit'] += $value['profit'];
                $total['tz_profit'] += $value['tz_profit'];
                if($dis) {
                    $zsTotal['zs_count'] += $value['zs_count'];         //直属总人数
                    $zsTotal['zs_active_user'] += $value['zs_active_user'];
                    $zsTotal['zs_new_reg'] += $value['zs_new_reg'];
                    $zsTotal['zs_team_Betting'] += $value['zs_team_Betting'];
                    $zsTotal['zs_team_win'] += $value['zs_team_win'];
                    $zsTotal['zs_person_back'] += $value['zs_person_back'];
                    $zsTotal['zs_zs_back'] += $value['zs_zs_back'];
                    $zsTotal['zs_team_back'] += $value['zs_team_back'];
                    $zsTotal['zs_tz_profit'] += $value['zs_tz_profit'];
                    $zsTotal['zs_profit'] += $value['zs_profit'];
                }
                unset($value);
            }

            $url = '?m=admin&c=reporting&a=groupDetailbak';
            $parameData = [
                'quick' => $quick,
                'group' => $group,
                'sort' => $sort,
                'start_time' => $start_date,
                'end_time' => $end_date,
                'username' => $username,
                'stype' => $stype,
                'isSearch' => $isSearch,
                'td_zs' => $td_zs,
                'uid' => $uid,
            ];
            $page = new pages($listCnt, $pagesize, $url, $parameData);
            $show = $page->show();

        }
        include template('reporting-groupDetailbak');
    }

    /**
     * 团队报表
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function groupDetail()
    {
        session_write_close();
        $m="admin";
        $c="reporting";
        $a="groupDetail";
        $pagesize = 10;
        $show_user_info = $this->admin['show_user_info'];  //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $threeTime = SYS_TIME - (86400*3); //3小时内登录的，计算活跃人数，新注册用户
        $list = array();
        $total = array();
        $where = " ";
        $listTotal = [];  //盈亏排序使用
        $listTeamBetting = []; //团队投注排序使用

        //排序
        $flag     = $_REQUEST['flag'];
        $yk_flag  = $_REQUEST['yk_flag'];
        if (empty($yk_flag)) {
            $yk_flag = 0;
        }
        $bet_flag = $_REQUEST['bet_flag'];
        if (empty($bet_flag)) {
            $bet_flag = 0;
        }
        
        if (empty($flag) || !is_numeric($flag) || $flag > 8 || $flag < 0) {
            $flag = 0;
        }

        $username = trim($_REQUEST['username']);
        $full = 0;
        if(!empty($username)){
            $where .= " AND u.username = '".$username."'";
        }

//        //微信
//        $weixin = trim($_REQUEST['weixin']);
//        if(!empty($weixin)){
//            $where .= " AND u.weixin LIKE '%".$weixin."%'";
//        }

        //分组
        $group = trim($_REQUEST['group']);
        if(!empty($group)){
            $where = $where." AND u.group_id = ".$group;
        }
        
        //搜索类型
        $stype = trim($_REQUEST['stype']);
        if(!empty($stype)){
            switch ($stype){
                case 1:
                    $where = $where." AND s.sessionid IS NOT NULL";
                    break;
                case 2:
                    $where = $where." AND s.sessionid IS NULL";
                    break;
                case 3: //充值
                    $where = $where." AND ual.type=10";
                    break;
                case 4: //提现
                    $where = $where." AND ual.type=11";
                    break;
            }
        }

        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);
        if(!empty($start_date) && !empty($end_date)){
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date." 23:59:59");
            
        }else{
            $start_date = date("Y-m-d");
            $end_date   = date("Y-m-d");
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date." 23:59:59");
        }
        $quick = $_REQUEST['quick'];
        if($quick!="0"&&$quick!=""){
            switch ($quick){
                case 1:
                    $start_time = strtotime(date("Y-m-d",strtotime("0 day")));
                    $end_time = $start_time + 86399;
                    break;
                case 2:
                    $start_time = strtotime(date("Y-m-d",strtotime("-1 day")));
                    $end_time = $start_time + 86399;
                    break;
                case 3:
                    $start_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));
                    $end_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;
                    break;
                case 4:
                    $start_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;
                    break;
                case 5:
                    $start_time = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));
                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));
                    break;
            }
            $start_date = date("Y-m-d",$start_time);
            $end_date = date("Y-m-d",$end_time);
        }
        $page = trim($_REQUEST['page']);
        if (empty($page) || !is_numeric($page)) {
            $page = 1;
        }

        //如果是初始化刚点进来的页面，则不进行数据搜索，直接载入模版
        if ($_REQUEST['start_time'] === null && $_REQUEST['end_time'] === null) {
            include template('reporting-groupDetail');
            return ;
        }
        
        //注册时间段[暂时过滤掉注册时间段的条件]
        // $where .= " AND u.regtime BETWEEN {$start_time} and {$end_time}";

        $rg_type = getParame('rg_type',0,0,'int');
        if($rg_type == 0) {
            $where .= " and u.reg_type NOT IN (0, 8, 9, 11) ";
        }else{
            $where .= ' and u.reg_type = '.$rg_type;
        }

        if (!empty($username)) {
            if ($stype == 1 || $stype == 2) {
                $sql = "SELECT u.id AS uid FROM un_user AS u LEFT JOIN un_session AS s ON u.id = s.user_id WHERE 1=1 " . $where;
            } else {
                $sql = "SELECT DISTINCT(u.id) AS uid FROM un_user AS u LEFT JOIN un_account_log ual ON ual.user_id = u.id WHERE 1=1 ".$where;
            }
        }else {
            if ($stype == 1 || $stype == 2) {
                $sql = "SELECT DISTINCT(u.id) AS uid FROM un_account_log ual 
                        LEFT JOIN un_user AS u ON ual.user_id = u.id 
                        LEFT JOIN un_session AS s ON u.id = s.user_id
                        WHERE 1=1 {$where} AND ual.addtime BETWEEN {$start_time} AND {$end_time}";
            }else {
                $sql = "SELECT DISTINCT(u.id) AS uid FROM un_account_log ual
                        LEFT JOIN un_user AS u ON ual.user_id = u.id
                        WHERE 1=1 {$where} AND ual.addtime BETWEEN {$start_time} AND {$end_time}";
            }
        }
        $arrId = O('model')->db->getAll($sql);
        $arrUserId = array();        //自我的id与自我id和团队ID
        if(!empty($arrId)) {
            foreach ($arrId as $v) {
                $teamIds = $this->teamLists($v['uid']);  //团队会员ID
                $leaguers = $this->leaguer($v['uid']);    //直属会员ID（包括自己）

                $arrTeamIds = array_merge($leaguers, $teamIds);
                $teamIds = array_column($arrTeamIds, 'user_id');
                $uids = implode(',',$teamIds);
                $arrUserId[] = ['uid' => $v['uid'], 'team_id' => $uids];
            }
        }

        $userIds = $this->getGroupIds($arrUserId, $start_date, $end_date, $page, $pagesize, $flag);
        
        $userCount = $userIds['ucount'];  //满足条件数量
        $url = '?m=admin&c=reporting&a=groupDetail';
        $pages = new pages($userCount,$pagesize,$url,$_REQUEST);
        $show = $pages->show();
        $page_start = $pages->offer;
        $page_size = $pagesize;

        //排序后该页显示的用户Id（排序完成后）
        $list = [];
        $resData2 = array();
        $dis = 0; //是否显示直属数据
        if(!empty($userIds['user_ids'])) {
            foreach ($userIds['user_ids'] as $ks => $vs) {
                $listTeamId = $this->teamLists($vs);  //团队会员ID
                $listLeaguers = $this->leaguer($vs);    //直属会员ID（包括自己）
                //$arrListTeamIds = array_merge($listLeaguers, $listTeamId);
                //$listTeamIds = array_column($arrListTeamIds, 'user_id');
                //$teamUids = implode(',',$listTeamIds);
                $arrUser = ['uid' => $vs, 'team_id' => $listTeamId, 'own_id' => $listLeaguers];

                $resData = $this->getUserDetail($arrUser, $start_date, $end_date);
                $resData['selfBackwater']= bcadd($resData['selfBackwater'],0,2);
                $resData['directlyBackwater']= bcadd($resData['directlyBackwater'],0,2);
                $resData['teamBackwater']= bcadd($resData['teamBackwater'],0,2);
                $resData['teamBackwater']= bcadd($resData['teamBackwater'],0,2);
                $resData['team_Betting']= bcadd($resData['team_Betting'],0,2);
                $resData['team_award']= bcadd($resData['team_award'],0,2);
                $resData['profit']= bcadd($resData['profit'],0,2);
                $resData['profit_2']= bcadd($resData['profit_2'],0,2);

                $list[] = $resData;

                $total['online'] += $resData['online']; //活跃人数
                $total['reg'] += $resData['reg']; //注册人数
                $total['team'] += $resData['team']; //团队人数

                $total['selfBackwater'] += $resData['selfBackwater']; //自身返水
                $total['directlyBackwater'] += $resData['directlyBackwater']; //直属会员返水
                $total['teamBackwater'] += $resData['teamBackwater']; //团队返水
                $total['team_Betting'] += $resData['team_Betting']; //团队会员投注
                $total['team_award'] += $resData['team_award']; //团队会员中奖
                $total['profit'] += $resData['profit'];//盈利
                $total['profit_2'] += $resData['profit_2'];//投注盈利

                //直属数据
                if(!empty($username)){
                    $dis = 1;
                    //去除本身
                    $listLeaguers2 = [];
                    foreach ($listLeaguers as $lk=>$lv){
                        if($lv['id'] != $vs){
                            $listLeaguers2[]['user_id'] = $lv['id'];
                        }
                    }
                    if(!empty($listLeaguers2)){
                        $arrUser2 = ['uid' => $vs, 'team_id' => $listLeaguers2, 'own_id' => array()];
                        $resData2 = $this->getUserDetail($arrUser2, $start_date, $end_date);
                    }
                }

            }
        }
        $total['selfBackwater'] = bcadd($total['selfBackwater'],0,2);
        $total['directlyBackwater'] = bcadd($total['directlyBackwater'],0,2);
        $total['teamBackwater'] = bcadd($total['teamBackwater'],0,2);
        $total['team_Betting'] = bcadd($total['team_Betting'],0,2);
        $total['team_award'] = bcadd($total['team_award'],0,2);
        $total['profit'] = bcadd($total['profit'],0,2);
        $total['profit_2'] = bcadd($total['profit_2'],0,2);

        //会员组
        $group = $this->model2->getGroup();
//        dump($list);
        include template('reporting-groupDetail');
    }


    /**
     * 获取团队会员信息 / 获取直属会员信息
     * @method ajax
     * @param uid int 用户id
     * @param type int 1:获取直属会员信息; 2:获取团队会员信息
     * @return html
     */
    public function getGroupInfo(){
        $id = $_REQUEST['uid'];
//        dump($id);
//        die;
        $type = $_REQUEST['type'];

        $yk_flag = $_REQUEST['yk_flag'];

        $flag = $_REQUEST['flag']; //总盈亏

        $bet_flag = $_REQUEST['bet_flag']?:1; //投注

        $start_date = $_REQUEST['start_time'];
        $end_date = $_REQUEST['end_time'];


//        $type = $_REQUEST['type'];
        $num = $_REQUEST['num'];  //人数
        $cpage = $_REQUEST['page']?$_REQUEST['page']:1; //当前页

        $pageSize = 10;

        $prePage = 0;
        $nextPage = 0;


        if($num < $pageSize){
            $prePage = 0;
            $nextPage = 0;
        }else{
            $totalPage = (int)($num/$pageSize);
            $yPage = $num%$pageSize;
            if($yPage!=0){
                $totalPage++;
            }
            if($cpage==1){
                if($totalPage>1){
                    $prePage=0;
                    $nextPage = $cpage+1;
                }
            }elseif ($cpage==$totalPage){
                $prePage = $cpage-1;
                $nextPage = 0;

            }else{
                $prePage = $cpage-1;
                $nextPage = $cpage+1;
            }
        }

        if($cpage==1){
            $offSet = 0;
        }else{
            $offSet = ($cpage-1)*$pageSize;
        }

        $limit = "limit {$offSet},{$pageSize}";
//        dump($limit);

        if($type==2){ //查团队
            //查询user表下级记录
            $res = $this->model2->teamListsLimit($id,$limit);
        }elseif ($type ==1){ //直属
            $sql = "SELECT u.id FROM un_user AS u WHERE u.parent_id = {$id} $limit";
            $res = O('model')->db->getAll($sql);
        }
//        dump($res);
//        die;

        if(!empty($res)){
            $resData = array();
            foreach ($res as $v){
                $resData[]  = $this->getGroupDetail($v['id'],$start_date,$end_date);
            }

            $html = '';
            foreach ($resData as $v){
                $html .= "<tr class=\"c2_{$v['uid']} appendList\" style=\"background-color: #E7F5FF\">
                        <td>{$v['uid']}</td>
                        <td>{$v['username']}</td>
                        <td></td>
                        <td>{$v['directly']}</td>
                        <td>{$v['team']}</td>
                        <td>{$v['online']}</td>
                        <td>{$v['reg']}</td>
                        <td>{$v['team_Betting']}</td>
                        <td>{$v['team_award']}</td>
                        <td>{$v['selfBackwater']}</td>
                        <td>{$v['directlyBackwater']}</td>
                        <td>{$v['teamBackwater']}</td>
                        <td>{$v['profit_2']}</td>
                        <td>{$v['profit']}</td>
                    </tr>";
            }

            $str = '<tr class="appendList"><td colspan="13"><div class="sxBtnBox">';
            if($nextPage==0 && $prePage!=0){ //最后一页
                $str .= '<button class="prevBtn" data-type="'.$type.'" data-uid="'.$id.'" data-page="'.$prePage.'"  data-num="'.$num.'" >上一页</button>';
            }elseif($nextPage!=0 && $prePage==0){ //第一页
                $str .= '<button data-type="'.$type.'" data-uid="'.$id.'" data-page="'.$nextPage.'" class="nextBtn"  data-num="'.$num.'" >下一页</button>';
            }elseif($nextPage!=0 && $prePage!=0){
                $str .= '<button class="prevBtn" data-type="'.$type.'" data-uid="'.$id.'" data-page="'.$prePage.'"  data-num="'.$num.'" >上一页</button><button data-type="'.$type.'" data-uid="'.$id.'" data-page="'.$nextPage.'" data-num="'.$num.'" class="nextBtn">下一页</button>';
            }
            $str .= '</div></td></tr>';
            echo $html.$str;
        }
    }

    /**
     * 团队报表
     * @method POST
     * @param token string
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @return  json
     */
    public function getGroupDetail($id,$start_date,$end_date)
    {
        $threeTime = SYS_TIME - (86400*3); //24小时内登录的，计算活跃人数，新注册用户

        //交易类型
        $trade = $trade = $this->model2->getTrade();
        $ids = implode($trade['tranTypeIds'],',');

        //团队会员 查询user表
        //查询自身记录
        $sql = "SELECT id, id AS uid, regtime, logintime, parent_id FROM un_user WHERE id={$id}";
        $c_user = O('model')->db->getOne($sql);

        //查询user表下级记录
        $field = "id, id AS uid, regtime, logintime, parent_id";
        $res = $this->recursive_query($id,$field);
        array_unshift($res,$c_user);
//        dump($res);

        $directlyIds = array();//直属会员id
        $teamIds = array();//团队会员id
        $online = 0;
        $reg = 0;
        foreach ($res as $v){
            if($v['logintime'] > $threeTime){
                $online++;   //活跃人数
            }
            if($v['regtime'] > $threeTime){
                $reg++;      //新注册人数
            }
            if($v['parent_id'] == $id){
                $directlyIds[] = $v['uid'];  //直属会员人数
            }
            $teamIds [] = $v['uid']; //团队人数
        }

        //团队交易记录
        $STeamIds = implode($teamIds,',');
        $teamTradeType = array();
        $teamTradeType = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds'],$STeamIds);
        $tradeType=array();
        //自身交易记录 orders表
        $tradeType = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds'],$id);
        //自身信息
        $sql3 = "SELECT u.id AS uid, u.username, u.weixin, u.regtime, u.logintime FROM un_user AS u WHERE u.id = {$id}";
        $self = O('model')->db->getOne($sql3);
        $data = array();
        $data['id'] = $id;//id
        $data['uid'] = $id;//id
        $data['username'] = $self['username'];//账户
        $data['weixin'] = $self['weixin'];//账户
        $data['online'] = $online;//活跃人数
        $data['reg'] = $reg;//注册人数
        $data['team'] = count($teamIds); //团队人数
        $data['directly'] = count($directlyIds); //直属会员人数
        $data['selfBackwater'] = $tradeType['19']; //自身返水
        $data['directlyBackwater'] = $tradeType['20']; //直属会员返水
        $data['teamBackwater'] = $tradeType['21']; //团队返水
        $data['team_Betting'] = $teamTradeType['13'] - $teamTradeType['14']; //团队会员投注
        $data['team_award'] = $teamTradeType['12'] - $teamTradeType['120']; //团队会员中奖-回滚
        $data['profit'] = ($tradeType['12'] + $tradeType['14'] + $tradeType['19'] + $tradeType['20'] + $tradeType['21'] + $tradeType['18'] + $tradeType['32'] + $tradeType['66'] + $tradeType['1000'] + $tradeType['999'] + $tradeType['998'] + $tradeType['997'] + $tradeType['995'] + $tradeType['994'] + $tradeType['993'] + $tradeType['992']) - $tradeType['13'] - $tradeType['120']; //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+九宫格995+平台任务994+福袋993+刮刮乐992)-投注-回滚
        $data['create_time']=time();
        D("user")->ReplaceTeamReport($data);

        $data['profit_2'] = $tradeType['12'] + $tradeType['14'] - $tradeType['13'] - $tradeType['120']; //投注盈利: 中奖+撤单-投注-回滚
        return $data;
    }

    /**
     * 日报表 月报表 
     */
    public function dailyReport(){
        //今天
        $start_date = date("Y-m-d 00:00:00");
        $end_date = date("Y-m-d H:i:s");
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);

        //交易类型
        $trade = $this->model2->getTrade();

        //今日交易数据
        $reporting_model = D('Reporting');
        $res = $reporting_model->getTradeLogB($start_time,$end_time);
        $day_data =$reporting_model->get_arr_diff($res,$trade['tranTypeIds']);

        //本月
        $start_month_date = date("Y-m-01 00:00:00");
        $end_month_date = date("Y-m-d H:i:s");
        $start_month_time = strtotime($start_month_date);
        $end_month_time = strtotime($end_month_date);

        //本月交易数据
        // $res = D('Reporting')->getTradeLogB($start_month_time, $end_month_time);
        // $month_data =D('Reporting')->get_arr_diff($res, $trade['tranTypeIds']);
        $json_obj = $this->getMonthDailyReportData();
        $month_data = $json_obj['data'];

        //最后统计时间
        $last_write_date = date('Y-m-d H:i:s', $json_obj['last_write_time']);

        include template('reporting-dailyReport');
    }

    /**
     * 搜索时间段日报表
     * @param start_date string  查询范围起始时间
     * @param end_date string  查询范围结束时间
     */
    public function dailyReportList(){
        //查询时间范围
        @$start_date = $_POST['start_time'];
        @$end_date = $_POST['end_time'];

        //每页显示条数
        $psize = 10;
        $start_time = strtotime($start_date." 00:00:00");
        $end = strtotime($end_date." 00:00:00");

        $quick = $_POST['quick'];
        if($quick!="0"&&$quick!=""){
            switch ($quick){
                case 1:
                    $start_time = strtotime(date("Y-m-d",strtotime("0 day")));
                    $end = $start_time;
                    break;
                case 2:
                    $start_time = strtotime(date("Y-m-d",strtotime("-1 day")));
                    $end = $start_time;
                    break;
                case 3:
                    $start_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));
                    $end = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;
                    break;
                case 4:
                    $start_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                    $end = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;
                    break;
                case 5:
                    $start_time = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));
                    $end = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));
                    break;
            }
            if($end>strtotime(date('Y-m-d',time()))) $end = strtotime(date('Y-m-d',time()));
            $start_date = date('Y-m-d',$start_time);
            $end_date = date('Y-m-d',$end);
        }

        $cnt = ($end-$start_time) / 86400 +1;//总条数
        $pev_data = $cnt-$_POST['page']*$psize;//查过的记录
        $pev_data = $pev_data>= 0 ? $pev_data:0;
        $end = $end-$pev_data*86400;//查询结束时间

        //交易类型
        $trade = $this->model2->getTrade();
        $page = new page($cnt, $psize, "?m=admin&c=reporting&a=dailyReport", ['start_time'=>$start_date,'end_time'=>$end_date]);
        $show = $page->show();

        $end_time = strtotime($start_date." 23:59:59");
        $start_time = $start_time + 86400*($_POST['page']-1)*$psize;//查询时间范围
        $end_time = $end_time + 86400*($_POST['page']-1)*$psize;//查询时间范围
        $data = array();
        $page_data = array();//当前页数据统计

        do {
            //今日交易数据
            /*$res = D('Reporting')->getTradeLogB($start_time,$end_time);*/
            $res = $this->model2->getTradeLog(date("Y-m-d",$start_time),date("Y-m-d",$start_time),$trade['tranTypeIds']);
            $cnt_user = $this->getCntUser(date("Y-m-d",$start_time),date("Y-m-d",$start_time),13);//投注人数
            /*$data[$start_time] = D('Reporting')->get_arr_diff($res,$trade['tranTypeIds']);*/
            foreach ($res as $k => $v){
                $page_data['data'][$k] += $v;
            }
            $page_data['ucnt']+=$cnt_user;
            $data[$start_time] = $res;
            $data[$start_time]['ucnt'] = $cnt_user;
            $start_time += 86400;
            $end_time += 86400;
        } while ($start_time <= $end);

        //搜索统计
        $total = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds']);
        $total_user = $this->getCntUser($start_date,$end_date,13);//投注人数

        include template('reporting-dailyReportList');
    }

    /**
     * 搜索时间段日报表
     * @param start_date string  查询范围起始时间
     * @param end_date string  查询范围结束时间
     */
    public function getTeamUsers(){
        //查询时间范围
        $sdate = $_POST['stime'];
        $start_time = $sdate." 00:00:00";
        $end_time = $sdate." 23:59:59";
        $cnt_user = $this->getCntUser($start_time,$end_time,13);//投注人数
        $data= array(
            'users' => $cnt_user,
            'start' => true
        );
        echo json_encode($data);
    }

    /**
     * 交易记录
     * @param $start_time int
     * @return json
     */
//    protected function getTradeLogB($start_time,$end_time){
//        $sql = "SELECT L.type, SUM(L.money) AS total_money, COUNT(L.id) AS cnt FROM un_account_log  AS L  WHERE L.addtime BETWEEN {$start_time} and {$end_time} AND reg_type NOT IN(0,8,9,11) GROUP BY L.type";
//        //交易记录
//        $orders = O('model')->db->getall($sql);
//        return $orders;
//    }


    /**
     * 从本地json文件中，读取本月统计数据
     * 2018-01-20 update
     */
    public function getMonthDailyReportData()
    {
        $filename = 'json_files/daily_report/daily_report_result.json';
        try {
            $json_data = @file_get_contents($filename);
            $json_obj = json_decode($json_data, true);
            $json_obj['last_write_time'] = @filemtime($filename);
            return $json_obj;

        } catch (Exception $e) {
            lg('dr_read_error', var_export(['json_data'=>$json_data, $filename], true));
            return [];
        }
    }


    // protected function getTradeLogB($start_time,$end_time){
    //     $list = [];
    //     $sql = "select L.type, L.money from #@_account_log L where L.addtime between {$start_time} and {$end_time} and reg_type NOT IN(0,8,9,11)";
    //     // $sql = "select L.type, L.money from #@_account_log L where L.addtime >= {$start_time} and L.addtime <= {$end_time} and reg_type NOT IN(0,8,9,11)";
    //     $typeInfo = [];
    //     $orders = $this->db->getall($sql);
    //     lg('debug_sql.txt', var_export(['sql'=>$sql,'s'=>$start_time,'e'=>$end_time,'count'=>count($orders),'orders'=>$orders],true));
    //     // echo '<!--', print_r($orders, true), '-->';
    //     foreach ($orders as $val) {
    //         if(!in_array($val['type'],$typeInfo)){
    //             $typeInfo[] = $val['type'];
    //             $list[$val['type']]['type'] = $val['type'];
    //         }
    //     }
    //     foreach ($orders as $key=>$val) {
    //         foreach ($list as $ke=>$va) {
    //             if($val['type'] == $ke){
    //                 if($ke == 32){
    //                     if($val['money'] > 0){
    //                         $list['10']['type'] = 10;
    //                         $list['10']['total_money'] += $val['money'];
    //                         $list['10']['cnt'] += 1;
    //                     } else {
    //                         $list['11']['type'] = 11;
    //                         $list['11']['total_money'] += abs($val['money']);
    //                         $list['11']['cnt'] += 1;
    //                     }
    //                 } else {
    //                     $list[$ke]['total_money'] += $val['money'];
    //                     $list[$ke]['cnt'] += 1;
    //                 }

    //             }
    //         }
    //     }
    //     unset($list['32']);
    //     return $list;
    // }

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

//     /**
//      * 根据交易类型返回数据不存在补0 
//      * @param $data array 交易记录
//      * @param $type array 交易类型
//      * @return array
//      */
//     protected function get_arr_diff($data=array(),$type=array()){
// //        $tradeType = array();
// //        $trade = array();
// //        foreach ($data as $v){
// //            $tradeType[] = $v['type'];
// //            $trade[$v['type']]['money'] = $v['total_money'];
// //            $trade[$v['type']]['cnt']  = $v['cnt'];
// //        }
// //        //无记录的返回默认值
// //        $diff = array_diff($type,$tradeType);
// //        if(!empty($diff)){
// //            foreach ($diff as $v){
// //                $trade[$v]['money'] = "0.00";
// //                $trade[$v]['cnt']  = "0";
// //            }
// //        }
//         $trade = array();
//         foreach ($type as $v){
//             $trade[$v]['money'] = "0.00";
//             $trade[$v]['cnt']  = "0";
//         }

//         foreach ($data as $v){
//             $trade[$v['type']]['money'] = $v['total_money'];
//             $trade[$v['type']]['cnt']  = $v['cnt'];
//         }

//         return $trade;
//     }

    /**
     * 获取总报表配置
     * @return bool|mixed|void
     */
    public function getSys(){
        $sql = "SELECT value FROM `un_collocated_functions` WHERE `type` = 1 AND `relation` = {$this->admin['userid']} ORDER BY `id` DESC";
        $res = O('model')->db->getOne($sql);
        return $res;
    }

    /**
     * 设置总报表配置
     * @return bool|mixed|void
     */
    public function setSys(){
        if(!empty($_POST['data'])){
            //删除以前相关配置
            $sql = "DELETE FROM `un_collocated_functions` WHERE (`type`= 1 AND relation = {$this->admin['userid']})";
            O('model')->db->query($sql);
            $data = implode(',',$_POST['data']);
            //插入相关配置
            $sql = "INSERT INTO `un_collocated_functions` ( `name`, `value`, `type`, `relation`) VALUES ( '总报表功能配置', '{$data}', 1, {$this->admin['userid']})";
            $res = O('model')->db->query($sql);
            if($res){
                $msg = array(
                    'status' => true,
                    'msg' => "设置成功!",
                    'code' => 0,
                );
                echo json_encode($msg);
            }else{
                $msg = array(
                    'status' => false,
                    'msg' => "设置失败!",
                    'code' => 1,
                );
                echo json_encode($msg);
            }
        }else{
            $msg = array(
                'status' => false,
                'msg' => "请选择相关配置!",
                'code' => 2,
            );
            echo json_encode($msg);
        }
        return ;
    }

    /**
     * 总报表
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function export(){
        $start_date = date("Y-m-d",mktime(0, 0 , 0,date("m")-1,1,date("Y")));
        $end_date = date("Y-m-d",mktime(23,59,59,date("m") ,0,date("Y")));
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date." 23:59:59");
        $where = " addtime BETWEEN {$start_time} and {$end_time}";

        //首充值人数 首充总额
        //$sql3 = "SELECT COUNT(*) AS num, SUM(total_money) as money FROM (SELECT nums as n, total_money FROM (SELECT COUNT(l.user_id) AS nums, SUM(money) AS total_money FROM un_account_log AS l LEFT JOIN un_user AS u ON u.id = l.user_id WHERE" . $where . " AND l.type = 10 AND u.reg_type NOT IN (8,9,11) GROUP BY l.user_id) AS A WHERE nums = 1) as N";
        $s = "select user_id,money from un_account_recharge where $where and status = 1 GROUP BY user_id";
        $sql3 = "SELECT count(*) as num,sum(money) as money from ($s) infos";
        $recharge = O('model')->db->getOne($sql3);

        //首提现人数 首提总额
        //$sql4 = "SELECT COUNT(*) AS num, SUM(total_money) as money FROM (SELECT nums as n, total_money FROM (SELECT COUNT(l.user_id) AS nums, SUM(money) AS total_money FROM un_account_log AS l LEFT JOIN un_user AS u ON u.id = l.user_id WHERE" . $where . " AND l.type = 11 AND u.reg_type NOT IN (0,8,9,11) GROUP BY l.user_id) AS A WHERE nums = 1) as N";
//        $sql4 = "SELECT COUNT(DISTINCT user_id) AS num, SUM(money) AS money FROM un_account_log AS l WHERE" . $where . " AND l.type = 11 AND `remark` LIKE '%该用户为首次提现%' AND reg_type NOT IN (0,8,9,11)";
        $s = "select user_id,money from un_account_cash where $where and status = 1 GROUP BY user_id";
        $sql4 = "SELECT count(*) as num,sum(money) as money from ($s) infos";;
        $cash = O('model')->db->getOne($sql4);

        //交易类型
        $trade = $this->model2->getTrade();

        //交易流水
        $trades = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds']);

        //投注人数
        $betting_num['num'] = $this->getCntUser($start_date,$end_date,13);

        $sql = "SELECT count(id) as num FROM un_user WHERE reg_type NOT IN (0,8,9,11) AND regtime BETWEEN {$start_time} and {$end_time}";//统计注册人数
        $total_user = O('model')->db->result($sql);

        $time = date("Y-m",mktime(0, 0 , 0,date("m")-1,1,date("Y")));
        $data = array(
            'time' => $time,//时间
            'total_num' => $total_user,//注册人数
            'betting_num' => $betting_num['num'],//投注人数
            'recharge_num' => $recharge['num'],//首存人数
            'cash_num' => $cash['num'],//首提人数
            'recharge_money' => $recharge['money']?$recharge['money']:'0.',//首存总额
            'cash_money' => $cash['money']?$cash['money']:'0',//首提总额
            'recharge' => $trades['10'],//充值总额
            'cash' => $trades['11'],//提现总额
            'betting_money' => $trades['13'] - $trades['14'],//投注总额
            'award_money' => $trades['12'] - $trades['120'],//中奖总额-回滚
            'selfBackwater_money' => $trades['19'],//个人返水总额
            'directlyBackwater_money' => $trades['20'],//直属会员返水总额
            'teamBackwater_money' => $trades['21'],//团队返水总额
            'other_money' => $trades['18'] + $trades['32'] + $trades['1000'] + $trades['999'] + $trades['998'] + $trades['997'] + $trades['995'] + $trades['994'] + $trades['993'] + $trades['992'],//其他支出 返利赠送 + 额度调整 + 大转盘 + 博饼 + 圣诞 + 红包 + 九宫格 + 平台任务 + 福袋 + 刮刮乐 OK
            'profit_money' => $trades['13'] - ($trades['12'] + $trades['14'] + $trades['19'] + $trades['20'] + $trades['21'] + $trades['18'] + $trades['32'] + $trades['66'] + $trades['1000'] + $trades['999'] + $trades['998'] + $trades['997'] + $trades['995'] + $trades['994'] + $trades['993'] + $trades['992']-$trades['120']),//盈利总额 投注-(中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+九宫格995+平台任务994+福袋993+刮刮乐992-回滚) OK
            'rebate_money' => $trades['18'],//天天返利 返利赠送
            'adjust_money' => $trades['32'],//额度调整总额
            'zhuan_pan_money' => $trades['1000'] ? : 0,   //大转盘
            'bo_bing_money' => $trades['999'] ? : 0,      //博饼
            'christmas_money' => $trades['998'] ? : 0,    //圣诞
            'nine_gong_money' => $trades['995'] ? : 0,    //九宫格
            'task_money' => $trades['994'] ? : 0,    //平台任务
            'lucky_bag_money' => $trades['993'] ? : 0,    //福袋
            'scratch_money' => $trades['992'] ? : 0,    //刮刮乐
            'hong_bao_money' => $trades['997'] ? : 0,    //红包
            'betting_profit_money' => $trades['13'] - $trades['14'] - $trades['12'],//平台盈利(投注 - 撤单 - 中奖)
            'recharge_profit_money' => $trades['10'] - $trades['11'],//充值盈利(充值 - 提现 )
        );
        $title = array(
            "时间",//注册人数
            "注册人数",//注册人数
            "投注人数",//投注人数
            "首存人数",//首存人数
            "首提人数",//首提人数
            "首存总额",//首存总额
            "首提总额",//首提总额
            "充值总额",//充值总额
            "提现总额",//提现总额
            "投注总额",//投注总额
            "中奖总额",//中奖总额
            "个人返水总额",//个人返水总额
            "直属会员返水总额",//直属会员返水总额
            "团队返水总额",//团队返水总额
            "其他支出",//其他支出 返利赠送 + 额度调整 + 大转盘 + 博饼 + 圣诞 + 红包 + 九宫格 + 平台任务
            "盈利总额",//盈利总额 投注-(中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+九宫格995+平台任务994+福袋993+刮刮乐992-回滚)
            "天天返利",//天天返利 返利赠送
            "额度调整总额",//额度调整总额
            "大转盘",//大转盘
            "博饼",//博饼
            "双旦",//双旦
            "九宫格",//九宫格
            "平台任务",//平台任务
            "福袋",//
            "刮刮乐",//
            "红包",//红包
            "平台盈利",//平台盈利
            "充值盈利",//充值盈利(充值 - 提现 )
        );
        lg('export',var_export(array(
            '$data'=>$data,
        ),1));
        $filename = $time."流水报表";
        exportexcel(array($data),$title,$filename);
    }

    /**
     * 每日报表查询当日投注盈亏
     */
    public function getDayProfitLoss()
    {
        $day = $_REQUEST['day'];
        if (!empty($day)) {
            $startTime = strtotime($day);
            $endTime = $startTime+86400;
            $where = "o.reg_type not in(0,8,9,11) and o.state = 0 and o.is_legal = 1 and o.addtime between '{$startTime}' and '{$endTime}'";
            $countSql = "select sum(money) as money, sum(award) as award, u.username, u.id, sum(money) - sum(award) as profit_loss from #@_orders o left join #@_user u on u.id=o.user_id where $where group by o.user_id ORDER by profit_loss desc";
            $list = $this->db->getall($countSql);
        }
        include template('reporting-getDayProfitLoss');
    }
    
    /**
     * 获取本Id下的团队成员ID
     * @return mixed sql
     */
    public function getMemberIds($userId) {
        $sql = "SELECT id FROM un_user WHERE parent_id = " . $userId;
        $arrId = O('model')->db->result($sql);
        if ($arrId) {
            foreach ($arrId as $v) {
                $arrIds = $this->getMemberIds($v['id']);
                $arrId = array_merge($arrId, $arrIds);
            }
        }
    
        return $arrIds;
    }
    
    /**
     * ID排序
     * @param array $arrUserId 用户ID数据
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @param int $pageSize 每页显示条数
     * @param int $type 1，按盈亏倒叙（大-小）3,按投资金额倒叙（大小）
     * @return  array
     */
    public function getGroupIds(&$arrUserId, $start_date, $end_date, $page = 1, $pageSize = 10, $flag = 0)
    {
        $start_time = strtotime($start_date. ' 00:00:00');
        $end_time = strtotime($end_date. ' 23:59:59');
        $start_date = $start_date . ' 00:00:00';
        $end_date = $end_date . ' 23:59:59';
        $useData = [];
        $count = 0;
        $useIds = [];
        $tradeStr = '12,14,18,19,20,21,66,997,998,999,995,994,993,992,1000';  //交易类型
        $userList = [];
        
        //今天实时数据
        if($start_time >= strtotime(date("Y-m-d 00:00:00"))){
            $start_time1 = strtotime(date("Y-m-d 00:00:00"));
            $end_time1 = strtotime(date("Y-m-d 23:59:59"));
        
            $sql = "SELECT SUM(IF(`type` = 13,`money`,0)) as betting_money, SUM(IF(`type` = 14,`money`,0)) as ubetting_money, SUM(IF(`type` = 120,`money`,0)) as back_money, SUM(IF(find_in_set(`type`, '{$tradeStr}') > 0,`money`,0)) as profit_money FROM `un_account_log` WHERE `addtime` BETWEEN {$start_time1} and {$end_time1} AND `reg_type` NOT IN (0,8,9,11)";
        
            foreach ($arrUserId as $k => $vdatas) {
                $sqls =  $sql;
                $sqls .=  " AND `user_id` IN ({$vdatas['team_id']})";
                $tradeLog = O('model')->db->getone($sqls);
                $userList[$vdatas['uid']] = $tradeLog;
            }
        } elseif($start_time < strtotime(date("Y-m-d 00:00:00")) && $end_time >= strtotime(date("Y-m-d 00:00:00"))){   //之前到今天数据
            $start_time1 = strtotime(date("Y-m-d 00:00:00"));
            $end_time1 = strtotime(date("Y-m-d 23:59:59"));
        
            $sql1 = "SELECT SUM(IF(`type` = 13,`money`,0)) as betting_money, SUM(IF(`type` = 14,`money`,0)) as ubetting_money, SUM(IF(`type` = 120,`money`,0)) as back_money, SUM(IF(find_in_set(`type`, '{$tradeStr}') > 0,`money`,0)) as profit_money
                     FROM `un_account_log` WHERE `addtime` BETWEEN {$start_time1} and {$end_time1} AND `reg_type` NOT IN (0,8,9,11)";
            $sql2 = "SELECT SUM(IF(`type` = 13,`money`,0)) as betting_money, SUM(IF(`type` = 14,`money`,0)) as ubetting_money, SUM(IF(`type` = 120,`money`,0)) as back_money, SUM(IF(find_in_set(`type`, '{$tradeStr}') > 0,`money`,0)) as profit_money 
                     FROM `un_daily_flow` WHERE `addtime`  BETWEEN '{$start_date}' and '{$end_date}'";

            foreach ($arrUserId as $k => $vdatas) {
                $tradeLog = [];

                $sqls1 =  $sql1;
                $sqls1 .=  " AND `user_id` IN ({$vdatas['team_id']})";
                $tradeLog1 = O('model')->db->getone($sqls1);
                $sqls2 =  $sql2;
                $sqls2 .=  " AND `user_id` IN ({$vdatas['team_id']})";
                $tradeLog2 = O('model')->db->getone($sqls2);

                $tradeLog['betting_money']  = $tradeLog1['betting_money'] + $tradeLog2['betting_money'];
                $tradeLog['ubetting_money'] = $tradeLog1['ubetting_money'] + $tradeLog2['ubetting_money'];
                $tradeLog['back_money']     = $tradeLog1['back_money'] + $tradeLog2['back_money'];
                $tradeLog['profit_money']   = $tradeLog1['profit_money'] + $tradeLog2['profit_money'];

                $userList[$vdatas['uid']] = $tradeLog;
            }
        } else {
             $sql = "SELECT SUM(IF(`type` = 13,`money`,0)) as betting_money, SUM(IF(`type` = 14,`money`,0)) as ubetting_money, SUM(IF(`type` = 120,`money`,0)) as back_money, SUM(IF(find_in_set(`type`, '{$tradeStr}') > 0,`money`,0)) as profit_money 
                     FROM `un_daily_flow` WHERE `addtime` BETWEEN '{$start_date}' and '{$end_date}'";

            foreach ($arrUserId as $k => $vdatas) {
                $sqls =  $sql;
                $sqls .=  " AND `user_id` IN ({$vdatas['team_id']})";
                $tradeLog = O('model')->db->getone($sqls);
                $userList[$vdatas['uid']] = $tradeLog;
            }
        }

        $profit = [];
        $betting = [];
        $sortId = [];
        foreach ($userList as $ky => $va) {
            if ($flag < 5 && ($va['profit_money'] - $va['betting_money'] - $va['back_money']) != 0) {
                $useData[] = ['id' => $ky, 'betting_money' => $va['betting_money'], 'profit_money' => ($va['profit_money'] - $va['betting_money'])];
                $profit[]  = $va['profit_money'] - $va['betting_money'] - $va['back_money'];
                $betting[] = $va['betting_money'] - $va['ubetting_money'];
                $sortId[]  = $ky;
            }elseif ($flag > 4 && ($va['betting_money'] - $va['ubetting_money']) != 0) {
                $useData[] = ['id' => $ky, 'betting_money' => $va['betting_money'], 'profit_money' => ($va['profit_money'] - $va['betting_money'])];
                $profit[]  = $va['profit_money'] - $va['betting_money'] - $va['back_money'];
                $betting[] = $va['betting_money'] - $va['ubetting_money'];
                $sortId[]  = $ky;
            }
        }

        //多维数组排序方法
        if ($flag == 0 || $flag == 1) {
            array_multisort($profit,SORT_ASC,$betting,SORT_ASC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 2) {
            array_multisort($profit,SORT_ASC,$betting,SORT_DESC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 3) {
            array_multisort($profit,SORT_DESC,$betting,SORT_ASC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 4) {
            array_multisort($profit,SORT_DESC,$betting,SORT_DESC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 5) {
            array_multisort($betting,SORT_ASC,$profit,SORT_ASC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 6) {
            array_multisort($betting,SORT_ASC,$profit,SORT_DESC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 7) {
            array_multisort($betting,SORT_DESC,$profit,SORT_ASC,$sortId,SORT_ASC,$useData);
        } elseif ($flag == 8) {
            array_multisort($betting,SORT_DESC,$profit,SORT_DESC,$sortId,SORT_ASC,$useData);
        } else {
            array_multisort($profit,SORT_ASC,$betting,SORT_ASC, $sortId,SORT_ASC,$useData);
        }

        $ucount = count($useData);
        
        if ($ucount < $page * $pageSize) {
            $count = $ucount;
        } else {
            $count = $page * $pageSize;
        }
        
        for ($i = (($page -1) * $pageSize) ; $i < $count; $i++) {
            $userIds[] = $useData[$i]['id'];
        }

        return ['user_ids' => $userIds, 'ucount' => $ucount];
    }
    
    
    /**
     * 单用户总报表统计
     * @param array $arrUserId 用户ID数据
     * @param start_time string 起始时间
     * @param end_time string 结束时间
     * @param int $pageSize 每页显示条数
     * @param int $type 1，按盈亏倒叙（大-小）3,按投资金额倒叙（大小）
     * @return  array
     */
    public function getUserDetail(&$arrUserId, $start_date, $end_date)
    {
        $activeTime = SYS_TIME - (86400*3); //24小时内登录的，计算活跃人数，新注册用户

        $arrTeamIds = array_merge($arrUserId['team_id'], $arrUserId['own_id']);
        $TeamIds = array_column($arrTeamIds, 'user_id');
        $teamId = implode(',',$TeamIds);

//        dump($arrUserId);
//        dump($TeamIds);
//        dump($teamId);

        //团队登录注册活跃度
        $sql = "SELECT SUM(IF(`logintime` > {$activeTime},1,0)) as online,SUM(IF(`regtime` > {$activeTime},1,0)) as reg FROM `un_user` WHERE `id` IN ({$teamId})";
//        dump($sql);
        $teamUser = O('model')->db->getOne($sql);

        //交易类型
        $trade = $trade = $this->model2->getTrade();
        $ids = implode($trade['tranTypeIds'],',');
        
        //团队交易记录
        $teamTradeType = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds'],$teamId);
        //自身交易记录 orders表
        $tradeType = $this->model2->getTradeLog($start_date,$end_date,$trade['tranTypeIds'], $arrUserId['uid']);
        //自身信息
        $sql3 = "SELECT u.id AS uid, u.username, FROM_UNIXTIME(u.regtime, '%Y-%m-%d %H:%i:%S') AS regtime, u.logintime FROM un_user AS u WHERE u.id = {$arrUserId['uid']}";
        $self = O('model')->db->getOne($sql3);
        $data = array();
        $data['uid'] = $arrUserId['uid'];//id
        $data['username'] = $self['username'];//账户
        $data['regtime'] = $self['regtime'];
        $data['online'] = $teamUser['online'];//活跃人数
        $data['reg'] = $teamUser['reg'];    //新注册人数
        $data['team'] = count($TeamIds);    //团队人数
        $data['directly'] = count($arrUserId['own_id']) - 1; //直属会员人数
        $data['selfBackwater'] = $tradeType['19']; //自身返水
        $data['directlyBackwater'] = $tradeType['20']; //直属会员返水
        $data['teamBackwater'] = $tradeType['21']; //团队返水
        $data['team_Betting'] = round($teamTradeType['13'] - $teamTradeType['14'], 2); //团队会员投注-测单
        $data['team_award'] = round($teamTradeType['12'] - $teamTradeType['120'], 2);  //团队会员中奖-回滚

        //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+九宫格995+平台任务994+福袋993+刮刮乐992)-投注-回滚
        $data['profit'] = round(($teamTradeType['12'] + $teamTradeType['14'] + $teamTradeType['19'] + $teamTradeType['20'] + $teamTradeType['21'] + $teamTradeType['18'] + $tradeType['32'] + $teamTradeType['66'] + $teamTradeType['1000'] + $teamTradeType['999'] + $teamTradeType['998'] + $teamTradeType['997'] + $teamTradeType['995'] + $teamTradeType['994'] + $teamTradeType['993'] + $teamTradeType['992']) - $teamTradeType['13'] - $teamTradeType['120'], 2);
        //$data['profit'] = ($tradeType['12'] + $tradeType['14'] + $tradeType['19'] + $tradeType['20'] + $tradeType['21'] + $tradeType['18'] + $tradeType['32'] + $tradeType['66'] + $tradeType['1000'] + $tradeType['999'] + $tradeType['998'] + $tradeType['997']) - $tradeType['13'] - $tradeType['120']; //盈利: (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997)-投注-回滚

        $data['profit_2'] = round($teamTradeType['12'] + $teamTradeType['14'] - $teamTradeType['13'] - $teamTradeType['120'], 2); //投注盈利: 中奖+撤单-投注-回滚
        $data['create_time']=time();

        return $data;
    }
    
    //预处理统计函数
    public function dealUserInfo()
    {
        include template('show-deal-user-info');
    }
    
    //统计
    public function startStatistics()
    {
        $ret = [];
        $type = $_REQUEST['type'];
        $model = D('reporting');
    
        if (empty($type)) {
            echo json_encode(['code' => 1, 'msg' => '参数不能为空！']);
            return;
        }
        
        if ($type == 'order') {   //订单数据统计
            $ret = $model->orderStatistics();
        }elseif ($type == 'recharge') {  //充值数据统计
            $ret = $model->rechargeStatistics();
        }elseif ($type == 'drawal') {    //提现数据统计
            $ret = $model->drawalStatistics();
        }
    
        echo json_encode($ret);
        return;
    }
    
    /**
     * 会员报表详情(修改）
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function getMemberDetails($id,$start_date,$end_date){
        //交易类型
        $trade = $this->model2->getTrade();
        //直属 会员
        $sql = "SELECT id FROM un_user WHERE parent_id={$id}";
        $directlyIds = O('model')->db->getAll($sql);
    /*
        //团队会员
        $res = $this->model2->teamLists($id);
        $teamIds = array();//团队会员id
        foreach ($res as $v){
            $teamIds[] = $v['id']; //团队人数
        }
    
        //团队交易记录
        $STeamIds = implode($teamIds,',');
        $teamTradeType = $this->getTradeLog($start_date,$end_date,$trade['tranTypeIds'],$STeamIds);
    */
        //自身交易记录 orders表
        $tradeType = $this->getTradeLog($start_date,$end_date,$trade['tranTypeIds'],$id);

        //初始化redis
       // $redis = initCacheRedis();
       // $backwater = $redis -> hMGet("Config:100012",array('value'));
        //关闭redis链接 
       // deinitCacheRedis($redis);
    
        //自身信息
        $sql3 = "SELECT u.id, u.username, u.nickname, u.weixin, u.logintime, a.money FROM un_user AS u LEFT JOIN un_account AS a ON u.id = a.user_id WHERE u.id = {$id}";
        $self = O('model')->db->getOne($sql3);
    
        $data = array();
        $data['uid'] = $id;
        $data['username'] = $self['username'];
        $data['offline'] = ceil((SYS_TIME-$self['logintime'])/86400);//无活跃天数
        $data['money'] = $self['money'];//账户
        $data['weixin'] = $self['weixin'];//微信
        $data['recharge'] = $tradeType['10']; //充值
        $data['cash'] = $tradeType['11']; //提现
        $data['profit_recharge'] = round($tradeType['10'] - $tradeType['11'], 2); //充值盈亏
        $data['betting'] = round($tradeType['13'] - $tradeType['14'], 2); //投注
        $data['totalBackwater'] = round($tradeType['19'] + $tradeType['20'] + $tradeType['21'], 2); //返水
        $data['directly'] = count($directlyIds); //直属会员人数
        //团队盈亏 (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+九宫格995+平台任务994+福袋993+刮刮乐992)-投注-回滚
        $data['profit_total'] = round(($tradeType['12'] + $tradeType['14'] + $tradeType['19'] + $tradeType['20'] + $tradeType['21'] + $tradeType['18'] + $tradeType['32'] + $tradeType['66'] + $tradeType['1000'] + $tradeType['999'] + $tradeType['998'] + $tradeType['997'] + $tradeType['995'] + $tradeType['994'] + $tradeType['993'] + $tradeType['992']) - $tradeType['13'] - $tradeType['120'], 2);
        //$data['team_Betting'] = $teamTradeType['13'] - $teamTradeType['14']; //团队会员投注
       // $data['profit_team'] = ($teamTradeType['12'] + $teamTradeType['14'] + $teamTradeType['19'] + $teamTradeType['20'] + $teamTradeType['21'] + $teamTradeType['18'] + $teamTradeType['32'] + $teamTradeType['66'] + $teamTradeType['1000'] + $teamTradeType['999'] + $teamTradeType['998'] + $teamTradeType['997'] + $teamTradeType['996']) - $teamTradeType['13'] - $teamTradeType['120']; //团队盈亏 (中奖+撤单+自身返水+直属会员返水+团队返水+充值赠送+额度调整+分享反利+大转盘1000+博饼999+圣诞998+红包997+独立送彩金996)-投注-回滚
        $data['profit_Betting'] = round(($tradeType['12'] + $tradeType['14']) - $tradeType['13']- $tradeType['120'], 2);//投注盈亏: (中奖+撤单)-投注-回滚

        return $data;
    }
    
    
    /**
     * 交易记录
     * @param $start_date string  起始日期
     * @param $end_date string  结束日期
     * @param $uids string  用户
     * @param $type array  类型
     * @return array
     */
    public function getTradeLog($start_date,$end_date,$type = array(),$uids = "")
    {
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date." 23:59:59");
        //交易流水金额
        $trades = array();
        foreach ($type as $v){
            $trades[$v] = 0;
        }
    
        $users = "";
        if($uids != "" && $uids !=0){
            $users = " AND user_id IN({$uids}) ";
        }elseif($uids === 0){
            return $trades;
        }
    
        //今天实时数据
        if($end_time >= time()){
            $start_time1 = strtotime(date("Y-m-d 00:00:00"));
            $end_time1 = strtotime(date("Y-m-d 23:59:59"));
    
            //交易流水金额
            foreach ($type as $v){
//                if($v == 32){
//                    $sql = "select money from #@_account_log where addtime between {$start_time1} and {$end_time1} and type = {$v} {$users} AND reg_type NOT IN (0,8,9,11)";
//                    $tradeLog = $this->db->getall($sql);
//                    foreach ($tradeLog as $val) {
//                        if($val['money'] > 0){
//                            $trades["10"] += $val['money'];
//                        } else {
//                            $trades["11"] += abs($val['money']);
//                        }
//                    }
//                } else {
                    $sql = "SELECT IFNULL(SUM(money),0) FROM un_account_log WHERE addtime BETWEEN {$start_time1} and {$end_time1} AND `type` = {$v} {$users} AND reg_type NOT IN (0,8,9,11)";
                    $tradeLog = O('model')->db->result($sql);
                    $trades[$v] += $tradeLog;
//                }
            }
        }
    
        //历史数据
        if($start_time<strtotime(date("Y-m-d 00:00:00"))){
            $start_time2 = strtotime(date("Y-m-d 00:00:00"));
            //交易流水金额
            foreach ($type as $v){
//                if($v == 32){
//                    $sql = "select money from #@_account_log where addtime between {$start_time} and {$start_time2} and type = {$v} {$users} AND reg_type NOT IN (0,8,9,11)";
//                    $tradeLog = O('model')->db->getall($sql);
//                    foreach ($tradeLog as $val) {
//                        if($val['money'] > 0){
//                            $trades["10"] += $val['money'];
//                        } else {
//                            $trades["11"] += abs($val['money']);
//                        }
//                    }
//                } else {
                    $sql = "SELECT IFNULL(SUM(money),0) FROM `un_daily_flow` WHERE `addtime`  BETWEEN '{$start_date}' and '{$end_date}' AND `type` = {$v} {$users}";
                    //echo $sql;
                    $tradeLog = O('model')->db->result($sql);
                    $trades[$v] += $tradeLog;
//                }
            }
        }
        return $trades;
    }
    
    /**
     * 交易记录
     * @param $start_date string  起始日期
     * @param $end_date string  结束日期
     * @param $uids string  用户
     * @param $type array  类型
     * @return array
     */
    public function getCntTradeLog($start_date,$end_date, $where)
    {
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date." 23:59:59");
    
    
    
        //交易类型
        $trade = $this->model2->getTrade();
        //交易流水金额
        $trades = array();
        foreach ($trade['tranTypeIds'] as $v){
            $trades[$v] = 0;
        }
    
        //今天实时数据
        if($end_time >= time()){
            $start_time1 = strtotime(date("Y-m-d 00:00:00"));
            $end_time1 = strtotime(date("Y-m-d 23:59:59"));
    
            //交易流水金额
            foreach ($trade['tranTypeIds'] as $v){
//                if($v == 32){
//                    $sql = "SELECT ual.money FROM un_account_log ual
//                    LEFT JOIN un_user u ON ual.user_id = u.id
//                    LEFT JOIN un_user_bank b ON u.id = b.user_id AND b.state = 1
//                    WHERE ual.type = {$v} AND u.reg_type NOT IN (0,8,9,11) {$where} AND ual.addtime BETWEEN {$start_time1} AND {$end_time1}";
//                    //$sql = "select money from #@_account_log where addtime between {$start_time1} and {$end_time1} and type = {$v} AND reg_type NOT IN (0,8,9,11)";
//                    $tradeLog = $this->db->getall($sql);
//                    foreach ($tradeLog as $val) {
//                        if($val['money'] > 0){
//                            $trades["10"] += $val['money'];
//                        } else {
//                            $trades["11"] += abs($val['money']);
//                        }
//                    }
//                } else {
                    $sql = "SELECT IFNULL(SUM(ual.money),0) FROM un_account_log ual
                    LEFT JOIN un_user u ON ual.user_id = u.id
                    LEFT JOIN un_user_bank b ON u.id = b.user_id AND b.state = 1
                    WHERE ual.type = {$v} {$where} AND ual.addtime BETWEEN {$start_time1} AND {$end_time1}";
                    //$sql = "SELECT IFNULL(SUM(money),0) FROM un_account_log WHERE addtime BETWEEN {$start_time1} and {$end_time1} AND `type` = {$v} AND reg_type NOT IN (0,8,9,11)";
                    $tradeLog = O('model')->db->result($sql);
                    $trades[$v] += $tradeLog;
//                }
            }
        }
    
        //历史数据
        if($start_time < strtotime(date("Y-m-d 00:00:00"))){
            $start_time2 = $start_date . ' 00:00:00';
            $end_time2   = $end_date." 23:59:59";
            //交易流水金额
            foreach ($trade['tranTypeIds'] as $v){
//                if($v == 32){
//                    $sql = "SELECT ual.money FROM un_account_log ual
//                    LEFT JOIN un_user u ON ual.user_id = u.id
//                    LEFT JOIN un_user_bank b ON u.id = b.user_id AND b.state = 1
//                    WHERE ual.type = {$v} AND u.reg_type NOT IN (0,8,9,11) {$where} AND ual.addtime BETWEEN '{$start_time2}' AND '{$end_time2}'";
//                    // var_dump($sql);
//                    //$sql = "select money from #@_account_log where addtime between {$start_date} and {$end_date} and type = {$v} AND reg_type NOT IN (0,8,9,11)";
//                    $tradeLog = O('model')->db->getall($sql);
//                    foreach ($tradeLog as $val) {
//                        if($val['money'] > 0){
//                            $trades["10"] += $val['money'];
//                        } else {
//                            $trades["11"] += abs($val['money']);
//                        }
//                    }
//                } else {
                    $sql = "SELECT IFNULL(SUM(ual.money),0) FROM `un_daily_flow` ual
                    LEFT JOIN un_user u ON ual.user_id = u.id
                    LEFT JOIN un_user_bank b ON u.id = b.user_id AND b.state = 1
                    WHERE ual.type = {$v} {$where} AND ual.addtime BETWEEN '{$start_time2}' AND '{$end_time2}' AND u.reg_type NOT IN (0,8,9,11)";
                    //var_dump($sql);
                    //$sql = "SELECT IFNULL(SUM(money),0) FROM `un_daily_flow` WHERE `addtime`  BETWEEN '{$start_date}' and '{$end_date}' AND `type` = {$v}";
                    //echo $sql;
                    $tradeLog = O('model')->db->result($sql);
                    $trades[$v] += $tradeLog;
//                }
            }
        }
        //var_dump($trades);
    
        return $trades;
    }


    //快捷查询时间
    private function getSearchTime($quick) {
        switch ($quick){
            case 1:
                $start_time = strtotime(date("Y-m-d",strtotime("0 day")));
                $end_time = $start_time + 86399;
                break;
            case 2:
                $start_time = strtotime(date("Y-m-d",strtotime("-1 day")));
                $end_time = $start_time + 86399;
                break;
            case 3:
                $start_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));
                $end_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;
                break;
            case 4:
                $start_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;
                break;
            case 5:
                $start_time = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));
                $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                break;
            default:
                $start_time = getParame('start_time',0,date('Y-m-d'));
                $end_time = getParame('end_time',0,date('Y-m-d'));
                $start_time = strtotime($start_time);
                $end_time = strtotime($end_time . ' 23:59:59');
                break;
        }

        return [$start_time, $end_time];
    }

    public function agentReportForms() {
        $isSearch = getParame('isSearch',0,0,'int');
        $m="admin";
        $c="reporting";
        $a="agentReportForms";
        $groupList = $this->model2->getGroup();
        if($isSearch) {
            $quick = getParame('quick',0,0);
            $time = $this->getSearchTime($quick);
            $start_time_int = $time[0];
            $end_time_int = $time[1];
            $start_date = date('Y-m-d',$start_time_int);
            $end_date = date('Y-m-d',$end_time_int);
            $page = getParame('page',0,1,'int');
            $pagesize = 10;
            $offset = ($page-1) * $pagesize;

            $username = getParame('username',0);
            $group = getParame('group',0,0);
            $stype = getParame('stype',0);
            $s_type = getParame('s_type',0);
            $show_user_info = $this->admin['show_user_info'];  //管理员有没有权限查看用户敏感信息 1，有;0，没有

            $where = '';
            $rg_type = getParame('rg_type',0,0,'int');
            if($rg_type == 0) {
                $where .= " and uu.reg_type NOT IN (0, 8, 9, 11) ";
            }else{
                $where .= ' and uu.reg_type = '.$rg_type;
            }
            if($username) $where .= ' and uu.username = "'.$username.'"';
            if($group) $where .= ' and uu.group_id = '.$group;
            $left = '';
            if($stype) {
                $left = ' left join un_session us on uu.id = us.user_id and is_admin = 0';
                if($stype == 1) $where .= ' and us.sessionid is NOT NULL';         //在线
                if($stype == 2) $where .= ' and us.sessionid is null';         //离线
            }
            if($s_type == 1) $s_where = "uut.pids like CONCAT('%,',uu.id,',')";//直属
            if($s_type == 2) $s_where = "uut.pids like CONCAT('%,',uu.id,',%')";//团队

            $countSql = 'select count(*) from un_user uu '.$left.' where 1=1 '.$where;
            $listCnt = $this->db->result($countSql);
            $url = '?m=admin&c=reporting&a=agentReportForms';
            $parameData = [
                'quick' => $quick,
                'start_time' => $start_date,
                'end_time' => $end_date,
                'username' => $username,
                'stype' => $stype,
                's_type' => $s_type,
                'isSearch' => $isSearch,
            ];
            $page = new pages($listCnt, $pagesize, $url, $parameData);
            $show = $page->show();

            $listSql = 'select uu.id from un_user uu '.$left.' where 1=1 '.$where . " limit $offset,$pagesize";
            $list = $this->db->getall($listSql);
            if(!$list) {
                include template('reporting-agentReportForms');
                return false;
            }
            $user_id_arr = array_column($list, 'id');
            if(count($user_id_arr) > 1) {
                $user_id_str = " uu.id IN (".implode(',', $user_id_arr).")";
            }else {
                $user_id_str = ' uu.id ='.$user_id_arr[0];
            }

            $fields = "uu.id,uu.username,uu.regtime,uut.user_id,if(uu_a.regtime > $start_time_int AND uu_a.regtime < $end_time_int,1,0) as reg,";
            $fields .= "SUM(IF(ual.type=10,ual.money,0)) as rec_total,";            //入款额
            $fields .= "SUM(IF(ual.type=11,ual.money,0)) as cash_total,";           //出款额
            $fields .= "SUM(IF(ual.type=12,ual.money,0)) as win_total,";            //中奖
            $fields .= "SUM(IF(ual.type=13,ual.money,0)) as bet_total,";            //投注
            $fields .= "SUM(IF(ual.type=14,ual.money,0)) as cd_total,";             //撤单
            $fields .= "SUM(IF(ual.type=32,ual.money,0)) as other_total,";          //其他收入
            $fields .= "SUM(IF(ual.type in (1000,999,998,997,995,994,993,992),ual.money,0)) as hd_total,";      //活动优惠
            $fields .= "(SELECT id FROM un_user_login_log uull WHERE uull.user_id = uut.user_id AND uull.addtime > $start_time_int AND uull.addtime < $end_time_int ORDER BY	uull.id	LIMIT 1) as login,";
            $fields .= "(SELECT money FROM un_account_log ual_a WHERE ual_a.user_id = uut.user_id AND ual_a.type = 10 AND ual_a.addtime > $start_time_int AND ual_a.addtime < $end_time_int ORDER BY ual_a.id LIMIT 1) as first_rec";          //首冲
//            $fields .= "(SELECT money FROM un_account_log ual_b WHERE ual_b.user_id = uut.user_id AND ual_b.type = 11 AND ual_b.addtime > 1535731200 AND ual_b.addtime < 1535817600 ORDER BY ual_b.id LIMIT 1) as first_cash";          //首提
            $left_a = " LEFT JOIN un_user_tree uut ON uu.id = uut.user_id OR $s_where";
            $left_a .= " LEFT JOIN un_user uu_a ON uu_a.id = uut.user_id";
            $left_a .= " LEFT JOIN un_account_log ual ON uut.user_id = ual.user_id AND ual.addtime > $start_time_int AND ual.addtime < $end_time_int";
            $fetchChildSql = "SELECT $fields FROM un_user uu $left_a WHERE $user_id_str GROUP BY uu.id,uut.user_id ORDER BY uu.id,uut.user_id";

            //reg_total 注册人数
            //login_total 登录人数
            //team_user 团队总人数
            //first_rec_total 首存人数
            //first_rec_amt_total 首存额
            //first_cash_total 首提人数
            //rec_total 入款额
            //cash_total 出款额
            //win_total 中奖
            //bet_total 投注
            //cd_total 撤单
            //other_total 其他
            //hd_total 活动
            $fields_a = 'id as uid,username,regtime,sum(reg) as reg_total,count(login) as login_total,COUNT(*) as team_user,count(first_rec) as first_rec_total,sum(if(first_rec,first_rec,0)) as first_rec_amt_total';
            $fields_a .= ',sum(rec_total) as rec_total,sum(cash_total) as cash_total,sum(win_total) as win_total,sum(bet_total) as bet_total';
            $fields_a .= ',sum(cd_total) as cd_total,sum(other_total) as other_total,sum(hd_total) as hd_total';
            $fetchSql = "select $fields_a from ($fetchChildSql) infos GROUP BY infos.id";

            $datalist = $this->db->getall($fetchSql);
            $total = [];
            foreach($datalist as &$value) {
                $value['bet_total'] = ($value['bet_total'] - $value['cd_total']);

                $total['regAdd'] += $value['reg_total'];
                $total['loginAdd'] += $value['login_total'];
                $total['teamAdd'] += $value['team_user'];
                $total['firstRecAdd'] += $value['first_rec_total'];
                $total['firstRecAmtAdd'] += $value['first_rec_amt_total'];
                $total['recAmtAdd'] += $value['rec_total'];
                $total['cashAmtAdd'] += $value['cash_total'];
                $total['winAmtAdd'] += $value['win_total'];
                $total['betAmtAdd'] += $value['bet_total'];
                $total['otherAmtAdd'] += $value['other_total'];
                $total['hdAmtAdd'] += $value['hd_total'];
                $value['regtime'] = date('Y-m-d H:i:s', $value['regtime']);
                unset($value);
            }
        }

        include template('reporting-agentReportForms');
    }

    /*
     *  房间盈亏
     * */
    public function roomProfitDetail() {
        $m="admin";
        $c="reporting";
        $a="roomProfitDetail";

        //彩种列表
        $lottery_list = $this->db->getall('select id,name from un_lottery_type');
        $lottery_id = getParame('lottery_id', 0);
        $room_list = [];
        if($lottery_id) {       //房间列表
            $room_list = $this->db->getall('select id,title from un_room where lottery_type = '.$lottery_id);
        }

        $quick = getParame('quick',0,0);
        if($quick) {
            $time = $this->getSearchTime($quick);
            $start_time_int = $time[0];
            $end_time_int = $time[1];
            $start_date = date('Y-m-d H:i:s',$start_time_int);
            $end_date = date('Y-m-d H:i:s',$end_time_int);
        }else {
            $start_date = getParame('start_time', 0, date('Y-m-d 00:00:00'));
            $end_date = getParame('end_time', 0, date('Y-m-d 23:59:59'));
            $start_time_int = strtotime($start_date);
            $end_time_int = strtotime($end_date);
        }
        $s_type = getParame('s_type', 0, 1);        //1  真人  2假人   3所有
        $username = getParame('username', 0);
        $page = getParame('page',0,1,'int');
        $sort = getParame('sort', 0, 1);        //1盈亏  2投注次数 3投注金额 4中奖金额

        $isSearch = getParame('isSearch',0,0,'int');
        if($isSearch) {
            $room_id = getParame('room_id', 1, 0, 'int');

            if($sort == 1) $sort = ' order by profitAmt desc';
            if($sort == 2) $sort = ' order by betNum desc';
            if($sort == 3) $sort = ' order by betAmt desc';
            if($sort == 4) $sort = ' order by winAmt desc';

            $pagesize = 10;
            $offset = ($page-1) * $pagesize;

            $where = ' lottery_type = '.$lottery_id;
            $where .= ' and room_no = '.$room_id;
            $where .= ' and uo.addtime >= '.$start_time_int;
            $where .= ' and uo.addtime <= '.$end_time_int;
            $where .= ' and uo.state = 0';
            $where .= ' and uo.award_state != 0';
            $where .= ' and uo.is_legal = 1';
            if($username) $where .= " and uu.username = '".$username."'";
            if($s_type == 1) $where .= ' and uu.reg_type not in (0, 8, 9, 11)';
            if($s_type == 2) $where .= ' and uu.reg_type  = 11';

            $fields = 'uu.username,COUNT(*) as betNum,SUM(uo.money) as betAmt,SUM(uo.award) as winAmt,SUM(uo.award - uo.money) as profitAmt';
            $sql = "select $fields from un_orders uo left join un_user uu on uo.user_id = uu.id where $where group by uo.user_id $sort limit $offset,$pagesize";
            $datalist = $this->db->getall($sql);

            $cntSql = "select count(distinct uo.user_id) from un_orders uo left join un_user uu on uo.user_id = uu.id where $where";
            $listCnt = $this->db->result($cntSql);
            $url = '?m=admin&c=reporting&a=roomProfitDetail';
            $parameData = [
                'quick' => $quick,
                'start_time' => $start_date,
                'end_time' => $end_date,
                'username' => $username,
                's_type' => $s_type,
                'isSearch' => $isSearch,
                'lottery_id' => $lottery_id,
                'room_id' => $room_id,
                'sort' => $sort,
            ];
            $page = new pages($listCnt, $pagesize, $url, $parameData);
            $show = $page->show();
        }
        include template('reporting-roomProfitDetail');
    }
}