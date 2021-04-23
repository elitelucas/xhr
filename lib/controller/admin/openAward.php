<?php



/**

 * @copyright by-chenerlin

 */

ini_set('max_execution_time', '0');

ini_set('memory_limit', '2048M');

!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');

include S_CORE . 'class' . DS . 'pages.php';

include S_CORE . 'class' . DS . 'page.php';



class OpenAwardAction extends Action

{

    

    /**

     *

     * 一键撤单

     */

    public function cancalOrdersByIssue(){

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');

        if($cancal_order_supper==1) {

            $lottery_type = $_REQUEST['lottery_type'];

            $type = $_REQUEST['type'];

            if ($type == 'deal') {

                $issue = $_REQUEST['issue'];

                $is_supper = ($cancal_order_supper == 1) ? 1 : 0; //超级管理员

                //WHERE issue = '{$issue}' AND reg_type != 9 AND lottery_type = {$lotteryType} AND state = 0 AND award_state = 0

                $sql = "SELECT id FROM un_orders WHERE lottery_type={$lottery_type} AND issue='{$issue}' AND state = 0 AND award_state = 0 AND reg_type != 9";

                lg('cancal_orders','一键撤单,查当期所有注单:sql:'.$sql);

                $res = $this->db->getall($sql);

                lg('cancal_orders','一键撤单,查当期所有注单数据:$res:'.json_encode($res,JSON_UNESCAPED_UNICODE));

                if(empty($res)){

                    $data = array(

                        "msg" => '未找到可撤消订单，可能没有投注，或者都已派奖!',

                        "err" => 1,

                    );

                    echo json_encode($data, JSON_UNESCAPED_UNICODE);

                    return false;

                }

                $con = count($res);

                lg('cancal_orders','一键撤单,查当期所有注单总数:$con:'.$con);

                $i=1;

                foreach ($res as $k=>$v) {

                    $id = 0;

                    $data=array();

                    $id = $v['id'];

                    if (!$i) {

                        continue;

                    }

                    $re = $this->db->getOne("select lottery_type,room_no,user_id,order_no,issue from un_orders where id={$id}");

                    $data=array(

                        'lottery_type'=>$re['lottery_type'],

                        'api_id'=>'3016',

                        'room_id'=>$re['room_no'],

                        'issue'=>$re['issue'],

                        'uid'=>$re['user_id'],

                        'order_no'=>$re['order_no'],

                        'is_admin'=>1,

                        'is_supper'=>$is_supper,

                        'admin_name'=>$this->admin['username'],

                    );



                    $url = C('home_url').'?m=api&c=workerman&a=cancal_orders';

                    lg('cancal_orders','一键撤单,请求接口:url:'.$url.',data:'.json_encode($data,JSON_UNESCAPED_UNICODE));

                    $rep = signa($url,$data); //直接用接口撤单

                    if(!empty($rep)){

                        $i++;

                    }

                }



                $getLotteryTypeSql = "SELECT id,`name` FROM un_lottery_type";

                $lottery_type_arr = $this->db->getall($getLotteryTypeSql);

                $lottery_type_arr = array_column($lottery_type_arr, 'name', 'id');



                $log_remark = $this->admin['username'] . "--" . date('Y-m-d H:i:s') . "--一键撤单--期号:$issue--游戏类型:$lottery_type_arr[$lottery_type]";

                admin_operation_log($this->admin['userid'], 50, $log_remark);



                lg('cancal_orders','一键撤单,查当期实际撤单总数:$i:'.$i);

                $data = array(

                    "msg" => '一键撤单成功!',

                    "err" => 0,

                );

                echo json_encode($data, JSON_UNESCAPED_UNICODE);

//                $data = array();

//                if($con === $i){

//                    $data = array(

//                        "msg" => '一键撤单成功!',

//                        "err" => 0,

//                    );

//                    echo json_encode($data, JSON_UNESCAPED_UNICODE);

//                }else{

//                    $data = array(

//                        "msg" => '未知错误',

//                        "err" => 0,

//                    );

//                    echo json_encode($data, JSON_UNESCAPED_UNICODE);

//                }

            } else {

                $lottery_type = $_REQUEST['lottery_type'];

                $redis = initCacheRedis();

                $game = $redis->hget('LotteryType:' . $lottery_type, 'name');

                //关闭redis链接

                deinitCacheRedis($redis);

                include template('cancal-orders-by-issue');

            }

        }else{

            echo '非超级管理员没权限！';

        }

    }



    //分分彩，引用的是三分彩的模板

    public function ffcList(){

        $lottery_type = 11;

        $where = ' where 1=1 and lottery_type='.$lottery_type.' ';

        if ($_REQUEST['issue'] != '') {

            $where .= ' and issue=' . floatval($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where .= ' and status=' . floatval($_REQUEST['state']);

        }

    

        //        $is_supper = ($this->admin['userid']==1)?1:0; //超级管理员

        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');

        //        dump('$this->admin[\'userid\']------------>'.$this->admin['userid']);

    

        $pagesize = 20;

        $sql = 'select count(*) as num from un_ssc' . $where;

        $cnt = $this->db->result($sql);

        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;

    

        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_ssc {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";

        $list = $this->db->getall($sql);

    

        foreach ($list as $k => $v) {

            $list[$k]['open_time'] = date('Y-m-d H:i:s',$v['open_time']);

            $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }

            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

            $spare_2 = D('workerman')->kaijiang_result_ssc($v['open_result']);

            $temp = array();

            $temp[] = str_replace('第一球_','',$spare_2[1]);

            $temp[] = str_replace('第一球_','',$spare_2[2]);

            $temp[] = $spare_2[23];

            $list[$k]['open_result1'] = $temp;

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award-ssc');

    }



    //牛牛

    public function nnList(){

        $lottery_type = 10;

        $where = ' where 1=1 and lottery_type='.$lottery_type.' ';

        if ($_REQUEST['issue'] != '') {

            $where .= ' and issue=' . floatval($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where .= ' and status=' . floatval($_REQUEST['state']);

        }



//        $is_supper = ($this->admin['userid']==1)?1:0; //超级管理员

        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');

//        dump('$this->admin[\'userid\']------------>'.$this->admin['userid']);



        $pagesize = 20;

        $sql = 'select count(*) as num from un_nn' . $where;

        $cnt = $this->db->result($sql);

        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;



        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_nn {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";

        $list = $this->db->getall($sql);



        foreach ($list as $k => $v) {

            $list[$k]['open_time'] = date('Y-m-d H:i:s',$v['open_time']);

            $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }

            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

            $re = getShengNiuNiu($v['open_result']);

            $list[$k]['open_result'] = $re[2];

            $list[$k]['open_result1'] = array($re[0],$re[1]);

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award-nn');

    }



    //急速六合彩，引用的是时时彩的模板

    public function jslhcList(){

        $lottery_type = 8;

        $where = ' where 1=1 and lottery_type='.$lottery_type.' ';

        if ($_REQUEST['issue'] != '') {

            $where .= ' and issue=' . floatval($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where .= ' and status=' . floatval($_REQUEST['state']);

        }



//        $is_supper = ($this->admin['userid']==1)?1:0; //超级管理员

        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');

//        dump('$this->admin[\'userid\']------------>'.$this->admin['userid']);



        $pagesize = 20;

        $sql = 'select count(*) as num from un_lhc' . $where;

        $cnt = $this->db->result($sql);

        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;



        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_lhc {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";

        $list = $this->db->getall($sql);



        foreach ($list as $k => $v) {

            $list[$k]['open_time'] = date('Y-m-d H:i:s',$v['open_time']);

            $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }

            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

            $temp = array();

            foreach (explode(',',$v['open_result']) as $sv){

                $temp[] = getLhcShengxiao($sv,$v['open_time']);

            }

            $list[$k]['open_result1'] = $temp;

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award-ssc');

    }



    //六合彩，引用的是时时彩的模板

    public function lhcList(){

        $lottery_type = 7;

        $where = ' where 1=1 and lottery_type='.$lottery_type.' ';

        if ($_REQUEST['issue'] != '') {

            $where .= ' and issue=' . floatval($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where .= ' and status=' . floatval($_REQUEST['state']);

        }



//        $is_supper = ($this->admin['userid']==1)?1:0; //超级管理员

        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');

//        dump('$this->admin[\'userid\']------------>'.$this->admin['userid']);



        $pagesize = 20;

        $sql = 'select count(*) as num from un_lhc' . $where;

        $cnt = $this->db->result($sql);

        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;



        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_lhc {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";

        $list = $this->db->getall($sql);



        foreach ($list as $k => $v) {

            $list[$k]['open_time'] = date('Y-m-d H:i:s',$v['open_time']);

            $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }

            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

            $temp = array();

            foreach (explode(',',$v['open_result']) as $sv){

                $temp[] = getLhcShengxiao($sv,$v['open_time']);

            }

            $list[$k]['open_result1'] = $temp;

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award-ssc');

    }





    //三分彩，引用的是时时彩的模板

    public function sfcList(){

        $lottery_type = 6;

        $where = ' where 1=1 and lottery_type='.$lottery_type.' ';

        if ($_REQUEST['issue'] != '') {

            $where .= ' and issue=' . floatval($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where .= ' and status=' . floatval($_REQUEST['state']);

        }



        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');



        $pagesize = 20;

        $sql = 'select count(*) as num from un_ssc' . $where;

        $cnt = $this->db->result($sql);

        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;



        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_ssc {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";

        $list = $this->db->getall($sql);



        foreach ($list as $k => $v) {

            $list[$k]['open_time'] = date('Y-m-d H:i:s',$v['open_time']);

            $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }

            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

            $spare_2 = D('workerman')->kaijiang_result_ssc($v['open_result']);

            $temp = array();

            $temp[] = str_replace('总和_','',$spare_2[21]);

            $temp[] = str_replace('总和_','',$spare_2[22]);

            $temp[] = $spare_2[23];

            $list[$k]['open_result1'] = $temp;

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award-ssc');

    }



    /**

     * 开奖列表(时时彩)

     */

    public function sscList()

    {

        $lottery_type = 5;

        $where = ' where 1=1 and lottery_type='.$lottery_type.' ';

        if ($_REQUEST['issue'] != '') {

            $where .= ' and issue=' . floatval($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where .= ' and status=' . floatval($_REQUEST['state']);

        }



        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');



        $pagesize = 20;

        $sql = 'select count(*) as num from un_ssc' . $where;

        $cnt = $this->db->result($sql);

        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;



        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_ssc {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";

        $list = $this->db->getall($sql);



        foreach ($list as $k => $v) {

            $list[$k]['open_time'] = date('Y-m-d H:i:s',$v['open_time']);

            $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }

            // dump($v['call_back_uid']);

            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

            $spare_2 = D('workerman')->kaijiang_result_ssc($v['open_result']);

            $temp = array();

            $temp[] = str_replace('总和_','',$spare_2[21]);

            $temp[] = str_replace('总和_','',$spare_2[22]);

            $temp[] = $spare_2[23];

            $list[$k]['open_result1'] = $temp;

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award-ssc');

    }





    /**

     * 开奖列表(时时彩)

     */

    public function sbList()

    {

        $lottery_type = 13;

        $where = ' where 1=1 and lottery_type='.$lottery_type.' ';

        if ($_REQUEST['issue'] != '') {

            $where .= ' and issue=' . floatval($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where .= ' and status=' . floatval($_REQUEST['state']);

        }



        // $is_supper = ($this->admin['userid']==1)?1:0; //超级管理员

        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');

        // dump('$this->admin[\'userid\']------------>'.$this->admin['userid']);



        $pagesize = 20;

        $sql = 'select count(*) as num from un_sb' . $where;

        $cnt = $this->db->result($sql);

        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;



        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_sb {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";

        $list = $this->db->getall($sql);



        foreach ($list as $k => $v) {

            $list[$k]['open_time'] = date('Y-m-d H:i:s',$v['open_time']);

            $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }

            // dump($v['call_back_uid']);

            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

            $spare_2 = D('workerman')->kaijiang_result_sb($v['open_result']);

//            dump($spare_2);

            $temp = array();

            $temp[] = $spare_2[9];

            $temp[] = $spare_2[10];

            $temp[] = $spare_2[11];

            $list[$k]['open_result1'] = $temp;

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award-sb');

    }







    /**

     * 分分PK10

     */

    public function ffpk10List()

    {

        $lottery_type = 14;

        $where = ' where 1=1 and lottery_type='.$lottery_type.' ';

        if ($_REQUEST['issue'] != '') {

            $where .= ' and issue=' . floatval($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where .= ' and status=' . floatval($_REQUEST['state']);

        }



        // $is_supper = ($this->admin['userid']==1)?1:0; //超级管理员

        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');

        // dump('$this->admin[\'userid\']------------>'.$this->admin['userid']);



        $pagesize = 20;

        $sql = 'select count(*) as num from un_ffpk10' . $where;

        $cnt = $this->db->result($sql);

        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;



        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_ffpk10 {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";

        $list = $this->db->getall($sql);



        foreach ($list as $k => $v) {

            $list[$k]['open_time'] = date('Y-m-d H:i:s',$v['open_time']);

            $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }

            // dump($v['call_back_uid']);

            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

            $spare_2 = D('workerman')->kaijiang_result($v['open_result']);

            $strleng = mb_strlen($spare_2[4]) - 1;

            //定义大小单双玩法数组

            $temp = array();

            $temp[] = mb_substr($spare_2[4], 0, 1, 'utf-8');

            $temp[] = mb_substr($spare_2[4], $strleng, 1, 'utf-8');

            $temp[] = $spare_2[5];

            $list[$k]['open_result1'] = $temp;

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award-sb');

    }



    /**

     * 开奖列表(幸运28)

     */

    public function LuckyList()

    {

        $lottery_type = 1;

        $where = array('lottery_type' => $lottery_type);

        if ($_REQUEST['issue'] != '') {

            $where['issue'] = trim($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where['state'] = trim($_REQUEST['state']);

        }

        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');



        $pagesize = 20;

        $numArr = D('openaward')->getOneCoupon('COUNT(*) as num', $where);

        $page = new pages($numArr['num'], $pagesize, url('', '', ''), $where);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;



        $field = 'id,issue,open_result,spare_1,spare_2,open_time,insert_time,state,user_id,is_call_back,call_back_uid';

        $order = "issue DESC";

        $list = D('openaward')->getlist($field, $where, $order, $limit);

        foreach ($list as $k => $v) {

            $list[$k]['open_time'] = date('Y-m-d H:i:s',$v['open_time']);

            $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);

            if(empty($v['insert_time'])){

                $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['open_time']);

            }

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }



            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award');

    }



    /**

     * 开奖列表(加拿大28)

     */

    public function jndList()

    {

        $lottery_type = 3;

        $where = array('lottery_type' => $lottery_type);

        if ($_REQUEST['issue'] != '') {

            $where['issue'] = trim($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where['state'] = trim($_REQUEST['state']);

        }

        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');

        $pagesize = 20;

        $numArr = D('openaward')->getOneCoupon('COUNT(*) as num', $where);

        $page = new pages($numArr['num'], $pagesize, url('', '', ''), $where);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;

        $field = 'id,issue,open_result,spare_1,spare_2,open_time,insert_time,state,user_id,is_call_back,call_back_uid';

        $order = "issue DESC";

        $list = D('openaward')->getlist($field, $where, $order, $limit);

        foreach ($list as $k => $v) {

            $list[$k]['open_time'] = date('Y-m-d H:i:s',$v['open_time']);

            $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);

            if(empty($v['insert_time'])){

                $list[$k]['insert_time'] = date('Y-m-d H:i:s',$v['open_time']);

            }



            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }

            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award');

    }



    /**

     * 开奖列表(北京PK10)

     */

    public function bjpk10List()

    {

        $lottery_type = 2;

        $where = ' where 1=1 and lottery_type='.$lottery_type;

        if ($_REQUEST['issue'] != '') {

            $where .= ' and qihao=' . floatval($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where .= ' and status=' . floatval($_REQUEST['state']);

        }



//        $is_supper = ($this->admin['userid']==1)?1:0; //超级管理员

        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');

//        dump('$this->admin[\'userid\']------------>'.$this->admin['userid']);



        $pagesize = 20;

        $sql = 'select count(*) as num from un_bjpk10' . $where;

        $cnt = $this->db->result($sql);

        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;



        $sql = "SELECT `id`,`qihao` as issue,`status` as state,`kaijianghaoma` AS open_result,`kaijiangshijian` as open_time,user_id,is_call_back,call_back_uid ,insert_time FROM un_bjpk10 {$where} ORDER BY qihao DESC LIMIT {$limit}";

        $list = O('model')->db->getall($sql);



        foreach ($list as $k => $v) {

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }

//            dump($v['call_back_uid']);

            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

            $spare_2 = D('workerman')->kaijiang_result($v['open_result']);

            $strleng = mb_strlen($spare_2[4]) - 1;

            //定义大小单双玩法数组

            $temp = array();

            $temp[] = mb_substr($spare_2[4], 0, 1, 'utf-8');

            $temp[] = mb_substr($spare_2[4], $strleng, 1, 'utf-8');

            $temp[] = $spare_2[5];

            $list[$k]['open_result1'] = $temp;

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award-pk10');

    }





    /**

     * 开奖列表(急速赛车)

     */

    public function jsscList()

    {

        $lottery_type = 9;

        $where = ' where 1=1 and lottery_type='.$lottery_type . ' ';

        if ($_REQUEST['issue'] != '') {

            $where .= ' and qihao=' . floatval($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where .= ' and status=' . floatval($_REQUEST['state']);

        }



//        $is_supper = ($this->admin['userid']==1)?1:0; //超级管理员

        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');

//        dump('$this->admin[\'userid\']------------>'.$this->admin['userid']);



        $pagesize = 20;

        $sql = 'select count(*) as num from un_bjpk10' . $where;

        $cnt = $this->db->result($sql);

        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;



        $sql = "SELECT `id`,`qihao` as issue,`status` as state,`kaijianghaoma` AS open_result,`kaijiangshijian` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_bjpk10 {$where} ORDER BY qihao DESC LIMIT {$limit}";

        $list = O('model')->db->getall($sql);



        foreach ($list as $k => $v) {

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }

//            dump($v['call_back_uid']);

            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];

            $list[$k]['admin'] = $admin['username'];

            $spare_2 = D('workerman')->kaijiang_result($v['open_result']);

            $strleng = mb_strlen($spare_2[4]) - 1;

            //定义大小单双玩法数组

            $temp = array();

            $temp[] = mb_substr($spare_2[4], 0, 1, 'utf-8');

            $temp[] = mb_substr($spare_2[4], $strleng, 1, 'utf-8');

            $temp[] = $spare_2[5];

            $list[$k]['open_result1'] = $temp;

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award-pk10');

    }





    /**

     * 开奖列表(幸运飞艇)

     */

    public function xyftList()

    {

        $lottery_type = 4;

        $where = ' where 1=1 ';

        if ($_REQUEST['issue'] != '') {

            $where .= ' and qihao=' . floatval($_REQUEST['issue']);

        }

        if ($_REQUEST['state'] != '') {

            $where .= ' and status=' . floatval($_REQUEST['state']);

        }



//        $is_supper = ($this->admin['userid']==1)?1:0; //超级管理员

        $callback_supper=is_supper($this->admin['roleid'],'callback');

        $cancal_order_supper=is_supper($this->admin['roleid'],'calcal_order');



        $pagesize = 20;

        $sql = 'select count(*) as num from un_xyft' . $where;

        $cnt = $this->db->result($sql);

        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;



        $sql = "SELECT `id`,`qihao` as issue,`status` as state,`kaijianghaoma` AS open_result,`kaijiangshijian` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_xyft {$where} ORDER BY qihao DESC LIMIT {$limit}";

        $list = O('model')->db->getall($sql);



        foreach ($list as $k => $v) {

            if (!in_array($v['state'], array('0', '1'))) {

                //锁定

                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');

                if (!empty($res)) {

                    $v['user_id'] = $res['click_uid'];

                    $list[$k]['user_id'] = $v['user_id'];

                }

            }

            if (!empty($v['user_id'])) {

                $admin = $this->db->getOne("select username from un_admin where userid={$v['user_id']}");

            } else {

                $admin['username'] = "未知userid-" . $v['user_id'];

            }



            if (!empty($v['call_back_uid'])) {

                $cbadmin = $this->db->getOne("select username from un_admin where userid={$v['call_back_uid']}");

            } else {

                $cbadmin['username'] = '';

            }

            $list[$k]['cbadmin'] = $cbadmin['username'];



            $list[$k]['admin'] = $admin['username'];

            $spare_2 = D('workerman')->kaijiang_result($v['open_result']);

            $strleng = mb_strlen($spare_2[4]) - 1;

            //定义大小单双玩法数组

            $temp = array();

            $temp[] = mb_substr($spare_2[4], 0, 1, 'utf-8');

            $temp[] = mb_substr($spare_2[4], $strleng, 1, 'utf-8');

            $temp[] = $spare_2[5];

            $list[$k]['open_result1'] = $temp;

        }

        $adminUid = $this->admin['userid'];

        include template('list-open-award-pk10');

    }



    /**

     * 手动开奖（step1）

     */

    public function handOpenward()

    {

        $id = trim($_REQUEST['id']);

        $lottery_type = trim($_REQUEST['lottery_type']);

        $issue = trim($_REQUEST['issue']);

        switch ($lottery_type) {

            case 1:

                include template('add-open-award');

                break;

            case 2:

                include template('add-open-award-pc10');

                break;

            case 3:

                include template('add-open-award');

                break;

        }

    }



    /**

     * 开奖处理

     */

 public function dealOpendaWard(){

        $lottery_type = getParame('lottery_type', 1, '', 'int', ['开奖彩种不能为空', '开奖彩种格式错误']);    //彩种类型

        $issue = getParame('issue', 1, '', 'int', ['开奖期号不能为空', '开奖期号为纯数字']);     //期号



        $getLotteryTypeSql = "SELECT id,`name` FROM un_lottery_type";

        $lottery_type_arr = $this->db->getall($getLotteryTypeSql);

        $lottery_type_arr = array_column($lottery_type_arr, 'name', 'id');



     //更改提示音状态

        $sql = "UPDATE `un_music_tips` SET STATUS=1 WHERE record_id='{$lottery_type}_{$issue}';";

        $this->db->query($sql);

        lg('music_tips_debug','手工开奖::$sql::'.$sql);



        //重庆时时彩     三分彩     分分彩

        if (in_array($lottery_type, array(5,6,11))) $this->dealOpendaWard_5_6_11($issue,$lottery_type);



        //欢乐骰宝

        if (in_array($lottery_type, array(13))) $this->dealOpendaWard_13($issue,$lottery_type);



        //分分PK10

        if (in_array($lottery_type, array(14))) $this->dealOpendaWard_14($issue, $lottery_type);



         //六合彩           急速六合彩

        if(in_array($lottery_type,array(7,8))) $this->dealOpendaWard_7_8($issue,$lottery_type);



        //28类 --

        if (in_array($lottery_type, array(1, 3))) $this->dealOpendaWard_1_3($issue, $lottery_type);



        //北京PK10

        if (in_array($lottery_type, array(2))) $this->dealOpendaWard_2($issue, $lottery_type);



        //幸运飞艇

        if (in_array($lottery_type, array(4))) $this->dealOpendaWard_4($issue, $lottery_type);



        //急速赛车

        if ($lottery_type == 9) $this->dealOpendaWard_9($issue, $lottery_type);



        //百人牛牛

        if ($lottery_type == 10) $this->dealOpendaWard_10($issue, $lottery_type);



        $log_remark = $this->admin['username'] . "--" . date('Y-m-d H:i:s') . "--手动开奖--期号:$issue--游戏类型:$lottery_type_arr[$lottery_type]";

        admin_operation_log($this->admin['userid'], 50, $log_remark);

        return false;

    }



    private function dealOpendaWard_10($issue, $lottery_type) {

        $open_time = getParame('open_time', 1, '', 'str', ['开奖时间不能为空', '开奖时间格式错误']);     //开奖时间



        $arr = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',];

        $data = [];

        foreach ($arr as $v) {

            $postNumber = getParame('number'.$v, 1, '', 'bairennn_input_filter', ['开奖号码'.$v.'不能为空', '开奖号码'.$v.'不是合法的扑克牌面']);

            $data[] = poker2num($postNumber);

        }



        //将得到的数据更新到数据库中

        $final['lottery_type'] = $lottery_type;

        $final['issue'] = $issue;

        $final['lottery_result'] = implode($data, ',');

        $final['lottery_time'] = strtotime($open_time);

        $final['insert_time'] = time();

        $final['status'] = 1;

        $final['user_id'] = $this->admin['userid'];

        $sql = "SELECT is_call_back ,call_back_uid FROM un_nn WHERE issue='{$issue}' and lottery_type={$lottery_type}";

        $res = $this->db->getOne($sql);

        $final['is_call_back'] = $res['is_call_back'];

        $final['call_back_uid'] = $res['call_back_uid'];



        $update_res = O('model')->db->replace('un_nn', $final);

        if ($update_res) {

            $redis = initCacheRedis();

            //shell派奖

            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->admin['userid'])));  //存开奖数据

            deinitCacheRedis($redis);

            //此处进入开奖派彩的逻辑

            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数

//                    D('workerman')->theLottery($issue, $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));

        } else {

            echo json_encode(array('status' => '1', 'ret_msg' => '开奖失败'));

            exit;

        }

        echo json_encode(array('status' => 0));

    }



    private function dealOpendaWard_9($issue, $lottery_type) {

        $open_time = getParame('open_time', 1, '', 'str', ['开奖时间不能为空', '开奖时间格式错误']);     //开奖时间

        $arr = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J");

        $data = array();

        foreach ($arr as $v) {

            $postNumber = getParame('number'.$v, 1, '', 'int', ['开奖号码'.$v.'不能为空', '开奖号码'.$v.'为纯数字']);



            if (!preg_match('/^\d{1,2}$/', $postNumber)) {

                $this->ajaxReturn('', '开奖号码' . $v . '请输入0-9的数字', 4005);

            }

            $data[] = $postNumber;

        }



        //将得到的数据更新到数据库中

        $final['lottery_type'] = $lottery_type;

        $final['qihao'] = $issue;

        $final['kaijianghaoma'] = implode($data, ',');

        $final['kaijiangshijian'] = $open_time;

        $final['insert_time'] = date('Y-m-d H:i:s', time());

        $final['status'] = 1;

        $final['user_id'] = $this->admin['userid'];

        $sql = "SELECT is_call_back ,call_back_uid FROM un_bjpk10 WHERE qihao='{$issue}' and lottery_type={$lottery_type}";

        $res = $this->db->getOne($sql);

        $final['is_call_back'] = $res['is_call_back'];

        $final['call_back_uid'] = $res['call_back_uid'];



        //REPLACE INTO `un_bjpk10` ( `qihao`,`kaijianghaoma`,`kaijiangshijian`,`insert_time`,`status`,`user_id` ) VALUES ('1111','1,2,3,4,5,6,7,8,9,10','2017-07-03 18:07:12','2017-07-03 18:08:01','1','44')

        $update_res = O('model')->db->replace('un_bjpk10', $final);

        if ($update_res) {

            $redis = initCacheRedis();

            //shell派奖

            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->admin['userid'])));  //存开奖数据

            deinitCacheRedis($redis);

            //此处进入开奖派彩的逻辑

            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数

//                    D('workerman')->theLottery($final['qihao'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));

        } else {

            echo json_encode(array('status' => '1', 'ret_msg' => '开奖失败'));

            exit;

        }

        echo json_encode(array('status' => 0));

    }



    private function dealOpendaWard_4($issue, $lottery_type){

        $open_time = getParame('open_time', 1, '', 'str', ['开奖时间不能为空', '开奖时间格式错误']);     //开奖时间

        $arr = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J");

        $data = array();

        foreach ($arr as $v) {

            $postNumber = getParame('number'.$v, 1, '', 'int', ['开奖号码'.$v.'不能为空', '开奖号码'.$v.'为纯数字']);



            if (!preg_match('/^\d{1,2}$/', $postNumber)) {

                $this->ajaxReturn('', '开奖号码' . $v . '请输入0-9的数字', 4005);

            }

            $data[] = $postNumber;

        }





        //将得到的数据更新到数据库中

        $final['qihao'] = $issue;

        $final['kaijianghaoma'] = implode($data, ',');

        $final['kaijiangshijian'] = $open_time;

        $final['insert_time'] = date('Y-m-d H:i:s', time());

        $final['status'] = 1;

        $final['user_id'] = $this->admin['userid'];

        $sql = "SELECT is_call_back ,call_back_uid FROM un_xyft WHERE qihao='{$issue}'";

        $res = $this->db->getOne($sql);

        $final['is_call_back'] = $res['is_call_back'];

        $final['call_back_uid'] = $res['call_back_uid'];

        //REPLACE INTO `un_bjpk10` ( `qihao`,`kaijianghaoma`,`kaijiangshijian`,`insert_time`,`status`,`user_id` ) VALUES ('1111','1,2,3,4,5,6,7,8,9,10','2017-07-03 18:07:12','2017-07-03 18:08:01','1','44')

        $update_res = O('model')->db->replace('un_xyft', $final);

        if ($update_res) {

            $redis = initCacheRedis();

            //shell派奖

            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->admin['userid'])));  //存开奖数据

            deinitCacheRedis($redis);

            //此处进入开奖派彩的逻辑

            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数

//                    D('workerman')->theLottery($final['qihao'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));

        } else {

            echo json_encode(array('status' => '1', 'ret_msg' => '开奖失败'));

            exit;

        }

        echo json_encode(array('status' => 0));

    }



    private function dealOpendaWard_2($issue, $lottery_type){

        $open_time = getParame('open_time', 1, '', 'str', ['开奖时间不能为空', '开奖时间格式错误']);     //开奖时间

        $arr = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J");

        $data = array();

        foreach ($arr as $v) {

            $postNumber = getParame('number'.$v, 1, '', 'int', ['开奖号码'.$v.'不能为空', '开奖号码'.$v.'为纯数字']);



            if (!preg_match('/^\d{1,2}$/', $postNumber)) {

                $this->ajaxReturn('', '开奖号码' . $v . '请输入0-9的数字', 4005);

            }

            $data[] = $postNumber;

        }



        //将得到的数据更新到数据库中

        $final['lottery_type'] = $lottery_type;

        $final['qihao'] = $issue;

        $final['kaijianghaoma'] = implode($data, ',');

        $final['kaijiangshijian'] = $open_time;

        $final['insert_time'] = date('Y-m-d H:i:s', time());

        $final['status'] = 1;

        $final['user_id'] = $this->admin['userid'];

        $sql = "SELECT is_call_back ,call_back_uid FROM un_bjpk10 WHERE qihao='{$issue}' and lottery_type={$lottery_type}";

        $res = $this->db->getOne($sql);

        $final['is_call_back'] = $res['is_call_back'];

        $final['call_back_uid'] = $res['call_back_uid'];



        //REPLACE INTO `un_bjpk10` ( `qihao`,`kaijianghaoma`,`kaijiangshijian`,`insert_time`,`status`,`user_id` ) VALUES ('1111','1,2,3,4,5,6,7,8,9,10','2017-07-03 18:07:12','2017-07-03 18:08:01','1','44')

        $update_res = O('model')->db->replace('un_bjpk10', $final);

        if ($update_res) {

            $redis = initCacheRedis();

            //shell派奖

            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->admin['userid'])));  //存开奖数据

            deinitCacheRedis($redis);

            //此处进入开奖派彩的逻辑

            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数

//                    D('workerman')->theLottery($final['qihao'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));

        } else {

            echo json_encode(array('status' => '1', 'ret_msg' => '开奖失败'));

            exit;

        }

        echo json_encode(array('status' => 0));

    }



    private function dealOpendaWard_13($issue, $lottery_type) {

        $arr = array('A', 'B', 'C');

        $data = array();

        foreach ($arr as $v) {

            $postNumber = getParame('number'.$v, 1, '', 'int', ['开奖号码'.$v.'不能为空', '开奖号码'.$v.'为纯数字']);



            if (!preg_match('/^\d{1,2}$/', $postNumber)) {

                $this->ajaxReturn('', '开奖号码' . $v . '请输入1-6的数字', 4005);

            }

            $data[] = $postNumber;

        }

        $open_time = getParame('open_time', 1, '', 'str', ['开奖时间不能为空', '开奖时间格式错误']);     //开奖时间



        //将得到的数据更新到数据库中

        $final['issue'] = $issue;

        $final['lottery_type'] = $lottery_type;

        $final['lottery_result'] = implode($data, ',');

        $final['lottery_time'] = strtotime($open_time);

        $final['insert_time'] = time();

        $final['status'] = 1;       //1为手动开奖

        $final['user_id'] = $this->admin['userid'];

        $sql = "SELECT is_call_back,call_back_uid FROM un_sb WHERE issue = '{$issue}' AND lottery_type = {$lottery_type}";

        $res = $this->db->getOne($sql);

        $final['is_call_back'] = $res['is_call_back'];

        $final['call_back_uid'] = $res['call_back_uid'];

        $update_res = O('model')->db->replace('un_sb', $final);

        lg('hand_open_log',var_export(array('SQL'=>$this->db->_sql(),'$final'=>$final,'$update_res'=>$update_res,'$data'=>$data),1));

        // dump($this->db->_sql());

        if ($update_res) {

            $redis = initCacheRedis();

            //shell派奖

            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->admin['userid'])));  //存开奖数据

            deinitCacheRedis($redis);

            //此处进入开奖派彩的逻辑

            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数

//                D('workerman')->theLottery($final['issue'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));

        } else {

            echo json_encode(array('status' => '1', 'ret_msg' => '开奖失败'));

            exit;

        }



        echo json_encode(array('status' => 0));

        return false;

    }



    private function dealOpendaWard_5_6_11($issue, $lottery_type) {

        $arr = array('A', 'B', 'C', 'D', 'E');

        $data = array();

        foreach ($arr as $v) {

            $postNumber = getParame('number'.$v, 1, '', 'int', ['开奖号码'.$v.'不能为空', '开奖号码'.$v.'为纯数字']);



            if (!preg_match('/^\d{1,2}$/', $postNumber)) {

                $this->ajaxReturn('', '开奖号码' . $v . '请输入0-9的数字', 4005);

            }

            $data[] = $postNumber;

        }

        $open_time = getParame('open_time', 1, '', 'str', ['开奖时间不能为空', '开奖时间格式错误']);     //开奖时间



        //将得到的数据更新到数据库中

        $final['issue'] = $issue;

        $final['lottery_type'] = $lottery_type;

        $final['lottery_result'] = implode($data, ',');

        $final['lottery_time'] = strtotime($open_time);

        $final['insert_time'] = time();

        $final['status'] = 1;       //1为手动开奖

        $final['user_id'] = $this->admin['userid'];

        $sql = "SELECT is_call_back,call_back_uid FROM un_ssc WHERE issue = '{$issue}' AND lottery_type = {$lottery_type}";

        $res = $this->db->getOne($sql);

        $final['is_call_back'] = $res['is_call_back'];

        $final['call_back_uid'] = $res['call_back_uid'];

        $update_res = O('model')->db->replace('un_ssc', $final);

        lg('hand_open_log',var_export(array('SQL'=>$this->db->_sql(),'$final'=>$final,'$update_res'=>$update_res,'$data'=>$data),1));

        // dump($this->db->_sql());

        if ($update_res) {

            $redis = initCacheRedis();

            //shell派奖

            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->admin['userid'])));  //存开奖数据

            deinitCacheRedis($redis);

            //此处进入开奖派彩的逻辑

            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数

//                D('workerman')->theLottery($final['issue'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));

        } else {

            echo json_encode(array('status' => '1', 'ret_msg' => '开奖失败'));

            exit;

        }



        echo json_encode(array('status' => 0));

        return false;

    }



    //分分PK10

    private function dealOpendaWard_14($issue, $lottery_type) {

        $arr = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J");

        $data = array();

        foreach ($arr as $v) {

            $postNumber = getParame('number'.$v, 1, '', 'int', ['开奖号码'.$v.'不能为空', '开奖号码'.$v.'为纯数字']);



            if (!preg_match('/^\d{1,2}$/', $postNumber)) {

                $this->ajaxReturn('', '开奖号码' . $v . '请输入1-10的数字', 4005);

            }

            $data[] = $postNumber;

        }



        $open_time = getParame('open_time', 1, '', 'str', ['开奖时间不能为空', '开奖时间格式错误']);     //开奖时间



        //将得到的数据更新到数据库中

        $final['issue'] = $issue;

        $final['lottery_type'] = $lottery_type;

        $final['lottery_result'] = implode($data, ',');

        $final['lottery_time'] = strtotime($open_time);

        $final['insert_time'] = time();

        $final['status'] = 1;       //1为手动开奖

        $final['user_id'] = $this->admin['userid'];

        $sql = "SELECT is_call_back,call_back_uid FROM un_ffpk10 WHERE issue = '{$issue}' AND lottery_type = {$lottery_type}";

        $res = $this->db->getOne($sql);

        $final['is_call_back'] = $res['is_call_back'];

        $final['call_back_uid'] = $res['call_back_uid'];

        $update_res = O('model')->db->replace('un_ffpk10', $final);

        lg('hand_open_log',var_export(array('SQL'=>$this->db->_sql(),'$final'=>$final,'$update_res'=>$update_res,'$data'=>$data),1));

        // dump($this->db->_sql());

        if ($update_res) {

            $redis = initCacheRedis();

            //shell派奖

            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->admin['userid'])));  //存开奖数据

            deinitCacheRedis($redis);

            //此处进入开奖派彩的逻辑

            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数

//                D('workerman')->theLottery($final['issue'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));

        } else {

            echo json_encode(array('status' => '1', 'ret_msg' => '开奖失败'));

            exit;

        }



        echo json_encode(array('status' => 0));

        return false;

    }



    //六合彩           急速六合彩

    private function dealOpendaWard_7_8($issue, $lottery_type) {

        lg('hand_open_log',var_export(array('$_REQUEST'=>$_REQUEST),1));



        $open_time = getParame('open_time', 1, '', 'str', ['开奖时间不能为空', '开奖时间格式错误']);     //开奖时间

        $arr = array('A', 'B', 'C', 'D', 'E','F','G');

        $data = array();

        foreach ($arr as $v) {

            $postNumber = getParame('number'.$v, 1, '', 'int', ['开奖号码'.$v.'不能为空', '开奖号码'.$v.'为纯数字']);



            if (!preg_match('/^\d{1,2}$/', $postNumber)) {

                $this->ajaxReturn('', '开奖号码' . $v . '请输入1-49的数字', 4005);

            }

            $data[] = $postNumber;

        }



        //将得到的数据更新到数据库中

        $final['issue'] = $issue;

        $final['lottery_type'] = $lottery_type;

        $final['lottery_result'] = implode($data, ',');

        $final['lottery_time'] = strtotime($open_time);

        $final['insert_time'] = time();

        $final['status'] = 1;       //1为手动开奖

        $final['user_id'] = $this->admin['userid'];

        $sql = "SELECT is_call_back,call_back_uid FROM un_lhc WHERE issue = '{$issue}' AND lottery_type = {$lottery_type}";

        $res = $this->db->getOne($sql);

        $final['is_call_back'] = $res['is_call_back'];

        $final['call_back_uid'] = $res['call_back_uid'];

        $update_res = O('model')->db->replace('un_lhc', $final);

        lg('hand_open_log',var_export(array('SQL'=>$this->db->_sql(),'$final'=>$final,'$update_res'=>$update_res,'$data'=>$data),1));

        // dump($this->db->_sql());

        if ($update_res) {

            $redis = initCacheRedis();

            //shell派奖

            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->admin['userid'])));  //存开奖数据

            deinitCacheRedis($redis);

            //此处进入开奖派彩的逻辑

            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数

//                D('workerman')->theLottery($final['issue'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));

        } else {

            echo json_encode(array('status' => '1', 'ret_msg' => '开奖失败'));

            exit;

        }



        echo json_encode(array('status' => 0));

        return false;

    }



    /**

     * 开奖处理         幸运28    加拿大28

     */

    private function dealOpendaWard_1_3($issue, $lottery_type) {

        //接收参数

        $numberA = getParame('numberA', 1, '', 'int', ['开奖号码A不能为空', '开奖号码A为纯数字']);

        $numberB = getParame('numberB', 1, '', 'int', ['开奖号码B不能为空', '开奖号码B为纯数字']);

        $numberC = getParame('numberC', 1, '', 'int', ['开奖号码C不能为空', '开奖号码C为纯数字']);



        //开奖结果

        $numResult = $numberA + $numberB + $numberC;

        $zwWay = ''; //玩法

        if ($numResult >= 0 && $numResult <= 13) { //属于小

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

        } elseif ($numResult >= 22 && $numResult <= 27) { //属于极大

            $zwWay .= '极大';

        }



        //个位数补零

        $numberA = strlen($numberA) == '1' ? '0' . $numberA : $numberA;

        $numberB = strlen($numberB) == '1' ? '0' . $numberB : $numberB;

        $numberC = strlen($numberC) == '1' ? '0' . $numberC : $numberC;

        $numResult = strlen($numResult) == '1' ? '0' . $numResult : $numResult; //中奖结果 28

        $spare_1 = $numberA . '+' . $numberB . '+' . $numberC;  //中奖号码 3x9





        $sql = "SELECT `spare_3` FROM `un_open_award` WHERE (`issue`='".($issue-1)."' AND lottery_type= {$lottery_type})";

        $ltj = $this->db->getOne($sql);



        $tj=calculate_tj((int)$numResult,$ltj['spare_3']);



        $data = array(

            'open_result' => $numResult,

            'spare_1' => $spare_1,

            'spare_2' => $zwWay,

            'spare_3' => $tj,

            'state' => 1,

            'user_id' => $this->admin['userid'],

        );



        $datetime = getParame('open_time', 1, '', 'str', ['开奖时间不能为空', '开奖时间格式错误']);     //开奖时间

        $reArr = $this->db->getOne("SELECT id FROM `un_open_award` WHERE (`issue`='{$issue}' AND lottery_type= {$lottery_type})");

        @file_put_contents('openAward.log', date('Y-m-d H:i:s').PHP_EOL.'------>手动开奖----->'.json_encode($reArr).PHP_EOL,FILE_APPEND);

        //没有开奖结果时

        if (!isset($reArr['id'])) {

            //获取开奖时间

            $openAwardIssue = D('openaward')->getOneCoupon('issue', array('issue' => $issue, 'lottery_type' => $lottery_type));

            if (!empty($openAwardIssue)) {

                // O('model')->db->query("DELETE FROM `un_open_award` WHERE (`issue`='{$issue}' AND lottery_type= {$lottery_type})");

                $this->db->query("DELETE FROM `un_open_award` WHERE (`issue`='{$issue}' AND lottery_type= {$lottery_type})");

                // echo json_encode(array('status' => '1', 'ret_msg' => '该期号已存在,请用手动开奖'));

                // exit;

            }



            $data['issue'] = $issue;

            $data['lottery_type'] = $lottery_type;

            $data['open_time'] = strtotime($datetime);

            $data['insert_time'] = time(); //记录实际开奖时间

            // $data['user_id'] = $this->admin['userid'];



            //开奖结果入库 手动补单

            // $res = D('openaward')->add($data);

            $res = $this->db->insert('un_open_award', $data);

            $logKey = '手动补单';

        } else {

            //开奖结果入库 手动开奖

            // $res = D('openaward')->save($data, array('issue' => $issue, 'lottery_type' => $lottery_type));

            $res = $this->db->update('un_open_award', $data,array('issue' => $issue, 'lottery_type' => $lottery_type));

            $logKey = '手动开奖';

        }

        @file_put_contents('openAward.log', date('Y-m-d H:i:s').PHP_EOL.'------>'.$logKey.'--sql--->'.$this->db->_sql().PHP_EOL,FILE_APPEND);

        @file_put_contents('openAward.log', date('Y-m-d H:i:s').PHP_EOL.'------>'.$logKey.'--------->游戏类型: '.$lottery_type.' 期号：'.$issue.' 开奖信息'.json_encode($data,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);





        if (!$res) { //开奖失败

            echo json_encode(array('status' => '1', 'ret_msg' => '开奖失败'));

            exit;

        }else{

            $redis = initCacheRedis();

            //shell派奖

            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->admin['userid'])));  //存开奖数据

            deinitCacheRedis($redis);

            // //此处进入开奖派彩的逻辑

            // //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数

            //D('workerman')->theLottery($data['qihao'],[$data['3x9'],$data['28'],$data['kjjg'],$data['tj']],$data['time'],$lt,0,0,array('frequency'=>1));

//                D('workerman')->theLottery($issue, [$spare_1, $numResult, $zwWay, $tj], strtotime($datetime), $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));

            echo json_encode(array('status' => 0));

            return false;

        }

    } //添加到一行结束





    /**

     * 用户投注累计、盈、连赢加分

     * author: Aho

     *

     * @param $userId

     * @param $type

     */

    function set_honor_score($userId, $betMoney, $winMoney, $wins)

    {

        $db = getconn();

        $conf = json_decode($db->result("select value from un_config where nid='honor_upgrade'"), true); // 加分条件



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

                    $score = $v['end'];

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

        $db->query("update un_user set honor_score=$score where id=$userId");

    }



    /**

     * 手动补单

     * @param

     */

    public function openward()

    {

        $flag = 1; //手动补单标志

        $lottery_type = trim($_REQUEST['lottery_type']);

        //初始化redis

        $redis = initCacheRedis();

        $game = $redis->hGet("LotteryType:" . $lottery_type, 'name');

        //关闭redis链接

        deinitCacheRedis($redis);

        switch ($lottery_type) {

            case 1:

                $numberArr = ['A','B','C'];

                $minNumber = 0;

                $maxNumber = 9;

                include template('add-open-award-i');

                break;

            case 2:

                if (!empty($_REQUEST['issue'])) {

                    $issue = trim($_REQUEST['issue']);

                }

                $numberArr = ['A','B','C','D','E','F','G','H','I','J'];

                $minNumber = 1;

                $maxNumber = 10;

                include template('add-open-award-pc10');

                break;

            case 4:

                if (!empty($_REQUEST['issue'])) {

                    $issue = trim($_REQUEST['issue']);

                }

                $numberArr = ['A','B','C','D','E','F','G','H','I','J'];

                $minNumber = 1;

                $maxNumber = 10;

                include template('add-open-award-pc10');

                break;

            case 3:

                $numberArr = ['A','B','C'];

                $minNumber = 0;

                $maxNumber = 9;

                include template('add-open-award-i');

                break;

            case 5:

                $numberArr = ['A','B','C','D','E'];

                $minNumber = 0;

                $maxNumber = 9;

                include template('add-open-award-cqssc');

                break;

            case 6:

                $numberArr = ['A','B','C','D','E'];

                $minNumber = 0;

                $maxNumber = 9;

                include template('add-open-award-cqssc');

                break;

            case 7:

                include template('add-open-award-lhc');

                break;

            case 8:

                include template('add-open-award-lhc');

                break;

            case 9:

                if (!empty($_REQUEST['issue'])) {

                    $issue = trim($_REQUEST['issue']);

                }

                $numberArr = ['A','B','C','D','E','F','G','H','I','J'];

                $minNumber = 1;

                $maxNumber = 10;

                include template('add-open-award-pc10');

                break;

            case 10:

                $keyArr = ['方块', '梅花', '红心', '黑桃'];

                $keyArr2 = ['A','2','3','4','5','6','7','8','9','10', 'J', 'Q', 'K'];

                $numberArr = [

                    'A' => '蓝方牌一',

                    'B' => '蓝方牌二',

                    'C' => '蓝方牌三',

                    'D' => '蓝方牌四',

                    'E' => '蓝方牌五',

                    'F' => '红方牌一',

                    'G' => '红方牌二',

                    'H' => '红方牌三',

                    'I' => '红方牌四',

                    'J' => '红方牌五',

                    ];

                include template('add-open-award-nn');

                break;

            case 11:

                $numberArr = ['A','B','C','D','E'];

                $minNumber = 0;

                $maxNumber = 9;

                include template('add-open-award-cqssc');

                break;

            case 13:

                $numberArr = ['A','B','C'];

                $minNumber = 1;

                $maxNumber = 6;

                include template('add-open-award-i');

                break;

            case 14:

                $numberArr = ['A','B','C','D','E','F','G','H','I','J'];

                $minNumber = 1;

                $maxNumber = 10;

                include template('add-open-award-pc10');

                break;

        }

    }

    

    

    /**

     * 预开奖

     * @param

     */

    public function preOpenAward()

    {

        $flag = trim($_REQUEST['flag']); //预开奖标志

        $lottery_type = trim($_REQUEST['lottery_type']);

        //初始化redis

        $redis = initCacheRedis();

        $game = $redis->hGet("LotteryType:" . $lottery_type, 'name');

        //关闭redis链接

        deinitCacheRedis($redis);

        switch ($lottery_type) {

            case 6:

                $numberArr = ['A','B','C','D','E'];

                $minNumber = 0;

                $maxNumber = 9;

                include template('add-open-award-cqssc');

                break;

            case 8:

                include template('add-open-award-lhc');

                break;

            case 9:

                $numberArr = ['A','B','C','D','E','F','G','H','I','J'];

                $minNumber = 1;

                $maxNumber = 10;

                include template('add-open-award-pc10');

                break;

            case 10:

                $keyArr = ['方块', '梅花', '红心', '黑桃'];

                $keyArr2 = ['A','2','3','4','5','6','7','8','9','10', 'J', 'Q', 'K'];

                $numberArr = [

                    'A' => '蓝方牌一',

                    'B' => '蓝方牌二',

                    'C' => '蓝方牌三',

                    'D' => '蓝方牌四',

                    'E' => '蓝方牌五',

                    'F' => '红方牌一',

                    'G' => '红方牌二',

                    'H' => '红方牌三',

                    'I' => '红方牌四',

                    'J' => '红方牌五',

                    ];

                include template('add-open-award-nn');

                break;

            case 11:

                $numberArr = ['A','B','C','D','E'];

                $minNumber = 0;

                $maxNumber = 9;

                include template('add-open-award-cqssc');

                break;

            case 13:

                $numberArr = ['A','B','C'];

                $minNumber = 1;

                $maxNumber = 6;

                include template('add-open-award-i');

                break;

            case 14:

                $numberArr = ['A','B','C','D','E','F','G','H','I','J'];

                $minNumber = 1;

                $maxNumber = 10;

                include template('add-open-award-pc10');

                break;

        }

    }

    

    /**

     * 开奖处理

     */

    public function dealPreOpendAward()

    {        

        //类型

        $lottery_type = trim($_REQUEST['lottery_type']);

        $flag = trim($_REQUEST['flag']);

        if($flag==2){

            $issue = trim($_REQUEST['issue']);

            if ($lottery_type == 6 || $lottery_type == 11) {

                $sql = "SELECT id FROM un_ssc WHERE issue = '{$issue}' AND lottery_type = {$lottery_type}";

                $res = $this->db->getOne($sql);

                if (!empty($res['id'])) {

                    echo json_encode(array('status' => 1, 'ret_msg' => '该彩种下，该期号已经进行开奖，不能进行预开奖'));

                    return;

                }

                $issue = trim($_REQUEST['issue']);

                if (empty($issue)) {

                    $this->ajaxReturn('', '开奖期号不能为空', 4005);

                }

                $open_time = trim($_REQUEST['open_time']);

                if (empty($open_time)) {

                    $this->ajaxReturn('', '开奖时间不能为空', 4005);

                }

                $arr = array('A', 'B', 'C', 'D', 'E');

                $data = array();

                foreach ($arr as $v) {

                    if (empty($_REQUEST['number' . $v]) && $_REQUEST['number' . $v] != 0) {

                        $this->ajaxReturn('', '开奖号码' . $v . '不能为空', 4005);

                    }

                    if (!preg_match('/^\d{1,2}$/', $_REQUEST['number' . $v])) {

                        $this->ajaxReturn('', '开奖号码' . $v . '请输入0-9的数字', 4005);

                    }

                    $data[] = $_REQUEST['number' . $v];

                }

        

                //将得到的数据更新到数据库中

                $final['issue'] = $issue;

                $final['lottery_type'] = $lottery_type;

                $final['lottery_result'] = implode($data, ',');

                $final['lottery_time'] = strtotime($open_time);

                $final['insert_time'] = time();

                $final['user_id'] = $this->admin['userid'];

                $final['status'] = 2;       //2未开奖

                $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

                $open_id = $this->db->getOne($sql);

                if (empty($open_id['id'])) {

                    $ret = $this->db->insert('un_pre_open', $final);

                }else {

                    $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

                }

                

                if ($ret) {

                    echo json_encode(array('status' => 0));

                }else {

                    echo json_encode(array('status' => 1));

                }

                

                return;

            }else if($lottery_type == 8){

                $sql = "SELECT id FROM un_lhc WHERE issue = '{$issue}' AND lottery_type = {$lottery_type}";

                $res = $this->db->getOne($sql);

                if (!empty($res['id'])) {

                    echo json_encode(array('status' => 1, 'ret_msg' => '该彩种下，该期号已经进行开奖，不能进行预开奖'));

                    return;

                }

                lg('hand_open_log',var_export(array('$_REQUEST'=>$_REQUEST),1));

                $issue = trim($_REQUEST['issue']);

                if (empty($issue)) {

                    $this->ajaxReturn('', '开奖期号不能为空', 4005);

                }

                $open_time = trim($_REQUEST['open_time']);

                if (empty($open_time)) {

                    $this->ajaxReturn('', '开奖时间不能为空', 4005);

                }

                $arr = array('A', 'B', 'C', 'D', 'E','F','G');

                $data = array();

                foreach ($arr as $v) {

                    if (empty($_REQUEST['number' . $v]) && $_REQUEST['number' . $v] != 0) {

                        $this->ajaxReturn('', '开奖号码' . $v . '不能为空', 4005);

                    }

                    if (!preg_match('/^\d{1,2}$/', $_REQUEST['number' . $v])) {

                        $this->ajaxReturn('', '开奖号码' . $v . '请输入1-49的数字', 4005);

                    }

                    $data[] = $_REQUEST['number' . $v];

                }

        

                //将得到的数据更新到数据库中

                $final['issue'] = $issue;

                $final['lottery_type'] = $lottery_type;

                $final['lottery_result'] = implode($data, ',');

                $final['lottery_time'] = strtotime($open_time);

                $final['insert_time'] = time();

                $final['user_id'] = $this->admin['userid'];

                $final['status'] = 2;       //2未开奖

                $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

                $open_id = $this->db->getOne($sql);

                if (empty($open_id['id'])) {

                    $ret = $this->db->insert('un_pre_open', $final);

                }else {

                    $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

                }

                

                if ($ret) {

                    echo json_encode(array('status' => 0));

                }else {

                    echo json_encode(array('status' => 1));

                }

                return;

            }elseif ($lottery_type == 9 || $lottery_type == 14) {

                if($lottery_type==9){

                    $sql = "SELECT id FROM un_bjpk10 WHERE qihao='{$issue}' and lottery_type={$lottery_type}";

                }else{

                    $sql = "SELECT id FROM un_ffpk10 WHERE issue='{$issue}' and lottery_type={$lottery_type}";

                }

                $res = $this->db->getOne($sql);

                if (!empty($res['id'])) {

                    echo json_encode(array('status' => 1, 'ret_msg' => '该彩种下，该期号已经进行开奖，不能进行预开奖'));

                    return;

                }

                $issue = trim($_REQUEST['issue']);

                if (empty($issue)) {

                    $this->ajaxReturn('', '开奖期号不能为空', 4005);

                }

                $open_time = trim($_REQUEST['open_time']);

                if (empty($open_time)) {

                    $this->ajaxReturn('', '开奖时间不能为空', 4005);

                }

                $arr = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J");

                $data = array();

                foreach ($arr as $v) {

                    if (empty($_REQUEST['number' . $v]) && $_REQUEST['number' . $v] != 0) {

                        $this->ajaxReturn('', '开奖号码' . $v . '不能为空', 4005);

                    }

                    if (!preg_match('/^\d{1,2}$/', $_REQUEST['number' . $v])) {

                        $this->ajaxReturn('', '开奖号码' . $v . '请输入0-9的数字', 4005);

                    }

                    $data[] = $_REQUEST['number' . $v];

                }



                $final['lottery_type'] = $lottery_type;

                $final['issue'] = $issue;

                $final['lottery_result'] = implode($data, ',');

                $final['lottery_time'] = strtotime($open_time);

                $final['insert_time'] = time();

                $final['user_id'] = $this->admin['userid'];

                $final['status'] = 2;       //2未开奖

                $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

                $open_id = $this->db->getOne($sql);

                if (empty($open_id['id'])) {

                    $ret = $this->db->insert('un_pre_open', $final);

                }else {

                    $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

                }

                

                if ($ret) {

                    echo json_encode(array('status' => 0));

                }else {

                    echo json_encode(array('status' => 1));

                }



                return ;

            }elseif ($lottery_type == 10) {

                $sql = "SELECT id FROM un_nn WHERE issue='{$issue}' and lottery_type={$lottery_type}";

                $res = $this->db->getOne($sql);

                if (!empty($res['id'])) {

                    echo json_encode(array('status' => 1, 'ret_msg' => '该彩种下，该期号已经进行开奖，不能进行预开奖'));

                    return;

                }

                $issue = trim($_REQUEST['issue']);

                if (empty($issue)) {

                    $this->ajaxReturn('', '开奖期号不能为空', 4005);

                }

                $open_time = trim($_REQUEST['open_time']);

                if (empty($open_time)) {

                    $this->ajaxReturn('', '开奖时间不能为空', 4005);

                }

                $arr = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',];

                $data = [];

                foreach ($arr as $v) {

                    $tmp_number_v = $_REQUEST['number' . $v];

                    if (empty($tmp_number_v) && $tmp_number_v != 0) {

                        $this->ajaxReturn('', '开奖号码' . $v . '不能为空', 4005);

                    }

                    if (!preg_match('/^(?:方块|梅花|红心|黑桃)(?:[2-9]|10|[AJQK])$/i', $tmp_number_v)) {

                        $this->ajaxReturn('', "开奖号码{$v}【{$tmp_number_v}】不是合法的扑克牌面", 4005);

                    }

                    $data[] = poker2num($tmp_number_v);

                }

                

                //将得到的数据更新到数据库中

                $final['lottery_type'] = $lottery_type;

                $final['issue'] = $issue;

                $final['lottery_result'] = implode($data, ',');

                $final['lottery_time'] = strtotime($open_time);

                $final['insert_time'] = time();

                $final['user_id'] = $this->admin['userid'];

                $final['status'] = 2;       //2未开奖

                $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

                $open_id = $this->db->getOne($sql);

                if (empty($open_id['id'])) {

                    $ret = $this->db->insert('un_pre_open', $final);

                }else {

                    $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

                }

                

                if ($ret) {

                    echo json_encode(array('status' => 0));

                }else {

                    echo json_encode(array('status' => 1));

                }



                return;

            }elseif ($lottery_type == 13) {

                $sql = "SELECT id FROM un_sb WHERE issue = '{$issue}' AND lottery_type = {$lottery_type}";

                $res = $this->db->getOne($sql);

                if (!empty($res['id'])) {

                    echo json_encode(array('status' => 1, 'ret_msg' => '该彩种下，该期号已经进行开奖，不能进行预开奖'));

                    return;

                }

                $issue = trim($_REQUEST['issue']);

                if (empty($issue)) {

                    $this->ajaxReturn('', '开奖期号不能为空', 4005);

                }

                $open_time = trim($_REQUEST['open_time']);

                if (empty($open_time)) {

                    $this->ajaxReturn('', '开奖时间不能为空', 4005);

                }

                $arr = array('A', 'B', 'C');

                $data = array();

                foreach ($arr as $v) {

                    if (empty($_REQUEST['number' . $v]) && $_REQUEST['number' . $v] != 0) {

                        $this->ajaxReturn('', '开奖号码' . $v . '不能为空', 4005);

                    }

                    if (!preg_match('/^\d{1,2}$/', $_REQUEST['number' . $v])) {

                        $this->ajaxReturn('', '开奖号码' . $v . '请输入1-6的数字', 4005);

                    }

                    $data[] = $_REQUEST['number' . $v];

                }

        

                //将得到的数据更新到数据库中

                $final['issue'] = $issue;

                $final['lottery_type'] = $lottery_type;

                $final['lottery_result'] = implode($data, ',');

                $final['lottery_time'] = strtotime($open_time);

                $final['insert_time'] = time();

                $final['user_id'] = $this->admin['userid'];

                $final['status'] = 2;       //2未开奖

                $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

                $open_id = $this->db->getOne($sql);

                if (empty($open_id['id'])) {

                    $ret = $this->db->insert('un_pre_open', $final);

                }else {

                    $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

                }

                

                if ($ret) {

                    echo json_encode(array('status' => 0));

                }else {

                    echo json_encode(array('status' => 1));

                }

                

                return;

            }else {

                echo json_encode(array('status' => 1));

            }
            
        }else{
            $numberOfIssues = trim($_REQUEST['numberOfIssues']);

            if ($lottery_type == 6 || $lottery_type == 11) {

                $sql = "SELECT issue, lottery_time FROM un_ssc WHERE lottery_type = {$lottery_type} ORDER BY issue DESC";

                $res = $this->db->getOne($sql);

                for($n=1; $n<=$numberOfIssues; $n++){
            
                    $arr = array('A', 'B', 'C', 'D', 'E');

                    $data = array();

                    foreach ($arr as $v) {               

                        $data[] = rand(0, 9);

                    }    

                    //将得到的数据更新到数据库中

                    $final['issue'] = $res['issue']+$n;

                    $final['lottery_type'] = $lottery_type;

                    $final['lottery_result'] = implode($data, ',');

                    if($lottery_type==6){

                        $final['lottery_time'] = $res['lottery_time']+180*$n;

                        $final['insert_time'] = time()+180*$n;

                    }else{

                        $final['lottery_time'] = $res['lottery_time']+60*$n;

                        $final['insert_time'] = time()+60*$n;
                    
                    }

                    $final['user_id'] = $this->admin['userid'];

                    $final['status'] = 2;       //2未开奖

                    $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

                    $open_id = $this->db->getOne($sql);

                    if (empty($open_id['id'])) {

                        $ret = $this->db->insert('un_pre_open', $final);

                    }else {

                        $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

                    }
                }

                if ($ret) {

                    echo json_encode(array('status' => 0));

                }else {

                    echo json_encode(array('status' => 1));

                }            

                return;

            }else if($lottery_type == 8){

                $sql = "SELECT issue, lottery_time FROM un_lhc WHERE lottery_type = {$lottery_type} ORDER BY issue DESC";

                $res = $this->db->getOne($sql);
                
                for($n=1; $n<=$numberOfIssues; $n++){

                    $arr = array('A', 'B', 'C', 'D', 'E','F','G');

                    $data = array();

                    foreach ($arr as $v) {

                        if (empty($_REQUEST['number' . $v]) && $_REQUEST['number' . $v] != 0) {

                            $this->ajaxReturn('', '开奖号码' . $v . '不能为空', 4005);

                        }

                        if (!preg_match('/^\d{1,2}$/', $_REQUEST['number' . $v])) {

                            $this->ajaxReturn('', '开奖号码' . $v . '请输入1-49的数字', 4005);

                        }

                        $data[] = rand();

                    }        

                    //将得到的数据更新到数据库中

                    $final['issue'] = $res['issue']+$n;

                    $final['lottery_type'] = $lottery_type;

                    $final['lottery_result'] = implode($data, ',');

                    $final['lottery_time'] = $res['lottery_time']+180*$n;

                    $final['insert_time'] = time()+180*$n;

                    $final['user_id'] = $this->admin['userid'];

                    $final['status'] = 2;       //2未开奖

                    $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

                    $open_id = $this->db->getOne($sql);

                    if (empty($open_id['id'])) {

                        $ret = $this->db->insert('un_pre_open', $final);

                    }else {

                        $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

                    }

                }

                if ($ret) {

                    echo json_encode(array('status' => 0));

                }else {

                    echo json_encode(array('status' => 1));

                }

                return;

            }elseif ($lottery_type == 9 || $lottery_type == 14) {

                if($lottery_type==9){

                    $sql = "SELECT qihao, kaijiangshijian FROM un_bjpk10 WHERE lottery_type={$lottery_type} ORDER BY qihao DESC";

                }else{

                    $sql = "SELECT issue, lottery_time FROM un_ffpk10 WHERE lottery_type={$lottery_type} ORDER BY issue DESC";

                }

                $res = $this->db->getOne($sql);

                for($n=1; $n<=$numberOfIssues; $n++){

                    $arr = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J");

                    $data = array();

                    foreach ($arr as $v) {                        

                        $data[] = rand(1, 10);

                    }

                    $final['lottery_type'] = $lottery_type;                    

                    $final['lottery_result'] = implode($data, ',');

                    if($lottery_type==9){

                        $final['issue'] = $res['qihao']+$n;

                        $final['lottery_time'] = $res['kaijiangshijian']+180*$n;

                        $final['insert_time'] = time()+180*$n;

                    }else{

                        $final['issue'] = $res['issue']+$n;

                        $final['lottery_time'] = $res['lottery_time']+60*$n;

                        $final['insert_time'] = time()+60*$n;
                    
                    }

                    $final['user_id'] = $this->admin['userid'];

                    $final['status'] = 2;       //2未开奖

                    $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

                    $open_id = $this->db->getOne($sql);

                    if (empty($open_id['id'])) {

                        $ret = $this->db->insert('un_pre_open', $final);

                    }else {

                        $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

                    }

                }                

                if ($ret) {

                    echo json_encode(array('status' => 0));

                }else {

                    echo json_encode(array('status' => 1, 'ret_msg' => json_encode($this->db->error())));

                }



                return ;

            }elseif ($lottery_type == 10) {

                $sql = "SELECT issue, lottery_time FROM un_nn WHERE lottery_type={$lottery_type} ORDER BY issue DESC";

                $res = $this->db->getOne($sql);
                
                for($n=1; $n<=$numberOfIssues; $n++){

                    $arr = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',];

                    $keyArr = ['方块', '梅花', '红心', '黑桃'];

                    $keyArr2 = ['A','2','3','4','5','6','7','8','9','10', 'J', 'Q', 'K'];

                    $data = [];

                    foreach ($arr as $v) {                        

                        $data[] = poker2num($keyArr[array_rand($keyArr)].$keyArr2[array_rand($keyArr2)]);

                    }                    

                    //将得到的数据更新到数据库中

                    $final['lottery_type'] = $lottery_type;

                    $final['issue'] = $res['issue']+$n;

                    $final['lottery_result'] = implode($data, ',');

                    $final['lottery_time'] = $res['lottery_time']+300*$n;

                    $final['insert_time'] = time()+300*$n;

                    $final['user_id'] = $this->admin['userid'];

                    $final['status'] = 2;       //2未开奖

                    $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

                    $open_id = $this->db->getOne($sql);

                    if (empty($open_id['id'])) {

                        $ret = $this->db->insert('un_pre_open', $final);

                    }else {

                        $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

                    }

                }                

                if ($ret) {

                    echo json_encode(array('status' => 0));

                }else {

                    echo json_encode(array('status' => 1));

                }



                return;

            }elseif ($lottery_type == 13) {

                $sql = "SELECT issue, lottery_time FROM un_sb WHERE lottery_type = {$lottery_type} ORDER BY issue DESC";

                $res = $this->db->getOne($sql);

                for($n=1; $n<=$numberOfIssues; $n++){
            
                    $arr = array('A', 'B', 'C');

                    $data = array();

                    foreach ($arr as $v) {               

                        $data[] = rand(1, 6);

                    }    

                    //将得到的数据更新到数据库中

                    $final['issue'] = $res['issue']+$n;

                    $final['lottery_type'] = $lottery_type;

                    $final['lottery_result'] = implode($data, ',');

                    $final['lottery_time'] = $res['lottery_time']+300*$n;

                    $final['insert_time'] = time()+300*$n;                    

                    $final['user_id'] = $this->admin['userid'];

                    $final['status'] = 2;       //2未开奖

                    $sql = "SELECT id FROM un_pre_open WHERE issue = '{$issue}' AND lottery_type = {$lottery_type} AND user_id > 0";

                    $open_id = $this->db->getOne($sql);

                    if (empty($open_id['id'])) {

                        $ret = $this->db->insert('un_pre_open', $final);

                    }else {

                        $ret = $this->db->update('un_pre_open', $final, ['id' => $open_id['id']]);

                    }
                }

                if ($ret) {

                    echo json_encode(array('status' => 0));

                }else {

                    echo json_encode(array('status' => 1));

                }            

                return;

            }else {

                echo json_encode(array('status' => 1));

            }
        }



        return;

    }


    

    public function diff_issue()

    {

        $lottery_type = $_REQUEST['lottery_type'];

        $lottery_result = $_REQUEST['lottery_result'];

        $issue = $_REQUEST['issue'];

        

        $lottery_info = get_lottery_info($lottery_type);

        $sql = "SELECT * FROM {$lottery_info['table']} WHERE {$lottery_info['issue']}='{$issue}' and lottery_type={$lottery_type}";

        $lottery_data = $this->db->getOne($sql);

        

        if ($lottery_type == 1 || $lottery_type == 3) {

            $lottery_data['sqare_1'] = strtr($lottery_data['sqare_1'], '+', ' ');

        }

        

        include template('diff-issue');

    }



    public function cupList(){

        $match_id = $_REQUEST['match_id'];

        $where = "where lottery_type = 12";

        if (!empty($match_id)) {

            $where .= " and r.match_id = {$_REQUEST['match_id']}";

        }

        $sql = "select count(r.id) from un_room r left join un_cup_against a  on a.match_id = r.match_id {$where}";

        $count = $this->db->result($sql);

        $pageSize = 3;

        $page = new page($count, $pageSize, "?m=admin&c=openAward&a=cupList", ['match_id'=>$match_id]);

        $show = $page->show();

        $order = 'order by id desc';

        $limit = "limit ".$page->offer.",".$pageSize;

        $sql = "select a.id,a.event_name,a.match_date,a.match_state,a.team_1_name,a.team_2_name,a.match_id,a.first_result,a.second_result,a.overtime_result,a.penalty_result from un_room r left join un_cup_against a  on a.match_id = r.match_id {$where} {$order} {$limit}";

        $data = $this->db->getall($sql);



        //四个比赛节点字段

        $field_arr_in_base_data = [

            [

                'match_status' => 2,

                'field_name' => 'first_result',

                'view_text' => '上半场比分',

            ],

            [

                'match_status' => 4,

                'field_name' => 'second_result',

                'view_text' => '下半场（90分钟含补时）比分',

            ],

            [

                'match_status' => 6,

                'field_name' => 'overtime_result',

                'view_text' => '加时比分',

            ],

            [

                'match_status' => 8,

                'field_name' => 'penalty_result',

                'view_text' => '点球比分',

            ],

        ];

        $match_info = [];

        $redis = initCacheRedis();

        $room_ids = $redis->lrange("allroomIds",0,-1);

        foreach ($room_ids as $val) {

            $room_info = $redis -> hGetAll("allroom:".$val);

            if ($room_info['lottery_type'] == 12) {

                $tmp['room_name'] = $room_info['title'];

                $tmp['match_id'] = $room_info['match_id'];

                $match_info[] = $tmp;

            }

        }

        

        foreach ($data as $key=>$val){

            $data[$key]['match_date'] = date("Y-m-d H:i:s",$val['start_time']);



            $tmp_result_arr = [];

            foreach ($field_arr_in_base_data as $field_key => $field_val) {

                //将四个比赛节点比分数据，赋值到新的关联数组中

                $tmp_result_arr[$field_key] = [

                    'view_text' => $field_val['view_text'],

                    'node_name' => $field_val['field_name'],

                    'result' => $val[$field_val['field_name']],

                    'match_status' => $field_val['match_status']

                ];

            }

            $data[$key]['result_arr'] = $tmp_result_arr;

        }



        include template('list-open-award-cup');

    }

    

    //世界杯手动派奖

    public function cupOpenWard()

    {

        $score = trim($_REQUEST['match_score']);

        $match_status = trim($_REQUEST['match_status']);

        $match_id = trim($_REQUEST['match_id']);

        

        if (empty($score)) {

            echo json_encode(['code' => 0, 'msg' => '比赛分数不能为空']);

            return;

        }

        

        if (empty($match_id)) {

            echo json_encode(['code' => 0, 'msg' => '比赛ID不能为空']);

            return;

        }

        

        if (empty($match_status)) {

            echo json_encode(['code' => 0, 'msg' => '比赛状态不能为空']);

            return;

        }



        $sql = "select ca.*, r.id as room_id from un_cup_against ca left join un_room r on ca.match_id = r.match_id where ca.match_id = " . $match_id;

        $match_data = $this->db->getOne($sql);

        

        if (empty($match_data)) {

            echo json_encode(['code' => 0, 'msg' => '比赛不存在空']);

            return;

        }



        $tdata = array(

            'status' => 1,

            'uid'    => $this->admin['userid'],

            'bi_feng' => $score,

            'room_id' => $match_data['room_id'],

            'type' => $match_status, //场子类型

            'time' => time(), //结束时间

        );



        $redis = initCacheRedis();

        $redis->hsetnx('pc_lottery_type:12',$match_id,encode($tdata));  //存开奖数据

        lg('word_cup_auto','派彩逻辑'.var_export(array('从Redis取出来的数据'=>$redis->hgetall('pc_lottery_type:12')),1));

        deinitCacheRedis($redis);

        

        $this->refreshRedis("fb_against", "all");

        

        echo json_encode(['code' => 1, 'msg' => '派奖已提交成功，派奖进行中~~~！']);



        return;

    }

    

    public function matchScore()

    {

        $matchInfo = [];

        $match_id = trim($_REQUEST['match_id']);

        $match_status =  trim($_REQUEST['match_status']);

        

        $sql = "select a.id,a.event_name,a.match_date,a.match_state,a.team_1_name,a.team_2_name,a.match_id,a.first_result,a.second_result,a.overtime_result,a.penalty_result from un_cup_against a 

                where a.match_id = " . $match_id;

        $matchData = $this->db->getOne($sql);

        

        $matchInfo['match_name'] = '【' . $matchData['event_name'] . '】 ' . $matchData['team_1_name'] . ' vs ' . $matchData['team_2_name'];

        

        if ($match_status == 2) {

            if (empty($matchData['first_result'])) {

                $matchInfo['score'][0] = '';

                $matchInfo['score'][1] = '';

            }else {

                $matchInfo['score'] = explode(':', $matchData['first_result']);

            }

            

            $matchInfo['status'] = '上半场';

        }elseif ($match_status == 4) {

            if (empty($matchData['second_result'])) {

                $matchInfo['score'][0] = '';

                $matchInfo['score'][1] = '';

            }else {

                $matchInfo['score'] = explode(':', $matchData['second_result']);

            }

            

            $matchInfo['status'] = '下半场';

        }elseif ($match_status == 6) {

            if (empty($matchData['overtime_result'])) {

                $matchInfo['score'][0] = '';

                $matchInfo['score'][1] = '';

            }else {

                $matchInfo['score'] = explode(':', $matchData['overtime_result']);

            }

            

            $matchInfo['status'] = '加时赛';

        }elseif ($match_status == 8) {

            if (empty($matchData['penalty_result'])) {

                $matchInfo['score'][0] = '';

                $matchInfo['score'][1] = '';

            }else {

                $matchInfo['score'] = explode(':', $matchData['penalty_result']);

            }

            

            $matchInfo['status'] = '点球';

        }else {

            $matchInfo['score'][0] = '';

            $matchInfo['score'][1] = '';

            $matchInfo['status'] = '';

        }

        

        include template('entry_score');

    }

    

    public function setMatchScore()

    {

        $score = '';

        $match_id = $_REQUEST['match_id'];

        $match_status = $_REQUEST['match_status'];

        $match_score_1 = $_REQUEST['match_score_1'];

        $match_score_2 = $_REQUEST['match_score_2'];

        

        if ($match_score_1 == '' || (int)$match_score_1 != $match_score_1 || $match_score_1 == '' || (int)$match_score_2 != $match_score_2) {

            echo json_encode(['code' => 0, 'msg' => '比赛分数为整数,不能为空']);

            return;

        }

        

        if ($match_score_1 < 0 || $match_score_2 < 0) {

            echo json_encode(['code' => 0, 'msg' => '比赛分数必须大于0']);

            return;

        }

        

        $score = $match_score_1 . ':' . $match_score_2;

        

        if (empty($match_id)) {

            echo json_encode(['code' => 0, 'msg' => '比赛ID不能为空']);

            return;

        }

        

        if (empty($match_status)) {

            echo json_encode(['code' => 0, 'msg' => '比赛状态不能为空']);

            return;

        }

        

        if ($match_status == 2) {

            $ret = $this->db->update('un_cup_against', ['first_result' => $score,'match_state'=>$match_status], ['match_id' => $match_id]);

        }elseif ($match_status == 4) {

            $ret = $this->db->update('un_cup_against', ['second_result' => $score,'match_state'=>$match_status], ['match_id' => $match_id]);

        }elseif ($match_status == 6) {

            $ret = $this->db->update('un_cup_against', ['overtime_result' => $score,'match_state'=>$match_status], ['match_id' => $match_id]);

        }elseif ($match_status == 8) {

            $ret = $this->db->update('un_cup_against', ['penalty_result' => $score,'match_state'=>$match_status], ['match_id' => $match_id]);

        }else {

            echo json_encode(['code' => 0, 'msg' => '比赛状态错误']);

            return;

        }

        

        if ($ret) {

            echo json_encode(['code' => 1, 'msg' => '比分设置成功！']);

        }else {

            echo json_encode(['code' => 0, 'msg' => '比分设置失败！']);

        }

    }

    //生成新的列表数据【暂不使用】

    public function _makeNewCupList($base_data)

    {

        if (! $base_data) {

            return [];

        }



        //四个比赛节点字段

        $field_arr_in_base_data = [

            'first_result',

            'second_result',

            'overtime_result',

            'penalty_result',

        ];



        $new_cup_list = [];

        foreach ($base_data as $base_key => $base_val) {



            $new_cup_list[$base_key] = [

                'id' => $base_val['id'],

                'event_name' => $base_val['event_name'],

                'team_1_name' => $base_val['team_1_name'],

                'team_2_name' => $base_val['team_2_name'],

                'match_id' => $base_val['match_id'],

            ];



            $tmp_result_arr = [];

            foreach ($field_arr_in_base_data as $field_key => $field_val) {

                //将四个比赛节点比分数据，赋值到新的关联数组中

                $tmp_result_arr[$field_key] = [

                    'node_name' => $field_val,

                    'result' => $base_val[$field_val],

                ];

            }

            $new_cup_list[$base_key]['result_arr'] = $tmp_result_arr;

        }

        return $new_cup_list;

    }

}

