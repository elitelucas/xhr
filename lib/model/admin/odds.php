<?php



/**

 * Created by PhpStorm.

 * User: wangrui

 * Date: 2016/11/18

 * Time: 22:27

 * desc: 用户邦定银行信息

 */

!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'model' . DS . 'common.php');



class OddsModel extends CommonModel {



    protected $table = '#@_odds';

    protected $table1 = '#@_config';

    protected $table2 = '#@_lottery_type';

    protected $table3 = '#@_user_group';

    protected $table4 = '#@_user';

    protected $table5 = '#@_orders';

    protected $table6 = '#@_agent_group';

    protected $table7 = '#@_account_log';

    protected $table8 = '#@_account';

    protected $table9 = '#@_room';



    private $admin;



    //逆向投注开关

    public function reverse() {

        return $this->db->getone("select value from " . $this->table1 . " where nid = 'reverse'");

    }



    //逆向投注开关设置

    public function upReverse($data, $where) {

        return $this->db->update($this->table1, $data, $where);

    }



    //赔率列表

    public function odds($where) {

        return $this->db->getall("select * from " . $this->table . " where lottery_type = {$where['lottery_type']} order by sort");

    }



    //特殊玩法列表

    public function specialWay() {

        return $this->db->getone("select value from " . $this->table1 . " where nid='specialWay'");

    }



    //特殊玩法开关

    public function upState($data) {

        $rt = $this->db->getone("select config from " . $this->table2 . " where id={$data['id']}");

        $config = json_decode($rt['config'], true);

        $config['special_way'] = $data['special_way'];

        $result = $this->db->update($this->table2, array("config" => json_encode($config)), array("id" => $data['id']));

        $this->refreshRedis("allroom","all");

        return  $result;

    }



    //某彩种的特殊玩法设置

    public function specialSet($id) {

        $rt = $this->db->getone("select config from " . $this->table2 . " where id={$id}");

        $config = json_decode($rt['config'], true);

        return $config;

    }



    //特殊玩法设置

    public function doSpecial($data, $where) {

        return $this->db->update($this->table1, $data, $where);

    }



    //彩种列表

    public function lottyList() {

        $sql = "select id,name from " . $this->table2;

        return $this->db->getall($sql);

    }



    //赔率修改

    public function doOdds($data, $where) {

        return $this->db->update($this->table, $data, $where);

    }



    //个人账户信息

    public function unAccount($id) {

        if(!empty(C('db_port'))) { //使用mycat时 查主库数据

            $sql = "/*#mycat:db_type=master*/ select * from " . $this->table8 . " where user_id = {$id} LIMIT 1 FOR UPDATE";

        }else{

            $sql = "select * from " . $this->table8 . " where user_id = {$id} LIMIT 1 FOR UPDATE";

        }

        return $this->db->getone($sql);

    }



    //返水设置房间列表

    public function roomBack() {

        return $this->db->getall("select room.title,room.id,type.name,room.lottery_type,backRate from " . $this->table9 . " as room left join " . $this->table2 . " as type on room.lottery_type = type.id"); //自身反水100011   会员反水100012

    }



    //返水类型

    public function typeBack() {

        $rt = $this->db->getone("select value from " . $this->table1 . " where nid='typeBack'");

        return $rt['value'];

    }



    //返水类型设置

    public function doBackType($data, $where) {

        return $this->db->update($this->table1, $data, $where);

    }



    //直属返水设置

    public function back() {

        $rt = $this->db->getone("select value from " . $this->table1 . " where nid = '100012'");

        return $rt['value'];

    }



    //返水比率设置

    public function backContent($data, $where) {

        $rt = $this->db->getone("select backRate from " . $this->table9 . " where id = {$where['id']}");

        lg('back',var_export(['$rt'=>$rt],1));

        $value = json_decode($rt['backRate'], true);

        lg('back',var_export(['前$value'=>$value],1));

        array_push($value,$data);

        lg('back',var_export(['后$value'=>$value],1));

        $up = addslashes(json_encode($value));

        lg('back',var_export(['$up'=>$up],1));

        return $this->db->update($this->table9, array("backRate" => $up), $where);

    }



    //返水比率列表

    public function backList($id) {

        $rt = $this->db->getone("select * from " . $this->table9 . " where id = {$id}");

        $array = array();

        $array['list'] = json_decode($rt['backRate'], true);

        $array['title'] = $rt['title'];

        $array['id'] = $rt['id'];

        return $array;

    }



    //返水比率删除

    public function backDelete($i, $id,$type) {

        $rt = $this->db->getone("select backRate from " . $this->table9 . " where id = {$id}");

        $array = json_decode($rt['backRate'], true);

        $ii=0;

        foreach ($array as $k=>$v){

            if($v['type']==$type){

                if($ii==$i){

                    unset($array[$k]);

                }

                $ii++;

            }

        }

//        array_splice($array, $i, 1);

        $where = array("id" => $id);

        $data = array("backRate" => addslashes(json_encode($array)));

        return $this->db->update($this->table9, $data, $where);

    }



    //回水设置

    public function doBack($data, $where) {

        return $this->db->update($this->table1, $data, $where);

    }



    //投注限额列表

    public function bet() {

        return $this->db->getall("select * from " . $this->table3);

    }



    //投注限额修改

    public function addBet($data, $where) {

        return $this->db->update($this->table3, $data, $where);

    }



    //获取投注限额信息根据ID

    public function betById($where) {

        return $this->db->getone("select * from " . $this->table3 . " where id = {$where['id']}");

    }



    //获取赔率规则

    public function rule() {

        return $this->db->getone("select value from " . $this->table1 . " where nid = 'oddsRule'");

    }



    //修改赔率规则

    public function upRule($data, $where) {

        return $this->db->update($this->table1, $data, $where);

    }



    //获取用户名称根据ID

    public function getUname($where) {

        $userId = $this->db->getone("select username from " . $this->table4 . " where id = {$where['id']}");

        return $userId['username']; //用户username     

    }



    //某天需要返水的用户ID 

    public function idList($where) {

        $s_time = strtotime($where['addtime'] . " 00:00:00");

        $e_time = strtotime($where['addtime'] . " 23:59:59");



        if ($where['account'] != "") {

            $sql = "select user_id from " . $this->table5 . " where addtime < {$e_time} and addtime > {$s_time} and is_backwater = 0 and state = 0 and user_id = "

                    . " (select id from " . $this->table4 . " where username = '{$where['account']}') group by user_id";

        } else {

            $sql = "select user_id from " . $this->table5 . " where addtime < {$e_time} and addtime > {$s_time} and is_backwater = 0 and state = 0 group by user_id";

        }

        return $this->db->getall($sql);

    }



    //返水列表

    public function calculate($where) {

        $sql = "select log.*,user.username from un_back_log as log "

                . " left join un_user as user on log.user_id = user.id "

                . " where log.addtime = '{$where['addtime']}'";

        $quick = $where["quick"];

        if($quick != "0"&&$quick !=""){

            switch ($quick){

                case 1:

                    $begin_time = strtotime(date("Y-m-d",strtotime("0 day")));

                    $end_time = $begin_time + 86399;

                    break;

                case 2:

                    $begin_time = strtotime(date("Y-m-d",strtotime("-1 day")));

                    $end_time = $begin_time + 86399;

                    break;

                case 3:

                    $begin_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));

                    $end_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;

                    break;

                case 4:

                    $begin_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));

                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;

                    break;

                case 5:

                    $begin_time = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));

                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));

                    break;

            }

            $sql = "select log.*,user.username from un_back_log as log "

                . " left join un_user as user on log.user_id = user.id "

                . " where log.addtime >= $begin_time AND log.addtime <= $end_time";

        }

        if ($where['account'] != '') {

            $sql .= " and user.username = '{$where['account']}'";

        }

        if(empty($where['back'])){

            $sql .= " and log.cntBack = '0'";

        }else{

            $sql .= " and log.cntBack > '0'";

        }

        

        if (!empty($where['state'])) {

            $sql .= " and log.state = {$where['state']}";

        }

        

        $sql .= " order by log.user_id ASC";


        return $this->db->getall($sql);

    }



    //一个人/一天的回水计算

    public function oneCalculate($where) {

        //返水方式

        $typeBack = $this->db->getone("select value from " . $this->table1 . " where nid = 'typeBack'");

        $typeBack = $typeBack['value'];



        $userId = $where['id']; //用户ID



        $s_time = strtotime($where['addtime'] . " 00:00:00");

        $e_time = strtotime($where['addtime'] . " 23:59:59");

        $selfSql = "select * from " . $this->table5 . " where user_id = $userId and addtime < {$e_time} and addtime > {$s_time} and is_backwater = 0 and state = 0";

        $selfCnt = $this->db->getall($selfSql);

        $rt = array(

            "selfMoney" => $selfCnt[0]['cnt'],

            "selfBack" => $selfCnt[0]['cnt'] * $selfRate / 100

        );

        return $rt;

    }



    //直属返水查询

    public function sonCalculate($where) {

        //自身返水比率 100011    直属会员/团队返水比率 100012

        $sonRate = $this->db->getone("select value from " . $this->table1 . " where nid = '100012'");

        $sonRate = $sonRate['value'];

        $userId = $where['id']; //用户ID



        $s_time = strtotime($where['addtime'] . " 00:00:00");

        $e_time = strtotime($where['addtime'] . " 23:59:59");

        $sonSql = "select sum(money) as cnt from " . $this->table5 . " where addtime < {$e_time} and addtime > {$s_time} and is_backwater = 0 and state = 0 and user_id in "

                . " (select id from " . $this->table4 . " where parent_id = $userId)";

        $moneyCnt = $this->db->getall($sonSql);



        $sonSql = "select count(*) from " . $this->table5 . " where addtime < {$e_time} and addtime > {$s_time} and is_backwater = 0 and state = 0 and user_id in "

                . " (select id from " . $this->table4 . " where parent_id = $userId)  group by user_id";

        $sonCnt = $this->db->getall($sonSql);





        return array(

            'sonMoney' => $moneyCnt[0]['cnt'],

            'sonCnt' => count($sonCnt),

            'sonBack' => $moneyCnt[0]['cnt'] * $sonRate / 100

        );

    }



    //团队返水查询

    public function teamCalculate($where) {

        $s_time = strtotime($where['addtime'] . " 00:00:00");

        $e_time = strtotime($where['addtime'] . " 23:59:59");

        $userId = $where['id']; //用户ID



        $teamSql = "select count(*) from " . $this->table5 . " where addtime < {$e_time} and addtime > {$s_time} and is_backwater = 0 and state = 0 and user_id in "

                . " ( SELECT u.id FROM un_user AS u WHERE FIND_IN_SET(u.id, getChildLst({$userId})) ) group by user_id";

        $teamCnt = $this->db->getall($teamSql);



        $moneySql = "select sum(money) as cnt from " . $this->table5 . " where addtime < {$e_time} and addtime > {$s_time} and is_backwater = 0 and state = 0 and user_id in "

                . " ( SELECT u.id FROM un_user AS u WHERE FIND_IN_SET(u.id, getChildLst({$userId})) )";

        $moneyCnt = $this->db->getall($moneySql);

        $moneyCnt = $moneyCnt[0]['cnt'];



        $rateSql = "select backwater from " . $this->table6 . " where lower < $moneyCnt and upper > $moneyCnt";

        $rateRt = $this->db->getone($rateSql);





        return array(

            'teamMoney' => $moneyCnt,

            'teamCnt' => count($teamCnt),

            'teamRate' => $rateRt['backwater'],

            'teamBack' => $rateRt['backwater'] * $moneyCnt / 100,

        );

    }



    //返水信息入库  事物提交

    public function transCalculate($log1, $log2, $log3, $log)

    {

        $rt1 = 1;

        $rt2 = 1;

        $rt3 = 1;

        $rt4 = 0;

        $rt5 = 0;





        try {

            if(!empty($log1['money']) && $log1['money'] > 0){

                $rt1 = $this->aadAccountLog($log1); //资金日志表

            }

    

            if(!empty($log2['money']) && $log2['money'] > 0){

                $rt2 = $this->aadAccountLog($log2); //资金日志表

            }



            if(!empty($log3['money']) && $log3['money'] != 0){

                $rt3 = $this->aadAccountLog($log3); //资金日志表

            }



            $sql = "update " . $this->table8 . " set money = money + {$log['add_money']} where user_id = {$log['user_id']}";

            $rt4 = $this->db->query($sql);   //个人账户表余额增加

            

            $sql = "update un_back_log set state = {$log['state']} , opertime = '{$log['opertime']}' where user_id = {$log['user_id']} and addtime = '{$log['addtime']}' and state = 1";

            $rt5 = $this->db->query($sql);   //返水表返水标记字段值、处理时间修改

    

            if ($rt1 && $rt2 && $rt3 && $rt4 && $rt5 ) {

//                $this->db->query("COMMIT");

                return 1;

            } else {

//                $this->db->query("ROLLBACK");

                return -1;

            }

        } catch (Exception $e) {

//            $this->db->query("ROLLBACK");



            return -1;

        }

    }



    //返水信息取消  事物提交

    public function transCalculates($log1, $log2, $log3, $log) {

        $this->db->query("START TRANSACTION");

//        $rt1 = $this->db->insert($this->table7, $log1); //资金日志表

//        $rt2 = $this->db->insert($this->table7, $log2); //资金日志表

//        $rt3 = $this->db->insert($this->table7, $log3); //资金日志表

        $rt1 = 1;

        if($log1['money'] > 0  && !empty($log1['money'])){

            $rt1 = $this->aadAccountLog($log1); //资金日志表

        }

        $rt2 = 1;



        if($log2['money'] > 0 && !empty($log2['money'])){

            $rt2 = $this->aadAccountLog($log2); //资金日志表

        }

        $rt3 = 1;

        if($log3['money'] > 0 && !empty($log3['money'])){

            $rt3 = $this->aadAccountLog($log3); //资金日志表

        }

        $s_time = strtotime($log['addtime'] . " 00:00:00");

        $e_time = strtotime($log['addtime'] . " 23:23:23");

        $sql = "update " . $this->table5 . " set is_backwater = 1 where user_id = {$log['user_id']} and addtime >{$s_time} and addtime < {$e_time}";

        $rt5 = $this->db->query($sql); //订单表返水标记



        if ($rt1 && $rt2  && $rt3  && $rt5 ) {

            $this->db->query("COMMIT");

            return 1;

        } else {

            $this->db->query("ROLLBACK");

            return -1;

        }

    }



    //生成订单号

    public function orderNo($str) {

        $min = pow(10, 2);

        $max = pow(10, 3) - 1;

        return $str . date('YmdHis', time()) . mt_rand($min, $max);  //当前时间加上3位随机数

    }



    public function setAdminUser($info) {

        $this->admin = $info;

    }



    //个人返水限制

    public function threeNoReturnLimit($data) {

        $arr1 = [1 => '组合', 2 => '极值', 3 => '单点', 4 => '特殊'];

        $type = $data['setType'] == 1?"个人返水限制":"团队返水限制";



        if(!empty($data['condition1'])) {

            $data['condition1']['xy28']['type'] = trim($data['condition1']['xy28']['type'],",");

            $data['condition1']['jnd28']['type'] = trim($data['condition1']['jnd28']['type'],",");

        }

        $postData[] = $data;

        $sql = "select * from ".$this->table1." where nid = 'three_no_return_limit'";

        $res = $this->db->getone($sql);

        if(empty($res)) {

            $tmp['nid'] = "three_no_return_limit";

            $tmp['name'] = '三无玩家返水限制';

            $tmp['desc'] = 'condition1：返水条件1；condition1->(xy28/jnd28)：限制类型 幸运28（1:组合 2:极值 3:单点 4:特殊） 加拿大28（5:组合 6:极值 7:单点 8:特殊） condition1->amount：百分比；condition2：返水条件2；setType：设置类型1：个人返水限制 2：团队返水限制';

            $tmp['value'] = json_encode($postData);

            $rows = $this->db->insert($this->table1, $tmp);



            $saveType = '新增';

        } else {

            $check = false;

            $value = json_decode($res['value'],true);

            foreach($value as $key=>$val) {

                if($data['setType'] == $val['setType']) {

                    $check = true;

                    $value[$key] = $data;

                }

            }

            if($check === false) {

                $value[] = $data;

            }

            $where['nid'] = "three_no_return_limit";

            $dat['value'] = json_encode($value);

            $rows = $this->db->update($this->table1, $dat, $where);

            $saveType = '更新';

        }



        $xy28str = $jnd28str = '';

        $xy28arr = $jnd28arr = [];

        if(isset($data['condition1'])) {

            if($data['condition1']['xy28']['type']) {

                $xy28arr = explode(',',$data['condition1']['xy28']['type']);

            }

            if($data['condition1']['jnd28']['type']) {

                $jnd28arr = explode(',',$data['condition1']['jnd28']['type']);

            }

            foreach($arr1 as $k=>$v) {

                if(in_array($k, $xy28arr))

                    $xy28str .= $v.'&';

                if(in_array($k, $jnd28arr))

                    $jnd28str .= $v.'&';

            }

        }

        $xy28str .= ';最低总额'.$data['condition1']['xy28']['amount'];

        $jnd28str .= ';最低总额'.$data['condition1']['jnd28']['amount'];



        $xy28_qs = isset($data['condition2']['xy28'])?$data['condition2']['xy28']:'';

        $jnd28_qs = isset($data['condition2']['jnd28'])?$data['condition2']['jnd28']:'';

        $log_remark = $this->admin['username'] . "--{$saveType}三无玩家返水限制($type)--下注:幸运28($xy28str);加拿大28($jnd28str)--投注:幸运28($xy28_qs);加拿大28($jnd28_qs)";

        admin_operation_log($this->admin['userid'], 40, $log_remark);



        if($rows < 0 || $rows === false)

        {

            return false;

        }

        else

        {

            return true;

        }



    }



    public function loadCupOdds(){

        $arr = $this->db->getall("select * from #@_cup_odds");

        if (!empty($arr)) {

            foreach ($arr as $val) {

                $array[$val['match_id']] = [];

            }

            foreach ($arr as $value) {

                foreach ($array as $key=>$val) {

                    if ($value['match_id'] == $key) {

                        $array[$key][] = $value;

                    }

                }

            }

            $redis = initCacheRedis();

            foreach ($array as $key=>$val) {

                $redis->hset("fb_odds", $key, encode($val));

            }

            deinitCacheRedis($redis);

        }



    }



    public function loadCupAgainst(){

        $arr = $this->db->getall("select * from #@_cup_against");

        if (!empty($arr)){

            foreach ($arr as $val) {

                $array[$val['match_id']] = [];

            }

            foreach ($arr as $value) {

                foreach ($array as $key=>$val) {

                    if ($value['match_id'] == $key) {

                        $array[$key][] = $value;

                    }

                }

            }

            $redis = initCacheRedis();

            foreach ($array as $key=>$val) {

                $redis->hset("fb_against", $key, encode($val));

            }

            deinitCacheRedis($redis);

        }



    }











    private function odds_data_7_8($oddsData) {

        foreach($oddsData as &$data) {



            //文字类型玩法

            if($data['type'] == 2) {

                $a = explode("_",$data['way']);



                if ($a[0] == "特码B") {

                    $data['way_type'][] = 'tema';

//                    $data['way_type'][] = 'tema_b';

                }



                if ($a[0] == "特码A") {

                    $data['way_type'][] = 'tema';

//                    $data['way_type'][] = 'tema_a';

                }

                if ($a[0] == "正码B") {

                    $data['way_type'][] = 'zhengma';

//                    $data['way_type'][] = 'zhengma_b';

                }

                if ($a[0] == "正码A") {

                    $data['way_type'][] = 'zhengma';

//                    $data['way_type'][] = 'zhengma_a';

                }



                for ($x=1;$x<=6;$x++) {

                    $j = 7 - $x;

                    if ($a[0] == "正{$j}特") {

                        $data['way_type'][] = 'zhengte';

//                        $data['way_type'][] = 'zheng_'.$j.'_te';

                    }

                    if ($a[0] == "正码{$j}") {

                        $data['way_type'][] = 'zhengma_1_6';

//                        $data['way_type'][] = 'zheng_ma_'.$j;

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



                    $data['way_type'][] = 'banbo';

                }



                if ($a[0] == "尾数") {

                    $data['way_type'][] = 'weishu';

                }



                if ($a[0] == "一肖") {

                    $data['way_type'][] = 'yixiao';

                }



                if ($a[0] == "特肖") {

                    $data['way_type'][] = 'texiao';

                }



                if ($a[0] == "四肖连不中") {

                    $data['way_type'][] = 'lianxiao';

//                    $data['way_type'][] = 'lianxiao_si_n';

                }

                if ($a[0] == "三肖连不中") {

                    $data['way_type'][] = 'lianxiao';

//                    $data['way_type'][] = 'lianxiao_san_n';

                }

                if ($a[0] == "二肖连不中") {

                    $data['way_type'][] = 'lianxiao';

//                    $data['way_type'][] = 'lianxiao_er_n';

                }

                if ($a[0] == "四肖连中") {

                    $data['way_type'][] = 'lianxiao';

//                    $data['way_type'][] = 'lianxiao_si_y';

                }

                if ($a[0] == "三肖连中") {

                    $data['way_type'][] = 'lianxiao';

//                    $data['way_type'][] = 'lianxiao_san_y';

                }

                if ($a[0] == "二肖连中") {

                    $data['way_type'][] = 'lianxiao';

//                    $data['way_type'][] = 'lianxiao_er_y';

                }



                if ($a[0] == "四尾连不中") {

                    $data['way_type'][] = 'lianwei';

//                    $data['way_type'][] = 'lianwei_si_n';

                }

                if ($a[0] == "三尾连不中") {

                    $data['way_type'][] = 'lianwei';

//                    $data['way_type'][] = 'lianwei_san_n';

                }

                if ($a[0] == "二尾连不中") {

                    $data['way_type'][] = 'lianwei';

//                    $data['way_type'][] = 'lianwei_er_n';

                }

                if ($a[0] == "四尾连中") {

                    $data['way_type'][] = 'lianwei';

//                    $data['way_type'][] = 'lianwei_si_y';

                }

                if ($a[0] == "三尾连中") {

                    $data['way_type'][] = 'lianwei';

//                    $data['way_type'][] = 'lianwei_san_y';

                }

                if ($a[0] == "二尾连中") {

                    $data['way_type'][] = 'lianwei';

//                    $data['way_type'][] = 'lianwei_er_y';

                }



                if ($a[0] == "1-2球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "1-3球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "1-4球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "1-5球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "1-6球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "2-3球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "2-4球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "2-5球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "2-6球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "3-4球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "3-5球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "3-6球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "4-5球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "4-6球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

                if ($a[0] == "5-6球") {

                    $data['way_type'][] = 'zheng_1_6_lh';

                }

            }



            //数字玩法

            if($data['type'] == 1) {

                $a = explode("_",$data['way']);

                if ($a[0] == "特码B") {

                    $data['way_type'][] = 'tema';

//                    $data['way_type'][] = 'tema_b';

                }

                if ($a[0] == "特码A") {

                    $data['way_type'][] = 'tema';

//                    $data['way_type'][] = 'tema_a';

                }

                if ($a[0] == "正码B") {

                    $data['way_type'][] = 'zhengma';

//                    $data['way_type'][] = 'zhengma_b';

                }

                if ($a[0] == "正码A") {

                    $data['way_type'][] = 'zhengma';

//                    $data['way_type'][] = 'zhengma_a';

                }



                for ($x=1;$x<=6;$x++) {

                    $j = 7 - $x;

                    if ($a[0] == "正{$j}特") {

                        $data['way_type'][] = 'zhengte';

//                        $data['way_type'][] = 'zhengte_1_6';

//                        $data['way_type'][] = 'zheng_'.$j.'_te';

                    }

                    if ($a[0] == "正码{$j}") {

                        $data['way_type'][] = 'zhengma_1_6';

//                        $data['way_type'][] = 'zheng_ma_'.$j;

                    }

                }



                if ($a[0] == "特串") {

                    $data['way_type'][] = 'lianma';

                }

                if ($a[0] == "二中特") {

                    $data['way_type'][] = 'lianma';

                }

                if ($a[0] == "二全中") {

                    $data['way_type'][] = 'lianma';

                }

                if ($a[0] == "三中二") {

                    $data['way_type'][] = 'lianma';

                }



                if ($a[0] == "三全中") {

                    $data['way_type'][] = 'lianma';

                }

                if ($a[0] == "十不中") {

                    $data['way_type'][] = 'buzhong';

                }

                if ($a[0] == "九不中") {

                    $data['way_type'][] = 'buzhong';

                }

                if ($a[0] == "八不中") {

                    $data['way_type'][] = 'buzhong';

                }

                if ($a[0] == "七不中") {

                    $data['way_type'][] = 'buzhong';

                }

                if ($a[0] == "六不中") {

                    $data['way_type'][] = 'buzhong';

                }

                if ($a[0] == "五不中") {

                    $data['way_type'][] = 'buzhong';

                }



                if ($a[0] == "三中二之中三") {

                    $data['way_type'][] = 'lianma';

                }

                if ($a[0] == "二中特之中特") {

                    $data['way_type'][] = 'lianma';

                }

            }

            unset($data);

        }

        return $oddsData;

    }



    private function odds_data_2_4_9_14($oddsData) {

        foreach($oddsData as &$data) {



            //文字类型玩法

            if($data['type'] == 2) {

                $a = explode("_",$data['way']);

                if (strpos($data['way'],"龙") !== false || strpos($data['way'],"虎") !== false) {

                    $data['way_type'][] = 'longhu';

                } else {

                    $data['way_type'][] = 'shuangmian';

                }



            }



            if($data['type'] == 1) {

                $data['way_type'][] = 'chehao';

            }



            if($data['type'] == 3) {

                if($data['way'] == '庄' || $data['way'] == '闲'){

                    $data['way_type'][] = 'zhuangxian';

                } elseif($data['way'] == "冠亚") {

                    $data['way_type'][] = 'guanya';

                } else {

                    $data['way_type'][] = 'guanyahe';

                }



            }

        }



        return $oddsData;

    }



    private function odds_data_5_6_11($oddsData) {

        foreach ($oddsData as &$data) {

            if($data['type'] == 2) {

                $data['way_type'][] = 'shuangmian';

            }

            if($data['type'] == 1) {

                $data['way_type'][] = 'shuzi';

            }

            if($data['type'] == 3) {

                if (in_array($data['way'],['龙', '虎', '和'])) {

                    $data['way_type'][] = 'longhu';

                } else {

                    $data['way_type'][] = 'zonghe';

                }

            }

        }

        return $oddsData;

    }





    private function odds_data_1_3($oddsData) {

        foreach ($oddsData as &$data) {

            if($data['type'] == 2) {

                $data['way_type'][] = 'shuangmian';

            }

            if($data['type'] == 1) {

                $data['way_type'][] = 'shuzi';

            }

            if($data['type'] == 3) {

                $data['way_type'][] = 'teshu';

            }

        }

        return $oddsData;

    }



    private function odds_data_10($oddsData) {

        $arr_key_nn = ['无牛','牛一','牛二','牛三','牛四','牛五','牛六','牛七','牛八','牛九','牛牛','花色牛'];

        $arr1 = ['第一张','第二张','第三张','第四张','第五张'];

        $arr2 = ['大','小','单','双','大单','大双', '小单', '小双'];

        $arr_key_zh = [];

        foreach($arr1 as $value) {

            foreach ($arr2 as $val) {

                $arr_key_zh[] = $value.'_'.$val;

            }

        }



        foreach ($oddsData as &$data) {

            if($data['type'] == 2) {

                $data['way_type'][] = 'paimian';

            }

            if($data['type'] == 1) {

                if (in_array($data['way'],$arr_key_nn)) {

                    $data['way_type'][] = 'niuniu';

                }

                if (in_array($data['way'],$arr_key_zh)) {

                    $data['way_type'][] = 'shuangmian';

                }

                if (in_array($data['way'],['红方胜','蓝方胜'])) {

                    $data['way_type'][] = 'shengfu';

                }

            }

            if($data['type'] == 3) {

                $a = explode("_",$data['way']);

                if (in_array($a[1],['黑桃','梅花','红心','方块'])) {

                    $data['way_type'][] = 'huase';

                }

                if (in_array($data['way'],['龙','虎'])) {

                    $data['way_type'][] = 'longhu';

                }

                if (in_array($data['way'],['有公牌','无公牌'])) {

                    $data['way_type'][] = 'gongpai';

                }

                if (in_array($data['way'],['大','小','单','双','大单','大双','小单','小双'])) {

                    $data['way_type'][] = 'zonghe';

                }



            }

        }

        return $oddsData;

    }



    private function odds_data_13($oddsData) {

        foreach ($oddsData as &$data) {

            if($data['type'] == 1) {

                $a = explode("_",$data['way']);

                if (in_array($a[0],['第一骰','第二骰','第三骰'])) {

                    $data['way_type'][] = 'shuzi';

                }

                if (in_array($a[0],['对子'])) {

                    $data['way_type'][] = 'duizi';

                }

                if (in_array($a[0],['豹子'])) {

                    $data['way_type'][] = 'weishai';

                }

                if (in_array($a[0],['单骰'])) {

                    $data['way_type'][] = 'danshai';

                }

                if (in_array($a[0],['双骰'])) {

                    $data['way_type'][] = 'shuangshai';

                }

                if (in_array($a[0],['总和'])) {

                    $data['way_type'][] = 'zonghe';

                }

            }

            if($data['type'] == 2) {

                $a = explode("_",$data['way']);

                if (in_array($a[0],['第一骰','第二骰','第三骰'])) {

                    $data['way_type'][] = 'shuangmian';

                }

                if (in_array($a[0],['总和'])) {

                    $data['way_type'][] = 'zonghe';

                }

            }

        }

        return $oddsData;

    }



    /*

     *  分类

     * $oddsData        un_odds select * 原样数据

     * */

    public $way_arr_zh = [];

    public function oddsDataByClass($oddsData, $lottery_type) {

        switch($lottery_type) {

            case 7:

            case 8:

                $way_arr_zh = [

                    'tema' => '特码',

                    'zhengma' => '正码',

                    'zhengte' => '正特',

                    'lianma' => '连码',

                    'banbo' => '半波',

                    'weishu' => '尾数',

                    'yixiao' => '一肖',

                    'texiao' => '特肖',

                    'lianxiao' => '连肖',

                    'lianwei' => '连尾',

                    'buzhong' => '不中',

                    'zhengma_1_6' => '正码1-6',

                    'zheng_1_6_lh' => '正1-6龙虎',

                ];

                $this->way_arr_zh = $way_arr_zh;

                return $this->odds_data_7_8($oddsData);

                break;

            case 2:

            case 4:

            case 9:

            case 14:

                $way_arr_zh = [

                    'longhu' => '龙虎',

                    'shuangmian' => '双面',

                    'chehao' => '车号',

                    'zhuangxian' => '庄闲',

                    'guanya' => '冠亚',

                    'guanyahe' => '冠亚和',

                ];

                $this->way_arr_zh = $way_arr_zh;

                return $this->odds_data_2_4_9_14($oddsData);

                break;

            case 5:

            case 6:

            case 11:

                 $way_arr_zh = [

                    'shuangmian' => '双面',

                    'shuzi' => '数字',

                    'longhu' => '龙虎',

                    'zonghe' => '总和',

                  ];

                 $this->way_arr_zh = $way_arr_zh;

                 return $this->odds_data_5_6_11($oddsData);

                 break;

            case 1:

            case 3:

                $way_arr_zh = [

                    'shuangmian' => '双面',

                    'shuzi' => '数字',

                    'teshu' => '特殊',

                ];



                $this->way_arr_zh = $way_arr_zh;

                return $this->odds_data_1_3($oddsData);

                break;

            case 10:

                $way_arr_zh = [

                    'paimian' => '牌面',

                    'niuniu' => '牛牛',

                    'shuangmian' => '双面',

                    'shengfu' => '胜负',

                    'huase' => '花色',

                    'longhu' => '龙虎',

                    'gongpai' => '公牌',

                    'zonghe' => '总和',

                ];

                $this->way_arr_zh = $way_arr_zh;

                return $this->odds_data_10($oddsData);

                break;

            case 13:

                $way_arr_zh = [

                    'shuzi' => '数字',

                    'duizi' => '对子',

                    'weishai' => '围骰',

                    'danshai' => '单骰',

                    'shuangshai' => '双骰',

                    'zonghe' => '总和',

                    'shuangmian' => '双面',

                ];

                $this->way_arr_zh = $way_arr_zh;

                return $this->odds_data_13($oddsData);

                break;

            default:

                return $oddsData;

                break;

        }

    }



}

