<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 16:06
 * desc: 钱包充提
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class AccountAction extends Action {

    /**
     * 数据表
     */
    private $model;
    private $model2;
    private $model3;
    private $model4;

    public function __construct() {
        parent::__construct();
        $this->model = D('account');
        $this->model2 = D('user');
        $this->model3 = D('userBank');
        $this->model4 = D('accountCash');
    }

    /**
     * 钱包充提
     * @method get /index.php?m=api&c=account&a=index&token=b5062b58d2433d1983a5cea888597eb6
     * @param
     * @return
     */
    public function index() {
        //验证token
        $this->checkAuth();
        if(!session::is_set('first_login')){
            //获取用户是否绑定银行信息
            $userBank = $this->getUserBank();
            session::set('first_login',1);
            $first_login = true;
        }else{
            $first_login = false;
        }
        if($this->model2->getOneCoupon('reg_type',"id=".$this->userId)['reg_type']==8){
            $tips = json_decode(D('config')->getOneCoupon('value','nid="tourist_tips"')['value'],true);
            $tips['msg'] = base64_decode($tips['msg']);
            $tips = json_encode($tips);
        }else{
            $tips = json_encode(['status'=>0]);
        }


        //账户
        $where = array(
            'user_id' => $this->userId
        );
        $res = $this->getOneAccount($where);
        if (empty($res)) {
            $res = array(
                'money' => '0.00'
            );
        }
		$res ['money_usable'] = $this->convert($res ['money']);
        $tempArr = explode('.', $res ['money_usable']);

        $money = number_format($res ['money_usable'],  strlen($tempArr[1]));

        $rtArr = $this->model2->getOneCoupon('reg_type', array('id' => $this->userId));

        $JumpUrl = $this->getUrl();
        include template('wallet/index');
    }

    /**
     * 交易记录
     * @method
     * @param
     * @return
     */
    public function getBills() {
        //验证token
        $this->checkAuth();
        if($_REQUEST['status'] == 1)
        {
            $data = array();
            $data['list'] = array();
            ErrorCode::successResponse($data);
        }
        //分页数据
        $page_cfg = $this->getConfig(100009); //获取每页展示多少数据
        $pageCnt = isset($page_cfg['value']) ? $page_cfg['value'] : 20;
        $page = (int) $_REQUEST['page'];
        $page = empty($page) ? 1 : $page;

        //交易状态列表
        $transtatus = array('1' => '处理中', '2' => '已完成');

        //交易类型列表
        $trantype = $this->getDictionary(2);

        switch ($_REQUEST['type']){
            case 1://充值：充值
                $types = "10";
                break;
            case 2://提现：冻结，成功，失败
                $types = "11,25,51";
                break;
            case 3://投注：中奖，撤单，未中奖,回滚,和局
                $types = "12,13,14,120,301";
                break;
            case 4://反水：自身，直接，团队
                $types = "19,20,21";
                break;
            case 5://返利：返利赠送，分享返现
                $types = "18,66";
                break;
            case 6://其他：额度调整，，提现手续费手续费，大转盘活动赠送元宝彩金，博饼活动赠送彩金，圣诞活动赠送彩金(双旦），红包活动(997)，九宫格(995)，平台任务(994)，福袋(993)，刮刮乐(992)赠送元宝彩金
                $types = "32,48,154,1000,999,998,997,995,994,993,992";
                break;
            default://全部
                $types = "";
        }

        //查询条件
        $where = array(
            'start_time' => $_REQUEST['start_time'],
            'end_time' => $_REQUEST['end_time'],
            'type' => $types,
            'status' => $_REQUEST['status'],
            'page' => $page,
            'pageCnt' => $pageCnt,
            'userId' => $this->userId
        );

        $list = $this->model->getBills($where);

        //获取游戏币比例
        $rmbratio = $this->getConfig('rmbratio');
        $rmbratio = $rmbratio['value'];

        //获取支付配置表
        $pay = $this->getPay();

        //获取线下支付类型
//        $payType = $this->getDictionary(10);
//
//        $payTypes = array();
//        foreach ($payType['lists'] as $k => $v){
//            $payTypes[$k] = $v;
//        }

        //获取线上支付类型
//        $payType2 = $this->getDictionary(13);
//        foreach ($payType2['lists'] as $k => $v){
//            $payTypes[$k] = $v;
//        }

        //显示+ -
        $arr = array(10, 12, 14, 18, 19, 20, 21, 51, 66, 1000,999,998,997,995,994,993,992,301);// + 充值,中奖,撤单,返利赠送,自身返水,直接会员返水,团队返水,提现失败,分享返现,大转盘活动赠送元宝彩金,薄饼活动赠送彩金,圣诞活动赠送彩金,红包活动赠送彩金,独立彩金,九宫格活动赠送彩金,平台任务赠送彩金,福袋赠送彩金,刮刮乐赠送彩金,和局
        $arr2 = array(11,13, 25, 48, 120, 154);//- 提现成功,投注,提现冻结,银行卡手续费,回滚,提现手续费（超次数）
        //32,会员额度调整 大于0 + 小于0 -

        $lists = array();
        if(!empty($list)){
            foreach ($list as $k => $v) {
                $lists[$k]['type'] = $v['type'];
                $lists[$k]['order_no'] = $v['order_num'];
                $lists[$k]['id'] = $v['id'];
                $lists[$k]['name'] = $trantype['lists'][$v['type']];
                $sql = "select f.pan_kou,f.odds,o.lottery_type from un_orders o join un_orders_football f on o.id = f.order_id where o.order_no = '{$v['order_num']}'";
                $order_infos = $this->db->getone($sql);
                $lists[$k]['lottery_type'] = $order_infos['lottery_type'];
                $lists[$k]['pan_kou'] = empty($order_infos['pan_kou']) ? "" : $order_infos['pan_kou'] ;
                $lists[$k]['odds'] = empty($order_infos['odds']) ? "" : $order_infos['odds'] ;
                $lists[$k]['addtime'] = date("Y-m-d H:i:s", $v['addtime']);
                $lists[$k]['pay_type'] = '';
                $lists[$k]['issue'] = '';

                //是否显示详情（回滚、各种活动相关）
                if (in_array($v['type'], [120,1000,999,998,997,995,994,993,992])) {
                    $lists[$k]['is_show_detail'] = 0;
                } else {
                    $lists[$k]['is_show_detail'] = 1;
                }

                if($v['type'] == 32){
                    if($v['money'] >= 0){
                        $lists[$k]['money_type'] = 1;
                        $lists[$k]['money'] = bcmul($v['money'],$rmbratio,2);
//                        $total_money += $lists[$k]['money'];
                    }else{
                        $lists[$k]['money_type'] = 2;
                        $lists[$k]['money'] = bcmul(abs($v['money']),$rmbratio,2);
//                        $total_money -= $lists[$k]['money'];
                    }
                }elseif (in_array($v['type'],$arr)){
                    $lists[$k]['money_type'] = 1;
                    $lists[$k]['money'] = bcmul($v['money'],$rmbratio,2);
//                    if($v['type'] != 51){
//                        $total_money += $lists[$k]['money'];
//                    }
                }elseif (in_array($v['type'],$arr2)){
                    $lists[$k]['money_type'] = 2;
                    $lists[$k]['money'] = bcmul($v['money'],$rmbratio,2);
//                    if($v['type'] != 25){
//                        $total_money -= $lists[$k]['money'];
//                    }
                }

                if($v['type'] == 10){
                    $sql = "SELECT payment_id, bank_name FROM `un_account_recharge` WHERE `order_sn` = '{$v['order_num']}' AND `user_id` = {$this->userId}";
                    $orderInfo = O('model')->db->getone($sql);
                    //兼容老版本数据
                    if (!empty($orderInfo['bank_name'])) {
                        $lists[$k]['pay_type'] = $orderInfo['bank_name'];
                    } else {
                        $lists[$k]['pay_type'] = $orderInfo ? $pay[$orderInfo['payment_id']]['name'] : '银联';
                    }
                    continue;
                }
                elseif(in_array($v['type'],array(12,13,301))){
                    $sql = "SELECT issue FROM `un_orders` WHERE `order_no` = '{$v['order_num']}' AND `user_id` = {$this->userId}";
                    $issue = O('model')->db->result($sql);
                    $lists[$k]['issue'] = $issue;
                }
                elseif($v['type'] == 14 || $v['type'] == 120)
                {
                    $sql = "SELECT remark FROM `un_account_log` WHERE `order_num` = '{$v['order_num']}' AND `user_id` = {$this->userId}";
                    $issue = O('model')->db->result($sql);
                    if(!empty($issue))
                    {
                        $xxx = explode(" ",$issue);
                        $lists[$k]['issue'] = $xxx[1]?:1;
                    }
                }
                //大转盘在状态处显示几等奖
                elseif($v['type'] == 1000){
                    $sql = "SELECT prize_id FROM `un_turntable_award_log` WHERE `order_num` = '{$v['order_num']}' AND `user_id` = {$this->userId}";
                    $prize_id = O('model')->db->result($sql);
                    $award_level_arr = [
                        '1' => '一等奖',
                        '2' => '二等奖',
                        '3' => '三等奖',
                        '4' => '四等奖',
                        '5' => '五等奖',
                        '6' => '六等奖',
                        '7' => '七等奖',
                        '8' => '八等奖',
                        '9' => '九等奖',
                        '10' => '十等奖',
                        '11' => '十一等奖',
                    ];
                    $lists[$k]['status_txt'] = $award_level_arr[$prize_id];
                } elseif($v['type'] == 999) {

                    //博饼活动
                    $sql = "SELECT ranking,event_num FROM `un_activity_prize` WHERE `order_num` = '{$v['order_num']}' AND `user_id` = {$this->userId} AND activity_type = 1";
                    $a = O('model')->db->getone($sql);
                    $lists[$k]['status_txt'] = "第 ".$a['event_num']." 期 获得 第 ".$a['ranking']." 名";

                } elseif($v['type'] == 998) {

                    //双旦活动
                    $sql = "SELECT prize_project,event_num FROM `un_activity_prize` WHERE `order_num` = '{$v['order_num']}' AND `user_id` = {$this->userId} AND activity_type = 2";
                    $a = O('model')->db->getone($sql);
                    $lists[$k]['status_txt'] = "第 ".$a['event_num']." 期 ".$a['prize_project'];

                } elseif($v['type'] == 997) {

                    //红包活动
                    $sql = "SELECT remark,activity_stage FROM `un_redpacket_gain_log` WHERE `order_num` = '{$v['order_num']}' AND `user_id` = {$this->userId}";
                    $tmp_info = O('model')->db->getone($sql);
                    $lists[$k]['status_txt'] = "第 {$tmp_info['activity_stage']} 期 {$tmp_info['remark']}";

                } elseif($v['type'] == 995) {

                    //九宫格活动
                    $sql = "SELECT prize_project,event_num FROM `un_activity_prize` WHERE `order_num` = '{$v['order_num']}' AND `user_id` = {$this->userId} AND activity_type = 3";
                    $a = O('model')->db->getone($sql);
                    $lists[$k]['status_txt'] = "第 ".$a['event_num']." 期 ".$a['prize_project'];

                } elseif($v['type'] == 994) {

                    //平台任务
                    $sql = "SELECT remark FROM `un_task_prize` WHERE `order_num` = '{$v['order_num']}' AND `user_id` = {$this->userId}";
                    $a = O('model')->db->getone($sql);
                    $lists[$k]['status_txt'] = $a['remark'];

                } elseif($v['type'] == 993) {

                    //福袋活动
                    $sql = "SELECT prize_project,event_num FROM `un_activity_prize` WHERE `order_num` = '{$v['order_num']}' AND `user_id` = {$this->userId} AND activity_type = 4";
                    $a = O('model')->db->getone($sql);
                    $lists[$k]['status_txt'] = "第 ".$a['event_num']." 期 ".$a['prize_project'];

                } elseif($v['type'] == 992) {

                    //刮刮乐活动
                    $sql = "SELECT prize_project,event_num FROM `un_activity_prize` WHERE `order_num` = '{$v['order_num']}' AND `user_id` = {$this->userId} AND activity_type = 5";
                    $a = O('model')->db->getone($sql);
                    $lists[$k]['status_txt'] = "第 ".$a['event_num']." 期 ".$a['prize_project'];

                }
                elseif($v['type'] == 10650) {
                    unset($lists[$k]);
                }

            }
        }
        sort($lists);
        $single_money = array();
        if(!empty($types)){
            $typeids = explode(',',$types);
            $start_time = strtotime($where['start_time']);
            $end_time = strtotime($where['end_time']." 23:59:59");
            foreach ($typeids as $v){
                $sql = "SELECT IFNULL(SUM(money),0) FROM un_account_log WHERE addtime BETWEEN {$start_time} and {$end_time} AND `type` = {$v} AND `user_id` = {$this->userId}";
                $single_money[$v]['type'] = $v;
                $single_money[$v]['money'] = number_format(abs($this->db->result($sql)), 2, '.', '');
            }
        }
        lg('get_bills',json_encode($single_money,JSON_UNESCAPED_UNICODE));

        //统计
        switch ($_REQUEST['type']){
            case 1://充值：充值
                $types = "10";
                $total_money = $single_money[10]['money'];
                break;
            case 2://提现：冻结，成功，失败
                $types = "11,25,51";
                $total_money = -$single_money[11]['money'];
                break;
            case 3://投注：中奖，撤单，未中奖,回滚,和局
                $types = "12,13,14,120,301";
                $total_money = round($single_money[12]['money'] + $single_money[14]['money'] - $single_money[13]['money']-$single_money[120]['money']-$single_money[301]['money'], 2);
                $single_money[12]['money'] = round(($single_money[12]['money']-$single_money[120]['money']),2); //中奖总额要扣除回滚的钱
                $single_money[12]['money'] = number_format(abs($single_money[12]['money']), 2, '.', '');
                break;
            case 4://反水：自身，直接，团队
                $types = "19,20,21";
                $total_money = round($single_money[19]['money'] + $single_money[20]['money'] + $single_money[21]['money'], 2);
                break;
            case 5://返利：返利赠送，分享返现
                $types = "18,66";
                $total_money = round($single_money[18]['money'] + $single_money[66]['money'], 2);
                break;
            case 6://其他：额度调整(32)，手续费(48)，提现手续费(154)，大转盘(1000)，博饼(999)，双旦(998)，红包(997)，九宫格(995)，平台任务(994)，福袋(993)，刮刮乐(992)
                $types = "32,48,154,1000,999,998,997,995,994,993,992";
                //独立送彩金和额度调整分开统计
                $single_money[32]['money'] = number_format(abs($single_money[32]['money']), 2, '.', '');
                $total_money = round($single_money[32]['money'] - $single_money[48]['money'] - $single_money[154]['money'] + $single_money[1000]['money'] + $single_money[999]['money'] + $single_money[998]['money'] + $single_money[997]['money'] + $single_money[995]['money'] + $single_money[994]['money'] + $single_money[993]['money'] + $single_money[992]['money'] , 2);
                break;
            default://全部
                $types = "";
                $total_money=0;
        }
        //var_dump($lists);
        sort($single_money);
        $data = array();
        $data['list'] = $lists;
        $data['trantype'] = $trantype['lists'];
        $data['transtatus'] = $transtatus;
        $data['single_money'] = $single_money;
        $data['total_money']['money'] = number_format(abs($total_money), 2, '.', '');
        $data['total_money']['money_type'] = $total_money>=0?1:2;
//        dump($data['single_money']);
//        dump($data['total_money']);
        ErrorCode::successResponse($data);
    }

    /**
     * 账户信息
     * @param $filed string 字段
     * @param $where mixed 条件
     * @return $res array
     */
    private function getOneAccount($where, $filed = '*') {
        $res = $this->model->getOneCoupon($filed, $where);
        return $res;
    }

    /**
     * 交易记录web
     * @return web
     */
    public function billsWeb() {
        //验证token
        $this->checkAuth();
        
        if (is_numeric($_REQUEST['type']) && $_REQUEST['type'] < 7) {
            $type = $_REQUEST['type'];
        } else {
            $type = 0;
        }
        
        if (is_numeric($_REQUEST['status']) && $_REQUEST['staus'] < 3) {
            $status = $_REQUEST['status'];
        } else {
            $status = 0;
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

        //初始化redis
        $redis = initCacheRedis();
        //$trantypes = $redis->lRange("DictionaryIds2", 0, -1);
        $page_cfg = $redis->hGetAll("Config:100009"); //获取每页展示多少数据
        $pageCnt = isset($page_cfg['value']) ? $page_cfg['value'] : 20;

         //交易类型列表
        $trantype = array(
            0 => '全部',
            1 => '充值', //充值：充值
            2 => '提现', //提现：冻结，成功，失败
            3 => '投注', //投注：中奖，撤单，未中奖
            4 => '返水', //反水：自身，直接，团队
            5 => '返利', //返利：返利赠送，分享返现
            6 => '其它', //其他：额度调整，手续费，大转盘，博饼, 双旦, 红包, 独立彩金
        );
        $statusType = [
            0 => '全部状态',
            1 => '处理中',
            2 => '已完成'
        ];
//        foreach ($trantypes as $v) {
//            $res = $redis->hMGet("Dictionary2:" . $v, array('id', 'name'));
//            if($res['id'] == 48){
//                continue;
//            }
//            $trantype[$res['id']] = $res['name'];
//        }

        
        //关闭redis
        deinitCacheRedis($redis);
        
        $backUrl = url('','account','index');

        include template('wallet/transactionRecord');
    }

    /**
     * 分发交易记录详情
     * @return redirect
     */
    public function recordDetail() {
        //验证token
        $this->checkAuth();

        //接收参数
        $id = trim($_REQUEST['id']);
        $type = trim($_REQUEST['types']);

        //交易类型
        //交易类型
        $trade = $this->getTrade();
        $backUrl = url('','account','billsWeb') . "&start_time=" . trim($_REQUEST['start_time']) . "&end_time=" . trim($_REQUEST['end_time']) . "&type=" . trim($_REQUEST['types']) . "&status=" . trim($_REQUEST['status']);
        if(!in_array($type,$trade['tranTypeIds'])){
            echo "<script>location.href=" . $backUrl . ";</script>";
        }
        $name = $trade['tranType'][$type];
        $list = $this->details();

        include template('wallet/datails');
        /*exit;
        if ($type == 10) { //充值
            header("location: " . url('', 'recharge', 'detail', array('recharge_id' => $id)));
        }
        if ($type == 11) { //提现
            header("location: " . url('', 'cash', 'detail', array('cash_id' => $id)));
        }
        if ($type == 13) { //投注
            header("location: " . url('', 'order', 'detail', array('id' => $id)));
        }*/
    }


    /**
     * 交易记录详情信息
     * @param $filed string 字段
     * @param $where mixed 条件
     * @return $res array
     */
    protected function details(){
        $type = trim($_REQUEST['type']);
        $order_sn = trim($_REQUEST['order_sn']);

        //银行
        $bank  = $this->getBank();
        $bank[1] = '微信';
        $bank[2] = '支付宝';

        //充值状态
        $rechargeStatus = array(
            0 => '未完成',
            1 => '已完成',
            2 => '驳回',
        );

        //提现状态
        $cashStatus = array(
            0 => '审请中',
            1 => '审核通过',
            2 => '审核不通过',
            3 => '取消提现',
        );

        switch ($type){
            case 10://充值
                $sql = "SELECT r.bank_name, r.order_sn, r.addtime, r.status, r.remark, r.money, u.username FROM un_account_recharge AS r LEFT JOIN un_user AS u ON u.id = r.user_id WHERE order_sn = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }
                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_sn']),
                    array('name'=>"账户",'value'=>$res['username']),
                    array('name'=>"充值方式",'value'=>$res['bank_name']),
                    array('name'=>"充值状态",'value'=>$cashStatus[$res['status']]),
                    array('name'=>"交易时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"附加码",'value'=>$res['remark']),
                    array('name'=>"充值金额",'value'=>$this->convert($res['money'])." 元宝")
                );
                break;
            case 11://提现成功
                $sql = "SELECT c.order_sn, c.addtime, c.status, c.money, c.fee, b.name, b.account, b.bank FROM un_account_cash AS c LEFT JOIN un_user_bank AS b ON b.id = c.bank_id WHERE order_sn = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }

                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_sn']),
                    array('name'=>"银行账户",'value'=>'**** **** **** '.subtext($res['account'],4,strlen($res['account'])-4)),
                    array('name'=>"提现银行",'value'=>$bank[$res['bank']]),
                    array('name'=>"提现状态",'value'=>$rechargeStatus[$res['status']]),
                    array('name'=>"交易时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"提现金额",'value'=>$this->convert($res['money'])." 元宝"),
                    array('name'=>"银行手续费",'value'=>$this->convert($res['fee'])." 元宝"),
                    array('name'=>"提现手续费",'value'=>$this->convert($res['extra_fee'])." 元宝")
                );
                break;
            case 12://中奖
                $sql = "SELECT order_num, money, use_money, addtime FROM un_account_log WHERE type = 12 AND order_num = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }

                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_num']),
                    array('name'=>"交易方式",'value'=>'中奖'),
                    array('name'=>"交易时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"交易金额",'value'=>$this->convert($res['money'])." 元宝"),
                );
                break;
            case 301: //和局
                $sql = "SELECT order_num, money, use_money, addtime FROM un_account_log WHERE type = {$type} AND order_num = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    ErrorCode::errorResponse(100029,'The data does not exist');
                }

                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_num']),
                    array('name'=>"交易方式",'value'=>'和局'),
                    array('name'=>"交易时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"交易金额",'value'=>$this->convert($res['money'])." 元宝"),
                );
                break;
            case 13: //投注
                $sql = "SELECT l.order_num, l.use_money, l.addtime, o.lottery_type, o.issue, o.money, o.way, o.award FROM un_account_log AS l LEFT JOIN un_orders AS o ON o.order_no = l.order_num WHERE l.type=13 AND l.order_num = '" . $order_sn . "'";
                $res = O('model')->db->getOne($sql);

                //北京PK10判断  Alan 2017-6-27
                switch ($res['lottery_type']) {
                    case 1:
                        $sql = "SELECT a.spare_1, a.spare_2, a.open_result FROM un_open_award AS a WHERE a.issue = '" . $res['issue'] . "'";
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 2:
                        $sql = 'select kaijianghaoma from un_bjpk10 where lottery_type = 2 and  qihao=' . $res['issue'];
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
                        $sql = 'select lottery_result from un_ssc where lottery_type = 5 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 6:
                        $sql = 'select lottery_result from un_ssc where lottery_type = 6 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 7:
                        $sql = 'select lottery_result from un_lhc where lottery_type = 7 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 8:
                        $sql = 'select lottery_result from un_lhc where lottery_type = 8 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 9:
                        $sql = 'select kaijianghaoma from un_bjpk10 where lottery_type = 9 and qihao =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 10:
                        $sql = 'select lottery_result from un_nn where lottery_type='.$res['lottery_type'].' and issue=' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    case 11:
                        $sql = 'select lottery_result from un_ssc where lottery_type = 11 and issue =' . $res['issue'];
                        $res3 = O('model')->db->getOne($sql);
                        break;
                    default:
                        $res3 = null;
                }
                //end
                if (!empty($res3)) {
                    $res = array_merge_recursive($res, $res3);
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
                        array('name' => "流水号", 'value' => $res['order_num']),
                        array('name' => "投注期号", 'value' => $res['issue']),
                        array('name' => "交易金额", 'value' => $this->convert($res['money']) . " 元宝"),
                        array('name' => "投注内容", 'value' => $res['way']),
                        array('name' => "开奖结果", 'value' => empty($res['kaijianghaoma']) ? '待开' : $res['kaijianghaoma']),
                        array('name' => "中奖金额", 'value' => $this->convert($res['award']) . " 元宝"),
                        array('name' => "即时余额", 'value' => $this->convert($res['use_money']) . " 元宝")
                    );
                } elseif(in_array($res['lottery_type'], [5,6,7,8,10,11])){
                    if($res['lottery_type']==10){
                        if (!empty($res['lottery_result'])) {
                            $spare_2 = getShengNiuNiu($res['lottery_result'],1);
                            $tmp = $spare_2['sheng'].','.($spare_2['sheng']=='红方胜'?str_replace('胜','',$spare_2['red']['lottery_niu']):str_replace('胜','',$spare_2['blue']['lottery_niu']));
                            $res['lottery_result'] = $tmp;
                        }
                    }
                    if(in_array($res['lottery_type'], [7,8])){
                        $res['lottery_result'] = preg_replace('/,(\d+)$/','+$1',$res['lottery_result']);
                    }
                    $data = array(
                        array('name' => "流水号", 'value' => $res['order_num']),
                        array('name' => "投注期号", 'value' => $res['issue']),
                        array('name' => "交易金额", 'value' => $this->convert($res['money']) . " 元宝"),
                        array('name' => "投注内容", 'value' => $res['way']),
                        array('name' => "开奖结果", 'value' => empty($res['lottery_result']) ? '待开' : $res['lottery_result']),
                        array('name' => "中奖金额", 'value' => $this->convert($res['award']) . " 元宝"),
                        array('name' => "即时余额", 'value' => $this->convert($res['use_money']) . " 元宝")
                    );
                } else {
                    $data = array(
                        array('name' => "流水号", 'value' => $res['order_num']),
                        array('name' => "投注期号", 'value' => $res['issue']),
                        array('name' => "交易金额", 'value' => $this->convert($res['money']) . " 元宝"),
                        array('name' => "投注内容", 'value' => $res['way']),
                        array('name' => "开奖结果", 'value' => empty($res['spare_1']) ? '待开' : $res['spare_1'] . " = " . $res['open_result'] . " " . $type . $type2 . $type3 . $type4),
                        array('name' => "中奖金额", 'value' => $this->convert($res['award']) . " 元宝"),
                        array('name' => "即时余额", 'value' => $this->convert($res['use_money']) . " 元宝")
                    );
                }


//                $sql = "SELECT l.order_num, l.use_money, l.addtime, o.lottery_type, o.issue, o.money, o.way, o.award, a.spare_1, a.spare_2, a.open_result FROM un_account_log AS l LEFT JOIN un_orders AS o ON o.order_no = l.order_num LEFT JOIN un_open_award AS a ON a.issue = o.issue WHERE l.type=13 AND l.order_num = '".$order_sn."'";
//                $res = O('model')->db->getOne($sql);
//                if(empty($res)){
//                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
//                }
//
//				if(is_numeric($res['way']) && strlen($res['way']) == 1){
//                    $res['way'] = '0'.$res['way'];
//				}
//                if(is_numeric($res['open_result']) && strlen($res['open_result']) == 1){
//                    $res['open_result'] = '0'.$res['open_result'];
//                }
//                $type = mb_substr($res['spare_2'], 0, 1, 'utf-8');
//                $type2 = mb_substr($res['spare_2'], 1, 1, 'utf-8');
//                if(!empty($type2)){
//                    $type2 = ', '.$type2;
//                }
//                $type3 = mb_substr($res['spare_2'], 0, 2, 'utf-8');
//                if(!empty($type3)){
//                    $type3 = ', '.$type3;
//                }
//                $type4 = mb_substr($res['spare_2'], 2, NULL, 'utf-8');
//                if(!empty($type4)){
//                    $type4 = ', '.$type4;
//                }
//                $data = array(
//                    array('name'=>"流水号",'value'=>$res['order_num']),
//                    array('name'=>"投注期号",'value'=>$res['issue']),
//                    array('name'=>"交易金额",'value'=>$this->convert($res['money'])." 元宝"),
//                    array('name'=>"投注内容",'value'=>$res['way']),
//                    array('name'=>"开奖结果",'value'=>empty($res['spare_1'])?'待开':$res['spare_1']." = ".$res['open_result']." ".$type.$type2.$type3.$type4),
//                    array('name'=>"中奖金额",'value'=>$this->convert($res['award'])." 元宝"),
//                    array('name'=>"即时余额",'value'=>$this->convert($res['use_money'])." 元宝")
//                );
                break;
            case 14://撤单
                $sql = "SELECT order_num, money, use_money, addtime FROM un_account_log WHERE type = 14 AND order_num = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }

                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_num']),
                    array('name'=>"撤单时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"撤单金额",'value'=>$this->convert($res['money'])." 元宝"),
                    array('name'=>"即时余额",'value'=>$this->convert($res['use_money'])." 元宝")
                );
                break;
            case 18://返利赠送
                $sql = "SELECT order_num, money, use_money, addtime FROM un_account_log WHERE type = 18 AND order_num = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }
                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_num']),
                    array('name'=>"赠送时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"赠送金额",'value'=>$this->convert($res['money'])." 元宝"),
                    array('name'=>"即时余额",'value'=>$this->convert($res['use_money'])." 元宝")
                );
                break;
            case 19://自身返水
                $sql = "SELECT order_num, money, use_money, addtime FROM un_account_log WHERE type = 19 AND order_num = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }
                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_num']),
                    array('name'=>"返水时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"返水金额",'value'=>$this->convert($res['money'])." 元宝"),
                    array('name'=>"即时余额",'value'=>$this->convert($res['use_money'])." 元宝")
                );
                break;
            case 20://直属返水
                $sql = "SELECT order_num, money, use_money, addtime FROM un_account_log WHERE type = 20 AND order_num = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }
                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_num']),
                    array('name'=>"返水时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"返水金额",'value'=>$this->convert($res['money'])." 元宝"),
                    array('name'=>"即时余额",'value'=>$this->convert($res['use_money'])." 元宝")
                );
                break;
            case 21://团队返水
                $sql = "SELECT order_num, money, use_money, addtime FROM un_account_log WHERE type = 21 AND order_num = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    ErrorCode::errorResponse(100029,'The data does not exist');
                }
                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_num']),
                    array('name'=>"返水时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"返水金额",'value'=>$this->convert($res['money'])." 元宝"),
                    array('name'=>"即时余额",'value'=>$this->convert($res['use_money'])." 元宝")
                );
                break;
            case 25://提现冻结
                $sql = "SELECT c.order_sn, c.addtime, c.status, c.money, c.fee, b.name, b.account, b.bank FROM un_account_cash AS c LEFT JOIN un_user_bank AS b ON b.id = c.bank_id WHERE order_sn = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }

                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_sn']),
                    array('name'=>"银行账户",'value'=>'**** **** **** '.subtext($res['account'],4,strlen($res['account'])-4)),
                    array('name'=>"提现银行",'value'=>$bank[$res['bank']]),
                    array('name'=>"提现状态",'value'=>$rechargeStatus[$res['status']]),
                    array('name'=>"交易时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"提现金额",'value'=>$this->convert($res['money'])." 元宝"),
                );
                break;
            case 32://会员额度调整
                $sql = "SELECT order_num, money, use_money, addtime FROM un_account_log WHERE type = 32 AND order_num = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }
                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_num']),
                    array('name'=>"调整时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"调整金额",'value'=>$this->convert($res['money'])." 元宝"),
                    array('name'=>"即时余额",'value'=>$this->convert($res['use_money'])." 元宝")
                );
                break;
            case 48://银行手续费
                $sql = "SELECT c.order_sn, c.addtime, c.status, c.money, c.fee, b.name, b.account, b.bank FROM un_account_cash AS c LEFT JOIN un_user_bank AS b ON b.id = c.bank_id WHERE order_sn = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }

                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_sn']),
                    array('name'=>"银行账户",'value'=>'**** **** **** '.subtext($res['account'],4,strlen($res['account'])-4)),
                    array('name'=>"提现银行",'value'=>$bank[$res['bank']]),
                    array('name'=>"提现状态",'value'=>$rechargeStatus[$res['status']]),
                    array('name'=>"交易时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"提现金额",'value'=>$this->convert($res['money'])." 元宝"),
                    array('name'=>"银行手续费",'value'=>$this->convert($res['fee'])." 元宝"),
                    array('name'=>"提现手续费",'value'=>$this->convert($res['extra_fee'])." 元宝")
                );
                break;
            case 51://提现失败
                $sql = "SELECT c.order_sn, c.addtime, c.status, c.money, c.fee, b.name, b.account, b.bank FROM un_account_cash AS c LEFT JOIN un_user_bank AS b ON b.id = c.bank_id WHERE order_sn = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }

                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_sn']),
                    array('name'=>"银行账户",'value'=>'**** **** **** '.subtext($res['account'],4,strlen($res['account'])-4)),
                    array('name'=>"提现银行",'value'=>$bank[$res['bank']]),
                    array('name'=>"提现状态",'value'=>$rechargeStatus[$res['status']]),
                    array('name'=>"交易时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"提现金额",'value'=>$this->convert($res['money'])." 元宝"),
                );
                break;
            case 66://分享反利
                $sql = "SELECT order_num, money, use_money, addtime FROM un_account_log WHERE type = 66 AND order_num = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    ErrorCode::errorResponse(100029,'The data does not exist');
                }
                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_num']),
                    array('name'=>"交易时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"反利金额",'value'=>$this->convert($res['money'])." 元宝"),
                    array('name'=>"即时余额",'value'=>$this->convert($res['use_money'])." 元宝")
                );
                break;
            case 154://提现手续费
                $sql = "SELECT c.order_sn, c.addtime, c.status, c.money, c.fee, c.extra_fee, b.name, b.account, b.bank FROM un_account_cash AS c LEFT JOIN un_user_bank AS b ON b.id = c.bank_id WHERE order_sn = '".$order_sn."'";
                $res = O('model')->db->getOne($sql);
                if(empty($res)){
                    return $data = array(array('name'=>"提示",'value'=>"没有找到您要的数据"));
                }
            
                $data = array(
                    array('name'=>"流水号",'value'=>$res['order_sn']),
                    array('name'=>"银行账户",'value'=>'**** **** **** '.subtext($res['account'],4,strlen($res['account'])-4)),
                    array('name'=>"提现银行",'value'=>$bank[$res['bank']]),
                    array('name'=>"提现状态",'value'=>$rechargeStatus[$res['status']]),
                    array('name'=>"交易时间",'value'=>date("Y-m-d H:i",$res['addtime'])),
                    array('name'=>"提现金额",'value'=>$this->convert($res['money'])." 元宝"),
                    array('name'=>"银行手续费",'value'=>$this->convert($res['fee'])." 元宝"),
                    array('name'=>"提现手续费",'value'=>$this->convert($res['extra_fee'])." 元宝")
                );
                break;
        }

       return $data;
    }

    /**
     * 交易类型
     * @return json
     */
    protected function getTrade(){
        //初始化redis
        $redis = initCacheRedis();
        $LTrade = $redis->lRange('DictionaryIds2', 0, -1);
        $tranType = array();
        foreach ($LTrade as $v){
            $res = $redis->hMGet("Dictionary2:" . $v, array('id', 'name'));
            $tranType[$res['id']] = $res['name'];
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return array('tranTypeIds'=>$LTrade,'tranType'=>$tranType);
    }

    /**
     * 银行信息列表
     * @return $bank array
     */
    private function getBank(){
        //初始化redis
        $redis = initCacheRedis();
        $bankIds = $redis->lRange("DictionaryIds1", 0, -1);

        //银行列表
        $bank = array();
        foreach ($bankIds as $v){
            $res = $redis->hMGet("Dictionary1:".$v,array('id','name'));
            $bank[$res['id']] = $res['name'];
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return $bank;
    }

    /**
     * 获取用户银行卡信息
     * @return mixed
     */
    private function getUserBank(){
        $field = "id AS bank_id, name, account, bank";
        $where = array(
            'user_id' =>$this->userId,
            'state' =>1
        );
        return $this->model3->getOneCoupon($field,$where, 'id desc');
    }


    /**
     * 第三方支付信息
     * @return array
     */
    protected function getPay(){
        //初始化redis
        $redis = initCacheRedis();
        $LPay = $redis->lRange('paymentConfigIds', 0, -1);
        $pay = array();
        foreach ($LPay as $v){
            $pay[$v] = $redis->hMGet("paymentConfig:" . $v, array('id','bank_id', 'config','type','name'));
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return $pay;
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
}
