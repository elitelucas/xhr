<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/19
 * Time: 16:24
 * desc: bank
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

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
    public function getUserBank(){
        //验证参数
        $this->checkInput($_REQUEST, array('token'));

        //验证token
        $this->checkAuth();
        $filed = 'name,account, bank, branch';
        $where = array(
            'user_id' => $this->userId,
            'state' => 1,
        );
        $res = $this->model->getOneCoupon($filed,$where, 'id desc');
        $sql = "SELECT name,classid FROM `un_dictionary` WHERE `id` = '{$res['bank']}'";
        $config = $this->db->getone($sql);
//        switch ($config)
//        $bank = $this->getBank();
//        $banklist = array();
//        foreach ($bank as $v){
//            $banklist[$v['id']] = $v['name'];
//        }
        $res['bank_type'] = $res['bank'];
        $res['bank'] = $config['name']?:'Bank';
        $res['account']=decrypt($res['account']);
        //初始化redis
        $redis = initCacheRedis();
        //获取是否可以更改银行卡
        $GameConfig= $redis -> hGetAll("Config:100010");
        $is_setBank = $GameConfig['value']?$GameConfig['value']:0;
        $res['is_setBank'] = $is_setBank;
        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($res);
    }

    /**
     * author: Aho
     * 用户银行卡绑定方式
     */
    public function switchCardType(){
        $conf = json_decode(D('config')->db->result("select value from un_config where nid='switch_card_type'"),true);
        $data['data'] = $conf;
        ErrorCode::successResponse($data);
    }



    /**
     * 添加银行卡信息记录
     * @method POST /index.php?m=api&c=bank&a=bank&token=c2c8dd2614d0c84821be93b383065384&name=zhanshang&account=8888888899999999&bank=1&branch=%E5%BB%BA%E8%AE%BE%E9%93%B6%E8%A1%8C%E6%B7%B1%E5%9C%B3%E6%94%AF%E8%A1%8C&mobile=18872574225
     * @param token string
     * @return mixed
     */
    public function bank(){
        //验证参数
        payLog('bank.txt',print_r($_REQUEST,true)."====97".print_r($_POST,true));
       $this->checkInput($_REQUEST, array('token','name','account','bank'),array('name','account','bank'));

        //验证token
        $this->checkAuth();
        $param = array_map('deal_array', $_POST);
        payLog('bank.txt',print_r($param,true)."====103");
        $mobile = $param['mobile'];
        $realname = $param['name'];
       /* if(!$this->validate($param['account'],3)){
            ErrorCode::errorResponse(100020 ,'银行卡号格式有误');
        }*/
//        if($mobile != '' && !$this->validate($param['mobile'],2)){
//            ErrorCode::errorResponse(100021 ,'手机号码格式有误');
//        }
        //限制假人,游客,机器人绑定银行卡
        $sql = "SELECT reg_type FROM `un_user` WHERE `id` = {$this->userId}";
        $reg_type = O('model')->db->result($sql);
        if(in_array($reg_type,array(8,9))){     //11        假人允许绑卡
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
        $bankName = $redis->hMGet("Dictionary1:" . $param['bank'], array('name'));
        $bankOff = $redis->hMGet("Config:100010",array('value'));
        $bankIds = $redis->lRange("DictionaryIds1", 0, -1);
        $bankIds = "'" . implode("','", $bankIds) . "'";
        $bindingYinlian = $redis->hGet("Config:binding_yinlian", 'value');
        //关闭redis链接
        deinitCacheRedis($redis);
        if($bankOff['value'] == 0 && !empty($bank)){
            ErrorCode::errorResponse('100019','Failed to add bank information!!');
        }
        if ($bindingYinlian != 0) {
            $sql = "SELECT id FROM `un_user_bank` WHERE `account` = '{$param['account']}' AND `state` = '1' AND `bank` IN ({$bankIds})";
            $bindding = $this->db->getall($sql);
            if (!empty($bindding)) {
                ErrorCode::errorResponse('100019', 'The bank card has been added, please change the bank card!!');
            }
        }

        //合并数据
        $param['branch'] = $param['province'] . ' ' . $param['city'] . ' ' . $param['branch'];

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
        lg('last_mod_name','api::bank,11用户::'.$uname);

        $res = $this->model->add($param);
        if(!$res){
            ErrorCode::errorResponse('100019','Failed to add bank information');
        }

        if ($mobile != '') {
            //手机号同步到user表
            $map = array(
                'mobile' => encrypt($mobile),
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

        ErrorCode::successResponse();
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
//
//        if($data['mobile'] != '' && !$this->validate($data['mobile'],2)){
//            ErrorCode::errorResponse(100021 ,'手机号码格式有误');
//        }

        if(!in_array(trim($_REQUEST['flag_b']),[1,2]))
        {
            ErrorCode::errorResponse(100021 ,'Illegal request');
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
            //'bank' =>$_REQUEST['flag_b']
        );
        $bank = $this->model->getlist($filed,$where);
        if ($_REQUEST['flag_b'] == 1) {
            $classid = 14;
            $nid = "binding_weixin";
            $msg = 'The WeChat has been added, please change WeChat!!';
        }else if ($_REQUEST['flag_b'] == 124) {
            $classid = 17;
            $nid = "binding_qqWallet";
            $msg = 'The QQ wallet has been added, please change the QQ wallet!!';
        }else {
            $classid = 15;
            $nid = "binding_zhifubao";
            $msg = 'The Alipay has been added, please change Alipay!!';
        }
        //初始化redis
        $redis = initCacheRedis();
        $bankOff = $redis->hMGet("Config:100010",array('value'));
        $bankIds = $redis->lRange("DictionaryIds" . $classid, 0, -1);
        $bankIds = "'" . implode("','", $bankIds) . "'";
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
        $param['account'] = encrypt($data['account']);
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
        lg('last_mod_name','api::bindWeChatAndAlipay,用户::'.$uname);

        $res = $this->model->add($param);
        if(!$res){
            if($_REQUEST['flag_b'] == 1)
            {
                ErrorCode::errorResponse('100019','微信信息添加失败');
            }else if($_REQUEST['flag_b'] == 2) {
                ErrorCode::errorResponse('100019','支付宝信息添加失败');
            }else if($_REQUEST['flag_b'] == 124){
                ErrorCode::errorResponse('100019','QQ钱包信息添加失败');
            }
        }

        //同步到user表
        $this->model2->save(array('realname'=>$data['name'],'mobile'=>encrypt($data['mobile'])),array('id'=>$this->userId));

        //更改以前的记录状态
        if(!empty($bank)){
            $data = array(
                'state' => 2,
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
//            if (strpos($tempArr['name'], '微信') !== false || strpos($tempArr['name'], '支付宝') !== false) { //过滤掉微信支付宝
//                continue;
//            }
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
}