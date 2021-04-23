<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/19
 * Time: 16:24
 * desc: bank
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class BankAction extends Action{
    /**
     * 数据表
     */
    private $model;
    private $model2;

    public function __construct(){
        parent::__construct();
        $this->model = D('userBank');
        $this->model2 = D('user');
    }

    /**
     * 银行信息列表
     * @method get /index.php?m=api&c=bank&a=getConfigBanks
     * @param token string
     * @return mixed
     */
    public function getConfigBanks(){
        $bank = $this->getBank();
        ErrorCode::successResponse(array('list' => $bank));
    }

    /**
     * 用户银行信息
     * @method get /index.php?m=api&c=bank&a=getUserBank&token=b0943f888bd20d33e119d7883447f7bb
     * @param token string
     * @return mixed
     */
    public function getUserBank()
    {
        //验证token
        $this->checkAuth();
        $JumpUrl = $this->getUrl();
        
        $flag = $_REQUEST['flag'];

        $list = $this->model->getBindBank($this->userId);
        //判断是否绑定过银行卡
        if (empty($list)) { //未绑定
            $flag = 0;
            
        }else {
            $flag = 1;
            foreach ($list as $k=>$val){
                $list[$k]['account']=decrypt($val['account']);
            }
        }

        $bank = $this->getBank();//银行列表
        include template('wallet/BankCard-info');
    }

    /**
     * 添加银行卡信息记录
     * @method get /index.php?m=api&c=bank&a=bank&token=b0943f888bd20d33e119d7883447f7bb
     * @param token string
     * @return mixed
     */
    public function bank(){
        //验证token
        $this->checkAuth();
        $param = array_map('deal_array', $_POST);
        $mobile = $param['mobile'];
        $realname = $param['name'];
        /*if(!$this->validate($param['account'],3)){
            ErrorCode::errorResponse(100020 ,'银行卡号格式有误');
        }*/
        if($mobile != '' && !$this->validate($param['mobile'],2)){
            ErrorCode::errorResponse(100021 ,'The phone number format is wrong');
        }
        //限制假人,游客,机器人绑定银行卡
        $sql = "SELECT reg_type FROM `un_user` WHERE `id` = {$this->userId}";
        $reg_type = O('model')->db->result($sql);
        if(in_array($reg_type,array(8,9,11))){
            ErrorCode::errorResponse(100021 ,'Can\'t bind bank card');
        }
        //查询是否已经绑定过银行卡
        $filed = 'id';
        $where = array(
            'user_id' => $this->userId,
            'state' => 1,
//            'bank'=> "not in(1,2)"
        );
        $bank = $this->model->getlist($filed,$where);
        //初始化redis
        $redis = initCacheRedis();
        $bankName = $redis->hMGet("Dictionary1:".$param['bank'],array('name'));
        $bankOff = $redis->hMGet("Config:100010",array('value'));
        $bankIds = $redis->lRange("DictionaryIds1",0,-1);
        $bankIds = "'".implode("','",$bankIds)."'";
        $bindingYinlian = $redis->hGet("Config:binding_yinlian",'value');
        //关闭redis链接
        deinitCacheRedis($redis);
        if($bankOff['value'] == 0 && !empty($bank)){
            ErrorCode::errorResponse('100019','Failed to add bank information!!');
        }
        if($bindingYinlian != 0){
            $sql = "SELECT id FROM `un_user_bank` WHERE `account` = '{$param['account']}' AND `state` = '1' AND `bank` IN ({$bankIds})";
            $bindding = $this->db->getall($sql);
            if(!empty($bindding)){
                ErrorCode::errorResponse('100019','The bank card has been added, please change the bank card!!');
            }
        }

        //合并数据
        $param['branch'] = $param['province']. ' '. $param['city'] . ' '  . $param['branch'];

        //添加参数
        $param_keys = array_keys($param);
        $filed = array('name','account','bank','branch');
        foreach ($param_keys as $v){
            if(!in_array($v,$filed)){
                unset($param[$v]);
            }
        }
        $param['account']=encrypt($param['account']);
        $param['user_id'] = $this->userId;
        $param['addtime'] = SYS_TIME;
        $param['addip'] = $_SERVER['REMOTE_ADDR'];
        $param['state'] = 1;

        //查用户名
        $sql = "SELECT username FROM un_user WHERE id={$this->userId};";
        $uname = $this->db->result($sql);
        $param['last_mod_name'] = '用户::'.$uname;
        lg('last_mod_name','web::bank,11用户::'.$uname);

        $res = $this->model->add($param);
        if(!$res){
            ErrorCode::errorResponse('100019','Failed to add bank information');
        }

        if ($mobile != '') {
            //手机号同步到user表
            $map = array(
                'mobile' => $mobile
            );
            $setWhere = array(
                'id' => $this->userId
            );
            $this->model2->save($map,$setWhere);
        }
 
        if ($realname != '') {
            //用户姓名同步到user表
            $map = array(
                'realname' => $realname,
            );
            $setWhere = array(
                'id' => $this->userId
            );
            $this->model2->save($map,$setWhere);
        }

        //更改以前的记录状态
        if(!empty($bank)){
            $data = array(
                'state' => 2
            );
            foreach ($bank as $v){
                $where = array(
                    'id' => $v['id'],
                );

                $this->model->save($data,$where);
            }
        }

        //完成平台任务
        $arr = D('admin/activity')->taskSuccess(1, $this->userId);
        if (!$arr) {
            ErrorCode::errorResponse('100019','Platform task failed to complete');
        }

        ErrorCode::successResponse(array('id' => $res, 'bankName' => $bankName['name']));
    }

    /**
     * 添加微信支付宝信息记录
     * @method get /index.php?m=api&c=bank&a=bank&token=b0943f888bd20d33e119d7883447f7bb
     * @param token string
     * @return mixed
     */
    public function bindWeChatAndAlipay(){
        //验证token
        $this->checkAuth();
        $data = array_map('deal_array', $_POST);

        if($data['mobile'] != '' && !$this->validate($data['mobile'],2)){
            ErrorCode::errorResponse(100021 ,'The phone number format is wrong');
        }

        if(!in_array(trim($_REQUEST['flag_b']),[1,2,124]))
        {
            ErrorCode::errorResponse(100021 ,'Illegal request');
        }
        //限制假人,游客,机器人绑定银行卡
        $sql = "SELECT reg_type FROM `un_user` WHERE `id` = {$this->userId}";
        $reg_type = O('model')->db->result($sql);
        if(in_array($reg_type,array(8,9,11))){
            ErrorCode::errorResponse(100021 ,'Can\'t bind bank card');
        }
        //查询是否已经绑定过银行卡//
        $filed = 'id';
        $where = array(
            'user_id' => $this->userId,
            'state' => 1,
//            'bank' =>$_REQUEST['flag_b']
        );
        $bank = $this->model->getlist($filed,$where);
        if ($_REQUEST['flag_b'] == 1) {
            $classid = 14;
            $nid = "binding_weixin";
            $msg = '该微信已被添加,请更换微信!!';
        }elseif ($_REQUEST['flag_b'] == 124) {
            $classid = 17;
            $nid = "binding_qqwallet";
            $msg = '该QQ钱包已被添加,请更换QQ钱包!!';
        }else {
            $classid = 15;
            $nid = "binding_zhifubao";
            $msg = '该支付宝已被添加,请更换支付宝!!';
        }
        //初始化redis
        $redis = initCacheRedis();
        $bankOff = $redis->hMGet("Config:100010",array('value'));
        $bankIds = $redis->lRange("DictionaryIds" . $classid, 0, -1);
        $bankIds = "'".implode("','",$bankIds)."'";
        $bindingYinlian = $redis->hGet("Config:" . $nid, 'value');
        //关闭redis链接
        deinitCacheRedis($redis);
        if($bankOff['value'] == 0 && !empty($bank)){
            ErrorCode::errorResponse('100019','Failed to add bank information!!');
        }

        if ($bindingYinlian != 0) {
            $sql = "SELECT id FROM `un_user_bank` WHERE `account` = '{$data['account']}' AND `state` = '1' AND `bank` IN ({$bankIds})";
            $bindding = $this->db->getall($sql);
            if (!empty($bindding)) {
                ErrorCode::errorResponse('100019', $msg);
            }
        }

        $param['name'] = $data['name'];
        $param['account']=encrypt($data['account']); 
        //$param['account'] = $data['account'];
        $param['bank'] = $_REQUEST['flag_b'];
        $param['branch'] = json_encode($data,JSON_UNESCAPED_UNICODE);
        $param['user_id'] = $this->userId;
        $param['addtime'] = SYS_TIME;
        $param['addip'] = $_SERVER['REMOTE_ADDR'];
        $param['state'] = 1;

        //查用户名
        $sql = "SELECT username FROM un_user WHERE id={$this->userId};";
        $uname = $this->db->result($sql);
        $param['last_mod_name'] = '用户::'.$uname;
        lg('last_mod_name','web::bindWeChatAndAlipay,用户::'.$uname);

        $res = $this->model->add($param);
        if(!$res){
            if($_REQUEST['flag_b'] == 1)
            {
                ErrorCode::errorResponse('100019','微信信息添加失败');
            }
            else if($_REQUEST['flag_b'] == 2)
            {
                ErrorCode::errorResponse('100019','支付宝信息添加失败');
            }else if($_REQUEST['flag_b'] == 124) {
                ErrorCode::errorResponse('100019','QQ钱包信息添加失败');
            }
        }

        //同步到Uuser表
        $this->model2->save(['realname'=>$data['name'],'mobile'=>$data['mobile']],['id'=>$this->userId]);


        //更改以前的记录状态
        if(!empty($bank)){
            $data = array(
                'state' => 2
            );
            foreach ($bank as $v){
                $where = array(
                    'id' => $v['id']
                );

                $this->model->save($data,$where);
            }
        }

        //完成平台任务
        $arr = D('admin/activity')->taskSuccess(1, $this->userId);
        if (!$arr) {
            ErrorCode::errorResponse('100019','Platform task failed to complete');
        }

        ErrorCode::successResponse(array('status' => 0));
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
            $tempArr = $redis->hMGet("Dictionary1:".$v,array('id','name'));
            if (strpos($tempArr['name'], '微信') !== false || strpos($tempArr['name'], '支付宝') !== false) { //过滤掉微信支付宝
                continue;
            }
            $bank [] = $redis->hMGet("Dictionary1:".$v,array('id','name'));
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return $bank;
    }

    /**
     * 验证
     * @param $data mixed 验证字段
     * @param  $code int
     * @return bool
     */
    private function validate($data,$code){
        $data = trim($data);
        $vdata = array(
            1 => '/^[a-zA-Z0-9]{6,15}$/',
            2 => '/^1[3|4|5|7|8][0-9]{9}$/',
            3 => '/^(\d{16}|\d{19})$/',
        );
        if(preg_match($vdata[$code], $data)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 银行卡管理
     */
    public function manageBank(){
        //验证token
        $this->checkAuth();

        //接收参数


        include template("wallet/BankCardManage");
    }

    /**
     * 绑定银行卡1
     */
    public function bindBank() {
        //验证token
        $this->checkAuth();
        $bank = [];
        $JumpUrl = $this->getUrl();

        $flag_b = $_REQUEST['flag_b'];
        $bank_id = $_REQUEST['bank_id'];

        //判断是否为修改银行卡信息
        if(!empty($bank_id))
        {
            $bankInfo = $this->model->getBankById($bank_id,$this->userId);
        }
        
        if ($flag_b == 3) { //银行列表
            $bank = $this->getBank();
        }

        //判断是否绑定银行卡
        if(empty($flag_b)) {
            $list = $this->model->getBindBank($this->userId);
            $conf = json_decode(D('config')->db->result("select value from un_config where nid='switch_card_type'"),true);
            include template('wallet/BankCard1');
        } else {
            include template("wallet/BankCard2");  //银联/支付宝/微信/QQ钱包
        }
    }

    /**
     * 绑定银行卡2
     */
    public function bindBank2() {
        //验证token
        $this->checkAuth();

        //接收参数
        $name = trim($_REQUEST['name']);
        $account = trim($_REQUEST['account']);

        //获取发卡银行列表
        $bank = $this->getBank();

        include template("wallet/BankCard3");
    }

    /**
     * 绑定银行卡成功
     */
    public function bankOk() {
        //验证token
        $this->checkAuth();
        //接收参数
        $bankId = trim($_REQUEST['id']);
        $bankName = trim($_REQUEST['bankName']);
        $bankInfo = $this->model->getOneCoupon('account',array('id' => $bankId));
        $bankInfo['account']=decrypt($bankInfo['account']);
        //查询是否设置资金密码
        $where = array(
            'id' => $this->userId
        );
        $field = 'paypassword';
        $userInfo = $this->model2->getOneCoupon($field, $where);
        include template('wallet/BankCardRes');
    }

    public function weChatAndAlipayOk()
    {
        include template('wallet/weChatAndAlipayOk');
    }

    public function  getBindBankInfo()
    {
        $this->checkAuth();
        $JumpUrl = $this->getUrl();
        $bank_id = trim($_REQUEST['bank_id']);
        $conf = json_decode(D('config')->db->result("select value from un_config where nid='switch_card_type'"),true);
        include template('wallet/BankCard1');
    }
}