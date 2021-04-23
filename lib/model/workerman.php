<?php
/**
 * User: Alan
 * Date: 2017/06/17
 * desc: 处理workerman请求
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class WorkermanModel extends CommonModel {

    /**
     * @return mixed 机器人数据
     */
    public function get_robot_data(){
        $nowtime = time();
        $sql = "SELECT user_id, lottery_type, username, room_id, way, bet_money, avatar, nickname, conf_id FROM un_bet_list where bet_time = $nowtime";
        $list = $this->db->getall($sql);
        return $list;
    }

    /**
     * @return mixed 飘窗机器人数据
     */
    public function get_barrage_data(){
        $now_time = time();
        $sql = "select a.money, a.way, a.name, a.lottery_type, u.nickname, u.avatar from un_barrage_auto a left join un_user u on a.user_id = u.id where a.barrage_time = {$now_time}";
        $list = $this->db->getall($sql);
        return $list;
    }

    /*
     * 北京PK10开奖
     */
    public function bjpk10_kaijiang($kj_haoma,$qihao,$auto_award=1,$status=0){
        //如果未正确传参, 终止程序
        if(empty($kj_haoma) || empty($qihao)){
            return;
        }
        //算出这期号码的开奖结果
        $kj_result=$this->kaijiang_result($kj_haoma);
        if(!$kj_result){
            return;
        }

        //查询这一期的所有投注（订单状态是正常的, 未开奖的, 非机器人投注的, 彩种为北京PK10,）
        $sql='select id,order_no,user_id,way,money,chase_number,reg_type,room_no from un_orders where issue='.$qihao.' and reg_type!=9 and lottery_type=2 and state=0 and award_state=0';
        $touzhu_res=$this->db->getall($sql);
        $Gateway = O('Gateway');
        $Gateway::$registerAddress = C('Gateway');
        if(count($touzhu_res)==0){
            $sql='update un_bjpk10 set status='.$status.' where qihao='.$qihao;
            $trans_res=$this->db->exec($sql);

            if($auto_award){
                $room=$this->db->query('select id from un_room where lottery_type=2');
                $xiaqi=$qihao+1;
                $qihao_info=$this->db->getone('select qihao,kaijianghaoma,kaijiangshijian from un_bjpk10 where qihao='.$qihao);
                $send = array('commandid'=>3011,'issue'=>$qihao,'open_time'=>substr($qihao_info['kaijiangshijian'], 5),'result'=>$qihao_info['kaijianghaoma'],'statistics'=>'');
                foreach ($room as $k=>$v){
                    Gateway::sendToGroup($v['id'],json_encode($send,JSON_UNESCAPED_UNICODE));
                    Gateway::sendToGroup($v['id'],json_encode(array('commandid'=>3004,'nickname'=>'','content'=>"    *****Welcome*****\n\n    ------------\n\n\n    No {$xiaqi} can bet now")));
                }
            }

            if (empty($trans_res)){
                throw new Exception('Update failed!'.$sql);
            }
            return;
        }

        //得到赔率信息
        $peilv_res=$this->db->getall('select way,odds,room from un_odds where lottery_type=2');
        if(!$peilv_res){
            return;
        }
        $peilv_arr=array();
        foreach ($peilv_res as $peilv_k=>$peilv_v){
            $peilv_arr[$peilv_v['way'].$peilv_v['room']]=$peilv_v['odds'];
        }

        //循环判断订单是否中奖
        foreach ($touzhu_res as $k=>$v){
            $this->db->query('START TRANSACTION');
            try {
                $zhongjiangjine=0;
                $peilv=0;

                //用户余额信息
                $ye_temp=$this->db->getone('select money from un_account where user_id='.$v['user_id']);
                $ye=$ye_temp['money'];

                $moneyData = $this->db->getone("select sum(money) as betMoney,sum(award) as winMoney from un_orders WHERE user_id='{$v['user_id']}' AND state=0");

                //最大连赢
                $wins_temp = $this->db->getone("select max(wins) as lianying from un_orders WHERE  user_id='{$v['user_id']}' AND award_state=2 AND id<'{$v['id']}' order by id desc");
                $wins=$wins_temp?$wins_temp['lianying']:0;

                //如果下注的单在开奖结果中, 说明是中奖了
                if(in_array($v['way'], $kj_result)){
                    //此玩法在此房间的赔率
                    $peilv=$peilv_arr[$v['way'].$v['room_no']];
                    $peilv=$peilv?$peilv:0;

                    $zhongjiangjine=bcmul($v[money], $peilv,2);

                    $ye+=$zhongjiangjine;

                    //给用户加钱
                    $sql_account="update un_account set money=money+'{$zhongjiangjine}',winning=winning+'{$zhongjiangjine}' WHERE user_id='{$v['user_id']}'";
                    $trans_res=$this->db->exec($sql_account);
                    if(!$trans_res){
                        throw new Exception('Update failed!'.$sql_account);
                    }
                    //修改订单状态
                    $sql_orders = "update un_orders set award_state='2',award='{$zhongjiangjine}' WHERE id='{$v['id']}'";
                    $trans_res = $this->db->exec($sql_orders);
                    if (empty($trans_res)){
                        throw new Exception('Update failed!'.$sql_orders);
                    }
                    //资金交易明细
                    $log_data=array('order_num' => $v['order_no'], 'user_id' => $v['user_id'], 'type' => 12, 'addtime' => time(), 'money' => $zhongjiangjine, 'use_money' => $ye, 'remark' => "The user won the prize, the order number is:" . $v['order_no'], 'reg_type' => $v['reg_type']);
                    $trans_res= $this->db->insert('un_account_log',$log_data);
                    if (empty($trans_res)){
                        throw new Exception('Update failed!'.$sql_orders);
                    }
                    //连赢加1
                    $sql = "update un_orders set wins=$wins+1 WHERE id='{$v['id']}' and user_id='{$v['user_id']}'";
                    $trans_res=$this->db->exec($sql);
                    if (empty($trans_res)){
                        throw new Exception('Update failed!'.$sql_orders);
                    }

                    //追中即停
                    if(!empty($v['chase_number'])){
                        $zhuiHaoInfo = $this->db->getall("select id,money,chase_number,user_id,order_no,issue from un_orders where state = 0 and award_state = 0 and chase_number = '".$v['chase_number']."' and issue > $qihao");
                        if(!empty($zhuiHaoInfo)) {
                            foreach ($zhuiHaoInfo as $info) {
                                $kymoney_temp = $this->db->getone("select money from un_account where user_id = {$info['user_id']}");
                                $kymoney=$kymoney_temp['money'];
                                $cdOrderSql = "update un_orders set state=1 WHERE id=".$info['id']."";
                                $cdOrderRet = $this->db->exec($cdOrderSql);
                                if (empty($cdOrderRet)) {
                                    throw new Exception('Update failed!'.$cdOrderSql);
                                };
                                //更新资金表
                                $cdAccMoney = $kymoney+$info['money'];
                                $cdAccSql = "update un_account set money=$cdAccMoney WHERE user_id='{$info['user_id']}'";
                                $cdAccRet = $this->db->exec($cdAccSql);
                                if (empty($cdAccRet)) {
                                    throw new Exception('Update failed!'.$cdAccSql);
                                }
                                //添加资金明细表
                                $order_num = "CD" . date("YmdHis") . rand(100, 999);
                                $cd_log=array('order_num' => $order_num, 'user_id' => $info['user_id'], 'type' => 14, 'addtime' => time(), 'money' => $info['money'], 'use_money' => $cdAccMoney, 'remark' => "Cancel the order after tracking number No ".$info['issue']." ".$info['order_no']." bet", 'reg_type' => $v['reg_type']);
                                $cdLogRet = $this->db->insert('un_account_log',$cd_log);
                                if (empty($cdLogRet)) {
                                    throw new Exception('Update failed!');
                                }
                            }
                        }
                    }

                    //中奖荣誉积分计算与判断
                    exchangeIntegral($zhongjiangjine, $v['user_id'], 3);
                }else{
                    //未中奖, 更新订单状态
                    $sql_orders="update un_orders set award_state='1' WHERE id='{$v['id']}'";
                    $trans_res=$this->db->exec($sql_orders);
                    if (empty($trans_res)){
                        throw new Exception('Update failed!'.$sql_orders);
                    }
                    //更新用户输赢金额
                    $sql_shuying="update un_account set winning=winning-'{$v['money']}' WHERE user_id='{$v['user_id']}'";
                    $trans_res=$this->db->exec($sql_shuying);
                    if (empty($trans_res)){
                        throw new Exception('Update failed!'.$sql_orders);
                    }
                }

                $this->db->query('COMMIT');

                //投注荣誉积分计算与判断
                exchangeIntegral($v[money], $v['user_id'], 2);
                //$this->set_honor_score($v['user_id'],$moneyData['betMoney'],$moneyData['winMoney'],$wins);

                $Gateway->sendToUid($v['user_id'],json_encode(array('commandid'=>3010,'money'=> number_format($ye, 2, '.', ''))));

            } catch (Exception $e) {
                $this->db->query('ROLLBACK');
            }
        }

        $sql='update un_bjpk10 set status='.$status. ' where qihao='.$qihao;
        $trans_res=$this->db->exec($sql);

        if($auto_award){
            $room=$this->db->query('select id from un_room where lottery_type=2');
            $xiaqi=$qihao+1;
            $qihao_info=$this->db->getone('select qihao,kaijianghaoma,kaijiangshijian from un_bjpk10 where qihao='.$qihao);
            $send = array('commandid'=>3011,'issue'=>$qihao,'open_time'=>substr($qihao_info['kaijiangshijian'], 5),'result'=>$qihao_info['kaijianghaoma'],'statistics'=>'');
            foreach ($room as $k=>$v){
                Gateway::sendToGroup($v['id'],json_encode($send,JSON_UNESCAPED_UNICODE));
                Gateway::sendToGroup($v['id'],json_encode(array('commandid'=>3004,'nickname'=>'','content'=>"    *****Welcome*****\n\n    ------------\n\n\n    No {$xiaqi} can bet now ")));
            }
        }
    }

    /*
     * 获取追号标识
     */
    public function getRandomString($len, $chars = null) {
        if (is_null($chars)) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        }
        mt_srand(10000000 * (double) microtime());
        for ($i = 0, $str = '', $lc = strlen($chars) - 1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }

    /*
     *
     * $data 结构3x9 28 kjjg
     * 28类的计算开奖结果
    */
    /**
     * @param $data
     * @return array
     */
    public function kaijiang_result_28($data,$lt){
        //开奖结果
        //防止中奖结果有时返回单数1或者01,去0操作
        $numResult = intval($data[1]);

        $zwWay = []; //玩法
        if ($numResult >= 0 && $numResult <= 13) { //属于小
            $zwWay['dx'] = '小';
        } else {
            $zwWay['dx'] = '大';
        }
        if ($numResult == 0 || !($numResult % 2)) { //属于双
            $zwWay['ds'] = '双';
        } else {
            $zwWay['ds'] = '单';
        }
        if ($numResult >= 0 && $numResult <= 5) { //属于极小
            $zwWay['jz'] = '极小';
        } elseif ($numResult >= 22 && $numResult <= 27) { //属于极大
            $zwWay['jz'] = '极大';
        }
        $result = array((string)$numResult,$zwWay['dx'],$zwWay['ds'],$zwWay['dx'].$zwWay['ds'],$zwWay['jz']);
        if (empty($result[4])){
            unset($result[4]);
        }
        //处理特殊玩法
        //红
        if (in_array($numResult,array(3,6,9,12,15,18,21,24))) {
            $result[] = '红';
        }

        //绿
        if (in_array($numResult,array(1,4,7,10,16,19,22,25))) {
            $result[] = '绿';
        }

        //蓝
        if (in_array($numResult,array(2,5,8,11,17,20,23,26))) {
            $result[] = '蓝';
        }

        //其它
        if (in_array($numResult,array(0,13,14,27))) {
            $result[] = '其它';
        }

        $bzArr = explode('+', $data[0]);
        //判断是否为豹子 三个数相同
        if ($bzArr[0] === $bzArr[1] && $bzArr[1] === $bzArr[2]) {
            $result[] = '豹子';
        }

        //判断是否为正顺 三个数递增
        if (intval($bzArr[1]) === intval($bzArr[0])+1 && intval($bzArr[2]) === intval($bzArr[0])+2) {
            $result[] = '正顺';
        }
        //判断是否为倒顺
        if (intval($bzArr[0]) === intval($bzArr[1])+1 && intval($bzArr[0]) === intval($bzArr[2])+2) {
            $result[] = '倒顺';
        }
        //判断是否为半顺
        if ((intval($bzArr[0]) === intval($bzArr[1])-1 || intval($bzArr[1]) === intval($bzArr[2])-1) || (intval($bzArr[1]) === intval($bzArr[0])-1 || intval($bzArr[2]) === intval($bzArr[1])-1)) {
            $result[] = '半顺';
        }

        //判断是否为对子 只要有两个数相同
        if ($bzArr[0] === $bzArr[1] || $bzArr[0]=== $bzArr[2] || $bzArr[1] === $bzArr[2]) {
            $result[] = '对子';
        }

	    //判断是否为乱顺
        sort($bzArr);

        if(intval($bzArr[0]) ==0 && intval($bzArr[1]) ==8 && intval($bzArr[2]) ==9){ //排除0 9 8

        }else{
            if (intval($bzArr[1]) === intval($bzArr[0])+1 && intval($bzArr[2]) === intval($bzArr[0])+2) {
                $result[] = '乱顺';
            }
        }

        $result = array_values($result);

        return $result;
    }

    /**
     *
     * 六合彩开奖结果
     *
     */
    public function kaijiang_result_lhc($kj_haoma){
        if(!empty($kj_haoma)){
            $data = explode(',', $kj_haoma);
            if(count($data)!=7){
                return false;
            }else{
                //定义波色
                $red = array(1,2,7,8,12,13,18,19,23,24,29,30,34,35,40,45,46);
                $blue = array(3,4,9,10,14,15,20,25,26,31,36,37,41,42,47,48);
                $green = array(5,6,11,16,17,21,22,27,28,32,33,38,39,43,44,49);
                $sum = array_sum($data);
                $tm = $data['6'];  //特码
                $zm = array_slice($data, 0,6);
                $zmSum = array_sum($zm);
                $list = [];
                $result = array();
                $return_zm = [];  //正码
                $sxArr = array();

                $result[] = '特码A_'.$tm;
                $result[] = '特码B_'.$tm;
                for ($i=0; $i < count($data); $i++) {
                    $_s  = getLhcShengxiao($data[$i]);
                    $result[] = '生肖'.($i+1).'_'.$_s;
                    $sxArr[] = $_s;
                }

                $sxArr = array_unique($sxArr); //去重

                if(in_array(str_replace('生肖7_','',$result[8]), array('牛','马','羊','鸡','狗','猪'))){
                    $jqys = '家禽';
                    $result[] = '特码A_家禽';
                    $result[] = '特码B_家禽';
                }else{
                    $jqys = '野兽';
                    $result[] = '特码A_野兽';
                    $result[] = '特码B_野兽';
                }

                $result[] = '特肖_'.getLhcShengxiao($tm);

                if($tm==49){
                    $dx = '和';
                    $result[] = '特码A_和';
                    $result[] = '特码B_和';
                }else if($tm>=25){
                    $dx = '大';
                    $result[] = '特码A_大';
                    $result[] = '特码B_大';
                    if($tm%2==0){
                        $dxds = '大双';
                        //$ds = '双';
                    }else{
                        $dxds = '大单';
                    }
                }else{
                    $dx = '小';
                    $result[] = '特码A_小';
                    $result[] = '特码B_小';
                    if($tm%2==0){
                        $dxds = '小双';
                    }else{
                        $dxds = '小单';
                    }
                }

                if($tm==49) {
                    $ds = '和';
                    $result[] = '特码A_和';
                    $result[] = '特码B_和';
                }elseif($tm%2==0){
                    $ds = '双';
                    $result[] = '特码A_双';
                    $result[] = '特码B_双';
                }else{
                    $ds = '单';
                    $result[] = '特码A_单';
                    $result[] = '特码B_单';
                }

                if($tm==49) {
                    $wdx = '和';
                    $result[] = '特码A_和';
                    $result[] = '特码B_和';
                }elseif( substr($tm,-1,1)>=5 ){
                    $wdx = '尾大';
                    $result[] = '特码A_尾大';
                    $result[] = '特码B_尾大';
                }else{
                    $wds = '尾小';
                    $result[] = '特码A_尾小';
                    $result[] = '特码B_尾小';
                }

                if($tm==49) {
                    $hds = '和';
                    $hdx = '和';
                    $result[] = '特码A_和';
                    $result[] = '特码B_和';
                }elseif($tm>=10){
                    if( (substr($tm,0,1)+substr($tm,-1,1))%2==0 ){
                        $hds = '合双';
                        $result[] = '特码A_合双';
                        $result[] = '特码B_合双';
                    }else{
                        $hds = '合单';
                        $result[] = '特码A_合单';
                        $result[] = '特码B_合单';
                    }

                    if( (substr($tm,0,1)+substr($tm,-1,1))>6){
                        $hdx = '合大';
                        $result[] = '特码A_合大';
                        $result[] = '特码B_合大';
                    }else{
                        $hdx = '合小';
                        $result[] = '特码A_合小';
                        $result[] = '特码B_合小';
                    }
                }else{
                    if($tm%2==0){
                        $hds = '合双';
                        $result[] = '特码A_合双';
                        $result[] = '特码B_合双';
                    }else{
                        $hds = '合单';
                        $result[] = '特码A_合单';
                        $result[] = '特码B_合单';
                    }

                    if($tm>6){
                        $hdx = '合大';
                        $result[] = '特码A_合大';
                        $result[] = '特码B_合大';
                    }else{
                        $hdx = '合小';
                        $result[] = '特码A_合小';
                        $result[] = '特码B_合小';
                    }
                }

                if(in_array($tm,$red)){
                    $result[] = '特码A_红波';
                    $result[] = '特码B_红波';
                }else if(in_array($tm, $blue)){
                    $result[] = '特码A_蓝波';
                    $result[] = '特码B_蓝波';
                }else{
                    $result[] = '特码A_绿波';
                    $result[] = '特码B_绿波';
                }


                //半波
                $bo=str_replace(array('波','特码A_','特码B_'),'',end($result));

                //半波_红合单
                $result[] = '半波_'.$bo.$ds;
                $result[] = '半波_'.$bo.$dx;
                $result[] = '半波_'.$bo.$hds;

                //分段
                if($tm>=1 && $tm<=10){
                    $result[] = '特码A_区段A';
                    $result[] = '特码B_区段A';
                }elseif($tm>=11 && $tm<=20){
                    $result[] = '特码A_区段B';
                    $result[] = '特码B_区段B';
                }elseif($tm>=21 && $tm<=30){
                    $result[] = '特码A_区段C';
                    $result[] = '特码B_区段C';
                }elseif($tm>=31 && $tm<=40){
                    $result[] = '特码A_区段D';
                    $result[] = '特码B_区段D';
                }elseif($tm>=41 && $tm<=49){
                    $result[] = '特码A_区段E';
                    $result[] = '特码B_区段E';
                }

                //正码

                //正码1-6
                for ($i=0; $i < count($zm); $i++) {
                    $result[] = '正码A_'.$zm[$i];
                    $result[] = '正码B_'.$zm[$i];
                }

                if($sum%2==0){
                    //正码A_总和大
                    $ds2 = '总和双';
                    $result[] = '正码A_总和双';
                    $result[] = '正码B_总和双';
                }else{
                    $ds2 = '总和单';
                    $result[] = '正码A_总和单';
                    $result[] = '正码B_总和单';
                }

                if($sum>=175){
                    $dx2 = '总和大';
                    $result[] = '正码A_总和大';
                    $result[] = '正码B_总和大';
                }
                else{
                    $dx2 = '总和小';
                    $result[] = '正码A_总和小';
                    $result[] = '正码B_总和小';
                }
                if(substr($zmSum, -1,1)>=5){
                    $wdx2 = '总尾大';
                    $result[] = '正码A_总尾大';
                    $result[] = '正码B_总尾大';
                }
                else{
                    $wdx2 = '总尾小';
                    $result[] = '正码A_总尾小';
                    $result[] = '正码B_总尾小';
                }
                if($data[0]>$data[6]){
                    $lh = '龙';
                    $result[] = '正码A_龙';
                    $result[] = '正码B_龙';
                }else{
                    $lh = '虎';
                    $result[] = '正码A_虎';
                    $result[] = '正码B_虎';
                }

                //正特计算结果
                for ($i=0; $i < count($zm); $i++) {

                    $result[] = '正'.($i+1).'特_'.$zm[$i];

                    if($zm[$i]==49){
                        array_push($result,'正'.($i+1).'特_和');
                        $result[] = '正'.($i+1).'特_大';
                        $result[] = '正'.($i+1).'特_小';
                    }elseif($zm[$i]>=25){
                        $result[] = '正'.($i+1).'特_大';
                    }else{
                        $result[] = '正'.($i+1).'特_小';
                    }

                    if($zm[$i]==49){
                        array_push($result,'正'.($i+1).'特_和');
                        $result[] = '正'.($i+1).'特_双';
                        $result[] = '正'.($i+1).'特_单';
                    }elseif($zm[$i]%2==0){
                        $result[] = '正'.($i+1).'特_双';
                    }else{
                        $result[] = '正'.($i+1).'特_单';
                    }

                    if($zm[$i]==49){
                        array_push($result,'正'.($i+1).'特_和');
                        $result[] = '正'.($i+1).'特_尾大';
                        $result[] = '正'.($i+1).'特_尾小';
                    }elseif(substr($zm[$i], -1,1)>=5){
                        $result[] = '正'.($i+1).'特_尾大';
                    }else{
                        $result[] = '正'.($i+1).'特_尾小';
                    }


                    if($zm[$i]>9){
                        $a = substr($zm[$i], 0,1)+substr($zm[$i], -1,1);
                    }else{
                        $a = $zm[$i];
                    }

                    if($zm[$i]==49){
                        array_push($result,'正'.($i+1).'特_和');
                        $result[] = '正'.($i+1).'特_合大';
                        $result[] = '正'.($i+1).'特_合小';
                    }elseif( $a>=7 ){
                        $result[] = '正'.($i+1).'特_合大';
                    }else{
                        $result[] = '正'.($i+1).'特_合小';
                    }

                    if($zm[$i]==49){
                        array_push($result,'正'.($i+1).'特_和');
                        $result[] = '正'.($i+1).'特_合双';
                        $result[] = '正'.($i+1).'特_合单';
                    }elseif($a%2==0){
                        $result[] = '正'.($i+1).'特_合双';
                    }else{
                        $result[] = '正'.($i+1).'特_合单';
                    }

                    if(in_array($zm[$i],$red)){
                        $result[] = '正'.($i+1).'特_红波';
                    }
                    else if(in_array($zm[$i], $blue)){
                        $result[] = '正'.($i+1).'特_蓝波';
                    }
                    else{
                        $result[] = '正'.($i+1).'特_绿波';
                    }
                }

                //尾数
                for ($i=0; $i < count($zm); $i++) {
                    $wei[]=substr($zm[$i],-1,1);
                }
                $wei[]=substr($tm,-1,1); //特码
                $wei = array_unique($wei); //去重
                foreach ($wei as $wk=>$wv){
                    array_push($result,'尾数_'.$wv.'尾');
                }

                foreach ($sxArr as $sv){
                    array_push($result,'一肖_'.$sv);
                }

                foreach ($sxArr as $sv){
                    //二肖连中_兔
                    array_push($result,'二肖连中_'.$sv);
                    array_push($result,'三肖连中_'.$sv);
                    array_push($result,'四肖连中_'.$sv);
                }

                foreach (array_diff(array('猪', '狗', '鸡', '猴','羊', '马', '蛇', '龙','兔', '虎', '牛', '鼠'),$sxArr) as $_dv){
                    array_push($result,'二肖连不中_'.$_dv);
                    array_push($result,'三肖连不中_'.$_dv);
                    array_push($result,'四肖连不中_'.$_dv);
                }

                //连尾
                for ($i=0; $i < count($zm); $i++) {
                    $lwei[]=substr($zm[$i],-1,1);
                }

                $lwei[]=substr($tm,-1,1);
                $result_lwei = array_unique($lwei);//去重
                foreach ($result_lwei as $lv){
                    //二尾连中_0尾
                    array_push($result,'二尾连中_'.$lv.'尾');
                    array_push($result,'三尾连中_'.$lv.'尾');
                    array_push($result,'四尾连中_'.$lv.'尾');

                }

                foreach (array_diff(range(0,9),$result_lwei) as $_dv){
                    array_push($result,'二尾连不中_'.$_dv.'尾');
                    array_push($result,'三尾连不中_'.$_dv.'尾');
                    array_push($result,'四尾连不中_'.$_dv.'尾');
                }

                //不中
                foreach (array_diff(range(1,49),$data) as $_dv){
                    array_push($result,'五不中_'.$_dv);
                    array_push($result,'六不中_'.$_dv);
                    array_push($result,'七不中_'.$_dv);
                    array_push($result,'八不中_'.$_dv);
                    array_push($result,'九不中_'.$_dv);
                    array_push($result,'十不中_'.$_dv);
                }


                //1-6龙虎
                for($lii=1;$lii<7;$lii++){
                    for($ii=$lii+1;$ii<7;$ii++){
                        if($zm[$lii-1]>$zm[($ii-1)]){
                            array_push($result,$lii.'-'.$ii.'球_龙');
                        }else{
                            array_push($result,$lii.'-'.$ii.'球_虎');
                        }
                    }
                }

                //正码
                //正码1-6
                for ($i=0; $i < count($zm); $i++) {
                    //正码6_单
                    if($zm[$i]==49){
                        array_push($result,'正码'.($i+1).'_和');
                        array_push($result,'正码'.($i+1).'_大');
                        array_push($result,'正码'.($i+1).'_小');
                    }elseif($zm[$i]>=25){
                        array_push($result,'正码'.($i+1).'_大');
                    }else{
                        array_push($result,'正码'.($i+1).'_小');
                    }

                    if($zm[$i]==49){
                        array_push($result,'正码'.($i+1).'_和');
                        array_push($result,'正码'.($i+1).'_双');
                        array_push($result,'正码'.($i+1).'_单');
                    }elseif($zm[$i]%2==0){
                        array_push($result,'正码'.($i+1).'_双');
                    }else{
                        array_push($result,'正码'.($i+1).'_单');
                    }

                    if($zm[$i]==49){
                        array_push($result,'正码'.($i+1).'_和');
                        array_push($result,'正码'.($i+1).'_尾大');
                        array_push($result,'正码'.($i+1).'_尾小');
                    }elseif(substr($zm[$i], -1,1)>=5){
                        array_push($result,'正码'.($i+1).'_尾大');
                    }else{
                        array_push($result,'正码'.($i+1).'_尾小');
                    }

                    if($zm[$i]>9){
                        $a = substr($zm[$i], 0,1)+substr($zm[$i], -1,1);
                    }else{
                        $a = $zm[$i];
                    }

                    if($zm[$i]==49){
                        array_push($result,'正码'.($i+1).'_和');
                        array_push($result,'正码'.($i+1).'_合大');
                        array_push($result,'正码'.($i+1).'_合小');
                    }elseif( $a>=7 ){
                        array_push($result,'正码'.($i+1).'_合大');
                    }else{
                        array_push($result,'正码'.($i+1).'_合小');
                    }

                    if($zm[$i]==49){
                        array_push($result,'正码'.($i+1).'_和');
                        array_push($result,'正码'.($i+1).'_合双');
                        array_push($result,'正码'.($i+1).'_合单');
                    }elseif($a%2==0){
                        array_push($result,'正码'.($i+1).'_合双');
                    }else{
                        array_push($result,'正码'.($i+1).'_合单');
                    }

                    if(in_array($zm[$i],$red)){
                        array_push($result,'正码'.($i+1).'_红波');
                    }else if(in_array($zm[$i], $blue)){
                        array_push($result,'正码'.($i+1).'_蓝波');
                    }else{
                        array_push($result,'正码'.($i+1).'_绿波');
                    }
                }

                foreach ($data as $v){
                    array_push($result,'特串_'.$v);
                }

                foreach ($data as $v){
                    array_push($result,'二中特_'.$v);
                }

                foreach ($data as $v){
                    array_push($result,'二中特之中特_'.$v);
                }

                foreach ($data as $v){
                    array_push($result,'二全中_'.$v);
                }

                foreach ($data as $v){
                    array_push($result,'三全中_'.$v);
                }

                foreach ($data as $v){
                    array_push($result,'三中二_'.$v);
                }

                foreach ($data as $v){
                    array_push($result,'三中二之中三_'.$v);
                }

                return array_unique($result);
            }
        }
    }


    /**
     *
     * 骰宝开奖结果
     *
     */
    public function kaijiang_result_sb($kj_haoma){
        if(!empty($kj_haoma)){
            $result = array();
            $data = explode(',', $kj_haoma);
            if(count($data)!=3){
                return false;
            }else{
                $sum = array_sum($data);
                $index = '';
                foreach ($data as $key => $value) {
                    switch ($key) {
                        case '0':
                            $index = '第一骰';
                            break;
                        case '1':
                            $index = '第二骰';
                            break;
                        case '2':
                            $index = '第三骰';
                            break;
                    }
                    $result[] = $index . '_'.$value;
                    if ($value >= 4) {
                        $result[] = $index . '_大';
                    } else {
                        $result[] = $index . '_小';
                    }
                    if ($value % 2 == 0) {
                        $result[] = $index . '_双';
                    } else {
                        $result[] = $index . '_单';
                    }
                    $result[] = '单骰_'.$value;
                }
                if(!in_array($sum,array(3,18))){
                    $result[] = '总和_'.$sum;
                    if ($sum >= 11) {
                        $result[] = '总和_大';
                    } else {
                        $result[] = '总和_小';
                    }
                    if ($sum % 2 == 0) {
                        $result[] = '总和_双';
                    } else {
                        $result[] = '总和_单';
                    }
                }else{
                    $result[] = '豹子_'.($sum==3?1:6);
                    $result[] = '';
                    $result[] = '';
                }
                $unData = array_unique($data);
                $cfData = $this->FetchRepeatMemberInArray($data);
                $cfData = array_values($cfData);
                $len = count($unData);
                if($len == 2) {
                    $result[] = '对子_'.$cfData[0];
                    //猜单骰
                    $sData = array_diff_assoc($unData,$cfData);
                    $sData = array_values($sData);
                }

                if($len == 1){
                    $result[] = '豹子_'.$unData[0];
                    $result[] = '对子_'.$cfData[0];
                    $result[] = '豹子_1-6';
                }
                //猜双骰
                if($len > 1){
                    sort($unData);
                    if($len == 2){
                        $result[] = '双骰_'.$unData[0].'-'.$unData[1];
                    }else{
                        $result[] = '双骰_'.$unData[0].'-'.$unData[1];
                        $result[] = '双骰_'.$unData[0].'-'.$unData[2];
                        $result[] = '双骰_'.$unData[1].'-'.$unData[2];
                    }
                }
                return $result;
            }
        }
    }


    //获取重复的元素
    function FetchRepeatMemberInArray($array) {
        // 获取去掉重复数据的数组
        $unique_arr = array_unique( $array );
        // 获取重复数据的数组
        $repeat_arr = array_diff_assoc ( $array, $unique_arr );
        return $repeat_arr;
    }


    /**
     *
     * 运算时时彩开奖结果
     *
     */
    public function kaijiang_result_ssc($kj_haoma){
        if(!empty($kj_haoma)){
            $data = explode(',', $kj_haoma);
            if(count($data)!=5){
                return false;
            }else{
                $sum = array_sum($data);
                $arr = [];
                $arr2 = array(1, 2, 3, 5, 7);
                $arr3 = array(0, 4, 6, 8, 9);
                $index = '';
                foreach ($data as $key => $value) {
                    switch ($key) {
                        case '0':
                            $index = '第一球';
                            break;
                        case '1':
                            $index = '第二球';
                            break;
                        case '2':
                            $index = '第三球';
                            break;
                        case '3':
                            $index = '第四球';
                            break;
                        case '4':
                            $index = '第五球';
                            break;
                    }
                    $result[] = $index . '_'.$value;
                    if ($value >= 5) {
                        $result[] = $index . '_大';
                    } else {
                        $result[] = $index . '_小';
                    }
                    if ($value % 2 == 0) {
                        $result[] = $index . '_双';
                    } else {
                        $result[] = $index . '_单';
                    }                   
                }
                if ($sum >= 23) {
                    $result[] = '总和'.$sum;
                    $result[] = '总和_大';
                } else {
                    $result[] = '总和'.$sum;
                    $result[] = '总和_小';
                }
                if ($sum % 2 == 0) {
                    $result[] = '总和_双';
                } else {
                    $result[] = '总和_单';
                }
                if ($data['0'] > $data['4']) {
                    $result[] = '龙';
                } else if ($data['0'] == $data['4']) {
                    $result[] = '和';
                } else {
                    $result[] = '虎';
                }
                return $result;
            }
        }
    }

    /*
     * 根据开奖号码算出所有开奖结果
     */
    public function kaijiang_result_new($kj_haoma){
        if(!empty($kj_haoma)){
            $haoma_arr=explode(',', $kj_haoma);
            if(count($haoma_arr)!=10){
                return false;
            }else{
                $kj_result=array();
                //冠亚大小单双
                if(($haoma_arr[0]+$haoma_arr[1])>11){
                    array_push($kj_result, '冠亚大');
                }else{
                    array_push($kj_result, '冠亚小');
                }
                if(($haoma_arr[0]+$haoma_arr[1])%2==0){
                    array_push($kj_result, '冠亚双');
                }else{
                    array_push($kj_result, '冠亚单');
                }

                foreach ($haoma_arr as $key => $value) {
                    $index='';
                    switch($key){
                        case '0':
                            $index = '冠军';
                            break;
                        case '1':
                            $index = '亚军';
                            break;
                        case '2':
                            $index = '第三名';
                            break;
                        case '3':
                            $index = '第四名';
                            break;
                        case '4':
                            $index = '第五名';
                            break;
                        case '5':
                            $index = '第六名';
                            break;
                        case '6':
                            $index = '第七名';
                            break;
                        case '7':
                            $index = '第八名';
                            break;
                        case '8':
                            $index = '第九名';
                            break;
                        case '9':
                            $index = '第十名';
                            break;
                    }
                    //单号的结果
                    array_push($kj_result, $index.'_'.$haoma_arr[$key]);
                    if($value>=6){
                        array_push($kj_result, $index.'_大');
                        if($value%2==0){
                            array_push($kj_result, $index.'_大双');
                        }else{
                            array_push($kj_result, $index.'_大单');
                        }
                    }
                    else{
                        array_push($kj_result, $index.'_小');
                        if($value%2==0){
                            array_push($kj_result, $index.'_小双');
                        }else{
                            array_push($kj_result, $index.'_小单');
                        }
                    }
                    if($value%2==0){
                        array_push($kj_result, $index.'_双');
                    }
                    else{
                        array_push($kj_result, $index.'_单');
                    }
                }
                for ($i=0; $i < 5; $i++) {
                    $index='';
                    switch($i){
                        case '0':
                            $index = '冠军';
                            break;
                        case '1':
                            $index = '亚军';
                            break;
                        case '2':
                            $index = '第三名';
                            break;
                        case '3':
                            $index = '第四名';
                            break;
                        case '4':
                            $index = '第五名';
                            break;
                    }
                    if($haoma_arr[$i] > $haoma_arr[9-$i]){
                        array_push($kj_result, $index.'_龙');

                    }elseif($haoma_arr[$i] < $haoma_arr[9-$i]){
                        array_push($kj_result, $index.'_虎');
                    }
                }

                if($haoma_arr[0]>$haoma_arr[1]){
                    array_push($kj_result, '庄');
                }else{
                    array_push($kj_result, '闲');
                }


                array_push($kj_result, '冠亚');
                array_push($kj_result, '冠亚_'.$haoma_arr[0].'_'.$haoma_arr[1]);
                array_push($kj_result, '冠亚_'.$haoma_arr[1].'_'.$haoma_arr[0]);

                //冠亚和_3
                //冠亚和_双
                array_push($kj_result, '冠亚和_'.($haoma_arr[0]+$haoma_arr[1]));
                $he  = $haoma_arr[0]+$haoma_arr[1];
                if($he%2==0){
                    array_push($kj_result,'冠亚和_双');
                }else{
                    array_push($kj_result,'冠亚和_单');
                }

                if($he>11){
                    array_push($kj_result,'冠亚和_大');
                }else{
                    array_push($kj_result,'冠亚和_小');
                }

                //冠亚和_C
                if($he >=3 && $he<=7){
                    array_push($kj_result,'冠亚和_A');
                }
                if($he >=8 && $he<=14){
                    array_push($kj_result,'冠亚和_B');
                }
                if($he >=15 && $he<=19){
                    array_push($kj_result,'冠亚和_C');
                }
                return $kj_result;
            }
        }
    }

    /*
     * 根据开奖号码算出所有开奖结果
     */
    public function kaijiang_result($kj_haoma){
        if(!empty($kj_haoma)){
            $haoma_arr=explode(',', $kj_haoma);
            if(count($haoma_arr)!=10){
                return false;
            }else{
                $kj_result=array();
                //冠亚大小单双
                if(($haoma_arr[0]+$haoma_arr[1])>11){
                    array_push($kj_result, '冠亚大');
                }else{
                    array_push($kj_result, '冠亚小');
                }
                if(($haoma_arr[0]+$haoma_arr[1])%2==0){
                    array_push($kj_result, '冠亚双');
                }else{
                    array_push($kj_result, '冠亚单');
                }
                //冠军大小单双 大双 小双 大单 小单
                if(($haoma_arr[0])>=6){
                    array_push($kj_result, '大');
                    $temp_dx='大';
                }else{
                    array_push($kj_result, '小');
                    $temp_dx='小';
                }
                if(($haoma_arr[0])%2==0){
                    array_push($kj_result, '双');
                    $temp_ds='双';
                }else{
                    array_push($kj_result, '单');
                    $temp_ds='单';
                }
                array_push($kj_result, $temp_dx.$temp_ds);
                //冠军龙虎
                if($haoma_arr[0]>$haoma_arr[9]){
                    array_push($kj_result, '龙');
                }else{
                    array_push($kj_result, '虎');
                }
                //冠军
                array_push($kj_result, $haoma_arr[0]);
                //冠亚和
                array_push($kj_result, '和'.($haoma_arr[0]+$haoma_arr[1]));
                return $kj_result;
            }
        }
    }

    /**
     * 获取最新一期的期号、开奖时间、封盘倒计时、开奖倒计时
     * $roomid  房间ID
     * $type 1为接口   2方法调用
     */
    public function get_bjpk10_lastest_model($roomid=0,$type=1){
        if(empty($roomid)){
            $roomid=$_REQUEST['roomid'];
        }
        if(empty($roomid)){
            $data['status']=0;
            $data['msg']='The room ID has not been transmitted, this room is no longer on playing';
            if($type==1){
                echo json_encode($data,JSON_UNESCAPED_UNICODE);
                return;
            }else{
                return $data;
            }
        }

        //如果不在售彩时间段
        $lottery_res=$this->db->getone('select config from un_lottery_type where id=2');
        $lottery_config=json_decode($lottery_res['config'],true);
        if(!(time()>strtotime($lottery_config['start_time']) && time()<strtotime($lottery_config['end_time']))){
            $data['status']=0;
            $data['msg']='Discontinued';
            $tip = $this->db->result("select tip from un_lottery_type WHERE id = $type");
            if($tip!="") $data['msg'] = $tip;
            if($type==1){
                echo json_encode($data,JSON_UNESCAPED_UNICODE);
                return;
            }else{
                return $data;
            }
        }

        //如果后台设置停止售彩
        $config_res=$this->db->getone('select value from un_config where nid="bjpk10_stop_or_sell"');
        $config_config=json_decode($config_res['value'],true);
        if($config_config['status']==2){
            $data['status']=0;
            $data['msg']=$config_config['title'];
            if($type==1){
                echo json_encode($data,JSON_UNESCAPED_UNICODE);
                return;
            }else{
                return $data;
            }
        }

        $json=@file_get_contents('bjpk10_qihao.json');
        $temp=json_decode($json,true);
        $final=json_decode($temp['txt'],true)['list'];
        $data=array();
        //根据平台返回的开奖期号表得到当前时间的期号信息

        foreach ($final as $k=>$v){
            //如果当前时间小于, 则取这一期, 并终止循环
            if(time()<$v['date']){
                //如果传了房间ID, 则返回封盘时间
                $room_closetime=$this->db->getone('select closure_time from un_room where id='.$roomid);
                if($room_closetime){
                    $data['status']=1;
                    $data['qihao']=$v['issue'];
                    $data['kaijiangshijian']=$v['date'];
                    $data['kaijiangshijian_geshi']=date('Y-m-d H:i:s',$v['date']);
                    $data['kj_daojishi']=$v['date']-time();
                    $data['fp_daojishi']=$data['kj_daojishi']-$room_closetime['closure_time'];
                    if($data['fp_daojishi']<0){
                        $data['fp_daojishi']=0;
                    }
                    $data['fengpanshijian']=$room_closetime['closure_time'];
                }else{
                    $data['status']=0;
                    $data['msg']='No room information is obtained, this room is no longer on playing';
                }
                break;
            }
        }
        if($type==1){
            echo json_encode($data,JSON_UNESCAPED_UNICODE);
            return;
        }else{
            return $data;
        }
    }

    /**
     * 获取用户荣誉信息
     * author: alan
     *
     * @param $userId   用户ID
     * @param int $type 返回类型 1：json 0：array
     *
     * @return bool|string
     */
    public function get_honor_level($userId) {
        $status_temp=$this->db->getone("select value from un_config where nid='is_show_honor'");
        $status = $status_temp['value'];
        $score_temp=$this->db->getone("select honor_score-lose_score from un_user where id=$userId");
        $score = $score_temp['honor_score-lose_score'];
        $score = $score < 0 ? 0 : $score;
        $honor=$this->db->getone("select name,icon,status,score,num from un_honor where score<=$score order by score desc");
        $honor['status1'] = $status;
        return $honor;
    }

    /**
     * 用户投注累计、输、盈、连赢加分
     * author: alan
     *
     * @param $userId
     * @param $type $userId,$betMoney,$winMoney,$wins
     */
    public function set_honor_score($userId,$betMoney,$winMoney,$wins) {
        $conf_temp=$this->db->getone("select value from un_config where nid='honor_upgrade'");
        $conf = json_decode($conf_temp['value'], true); // 加分条件

        // 累计投注
        $score = 0;
        if ($conf['betData']['status'] == 1 && $betMoney) {
            foreach ($conf['betData']['data'] as $k => $v) {
                if (intval($betMoney) >= $v['then']) {
                    $score = $v['end'];
                }
            }
        }
        // 当天累计中奖
        if ($conf['winData']['status'] == 1 && $winMoney) {
            foreach ($conf['winData']['data'] as $k => $v) {
                if (intval($winMoney) >= $v['then']) {
                    $score =  $v['end'];
                }
            }

        }

        // 累计连赢
        if ($conf['winsData']['status'] == 1 && $wins) {
            foreach ($conf['winsData']['data'] as $k => $v) {
                if (intval($wins) >= $v['then']) {
                    $score = $v['end'];
                }
            }
        }
        if(!empty($score)){
            $this->db->exec("update un_user set honor_score='{$score}' where id=$userId");
        }
    }

    /**
     * 算出该期号码的开奖结果
     * @param array $data 开奖号码
     * @param int $lotteryType 开奖采种
     * @return  mixed|bool|array
     */
    public function getLotteryResults($lotteryType,$data)
    {
        switch ($lotteryType){
            case 1:
                return $this->kaijiang_result_28($data,$lotteryType);
                break;
            case 2:
                return $this->kaijiang_result_new($data['kaijianghaoma']);
                break;
            case 3:
                return $this->kaijiang_result_28($data,$lotteryType);
                break;
            case 4:
                return $this->kaijiang_result_new($data['kaijianghaoma']);
                break;
            case 5:
                return $this->kaijiang_result_ssc($data['lottery_result']);
                break;
            case 6:
                return $this->kaijiang_result_ssc($data['lottery_result']);
                break;
            case 7:
                return $this->kaijiang_result_lhc($data['lottery_result']);
                break;
            case 8:
                return $this->kaijiang_result_lhc($data['lottery_result']);
                break;
            case 9:
                return $this->kaijiang_result_new($data['kaijianghaoma']);
                break;
            case 10:
                return $this->kaijiang_result_nn($data['lottery_result']); //牛牛
                break;
            case 11:
                return $this->kaijiang_result_ssc($data['lottery_result']);
                break;
            case 13:
                return $this->kaijiang_result_sb($data['lottery_result']);
                break;
            case 14:
                return $this->kaijiang_result_new($data['lottery_result']);
                break;
        }
    }

    function  kaijiang_result_nn($kj_haoma)
    {
        if (!empty($kj_haoma)) {
            $arr = explode(',',$kj_haoma);
            if (count($arr) != 10) {
                return false;
            } else {
                $re  = getShengNiuNiu($kj_haoma,2);
                $data[] = $re['sheng'];
                $data[] = $re['data']['lottery_niu'];
                $data[] = $re['data']['lottery_lh'];
                $data[] = $re['data']['lottery_dx'];
                $data[] = $re['data']['lottery_ds'];
                $data[] = $re['data']['lottery_dxds'];
//                $data[] = $re['data']['lottery_sum'];
                $data[] = $re['data']['lottery_gp']==1?'有公牌':'无公牌';
                $map =  array(
                    '第一张',
                    '第二张',
                    '第三张',
                    '第四张',
                    '第五张',
                );
                foreach ($map as $k=>$v){
                    $data[] = $map[$k].'_'.$re['data']['pai_str'][$k];
                    $data[] = $map[$k].'_'.$re['data']['hua_str'][$k];
                    $data[] = $map[$k].'_'.$re['data']['pai_dx'][$k];
                    $data[] = $map[$k].'_'.$re['data']['pai_ds'][$k];
                    $data[] = $map[$k].'_'.$re['data']['pai_dxds'][$k];
                }
                return $data;
            }
        }
    }
    /**
     * @param $a
     * @param $b
     * @return float|int
     *
     */
    function zushu($a, $b) {
        $topNum = 1;
        for($i=$a;$i>$a-$b;$i--){
            $topNum = $topNum*$i;
        }
        $botNum = 1;
        for($j = 1; $j <= $b; $j++){
            $botNum = $botNum*$j;
        }
        $dataSum =  $topNum / $botNum;
        return $dataSum;
    }

    /**
     * @param $lottery_numbers 开奖号码
     * @param $lotteryResults 开奖结果
     *
     */

    function awardLhc($lottery_numbers,$lotteryResults,$way,$odds,$v){

        $numArr = explode(',',$lottery_numbers);
        $tm = $numArr[6];
        $zm = array_slice($numArr, 0,6);

        $zArr = array(
            '正1特',
            '正2特',
            '正3特',
            '正4特',
            '正5特',
            '正6特'
        );

        $zmArr = array(
            '正码1',
            '正码2',
            '正码3',
            '正码4',
            '正码5',
            '正码6'
        );

        foreach ($zm as $zk=>$zv) { //正码出现49时
            $wArr = explode('_', $way);
            if ($zv == 49 && ($wArr[0] == $zArr[$zk] || $wArr[0] == $zmArr[$zk]) && in_array($wArr[1], array('大', '小', '单', '双', '合单', '合双', '合大', '合小', '尾大', '尾小'))) {
                return array('type' => 3, 'odds' => 1);
            }
        }

        if(in_array($way,$lotteryResults)){
            return array('type'=>1); //简单订单
        }
        if($tm==49){
            if((strpos($way,'半波_') !== false) || in_array(str_replace(array('特码A_','特码B_'),'',$way),array('大','小','单','双','合单','合双','合大','合小','尾大','尾小'))){
                return array('type'=>3,'odds'=>1);
            }
        }

        if(strpos($way,'特串') !== false){
            $wayArr = explode(',',str_replace('特串_','',$way));
            $result=array_intersect($numArr,$wayArr);
            $odArr=array();
            if(count($result)>1 && in_array($tm,$result)){ //含有特码
                foreach ($result as $vv){
                    $odArr['特串_'.$vv] = $odds[$v['room_no']]['特串_'.$vv]; //收集赔率
                }
                $zu = (count($result)-1);
                return array('type'=>2,'zu'=>$zu,'odds'=>bcmul($zu,min($odArr),2)); //含多注订单, 要返回注数的相乘值
            }
        }

        //二中特
        if(strpos($way,'二中特') !== false){
//            $numArr = explode(',',$lottery_numbers);
//            $tm = $numArr[6];
            $wayArr = explode(',',str_replace('二中特_','',$way));
            $result=array_intersect($numArr,$wayArr);
            $odArr=array();
            $tmpOdds = 0;
            $len = count($result);
            if($len>1){
                foreach ($result as $vv){
                    $odArr['二中特_'.$vv] = $odds[$v['room_no']]['二中特_'.$vv]; //收集赔率
                    if($vv == $tm){
                        $odArr['二中特之中特_'.$vv] = $odds[$v['room_no']]['二中特之中特_'.$vv]; //收集赔率
                    }
                }
                if(in_array($tm,$result)){
                    //中二
                    $zu = $this->zushu($len-1,2);
                    $zu1 = $zu;
                    $tmpOdds = bcadd($tmpOdds,bcmul($zu,min($odArr),2),2);
//                    dump($tmpOdds);

                    //中特
                    $zu = ($len-1);
                    $zu1 += $zu;
                    $tmpOdds = bcadd($tmpOdds,bcmul($zu,$odArr['二中特之中特_'.$tm],2),2);
//                    dump($tmpOdds);
                }else{
                    //中二
                    $zu = $this->zushu($len,2);
                    $zu1 = $zu;
                    $tmpOdds = bcadd($tmpOdds,bcmul($zu,min($odArr),2),2);
                }
//                dump($tmpOdds);
                return array('type'=>2,'zu'=>$zu1,'odds'=>$tmpOdds); //含多注订单, 要返回注数的相乘值
            }
        }

        //$zm = array_slice($data, 0,6)
        //二全中_46
        if(strpos($way,'二全中') !== false){
//            $numArr = explode(',',$lottery_numbers);
//            $zm = array_slice($numArr, 0,6);
            $wayArr = explode(',',str_replace('二全中_','',$way));
            $result=array_intersect($zm,$wayArr);
            $odArr=array();
            $tmpOdds = 0;
            $len = count($result);
            if($len>1){
                foreach ($result as $vv){
                    $odArr['二全中_'.$vv] = $odds[$v['room_no']]['二全中_'.$vv]; //收集赔率
                }
//                dump($odArr);
//                dump($result);
                $zu = $this->zushu($len,2);
                $tmpOdds = bcadd($tmpOdds,bcmul($zu,min($odArr),2),2);
//                dump($tmpOdds);
                return array('type'=>2,'zu'=>$zu,'odds'=>$tmpOdds); //含多注订单, 要返回注数的相乘值
            }
        }

        //三全中_46
        if(strpos($way,'三全中') !== false){
//            $numArr = explode(',',$lottery_numbers);
//            $zm = array_slice($numArr, 0,6);
            $wayArr = explode(',',str_replace('三全中_','',$way));
            $result=array_intersect($zm,$wayArr);
            $odArr=array();
            $tmpOdds = 0;
            $len = count($result);
            if($len>2){
                foreach ($result as $vv){
                    $odArr['三全中_'.$vv] = $odds[$v['room_no']]['三全中_'.$vv]; //收集赔率
                }
//                dump($odArr);
//                dump($result);
                $zu = $this->zushu($len,3);
                $tmpOdds = bcadd($tmpOdds,bcmul($zu,min($odArr),2),2);
//                dump($tmpOdds);
                return array('type'=>2,'zu'=>$zu,'odds'=>$tmpOdds); //含多注订单, 要返回注数的相乘值
            }
        }

        //三中二
        if(strpos($way,'三中二') !== false){
//            $numArr = explode(',',$lottery_numbers);
//            $zm = array_slice($numArr, 0,6);
            $wayArr = explode(',',str_replace('三中二_','',$way));
            $result=array_intersect($zm,$wayArr);
            $odArr=array();
            $odArr3=array();
            $tmpOdds = 0;
            if(count($result)>1){
                foreach ($result as $vv){
                    $odArr['三中二_'.$vv] = $odds[$v['room_no']]['三中二_'.$vv]; //收集赔率
                    if(count($result)>2){
                        $odArr3['三中二之中三_'.$vv] = $odds[$v['room_no']]['三中二之中三_'.$vv]; //收集赔率
                    }
                }
//                dump($odArr);
//                dump($odArr3);
//                dump($result);
                $len = count($result); //中的个数
                if($len>2){ //中三个号的
                    //中二
                    $zu = ($this->zushu($len,2))*($this->zushu((count($wayArr)-$len),1));
                    $zu1 = $zu;
                    $tmpOdds = bcadd($tmpOdds,bcmul($zu,min($odArr),2),2);
//                    dump($tmpOdds);

                    //中三
                    $zu = $this->zushu($len,3);
                    $zu1 += $zu;
                    $tmpOdds = bcadd($tmpOdds,bcmul($zu,min($odArr3),2),2);
//                    dump($tmpOdds);
                }else{
                    //中二
                    $zu = ($this->zushu($len,2))*($this->zushu((count($wayArr)-$len),1));
                    $zu1 = $zu;
                    $tmpOdds = bcadd($tmpOdds,bcmul($zu,min($odArr),2),2);
                }
//                dump($tmpOdds);
                return array('type'=>2,'zu'=>$zu1,'odds'=>$tmpOdds); //含多注订单, 要返回注数的相乘值
            }
        }

        $dbWayArr = explode('_',$way); //数据库取出来的way字段


        //连尾统一处理
        $preArr=array(
            '二尾连中'=>1,
            '三尾连中'=>2,
            '四尾连中'=>3,
            '二尾连不中'=>1,
            '三尾连不中'=>2,
            '四尾连不中'=>3,
        );
        if(in_array($dbWayArr[0],array_keys($preArr))){
            $wayArr = explode(',',$dbWayArr[1]);
            $lwei=array();
            $result=array();
            foreach ($numArr as $_nv){
                $lwei[]=substr($_nv,-1,1);
            }
            if(in_array($dbWayArr[0],array('二尾连中','三尾连中','四尾连中'))){
                $result=array_intersect($lwei,$wayArr); //交集
            }else{
                $result=array_diff($wayArr,$lwei); //差集
            }

            $result = array_unique($result); //去重

            $odArr=array();
            $len = count($result);
            foreach ($preArr as $pk=>$pv){
                if($dbWayArr[0]==$pk && $len>$pv){
                    foreach ($result as $vv){
                        $odArr[$pk.'_'.$vv.'尾'] = $odds[$v['room_no']][$pk.'_'.$vv.'尾']; //收集赔率
                    }
                    $zu = $this->zushu($len,($pv+1));
                    return array('type'=>2,'zu'=>$zu,'odds'=>bcmul($zu,min($odArr),2));
                }
            }
        }

        //连肖统一处理
        $preArr=array(
            '二肖连中'=>1,
            '三肖连中'=>2,
            '四肖连中'=>3,
            '二肖连不中'=>1,
            '三肖连不中'=>2,
            '四肖连不中'=>3,
        );
        if(in_array($dbWayArr[0],array_keys($preArr))){
            $wayArr = explode(',',$dbWayArr[1]);
            $lxiao=array();
            $result=array();
            foreach ($numArr as $_nv){
                $lxiao[]=getLhcShengxiao($_nv);
            }
            if(in_array($dbWayArr[0],array('二肖连中','三肖连中','四肖连中'))){
                $result=array_intersect($lxiao,$wayArr); //交集
            }else{
                $result=array_diff($wayArr,$lxiao); //差集
            }

            $result = array_unique($result); //去重

            $odArr=array();
            $len = count($result);
            foreach ($preArr as $pk=>$pv){
                if($dbWayArr[0]==$pk && $len>$pv){
                    foreach ($result as $vv){
                        $odArr[$pk.'_'.$vv] = $odds[$v['room_no']][$pk.'_'.$vv]; //收集赔率
                    }
                    $zu = $this->zushu($len,($pv+1));
                    return array('type'=>2,'zu'=>$zu,'odds'=>bcmul($zu,min($odArr),2));
                }
            }
        }


        //不中
        $preArr=array(
            '五不中'=>4,
            '六不中'=>5,
            '七不中'=>6,
            '八不中'=>7,
            '九不中'=>8,
            '十不中'=>9,
        );
//        $dbWayArr = explode('_',$way); //数据库取出来的way字段
        if(in_array($dbWayArr[0],array_keys($preArr))){
            $wayArr = explode(',',$dbWayArr[1]);
            $bz=array();
            $result=array();
            foreach (array_diff(range(1,49),$numArr) as $_dv){
                $bz[] = $_dv;
            }
            $result=array_intersect($bz,$wayArr); //交集
            $odArr=array();
            $len = count($result);
            foreach ($preArr as $pk=>$pv){
                if($dbWayArr[0]==$pk && $len>$pv){
                    foreach ($result as $vv){
                        $odArr[$pk.'_'.$vv] = $odds[$v['room_no']][$pk.'_'.$vv]; //收集赔率
                    }
                    $zu = $this->zushu($len,($pv+1));
                    return array('type'=>2,'zu'=>$zu,'odds'=>bcmul($zu,min($odArr),2));
                }
            }
        }

        return false;
    }


    public function mixVal($order_no = '', $money = '', $addtime = '', $way = '')
    {

        $md5_val = md5(($addtime - $money) . substr($order_no, 10) . $way);

        return $md5_val;
    }



    /**
     * 计算比分输赢结果
     * @param string $current_score 下注时的比分
     * @param string $whole_score 全场比分
     * @param string $rang_pk 让球盘口
     * @param string $buy_type 让球方, 传大写的A或B
     * 2018-05-08
     * 返回数据如下：
     * $rt_data =>
     *  array (
     *    'which_win' => 'B',
     *    'win_score' => 0.5,
     *  ),
     */
    public function calculScoreNew($current_score = '0:0', $whole_score = '0:0', $rang_pk = '-0.5/1', $buy_type = '',$odds='0')
    {

        $rt_data = array();
        //当时投注的比分
        $current_arr = explode(':', $current_score);

        //全场比分
        $whole_arr = explode(':', $whole_score);

        $final_score_left = 0;
        $final_score_right = 0;

        $final_score_left = $whole_arr[0] - $current_arr[0];
        $final_score_right = $whole_arr[1] - $current_arr[1];


        //让球盘口有可能是单个数字, 不含斜杠
        $rang_arr = explode('/', $rang_pk);
        if (count($rang_arr) == 1) {
            $rang_arr[0] = $rang_arr[0] - 0;
            if ($buy_type == 'A') {
                $final_score_left = $final_score_left + $rang_arr[0];
            } else {
                $final_score_right = $final_score_right + $rang_arr[0];
            }

            if ($final_score_left > $final_score_right) {
                $rt_data = [
                    'A'=>$odds+1,
                ];
            } elseif ($final_score_left == $final_score_right) {
                $rt_data = [
                    'A'=>1,
                    'B'=>1,
                ];
            } else {
                $rt_data = [
                    'B'=>$odds+1,
                ];
            }
            return $rt_data;
        }

        //多盘口情况
        $rang_arr[0] = $rang_arr[0] - 0;
        // if ($rang_arr[0] < 0) {
        if (substr($rang_pk, 0, 1) == '-') {
            $rang_arr[1] = 0 - $rang_arr[1];
        }


        if ($buy_type == 'A') {
            $final_score_left_1 = $final_score_left + $rang_arr[0];
            $final_score_left_2 = $final_score_left + $rang_arr[1];
            $final_score_right_1 = $final_score_right;
            $final_score_right_2 = $final_score_right;
        }
        else {
            $final_score_left_1 = $final_score_left;
            $final_score_left_2 = $final_score_left;
            $final_score_right_1 = $final_score_right + $rang_arr[0];
            $final_score_right_2 = $final_score_right + $rang_arr[1];
        }

        //买A队的情况
        if ($buy_type == 'A') {
            if ($final_score_left_1 > $final_score_right_1 && $final_score_left_2 > $final_score_right_2) {
                //quan-y
                $rt_data = [
                    'A'=>$odds+1,
                ];
            }
            elseif ($final_score_left_1 > $final_score_right_1 && $final_score_left_2 == $final_score_right_2) {
                //yin-yi-ban
                $rt_data = [
                    'A'=>$odds/2+1,
                    'B'=>0.5,
                ];
            }
            elseif ($final_score_left_1 == $final_score_right_1 && $final_score_left_2 > $final_score_right_2) {
                //yin-yi-ban
                $rt_data = [
                    'A'=>$odds/2+1,
                    'B'=>0.5,
                ];
            }
            elseif ($final_score_left_1 == $final_score_right_1 && $final_score_left_2 < $final_score_right_2) {
                //shu-yi-ban
                $rt_data = [
                    'A'=>0.5,
                    'B'=>$odds/2+1,
                ];
            }
            elseif ($final_score_left_1 < $final_score_right_1 && $final_score_left_2 < $final_score_right_2) {
                //quan-shu
                $rt_data = [
                    'B'=>$odds+1,
                ];
            }
            elseif ($final_score_left_1 < $final_score_right_1 && $final_score_left_2 == $final_score_right_2) {
                //shu-yi-ban
                $rt_data = [
                    'A'=>0.5,
                    'B'=>$odds/2+1,
                ];
            }
        }

        //买B队的情况
        else {
            if ($final_score_left_1 > $final_score_right_1 && $final_score_left_2 > $final_score_right_2) {
                //quan-shu
                $rt_data = [
                    'A'=>$odds+1,
                ];
            }
            elseif ($final_score_left_1 > $final_score_right_1 && $final_score_left_2 == $final_score_right_2) {
                //shu-yi-ban
                $rt_data = [
                    'A'=>$odds/2+1,
                    'B'=>0.5,
                ];
            }
            elseif ($final_score_left_1 == $final_score_right_1 && $final_score_left_2 > $final_score_right_2) {
                //shu-yi-ban
                $rt_data = [
                    'A'=>$odds/2+1,
                    'B'=>0.5,
                ];
            }
            elseif ($final_score_left_1 == $final_score_right_1 && $final_score_left_2 < $final_score_right_2) {
                //ying-yi-ban
                $rt_data = [
                    'A'=>0.5,
                    'B'=>$odds/2+1,
                ];
            }
            elseif ($final_score_left_1 < $final_score_right_1 && $final_score_left_2 < $final_score_right_2) {
                //quan-ying
                $rt_data = [
                    'B'=>$odds+1,
                ];
            }
            elseif ($final_score_left_1 < $final_score_right_1 && $final_score_left_2 == $final_score_right_2) {
                $rt_data = [
                    'A'=>0.5,
                    'B'=>$odds/2+1,
                ];
            }
        }

        return $rt_data;
    }

    /**
     * 计算比分结果
     * @param string $current_score 下注时的比分
     * @param string $whole_score 全场比分
     * @param string $rang_pk 让球盘口
     * @param string $odds 赔率
     * @param string $type 场子类型
     * 2018-05-08
     *
     */
    function calculScore($current_score = '0:0', $whole_score = '0:0', $rang_pk = '0.5/1',$odds,$type,$way='',$isAddOne=0)
    {
        $rt_data = array();

        //当时投注的比分
        $current_arr = explode(':', $current_score);

        //开奖比分
        $whole_arr = explode(':', $whole_score);

        //根据盘口判断是否为主队让球, 主队让球为“A”, 客队让球为“B”
        $first_key = substr($rang_pk, 0, 1);
        if ($first_key == '-') {
            $rang_flag = 'B';
        }else{
            $rang_flag = 'A';
        }

        //让球盘口有可能是单个数字, 不含斜杠
        $rang_arr = explode('/', $rang_pk);
        $rang_arr[0] = abs($rang_arr[0]);
        if (count($rang_arr) == 1) {
            $rang_arr[1] = $rang_arr[0];
        }

        //让球前的主队得分
        $before_rang_a_score = $whole_arr[0] - $current_arr[0];

        //让球前的客队得分
        $before_rang_b_score = $whole_arr[1] - $current_arr[1];

        //总进分 判断大小单双
        $total = $before_rang_a_score+$before_rang_b_score;

        if($way!=''){ //新玩法

            //全场逻辑
            if($type==2){
                if($total<3){
                    $re_data["半场入球_{$total}"] = $odds;
                }else{
                    $re_data["半场入球_3或以上"] = $odds;
                }

                if($before_rang_a_score>$before_rang_b_score){
                    $re_data["半场_A胜"] = $odds;
                }elseif ($before_rang_a_score==$before_rang_b_score){
                    $re_data["半场_平局"] = $odds;
                }else{
                    $re_data["半场_B胜"] = $odds;
                }
            }

//            dump(array('$way'=>$way,'$type'=>$type,'$total'=>$total,'$before_rang_a_score'=>$before_rang_a_score,'$before_rang_b_score'=>$before_rang_b_score,'$re_data'=>$re_data));

            //全场逻辑
            if($type==4){
                if($total<=1) {
                    $re_data["全场入球_0~1"] = $odds;
                }elseif($total>=2 && $total<=3){
                    $re_data["全场入球_2~3"] = $odds;
                }elseif($total>=4 && $total<=6){
                    $re_data["全场入球_4~6"] = $odds;
                }else{
                    $re_data["全场入球_7或以上"] = $odds;
                }

                if($before_rang_a_score>$before_rang_b_score){
                    $re_data["全场_A胜"] = $odds;
                }elseif ($before_rang_a_score==$before_rang_b_score){
                    $re_data["全场_平局"] = $odds;
                }else{
                    $re_data["全场_B胜"] = $odds;
                }

                //主主 主和
                if($current_arr[0]>$current_arr[1] && $whole_arr[0]>$whole_arr[1]){
                    $re_data["半/全场_主主"] = $odds;
                }elseif($current_arr[0]>$current_arr[1] && $whole_arr[0]==$whole_arr[1]){
                    $re_data["半/全场_主和"] = $odds;
                }elseif($current_arr[0]>$current_arr[1] && $whole_arr[0]<$whole_arr[1]){
                    $re_data["半/全场_主客"] = $odds;
                }elseif($current_arr[0]==$current_arr[1] && $whole_arr[0]>$whole_arr[1]){
                    $re_data["半/全场_和主"] = $odds;
                }elseif($current_arr[0]==$current_arr[1] && $whole_arr[0]==$whole_arr[1]){
                    $re_data["半/全场_和和"] = $odds;
                }elseif($current_arr[0]==$current_arr[1] && $whole_arr[0]<$whole_arr[1]){
                    $re_data["半/全场_和客"] = $odds;
                }elseif($current_arr[0]<$current_arr[1] && $whole_arr[0]>$whole_arr[1]){
                    $re_data["半/全场_客主"] = $odds;
                }elseif($current_arr[0]<$current_arr[1] && $whole_arr[0]==$whole_arr[1]){
                    $re_data["半/全场_客和"] = $odds;
                }elseif($current_arr[0]<$current_arr[1] && $whole_arr[0]<$whole_arr[1]){
                    $re_data["半/全场_客客"] = $odds;
                }

                if($whole_arr[0]>4 || $whole_arr[1]>4){
                    $re_data["全场比分_其他"] = $odds;
                }else{
                    $re_data["全场比分_{$whole_arr[0]}-{$whole_arr[1]}"] = $odds;
                }
            }

//            dump($rt_data);
            return $re_data;
        }

        $pre_rang = abs($rang_arr[0]);
        if($pre_rang == $total){
            if($total==abs($rang_arr[1])){
                $rt_data['大'] = 1;
                $rt_data['小'] = 1;
            }else{
                $rt_data['大'] = 0.5;
                $rt_data['小'] =$odds/2+$isAddOne;
            }
        }else if($total > $pre_rang){
            if($total > abs($rang_arr[1])){
                $rt_data['大'] = $odds+$isAddOne;
            }else{
                $rt_data['大'] = $odds/2+$isAddOne;
                $rt_data['小'] = 0.5;
            }
        }else{
            $rt_data['小'] = $odds+$isAddOne;
        }

        //单双
        if($type==4){ //只有全场时才派单双
            if($total==0 || $total%2==0){ //双
                $rt_data['双'] = $odds+$isAddOne;
            }else{
                $rt_data['单'] = $odds+$isAddOne;
            }
        }

        return $rt_data;
    }


    public function audit($order_no,$way,$money,$odds=false)
    {
        //查记录
        $sql = "SELECT way,money,odds FROM un_orders_audit WHERE order_no='{$order_no}'";
        $res = $this->db->getone($sql);

        if(!empty($res)){
            $data = array();
            if($res['way'] != $way){
                $data['way'] = $res['way'];
            }

            if($res['money'] != $money){
                $data['money'] = $res['money'];
            }

            if($odds != false){
                if($res['odds'] != $odds){
                    $data['odds'] = $res['odds'];
                }
            }
            if(!empty($data)){
                //修改订单表
                $tmp = array();
                foreach($data as $cc=>$cvv){
                    $tmp[] = "{$cc}='{$cvv}'";
                }
                $set = implode(',',$tmp);

                $sql = "UPDATE un_orders SET {$set} WHERE order_no='{$order_no}'";
                $ch_re = $this->db->query($sql);
                return $data;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * 派奖
     * @param string $issue 开奖期号
     * @param array $data 开奖号码 及相关信息
     * @param string $openTime 开奖时间
     * @param int $lotteryType 开奖彩种
     * @param int $status 开奖状态 0自动, 1手动, 2未开;
     * @param int $uid 开奖人 0自动;
     * @param array $other 其它信息; 包含开奖次数 frequency
     */
    public function theLottery($issue, $data, $openTime, $lotteryType, $status, $uid,$other){

        $integralData = [];
        //防止刷单
        $redis = initCacheRedis();
        $co_str = $lotteryType.':'.$issue;
        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去
            $redis->expire($co_str,90); //设置它的超时
            deinitCacheRedis($redis);
        }else{
            deinitCacheRedis($redis);
            return false;
        }

        //如果未正确传参, 终止程序
        if(empty($issue) || empty($data) || empty($openTime) || empty($lotteryType)){
            return;
        }

        //针对六合彩写入缓存数据, 给投注验证用
        if($lotteryType == 7){
            $redis = initCacheRedis();
            $re = $redis->set('lhc_issue',$issue);
            deinitCacheRedis($redis);
        }

        //设置开奖次数
        if(!isset($other['frequency'])) $other['frequency'] = 1;

        //算出这期号码的开奖结果
        $lotteryResults = $this->getLotteryResults($lotteryType,$data);
        //牛牛 已开次数记录
        if($lotteryType==10){
            if($lotteryResults[1] == '牛牛'){ //是否有牛牛
                $redis = initCacheRedis();
                $redis->incr('opened_nn_num'); //自增
                $redis->expireAt('opened_nn_num',strtotime('23:59:59')); //设置失效时间
                deinitCacheRedis($redis);
            }
        }


        if(!$lotteryResults){
            return;
        }

        //开始派奖

        //订单审计配置
        $bt = microtime(1);
        $redis = initCacheRedis();
        $order_audit_set = $redis->hget('Config:order_audit','value');
        deinitCacheRedis($redis);
        if(empty($order_audit_set)){ //未开启
            //查询该期的所有投注
            $sql = "SELECT id, order_no, user_id, way, money,single_money,chase_number, reg_type, room_no,win_stop,addtime,whats_val FROM un_orders WHERE issue = {$issue} AND reg_type != 9 AND lottery_type = {$lotteryType} AND state = 0 AND award_state = 0";
        }else{
            //联表查, 提高效率
            $sql = "SELECT o.id, o.order_no, o.user_id, o.way, o.money,o.single_money,o.chase_number, o.reg_type, o.room_no,o.win_stop,o.addtime,o.whats_val,oa.`way` AS way_a,oa.`money` AS money_a FROM un_orders o,`un_orders_audit` oa WHERE o.issue = {$issue} AND o.`order_no`=oa.`order_no` AND o.reg_type != 9 AND o.lottery_type = {$lotteryType} AND o.state = 0 AND o.award_state = 0";
        }

        $lists = $this->db->getall($sql);
        $et = microtime(1);

//        //连接workerman
//        $Gateway = O('Gateway');
//        $Gateway::$registerAddress = C('Gateway');

        if(empty($lists)){
            //修改开奖状态
            $this->modifyLotteryStatus($lotteryType, $issue, $status, $uid, 0,$other['frequency']);

            //发送推送信息
            $this->sendOpenAward($issue, $lotteryType, $data);
            return false;
        }

        //获取赔率信息
        $odds = $this->getOdds($issue,$lotteryResults,$lotteryType);
        if(!$odds) return false;

        $award_uids = array();
        $updateAccountUids = array(); //待更新余额的用户
        //1314的赔率,给后面用的
        $toddsa_org = [];
        if (in_array($lotteryType, array(1,3))) {
            $toddsa = '';
            $redis = initCacheRedis();
            $tmpArr = $redis->hGet('Config:oddsRule', 'value');
            //关闭redis链接
            deinitCacheRedis($redis);
            $toddsa_org = json_decode($tmpArr, 1);
        }

        //循环判断订单是否中奖
        foreach ($lists as $k=>$v){

            if(!empty($order_audit_set)) { //开启
                //订单审计前
                $sj_data = array();
                if(!empty($v['way_a']) && $v['way'] != $v['way_a']){
                    $v['way'] = $v['way_a'];
                    $sj_data['way'] = $v['way_a'];
                }

                if(!empty($v['money_a']) && $v['money'] != $v['money_a']){
                    $v['money'] = $v['money_a'];
                    $sj_data['money'] = $v['money_a'];
                }

                if(!empty($sj_data)){
                    //修改订单表
                    $sjtmp = array();
                    foreach($sj_data as $cc=>$cvv){
                        $sjtmp[] = "{$cc}='{$cvv}'";
                    }
                    $set = implode(',',$sjtmp);

                    $sql = "UPDATE un_orders SET {$set} WHERE id='{$v['id']}'";
                    $ch_re = $this->db->query($sql);
                }
            }


            $currentOdds = null;
            $this->db->query('START TRANSACTION');
            try {
                //用户余额信息
                if(!empty(C('db_port'))) { //使用mycat时 查主库数据
                    $sql = "/*#mycat:db_type=master*/ select money from un_account where user_id='{$v['user_id']}' for update";
                }else{
                    $sql = "select money from un_account where user_id='{$v['user_id']}' for UPDATE";
                }
                $money = $this->db->result($sql);

                $whats_val = $this->mixVal($v['order_no'], $v['money'], $v['addtime'], $v['way']);

                $awardLhcData = array();
                if(in_array($lotteryType,array(7,8))){
                    $awardLhcData = $this->awardLhc($data['lottery_result'],$lotteryResults,$v['way'],$odds,$v); //判断六合彩是否中奖
                }

                if ($whats_val == $v['whats_val'] && (in_array($v['way'], $lotteryResults) || !empty($awardLhcData))){ //如果下注的单在开奖结果中, 说明是中奖了
                    if((strpos($v['way'],'冠亚') !== false) && in_array($lotteryType,array(2,4,9,14))){
                        //猜冠亚单独处理
                        $arrayNewWay = explode('_',$v['way']);
                        if(count($arrayNewWay)==3 && $arrayNewWay[0]=='冠亚'){
                            $_1 =(int)$arrayNewWay[1];
                            $_2 =(int)$arrayNewWay[2];
                            if($_1<11 && $_2<11 && $_1>0 && $_2>0){
                                $v['way'] = $arrayNewWay[0];
                            }
                        }
                    }

                    //此玩法在此房间的赔率
                    $currentOdds = $odds[$v['room_no']][$v['way']];

                    if($awardLhcData['type']>1){ //六合彩多注玩法 和 和局现像
                        $currentOdds = $awardLhcData['odds'];
                    }

                    //添加1314的28类限额--20170815
                    if (in_array($lotteryType, array(1, 3))) {

                        //阶梯赔率 顺 对 豹
                        if (in_array($v['way'], array('豹子', '对子', '正顺', '半顺', '倒顺', '乱顺'))) {

                            $toddsa = $toddsa_org[$v['room_no']];

                            foreach ($toddsa as $tk=>$tv){ //清除没设置的数据
                                if (empty($tv[0]['status'])){
                                    unset($toddsa[$tk][0]);
                                }
                                if (empty($tv[1]['status'])){
                                    unset($toddsa[$tk][1]);
                                }
                                if (empty($tv[2]['status'])){
                                    unset($toddsa[$tk][2]);
                                }
                                if (empty($tv[3]['status'])){
                                    unset($toddsa[$tk][3]);
                                }
                            }
                        }

                        //13、14号赔率
                        if ((in_array(13, $lotteryResults) || in_array(14, $lotteryResults)) && in_array($v['way'], array('大', '小', '单', '双', '大单', '小双', '小单', '大双'))) {

                            $toddsa = $toddsa_org[$v['room_no']];

                            foreach ($toddsa as $tk=>$tv){ //清除没设置的数据
                                if (empty($tv[0]['status'])){
                                    unset($toddsa[$tk][0]);
                                }
                                if (empty($tv[1]['status'])){
                                    unset($toddsa[$tk][1]);
                                }
                                if (empty($tv[2]['status'])){
                                    unset($toddsa[$tk][2]);
                                }
                                if (empty($tv[3]['status'])){
                                    unset($toddsa[$tk][3]);
                                }
                            }
                        }

                        $todds_type = 0;
                        //13、14号赔率
                        if ($toddsa){
                            if (in_array($v['way'],array('大','小','单','双'))){
                                $todds_type=1;
                                $ways = "'大','小','单','双'";
                            }elseif (in_array($v['way'],array('大单','小双'))){
                                $todds_type=2;
                                $ways = "'大单','小双'";
                            }elseif (in_array($v['way'],array('小单','大双'))){
                                $todds_type=3;
                                $ways = "'小单','大双'";
                            }elseif ($v['way']=='豹子'){
                                $todds_type=4;
                                $ways = "'豹子'";
                            }elseif ($v['way']=='正顺'){
                                $todds_type=5;
                                $ways = "'正顺'";
                            }elseif ($v['way']=='对子'){
                                $todds_type=6;
                                $ways = "'对子'";
                            }elseif ($v['way']=='倒顺'){
                                $todds_type=7;
                                $ways = "'倒顺'";
                            }elseif ($v['way']=='半顺'){
                                $todds_type=8;
                                $ways = "'半顺'";
                            }elseif ($v['way']=='乱顺'){
                                $todds_type=9;
                                $ways = "'乱顺'";
                            }


                            if ($todds_type > 0) {
                                $oddsb=-1;
                                $tjmoney = $this->db->getone("select sum(money) as total from un_orders WHERE  user_id='{$v['user_id']}' AND state=0 AND room_no='{$v['room_no']}' AND issue='{$issue}' AND way IN ({$ways})");
                                foreach ($toddsa_org[$v['room_no']] as $k1=>$v1){
                                    if($k1==$todds_type){
                                        array_multisort($v1); //二组数组排序
                                        foreach ($v1 as $k2=>$v2){
                                            if($v2['status'] == 1 && $v2['point'] != '' && $v2['ratio'] != '' && $v2['point'] >= 0){
//                                                if (bccomp($tjmoney['total'],$v2['point'],2) == 1 || bccomp($tjmoney['total'],$v2['point'],2) == 0){
                                                if (bccomp($tjmoney['total'],$v2['point'],2) == 1){
                                                    $oddsb = $v2['ratio'];
                                                }
                                            }
                                        }
                                    }
                                }

                                if ($oddsb>=0){ //这里不能用isset
                                    $currentOdds = $oddsb;
                                }
                                unset($oddsb);
                            }

                        }
                    }

                    if(!$currentOdds){
                        $currentOdds = 0;
                    }

                    $award = bcmul($v['money'], $currentOdds,2);


                    if($awardLhcData['type']==2){ //六合彩多注玩法
                        $award = bcmul($v['single_money'], $currentOdds,2);
                    }

                    $currentMoney = bcadd($money, $award, 2);

                    //给用户加钱
                    $sql = "update un_account set money=money+'{$award}',winning=winning+'{$award}' WHERE user_id='{$v['user_id']}'";
                    $res = $this->db->exec($sql);
                    if(!$res){
                        @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 游戏类型: '.$lotteryType.' 期号：'.$issue.' 用户ID: '.$v['user_id'].' 中奖金额: '.$award.' 余额更新失败: '.$sql.PHP_EOL,FILE_APPEND);
                        throw new Exception('Update failed!'.$sql);
                    }else{
                        @file_put_contents('openAward.log', date('Y-m-d H:i:s').PHP_EOL.'游戏类型: '.$lotteryType.' 期号：'.$issue.' 用户ID: '.$v['user_id'].' 中奖,订单id: '.$v['id'].' 投注内容: '.$v['way'].' 投注金额: '.$v['money'].' 中奖金额: '.$award.' sql: '.$sql.PHP_EOL,FILE_APPEND);
                    }

                    $award_uids[$v['user_id']][$v['room_no']] = bcadd($award_uids[$v['user_id']][$v['room_no']],$award,2); //收集中奖额

                    if($awardLhcData['type'] == 3){
                        $type = 301;
                        $remark = "和局, 订单号为：". $v['order_no'];
                        $award_state = 3;
                    }else{
                        $type = 12;
                        $remark = "用户中奖, 订单号为：" . $v['order_no'];
                        $award_state = 2;
                    }

                    //修改订单状态
                    $sql = "update un_orders set award_state='{$award_state}',award='{$award}' WHERE id='{$v['id']}'";
                    $res = $this->db->exec($sql);
                    if (empty($res)){
                        throw new Exception('Update failed!'.$sql);
                    }


                    //资金交易明细
                    $log_data=array(
                        'order_num' => $v['order_no'],
                        'user_id' => $v['user_id'],
                        'type' => $type,
                        'addtime' => time(),
                        'money' => $award,
                        'use_money' => $currentMoney,
                        'remark' => $remark,
                        'reg_type' => $v['reg_type']
                    );
                    $sql = $this->insert('un_account_log',$log_data);
                    $res = $this->db->exec($sql);
                    if (empty($res)){
                        throw new Exception('Update failed!'.$sql);
                    }


                    //追中即停
                    if(!empty($v['chase_number']) && $v['win_stop']==1){
                        $zhuiHaoInfo = $this->db->getall("select id,money,chase_number,user_id,order_no,issue from un_orders where state = 0 and award_state = 0 and chase_number = '".$v['chase_number']."' and issue > $issue");
                        if(!empty($zhuiHaoInfo)) {
                            foreach ($zhuiHaoInfo as $info) {
                                $kymoney = $this->db->result("select money from un_account where user_id = {$info['user_id']} for UPDATE");
                                $sql = "update un_orders set state=1 WHERE id=".$info['id']."";
                                $res = $this->db->exec($sql);
                                if (empty($res)) {
                                    @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 游戏类型: '.$lotteryType.' 期号：'.$issue.' 用户ID: '.$v['user_id'].' 追号, 修改订单状态失败: '.$sql.PHP_EOL,FILE_APPEND);
                                    throw new Exception('Update failed!'.$sql);
                                };

                                //更新资金表
                                $cdAccMoney = $kymoney+$info['money'];
                                $sql = "update un_account set money = {$cdAccMoney} WHERE user_id='{$info['user_id']}'";
                                $res = $this->db->exec($sql);
                                if (empty($res)) {
                                    @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 游戏类型: '.$lotteryType.' 期号：'.$issue.' 用户ID: '.$v['user_id'].'  追号, 返还资金 '.$info['money'].' 修改资金失败: '.$sql.PHP_EOL,FILE_APPEND);
                                    throw new Exception('Update failed!'.$sql);
                                }else{
                                    @file_put_contents('openAward.log', date('Y-m-d H:i:s').PHP_EOL.'游戏类型: '.$lotteryType.' 期号：'.$issue.' 用户ID: '.$v['user_id'].' 追号中奖撤单,订单id: '.$v['id'].' 投注内容: '.$info['way'].' 投注金额: '.$info['money'].' sql: '.$sql.PHP_EOL,FILE_APPEND);
                                }

                                //添加资金明细表
                                $order_num = "CD" . date("YmdHis") . rand(100, 999);
                                $cd_log=array(
                                    'order_num' => $order_num,
                                    'user_id' => $info['user_id'],
                                    'type' => 14,
                                    'addtime' => time(),
                                    'money' => $info['money'],
                                    'use_money' => $cdAccMoney,
                                    'remark' => "追号撤单 ".$info['issue']." 期的".$info['order_no']."投注",
                                    'reg_type' => $v['reg_type']);
                                $sql = $this->insert('un_account_log',$cd_log);
                                $res = $this->db->exec($sql);
                                if (empty($res)){
                                    @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 游戏类型: '.$lotteryType.' 期号：'.$issue.' 用户ID: '.$v['user_id'].' 追号, 资金明细更新失败: '.$sql.PHP_EOL,FILE_APPEND);
                                    throw new Exception('Update failed!'.$sql);
                                }
                            }
                        }
                    }

                    //收集中奖用户
                    if(!in_array($v['user_id'],$updateAccountUids)){
                        $updateAccountUids[]=$v['user_id'];
                    }
                    
                    //中奖加荣誉积分
                    $integralData[] = ['money' => $award, 'user_id' => $v['user_id'], 'type' => 3];
                }else{
                    //未中奖, 更新订单状态
                    if($whats_val != $v['whats_val']){
                        $sql = "update un_orders set award_state=1,ext_a='异常单' WHERE id='{$v['id']}'";
                    }else{
                        $sql = "update un_orders set award_state=1 WHERE id='{$v['id']}'";
                    }

                    $res = $this->db->exec($sql);
                    if (empty($res)){
                        throw new Exception('Update failed!'.$sql);
                    }

                    //更新用户输赢金额
                    $sql = "update un_account set winning=winning-'{$v['money']}' WHERE user_id='{$v['user_id']}'";
                    $res=$this->db->exec($sql);
                    if (empty($res)){
                        throw new Exception('Update failed!'.$sql);
                    }
                }
                
                $this->db->query('COMMIT');
                
                //投注加荣誉积分
                $integralData[] = ['money' => $v['money'], 'user_id' => $v['user_id'], 'type' => 2];

            } catch (Exception $e) {
                //设置捕获异常开奖
                $this->getOpenError($lotteryType,$issue,$other['frequency']);
                $this->db->query('ROLLBACK');
            }
        }

        if($lotteryType==1 && $issue==896013){
            $eetime = microtime(1);
        }

        //修改开奖状态
        $this->modifyLotteryStatus($lotteryType, $issue, $status, $uid, 1,$other['frequency']);
        //统一更新帐户余额
        foreach ($updateAccountUids as $uk=>$uv){
            if(!empty(C('db_port'))) { //使用mycat时 查主库数据
                $sql = "/*#mycat:db_type=master*/ SELECT money FROM `un_account` WHERE user_id=".$uv;
            }else{
                $sql = "SELECT money FROM `un_account` WHERE user_id=".$uv;
            }
            $umoney = $this->db->result($sql);
            $sdata=array( //调用双活接口
                    'type'=>'update_account_by_uid',
                    'id'=>$uv,
                    'json'=>json_encode(array('commandid'=>3010,'money'=> number_format($umoney, 2, '.', ''))),
                );
                send_home_data($sdata);
        }

        //发送中奖额
        foreach ($award_uids as $k=>$v){
            //这里要用双活功能
            $data['type']="double_user_award_info";
            $data['id']=$k;
            $data['issue']=$issue;
            $data['lottery_type']=$lotteryType;
            $data['data']=encode($v);
            send_home_data($data);
        }

        //发送推送信息
        if ($other['frequency'] >= 1) {
            $this->sendOpenAward($issue, $lotteryType, $data);
            //飘窗
            $data['type']="barrage";
            $data['issue']=$issue;
            $data['lottery_type']=$lotteryType;
            send_home_data($data);
        };

        //未全部正常派奖, 重复派奖3次,3次以后提示手动派奖
        $res = $this->getOpenError($lotteryType,$issue,"GET");
        if(!empty($res) && $res == $other['frequency']){
            $other['frequency'] += 1;
            if($other['frequency'] < 4){
                self::theLottery($issue, $data, $openTime, $lotteryType, $status, $uid,$other);
            }else{
                //提示手动派奖 修改开奖状态
                $this->modifyLotteryStatus($lotteryType, $issue, 2, 0, 1,4);
                //初始化redis
                $redis = initCacheRedis();
                $error = array(
                    'lotteryType'=>$lotteryType,
                    'issue'=>$lotteryType,
                );
                $redis->setex("openError:{$lotteryType}", 600,json_encode($error));
                //关闭redis链接
                deinitCacheRedis($redis);
            }
        }
       
        //中奖/投注荣誉积分兑换
        if (!empty($integralData)) {
            foreach ($integralData as $ik => $iv) {
                exchangeIntegral($iv['money'], $iv['user_id'], $iv['type']);
            }
        }
    }

    /**
     * 派奖
     * @param string $issue 开奖期号
     * @param array $data 开奖号码 及相关信息
     * @param int $lotteryType 开奖彩种
     */
    public function theLotteryWithoutPaicai($issue, $data, $lotteryType)
    {

        //如果未正确传参, 终止程序
        if(empty($issue) || empty($data) || empty($lotteryType)){
            return;
        }

        //兼容急速赛车表结构不一的问题
        if ($lotteryType == '9') {
            $data['kaijianghaoma'] = $data['lottery_result'];
        }

        //算出这期号码的开奖结果
        $lotteryResults = $this->getLotteryResults($lotteryType,$data);
       // dump($lotteryResults);


        if(count($lotteryResults)==0){
            return;
        }

        //开始派奖

        //查询该期的所有投注（按 reg_type 值, 除去游客-8 机器人-9 假人-11）
        $sql = "SELECT id, order_no, user_id, way, money,single_money, reg_type, room_no, addtime, whats_val FROM un_orders WHERE issue = {$issue} AND reg_type != 8 AND reg_type != 9 AND reg_type != 11 AND lottery_type = {$lotteryType} AND state = 0 AND award_state = 0";
        $lists = $this->db->getall($sql);
        if(empty($lists)){
            return false;
        }
        //获取赔率信息
        $odds = $this->getOdds($issue,$lotteryResults,$lotteryType);
        if(!$odds) return false;

        //所有投注金额
        $all_bet_money = [];
        //所有中奖金额
        $all_award = [];

        //循环判断订单是否中奖
        foreach ($lists as $k=>$v){
            $all_bet_money[] = $v['money'];
            $currentOdds = null;
            $this->db->query('START TRANSACTION');
            try {
                // //用户余额信息

                $whats_val = $this->mixVal($v['order_no'], $v['money'], $v['addtime'], $v['way']);

                $awardLhcData = array();
                if(in_array($lotteryType,array(8))){
                    $awardLhcData = $this->awardLhc($data['lottery_result'],$lotteryResults,$v['way'],$odds,$v); //判断六合彩是否中奖
                }
                if ($whats_val == $v['whats_val'] && (in_array($v['way'], $lotteryResults) || !empty($awardLhcData))){ //如果下注的单在开奖结果中, 说明是中奖了
                    if((strpos($v['way'],'冠亚') !== false) && in_array($lotteryType,array(9))){
                        //猜冠亚单独处理
                        $arrayNewWay = explode('_',$v['way']);
                        if(count($arrayNewWay)==3 && $arrayNewWay[0]=='冠亚'){
                            $_1 =(int)$arrayNewWay[1];
                            $_2 =(int)$arrayNewWay[2];
                            if($_1<11 && $_2<11 && $_1>0 && $_2>0){
                                $v['way'] = $arrayNewWay[0];
                            }
                        }
                    }

                    //此玩法在此房间的赔率
                    $currentOdds = $odds[$v['room_no']][$v['way']];
                    if($awardLhcData['type']>1){ //六合彩多注玩法 和 和局现像
                        $currentOdds = $awardLhcData['odds'];
                    }

                    if(!$currentOdds){
                        @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'WARNING 游戏类型: '.$lotteryType.' 期号：'.$issue.' 用户ID: '.$v['user_id'].' 中奖号码: '.$v['way'].' 开奖中奖号码: '.json_encode($lotteryResults,JSON_UNESCAPED_UNICODE).' 无法获取赔率信息: '.json_encode($odds,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);
                        $currentOdds = 0;
                    }

                    $award = bcmul($v['money'], $currentOdds,2);

                    if($awardLhcData['type']==2){ //六合彩多注玩法
                        $award = bcmul($v['single_money'], $currentOdds,2);
                    }
                    //累计中奖
                    $all_award[] = $award;
                }


                $result = $this->db->query('COMMIT');
                
            } catch (Exception $e) {
                //设置捕获异常开奖
                $this->getOpenError($lotteryType,$issue,'');
                $this->db->query('ROLLBACK');

            }
        }

        $redis = initCacheRedis();
        $json_data = $redis->hGet('Config:pre_open_setting','value');

        //关闭redis链接
        deinitCacheRedis($redis);

        $sha_lv_key = 'sha_lv_' . $lotteryType;
        $json_obj = json_decode($json_data, true);
        /**
            新版杀率 update 20180627
            需求:已开的所有期投注[包括本期]-已开的所有期中奖[包括本期]）/已开的所有期投注[包括本期]
         */
        $all_bet_sum = array_sum($all_bet_money); //本期所有投注
        $all_award_sum = array_sum($all_award); //本期中奖

        $fastTime = 0;
        //周期限制开始时间
        if($json_obj[$sha_lv_key]['cal_range']=='0'){
            $sql = "SELECT `time` FROM un_order_statistics WHERE type = {$lotteryType}";
            $fastTime = ($this->db->result($sql))?:0;
            $fastTime>strtotime(date('Y-m-01'))?:$fastTime = strtotime(date('Y-m-01'));//每月第一天的时间戳
        }else $fastTime = time()-70;

        if(!empty($all_bet_sum) || !empty($all_award_sum)){
            //历史投注
            $sql = "SELECT SUM(money) AS bet_total FROM un_orders WHERE addtime >={$fastTime} and issue < {$issue} AND reg_type != 8 AND reg_type != 9 AND reg_type != 11 AND lottery_type = {$lotteryType} AND state = 0";
            $history_bet_total = ($this->db->result($sql))?:0;
            //历史中奖
            $sql = "SELECT SUM(award) AS award_total FROM un_orders WHERE addtime >={$fastTime} and issue < {$issue} AND reg_type != 8 AND reg_type != 9 AND reg_type != 11 AND lottery_type = {$lotteryType} AND state = 0 AND award_state = 2";
            $history_award_total = ($this->db->result($sql))?:0;

            if($json_obj[$sha_lv_key]["cal_range"]=='0') {
                //累加
                $all_bet_sum +=$history_bet_total;
                $all_award_sum +=$history_award_total;
            }
        }
        
        if($all_bet_sum>0){
            $sha_lv = ($all_bet_sum - $all_award_sum) / $all_bet_sum * 100;
        }else{
            $sha_lv = 0 ;
        }

        return [
            'sha_lv' => $sha_lv,
        ];

    }

    /**
     * 开奖推送信息
     * @param string $Gateway 连接workm
     * @param string $issue 开奖期号
     * @param int $lotteryType 开奖采种
     */
    public function sendOpenAwardFootBall($issue, $lotteryType, $data)
    {
        $nowTime = time();
        $bi_feng = $data['bi_feng']; //开奖比分
        $room_id =  $data['room_id']; //房间号 要单独获取到
        $re_type =  $data['cup_type']?:$data['type']; //场子类型


        $pressType = array(
            2=>'半场',
            4=>'全场',
            6=>'加时',
            8=>'点球',
        );

        //房间推送信息
        $room = $this->getRooms();
        $room_ids_str = '';
        $send = array();
        foreach ($room as $k=>$v){
            if($v['lottery_type'] != $lotteryType) continue;
            if($v['id'] != $room_id) continue;

            //收集房间号
            $room_ids_str .= '_'.$v['id'];

            //推送开奖结果
            $send = array(
                'commandid'=>3011,
                'type'=>$pressType[$re_type],
                'open_time'=>date('H:i:s'),
                'result'=>$pressType[$re_type].'结束'.$bi_feng
            );

        }

        if(!empty($room_ids_str)) {
            //调用双活接口
            $data['type'] = "open_lottery";
            $data['ids'] = trim($room_ids_str,'_'); //房间集合
            $data['isOpen'] = 1; //是否开奖
            $data['json'] = encode($send);
            for ($i = 0; $i < 2; $i++) {  //防止开奖结果没送出来
                send_home_data($data);
                sleep(1);
            }
        }
    }

    /**
     * 开奖推送信息
     * @param string $Gateway 连接workm
     * @param string $issue 开奖期号
     * @param int $lotteryType 开奖采种
     */
    public function sendOpenAward($issue, $lotteryType, $data)
    {

        //开奖间隔 停售配置
        switch ($lotteryType){
            case 1:
                $stopConfig = 'xy28_stop_or_sell';
                $sql = "SELECT issue, open_result, spare_1, spare_2, FROM_UNIXTIME(open_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM `un_open_award` WHERE `lottery_type` = '{$lotteryType}' AND `issue` = '{$issue}'";
                $qihao_info = $this->db->getone($sql);
                $qihao_info['open_no'] = $qihao_info['spare_1'] . '=' . $qihao_info['open_result'] . ' ( ' . mb_substr($qihao_info['spare_2'], 0, 2) . ' )';
                break;
            case 2:
                $stopConfig = 'bjpk10_stop_or_sell';
                $sql = "select qihao AS issue, kaijianghaoma AS open_no, kaijiangshijian AS open_time from un_bjpk10 where qihao='{$issue}' and lottery_type={$lotteryType}";
                $qihao_info=$this->db->getone($sql);
                break;
            case 3:
                $stopConfig = 'jnd28_stop_or_sell';
                $sql = "SELECT issue, open_result, spare_1, spare_2, FROM_UNIXTIME(open_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM `un_open_award` WHERE `lottery_type` = '{$lotteryType}' AND `issue` = '{$issue}'";
                $qihao_info=$this->db->getone($sql);
                $qihao_info['open_no'] = $qihao_info['spare_1'] . '=' . $qihao_info['open_result'] . ' ( ' . mb_substr($qihao_info['spare_2'], 0, 2) . ' )';
                break;
            case 4:
                $stopConfig = 'xyft_stop_or_sell';
                $sql = "select qihao AS issue, kaijianghaoma AS open_no, kaijiangshijian AS open_time from un_xyft where qihao='{$issue}'";
                $qihao_info = $this->db->getone($sql);
                break;
            case 5:
                $stopConfig = 'cqssc_stop_or_sell';
                $sql = "SELECT issue, lottery_result AS open_no, FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM un_ssc WHERE issue={$issue} AND lottery_type={$lotteryType}";
                $qihao_info = $this->db->getone($sql);
                break;
            case 6:
                $stopConfig = 'sfc_stop_or_sell';
                $sql = "SELECT issue, lottery_result AS open_no, FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM un_ssc WHERE issue={$issue} AND lottery_type={$lotteryType}";
                $qihao_info = $this->db->getone($sql);
                break;
            case 7:
                $stopConfig = 'lhc_stop_or_sell';
                $sql = "SELECT issue, lottery_result AS open_no, FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM un_lhc WHERE issue={$issue} AND lottery_type={$lotteryType}";
                $qihao_info = $this->db->getone($sql);
                break;
            case 8:
                $stopConfig = 'jslhc_stop_or_sell';
                $sql = "SELECT issue, lottery_result AS open_no, FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM un_lhc WHERE issue={$issue} AND lottery_type={$lotteryType}";
                $qihao_info = $this->db->getone($sql);
                break;
            case 9:
                $stopConfig = 'jssc_stop_or_sell';
                $sql = "select qihao AS issue, kaijianghaoma AS open_no, kaijiangshijian AS open_time from un_bjpk10 where qihao='{$issue}' and lottery_type={$lotteryType}";
                $qihao_info=$this->db->getone($sql);
                break;
            case 10:
                $stopConfig = 'nn_stop_or_sell';
                $sql = "SELECT issue, lottery_result AS open_no, FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM un_nn WHERE issue={$issue} AND lottery_type={$lotteryType}";
                $qihao_info = $this->db->getone($sql);
                break;
            case 11:
                $stopConfig = 'ffc_stop_or_sell';
                $sql = "SELECT issue, lottery_result AS open_no, FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM un_ssc WHERE issue={$issue} AND lottery_type={$lotteryType}";
                $qihao_info = $this->db->getone($sql);
                break;
            case 13:
                $stopConfig = 'sb_stop_or_sell';
                $sql = "SELECT issue, lottery_result AS open_no, FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM un_sb WHERE issue={$issue} AND lottery_type={$lotteryType}";
                $qihao_info = $this->db->getone($sql);
                break;
            case 14:
                $stopConfig = 'ffpk10_stop_or_sell';
                $sql = "SELECT issue, lottery_result AS open_no, FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM un_ffpk10 WHERE issue={$issue} AND lottery_type={$lotteryType}";
                $qihao_info = $this->db->getone($sql);
                break;
        }
        $nowTime = time();
        $issueInfo = $this->getQihao($lotteryType,SYS_TIME); //改成系统时间

        //游戏配置
        $redis  = initCacheRedis();
        $config = decode($redis->hGet('Config:'.$stopConfig,'value'));
        deinitCacheRedis($redis);

        //房间推送信息
        $room = $this->getRooms();
        $room_ids_str = '';
        $send = $sendIssueInfo = array();
        foreach ($room as $k=>$v){
            if($v['lottery_type'] != $lotteryType) continue;
            if($issueInfo['issue'] == 0){
                return false;
                //停售信息
                $send = array('commandid'=>3001,'serTime'=>time(),'lotteryType'=>$lotteryType,'time'=>-1,'issue'=>0,'sealingTim'=>0, 'stopOrSell'=>2, 'stopMsg'=>'Temporarily suspended, Confirm the playing time');
                //调用双活接口
                $data['type']="open_lottery";
                $data['id']=$v['id']; //房间ID
                $data['json']=encode($send);
                send_home_data($data);
            }else{
                if($issueInfo['issue'] == ($issue + 1)){
                    $sum_result_str = '';

                    //推送倒计时(及时更新到下一期)
                    $countdown = $issueInfo['date']-time();
                    $sealingTim = $v['closure_time'];
                    $sealingTim = $sealingTim?$sealingTim:0;
                    $sendIssueInfo[$v['id']] = array('commandid'=>3001,'lotteryType'=>$lotteryType,'serTime'=>time(),'time'=>$countdown,'issue'=>$issueInfo['issue'],'sealingTim'=>$sealingTim, 'stopOrSell'=>$config['status'], 'stopMsg'=>$config['title'],'adminSend'=>1);

                    //时时彩开奖结果字段处理
                    if (in_array($lotteryType,array(5,6,11))) {
                        //时时彩开奖结果
                        $ssc_result_sum = array_sum(explode(',', $qihao_info['open_no']));
                        $sum_result_arr = [];
                        $sum_result_arr[] = $ssc_result_sum;
                        $sum_result_arr[] = ($ssc_result_sum >= 23) ? '大' : '小';
                        $sum_result_arr[] = ($ssc_result_sum % 2 == 0) ? '双' : '单';
                        $sum_result_str = implode(',', $sum_result_arr);
                    }

                    if (in_array($lotteryType,array(13))) {
                        //骰宝开奖结果
                        $sb_result_sum = array_sum(explode(',', $qihao_info['open_no']));
                        $sum_result_arr = [];
                        $sum_result_arr[] = $sb_result_sum;
                        $sum_result_arr[] = ($sb_result_sum >= 11) ? '大' : '小';
                        $sum_result_arr[] = ($sb_result_sum % 2 == 0) ? '双' : '单';
                        $sum_result_str = implode(',', $sum_result_arr);
                    }

                    //六合开奖结果字段处理
                    if (in_array($lotteryType,array('7','8'))) {
                        $lhcNumArr = explode(',',$qihao_info['open_no']);
                        $lhcTmpArr = array();
                        foreach ($lhcNumArr as $lv){
                            $lhcTmpArr[] = getLhcShengxiao($lv,strtotime($qihao_info['open_time']));
                        }
                        $sum_result_str = implode(',', $lhcTmpArr);
                    }

                    if(in_array($lotteryType,array(10))) {
                        $tmpData = getShengNiuNiu($qihao_info['open_no']);
                        $nn_result = str_replace(' ', '', $tmpData[2]);
                        $sum_result_str = $tmpData[0] . ',' . $tmpData[1];
                        $send = array('commandid' => 3011, 'issue' => (string)$issue, 'open_time' => substr($qihao_info['open_time'], 5), 'result' => $nn_result, 'statistics' => '', 'sum_result_str' => $sum_result_str, 'niu' => $tmpData[3]);
                    }elseif (in_array($lotteryType,array(7,8))) {
                        //推送开奖结果
                        $send = array('commandid'=>3011,'issue'=>(string)$issue,'open_time'=>substr($qihao_info['open_time'], 5),'result'=>preg_replace('/,(\d+)$/','+$1',$qihao_info['open_no']),'statistics'=>'','sum_result_str'=>$sum_result_str);

                    }else{
                        //推送开奖结果
                        $send = array('commandid'=>3011,'issue'=>(string)$issue,'open_time'=>substr($qihao_info['open_time'], 5),'result'=>$qihao_info['open_no'],'statistics'=>'','sum_result_str'=>$sum_result_str);
                    }

                    if (in_array($lotteryType, array('1', '3'))) {
                        if (!empty($data[3])) {
                            if ($data[3]) {
                                $tj = explode(',', $data[3]);
                                $send['statistics'] = "\n         大数未开{$tj[0]}期\n         小数未开{$tj[1]}期\n         单数未开{$tj[2]}期\n         双数未开{$tj[3]}期\n         大单未开{$tj[4]}期\n         大双未开{$tj[5]}期\n         小单未开{$tj[6]}期\n         小双未开{$tj[7]}期\n         极大未开{$tj[8]}期\n         极小未开{$tj[9]}期";
                            }
                        }
                    }
                    $send['adminSend'] = 1;

                    //收集房间号
                    $room_ids_str .= '_'.$v['id'];
                }
            }
        }


        if(!empty($room_ids_str)) {
            //调用双活接口
            $data['type'] = "open_lottery";
            $data['lottery_type'] = $lotteryType;
            $data['ids'] = trim($room_ids_str,'_'); //房间集合
            $data['isOpen'] = 1; //是否开奖
            $data['json'] = encode($send);
            $data['jsonIssueInfo'] = encode($sendIssueInfo);

            for ($i = 0; $i < 5; $i++) {  //防止开奖结果没送出来
                send_home_data($data);
                sleep(1);
            }
        }
    }

    /**
     * 获取赔率
     * @param string $issue 开奖期号
     * @param string $lotteryResults 中奖号码
     * @param int $lotteryType 开奖采种
     * @return mixed|bool|array
     */
    public function getOdds($issue,$lotteryResults, $lotteryType)
    {
        //得到赔率信息
        $sql = "SELECT way,odds,room FROM un_odds WHERE lottery_type = {$lotteryType} AND  way IN ('".implode("','",$lotteryResults)."')";
//        dump($sql);
        $odds = $this->db->getall($sql);
        if(empty($odds)){
            @file_put_contents('lottery.log', date('Y-m-d H:i:s').PHP_EOL.'ERROR 游戏类型: '.$lotteryType.' 期号：'.$issue.' 中奖号码: '.json_encode($lotteryResults,JSON_UNESCAPED_UNICODE).' 无法获取赔率信息: '.$sql.PHP_EOL,FILE_APPEND);
            return false;
        }
        $oddsa=array();
        foreach ($odds as $v){
            //彩种赔率改为房间赔率
            $oddsa[$v['room']][$v['way']] = $v['odds'];
        }
        @file_put_contents('openAward.log', date('Y-m-d H:i:s').PHP_EOL.'游戏类型: '.$lotteryType.' 期号：'.$issue.' 中奖号码: '.json_encode($lotteryResults,JSON_UNESCAPED_UNICODE).' 赔率信息: '.$sql.' | data: '.json_encode($odds,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);
        return $oddsa;
    }

    /**
     * 获取期号
     * @param int $lotteryType 开奖采种
     * @param int $nowtime 当前时间
     */
    public function getQihao($lotteryType,$nowtime,$room = 0)
    {

        $btime = microtime(1);
        //停售时间段
        $stopStartTime = '23:59:59';//停售开始时间
        $stopEndTime = '00:00';//停售结束时间
        $stopTime = '0';//当天时间段停售 0 第二天停售 86400
        //开奖间隔 停售配置 停售时间段
        switch ($lotteryType){
            case 1:
                $space = 300;
                $stopConfig = 'xy28_stop_or_sell';
                $stopStartTime = '23:55';
                $stopEndTime = '09:00';
                $stopTime = 86400;
                break;
            case 2:
                $space = 1200;
                $stopConfig = 'bjpk10_stop_or_sell';
                $stopStartTime = '23:55';
                $stopEndTime = '09:00';
                break;
            case 3:
                $space = 210;
                $stopConfig = 'jnd28_stop_or_sell';
                $stopStartTime = '19:00';
                $stopEndTime = '19:10';
                break;
            case 4:
                $space = 300;
                $stopConfig = 'xyft_stop_or_sell';
                $stopStartTime = '04:04';
                $stopEndTime = '13:00';
                break;
            case 5:
                $space = 1200;
                $stopConfig = 'cqssc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 6:
                $space = 180;
                $stopConfig = 'sfc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 7:
                $space = 180;
                $stopConfig = 'lhc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 8:
                $space = 300;
                $stopConfig = 'jslhc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 9:
                $space = 180;
                $stopConfig = 'jssc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 10:
                $space = 300;
                $stopConfig = 'nn_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 11:
                $space = 60;
                $stopConfig = 'ffc_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 13:
                $space = 300;
                $stopConfig = 'tb_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            case 14:
                $space = 60;
                $stopConfig = 'ffpk10_stop_or_sell';
                $stopStartTime = '00:00';
                $stopEndTime = '00:00';
                break;
            default:
                $space = 0;
        }

        //连接redis
        $redis = initCacheRedis();
        $first = $redis->get("QiHaoFirst".$lotteryType);
        $last = $redis->get("QiHaoLast".$lotteryType);
        //返回信息
        $data = array(
            'issue' => 0,
            'date' => 0,
            'time' => 0,
            'QiHaoFirst' => json_decode($first,true),
            'QiHaoLast' => json_decode($last,true),
        );

        if($lotteryType==0){ //六合彩单独处理

        }else{
            //如果不在售彩时间段
            $lottery = $this -> getRedisHashValues('LotteryType:'.$lotteryType,'config');
            $lottery_config=json_decode($lottery,true);

            $start_time = strtotime($lottery_config['start_time']);
            $end_time = strtotime($lottery_config['end_time']);
            $stop_start_time = strtotime($stopStartTime);
            $stop_end_time = strtotime($stopEndTime) + $stopTime;

            //停售时间不在当天时间段的特殊处理
            if($end_time <= $start_time){
                $specialTime = 86400;
                $start_time -= $specialTime;
                $end_time += $specialTime;
            }
        }

        if($nowtime < $start_time || $nowtime > $end_time || ($nowtime > $stop_start_time && $nowtime < $stop_end_time)){
            if($lotteryType==3){
                $data['msg'] = 'Discontinued time: '.$lottery_config['end_time'].'-'.$lottery_config['start_time'];
            }else{
                $data['msg'] = 'Discontinued, play time 1: '.$lottery_config['start_time'].'-'.$lottery_config['end_time'];
            }
            $tip = $this->db->result("select tip from un_lottery_type WHERE id = $lotteryType");
            if($tip!="") $data['msg'] = $tip;
            $data['sealingTim'] = 0;
            $data['lotteryType'] = $lotteryType;
            $data['stopOrSell'] = 2;
            return $data;
        }

        //如果后台设置停止售彩
        $config_res=$this->getConfig($stopConfig,array('value'));
        $config_config=json_decode($config_res['value'],true);
        if($config_config['status']==2){

            $data['msg'] = $config_config['title'];
            $data['sealingTim'] = 0;
            $data['lotteryType'] = $lotteryType;
            $data['stopOrSell'] = 2;
            return $data;
        }

        //todo
        //房间停售 封盘时间
        $closure_time = 0;
        if($room){
            //TODO: 后续完善,暂无房间停售
            //封盘时间
            $closure_time = $this -> getRedisHashValues('allroom:'.$room,'closure_time');
            $closure_time = $closure_time?$closure_time:0;
        }

        $QiHao = $redis->lRange("QiHaoIds".$lotteryType, 0, -1);

        foreach ($QiHao as $v){
            $res = json_decode($v,true);
            if($res['date'] <= $nowtime){
                //将对应的键删除
                $redis->Lrem("QiHaoIds".$lotteryType, $v);
            }else{
                if($lotteryType==7){ //六合彩单独处理
                    $data = $res;
                }else{
                    if($res['date']-$nowtime <= $space){
                        $data = $res;
                    }
                }
                break;
            }
        }
        deinitCacheRedis($redis);
        if ($data['issue'] == 0){
            $data = self::setqihao($lotteryType,$nowtime);
        }
        $data['sealingTim'] = $closure_time;
        $data['lotteryType'] = $lotteryType;
        $data['stopOrSell'] = 1;
        if($nowtime < $start_time || $nowtime > $end_time || ($nowtime > $stop_start_time && $nowtime < $stop_end_time)){
            $data['stopOrSell'] = 2;
        }else{
            if($data['issue']==0){
                if($lotteryType==3){ //update 20180621
                    $data['msg'] = 'Discontinued time: '.$lottery_config['end_time'].'-'.$lottery_config['start_time'];
                }else{
                    $data['msg'] = 'Discontinued, play time2： '.$lottery_config['start_time'].'-'.$lottery_config['end_time'];
                }
                $tip = $this->db->result("select tip from un_lottery_type WHERE id = $lotteryType");
                if($tip!="") $data['msg'] = $tip;
                $data['sealingTim'] = 0;
                $data['lotteryType'] = $lotteryType;
                $data['stopOrSell'] = 2;
                return $data;
            }
        }
        $data['stopOrSell'] = ($data['issue']==0)?2:1;
        $data['time'] = $data['date']-time();
        $data['QiHaoFirst'] = json_decode($first,true);
        $data['QiHaoLast'] = json_decode($last,true);
        //幸运飞艇期号前台需要截取掉前面4位, 后台必须把期号转成字符串类型, 前台才不会报错, 才能进行切割（WTF）...
        $data['issue'] = (string)$data['issue'];

        $etime = microtime(1);
        return $data;
    }

    /**
     * 重获取期号
     * @param int $lotteryType 开奖采种
     * @param int $nowtime 当前时间
     */
    public function setqihao($lotteryType,$time){

        //开奖间隔 数据源
        switch ($lotteryType){
            case 1:
                $space = 300;
                $data = @file_get_contents('xy28_qihao.json'); //获取数据
                break;
            case 2:
                $space = 1200;
                $data = @file_get_contents('bjpk10_qihao.json'); //获取数据
                break;
            case 3:
                $space = 210;
                $data = @file_get_contents('jnd28_qihao.json'); //获取数据
                break;
            case 4:
                $space = 300;
                $data = @file_get_contents('xyft_qihao.json'); //获取数据
                break;
            case 5:
                $space = 1200;
                $data = @file_get_contents('cqssc_qihao.json'); //获取数据
                break;
            case 6:
                $space = 180;
                $data = @file_get_contents('sfc_qihao.json'); //获取数据
                break;
            case 7:
                $space = 180;
                $data = @file_get_contents('lhc_qihao.json'); //获取数据
                break;
            case 8:
                $space = 300;
                $data = @file_get_contents('jslhc_qihao.json'); //获取数据
                break;
            case 9:
                $space = 180;
                $data = @file_get_contents('jssc_qihao.json'); //获取数据
                break;
            case 10:
                $space = 300;
                $data = @file_get_contents('nn_qihao.json'); //获取数据
                break;
            case 11:
                $space = 60;
                $data = @file_get_contents('ffc_qihao.json'); //获取数据
                break;
            case 13:
                $space = 300;
                $data = @file_get_contents('sb_qihao.json'); //获取数据
                break;
            case 14:
                $space = 60;
                $data = @file_get_contents('ffpk10_qihao.json'); //获取数据
                break;
            default:
                $space = 0;
        }
        $data = json_decode($data,true);
        $list = json_decode($data['txt'],true);

        //连接redis
        $redis = initCacheRedis();
        $redis -> del("QiHaoFirst".$lotteryType);
        $redis -> del("QiHaoLast".$lotteryType);
        $redis -> del("QiHaoIds".$lotteryType); //删除之前的缓存
        //最后一期
        $last = end($list['list']);
        $redis -> set("QiHaoLast".$lotteryType,json_encode($last));
        //第一期
        $first = reset($list['list']);
        $redis -> set("QiHaoFirst".$lotteryType,json_encode($first));
        //一天的期号
        foreach ($list['list'] as $v){
            $key = json_encode($v);
            //将对应的键存入队列中
            $redis -> RPUSH("QiHaoIds".$lotteryType, $key);
        }
        $QiHao = $redis->lRange("QiHaoIds".$lotteryType, 0, -1);

        //返回信息
        $data = array(
            'issue' => 0,
            'date' => 0,
            'QiHaoFirst' => $first,
            'QiHaoLast' => $last,
            'msg' => "Before the lottery play time, the play is temporarily suspended",
        );
        foreach ($QiHao as $v){
            $res = json_decode($v,true);
            if($res['date'] <= $time){
                //将对应的键删除
                $redis -> Lrem("QiHaoIds".$lotteryType, $v);
            }else{
                if($res['date']-$time <= $space){
                    $data = $res;
                    break;
                }
            }
        }
        $redis -> close();
        return $data;
    }

    /**
     * 修改开奖状态
     * @param string $issue 开奖期号
     * @param int $lotteryType 开奖采种
     * @param int $status 开奖状态 0自动, 1手动, 2未开;
     * @param int $uid 开奖人 0自动;
     * @param int $type 0无投注订单;
     * @param int $frequency 开奖频率次数;
     */
    public function modifyLotteryStatus($lotteryType, $issue, $status, $uid, $type = 0,$frequency)
    {
        $msg = $type?'':'No betting information, ';

        //开奖间隔 数据源
        switch ($lotteryType){
            case 1:
                $sql = "UPDATE un_open_award SET `state`='{$status}', `user_id`='{$uid}' WHERE (`lottery_type`=$lotteryType and  `issue`='{$issue}')";
                $res = $this->db->exec($sql);
                break;
            case 2:
                $sql = "UPDATE un_bjpk10 SET `status`='{$status}', `user_id`='{$uid}' WHERE (`qihao`='{$issue}' and lottery_type={$lotteryType})";
                $res = $this->db->exec($sql);
                break;
            case 3:
                $sql = "UPDATE un_open_award SET `state`='{$status}', `user_id`='{$uid}' WHERE (`lottery_type`=$lotteryType and  `issue`='{$issue}')";
                $res = $this->db->exec($sql);
                break;
            case 4:
                $sql = "UPDATE un_xyft SET `status`='{$status}', `user_id`='{$uid}' WHERE (`qihao`='{$issue}')";
                $res = $this->db->exec($sql);
                break;
            case 5:
                $sql = "UPDATE un_ssc SET `status`='{$status}', `user_id`='{$uid}' WHERE (`issue`='{$issue}' and `lottery_type`=$lotteryType)";
                $res = $this->db->exec($sql);
                break;
            case 6:
                $sql = "UPDATE un_ssc SET `status`='{$status}', `user_id`='{$uid}' WHERE (`issue`='{$issue}' and `lottery_type`=$lotteryType)";
                $res = $this->db->exec($sql);
                break;
            case 7:
                $sql = "UPDATE un_lhc SET `status`='{$status}', `user_id`='{$uid}' WHERE (`issue`='{$issue}' and `lottery_type`=$lotteryType)";
                $res = $this->db->exec($sql);
                break;
            case 8:
                $sql = "UPDATE un_lhc SET `status`='{$status}', `user_id`='{$uid}' WHERE (`issue`='{$issue}' and `lottery_type`=$lotteryType)";
                $res = $this->db->exec($sql);
                break;
            case 9:
                $sql = "UPDATE un_bjpk10 SET `status`='{$status}', `user_id`='{$uid}' WHERE (`qihao`='{$issue}' and lottery_type={$lotteryType})";
                $res = $this->db->exec($sql);
                break;
            case 10:
                $sql = "UPDATE un_nn SET `status`='{$status}', `user_id`='{$uid}' WHERE (`issue`='{$issue}' and `lottery_type`=$lotteryType)";
                $res = $this->db->exec($sql);
                break;
            case 11:
                $sql = "UPDATE un_ssc SET `status`='{$status}', `user_id`='{$uid}' WHERE (`issue`='{$issue}' and `lottery_type`={$lotteryType})";
                $res = $this->db->exec($sql);
                break;
            case 13:
                $sql = "UPDATE un_sb SET `status`='{$status}', `user_id`='{$uid}' WHERE (`issue`='{$issue}' and `lottery_type`={$lotteryType})";
                $res = $this->db->exec($sql);
                break;
            case 14:
                $sql = "UPDATE un_ffpk10 SET `status`='{$status}', `user_id`='{$uid}' WHERE (`issue`='{$issue}' and `lottery_type`={$lotteryType})";
                $res = $this->db->exec($sql);
                break;
        }

        if (empty($res)){
            //设置捕获异常开奖
            $this->getOpenError($lotteryType,$issue,$frequency);
        }
    }

    /**
     * 控制昵称显示
     * @param string $username 昵称
     */
    public function getNickname($username)
    {
        if(empty($username)){
            $username = time();
        }
        //控制昵称显示 0-关闭 1-打开 关闭的话, 就只能看到所有第一个字母和最后一个字母, 中间都是***,举例：昵称为张三李四, 开启后就是显示为张*四。开启的话, 可以显示所有昵称
        $tznickanme = $this->getConfig("tznickname",'value');
        if(!$tznickanme){
            $strleng = mb_strlen($username)-1;
            $username = mb_substr($username,0,1,'utf-8')."***".mb_substr($username,$strleng,1,'utf-8');
        }
        return $username;
    }

    /**
     * 捕获开奖异常信息
     * @param int $lotteryType 彩种
     * @param int $issue 期号
     * @param int $frequency 次数频率$frequency
     */
    public function getOpenError($lotteryType, $issue,$frequency)
    {
        //初始化redis
        $redis = initCacheRedis();
        if(strtoupper($frequency) === "GET"){
            return $redis->get("openError_{$lotteryType}:{$issue}");
        }else{
            $redis->setex("openError_{$lotteryType}:{$issue}", 600,$frequency);
        }

        //关闭redis链接
        deinitCacheRedis($redis);
    }

    /**
     * 添加/更新彩种长龙
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-25 21:01:47
     * @param $issue int 期号
     * @param $lotteryType int 彩种id
     * @return void
     */
    public function longDragon($lotteryType, $issue){
        //设置php系统变量
        ini_set('max_execution_time', '0');
        ini_set('memory_limit','2048M');

        if (!empty($issue) && !empty($lotteryType) && in_array($lotteryType,['1','2','3','4','5','6','7','8','9','10','11','13','14'])) {

            $rows = $this->getOneCouponNew('*', ['lottery_type'=>$lotteryType], 'id desc', "#@_long_dragon");
            $start = strtotime(date('Y-m-01 00:00:00' ,strtotime('-2 month')));
            $end = strtotime(date('Y-m-d H:i:s' , time()));

            if (in_array($lotteryType,['1','3'])) {
                //1：幸运28   3：加拿大28
                if (empty($rows)) {
                    $sql = "select open_result,issue,id from #@_open_award where open_time between {$start} and {$end} and lottery_type = {$lotteryType} order by open_time DESC";
                    $list = $this->db->getall($sql);
                    $dx_arr = [];
                    $ds_arr = [];
                    foreach ($list as $key=>$value) {
                        $result = $this->getLotteryResults($lotteryType, array(0,$value['open_result']));
                        foreach ($result as $value_1) {
                            if (in_array($value_1,['大','小'])) {
                                $dx_arr[] = $value_1;
                            }
                            if (in_array($value_1,['单','双'])) {
                                $ds_arr[] = $value_1;
                            }
                        }
                    }
                    $data['dx'] = [];
                    $data['ds'] = [];
                    foreach ($dx_arr as $key=>$val) {
                        if (stripos($data['dx'][count($data['dx'])-1],$val) === false) {
                            $data['dx'][] = $val;
                        } else {
                            $data['dx'][count($data['dx'])-1] = $data['dx'][count($data['dx'])-1].",".$val;
                        }
                    }

                    foreach ($ds_arr as $key=>$val) {
                        if (stripos($data['ds'][count($data['ds'])-1],$val) === false) {
                            $data['ds'][] = $val;
                        } else {
                            $data['ds'][count($data['ds'])-1] = $data['ds'][count($data['ds'])-1].",".$val;
                        }
                    }
                    if (count($data['ds']) > 30) {
                        $data['ds'] = array_slice($data['ds'],count($data['ds'])-30,30);
                    }

                    if (count($data['dx']) > 30) {
                        $data['dx'] = array_slice($data['dx'],count($data['dx'])-30,30);
                    }
                    $array = array_shift($list);
                    $post_data = [
                        'value' => encode($data),
                        'lottery_type' => $lotteryType,
                        'issue' => $array['issue'],
                        'add_time' => time()
                    ];
                    $this->db->insert("#@_long_dragon", $post_data);

                } else {

                    $data = decode($rows['value']);
                    $sql = "select open_result,issue,id from #@_open_award where lottery_type = {$lotteryType} and issue = {$issue}";
                    $list = $this->db->getone($sql);
                    $result = $this->getLotteryResults($lotteryType, array(0,$list['open_result']));
                    foreach ($result as $value) {
                        if (in_array($value,['大','小'])) {
                            $dx = $value;
                        }
                        if (in_array($value,['单','双'])) {
                            $ds = $value;
                        }
                    }

                    if (stripos($data['dx'][count($data['dx'])-1],$dx) === false) {
                        $data['dx'][] = $dx;
                    } else {
                        $data['dx'][count($data['dx'])-1] = $data['dx'][count($data['dx'])-1].",".$dx;
                    }

                    if (stripos($data['ds'][count($data['ds'])-1],$ds) === false) {
                        $data['ds'][] = $ds;
                    } else {
                        $data['ds'][count($data['ds'])-1] = $data['ds'][count($data['ds'])-1].",".$ds;
                    }

                    if (count($data['ds']) > 30) {
                        $data['ds'] = array_slice($data['ds'],count($data['ds'])-30,30);
                    }

                    if (count($data['dx']) > 30) {
                        $data['dx'] = array_slice($data['dx'],count($data['dx'])-30,30);
                    }

                    if ($rows['issue'] != $issue && $rows['lottery_type'] == $lotteryType) {
                        $post_data = [
                            'value' => encode($data),
                            'issue' => $issue,
                            'update_time' => time()
                        ];
                        $this->db->update("#@_long_dragon", $post_data, ['issue'=>$rows['issue'], 'lottery_type'=>$lotteryType]);
                    }
                }

            } elseif(in_array($lotteryType,['2','9','4','14'])){
                //2：北京PK10   9：急速赛车   4：幸运飞艇   14：分分PK10
                $start = date('Y-m-d H:i:s' , $start);
                $end = date('Y-m-d H:i:s' , $end);
                if (empty($rows)) {
                    if ($lotteryType == 2 || $lotteryType == 9) {
                        $sql = "select kaijianghaoma, qihao as issue from #@_bjpk10 where kaijiangshijian >= '".$start."' and kaijiangshijian <= '".$end."' and lottery_type = {$lotteryType} order by kaijiangshijian DESC";
                    } elseif($lotteryType == 14){
                        $sql = "select lottery_result, issue from #@_ffpk10 where lottery_time >= '".strtotime($start)."' and lottery_time <= '".strtotime($end)."' and lottery_type = {$lotteryType} order by lottery_time DESC";
                    } else {
                        $sql = "select kaijianghaoma, qihao as issue from #@_xyft where kaijiangshijian >= '".$start."' and kaijiangshijian <= '".$end."' order by kaijiangshijian DESC";
                    }
                    $list = $this->db->getall($sql);

                    $arr = [
                        "one_dx" => [],
                        "two_dx" => [],
                        "three_dx" => [],
                        "four_dx" => [],
                        "five_dx" => [],
                        "six_dx" => [],
                        "seven_dx" => [],
                        "eight_dx" => [],
                        "nine_dx" => [],
                        "ten_dx" => [],

                        "one_ds" => [],
                        "two_ds" => [],
                        "three_ds" => [],
                        "four_ds" => [],
                        "five_ds" => [],
                        "six_ds" => [],
                        "seven_ds" => [],
                        "eight_ds" => [],
                        "nine_ds" => [],
                        "ten_ds" => [],

                        "one_lh" => [],
                        "two_lh" => [],
                        "three_lh" => [],
                        "four_lh" => [],
                        "five_lh" => [],

                        "zx" => [],
                        "gyh_dx" => [],
                        "gyh_ds" => [],
                    ];
                    $data = $arr;

                    foreach ($list as $key=>$value) {

                        if ($lotteryType == 14) {
                            $result = $this->getLotteryResults($lotteryType, array('lottery_result'=>$value['lottery_result']));
                        } else {
                            $result = $this->getLotteryResults($lotteryType, array('kaijianghaoma'=>$value['kaijianghaoma']));
                        }


                        foreach ($result as $value_1) {
                            if (in_array($value_1,['冠军_大','冠军_小'])) {
                                $arr["one_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['亚军_大','亚军_小'])) {
                                $arr["two_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第三名_大','第三名_小'])) {
                                $arr["three_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第四名_大','第四名_小'])) {
                                $arr["four_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第五名_大','第五名_小'])) {
                                $arr["five_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第六名_大','第六名_小'])) {
                                $arr["six_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第七名_大','第七名_小'])) {
                                $arr["seven_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第八名_大','第八名_小'])) {
                                $arr["eight_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第九名_大','第九名_小'])) {
                                $arr["nine_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第十名_大','第十名_小'])) {
                                $arr["ten_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['冠军_单','冠军_双'])) {
                                $arr["one_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['亚军_单','亚军_双'])) {
                                $arr["two_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第三名_单','第三名_双'])) {
                                $arr["three_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第四名_单','第四名_双'])) {
                                $arr["four_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第五名_单','第五名_双'])) {
                                $arr["five_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第六名_单','第六名_双'])) {
                                $arr["six_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第七名_单','第七名_双'])) {
                                $arr["seven_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第八名_单','第八名_双'])) {
                                $arr["eight_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第九名_单','第九名_双'])) {
                                $arr["nine_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第十名_单','第十名_双'])) {
                                $arr["ten_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['冠军_龙','冠军_虎'])) {
                                $arr["one_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['亚军_龙','亚军_虎'])) {
                                $arr["two_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['第三名_龙','第三名_虎'])) {
                                $arr["three_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['第四名_龙','第四名_虎'])) {
                                $arr["four_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['第五名_龙','第五名_虎'])) {
                                $arr["five_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['庄','闲'])) {
                                $arr["zx"][] = $value_1;
                            }
                            if (in_array($value_1,['冠亚和_大','冠亚和_小'])) {
                                $arr["gyh_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['冠亚和_单','冠亚和_双'])) {
                                $arr["gyh_ds"][] = $value_1;
                            }
                        }
                    }
                    foreach ($arr as $key=>$val) {
                        foreach ($val as $va) {
                            if (stripos($data[$key][count($data[$key])-1],$va) === false) {
                                $data[$key][] = $va;
                            } else {
                                $data[$key][count($data[$key])-1] = $data[$key][count($data[$key])-1].",".$va;
                            }
                        }
                    }
                    foreach ($data as $key=>$val) {
                        if (count($val) > 30) {
                            $data[$key] = array_slice($data[$key],count($data[$key])-30,30);
                        }
                    }
                    $array = array_shift($list);
                    $post_data = [
                        'value' => encode($data),
                        'lottery_type' => $lotteryType,
                        'issue' => $array['issue'],
                        'add_time' => time()
                    ];
                    $this->db->insert("#@_long_dragon", $post_data);

                } else {

                    $data = decode($rows['value']);

                    if ($lotteryType == 2 || $lotteryType == 9) {
                        $sql = "select kaijianghaoma, qihao as issue from #@_bjpk10 where lottery_type = {$lotteryType} and qihao = {$issue}";
                    } elseif($lotteryType == 14){
                        $sql = "select lottery_result, issue from #@_ffpk10 where lottery_time >= '".strtotime($start)."' and lottery_time <= '".strtotime($end)."' and lottery_type = {$lotteryType} order by lottery_time DESC";
                    } else {
                        $sql = "select kaijianghaoma, qihao as issue from #@_xyft where qihao = {$issue}";
                    }
                    $list = $this->db->getone($sql);
                    if ($lotteryType == 14) {
                        $result = $this->getLotteryResults($lotteryType, array('lottery_result'=>$list['lottery_result']));
                    } else {
                        $result = $this->getLotteryResults($lotteryType, array('kaijianghaoma'=>$list['kaijianghaoma']));
                    }
                    foreach ($result as $value_1) {
                        if (in_array($value_1,['冠军_大','冠军_小'])) {
                            $arr["one_dx"] = $value_1;
                        }
                        if (in_array($value_1,['亚军_大','亚军_小'])) {
                            $arr["two_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第三名_大','第三名_小'])) {
                            $arr["three_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第四名_大','第四名_小'])) {
                            $arr["four_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第五名_大','第五名_小'])) {
                            $arr["five_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第六名_大','第六名_小'])) {
                            $arr["six_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第七名_大','第七名_小'])) {
                            $arr["seven_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第八名_大','第八名_小'])) {
                            $arr["eight_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第九名_大','第九名_小'])) {
                            $arr["nine_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第十名_大','第十名_小'])) {
                            $arr["ten_dx"] = $value_1;
                        }
                        if (in_array($value_1,['冠军_单','冠军_双'])) {
                            $arr["one_ds"] = $value_1;
                        }
                        if (in_array($value_1,['亚军_单','亚军_双'])) {
                            $arr["two_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第三名_单','第三名_双'])) {
                            $arr["three_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第四名_单','第四名_双'])) {
                            $arr["four_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第五名_单','第五名_双'])) {
                            $arr["five_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第六名_单','第六名_双'])) {
                            $arr["six_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第七名_单','第七名_双'])) {
                            $arr["seven_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第八名_单','第八名_双'])) {
                            $arr["eight_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第九名_单','第九名_双'])) {
                            $arr["nine_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第十名_单','第十名_双'])) {
                            $arr["ten_ds"] = $value_1;
                        }
                        if (in_array($value_1,['冠军_龙','冠军_虎'])) {
                            $arr["one_lh"] = $value_1;
                        }
                        if (in_array($value_1,['亚军_龙','亚军_虎'])) {
                            $arr["two_lh"] = $value_1;
                        }
                        if (in_array($value_1,['第三名_龙','第三名_虎'])) {
                            $arr["three_lh"] = $value_1;
                        }
                        if (in_array($value_1,['第四名_龙','第四名_虎'])) {
                            $arr["four_lh"] = $value_1;
                        }
                        if (in_array($value_1,['第五名_龙','第五名_虎'])) {
                            $arr["five_lh"] = $value_1;
                        }
                        if (in_array($value_1,['庄','闲'])) {
                            $arr["zx"] = $value_1;
                        }
                        if (in_array($value_1,['冠亚和_大','冠亚和_小'])) {
                            $arr["gyh_dx"] = $value_1;
                        }
                        if (in_array($value_1,['冠亚和_单','冠亚和_双'])) {
                            $arr["gyh_ds"] = $value_1;
                        }
                    }

                    foreach ($data as $key=>$val) {
                        if (stripos($data[$key][count($data[$key])-1],$arr[$key]) === false) {
                            $data[$key][] = $arr[$key];
                        } else {
                            $data[$key][count($data[$key])-1] = $data[$key][count($data[$key])-1].",".$arr[$key];
                        }
                    }

                    foreach ($data as $key=>$val) {
                        if (count($val) > 30) {
                            $data[$key] = array_slice($data[$key],count($data[$key])-30,30);
                        }
                    }

                    if ($rows['issue'] != $issue && $rows['lottery_type'] == $lotteryType) {
                        $post_data = [
                            'value' => encode($data),
                            'issue' => $issue,
                            'update_time' => time()
                        ];
                        $this->db->update("#@_long_dragon", $post_data, ['issue'=>$rows['issue'], 'lottery_type'=>$lotteryType]);
                    }
                }

            } elseif(in_array($lotteryType,['5','6','11'])){

                //5：重庆时时彩   6：三分彩   11:分分彩
                if (empty($rows)) {
                    $sql = "select lottery_result, issue, id from #@_ssc where lottery_time between {$start} and {$end} and lottery_type = {$lotteryType} order by lottery_time DESC";
                    $list = $this->db->getall($sql);
                    $arr = [
                        "one_dx" => [],
                        "two_dx" => [],
                        "three_dx" => [],
                        "four_dx" => [],
                        "five_dx" => [],

                        "one_ds" => [],
                        "two_ds" => [],
                        "three_ds" => [],
                        "four_ds" => [],
                        "five_ds" => [],

                        "one_zh" => [],
                        "two_zh" => [],
                        "three_zh" => [],
                        "four_zh" => [],
                        "five_zh" => [],

                        "zh_dx" => [],
                        "zh_ds" => [],
                        "lhh" => [],
                    ];
                    $data = $arr;

                    foreach ($list as $key=>$value) {

                        $result = $this->getLotteryResults($lotteryType, array('lottery_result'=>$value['lottery_result']));

                        foreach ($result as $value_1) {
                            if (in_array($value_1,['第一球_大','第一球_小'])) {
                                $arr["one_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第二球_大','第二球_小'])) {
                                $arr["two_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第三球_大','第三球_小'])) {
                                $arr["three_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第四球_大','第四球_小'])) {
                                $arr["four_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第五球_大','第五球_小'])) {
                                $arr["five_dx"][] = $value_1;
                            }

                            if (in_array($value_1,['第一球_单','第一球_双'])) {
                                $arr["one_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第二球_单','第二球_双'])) {
                                $arr["two_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第三球_单','第三球_双'])) {
                                $arr["three_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第四球_单','第四球_双'])) {
                                $arr["four_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第五球_单','第五球_双'])) {
                                $arr["five_ds"][] = $value_1;
                            }

                            if (in_array($value_1,['总和_大','总和_小'])) {
                                $arr["zh_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['总和_单','总和_双'])) {
                                $arr["zh_ds"][] = $value_1;
                            }

                            if (in_array($value_1,['龙','虎','和'])) {
                                $arr["lhh"][] = $value_1;
                            }
                        }
                    }
                    foreach ($arr as $key=>$val) {
                        foreach ($val as $va) {
                            if (stripos($data[$key][count($data[$key])-1],$va) === false) {
                                $data[$key][] = $va;
                            } else {
                                $data[$key][count($data[$key])-1] = $data[$key][count($data[$key])-1].",".$va;
                            }
                        }
                    }
                    foreach ($data as $key=>$val) {
                        if (count($val) > 30) {
                            $data[$key] = array_slice($data[$key],count($data[$key])-30,30);
                        }
                    }

                    $array = array_shift($list);
                    $post_data = [
                        'value' => encode($data),
                        'lottery_type' => $lotteryType,
                        'issue' => $array['issue'],
                        'add_time' => time()
                    ];
                    $this->db->insert("#@_long_dragon", $post_data);

                } else {

                    $data = decode($rows['value']);

                    $sql = "select lottery_result, issue, id from #@_ssc where lottery_type = {$lotteryType} and issue = {$issue}";
                    $list = $this->db->getone($sql);

                    $result = $this->getLotteryResults($lotteryType, array('lottery_result'=>$list['lottery_result']));

                    foreach ($result as $value_1) {
                        if (in_array($value_1,['第一球_大','第一球_小'])) {
                            $arr["one_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第二球_大','第二球_小'])) {
                            $arr["two_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第三球_大','第三球_小'])) {
                            $arr["three_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第四球_大','第四球_小'])) {
                            $arr["four_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第五球_大','第五球_小'])) {
                            $arr["five_dx"] = $value_1;
                        }

                        if (in_array($value_1,['第一球_单','第一球_双'])) {
                            $arr["one_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第二球_单','第二球_双'])) {
                            $arr["two_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第三球_单','第三球_双'])) {
                            $arr["three_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第四球_单','第四球_双'])) {
                            $arr["four_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第五球_单','第五球_双'])) {
                            $arr["five_ds"] = $value_1;
                        }

                        if (in_array($value_1,['总和_大','总和_小'])) {
                            $arr["zh_dx"] = $value_1;
                        }
                        if (in_array($value_1,['总和_单','总和_双'])) {
                            $arr["zh_ds"] = $value_1;
                        }

                        if (in_array($value_1,['龙','虎','和'])) {
                            $arr["lhh"] = $value_1;
                        }
                    }

                    foreach ($data as $key=>$val) {
                        if (stripos($data[$key][count($data[$key])-1],$arr[$key]) === false) {
                            $data[$key][] = $arr[$key];
                        } else {
                            $data[$key][count($data[$key])-1] = $data[$key][count($data[$key])-1].",".$arr[$key];
                        }
                    }

                    foreach ($data as $key=>$val) {
                        if (count($val) > 30) {
                            $data[$key] = array_slice($data[$key],count($data[$key])-30,30);
                        }
                    }

                    if ($rows['issue'] != $issue && $rows['lottery_type'] == $lotteryType) {
                        $post_data = [
                            'value' => encode($data),
                            'issue' => $issue,
                            'update_time' => time()
                        ];
                        $this->db->update("#@_long_dragon", $post_data, ['issue'=>$rows['issue'], 'lottery_type'=>$lotteryType]);
                    }

                }

            } elseif(in_array($lotteryType,['7','8'])){

                //六合彩  急速六合彩
                if (empty($rows)) {

                    $sql = "select lottery_result, issue, id from #@_lhc where lottery_time between {$start} and {$end} and lottery_type = {$lotteryType} order by lottery_time DESC";
                    $list = $this->db->getall($sql);

                    $arr = [
                        "special_dx" => [],//特码A-大小
                        "special_ds" => [],//特码A-单双
                        "special_bs" => [],//特码A-波色
                        "special_wdx" => [],//特码A-尾大小
                        "special_hdx" => [],//特码A-合大小
                        "special_hds" => [],//特码A-合单双
                        "special_jqys" => [],//特码A-家禽野兽

                        "just_zhdx" => [],//正码A-总和大小
                        "just_zhds" => [],//正码A-总和单双
                        "just_lh" => [],//正码A-龙虎
                        "just_wdx" => [],//正码A-总尾大小

                        "just_1_special_wdx" => [],//正1特-尾大小
                        "just_1_special_dx" => [],//正1特-大小
                        "just_1_special_ds" => [],//正1特-单双
                        "just_1_special_hds" => [],//正1特-合单双
                        "just_1_special_hdx" => [],//正1特-合大小
                        "just_1_special_bs" => [],//正1特-波色

                        "just_2_special_wdx" => [],//正2特-尾大小
                        "just_2_special_dx" => [],//正2特-大小
                        "just_2_special_ds" => [],//正2特-单双
                        "just_2_special_hds" => [],//正2特-合单双
                        "just_2_special_hdx" => [],//正2特-合大小
                        "just_2_special_bs" => [],//正2特-波色

                        "just_3_special_wdx" => [],//正3特-尾大小
                        "just_3_special_dx" => [],//正3特-大小
                        "just_3_special_ds" => [],//正3特-单双
                        "just_3_special_hds" => [],//正3特-合单双
                        "just_3_special_hdx" => [],//正3特-合大小
                        "just_3_special_bs" => [],//正3特-波色

                        "just_4_special_wdx" => [],//正4特-尾大小
                        "just_4_special_dx" => [],//正4特-大小
                        "just_4_special_ds" => [],//正4特-单双
                        "just_4_special_hds" => [],//正4特-合单双
                        "just_4_special_hdx" => [],//正4特-合大小
                        "just_4_special_bs" => [],//正4特-波色

                        "just_5_special_wdx" => [],//正5特-尾大小
                        "just_5_special_dx" => [],//正5特-大小
                        "just_5_special_ds" => [],//正5特-单双
                        "just_5_special_hds" => [],//正5特-合单双
                        "just_5_special_hdx" => [],//正5特-合大小
                        "just_5_special_bs" => [],//正5特-波色

                        "just_6_special_wdx" => [],//正6特-尾大小
                        "just_6_special_dx" => [],//正6特-大小
                        "just_6_special_ds" => [],//正6特-单双
                        "just_6_special_hds" => [],//正6特-合单双
                        "just_6_special_hdx" => [],//正6特-合大小
                        "just_6_special_bs" => [],//正6特-波色

                        "1_2_lh" =>[],
                        "1_3_lh" =>[],
                        "1_4_lh" =>[],
                        "1_5_lh" =>[],
                        "1_6_lh" =>[],
                        "2_3_lh" =>[],
                        "2_4_lh" =>[],
                        "2_5_lh" =>[],
                        "2_6_lh" =>[],
                        "3_4_lh" =>[],
                        "3_5_lh" =>[],
                        "3_6_lh" =>[],
                        "4_5_lh" =>[],
                        "4_6_lh" =>[],
                        "5_6_lh" =>[],
                    ];
                    $data = $arr;

                    for ($num = 0; $num < count($list); $num++) {
                        $result = $this->getLotteryResults($lotteryType, array('lottery_result'=>$list[$num]['lottery_result']));
                        foreach ($result as $value_1) {
                            if (in_array($value_1,['特码A_大','特码A_小'])) {
                                $arr["special_dx"][] = str_replace("A","",$value_1);
                            }
                            if (in_array($value_1,['特码A_单','特码A_双'])) {
                                $arr["special_ds"][] = str_replace("A","",$value_1);
                            }
                            if (in_array($value_1,['特码A_红波','特码A_蓝波','特码A_绿波'])) {
                                $arr["special_bs"][] = str_replace("A","",$value_1);
                            }
                            if (in_array($value_1,['特码A_尾大','特码A_尾小'])) {
                                $arr["special_wdx"][] = str_replace("A","",$value_1);
                            }
                            if (in_array($value_1,['特码A_合大','特码A_合小'])) {
                                $arr["special_hdx"][] = str_replace("A","",$value_1);
                            }
                            if (in_array($value_1,['特码A_合单','特码A_合双'])) {
                                $arr["special_hds"][] = str_replace("A","",$value_1);
                            }
                            if (in_array($value_1,['特码A_家禽','特码A_野兽'])) {
                                $arr["special_jqys"][] = str_replace("A","",$value_1);
                            }
                            if (in_array($value_1,['正码A_总和大','正码A_总和小'])) {
                                $arr["just_zhdx"][] = str_replace("A","",$value_1);
                            }
                            if (in_array($value_1,['正码A_总和单','正码A_总和双'])) {
                                $arr["just_zhds"][] = str_replace("A","",$value_1);
                            }
                            if (in_array($value_1,['正码A_龙','正码A_虎'])) {
                                $arr["just_lh"][] = str_replace("A","",$value_1);
                            }
                            if (in_array($value_1,['正码A_总尾大','正码A_总尾小'])) {
                                $arr["just_wdx"][] = str_replace("A","",$value_1);
                            }

                            if (in_array($value_1,['正1特_尾大','正1特_尾小'])) {
                                $arr["just_1_special_wdx"][] = $value_1;
                            }
                            if (in_array($value_1,['正1特_大','正1特_小'])) {
                                $arr["just_1_special_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['正1特_单','正1特_双'])) {
                                $arr["just_1_special_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['正1特_合单','正1特_合双'])) {
                                $arr["just_1_special_hds"][] = $value_1;
                            }
                            if (in_array($value_1,['正1特_合大','正1特_合小'])) {
                                $arr["just_1_special_hds"][] = $value_1;
                            }
                            if (in_array($value_1,['正1特_红波','正1特_蓝波','正1特_绿波'])) {
                                $arr["just_1_special_bs"][] = $value_1;
                            }

                            if (in_array($value_1,['正2特_尾大','正2特_尾小'])) {
                                $arr["just_2_special_wdx"][] = $value_1;
                            }
                            if (in_array($value_1,['正2特_大','正2特_小'])) {
                                $arr["just_2_special_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['正2特_单','正2特_双'])) {
                                $arr["just_2_special_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['正2特_合单','正2特_合双'])) {
                                $arr["just_2_special_hds"][] = $value_1;
                            }
                            if (in_array($value_1,['正2特_合大','正2特_合小'])) {
                                $arr["just_2_special_hdx"][] = $value_1;
                            }
                            if (in_array($value_1,['正2特_红波','正2特_蓝波','正2特_绿波'])) {
                                $arr["just_2_special_bs"][] = $value_1;
                            }

                            if (in_array($value_1,['正3特_尾大','正3特_尾小'])) {
                                $arr["just_3_special_wdx"][] = $value_1;
                            }
                            if (in_array($value_1,['正3特_大','正3特_小'])) {
                                $arr["just_3_special_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['正3特_单','正3特_双'])) {
                                $arr["just_3_special_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['正3特_合单','正3特_合双'])) {
                                $arr["just_3_special_hds"][] = $value_1;
                            }
                            if (in_array($value_1,['正3特_合大','正3特_合小'])) {
                                $arr["just_3_special_hdx"][] = $value_1;
                            }
                            if (in_array($value_1,['正3特_红波','正3特_蓝波','正3特_绿波'])) {
                                $arr["just_3_special_bs"][] = $value_1;
                            }

                            if (in_array($value_1,['正4特_尾大','正4特_尾小'])) {
                                $arr["just_4_special_wdx"][] = $value_1;
                            }
                            if (in_array($value_1,['正4特_大','正4特_小'])) {
                                $arr["just_4_special_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['正4特_单','正4特_双'])) {
                                $arr["just_4_special_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['正4特_合单','正4特_合双'])) {
                                $arr["just_4_special_hds"][] = $value_1;
                            }
                            if (in_array($value_1,['正4特_合大','正4特_合小'])) {
                                $arr["just_4_special_hdx"][] = $value_1;
                            }
                            if (in_array($value_1,['正4特_红波','正4特_蓝波','正4特_绿波'])) {
                                $arr["just_4_special_bs"][] = $value_1;
                            }

                            if (in_array($value_1,['正5特_尾大','正5特_尾小'])) {
                                $arr["just_5_special_wdx"][] = $value_1;
                            }
                            if (in_array($value_1,['正5特_大','正5特_小'])) {
                                $arr["just_5_special_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['正5特_单','正5特_双'])) {
                                $arr["just_5_special_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['正5特_合单','正5特_合双'])) {
                                $arr["just_5_special_hds"][] = $value_1;
                            }
                            if (in_array($value_1,['正5特_合大','正5特_合小'])) {
                                $arr["just_5_special_hdx"][] = $value_1;
                            }
                            if (in_array($value_1,['正5特_红波','正5特_蓝波','正5特_绿波'])) {
                                $arr["just_5_special_bs"][] = $value_1;
                            }

                            if (in_array($value_1,['正6特_尾大','正6特_尾小'])) {
                                $arr["just_6_special_wdx"][] = $value_1;
                            }
                            if (in_array($value_1,['正6特_大','正6特_小'])) {
                                $arr["just_6_special_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['正6特_单','正6特_双'])) {
                                $arr["just_6_special_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['正6特_合单','正6特_合双'])) {
                                $arr["just_6_special_hds"][] = $value_1;
                            }
                            if (in_array($value_1,['正6特_合大','正6特_合小'])) {
                                $arr["just_6_special_hdx"][] = $value_1;
                            }
                            if (in_array($value_1,['正6特_红波','正6特_蓝波','正6特_绿波'])) {
                                $arr["just_6_special_bs"][] = $value_1;
                            }

                            if (in_array($value_1,['1-2球_龙','1-2球_虎'])) {
                                $arr["1_2_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['1-3球_龙','1-3球_虎'])) {
                                $arr["1_3_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['1-4球_龙','1-4球_虎'])) {
                                $arr["1_4_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['1-5球_龙','1-5球_虎'])) {
                                $arr["1_5_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['1-6球_龙','1-6球_虎'])) {
                                $arr["1_6_lh"][] = $value_1;
                            }

                            if (in_array($value_1,['2-3球_龙','2-3球_虎'])) {
                                $arr["2_3_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['2-4球_龙','2-4球_虎'])) {
                                $arr["2_4_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['2-5球_龙','2-5球_虎'])) {
                                $arr["2_5_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['2-6球_龙','2-6球_虎'])) {
                                $arr["2_6_lh"][] = $value_1;
                            }

                            if (in_array($value_1,['3-4球_龙','3-4球_虎'])) {
                                $arr["3_4_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['3-5球_龙','3-5球_虎'])) {
                                $arr["3_5_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['3-6球_龙','3-6球_虎'])) {
                                $arr["3_6_lh"][] = $value_1;
                            }

                            if (in_array($value_1,['4-5球_龙','4-5球_虎'])) {
                                $arr["4_5_lh"][] = $value_1;
                            }
                            if (in_array($value_1,['4-6球_龙','4-6球_虎'])) {
                                $arr["4_6_lh"][] = $value_1;
                            }

                            if (in_array($value_1,['5-6球_龙','5-6球_虎'])) {
                                $arr["5_6_lh"][] = $value_1;
                            }
                        }
                    }

                    foreach ($arr as $key=>$val) {
                        foreach ($val as $va) {
                            if (stripos($data[$key][count($data[$key])-1],$va) === false) {
                                $data[$key][] = $va;
                            } else {
                                $data[$key][count($data[$key])-1] = $data[$key][count($data[$key])-1].",".$va;
                            }
                        }
                    }
                    foreach ($data as $key=>$val) {
                        if (count($val) > 30) {
                            $data[$key] = array_slice($data[$key],count($data[$key])-30,30);
                        }
                    }

                    $array = array_shift($list);
                    $post_data = [
                        'value' => encode($data),
                        'lottery_type' => $lotteryType,
                        'issue' => $array['issue'],
                        'add_time' => time()
                    ];
                    $this->db->insert("#@_long_dragon", $post_data);

                } else {

                    $data = decode($rows['value']);
                    $sql = "select lottery_result, issue, id from #@_lhc where lottery_type = {$lotteryType} and issue = {$issue}";
                    $list = $this->db->getone($sql);
                    $result = $this->getLotteryResults($lotteryType, array('lottery_result'=>$list['lottery_result']));
                    foreach ($result as $value_1) {
                        if (in_array($value_1,['特码A_大','特码A_小'])) {
                            $arr["special_dx"] = str_replace("A","",$value_1);
                        }
                        if (in_array($value_1,['特码A_单','特码A_双'])) {
                            $arr["special_ds"] = str_replace("A","",$value_1);
                        }
                        if (in_array($value_1,['特码A_红波','特码A_蓝波','特码A_绿波'])) {
                            $arr["special_bs"] = str_replace("A","",$value_1);
                        }
                        if (in_array($value_1,['特码A_尾大','特码A_尾小'])) {
                            $arr["special_wdx"] = str_replace("A","",$value_1);
                        }
                        if (in_array($value_1,['特码A_合大','特码A_合小'])) {
                            $arr["special_hdx"] = str_replace("A","",$value_1);
                        }
                        if (in_array($value_1,['特码A_合单','特码A_合双'])) {
                            $arr["special_hds"] = str_replace("A","",$value_1);
                        }
                        if (in_array($value_1,['特码A_家禽','特码A_野兽'])) {
                            $arr["special_jqys"] = str_replace("A","",$value_1);
                        }
                        if (in_array($value_1,['正码A_总和大','正码A_总和小'])) {
                            $arr["just_zhdx"] = str_replace("A","",$value_1);
                        }
                        if (in_array($value_1,['正码A_总和单','正码A_总和双'])) {
                            $arr["just_zhds"] = str_replace("A","",$value_1);
                        }
                        if (in_array($value_1,['正码A_龙','正码A_虎'])) {
                            $arr["just_lh"] = str_replace("A","",$value_1);
                        }
                        if (in_array($value_1,['正码A_总尾大','正码A_总尾小'])) {
                            $arr["just_wdx"] = str_replace("A","",$value_1);
                        }

                        if (in_array($value_1,['正1特_尾大','正1特_尾小'])) {
                            $arr["just_1_special_wdx"] = $value_1;
                        }
                        if (in_array($value_1,['正1特_大','正1特_小'])) {
                            $arr["just_1_special_dx"] = $value_1;
                        }
                        if (in_array($value_1,['正1特_单','正1特_双'])) {
                            $arr["just_1_special_ds"] = $value_1;
                        }
                        if (in_array($value_1,['正1特_合单','正1特_合双'])) {
                            $arr["just_1_special_hds"] = $value_1;
                        }
                        if (in_array($value_1,['正1特_合大','正1特_合小'])) {
                            $arr["just_1_special_hdx"] = $value_1;
                        }
                        if (in_array($value_1,['正1特_红波','正1特_蓝波','正1特_绿波'])) {
                            $arr["just_1_special_bs"] = $value_1;
                        }

                        if (in_array($value_1,['正2特_尾大','正2特_尾小'])) {
                            $arr["just_2_special_wdx"] = $value_1;
                        }
                        if (in_array($value_1,['正2特_大','正2特_小'])) {
                            $arr["just_2_special_dx"] = $value_1;
                        }
                        if (in_array($value_1,['正2特_单','正2特_双'])) {
                            $arr["just_2_special_ds"] = $value_1;
                        }
                        if (in_array($value_1,['正2特_合单','正2特_合双'])) {
                            $arr["just_2_special_hds"] = $value_1;
                        }
                        if (in_array($value_1,['正2特_合大','正2特_合小'])) {
                            $arr["just_2_special_hdx"] = $value_1;
                        }
                        if (in_array($value_1,['正2特_红波','正2特_蓝波','正2特_绿波'])) {
                            $arr["just_2_special_bs"] = $value_1;
                        }

                        if (in_array($value_1,['正3特_尾大','正3特_尾小'])) {
                            $arr["just_3_special_wdx"] = $value_1;
                        }
                        if (in_array($value_1,['正3特_大','正3特_小'])) {
                            $arr["just_3_special_dx"] = $value_1;
                        }
                        if (in_array($value_1,['正3特_单','正3特_双'])) {
                            $arr["just_3_special_ds"] = $value_1;
                        }
                        if (in_array($value_1,['正3特_合单','正3特_合双'])) {
                            $arr["just_3_special_hds"] = $value_1;
                        }
                        if (in_array($value_1,['正3特_合大','正3特_合小'])) {
                            $arr["just_3_special_hdx"] = $value_1;
                        }
                        if (in_array($value_1,['正3特_红波','正3特_蓝波','正3特_绿波'])) {
                            $arr["just_3_special_bs"] = $value_1;
                        }

                        if (in_array($value_1,['正4特_尾大','正4特_尾小'])) {
                            $arr["just_4_special_wdx"] = $value_1;
                        }
                        if (in_array($value_1,['正4特_大','正4特_小'])) {
                            $arr["just_4_special_dx"] = $value_1;
                        }
                        if (in_array($value_1,['正4特_单','正4特_双'])) {
                            $arr["just_4_special_ds"] = $value_1;
                        }
                        if (in_array($value_1,['正4特_合单','正4特_合双'])) {
                            $arr["just_4_special_hds"] = $value_1;
                        }
                        if (in_array($value_1,['正4特_合大','正4特_合小'])) {
                            $arr["just_4_special_hdx"] = $value_1;
                        }
                        if (in_array($value_1,['正4特_红波','正4特_蓝波','正4特_绿波'])) {
                            $arr["just_4_special_bs"] = $value_1;
                        }

                        if (in_array($value_1,['正5特_尾大','正5特_尾小'])) {
                            $arr["just_5_special_wdx"] = $value_1;
                        }
                        if (in_array($value_1,['正5特_大','正5特_小'])) {
                            $arr["just_5_special_dx"] = $value_1;
                        }
                        if (in_array($value_1,['正5特_单','正5特_双'])) {
                            $arr["just_5_special_ds"] = $value_1;
                        }
                        if (in_array($value_1,['正5特_合单','正5特_合双'])) {
                            $arr["just_5_special_hds"] = $value_1;
                        }
                        if (in_array($value_1,['正5特_合大','正5特_合小'])) {
                            $arr["just_5_special_hdx"] = $value_1;
                        }
                        if (in_array($value_1,['正5特_红波','正5特_蓝波','正5特_绿波'])) {
                            $arr["just_5_special_bs"] = $value_1;
                        }

                        if (in_array($value_1,['正6特_尾大','正6特_尾小'])) {
                            $arr["just_6_special_wdx"] = $value_1;
                        }
                        if (in_array($value_1,['正6特_大','正6特_小'])) {
                            $arr["just_6_special_dx"] = $value_1;
                        }
                        if (in_array($value_1,['正6特_单','正6特_双'])) {
                            $arr["just_6_special_ds"] = $value_1;
                        }
                        if (in_array($value_1,['正6特_合单','正6特_合双'])) {
                            $arr["just_6_special_hds"] = $value_1;
                        }
                        if (in_array($value_1,['正6特_合大','正6特_合小'])) {
                            $arr["just_6_special_hdx"] = $value_1;
                        }
                        if (in_array($value_1,['正6特_红波','正6特_蓝波','正6特_绿波'])) {
                            $arr["just_6_special_bs"] = $value_1;
                        }

                        if (in_array($value_1,['1-2球_龙','1-2球_虎'])) {
                            $arr["1_2_lh"] = $value_1;
                        }
                        if (in_array($value_1,['1-3球_龙','1-3球_虎'])) {
                            $arr["1_3_lh"] = $value_1;
                        }
                        if (in_array($value_1,['1-4球_龙','1-4球_虎'])) {
                            $arr["1_4_lh"] = $value_1;
                        }
                        if (in_array($value_1,['1-5球_龙','1-5球_虎'])) {
                            $arr["1_5_lh"] = $value_1;
                        }
                        if (in_array($value_1,['1-6球_龙','1-6球_虎'])) {
                            $arr["1_6_lh"] = $value_1;
                        }

                        if (in_array($value_1,['2-3球_龙','2-3球_虎'])) {
                            $arr["2_3_lh"] = $value_1;
                        }
                        if (in_array($value_1,['2-4球_龙','2-4球_虎'])) {
                            $arr["2_4_lh"] = $value_1;
                        }
                        if (in_array($value_1,['2-5球_龙','2-5球_虎'])) {
                            $arr["2_5_lh"] = $value_1;
                        }
                        if (in_array($value_1,['2-6球_龙','2-6球_虎'])) {
                            $arr["2_6_lh"] = $value_1;
                        }

                        if (in_array($value_1,['3-4球_龙','3-4球_虎'])) {
                            $arr["3_4_lh"] = $value_1;
                        }
                        if (in_array($value_1,['3-5球_龙','3-5球_虎'])) {
                            $arr["3_5_lh"] = $value_1;
                        }
                        if (in_array($value_1,['3-6球_龙','3-6球_虎'])) {
                            $arr["3_6_lh"] = $value_1;
                        }

                        if (in_array($value_1,['4-5球_龙','4-5球_虎'])) {
                            $arr["4_5_lh"] = $value_1;
                        }
                        if (in_array($value_1,['4-6球_龙','4-6球_虎'])) {
                            $arr["4_6_lh"] = $value_1;
                        }

                        if (in_array($value_1,['5-6球_龙','5-6球_虎'])) {
                            $arr["5_6_lh"] = $value_1;
                        }
                    }

                    foreach ($data as $key=>$val) {
                        if (stripos($data[$key][count($data[$key])-1],$arr[$key]) === false) {
                            $data[$key][] = $arr[$key];
                        } else {
                            $data[$key][count($data[$key])-1] = $data[$key][count($data[$key])-1].",".$arr[$key];
                        }
                    }

                    foreach ($data as $key=>$val) {
                        if (count($val) > 30) {
                            $data[$key] = array_slice($data[$key],count($data[$key])-30,30);
                        }
                    }

                    if ($rows['issue'] != $issue && $rows['lottery_type'] == $lotteryType) {
                        $post_data = [
                            'value' => encode($data),
                            'issue' => $issue,
                            'update_time' => time()
                        ];
                        $this->db->update("#@_long_dragon", $post_data, ['issue'=>$rows['issue'], 'lottery_type'=>$lotteryType]);
                    }

                }

            } elseif(in_array($lotteryType,['10'])){

                //百人牛牛
                if (empty($rows)) {

                    $sql = "select lottery_result, issue, id from #@_nn where lottery_time between {$start} and {$end} and lottery_type = {$lotteryType} order by lottery_time DESC";
                    $list = $this->db->getall($sql);

                    $arr = [
                        "sf" => [],//胜负
                        "lh" => [],//龙虎
                        "gp" => [],//有无公牌
                        "dx" => [],//总和大小
                        "ds" => [],//总和单双

                        "first_dx" => [],//第一张大小
                        "first_ds" => [],//第一张单双
                        "first_hs" => [],//第一张花色

                        "second_dx" => [],//第二张大小
                        "second_ds" => [],//第二张单双
                        "second_hs" => [],//第二张花色

                        "third_dx" => [],//第三张大小
                        "third_ds" => [],//第三张单双
                        "third_hs" => [],//第三张花色

                        "fourth_dx" => [],//第四张大小
                        "fourth_ds" => [],//第四张单双
                        "fourth_hs" => [],//第四张花色

                        "fifth_dx" => [],//第五张大小
                        "fifth_ds" => [],//第五张单双
                        "fifth_hs" => [],//第五张花色


                    ];
                    $data = $arr;

                    foreach ($list as $key=>$value) {

                        $result = $this->getLotteryResults($lotteryType, array('lottery_result'=>$value['lottery_result']));

                        foreach ($result as $value_1) {

                            if (in_array($value_1,['红方胜','蓝方胜'])) {
                                $arr["sf"][] = $value_1;
                            }
                            if (in_array($value_1,['龙','虎'])) {
                                $arr["lh"][] = $value_1;
                            }
                            if (in_array($value_1,['有公牌','无公牌'])) {
                                $arr["gp"][] = $value_1;
                            }
                            if (in_array($value_1,['大','小'])) {
                                $arr["dx"][] = $value_1;
                            }
                            if (in_array($value_1,['单','双'])) {
                                $arr["ds"][] = $value_1;
                            }

                            if (in_array($value_1,['第一张_大','第一张_小'])) {
                                $arr["first_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第一张_单','第一张_双'])) {
                                $arr["first_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第一张_黑桃','第一张_红心','第一张_梅花','第一张_方块'])) {
                                $arr["first_hs"][] = $value_1;
                            }

                            if (in_array($value_1,['第二张_大','第二张_小'])) {
                                $arr["second_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第二张_单','第二张_双'])) {
                                $arr["second_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第二张_黑桃','第二张_红心','第二张_梅花','第二张_方块'])) {
                                $arr["second_hs"][] = $value_1;
                            }

                            if (in_array($value_1,['第三张_大','第三张_小'])) {
                                $arr["third_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第三张_单','第三张_双'])) {
                                $arr["third_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第三张_黑桃','第三张_红心','第三张_梅花','第三张_方块'])) {
                                $arr["third_hs"][] = $value_1;
                            }

                            if (in_array($value_1,['第四张_大','第四张_小'])) {
                                $arr["fourth_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第四张_单','第四张_双'])) {
                                $arr["fourth_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第四张_黑桃','第四张_红心','第四张_梅花','第四张_方块'])) {
                                $arr["fourth_hs"][] = $value_1;
                            }

                            if (in_array($value_1,['第五张_大','第五张_小'])) {
                                $arr["fifth_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第五张_单','第五张_双'])) {
                                $arr["fifth_ds"][] = $value_1;
                            }
                            if (in_array($value_1,['第五张_黑桃','第五张_红心','第五张_梅花','第五张_方块'])) {
                                $arr["fifth_hs"][] = $value_1;
                            }
                        }
                    }

                    foreach ($arr as $key=>$val) {
                        foreach ($val as $va) {
                            if (stripos($data[$key][count($data[$key])-1],$va) === false) {
                                $data[$key][] = $va;
                            } else {
                                $data[$key][count($data[$key])-1] = $data[$key][count($data[$key])-1].",".$va;
                            }
                        }
                    }
                    foreach ($data as $key=>$val) {
                        if (count($val) > 30) {
                            $data[$key] = array_slice($data[$key],count($data[$key])-30,30);
                        }
                    }

                    $array = array_shift($list);
                    $post_data = [
                        'value' => encode($data),
                        'lottery_type' => $lotteryType,
                        'issue' => $array['issue'],
                        'add_time' => time()
                    ];
                    $this->db->insert("#@_long_dragon", $post_data);

                } else {

                    $data = decode($rows['value']);
                    $sql = "select lottery_result, issue, id from #@_nn where lottery_type = {$lotteryType} and issue = {$issue}";
                    $list = $this->db->getone($sql);
                    $result = $this->getLotteryResults($lotteryType, array('lottery_result'=>$list['lottery_result']));
                    foreach ($result as $value_1) {

                        if (in_array($value_1,['红方胜','蓝方胜'])) {
                            $arr["sf"] = $value_1;
                        }
                        if (in_array($value_1,['龙','虎'])) {
                            $arr["lh"] = $value_1;
                        }
                        if (in_array($value_1,['有公牌','无公牌'])) {
                            $arr["gp"] = $value_1;
                        }
                        if (in_array($value_1,['大','小'])) {
                            $arr["dx"] = $value_1;
                        }
                        if (in_array($value_1,['单','双'])) {
                            $arr["ds"] = $value_1;
                        }

                        if (in_array($value_1,['第一张_大','第一张_小'])) {
                            $arr["first_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第一张_单','第一张_双'])) {
                            $arr["first_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第一张_黑桃','第一张_红心','第一张_梅花','第一张_方块'])) {
                            $arr["first_hs"] = $value_1;
                        }

                        if (in_array($value_1,['第二张_大','第二张_小'])) {
                            $arr["second_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第二张_单','第二张_双'])) {
                            $arr["second_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第二张_黑桃','第二张_红心','第二张_梅花','第二张_方块'])) {
                            $arr["second_hs"] = $value_1;
                        }

                        if (in_array($value_1,['第三张_大','第三张_小'])) {
                            $arr["third_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第三张_单','第三张_双'])) {
                            $arr["third_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第三张_黑桃','第三张_红心','第三张_梅花','第三张_方块'])) {
                            $arr["third_hs"] = $value_1;
                        }

                        if (in_array($value_1,['第四张_大','第四张_小'])) {
                            $arr["fourth_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第四张_单','第四张_双'])) {
                            $arr["fourth_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第四张_黑桃','第四张_红心','第四张_梅花','第四张_方块'])) {
                            $arr["fourth_hs"] = $value_1;
                        }

                        if (in_array($value_1,['第五张_大','第五张_小'])) {
                            $arr["fifth_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第五张_单','第五张_双'])) {
                            $arr["fifth_ds"] = $value_1;
                        }
                        if (in_array($value_1,['第五张_黑桃','第五张_红心','第五张_梅花','第五张_方块'])) {
                            $arr["fifth_hs"] = $value_1;
                        }
                    }
                    foreach ($data as $key=>$val) {
                        if (stripos($data[$key][count($data[$key])-1],$arr[$key]) === false) {
                            $data[$key][] = $arr[$key];
                        } else {
                            $data[$key][count($data[$key])-1] = $data[$key][count($data[$key])-1].",".$arr[$key];
                        }
                    }

                    foreach ($data as $key=>$val) {
                        if (count($val) > 30) {
                            $data[$key] = array_slice($data[$key],count($data[$key])-30,30);
                        }
                    }

                    if ($rows['issue'] != $issue && $rows['lottery_type'] == $lotteryType) {
                        $post_data = [
                            'value' => encode($data),
                            'issue' => $issue,
                            'update_time' => time()
                        ];
                        $this->db->update("#@_long_dragon", $post_data, ['issue'=>$rows['issue'], 'lottery_type'=>$lotteryType]);
                    }
                }

            } elseif(in_array($lotteryType,['13'])){

                //骰宝
                if (empty($rows)) {

                    $sql = "select lottery_result, issue, id from #@_sb where lottery_time between {$start} and {$end} and lottery_type = {$lotteryType} order by lottery_time DESC";
                    $list = $this->db->getall($sql);

                    $arr = [
                        "dx" => [],//总和大小
                        "ds" => [],//总和单双
                        "bz" => [],//豹子

                        "first_dx" => [],//第一骰大小
                        "first_ds" => [],//第一骰单双

                        "second_dx" => [],//第二骰大小
                        "second_ds" => [],//第二骰单双

                        "third_dx" => [],//第三骰大小
                        "third_ds" => [],//第三骰单双

                    ];
                    $data = $arr;

                    foreach ($list as $key=>$value) {

                        $result = $this->getLotteryResults($lotteryType, array('lottery_result'=>$value['lottery_result']));

                        foreach ($result as $value_1) {

                            if (in_array($value_1,['总和_大','总和_小'])) {
                                $arr["dx"][] = $value_1;
                            }
                            if (in_array($value_1,['总和_单','总和_双'])) {
                                $arr["ds"][] = $value_1;
                            }
                            if (in_array($value_1,['豹子_1','豹子_2','豹子_3','豹子_4','豹子_5','豹子_6'])) {
                                $arr["bz"][] = $value_1;
                            }

                            if (in_array($value_1,['第一骰_大','第一骰_小'])) {
                                $arr["first_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第一骰_单','第一骰_双'])) {
                                $arr["first_ds"][] = $value_1;
                            }

                            if (in_array($value_1,['第二骰_大','第二骰_小'])) {
                                $arr["second_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第二骰_单','第二骰_双'])) {
                                $arr["second_ds"][] = $value_1;
                            }

                            if (in_array($value_1,['第三骰_大','第三骰_小'])) {
                                $arr["third_dx"][] = $value_1;
                            }
                            if (in_array($value_1,['第三骰_单','第三骰_双'])) {
                                $arr["third_ds"][] = $value_1;
                            }
                        }
                    }

                    foreach ($arr as $key=>$val) {
                        foreach ($val as $va) {
                            if (stripos($data[$key][count($data[$key])-1],$va) === false) {
                                $data[$key][] = $va;
                            } else {
                                $data[$key][count($data[$key])-1] = $data[$key][count($data[$key])-1].",".$va;
                            }
                        }
                    }
                    foreach ($data as $key=>$val) {
                        if (count($val) > 30) {
                            $data[$key] = array_slice($data[$key],count($data[$key])-30,30);
                        }
                    }

                    $array = array_shift($list);
                    $post_data = [
                        'value' => encode($data),
                        'lottery_type' => $lotteryType,
                        'issue' => $array['issue'],
                        'add_time' => time()
                    ];
                    $this->db->insert("#@_long_dragon", $post_data);
                } else {

                    $data = decode($rows['value']);
                    $sql = "select lottery_result, issue, id from #@_sb where lottery_type = {$lotteryType} and issue = {$issue}";
                    $list = $this->db->getone($sql);
                    $result = $this->getLotteryResults($lotteryType, array('lottery_result'=>$list['lottery_result']));

                    foreach ($result as $value_1) {

                        if (in_array($value_1,['总和_大','总和_小'])) {
                            $arr["dx"] = $value_1;
                        }
                        if (in_array($value_1,['总和_单','总和_双'])) {
                            $arr["ds"] = $value_1;
                        }
                        if (in_array($value_1,['豹子_1','豹子_2','豹子_3','豹子_4','豹子_5','豹子_6'])) {
                            $arr["bz"] = $value_1;
                        }

                        if (in_array($value_1,['第一骰_大','第一骰_小'])) {
                            $arr["first_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第一骰_单','第一骰_双'])) {
                            $arr["first_ds"] = $value_1;
                        }

                        if (in_array($value_1,['第二骰_大','第二骰_小'])) {
                            $arr["second_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第二骰_单','第二骰_双'])) {
                            $arr["second_ds"] = $value_1;
                        }

                        if (in_array($value_1,['第三骰_大','第三骰_小'])) {
                            $arr["third_dx"] = $value_1;
                        }
                        if (in_array($value_1,['第三骰_单','第三骰_双'])) {
                            $arr["third_ds"] = $value_1;
                        }
                    }

                    foreach ($data as $key=>$val) {
                        if (stripos($data[$key][count($data[$key])-1],$arr[$key]) === false) {
                            if (!empty($arr[$key])) {
                                $data[$key][] = $arr[$key];
                            }
                        } else {
                            $data[$key][count($data[$key])-1] = $data[$key][count($data[$key])-1].",".$arr[$key];
                        }
                    }
                    foreach ($data as $key=>$val) {
                        if (count($val) > 30) {
                            $data[$key] = array_slice($data[$key],count($data[$key])-30,30);
                        }
                    }

                    if ($rows['issue'] != $issue && $rows['lottery_type'] == $lotteryType) {
                        $post_data = [
                            'value' => encode($data),
                            'issue' => $issue,
                            'update_time' => time()
                        ];
                        $this->db->update("#@_long_dragon", $post_data, ['issue'=>$rows['issue'], 'lottery_type'=>$lotteryType]);
                    }

                }
            }

        }

    }

    /**
     * 获取长龙结果
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-25 21:01:47
     * @param lotteryType int 彩种id
     * @return void
     */
    public function getLongDragon($lottery_type){
        $sql = "select value from #@_long_dragon where lottery_type = '".$lottery_type."'";
        $res = $this->db->getone($sql);
        $result = decode($res['value']);
        $rows = [];
        foreach ($result as $value) {
            $array = array_pop($value);
            $array = explode(",",$array);
            $count = count($array);
            if ($count >= 2) {
                $tmp['way'] = $array[0];
                $tmp['num'] = $count;
                $rows[] = $tmp;
            }
        }
        return $rows;
    }
}