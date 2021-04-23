<?php

/**
 * @copyright			(C) 2013 CHENGHUITONG.COM
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
//include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'pay.php');
include S_CORE . 'class' . DS . 'pages.php';
//include S_CORE . 'class' . DS . 'withdraw' . DS . 'mima.php';
//include S_PAGE . 'controller' . DS . 'web' . DS . 'pay.php';

class FinanceAction extends Action {

    private $model;
    private $uploads_dir='up_files/room';

    public function __construct() {
        parent::__construct();
        $this->model = D('admin/finance');
    }

    //手工提现
    public function man_cash(){
        $time = date('Y-m-d H:i:s');
        $redis = initCacheRedis();
        //充值下限
        $Config= $redis->HMGet("Config:cash",array('value'));
        $cash = json_decode($Config['value'],true);
        deinitCacheRedis($redis);

        include template('man_cash');
    }

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

    //手工提现
    public function do_man_cash(){

        $user_id = $_REQUEST['user_id'];
        
        if (empty($user_id) || !is_numeric($user_id)) {
            echo encode(['code'=>0,'msg'=>'非法用户！',]);
            return false;
        }

        $money = $_REQUEST['cash_money']; //提现金额

        $redis = initCacheRedis();
        //充值下限
        $Config= $redis->HMGet("Config:cash",array('value'));
        $cash = json_decode($Config['value'],true);
        if(($money<$cash['cash_lower']) || ($money>$cash['cash_upper'])){
            $data = array(
                'code'=>0,
                'msg'=>'提现金额 '.$cash['cash_lower'].'到 '.$cash['cash_upper'],
            );
            echo encode($data);
            return false;
        }
        deinitCacheRedis($redis);

        $sql = "SELECT id FROM `un_user` WHERE `id`={$user_id}";
        $userId = $this->db->getone($sql);
        if (empty($userId['id'])) {
            echo encode(['code'=>0,'msg'=>'非法用户！',]);
            return false;
        }
        
        //提现记录
        $accountCash = D('accountCash')->getOneCoupon('id', ['user_id' => $userId['id'], 'status' => 0]);
        if(!empty($accountCash)){
            echo encode(['code' => 0, 'msg' => '上次提现还未完成,请稍后再试!']);
            return false;
        }

        if(!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $money) || ($money == 0 || $money == '0.0' || $money == '0.00')){
            $data = array(
                'code'=>0,
                'msg'=>'提现金额必须大于0',
            );
            echo encode($data);
            return false;
        }

        $sql = "SELECT money FROM `un_account` WHERE user_id={$user_id}";
        $account = $this->db->getone($sql);
        if($money > $account['money']){
            $data = array(
                'code'=>0,
                'msg'=>'资金不足,无法提现',
            );
            echo encode($data);
            return false;
        }

        //生成随机订单号
        $orderSn = "TX".$this->orderSn();
        $data = array(
            'user_id'   => $_REQUEST['user_id'],
            'order_sn'  => $orderSn,
            'bank_id'   => $_REQUEST['bank_id'],
            'money'     => $money,
            'extra_fee' => 0,
            'addtime'   => strtotime($_REQUEST['time'])?:SYS_TIME,
        );

        //开启事物
        O('model')->db->query('BEGIN');
        try{
            $res = $this->db->insert('#@_account_cash',$data);
            lg('man_cash',var_export(array('添加到提现表后得到的数据::$res'=>$res,'$data'=>$data),1));
            $cash_id = $res;

            //添加log表记录
            $sql = "SELECT reg_type FROM `un_user` WHERE `id` = {$data['user_id']}";
            $reg_type = $this->db->result($sql);

            $logData = array(
                'user_id' => $user_id,
                'order_num' => $orderSn,
                'type' => 25,
                'money' => $money,
                'reg_type' => $reg_type,
                'use_money' => $account['money'] - $money,
                'remark' => '用户: '.$_REQUEST['user_name'].' 申请提现: '.$money.' 到绑定银行id为: '.$_REQUEST['bank_id'].' 账户为: '.$_REQUEST['card'],
                'addtime' => SYS_TIME,
            );

            $resa = $this->db->insert('#@_account_log',$logData);
            lg('man_cash',var_export(array('添加资金表后得到的数据::$resa'=>$resa,'$logData'=>$logData),1));

            if(!$resa){
                throw new Exception();
            }

            //减钱操作
            $sql = "UPDATE `un_account` SET money=money-{$money} ,money_freeze=money_freeze+{$money} WHERE user_id={$user_id}";
            $upAccountRes = $this->db->query($sql);
            lg('man_cash',var_export(array('扣钱后::$upAccountRes'=>$upAccountRes,'$sql'=>$sql),1));
            if(!$upAccountRes){
                throw new Exception();
            }
            //提交事物
            O('model')->db->query('COMMIT');

            $data = array(
                'code'=>1,
                'msg'=>'提现成功',
            );
            echo encode($data);

        }catch (Exception $e){
            //回滚事物
            O('model')->db->query('ROLLBACK');
            $data = array(
                'code'=>0,
                'msg'=>'提现失败',
            );
            echo encode($data);
        }
        return true;
    }

    /**
     * 生成随机订单号
     * @return $orderSn string
     */
    private function orderSn($length = 3) {
        $min = pow(10 , ($length - 1));
        $max = pow(10, $length) - 1;
        return date('YmdHis',time()).mt_rand($min, $max);  //当前时间加上3位随机数
    }


    //获取用户信息
    public function man_cash_get_info(){
        $userName = trim($_REQUEST['username']);

        if($userName==''){
            $data = array(
                'code'=>0,
                'msg'=>'请输入用户名',
            );
            echo encode($data);
            return false;
        }

        $sql = "SELECT u.id, u.realname,g.`name` as gname FROM un_user AS u LEFT JOIN `un_user_group` AS g ON g.`id`=u.`group_id` WHERE u.username='{$userName}'";
        $re  = $this->db->getone($sql);
        if (empty($re)) {
            $data = array(
                'code'=>0,
                'msg'=>'无此用户数据',
            );
            echo encode($data);
            return false;
        }

        $sql = "SELECT a.`money`,c.id as bank_id,c.`bank`,c.branch,c.account FROM `un_account` AS a LEFT JOIN `un_user_bank` AS c ON c.`user_id`=a.`user_id`  WHERE a.user_id={$re['id']} AND c.state=1";
        $res  = $this->db->getone($sql);
        if (empty($res['bank'])) {
            $data = array(
                'code'=>0,
                'msg'=>'该用户没有绑定银行卡',
            );
            echo encode($data);
            return false;
        }

        $sql ="SELECT NAME FROM `un_dictionary` WHERE id={$res['bank']}";
        $res['bank_name'] = $this->db->result($sql);
        $res['realname'] = $re['realname'];
        $res['gname'] = $re['gname'];
        $res['user_id'] = $re['id'];
        $data= array(
            'code'=>1,
            'data'=>$res,
        );
        echo encode($data);
        return true;
    }

    //银行列表
    public function bank() {
        $addType = '新增';

        try {
            $where = $_REQUEST; //搜索条件
            unset($where['m']);
            unset($where['c']);
            unset($where['a']);

            switch ($where['classid'] ){
                case 14:
                    $name = "微信";
                    break;
                case 15:
                    $name = "支付宝";
                    break;
                case 17:
                    $name = "QQ钱包";
                    break;
                case 18:
                    $name = "云闪付";
                    break;
                case 19:
                    $name = "银联钱包";
                    break;
                case 20:
                    $name = "京东钱包";
                    break;
                default:
                    $where['classid'] =1;
                    $name = "银行卡";
            }
            $classid  = $where['classid'];

            $listBankType = $this->db->getall("SELECT `id`,`name` FROM un_dictionary_class WHERE `id` in (1, 14, 15, 17, 18, 19, 20)");
            $addType .= $name;

            $pagesize = 20;
            $listCnt = $this->model->cntBank($where);
            $url = '?m=admin&c=finance&a=bank';
            $page = new pages($listCnt, $pagesize, $url, $where);
            $show = $page->show();

            $where['page_start'] = $page->offer;
            $where['page_size'] = $pagesize;
            $list = $this->model->listBank($where);
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }

        include template('list-bank');
    }

    //添加银行页面跳转
    public function addBank() {

        switch ($_REQUEST['classid'] ){
            case 14:
                $name = "微信";
                break;
            case 15:
                $name = "支付宝";
                break;
            case 17:
                $name = "QQ钱包";
                break;
            case 18:
                $name = "云闪付";
                break;
            case 19:
                $name = "银联钱包";
                break;
            case 20:
                $name = "京东钱包";
                break;
            default:
                $_REQUEST['classid'] =1;
                $name = "银行卡";
        }
        $classid  = $_REQUEST['classid'];
        include template('add-bank');
    }

    //添加银行信息
    public function doAddBank() {

        try {
            $bankname = $_REQUEST['bank'];

            switch ($_REQUEST['classid'] ){
                case 14:
                    $name = "微信";
                    break;
                case 15:
                    $name = "支付宝";
                    break;
                case 17:
                    $name = "QQ钱包";
                    break;
                case 18:
                    $name = "云闪付";
                    break;
                case 19:
                    $name = "银联钱包";
                    break;
                case 20:
                    $name = "京东钱包";
                    break;
                default:
                    $_REQUEST['classid'] =1;
                    $name = "银行卡";
            }
            $classid  = $_REQUEST['classid'];

            $is_sys = 1;
            //$value = $this->model->lastBankValue();
            if ($bankname==null||$bankname == ''){
                echo json_encode(array('rt' => 0,'msg'=>$name.'不能为空'));;
            }
            $banks = $this->model->listAllBank($classid);
            for($i = 0;$i < count($banks); $i++){
                if ($bankname == $banks[$i]['name']){
                    echo json_encode(array('rt' => 0,'msg'=>$name.'已添加该'.$bankname.'，请勿重复添加'));
                    return;
                }
            }
            $rt = $this->model->addBank(array(
                'classid' => $classid,
                'name' => $bankname,
                'is_sys' => $is_sys,
                'value' => '',
            ));

            $log_remark = $this->admin['username'] . "--" . date('Y-m-d H:i:s') . "--新增开户行($name):".$bankname;
            admin_operation_log($this->admin['userid'], 12, $log_remark, $rt);

//            if ($rt>0){
//                $this->refreshRedis('dictionary',1);
//            }
//            if(move_uploaded_file($_FILES['logo']['tmp_name'], "$this->uploads_dir/$value".'.jpg')){
//                echo json_encode(array('rt' => $rt));
//            }
//            echo $rt;
//            return;
            $this->refreshRedis("dictionary", "all");
            echo json_encode(array('rt' => 1));
        } catch (Exception $exc) {
//            echo $exc->getTraceAsString();
            throw $exc;
        }
    }


    public function doDeleteBank(){
        $bankId = $_POST['bankId'];
        $classId = $_POST['classId'];
        if (in_array($bankId,array(1,2, 124, 201, 210, 212))) {
            echo json_encode(array('rt' => 0));
            return;
        }

        $classId_name = [
            14 => '微信',
            15 => '支付宝',
            17 => 'QQ钱包',
            18 => '云闪付',
            19 => '银联钱包',
            20 => '京东钱包',
        ];
        $name = isset($classId_name[$classId])?$classId_name[$classId]:'银行卡';

        if(is_numeric($bankId)){
            $sql = "select * from un_dictionary where id = $bankId"; //1-银行信息
            $bank = $this->db->getone($sql);

            echo json_encode(array('rt' => $this->model->deleteBank($bankId,$classId)));

            $log_remark = $this->admin['username'] . "--" . date('Y-m-d H:i:s') . "--删除开户行($name):".$bank['name'];
            admin_operation_log($this->admin['userid'], 12, $log_remark, $bankId);

            $this->refreshRedis("dictionary", "all");
            $this->refreshRedis("paymentConfig", "all");
        }else{
            echo json_encode(array('rt' => 0));
        }
    }

    //银行卡列表
    public function bankcard() {
        $addType = '新增';

        try {
            $where = $_REQUEST; //搜索条件
            unset($where['m']);
            unset($where['c']);
            unset($where['a']);

            switch ($where['type'] ){
                case 35:
                    $name = "微信";
                    $color = 'red';
                    break;
                case 36:
                    $name = "支付宝";
                    $color = 'blue';
                    break;
                case 125:
                    $name = "QQ钱包";
                    $color = 'violet';
                    break;
                case 202:
                    $name = "云闪付";
                    $color = 'GoldenRod';
                    break;
                case 211:
                    $name = "银联钱包";
                    $color = 'DeepPink';
                    break;
                case 213:
                    $name = "京东钱包";
                    $color = 'LightCoral';
                    break;
                default:
                    $where['type'] = 37;
                    $color = 'green';
                    $name = "银行卡";
            }

            $listBankType = $this->db->getall("SELECT `id`,`name` FROM un_dictionary WHERE `classid` = 10");
            $addType .= $name;

            $pagesize = 20;
            $listCnt = $this->model->cntBankcard(' where  type = ' . $where['type']);
            $url = '?m=admin&c=finance&a=bankcard';
            $page = new pages($listCnt, $pagesize, $url, $where);
            $show = $page->show();

            $where['page_start'] = $page->offer;
            $where['page_size'] = $pagesize;
            $list = $this->model->listBankcardType($where);

            foreach ($list as $key => $value) {
                $list[$key]['state_cn'] = $value['canuse'] == 1 ? '启用' : '禁用';
                $data = unserialize($list[$key]['config']);
                $list[$key]['account']=$data['account'];
                $list[$key]['account_name']=$data['account_name'];
                $list[$key]['branch']= $data['branch'];
                if($list[$key]['handsel']=='0.00') $list[$key]['handsel']='-';
            }
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }

        include template('list-bankcard');
    }


    //添加银行卡页面跳转
    public function addBankcard()
    {
        $type = $_REQUEST['type'];
        switch ($type){
            case 35:
                $name = "微信";
                $classid  = 14;
                break;
            case 36:
                $name = "支付宝";
                $classid  = 15;
                break;
            case 125:
                $name = "QQ钱包";
                $classid  = 17;
                break;
            case 202:
                $name = "云闪付";
                $classid  = 18;
                break;
            case 211:
                $name = "银联钱包";
                $classid  = 19;
                break;
            case 213:
                $name = "京东钱包";
                $classid  = 20;
                break;
            default:
                $name = "银行卡";
                $classid  = 1;
        }
        //$list = $this->model->listAllBank("1, 14, 15, 17, 18, 19, 20");
        $list = $this->model->listAllBanksType($classid);
        foreach ($list as $k => $v){
            switch ($v['classid'] ){
                case 14:
                    $name = "微信";
                    break;
                case 15:
                    $name = "支付宝";
                    break;
                case 17:
                    $name = "QQ钱包";
                    break;
                case 18:
                    $name = "云闪付";
                    break;
                case 19:
                    $name = "银联钱包";
                    break;
                case 20:
                    $name = "京东钱包";
                    break;
                default:
                    $name = "银行卡";
            }
            $list[$k]['name']= $name.' - '.$v['name'];
        }
        include template('add-bankcard');
    }

    //修改银行卡页面跳转
    public function upBankcard() {
        $type = $_REQUEST['type'];
        switch ($type){
            case 35:
                $name = "微信";
                $classid  = 14;
                break;
            case 36:
                $name = "支付宝";
                $classid  = 15;
                break;
            case 125:
                $name = "QQ钱包";
                $classid  = 17;
                break;
            case 202:
                $name = "云闪付";
                $classid  = 18;
                break;
            case 211:
                $name = "银联钱包";
                $classid  = 19;
                break;
            case 213:
                $name = "京东钱包";
                $classid  = 20;
                break;
            default:
                $name = "银行卡";
                $classid  = 1;
        }
        $bankcard = $this->model->m('payment_config',$_REQUEST['id']);

        //$payGroup=$this->model->m('payment_group',$bankcard['group_id']);
        //$list = $this->model->listAllBank("1,14,15,17,18,19,20");
        $list = $this->model->listAllBanksType($classid);
        foreach ($list as $k => $v){
            switch ($v['classid'] ){
                case 14:
                    $name = "微信";
                    break;
                case 15:
                    $name = "支付宝";
                    break;
                case 17:
                    $name = "QQ钱包";
                    break;
                case 18:
                    $name = "云闪付";
                    break;
                case 19:
                    $name = "银联钱包";
                    break;
                case 20:
                    $name = "京东钱包";
                    break;
                default:
                    $name = "银行卡";
            }
            $list[$k]['name']= $name.' - '.$v['name'];
        }
        $config = unserialize($bankcard['config']);
        $bankcard['account'] = $config['account'];
        $bankcard['account_name'] = $config['account_name'];
        $bankcard['branch'] = $config['branch'];
        $bankcard['code'] = $config['code'];

        include template('update-bankcard');
    }

    //修改银行卡信息
    public function doUpBankcard() {
        try {
            $postKeyArr = [
                'bank_id' => '开户行',
                'branch' => '支行',
                'account' => '账号',
                'account_name' => '开户名',
                'min_recharge' => '最低充值',
                'max_recharge' => '最高充值',
                'fee' => '手续费',
                'balance' => '余额',
                'upper_limit' => '金额范围上限',
                'prompt' => '充值提示',
                'canuse' => '是否启用',
                'bank_link' => '链接',
            ];

            $sql = "select * from un_payment_config where id = ".$_REQUEST['id']; //1-银行信息
            $bank = $this->db->getone($sql);

            $log_remark = '修改银行卡--卡号:'.$_REQUEST['account'].'--';
            if(!empty($bank)) {
                $bank = array_merge($bank, unserialize($bank['config']));

                foreach($postKeyArr as $k=>$v) {
                    if(isset($_REQUEST[$k]) && isset($bank[$k]) && ($_REQUEST[$k] != $bank[$k]))
                        $log_remark .= $v.':'.$bank[$k].'=>'.$_REQUEST[$k].'-';
                }
            }

            $data = array();
            $where = array('id' => $_REQUEST['id']);
            if ($_REQUEST['canuse'] != '') {
                $data['canuse'] = $_REQUEST['canuse'];
            }else {
                $data['canuse'] = 0;
            }
//            if ($_REQUEST['name'] != '') {
//                $data['name'] = $_REQUEST['name'];
//            }
            if ($_REQUEST['account'] != '') {
                $data['account'] = $_REQUEST['account'];
            }
            if ($_REQUEST['branch'] != '') {
                $data['branch'] = $_REQUEST['branch'];
            }

            $data['prompt'] = $_REQUEST['prompt'];

            if ($_REQUEST['bank_id'] != '') {
                $data['bank_id'] = $_REQUEST['bank_id'];
                //$data['classid'] = $this->model->m('dictionary',$data['bank_id'] )['classid'];
                $dictionary = $this->model->getDictionary($data['bank_id']);
                $data['name'] =  $dictionary['name'];
                switch ($dictionary['classid'] ){
                    case 14:
                        $data['type'] = 35;
                        $data['logo'] = '/'.$this->uploads_dir.'/weixin.png';
                        break;
                    case 15:
                        $data['type'] = 36;
                        $data['logo'] = '/'.$this->uploads_dir.'/zhifubao.png';
                        break;
                    case 17:
                        $data['type'] = 125;
                        $data['logo'] = '/'.$this->uploads_dir.'/qqWallet.png';
                        break;
                    case 18:
                        $data['type'] = 202;
                        $data['logo'] = '/'.$this->uploads_dir.'/yunshanfu.png';
                        break;
                    case 19:
                        $data['type'] = 211;
                        $data['logo'] = '/'.$this->uploads_dir.'/banklink.png';
                        break;
                    case 20:
                        $data['type'] = 213;
                        $data['logo'] = '/'.$this->uploads_dir.'/jindong.png';
                        break;
                    default:
                        $data['type'] = 37;
                        $data['logo'] = '/'.$this->uploads_dir.'/yinlian.png';
                }
            }

            if ($_REQUEST['min_recharge'] != '') {
                $data['min_recharge'] = $_REQUEST['min_recharge'];
            }
            if ($_REQUEST['max_recharge'] != '') {
                $data['max_recharge'] = $_REQUEST['max_recharge'];
            }
            if ($_REQUEST['balance'] != '') {
                $data['balance'] = $_REQUEST['balance'];
            }
            if ($_REQUEST['upper_limit'] != '') {
                $data['upper_limit'] = $_REQUEST['upper_limit'];
            }
            if ($_REQUEST['fee'] != '') {
                $data['fee'] = $_REQUEST['fee'];
            }
            if ($_REQUEST['balance'] != '') {
                $data['balance'] = $_REQUEST['balance'];
            }
            if ($_REQUEST['handsel'] != '') {
                $data['handsel'] = $_REQUEST['handsel'];
            }

            if ($data['min_recharge'] < 0 || $data['min_recharge'] > $data['upper_limit']){
                 $arr['code'] = -1;
                 $arr['msg'] = "每次最低充值金额范围设置有误";

                 echo json_encode($arr);
                 return;
            }

            if ($data['max_recharge'] > $data['upper_limit']) {
                 $arr['code'] = -1;
                 $arr['msg'] = "每次最高充值金额范围设置有误";

                 echo json_encode($arr);
                 return;
            }

            if ($data['max_recharge'] > 0 && $data['min_recharge'] > $data['max_recharge']) {
                 $arr['code'] = -1;
                 $arr['msg'] = "每次最低充值金额范围设置有误";

                 echo json_encode($arr);
                 return;
            }

            $data['bank_link'] = $_REQUEST['bank_link'];


           // print_r($data);

            if(!empty($_FILES['code']['tmp_name']))
            {

                $code = upLodeImg($_FILES['code']);

                if($code['code'] == -1 )
                {
                    echo json_encode($code);
                    exit;
                }
                else
                {
                    $data['config']=serialize(array('account_name'=>$_REQUEST['account_name'],'account'=>$_REQUEST['account'],'branch'=>$_REQUEST['branch'],'code'=>$code['msg']));
                }
            }
            else
            {
                if(empty($_REQUEST['QRcode'])){
                    $data['config']=serialize(array('account_name'=>$_REQUEST['account_name'],'account'=>$_REQUEST['account'],'branch'=>$_REQUEST['branch']));
                }
                $data['config']=serialize(array('account_name'=>$_REQUEST['account_name'],'account'=>$_REQUEST['account'],'branch'=>$_REQUEST['branch'],'code'=>$_REQUEST['QRcode']));
            }
            unset($data['account']);
            unset($data['account_name']);
            unset($data['branch']);

            $rt = $this->model->upBankcard($data, $where);
            $this->refreshRedis("paymentConfig", "all");
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
        if($rt > 0)
        {

            admin_operation_log($this->admin['userid'], 12, $log_remark, $_REQUEST['id']);

            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        }
        else
        {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        echo json_encode($arr);
        //return json_encode($arr);
    }

    //添加银行卡信息
    public function doAddBankcard() {
        $arr = [];
        try {
            $data = array();
            $data['bank_id'] = $_REQUEST['bank_id'];
            if(!empty($_FILES['code']['tmp_name'])) {
                $code = upLodeImg($_FILES['code']);
                if($code['code'] == -1)
                {
                    echo json_encode($code);
                    exit;
                }
                else
                {
                    $data['config']=serialize(array('account_name'=>$_REQUEST['account_name'],'account'=>$_REQUEST['account'],'branch'=>$_REQUEST['branch'],'code'=>$code['msg']));
                }
            }
            else
            {
                $data['config']=serialize(array('account_name'=>$_REQUEST['account_name'],'account'=>$_REQUEST['account'],'branch'=>$_REQUEST['branch']));
            }
            $data['fee'] = $_REQUEST['fee'];
            $data['balance'] = $_REQUEST['balance'];
            $data['min_recharge'] = $_REQUEST['min_recharge'];
            $data['max_recharge'] = $_REQUEST['max_recharge'];
            $data['lower_limit'] = $_REQUEST['lower_limit'];
            $data['upper_limit'] = $_REQUEST['upper_limit'];
            $dictionary = $this->model->getDictionary($data['bank_id']);
            $data['name'] =  $dictionary['name'];
            $data['canuse']= $_REQUEST['canuse'];
            $data['bank_link']= $_REQUEST['bank_link'];
            $data['prompt']= $_REQUEST['prompt'];
            $data['handsel']= $_REQUEST['handsel'];

            //if ($data['min_recharge'] <= 0 || $data['min_recharge'] > $data['upper_limit']){
            if ($data['min_recharge'] <= 0){
                    $arr['code'] = -1;
                    $arr['msg'] = "每次最低充值金额范围设置有误";

                    echo json_encode($arr);
                    return;
            }
            
            //if ($data['max_recharge'] > $data['upper_limit']) {
            if ($data['max_recharge'] < 0) {
                $arr['code'] = -1;
                $arr['msg'] = "每次最高充值金额范围设置有误";

                echo json_encode($arr);
                return;
            }

            if ($data['max_recharge'] > 0 && $data['min_recharge'] > $data['max_recharge']) {
                $arr['code'] = -1;
                $arr['msg'] = "每次最低充值金额范围设置有误";

                echo json_encode($arr);
                return;
            }

            switch ($dictionary['classid'] ){
                case 14:
                    $data['type'] = 35;
                    $data['logo'] = '/'.$this->uploads_dir.'/weixin.png';
                    break;
                case 15:
                    $data['type'] = 36;
                    $data['logo'] = '/'.$this->uploads_dir.'/zhifubao.png';
                    break;
                case 17:
                    $data['type'] = 125;
                    $data['logo'] = '/'.$this->uploads_dir.'/qqWallet.png';
                    break;
                case 18:
                    $data['type'] = 202;
                    $data['logo'] = '/'.$this->uploads_dir.'/yunshanfu.png';
                    break;
                case 19:
                    $data['type'] = 211;
                    $data['logo'] = '/'.$this->uploads_dir.'/banklink.png';
                    break;
                case 20:
                    $data['type'] = 213;
                    $data['logo'] = '/'.$this->uploads_dir.'/jindong.png';
                    break;
                default:
                    $data['type'] = 37;
                    $data['logo'] = '/'.$this->uploads_dir.'/yinlian.png';
            }

            $sql = "select * from un_dictionary where id = ".$data['bank_id']; //1-银行信息
            $bank = $this->db->getone($sql);
            $log_remark = '新增银行卡--银行:'.$bank[$data['bank_id']].'-账号:'.$_REQUEST['account'].'-开户名:'.$_REQUEST['account_name'];

            $rt = $this->model->addBankcard($data);

            admin_operation_log($this->admin['userid'], 12, $log_remark, $rt);

            $this->refreshRedis("paymentConfig", "all");
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }

        if($rt > 0)
        {
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        }
        else
        {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        echo json_encode($arr);
    }

    //删除银行卡信息
    public function deleteBankCard()
    {
        $bank_id = $_REQUEST['bank_id'];

        if (empty($bank_id) || (int)$bank_id != $bank_id) {
            echo json_encode(['code' => 0, 'msg' => '银行卡ID错误！']);
            return;
        }

        $sql = "select * from un_payment_config where id = ".$bank_id; //1-银行信息
        $bank = $this->db->getone($sql);
        if(empty($bank)) {
            echo json_encode(['code' => 0, 'msg' => '银行卡不存在！']);
            return;
        }

        $bank = array_merge($bank, unserialize($bank['config']));
        $log_remark = '删除银行卡;银行卡号:'.$bank['account'];

        $ret = $this->model->deleteBankCard($bank_id);

        admin_operation_log($this->admin['userid'], 12, $log_remark, $bank_id);
        $this->refreshRedis("paymentConfig", "all");

        echo json_encode($ret);
    }


    public function listPayGroup(){
        try {
            $where = $_REQUEST; //搜索条件
            unset($where['m']);
            unset($where['c']);
            unset($where['a']);

            $pagesize = 20;
            $listCnt = $this->model->cntPayGroups($_REQUEST['purpose']);
            $url = '?m=admin&c=finance&a=listPayGroup';
            $page = new pages($listCnt, $pagesize, $url, $where);
            $show = $page->show();
            $where['purpose'] = $_REQUEST['purpose'];
            $where['page_start'] = $page->offer;
            $where['page_size'] = $pagesize;
            $list = $this->model->listPayGroups($where);
            $group_list = $this->db->getall("SELECT id,name from un_user_group");
            $group_list = columnIdName($group_list);
            $purpose = $_REQUEST['purpose'];
            foreach ($list as $key => $value) {
                $config = unserialize($value['config']);
                $list[$key]['account'] = $config['account'];
                $list[$key]['account_name'] = $config['account_name'];
                $list[$key]['entrance'] .= $this->model->getPayEntrance($value['id']);
                $user_group = explode(",",$value["user_group"]);
                foreach ($user_group as $k=>$i){
                    @$user_group[$k] = $group_list[$i];
                }
                $list[$key]['user_group'] = implode("/",$user_group);
            }

        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
//        echo json_encode($list);
        include template('list-pay-group');
    }

    public function doAddChargePayGroup(){
        try {
            $data = array();
            $data['bank_id'] = $_REQUEST['bank_id'];
            $data['config']=serialize(array('account_name'=>$_REQUEST['account_name'],'account'=>$_REQUEST['account'],'branch'=>$_REQUEST['branch']));


            $data['fee'] = $_REQUEST['fee'];
            $data['balance'] = $_REQUEST['balance'];
            $data['lower_limit'] = $_REQUEST['lower_limit'];
            $data['upper_limit'] = $_REQUEST['upper_limit'];
            $dictionary = $this->model->getDictionary($data['bank_id']);
            $data['logo'] = '/'.$this->uploads_dir.'/'.$dictionary['value'].'.jpg';
            $data['name'] =  $dictionary['name'];
            if($dictionary['name']=='微信'){
                $data['type'] = 1;
            }else if($dictionary['name'] == '支付宝'){
                $data['type'] = 2;
            }else if($dictionary['name'] == 'QQ钱包'){
                $data['type'] = 4;
            }else if($dictionary['name'] == '云闪付'){
                $data['type'] = 5;
            }else if($dictionary['name'] == '银联钱包'){
                $data['type'] = 6;
            }else if($dictionary['name'] == '京东钱包'){
                $data['type'] = 7;
            }else{
                $data['type'] = 3;
            }

//            $data['addtime'] = time();

            $rt = $this->model->addBankcard($data);
            $this->refreshRedis("paymentConfig", "all");
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
        echo json_encode(array('rt' => $rt));
    }

    public function addPayGroup(){
        try {
            $purpose=$_REQUEST['purpose'];
            $user_group_list = $this->model->getUserGroups();

            if($purpose==0){
                $pay_type_list=array();

            }else{


            }


            $entrance_list = $this->model->getDictionaryClass(4);

        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
        $pay_type_list = $this->model->getDictionaryClass(10);

        //禁止QQ钱包、云闪付、银联钱包、京东钱包提现方式
        foreach ($pay_type_list as $k => $v) {
            if (in_array($v['id'],[125, 202, 211, 213])) {
                unset($pay_type_list[$k]);
            }
        }
        include template('add-pay-group');
    }
    
    //通过用户组类型获取对应线下充值方式
    public function getPaymentType()
    {
        $payTypeData = [];
        $user_group_id = $_REQUEST['user_group'];
        
        $userGroupData = O('model')->db->getone("SELECT `id`, `name`, `powers` FROM `un_user_group` WHERE id = " . $user_group_id);
        
        if (!empty($userGroupData['powers'])) {
            $payTypeData = O('model')->db->getall("SELECT `id`, `name` FROM `un_dictionary` WHERE id in (" . $userGroupData['powers'] . ")");
        }
        
        echo json_encode(['code' => 1, 'msg' => '', 'data' => ['payType' => $payTypeData]]);
        return;
    }

    public function doAddPayGroup(){
        $data = array();
        $pay_type   = $_REQUEST['pay_type'];
        $user_group = $_REQUEST['user_group'];
        $entrance   = $_REQUEST['entrance'];
        $purpose    = $_REQUEST['purpose'];
        
        if (empty($pay_type)) {
            echo json_encode(['code' => 0, 'msg' => '支付类型不能为空！']);
            return;
        }

        if ($purpose == '') {
            echo json_encode(['code' => 0, 'msg' => '卡组类型不能为空！']);
            return;
        }
        
        if (empty($user_group)) {
            echo json_encode(['code' => 0, 'msg' => '用户组不能为空！']);
            return;
        }
        
        if (empty($entrance)) {
            echo json_encode(['code' => 0, 'msg' => '支付端口不能为空！']);
            return;
        }
        
        $data['pay_type'] = $pay_type;
        $data['purpose']  = $purpose;
        $data['user_group'] = $user_group;
        $data['entrance']   = $entrance[0];
        unset($entrance[0]);
        
        
        //$data['user_group'] = implode(',', $user_group);
        //$data['entrance']   = implode(',', $entrance);

        //排序默认0
        $data['sort'] = 0;
        //todo
        try {
            $ret = $this->model->addPayGroup($data, $entrance);
            $this->refreshRedis("paymentConfig", "all");
            echo json_encode($ret);
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }

        return;
    }

    public function bindPayConfig(){
        try {
            $payGroupId = $_REQUEST['pay_group_id'];
            $purpose = $_REQUEST['purpose'];
            $payGroupInfo = $this->model->getPayGroupInfo($payGroupId);

            $payGroupInfo['entrance'] .= $this->model->getPayEntrance($payGroupId);
            $payGroup = $this->model->getPayGroup($payGroupId);
            $payConfigs = $this->model->getUnbindPayConfigByPayType($payGroup['pay_type']);
            foreach ($payConfigs as $key => $value) {
                $config = unserialize($value['config']);
                $payConfigs[$key]['account'] = $config['account'];
                $payConfigs[$key]['account_name'] = $config['account_name'];
                $payConfigs[$key]['branch'] = $config['branch'];
            }

            $user_group = explode(",",$payGroupInfo['user_group']);
            foreach ($user_group as $k=>$i){
                $sql = "SELECT `name` FROM un_user_group WHERE id = $i";
                $user_group[$k] = $this->db->result($sql);
            }
            $payGroupInfo['user_group'] = implode("/",$user_group);
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }

        include template('bind-pay-config');
    }

    //卡与卡组进行绑定
    public function doBindPayConfig()
    {
        $ret = [];
        $pay_group_id = $_REQUEST['pay_group_id'];
        $pay_config_id = $_REQUEST['pay_config_id'];
        $pay_sort = $_REQUEST['sort'];

        if ($_REQUEST['purpose'] == 0) {
            if ($pay_sort > 999 || $pay_sort < 1) {
                echo json_encode(['code' => 0, 'msg' => '排序号不合法！']);
                return;
            }
        }

        try {
            $ret = $this->model->bindPayment($pay_group_id,$pay_config_id, $pay_sort);
            $this->refreshRedis("paymentConfig", "all");
        } catch (Exception $e) {
            //echo $e->getTraceAsString();
            if (empty($ret)) {
                $ret = ['code' => 0, 'msg' => '绑定失败！'];
            }
        }

        echo json_encode($ret);
    }

    public function doUnBindPayConfig()
    {
        $ret = [];
        $payGroupId = $_REQUEST['pay_group_id'];

        $adminInfo = $this->admin; //记Log用的
        unset($adminInfo['password']);
        lg('un_bind_pay_config',var_export(array(
            '刚接收到的数据',
            '$adminInfo'=>$adminInfo,
            '$_REQUEST'=>$_REQUEST,
        ),1));

        try {
            //todo
            $ret = $this->model->unBindPayConfig($payGroupId);
            $this->refreshRedis("paymentConfig", "all");
        } catch (Exception $e) {
            //echo $e->getTraceAsString();
            if (empty($ret)) {
                $ret = ['code' => 0, 'msg' => '解绑失败！'];
            }
        }

        echo json_encode($ret);
    }

    //充值列表
    public function charge() {
        
        try {
            $where = $_REQUEST; //搜索条件
            unset($where['m']);
            unset($where['c']);
            unset($where['a']);

            $where['s_time'] = empty($where['s_time']) ? date('Y-m-d') : $where['s_time'];
            $where['e_time'] = empty($where['e_time']) ? date('Y-m-d') : $where['e_time'];
            if($where['quick']!="0"&&$where['quick']!=""){
                switch ($where['quick']){
                    case 1:
                        $where['s_time'] = strtotime(date("Y-m-d",strtotime("0 day")));
                        $where['e_time'] = $where['s_time'] + 86400;
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
            //获取支付类型
            $payType = $this->getPayType(10);

            //获取支付方式
            $payList = $this->model->listAllBank("1, 14, 15, 17, 18, 19, 20");
            $payListName = [];
            foreach ($payList as $k => $v) {
                $payListName[$v['id']] = $v['name'];
            }

            $payIds = array();
            if(!empty($where['payment_id'])){
                if (in_array($where['payment_id'], $payType['tranTypeIds'])) {
                    $payIds[] = $where['payment_id'];
                }
            }else {
                $payIds = $payType['tranTypeIds'];
            }

            $pagesize = 20;
            $listCnt = $this->model->cntCharge($where,$payIds);
            $url = '?m=admin&c=finance&a=charge';
            $page = new pages($listCnt, $pagesize, $url, $where);
            $show = $page->show();


            $where['page_start'] = $page->offer;
            $where['page_size'] = $pagesize;
//            die(var_dump($payIds));
            $list = $this->model->listCharge($where,$payIds);

            //var_dump($list);
            $succMoney = 0;//本页充值成功
            $dealMoney = 0;//本页待处理
            $cancMoney = 0;//本页驳回
            foreach ($list as $key => $value) {
                if($value['status'] == 0){
                    //锁定
                    $res = D('user')->getMusicTips($value['id'],'5,2');
                    if(!empty($res)){
                        $list[$key]['verify_userid'] = $res['click_uid'];
                    }
                    $dealMoney += $value['money'];
                }
                if($value['status'] == 1){
                    $succMoney += $value['money'];
                }
                if($value['status'] == 2){
                    $cancMoney += $value['money'];
                }
                $list[$key]['state_cn'] = $value['state'] == 1 ? '启用' : '禁用';
                //$list[$key]['type_cn'] = $value['type'] == 1 ? '银联' : ($value['type'] == 2 ? '支付宝' : ($value['type'] == 1 ? '微信' : 'QQ钱包'));
                $list[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);
                $list[$key]['verify_time'] = $value['verify_time']?date('Y-m-d H:i:s', $value['verify_time']):'';
                switch ($value['type']){
                    case 1:
                        $list[$key]['type_cn'] = '微信';
                        break;
                    case 2:
                        $list[$key]['type_cn'] = '支付宝';
                        break;
                    case 124:
                        $list[$key]['type_cn'] = 'QQ钱包';
                        break;
                    case 201:
                         $list[$key]['type_cn'] = '云闪付';
                        break;
                    case 210:
                        $list[$key]['type_cn'] = '银联钱包';
                        break;
                    case 212:
                        $list[$key]['type_cn'] = '京东钱包';
                        break;
                    default:
                        $list[$key]['type_cn'] = '银联';
                }
            }
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
        //后台用户信息
        $admin = $this->getAdmin();
        $adminUid = $this->admin['userid'];
        //$tj = $this->model->offlineTJ($where, $payIds);//线下充值统计
        
        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];
        
        include template('list-charge');
    }


    public function offlineChargeSet() {
        $role = D('admin/role');
        $info = $role->getConfig('recharge_time');

        include template('set-off-line-recharge');
    }

    public function lookUp()
    {

        $uid = $_REQUEST['uid'];
        $type = $_REQUEST['type'];
        if(empty($uid)){
            $json = encode(array(
                'code'=>1,
                'msg'=>'用户不能为空',
            ));
            echo $json;
            return false;
        }

        $sql = '';
        switch($type)
        {
            case 1: //查用户的线下充值次数
                //获取支付类型
                $payType = $this->getPayType(10);
                $payIds = implode(',',$payType['tranTypeIds']);

                $sql = "SELECT COUNT(r.id) AS total FROM `un_account_recharge` AS r,un_payment_config AS pc 
WHERE r.user_id={$uid} 
AND pc.id = r.payment_id 
AND r.status=1
AND (
pc.type IN ({$payIds}) 
OR r.pay_type IN ({$payIds})
)";
                break;
            case 2:
                //获取在线充值的payment_id
                $sql1 = 'SELECT PC.id FROM un_payment_config PC RIGHT JOIN '
                    . '(SELECT D.id FROM un_dictionary D LEFT JOIN un_dictionary_class DC ON D.classid=DC.id WHERE DC.id = 13) F'
                    . ' ON F.id=PC.type';
                $paymentIdArr = $this->db->getall($sql1);
                $paymentIdStr = '';
                foreach ($paymentIdArr as $v) {
                    $paymentIdStr .= $v['id'] . ',';
                }

                $paymentIdStr = rtrim($paymentIdStr, ',');
                $sql = "SELECT COUNT(r.id) AS total FROM `un_account_recharge` AS r,un_payment_config AS pc 
WHERE r.user_id={$uid} 
AND pc.id = r.payment_id 
AND r.status=1
AND r.payment_id IN ({$paymentIdStr})";
                break;
            case 3:
                $sql = "SELECT COUNT(*) AS total FROM un_account_cash WHERE user_id={$uid} AND `status`=1";
                break;
        }
        $res = $this->db->result($sql);
        $json = encode(array(
            'code'=>0,
            'res'=>$res,
        ));
        echo $json;
        return false;
    }

    public function dealRecharge(){
        $logId = $_REQUEST['id'];
        $dealType = isset($_REQUEST['dealType']) ? $_REQUEST['dealType'] : 0;
        $recharge=$this->model->m('account_recharge',$logId);
        $recharge['addtime'] =  date('Y-m-d H:i:s', $recharge['addtime']);
        $recharge['verify_time'] = $recharge['verify_time']==0?'': date('Y-m-d H:i:s', $recharge['verify_time']);
        $user = $this->model->m('user',$recharge['user_id']);
//        echo json_encode($user);
        $payment = $this->model->m('payment_config',$recharge['payment_id']);
        if (!empty($payment)) {
            $config = unserialize($payment['config']);
            $payment['branch']=$config['branch'];
            $payment['account']=$config['account'];
            $payment['account_name']=$config['account_name'];
        } else {
            $payment['name'] = $recharge['bank_name'];
            $payment['branch'] = '';
            $payment['account'] = '(该银行卡已被下架）';
            $payment['account_name'] = '';
        }

        $bank = $this->model->q("select b.name,b.branch,b.bank,b.account from un_user_bank as b where b.user_id = {$recharge['user_id']}");
        if(in_array($bank['bank'],array(1,2))){
            $bank['branch'] = $bank['bank']==1 ? '微信' : '支付宝';
        }

        $remarks = json_decode($recharge['verify_remark'],true);
        $payment['statusStr'] = $this->model->getRechargeStatusStr($remarks['status']);
        
        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];

        include template('deal-charge');
    }
    
    public function setRechargeStatus()
    {
        $id = $_REQUEST['id'];
        
        $sql = "SELECT id, status FROM `un_account_recharge` WHERE id = " . $id;
        $rechargeData = $this->db->getone($sql);
        if (empty($rechargeData)) {
            echo json_encode(array('rt' => 0, 'msg'=>'订单不存在！'));
            return;
        }
        
        if ($rechargeData['status'] != 3) {
            echo json_encode(array('rt' => 0, 'msg'=>'充值订单状态已修改！'));
            return;
        }
        
        $sql = "UPDATE `un_account_recharge` SET status = 0 WHERE id = '{$id}'";
        $cnt = $this->db->query($sql);
        
        if($cnt) {
            echo json_encode(array('rt' => 1, 'msg'=>'修改状态成功！'));
        }else {
            echo json_encode(array('rt' => 0, 'msg'=>'修改状态失败！'));
        }
        
        return;
    }

    //确认充值
    public function agreeCharge() {

        //防止并发
        lg('agree_charge_log','接收到的所有参数::'.encode($_REQUEST).',implode::'.implode(':',$_REQUEST));

        lg("run_time_log","后台用户开始审核充值订单");
        $start_time = microtime(true);
        $redis = initCacheRedis();
        $co_str = implode(':',$_REQUEST);
        lg('agree_charge_log','$co_str::'.$co_str.',查看是否生效::'.$redis->get($co_str));
        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去
            lg('agree_charge_log','进行设置超时时间');
            $redis->expire($co_str,60); //设置它的超时
            lg('agree_charge_log','超时时间::'.$redis->ttl($co_str));
            deinitCacheRedis($redis);
        }else{
            deinitCacheRedis($redis);
            echo json_encode(array('rt' => 0, 'msg'=>'当前充值已经有人处理了！'));
            return false;
        }
        $end_time = microtime(true);
        lg("run_time_log","redis防止并发处理执行时间：".getRunTime($end_time,$start_time));

        $id = $_REQUEST['id'];
        $remark = $_REQUEST['verify_remark'];
        if ($remark==null||$remark==''){
            $remark ='同意';
        }

        $rt = $this->model->agreeRecharge($id,$remark,$this->admin);

        //删除撤单间隔标识--防止并发
        $redis = initCacheRedis();
        lg('agree_charge_log','删除撤单间隔标识::'.$co_str);
        $redis->del($co_str);
        deinitCacheRedis($redis);
        $end_time = microtime(true);

        if($rt > 0){
            echo json_encode(array('rt' => 1, 'msg'=>'充值处理成功！'));
        }elseif ($rt == -1){
            echo json_encode(array('rt' => -1, 'msg'=>'充值卡充值余额已达充值卡的最高限额，请修改充值卡金额范围！'));
        }else{
            echo json_encode(array('rt' => 0, 'msg'=>'充值处理失败！'));
        }
    }

    //驳回充值
    public function refuseCharge() {
        $start_time = microtime(true);
        $id = $_REQUEST['id'];
        $remark = $_REQUEST['verify_remark'];
        if ($remark==null||$remark==''){
            $remark ='不同意';
        }
        $rt = $this->model->refuseRecharge($id,$remark,$this->admin);
        echo json_encode($rt);
        $end_time = microtime(true);
        lg("run_time_log","驳回充值订单执行完毕，执行时间：".getRunTime($end_time,$start_time));
    }

    //提现列表
    public function drawal() {
        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];
        try {
            $where = $_REQUEST; //搜索条件
            unset($where['m']);
            unset($where['c']);
            unset($where['a']);
            $status = $where['status'];
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
            if ($where['status'] == 1) {
                $where['status'] = array(array('=',1),array('=',4),'or');   //4为免审核成功到帐  hady
            }
            if ($where['status'] == 0 && $where['status'] != '') {
                $where['status'] = array(['=',0],['=',5],['=',6],'or');
            }
            if ($where['status'] == 2) {
                $where['status'] = array(array('=',2),array('=',7),'or');   //7为免审核拒绝  hady
            }
//            payLog('a.txt',print_r($where, true));
            $count = $this->model->cntDrawal($where);
            $listCnt = $count['cnt'];
            $url = '?m=admin&c=finance&a=drawal';
            $page = new pages($listCnt, $pagesize, $url, $where);
            $show = $page->show();

            $where['page_start'] = $page->offer;
            $where['page_size'] = $pagesize;

            $list = $this->model->listDrawal($where);
            //获取支付配置信息
            $pay = $this->getPay();
//            payLog('a.txt',print_r($list,true) . "++2343f+++");

            $pageTotal = 0;
            foreach ($list as $key => $value) {
                if($value['status']==0){
                    $res = D('user')->getMusicTips($value['id'],'6,1');
                    if(!empty($res)){
                        $list[$key]['verify_user_id'] = $res['click_uid'];
                    }
                }
                if($value['status']==1 || $value['status'] == 4){
                    $pageTotal += $value['money'];
                }
                $config= unserialize($pay[$value['payment_id']]['config']);
                $list[$key]['state_cn'] = $value['state'] == 1 ? '启用' : '禁用';
                $list[$key]['type_cn'] = $value['type'] == 1 ? '银联' : ($value['type'] == 2 ? '支付宝' : ($value['type'] == 1 ? '微信' : 'QQ钱包'));

                $list[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);
//                if ($value['status'] == 4) {
//                    $sql = "select name from un_payment_config where id = {$value['payment_id']}";
//                    $payWithdraw = $this->db->getone($sql);
//                    $list[$key]['acc_account_bank'] =$payWithdraw['name'];
//                } else {
//                    $list[$key]['acc_account_bank'] =$value['acc_bank_name']. $config['branch'];
//                }
                if ($value['status'] == 4) {
                    $sql = "select name from un_payment_config where id = {$value['payment_id']}";
//                    $payWithdraw = $this->db->getone($sql);
                    $list[$key]['acc_account_bank'] =$pay[$value['payment_id']]['name'];
                } else {
                    $list[$key]['acc_account_bank'] =$value['acc_bank_name']. $config['branch'];
                }
                $list[$key]['acc_account'] = $config['account'];
                if($value['bank'] == 1) {
                    $list[$key]['branch'] = "微信";
                } else if($value['bank'] == 2) {
                    $list[$key]['branch'] = "支付宝";
                } else if($value['bank'] == 124) {
                    $list[$key]['branch'] = "QQ钱包";
                } else if($value['bank'] == 201) {
                    $list[$key]['branch'] = "云闪付";
                } else if($value['bank'] == 210) {
                    $list[$key]['branch'] = "银联钱包";
                } else if($value['bank'] == 212) {
                    $list[$key]['branch'] = "京东钱包";
                }
            }
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
//        dump($list);
        $res['total']=number_format($count['total'],2);
        $pageTotal=number_format($pageTotal,2);
        $sessid_role_id=$_SESSION['admin']['roleid'];
        //用户组
        $group = $this->getGroup();
        //后台用户信息
        $admin = $this->getAdmin();
        $adminUid = $this->admin['userid'];
        $where['status'] = $status;
        include template('list-drawal');
//        echo json_encode($list);
        //运行结束
//        $now_tiem1 = microtime(true);
//        echo $now_tiem1.'<br>';
//        $new_time = ($now_tiem1-$now_tiem)*1000;
//        echo $new_time.'<br>';
    }

    public function checkUser(){
        $logId = $_REQUEST['id'];
        $withdraw = $this->model->m('account_cash',$logId);
        $user = $this->model->m('user',$withdraw['user_id']);
        if(!empty($user)){
            echo json_encode(array("rt"=>1));
        }else{
            echo json_encode(array("rt"=>-1,"msg"=>"该用户不存在"));
        }
    }

    public function dealWithdraw(){
        $logId = $_REQUEST['id'];
        $withdraw=$this->model->m('account_cash',$logId);
        $withdraw_apply_time=$withdraw['addtime'];
        $withdraw['addtime'] =  date('Y-m-d H:i:s', $withdraw['addtime']);
        $withdraw['verify_time'] = $withdraw['verifytime']==0?'': date('Y-m-d H:i:s', $withdraw['verifytime']);
        $user = $this->model->m('user',$withdraw['user_id']);
        $remarks = json_decode($withdraw['verifyremark'],true);

        $withdraw['statusStr'] =$this->model->getWithdrawStatusStr(json_decode($withdraw['verifyremark'],true)['status']);
        $userBank=$this->model->q('select un_dictionary.name as bank, un_user_bank.branch,un_user_bank.account,un_user_bank.bank as bank_type  from un_user_bank left join un_dictionary on un_user_bank.bank = un_dictionary.id where user_id='.$user['id'].' and un_user_bank.state = 1 order by un_user_bank.id desc');
        if($userBank['bank_type'] == 1 || $userBank['bank_type'] == 2)
        {
            $userBank['branch'] = "";
        }
        //获取用户上次申请体现的处理时间
        $apply_result=$this->model->q("SELECT verifytime  FROM  un_account_cash WHERE user_id={$withdraw['user_id']} AND (status=1 or status=4) AND id<>$logId ORDER BY verifytime DESC LIMIT 1");
        if($apply_result){
            $last_withdraw_time = $apply_result['verifytime'];
        }else{
            $last_withdraw_time = $user['regtime'];
        }
        $this_withdraw_time=strtotime($withdraw['addtime']);
        //洗码量
        $money = D("order")->getUserBetMoneySum(" user_id={$withdraw['user_id']} AND addtime BETWEEN $last_withdraw_time AND $this_withdraw_time AND state=0 ");

//        $sql = "SELECT verifytime FROM `un_account_cash` WHERE `id` = 919";
//        $last_do_withdraw_time = date('Y-m-d H:i:s',$last_withdraw_time);
//        $payment = $user = $this->model->m('payment_config',$withdraw['payment_id']);
//        $config = unserialize($payment['config']);
//        $payment['branch']=$config['branch'];
//        $payment['account']=$config['account'];
//        $payment['account_name']=$config['account_name'];
//        $remarks = json_decode($withdraw['verify_remark'],true);
//        $payment['statusStr'] = $this->model->getRechargeStatusStr($remarks['status']);
//payLog('bb.txt',print_r($withdraw,true). "===1606");
//dump($withdraw);

        if ($withdraw['status'] == 4 || $withdraw['status'] == 6 || $withdraw['status'] == 7 || $withdraw['status'] == 8) {
            $sql = "select name,nid from un_payment_config where id = {$withdraw['payment_id']}";
            $payWithdraw = $this->db->getone($sql);
            $bank = $this->withdrawBank($payWithdraw['nid']);
            if ($bank) {
                $bankcode = 1;
            }
            $withdrawName = $payWithdraw['name'];
            $payConfigs = ['id'=> $withdraw['payment_id']];
        } else {
            if($withdraw['payment_id']==null||$withdraw['payment_id']==0){
                $payConfigs = $this->model->getCanUseCashPayConfig($_REQUEST['id'])[0];
            }else{
                $payConfigs = $this->model->getCashPayConfig($withdraw['payment_id']);
            }

            $config = unserialize($payConfigs['config']);
            $payConfigs['account'] = $config['account'];
            $payConfigs['account_name'] = $config['account_name'];
            $payConfigs['branch'] = $config['branch'];
            unset($payConfigs['config']);
        }


        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];

        /*
                if($withdraw['payment_id']==null||$withdraw['payment_id']==0){
                    $payConfigs = $this->model->getCanUseCashPayConfig($_REQUEST['id'])[0];
                }else{
                    $payConfigs = $this->model->getCashPayConfig($withdraw['payment_id']);
                }

                $config = unserialize($payConfigs['config']);
                $payConfigs['account'] = $config['account'];
                $payConfigs['account_name'] = $config['account_name'];
                $payConfigs['branch'] = $config['branch'];
                unset($payConfigs['config']);
                */

//        bell 添加
//        $filde = "d.order_sn,e.username,e.realname,e.mobile,d.money,d.addtime,b.name bankName,a.branch,a.account,c.config,c.lower_limit,c.upper_limit,c.fee,d.status,c.balance,d.verifytime";
//        $sql = "select {$filde} from un_user_bank a left join un_dictionary b on a.bank = b.id left join un_payment_config c on b.id = c.bank_id left join un_account_cash d on d.bank_id = a.id left join un_user e on e.id = a.user_id where d.id = $logId";
//        $list = $this->model->q($sql);
//        $list['addtime'] = date('Y-m-d H:i:s', $list['addtime']);
//        $list['config'] = unserialize($list['config']);
//        $list['status'] = $this->model->getWithdrawStatusStr($list['status']);
//        if(!empty($list['verifytime']))
//        {
//            $list['verifytime'] = date('Y-m-d H:i:s', $list['verifytime']);
//        }

        include template('deal-withdraw');
    }

    public function withdrawBank($nid)
    {
        switch ($nid) {
            case 'quan_yin_withdraw':
                return array(
                    'CMB' => '招商银行',
                    'ICBC' => '工商银行',
                    'CCB' => '建设银行',
                    'BOC' => '中国银行',
                    'ABC' => '农业银行',
                    'BOCM' => '交通银行',
                    'CGB' => '广发银行',
                    'CITIC' => '中信银行',
                    'CEB' => '光大银行',
                    'CMBC' => '民生银行',
                    'HXB' => '华夏银行',
                    'PSBC' => '邮储银行',
                    'BCCB' => '北京银行',
                    'CIB' => '兴业银行',
                    'PUFA' => '上海浦东发展银行',
                    'SHANGHAI' => '上海银行',
                    'PINGAN' => '平安银行',
                );
        }
    }

    public function autoDrawal() {
        $id = $_REQUEST['id'];
        $admin = $this->admin;
        $now_admin=$this->db->getone('select userid from un_admin where disabled=0 and userid='.$admin['userid']);
        if(!$now_admin){
            echo json_encode(['code'=>0,'msg'=>'您的账号异常，无法进行审核操作']);
        }

        $sql = "select * from un_account_cash where id = {$id}";
        $result = $this->db->getone($sql);
        if($result['status'] == 8) {
            $postData = [
                'id' => $result['id'],
                'payment_id' => $result['payment_id'],
                'order_no' => $result['order_sn'],
                'verifyremark' => $result['verifyremark'],
                'user_id' => $result['user_id'],
            ];
            //查询提现进度
            $sql = "select config,id, nid from un_payment_config where id = {$postData['payment_id']} ";
            $config = $this->db->getone($sql);
            $config['config'] = unserialize($config['config']);
            $postData['nid'] = $config['nid'];
            $result = $this->model->drawalFollowUp($postData);
            echo json_encode($result);exit;
        }
        if ($result['status'] != 6) {
            echo json_encode(['code'=>0,'msg'=>'此单为人工审核']);
        } else {
            $sql = "select * from un_user_bank  where id = {$result['bank_id']}";
            $bankInfo = $this->db->getone($sql);
//            $totalMoney = $result['money'] + $result['extra_fee'];
            $totalMoney = $result['money'];         //手续费不能代付给用户  所以这里不用加
            $sql = "select config,id, nid from un_payment_config where id = {$result['payment_id']} ";
            $config = $this->db->getone($sql);
            $config['config'] = unserialize($config['config']);
            $postData = [
                'money' => $totalMoney,
                'account' => $bankInfo['account'],
                'name' => $bankInfo['name'],
                'accountCashId' => $result['id'],
                'user_id' => $result['user_id'],
                'payment_id' => $config['id'],
                'config' => $config['config'],
                'order_sn' => $result['order_sn'],
                'extra_fee' => $result['extra_fee'],
                'bank_id' => $result['bank_id'],
                'bankcode' => $_REQUEST['bankcode'],
                'nid' => $config['nid'],
            ];
            $result = $this->model->autoDrawal($postData,$admin,$result['verifyremark']);
            if ($result) {
                echo json_encode($result);
            } else {
                echo json_encode($result);
            }
        }
    }

    public function intervalAuto()
    {

        $result = $this->model->intervalAuto();
    }

    public function agreeWithdraw(){
        $start_time = microtime(true);
        try{
            $rt= $this->model->agreeWithdraw($_REQUEST['id'],$_REQUEST['paymentId'],$_REQUEST['remark'],$_REQUEST['fee'],$this->admin);
            echo json_encode($rt);
        }catch (Exception $e){
            //throw $e;
            echo json_encode(['code' => 0, 'msg' => '处理失败，请稍后再试！']);
        }
        $end_time = microtime(true);
    }

    public function getCanUseCashPayConfig(){
        echo json_encode($this->model->getCanUseCashPayConfig(59));
    }

    public function refuseWithDrawl(){
        $redis = initCacheRedis();
        $co_str = 'refuseWithDrawl_' . $_REQUEST['id'];
        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去
            $redis->expire($co_str,30); //设置它的超时
            deinitCacheRedis($redis);
        }else{
            deinitCacheRedis($redis);
            echo json_encode(array('rt' => 0));
            return false;
        }
        $remark = $_REQUEST['verify_remark'];
        if ($remark==null||$remark==''){
            $remark='不同意';
        }
        $rt=$this->model->refuseWithDrawl($_REQUEST['id'],$remark,$this->admin,$_REQUEST['paymentId']);
        echo json_encode(array('rt' => $rt));
    }

    public function delPaymentGroupById()
    {
        $rt = $this->model->delPaymentGroupById($_REQUEST['id']);
        if($rt > 0)
        {
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        }
        else
        {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        echo json_encode($arr);
    }

    //额度调整列表
    public function quotaAdjustment()
    {
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
        $listSum = $this->model->cntQuota($where);
        $listCnt = $listSum['cnt'];
        $url = '?m=admin&c=finance&a=quotaAdjustment';
        $page = new pages($listCnt, $pagesize, $url, $where);
        $show = $page->show();
        $where['page_start'] = $page->offer;
        $where['page_size'] = $pagesize;
        $list = $this->model->listQuota($where);
        $pageSum = 0;
        if(is_array($list)){
            foreach ($list as $value) {
                $pageSum += $value['money'];
            }
        }

        include template('list-quota');
    }

    //额度调整
    public function quotaDetail(){

        include template('quotaAdjustment');
    }

    public function quotaAdjustmentAct()
    {
        $username = trim($_REQUEST['username']);
        $redis = initCacheRedis();
        $co_str = 'adjAcount:'.$username;

        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去
            $redis->expire($co_str,10); //设置它的超时
            lg('adj_acount_log','超时时间::'.$redis->ttl($co_str));
            deinitCacheRedis($redis);
        }else{
            lg('adj_acount_log','并发操作::'.'$username::'.$username.',$co_str::'.$co_str);
            $arr['code'] = -1;
            $arr['msg'] = "用户不存在";
            deinitCacheRedis($redis);
            return encode($arr);
        }

        $type = trim($_REQUEST['type']);
        $amount = trim($_REQUEST['amount']);
        $remark = trim($_REQUEST['remark']);
        if(!empty($username) ) {
            $temp = [];
            $temp["username"] = $username;
            $temp["flag"] = $type;
            $temp["money"] = $amount;
            $temp["oper"] = $this->admin['username'];
            $temp['operid'] = $this->admin['userid'];
            $temp["remark"] = $remark;
            $temp["bet_state"] = trim($_REQUEST['bet_state']);
            $temp["bet_amount"] = trim($_REQUEST['bet_amount']);
            $rt = $this->db->getone("select un_account.user_id,un_account.money from un_user left join un_account on un_user.id = un_account.user_id where un_user.reg_type <> 9 and un_user.username = '".$username."'");
            $temp["account"] = $rt["money"];
            $temp["id"] = $rt["user_id"];
            $temp["old_bet_amount"] = $bet_amount = D('admin/user')->getBetAmount($temp["id"]);
            $res = $this->model->quotaAdjustment($temp);
            $arr = $res;
        } else {
            $arr['code'] = -1;
            $arr['msg'] = "参数错误";
        }
        echo json_encode($arr);
    }

    /**
     * 获取银行字典列表
     * @return array
     */
    public function getBankTypeList()
    {
        $where = '';
        $type = trim($_REQUEST['type']);

        if ($type == 35) {
            $where = ' WHERE classid = 14';
        } elseif($type == 36) {
            $where = ' WHERE classid = 15';
        } elseif($type == 37) {
            $where = ' WHERE classid = 1';
        } elseif($type == 125) {
            $where = ' WHERE classid = 17';
        } elseif($type == 202) {
            $where = ' WHERE classid = 18';
        } elseif($type == 211) {
            $where = ' WHERE classid = 19';
        } elseif($type == 213) {
            $where = ' WHERE classid = 20';
        } else {
            $where = ' WHERE classid in (1,14,15,17,18,19,20)';
        }

        $bankData = O('model')->db->getAll("SELECT `id`, `name` FROM `un_dictionary` " . $where);

        echo json_encode($bankData);
    }

    /**
     * 会员组
     * @return array
     */
    protected function getGroup(){
        //初始化redis
        $redis = initCacheRedis();
        $LGroup = $redis->lRange('groupIds', 0, -1);
        $group = array();
        foreach ($LGroup as $v){
            $group[$v] = $redis->hMGet("group:" . $v, array('id', 'name'));
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return $group;
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
     * 支付类型
     * @param $type 类型
     * @return array
     */
    protected function getPayType($type){
        //初始化redis
        $redis = initCacheRedis();
        $LTrade = $redis->lRange('DictionaryIds'.$type, 0, -1);
        //var_dump($LTrade);
        $tranType = array();
        foreach ($LTrade as $v){
            $res = $redis->hMGet("Dictionary".$type.":" . $v, array('id', 'name'));
            //var_dump($res);
            $tranType[$res['id']] = $res['name'];
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return array('tranTypeIds'=>$LTrade,'tranType'=>$tranType);
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

    /**
     * 手动充值页面
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-03-30 11:34
     */
    public function handChargeEdit() {
        //获取支付类型
        $payType = $this->getPayType(10);

        include template('list-charge-hand-edit');
    }

    /**
     * 验证用户名是否存在
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-03-30 11:34
     */
    public function checkUsername() {
        $id = D("admin/user")->checkUsername($_REQUEST['username']);
        if ($id === false) {
            jsonReturn(['code'=>'-1','msg'=>'该用户【'.$_REQUEST['username'].'】不存在']);
        } else {
            jsonReturn(['code'=>'0','msg'=>'获取信息成功','data'=>$id]);
        }
    }

    /**
     * 根据支付类型获取支付方式
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-03-30 11:34
     */
    public function getPaymentId() {
        $type = $_REQUEST['type'];

        $sql = "select p.id,p.name,p.config,p.bank_id,p.min_recharge,p.max_recharge,p.name,p.upper_limit,p.balance from #@_payment_config as p left join #@_payment_group as g on g.payment_id = p.id where p.type = $type and p.bank_id != 0 and p.group_id != 0 and p.canuse = 1 and g.purpose = 0 GROUP BY p.id";
        $list = $this->db->getall($sql);

        foreach ($list as $key=>$val) {
            $payConf = unserialize($val['config']);
            $list[$key]['recharge_balance'] = intval(bcsub($val['upper_limit'],$val['balance'],2));
            $list[$key]['account_name'] = $payConf['account_name'];
            $list[$key]['account'] = $payConf['account'];
        }

        if (empty($list)) {
            jsonReturn(['code'=>'-1','msg'=>'支付方式不存在或没有配置']);
        } else {
            jsonReturn(['code'=>'0','msg'=>'获取信息成功','data'=>$list]);
        }
    }

    /**
     * 手动充值页面
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-03-30 11:34
     */
    public function handChargeAct() {
        $orderSn = "CZ" . $this->orderSn();
        $orderData = array(
            'order_sn' => $orderSn,
            'payment_id' => empty($_REQUEST['payment_id']) ? -1 : $_REQUEST['payment_id'] ,
            'pay_type' => empty($_REQUEST['pay_type']) ? -1 : $_REQUEST['pay_type'] ,
            'bank_id' => $_REQUEST['bank_id'],
            'bank_name' => $_REQUEST['name'],
            'user_id' => $_REQUEST['user_id'],
            'money' => $_REQUEST['money'],
            'remark' => $_REQUEST['remark'],
            'addip' => ip(),
            'addtime' => SYS_TIME
        );
        $min_recharge = 0;    //每次充值最小金额限制
        $max_recharge = 0;    //每次充值最大金额限制
        
        $redis = initCacheRedis();  //初始化redis
        $config= $redis->HMGet("Config:recharge",array('value'));
        $lower_limit  = $config['value'];
        deinitCacheRedis($redis);
        //充值限额判断（不能低于0，不能超出银行卡的最高限额）
        $sql = "SELECT type,name,min_recharge, max_recharge, upper_limit, balance, bank_id FROM un_payment_config WHERE `id` = {$orderData['payment_id']}";
        $pay_config = O('model')->db->getOne($sql);
        if(empty($pay_config)){
            jsonReturn(['code'=>'-1','msg'=>'支付方式不存在']);
        }
        
        if ($pay_config['min_recharge'] == '0.00' && $pay_config['max_recharge'] == '0.00') {
            //$max_recharge = $pay_config['upper_limit'] - $pay_config['balance'];
            $max_recharge = ($pay_config['upper_limit'] - $pay_config['balance']) > $pay_config['min_recharge'] ? ($pay_config['upper_limit'] - $pay_config['balance']) : -1;
        } elseif ($pay_config['min_recharge'] != '0.00' && $pay_config['max_recharge'] == '0.00') {
            $min_recharge = $pay_config['min_recharge'];
            $max_recharge = $pay_config['upper_limit'] - $pay_config['balance'];
        } elseif (($pay_config['upper_limit'] - $pay_config['balance']) < $pay_config['min_recharge']) {
            $max_recharge = -1;
        } elseif ($pay_config['max_recharge'] > ($pay_config['upper_limit'] - $pay_config['balance'])) {
            $min_recharge = $pay_config['min_recharge'];
            $max_recharge = $pay_config['upper_limit'] - $pay_config['balance'];
        } else {
            $min_recharge = $pay_config['min_recharge'];
            $max_recharge = $pay_config['max_recharge'];
        }
        
        if ($max_recharge != -1 && $max_recharge < $lower_limit) {
            $max_recharge = -1;
        }
        
        if ($min_recharge < $lower_limit) {
            $min_recharge = $lower_limit;
        }
        
        if($orderData['money'] < $min_recharge || $orderData['money'] > $max_recharge){
            jsonReturn(['code'=>'-1','msg'=>"单次充值限额范围 {$min_recharge} ~ {$max_recharge} 元"]);
        }
        
        if ($max_recharge < 0) {
            jsonReturn(['code'=>'-1','msg'=>'该充值方式额度已满，请返回选择其他充值方式充值！']);
        }

        if (empty($orderData['remark'])) {
            $orderData['remark'] = "管理员 ".$this->admin['username']." 手动充值";
        }

        $user_id = D("user")->getUserInfo("id",['id'=>$orderData['user_id']]);
        if (empty($user_id)) {
            jsonReturn(['code'=>'-1','msg'=>'用户不存在']);
        }

        $paymeng = $this->db->getone("select id,NAME,bank_id,min_recharge,max_recharge,NAME from un_payment_config where id = {$orderData['payment_id']}");
        if (empty($paymeng)) {
            jsonReturn(['code'=>'-1','msg'=>'支付方式不存在']);
        }

        $res = D('accountrecharge')->add($orderData);
        if ($res > 0) {
            jsonReturn(['code'=>'0','msg'=>'生成订单成功']);
        } else {
            jsonReturn(['code'=>'-1','msg'=>'生成订单失败']);
        }
    }
    
    //待支付列表
    public function waitRecharge()
    {
        $where = $_REQUEST; //搜索条件
        unset($where['m']);
        unset($where['c']);
        unset($where['a']);
        //dump($where);
        $where['s_time'] = empty($where['s_time']) ? date('Y-m-d') : $where['s_time'];
        $where['e_time'] = empty($where['e_time']) ? date('Y-m-d') : $where['e_time'];
        $where['status'] = 3;  //待支付订单
        
        try {
            //获取支付类型
            $payType = $this->getPayType(10);
    
            //获取支付方式
            $payList = $this->model->listAllBank("1, 14, 15, 17, 18, 19, 20");
            $payListName = [];
            foreach ($payList as $k => $v) {
                $payListName[$v['id']] = $v['name'];
            }
    
            $payIds = array();
            if(!empty($where['payment_id'])){
                if (in_array($where['payment_id'], $payType['tranTypeIds'])) {
                    $payIds[] = $where['payment_id'];
                }
            }else {
                $payIds = $payType['tranTypeIds'];
            }
    
            $pagesize = 20;
            $listCnt = $this->model->cntCharge($where,$payIds);
            $url = '?m=admin&c=finance&a=waitRecharge';
            $page = new pages($listCnt, $pagesize, $url, $where);
            $show = $page->show();
    
    
            $where['page_start'] = $page->offer;
            $where['page_size'] = $pagesize;
            $list = $this->model->listCharge($where,$payIds);
    
            //var_dump($list);
            $succMoney = 0;//本页充值成功
            $dealMoney = 0;//本页待处理
            $cancMoney = 0;//本页驳回
            foreach ($list as $key => $value) {
                if($value['status'] == 0){
                    //锁定
                    $res = D('user')->getMusicTips($value['id'],'5,2');
                    if(!empty($res)){
                        $list[$key]['verify_userid'] = $res['click_uid'];
                    }
                    $dealMoney += $value['money'];
                }
                if($value['status'] == 1){
                    $succMoney += $value['money'];
                }
                if($value['status'] == 2){
                    $cancMoney += $value['money'];
                }
                $list[$key]['state_cn'] = $value['state'] == 1 ? '启用' : '禁用';
                //$list[$key]['type_cn'] = $value['type'] == 1 ? '银联' : ($value['type'] == 2 ? '支付宝' : ($value['type'] == 1 ? '微信' : 'QQ钱包'));
                $list[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);
                switch ($value['type']){
                    case 1:
                        $list[$key]['type_cn'] = '微信';
                        break;
                    case 2:
                        $list[$key]['type_cn'] = '支付宝';
                        break;
                    case 124:
                        $list[$key]['type_cn'] = 'QQ钱包';
                        break;
                    case 201:
                        $list[$key]['type_cn'] = '云闪付';
                        break;
                    case 210:
                        $list[$key]['type_cn'] = '银联钱包';
                        break;
                    case 212:
                        $list[$key]['type_cn'] = '京东钱包';
                        break;
                    default:
                        $list[$key]['type_cn'] = '银联';
                }
            }
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
        //后台用户信息
        $admin = $this->getAdmin();
        $adminUid = $this->admin['userid'];
        $tj = $this->model->offlineTJ($where, $payIds);//线下充值统计
    
        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];
    
        include template('list-wait-charge');
    }

    public function statisticsData(){
        $arr = explode("&",$_REQUEST['data']);
        $type = $_REQUEST['type'];
        $where = [];
        foreach ($arr as $val) {
            $tmp = explode("=", $val);
            if (!empty($tmp[1])) {
                $where[$tmp[0]] = urldecode($tmp[1]);
            }
        }

        if ($type == 1) {
            //获取支付类型
            $payType = $this->getPayType(10);
            $payIds = array();
            if(!empty($where['payment_id'])){
                if (in_array($where['payment_id'], $payType['tranTypeIds'])) {
                    $payIds[] = $where['payment_id'];
                }
            }else {
                $payIds = $payType['tranTypeIds'];
            }
            $tj = $this->model->offlineTJ($where, $payIds);//线下充值统计

        } elseif ($type == 2) {

            $tj = D('admin/topup')->onlineTJ($where); //线上充值统计;

        } elseif ($type == 3) { //提现管理统计;
            $where['s_time'] = empty($where['s_time']) ? date('Y-m-d') : $where['s_time'];
            $where['e_time'] = empty($where['e_time']) ? date('Y-m-d') : $where['e_time'];
//            dump($where);

            if ($where['status'] == 1) {
                $where['status'] = array(array('=',1),array('=',4),'or');   //4为免审核成功到帐
            }
            $tj = $this->model->drawal_num($where);

        } elseif ($type == 4){ //会员
            $user = D('admin/user');
            $tj = $user->getUserInfoTJ($where);
            $tj[0]['auth_style'] = Session::get('style');
        } elseif ($type == 5){ //订单
            $order = D('admin/orders');
            $where['e_time'] = date('Y-m-d',strtotime($where['e_time'])) ;
            $list = $order->orderTJ2($where);
            $list['gain'] = number_format($list['gain'], 2, '.', '');

            $tj = '<span class="back-page" style="' . Session::get('style') . '">合计：';
            $tj .= '待开奖：<b>' . (empty($list['noOpen']) ? 0 : round($list['noOpen'], 2)) . '</b>';
            $tj .= '已开奖：<b>' . (empty($list['yeOpen']) ? 0 : round($list['yeOpen'], 2)) . '</b>';
            $tj .= '撤单：<b>' . (empty($list['cancel']) ? 0 : round($list['cancel'], 2)) . '</b>';
            $tj .= '投注(含未开奖)：<b>' . (empty($list['bet']) ? 0 : round($list['bet'], 2)) . '</b>';
            $tj .= '奖金：<b>' . (empty($list['bonus']) ? 0 : round($list['bonus'], 2)) . '</b>';
            $tj .= '盈亏：';
            if ($list['gain'] == 0) {
                $tj .= '0';
            }else {
                if ($list['gain'] > 0) {
                    $tj .= '<font style="color: #0099ff;"><b>' . round($list['gain'], 2) . '</b></font>';
                }else {
                    $tj .= '<font style="color: #FF3300;"><b>' . round($list['gain'], 2) . '</b></font>';
                }
                $tj .= '</span>';
            }
        } elseif ($type == 6) { //资金明细
           $tj = D('admin/orders')->cntMoneyNew($where);
        }

        if (empty($tj) && $tj !=0){
            $msg['code'] = "-1";
            $msg['msg'] = "请求失败";
        } else {
            $msg['code'] = "0";
            $msg['msg'] = "请求完成";
            $msg['data'] = $tj;
        }
        jsonReturn($msg);
    }

}
