<?php

/**
 *
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'pages.php';

class TopupAction extends Action {

    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = D('admin/topup');
    }

    //线上充值列表
    public function topup() {
        $where = $_REQUEST; //搜索条件
        unset($where['m']);
        unset($where['c']);
        unset($where['a']);
        $where['s_time'] = empty($where['s_time']) ? date('Y-m-d') : $where['s_time'];
        $where['e_time'] = empty($where['e_time']) ? date('Y-m-d') : $where['e_time'];

        $quick = $where['quick'];
        if($where['quick']!="0"&&$where['quick']!=""){
            switch ($where['quick']){
                case 1:
                    $where['s_time'] = strtotime(date("Y-m-d",strtotime("0 day")));
                    $where['e_time'] = $where['s_time'] + 86399;
                    break;
                case 2:
                    $where['s_time'] = strtotime(date("Y-m-d",strtotime("-1 day")));
                    $where['e_time'] = $where['s_time'] + 86399;
                    break;
                case 3:
                    $where['s_time'] = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));
                    $where['e_time'] = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;
                    break;
                case 4:
                    $where['s_time'] = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                    $where['e_time'] = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;
                    break;
                case 5:
                    $where['s_time'] = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));
                    $where['e_time'] = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));
                    break;
            }
            $where['s_time'] = empty($where['s_time']) ? date('Y-m-d') : date('Y-m-d',$where['s_time']);
            $where['e_time'] = empty($where['e_time']) ? date('Y-m-d') : date('Y-m-d',$where['e_time']);
        }

        $pagesize = 20;
        $listCnt = $this->model->topupCnt($where);
        $url = '?m=admin&c=topup&a=topup';
        $page = new pages($listCnt, $pagesize, $url, $where);
        $show = $page->show();
        $where['page_start'] = $page->offer;
        $where['page_size'] = $pagesize;
        $list = $this->model->topupList($where);

        //在线充值类型
        $sql = 'SELECT PC.id,PC.name FROM un_payment_config PC RIGHT JOIN '
                . '(SELECT D.id FROM un_dictionary D LEFT JOIN un_dictionary_class DC ON D.classid=DC.id WHERE DC.id = 13) F'
                . ' ON F.id=PC.type';
        $paymentIdArr = O('model')->db->getall($sql, 'id');

        $succMoney = 0; //本页充值成功
        $dealMoney = 0; //本页待处理
        $cancMoney = 0; //本页驳回
        foreach ($list as $key => $value) {
            if ($value['status'] == 0) {
                $res = D('user')->getMusicTips($value['id'],'5,2');
                if(!empty($res)){
                    $list[$key]['verify_userid'] = $res['click_uid'];
                }
                $dealMoney += $value['money'];
            }
            if ($value['status'] == 1) {
                $succMoney += $value['money'];
            }
            if ($value['status'] == 2) {
                $cancMoney += $value['money'];
            }
            $list[$key]['payment_id'] = $paymentIdArr[$value['payment_id']]['name'];
            $list[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);
            $verify_remark = json_decode($value['verify_remark'],true);
            $list[$key]['verify_remark'] = $verify_remark['msg'];
            $list[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);
//            $admin = $this->model->adminName($value['verify_userid']);
//            $list[$key]['verify_userid'] = empty($admin) ? "admin" : $admin['username'];
        }
        //后台用户信息
        $admin = $this->getAdmin();
        $adminUid = $this->admin['userid'];
        //$tj = $this->model->onlineTJ($where); //线上充值统计
        include template('list-topup');
    }

    //充值处理
    public function dealTopup() {
        $id = $_REQUEST['id'];

        $info = $this->model->recharge($id);
        $info['addtime'] = date('Y-m-d H:i:s', $info['addtime']);
        include template('add-topup');
    }

    //确认充值
    public function submitTopup(){
        $start_time_1 = microtime(true);
        lg("run_time_log","后台用户开始审核线上充值订单");
        $log_id         = $_REQUEST['id'];
        $payment_id     = $_REQUEST['payment_id'];
        $order_sn       = $_REQUEST['order_sn'];
        $user_id        = $_REQUEST['user_id'];
        $remark         = $_REQUEST['verify_remark'];
        $recharge_money = $_REQUEST['money'];
        
        if (!is_numeric($log_id) || !is_numeric($user_id) || !is_numeric($recharge_money) || $recharge_money <= 0) {
            echo json_encode(array("rt" => 0));

            return;
        }
        $recharge_money = number_format($recharge_money, 2, '.', '');
        
        O('model')->db->query('BEGIN');
        
        try {
            
            //判断用户是否为分享注册首充
            $start_time = microtime(true);
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $user_id));
            $end_time = microtime(true);
            lg("run_time_log","1.获取用户的分享ID信息(un_user)执行时间：".getRunTime($end_time,$start_time));

            if ($shareIdArr['share_id'] != 0) {

                $start_time = microtime(true);
                $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $user_id));
                $end_time = microtime(true);
                lg("run_time_log","2.获取用户是否是首充(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));

                if (empty($rechargeRecord)) { //首充奖励
                    //初始化redis
                    $start_time = microtime(true);
                    $redis = initCacheRedis();
                    $fsConfig = $redis->HMGet("Config:cashBack",array('value'));
                    //关闭redis链接
                    deinitCacheRedis($redis);
                    $end_time = microtime(true);
                    lg("run_time_log","3.获取首充奖励配置信息(redis)执行时间：".getRunTime($end_time,$start_time));

                    $start_time = microtime(true);
                    $sql = "SELECT * FROM un_account WHERE user_id = '{$shareIdArr['share_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
                    $accountInfo = O('model')->db->getone($sql);
                    $end_time = microtime(true);
                    lg("run_time_log","4.获取分享用户资金信息(un_account)执行时间：".getRunTime($end_time,$start_time));
                    //扫码返现百分率
                    $cashback_rate = 0;
                    $cashback_list = json_decode($fsConfig['value'],true);
                    foreach ($cashback_list as $k=>$i){
                        if($i["low"]<=$recharge_money&&$recharge_money<=$i["upper"]){
                            $cashback_rate = $i["rate"];
                        }
                    }
                    $cashback_amount = bcdiv(($cashback_rate*$recharge_money),100,2);
                    $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金
            
                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => '用户id为:' . $shareIdArr['share_id'] . ' 分享奖励:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );

                    //产生充值流水
                    $start_time = microtime(true);
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    $end_time = microtime(true);
                    lg("run_time_log","5.添加分享ID的资金流水信息(un_account_log)执行时间：".getRunTime($end_time,$start_time));

                    //更新用户账户金额
                    $start_time = microtime(true);
                    $res = D('account')->save(array('money' => '+=' . $cashback_amount), array('user_id' => $shareIdArr['share_id']));
                    $end_time = microtime(true);
                    lg("run_time_log","6.更新分享ID的资金信息(un_account_log)执行时间：".getRunTime($end_time,$start_time));
                }
            }

            $data = array(
                "verify_userid" => $this->admin['userid'],
                "verify_time" => time(),
                "status" => 1,
            );
            
            //$this->model->shareRebate($user_id);
            
            //判断是否是首充
            $start_time = microtime(true);
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($user_id);
            $end_time = microtime(true);
            lg("run_time_log","7.获取用户是否首充信息(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));

            $verify_remark = array();
            $verify_remark['msg'] = $remark;
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            
            }

            $start_time = microtime(true);
            $accountRecharge = $this->model->getAccountRecharge($log_id);
            $end_time = microtime(true);
            lg("run_time_log","8.获取用户的充值订单信息(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));

            if (empty($accountRecharge)) {
                O('model')->db->query('ROLLBACK');
                
                echo json_encode(array("rt" => 0));
                return;
            }
            
            if ($accountRecharge['money'] != $recharge_money) {
                $verify_remark['msg'] .= '（提示：后台修改线上充值金额由 ' . $accountRecharge['money'] .' 修改为 ' . $recharge_money . '）';
                
                $data['money'] = $recharge_money;
            }
    
            $data['verify_remark'] = json_encode($verify_remark,JSON_UNESCAPED_UNICODE);
            
            //更新充值表记录状态
            $start_time = microtime(true);
            $rt1 = $this->model->upRecharge($data, array("id" => $log_id, "status" => 0));
            $end_time = microtime(true);
            lg("run_time_log","9.更新用户的充值订单信息(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));
    
            //更新公司账户余额
            $start_time = microtime(true);
            $balance = $this->model->paymentBalance(array("id" => $payment_id, "balance" => $recharge_money));
            $end_time = microtime(true);
            lg("run_time_log","10.更新充值方式信息(un_payment_config)执行时间：".getRunTime($end_time,$start_time));
    
            //用户当前余额
            $start_time = microtime(true);
            $money = $this->model->userMoney($user_id);
            $end_time = microtime(true);
            lg("run_time_log","11.获取用户资金信息(un_account)执行时间：".getRunTime($end_time,$start_time));
            
            //充值记录
            $logData = array(
                "user_id" => $user_id,
                "order_num" => $order_sn,
                "type" => 10,
                "money" => $recharge_money,
                "use_money" => $money + $recharge_money,
                "remark" => $firstRecharge."线上充值：确认充值 ¥{$recharge_money}",
                "verify" => $this->admin['userid'],
                "addtime" => time(),
                "addip" => ip(),
                "admin_money" => $balance
            );
            
            //资金表日志记录
            $start_time = microtime(true);
            $rt2 = $this->model->addLog($logData);
            $end_time = microtime(true);
            lg("run_time_log","12.添加资金明细信息(un_account_log)执行时间：".getRunTime($end_time,$start_time));

            //更新用户余额
            $start_time = microtime(true);
            $rt3 = $this->model->upAccount($recharge_money, $user_id);
            $end_time = microtime(true);
            lg("run_time_log","13.更新用户资金信息(un_account)执行时间：".getRunTime($end_time,$start_time));


            $start_time = microtime(true);
            $finance = D('admin/finance');
            $finance->ttfl($log_id);
            $end_time = microtime(true);
            lg("run_time_log","14.天天返利信息(un_account_recharge, un_ttfl_cfg, un_ttfl_log)执行时间：".getRunTime($end_time,$start_time));

            //线上充值送彩金
            $percent = $this->db->result("select value from un_config where nid = 'handsel_set'");
            if($percent>0){
                $type = $this->db->result("select payment_id from un_account_recharge where order_sn = '$order_sn'");
                $username = $this->db->result("select username from un_user where id ='$user_id'");
                $handsel = bcdiv(bcmul($recharge_money,$percent,2),100,2);
                $order_handsel=[];
                $order_handsel["user_id"] = $user_id;
                $order_handsel["username"] = $username;
                $order_handsel["order_id"] = $order_sn;
                $order_handsel["type"] = $type;
                $order_handsel["percent"] = $percent;
                $order_handsel["money"] = $recharge_money;
                $order_handsel["handsel"] = $handsel;
                $order_handsel["create_time"] = time();

                $auto_online_handsel = $this->db->result("select value from un_config where nid = 'auto_online_handsel'");
                if($auto_online_handsel == 1) {
                    $order_handsel["status"] = 1;
                    D('account')->save(array('money' => '+=' . $handsel), array('user_id' => $user_id));
                    $acount_log['user_id'] = $user_id;
                    $acount_log['order_num'] = $order_sn;
                    $acount_log['type'] = 1071;
                    $acount_log['money'] = $handsel;
                    $acount_log['use_money'] = $money + $recharge_money + $handsel;
                    $acount_log['remark'] = '用户id为:' . $user_id . ' 充值送彩金:' . $handsel . '成功';
                    $acount_log['verify'] = $this->admin['userid'];
                    $acount_log['addtime'] = time();
                    $acount_log['addip'] = ip();
                    $rt2 = $this->model->addLog($acount_log);
                }else $order_handsel["status"] = 0;
                $this->db->insert('un_online_handsel',$order_handsel);
            }


            if($rt1 && $rt2 && $rt3){
                O('model')->db->query('COMMIT');

                //添加荣誉机制
                $start_time = microtime(true);
                exchangeIntegral($recharge_money, $user_id, 1);
                $end_time = microtime(true);
                lg("run_time_log","15.更新荣誉积分信息(un_user_amount_total)执行时间：".getRunTime($end_time,$start_time));

                echo json_encode(array("rt" => 1));
            } else {
                O('model')->db->query('ROLLBACK');
                
                echo json_encode(array("rt" => 0));
            }
        } catch (\Exception $e) {
            O('model')->db->query('ROLLBACK');
            
            echo json_encode(array("rt" => 0));
        }
        $end_time_1 = microtime(true);
    }

    //驳回充值
    public function cancelTopup()
    {
        $start_time_1 = microtime(true);
        lg("run_time_log","后台用户开始驳回线上充值订单");
        $log_id = $_REQUEST['id'];
        if (!is_numeric($log_id)) {
            echo json_encode(array("rt" => 0));
        
            return;
        }

        $start_time = microtime(true);
        $accountRecharge = $this->model->getAccountRecharge($log_id);
        $end_time = microtime(true);
        lg("run_time_log","1.获取用户的线上充值订单信息(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));

        if (empty($accountRecharge)) {
            echo json_encode(array("rt" => 0));

            return;
        }
        
        $data = array(
            "verify_userid" => $this->admin['userid'],
            "verify_time" => time(),
            "verify_remark" => $_REQUEST['verify_remark'],
            "status" => 2,
        );

        $start_time = microtime(true);
        $rt = $this->model->upRecharge($data, array("id" => $log_id, "status" => 0));
        $end_time = microtime(true);
        lg("run_time_log","2.更新用户的线上充值订单信息(un_account_recharge)执行时间：".getRunTime($end_time,$start_time));

        echo json_encode(array("rt" => $rt));
        $end_time_1 = microtime(true);
        lg("run_time_log","驳回线上充值执行完毕，执行时间：".getRunTime($end_time_1,$start_time_1));
    }

    //充值处理详情
    public function detail() {
        $id = $_REQUEST['id'];

        $info = $this->model->recharge($id);
        $info['addtime'] = date('Y-m-d H:i:s', $info['addtime']);
        $verify_remark = json_decode($info['verify_remark'],true);
        $info['verify_remark'] = $verify_remark['msg'];
        include template('detail-topup');
    }

    //设置提现条件
    public function setCash()
    {
        //N倍流水金额  (*0为不限制)
        $cashLimit = $this->db->getone("select value from un_config where nid = 'cashLimit'");
        if (empty($cashLimit)) {
            $cashLimit = array(
                "nid" => "cashLimit",
                "value" => "0",
                "name" => "提现设置",
                "desc" => "历史投注金额(最后一次成功充值之后) * 倍率(value) >= 最后一次成功充值金额"
            );
            $this->db->insert("un_config", $cashLimit);
        }
        
        //每天免费提现次数设置
        $freeWithdrawCont = $this->db->getone("select value from un_config where nid = 'daily_free_withdraw_count'");
        if (empty($freeWithdrawCont)) {
            $freeWithdrawCont = array(
                "nid" => "daily_free_withdraw_count",
                "value" => "0",
                "name" => "每天免费提现次数",
                "desc" => "每天用户免费提现次数"
            );
            $this->db->insert("un_config", $freeWithdrawCont);
        }

        //每天超出免费提现次数后，每次提现额外手续费设置
        $withdrawFee = $this->db->getone("select value from un_config where nid = 'daily_withdraw_fee'");
        if (empty($freeWithdrawCont)) {
            $withdrawFee = array(
                "nid" => "daily_withdraw_fee",
                "value" => "0",
                "name" => "每天超次数每次提现额外费用",
                "desc" => "每天用户提现次数超出免费提现次数后，每次提现的额外手续费"
            );
            $this->db->insert("un_config", $withdrawFee);
        }

        
        //每天超出免费提现次数后，每次提现额外手续费设置
        $withdraw_interval = $this->db->getone("select value from un_config where nid = 'withdraw_interval'");
        if (empty($withdraw_interval)) {
            $withdraw_interval = [
                "nid" => "withdraw_interval",
                "value" => "0"
            ];
            $this->db->insert("un_config", $withdraw_interval);
        }

        $cash = $this->db->getone("select value from un_config where nid = 'cash'");
        if (empty($cash)) {
            $cash = [
                "nid" => "cash",
                "value" => json_encode(['cash_upper' => 0, 'cash_lower' => 0,])
            ];
            $this->db->insert("un_config", $cash);
        }
        $cashValue = json_decode($cash['value'], true);

        $unaudit = $this->db->getone("select value from un_config where nid = 'unauditAmount'");
        if (empty($unaudit)) {
            $cash = [
                "nid" => "cash",
                "value" => 0
            ];
            $this->db->insert("un_config", $cash);
        }

        include template('add-setcash');
    }
    
    

    //设置提现条件
    public function doSetcash()
    {
        $cash_limit = trim(isset($_REQUEST['cash_limit']) ? $_REQUEST['cash_limit'] : 0);
        $free_withdraw_cont = trim(isset($_REQUEST['free_withdraw_cont']) ? $_REQUEST['free_withdraw_cont'] : 0);
        $withdraw_fee = trim(isset($_REQUEST['withdraw_fee']) ? $_REQUEST['withdraw_fee'] : '');
        $withdraw_interval = trim(isset($_REQUEST['withdraw_interval']) ? $_REQUEST['withdraw_interval'] : '');
        $cash_upper = trim(isset($_REQUEST['cash_upper']) ? $_REQUEST['cash_upper'] : '');
        $cash_lower = trim(isset($_REQUEST['cash_lower']) ? $_REQUEST['cash_lower'] : '');
        $unauditAmount = trim(isset($_REQUEST['unaudit']) ? $_REQUEST['unaudit'] : '');

        $redis = initCacheRedis();
        $re = $redis->hGet('Config:withdraw_cash_role','value');
        deinitCacheRedis($redis);
        $role_arr = decode($re);
        if(!in_array($this->admin['roleid'],$role_arr)){
            $code = array(
                'rt' => 0,
                'msg' => '你没有权限更改!',
            );
            echo encode($code);
            return false;
        }

        if(!is_numeric($withdraw_interval) || $withdraw_interval < 0){
            echo json_encode(array("rt" => "-1","msg"=>"输入正数时间间隔"));
            return;
        }


        if(!is_numeric($cash_limit) || $cash_limit < 0){
            echo json_encode(array("rt" => "-1","msg"=>"输入正数秒数"));
            return;
        }
        
        if(!is_numeric($free_withdraw_cont) || $free_withdraw_cont < 0){
            echo json_encode(array("rt" => "-1","msg"=>"请输入正整数！"));
            return;
        }
        
        //输入金额格式判断
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $withdraw_fee)) {
            echo json_encode(array("rt" => "-1","msg"=>"输入金额格式错误！"));
            return;
        }
        
        $withdraw_fee = number_format($withdraw_fee, 2, '.', '');
        
        if ($withdraw_fee > 0 && $withdraw_fee <= 0.00) {
            echo json_encode(array("rt" => "-1","msg"=>"设置免费提现次数大于0时，额外提现手续费金额必须大于0！"));
            return;
        }

        //输入金额格式判断
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $cash_upper)) exit(json_encode(array("rt" => "-1","msg"=>"提现上限金额格式错误！")));
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $cash_lower)) exit(json_encode(array("rt" => "-1","msg"=>"提现下限金额格式错误！")));
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $unauditAmount)) exit(json_encode(array("rt" => "-1","msg"=>"提现免审核额格式错误！")));

        $this->db->update("un_config", array("value" => $cash_limit), array("nid" => "cashLimit"));
        $this->db->update("un_config", array("value" => $free_withdraw_cont), array("nid" => "daily_free_withdraw_count"));
        $this->db->update("un_config", array("value" => $withdraw_fee), array("nid" => "daily_withdraw_fee"));
        $this->db->update("un_config", array("value" => $withdraw_interval), array("nid" => "withdraw_interval"));
        $this->db->update("un_config", array("value" => $withdraw_interval), array("nid" => "withdraw_interval"));

        $cash = json_encode(['cash_upper' => $cash_upper, 'cash_lower' => $cash_lower]);
        $this->db->update("un_config", array("value" => $cash), array("nid" => "cash"));
        $this->db->update("un_config", array("value" => $unauditAmount), array("nid" => "unauditAmount"));
        //刷新配置redis
        $this->refreshRedis("config", "all");
        echo json_encode(array("rt" => 1, "msg"=>"设置成功！"));
    }
    
    //自动充值开关设置条件
    public function setAutoLineRecharge()
    {
        $autoLineRecharge = $this->db->getone("select value from un_config where nid = 'auto_line_recharge'");
        if (empty($autoLineRecharge)) {
            $autoLineRechargeData = array(
                "nid" => "auto_line_recharge",
                "value" => "0",
                "name" => "自动充值设置",
                "desc" => "自动开关设置: 1:开启线上（第三方充值）自动充值到账，0：关闭线上（第三方充值）自动到账，需要进行手动操作到账"
            );
            $this->db->insert("un_config", $autoLineRechargeData);
    
            $autoLineRecharge = $this->db->getone("select value from un_config where nid = 'auto_line_recharge'");
        }

        $handsel_set = $this->db->getone("select value from un_config where nid = 'handsel_set'");
        if (empty($handsel_set)) {
            $handsel_set_data = array(
                "nid" => "handsel_set",
                "value" => '0',
                "name" => "充值送彩金(%)",
                "desc" => "充值送彩金的比例"
            );
            $this->db->insert("un_config", $handsel_set_data);

            $handsel_set = $this->db->getone("select value from un_config where nid = 'handsel_set'");
        }

        include template('set-auto-line-recharge');
    }
    //自动充值开关设置条件
    public function doSetAutoLineRecharge()
    {
        $auto_line_recharge = $_REQUEST['auto_line_recharge'];
        $handsel = $_REQUEST['handsel'];
        if ($auto_line_recharge != 1) {
            $auto_line_recharge = 0;
        }

        $autoLineRecharge = $this->db->getone("select value from un_config where nid = 'auto_line_recharge'");
        if (empty($autoLineRecharge)) {
            $autoLineRechargeData = array(
                "nid" => "auto_line_recharge",
                "value" => $auto_line_recharge,
                "name" => "自动充值设置",
                "desc" => "自动开关设置: 1:开启线上（第三方充值）自动充值到账，0：关闭线上（第三方充值）自动到账，需要进行手动操作到账"
            );
            $this->db->insert("un_config", $autoLineRechargeData);
        }else {
            $this->db->update("un_config", array("value" => $auto_line_recharge), array("nid" => "auto_line_recharge"));
        }

        $handsel_set = $this->db->getone("select value from un_config where nid = 'handsel_set'");
        if (empty($handsel_set)) {
            $handsel_set = array(
                "nid" => "handsel_set",
                "value" => $handsel,
                "name" => "充值送彩金(%)",
                "desc" => "充值送彩金的比例"
            );
            $this->db->insert("un_config", $handsel_set);
        }else {
            $this->db->update("un_config", array("value" => $handsel), array("nid" => "handsel_set"));
        }
        
        $this->refreshRedis("config", "all");
        echo json_encode(array("rt" => 1, "msg"=>"设置成功！"));
    }

    /**
     * 获取后台用户
     * @return array
     */
    protected function getAdmin(){
        $admin = O('model')->db->getAll("SELECT userid,username FROM `un_admin`");
        $admins = array();
        foreach ($admin as $v){
            $admins[$v['userid']] = $v['username'];
        }
        return $admins;
    }
}
