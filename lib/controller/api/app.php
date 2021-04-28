<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 13:34
 * desc: 天天反利 玩法介绍
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class AppAction extends Action{
    /**
     * 数据表
     */
    //private $model;

    public function __construct(){
        parent::__construct();
        //$this->model = D('');
    }

    /**
     * 天天返利
     * @method get /index.php?m=api&c=app&a=rebate&token=b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @param is_api_data boolean 是否为api数据，默认为0，0则显示页面，如传1，则显示数据
     * @return mixed
     */
    public function rebate(){
        //验证参数
        $this->checkInput($_REQUEST, array('token'));

        //验证token
        $this->checkAuth();

        //初始化redis
        $redis = initCacheRedis();

        //获取配置参数
        $configJson = $redis -> HMGet("Config:activity",array('value'));
        $infoRt = json_decode($configJson['value'], true);
        
        $infoArr = array();
        foreach ($infoRt as  $value) {
            if($value['status'] == 1){
                $infoArr[] = $value;
                break;
            }
        }

        //关闭redis链接
        deinitCacheRedis($redis);

        if ($_REQUEST['is_api_data'] == '1') {
            ErrorCode::successResponse($infoArr[0]);
        }
        else {
            include template('app-rebate');
        }

    }

    /**
     * 玩法介绍
     * @method get /index.php?m=api&c=app&a=gameList&token=b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @return mixed
     */
    public function gameList(){
        //验证参数
        $this->checkInput($_REQUEST, array('token'));

        //验证token
        $this->checkAuth();
        //初始化redis
        $redis = initCacheRedis();
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
        $gameInfo = array();
        foreach ($LotteryTypeIds as $v){
            $gameInfo[] = $redis->hGetAll("LotteryType:".$v);
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse(array('list' => $gameInfo));
    }

    /**
     * 马甲包链接
     * @method get /index.php?m=api&c=app&a=ma_jia_link
     * @return string
     */
    public function ma_jia_link()
    {
        // //初始化redis
        // $redis = initCacheRedis();
        // //关闭redis链接
        // deinitCacheRedis($redis);
        $rt_data = [
            'url' => '',
        ];
        ErrorCode::successResponse($rt_data);
    }

    /**
     * 玩法介绍信息列表
     * @method get /index.php?m=api&c=app&a=gameplay&token=b5062b58d2433d1983a5cea888597eb6&lottery_type=1
     * @param token string
     * @param lottery_type int
     * @return mixed
     */
    public function gameplay(){
        //验证参数
        //$this->checkInput($_REQUEST, array('token','lottery_type'),'all');
        $lottery_type = trim($_REQUEST['lottery_type']);
        //验证token
        //$this->checkAuth();
        //初始化redis
       /* $redis = initCacheRedis();
        //验证游戏类型
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
        if(!in_array($lottery_type,$LotteryTypeIds)){
            //ErrorCode::errorResponse(100012,'该类型游戏不存在');
        }

        //关闭redis链接
        deinitCacheRedis($redis);*/
        switch($lottery_type){
            case 1:
                include template('appway28');
                break;
            case 2:
                include template('appway-pc10');
                break;
            case 3:
                include template('appway-Canada28');
                break;
            case 4:
                include template('appway-xyft');
                break;
            case 5:
                include template('appway-cqssc');
                break;
            case 6:
                include template('appway-sfc');
                break;
            case 7:
                include template('appway-xglhc');
                break;
            case 8:
                include template('appway-jslhc');
                break;
            case 9:
                include template('appway-jssc');
                break;
            case 10:
                include template('appway-nn');
                break;
            case 11:
                include template('appway-ffc');
                break;
            case 12:
                include template('appway-sjb');
                break;
            case 13:
                include template('appway-sb');
                break;
            case 14:
                include template('appway-ffpk10');
                break;
        }
    }



    /**
     * 自动下载(ios)
     */
    public function appDown() {
        //接收参数
        $id = $_REQUEST['id'];
        $db = getconn();
        $sql = "SELECT url FROM un_version WHERE id = {$id}";
        $res = $db->getone($sql);
        
        include template('app-down');
    }

    /*
     * 客服联系方式
     * */
    public function customerType()
    {
        $redis = initCacheRedis();
        //客服配置
        $val = $redis->hget('Config:kefu_set','value');
        $kefu = decode($val);
        deinitCacheRedis($redis);

        $arr = [['name'=>'weixin', 'value'=>$kefu["weixin"]],[ 'name'=>'qq', 'value'=>$kefu["qq"]],['name' => 'link', 'value' => $kefu['kefu']]];
        $data["status"] = 0;
        $data['ret_msg'] = "Request succeeded";
        $data['info'] = $arr;
        ErrorCode::successResponse($data);
    }

    
    /*
     * 客服联系方式
     * */
    public function addMsgCues()
    {
        //添加后台提示信息
        $arr['status'] = addMsgCue("new_service_msg", array('user_id' => $_SESSION['SN_']['uid']));
        ErrorCode::successResponse($arr);
    }

    /*
     * app版本 app请求是http协议是否带s
     */
    public function getAppVersion(){
        $arr['code'] = 0;
        $arr['msg'] = "Request succeeded";
        $arr['data'] = "0";
        jsonReturn($arr);
    }

    /*
 * app版本 app请求是http协议是否带s
 */
    public function getTips(){
        @$id = $_POST["payment_id"];
        if(!isset($id)) $id = 0;
        $result = $list = $this->db->getone("SELECT prompt from un_payment_config WHERE id = $id");
        jsonReturn($result);
    }

    /**
     *  获取系统维护信息
    */
    public function systemMaintenance(){
        $res = $this->cp();
        $data = array(
            'systemState' => $res,
            'msg' => 'System under maintenance'
        );
        ErrorCode::successResponse($data);
    }

    //获取启动页信息
    public function getStartPageInfo(){
        $newTime = time();
        $sql = "select id,type,img_path,end_time,start_time,state,url from un_start_page where ((start_time < '{$newTime}' and end_time > '{$newTime}') || type = 3) and state = 1";
        $list = $this->db->getall($sql);
        $default = '';
        for ($a=0;$a<count($list);$a++) {
            if($list[$a]['type'] == 3){
                $default = $list[$a];
                unset($list[$a]);
            }
        }

        $new_list = array_merge($list);
        for($i=0;$i<count($new_list);$i++){
            for($j=$i+1;$j<count($new_list);$j++){
                if($new_list[$i]['start_time'] < $new_list[$j]['start_time']){
                    $a = $new_list[$i];
                    $new_list[$i] = $new_list[$j];
                    $new_list[$j] = $a;
                }
            }
        }
        $arr = empty($new_list['0']) ? $default : $new_list['0'];
        $data = array(
            'data' => $arr,
            'ret_msg' => 'Request succeeded'
        );
        ErrorCode::successResponse($data);
    }
    
    /**
     * 文章栏目列表（类别）
     */
    public function listArticleColumn()
    {
        $type = $_REQUEST['type'];
        $articeColumnList = [];
        $columnList = [];
    
        $articeColumnJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'article_column'");
        if (!empty($articeColumnJson)) {
            $articeColumnList = json_decode($articeColumnJson['value'],true);
        }
        
        foreach ($articeColumnList['column'] as $k => $v) {
            if ($v['status'] == 1) {
                //页脚下固定
                if ($type == 1) {
                    if ($v['type'] <= 7) $columnList[] = $v;
                }elseif ($type == 2){
                    //展示页可增加删除
                    if ($v['type'] != 6) $columnList[] = $v;
                }
            }
        }
    
        $data = array(
            'data' => $columnList,
            'ret_msg' => 'Request succeeded'
        );
        
        ErrorCode::successResponse($data);
    }
    
    /**
     *输出文章栏目文章（通过栏目列表获取最新修改的文章）
     */
    public function getTypeArticle()
    {
        $article = [];
        $type = $_REQUEST['type'];
    
        $article = $this->db->getone("SELECT `id`, `title`, `content` FROM `un_article` WHERE `type` = {$type} AND `status` = 1 ORDER BY `edit_time` DESC");
        
        if (!empty($article)) {
            $article['content'] =  htmlspecialchars_decode($article['content']);
        }else {
            $article = [];
        }
        
        $data = array(
            'data' => $article,
            'ret_msg' => 'Request succeeded'
        );
        
        ErrorCode::successResponse($data);
    }
    
    /**
     *输出文章栏目文章
     */
    public function getArticle()
    {
        $article = [];
        $id = $_REQUEST['id'];
    
        $article = $this->db->getone("SELECT `id`, `title`, `content` FROM `un_article` WHERE `status` = 1 AND  `id` = " . $id);
        if (!empty($article)) {
            $article['content'] =  htmlspecialchars_decode($article['content']);
        }else {
            $article = [];
        }
    
        $data = array(
            'data' => ['article' => $article],
            'ret_msg' => 'Request succeeded'
        );
    
        ErrorCode::successResponse($data);
    }

    /**
     * 任务列表接口
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-19 18:31:26
     */
    public function taskIndex(){

        //验证参数
        $this->checkInput($_REQUEST, array('token'));

        //验证token
        $this->checkAuth();

        $model = D("admin/activity");

        $config = $model->getTaskConfig();

        if (empty($config)) {
            ErrorCode::errorResponse('-1',"The current system does not have platform tasks configured");
        } else {

            $model->autoTaskState();
            $taskInfo = $model->taskIndexN($config, $this->userId);
//            $taskInfo = $model->taskIndex($config, $this->userId);
            $data = array(
                'data' => $taskInfo,
                'ret_msg' => 'Request succeeded'
            );
            ErrorCode::successResponse($data);
        }

    }

    /**
     * 平台任务日常签到接口
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-18 15:24:46
     */
    public function taskSign(){
        //验证参数
        $this->checkInput($_REQUEST, array('token','type'));

        //验证token
        $this->checkAuth();

        $model = D("admin/activity");

        $type = trim($_REQUEST['type']);
        if ($type != 7) {
            ErrorCode::errorResponse('-1',"Missing required parameters：type");
        }

        $check_status = $model->doTaskAuth($this->userId);
        if ($check_status !== true) {
            ErrorCode::errorResponse('-1','You cannot login if your bet amount or recharge amount does not meet the requirements');
        }


        //防止用户短时间多次点击提现接口，后台同一个用户出现多个未处理的提现订单（默认下同时间内只有一个提现订单）
        $preventFlag = 'taskSignId' . $this->userId;
        if (preventSupervene($preventFlag, 30)) {
            ErrorCode::errorResponse('-1','Do not login repeatedly');
        }



        $config = $model->getTaskConfig();
        if (empty($config)) {
            ErrorCode::errorResponse('-1',"The current system does not have platform tasks configured");
            exit;
        }

        $arr = $model->taskSuccess(7, $this->userId);
        if (is_array($arr)) {
            ErrorCode::errorResponse('-1',"Do not login repeatedly");
        } else {
            if ($arr !== false) {

                $monday = this_monday();
                $sunday = this_sunday() + 86399;
                $sql = "select count(id) as count from #@_task_prize where user_id = {$this->userId} and type = 7 and complete_time between {$monday} and {$sunday}";
                $count = $this->db->getone($sql)['count'];
                if ($count == 7) {
                    $res = $model->taskSuccess(23, $this->userId);
                    if (is_array($res)) {
                        ErrorCode::errorResponse('-1',"Do not login repeatedly");
                    } else {
                        if ($res !== false) {

                            $rows = $model->receiveTaskReward($this->userId, $res);
                            if ($rows === true) {
                                ErrorCode::successResponse(['ret_msg' => 'Login successfully']);
                            } else {
                                if ($rows !== false) {
                                    ErrorCode::errorResponse('-1',"Login successfully,".$rows);
                                } else {
                                    ErrorCode::errorResponse('-1',"Login successfully, Failure to receive awards");
                                }
                            }

                        } else {
                            ErrorCode::errorResponse('-1',"Login failed");
                        }
                    }

                } else {

                    $rows = $model->receiveTaskReward($this->userId, $arr);
                    if ($rows === true) {
                        ErrorCode::successResponse(['ret_msg' => 'Login successfully']);
                    } else {
                        if ($rows !== false) {
                            ErrorCode::errorResponse('-1',"Login successfully,".$rows);
                        } else {
                            ErrorCode::errorResponse('-1',"Login successfully, Failure to receive awards");
                        }
                    }

                }

            } else {
                ErrorCode::errorResponse('-1',"Login failed");
            }
        }

    }

    /**
     * 领取奖励操作
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-18 15:27:02
     */
    public function receiveTaskReward(){
        //验证参数
        $this->checkInput($_REQUEST, array('token','id'));

        //验证token
        $this->checkAuth();

        $rows = D("admin/activity")->receiveTaskReward($this->userId, $_REQUEST['id']);
        if ($rows === true) {
            ErrorCode::successResponse(['ret_msg' => 'Successfully received task rewards']);
        } else {
            if ($rows !== false) {
                ErrorCode::errorResponse('-1',$rows);
            } else {
                ErrorCode::errorResponse('-1',"Failure to receive the task reward");
            }
        }
    }

    public function clearNewVersion(){

        $files = glob('./pcweb/js/*'); // get all file names

        foreach($files as $file){ // iterate files

            if(is_file($file)) {

                unlink($file); // delete file

            }

        }

        $files = glob('./pcmobile/static/js/*'); // get all file names

        foreach($files as $file){ // iterate files

            if(is_file($file)) {

                unlink($file); // delete file

            }

        }
        
        echo "Success";

    }

    /**
     * 获取签到次数
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-25 10:12:52
     */
    public function getSignCont(){
        //验证参数
        $this->checkInput($_REQUEST, array('token'));

        //验证token
        $this->checkAuth();
        $model = D("admin/activity");
        $config = $model->getTaskConfig();
        if (empty($config)) {
            ErrorCode::errorResponse('-1',"The current system does not have platform tasks configured");
            exit;
        }
        $arr = [];
        foreach ($config as $val) {
            if (empty($val)) {
                continue;
            }
            foreach ($val['config'] as $k => $v) {
                if ($v['id'] == 7 && $v['state'] == 2) {
                    $arr = $v;
                }
            }
        }

        if (!empty($arr)) {
            $monday = this_monday();
            $sunday = this_sunday() + 86399;
            $start_time = strtotime(date("Y-m-d"));
            $end_time = strtotime(date("Y-m-d")) + 86399;
            $sql = "select id,complete_time from #@_task_prize where user_id = {$this->userId} and type = 7 and complete_time between {$monday} and {$sunday}";
            $a = $this->db->getall($sql);
            $arr['is_sign'] = 0;
            $arr['count'] = 0;
            foreach ($a as $val) {
                $arr['count']++;
                if ($val['complete_time'] >= $start_time && $val['complete_time'] <= $end_time) {
                    $arr['is_sign'] = 1;
                }
            }
            $check_status = $model->doTaskAuth($this->userId);
            if ($check_status === true) {
                $data['status'] = "0";
                $data['ret_msg'] = "Request succeeded";
                $data['data'] = $arr;
            } else {
                $data['status'] = "-1";
                $data['ret_msg'] = "You cannot login if your bet amount or recharge amount does not meet the requirements";
                $data['data'] = $check_status;
            }
            jsonReturn($data);
        } else {
            ErrorCode::errorResponse('-1',"Daily login task is not activated");
        }
    }

    /**
     * 获取短连接
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-25 10:12:52
     */
    public function getShortLink(){
        //验证参数
        $this->checkInput($_REQUEST, array('token'));

        //验证token
        $this->checkAuth();
        @$line = $_REQUEST['line'];
        $home = C("app_home");
        if(isset($line)) $home = $line;

        $url_info = [
            'app'=> [
//                'offline' => $home.'/?m=web&c=user&a=register&pid='.$this->userId.'&type=2',
//                'money' => $home.'/?m=web&c=user&a=register&pid='.$this->userId.'&type=3',
                'offline' => $home.'/pcmobile/index.html#/reg?pid='.$this->userId.'&type=2',
                'money' => $home.'/pcmobile/index.html#/reg?pid='.$this->userId.'&type=3',
            ],
            'pc'=> [
                'offline' => $home.'/pcweb/index.html#/reg?pid='.$this->userId.'&type=2',
                'money' => $home.'/pcweb/index.html#/reg?pid='.$this->userId.'&type=3',
            ]
        ];

        $redis = initCacheRedis();
        $reg_sw = $redis->hGet('Config:AgencyRegSwitch','value');
        deinitCacheRedis($redis);
        $url_info["reg_swicth"] = decode($reg_sw);
        
        $key_info = ['211160679','2702428363','569452181','1905839263','783190658'];

        foreach ($url_info as $key => $val) {
            foreach ($val as $k => $v) {
                $url = str_replace("&","%26",$v);
                $url = str_replace("#","%23",$url);
                foreach ($key_info as $value) {
                    $url = "http://api.weibo.com/2/short_url/shorten.json?source={$value}&url_long={$url}";
                    $res = curl_get_content($url);
                    $res = decode($res);
                    if (!isset($res['error_code'])) {
                        $url_info[$key][$k] = $res['urls'][0]['url_short'];
                        break;
                    }
                }
            }
        }

        $data = array(
            'data' => $url_info,
            'ret_msg' => 'Request succeeded'
        );
        ErrorCode::successResponse($data);
    }

    /**
     * 获取平台logo，平台名称等配置信息
     * @copyright gpgao
     * @date 2018-06-05 18:57:55
     */
    public function getPlatformConfig(){
        $redis = initCacheRedis();
        $config = $redis->hGet('Config:appVersion','value');
        deinitCacheRedis($redis);
        $config = decode($config);
        if (!empty($config)) {
            $data = array(
                'data' => $config,
                'ret_msg' => 'Request succeeded'
            );
            ErrorCode::successResponse($data);
        } else {
            ErrorCode::errorResponse('-1',"Network error, please try again");
        }
    }

    /**
     * 获取平台logo，平台名称等配置信息
     * @copyright gpgao
     * @date 2018-06-05 18:57:55
     */
    public function getAppDownload(){
        $sql1 = "select `addtime`,url from un_version where `type`=1 order by addtime desc ";
        $re1 = $this->db->getone($sql1);
        $sql2 = "select `addtime`,url  from un_version where `type`=2 order by addtime desc ";
        $re2 = $this->db->getone($sql2);

        $data = array(
            'android_url' => $re2['url'],
            'ios_url' => $re1['url']
        );
        ErrorCode::successResponse($data);

    }

    /**
     *
     * @copyright gpgao
     * @date 2018-06-07 11:34:00
     * @param type int 活动类型  1:大转盘  2:九宫格 3:福袋  4：刮刮乐
     */
    public function getActivityBack(){
        //验证参数
        $this->checkInput($_REQUEST, array('type'));

        //验证token
//        $this->checkAuth();

        $type = trim($_REQUEST['type']);
        if (!in_array($type,['1','2','3','4'])) {
            ErrorCode::errorResponse('-1',"Illegal request");
        }

        $redis = initCacheRedis();
        $configJson = $redis->hGet('Config:back_ground_config', 'value');
        $config = json_decode($configJson,true);
        deinitCacheRedis($redis);
        $back_type = "";
        foreach ($config as $val) {
            if ($val['activity_type'] == $type ) {
                $back_type = $val['back_type'];
            }
        }
        if (!empty($back_type)) {
            $data = array(
                'data' => $back_type,
                'ret_msg' => 'Request succeeded'
            );
            ErrorCode::successResponse($data);
        } else {
            ErrorCode::errorResponse('-1',"Network error, pleast try again");
        }
    }

    /**
     *
     * @copyright gpgao
     * @date 2018-06-07 11:34:00
     */
    public function getNeedBet()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token'));
        //验证token
        $this->checkAuth();

        $user_id = $_REQUEST['id'];
        $time = $this->db->result("select addtime from un_account_cash where user_id = {$user_id} and (status = 1 or status = 4) order by id desc");
        if (empty($time)) {
            $sql = "SELECT regtime FROM `un_user` WHERE `id` = '{$user_id}'";
            $time = $this->db->result($sql);
        }
        $sql = "select ifnull(sum(money),0) as money from un_orders where user_id = {$user_id} AND award_state>0 AND state=0 AND addtime > {$time}";
        $betRt = $this->db->result($sql);

        $nRt = abs($this->db->result("select value from un_config where nid = 'cashLimit'"));
        $sql  = "select ifnull(sum(money),0) as money from un_account_recharge where user_id = {$user_id} and status = 1 and addtime > {$time}";
        $regRt = $this->db->result($sql);
        $sql = "select bet_amount from un_user where id={$user_id}";
        $bet_amount = $this->db->result($sql);
        $limit = $nRt*$regRt-$betRt+$bet_amount;
        if($limit<0||$nRt==0) $limit = 0;
        $data=[];
        $data["limit"] = $limit;
        ErrorCode::successResponse($data);
    }
        /**
     * 获取彩种长龙
     * @copyright gpgao
     * @date 2018-06-12 16:12:04
     */
    public function getLongDragon(){
        //验证参数
        $this->checkInput($_REQUEST, array('token','lottery_type'));

        //验证token
        $this->checkAuth();

        $lottery_type = $_REQUEST['lottery_type'];

        $data = array(
            'data' => D("workerman")->getLongDragon($lottery_type),
            'ret_msg' => 'Request succeeded'
        );
        ErrorCode::successResponse($data);
    }

    public function getLongDragonOdds(){
        //验证参数
        $this->checkInput($_REQUEST, array('token','room_id','lottery_type'));

        //验证token
        $this->checkAuth();

        $long_dragon_info = array(
            '1'=> array(array('大','小'),array('单','双')),
            '2'=> array(array('大','小'),array('单','双'),array('龙','虎'),array('庄','闲')),
            '3'=> array(array('大','小'),array('单','双')),
            '4'=> array(array('大','小'),array('单','双'),array('龙','虎'),array('庄','闲')),
            '5'=> array(array('大','小'),array('单','双'),array('龙','虎','和')),
            '6'=> array(array('大','小'),array('单','双'),array('龙','虎','和')),
            '7'=> array(
                array('大','小'),array('单','双'),array('红波','蓝波','绿波'),array('尾大','尾小'),
                array('合大','合小'),array('合单','合双'),array('家禽','野兽'),array('总和大','总和小'),
                array('总和单','总和双'),array('龙','虎'),array('总尾大','总尾小')
            ),
            '8'=> array(
                array('大','小'),array('单','双'),array('红波','蓝波','绿波'),array('尾大','尾小'),
                array('合大','合小'),array('合单','合双'),array('家禽','野兽'),array('总和大','总和小'),
                array('总和单','总和双'),array('龙','虎'),array('总尾大','总尾小'),array()
            ),
            '9'=> array(array('大','小'),array('单','双'),array('龙','虎'),array('庄','闲')),
            '10'=> array(array('红方胜','蓝方胜'),array('龙','虎'),array('有公牌','无公牌'),array('大','小'),array('单','双'),array('黑桃','红心','梅花','方块')),
            '11'=> array(array('大','小'),array('单','双'),array('龙','虎','和')),
            '13'=> array(array('大','小'),array('单','双'),array('1','2','3','4','5','6')),
            '14'=> array(array('大','小'),array('单','双'),array('龙','虎'),array('庄','闲')),
        );

        $room_id = $_REQUEST['room_id'];
        $lottery_type = $_REQUEST['lottery_type'];

        $way = $this->db->getall("select way,odds,sort,type from #@_odds where room = {$room_id}");
        foreach ($way as $val) {
            switch ($val['type']){
                case 1:
                    $waysArr['panel_1'][] = $val;
                    break;
                case 2:
                    $waysArr['panel_2'][] = $val;
                    break;
                case 3:
                    $waysArr['panel_3'][] = $val;
                    break;
            }
        }
        if (!empty($waysArr)) {

            $long_dragon = $long_dragon_info[$lottery_type];
            $long_dragon_a = D("workerman")->getLongDragon($lottery_type);

            foreach ($long_dragon as $long_dragon_key => $long_dragon_value) {
                foreach ($long_dragon_a as $long_dragon_a_key => $long_dragon_a_value) {
                    $long_dragon_a[$long_dragon_a_key]['title'] = $long_dragon_a_value['way'];
                    $way = explode("_",$long_dragon_a_value['way']);
                    if (count($way) > 1) {

                        if (in_array($way[1],$long_dragon_value)) {
                            if(!empty($waysArr['panel_2'])){
                                foreach ($waysArr['panel_2'] as $panel_2) {
                                    $way_1 = explode("_",$panel_2['way']);
                                    if (in_array($way_1[1],$long_dragon_value) && $way[0] == $way_1[0]) {
                                        $long_dragon_a[$long_dragon_a_key]['data'][] = $panel_2;
                                    }
                                }
                            }
                            if(!empty($waysArr['panel_3'])){
                                foreach ($waysArr['panel_3'] as $panel_3) {
                                    $way_1 = explode("_",$panel_3['way']);
                                    if (in_array($way_1[1],$long_dragon_value) && $way[0] == $way_1[0]) {
                                        $long_dragon_a[$long_dragon_a_key]['data'][] = $panel_3;
                                    }
                                }
                            }
                            if (empty($long_dragon_a[$long_dragon_a_key]['data'])) {
                                unset($long_dragon_a[$long_dragon_a_key]);
                            }
                        }

                    } else {

                        if (in_array($long_dragon_a_value['way'],$long_dragon_value)) {
                            if(!empty($waysArr['panel_2'])){
                                foreach ($waysArr['panel_2'] as $panel_2) {
                                    if (in_array($panel_2['way'],$long_dragon_value)) {
                                        $long_dragon_a[$long_dragon_a_key]['data'][] = $panel_2;
                                    }
                                }
                            }
                            if(!empty($waysArr['panel_3'])){
                                foreach ($waysArr['panel_3'] as $panel_3) {
                                    if (in_array($panel_3['way'],$long_dragon_value)) {
                                        $long_dragon_a[$long_dragon_a_key]['data'][] = $panel_3;
                                    }
                                }
                            }
                            if (empty($long_dragon_a[$long_dragon_a_key]['data'])) {
                                unset($long_dragon_a[$long_dragon_a_key]);
                            }
                        }
                        
                    }
                }
            }
            $arr = array_values($long_dragon_a);
            $data = array('ret_msg'=>'success','data'=>$arr);
        } else {
            $data = array('ret_msg'=>'No data!');
        }
        ErrorCode::successResponse($data);
    }


}
