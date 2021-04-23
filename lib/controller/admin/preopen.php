<?php



!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');

include S_CORE . 'class' . DS . 'pages.php';



/**

 * 后台预开奖控制器

 * 2018-03-29

 */

class PreopenAction extends Action

{

    public $lottery_type_map = [

        '8'  => '急速六合彩',

        '6'  => '三分彩',

        '9'  => '急速赛车',

        '10' => '百人牛牛',

        '11' => '分分彩',

        '13' => '欢乐骰宝',

        '14' => '分分PK10'

    ];



    public function __construct()

    {

        parent::__construct();

    }



    /**

     * 预开奖列表

     * 2018-03-29

     */

    public function list()

    {



        //分页带上的条件

        $params = $_REQUEST;

        unset($params['m']);

        unset($params['c']);

        unset($params['a']);



        //默认显示急速六合彩

        $current_lottery_type = intval($params['lottery_type']) ? : 8;



        $where = " WHERE lottery_type = {$current_lottery_type} ";



        $group_by = 'GROUP BY issue';



        // $sql = "SELECT COUNT(*) AS rows_cnt FROM un_pre_open {$where} {$group_by} ";

        $sql = "SELECT COUNT(*) AS rows_cnt FROM 

            (SELECT issue FROM un_pre_open {$where} {$group_by}) AS tmp_tbl";



        $countInfo = $this->db->getOne($sql);

        $listCnt = $countInfo['rows_cnt'];

        $pagesize = 20;

        $url = '?m=admin&c=preopen&a=list';

        $page = new pages($listCnt, $pagesize, $url, $params);

        $show = $page->show();



        $page_start = $page->offer;

        $page_size = $pagesize;



        $limit = "limit {$page_start},{$page_size}";



        //查询列表

        // $sql2 = "SELECT {$field} FROM un_pre_open {$where} {$group_by} ORDER BY id DESC {$limit}";

        $sql2 = "SELECT issue FROM un_pre_open {$where} {$group_by} ORDER BY issue DESC {$limit}";



        $list = $this->db->getAll($sql2);


        $pre_open_model = D('preopen');



        //从redis里取配置数据

        $redis = initCacheRedis();



        //取彩种当前期号

        $issue_json_arr = $redis->lRange('QiHaoIds' . $current_lottery_type, 0, 0);

        $issue_info_obj = json_decode($issue_json_arr[0], true);

        $running_issue = $issue_info_obj['issue'];



        $field = 'id, lottery_type, issue, lottery_result, lottery_time, insert_time, user_id, sha_lv, use_flag ';



        foreach ($list as $each_k => $each_v) {

            $tmp_sql = "SELECT {$field} FROM un_pre_open {$where} AND issue = {$each_v['issue']}";

            $tmp_data = $this->db->getAll($tmp_sql);



            //百人牛牛结果处理，将数字转换为牌面

            if ($current_lottery_type == '10') {

                $tmp_data = $this->handle_nn($tmp_data);

            }

            //查询历史配置信息

            $tmp_history_info = $pre_open_model->fetchHistory($current_lottery_type, $each_v['issue']);

            $list[$each_k]['setting_type_then'] = $tmp_history_info['setting_type_then'];

            $list[$each_k]['percent_then'] = ($tmp_history_info['percent_then'] === null) ? '--' : $tmp_history_info['percent_then'];

            $list[$each_k]['is_preopen_running_then'] = ($tmp_history_info['is_preopen_running_then'] === null) ? '0' : '1';



            $list[$each_k]['lottery_result_list'] = array_column($tmp_data, 'lottery_result');



            $list[$each_k]['sha_lv_list'] = array_column($tmp_data, 'sha_lv');

            $list[$each_k]['use_flag_list'] = array_column($tmp_data, 'use_flag');



            //使用user_flag字段值大于0的，作为结果插入时间

            $insert_time_list = array_column($tmp_data, 'insert_time', 'use_flag');

            ksort($insert_time_list);

            $list[$each_k]['insert_time_final'] = end($insert_time_list);



            //判断是否存在手动开奖的记录

            $tmp_user_id_list = array_column($tmp_data, 'user_id');

            $list[$each_k]['has_by_hand'] = (array_sum($tmp_user_id_list) > 0) ? '1' : '0';



            //手动补单的情况

            $tmp_use_flag_list = array_column($tmp_data, 'use_flag');

            $list[$each_k]['bu_dan_flag'] = (array_sum($tmp_use_flag_list) > 0) ? '1' : '0';



            $list[$each_k]['is_issue_stop'] = intval($pre_open_model->checkIssueStop($current_lottery_type, $each_v['issue']));

        }


        $json_data = $redis->hGet('Config:pre_open_setting', 'value');



        //关闭redis链接

        deinitCacheRedis($redis);



        $json_obj = json_decode($json_data, true);



        //自开型彩种名称映射map

        $lottery_type_map = $this->lottery_type_map;



        include template('preopen-list');

    }



    /**

     * 处理牛牛的开奖结果

     * 2018-03-31

     */

    public function handle_nn($data_list)

    {

        if (! $data_list || ! is_array($data_list)) {

            return false;

        }



        //将变量值放置到新的变量里

        $new_data_list = $data_list;



        //逐个转换牌面

        foreach ($new_data_list as $each_k => $each_v) {

            $re = getShengNiuNiu($each_v['lottery_result']);

            $res = getShengNiuNiu($each_v['lottery_result'],1);

            $niu = $res['sheng']=='蓝方胜'?$res['blue']['lottery_niu']:$res['red']['lottery_niu'];

            $new_data_list[$each_k]['lottery_result'] = "{$re[2]} [{$re[0]}][{$niu}]";

        }



        return $new_data_list;

    }





    /**

     * 编辑配置表单页

     * 2018-03-29

     */

    public function editConf()

    {

        //从redis里取配置数据

        $redis = initCacheRedis();

        $json_data = $redis->hGet('Config:pre_open_setting', 'value');



        //关闭redis链接

        deinitCacheRedis($redis);



        $json_obj = json_decode($json_data, true);

        $lottery_type = intval($_REQUEST['lottery_type']);



        //拼接json对象的关联key值

        $sha_lv_key = 'sha_lv_' . $lottery_type;



        //自开型彩种名称映射map

        $lottery_type_map = $this->lottery_type_map;



        include template('preopen-editConf');

    }



    /**

     * 保存设置值

     * 2018-03-29

     */

    public function saveConf()

    {

        /*

            前端传入的值：

            lottery_type        ----------> 彩种

            percent             ----------> 彩种杀率

            setting_type        ----------> 预开模式

            is_preopen_running  ----------> 是否开启预开奖模式

        */

        $post_data = $_POST;



        //从redis里取配置数据

        $redis = initCacheRedis();

        $json_data = $redis->hGet('Config:pre_open_setting', 'value');

        $json_obj = json_decode($json_data, true);



        //获取彩种拼接json对象的key值

        $lottery_type = intval($post_data['lottery_type']);

        $sha_lv_key = 'sha_lv_' . $lottery_type;



        //覆盖写入

        $json_obj[$sha_lv_key]['percent'] = intval($post_data['percent']);

        $json_obj[$sha_lv_key]['percent_1'] = intval($post_data['percent_1']);

        $json_obj[$sha_lv_key]['percent_2'] = intval($post_data['percent_2']);

        $json_obj[$sha_lv_key]['is_preopen_running'] = intval($post_data['is_preopen_running']);

        $json_obj[$sha_lv_key]['cal_range'] = trim($post_data['cal_range']);

        $json_obj[$sha_lv_key]['setting_type'] = trim($post_data['setting_type']);



        $new_json_str = json_encode($json_obj, JSON_UNESCAPED_UNICODE);



        $update_sql = "UPDATE un_config SET value = '{$new_json_str}' WHERE nid = 'pre_open_setting' ";

        $rows = $this->db->query($update_sql);



        $time = time();

        $update_sql = "UPDATE un_order_statistics SET `bet_total`='0',`award_total`='0',`time` = '$time' WHERE `type` = '$lottery_type' ";

        $rows = $this->db->query($update_sql);

        //保存后更新redis缓存

        $this->refreshRedis('config', 'all');



        //关闭redis链接

        deinitCacheRedis($redis);



        echo json_encode(['status' => 0, 'msg' => 'OK',]);

    }



    /**

     * 停用当前期预开奖开奖

     * 2018-03-31

     */

    public function addIssueStopLog()

    {

        $post_data = $_POST;



        $insert_data = [

            'issue' => floatval($post_data['issue']),

            'lottery_type' => intval($post_data['lottery_type']),

            'user_id' => $this->admin['userid'],

        ];



        $pre_open_model = D('preopen');



        $rt = $pre_open_model->addIssueStopLog($insert_data);

        if (! $rt) {

            $json_back = ['status' => 1, 'msg' => '因网络缘故，操作失败', ];

        } else {

            $json_back = ['status' => 0, 'msg' => '', ];

        }

        echo json_encode($json_back);

    }


    public function preOpenAward()

    {

        $issue = trim($_REQUEST['issue']);

        $lottery_type = trim($_REQUEST['lottery_type']);

        $field = ' id, lottery_type, issue, lottery_result, insert_time ';

        $where = " WHERE lottery_type = {$lottery_type} ";

        $sql = "SELECT {$field} FROM un_pre_open {$where} AND issue = {$issue}";

        $data = $this->db->getOne($sql);

        $data['lottery_result'] = explode(',', $data['lottery_result']);

        //初始化redis

        $redis = initCacheRedis();

        $game = $redis->hGet("LotteryType:" . $lottery_type, 'name');

        //关闭redis链接

        deinitCacheRedis($redis);

        switch ($lottery_type) {

            case 6:

                $numberArr = ['A','B','C','D','E'];

                $minNumber = 0;

                $maxNumber = 9;

                include template('edit-open-award-cqssc');

                break;

            case 8:

                include template('edit-open-award-lhc');

                break;

            case 9:

                $numberArr = ['A','B','C','D','E','F','G','H','I','J'];

                $minNumber = 1;

                $maxNumber = 10;

                include template('edit-open-award-pc10');

                break;

            case 10:

                $result1=array();

                $result2=array();

                foreach($data['lottery_result'] as $result){

                    $result = num2poker($result);

                    $result1[0] = mb_substr($result, 0, 2);

                    $result1[1] = mb_substr($result, 2);

                    $result2[] =$result1;

                }

                $data['lottery_result'] = [

                    'A' => $result2[0],

                    'B' => $result2[1],

                    'C' => $result2[2],

                    'D' => $result2[3],

                    'E' => $result2[4],

                    'F' => $result2[5],

                    'G' => $result2[6],

                    'H' => $result2[7],

                    'I' => $result2[8],

                    'J' => $result2[9],

                ];

                $keyArr = ['方块', '梅花', '红心', '黑桃'];

                $keyArr2 = ['A','2','3','4','5','6','7','8','9','10', 'J', 'Q', 'K'];

                $numberArr = [

                    'A' => '蓝方牌一',

                    'B' => '蓝方牌二',

                    'C' => '蓝方牌三',

                    'D' => '蓝方牌四',

                    'E' => '蓝方牌五',

                    'F' => '红方牌一',

                    'G' => '红方牌二',

                    'H' => '红方牌三',

                    'I' => '红方牌四',

                    'J' => '红方牌五',

                    ];

                include template('edit-open-award-nn');

                break;

            case 11:

                $numberArr = ['A','B','C','D','E'];

                $minNumber = 0;

                $maxNumber = 9;

                include template('edit-open-award-cqssc');

                break;

            case 13:

                $numberArr = ['A','B','C'];

                $minNumber = 1;

                $maxNumber = 6;

                include template('edit-open-award-i');

                break;

            case 14:

                if (!empty($_REQUEST['issue'])) {

                    $issue = trim($_REQUEST['issue']);

                }

                $numberArr = ['A','B','C','D','E','F','G','H','I','J'];

                $minNumber = 1;

                $maxNumber = 10;

                include template('edit-open-award-pc10');

                break;

        }

    }


    public function dealPreOpendAward()

    {        

        //类型

        $lottery_type = trim($_REQUEST['lottery_type']);

        $issue = trim($_REQUEST['issue']);

        if ($lottery_type == 6 || $lottery_type == 11) {            

            $arr = array('A', 'B', 'C', 'D', 'E');

            $data = array();

            foreach ($arr as $v) {

                if (empty($_REQUEST['number' . $v]) && $_REQUEST['number' . $v] != 0) {

                    $this->ajaxReturn('', '开奖号码' . $v . '不能为空', 4005);

                }

                if (!preg_match('/^\d{1,2}$/', $_REQUEST['number' . $v])) {

                    $this->ajaxReturn('', '开奖号码' . $v . '请输入0-9的数字', 4005);

                }

                $data[] = $_REQUEST['number' . $v];

            }

    

            //将得到的数据更新到数据库中

            $final['lottery_result'] = implode($data, ',');

            $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

            $open_id = $this->db->getOne($sql);

            $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

            if ($ret) {

                echo json_encode(array('status' => 0));

            }else {

                echo json_encode(array('status' => 1));

            }            

            return;

        }else if($lottery_type == 8){

            $arr = array('A', 'B', 'C', 'D', 'E','F','G');

            $data = array();

            foreach ($arr as $v) {

                if (empty($_REQUEST['number' . $v]) && $_REQUEST['number' . $v] != 0) {

                    $this->ajaxReturn('', '开奖号码' . $v . '不能为空', 4005);

                }

                if (!preg_match('/^\d{1,2}$/', $_REQUEST['number' . $v])) {

                    $this->ajaxReturn('', '开奖号码' . $v . '请输入1-49的数字', 4005);

                }

                $data[] = $_REQUEST['number' . $v];

            }

    

            //将得到的数据更新到数据库中

            $final['lottery_result'] = implode($data, ',');

            $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

            $open_id = $this->db->getOne($sql);            

            $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

            if ($ret) {

                echo json_encode(array('status' => 0));

            }else {

                echo json_encode(array('status' => 1));

            }

            return;

        }elseif ($lottery_type == 9 || $lottery_type == 14) {

            $arr = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J");

            $data = array();

            foreach ($arr as $v) {

                if (empty($_REQUEST['number' . $v]) && $_REQUEST['number' . $v] != 0) {

                    $this->ajaxReturn('', '开奖号码' . $v . '不能为空', 4005);

                }

                if (!preg_match('/^\d{1,2}$/', $_REQUEST['number' . $v])) {

                    $this->ajaxReturn('', '开奖号码' . $v . '请输入0-9的数字', 4005);

                }

                $data[] = $_REQUEST['number' . $v];

            }

            $final['lottery_result'] = implode($data, ',');

            $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

            $open_id = $this->db->getOne($sql);

            $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

            if ($ret) {

                echo json_encode(array('status' => 0));

            }else {

                echo json_encode(array('status' => 1));

            }

            return ;

        }elseif ($lottery_type == 10) {

            $arr = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',];

            $data = [];

            foreach ($arr as $v) {

                $tmp_number_v = $_REQUEST['number' . $v];

                if (empty($tmp_number_v) && $tmp_number_v != 0) {

                    $this->ajaxReturn('', '开奖号码' . $v . '不能为空', 4005);

                }

                if (!preg_match('/^(?:方块|梅花|红心|黑桃)(?:[2-9]|10|[AJQK])$/i', $tmp_number_v)) {

                    $this->ajaxReturn('', "开奖号码{$v}【{$tmp_number_v}】不是合法的扑克牌面", 4005);

                }

                $data[] = poker2num($tmp_number_v);

            }

            

            //将得到的数据更新到数据库中

            $final['lottery_result'] = implode($data, ',');

            $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

            $open_id = $this->db->getOne($sql);

            $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

            if ($ret) {

                echo json_encode(array('status' => 0));

            }else {

                echo json_encode(array('status' => 1));

            }

            return;

        }elseif ($lottery_type == 13) {

            $arr = array('A', 'B', 'C');

            $data = array();

            foreach ($arr as $v) {

                if (empty($_REQUEST['number' . $v]) && $_REQUEST['number' . $v] != 0) {

                    $this->ajaxReturn('', '开奖号码' . $v . '不能为空', 4005);

                }

                if (!preg_match('/^\d{1,2}$/', $_REQUEST['number' . $v])) {

                    $this->ajaxReturn('', '开奖号码' . $v . '请输入1-6的数字', 4005);

                }

                $data[] = $_REQUEST['number' . $v];

            }

    

            //将得到的数据更新到数据库中

            $final['lottery_result'] = implode($data, ',');

            $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

            $open_id = $this->db->getOne($sql);

            $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

            if ($ret) {

                echo json_encode(array('status' => 0));

            }else {

                echo json_encode(array('status' => 1));

            }            

            return;

        }else {

            echo json_encode(array('status' => 1));

        }
            
        
        return;

    }


    
}