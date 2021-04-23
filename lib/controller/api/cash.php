<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 13:34
 * desc: 提现
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'pay.php');

class CashAction extends Action{
    /**
     * 数据表
     */
    private $model;
    private $model2;
    private $model3;
    private $model4;
    private $model5;

    public function __construct(){
        parent::__construct();
        $this->model = D('userBank');
        $this->model2 = D('accountCash');
        $this->model3 = D('user');
        $this->model4 = D('account');
        $this->model5 = D('accountLog');
    }

    /**
     * 提现
     * @method get /index.php?m=api&c=cash&a=getBankCard&token=b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @return mixed
     */
    public function getBankCard(){
        //验证参数
        $this->checkInput($_REQUEST, array('token'));

        //验证token
        $this->checkAuth();

        $res = $this->getUserBank();
        $sql = "SELECT name,classid FROM `un_dictionary` WHERE `id` = '{$res['bank']}'";
        $config = $this->db->getone($sql);
        //初始化redis
        $redis = initCacheRedis();
        
        //获取提现限制数据
        $withdrawSet = $this->getWithdrawSet($this->userId);
        
        //充值下限
        $Config= $redis -> HMGet("Config:cash",array('value'));
        $cash = json_decode($Config['value'],true);

        //关闭redis链接
        deinitCacheRedis($redis);
        $res['bank'] =  $config['name']?:'Bank';
        $res['lower_limit'] = $cash['cash_lower'];
        $res['upper_limit'] = $cash['cash_upper'];
        $res['withdraw_limit'] = $withdrawSet;

        ErrorCode::successResponse($res);
    }

    /**
     * 提现数据提交
     * @method get /index.php?m=api&c=cash&a=cash&token=b5062b58d2433d1983a5cea888597eb6&bank_id=1&money=1&psd=123
     * @param token string
     * @param bank_id int 银行卡记录表索引id
     * @param money string 提现金额
     * @param psd string 提现密码
     * @return mixed
     */
    public function cash()
    {
        session_start();
        //验证参数
        $this->checkInput($_REQUEST, array('token','bank_id','money','psd'),'all');

        //验证token
        $this->checkAuth();
        $withdraw_interval = intval($this->db->result("select value from un_config where nid = 'withdraw_interval'"));
        if(isset($_SESSION["withdraw_time"])&&$_SESSION["withdraw_uid"]==$this->userId&&$withdraw_interval>0) {
            $check_interval =  time() - $_SESSION["withdraw_time"];
            if($check_interval<$withdraw_interval && $check_interval>0) ErrorCode::errorResponse(100026,'The withdrawal time interval is '.$withdraw_interval.' seconds,there is still before the next withdrawal'.($withdraw_interval-$check_interval).'seconds');
        }

        //初始化redis
        $redis = initCacheRedis();
        //充值下限
        $Config= $redis->HMGet("Config:cash",array('value'));
        $cash = json_decode($Config['value'],true);

        $bankId = trim($_REQUEST['bank_id']);
        $money = trim($_REQUEST['money']);
        $psd = trim($_REQUEST['psd']);

        if(($money<$cash['cash_lower']) || ($money>$cash['cash_upper'])){
            ErrorCode::errorResponse(100026, 'Withdrawal amouunt '.$cash['cash_lower'].' to '.$cash['cash_upper']);
        }

        if(!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $money) || ($money == 0 || $money == '0.0' || $money == '0.00')){
            ErrorCode::errorResponse(100015,'The funds entered are incorrect');
        }

        //提现记录
        $where = array(
            'user_id' => $this->userId,
            'status' => 0
        );
        $field = 'id';
        $accountCash = $this->model2->getOneCoupon($field, $where);
        if(!empty($accountCash)){
            ErrorCode::errorResponse(100026,'The last withdrawal has not been completed, please try again later!');
        }
        $fields = 'id,username,paypassword,regtime,reg_type,group_id,layer_id'; //需要的字段
        $userInfo = $this->model3->getUserInfo($fields,array('id' => $this->userId),1);
		if(in_array($userInfo['reg_type'],array(8,9))){
			ErrorCode::errorResponse(100017 ,'Can\'t withdraw!');
		}
        $paypsd = md5($psd);

        if($paypsd !== $userInfo['paypassword']){
            $redis->hIncrBy("user_cash:".$this->userId,'paypsd_error',1);
            $paypsd_error = $redis->hGet("user_cash:".$this->userId,'paypsd_error');
            if($paypsd_error>=3){
                ErrorCode::errorResponse(100017 ,'The fund password is incorrect for more than 3 times, please contact customer service');
            }else{
                $num =3 - $paypsd_error;
                ErrorCode::errorResponse(100018 ,'The fund password is wrong, you still have'.$num.' chances');
            }

        }else{
            $paypsd_error = $redis->hGet("user_cash:".$this->userId,'paypsd_error');
            if($paypsd_error>=3){
                ErrorCode::errorResponse(100017 ,'The fund password is incorrect for more than 3 times, please contact customer service!');
            }
            $redis->del("user_cash:".$this->userId);
        }

        //关闭redis链接
        deinitCacheRedis($redis);

        //验证提现限制
        $nRt = abs($this->db->result("select value from un_config where nid = 'cashLimit'"));

        $bet_amount = $this->db->result("select bet_amount from un_user where id={$this->userId}");
        lg('cash_limit_debug.json','APP用户::'.$this->userId.',提现设置$nRt::'.$nRt);
        if (!empty($nRt)) { //倍率已设置  并且大于0 才做判断
            //验证提现限制
            $time = $this->db->result("select addtime from un_account_cash where user_id = {$this->userId} and status = 1 order by id desc");
            if (empty($time)) {
                $sql = "SELECT regtime FROM `un_user` WHERE `id` = '{$this->userId}'";
                $time = $this->db->result($sql);
            }

            $sql = "select ifnull(sum(money),0) as money from un_orders where user_id = {$this->userId} AND award_state>0 AND state=0 AND addtime > {$time}";
            $betRt = $this->db->result($sql);
            lg('cash_limit_debug.json','APP用户::'.$this->userId.',有效投注::'.$betRt.',sql::'.$sql);
            $sql  = "select ifnull(sum(money),0) as money from un_account_recharge where user_id = {$this->userId} and status = 1 and addtime > {$time}";
            $regRt = $this->db->result($sql);
            lg('cash_limit_debug.json','APP用户::'.$this->userId.',充值总额::'.$regRt.',sql::'.$sql);
            lg('cash_limit_debug.json','APP用户::'.$this->userId.',提现设置$nRt::'.$nRt.',有效投注::'.$betRt.',充值总额::'.$regRt.'$regRt * $nRt > $betRt::'.($regRt * $nRt > $betRt));
            if ($nRt>0&&(($regRt * $nRt + $bet_amount)> $betRt)) {
                ErrorCode::errorResponse(100030, "Withdrawal limit: You must reach {$nRt} times the turnover before you can withdraw!");
            }

            //查最后一次充值时间
            $sql = "SELECT `addtime`,money FROM `un_account_recharge` WHERE user_id = {$this->userId} AND STATUS = 1 ORDER BY `addtime` DESC LIMIT 1";
            $re = $this->db->getone($sql); //最后一次时间,没有记录就设置为当前值
            $last_time = isset($re['addtime']) ? $re['addtime'] : $time;
            $last_money = isset($re['money']) ? $re['money'] : 0;

            lg('cash_limit_debug.json','APP最后充值验证,最后时间::'.$last_time.',最后金额::'.$last_money.',SQL::'.$sql);

            //最后充值到当前时间的有效投注
            $sql = "select ifnull(sum(money),0) as money from un_orders where user_id = {$this->userId} AND award_state>0 AND state=0 AND addtime > {$last_time}";
            $lbetRt = $this->db->result($sql);
            lg('cash_limit_debug.json','APP最后充值验证,用户::'.$this->userId.',最后充值到当前时间的有效投注::'.$lbetRt.',sql::'.$sql);
            lg('cash_limit_debug.json','APP最后充值验证,用户::'.$this->userId.',提现设置::'.$nRt.',最后充值到当前时间的有效投注::'.$lbetRt.'$lbetRt < $last_money * $nRt::'.($lbetRt < $last_money * $nRt));

//            if($lbetRt < ($last_money * $nRt)+$bet_amount){
            lg('cash_limit_debug.json','APP最后充值验证,用户::'.$this->userId.',最后充值到当前时间的有效投注::$lbetRt::'.$lbetRt.',sql::'.$sql);
            lg('cash_limit_debug.json',var_export(array(
                '$nRt'=>$nRt,
                '$lbetRt'=>$lbetRt,
                '$last_money'=>$last_money,
                '$bet_amount'=>$bet_amount,
                '(($lbetRt < (($last_money * $nRt)+$bet_amount ))&&$nRt>0)'=>(($lbetRt < (($last_money * $nRt)+$bet_amount ))&&$nRt>0),
            ),1));
            lg('cash_limit_debug.json','APP最后充值验证,用户::'.$this->userId.',提现设置::$nRt::'.$nRt.',最后充值到当前时间的有效投注::$lbetRt::'.$lbetRt.'(($lbetRt < (($last_money * $nRt)+$bet_amount ))&&$nRt>0)::'.(($lbetRt < (($last_money * $nRt)+$bet_amount ))&&$nRt>0));
            if(($lbetRt < (($last_money * $nRt)+$bet_amount ))&&$nRt>0){
                $current_money = ($money-$last_money)>0?($money-$last_money):0;
                $agree = trim($_REQUEST['agree'])?trim($_REQUEST['agree']):0;  //不同意或者刚进入页面时

                if($agree==0){
                    if($current_money>0){
                        ErrorCode::errorResponse(100039, "Since your last deposit did not complete the entered amount, you can only withdraw (".$current_money.") USD now. Do you want to continue withdrawing?");
                    }else{
                        ErrorCode::errorResponse(100040, "Since your last deposit has not completed the entered amount, it is not possible to withdraw, please enter the new withdrawal amount!");
                    }
                }else{
                    if($current_money>0){
                        lg('cash_limit_debug.json','H5最后充值验证,用户::'.$this->userId.',提现设置::'.$nRt.',最后充值到当前时间的有效投注::'.$lbetRt.',提现原始金额::'.$money.',计算后的金额::'.$current_money);
                        $money=$current_money;
                        if($money<$cash['cash_lower']){
                            ErrorCode::errorResponse(100041, 'The withdrawal amount is less than the background setting【'.$cash['cash_lower'].'】 cannot withdraw cash!');
                        }
                    }else{
                        ErrorCode::errorResponse(100040, "Since your last deposit has not completed the entered amount, it is not possible to withdraw, please enter the new withdrawal amount!");
                    }
                }
            }
        }
        
        //获取提现限制数据
        $withdrawFee = 0;
        $withdrawSet = $this->getWithdrawSet($this->userId);
        if ($withdrawSet['cont'] == 0) {
            $withdrawFee = $withdrawSet['withdrwlFee'];
        }

        //账户
        $where = array(
            'user_id' => $this->userId
        );
//
        $account = $this->getOneAccount($where);



        if($money + $withdrawFee > $account['money']){
            ErrorCode::errorResponse(100024 ,'Not enough funds to withdraw cash');
        }
        $field = "id AS bank_id, name, account, bank";
        $where = array(
            'id' => $bankId,
            'user_id' => $this->userId,
            'state' => 1
        );
        $user_bank = $this->model->getOneCoupon($field,$where);
        if(empty($user_bank)){
            ErrorCode::errorResponse(100027 ,'The bank card information applied for cash withdrawal is incorrect');
        }
        //生成随机订单号
        $orderSn = "TX".$this->orderSn();
        $data = array(
            'user_id' => $this->userId,
            'order_sn' => $orderSn,
            'bank_id' => $bankId,
            'money' => $money,
            'extra_fee' => $withdrawFee,
            'addtime' => SYS_TIME
        );

        /**start 如果后台开启代付业务  Hady
         *  unauditAmount 大于0则开启
         */
        $sql = "select classid from un_dictionary where id = {$user_bank['bank']}";
        $bankWithdral = $this->db->getone($sql);
        $sql = "select id,name,canuse,user_group,pay_layers from un_payment_config where canuse =1 and type = 302 ";
        $withdrawInfo = $this->db->getone($sql);
        $withdrawGroup = explode(',',$withdrawInfo['user_group']);
        $withdrawLayer = explode(',',$withdrawInfo['pay_layers']);

        //开启代付      当前有代付通道  会员组  会员层级
        if ($bankWithdral['classid'] == 1 && $withdrawInfo['id'] && in_array($userInfo['group_id'],$withdrawGroup) && in_array($userInfo['layer_id'],$withdrawLayer)) {
            $sql = "select value from un_config where nid = 'unauditAmount' ";
            $unaudit = $this->db->getone($sql);
            if ($unaudit['value'] > 0 && $money <= $unaudit['value']) {
                $data['status'] = 6;
                $data['payment_id'] = $withdrawInfo['id'];
                $data['verifyremark'] = json_encode(['status'=>6,'remark'=>array(array('admin'=>$withdrawInfo['name'],'remark'=>"Automatic withdrawal application"))],JSON_UNESCAPED_UNICODE);
            }
        }
        /**结束  Hady**/
        
        //开启事物
        O('model')->db->query('BEGIN');
         try{

             //查余额
             $field = 'money,money_freeze';
             if(!empty(C('db_port'))){ //使用mycat时 查主库数据
                 $sqla="/*#mycat:db_type=master*/ select {$field} from un_account WHERE user_id={$this->userId} LIMIT 1 FOR UPDATE";
             }else{
                 $sqla="select {$field} from un_account WHERE user_id={$this->userId} LIMIT 1 FOR UPDATE";
             }
             $account = $this->db->getone($sqla);

             //生成订单
             $res = $this->model2->add($data);
             if(!$res){
                 throw new Exception();
             }
            $cash_id = $res;
             //添加log表记录
             $logData = array(
                 'user_id' => $this->userId,
                 'order_num' => $orderSn,
                 'type' => 25,
                 'money' => $money,
                 'use_money' => $account['money'] - $money,
                 'remark' => 'User: '.$userInfo['username'].' Apply for withdrawal: '.$money.' The id of the bound bank: '.$bankId.' Account: '.$user_bank['account'],
                 'addtime' => SYS_TIME
             );
             $res = $this->model5->aadAccountLog($logData);
             if(!$res){
                 throw new Exception();
             }
             //变更资金表数据
             $map = array(
                'money_freeze' => $account['money_freeze'] + $money + $withdrawFee,
                'money' => $account['money'] - $money - $withdrawFee
             );
             $setWhere = array(
                 'user_id' => $this->userId
             );
             $res = $this->model4->save($map,$setWhere);
             if(!$res){
                 throw new Exception();
             }
             //提交事物
             O('model')->db->query('COMMIT');

             //添加后台提示信息
             $map = array();
             $map['id'] = $cash_id;
             $map['user_id'] = $this->userId;
             $map['money'] = $money;
             D('user')->setCashMusic($map);
//             $fCash = $this->model2->getOneCoupon('COUNT(1) as num', array('user_id' => $this->userId));
//             $type = $fCash['num'] > 1 ? 'cash_msg' : 'fc_msg';
//             $uidInfo = D("user")->getSoundReceiveUid();
//             if(!empty($uidInfo)){
//                 foreach ($uidInfo as $val) {
//                     $tonePermissions[] = json_encode(["admin_uid"=>$val['userid'], "money"=>$money,'user_id'=>$this->userId]);
//                 }
//                 if(addMsgCue($type, $tonePermissions)){
//                     @file_put_contents('sounds.log',date('Y-m-d H:i:s')."---".$type."---set:".json_encode($tonePermissions)."\n",FILE_APPEND);
//                 };
//             }

            //判断后台提现银行卡余额是否不足
             if ($data['status'] != 6) {
                 $sql = "select id,name,balance,canuse from "
                     . "(select PG.payment_id from un_payment_group PG left join un_user U on U.group_id = PG.user_group and U.entrance = PG.entrance"
                     . " where PG.purpose = 1 and U.id = '{$this->userId}' and PG.payment_id != 0) A"
                     . " left join un_payment_config PC on A.payment_id = PC.id where canuse = 1 order by id ASC LIMIT 0,1";
                 $bankArr = O('model')->db->getone($sql);
                 if (!empty($bankArr) && $money > $bankArr['balance']) { //余额不足报警
                     $data = array();
                     $data['tip'] = 'Bank card management';
                     $data['url'] = '?m=admin&c=finance&a=drawal';
                     $data['time'] = time();
                     $data['msg'] = "The balance is not enough";
                     $data['record_id'] = $bankArr['id'];
                     $data['type'] = 4;
                     $data['remark'] = "Userid: {$this->userId}, withdraw, Withdrawal Amount: {$money}, Cash card {$bankArr['name']} is not enough, Remaining: {$bankArr['balance']}";
                     D("user")->setMusicTips($data);
//                $uidInfo = D("user")->getSoundReceiveUid();
//                if(!empty($uidInfo)){
//                    foreach ($uidInfo as $val) {
//                        $insu_msg[] = json_encode(["admin_uid"=>$val['userid'], "money"=>$money,'user_id'=>$this->userId]);
//                    }
//                    if(addMsgCue("insu_msg", $insu_msg)){
//                        @file_put_contents('sounds.log',date('Y-m-d H:i:s')."---insu_msg---set:".json_encode($tonePermissions)."\n",FILE_APPEND);
//                    };
//                }
                 }
                 D('accountCash')->save(['payment_id'=>$bankArr['id']],['id'=>$cash_id]);
             }
             $_SESSION["withdraw_uid"] = $this->userId;
             $_SESSION["withdraw_time"] = time();
             ErrorCode::successResponse(array('cash_id'=>$cash_id));
         }catch (Exception $e){
             //回滚事物
             O('model')->db->query('ROLLBACK');
             ErrorCode::errorResponse(100016,'The system is abnormal and the withdrawal fails');
         }
    }
    
    /**
     * 提现详情记录
     * @method get /index.php?m=api&c=cash&a=detail&token=b5062b58d2433d1983a5cea888597eb6&cash_id=1
     * @param token string
     * @param bank_id int 银行卡记录表索引id
     * @param money string 提现金额
     * @param psd string 提现密码
     * @return mixed
     */
    public function detail(){
        //验证参数
        $this->checkInput($_REQUEST, array('token','cash_id'));
        $id = $_REQUEST['cash_id'];
        //验证token
        $this->checkAuth();

        $sql = "SELECT c.id, c.order_sn, c.addtime, c.money, c.bank_id, c.status AS type,c.credited, c.verifytime, c.verifyremark, b.id as bid, b.name, b.account, b.bank, b.branch FROM un_account_cash AS c LEFT JOIN un_user_bank AS b ON b.id = c.bank_id WHERE c.id = {$id} and b.user_id = {$this->userId}";
        $res = $this->model2->getOneData($sql);
        //银行列表
        $bank = $this->getBank();
        $res['bank'] = $bank[$res['bank']];

        ErrorCode::successResponse($res);
    }

    /**
     * 提现状态
     * @method get /index.php?m=api&c=cash&a=presentState&token=b5062b58d2433d1983a5cea888597eb6&cash_id=1
     * @param token string
     * @return mixed
     */
    public function presentState(){
        //验证参数
        $this->checkInput($_REQUEST, array('token','cash_id'));
        $id = $_REQUEST['cash_id'];
        //验证token
        $this->checkAuth();

        $sql = "SELECT c.id, c.order_sn, c.addtime, c.money, c.bank_id, c.status AS type,c.credited, c.verifytime, c.verifyremark, c.is_read, b.id as bid, b.name, b.account, b.bank, b.branch FROM un_account_cash AS c LEFT JOIN un_user_bank AS b ON b.id = c.bank_id WHERE c.id = {$id} and b.user_id = {$this->userId}";
        $res = $this->model2->getOneData($sql);

        if(in_array($res['type'],array('1','2','3','4','5','6'))){
            //变更读取数据
            $map = array(
                'is_read' => 1
            );
            $where = array(
                'id' => $id
            );
            $this->model2->save($map,$where);
        }
        //银行列表
        $bank = $this->getBank();

        if ($res['type'] == 5 || $res['type'] == 6) {
            $res['type'] = 0;
        }
        $res['bank'] = $bank[$res['bank']];

        ErrorCode::successResponse($res);
    }
    
    /**
     * 获取用户提现的限制，系统次数限制，系统额外手续费
     * @param int $user_id 用户ID
     * @return array
     */
    public function getWithdrawSet($user_id)
    {
        $retData = [];
        $cont=0;
    
        //初始化redis
        $redis = initCacheRedis();
        //充值下限
        $Config = $redis -> HMGet("Config:cash",array('value'));
        $cash = json_decode($Config['value'],true);
    
    
        //获取每天提现次数限制
        $freeCont = $redis -> HMGet("Config:daily_free_withdraw_count",array('value'));
        if (!isset($freeCont['value']) || $freeCont['value'] == '' || !is_numeric($freeCont['value'])) {
            $this->refreshRedis2("config", "all");
            $freeCont = $redis -> HMGet("Config:daily_free_withdraw_count",array('value'));
            if (!isset($freeCont['value']) || $freeCont['value'] == '' || !is_numeric($freeCont['value'])) {
                $freeCont['value'] = 0;
            }
        }
    
        //获取每天提现额外手续费
        $withdrwlFee = $redis -> HMGet("Config:daily_withdraw_fee",array('value'));
        if (!isset($withdrwlFee['value']) || $withdrwlFee['value'] == '' || !is_numeric($withdrwlFee['value'])) {
            $this->refreshRedis2("config", "all");
            $withdrwlFee = $redis -> HMGet("Config:daily_withdraw_fee",array('value'));
            if (!isset($withdrwlFee['value']) || $withdrwlFee['value'] == '' || !is_numeric($withdrwlFee['value'])) {
                $withdrwlFee['value'] = 0;
            }
        }
    
        //关闭redis链接
        deinitCacheRedis($redis);
    
        //获取今日提现次数
        if ($freeCont['value'] > 0) {
            $start_time = strtotime(date('Y-m-d 00:00:00'));
            $end_time   = strtotime(date('Y-m-d 23:59:59'));
            $sql = 'select id from un_account_cash where user_id = ' . $this->userId . ' and status in (0,1) and addtime between ' . $start_time . ' and ' . $end_time . ' limit ' . $freeCont['value'];
            $timeCont = $this->db->getall($sql);
    
            $cont = empty($timeCont) ? 0 : count($timeCont);
    
            $retData['cont'] = $freeCont['value'] - $cont;
        } else {
            $retData['cont'] = 1;
        }
    
        $retData['freeCont']     = $freeCont['value'];
        $retData['withdrwlFee']  = $withdrwlFee['value'];
        $retData['withdrwlCont'] = $cont;
    
        return $retData;
    }

    /**
     * 提现数据提交
     * @return mixed
     */
    private function getUserBank(){
        $field = "id AS bank_id, name, account, bank";
        $where = array(
            'user_id' =>$this->userId,
            'state' =>1
        );
       return $this->model->getOneCoupon($field,$where, 'id desc');
    }

    /**
     * 生成随机订单号
     * @return $orderSn string
     */
    private function orderSn($length = 3) {
        /*$yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $orderSn = $yCode[intval(date('Y')) - 2011] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        return $orderSn;*/
        $min = pow(10 , ($length - 1));
        $max = pow(10, $length) - 1;
        return date('YmdHis',time()).mt_rand($min, $max);  //当前时间加上3位随机数
    }

    /**
     * bank
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
        
        //微信和支付宝方式不在Dictionary1里面
        $bank[1] = '微信';
        $bank[2] = '支付宝';
        return $bank;
    }

//    static function getAccountForUpdate($where, $filed = '*')
//    {
//        //查余额
//        if(!empty(C('db_port'))){ //使用mycat时 查主库数据
//            $sqla="/*#mycat:db_type=master*/ select money,money_freeze,money_usable from un_account WHERE user_id={$this->userId} LIMIT 1 for update";
//        }else{
//            $sqla="select money,money_freeze,money_usable from un_account WHERE user_id={$this->userId} LIMIT 1 for update";
//        }
//        return $this->db->getone($sqla);
//    }
//    getAccountForUpdate(){
//
//}

    /**
     * 账户信息
     * @param $filed string 字段
     * @param $where mixed 条件
     * @return $res array
     */
    private function getOneAccount($where, $filed = '*') {
        $res = $this->model4->getOneCoupon($filed, $where);
        return $res;
    }
}