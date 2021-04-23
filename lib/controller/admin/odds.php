<?php



/**

 * @copyright			(C) 2013 CHENGHUITONG.COM

 */

!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');

include S_CORE . 'class' . DS . 'pages.php';

include S_CORE . 'class' . DS . 'page.php';



class OddsAction extends Action {



    private $model;



    public function __construct() {

        parent::__construct();

        $this->model = D('admin/odds');

    }



    //团队返水查询

    public function teamBack(){

        $data = $_REQUEST;

        unset($data['m'],$data['c'],$data['a']);

        $where['account'] = $data['account']?$data['account']:'';

        $date = $data['addtime']?$data['addtime']:date('Y-m-d',strtotime('today -1 day'));

        $begin_time = strtotime($date);

        $end_time = strtotime($date.' 23:59:59');

        $quick = $data["quick"];

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

        }



        if(empty($where['account'])){

            include template('team_back');

            return false;

        }



        $data = array();

        if(!empty($where['account'])){



            $sql = "select id from un_user where username='{$where['account']}'";

            $user_id = $this->db->result($sql);



            $sql = "SELECT user_id FROM `un_user_tree` WHERE `pids` LIKE '%,{$user_id},%' or user_id={$user_id}";

            $res = $this->db->getall($sql);



            if(count($res)>1){

                $self = array('user_id' => $user_id);

                array_push($res, $self);

                $res = array_column($res,'user_id');

                $res = array_unique($res); //去重

                $total = array();

                $total['lottery_ids'] = array();

                $total['zhu_he']=0;

                $total['ji_zhi']=0;

                $total['point']=0;

                $total['money']=0;

                $total['money_self']=0;

                $total_money=0;

                $zhu_he = array('小单','大单','小双','大双');

                $sp_way = array("红","绿","蓝","豹子","正顺","对子","倒顺","半顺","乱顺");

                $point = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27'];

                $total = array();

                foreach ($res as $id) {

//                    $id = $v['user_id'];



                    $sql = "SELECT username FROM `un_user` WHERE id={$id}";



                    $re = $this->db->result($sql);

                    if (!empty($re)) {

                        $data[$id]['lottery_ids'] = array();

//                        $data[$id]['lottery_ids']['total']='';

                        $data[$id]['back_satus'] = '';

                        $data[$id]['id'] = $id;

                        $data[$id]['money']=0;

                        $data[$id]['username'] = $re;

                        $sql = "SELECT * FROM un_orders WHERE user_id={$id} AND `addtime` >= {$begin_time} AND `addtime` <= {$end_time} AND state = 0";

                        $list = $this->db->getall($sql);



                        if (!empty($list)) {





                            foreach ($list as $kk => $vv) {



                                $data[$id]['money'] = bcadd($data[$id]['money'], $vv['money'], 2);



                                $total_money = bcadd($total_money, $vv['money'], 2);



                                if (!in_array($vv['issue'], $data[$id]['lottery_ids'])) {

                                    $data[$id]['lottery_ids'][] = $vv['issue']; //收集期号

                                }

                                if ($vv['lottery_type'] == 1 || $vv['lottery_type'] == 3) {

                                    //组合投注统计

                                    if (in_array($vv['way'], $zhu_he)) {

                                        $data[$id]['zhu_he'] = bcadd($data[$id]['zhu_he'], $vv['money'], 2);

                                        $total['zhu_he'] = bcadd($total['zhu_he'], $vv['money'], 2);

                                    }



                                    //极值投注统计

                                    if (in_array($vv['way'], array('极大', '极小'))) {

                                        $data[$id]['ji_zhi'] = bcadd($data[$id]['ji_zhi'], $vv['money'], 2);

                                        $total['ji_zhi'] = bcadd($total['ji_zhi'], $vv['money'], 2);

                                    }



                                    //单点投注统计

                                    if (in_array($vv['way'], $point)) {

                                        $data[$id]['point'] = bcadd($data[$id]['point'], $vv['money'], 2);

                                        $total['point'] = bcadd($total['point'], $vv['money'], 2);

                                    }



                                    //特殊投注统计

                                    if (in_array($vv['way'], $sp_way)) {

                                        $data[$id]['spway'] = bcadd($data[$id]['spway'], $vv['money'], 2);

                                        $total['spway'] = bcadd($total['spway'], $vv['money'], 2);

                                    }

                                }

                            }

                        }



                        $data[$id]['lottery_ids']['total'] = count($data[$id]['lottery_ids']);

                        $sql = "SELECT state FROM un_back_log WHERE user_id={$id} AND `addtime`='{$date}'";

                        $re = $this->db->getone($sql);

                        $back = $re['teamBack']?:0.00;

                        if (empty($re)) {

                            $data[$id]['back_satus'] = '';

                        } else {

                            $data[$id]['back_satus'] = $re['state'] == 1 ? '未返水' : ($re['state'] == 2 ? '确认返水' : '取消返水');

                        }

                    }

                }

                foreach ($data as $kk=>$vv) {

                    $total['money'] = bcadd($total['money'],$vv['money'],2);

                    $total['ids'] += $data[$kk]['lottery_ids']['total'];

                }

                $sql = "SELECT teamBack FROM un_back_log WHERE user_id={$user_id} AND `addtime`='{$date}'";

                $reb = $this->db->result($sql);

                $back = $reb?:0.00;

            }

        }



        include template('team_back');

    }



    //直属返水查询

    public function sonBack(){

        $data = $_REQUEST;

        unset($data['m'],$data['c'],$data['a']);

        $where['account'] = $data['account']?$data['account']:'';

        $date = $data['addtime']?$data['addtime']:date('Y-m-d',strtotime('today -1 day'));

        $begin_time = strtotime($date);

        $end_time = strtotime($date.' 23:59:59');

        $quick = $data["quick"];

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

        }



        $data = array();

        if(empty($where['account'])){

            include template('son_back');

            return false;

        }else{



            $sql = "select id from un_user where username='{$where['account']}'";

            $user_id = $this->db->result($sql);





            if(!empty($user_id)){

                $total_money=0;

                $sql = "SELECT id  FROM `un_user` WHERE `parent_id` = {$user_id}";

                $pids = $this->db->getall($sql);



                if(!empty($pids)){

                    $total = array();

                    foreach ($pids as $v) {

                        $id = $v['id']; //用户ID号

                        $data[$id]['id'] = $id;

                        $data[$id]['money']=0;

                        $sql = "SELECT username FROM `un_user` WHERE id={$id}";



                        $data[$id]['username'] = $this->db->result($sql);



                        $sql = "SELECT * FROM un_orders  WHERE user_id={$id} AND `addtime` >= {$begin_time} AND `addtime` <= {$end_time} AND state = 0";

                        $list = $this->db->getall($sql);

                        $data[$id]['lottery_ids'] = array(); //初始化期数

                        if(!empty($list)){



                            foreach ($list as $k=>$v) {

                                $sp_way = array("红","绿","蓝","豹子","正顺","对子","倒顺","半顺","乱顺");

                                $zhu_he = array('小单','大单','小双','大双');

                                $point = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27'];



                                $data[$id]['money'] = bcadd($data[$id]['money'],$v['money'],2);



                                $total_money = bcadd($total_money, $v['money'], 2);

                                $total['money'] = bcadd($total['money'], $v['money'], 2);



                                if(!in_array($v['issue'],$data[$id]['lottery_ids'])){

                                    $data[$id]['lottery_ids'][] = $v['issue']; //收集期号

                                }



                                if ($v['lottery_type'] == 1 || $v['lottery_type'] == 3) {

                                    //组合投注统计

                                    if (in_array($v['way'], $zhu_he)) {

                                        $data[$id]['zhu_he'] = bcadd($data[$id]['zhu_he'],$v['money'],2);



                                        if( $id !=$user_id) {

                                            $total['zhu_he'] = bcadd($total['zhu_he'],$v['money'],2);

                                        }

                                    }



                                    //极值投注统计

                                    if (in_array($v['way'], array('极大', '极小'))) {

                                        $data[$id]['ji_zhi'] = bcadd($data[$id]['ji_zhi'],$v['money'],2);

                                        if( $id !=$user_id) {

                                            $total['ji_zhi'] = bcadd($total['ji_zhi'],$v['money'],2);

                                        }

                                    }



                                    //单点投注统计

                                    if (in_array($v['way'], $point)) {

                                        $data[$id]['point'] = bcadd($data[$id]['point'],$v['money'],2);

                                        if( $id !=$user_id) {

                                            $total['point'] = bcadd($total['point'],$v['money'],2);

                                        }

                                    }



                                    //特殊投注统计

                                    if(in_array($v['way'],$sp_way)){

                                        $data[$id]['spway'] = bcadd($data[$id]['spway'],$v['money'],2);

                                        if( $id !=$user_id) {

                                            $total['spway'] = bcadd($total['spway'],$v['money'],2);

                                        }

                                    }

                                }

                            }

                        }



                        $data[$id]['lottery_ids']['total'] = count($data[$id]['lottery_ids'])?:0;

                        $total['ids'] += $data[$id]['lottery_ids']['total'];



                        $sql = "SELECT state,sonBack FROM un_back_log WHERE user_id={$id} AND `addtime`='{$date}'";

                        $re = $this->db->getone($sql);

                        $back = $re['sonBack']?:0.00;

                        if(empty($re)){

                            $data[$id]['back_satus']='';

                        }else{

                            $data[$id]['back_satus'] = $re['state']==1?'未返水':($re['state']==2?'确认返水':'取消返水');

                        }

                    }



                    $sql = "SELECT sonBack FROM un_back_log WHERE user_id={$user_id} AND `addtime`='{$date}'";

                    $reb = $this->db->result($sql);

                    $back = $reb?:0.00;

                }

            }

        }



        include template('son_back');

        return false;

    }



    //个人返水查询

    public function selfBack(){

        $data = $_REQUEST;

        unset($data['m'],$data['c'],$data['a']);

//        dump($data);

        $where['account'] = $data['account']?$data['account']:'';

        //加个字段 开始时间的

        $sdate = $data['start_date']?:date('Y-m-d',strtotime("-1 days"));

        $edate = $data['end_date']?:date('Y-m-d',strtotime("-1 days"));

        $begin_time = strtotime($sdate);

        $end_time = strtotime($edate)+24*3600;

        $lottery_id = $_REQUEST['lottery_id']?:0;



//        $quick = $data["quick"];

//        if($quick != "0"&&$quick !=""){

//            switch ($quick){

//                case 1:

//                    $begin_time = strtotime(date("Y-m-d",strtotime("0 day")));

//                    $end_time = $begin_time + 86399;

//                    break;

//                case 2:

//                    $begin_time = strtotime(date("Y-m-d",strtotime("-1 day")));

//                    $end_time = $begin_time + 86399;

//                    break;

//                case 3:

//                    $begin_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));

//                    $end_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;

//                    break;

//                case 4:

//                    $begin_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));

//                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;

//                    break;

//                case 5:

//                    $begin_time = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));

//                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));

//                    break;

//            }

//        }



        //页面显示用的

        $hsdate = date('Y-m-d',$begin_time);

        $hedate = date('Y-m-d',$end_time);



        $room_id = $data['room_id']?$data['room_id']:0;

        $sql  = "SELECT id,title,lottery_type FROM `un_room` order by id ASC";

        $room_info = $this->db->getall($sql);



        //实例化redis

        $redis = initCacheRedis();



        //查彩种

        // 房间信息 获取实时在线房间人数后期调整 ???

        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);



        //缓存一个hash数组，键为彩种id，值为彩种名称

        $lottery_map = [];

        foreach ($LotteryTypeIds as $lottery_tmp_v) {

            $tmp_lottery_title = $redis->hGet("LotteryType:{$lottery_tmp_v}", 'name');

            $lottery_map[$lottery_tmp_v] = $tmp_lottery_title;

        }

//        dump($lottery_map);



//        $Room = array();

//        foreach ($LotteryTypeIds as $v){

//            $gameInfo = $redis->hGetAll("LotteryType:".$v);

//            $PRoomIds = $redis->lRange("PublicRoomIds".$v, 0, -1);

//            foreach ($PRoomIds as $k){

//                $PRoomInfo = $redis -> hMGet("PublicRoom".$v.":".$k,array('id','title'));

//

//                $Room[$PRoomInfo['id']] = $PRoomInfo['title'];

//            }

//            $SRoomIds = $redis->lRange("PrivateRoomIds".$v, 0, -1);

//            foreach ($SRoomIds as $sk){

//                $SRoomInfo = $redis -> hMGet("PrivateRoom".$v.":".$sk,array('id','title'));

//                $Room[$SRoomInfo['id']] = $SRoomInfo['title'];

//            }

//        }



        foreach ($room_info as &$each_info) {

            //彩种标题

            $lottery_title = $redis->hGet("LotteryType:{$each_info['lottery_type']}",'name');

            $each_info['lottery_title'] = $lottery_title;

        }



        //关闭redis链接

        deinitCacheRedis($redis);



        if(empty($where['account'])){

            include template('self_back');

            return false;

        }



        $sql = "select id from un_user where username='{$where['account']}'";

        $user_info = $this->db->getone($sql);

        if(!empty($user_info)){



            $sql = "SELECT * FROM un_orders  WHERE user_id={$user_info['id']} AND `addtime` >= {$begin_time} AND `addtime` <= {$end_time} AND state = 0";

            if($room_id > 0){

                $sql .= " AND room_no={$room_id}";

            }

            if($lottery_id>0){

                $sql .= " AND lottery_type={$lottery_id}";

            }

//            dump($sql);

            $list = $this->db->getall($sql);



            $ldata = array();



            if(!empty($list)){

                $total = array();

                $total['lottery_ids'] = array();

                $total['zhu_he']=$total['ji_zhi']=$total['point']=0;

                $zhu_he = array('小单','大单','小双','大双');

                $sp_way = array("红","绿","蓝","豹子","正顺","对子","倒顺","半顺","乱顺");

                $point = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27'];

                foreach ($list as $k=>$v){

                    $ldata[$v['room_no']]['money'] = bcadd($ldata[$v['room_no']]['money'],$v['money'],2);

                    $ldata[$v['room_no']]['award'] = bcadd($ldata[$v['room_no']]['award'],$v['award'],2);



                    $total['money'] = bcadd($total['money'],$v['money'],2);

                    $total['award'] = bcadd($total['award'],$v['award'],2);



                    if(empty($ldata[$v['room_no']]['lottery_ids'])){

                        $ldata[$v['room_no']]['lottery_ids']=array();

                    }

                    if(!in_array($v['issue'],$ldata[$v['room_no']]['lottery_ids'])){

                        $ldata[$v['room_no']]['lottery_ids'][] = $v['issue']; //收集期号

                    }



                    if(!in_array($v['issue'],$total['lottery_ids'])){

                        $total['lottery_ids'][] = $v['issue']; //收集期号

                    }



                    if($v['lottery_type']==1 || $v['lottery_type']==3){

                        //组合投注统计

                        if(in_array($v['way'],$zhu_he)){

//                            $ldata[$v['room_no']]['zhu_he'] ++;

                            $ldata[$v['room_no']]['zhu_he'] = bcadd($ldata[$v['room_no']]['zhu_he'],$v['money'],2);



                            $total['zhu_he'] =bcadd($total['zhu_he'],$v['money'],2);

                        }



                        //极值投注统计

                        if(in_array($v['way'],array('极大','极小'))){

                            $ldata[$v['room_no']]['ji_zhi'] = bcadd($ldata[$v['room_no']]['ji_zhi'],$v['money'],2);

                            $total['ji_zhi'] = bcadd($total['ji_zhi'],$v['money'],2);

//                            $total['ji_zhi'] ++;

                        }



                        //单点投注统计

                        if(in_array($v['way'],$point)){

                            $ldata[$v['room_no']]['point'] = bcadd($ldata[$v['room_no']]['point'],$v['money'],2);

                            $total['point'] = bcadd($total['point'],$v['money'],2);

                        }



                        //特殊投注统计

                        if(in_array($v['way'],$sp_way)){

                            $ldata[$v['room_no']]['spway'] = bcadd($ldata[$v['room_no']]['spway'],$v['money'],2);

                            $total['spway'] = bcadd($total['spway'],$v['money'],2);

                        }



                    }



                    if($v['lottery_type']==7 || $v['lottery_type']==8){

                        $tmp = explode('_',$v['way']);

                        if($tmp[0]=='特码A'){

                            $ldata[$v['room_no']]['tma'] = bcadd($ldata[$v['room_no']]['tma'],$v['money'],2);

                            $total['tma'] = bcadd($total['tma'],$v['money'],2);

                        }



                        if($tmp[0]=='正码A'){

                            $ldata[$v['room_no']]['zma'] = bcadd($ldata[$v['room_no']]['zma'],$v['money'],2);

                            $total['zma'] = bcadd($total['zma'],$v['money'],2);

                        }

                    }

                }



                foreach ($ldata as $k=>$v) {

                    $ldata[$k]['unwin'] = bcsub($v['award'],$v['money'],2); //输分

                    $ldata[$k]['lottery_ids']['total'] = count($v['lottery_ids']);

                }



                $total['unwin'] = bcsub($total['award'],$total['money'],2); //输分

                $total['lottery_ids']['total'] = count($total['lottery_ids']);



                $sql = "SELECT state,selfBack FROM un_back_log WHERE user_id={$user_info['id']} AND `addtime`='{$sdate}'";

//                dump($sql);

                $re = $this->db->getone($sql);

                $selfBack = $re['selfBack'];

                if(empty($re)){

                    $back_satus='';

                }else{

                    $back_satus = $re['state']==1?'未返水':($re['state']==2?'确认返水':'取消返水');

                }

            }

        }



        include template('self_back');

        return 1;

    }



    //设置返水的redis标识

    public function set_back(){

        $redis = initCacheRedis();

        $redis->set('back_water_success',0);

        $redis->set('back_water_server_model',2);

        deinitCacheRedis($redis);

        $data = array(

            'status'=>0,

            'msg'=>'提交成功，稍后刷新页面查看结果！!',

        );

        echo encode($data);

    }





    //赔率设置列表

    public function odds() {

        $roomid = $_REQUEST['room'];

        $redis = initCacheRedis();

        if(!empty($roomid)){

            $lottery_type = $redis->hMGet("allroom:{$roomid}",['lottery_type','match_id']);

            if ($lottery_type['lottery_type'] == 12) {

                $list = $this->db->getall("select * from #@_cup_odds where match_id = {$lottery_type['match_id']}");

            } else {

                $list = $this->model->getList('*',array("room" => $roomid),'sort');

            }


            $list = D('odds')->oddsDataByClass($list, $lottery_type['lottery_type']);

            $way_arr_zh = D('odds')->way_arr_zh;

        }

        $rooms = D('room')->getList('id,title,lottery_type');



        //实例化redis

        foreach ($rooms as &$each_info) {

            //彩种标题

            $lottery_title = $redis->hGet("LotteryType:{$each_info['lottery_type']}",'name');

            $each_info['lottery_title'] = $lottery_title;

        }



        //关闭redis链接

        deinitCacheRedis($redis);



        include template('list-odds');

    }







    //赔率设置

    public function doOdds() {

        $id = getParame('id', 1, 0, 'int', ['非法操作', '非法操作']);

        $where = array(

            "id" => $id

        );



        $data = array();

        $data['odds'] = getParame('odds', 0, '', 'decimalMax2', ['', '赔率格式为正整数或小数点后最多2位！'], 'odds_return');

        $data['sort'] = getParame('sort', 0, '', 'int', ['', '排序为整数！'], 'odds_return');

        $data = array_filter($data);

        $lottery_type = getParame('lottery_type', 1, 0, 'int', ['彩种类型错误', '彩种类型错误！'], 'odds_return');



        if ($lottery_type != 12) {

            $sql = "select o.room,o.way,o.odds,o.sort,r.title from un_odds o left join un_room r on o.room = r.id where o.id={$id}";

            $info = $this->db->getone($sql);

            $rt = $this->model->doOdds($data, $where);

            $this->refreshRedis("way", "all");

        } else {

            $sql = "select r.id,o.handicap,o.way,o.odds,o.sort,r.title from un_cup_odds o join un_room r on o.match_id = r.match_id where o.id={$id}";

            $info = $this->db->getone($sql);

            if ($_REQUEST['handicap'] != "") {

                $data['handicap'] = $_REQUEST['handicap'];

            }

            $rt = $this->db->update("#@_cup_odds",$data,$where);

            if ($rt > 0) {

                $odds_info = $this->db->getone("select * from #@_cup_odds where id = {$id}");

                $this->db->insert("#@_cup_odds_log",['way' => $odds_info['way'], 'lottery_type' => 12, 'odds' => $odds_info['odds'], 'handicap' => $odds_info['handicap'], 'add_time' => time(), 'match_id' => $odds_info['match_id']]);

            }

            $this->refreshRedis("fb_odds", "all");

//            D("admin/odds")->loadCupOdds();

        }



        $redis = initCacheRedis();

        $lottery_title = $redis->hGet("LotteryType:{$lottery_type}",'name');

        deinitCacheRedis($redis);

        $log_remarks = "赔率设置:--彩种:".$lottery_title.'--房间:'.$info['title'].'--玩法:'.$info['way'];

        if($data['odds']) $log_remarks .= "--赔率:".$info['odds'].'->'.$data['odds'];

        if($data['sort']) $log_remarks .= "--排序:".$info['sort'].'->'.$data['sort'];

        if(isset($data['handicap']) && $data['handicap']) $log_remarks .= "--盘口:".$info['handicap'].'->'.$data['handicap'];

        admin_operation_log($this->admin['userid'], 70, $log_remarks);



        $data=array( //调用双活接口

            'type'=>'update_odds',

            'id'=>$info['id'],

            'json'=>encode(array('commandid' => 3024,'room_id'=>$info['id'])),

        );

        send_home_data($data);

        echo json_encode(array("rt" => $rt));

    }



    //赔率设置 一键设置

    public function doOddsAll() {

        $str = $_REQUEST['str'];

        $str1 = explode("|", $str);

        $oid=0;

        foreach ($str1 as $value) {

            $str2 = explode("-", $value);

            $array = array(

                "odds" => $str2[1],

                "id" => $str2[0],

                "sort" => $str2[2],

                "handicap" => $str2[3],

            );



            if ($array['odds'] != "") {

                if (!is_numeric($array['odds']) || $array['odds'] < 0) {

                    continue;

                }



                $f = $array['odds'];

                $data['odds'] = $f;

                if (strstr($f, ".")) {

                    $arr = explode(".", $f);

                    $val = $arr[0] . "." . substr($arr[1], 0, 2);

                    $data['odds'] = $val;

                }

                $oid = $str2[0];

            }

            if ($array['sort'] != "") {

                $data['sort'] = $array['sort'];

            }

            if ($_POST['lottery_type'] != 12) {

                $this->model->doOdds($data, array("id"=>$array['id']));

            } else {

                if ($array['id'] != '') {

                    if ($array['handicap'] != "") {

                        $data['handicap'] = $array['handicap'];

                    }

                    $this->db->update("#@_cup_odds",$data,['id'=>$array['id']]);

                    $odds_info = $this->db->getone("select * from #@_cup_odds where id = {$array['id']}");

                    $this->db->insert("#@_cup_odds_log",['way' => $odds_info['way'], 'lottery_type' => 12, 'odds' => $odds_info['odds'], 'handicap' => $odds_info['handicap'], 'add_time' => time(), 'match_id' => $odds_info['match_id']]);

                }



            }



        }

        $this->refreshRedis("way", "all");

        $this->refreshRedis("fb_odds", "all");

        if($oid>0){

            if ($_POST['lottery_type'] != 12) {

                $room_id = $this->db->result("select room from un_odds where id={$oid}");

            }else{

                $room_id = $this->db->result("select r.id from un_cup_odds o join un_room r on o.match_id = r.match_id where o.id={$oid}");

            }



            $data=array( //调用双活接口

                'type'=>'update_odds',

                'id'=>$room_id,

                'json'=>encode(array('commandid' => 3024,'room_id'=>$room_id)),

            );

            send_home_data($data);

        }

        echo json_encode(array("rt" => 1));

    }



    //直属返水设置

    public function backSon() {

        $data = $this->model->back();



        include template('list-back');

    }



    //个人返水设置

    public function back() {

        $roomlist = $this->model->roomBack();

        $type = $_REQUEST['type']?:1;

//        $type = $this->model->typeBack();

        $room_id = $_REQUEST['room_id'] == "" ? $roomlist[0]['id'] : $_REQUEST['room_id']; //默认展示第一个房间的返水列表



        $backlist = $this->model->backList($room_id);

//        dump($backlist);

        $json = array();

        if(!empty($backlist['list'])){

            $list_num = count($backlist['list']);

            foreach ($backlist['list'] as $key => $value) {

//                dump($value);

                if(empty($value['type'])){

//                    unset($backlist['list'][$key]);

                    $json[$key] = $backlist['list'][$key];

                    $json[$key]['type'] = $type;

                }



                if($value['type']==$type){

                    $backlist['list'][$key]['addtime'] = empty($value['addtime']) ? "-" : date("Y-m-d H:i:s", $value['addtime']);

//                    if ($key == $list_num - 1) {

//                        $backlist['list'][$key]['del'] = 1;

//                    }

                }else{

                    unset($backlist['list'][$key]);

                }

            }



            if(!empty($json)){

                $data = encode($json);

                $sql = "UPDATE `un_room` SET backRate='{$data}' WHERE id={$room_id}";

                $this->db->query($sql);

            }

            if(!empty($backlist['list'])){

                $key = max(array_keys($backlist['list']));

                $backlist['list'][$key]['del'] = 1;

//                dump($backlist['list'][$key]);

                $max  = $backlist['list'][$key]['upper'];

            }

        }

        include template('list-backset');

    }



    //返水类型设置

    public function backType() {

        $value = $_REQUEST['value'];

        $rt = $this->model->doBackType(array("value" => $value), array("nid" => "typeBack"));

        echo json_encode(array("rt" => $rt));

    }



    public function getMaxVal(){

        $type = $_REQUEST['type'];

        $id = $_REQUEST['room_id'];

        $sql = "SELECT backRate FROM un_room WHERE id={$id}";

        $re = $this->db->result($sql);

        $val = decode($re);

        $max = 1;

        foreach ($val as $v){

            if($v['type']==$type && $v['upper']>$max){

                $max = $v['upper'];

            }

        }

        $data = array(

            'code'=>0,

            'max'=>$max,

        );

        echo encode($data);

    }



    //返水比率设置

    public function backContent() {

        $id = $_REQUEST['room_id'];

        $upper = $_REQUEST['upper'];

        $lower = $_REQUEST['lower'];

        $rate = bcadd($_REQUEST['rate'],0,2);

        $type = $_REQUEST['type'];

        $rate_special = $_REQUEST['rate_special'];

        $rate_just = $_REQUEST['rate_just'];

        $addtime = time();



        $rt = $this->model->backContent(array("rate_special" => $rate_special, "rate_just" => $rate_just, "upper" => $upper, "lower" => $lower, "rate" => $rate,"type"=>$type, "addtime" => $addtime), array("id" => $id));

        echo json_encode(array("rt" => $rt));

    }



    //返水比率删除

    public function backDelete() {

        $i = $_REQUEST['i'];

        $id = $_REQUEST['id'];

        $type = $_REQUEST['type'];



        $rt = $this->model->backDelete($i, $id,$type);

        echo json_encode(array("rt" => $rt));

    }



    //回水设置

    public function doBack() {

        $where = array("nid" => $_REQUEST['nid']);

        $data = array("value" => $_REQUEST['val']);



        $rt = $this->model->doBack($data, $where);

        $this->refreshRedis("all", "all");

        echo json_encode(array("rt" => $rt));

    }



    //返水列表

    public function calculate() {

        $redis = initCacheRedis();

        $back_water_success = $redis->get('back_water_success');

        $back_success = $back_water_success?$back_water_success:0;

        $back_water_server_model = $redis->get('back_water_server_model');

        $back_model = $back_water_server_model?$back_water_server_model:0;

        deinitCacheRedis($redis);

        $where['account'] = $_REQUEST['account'];

        $where['addtime'] = empty($_REQUEST['addtime']) ? date('Y-m-d', strtotime('-1 day')) : $_REQUEST['addtime'];

        $back = isset($_REQUEST['back']) ? $_REQUEST['back'] : 1;

        $where['back'] = $back;

        $where['quick'] = $_REQUEST['quick'];

        //总数

        $lists = $this->model->calculate($where);

        if(!empty($lists)){

            //每页显示20条记录

            $pagesize = 20;

            $url = '?m=admin&c=odds&a=calculate';

            $page = new pages(count($lists), $pagesize, $url, $_REQUEST);

            $show = $page->show();

            $total = array();//总计

            $current_total = array();//当前页总计

            $backAll = 0; //没有待操作用户返水,则不显示一键返水

            foreach ($lists as $k => $v) {

                if ($v['state'] == 1) {

                    $backAll = 1;

                }

                if($page->offer<=$k && $k<($page->offer+$pagesize)){

                    $list[] = $v;

                    $current_total['selfMoney']+= $v['selfMoney'];//个人投注

                    $current_total['selfLose']+= $v['selfLose'];//个人投注

                    $current_total['limitSelfMoney']+= $v['limitSelfMoney'];//个人投注

                    $current_total['selfBack']+= $v['selfBack'];//个人返水

                    $current_total['limitSelfBack']+= $v['limitSelfBack'];//个人返水

                    $current_total['sonMoney']+= $v['sonMoney'];//直属投注

                    $current_total['limitSonMoney']+= $v['limitSonMoney'];//直属投注

                    $current_total['sonBack']+= $v['sonBack'];//直属返水

                    $current_total['limitSonBack']+= $v['limitSonBack'];//直属返水

                    $current_total['teamMoney']+= $v['teamMoney'];//团队投注

                    $current_total['limitTeamMoney']+= $v['limitTeamMoney'];//团队投注

                    $current_total['teamBack']+= $v['teamBack'];//团队返水

                    $current_total['limitTeamBack']+= $v['limitTeamBack'];//团队返水

                    $current_total['cntBack']+= $v['cntBack'];//总计返水

                    $current_total['limitCntBack']+= $v['limitCntBack'];//总计返水

                    $current_total['yesdayBack']+= $v['yesdayBack'];//总计昨日返水

                }

                $total['selfMoney']+= $v['selfMoney'];//个人投注

                $total['selfLose']+= $v['selfLose'];//个人投注

                $total['limitSelfMoney']+= $v['limitSelfMoney'];//个人投注

                $total['selfBack']+= $v['selfBack'];//个人返水

                $total['limitSelfBack']+= $v['limitSelfBack'];//个人返水

                $total['sonMoney']+= $v['sonMoney'];//直属投注

                $total['limitSonMoney']+= $v['limitSonMoney'];//直属投注

                $total['sonBack']+= $v['sonBack'];//直属返水

                $total['limitSonBack']+= $v['limitSonBack'];//直属返水

                $total['teamMoney']+= $v['teamMoney'];//团队投注

                $total['limitTeamMoney']+= $v['limitTeamMoney'];//团队投注

                $total['teamBack']+= $v['teamBack'];//团队返水

                $total['limitTeamBack']+= $v['limitTeamBack'];//团队返水

                $total['cntBack']+= $v['cntBack'];//总计返水

                $total['limitCntBack']+= $v['limitCntBack'];//总计返水

                $total['yesdayBack']+= $v['yesdayBack'];//总计昨日返水

            }

        }

        //获取返水类型

        $sql = "SELECT back_type FROM un_agent_group ORDER BY id DESC LIMIT 0,1";

        $backType = $this->db->result($sql);

        include template('list-calculate');

    }



    //返水脚本   用于在un_back_log表记录数据

    public function calculate_back() {

        $where = array();

        $where['account'] = $_REQUEST['account'];

        $where['addtime'] = $_REQUEST['addtime'] == "" ? date("Y-m-d", strtotime("-1 day")) : $_REQUEST['addtime'];



        $idList = $this->model->idList($where); //某天需要返水的用户ID

        $result = array();

        $array = array();

        foreach ($idList as $value) {

            $where['id'] = $value['user_id'];

            $data = array();

            $self = $this->model->oneCalculate($where); // 一个人/一天的回水计算

            $son = $this->model->sonCalculate($where); // 直属人数/投注/返水

            $team = $this->model->teamCalculate($where); // 团队人数/投注/返水

            $data['selfMoney'] = empty($self['selfMoney']) ? 0 : $self['selfMoney'];

            $data['selfBack'] = round(empty($self['selfBack']) ? 0 : $self['selfBack'], 2);

            $data['sonMoney'] = empty($son['sonMoney']) ? 0 : $son['sonMoney'];

            $data['sonCnt'] = empty($son['sonCnt']) ? 0 : $son['sonCnt'];

            $data['sonBack'] = round(empty($son['sonBack']) ? 0 : $son['sonBack'], 2);

            $data['teamMoney'] = empty($team['teamMoney']) ? 0 : $team['teamMoney'];

            $data['teamCnt'] = empty($team['teamCnt']) ? 0 : $team['teamCnt'];

            $data['teamRate'] = empty($team['teamRate']) ? 0 : $team['teamRate'];

            $data['teamBack'] = round(empty($team['teamBack']) ? 0 : $team['teamBack'], 2);

            $data['cntBack'] = $data['teamBack'] + $data['sonBack'] + $data['selfBack'];

            $data['user_id'] = $value['user_id'];

            $data['username'] = $this->model->getUname(array("id" => $data['user_id']));

            $array[] = $data;

            $result['cnt'] += $data['cntBack'];

        }

        $result['list'] = $array;



        include template('list-calculate');

    }



    //赔率规则

    public function rule() {

        //获取一个默认的房间号

        $sql = "select id from un_room WHERE lottery_type in (1,3) order by id asc";

        $first_room = $this->db->result($sql);

        //改成房间

        $room_id = empty($_REQUEST['room_id']) ? $first_room : $_REQUEST['room_id'];



        //实例化redis

        $redis = initCacheRedis();



        $roomList = $this->db->getall('select id,lottery_type,title from un_room WHERE lottery_type in (1,3) order by id desc');



        $lists = $redis->hGet('Config:oddsRule','value');





        $lists = json_decode($lists, true);

        $list = $lists[$room_id];

        $exData = array(

            4=>'豹子',

            5=>'正顺',

            6=>'对子',

            7=>'倒顺',

            8=>'半顺',

            9=>'乱顺',

        );





        foreach($roomList as $k=>$v){

            //彩种标题

            $lottery_title = $redis->hGet("LotteryType:{$v['lottery_type']}",'name');

            $roomList[$k]['lottery_title'] = $lottery_title;

            if(empty($lists[$v['id']]) && in_array($v['lottery_type'],array(1,3))){ //如果没数据就自动从配置更新过来

                $lists[$v['id']]=$lists[$v['lottery_type']];

                $sql = "UPDATE `un_config` SET VALUE='".encode($lists)."' WHERE nid='oddsRule'";

                $this->db->query($sql);

                $this->refreshRedis('config', 'all'); //刷新缓存

            }



        }

        //关闭redis链接

        deinitCacheRedis($redis);



        include template('list-rule');

    }



    //赔率规则修改

    public function upRule() {

        lg('up_rule',var_export(array(

            'admin_info'=>$this->admin,

        ),1));

        $list = $this->model->rule();

        $list = json_decode($list['value'], true);



        for($i=1;$i<5;$i++){

            $_REQUEST['point'.$i] = trim($_REQUEST['point'.$i]);

            $_REQUEST['ratio'.$i] = trim($_REQUEST['ratio'.$i]);

            if($_REQUEST['point'.$i] != '' && !preg_match('/^\d+(?:\.\d{1,2})?$/',$_REQUEST['point'.$i])){

                echo encode(array('rt'=>0,'msg'=>'非法数据，请重试！'));

                return false;

                break;

            }



            if($_REQUEST['ratio'.$i] != '' && !preg_match('/^\d+(?:\.\d{1,2})?$/',$_REQUEST['ratio'.$i])){

                echo encode(array('rt'=>0,'msg'=>'非法数据，请重试！'));

                return false;

                break;

            }

        }



        $ratio1 = $_REQUEST['ratio1'];

        $ratio2 = $_REQUEST['ratio2'];

        $ratio3 = $_REQUEST['ratio3'];

        $ratio4 = $_REQUEST['ratio4'];



        if (strstr($ratio1, ".")) {

            $arr = explode(".", $ratio1);

            $ratio1 = $arr[0] . "." . substr($arr[1], 0, 2);

        }

        if (strstr($ratio2, ".")) {

            $arr = explode(".", $ratio2);

            $ratio2 = $arr[0] . "." . substr($arr[1], 0, 2);

        }

        if (strstr($ratio3, ".")) {

            $arr = explode(".", $ratio3);

            $ratio3 = $arr[0] . "." . substr($arr[1], 0, 2);

        }

        if (strstr($ratio4, ".")) {

            $arr = explode(".", $ratio4);

            $ratio4 = $arr[0] . "." . substr($arr[1], 0, 2);

        }



        $data = array(

            array(

                'point' => $_REQUEST['point1'],

                'ratio' => $ratio1,

                'status' => $_REQUEST['status1']

            ),

            array(

                'point' => $_REQUEST['point2'],

                'ratio' => $ratio2,

                'status' => $_REQUEST['status2']

            ),

            array(

                'point' => $_REQUEST['point3'],

                'ratio' => $ratio3,

                'status' => $_REQUEST['status3']

            ),

            array(

                'point' => $_REQUEST['point4'],

                'ratio' => $ratio4,

                'status' => $_REQUEST['status4']

            )

        );

        $redis = initCacheRedis();

        $roomInifo = $this->db->getone('select id,lottery_type,title from un_room WHERE id = '.$_REQUEST['room_id']);

        $lottery_title = $redis->hGet("LotteryType:{$roomInifo['lottery_type']}",'name');

        $typeZh = [1 => '大小单双', 3 => '小单大双'];

        $staZh = [1 => '开启', 0 => '关闭'];

        $log_remarks = "赔率规则调整--房间:".$lottery_title.'--'.$roomInifo['title'].'--调整类型:'.$typeZh[$_REQUEST['type']];

        if(!empty($list[$_REQUEST['room_id']][$_REQUEST['type']])){

            foreach($list[$_REQUEST['room_id']][$_REQUEST['type']] as $k=>$value) {

                if($value['point'] != $data[$k]['point']) $log_remarks .= "--总注:".$value['point'].'->'.$data[$k]['point'];

                if($value['ratio'] != $data[$k]['ratio']) $log_remarks .= "--倍数:".$value['ratio'].'->'.$data[$k]['ratio'];

                if($value['status'] != $data[$k]['status']) $log_remarks .= "--状态:".$staZh[$value['status']].'->'.$staZh[$data[$k]['status']];

            }

        }

        //关闭redis链接

        deinitCacheRedis($redis);



        $list[$_REQUEST['room_id']][$_REQUEST['type']] = $data;

        $add = array(

            "value" => addslashes(json_encode($list))

        );

        $rt = $this->model->upRule($add, array("nid" => "oddsRule"));

        lg('up_rule',var_export(array(

            '$rt'=>$rt,

        ),1));

        if($rt) {

            admin_operation_log($this->admin['userid'], 70, $log_remarks);

            $this->refreshRedis("config", "all");

        }

        echo json_encode(array("rt" => $rt));

    }



    //投注限额

    public function bet() {

        $list = $this->model->bet();

        foreach ($list as $key => $value) {

            $list[$key]['limit_state_cn'] = $value['limit_state'] == 1 ? "启用" : "停用";

        }



        include template('list-bet');

    }



    //修改投注限额跳转

    public function upBet() {

        $list = $this->model->betById(array("id" => $_REQUEST['id']));

        include template('add-bet');

    }



    //修改投注限额

    public function doUpBet() {

        $data = array();

        $where = array("id" => $_REQUEST['id']);



        if ($_REQUEST['limit_state'] != '') {

            $data['limit_state'] = $_REQUEST['limit_state'];

        }

        if ($_REQUEST['lower'] != '') {

            $data['lower'] = $_REQUEST['lower'];

        }

        if ($_REQUEST['upper'] != '') {

            $data['upper'] = $_REQUEST['upper'];

        }

        $rt = $this->model->addBet($data, $where);

        lg('do_up_bet',"SQL::".$this->db->_sql());

        $this->refreshRedis("group", "all");

        echo json_encode(array("rt" => $rt));

    }



    //一键返水

    public function submitCalculateAll()

    {

        $addtime = $_REQUEST['addtime'];

        $flag    = 'adminsubmitCalculateAll';  //操作redis加锁key值



        //操作加锁,设置4小时后失效

        if (!superveneLock($flag, 60*30, 1)) {

            echo json_encode(array('rt' => 0, 'msg' => '操作失败，操作被锁定，请稍后重试！'));



            return;

        }



        $where = array(

            'addtime' => $addtime,

            'back' => 1,

            'state' => 1

        );

        $infoList = $this->model->calculate($where);

        foreach ($infoList as $info) {

            if ($info['state'] != 1 || $info['cntBack'] <= 0) {

                continue;

            }



            $selfType = 19;

            $sonType = 20;

            $teamType = 21;

            $nowtime = time();

            $ip = ip();



            $this->db->query("BEGIN");



            $un_account = $this->model->unAccount($info['user_id']); //用户账户金额

            //自身返水记录

            $log1 = array(

                'user_id' => $info['user_id'],

                'order_num' => $this->model->orderNo("FS"),

                'type' => $selfType,

                'money' => $info['selfBack'],

                'use_money' => $un_account['money'] + $info['selfBack'],

                'remark' => "{$addtime}:会员返水{$info['selfBack']}元",

                'addtime' => $nowtime,

                'addip' => $ip

            );



            //直属返水记录

            $log2 = array(

                'user_id' => $info['user_id'],

                'order_num' => $this->model->orderNo("FS"),

                'type' => $sonType,

                'money' => $info['sonBack'],

                'use_money' => $un_account['money'] + $info['selfBack'] + $info['sonBack'],

                'remark' => "{$addtime}:会员直属返水{$info['sonBack']}元",

                'addtime' => $nowtime,

                'addip' => $ip

            );



            //团队返水记录

            $log3 = array(

                'user_id' => $info['user_id'],

                'order_num' => $this->model->orderNo("FS"),

                'type' => $teamType,

                'money' => $info['teamBack'],

                'use_money' => $un_account['money'] + $info['selfBack'] + $info['sonBack'] + $info['teamBack'],

                'remark' => "{$addtime}:团队返水{$info['teamBack']}元",

                'addtime' => $nowtime,

                'addip' => $ip

            );



            $log = array(

                "state" => 2,

                "addtime" => $addtime,

                "user_id" => $info['user_id'],

                "opertime" => date('Y-m-d H:i:s'),

                "add_money" => $log1['money'] + $log2['money'] + $log3['money']

            );

            $rt = $this->model->transCalculate($log1, $log2, $log3, $log); //返水信息用事物入库



            if($rt==1){



                $this->db->query("COMMIT");

            }

            if($rt==-1){

                $this->db->query("ROLLBACK");

            }

        }

        

        //操作解锁 ,10秒后失效

        if (!superveneLock($flag, 10, 0)) {

            echo json_encode(array('rt' => 1, 'msg' => '返水操作成功，但该操作被锁定，4小时后自动解锁！'));

        

            return;

        }



        echo json_encode(array('rt' => 1, 'msg' => '返水操作成功！'));

    }



    //确认返水

    public function submitCalculate()

    {

        $username = $_REQUEST['username'];

        $addtime = $_REQUEST['addtime'];

        

        $flag   = 'adminsubmitCalculate' . $username;  //操作redis加锁key值

        

        //操作加锁,如果有人在操作，加锁失败

        if (preventSupervene($flag, 5)) {

            echo json_encode(array('rt' => 0, 'msg' => '操作失败，操作被锁定，请稍后重试！'));

        

            return;

        }

        

        $where = array(

            'account' => $username,

            'addtime' => $addtime,

            'state' => 1,

            'back' => 1

        );

        $infoList = $this->model->calculate($where);

        $info = $infoList[0];

        

        //判断是否已经返水

        if ($info['state'] != 1) {

            echo json_encode(array("rt" => 0, 'msg' => '已经确认或取消返水，请勿重复操作！'));

            return;

        }



        $selfType = 19;

        $sonType = 20;

        $teamType = 21;

        $nowtime = time();

        $ip = ip();



        $un_account = $this->model->unAccount($info['user_id']); //用户账户金额

        //自身返水记录

        $log1 = array(

            'user_id' => $info['user_id'],

            'order_num' => $this->model->orderNo("FS"),

            'type' => $selfType,

            'money' => $info['selfBack'],

            'use_money' => $un_account['money'] + $info['selfBack'],

            'remark' => "{$addtime}:会员返水{$info['selfBack']}元",

            'addtime' => $nowtime,

            'addip' => $ip

        );



        //直属返水记录

        $log2 = array(

            'user_id' => $info['user_id'],

            'order_num' => $this->model->orderNo("FS"),

            'type' => $sonType,

            'money' => $info['sonBack'],

            'use_money' => $un_account['money'] + $info['selfBack'] + $info['sonBack'],

            'remark' => "{$addtime}:会员直属返水{$info['sonBack']}元",

            'addtime' => $nowtime,

            'addip' => $ip

        );



        //团队返水记录

        $log3 = array(

            'user_id' => $info['user_id'],

            'order_num' => $this->model->orderNo("FS"),

            'type' => $teamType,

            'money' => $info['teamBack'],

            'use_money' => $un_account['money'] + $info['selfBack'] + $info['sonBack'] + $info['teamBack'],

            'remark' => "{$addtime}:团队返水{$info['teamBack']}元",

            'addtime' => $nowtime,

            'addip' => $ip

        );



        $log = array(

            "state" => 2,

            "addtime" => $addtime,

            "user_id" => $info['user_id'],

            "opertime" => date('Y-m-d H:i:s'),

            "add_money" => $log1['money'] + $log2['money'] + $log3['money']

        );

        $rt = $this->model->transCalculate($log1, $log2, $log3, $log); //返水信息用事物入库



        echo json_encode(array('rt' => $rt, 'msg' => '操作成功！'));

    }



    //取消返水   返0

    public function cancelCalculate() {

        $username = $_REQUEST['username'];

        $addtime = $_REQUEST['addtime'];

        $where = array(

            'account' => $username,

            'addtime' => $addtime,

            'back' => 1

        );

        $infoList = $this->model->calculate($where);

        $info = $infoList[0];



        $selfType = 19;

        $sonType = 20;

        $teamType = 21;

        $nowtime = time();

        $ip = ip();



        $un_account = $this->model->unAccount($info['user_id']); //用户账户金额



        $info['selfBack'] = 0;

        $info['sonBack'] = 0;

        $info['teamBack'] = 0;



        //自身返水记录

        $log1 = array(

            'user_id' => $info['user_id'],

            'order_num' => $this->model->orderNo("FS"),

            'type' => $selfType,

            'money' => $info['selfBack'],

            'use_money' => $un_account['money'] + $info['selfBack'],

            'remark' => "{$addtime}:会员返水{$info['selfBack']}元",

            'addtime' => $nowtime,

            'addip' => $ip

        );



        //直属返水记录

        $log2 = array(

            'user_id' => $info['user_id'],

            'order_num' => $this->model->orderNo("FS"),

            'type' => $sonType,

            'money' => $info['sonBack'],

            'use_money' => $un_account['money'] + $info['selfBack'] + $info['sonBack'],

            'remark' => "{$addtime}:会员直属返水{$info['sonBack']}元",

            'addtime' => $nowtime,

            'addip' => $ip

        );



        //团队返水记录

        $log3 = array(

            'user_id' => $info['user_id'],

            'order_num' => $this->model->orderNo("FS"),

            'type' => $teamType,

            'money' => $info['teamBack'],

            'use_money' => $un_account['money'] + $info['selfBack'] + $info['sonBack'] + $info['teamBack'],

            'remark' => "{$addtime}:团队返水{$info['teamBack']}元",

            'addtime' => $nowtime,

            'addip' => $ip

        );



        $log = array(

            "state" => 3,

            "addtime" => $addtime,

            "user_id" => $info['user_id'],

            "opertime" => date('Y-m-d H:i:s'),

            "add_money" => $log1['money'] + $log2['money'] + $log3['money']

        );

        $rt = $this->model->transCalculate($log1, $log2, $log3, $log); //返水信息用事物入库



        echo json_encode(array('rt' => $rt));

    }



    //特殊玩法设置

    public function special() {

        //获取一个默认的房间号

        $sql = "select id from un_room order by id asc";

        $first_room = $this->db->result($sql);



        //改成房间

        $room_id = empty($_REQUEST['room_id']) ? $first_room : $_REQUEST['room_id'];

        $oddsRt = $this->model->specialWay();

        $oddsList = json_decode($oddsRt['value'], true);



        //实例化redis

        $redis = initCacheRedis();



        $roomList = $this->db->getall('select id,lottery_type,special_way,title from un_room where lottery_type not in(7,8,12,13) order by id desc');



        foreach($roomList as $k=>$v){

            //彩种标题

            $lottery_title = $redis->hGet("LotteryType:{$v['lottery_type']}",'name');

            $roomList[$k]['lottery_title'] = $lottery_title;



            if($v['special_way']==''){

                $list = $oddsList[$v['lottery_type']];

                $croomSWSet=array(

                    'status'=>0,

                    'list'=>$list,

                );

                $this->db->query("update un_room set special_way='".json_encode($croomSWSet,JSON_UNESCAPED_UNICODE)."' where id=".$v['id']);

                unset($croomSWSet);

            }

        }



        //关闭redis链接

        deinitCacheRedis($redis);



        $croom = $this->db->getone('select * from un_room where id='.$room_id); //当前房间

        $croomSWSet= json_decode($croom['special_way'],1); //房间特殊玩法设置



        include template('list-special');

    }

    

    //特殊玩法修改设置

    public function editSpecial() 

    {

        $room_id  = $_REQUEST['room_id'];

        $way_name = $_REQUEST['way_name'];

        $oddsRt = $this->model->specialWay();

        $oddsList = json_decode($oddsRt['value'], true);



        $croom = $this->db->getone('select * from un_room where id='.$room_id); //当前房间

        $croomSWSet= json_decode($croom['special_way'],1); //房间特殊玩法设置

        //实例化redis

        $redis = initCacheRedis();

        $lottery_title = $redis->hGet("LotteryType:{$croom['lottery_type']}",'name');

        //关闭redis链接

        deinitCacheRedis($redis);



        include template('edit-special');

    }





    //特殊玩法单个是否禁用

    public function change_status() {

        $room_id = $_REQUEST['room_id']; //房间

        $special = $_REQUEST['special']; //玩法

        $is_disable = $_REQUEST['is_disable'] == 1 ? 1 : 0; //是否禁用

        $croom = $this->db->getone('select special_way from un_room where id='.$room_id); //当前房间

        $croomSWSet= json_decode($croom['special_way'],1); //房间特殊玩法设置

        foreach($croomSWSet['list'] as $k=>$v){

            if($k==$special){

                $croomSWSet['list'][$k]['is_disable'] = $is_disable;

            }

        }

        $rt = $this->db->query("update un_room set special_way='".json_encode($croomSWSet,JSON_UNESCAPED_UNICODE)."' where id=".$room_id);

        $this->refreshRedis("all", "all"); //刷新缓存

        $data=array( //调用双活接口

            'type'=>'update_odds',

            'id'=>$room_id,

            'json'=>encode(array('commandid' => 3024,'room_id'=>$room_id)),

        );

        send_home_data($data);

        echo json_encode(array("rt" => $rt));

    }



    //修改特殊玩法

    public function doSpecial() {

        //$lottery_type = $_REQUEST['lottery_type']; //彩种

        $room_id = $_REQUEST['room_id']; //房间

        $color = $_REQUEST['color']; //颜色

        $desc = $_REQUEST['desc']; //描述

        $special = $_REQUEST['special']; //玩法

        $state = $_REQUEST['state']; //开启状态

        

        if ($state != 1) $state = 0;



        $croom = $this->db->getone('select * from un_room where id='.$room_id); //当前房间

//        var_dump($croom);

        $croomSWSet= json_decode($croom['special_way'],1); //房间特殊玩法设置

        foreach($croomSWSet['list'] as $k=>$v){

            if($k==$special){

                if ($color != "") {

                    $croomSWSet['list'][$k]['color'] = $color;

                }

                if ($desc != "") {

                    $croomSWSet['list'][$k]['desc'] = $desc;

                }

                $croomSWSet['list'][$k]['is_disable'] = $state;

            }

        }

        $rt = $this->db->query("update un_room set special_way='".json_encode($croomSWSet,JSON_UNESCAPED_UNICODE)."' where id=".$room_id);



        $this->refreshRedis("all", "all");

        $data=array( //调用双活接口

            'type'=>'update_odds',

            'id'=>$room_id,

            'json'=>encode(array('commandid' => 3024,'room_id'=>$room_id)),

        );

        send_home_data($data);

        echo json_encode(array("rt" => $rt));

    }



    //特殊玩法开关

    public function upState() {

        $room_id = $_REQUEST['id'];

        $status = $_REQUEST['status'];

        $croom = $this->db->getone('select * from un_room where id='.$room_id); //当前房间

//        var_dump($croom);

        $croomSWSet= json_decode($croom['special_way'],1); //房间特殊玩法设置

        $croomSWSet['status']=$status;

        $rt = $this->db->query("update un_room set special_way='".json_encode($croomSWSet,JSON_UNESCAPED_UNICODE)."' where id=".$room_id);

        //$rt = $this->model->upState(array("id" => $id, "special_way" => $special_way));

        $this->refreshRedis("all", "all");

        $data=array( //调用双活接口

            'type'=>'update_odds',

            'id'=>$room_id,

            'json'=>encode(array('commandid' => 3024,'room_id'=>$room_id)),

        );

        send_home_data($data);

        echo json_encode(array("rt" => $rt));

    }



    //逆向投注开关

    public function reverse() {

        $rt = $this->model->reverse();

        $list = json_decode($rt['value'], true);

        include template('list-reverse');

    }



    //逆向投注设置

    public function upReverse() {

        $state = $_REQUEST['state'];

        $type = $_REQUEST['type'];

        $rt = $this->model->reverse();

        $json = json_decode($rt['value'], true);

        if (empty($json)) {

            //初始化

            $json[1]['name'] = '大小';

            $json[1]['state'] = "0";

            $json[2]['name'] = '单双';

            $json[2]['state'] = "0";

            $json[3]['name'] = '大单&小单&大双&小双';

            $json[3]['state'] = "0";

        }

        $json[$type]['state'] = $state;

        $res = $this->model->upReverse(array("value" => addslashes(json_encode($json))), array("nid" => "reverse"));

        $this->refreshRedis("allroom","all");

        echo json_encode(array('rt' => $res));

    }



    //设置三无人员限制页面

    public function waterLimitation() {

        $redis = initCacheRedis();

        $res = $redis->hGet("Config:three_no_return_limit",'value');

        if (!empty($res)) {

            $config = json_decode($res, true);

            foreach ($config as $val) {

                if (!empty($val['condition1']['xy28']['type'])) {

                    $type = explode(",", $val['condition1']['xy28']['type']);

                    $val['condition1']['xy28']['type'] = $type;

                }

                if (!empty($val['condition1']['jnd28']['type'])) {

                    $type = explode(",", $val['condition1']['jnd28']['type']);

                    $val['condition1']['jnd28']['type'] = $type;

                }



                if ($val['setType'] == 1) {

                    $selfList = $val;

                }

                if ($val['setType'] == 2) {

                    $teamList = $val;

                }

            }

        }

        deinitCacheRedis($redis);



        include template('waterLimitation');

    }



    //设置三无人员限制处理

    public function waterLimitAct() {

        $post_data = $_POST;

        $this->model->setAdminUser($this->admin);

        if ($this->model->threeNoReturnLimit($post_data)) {

            $arr['code'] = 0;

            $arr['msg'] = '操作成功';

        } else {

            $arr['code'] = -1;

            $arr['msg'] = '操作失败';

        }



        //刷新redis

        $this->refreshRedis('config', 'all');



        echo json_encode($arr);

    }



    /**

     * 层级返水

     * @return bool|mixed|void

     */

    public function layerBack(){

        $layer =$this->model->getUserLayer();

        include template('list-backset-layer');

    }



    /**

     * 添加/编辑 会员层级

     * @return bool|mixed|void

     */

    public function addUserLayer(){

        $id = $_REQUEST['id'];

        if(!empty($id)){

            $res = O("model")->db->getone("SELECT id, layer, logo, type, status, remark FROM `un_user_layer` WHERE `id` = {$id}");

        }



        include template('add-user-layer');

    }



    /**

     * 验证会员层级是否可用

     * @return bool|mixed|void

     */

    public function getLayer(){

        $layer = $_REQUEST['name'];

        $res = O("model")->db->result("SELECT id FROM `un_user_layer` WHERE `layer` = {$layer}");

        if($res){

            jsonReturn(array('status' => 1, 'msg' => "该层级已存在,请重新输入"));

        }else{

            jsonReturn(array('status' => 0, 'msg' => "该层级可以使用"));

        }

    }



    /**

     * 设置会员层级

     * @return bool|mixed|void

     */

    public function setLayer(){

        $id = $_REQUEST['id'];

        $data['layer'] = trim($_REQUEST['layer']);

        $data['logo'] = $_REQUEST['logo'];

        $data['type'] = $_REQUEST['type'];

        $data['status'] = $_REQUEST['status'];

        $data['remark'] = $_REQUEST['remark'];

        if(empty($id)){//添加

            $layer = O("model")->db->result("SELECT layer FROM `un_user_layer` WHERE `layer` = {$data['layer']}");

            if($layer){

                jsonReturn(array('status' => 1, 'msg' => "该层级已存在,请重新输入"));

            }



            $msg = "添加";

            $res = O("model")->db->insert("un_user_layer",$data);

        }else{//修改

            $msg = "修改";

            $res = O("model")->db->update("un_user_layer",$data,'id='.$id);

        }



        if($res){

            $this->refreshRedis('layer','all');

            jsonReturn(array('status' => 0, 'msg' => $msg."成功",'data'=>$data['layer']));

        }else{

            jsonReturn(array('status' => 1, 'msg' => $msg."失败"));

        }

    }



    /**

     * 会员层级配置

     * @return bool|mixed|void

     */

    public function layerConfig(){

        $id = $_REQUEST['id'];

        $res = $this->db->getone("SELECT id, layer, config FROM `un_user_layer` WHERE `id` = {$id}");

        $config = json_decode($res['config'],true);

        if (!empty($config)) {

            $arr = array_pop($config);

        }



        include template('add-user-layer-config');

    }



    /**

     * 会员层级配置列表

     * @return bool|mixed|void

     */

    public function layerConfigList(){

        $id = $_REQUEST['id'];

        $res = $this->db->getone("SELECT id, layer, config,type FROM `un_user_layer` WHERE `id` = {$id}");

        $config = json_decode($res['config'],true);

        $key = count($config);

        $config[$key-1]['del'] = 1;

        include template('list-user-layer-config');

    }



    /**

     * 设置会员层级配置

     * @return bool|mixed|void

     */

    public function setLayerConfig(){

        $data = array();

        $id = trim($_REQUEST['id']);

        $data['min_money'] = trim($_REQUEST['min_money']);

        $data['max_money'] = trim($_REQUEST['max_money']);

        $data['backwater'] = trim($_REQUEST['backwater']);

        $data['cid'] = time();

        //判断金额

        if($data['min_money'] >= $data['max_money']){

            jsonReturn(array('status' => 1, 'msg' => "最大金额必须大于最小金额"));

        }

        //判断最小金额

        $res = $this->db->result("SELECT config FROM `un_user_layer` WHERE `id` = {$id}");

        $config = json_decode($res,true);

        if (!empty($config)) {

            $arr = end($config);

            if ($data['min_money'] < $arr['max_money']) {

                jsonReturn(array('status' => 1, 'msg' => "该层级最小金额必须大于等于".$arr['max_money']));

            }

        }

        $config[] = $data;

        $res= $this->db->update("un_user_layer", ['config' => json_encode($config)], ['id' => $id]);

        if($res !== false){

            $this->refreshRedis('layer','all');

            jsonReturn(array('status' => 0, 'msg' => "保存成功"));

        }else{

            jsonReturn(array('status' => 1, 'msg' => "保存失败"));

        }

    }



    /**

     * 设置会员层级

     * @return bool|mixed|void

     */

    public function delLayerConfig(){

        $id = trim($_REQUEST['id']);

        $cid = trim($_REQUEST['cid']);



        //判断

        $res = $this->db->result("SELECT config FROM `un_user_layer` WHERE `id` = {$id}");

        $config = json_decode($res,true);

        if(!empty($config)) {

            foreach ($config as $k => $v) {

                if ($cid == $v['cid']) {

                    unset($config[$k]);

                }

            }

            $res = $this->db->update("un_user_layer",array('config'=>json_encode($config)),'id='.$id);

            if($res){

                $this->refreshRedis('layer','all');

                jsonReturn(array('status' => 0, 'msg' => "删除成功"));

            }else{

                jsonReturn(array('status' => 1, 'msg' => "删除失败"));

            }



        }

        jsonReturn(array('status' => 0, 'msg' => "删除成功"));

    }



    /**

     * 上传图标

     * @method GET

     * @return json

     */

    public function uploadImg() {

        $error = array();

        if ($_FILES['file']['error'] > 0) {

            jsonReturn(array('status' => 200000, 'data' => '图片上传失败'));

        } else {

            if ($_FILES['file']['size'] > 2097152) { // 图片大于2MB

                ErrorCode::errorResponse(ErrorCode::AVATAR_TOO_BIG);

            } else {

                $suffix = '';

                switch ($_FILES['file']['type']) {

                    case 'image/gif':

                        $suffix = 'gif';

                        break;

                    case 'image/jpeg':

                    case 'image/pjpeg':

                        $suffix = 'jpg';

                        break;

                    case 'image/bmp':

                        $suffix = 'bmp';

                        break;

                    case 'image/png':

                    case 'image/x-png':

                        $suffix = 'png';

                        break;

                    default:

                        jsonReturn(array('status' => 200001, 'data' => '图片格式不正确'));

                }



                $FileName = md5(time()) . "." . $suffix;



                $path = $this->getAvatarUrl($FileName, 0);



                if (!move_uploaded_file($_FILES['file']['tmp_name'], $path)) {

                    jsonReturn(array('status' => 200001, 'data' => '图片上传失败'));

                }

                jsonReturn(array('status' => 0, 'data' => "/" . C('upfile_path') . '/ulayer/' . $FileName));

            }

        }

    }



    private function getAvatarUrl($avatarFileName, $isRand = 1) {

        if (empty($avatarFileName)) {

            return '';

        }

        $avatarUrl = S_ROOT . C('upfile_path') . '/ulayer/';

        if ($isRand) {

            $avatarUrl .= ('?rand=' . time());

        }

        if (!file_exists($avatarUrl)) {

            @mkdir($avatarUrl, 0777, true);

        }



        return $avatarUrl . $avatarFileName;

    }



    public function editBack(){

        $type = $_REQUEST['type'];

        $roomlist = $this->model->roomBack();

        foreach ($roomlist as $key=>$val) {

            $backRate = json_decode($val['backRate'],true);

            if (!empty($backRate)) {

                if($backRate['type']==$type){

                    if (!empty($backRate)) {

                        $roomlist[$key]['backRate'] = array_pop($backRate);

                    } else {

                        $roomlist[$key]['backRate'] = $backRate;

                    }

                }

            }

        }

        $max = $_REQUEST['max']?:1;

        $room_id  = $_REQUEST['room_id'];

        include template('list-backset-edit');

    }



    /**

     * 修改足彩赔率为自动或手动

     * @author bell <bell.gao@wiselinkcn.com>

     * @copyright 2018-05-09 21:33:13

     */

    public function autoCharge(){

        $id = $_POST['id'];

        $match_id = $_POST['match_id'];

        $is_auto = $_POST['is_auto'];

        if ($is_auto == 0) {

            $data['is_auto'] = 1;

        } else {

            $data['is_auto'] = 0;

        }

        $res = $this->db->update("#@_cup_odds",$data,['id'=>$id,'match_id'=>$match_id]);

        if ($res !== false) {

            $this->refreshRedis('fb_odds','all');

            echo json_encode(['code' => 0, 'msg' => "修改成功"]);

        } else {

            echo json_encode(['code' => -1, 'msg' => "修改失败"]);

        }







    }



    /**

     * 一键手动赔率/自动赔率

     * @copyright 2018-05-22 12:04:35

     */

    public function allAuto(){

        $match_id = $_REQUEST['match_id'];

        $type = $_REQUEST['type'];



        $res = $this->db->update("#@_cup_odds",['is_auto' => $type],['match_id'=>$match_id]);

        if ($res !== false) {

            $this->refreshRedis('fb_odds','all');

            echo json_encode(['code' => 0, 'msg' => "修改成功"]);

        } else {

            echo json_encode(['code' => -1, 'msg' => "修改失败"]);

        }



    }



    public function startOrStop(){

        $id = $_REQUEST['id'];

        $match_id = $_REQUEST['match_id'];

        $state = $_REQUEST['state'];

        if (empty($id)) {

            $res = $this->db->update("#@_cup_odds",['state' => $state],['match_id'=>$match_id]);

        } else {

            if ($state == 0) {

                $data['state'] = 1;

            } else {

                $data['state'] = 0;

            }

            $res = $this->db->update("#@_cup_odds",$data,['id'=>$id,'match_id'=>$match_id]);

        }



        if ($res !== false) {

            $this->refreshRedis('fb_odds','all');

            echo json_encode(['code' => 0, 'msg' => "修改成功"]);

        } else {

            echo json_encode(['code' => -1, 'msg' => "修改失败"]);

        }

    }



    /**

     * 获取足彩赔率历史

     * @copyright 2018-05-11 17:40:48

     * @return void

     */

    public function cupOddsLog(){

        $match_id = $_REQUEST['match_id'];

        $way = $_REQUEST['way'];

        $where = ['match_id' => $match_id,'way' => $way];

        $count = $this->model->getCount("#@_cup_odds_log",$where);

        $pageSize = 20;

        $page = new page($count, $pageSize, "?m=admin&c=odds&a=cupOddsLog", $where);

        $show = $page->show();

        $filed = 'id,way,odds,handicap,add_time';

        $order = 'add_time desc';

        $limit = $page->offer.",".$pageSize;

        $data = $this->model->getListNew($filed, $where, $order, $limit, "#@_cup_odds_log");

        foreach ($data as $key=>$val){

            $data[$key]['add_time'] = date("Y-m-d H:i:s",$val['add_time']);

        }

        include template('list-odds-log');

    }

}

