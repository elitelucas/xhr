<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 13:34
 * desc: 天天反利 玩法介绍
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class OrderAction extends Action {

    /**
     * 数据表
     */
    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = D('order');
    }

    /**
     * 投注列表
     * @method get /index.php?m=api&c=order&a=betList&token=b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @return mixed
     */
    public function betListData() {
        //验证token
        $this->checkAuth();

        //验证请求参数
        if ($_REQUEST['status'] != '' && !in_array($_REQUEST['status'], array(0, 1, 2, 3, 4))) {
            ErrorCode::errorResponse(200003, 'The transaction status does not exist');
        }

        //分页数据
        $page_cfg = $this->getConfig(100009); //获取每页展示多少数据
        $pageCnt = isset($page_cfg['value']) ? $page_cfg['value'] : 20;
        $page = (int) $_REQUEST['page'];
        $page = empty($page) ? 1 : $page;

        $where = array(
            'start_time' => $_REQUEST['start_time'],

            'end_time' => $_REQUEST['end_time'],
            'status' => $_REQUEST['status'],
            'type' => $_REQUEST['type'],
            'userId' => $this->userId,
            'page' => $page,
            'pageCnt' => $pageCnt
        );

        $list = $this->model->betList($where);

        //彩种类型
        $gameInfo = $this->getLottery();

        //交易类型列表
//        $trantype = $this->getDictionary(2);
//
        //获取游戏币比例
        $rmbratio = $this->getConfig('rmbratio');
        $rmbratio = $rmbratio['value'];

        $total_money = 0;
        $total_award = 0;
        $lists = array();
        $redis = initCacheRedis();
        foreach ($list as $k => $v) {
            $lists[$k]['id'] = $v['id'];
            $lists[$k]['lottery_type'] = $v['lottery_type'];
            $lists[$k]['issue'] = $v['issue'];
            if($v['lottery_type'] == 12){
                $sql = "select pan_kou,odds from un_orders_football where order_id = {$v['id']}";
                $order_infos = $this->db->getone($sql);
                $lists[$k]['pan_kou'] = empty($order_infos['pan_kou']) ? "" : $order_infos['pan_kou'] ;
                $lists[$k]['odds'] = empty($order_infos['odds']) ? "" : $order_infos['odds'] ;
            }
            //获取房间名
            $lists[$k]['room_name'] = $redis->hget("allroom:{$v['room_no']}", "title")?:'';
            $lists[$k]['addtime'] = date('Y-m-d H:i', $v['addtime']);
            $lists[$k]['name'] = $gameInfo[$v['lottery_type']];
            $lists[$k]['money'] = bcmul($v['money'],$rmbratio,2);
            $total_money += $lists[$k]['money'];
            $lists[$k]['award'] = bcmul($v['award'],$rmbratio,2);
            lg('orders_call_back','$lists[$k][\'award\']-------------->'.$lists[$k]['award']);
            $total_award += $lists[$k]['award'];
            $lists[$k]['way']  =$v['way'];
            if (is_numeric($v['way']) && strlen($v['way']) == 1) {
                $lists[$k]['way'] = '0' . $v['way'];
            }
            if($v['state'] == 0){
                $lists[$k]['state'] = '投注';
                if ($v['award_state'] == 0) {
                    $lists[$k]['status'] = '待开奖';
                    $lists[$k]['money_type'] = 2;
                } elseif ($v['award_state'] == 1) {
                    $lists[$k]['status'] = '未中奖';
                    $lists[$k]['money_type'] = 2;
                } elseif ($v['award_state'] == 2) {
                    $lists[$k]['status'] = '已中奖';
                    $lists[$k]['money_type'] = 2;
                }
            }else{
                $lists[$k]['state'] = '撤单';
                $lists[$k]['status'] = '已撤单';
                $lists[$k]['money_type'] = 1;
            }
        }
        deinitCacheRedis($redis);

        //起始时间
        $start_date = trim($_REQUEST['start_time']);
        //结束时间
        $end_date = trim($_REQUEST['end_time']);
        if($start_date == "all")
        {
            $start_date = date("Y-m-d");
        }
        if($end_date == "all")
        {
            $end_date = date("Y-m-d");
        }
        if (!empty($start_date) && !empty($end_date)) {
            $start_time = strtotime($start_date);
            $end_time = strtotime($end_date . " 23:59:59");
            $where = " AND addtime BETWEEN {$start_time} and {$end_time}";
        } elseif (!empty($start_date)) {
            $start_time = strtotime($start_date);
            $where = " AND addtime >= {$start_time}";
        }elseif (!empty($end_date)) {
            $end_time = strtotime($end_date . " 23:59:59");
            $where = " AND addtime <= {$end_time}";
        } else {
            $where = "";
        }

        if(!empty($_REQUEST['type']) && $_REQUEST['type']!= "all"){
            $where .=" AND lottery_type = {$_REQUEST['type']}";
        }
        $sql = "select sum(money) as money, SUM(award)AS award from un_orders  where user_id={$this->userId} AND state = 0{$where}";

        $res = O('model')->db->getOne($sql);
        if(empty($res['money'])){
            $res['money'] = 0;
        }
        if(empty($res['award'])){
            $res['award'] = 0;
        }
//        $res['money'] = $total_money;
//        $res['award'] = $total_award;

        ErrorCode::successResponse(array('list' => $lists, 'total'=>$res));
    }

    /**
     * 投注详情
     */
    public function detail() {
        //验证token
        $this->checkAuth();

        $data = $this->getDetail();
        
        $backUrl = url('','order','betRecordWeb') . "&start_time=" . trim($_REQUEST['start_time']) . "&end_time=" . trim($_REQUEST['end_time']) . "&type=" . trim($_REQUEST['types']) . "&page=" . trim($_REQUEST['page']) . "&status=" . trim($_REQUEST['status']);

        include template('wallet/orderDatails');
    }

    protected function getDetail(){
        $sql = "SELECT order_no, money, issue, addtime, state, lottery_type, way, award,award_state FROM un_orders WHERE id = ".$_REQUEST['id'];
        $res = O('model')->db->getOne($sql);
        if (empty($res)) {
            return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
        }
        $fb_data = array();
        if($res['lottery_type']==12){
            $sql = "SELECT result_bi_feng,pan_kou,odds,bi_feng FROM `un_orders_football` WHERE order_id={$_REQUEST['id']}";
            $fb_data = $this->db->getone($sql);
        }

        switch ($res['state']) {
            case 0: //投注
                $sql2 = "SELECT l.use_money FROM un_account_log AS l WHERE l.order_num = '" . $res['order_no'] . "' AND l.user_id = ".$this->userId." AND l.type = 13";
                $res2 = O('model')->db->getOne($sql2);
                if (!empty($res2)) {
                    $res = array_merge_recursive($res, $res2);
                }

                //北京PK10判断  Alan 2017-6-27
                switch ($res['lottery_type']) {
                    case 1:
                        $sql = "SELECT a.spare_1, a.spare_2, a.open_result FROM un_open_award AS a WHERE a.issue = '" . $res['issue'] . "'";
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 2:
                        $sql = 'select kaijianghaoma from un_bjpk10 where lottery_type='.$res['lottery_type'].' and qihao=' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 3:
                        $sql = "SELECT a.spare_1, a.spare_2, a.open_result FROM un_open_award AS a WHERE a.issue = '" . $res['issue'] . "'";
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 4:
                        $sql = 'select kaijianghaoma from un_xyft where qihao=' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 5:
                        $sql = 'select lottery_result from un_ssc where lottery_type = '.$res['lottery_type'].' and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 6:
                        $sql = 'select lottery_result from un_ssc where lottery_type = '.$res['lottery_type'].' and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 7:
                        $sql = 'select lottery_result from un_lhc where lottery_type = '.$res['lottery_type'].' and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 8:
                        $sql = 'select lottery_result from un_lhc where lottery_type = '.$res['lottery_type'].' and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 9:
                        $sql = 'select kaijianghaoma from un_bjpk10 where lottery_type='.$res['lottery_type'].' and qihao=' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 10:
                        $sql = 'select lottery_result from un_nn where lottery_type = '.$res['lottery_type'].' and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 11:
                        $sql = 'select lottery_result from un_ssc where lottery_type = '.$res['lottery_type'].' and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 13:
                        $sql = 'select lottery_result from un_sb where lottery_type = '.$res['lottery_type'].' and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 14:
                        $sql = 'select lottery_result from un_ffpk10 where lottery_type = '.$res['lottery_type'].' and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    default:
                        $res3 = null;
                }
                //end

                if(!empty($res3)){
                    $res = array_merge_recursive($res,$res3);
                }

                if (is_numeric($res['way']) && strlen($res['way']) == 1) {
                    $res['way'] = '0' . $res['way'];
                }
                if (is_numeric($res['open_result']) && strlen($res['open_result']) == 1) {
                    $res['open_result'] = '0' . $res['open_result'];
                }
                $type = mb_substr($res['spare_2'], 0, 1, 'utf-8');
                $type2 = mb_substr($res['spare_2'], 1, 1, 'utf-8');
                if (!empty($type2)) {
                    $type2 = ', ' . $type2;
                }
                $type3 = mb_substr($res['spare_2'], 0, 2, 'utf-8');
                if (!empty($type3)) {
                    $type3 = ', ' . $type3;
                }
                $type4 = mb_substr($res['spare_2'], 2, NULL, 'utf-8');
                if (!empty($type4)) {
                    $type4 = ', ' . $type4;
                }
                if (in_array($res['lottery_type'], array(2, 4, 9))) {
                    $data = array(
                        array('name' => "流水号", 'value' => $res['order_no']),
                        array('name' => "投注期号", 'value' => $res['issue']),
                        array('name' => "交易金额", 'value' => $this->convert($res['money']) . " 元宝"),
                        array('name' => "投注内容", 'value' => $res['way']),
                        array('name' => "开奖结果", 'value' => empty($res['award_state']) ? '待开' : $res['kaijianghaoma']),
                        array('name' => "中奖金额", 'value' => $this->convert($res['award']) . " 元宝"),
                        array('name' => "即时余额", 'value' => $this->convert($res['use_money']) . " 元宝")
                    );
                } elseif(in_array($res['lottery_type'], [5,6,7,8,10,11,13,14])){
                    if($res['lottery_type']==10){
                        if (!empty($res['lottery_result'])) {
                            $spare_2 = getShengNiuNiu($res['lottery_result'],1);
                            $tmp = $spare_2['sheng'].','.($spare_2['sheng']=='红方胜'?$spare_2['red']['lottery_niu']:$spare_2['blue']['lottery_niu']);
                            $res['lottery_result'] = $tmp;
                        }
                    }
                    if(in_array($res['lottery_type'], [7,8])){
                        $res['lottery_result'] = preg_replace('/,(\d+)$/','+$1',$res['lottery_result']);
                    }
                    $data = array(
                        array('name' => "流水号", 'value' => $res['order_no']),
                        array('name' => "投注期号", 'value' => $res['issue']),
                        array('name' => "交易金额", 'value' => $this->convert($res['money']) . " 元宝"),
                        array('name' => "投注内容", 'value' => $res['way']),
                        array('name' => "开奖结果", 'value' => empty($res['award_state']) ? '待开' : $res['lottery_result']),
                        array('name' => "中奖金额", 'value' => $this->convert($res['award']) . " 元宝"),
                        array('name' => "即时余额", 'value' => $this->convert($res['use_money']) . " 元宝")
                    );
                }else if(in_array($res['lottery_type'], array(12))){
                    $data = array(
                        array('name' => "流水号", 'value' => $res['order_no']),
//                        array('name' => "投注期号", 'value' => $res['issue']),
                        array('name' => "交易金额", 'value' => $this->convert($res['money']) . " 元宝"),
                        array('name' => "投注内容", 'value' => $res['way']),
                        array('name' => "投注赔率", 'value' => $fb_data['odds']),
                        array('name' => "投注比分", 'value' => $fb_data['bi_feng']),
                        array('name' => "投注盘口", 'value' => $fb_data['pan_kou']),
                        array('name' => "开奖结果", 'value' => empty($fb_data['result_bi_feng']) ? '待开' : $fb_data['result_bi_feng']),
                        array('name' => "中奖金额", 'value' => $this->convert($res['award']) . " 元宝"),
                        array('name' => "即时余额", 'value' => $this->convert($res['use_money']) . " 元宝")
                    );
                } else {
                    $data = array(
                        array('name' => "流水号", 'value' => $res['order_no']),
                        array('name' => "投注期号", 'value' => $res['issue']),
                        array('name' => "交易金额", 'value' => $this->convert($res['money']) . " 元宝"),
                        array('name' => "投注内容", 'value' => $res['way']),
                        array('name' => "开奖结果", 'value' => empty($res['award_state']) ? '待开' : $res['spare_1'] . " = " . $res['open_result'] . " " . $type . $type2 . $type3 . $type4),
                        array('name' => "中奖金额", 'value' => $this->convert($res['award']) . " 元宝"),
                        array('name' => "即时余额", 'value' => $this->convert($res['use_money']) . " 元宝")
                    );
                }
                
                break;
            case 1://撤单
                $data = array(
                    array('name' => "流水号", 'value' => $res['order_no']),
                    array('name' => "撤单期号", 'value' => $res['issue']),
                    array('name' => "撤单金额", 'value' => $this->convert($res['money']) . " 元宝")
                );
                break;
            default:
                return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
        }
        return $data;
    }

    /**
     * 投注记录web
     */
    public function betRecordWeb() {
        //验证token
        $this->checkAuth();
        
        $backUrl = url('','user','my');

        if (is_numeric($_REQUEST['type']) && $_REQUEST['type'] < 5) {
            $type = $_REQUEST['type'];
        } else {
            $type = 0;
        }
        
        if (is_numeric($_REQUEST['status']) && $_REQUEST['staus'] < 5) {
            $status = $_REQUEST['status'];
        } else {
            $status = 0;
        }
        
        if (is_numeric($_REQUEST['page'])) {
            $page = $_REQUEST['page'];
        } else {
            $page = 1;
        }
        
        if (trim($_REQUEST['start_time'])) {
            $start_time = trim($_REQUEST['start_time']);
        } else {
            $start_time = date('Y-m-d',time());
        }
        
        if (trim($_REQUEST['end_time'])) {
            $end_time = trim($_REQUEST['end_time']);
        } else {
            $end_time = date('Y-m-d',time());
        }

        //redis里面取彩种类型
        $redis = initCacheRedis();
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
        $gameInfo = array();
        $gameInfo[] = ['id' => 0, 'name' => '全部类型'];
        foreach ($LotteryTypeIds as $v) {
            $gameInfo[] = $redis->hGetAll("LotteryType:" . $v);
        }

        //关闭redis
        deinitCacheRedis($redis);

        //状态数组
        $statusArr = array(
            '0' => '全部状态',
            '1' => '已中奖',
            '2' => '未中奖',
            '3' => '待开奖',
            '4' => '撤单'
        );

        include template('wallet/bettingRecord');
    }

    /**
     * 获取当期所下投注
     */
    public function nowBet() {
        //验证token
        $this->checkAuth();

        //接收参数
        $issue = $_REQUEST['issue'];
        $room_no = $_REQUEST['room_no'];
        $sql = "select way,money,order_no,addtime,chase_number from un_orders where user_id = $this->userId and issue = $issue and room_no = $room_no and state = 0 and chase_number = '' order by id asc";
        $list = $this->db->getall($sql);
        //$list = $this->model->getlist('way,money,order_no', array('user_id' =>$this->userId, 'issue' => $issue, 'room_no' => $room_no, 'state' => 0), 'id ASC');
        foreach ($list as $k => $v) {
           $v['money'] = convert($v['money']);
           $v['addtime'] = date('H:i', $v['addtime']);
           $list[$k] = $v;
        }
        ErrorCode::successResponse(array('list' => $list));
    }

    /**
     * 字典表
     * @param $type 类型
     * @return array
     */
    protected function getDictionary($type){
        //初始化redis
        $redis = initCacheRedis();
        $LTrade = $redis->lRange('DictionaryIds'.$type, 0, -1);
        $tranType = array();
        foreach ($LTrade as $v){
            $res = $redis->hMGet("Dictionary".$type.":" . $v, array('id', 'name'));
            $tranType[$res['id']] = $res['name'];
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return array('ids'=>$LTrade,'lists'=>$tranType);
    }


    /**
     * 配置信息
     * @param $k
     * @return $config array
     */
    private function getConfig($k){
        //初始化redis
        $redis = initCacheRedis();
        $config = $redis->hGetAll("Config:$k");
        //关闭redis链接
        deinitCacheRedis($redis);
        return $config;
    }

    private function getLottery(){
        //redis里面取彩种类型
        $redis = initCacheRedis();
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
        $gameInfo = array();
        foreach ($LotteryTypeIds as $v) {
            $res = $redis->hMGet("LotteryType:" . $v, array('id', 'name'));
            $gameInfo[$res['id']] = $res['name'];
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return $gameInfo;
    }

}
