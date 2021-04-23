<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/6
 * Time: 10:00
 * desc: 充值
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class RechargeModel extends CommonModel
{
    
    /**
     * 数据表
     */
    protected $table  = '#@_account_recharge'; //充值订单表
    protected $table1 = '#@_sys_message_read'; //公告信息表

    /**
     * 获取线下充值方式列表
     * @param int $userId 用户ID
     * @return array
     */
    public function getOfflineList($userId, $rechargeData)
    {
        $list = [];
        $field = array(  //返回指定的数据
            'id',
            'name',
            'account_name',
            'account',
            'branch',
            'logo',
            'fee',
            'lower_limit',
            'payment_name',
            'code',
            'bank_link',
            'upper_limit',
            'balance',
            'pay_type',
            'prompt',
            'payment_id',
            'min_recharge',
            'max_recharge',
            'handsel'
        );

        $sql = "SELECT g.powers, u.group_id, uull.flag AS entrance FROM un_user AS u 
                LEFT JOIN un_user_group AS g ON u.group_id = g.id 
                LEFT JOIN un_user_login_log AS uull ON u.id = uull.user_id 
                WHERE u.id = ".$userId . " ORDER BY uull.addtime DESC";
        $payGroup = O('model')->db->getOne($sql);
        
        //线下充值列表
        if(!empty($rechargeData['status']) && $rechargeData['status'] == 1){
            //层级禁用
            //RIGHT JOIN un_payment_config AS pc ON  pc.group_id = pg.id
            $sql = "SELECT pc.id,type AS pay_type,pc.name AS payment_name, pc.logo, pc.config, pc.bank_link, pc.balance, pc.upper_limit, pc.min_recharge, pc.max_recharge, pc.prompt ,pc.handsel FROM un_payment_group AS pg
                     RIGHT JOIN un_payment_config AS pc ON  pc.id = pg.payment_id
                     WHERE find_in_set(".$payGroup['group_id'] . ",pg.user_group) 
                     AND pg.pay_type IN (".$payGroup['powers'].")
                     AND pg.purpose = 0
                     AND pg.entrance = ".$payGroup['entrance'] . "
                     AND pc.canuse = 1 ORDER BY pg.sort != 0 DESC, pg.sort";
            //AND (find_in_set(".$regType['layer_id'].",pc.pay_layers) or pc.pay_layers='')
        } else {
            //层级启用
            $sql = "SELECT pc.id,type AS pay_type,pc.name AS payment_name, pc.logo, pc.config, pc.bank_link, pc.balance, pc.upper_limit, pc.min_recharge, pc.max_recharge, pc.prompt,pc.handsel FROM un_payment_group AS pg
                     RIGHT JOIN un_payment_config AS pc ON  pc.id = pg.payment_id
                     WHERE find_in_set(".$payGroup['group_id'] . ",pg.user_group) 
                     AND pg.pay_type IN (".$payGroup['powers'].")
                     AND pg.purpose = 0
                     AND pg.entrance = ".$payGroup['entrance'] . "
                     AND pc.canuse = 1
                     AND (find_in_set(" . $rechargeData['layer_id'] . ",pc.pay_layers) or pc.pay_layers = '')
                     ORDER BY pg.sort != 0 DESC, pg.sort";
            //AND (find_in_set(".$regType['layer_id'].",pc.pay_layers) or pc.pay_layers='')
        }
        $offlineList = O('model')->db->getAll($sql);

        if (!empty($offlineList)) {
            foreach ($offlineList as $k => $v) {
                if ($v['min_recharge'] == '0.00' && $v['max_recharge'] == '0.00') {
                    $offlineList[$k]['max_recharge'] = ($v['upper_limit'] - $v['balance']) > $v['min_recharge'] ? ($v['upper_limit'] - $v['balance']) : -1;
                } elseif (($v['upper_limit'] - $v['balance']) < $v['min_recharge']) {
                    $offlineList[$k]['max_recharge'] = -1;
                } elseif ($v['min_recharge'] != '0.00' && $v['max_recharge'] == '0.00') {
                    $offlineList[$k]['max_recharge'] = $v['upper_limit'] - $v['balance'];
                } elseif ($v['max_recharge'] > ($v['upper_limit'] - $v['balance'])) {
                    $offlineList[$k]['max_recharge'] = $v['upper_limit'] - $v['balance'];
                }
        
                if ($offlineList[$k]['max_recharge'] == 0) {
                    $offlineList[$k]['max_recharge'] = -1;
                }
        
                if ($offlineList[$k]['max_recharge'] != -1) {
                    $offlineList[$k]['max_recharge'] = number_format($offlineList[$k]['max_recharge'], 2, '.', '');
        
                    if ($offlineList[$k]['max_recharge'] < $rechargeData['lower_limit']) {
                        $offlineList[$k]['max_recharge'] = -1;
                    }
                }
        
                $offlineList[$k]['min_recharge'] = number_format($offlineList[$k]['min_recharge'], 2, '.', '');
                if ($offlineList[$k]['min_recharge'] < $rechargeData['lower_limit']) {
                    $offlineList[$k]['min_recharge'] = $rechargeData['lower_limit'];
                }
                
                $arr = unserialize($v['config']);
                unset($v['config']);
                $offlineList[$k] = array_merge($arr,$offlineList[$k]);
                $offlineList[$k]['name'] = $offlineList[$k]['payment_name'];
                $offlineList[$k]['payment_id'] = $offlineList[$k]['id'];
                $keys = array_keys($offlineList[$k]);
                foreach ($keys as $ks){
                    if(!in_array($ks,$field)){
                        unset($offlineList[$k][$ks]);
                    }
                }

                if (!empty($offlineList[$k]['prompt'])) {
                    $arrPrompt = explode('|', $offlineList[$k]['prompt']);
                    $offlineList[$k]['prompt'] = implode('\n', $arrPrompt);
                    //$offlineList[$k]['prompt'] = explode('\n', $offlineList[$k]['prompt']);
                } else {
                        $offlineList[$k]['prompt'] = '';
                }
            }
        }

        return $offlineList;
    }
    
    /**
     * 线上充值列表
     * @param int $userId 用户ID
     * @return array 
     */
    public function getOnlineList($userId, $rechargeData){
        $list = [];
        $payList = [];
        
        $sql = "SELECT g.online_type, u.group_id, uull.flag FROM un_user AS u
                LEFT JOIN un_user_group AS g ON u.group_id = g.id
                LEFT JOIN un_user_login_log AS uull ON u.id = uull.user_id
                WHERE u.id = ".$userId . " ORDER BY uull.addtime DESC";
        $entrance = O('model')->db->getOne($sql);

//        payLog('a.txt',print_r($entrance,true).print_r($entrance,true) );

        //$sql = "SELECT uull.`flag` FROM `un_user_login_log` AS uull WHERE uull.`user_id` = ".$userId . " ORDER BY uull.addtime DESC";
        //$entrance = O('model')->db->getOne($sql);
        /*
        if (!empty($rechargeData['status']) && $rechargeData['status'] == 1){ 
            //层级禁用状态
            $sql = 'SELECT PC.* FROM un_payment_config PC RIGHT JOIN
                    (SELECT D.id FROM un_dictionary D LEFT JOIN un_dictionary_class DC ON D.classid=DC.id WHERE DC.id = 13) F
                    ON F.id=PC.type WHERE canuse = 1 ORDER BY PC.sort';
        } else { 
            //层级启用状态
            $sql = 'SELECT PC.* FROM un_payment_config PC RIGHT JOIN
                    (SELECT D.id FROM un_dictionary D LEFT JOIN un_dictionary_class DC ON D.classid=DC.id WHERE DC.id = 13) F
                    ON F.id=PC.type WHERE canuse = 1 and (find_in_set('.$rechargeData['layer_id'].',PC.pay_layers) or PC.pay_layers=\'\') ORDER BY PC.sort';
        }
        */
        
        if (!empty($rechargeData['status']) && $rechargeData['status'] == 1){
            //层级禁用状态
            if (empty($entrance['online_type'])) {
                $sql = 'SELECT PC.* FROM un_payment_config PC WHERE canuse = 1  AND (find_in_set(' . $entrance['group_id'] . ',PC.user_group) or PC.user_group=\'\') ORDER BY PC.sort';
            } else {
                $sql = 'SELECT PC.* FROM un_payment_config PC WHERE canuse = 1  AND (find_in_set(PC.type,"' . $entrance['online_type'] . '")) and (find_in_set(' . $entrance['group_id'] . ',PC.user_group) or PC.user_group=\'\') ORDER BY PC.sort';
            }
        } else {
            //层级启用状态
            if (empty($entrance['online_type'])) {
                $sql = 'SELECT PC.* FROM un_payment_config PC WHERE canuse = 1  AND(find_in_set(' . $entrance['group_id'] . ',PC.user_group) or PC.user_group=\'\') and (find_in_set('.$rechargeData['layer_id'].',PC.pay_layers) or PC.pay_layers=\'\') ORDER BY PC.sort';
            }else {
                $sql = 'SELECT PC.* FROM un_payment_config PC WHERE canuse = 1  AND (find_in_set(PC.type,"' . $entrance['online_type'] . '")) and (find_in_set(' . $entrance['group_id'] . ',PC.user_group) or PC.user_group=\'\') and (find_in_set('.$rechargeData['layer_id'].',PC.pay_layers) or PC.pay_layers=\'\') ORDER BY PC.sort';
                
            }
        }

        $onlineList = O('model')->db->getAll($sql);
        //dump($sql);

        foreach ($onlineList as $v) {
            $payConfig = unserialize($v['config']);
        
            $payArr = array(
                'logo'         => isset($v['logo']) ? $v['logo'] : '',
                'lower_limit'  => $rechargeData['lower_limit'],
                'pay_type'     => $v['nid'],  //填充数据，验证非空需要
                'type'         => $v['id'],   //app接口使用
                'payment_id'   => $v['id'],  //h5页面使用
            );
            
            //同一支付，不同支付方式配置，（如微信：扫码、WAP、H5）
            if (!empty($payConfig['payType'])) {
                //兼容上一版本库
                $type = [];
                if ($v['type'] == 67) {
                    $type = ['wx', 'wxwap', 'wxh'];
                } elseif ($v['type'] == 68) {
                    $type = ['ali', 'aliwap', 'alih'];
                } elseif ($v['type'] == 75) {
                    $type = ['wy', 'wykj', 'wyh'];
                } elseif ($v['type'] == 139) {
                    $type = ['qq', 'qqwap', 'qqh'];
                } elseif ($v['type'] == 214) {
                    $type = ['yl', 'ylwap', 'ylh'];
                } elseif ($v['type'] == 215) {
                    $type = ['jd', 'jdwap', 'jdh'];
                } else {
                    continue;
                }

                foreach ($payConfig['payType'] as $ck => $cv) {
                    //判断不同端（ios,android,h5,pc)显示不同的充值方式
                    if (!empty($cv['payEntrance']) && strpos($cv['payEntrance'], $entrance['flag']) === false) {
                        continue;
                    }
                    //判断不同端（ios,android,h5,pc)是否显示不同的充值方式
                    if (!empty($cv['payStatus']) && $cv['payStatus'] != 1) {
                        continue;
                    }
                    //兼容上一版本
                    if (in_array($ck, $type)) {
                        $payArr['payment_name'] = $cv['name'];
                        $payArr['channel_type'] = $ck;

                        if ($cv['request_type'] == 2) {
                            $payArr['type_mode'] = 1;  //1时，跳转到第三方平台接口
                        } else {
                            $payArr['type_mode'] = 0;  //0时，跳转到二维码扫码接口
                        }
                    } else {
                        continue;
                    }

                    //网银时，有时候需要获取可充值银行列表
                    if ($v['type'] == 75) {
                        if (!empty($cv['bank_id'])) {
                            $payArr['type_mode'] = 2;  //2时，跳转到第三方平台接口，同时需要调接口获取可充值银行列表
                        } else {
                            $payArr['type_mode'] = 1;  //1时，跳转到第三方平台接口，不用获取银行列表
                        }
                    }
                    
                    if (!empty($v['prompt'])) {
                        $payArr['prompt'] = explode('|', $v['prompt']);
                    } else {
                        $payArr['prompt'] = [];
                    }
                    
                    $payList[] = $payArr;
                    
                }
            }
        }

        return $payList;
    }
    
    /**
     * 获取用类型、层级和层级状态
     * @param int $userId 用户ID
     * @return array
     */
    public function getRechargeListInfo($userId)
    {
        //初始化redis
        $redis = initCacheRedis();
        //获取平台统一线上、线下充值下限
        $config= $redis->HMGet("Config:recharge",array('value'));
        $lower_limit  = $config['value'];
        //关闭redis链接
        deinitCacheRedis($redis);

        $sql = "select u.`reg_type`, u.`layer_id`, ul.`status` from `un_user` u 
                LEFT JOIN un_user_layer ul ON ul.`layer` = u.layer_id
                where u.`id`= " . $userId;
        $recharge_info = O('model')->db->getone($sql);
        
        $recharge_info['lower_limit'] = $lower_limit;

        return $recharge_info;
    }

    /**
     * 线上扫码充值页面
     */
    public function  rechargeQrcode()
    {
        //验证token
        $this->checkAuth();
        //接收参数
        $payment_id= trim($_REQUEST['payment_id']);
        $money = trim($_REQUEST['money']);
        $order_no = trim($_REQUEST['order_no']);
        $qrcode_url = session::get('qrcode_url');
        $pay_url = session::get('pay_url');
    
        //获取充值渠道信息
        $paymentInfoArr = D('paymentconfig')->getOneCoupon('type, config, nid', array('id' => $payment_id));
        if (empty($paymentInfoArr)) {
            ErrorCode::errorResponse(200021, 'Payment method does not exist');
        }
    
        $configArr = unserialize($paymentInfoArr['config']);
    
        include template('wallet/rechargeQrcode');
    }
    
    //check支付订单状态
    public function checkOrder()
    {
        //验证token
        $this->checkAuth();
        //接收参数
        $order_sn = $_REQUEST['order_sn'];
        $rows = $this->model->osDetail($order_sn, $this->userId);
        if(empty($rows))
        {
            $arr['code'] = -1;
            $arr['msg'] = "Recharge order number does not exist";
            $arr['info'] = "";
        }
        else
        {
            $arr['code'] = 0;
            $arr['msg'] = "Get data successfully";
            $arr['info'] = $rows;
        }

        jsonReturn($arr);
    }
    
    /**
     * 线上、线下充值成功跳转页面
     * @return web
     */
    public function rechargeOk()
    {
        //接收参数
        $order_sn   = trim($_REQUEST['order_sn']);    //支付订单号
        $type       = trim($_REQUEST['type']);        //支付类型：1,线上支付，2，线下支付
        $extra_code = trim($_REQUEST['extra_code']);  //线下支付的附加码
        $bank_link  = trim($_REQUEST['bank_link']);   //线下支持跳转银行链接
        $JumpUrl    = $this->getUrl();
    
        $orderData   = D('accountrecharge')->getOneCoupon('status, money, payment_id', array('order_sn' => $order_sn));
        if (empty($orderData)) {
            return '充值订单号异常，请联系客服！';
        }
    
        $paymentData = D('paymentconfig')->getOneCoupon('name', array('id' => $orderData['payment_id']));
        if (empty($paymentData)) {
            return '充值方式异常，请联系客服！';
        }
    
        $res['tittle'] = $paymentData['name'] . '充值';
        $res['money']  = $orderData['money'];
        $res['status'] = $orderData['status'];
    
        if ($type == 2) {  //线下充值
            $res['name']   = '线下' . $paymentData['name'] . '转账';
            $res['status'] = 1; //提交订单成功（并不等于充值成功）
        } else {           //线上充值
            $res['name'] = '线上' . $paymentData['name'] . '充值';
        }
    
        include template('wallet/rechargeStatus');
    }

    /**
     * 充值记录
     * @method get /index.php?m=api&c=recharge&a=rechargeList&token=b5062b58d2433d1983a5cea888597eb6&payment_nid=1&money=1&extra_code=1
     * @param
     * @return
     */
    //public function rechargeList() {
    public function rechargeRecordList() {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'page'));
        //验证token
        $this->checkAuth();
    
        $redis = initCacheRedis();
        $page_cfg = $redis->hGetAll("Config:100009"); //获取每页展示多少数据
        $pageCnt = $page_cfg['value'];
    
        $userId = $this->userId;
        $list = $this->model->rechargeList($userId, $_REQUEST['page'], $pageCnt);
    
        $data = array();
        $data['list'] = $list;
    
        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($data);
    }
    
    /**
     * 充值订单详情
     */
    public function rechargeOrderDetail() {
        //验证token
        $this->checkAuth();
    
        $recharge_id = $_REQUEST['recharge_id'];
        $info = $this->model->detail($recharge_id, $this->userId);
        if (empty($info)) {
            ErrorCode::errorResponse(200004, 'The deposit ID does not exist');
        }
    
        $data = array(
            'order_sn' => $info['order_sn'],
            'addtime' => date("Y-m-d H:i:s", $info['addtime']),
            'type' => $info['name'],
            'state' => $info['status'],
            'extra_code' => $info['remark'],
            'account' => $info['account']
        );
    
        include template('wallet/rechargeDatails');
    }
    
    /**
     * 支付二维码生成
     */
    public function payQrcode() {
        //验证token
        $this->checkAuth();
    
        //接收生成二维码数据
        $qrcode_url = $_REQUEST['qrcode_url'];
        $sess_qrcode_url = session::get('qrcode_url');
    
        if ($qrcode_url != $sess_qrcode_url) {
            session::set('qrcode_url', null);
            $qrcode_url = '付款二维码url非法，请重新进行充值操作！';
        }
    
        O('phpQRcode');
        $errorCorrectionLevel = "L"; // 纠错级别：L、M、Q、H
        $matrixPointSize = "6"; // 点的大小：1到10
        Qrcode::png($qrcode_url, false, $errorCorrectionLevel, $matrixPointSize);
    }
    
    /**
     * 生成随机附加码
     * @param $len string
     * @param $chars string
     * @return $str string
     */
    private function getRandomString($len, $chars = null) {
        if (is_null($chars)) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        }
        mt_srand(10000000 * (double) microtime());
        for ($i = 0, $str = '', $lc = strlen($chars) - 1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }
}
