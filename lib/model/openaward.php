<?php



/**

 * Created by PhpStorm.

 * User: Administrator

 * Date: 2016/11/14

 * Time: 16:13

 * desc: 开奖记录

 */

!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'model' . DS . 'common.php');



class OpenAwardModel extends CommonModel {

    protected $table = '#@_open_award';

    /**

     * 开奖处理

     */

    public function opendaWard($admin_id) {

        //类型

        $lottery_type = trim($_REQUEST['lottery_type']);



        //接收参数

        $numberA = trim($_REQUEST['numberA']);

        $numberB = trim($_REQUEST['numberB']);

        $numberC = trim($_REQUEST['numberC']);



        //开奖ID

        $id = trim($_REQUEST['id']);

        //期号

        $issue = trim($_REQUEST['issue']);



        //开奖结果

        $numResult = $numberA + $numberB + $numberC;

        $zwWay = ''; //玩法

        if ($numResult >= 0 && $numResult <= 13 ) { //属于小

            $zwWay .= '小';

        } else {

            $zwWay .= '大';

        }

        if ($numResult == '0' || !($numResult % 2)) { //属于双

            $zwWay .= '双';

        } else {

            $zwWay .= '单';

        }

        if ($numResult >= 0 && $numResult <= 5) { //属于极小

            $zwWay .= '极小';

        }elseif ($numResult >= 22 && $numResult <= 27) { //属于极大

            $zwWay .= '极大';

        }



        //个位数补零

        $numberA = strlen($numberA) == '1' ? '0' . $numberA : $numberA;

        $numberB = strlen($numberB) == '1' ? '0' . $numberB : $numberB;

        $numberC = strlen($numberC) == '1' ? '0' . $numberC : $numberC;

        $numResult = strlen($numResult) == '1' ? '0' . $numResult : $numResult; //中奖结果

        $spare_1 = $numberA . '+' . $numberB . '+' . $numberC;  //中奖号码



        $data = array(

            'open_result' => $numResult,

            'spare_1' => $spare_1,

            'spare_2' => $zwWay,

            'state' => 1,

            'user_id' => $admin_id,

        );

        if(empty($id)){

            //获取开奖时间

            $openAwardIssue =  D('openaward')->getOneCoupon('issue', array('issue' => $issue, 'lottery_type' => $lottery_type));

            if(!empty($openAwardIssue)){

                O('model')->db->query("DELETE FROM `un_open_award` WHERE (`issue`='{$issue}' AND lottery_type= {$lottery_type})");

//                echo json_encode(array('status' => '1', 'ret_msg' => '该期号已存在,请用手动开奖'));

//                exit;

            }

            $datetime = trim($_REQUEST['open_time']);

            $data['issue'] = $issue;

            $data['lottery_type'] = $lottery_type;

            $data['open_time'] = strtotime($datetime);

            $data['user_id'] = $admin_id;



            //开奖结果入库 手动补单

            $res = D('openaward')->add($data);

        }else{

            //开奖结果入库 手动开奖

            $res = D('openaward')->save($data, array('issue' => $issue, 'lottery_type' => $lottery_type));

        }



        if (!$res) { //开奖失败

            echo json_encode(array('status' => '1', 'ret_msg' => 'Draw failed'));

            exit;

        }



        //获取开奖时间

        if(empty($id)){

            $openAwardArr['open_time'] = $data['open_time'];

        }else{

            $openAwardArr =  D('openaward')->getOneCoupon('open_time', array('issue' => $issue, 'lottery_type' => $lottery_type));

        }



        //redis初始化

        $redis = initCacheRedis();

        $hzWay = mb_substr($zwWay, 0,2);

        //定义开奖发送数据

        $send = array(

            'commandid'  => 3011,

            'issue'      => $issue,

            'open_time'  => date("m-d H:i:s", $openAwardArr['open_time']),

            'result'     =>  $spare_1 . "=" . $numResult . "({$hzWay})",

            'statistics' => '',

            'test-a-openaward' => '108行',

        );



        //防止中奖结果有时返回单数1或者01

        $numResult = is_string($numResult)?$numResult:(string)$numResult;

        $safetyRes = strlen($numResult) == 2 && (string)$numResult[0] == 0 ? (string)$numResult[1] : $numResult;



        //计算哪些玩法中奖

        $result = array($safetyRes, mb_substr($zwWay, 0,1), mb_substr($zwWay, 1,1), mb_substr($zwWay, 0,2), mb_substr($zwWay, 2));



        if (empty($result[4])) {

            unset($result[4]); //删除为空的玩法

        }



        //特殊玩法

        $specialWay = $redis->hGetAll('Config:specialWay');

        $specialWayArr = json_decode($specialWay['value'], true);

        foreach ($specialWayArr[$lottery_type] as $k => $v) {

            $tempArr = explode(',', $v['way']);

            if (in_array($safetyRes, $tempArr)) { //新增玩法的名称

                $result[] = $k;

                break;

            }

        }



        //判断是否为豹子

        if ($numberA === $numberB && $numberB === $numberC) {

            $result[] = '豹子';

        }

        //判断是否为正顺

        if (intval($numberB) === intval($numberA)+1 && intval($numberC) === intval($numberA)+2) {

            $result[] = '正顺';

        }

        //判断是否为倒顺

        if (intval($numberA) === intval($numberB)+1 && intval($numberA) === intval($numberC)+2) {

            $result[] = '倒顺';

        }

        //判断是否为半顺

        if ((intval($numberA) === intval($numberB)-1 || intval($numberB) === intval($numberC)-1) || (intval($numberB) === intval($numberA)-1 || intval($numberC) === intval($numberB)-1)) {

            $result[] = '半顺';

        }

        //判断是否为对子

        if ($numberA === $numberB || $numberA === $numberC || $numberB === $numberC) {

            $result[] = '对子';

        }

        //判断是否为乱顺

        if (!(intval($numberB) === intval($numberA)+1 && intval($numberC) === intval($numberA)+2)) {

            $newArr = explode("+",$spare_1);

            for ($i = 0; $i < count($newArr); $i++) {

                for ($j = $i+1; $j< count($newArr); $j++) {

                    if ($newArr[$i] > $newArr[$j]) {

                        $b = $newArr[$i];

                        $newArr[$i] = $newArr[$j];

                        $newArr[$j] = $b;

                    }

                }

            }

            if (intval($newArr[1]) === intval($newArr[0])+1 && intval($newArr[2]) === intval($newArr[0])+2) {

                $result[] = '乱顺';

            }

        }



        //查找中奖玩法对应的赔率

        $db = getconn();

        //$odds = $db->query("select way,odds from un_odds WHERE lottery_type='{$lottery_type}' AND  way IN ('".implode("','",$result)."')");

        //彩种赔率改为房间赔率

        $odds = $db->query("select way,odds,room from un_odds WHERE lottery_type='{$lottery_type}' AND  way IN ('".implode("','",$result)."')");

        $oddsa=array();

        foreach ($odds as $v){

            //$oddsa[$v['way']] = $v['odds']; //玩法与赔率对应

            //彩种赔率改为房间赔率

            $oddsa[$v['room']][$v['way']] = $v['odds'];

        }



        //13、14号赔率

        $toddsa = false;

        $toddsa_org=array();

        if(in_array($lottery_type,array(1,3))){

            if ($numResult == 13 || $numResult == 14){

                $tmpArr = $redis->hGetAll('Config:oddsRule');

                $toddsa_org = json_decode($tmpArr['value'],1);

            }

        }



        //查找用户投注该期的数据

        $sql = "select U.reg_type,O.id,O.order_no,O.user_id,O.room_no,O.way,O.money,A.money AS a_money,U.nickname,O.issue from un_orders O LEFT JOIN un_account A ON O.user_id=A.user_id LEFT JOIN un_user U ON O.user_id=U.id WHERE O.issue='{$issue}' AND O.lottery_type='{$lottery_type}' AND O.state='0' AND O.award_state='0' and O.reg_type != 9";

        $list = $db->query($sql);



        $Gateway = O('Gateway');

        $Gateway::$registerAddress = C('Gateway');

        if ($list) {

            $user_acc = array();

            foreach ($list as $v) {

                if($v['reg_type'] == 9)

                {

                    continue;

                }

                $wins = $db->result("select wins from un_orders WHERE  user_id='{$v['user_id']}' AND award_state=2 AND id<'{$v['id']}' order by id desc");

                if (in_array($v['way'], $result)) { //中奖了

                    //针对不同房间进行赔率系数控制

                    //$roomInfo = $redis->hGetAll('allroom:'.$v['room_no']);

                    //$newOdds = (string)($oddsa[$v['way']] * $roomInfo['odds_cof']);

                    //彩种赔率改为房间赔率

                    $newOdds = (string)$oddsa[$v['room_no']][$v['way']]; //当前房间赔率

                    $tempArr = explode('.', $newOdds);

                    if (isset($tempArr[1]) && count($tempArr[1]) > 2) { //小数点控制，最多显示两位

                        $length = count($tempArr[1]) - 2;

                        $newOdds = substr($newOdds, 0, $length);

                    }



                    //开启事务

                    $db->query('BEGIN');

                    try {

                        //添加1314的28类限额--20171130--by joinsen

                        if (in_array($lottery_type, array(1, 3))) {

                            if ((in_array(13, $result) || in_array(14, $result)) && in_array($v['way'], array('大', '小', '单', '双', '大单', '小双', '小单', '大双'))) {

                                $toddsa = $toddsa_org[$v['room_no']];

                                foreach ($toddsa as $k=>$v){

                                    if (empty($v[0]['status'])){

                                        unset($toddsa[$k][0]);

                                    }

                                    if (empty($v[1]['status'])){

                                        unset($toddsa[$k][1]);

                                    }



                                    if (empty($v[2]['status'])){

                                        unset($toddsa[$k][2]);

                                    }

                                    if (empty($v[3]['status'])){

                                        unset($toddsa[$k][3]);

                                    }

                                }

                            }

                        }



                        $todds_type = 0;

                        //13、14号赔率

                        if (!empty($toddsa)){

                            // edit by wangmingxing 赔率规则 修改成三种  每种四条规则

                            if (!empty($toddsa[1]) && in_array($v['way'],array('大','小','单','双'))){

                                $todds_type=1;

                                $ways = "'大','小','单','双'";

                            }elseif (!empty($toddsa[2]) && in_array($v['way'],array('大单','小双'))){

                                $todds_type=2;

                                $ways = "'大单','小双'";

                            }elseif (!empty($toddsa[3]) && in_array($v['way'],array('小单','大双'))){

                                $todds_type=3;

                                $ways = "'小单','大双'";

                            }



                            if ($todds_type > 0 ){

                                $tjmoney = $db->result("select sum(money) from un_orders WHERE  user_id='{$v['user_id']}' AND state=0 AND room_no='{$v['room_no']}' AND issue='{$issue}' AND way IN ({$ways})");

                                foreach ($toddsa_org[$v['room_no']] as $v1){

                                    if (bccomp($tjmoney,$v1[0]['point'],2) == 1){

                                        $oddsb = $v1[0]['ratio'];

                                    }

                                }

                                if (isset($oddsb)){

                                    $money = bcmul($v['money'],$oddsb,2);

                                }else{

                                    $money = bcmul($v['money'],$newOdds,2);

                                }

                                unset($todds_type,$oddsb);

                            }else{

                                $money = bcmul($v['money'],$newOdds,2);

                            }

                        }else{

                            $money = bcmul($v['money'],$newOdds,2); //计算可得金额

                        }



                        if (isset($user_acc[$v['user_id']])){

                            $ye = bcadd($user_acc[$v['user_id']]['money'], $money, 2);

                            $user_acc[$v['user_id']]['money'] = $ye;

                        }else{

                            $ye = bcadd($v['a_money'], $money, 2);

                            $user_acc[$v['user_id']]['nickname'] = $v['nickname'];

                            $user_acc[$v['user_id']]['money'] = $ye;

                            $user_acc[$v['user_id']]['room_no'] = $v['room_no'];

                        }

                        $wins += 1;

                        //更新订单为中奖、中了多少money

                        //$sql = "update un_orders set award_state='2',wins='{$wins}',award='{$money}' WHERE id='{$v['id']}'";

                        $sql = "update un_orders set award_state='2',award='{$money}' WHERE id='{$v['id']}'";

                        $ret = $db->query($sql);

                        if (empty($ret)) {

                            throw new Exception('更新失败!'.$sql);



                        }



                        //增加账户日志记录

                        $logData = array(

                            'order_num' => $v['order_no'],

                            'user_id' => $v['user_id'],

                            'type' => 12,

                            'addtime' => time(),

                            'money' => $money,

                            'use_money' => $ye,

                            'remark' => "The user won the prize, the order number is:" . $v['order_no']

                        );

            //                        $inid = D('accountlog')->add($logData);

                        $inid = D('accountlog')->aadAccountLog($logData);

                        if (empty($inid)) {

                            throw new Exception('账户日志添加失败!');

                        }



                        //更新账户金额

                        $sql = "update un_account set money=money+'{$money}',winning=winning+'{$money}' WHERE user_id='{$v['user_id']}'";

                        $ret = $db->query($sql);

                        if (empty($ret)) {

                            throw new Exception('更新失败!'.$sql);

                        }

                        //追号中奖即停

                        $isZhuiHao = $db->getone("select chase_number from un_orders where order_no = '".$v['order_no']."'");//获取追号标识

                        if(!empty($isZhuiHao)) {

                            $zhuiHaoInfo = $db->getall("select id,money,chase_number,user_id,order_no,issue from un_orders where state = 0 and award_state = 0 and chase_number = '".$isZhuiHao['chase_number']."' and issue > {$v['issue']}");//获取追号数据

                            if(!empty($zhuiHaoInfo)) {

                                foreach ($zhuiHaoInfo as $info) {

                                    $kymoney = $db->getone("select money from un_account where user_id = {$info['user_id']}");

                                    $cdOrderSql = "update un_orders set state=1 WHERE id=".$info['id']."";

                                    $cdOrderRet = $db->query($cdOrderSql);

                                    if (empty($cdOrderRet)) {

                                        throw new Exception('更新失败!'.$cdOrderSql);

                                    };

                                    //更新资金表

                                    $cdAccMoney = $kymoney['money']+$info['money'];

                                    $cdAccSql = "update un_account set money=$cdAccMoney WHERE user_id='{$info['user_id']}'";

                                    $cdAccRet = $db->query($cdAccSql);

                                    if (empty($cdAccRet)) {

                                        throw new Exception('更新失败!'.$cdAccSql);

                                    }

                                    //添加资金明细表 增加账户日志记录

                                    $order_no = "CD" . date("YmdHis") . rand(100, 999);

                                    $cdLogData = array(

                                        'order_num' => $order_no,

                                        'user_id' => $info['user_id'],

                                        'type' => 14,

                                        'addtime' => time(),

                                        'money' => $info['money'],

                                        'use_money' => $cdAccMoney,

                                        'remark' => "Cancel the order after tracking number No ".$info['issue']." ".$info['order_no']." bet"

                                    );

                                    $inid = D('accountlog')->aadAccountLog($cdLogData);

                                    if (empty($inid)) {

                                        throw new Exception('账户日志添加失败!');

                                    }

                                }

                            }

                        }

                        $db->query("COMMIT"); //事务提交

                    } catch (Exception $err) {

                        file_put_contents('error.log', "派奖：".$err->getMessage() . "\n", FILE_APPEND);

                        $db->query("ROLLBACK"); //事务回滚

                    }

                } else {

                    $db->query("update un_orders set award_state='1' WHERE id='{$v['id']}'"); //更新订单为未中奖

                    $db->query("update un_account set winning=winning-'{$v['money']}' WHERE user_id='{$v['user_id']}'"); //更新输赢字段数据

                }



                $moneyData = $db->getone("select sum(money) as betMoney,sum(award) as winMoney from un_orders WHERE  user_id='{$v['user_id']}' AND award_state=2");

                $this->set_honor_score($v['user_id'],$moneyData['betMoney'],$moneyData['winMoney'],$wins);

            }



            $acc_up = array();

            foreach ($user_acc as $k=>$v){

                $money = convert($v['money']); //转化为元宝

//                $Gateway::sendToUid($k,json_encode(array('commandid'=>3010,'money'=>number_format($money, 2, '.', '')))); //发送余额

                $sdata=array( //调用双活接口

                    'type'=>'update_account_by_uid',

//                    'api'=>'sendToUid',

                    'id'=>$v['user_id'],

                    'json'=>json_encode(array('commandid'=>3010,'money'=> number_format($money, 2, '.', ''))),

                );

                send_home_data($sdata);

                if (isset($acc_up[$v['room_no']])){

                    $acc_up[$v['room_no']] .= "    ".$v['nickname'].":".$money."\n";

                }else{

                    $acc_up[$v['room_no']] = "    ".$v['nickname'].":".$money."\n";

                }

            }



        }



        //获取所有房间

        $Ids = $redis->lRange('allroomIds', 0, -1);



        $room = array();

        foreach ($Ids as $v){

            $room[$v] = $redis->hGetAll('allroom:'.$v);

        }

        //关闭redis链接

        deinitCacheRedis($redis);



        foreach ($room as $v){

            if ($v['lottery_type'] == $lottery_type){

                $Gateway::sendToGroup($v['id'],json_encode($send));

                $count = $Gateway::getClientCountByGroup($v['id']);

                $Gateway::sendToGroup($v['id'],json_encode(array('commandid'=>3004,'nickname'=>'','content'=>"    *****欢迎您*****\n\n    ------------\n{$acc_up[$v['id']]}\n\n    ")));

            }

        }



        echo json_encode(array('status' => 0));

    }

    

    //获取幸运28或加拿大28走势信息

    public function trendjnd28Xy28($data)

    {

        //定义大小单双玩法数组

        $arrWay = array('期号', '值', '大','小','单','双','大单','大双','小单','小双');

        

        //获取开奖结果

        //$where = "`state` IN (0,1) AND `lottery_type` = {$data['lottery_type']} AND `open_time` BETWEEN ({$data['start_time']}) AND ({$data['end_time']})";

        //$filed = 'id, issue, open_time, open_result, open_no, spare_1, spare_2, spare_3, lottery_type';

        //$order = 'issue DESC,open_time DESC';

        $sql = "select id, issue, open_time, open_result, open_no, spare_1, spare_2, spare_3, lottery_type from un_open_award 

                where `state` IN (0,1) AND `lottery_type` = {$data['lottery_type']} AND `open_time` BETWEEN ({$data['start_time']}) AND ({$data['end_time']})

                ORDER BY issue DESC,open_time DESC";

        

        $list  = O("model")->db->getall($sql);



        //开奖间隔

        foreach ($list as $k => $v) {

            $tempArr = array();

            //大小单双分隔

            $type  = mb_substr($v['spare_2'], 0, 1, 'utf-8');

            $type2 = mb_substr($v['spare_2'], 1, 1, 'utf-8');

            $type3 = mb_substr($v['spare_2'], 0, 2, 'utf-8');

            $type4 = mb_substr($v['spare_2'], 2, NULL, 'utf-8');

            $temp  = array($type, $type2, $type3, $type4);

            

            //循环匹配

            foreach ($arrWay as $va) {

                if ($va == '期号' || $va == '值') continue;

                $tempArr[] = in_array($va, $temp) ? $va : '';

            }

        

            //过滤掉没有走势的数据

            if ($k == 0 && $list[$k]['spare_3'] == "") { 

                unset($list[$k]);

                continue;

            }

            

            foreach($tempArr as $kt => $vt)

            {

                //大为蓝；小为红；单为蓝；双为红；大单大双为蓝；小单小双为红

                if($vt == "大" || $vt == "单" || $vt == "大单" || $vt == "大双")

                {

                    $tempArr[$kt] = $vt."-red";

                }elseif($vt == "小" || $vt == "双" || $vt == "小单" || $vt == "小双")

                {

                    $tempArr[$kt] = $vt."-blue";

                }

            }



            $list[$k]['spare_2'] = $tempArr;

            $list[$k]['open_result'] = strlen($v['open_result']) == 1 ? '0'.$v['open_result'] : $v['open_result'];

            $list[$k]['spare_3'] = explode(',', $v['spare_3']);

        }

        

        $list = array_values($list);

        

        return ['list' => $list, 'way' => $arrWay];

    }

    

    //获取三分彩或重启时时彩走势信息

    public function trendCqsscSfc($data)

    {

        $sql = "SELECT id, issue, lottery_result AS open_result,lottery_time AS open_time FROM `un_ssc` 

                WHERE `lottery_time` BETWEEN '{$data['start_time']}' AND '{$data['end_time']}' AND `status` IN (0,1) AND `lottery_type` = {$data['lottery_type']} 

                ORDER BY `issue` DESC LIMIT {$data['limit']}";

        $res  = O("model")->db->getall($sql);

        $list = array();

        //定义大小单双玩法数组

        $arrWay = array('期号', '总和', '大','小','单','双','龙虎和');

        foreach ($res as $k => $v){

            $tempArr = [

                '1' => "", //大

                '2' => "", //小

                '3' => "", //单

                '4' => "", //双

                '5' => "", //龙虎和

            ];

            $open_result = 0;

            $spare_2 = D('workerman')->kaijiang_result_ssc($v['open_result']);

            foreach ($spare_2 as $val) {

                if (preg_match("/总和\d+/", $val, $arr)) {

                    $open_result = str_replace("总和"," ", $arr[0]);

                }

                if ($val == "总和_大") {

                    $arr = explode("_",$val);

                    $tempArr[1] = $arr[1];

                }

                if ($val == "总和_小") {

                    $arr = explode("_",$val);

                    $tempArr[2] = $arr[1];

                }

                if ($val == "总和_单") {

                    $arr = explode("_",$val);

                    $tempArr[3] = $arr[1];

                }

                if ($val == "总和_双") {

                    $arr = explode("_",$val);

                    $tempArr[4] = $arr[1];

                }

                if (in_array($val,['龙','虎','和'])) {

                    $tempArr[5] = $val;

                }

            }

            $list[$k]['issue'] = $v['issue'];

            $list[$k]['open_result'] = $open_result;

            $list[$k]['spare_2'] = array_values($tempArr);

        }

        

        return ['list' => $list, 'way' => $arrWay];

    }

    

    //获取六合彩或急速六合彩走势信息

    public function trendLhcJslhc($data)

    {

        $sql = "SELECT id, issue, lottery_result AS open_result, lottery_time AS open_time FROM `un_lhc` WHERE `lottery_time` BETWEEN '{$data['start_time']}' AND '{$data['end_time']}' AND `status` IN (0,1) AND `lottery_type` = {$data['lottery_type']} ORDER BY `issue` DESC LIMIT {$data['limit']}";

        $res  = O("model")->db->getall($sql);

        $list = array();

        $arrWay = array('期号', '总和', '特码','特肖','家禽野兽','波色','大','小','单','双','合大','合小','合单','合双','和');

        foreach ($res as $k => $v){

            $spare_2 = D('workerman')->kaijiang_result_lhc($v['open_result']);

            $open_result_all = explode(",",$v['open_result']);

            $open_result = 0;

            $tempArr = [

                '1' => 0, //特码

                '2' => "", //特肖

                '3' => 0, //家禽还是野兽

                '4' => 0, //波色

                '5' => "", //大

                '6' => "",//小

                '7' => "",//单

                '8' => "",//双

                '9' => "", //合大

                '10' => "",//合小

                '11' => "",//合单

                '12' => "",//合双

                '13' => ""//合

            ];

            foreach ($open_result_all as $val) {

                $open_result += $val;

            }

            foreach ($spare_2 as $value) {

                $result = explode("_",$value);

                if (in_array($result[0],['特肖'])) {

                    $tempArr[2] = $result[1];

                }

                if (in_array($result[0],['特码A'])) {

                    if (in_array($result[1],range(1,49))) {

                        $tempArr[1] = $result[1];

                    }

                    if (in_array($result[1],['家禽','野兽'])) {

                        $tempArr[3] = $result[1];

                    }

                    if (in_array($result[1],['红波','蓝波','绿波'])) {

                        $tempArr[4] = $result[1];

                    }

                    if (in_array($result[1],['大'])) {

                        $tempArr[5] = $result[1];

                    }

                    if (in_array($result[1],['小'])) {

                        $tempArr[6] = $result[1];

                    }

                    if (in_array($result[1],['单'])) {

                        $tempArr[7] = $result[1];

                    }

                    if (in_array($result[1],['双'])) {

                        $tempArr[8] = $result[1];

                    }

                    if (in_array($result[1],['合大'])) {

                        $tempArr[9] = $result[1];

                    }

                    if (in_array($result[1],['合小'])) {

                        $tempArr[10] = $result[1];

                    }

                    if (in_array($result[1],['合单'])) {

                        $tempArr[11] = $result[1];

                    }

                    if (in_array($result[1],['合双'])) {

                        $tempArr[12] = $result[1];

                    }

                    if (in_array($result[1],['和'])) {

                        $tempArr[13] = $result[1];

                    }

                }

            }

            $list[$k]['issue'] = $v['issue'];

            $list[$k]['open_result'] = $open_result;

            $list[$k]['spare_2'] = array_values($tempArr);

        }

        

        return ['list' => $list, 'way' => $arrWay];

    }

    

    //获取北京PK10走势信息

    public function trendBjpk10($data)

    {

        $sql = "SELECT id,qihao AS issue,kaijianghaoma AS open_result,kaijiangshijian AS open_time FROM `un_bjpk10` WHERE `lottery_type` = {$data['lottery_type']} AND `kaijiangshijian` BETWEEN '{$data['start_date']}' AND '{$data['end_date']}' AND `status` IN (0,1) ORDER BY `qihao` DESC LIMIT {$data['limit']}";

        $res  = O("model")->db->getall($sql);

        $list = array();

        //定义大小单双玩法数组

        $typeArr = array('期号', '冠亚和', '大','小','单','双', '龙虎');

        foreach ($res as $k => $v){

            $spare_2 = D('workerman')->kaijiang_result($v['open_result']);

            $strleng = mb_strlen($spare_2[4])-1;

            //定义大小单双玩法数组

            $temp = array();

            $temp[] = mb_substr($spare_2[4],0,1,'utf-8');

            $temp[] = mb_substr($spare_2[4],$strleng,1,'utf-8');

        

            //循环匹配

            $tempArr = array();

            foreach ($typeArr as $v2) {

                if (in_array($v2, ['期号','冠亚和', '龙虎'])) continue;

                $tempArr[] = in_array($v2, $temp) ? $v2 : '';

            }

            $tempArr[] = in_array($spare_2[5], array('龙','虎')) ? $spare_2[5] : '';

        

            foreach($tempArr as $key=>$val)

            {

                //大为蓝；小为红；单为蓝；双为红；大单大双为蓝；小单小双为红

                if($val == "大" || $val == "单" || $val == "大单" || $val == "大双" || $val == "龙")

                {

                    $tempArr[$key] = $val."-blue";

                }

                elseif($val == "小" || $val == "双" || $val == "小单" || $val == "小双" || $val == "虎")

                {

                    $tempArr[$key] = $val."-red";

                }

            }

        

            $list[$k]['issue'] = $v['issue'];

            $list[$k]['spare_2'] = $tempArr;

            //                        $list[$k]['spare_1'] = $spare_2['7'];

            $strleng1 = mb_strlen($spare_2['7']);

            $list[$k]['open_result'] = mb_substr($spare_2['7'],1,$strleng1,'utf-8');

            //                        $list[$k]['open_time'] = $v['open_time'];

        }

        



        return ['list' => $list, 'way' => $typeArr];

    }

    

    //获取幸运飞艇走势信息

    public function trendXyft($data)

    {

        $sql = "SELECT id,qihao AS issue,kaijianghaoma AS open_result,kaijiangshijian AS open_time FROM `un_xyft` WHERE `kaijiangshijian` BETWEEN '{$data['start_date']}' AND '{$data['end_date']}' AND `status` IN (0,1) ORDER BY `qihao` DESC LIMIT {$data['limit']}";

        $res  = O("model")->db->getall($sql);

        $list = array();

        //定义大小单双玩法数组

        $typeArr = array('期号', '冠亚和', '大','小','单','双', '龙虎');

        foreach ($res as $k => $v){

            $spare_2 = D('workerman')->kaijiang_result($v['open_result']);

            $strleng = mb_strlen($spare_2[4])-1;

            //定义大小单双玩法数组

            $temp = array();

            $temp[] = mb_substr($spare_2[4],0,1,'utf-8');

            $temp[] = mb_substr($spare_2[4],$strleng,1,'utf-8');

        

            //循环匹配

            $tempArr = array();

            foreach ($typeArr as $v2) {

                if (in_array($v2, ['期号','冠亚和', '龙虎'])) continue;

                $tempArr[] = in_array($v2, $temp) ? $v2 : '';

            }

            $tempArr[] = in_array($spare_2[5], array('龙','虎')) ? $spare_2[5] : '';

        

            foreach($tempArr as $key=>$val)

            {

                //大为蓝；小为红；单为蓝；双为红；大单大双为蓝；小单小双为红

                if($val == "大" || $val == "单" || $val == "大单" || $val == "大双" || $val == "龙")

                {

                    $tempArr[$key] = $val."-blue";

                }

                elseif($val == "小" || $val == "双" || $val == "小单" || $val == "小双" || $val == "虎")

                {

                    $tempArr[$key] = $val."-red";

                }

            }

        

            $list[$k]['issue'] = $v['issue'];

            $list[$k]['spare_2'] = $tempArr;

            //                        $list[$k]['spare_1'] = $spare_2['7'];

            $strleng1 = mb_strlen($spare_2['7']);

            $list[$k]['open_result'] = mb_substr($spare_2['7'],1,$strleng1,'utf-8');

            //                        $list[$k]['open_time'] = $v['open_time'];

        }

        

        return ['list' => $list, 'way' => $typeArr];

    }

    

    public function trendNiuniu($data)

    {

        $sql = "SELECT id, issue, lottery_result AS open_result,lottery_time AS open_time FROM `un_nn` WHERE `lottery_time` BETWEEN '{$data['start_time']}' AND '{$data['end_time']}' AND `status` IN (0,1) AND `lottery_type` = {$data['lottery_type']} ORDER BY `issue` DESC LIMIT {$data['limit']}";

        $res  = O("model")->db->getall($sql);

        $list = array();

        $typeArr = array('期号', '胜负', '牛牛','公牌','龙虎','总和大','总和小','单','双');

        foreach ($res as $k => $v) {

            $open_result = explode(",",$v['open_result']);

            $spare_1 = checkNiuNiu([$open_result[0],$open_result[1],$open_result[2],$open_result[3],$open_result[4]]);//蓝

            $spare_2 = checkNiuNiu([$open_result[5],$open_result[6],$open_result[7],$open_result[8],$open_result[9]]);//红

            if ($spare_1['lottery_niu_num'] > $spare_2['lottery_niu_num']) {

                $winner = "蓝";

            } elseif($spare_1['lottery_niu_num'] == $spare_2['lottery_niu_num']) {

                if ($spare_1['lottery_max_num'] > $spare_2['lottery_max_num']) {

                    $winner = "蓝";

                } else {

                    $winner = "红";

                }

            } else {

                $winner = "红";

            }



            if ($winner == "蓝") {

                $winner .= '-blue';

                $tmp['niu'] = $spare_1['lottery_niu'];//牛几

                $tmp['gp'] = $spare_1['lottery_gp'] == 1 ? '有-red' : '无-blue';//公牌

                $tmp['lh'] = $spare_1['lottery_lh'] == '龙' ? '龙-red' : '虎-blue';//龙虎

                $tmp['large'] = $spare_1['lottery_sum'] > 35 ? '大' : '';

                $tmp['small'] = $spare_1['lottery_sum'] < 34 ? '小' : '' ;

                $tmp['single'] = $spare_1['lottery_sum']%2 != 0 ? '单' : '';

                $tmp['double'] = $spare_1['lottery_sum']%2 == 0 ? '双' : '';

                //$tmp['he'] = $spare_1['lottery_sum'];//总和

            } else {

                $winner .= '-red';

                $tmp['niu'] = $spare_2['lottery_niu'];//牛几

                $tmp['gp'] = $spare_2['lottery_gp'] == 1 ? '有-red' : '无-blue';//公牌

                $tmp['lh'] = $spare_2['lottery_lh'] == '龙' ? '龙-red' : '虎-blue';//龙虎

                $tmp['large'] = $spare_2['lottery_sum'] > 35 ? '大' : '';

                $tmp['small'] = $spare_2['lottery_sum'] < 34 ? '小' : '' ;

                $tmp['single'] = $spare_2['lottery_sum']%2 != 0 ? '单' : '';

                $tmp['double'] = $spare_2['lottery_sum']%2 == 0 ? '双' : '';

                //$tmp['he'] = $spare_2['lottery_sum'];//总和

            }

            unset($tmp['hua'],$tmp['hua_str'],$tmp['pai'],$tmp['pai_str'],$tmp['lottery_pai_arr'],$tmp['lottery_max_num']);

            $list[$k]['issue'] = $v['issue'];

            $list[$k]['open_result'] = $winner;

            $list[$k]['spare_2'] = array_values($tmp);

        }



        return ['list' => $list, 'way' => $typeArr];

    }



    public function trendSaoBao($data){



        $sql = "SELECT id, issue, lottery_result AS open_result,lottery_time AS open_time FROM `un_sb` WHERE `lottery_time` BETWEEN '{$data['start_time']}' AND '{$data['end_time']}' AND `status` IN (0,1) AND `lottery_type` = {$data['lottery_type']} ORDER BY `issue` DESC LIMIT {$data['limit']}";

        $res  = O("model")->db->getall($sql);

        $list = array();

        $typeArr = ['期号','总和','大','小','单','双','豹子'];



        foreach ($res as $k => $v){

            $tempArr = [

                '1' => "", //总和

                '2' => "", //大

                '3' => "", //小

                '4' => "", //单

                '5' => "", //双

                '6' => "", //豹子

            ];

            $spare_2 = D('workerman')->kaijiang_result_sb($v['open_result']);

            foreach ($spare_2 as $value) {



                if ($value == "总和_大") {

                    $tempArr[2] = str_replace("总和_",'',$value);

                }

                if ($value == "总和_小") {

                    $tempArr[3] = str_replace("总和_",'',$value);

                }

                if ($value == "总和_单") {

                    $tempArr[4] = str_replace("总和_",'',$value);

                }

                if ($value == "总和_双") {

                    $tempArr[5] = str_replace("总和_",'',$value);

                }

                if ($value == "总和_小") {

                    $tempArr[3] = str_replace("总和_",'',$value);

                }

                if (in_array($value,['豹子_1','豹子_2','豹子_3','豹子_4','豹子_5','豹子_6'])) {

                    $tempArr[6] = str_replace("豹子_",'',$value);

                }



            }

            $tempArr[1] = str_replace("总和_",'',$spare_2[9]);

            $list[$k]['issue'] = $v['issue'];



            $list[$k]['spare_2'] = array_values($tempArr);

        }

        return ['list' => $list, 'way' => $typeArr];



    }

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

}

    