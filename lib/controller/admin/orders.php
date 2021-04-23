<?php

/**
 * @copyright			(C) 2013 CHENGHUITONG.COM
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'pages.php';

class OrdersAction extends Action {

    private $model;
    public $admin;
    public $parent_string = '';

    public function __construct() {
        parent::__construct();
        $this->model = D('admin/orders');
        $this->return = array(
            'code' => 0,
            'msg' => '',
            'data' => '',
            'pageshow' => ''
        );
    }

    public function t(){
        $sql = "select id from un_user WHERE parent_id!=0";
        $re  = $this->db->getall($sql);
        $res='';
        foreach ($re as $k=>$v)
        {
//            $pstr="";
            $res =  $this->tt($v['id']);
            dump($res);
        }
    }

    public function tt($uid){

        $sql = "select parent_id from un_user where id={$uid}";
        $re  = $this->db->result($sql);
        if(!empty($re)){
//            $this->parent_string = $re;
//        }else{
            $this->parent_string .= $this->tt($re);
        }

        return $this->parent_string;
        //        $pstr=$re;
//        if($re==0){
//            $pstr=$re;
//        }

    }

    //后台手动回滚订单
    public function order_call_back(){
//        $redis = initCacheRedis();
//        $re = $redis->hGet('Config:cancal_callback_order','value');
//        deinitCacheRedis($redis);
//        $role = decode($re);
//        $role = explode(',',$role['calcal_order']);
//        if(in_array($this->admin['roleid'],$role)){
//            $is_supper=1;
//        }else{
//            $is_supper=0;
//        }
        $is_supper=is_supper($this->admin['roleid'],'callback');
        $lottery_type = $_REQUEST['lottery_type'];
        $uid = $this->admin['userid'];
        $url  =  C('home_url')."/index.php?m=api&c=order&a=ordersCallBack";

        if(in_array($lottery_type,array(12))){
            $match_id = $_REQUEST['match_id'];
            $match_status = $_REQUEST['match_status'];
            $sdata =array(
                'lottery_type'=>$lottery_type,
                'match_id'=>$match_id,
                'match_status'=>$match_status,
                'uid'=>$uid,
                'is_supper'=>$is_supper,
            );
        }else{
            $issue = $_REQUEST['issue'];
            $sdata =array(
                'lottery_type'=>$lottery_type,
                'issue'=>$issue,
                'uid'=>$uid,
                'is_supper'=>$is_supper,
            );
        }

//        $json = json_encode(array('lottery_type'=>$lottery_type,'issue'=>$issue,'uid'=>$uid),JSON_UNESCAPED_UNICODE);
//        $is_supper = ($this->admin['userid']==1)?1:0; //超级管理员

        echo signa($url,$sdata);
    }

    //后台取消订单
    public function cancal_order(){
//        $redis = initCacheRedis();
//        $re = $redis->hGet('Config:cancal_callback_order','value');
//        deinitCacheRedis($redis);
//        $role = decode($re);
//        $role = explode(',',$role['calcal_order']);
//        if(in_array($this->admin['roleid'],$role)){
//            $is_supper=1;
//        }else{
//            $is_supper=0;
//        }
        $is_supper=is_supper($this->admin['roleid'],'calcal_order');
        $id=$_POST['id'];
        $re = $this->db->getone("select lottery_type,room_no,user_id,order_no,issue from un_orders where id={$id} and award_state=0 and state=0");
        if(empty($re)){
            echo encode(array('err'=>1,'msg'=>'已开奖或已被撤单'));
            return false;
        }
//        $is_supper = ($this->admin['userid']==1)?1:0;
        $data=array(
            'lottery_type'=>$re['lottery_type'],
            'api_id'=>'3016',
            'room_id'=>$re['room_no'],
            'issue'=>$re['issue'],
            'uid'=>$re['user_id'],
            'order_no'=>$re['order_no'],
            'is_admin'=>1,
            'admin_name'=>$this->admin['username'],
            'is_supper'=>$is_supper,
        );
        lg('cancal_orders_log',var_export(array(
            '$re'=>$re,
            '$data'=>$data,
        ),1));
        $re = signa(C('app_home').'?m=api&c=workerman&a=cancal_orders',$data); //直接用接口撤单
        echo $re;
    }


    //订单列表
    public function order()
    {
        //实例化redis
        $redis = initCacheRedis();
    
        try {
            $where = $_REQUEST; //搜索条件
            unset($where['m']);
            unset($where['c']);
            unset($where['a']);
            
            $where['s_time'] = empty($where['s_time']) ? date('Y-m-d') : date('Y-m-d',strtotime($where['s_time']));
            $where['e_time'] = empty($where['e_time']) ? date('Y-m-d') : date('Y-m-d',strtotime($where['e_time']));

            $quick = getParame('quick',0,0,'int');
            switch ($quick) {
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
                default:
                    $where['s_time'] = strtotime(getParame('s_time',0,date('Y-m-d')));
                    $where['e_time'] = strtotime(getParame('e_time',0,date('Y-m-d 23:59:59')));
                    break;
            }

            $pagesize = 20;
            $where['page_size'] = $pagesize;
            $where['page'] = empty($where['page']) ? 1 : $where['page'];
            $where['page_start'] = $where['page_size'] * ($where['page'] - 1);
            $list = $this->model->listOrder($where);

            $where['s_time'] = date('Y-m-d H:i:s', $where['s_time']);
            $where['e_time'] = date('Y-m-d H:i:s', $where['e_time']);

            foreach($list as $key=>$val)
            {
//                $flag = $this->db->getone("select flag from un_user_login_log where user_id = {$val['user_id']} and addtime < {$val['addtime']} order by addtime DESC");
                //                    dump("select flag from un_user_login_log where user_id = {$val['user_id']} and addtime < {$val['addtime']} order by addtime DESC");
//                $list[$key]['flag'] = $flag['flag'];

                //彩种标题
                $list[$key]['lottery_title'] = $redis->hGet("LotteryType:{$val['lottery_type']}",'name');
            }

            $issues = array();//查询期号
            $pageSum = 0; //当前页交易金额
            $gainSum = 0; //总计盈亏

            //投注类型:2半场,4全场,6加时,8点球
            $pressType = array(
                2=>'半场',
                4=>'全场',
                6=>'加时',
                8=>'点球',
            );

            foreach ($list as $key => $value) {

                if($value['lottery_type'] == 12){
                    $sql = "SELECT 'bi_feng','type','pan_kou','odds','result_bi_feng' FROM `un_orders_football` WHERE order_id={$value['id']}";
                    $res  = $this->db->getone($sql);
                    $list[$key]['bi_feng'] = $res['bi_feng'];
                    $list[$key]['type'] = $pressType[$res['type']];
                    $list[$key]['pan_kou'] = $res['pan_kou'];
                    $list[$key]['odds'] = $res['odds'];
                    $list[$key]['result_bi_feng'] = $res['result_bi_feng'];
                }

                //判断投注类型逻辑
                $list[$key]['bet_type'] = '正常投注';
                if(!empty($value['chase_number'])){
                    $list[$key]['bet_type'] = '追号';
                }else{
                    if(!empty($value['ext_b'])){
                        $list[$key]['bet_type'] = '跟投';
                    }
                }

                $issues[$value['lottery_type']][] = $value['issue'];
                $list[$key]['award_state_cn'] = "";
                $list[$key]['single_money'] = ($value['single_money'])>0?$value['single_money']:$value['money'];

                $gain = 0;
                if ($value['state'] == 1) {
                    $list[$key]['award_state_cn'] = "撤单";
                } else {
                    $pageSum += $value['money'];
                    if ($value['award_state'] == 0) {
                        $list[$key]['award_state_cn'] = "待开奖";
                        $noOpen += $value['money'];
                    }elseif ($value['award_state'] == 1) {
                        $list[$key]['award_state_cn'] = "未中奖";
                        $gain = $value['award'] - $value['money'];
                        $yeOpen += $value['money'];
                    }elseif($value['award_state'] == 2) {
                        $list[$key]['award_state_cn'] = "已中奖";
                        $gain = $value['award'] - $value['money'];
                    }elseif($value['award_state'] == 3) {
                        $list[$key]['award_state_cn'] = "和局";
                        $gain = $value['award'] - $value['money'];
                    }
                }
                $gainSum += $gain;
                $list[$key]['gain'] = bcadd($gain,0.00,2);
                $list[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);
            }
            $issue = array();
            if(!empty($issues)){
                foreach ($issues as $k=>$v){
                    $s_issues = "'".implode("','",array_unique($v))."'";
                    $res = $this->getAwardResult($k,$s_issues); //这里取开奖结果
                    foreach ($res as $ks => $vs){
                        $issue[$k][$vs['issue']]['open_result'] = $vs['open_result'];
                        $issue[$k][$vs['issue']]['open_time'] = $vs['open_time'];
                    }
                }
            }
    

            // 房间信息 获取实时在线房间人数后期调整 ???
            $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
    
            //缓存一个hash数组，键为彩种id，值为彩种名称
            $lottery_map = [];
            foreach ($LotteryTypeIds as $lottery_tmp_v) {
                $tmp_lottery_title = $redis->hGet("LotteryType:{$lottery_tmp_v}", 'name');
                $lottery_map[$lottery_tmp_v] = $tmp_lottery_title;
            }
    
            $Room = array();
            foreach ($LotteryTypeIds as $v){
                $gameInfo = $redis->hGetAll("LotteryType:".$v);
                $PRoomIds = $redis->lRange("PublicRoomIds".$v, 0, -1);
                foreach ($PRoomIds as $k){
                    $PRoomInfo = $redis -> hMGet("PublicRoom".$v.":".$k,array('id','title'));
    
                    $Room[$PRoomInfo['id']] = $PRoomInfo['title'];
                }
                $SRoomIds = $redis->lRange("PrivateRoomIds".$v, 0, -1);
                foreach ($SRoomIds as $sk){
                    $SRoomInfo = $redis -> hMGet("PrivateRoom".$v.":".$sk,array('id','title'));
                    $Room[$SRoomInfo['id']] = $SRoomInfo['title'];
                }
            }
    
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
        $play = json_encode($this->plays(), true);
        $roomInfo = $this->db->getall("select id,title,lottery_type from un_room");
    
        foreach ($roomInfo as &$each_info) {
            // //彩种标题-方案a
            // $lottery_title = $redis->hGet("LotteryType:{$each_info['lottery_type']}",'name');
            // $each_info['lottery_title'] = $lottery_title;
    
            //彩种标题-方案b（效率更高）
            $each_info['lottery_title'] = $lottery_map[$each_info['lottery_type']];
        }
    
        //关闭redis链接
        deinitCacheRedis($redis);

        $gainSum = number_format($gainSum, 2, '.', '');
        $pageSum = number_format($pageSum, 2, '.', '');
    
        $cancal_order_supper = is_supper($this->admin['roleid'],'calcal_order');

        $post_run_data = [
            'report_type'=> 2,
            'platform_id'=> C('platform_id'),
            'modules_id' => '1011',
            'domain_name'=> C('app_home'),
            'url'=> $_SERVER["QUERY_STRING"],
            'php_run_time' => intval(str_replace("ms","",getRunTime(microtime(true),$this->tong_ji_start_time))),
            'app_type'=>4,
            'network'=>'line'
        ];
        include template('list-order');
    }

    //订单统计
    public function listOrderPageFB()
    {
        session_write_close();
        try {
            $where = $_REQUEST; //搜索条件
            unset($where['m']);
            unset($where['c']);
            unset($where['a']);
            $where['s_time'] = empty($where['s_time']) ? date('Y-m-d') : date('Y-m-d',strtotime($where['s_time']));
            $where['e_time'] = empty($where['e_time']) ? date('Y-m-d') : date('Y-m-d',strtotime($where['e_time']));
            $pagesize = 20;
            $where['page_size'] = $pagesize;
            $where['is_fb'] = 1;
            $where['page'] = empty($where['page']) ? 1 : $where['page'];
            $where['page_start'] = $where['page_size'] * ($where['page'] - 1);


            $listCnt = $this->model->cntOrder($where);

            $url = '?m=admin&c=orders&a=order_fb';
            $page = new pages($listCnt, $pagesize, $url, $where);
            $show = $page->show();

            $tj = $this->model->orderTJ2($where);
            //$tj = ["noOpen" => 100, "yeOpen" => 100, "cancel" => 100, "bet" => 100, "bonus" => 100, "gain" => 10];
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }

        $tj['gain'] = number_format($tj['gain'], 2, '.', '');

        $cancal_order_supper = is_supper($this->admin['roleid'],'calcal_order');

        $totalPage = '<span class="back-page" style="' . Session::get('style') . '">合计：';
        $totalPage .= '待开奖：<b>' . (empty($tj['noOpen']) ? 0 : $tj['noOpen']) . '</b>';
        $totalPage .= '已开奖：<b>' . (empty($tj['yeOpen']) ? 0 : $tj['yeOpen']) . '</b>';
        $totalPage .= '撤单：<b>' . (empty($tj['cancel']) ? 0 : $tj['cancel']) . '</b>';
        $totalPage .= '投注(含未开奖)：<b>' . (empty($tj['bet']) ? 0 : $tj['bet']) . '</b>';
        $totalPage .= '奖金：<b>' . (empty($tj['bonus']) ? 0 : $tj['bonus']) . '</b>';
        $totalPage .= '盈亏：';
        if ($tj['gain'] == 0) {
            $totalPage .= '0';
        }else {
            if ($tj['gain'] > 0) {
                $totalPage .= '<font style="color: #0099ff;"><b>' . $tj['gain'] . '</b></font>';
            }else {
                $totalPage .= '<font style="color: #FF3300;"><b>' . $tj['gain'] . '</b></font>';
            }
            $totalPage .= '</span>';
        }

        echo json_encode(['code' => 0, 'msg' => '', 'data' => ['listPage' => $totalPage, 'show' => $show]]);
        return;
    }
    
    //订单统计
    public function listOrderPage()
    {
        session_write_close();
        try {
            $where = $_REQUEST; //搜索条件
            unset($where['m']);
            unset($where['c']);
            unset($where['a']);
            $where['s_time'] = empty($where['s_time']) ? date('Y-m-d') : date('Y-m-d',strtotime($where['s_time']));
            $where['e_time'] = empty($where['e_time']) ? date('Y-m-d') : date('Y-m-d',strtotime($where['e_time']));
            $pagesize = 20;
            $page_size =$where['page_size'];
            $page =$where['page'];
            unset($where['page_size']);
            unset($where['page']);
            unset($where['page_start']);

            $where['s_time'] = strtotime(getParame('s_time',0,date('Y-m-d')));
            $where['e_time'] = strtotime(getParame('e_time',0,date('Y-m-d 23:59:59')));
            $listCnt = $this->model->listOrder($where, 2);
            $listCnt = $listCnt[0]['count_num'];
//            $listCnt = count($this->model->listOrder($where));
            $where['page_size'] = $pagesize;
            $where['page'] = empty($page) ? 1 : $page;
            $where['page_start'] = $page_size * ($page - 1);

            $url = '?m=admin&c=orders&a=order';
            $where['s_time'] = date('Y-m-d H:i:s',$where['s_time']);
            $where['e_time'] = date('Y-m-d H:i:s',$where['e_time']);
            $page = new pages($listCnt, $pagesize, $url, $where);

            $show = $page->show();
            
//            $tj = $this->model->orderTJ2($where);
            //$tj = ["noOpen" => 100, "yeOpen" => 100, "cancel" => 100, "bet" => 100, "bonus" => 100, "gain" => 10];
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
    
//        $tj['gain'] = number_format($tj['gain'], 2, '.', '');
    
        $cancal_order_supper = is_supper($this->admin['roleid'],'calcal_order');
    
//        $totalPage = '<span class="back-page" style="' . Session::get('style') . '">合计：';
//        $totalPage .= '待开奖：<b>' . (empty($tj['noOpen']) ? 0 : round($tj['noOpen'], 2)) . '</b>';
//        $totalPage .= '已开奖：<b>' . (empty($tj['yeOpen']) ? 0 : round($tj['yeOpen'], 2)) . '</b>';
//        $totalPage .= '撤单：<b>' . (empty($tj['cancel']) ? 0 : round($tj['cancel'], 2)) . '</b>';
//        $totalPage .= '投注(含未开奖)：<b>' . (empty($tj['bet']) ? 0 : round($tj['bet'], 2)) . '</b>';
//        $totalPage .= '奖金：<b>' . (empty($tj['bonus']) ? 0 : round($tj['bonus'], 2)) . '</b>';
//        $totalPage .= '盈亏：';
//        if ($tj['gain'] == 0) {
//            $totalPage .= '0';
//        }else {
//            if ($tj['gain'] > 0) {
//                $totalPage .= '<font style="color: #0099ff;"><b>' . round($tj['gain'], 2) . '</b></font>';
//            }else {
//                $totalPage .= '<font style="color: #FF3300;"><b>' . round($tj['gain'], 2) . '</b></font>';
//            }
//            $totalPage .= '</span>';
//        }
    
        echo json_encode(['code' => 0, 'msg' => '', 'data' => [/*'listPage' => $totalPage, */'show' => $show]]);
        return;
    }
    
    /*
    public function order() {
    
        //实例化redis
        $redis = initCacheRedis();
    
        try {
            $where = $_REQUEST; //搜索条件
            unset($where['m']);
            unset($where['c']);
            unset($where['a']);
            if(empty($where['search'])){
                $where['s_time'] = empty($where['s_time']) ? date('Y-m-d 00:00:00') : $where['s_time'];
                $where['e_time'] = empty($where['e_time']) ? date('Y-m-d 23:59:59') : $where['e_time'];
            }
    
            //            dump($where);
    
            $pagesize = 20;
            $listCnt = $this->model->cntOrder($where);
            $url = '?m=admin&c=orders&a=order';
            $page = new pages($listCnt, $pagesize, $url, $where);
            $show = $page->show();
    
            $where['page_start'] = $page->offer;
            $where['page_size'] = $pagesize;
            $list = $this->model->listOrder($where);
            if(empty($list))
            {
                $tj = $this->model->orderTJ();
            }
            else
            {
                foreach($list as $key=>$val)
                {
                    $flag = $this->db->getone("select flag from un_user_login_log where user_id = {$val['user_id']} and addtime < {$val['addtime']} order by addtime DESC");
                    //                    dump("select flag from un_user_login_log where user_id = {$val['user_id']} and addtime < {$val['addtime']} order by addtime DESC");
                    //                    dump($flag['flag']);
                    $list[$key]['flag'] = $flag['flag'];
    
                    //                    dump($list[$key]['flag']);
                    //彩种标题
                    $list[$key]['lottery_title'] = $redis->hGet("LotteryType:{$val['lottery_type']}",'name');
                }
    
                $tj = $this->model->orderTJ2($where);
                $issues = array();//查询期号
                $pageSum = 0; //当前页交易金额
                $gainSum = 0; //总计盈亏
                foreach ($list as $key => $value) {
                    $issues[$value['lottery_type']][] = $value['issue'];
                    $list[$key]['award_state_cn'] = "";
                    $list[$key]['single_money'] = ($value['single_money'])>0?$value['single_money']:$value['money'];
    
                    $gain = 0;
                    if ($value['state'] == 1) {
                        $list[$key]['award_state_cn'] = "撤单";
                    } else {
                        $pageSum += $value['money'];
                        if ($value['award_state'] == 0) {
                            $list[$key]['award_state_cn'] = "待开奖";
                            $noOpen += $value['money'];
                        }elseif ($value['award_state'] == 1) {
                            $list[$key]['award_state_cn'] = "未中奖";
                            $gain = $value['award'] - $value['money'];
                            $yeOpen += $value['money'];
                        }elseif($value['award_state'] == 2) {
                            $list[$key]['award_state_cn'] = "已中奖";
                            $gain = $value['award'] - $value['money'];
                        }elseif($value['award_state'] == 3) {
                            $list[$key]['award_state_cn'] = "和局";
                            $gain = $value['award'] - $value['money'];
                        }
                    }
                    $gainSum += $gain;
                    $list[$key]['gain'] = bcadd($gain,0.00,2);
                    $list[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);
                }
                $issue = array();
                if(!empty($issues)){
                    foreach ($issues as $k=>$v){
                        $s_issues = "'".implode("','",array_unique($v))."'";
                        $res = $this->getAwardResult($k,$s_issues); //这里取开奖结果
                        foreach ($res as $ks => $vs){
                            $issue[$k][$vs['issue']] = $vs['open_result'];
                        }
                    }
                }
            }
    
            //            dump($list);
    
            // 房间信息 获取实时在线房间人数后期调整 ???
            $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
    
            //缓存一个hash数组，键为彩种id，值为彩种名称
            $lottery_map = [];
            foreach ($LotteryTypeIds as $lottery_tmp_v) {
                $tmp_lottery_title = $redis->hGet("LotteryType:{$lottery_tmp_v}", 'name');
                $lottery_map[$lottery_tmp_v] = $tmp_lottery_title;
            }
    
            $Room = array();
            foreach ($LotteryTypeIds as $v){
                $gameInfo = $redis->hGetAll("LotteryType:".$v);
                $PRoomIds = $redis->lRange("PublicRoomIds".$v, 0, -1);
                foreach ($PRoomIds as $k){
                    $PRoomInfo = $redis -> hMGet("PublicRoom".$v.":".$k,array('id','title'));
    
                    $Room[$PRoomInfo['id']] = $PRoomInfo['title'];
                }
                $SRoomIds = $redis->lRange("PrivateRoomIds".$v, 0, -1);
                foreach ($SRoomIds as $sk){
                    $SRoomInfo = $redis -> hMGet("PrivateRoom".$v.":".$sk,array('id','title'));
                    $Room[$SRoomInfo['id']] = $SRoomInfo['title'];
                }
            }
    
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
        $play = json_encode($this->plays(), true);
        $roomInfo = $this->db->getall("select id,title,lottery_type from un_room");
    
        foreach ($roomInfo as &$each_info) {
            // //彩种标题-方案a
            // $lottery_title = $redis->hGet("LotteryType:{$each_info['lottery_type']}",'name');
            // $each_info['lottery_title'] = $lottery_title;
    
            //彩种标题-方案b（效率更高）
            $each_info['lottery_title'] = $lottery_map[$each_info['lottery_type']];
        }
    
        //关闭redis链接
        deinitCacheRedis($redis);
    
        $tj['gain'] = number_format($tj['gain'], 2, '.', '');
        $gainSum = number_format($gainSum, 2, '.', '');
        $pageSum = number_format($pageSum, 2, '.', '');
    
        $cancal_order_supper = is_supper($this->admin['roleid'],'calcal_order');
    
        include template('list-order');
    }
    */

    //资金明细列表
    public function money() {
        try {
            $where = $_REQUEST; //搜索条件
            unset($where['m']);
            unset($where['c']);
            unset($where['a']);
            if(empty($where['search'])){
                $where['s_time'] = empty($where['s_time']) ? date('Y-m-d') : $where['s_time'];
                $where['e_time'] = empty($where['e_time']) ? date('Y-m-d') : $where['e_time'];
            }
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


            if (empty($where['page_size'])) {
                $pagesize = 20;
            } else {
                $pagesize = $where['page_size'];
            }
            
            $listSum = $this->model->cntMoney($where);
            $listCnt = $listSum['cnt'];

            $url = '?m=admin&c=orders&a=money';
            $page = new pages($listCnt, $pagesize, $url, $where);

            //暂时关闭可选择偏移量的方法调用
            // $show = $page->shows();
            $show = $page->show();

            $where['page_start'] = $page->offer;
            $where['page_size'] = $pagesize;
            $list = $this->model->listMoney($where);

            $type = $this->model->listType(); //交易类型  classid-2
            payLog('b.txt',print_r($type,true). "==598".print_r($list,true));

            $pageSum = 0; //当前页交易金额
            foreach ($list as $key => $value) {
                $pageSum += $value['money'];
                $list[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);
            }
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }

        include template('list-money');
    }

    //局分析
    public function analys() {
        $today = date('Y-m-d');
        $list = $this->model->lottyList();

        include template('list-analys');
    }

    //局分析查询
    public function analysSearch() {
        $where = $_REQUEST;
        $list = $this->model->analys($where);
        echo json_encode($list);
    }

    //局分析明细
    public function analysData() {
        $where = array(
            'issue' => $_POST['issue'],
            'lottery_type' => $_POST['lottery_type']
        );
        $array = $this->plays($_POST['lottery_type']);
        $orders = $this->model->analysData($where);
        foreach ($orders as $value) {
            $array[$value['way']]['cnt'] ++;
            $array[$value['way']]['money'] += $value['money'];
            $array[$value['way']]['award'] += $value['award'];
            $array[$value['way']]['award_fd'] += $value['award'] * 0.01; //返点率,需要查询数据字典
        }

        $data = array();
        $data['cnt'] = count($orders);
        $data['list'] = $array;

        echo json_encode($data);
    }

    //玩法   配置数据
    public function plays($type='1,2,3,4,5,6,7,8,9,10,11') {
        if ($type == 12) {
            $sql = "SELECT DISTINCT(way) FROM `un_cup_odds`";
            $res = $this->db->getall($sql);
        } else {
            $sql = "SELECT way FROM `un_odds_copy` WHERE `lottery_type` IN ({$type}) ORDER BY type";
            $res = $this->db->getall($sql);
        }

        foreach ($res as $v){
            $array = explode("_",$v['way']);
            if (in_array($array[0],['三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中','五不中','六不中','七不中','八不中','九不中','十不中','二尾连中','三尾连中','四尾连中','二尾连不中','三尾连不中','四尾连不中'])) {
                $v['way'] = $array[0];
            }
            if (!in_array($array[0],['三中二之中三','二中特之中特'])) {
                $arr[$v['way']] = array();
            }
        }
        return $arr;
    }
    /*
     * 返回房间的玩法
     */
    public function wanfa(){
        $room=$_REQUEST['room'];
        $lottery_type=$_REQUEST['lottery_type'];
        if(empty($room)||empty($lottery_type)){
            echo -1;
            exit();
        }
        if ($lottery_type == 12) {
            $sql = "select way,type,lottery_type from `un_cup_odds` group by way";
            $res = $this->db->getall($sql);
        } else {
            $res=$this->db->getall('select way from un_odds where lottery_type='.$lottery_type.' and room='.$room);
        }

        if($lottery_type==1 || $lottery_type==3){
            $res[]['way']="组合";
            $res[]['way']="单点";
            $res[]['way']="极值";
        }
        if($res){
            echo json_encode($res,JSON_UNESCAPED_UNICODE);
            exit();
        }else{
            echo -2;
            exit();
        }
    }

    //测试数据库数据
    public function testDB() {
        $issue = $_REQUEST['issue'];
        $rt = $this->model->testDB("select * from un_user where username = '{$issue}'");
    }

    /**
     * 会员投注百分比
     * @return bool|mixed|void
     */
    public function percentage()
    {
        $redis = initCacheRedis();
        $lottery_ids = $redis->lRange("LotteryTypeIds",0,-1);
        foreach ($lottery_ids as $val) {
            $arr = [
                'name'=> $redis->hGet("LotteryType:{$val}",'name'),
                'id'  => $val
            ];
            $lottery_info[] = $arr;
        }
        //关闭redis链接
        deinitCacheRedis($redis);

        $start_time = getParame('start_time', 0, date('Y-m-d'));
        $lottery_type = getParame('lottery_type', 0, 1);
        $username = getParame('username', 0);

        $map = array();
        $map['start_time'] = strtotime($start_time);
        $map['end_time'] = $map['start_time']+86399;
        $quick = getParame('quick', 0);
        if($quick != "0"&&$quick !=""){
            switch ($quick){
                case 1:
                    $map['start_time'] = strtotime(date("Y-m-d",strtotime("0 day")));
                    $map['end_time'] = $map['start_time'] + 86399;
                    break;
                case 2:
                    $map['start_time'] = strtotime(date("Y-m-d",strtotime("-1 day")));
                    $map['end_time'] = $map['start_time'] + 86399;
                    break;
                case 3:
                    $map['start_time'] = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));
                    $map['end_time'] = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;
                    break;
                case 4:
                    $map['start_time'] = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                    $map['end_time'] = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;
                    break;
                case 5:
                    $map['start_time'] = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));
                    $map['end_time'] = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));
                    break;
            }
        }
        $map['username'] = $username;
        $map['lottery_type'] = $lottery_type;
        $where = "O.addtime BETWEEN {$map['start_time']} AND {$map['end_time']}";
        if(!empty($username)) $where.= " AND U.username='{$username}'";
        if(!empty($lottery_type)) $where.= " AND O.lottery_type='{$lottery_type}'";

        //总数
        $sql = "SELECT COUNT(DISTINCT O.user_id) as cnt FROM un_orders AS O INNER JOIN un_user AS U ON O.user_id = U.id WHERE {$where} AND O.state = 0 AND O.reg_type NOT IN (0,8,9,11)";
        $cnt = $this->db->result($sql);
        $pagesize = 20;
        $page = new pages($cnt, $pagesize, '', $_REQUEST);
        $show = $page->show();

        //当日彩种投注会员列表
        $sql = "SELECT DISTINCT(O.user_id), U.username FROM 	un_orders AS O INNER JOIN un_user AS U ON O.user_id = U.id WHERE {$where} AND O.state = 0 AND O.reg_type NOT IN (0, 8, 9, 11)  LIMIT {$page->offer}, {$pagesize}";
        $user = $this->db->getall($sql);
        $userids = array();
        $usernames = array();

        foreach ($user as $v){
            $userids[] = $v['user_id'];
            $usernames[$v['user_id']]['username'] = $v['username'];

            //会员当日所有彩种投注额
            $sql = "select sum(money) from un_orders where user_id = {$v['user_id']} and addtime between {$map['start_time']} and {$map['end_time']} and state = 0";
            $usernames[$v['user_id']]['total'] = $this->db->result($sql);
        }

        if(!empty($userids)){
            $ids = implode(',',$userids);
            $where1 = "O.addtime BETWEEN {$map['start_time']} AND {$map['end_time']} AND O.lottery_type ={$map['lottery_type']}";
            //投注明细（具体投注项   彩种  投注额）
            $sql = "SELECT O.user_id, O.way, O.lottery_type, O.money FROM un_orders AS O WHERE {$where1} AND O.user_id IN($ids) AND  O.state = 0";
            $list = $this->db->getall($sql);

            $where2 = "addtime = '{$_REQUEST['start_time']}'";
            $sql2 = "SELECT user_id FROM un_back_log WHERE {$where2} AND user_id IN($ids) AND  cntBack > 0";
            $list2 = $this->db->getall($sql2);
            $back_users = array();
            foreach ($list2 as $v){
                $back_users[] = $v['user_id'];
            }

            $way_info = $this->loadGameWayNew();
            $except_way = [
                "冠亚", "三中二", "三全中", "二全中", "二中特", "特串", "五不中", "六不中", "七不中", "八不中",
                "九不中", "十不中", "二尾连中", "三尾连中", "四尾连中", "二尾连不中", "三尾连不中", "四尾连不中", "二肖连中",
                "三肖连中", "四肖连中", "二肖连不中", "三肖连不中", "四肖连不中"
            ];
            foreach ($list as $k => $v){

                $arr = explode('_',$v['way']); //多个号码一起时
                if (in_array($arr[0],$except_way)) {
                    $v['way'] = $arr[0];
                }
                
                if(in_array($v['user_id'],$back_users)){
                    $usernames[$v['user_id']]['isback'] = 1;
                }else{
                    $usernames[$v['user_id']]['isback'] = 0;
                }
                $usernames[$v['user_id']]['user_id'] = $v['user_id'];
                $usernames[$v['user_id']]['money'] += $v['money'];
                if (!empty($way_info[$v['lottery_type']])) {
                    foreach ($way_info[$v['lottery_type']] as $key=>$val){
                        if(in_array($v['way'],$val)){
                            $usernames[$v['user_id']][$key] += $v['money'];
                            break;
                        }
                    }
                }
            }
        }

        ob_start();

        include template("order-percentage");
    }

    /**
     * 游戏玩法
     */
    private function loadGameWay($type){
        $sql = "SELECT way, type FROM `un_odds_copy` WHERE `lottery_type` = '{$type}'";
        $res = $this->db->getall($sql);
        $lottery_type = array();
        $lottery_type[0] = array(1,3);
        $lottery_type[1] = array(2,4);
        //通过彩票类型 玩法方式之一
        foreach ($lottery_type as $k => $l){
            if(in_array($type,$l)){
                $flag = $k;
                break;
            }
        }

        $dxds_array=array('大','小',"单","双");
        $jz_array=array("极大","极小");
        $zh_data=array("小双","小单","大双","大单");
        //type 1为数字 2文字 3特殊玩法
        foreach ($res as $v) {
            if($k==0) {
                if ($v['type'] == 2) {
                    if(in_array($v['way'],$dxds_array)){
                        $data['panel_dxds'][] = $v['way'];
                    }else if(in_array($v['way'],$jz_array)){
                        $data['panel_jz'][] = $v['way'];
                    }else{
                        $data['panel_zh'][] = $v['way'];
                    }
                } elseif ($v['type'] == 1) {
                    $data['panel_2' . $flag][] = $v['way'];
                } else {
                    $data['panel_3' . $flag][] = $v['way'];
                }
            }else{
                if ($v['type'] == 2) {
                    $data['panel_1' . $flag][] = $v['way'];
                } elseif ($v['type'] == 1) {
                    $data['panel_2' . $flag][] = $v['way'];
                } else {
                    $data['panel_3' . $flag][] = $v['way'];
                }
            }
        }
        return $data;
    }

    /**
     * 游戏玩法
     */
    private function loadGameWayNew(){
        $sql = "SELECT way, type, lottery_type FROM `un_odds_copy`";
        $res = $this->db->getall($sql);
        $sql = "select way,type,lottery_type from `un_cup_odds` group by way";
        $res1 = $this->db->getall($sql);
        $res = array_merge($res,$res1);
        $dxds_array=array('大','小',"单","双");
        $jz_array=array("极大","极小");

        foreach ($res as $key=>$val) {

            //1幸运28   3加拿大28   2北京PK10   4幸运飞艇   5重庆时时彩    6三分彩
            if (in_array($val['lottery_type'],['1','3'])) {

                //type 1为数字 2文字 3特殊玩法
                if ($val['type'] == 2) {
                    if(in_array($val['way'],$dxds_array)){
                        $data[$val['lottery_type']]['a'][] = $val['way']; //大小单双
                    }else if(in_array($val['way'],$jz_array)){
                        $data[$val['lottery_type']]['b'][] = $val['way']; //极值
                    }else{
                        $data[$val['lottery_type']]['c'][] = $val['way']; //组合
                    }
                } elseif ($val['type'] == 1) {
                    $data[$val['lottery_type']]['d'][] = $val['way']; //数字
                } else {
                    $data[$val['lottery_type']]['e'][] = $val['way']; //特殊玩法
                }

            } elseif (in_array($val['lottery_type'],['2','4','9','14'])){

                //type 1为数字 2文字 3特殊玩法
                if ($val['type'] == 1){
                    $data[$val['lottery_type']]['a'][] = $val['way']; //数字
                } elseif ($val['type'] == 2){
                    if (strpos($val['way'],"龙") || strpos($val['way'],"虎")) {
                        $data[$val['lottery_type']]['b'][] = $val['way']; //龙虎
                    } else {
                        $data[$val['lottery_type']]['c'][] = $val['way']; //双面
                    }
                } else {
                    if($val['way'] == '庄' || $val['way'] == '闲'){
                        $data[$val['lottery_type']]['d'][] = $val['way']; //庄闲
                    } elseif($val['way'] == "冠亚") {
                        $data[$val['lottery_type']]['e'][] = $val['way']; //冠亚
                    } else {
                        $data[$val['lottery_type']]['f'][] = $val['way']; //冠亚和
                    }
                }

            } elseif (in_array($val['lottery_type'],['5','6','11'])) {

                //type 1为数字 2文字 3特殊玩法
                if ($val['type'] == 1) {
                    $data[$val['lottery_type']]['a'][] = $val['way']; //数字
                } elseif ($val['type'] == 2) {
                    $data[$val['lottery_type']]['c'][] = $val['way']; //双面
                } else {
                    if (in_array($val['way'],['龙','虎','和'])) {
                        $data[$val['lottery_type']]['d'][] = $val['way']; //龙虎和
                    } else {
                        $data[$val['lottery_type']]['b'][] = $val['way']; //总和
                    }
                }

            } elseif (in_array($val['lottery_type'],['7','8'])) {
                //type 1为数字 2文字 3特殊玩法
                $arr = explode("_",$val['way']);
                if (in_array($arr[0],['特码A','特码B'])) {
                    $data[$val['lottery_type']]['a'][] = $val['way']; //特码
                }
                if (in_array($arr[0],['正码A','正码B'])) {
                    $data[$val['lottery_type']]['b'][] = $val['way']; //正码
                }
                if (in_array($arr[0],['正1特','正2特','正3特','正4特','正5特','正6特'])) {
                    $data[$val['lottery_type']]['c'][] = $val['way']; //正1-6特
                }
                if (in_array($arr[0],['三中二','三全中','二全中','二中特','特串'])) {
                    $data[$val['lottery_type']]['d'][] = $arr[0]; //连码
                }
                if (in_array($arr[0],['半波'])) {
                    $data[$val['lottery_type']]['e'][] = $val['way']; //半波
                }
                if (in_array($arr[0],['尾数'])) {
                    $data[$val['lottery_type']]['f'][] = $val['way']; //尾数
                }
                if (in_array($arr[0],['一肖'])) {
                    $data[$val['lottery_type']]['g'][] = $val['way']; //一肖
                }
                if (in_array($arr[0],['特肖'])) {
                    $data[$val['lottery_type']]['h'][] = $val['way']; //特肖
                }
                if (in_array($arr[0],['二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中'])) {
                    $data[$val['lottery_type']]['i'][] = $arr[0]; //连肖
                }
                if (in_array($arr[0],['二尾连中','三尾连中','四尾连中','二尾连不中','三尾连不中','四尾连不中'])) {
                    $data[$val['lottery_type']]['j'][] = $arr[0]; //连尾
                }
                if (in_array($arr[0],['五不中','六不中','七不中','八不中','九不中','十不中'])) {
                    $data[$val['lottery_type']]['k'][] = $arr[0]; //不中
                }
                if (in_array($arr[0],['正码1','正码2','正码3','正码4','正码5','正码6'])) {
                    $data[$val['lottery_type']]['l'][] = $val['way']; //正码1-6
                }
                if (in_array($arr[0],['1-2球','1-3球','1-4球','1-5球','1-6球','2-3球','2-4球','2-5球','2-6球','3-4球','3-5球','3-6球','4-5球','4-6球','5-6球'])) {
                    $data[$val['lottery_type']]['m'][] = $val['way']; //正1-6龙虎
                }

            } elseif (in_array($val['lottery_type'],['10'])) {

                if ($val['type'] == 1) {
                    $data[$val['lottery_type']]['b'][] = $val['way']; //猜牌面
                } elseif ($val['type'] == 2) {
                    $arr = explode("_",$val['way']);
                    if (count($arr) == 1) {
                        $data[$val['lottery_type']]['a'][] = $val['way']; //猜牛牛
                    } elseif (count($arr) == 2) {
                        $data[$val['lottery_type']]['c'][] = $val['way']; //猜双面
                    }
                } else {
                    $arr = explode("_",$val['way']);
                    if (count($arr) == 1) {
                        if (in_array($val['way'],['龙','虎'])) {
                            $data[$val['lottery_type']]['e'][] = $val['way']; //猜龙虎
                        } elseif(in_array($val['way'],['有','无'])) {
                            $data[$val['lottery_type']]['f'][] = $val['way']; //猜龙虎
                        } elseif (in_array($val['way'],['红方胜','蓝方胜'])) {
                            $data[$val['lottery_type']]['h'][] = $val['way']; //猜胜负
                        } else {
                            $data[$val['lottery_type']]['g'][] = $val['way']; //猜总和
                        }
                    } elseif (count($arr) == 2) {
                        $data[$val['lottery_type']]['d'][] = $val['way']; //猜花色
                    }
                }

            } elseif (in_array($val['lottery_type'],['12'])) {

                $a = explode("_",$val['way']);
                if (in_array($a[0],['全场','半场'])) { //独赢盘
                    $data[$val['lottery_type']]['a'][] = $val['way'];
                } elseif(in_array($val['way'],['半场让球','半场大小'])) { //半场
                    $data[$val['lottery_type']]['b'][] = $val['way'];
                } elseif (in_array($val['way'],['全场让球','全场大小'])) { //全场
                    $data[$val['lottery_type']]['c'][] = $val['way'];
                } elseif (in_array($val['way'],['全场单双'])) { //单双
                    $data[$val['lottery_type']]['d'][] = $val['way'];
                } elseif (in_array($val['way'],['半场入球','全场入球'])) { //总入球
                    $data[$val['lottery_type']]['e'][] = $val['way'];
                } elseif (in_array($val['way'],['半/全场'])) { //半/全场
                    $data[$val['lottery_type']]['f'][] = $val['way'];
                } elseif (in_array($val['way'],['全场比分'])) { //波胆
                    $data[$val['lottery_type']]['g'][] = $val['way'];
                } elseif (in_array($val['way'],['加时让球','加时大小'])) { //加时
                    $data[$val['lottery_type']]['h'][] = $val['way'];
                } elseif (in_array($val['way'],['点球让球','点球大小'])) { //点球
                    $data[$val['lottery_type']]['i'][] = $val['way'];
                }

            } elseif (in_array($val['lottery_type'],['13'])) {

                $a = explode("_",$val['way']);
                if (in_array($a[0],['第一骰','第二骰','第三骰']) && in_array($a[1],['1','2','3','4','5','6'])) { //猜数子
                    $data[$val['lottery_type']]['a'][] = $val['way'];
                } elseif(in_array($a[0],['第一骰','第二骰','第三骰']) && in_array($a[1],['大','小','单','双'])) { //猜双面
                    $data[$val['lottery_type']]['b'][] = $val['way'];
                } elseif (in_array($val['way'],['总和'])) { //猜总和
                    $data[$val['lottery_type']]['c'][] = $val['way'];
                } elseif (in_array($val['way'],['对子'])) { //猜对子
                    $data[$val['lottery_type']]['d'][] = $val['way'];
                } elseif (in_array($val['way'],['豹子'])) { //猜围骰
                    $data[$val['lottery_type']]['e'][] = $val['way'];
                } elseif (in_array($val['way'],['单骰'])) { //猜单骰
                    $data[$val['lottery_type']]['f'][] = $val['way'];
                } elseif (in_array($val['way'],['双骰'])) { //猜双骰
                    $data[$val['lottery_type']]['g'][] = $val['way'];
                }
            }
        }
        return $data;
    }



    /**
     * 获取开奖结果
     * @param $type int 彩票类型
     * @param $issue string 期号
     * @return mixed
     */
    private function getAwardResult($type,$issue){
        switch ($type){
            case 1:
                $sql = "SELECT issue,open_result,FROM_UNIXTIME(open_time,'%Y-%m-%d %H:%i:%s') as open_time FROM `un_open_award` WHERE `issue` IN ({$issue})";
                $res = O('model')->db->getAll($sql);
                break;
            case 2:
                $sql="SELECT qihao AS issue, kaijianghaoma AS open_result,kaijiangshijian as open_time FROM un_bjpk10 WHERE qihao IN ({$issue}) and lottery_type={$type}";
                $res=$this->db->getAll($sql);
                break;
            case 3:
                $sql = "SELECT issue,open_result,FROM_UNIXTIME(open_time,'%Y-%m-%d %H:%i:%s') as open_time FROM `un_open_award` WHERE `issue` IN ({$issue})";
                $res = O('model')->db->getAll($sql);
                break;
            case 4:
                $sql="SELECT qihao AS issue, kaijianghaoma AS open_result,kaijiangshijian as open_time FROM un_xyft WHERE qihao IN ({$issue})";
                $res=$this->db->getAll($sql);
                break;
            case 5:
                $sql="SELECT issue,lottery_result as open_result,FROM_UNIXTIME(lottery_time,'%Y-%m-%d %H:%i:%s') as open_time FROM un_ssc WHERE lottery_type={$type} and issue IN ({$issue})";
                $res=$this->db->getAll($sql);
                break;
            case 6:
                $sql="SELECT issue,lottery_result as open_result,FROM_UNIXTIME(lottery_time,'%Y-%m-%d %H:%i:%s') as open_time FROM un_ssc WHERE lottery_type={$type} and issue IN ({$issue})";
                $res=$this->db->getAll($sql);
                break;
            case 7:
                $sql="SELECT issue,lottery_result as open_result,FROM_UNIXTIME(lottery_time,'%Y-%m-%d %H:%i:%s') as open_time FROM un_lhc WHERE lottery_type={$type} and issue IN ({$issue})";
                $res=$this->db->getAll($sql);
                break;
            case 8:
                $sql="SELECT issue,lottery_result as open_result,FROM_UNIXTIME(lottery_time,'%Y-%m-%d %H:%i:%s') as open_time FROM un_lhc WHERE lottery_type={$type} and issue IN ({$issue})";
                $res=$this->db->getAll($sql);
                break;
            case 9:
                $sql="SELECT qihao AS issue, kaijianghaoma AS open_result,kaijiangshijian as open_time FROM un_bjpk10 WHERE qihao IN ({$issue}) and lottery_type={$type}";
                $res=$this->db->getAll($sql);
                break;
            case 10:
                $sql="SELECT issue,lottery_result as open_result,FROM_UNIXTIME(lottery_time,'%Y-%m-%d %H:%i:%s') as open_time FROM un_nn WHERE lottery_type={$type} and issue IN ({$issue})";
                $tmp_res=$this->db->getAll($sql);
                $res = array();
                foreach ($tmp_res as $k=>$v){
                    $re  = getShengNiuNiu($v['open_result'],3);
                    $res[$k] = array(
                        'issue'=>$v['issue'],
                        'open_result'=>$re,
                        'open_time'=>$v['open_time']
                    );
                }

                break;
           case 11:
                $sql="SELECT issue,lottery_result as open_result,FROM_UNIXTIME(lottery_time,'%Y-%m-%d %H:%i:%s') as open_time FROM un_ssc WHERE lottery_type={$type} and issue IN ({$issue})";
                $res=$this->db->getAll($sql);
                break;
           case 13:
                $sql="SELECT issue,lottery_result as open_result,FROM_UNIXTIME(lottery_time,'%Y-%m-%d %H:%i:%s') as open_time FROM un_sb WHERE lottery_type={$type} and issue IN ({$issue})";
                $res=$this->db->getAll($sql);
                break;
            case 14:
                $sql="SELECT issue,lottery_result as open_result,FROM_UNIXTIME(lottery_time,'%Y-%m-%d %H:%i:%s') as open_time FROM un_ffpk10 WHERE lottery_type={$type} and issue IN ({$issue})";
                $res=$this->db->getAll($sql);
                break;
            default:
                $res = array();
        }
        return $res;
    }
}
