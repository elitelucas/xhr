<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/14
 * Time: 14:03
 * desc: 初始化redis缓存数据
 */

!defined('IN_SNYNI') && die('Access Denied!');
ini_set('max_execution_time','0');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class InitCacheAction extends Action{
    public $db;
    protected $model;
    protected $model1;
    protected $model2;
    protected $model3;
    protected $model4;
    protected $model5;
    protected $model6;
    protected $model7;
    protected $model8;
    protected $model9;
    protected $model10;
    protected $model11;
    protected $model12;
    public $cache_redis;

    public function __construct()
    {
        parent::__construct();
        $this->db = getconn();
        $this->model = D('config');
        $this->model1 = D('room');
        $this->model2 = D('banner');
        $this->model3 = D('message');
        $this->model4 = D('lotteryType');
        $this->model5 = D('agent');
        $this->model6 = D('paymentConfig');
        $this->model7 = D('userLayer');
        $this->model8 = D('dictionaryClass');
        $this->model9 = D('dictionary');
        $this->model10 = D('gameWay');
        $this->model11 = D('messageConf');
        $this->model12 = D('group');
    }

    /**
     * @method get /index.php?m=api&c=initCache&a=index&pass=un123456&action=all&param=all 更新所有
     */
    public function index(){
        $param = $_REQUEST;
        //toDo验证用户 预留


        //验证口令
        if (!(isset($param['pass']) && $param['pass'] == C('pass'))) {
            ErrorCode::errorResponse(100001,'Refresh configuration password error');
        }

        //验证参数
        if (!(isset($param['action']) && !empty($param['action']))) {
            ErrorCode::errorResponse(100005,'Please enter the name of the data interface to be initialized');
        }
        if (!(isset($param['param']) && !empty($param['param']))) {
            ErrorCode::errorResponse(100006,'Please enter the data parameters to be initialized');
        }

        //初始化所有redis缓存记录
        $this->cache_redis = initCacheRedis();
        $flag = false;
        if ($param['action'] === 'all' && $param['param'] === 'all') {
            $flag = true;
        }


        //初始化配置
        if ($flag || $param['action'] === 'config') {
            $data = $this->getListConfig();
            $key = array(
                'hk' => 'Config:',
                'lk' => 'ConfigIds',
            );
            $this->load($data,'nid',$key,'LoadConfig');
        }

        //初始化足彩赔率
        if ($flag || $param['action'] === 'fb_odds') {
            D("admin/odds")->loadCupOdds();
        }

        //初始化足彩赛事
        if ($flag || $param['action'] === 'fb_against') {
            D("admin/odds")->loadCupAgainst();
        }

        //初始化游戏类型
        if ($flag || $param['action'] === 'lotteryType') {
            $data = $this->getLotteryType();
            $key = array(
                'hk' => 'LotteryType:',
                'lk' => 'LotteryTypeIds',
            );
            $this->load($data,'id',$key,'LoadLotteryType');
        }

        //初始化字典类型
        if ($flag || $param['action'] === 'dictionaryClass') {
            $data = $this->getDictionaryClass();
            $key = array(
                'hk' => 'DictionaryClass:',
                'lk' => 'DictionaryClassIds',
            );
            $this->load($data,'id',$key,'LoadDictionaryClass');
        }

        //初始化字典表
        if ($flag || $param['action'] === 'dictionary') {
            $TypeIds = $this->cache_redis->lRange("DictionaryClassIds", 0, -1);
            if(in_array($param['param'],$TypeIds)){
                $data = $this->getListDictionary($param['param']);
                $key = array(
                    'hk' => 'Dictionary'.$param['param'].':',
                    'lk' => 'DictionaryIds'.$param['param'],
                );
                $this->load($data,'id',$key,'LoadDictionary'.$param['param']);
            }elseif($param['param'] === 'all'){
                $res = $this->loadDictionary();
                foreach ($res as $k => $v){
                    $key = array(
                        'hk' => 'Dictionary'.$k .':',
                        'lk' => 'DictionaryIds'.$k ,
                    );
                 $this->load($v,'id',$key,'LoadDictionary'.$k );
                }
            }else{
                ErrorCode::errorResponse(100010,'The data to be initialized does not exist');
            }
        }

        //初始化公共房间
        if ($flag || $param['action'] === 'publicRoom') {
            $LotteryTypeIds = $this->cache_redis->lRange("LotteryTypeIds", 0, -1);
            if(in_array($param['param'],$LotteryTypeIds)){
                $data = $this->getListPRoom($param['param']);
                $key = array(
                    'hk' => 'PublicRoom'.$param['param'].':',
                    'lk' => 'PublicRoomIds'.$param['param'],
                );
                $this->load($data,'id',$key,'LoadPublicRoom'.$param['param']);
            }elseif($param['param'] === 'all'){
                $res = $this->loadPublicRoom();
                foreach ($res as $k => $v){
                    $key = array(
                        'hk' => 'PublicRoom'.$k .':',
                        'lk' => 'PublicRoomIds'.$k ,
                    );
                    $this->load($v,'id',$key,'LoadPublicRoom'.$k );
                }
            }else{
                ErrorCode::errorResponse(100010,'The data to be initialized does not exist');
            }
        }

        //初始化私密房间
        if ($flag || $param['action'] === 'room') {
            $LotteryTypeIds = $this->cache_redis->lRange("LotteryTypeIds", 0, -1);
            if(in_array($param['param'],$LotteryTypeIds)){
                $data = $this->getListSRoom($param['param']);
                $key = array(
                    'hk' => 'PrivateRoom'.$param['param'].':',
                    'lk' => 'PrivateRoomIds'.$param['param'],
                );
                $this->load($data,'passwd',$key,'LoadPrivateRoom'.$param['param']);
            }elseif($param['param'] === 'all'){
                $res = $this->loadPrivateRoom();
                foreach ($res as $k => $v){
                    $key = array(
                        'hk' => 'PrivateRoom'.$k .':',
                        'lk' => 'PrivateRoomIds'.$k ,
                    );
                    $this->load($v,'passwd',$key,'LoadPrivateRoom'.$k );
                }
            }else{
                ErrorCode::errorResponse(100010,'The data to be initialized does not exist');
            }
        }

        //初始化轮播图
        if ($flag || $param['action'] === 'banner') {
            $data = $this->getListBanner();
            $key = array(
                'hk' => 'Banner:',
                'lk' => 'BannerIds',
            );
            $this->load($data,'id',$key,'LoadBanner');
        }

        //初始公告
        if ($flag || $param['action'] === 'sysMessage') {
            $data = $this->getListSysMessage();
            $key = array(
                'hk' => 'SysMessage:',
                'lk' => 'SysMessageIds',
            );
            $this->load($data,'id',$key,'LoadSysMessage');
        }


        //初始会员层级
        if ($flag || $param['action'] === 'layer') {
            $data = $this->getListLayer();
            $key = array(
                'hk' => 'Layer:',
                'lk' => 'LayerIds',
            );
            $this->load($data,'layer',$key,'LoadUserLayer');
        }

        //初始第三方支付信息
        if ($flag || $param['action'] === 'paymentConfig') {
            $data = $this->getListPaymentConfig();
            $key = array(
                'hk' => 'paymentConfig:',
                'lk' => 'paymentConfigIds',
            );
            $this->load($data,'id',$key,'LoadpaymentConfig');
        }

        //初始化信息配置
        if ($flag || $param['action'] === 'messageconfig') {
            $res = $this->model11->getList("*",array('state'=>1));
            $this->cache_redis->set('messageconfig',json_encode($res));
            echo "messageconfig data success...<br>";
        }

        if ($flag || $param['action'] === 'allroom') {
            $data = $this->model1->getList('',array('status'=>0));
            $key = array(
                'hk' => 'allroom:',
                'lk' => 'allroomIds',
            );
            $this->load($data,'id',$key,'allroom');
        }

        //初始化代理等级
        if ($flag || $param['action'] === 'agent') {
            $data = $this->getListAgent();
            $key = array(
                'hk' => 'agent:',
                'lk' => 'agentIds',
            );
            $this->load($data,'id',$key,'LoadAgent');
        }


        //初始化会员组
        if ($flag || $param['action'] === 'group') {
            $data = $this->getListGroup();
            $key = array(
                'hk' => 'group:',
                'lk' => 'groupIds',
            );
            $this->load($data,'id',$key,'LoadGroup');
        }

        //初始化游戏玩法
        if ($flag || $param['action'] === 'way') {
            $res = $this->loadGameWay();
            foreach ($res as $k=>$v){
                $this->cache_redis->set('way'.$k,json_encode($v));
            }
        }

//        //更新足彩赛事id
//        $sql = "SELECT match_id FROM `un_room` WHERE `lottery_type` = '12'";
//        $res = $this->db->getall($sql);
//        $this->cache_redis->del('foot_ball_match_ids');
//        foreach ($res as $v){
//            $this->cache_redis->lpush('foot_ball_match_ids',$v['match_id']);
//        }

        //更新六合彩上一次期号
        $sql_issue = "SELECT issue FROM `un_lhc` WHERE lottery_type=7 ORDER BY issue DESC LIMIT 1";
        $reIssue = $this->db->result($sql_issue);
        $re = $this->cache_redis->set('lhc_issue',$reIssue);
        lg('check_lhc_issue','刷新缓存时六合彩写入数据redis'.var_export(array(
                '$re'=>$re,
                '$reIssue'=>$reIssue,
                '$this->cache_redis->get(\'lhc_issue\')'=>$this->cache_redis->get('lhc_issue'),
            ),1));

        //清除redis中的期号表
        $idsArr = $this->cache_redis->lrange('LotteryTypeIds',0,-1);
        foreach ($idsArr as $v) {
            $key = 'QiHaoIds'.$v;
            $re = $this->cache_redis->del($key);
        }

        //删除模板缓存文件
        require S_CORE . 'common/dir.func.php';
        deldir('./caches/cache_tpl/');

        //关闭redis
        deinitCacheRedis($this->cache_redis);
    }

    /**
     * 配置
     */
    public function getListConfig(){
        $res = $this->model->getList();
        return $res;
    }

    /**
     * 游戏类型
     */
    public function getLotteryType(){
        $res = $this->model4->getList('*','');
        $date=array();
        foreach ($res as $v){
            $conf = json_decode($v['config'],true);
            $conf['start_time']=strtotime("1970-01-01 ".$conf['start_time'])+28800;
            $conf['end_time']=strtotime("1970-01-01 ".$conf['end_time'])+28800;
            $date[$v['id']] = array_merge($v,$conf);
        }
        return $date;
    }

    /**
     * 字典类型
     */
    public function getDictionaryClass(){
        $res = $this->model8->getList();
        return $res;
    }

    /**
     * 字典
     */
    private function loadDictionary(){
        $res = $this->getListDictionary();
        $TypeIds = $this->cache_redis->lRange("DictionaryClassIds", 0, -1);
        $room = array();
        foreach ($res as $v) {
            foreach ($TypeIds as $vk){
                if($vk == $v['classid']){
                    $room[$vk][] = $v;
                }
            }
        }
        return $room;
    }

    /**
     * 字典
     */
    public function getListDictionary($type = 'null'){
        if($type !== 'null'){
            $where[] = "classid = ".$type;
        }
        $where[] = "is_sys = 1";
        $res = $this->model9->getList('',$where);
        return $res;
    }


    /**
     * 游戏玩法
     */
    private function loadGameWay(){
        $res = $this->model10->getList('','','sort');

        $data = array();
        foreach ($res as $v) {
            if ($v['type'] == 2) {
                $data[$v['room']]['panel_1'][]= array('title' => $v['way'], 'value' => $v['odds'], 'color' => '', 'sort' => $v['sort']);
                $data[$v['room']]['lottery_type'] =  $v['lottery_type'];
            } elseif ($v['type'] == 1) {
                $data[$v['room']]['panel_2'][] = array('title' => $v['way'], 'value' => $v['odds'], 'color' => '', 'sort' => $v['sort']);
                $data[$v['room']]['lottery_type'] =  $v['lottery_type'];
            } else {
                $data[$v['room']]['panel_3'][]= array('title' => $v['way'], 'value' => $v['odds'], 'sort' => $v['sort']);
                $data[$v['room']]['lottery_type'] =  $v['lottery_type'];
            }
        }
        //添加特殊玩法
        $lotterArr = $this->model4->getList('*','');
        $configArr = array();
        foreach ($lotterArr  as $v){
            $configArr[$v['id']] = json_decode($v['config'], true);
        }
        //$rooms = $this->model1->getList('*','lottery_type='.$v['id']);
//        $specialWay = $this->cache_redis->hMget('Config:specialWay', array('value'));
//        $specialWayArr = json_decode($specialWay['value'], true);
        $roomS = [];
        $tempData = [];
        $tempColorArr = [];
        $redis = initCacheRedis();
        foreach ($res as $v) {
            $tempColor = $panel_3 = array();
            if(!in_array($v['room'], $roomS)) {
                $specialWays = $redis->hget("allroom:".$v['room'],'special_way');
                $specialWayArr = json_decode($specialWays, true);
                if(!empty($specialWayArr['list'])){ //防止刷死
                    foreach ($specialWayArr['list'] as $specialWay) {
                        $tempArr = explode(',', $specialWay['way']);
                        foreach ($tempArr as $v2) { //对应数字颜色的标注
                            $tempColor[$v2] = $specialWay['color'];
                        }
                    }
                }

                $tempData[$v['room']] = $specialWayArr;
                $tempColorArr[$v['room']] = $tempColor;
                $roomS[] = $v['room'];
            }else {
                $specialWayArr = $tempData[$v['room']];
                $tempColor = $tempColorArr[$v['room']];
            }

            //循环匹配颜色
            foreach ($data[$v['room']]['panel_2'] as $k2=>$v2) {
                //筛选号码对应颜色数组
                $v2['color'] = $tempColor[$v2['title']];
                $data[$v['room']]['panel_2'][$k2] = $v2;
            }

            //特殊玩法处理
//            lg('is_disable','$specialWayArr[\'status\']::'.$specialWayArr['status']);
            if ($specialWayArr['status'] == 1) { //开启特殊玩法
//                dump($data[$v['room']]['panel_3']);
                //匹配特殊玩法描述
                foreach ($data[$v['room']]['panel_3'] as $k3=>$v3) {
//                    lg('is_disable','$v3::::'.encode($v3).',$specialWayArr[\'list\']::'.encode($specialWayArr['list']));
                    if($specialWayArr['list'][$v3['title']]['is_disable']==1){ //禁用
                        unset($data[$v['room']]['panel_3'][$k3]);
                    }else{
                        //筛选对应的描述和颜色
                        $v3['desc'] = $specialWayArr['list'][$v3['title']]['desc'];
                        $v3['color'] = $specialWayArr['list'][$v3['title']]['color'];
//                        if($v['lottery_type'] == 5){
//                            if(in_array($v3['title'],array('龙','虎','和'))){
//                                $data[$v['room']]['panel_4'][] = $v3;
//                                unset($data[$v['room']]['panel_3'][$k3]);
//                            }else{
//                                $data[$v['room']]['panel_3'][$k3] = $v3;
//                            }
//                        }else{
//                            $data[$v['room']]['panel_3'][$k3] = $v3;
//                        }
                        $data[$v['room']]['panel_3'][$k3] = $v3;
                    }
                }
                //重置key
                $data[$v['room']]['panel_3'] = array_values($data[$v['room']]['panel_3']);
                if(!empty($data[$v['room']]['panel_4'])){
                    $data[$v['room']]['panel_4'] = array_values($data[$v['room']]['panel_4']);
                }
            }else{
                $data[$v['room']]['panel_3'] = array();
            }
        }
        deinitCacheRedis($redis);
        foreach ($data as $key=>$val) {
            if(in_array($val['lottery_type'],['2','4','9','14'])) {

                $panel_1 = ['list'=>[]];            //双面
                $panel_2 = ['list'=>[]];            //车号
                $panel_3 = ['list'=>[]];            //冠亚和
                $panel_4 = ['list'=>[]];            //庄闲
                $panel_5 = ['list'=>[]];            //冠亚
                $panel_6 = ['list'=>[]];            //龙虎
                foreach ($val['panel_1'] as $va) {
                    $a = explode("_",$va['title']);
                    if(!in_array($a[0],$panel_1['list'])){
                        $panel_1['list'][] = $a[0];
                    }

                    if (strpos($va['title'],"龙") !== false || strpos($va['title'],"虎") !== false) {
                        $desc = '';
                        if ($va['title'] == "冠军_龙") {
                            $desc = '第一名车号大于第十名车号即为中奖';
                        } elseif($va['title'] == "亚军_龙"){
                            $desc = '第二名车号大于第九名车号即为中奖';
                        } elseif($va['title'] == "第三名_龙"){
                            $desc = '第三名车号大于第八名车号即为中奖';
                        } elseif($va['title'] == "第四名_龙"){
                            $desc = '第四名车号大于第七名车号即为中奖';
                        } elseif($va['title'] == "第五名_龙"){
                            $desc = '第五名车号大于第六名车号即为中奖';
                        } elseif($va['title'] == "冠军_虎"){
                            $desc = '第一名车号小于第十名车号即为中奖';
                        } elseif($va['title'] == "亚军_虎"){
                            $desc = '第二名车号小于第九名车号即为中奖';
                        } elseif($va['title'] == "第三名_虎"){
                            $desc = '第三名车号小于第八名车号即为中奖';
                        } elseif($va['title'] == "第四名_虎"){
                            $desc = '第四名车号小于第七名车号即为中奖';
                        } elseif($va['title'] == "第五名_虎"){
                            $desc = '第五名车号小于第六名车号即为中奖';
                        }
                        $panel_6[$a[0]][] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort'],'desc' => $desc];
                    } else {
                        $panel_1[$a[0]][] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort']];
                        $panel_1["说明"] = [
                            '大'=>'中奖和值:[6,7,8,9,10]', //大
                            '小'=>'中奖和值:[1,2,3,4,5]',  //小
                            '单'=>'中奖和值:[1,3,5,7,9]',  //单
                            '双'=>'中奖和值:[2,4,6,8,10]', //双
                            '大单'=>'中奖和值:[7,9]',        //大单
                            '大双'=>'中奖和值:[6,8,10]',     //大双
                            '小单'=>'中奖和值:[1,3,5]',      //小单
                            '小双'=>'中奖和值:[2,4]',        //小双
                        ];
                    }

                }
                foreach ($val['panel_2'] as $va) {
                    $b = explode("_",$va['title']);
                    if(!in_array($b[0],$panel_2['list'])){
                        $panel_2['list'][] = $b[0];
                    }

                    $panel_2[$b[0]][] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort']];
                    $panel_2["说明"] = [
                        '1'=>'中奖号码:[1]', //1
                        '2'=>'中奖号码:[2]', //2
                        '3'=>'中奖号码:[3]', //3
                        '4'=>'中奖号码:[4]', //4
                        '5'=>'中奖号码:[5]', //5
                        '6'=>'中奖号码:[6]', //6
                        '7'=>'中奖号码:[7]', //7
                        '8'=>'中奖号码:[8]', //8
                        '9'=>'中奖号码:[9]', //9
                        '10'=>'中奖号码:[10]' //10
                    ];
                }
                unset($panel_1['list']);
                unset($panel_2['list']);
                unset($panel_6['list']);
                foreach ($panel_1 as &$val_1) {
                    foreach ($val_1 as $va_1) {
                        if (is_array($va_1)) {
                            $sort_arr = array_column($val_1, 'sort');
                            array_multisort($sort_arr, SORT_ASC, $val_1);
                        }
                    }
                }
                foreach ($panel_2 as &$val_2) {
                    foreach ($val_2 as $va_2) {
                        if (is_array($va_2)) {
                            $sort_arr = array_column($val_2, 'sort');
                            array_multisort($sort_arr, SORT_ASC, $val_2);
                        }
                    }
                }
                foreach ($panel_6 as &$val_6) {
                    foreach ($val_6 as $va_6) {
                        if (is_array($va_6)) {
                            $sort_arr = array_column($val_6, 'sort');
                            array_multisort($sort_arr, SORT_ASC, $val_6);
                        }
                    }
                }
                $data[$key]['panel_1'] = $panel_1;
                $data[$key]['panel_2'] = $panel_2;
                $data[$key]['panel_6'] = $panel_6;

                if (!empty($val['panel_3'])) {
                    unset($panel_3['list']);
                    unset($panel_4['list']);
                    unset($panel_5['list']);
//                    $panel_3['区段']=array();
//                    $panel_3['冠亚']=array();
//                    $panel_3['和']=array();
                    foreach ($val['panel_3'] as $va) {
                        if($va['title'] == '庄' || $va['title'] == '闲'){
                            $panel_4['庄闲'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort'],'desc' => $va['desc']];
                        } elseif($va['title'] == "冠亚") {
                            $panel_5[] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort'],'desc' => $va['desc']];
                        } else {
                            if ($va['title'] == "冠亚和_A" || $va['title'] == "冠亚和_B" || $va['title'] == "冠亚和_C") {
                                $panel_3['区段'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort'],'desc' => $va['desc']];
                            } elseif ($va['title'] == "冠亚和_大" || $va['title'] == "冠亚和_小" || $va['title'] == "冠亚和_单" || $va['title'] == "冠亚和_双") {
                                $panel_3['冠亚'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort'],'desc' => $va['desc']];
                            } else {
                                $panel_3['和'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort'],'desc' => $va['desc']];
                            }
                        }
                    }
                    foreach ($panel_3 as &$val_3) {
                        foreach ($val_3 as $va_3) {
                            if (is_array($va_3)) {
                                $sort_arr = array_column($val_3, 'sort');
                                array_multisort($sort_arr, SORT_ASC, $val_3);
                            }
                        }
                    }
                    foreach ($panel_4 as &$val_4) {
                        foreach ($val_4 as $va_4) {
                            if (is_array($va_4)) {
                                $sort_arr = array_column($val_4, 'sort');
                                array_multisort($sort_arr, SORT_ASC, $val_4);
                            }
                        }
                    }
                    foreach ($panel_5 as &$val_5) {
                        foreach ($val_5 as $va_5) {
                            if (is_array($va_5)) {
                                $sort_arr = array_column($val_5, 'sort');
                                array_multisort($sort_arr, SORT_ASC, $val_5);
                            }
                        }
                    }
                    $data[$key]['panel_3'] = $panel_3;
                    $data[$key]['panel_4'] = $panel_4;
                    $data[$key]['panel_5'] = $panel_5;
                }

            } elseif(in_array($val['lottery_type'],['5','6','11'])) {

                $panel_1 = ['list'=>[]];
                $panel_2 = ['list'=>[]];
                $panel_3 = ['list'=>[]];
                $panel_4 = ['list'=>[]];
                foreach ($val['panel_1'] as $va) {
                    $a = explode("_",$va['title']);
                    if(!in_array($a[0],$panel_1['list'])){
                        $panel_1['list'][] = $a[0];
                    }
                    $panel_1[$a[0]][] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort']];
                    $panel_1["说明"] = [
                        '大'=>'中奖号码:[5,6,7,8,9]',
                        '小'=>'中奖号码:[0,1,2,3,4]',
                        '单'=>'中奖号码:[1,3,5,7,9]',
                        '双'=>'中奖号码:[0,2,4,6,8]',
                    ];

                }
                foreach ($val['panel_2'] as $va) {
                    $b = explode("_",$va['title']);
                    if(!in_array($b[0],$panel_2['list'])){
                        $panel_2['list'][] = $b[0];
                    }
                    $panel_2[$b[0]][] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort']];
                    $panel_2["说明"] = [
                        'a0'=>'中奖号码:[0]',
                        'a1'=>'中奖号码:[1]',
                        'a2'=>'中奖号码:[2]',
                        'a3'=>'中奖号码:[3]',
                        'a4'=>'中奖号码:[4]',
                        'a5'=>'中奖号码:[5]',
                        'a6'=>'中奖号码:[6]',
                        'a7'=>'中奖号码:[7]',
                        'a8'=>'中奖号码:[8]',
                        'a9'=>'中奖号码:[9]',
                    ];
                }
                unset($panel_1['list']);
                unset($panel_2['list']);
                foreach ($panel_1 as &$val_1) {
                    foreach ($val_1 as $va_1) {
                        if (is_array($va_1)) {
                            $sort_arr = array_column($val_1, 'sort');
                            array_multisort($sort_arr, SORT_ASC, $val_1);
                        }
                    }
                }
                foreach ($panel_2 as &$val_2) {
                    foreach ($val_2 as $va_2) {
                        if (is_array($va_2)) {
                            $sort_arr = array_column($val_2, 'sort');
                            array_multisort($sort_arr, SORT_ASC, $val_2);
                        }
                    }
                }
                $data[$key]['panel_1'] = $panel_1;
                $data[$key]['panel_2'] = $panel_2;

                if (!empty($val['panel_3'])) {
                    foreach ($val['panel_3'] as $va) {
                        if (in_array($va['title'],['龙', '虎', '和'])) {
                            $panel_4[] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort'],'desc' => $va['desc']];
                        } else {
                            $panel_3[] = ['title'=>$va['title'],'value'=>$va['value'],'sort' => $va['sort'],'desc' => $va['desc']];
                        }

                    }
                    unset($panel_3['list']);
                    unset($panel_4['list']);
                    foreach ($panel_3 as &$val_3) {
                        foreach ($val_3 as $va_1) {
                            if (is_array($va_1)) {
                                $sort_arr = array_column($val_3, 'sort');
                                array_multisort($sort_arr, SORT_ASC, $val_3);
                            }
                        }
                    }

                    foreach ($panel_4 as &$val_4) {
                        foreach ($val_4 as $va_1) {
                            if (is_array($va_1)) {
                                $sort_arr = array_column($val_4, 'sort');
                                array_multisort($sort_arr, SORT_ASC, $val_4);
                            }
                        }
                    }
                    $data[$key]['panel_3'] = $panel_3;
                    $data[$key]['panel_4'] = $panel_4;
                }

            } elseif (in_array($val['lottery_type'],['7','8'])) {

                $panel_1_tmp = ['特码A'=>[],'特码B'=>[]];    //特码
                $panel_2_tmp = ['正码A'=>[],'正码B'=>[]];    //正码
                $panel_3_tmp = ['正1特'=>[],'正2特'=>[],'正3特'=>[],'正4特'=>[],'正5特'=>[],'正6特'=>[]];    //正特1-6
                $panel_4_tmp = ['三中二'=>[],'三全中'=>[],'二全中'=>[],'二中特'=>[],'特串'=>[]];    //连码
                $panel_5_tmp = [];    //半波
                $panel_6_tmp = [];    //尾数
                $panel_7_tmp = [];    //一肖
                $panel_8_tmp = [];    //特肖
                $panel_9_tmp = ['二肖连中'=>[],'三肖连中'=>[],'四肖连中'=>[],'二肖连不中'=>[],'三肖连不中'=>[],'四肖连不中'=>[]];    //连肖
                $panel_10_tmp = ['二尾连中'=>[],'三尾连中'=>[],'四尾连中'=>[],'二尾连不中'=>[],'三尾连不中'=>[],'四尾连不中'=>[]];   //连尾
                $panel_11_tmp = ['五不中'=>[],'六不中'=>[],'七不中'=>[],'八不中'=>[],'九不中'=>[],'十不中'=>[],];   //不中
                $panel_12_tmp = ['正码1'=>[],'正码2'=>[],'正码3'=>[],'正码4'=>[],'正码5'=>[],'正码6'=>[]];   //正码1-6
                $panel_13_tmp = ['1-2球'=>[],'1-3球'=>[],'1-4球'=>[],'1-5球'=>[],'1-6球'=>[],'2-3球'=>[],'2-4球'=>[],'2-5球'=>[],'2-6球'=>[],'3-4球'=>[],'3-5球'=>[],'3-6球'=>[],'4-5球'=>[],'4-6球'=>[],'5-6球'=>[],];   //龙虎1-6

                //文字玩法
                foreach ($val['panel_1'] as $va) {
                    $a = explode("_",$va['title']);
                    if ($a[0] == "特码B") {
                        $panel_1_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "特码A") {
                        $panel_1_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "正码B") {
                        $panel_2_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "正码A") {
                        $panel_2_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }

                    for ($x=1;$x<=6;$x++) {
                        $j = 7 - $x;
                        if ($a[0] == "正{$j}特") {
                            $panel_3_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                        }
                        if ($a[0] == "正码{$j}") {
                            $panel_12_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                        }
                    }
                    if ($a[0] == "半波") {
                        if ($a[1] == "红单") {
                            $rest = [1,7,13,19,23,29,35,45];
                        } elseif ($a[1] == "红双") {
                            $rest = [2,8,12,18,24,30,34,40,46];
                        } elseif ($a[1] == "红大") {
                            $rest = [29,30,34,35,40,45,46];
                        } elseif ($a[1] == "红小") {
                            $rest = [1,2,7,8,12,13,18,19,23,24];
                        } elseif ($a[1] == "红合单") {
                            $rest = [1,7,12,18,23,29,30,34,45];
                        } elseif ($a[1] == "红合双") {
                            $rest = [2,8,13,19,24,35,40,46];
                        } elseif ($a[1] == "绿单") {
                            $rest = [5,11,17,21,27,33,39,43];
                        } elseif ($a[1] == "绿双") {
                            $rest = [6,16,22,28,32,38,44];
                        } elseif ($a[1] == "绿大") {
                            $rest = [27,28,32,33,38,39,43,44];
                        } elseif ($a[1] == "绿小") {
                            $rest = [5,6,11,16,17,21,22];
                        } elseif ($a[1] == "绿合单") {
                            $rest = [5,16,21,27,32,38,43];
                        } elseif ($a[1] == "绿合双") {
                            $rest = [6,11,17,22,28,33,39,44];
                        } elseif ($a[1] == "蓝单") {
                            $rest = [3,9,15,25,31,37,41,47];
                        } elseif ($a[1] == "蓝双") {
                            $rest = [4,10,14,20,26,36,42,48];
                        } elseif ($a[1] == "蓝大") {
                            $rest = [25,26,31,36,37,41,42,47,48];
                        } elseif ($a[1] == "蓝小") {
                            $rest = [3,4,9,10,14,15,20];
                        } elseif ($a[1] == "蓝合单") {
                            $rest = [3,9,10,14,25,36,41,47];
                        } elseif ($a[1] == "蓝合双") {
                            $rest = [4,15,20,26,31,37,42,48];
                        }

                        $panel_5_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest' => $rest];
                    }
                    if ($a[0] == "尾数") {
                        $rest = getLianWei($a[1]);
                        $panel_6_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "一肖") {
                        $rest = getLhcNumber(null, $a[1]);
                        $panel_7_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "特肖") {
                        $rest = getLhcNumber(null, $a[1]);
                        $panel_8_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "四肖连不中") {
                        $rest = getLhcNumber(null, $a[1]);
                        $panel_9_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "三肖连不中") {
                        $rest = getLhcNumber(null, $a[1]);
                        $panel_9_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "二肖连不中") {
                        $rest = getLhcNumber(null, $a[1]);
                        $panel_9_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "四肖连中") {
                        $rest = getLhcNumber(null, $a[1]);
                        $panel_9_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "三肖连中") {
                        $rest = getLhcNumber(null, $a[1]);
                        $panel_9_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "二肖连中") {
                        $rest = getLhcNumber(null, $a[1]);
                        $panel_9_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "四尾连不中") {
                        $rest = getLianWei($a[1]);
                        $panel_10_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "三尾连不中") {
                        $rest = getLianWei($a[1]);
                        $panel_10_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "二尾连不中") {
                        $rest = getLianWei($a[1]);
                        $panel_10_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "四尾连中") {
                        $rest = getLianWei($a[1]);
                        $panel_10_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "三尾连中") {
                        $rest = getLianWei($a[1]);
                        $panel_10_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "二尾连中") {
                        $rest = getLianWei($a[1]);
                        $panel_10_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort'],'rest'=>$rest];
                    }
                    if ($a[0] == "1-2球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "1-3球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "1-4球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "1-5球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "1-6球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "2-3球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "2-4球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "2-5球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "2-6球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "3-4球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "3-5球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "3-6球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "4-5球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "4-6球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "5-6球") {
                        $panel_13_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }

                }


                //数字玩法
                foreach ($val['panel_2'] as $va) {
                    $a = explode("_",$va['title']);
                    if ($a[0] == "特码B") {
                        $panel_1_tmp[$a[0]]['num'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "特码A") {
                        $panel_1_tmp[$a[0]]['num'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "正码B") {
                        $panel_2_tmp[$a[0]]['num'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "正码A") {
                        $panel_2_tmp[$a[0]]['num'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }

                    for ($x=1;$x<=6;$x++) {
                        $j = 7 - $x;
                        if ($a[0] == "正{$j}特") {
                            $panel_3_tmp[$a[0]]['num'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                        }
                        if ($a[0] == "正码{$j}") {
                            $panel_12_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                        }
                    }
                    if ($a[0] == "特串") {
                        $panel_4_tmp[$a[0]]['num'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "二中特") {
                        $panel_4_tmp[$a[0]]['num'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "二全中") {
                        $panel_4_tmp[$a[0]]['num'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "三中二") {
                        $panel_4_tmp[$a[0]]['num'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }

                    if ($a[0] == "三全中") {
                        $panel_4_tmp[$a[0]]['num'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "十不中") {
                        $panel_11_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "九不中") {
                        $panel_11_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "八不中") {
                        $panel_11_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "七不中") {
                        $panel_11_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "六不中") {
                        $panel_11_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                    if ($a[0] == "五不中") {
                        $panel_11_tmp[$a[0]]['text'][] = ['title'=>$va['title'],'value'=>$va['value'],'sort'=>$va['sort']];
                    }
                }
                foreach ($val['panel_2'] as $va) {
                    $a = explode("_",$va['title']);
                    if ($a[0] == "三中二之中三") {
                        foreach ($panel_4_tmp["三中二"]['num'] as $kkk => $aaa) {
                            $x_arr = explode("_",$aaa['title']);
                            if ($a[1] == $x_arr[1]) {
                                $panel_4_tmp["三中二"]['num'][$kkk]['value'] .= "/".$va['value'];
                            }
                        }
                    }
                    if ($a[0] == "二中特之中特") {
                        foreach ($panel_4_tmp["二中特"]['num'] as $kkk => $aaa) {
                            $x_arr = explode("_",$aaa['title']);
                            if ($a[1] == $x_arr[1]) {
                                $panel_4_tmp["二中特"]['num'][$kkk]['value'] .= "/".$va['value'];
                            }
                        }
                    }
                }
                $data[$key]['panel_1'] = $this->paiXuLhcArr($panel_1_tmp);
                $data[$key]['panel_2'] = $this->paiXuLhcArr($panel_2_tmp);
                $data[$key]['panel_3'] = $this->paiXuLhcArr($panel_3_tmp);
                $data[$key]['panel_4'] = $this->paiXuLhcArr($panel_4_tmp);
                $data[$key]['panel_5'] = $this->paiXuLhcArr($panel_5_tmp);
                $data[$key]['panel_6'] = $this->paiXuLhcArr($panel_6_tmp);
                $data[$key]['panel_7'] = $this->paiXuLhcArr($panel_7_tmp);
                $data[$key]['panel_8'] = $this->paiXuLhcArr($panel_8_tmp);
                $data[$key]['panel_9'] = $this->paiXuLhcArr($panel_9_tmp);
                $data[$key]['panel_10'] = $this->paiXuLhcArr($panel_10_tmp);
                $data[$key]['panel_11'] = $this->paiXuLhcArr($panel_11_tmp);
                $data[$key]['panel_12'] = $this->paiXuLhcArr($panel_12_tmp);
                $data[$key]['panel_13'] = $this->paiXuLhcArr($panel_13_tmp);

            } elseif (in_array($val['lottery_type'],['1','3'])) {
	    
                $common_desc_obj_a = [
                    '大' => '中奖和值:[14,15,16,17,18,19,20,21,22,23,24,25,26,27]',
                    '小' => '中奖和值:[0,1,2,3,4,5,6,7,8,9,10,11,12,13]',
                    '单' => '中奖和值:[1,3,5,7,9,11,13,15,17,19,21,23,25,27]',
                    '双' => '中奖和值:[0,2,4,6,8,10,12,14,16,18,20,22,24,26]',

                    '大单' => '中奖和值:[15,17,19,21,23,25,27]',
                    '大双' => '中奖和值:[14,16,18,20,22,24,26]',
                    '小单' => '中奖和值:[1,3,5,7,9,11,13]',
                    '小双' => '中奖和值:[0,2,4,6,8,10,12]',

                    '极大' => '中奖和值:[22,23,24,25,26,27]',
                    '极小' => '中奖和值:[0,1,2,3,4,5]',
                ];

                $common_desc_obj_b = [
                    '0' => '中奖号码:[00]',
                    '1' => '中奖号码:[01]',
                    '2' => '中奖号码:[02]',
                    '3' => '中奖号码:[03]',
                    '4' => '中奖号码:[04]',
                    '5' => '中奖号码:[05]',
                    '6' => '中奖号码:[06]',
                    '7' => '中奖号码:[07]',
                    '8' => '中奖号码:[08]',
                    '9' => '中奖号码:[09]',
                    '10' => '中奖号码:[10]',
                    '11' => '中奖号码:[11]',
                    '12' => '中奖号码:[12]',
                    '13' => '中奖号码:[13]',
                    '14' => '中奖号码:[14]',
                    '15' => '中奖号码:[15]',
                    '16' => '中奖号码:[16]',
                    '17' => '中奖号码:[17]',
                    '18' => '中奖号码:[18]',
                    '19' => '中奖号码:[19]',
                    '20' => '中奖号码:[20]',
                    '21' => '中奖号码:[21]',
                    '22' => '中奖号码:[22]',
                    '23' => '中奖号码:[23]',
                    '24' => '中奖号码:[24]',
                    '25' => '中奖号码:[25]',
                    '26' => '中奖号码:[26]',
                    '27' => '中奖号码:[27]',
                ];

                //添加大小单双、组合、极值的中奖说明字段
                foreach ($val['panel_1'] as &$v1_3_1) {
                    $v1_3_1['desc'] = $common_desc_obj_a[$v1_3_1['title']];
                }

                //添加单点数字的中奖说明字段
                foreach ($val['panel_2'] as &$v1_3_2) {
                    $v1_3_2['desc'] = $common_desc_obj_b[$v1_3_2['title']];
                }

                $data[$key]['panel_1'] = $val['panel_1'];
                $data[$key]['panel_2'] = $val['panel_2'];

            } elseif (in_array($val['lottery_type'],['10'])) {

                $panel_1 = [];//猜牛牛
                $panel_2 = ['第一张'=>[],'第二张'=>[],'第三张'=>[],'第四张'=>[],'第五张'=>[]];//猜牌面
                $panel_3 = ['第一张'=>[],'第二张'=>[],'第三张'=>[],'第四张'=>[],'第五张'=>[]];//猜双面
                $panel_4 = ['第一张'=>[],'第二张'=>[],'第三张'=>[],'第四张'=>[],'第五张'=>[]];//猜花色
                $panel_5 = [];//猜龙虎
                $panel_6 = [];//猜公牌
                $panel_7 = [];//猜总和
                $panel_8 = [];//猜胜负
                $common_desc_obj_1 = [
                    '无牛' => '任意3张总和不能为10或10的倍数',
                    '牛一' => '任意3张总和为10或10的倍数，剩余两张之和个位为1',
                    '牛二' => '任意3张总和为10或10的倍数，剩余两张之和个位为2',
                    '牛三' => '任意3张总和为10或10的倍数，剩余两张之和个位为3',
                    '牛四' => '任意3张总和为10或10的倍数，剩余两张之和个位为4',
                    '牛五' => '任意3张总和为10或10的倍数，剩余两张之和个位为5',
                    '牛六' => '任意3张总和为10或10的倍数，剩余两张之和个位为6',
                    '牛七' => '任意3张总和为10或10的倍数，剩余两张之和个位为7',
                    '牛八' => '任意3张总和为10或10的倍数，剩余两张之和个位为8',
                    '牛九' => '任意3张总和为10或10的倍数，剩余两张之和个位为9',
                    '牛牛' => '任意3张总和为10或10的倍数，剩余两张之和个位为0',
                    '花色牛' => '五张公牌',
                ];
                $common_desc_obj_2 = [
                    '第一张_A' => '中奖和值:[A]',
                    '第一张_2' => '中奖和值:[2]',
                    '第一张_3' => '中奖和值:[3]',
                    '第一张_4' => '中奖和值:[4]',
                    '第一张_5' => '中奖和值:[5]',
                    '第一张_6' => '中奖和值:[6]',
                    '第一张_7' => '中奖和值:[7]',
                    '第一张_8' => '中奖和值:[8]',
                    '第一张_9' => '中奖和值:[9]',
                    '第一张_10' => '中奖和值:[10]',
                    '第一张_J' => '中奖和值:[J]',
                    '第一张_Q' => '中奖和值:[Q]',
                    '第一张_K' => '中奖和值:[K]',
                    '第二张_A' => '中奖和值:[A]',
                    '第二张_2' => '中奖和值:[2]',
                    '第二张_3' => '中奖和值:[3]',
                    '第二张_4' => '中奖和值:[4]',
                    '第二张_5' => '中奖和值:[5]',
                    '第二张_6' => '中奖和值:[6]',
                    '第二张_7' => '中奖和值:[7]',
                    '第二张_8' => '中奖和值:[8]',
                    '第二张_9' => '中奖和值:[9]',
                    '第二张_10' => '中奖和值:[10]',
                    '第二张_J' => '中奖和值:[J]',
                    '第二张_Q' => '中奖和值:[Q]',
                    '第二张_K' => '中奖和值:[K]',
                    '第三张_A' => '中奖和值:[A]',
                    '第三张_2' => '中奖和值:[2]',
                    '第三张_3' => '中奖和值:[3]',
                    '第三张_4' => '中奖和值:[4]',
                    '第三张_5' => '中奖和值:[5]',
                    '第三张_6' => '中奖和值:[6]',
                    '第三张_7' => '中奖和值:[7]',
                    '第三张_8' => '中奖和值:[8]',
                    '第三张_9' => '中奖和值:[9]',
                    '第三张_10' => '中奖和值:[10]',
                    '第三张_J' => '中奖和值:[J]',
                    '第三张_Q' => '中奖和值:[Q]',
                    '第三张_K' => '中奖和值:[K]',
                    '第四张_A' => '中奖和值:[A]',
                    '第四张_2' => '中奖和值:[2]',
                    '第四张_3' => '中奖和值:[3]',
                    '第四张_4' => '中奖和值:[4]',
                    '第四张_5' => '中奖和值:[5]',
                    '第四张_6' => '中奖和值:[6]',
                    '第四张_7' => '中奖和值:[7]',
                    '第四张_8' => '中奖和值:[8]',
                    '第四张_9' => '中奖和值:[9]',
                    '第四张_10' => '中奖和值:[10]',
                    '第四张_J' => '中奖和值:[J]',
                    '第四张_Q' => '中奖和值:[Q]',
                    '第四张_K' => '中奖和值:[K]',
                    '第五张_A' => '中奖和值:[A]',
                    '第五张_2' => '中奖和值:[2]',
                    '第五张_3' => '中奖和值:[3]',
                    '第五张_4' => '中奖和值:[4]',
                    '第五张_5' => '中奖和值:[5]',
                    '第五张_6' => '中奖和值:[6]',
                    '第五张_7' => '中奖和值:[7]',
                    '第五张_8' => '中奖和值:[8]',
                    '第五张_9' => '中奖和值:[9]',
                    '第五张_10' => '中奖和值:[10]',
                    '第五张_J' => '中奖和值:[J]',
                    '第五张_Q' => '中奖和值:[Q]',
                    '第五张_K' => '中奖和值:[K]',
                ];
                $common_desc_obj_3 = [
                    '第一张_大' => '中奖值:[7,8,9,10,J,Q,K]',
                    '第一张_小' => '中奖值:[1,2,3,4,5,6]',
                    '第一张_单' => '中奖值:[1,3,5,7,9,J,K]',
                    '第一张_双' => '中奖值:[2,4,6,8,10,Q]',
                    '第一张_大单' => '中奖值:[7,9,J,K]',
                    '第一张_大双' => '中奖值:[8,10,Q]',
                    '第一张_小单' => '中奖值:[1,3,5]',
                    '第一张_小双' => '中奖值:[2,4,6]',
                    '第二张_大' => '中奖值:[7,8,9,10,J,Q,K]',
                    '第二张_小' => '中奖值:[1,2,3,4,5,6]',
                    '第二张_单' => '中奖值:[1,3,5,7,9,J,K]',
                    '第二张_双' => '中奖值:[2,4,6,8,10,Q]',
                    '第二张_大单' => '中奖值:[7,9,J,K]',
                    '第二张_大双' => '中奖值:[8,10,Q]',
                    '第二张_小单' => '中奖值:[1,3,5]',
                    '第二张_小双' => '中奖值:[2,4,6]',
                    '第三张_大' => '中奖值:[7,8,9,10,J,Q,K]',
                    '第三张_小' => '中奖值:[1,2,3,4,5,6]',
                    '第三张_单' => '中奖值:[1,3,5,7,9,J,K]',
                    '第三张_双' => '中奖值:[2,4,6,8,10,Q]',
                    '第三张_大单' => '中奖值:[7,9,J,K]',
                    '第三张_大双' => '中奖值:[8,10,Q]',
                    '第三张_小单' => '中奖值:[1,3,5]',
                    '第三张_小双' => '中奖值:[2,4,6]',
                    '第四张_大' => '中奖值:[7,8,9,10,J,Q,K]',
                    '第四张_小' => '中奖值:[1,2,3,4,5,6]',
                    '第四张_单' => '中奖值:[1,3,5,7,9,J,K]',
                    '第四张_双' => '中奖值:[2,4,6,8,10,Q]',
                    '第四张_大单' => '中奖值:[7,9,J,K]',
                    '第四张_大双' => '中奖值:[8,10,Q]',
                    '第四张_小单' => '中奖值:[1,3,5]',
                    '第四张_小双' => '中奖值:[2,4,6]',
                    '第五张_大' => '中奖值:[7,8,9,10,J,Q,K]',
                    '第五张_小' => '中奖值:[1,2,3,4,5,6]',
                    '第五张_单' => '中奖值:[1,3,5,7,9,J,K]',
                    '第五张_双' => '中奖值:[2,4,6,8,10,Q]',
                    '第五张_大单' => '中奖值:[7,9,J,K]',
                    '第五张_大双' => '中奖值:[8,10,Q]',
                    '第五张_小单' => '中奖值:[1,3,5]',
                    '第五张_小双' => '中奖值:[2,4,6]',
                ];
                $common_desc_obj_4 = [
                    '红方胜' => '红方的点数大于蓝方',
                    '蓝方胜' => '蓝方的点数大于红方'
                ];
		
                foreach ($val['panel_2'] as &$vv_1) {
                    $vv_1['desc'] = $common_desc_obj_2[$vv_1['title']];
                    $a = explode("_",$vv_1['title']);
                    $panel_2[$a[0]]['title'] = $a[0];
                    $panel_2[$a[0]]['data'][] = $vv_1;
                }

                foreach ($val['panel_1'] as &$vv_2) {
                    $arr_key_nn = array_keys($common_desc_obj_1);
                    $arr_key_zh = array_keys($common_desc_obj_3);
                    if (in_array($vv_2['title'],$arr_key_nn)) {
                        $vv_2['desc'] = $common_desc_obj_1[$vv_2['title']];
                        $panel_1[$vv_2['title']]['title'] = $vv_2['title'];
                        $panel_1[$vv_2['title']]['data'][] = $vv_2;
                    }
                    if (in_array($vv_2['title'],$arr_key_zh)) {
                        $a = explode("_",$vv_2['title']);
                        $vv_2['desc'] = $common_desc_obj_3[$vv_2['title']];
                        $panel_3[$a[0]]['title'] = $a[0];
                        $panel_3[$a[0]]['data'][] = $vv_2;
                    }
                    if (in_array($vv_2['title'],['红方胜','蓝方胜'])) {
                        $vv_2['desc'] = $common_desc_obj_4[$vv_2['title']];
                        $panel_8[$vv_2['title']]['title'] = $vv_2['title'];
                        $panel_8[$vv_2['title']]['data'][] = $vv_2;
                    }
                }

                if (!empty($val['panel_3'])) {
                    foreach ($val['panel_3'] as $vv_3) {
                        $a = explode("_",$vv_3['title']);
                        if (in_array($a[1],['黑桃','梅花','红心','方块'])) {
                            $panel_4[$a[0]]['title'] = $a[0];
                            $panel_4[$a[0]]['data'][] = $vv_3;
                        }
                        if (in_array($vv_3['title'],['龙','虎'])) {
                            $panel_5[$vv_3['title']]['title'] = $vv_3['title'];
                            $panel_5[$vv_3['title']]['data'][] = $vv_3;
                        }
                        if (in_array($vv_3['title'],['有公牌','无公牌'])) {
                            $panel_6[$vv_3['title']]['title'] = $vv_3['title'];
                            $panel_6[$vv_3['title']]['data'][] = $vv_3;
                        }
                        if (in_array($vv_3['title'],['大','小','单','双','大单','大双','小单','小双'])) {
                            $panel_7[$vv_3['title']]['title'] = $vv_3['title'];
                            $panel_7[$vv_3['title']]['data'][] = $vv_3;
                        }
                    }
                }
                $data[$key]['panel_1'] = $this->paiXuNnArr(array_values($panel_1));
                $data[$key]['panel_2'] = $this->paiXuNnArr(array_values($panel_2));
                $data[$key]['panel_3'] = $this->paiXuNnArr(array_values($panel_3));
                $data[$key]['panel_4'] = $this->paiXuNnArr(array_values($panel_4));
                $data[$key]['panel_5'] = $this->paiXuNnArr(array_values($panel_5));
                $data[$key]['panel_6'] = $this->paiXuNnArr(array_values($panel_6));
                $data[$key]['panel_7'] = $this->paiXuNnArr(array_values($panel_7));
                $data[$key]['panel_8'] = $this->paiXuNnArr(array_values($panel_8));

            } elseif(in_array($val['lottery_type'],['13'])) {

                $panel_1 = ["第一骰"=>[],"第二骰"=>[],"第三骰"=>[]];//猜数子
                $panel_2 = ["第一骰"=>[],"第二骰"=>[],"第三骰"=>[]];//猜双面
                $panel_3 = [];//猜总和
                $panel_4 = [];//猜对子
                $panel_5 = [];//猜围骰
                $panel_6 = [];//猜单骰
                $panel_7 = [];//猜双骰
                foreach ($val['panel_2'] as $va) {
                    $a = explode("_",$va['title']);
                    if (in_array($a[0],['第一骰','第二骰','第三骰'])) {
                        $panel_1[$a[0]][] = $va;
                    }
                    if (in_array($a[0],['对子'])) {
                        $panel_4[] = $va;
                    }
                    if (in_array($a[0],['豹子'])) {
                        $panel_5[] = $va;
                    }
                    if (in_array($a[0],['单骰'])) {
                        $panel_6[] = $va;
                    }
                    if (in_array($a[0],['双骰'])) {
                        $panel_7[] = $va;
                    }
                    if (in_array($a[0],['总和'])) {
                        $panel_3[] = $va;
                    }
                }
                foreach ($val['panel_1'] as $va) {
                    $a = explode("_",$va['title']);
                    if (in_array($a[0],['第一骰','第二骰','第三骰'])) {
                        $panel_2[$a[0]][] = $va;
                    }
                    if (in_array($a[0],['总和'])) {
                        $panel_3[] = $va;
                    }

                }

                $data_1 = [];
                foreach ($panel_1 as $key_1=>&$val_1) {
                    foreach ($val_1 as $va_1) {
                        if (is_array($va_1)) {
                            $sort_arr = array_column($val_1, 'sort');
                            array_multisort($sort_arr, SORT_ASC, $val_1);
                        }
                    }
                    $tmp['name'] = $key_1;
                    $tmp['data'] = $val_1;
                    $data_1[] = $tmp;
                }
                $panel_1 = $data_1;

                $data_2 = [];
                foreach ($panel_2 as $key_2=>&$val_2) {
                    foreach ($val_2 as $va_2) {
                        if (is_array($va_2)) {
                            $sort_arr = array_column($val_2, 'sort');
                            array_multisort($sort_arr, SORT_ASC, $val_2);
                        }
                    }
                    $tmp['name'] = $key_2;
                    $tmp['data'] = $val_2;
                    $data_2[] = $tmp;
                }
                $panel_2 = $data_2;


                $sort_arr = array_column($panel_3, 'sort');
                array_multisort($sort_arr, SORT_ASC, $panel_3);

                $sort_arr = array_column($panel_4, 'sort');
                array_multisort($sort_arr, SORT_ASC, $panel_4);

                $sort_arr = array_column($panel_5, 'sort');
                array_multisort($sort_arr, SORT_ASC, $panel_5);

                $sort_arr = array_column($panel_6, 'sort');
                array_multisort($sort_arr, SORT_ASC, $panel_6);

                $sort_arr = array_column($panel_7, 'sort');
                array_multisort($sort_arr, SORT_ASC, $panel_7);

                $data[$key]['panel_1'] = $panel_1;
                $data[$key]['panel_2'] = $panel_2;
                $data[$key]['panel_3'] = $panel_3;
                $data[$key]['panel_4'] = $panel_4;
                $data[$key]['panel_5'] = $panel_5;
                $data[$key]['panel_6'] = $panel_6;
                $data[$key]['panel_7'] = $panel_7;
            }
            unset($data[$key]['lottery_type']);
        }
        return $data;
    }

    /**
     * 公共房间
     */
    private function loadPublicRoom(){
        //查询公共房间
        $res = $this->getListPRoom();
        $LotteryTypeIds = $this->cache_redis->lRange("LotteryTypeIds", 0, -1);
        $room = array();
        foreach ($res as $v) {
            foreach ($LotteryTypeIds as $vk){
                if($vk == $v['lottery_type']){
                    $room[$vk][] = $v;
                }
            }
        }

        foreach ($room as $k => $v){ //查私密房
            //取后台上传的图片
            $tmp_sql = "SELECT avatar FROM `un_room` WHERE `passwd` <> '' AND lottery_type = {$v[0]['lottery_type']}"; //有密码，就是私密房
            $avatar = $this->db->result($tmp_sql);
            if(!empty($avatar)){ //当有值时才添加私密房
                $arr = array(
                    'id' => $k."_".count($v),
                    'title' => '私密房间',
                    'max_number' => '私密房间',
                    // 'avatar' => '/up_files/room/privacy.png?rand='.rand(),
                    'avatar' => $avatar,
                    'lower' => '0',
                    'upper' => '0',
                    'low_yb' => '0',
                    'max_yb' => '0',
                    'passwd' => 'Private',
                    'sort' => count($v)+1,
                    'lottery_type' => $k,
                    'status' => 0,
                    'online' => 0
                );

                array_push($room[$k],$arr);
            }
        }
        return $room;
    }

    /**
     * 公共房间
     */
    public function  getListPRoom($type = 'null'){
        if($type !== 'null'){
            $where[] = "lottery_type = ".$type;
        }
        //$where[] = "status = 0";
        $where[] = "passwd = ''";
        $order = 'sort ASC';
        $res = $this->model1->getList('',$where,$order);
        if($type !== 'null'){
            $arr = array(
                'id' => $type."_".count($res),
                'title' => '私密房间',
                'max_number' => '私密房间',
                'avatar' => '/up_files/room/privacy.png',
                'lower' => '0',
                'upper' => '0',
                'low_yb' => '0',
                'max_yb' => '0',
                'passwd' => 'Private',
                'sort' => count($res)+1,
                'lottery_type' => $type,
                'status' => 0,
                'online' => 0
            );
            array_push($res,$arr);
        }
        $res = $this->convertData($res);
        return $res;
    }

    /**
     * 私密房间
     */
    private function loadPrivateRoom(){
        //查询私密房间
        $res = $this->getListSRoom();
        $LotteryTypeIds = $this->cache_redis->lRange("LotteryTypeIds", 0, -1);
        $room = array();
        foreach ($res as $v) {
            foreach ($LotteryTypeIds as $vk){
                if($vk == $v['lottery_type']){
                    $room[$vk][] = $v;
                }
            }
        }
        return $room;
    }

    /**
     * 私密房间
     */
    public function getListSRoom($type = 'null'){
        if($type !== 'null'){
            $where[] = "lottery_type = ".$type;
        }
        $where[] = "status = 0";
        $where[] = "passwd <> ''";
        $order = 'sort ASC';
        $res = $this->model1->getList('',$where,$order);
        $res = $this->convertData($res);
        return $res;
    }

    /**
     * 获取轮播图
     * @return $res array
     */
    public function getListBanner(){
        $where = array(
            'is_show' => 1,
        );
        $order = 'sort ASC';
        $res = $this->model2->getList("*",$where,$order);
        return $res;
    }

    /**
     * 获取代理等级
     * @return $res array
     */
    public function getListAgent(){
        $res = $this->model5->getList();
        return $res;
    }

    /**
     * 获取代理等级
     * @return $res array
     */
    public function getListGroup(){
        $res = $this->model12->getList();
        return $res;
    }

    /**
     * 获取公告
     * @return $res array
     */
    public function getListSysMessage(){
        $where[] = "user_id = 0";
        $where[] = "touser_id = '0'";
        $where[] = "state = 0";
        $order = 'addtime DESC';
        $res = $this->model3->getList("*",$where,$order);
        return $res;
    }

    /**
     * 获取收款账号信息
     * @return $res array
     */
    public function getListpaymentConfig(){
        $where = array(
        );
        $order = 'sort ASC';
        $res = $this->model6->getList("*",$where);
        return $res;
    }

    /**
     * 获取会员层级
     * @return $res array
     */
    public function getListLayer(){
        $res = $this->model7->getList();
        return $res;
    }

    /**
     * 游戏币换算
     * @param array $data
     * @return array $arr
     */
    public function convertData($data){
        $rmbratio = $this->cache_redis-> HMGet("Config:rmbratio",array('value'));
        $arr = array();
        foreach ($data as $v){
            $v['lower'] = intval($v['lower']) * $rmbratio['value'];
            $v['upper'] = intval($v['upper']) * $rmbratio['value'];
            $arr[] = $v;
        }
        return $arr;
    }
    
    /**
     * 初始化数据
     */
    private function load($data,$index,$key,$name) {
        //清除缓存
        $this->clear(array("{$key['hk']}*", $key['lk']));
        //将查询数据写入redis
        foreach ($data as $v) {
            $id = $v[$index];
            //将对应的键存入队列中
            $this->cache_redis->rPush($key['lk'], $id);
            //将数据写入redis哈希表
            $this->cache_redis->hMset($key['hk'].$id, $v);
        }

        //将更新后的数据显示页面
        $hkeys = count($this->cache_redis->keys("{$key['hk']}*"));
        $listkeys = count($this->cache_redis->lRange($key['lk'], 0, -1));

        echo  "{$name} {HK: {$hkeys}, LK: {$listkeys}} data success...<br/>";
        //jsonReturn(array('status'=>0,'data'=>$msg));
    }

    /**
     * 清楚redis 缓存
     */
    private  function clear($regex) {
        foreach ($regex as $re) {
            $keys = $this->cache_redis->keys($re);
            foreach ($keys as $key) {
                $this->cache_redis->del($key);
            }
        }
    }

    /**
     * 历史版本
     */
    public function versionLog(){
        header("Access-Control-Allow-Origin: *");

        $type = $_REQUEST['type'];
        if(!in_array($type,array(1,2))){
            $type = 1;
        }
        $sql = "SELECT * FROM #@_version WHERE type = {$type} order by id desc";
    	$rt = $this->db->getone($sql);

//        $rt['url'] = $type == 1 ? url('api','app','appDown', array('id' => $rt['id'])) : $rt['url'];

        ErrorCode::successResponse(array("data"=>$rt));
    }

    public function paiXuLhcArr($arr){
        $data = [];
        foreach ($arr as $key => $val) {
            if (!empty($val['text']) && is_array($val['text'])) {
                $val['text'] = multi_array_sort($val['text'],'sort',SORT_ASC);
            }
            if (!empty($val['num'] ) && is_array($val['num'] )) {
                $val['num']  = multi_array_sort($val['num'],'sort',SORT_ASC);
            }
            $tmp = ['name'=>$key,'data'=>$val];
            $data[] = $tmp;
        }
        return $data;
    }

    public function paiXuNnArr($arr){
        foreach ($arr as $key => $val) {
            if (empty($val)) {
                unset($arr[$key]);
            }
            if (!empty($val['data']) && is_array($val['data'])) {
                $arr[$key]['data'] = multi_array_sort($val['data'],'sort',SORT_ASC);
            }
        }
        return $arr;
    }
}