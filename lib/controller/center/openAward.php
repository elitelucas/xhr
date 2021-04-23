<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'center' . DS . 'action.php');
include S_CORE . 'class' . DS . 'pages.php';

class OpenAwardAction extends Action{

    public function __construct() {
        parent::__construct();

        if (!$this->checkAuth()) {
            $this->retArr['data'][] = $_REQUEST;
            $this->returnCurl();
            return;
        }
        
    }

    
    /*
     *  彩种开奖记录   
     *    
     * */
    public function openAwardData() {
  
        $lottery_type = $this->getParame('lottery_type',1,'','int','开奖彩种为空或格式错误');
        
        switch ($lottery_type) {
            case 1:     //辛运28
                $this->openAwardData_1_3($lottery_type);
                break;
            case 2:     //北京PK10
                $this->openAwardData_2_9($lottery_type);
                break;
            case 3:         //加拿大28
                $this->openAwardData_1_3($lottery_type);
                break;
            case 4:     //辛运飞艇
                $this->openAwardData_4($lottery_type);
                break;
            case 5:     //重庆时时彩
                $this->openAwardData_5_6_11($lottery_type);
                break;
            case 6:     //三分彩
                $this->openAwardData_5_6_11($lottery_type);
                break;
            case 7:     //六合彩
                $this->openAwardData_7_8($lottery_type);
                break;
            case 8:     //极速六合彩
                $this->openAwardData_7_8($lottery_type);
                break;
            case 9:     //急速赛车
                $this->openAwardData_2_9($lottery_type);
                break;
            case 10:     //百人牛牛
                $this->openAwardData_10($lottery_type);
                break;
            case 11:     //分分彩
                $this->openAwardData_5_6_11($lottery_type);
                break;
            case 13:     //欢乐骰宝
                $this->openAwardData_13($lottery_type);
                break;
            case 14:     //分分PK10
                $this->openAwardData_14($lottery_type);
                break;
        }
    }
    
    private function openAwardData_14($lottery_type) {
        $issue = $this->getParame('issue',0);
        $state = $this->getParame('state',0);
        $page = $this->getParame('page',0, 1, 'int');
        
        $where = ' where lottery_type='.$lottery_type;
        $issue && $where .= ' and issue = '.$issue;
        $state && $where .= ' and status = '.$state;
        
        $pagesize = 20;
        $sql = 'select count(*) as num from un_ffpk10' . $where;
        $cnt = $this->db->result($sql);
        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);
        $show = $page->show();
        $show = str_replace('onclick="skip_page()"', '', $show);
        $limit = $page->offer . ',' . $pagesize;

        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_ffpk10 {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";
        $list = $this->db->getall($sql);
        $openData = [];
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
                $admin = $this->db->getone("select username from un_admin where userid={$v['user_id']}");
            } else {
                $admin['username'] = "未知userid-" . $v['user_id'];
            }
            // dump($v['call_back_uid']);
            if (!empty($v['call_back_uid'])) {
                $cbadmin = $this->db->getone("select username from un_admin where userid={$v['call_back_uid']}");
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
            $openData[] = [
                'id' => $v['id'],
                'issue' => $v['issue'],
                'open_num' => $list[$k]['open_result'],
                'open_result' => implode(',', $list[$k]['open_result1']),
                'open_time' => date('Y-m-d H:i:s',$v['open_time']),
                'insert_time' =>date('Y-m-d H:i:s',$v['insert_time']),
                'state' => $v['state'],
                'user_id' => $v['user_id'],
            ];
        }
        $data = [
            'page' => $show,
            'list' => $openData,
        ];
        $this->returnCurl($data);
    }


    private function openAwardData_13($lottery_type) {
        $issue = $this->getParame('issue',0);
        $state = $this->getParame('state',0);
        $page = $this->getParame('page',0, 1, 'int');
        
        $where = ' where lottery_type='.$lottery_type;
        $issue && $where .= ' and issue = '.$issue;
        $state && $where .= ' and status = '.$state;
        
        $pagesize = 20;
        $sql = 'select count(*) as num from un_sb' . $where;
        $cnt = $this->db->result($sql);
        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);
        $show = $page->show();
        $show = str_replace('onclick="skip_page()"', '', $show);
        $limit = $page->offer . ',' . $pagesize;
        
        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_sb {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";
        $list = $this->db->getall($sql);
        $openData = [];
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
            $spare_2 = D('workerman')->kaijiang_result_sb($v['open_result']);
//            dump($spare_2);
            $temp = array();
            $temp[] = $spare_2[9];
            $temp[] = $spare_2[10];
            $temp[] = $spare_2[11];
            $list[$k]['open_result1'] = $temp;
            $openData[] = [
                'id' => $v['id'],
                'issue' => $v['issue'],
                'open_num' => $list[$k]['open_result'],
                'open_result' => implode(',', $list[$k]['open_result1']),
                'open_time' => date('Y-m-d H:i:s',$v['open_time']),
                'insert_time' =>date('Y-m-d H:i:s',$v['insert_time']),
                'state' => $v['state'],
                'user_id' => $v['user_id'],
            ];
        }
        $data = [
            'page' => $show,
            'list' => $openData,
        ];
        $this->returnCurl($data);
    }


    private function openAwardData_10($lottery_type) {
        $issue = $this->getParame('issue',0);
        $state = $this->getParame('state',0);
        $page = $this->getParame('page',0, 1, 'int');
        
        $where = ' where lottery_type='.$lottery_type;
        $issue && $where .= ' and issue = '.$issue;
        $state && $where .= ' and status = '.$state;
        
        $pagesize = 20;
        $sql = 'select count(*) as num from un_nn' . $where;
        $cnt = $this->db->result($sql);
        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);
        $show = $page->show();
        $show = str_replace('onclick="skip_page()"', '', $show);
        $limit = $page->offer . ',' . $pagesize;
        
        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_nn {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";
        $list = $this->db->getall($sql);
        $openData = [];
        foreach ($list as $k => $v) {
            if (!in_array($v['state'], array('0', '1'))) {
                //锁定
                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');
                if (!empty($res)) {
                    $v['user_id'] = $res['click_uid'];
                    $list[$k]['user_id'] = $v['user_id'];
                }
            }
          
            if($v['open_result']) {
                $re = getShengNiuNiu($v['open_result']);
            } else {
                $re = ['', '', ''];
            }
            $list[$k]['open_result'] = $re[2];
            $list[$k]['open_result1'] = array($re[0],$re[1]); 
           
            $openData[] = [
                'id' => $v['id'],
                'issue' => $v['issue'],
                'open_num' => $list[$k]['open_result'],
                'open_result' => implode(',', $list[$k]['open_result1']),
                'open_time' => date('Y-m-d H:i:s',$v['open_time']),
                'insert_time' =>date('Y-m-d H:i:s',$v['insert_time']),
                'state' => $v['state'],
                'user_id' => $v['user_id'],
            ];
        }
        $data = [
            'page' => $show,
            'list' => $openData,
        ];
        $this->returnCurl($data);
    }

    private function openAwardData_7_8($lottery_type) {
        $issue = $this->getParame('issue',0);
        $state = $this->getParame('state',0);
        $page = $this->getParame('page',0, 1, 'int');
        
        $where = ' where lottery_type='.$lottery_type;
        $issue && $where .= ' and issue = '.$issue;
        $state && $where .= ' and status = '.$state;
        
        $pagesize = 20;
        $sql = 'select count(*) as num from un_lhc' . $where;
        $cnt = $this->db->result($sql);
        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);
        $show = $page->show();
        $show = str_replace('onclick="skip_page()"', '', $show);
        $limit = $page->offer . ',' . $pagesize;

        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_lhc {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";
        $list = $this->db->getall($sql);
        $openData = [];
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
            $temp = array();
            foreach (explode(',',$v['open_result']) as $sv){
                $temp[] = getLhcShengxiao($sv,$v['open_time']);
            }
            $openData[] = [
                'id' => $v['id'],
                'issue' => $v['issue'],
                'open_num' => $v['open_result'],
                'open_result' => implode(',', $temp),
                'open_time' => date('Y-m-d H:i:s',$v['open_time']),
                'insert_time' =>date('Y-m-d H:i:s',$v['insert_time']),
                'state' => $v['state'],
                'user_id' => $v['user_id'],
            ];
        }
        $data = [
            'page' => $show,
            'list' => $openData,
        ];
        $this->returnCurl($data);
    }

    private function openAwardData_5_6_11($lottery_type) {
        $issue = $this->getParame('issue',0);
        $state = $this->getParame('state',0);
        $page = $this->getParame('page',0, 1, 'int');
        
        $where = ' where lottery_type='.$lottery_type;
        $issue && $where .= ' and issue = '.$issue;
        $state && $where .= ' and status = '.$state;
        
        $pagesize = 20;
        $sql = 'select count(*) as num from un_ssc' . $where;
        $cnt = $this->db->result($sql);
        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);
        $show = $page->show();
        $show = str_replace('onclick="skip_page()"', '', $show);
        $limit = $page->offer . ',' . $pagesize;
        
        $sql = "SELECT `id`,issue,`status` as state,`lottery_result` AS open_result,`lottery_time` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_ssc {$where} and lottery_type={$lottery_type} ORDER BY issue DESC LIMIT {$limit}";
        $list = $this->db->getall($sql);
        $openData = [];
        foreach ($list as $k => $v) {
            if (!in_array($v['state'], array('0', '1'))) {
                //锁定
                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');
                if (!empty($res)) {
                    $v['user_id'] = $res['click_uid'];
                    $list[$k]['user_id'] = $v['user_id'];
                }
            }
           
            $spare_2 = D('workerman')->kaijiang_result_ssc($v['open_result']);
            $temp = array();
            if($lottery_type == 11) {
                $temp[] = str_replace('第一球_','',$spare_2[1]);
                $temp[] = str_replace('第一球_','',$spare_2[2]);
            } else {
                $temp[] = str_replace('总和_','',$spare_2[21]);
                $temp[] = str_replace('总和_','',$spare_2[22]);
            }
            
            $temp[] = $spare_2[23];
            $openData[] = [
                'id' => $v['id'],
                'issue' => $v['issue'],
                'open_num' => $v['open_result'],
                'open_result' => implode(',', $temp),
                'open_time' => date('Y-m-d H:i:s',$v['open_time']),
                'insert_time' =>date('Y-m-d H:i:s',$v['insert_time']),
                'state' => $v['state'],
                'user_id' => $v['user_id'],
            ];
        }
        $data = [
            'page' => $show,
            'list' => $openData,
        ];
        $this->returnCurl($data);
    }

    private function openAwardData_4($lottery_type){
        $issue = $this->getParame('issue',0);
        $state = $this->getParame('state',0);
        $page = $this->getParame('page',0, 1, 'int');
        
        $where = ' where 1=1';
        $issue && $where .= ' and qihao = '.$issue;
        $state && $where .= ' and status = '.$state;
        
        $pagesize = 20;
        
        $sql = 'select count(*) as num from un_xyft' . $where;
        $cnt = $this->db->result($sql);
        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);
        $show = $page->show();
        $show = str_replace('onclick="skip_page()"', '', $show);
        $limit = $page->offer . ',' . $pagesize;

        $sql = "SELECT `id`,`qihao` as issue,`status` as state,`kaijianghaoma` AS open_result,`kaijiangshijian` as open_time,user_id,is_call_back,call_back_uid,insert_time FROM un_xyft {$where} ORDER BY qihao DESC LIMIT {$limit}";
        $list = O('model')->db->getall($sql);
        $openData = [];
        foreach ($list as $k => $v) {
            if (!in_array($v['state'], array('0', '1'))) {
                //锁定
                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');
                if (!empty($res)) {
                    $v['user_id'] = $res['click_uid'];
                    $list[$k]['user_id'] = $v['user_id'];
                }
            }
    
            $spare_2 = D('workerman')->kaijiang_result($v['open_result']);
            $strleng = mb_strlen($spare_2[4]) - 1;
            //定义大小单双玩法数组
            $temp = array();
            $temp[] = mb_substr($spare_2[4], 0, 1, 'utf-8');
            $temp[] = mb_substr($spare_2[4], $strleng, 1, 'utf-8');
            $temp[] = $spare_2[5];
            $openData[] = [
                'id' => $v['id'],
                'issue' => $v['issue'],
                'open_num' => $v['open_result'],
                'open_result' => implode(',', $temp),
                'open_time' => $v['open_time'],
                'insert_time' => $v['insert_time'],
                'state' => $v['state'],
                'user_id' => $v['user_id'],
            ];
        }
        $data = [
            'page' => $show,
            'list' => $openData,
        ];
        $this->returnCurl($data);
    }

    private function openAwardData_2_9($lottery_type) {
        $issue = $this->getParame('issue',0);
        $state = $this->getParame('state',0);
        $page = $this->getParame('page',0, 1, 'int');
        
        $where = ' where lottery_type='.$lottery_type;
        $issue && $where .= ' and qihao = '.$issue;
        $state && $where .= ' and status = '.$state;
        
        $pagesize = 20;
        $sql = 'select count(*) as num from un_bjpk10' . $where;
        $cnt = $this->db->result($sql);
        $page = new pages($cnt, $pagesize, url('', '', ''), $_REQUEST);
        $show = $page->show();
        $show = str_replace('onclick="skip_page()"', '', $show);
        
        $limit = $page->offer . ',' . $pagesize;
        $sql = "SELECT `id`,`qihao` as issue,`status` as state,`kaijianghaoma` AS open_result,`kaijiangshijian` as open_time,user_id,is_call_back,call_back_uid ,insert_time FROM un_bjpk10 {$where} ORDER BY qihao DESC LIMIT {$limit}";
        $list = O('model')->db->getall($sql);
        $openData = [];
        foreach ($list as $k => $v) {
            if (!in_array($v['state'], array('0', '1'))) {
                //锁定
                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');
                if (!empty($res)) {
                    $v['user_id'] = $res['click_uid'];
                    $list[$k]['user_id'] = $v['user_id'];
                }
            }
  
            $spare_2 = D('workerman')->kaijiang_result($v['open_result']);
            $strleng = mb_strlen($spare_2[4]) - 1;
            //定义大小单双玩法数组
            $temp = array();
            $temp[] = mb_substr($spare_2[4], 0, 1, 'utf-8');
            $temp[] = mb_substr($spare_2[4], $strleng, 1, 'utf-8');
            $temp[] = $spare_2[5];
            
            $openData[] = [
                'id' => $v['id'],
                'issue' => $v['issue'],
                'open_num' => $v['open_result'],
                'open_result' => implode(',', $temp),
                'open_time' => $v['open_time'],
                'insert_time' => $v['insert_time'],
                'state' => $v['state'],
                'user_id' => $v['user_id'],
            ];
        }
        $data = [
            'page' => $show,
            'list' => $openData,
        ];
        $this->returnCurl($data);
    }

    

    private function openAwardData_1_3($lottery_type) {
        $issue = $this->getParame('issue',0);
        $state = $this->getParame('state',0);
        $page = $this->getParame('page',0, 1, 'int');
        
        $where['lottery_type'] = $lottery_type;
        $issue && $where['issue'] = $issue;
        $state && $where['state'] = $state;
        
        $pagesize = 20;
        $numArr = D('openaward')->getOneCoupon('COUNT(*) as num', $where);
            
        $where['page'] = $page;
        $page = new pages($numArr['num'], $pagesize, url('', '', ''), $where);
        unset($where['page']);
        $show = $page->show();
        $show = str_replace('onclick="skip_page()"', '', $show);
        $limit = $page->offer . ',' . $pagesize;

        $field = 'id,issue,open_result,spare_1,spare_2,open_time,insert_time,state,user_id,is_call_back,call_back_uid';
        $order = "issue DESC";
        $list = D('openaward')->getlist($field, $where, $order, $limit);
        $openData = [];
         foreach ($list as $k => $v) {
            $v['open_time'] = date('Y-m-d H:i:s',$v['open_time']);
            if(!$v['insert_time']){
                $v['insert_time'] =  $v['open_time'];
            }else {
                $v['insert_time'] = date('Y-m-d H:i:s',$v['insert_time']);
            }
            if (!in_array($v['state'], array('0', '1'))) {
                //锁定
                $res = D('user')->getMusicTips($lottery_type . '_' . $v['issue'], '3');
                if (!empty($res)) {
                    $v['user_id'] = $res['click_uid'];
                    $list[$k]['user_id'] = $v['user_id'];
                }
            }
                    
            $open_result = $v['open_result']?"=".$v['open_result']:'';
            $openData[] = [
                'id' => $v['id'],
                'issue' => $v['issue'],
                'open_num' => $v['spare_1'].$open_result,
                'open_result' => $v['spare_2'],
                'open_time' => $v['open_time'],
                'insert_time' => $v['insert_time'],
                'state' => $v['state'],
                'user_id' => $v['user_id'],
            ];
        }
        $data = [
            'page' => $show,
            'list' => $openData,
        ];
        $this->returnCurl($data);
    }
    
    
    /*
     * 回滚
     *
     */
    public function rollback() {
        $lottery_type = $this->getParame('lottery_id',1,'','int','彩种ID为空或格式错误');
        $issue = $this->getParame('issue',1,'','checkLongNum','期号为空或格式错误');
        $uid = $this->userId;
        $url  =  C('home_url')."/index.php?m=api&c=order&a=ordersCallBack";
        $is_supper = 1;
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
    
        $ret = signa($url,$sdata);
        $resArr = json_decode($ret, true);
        $json_error = json_last_error();

        log_to_mysql($resArr, 'rollback_$resArr');
   
        if($json_error) {           //数据格式错误
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'Return data format error!';
        }else {
            if(isset($resArr['err']) && $resArr['err'] == 0) {
                $this->retArr['msg']  = 'success';
            } else {
                $this->retArr['code'] = 1;
                $errMsg = isset($resArr['msg'])?$resArr['msg']:'';
                !$errMsg && $errMsg = isset($resArr['ret_msg'])?$resArr['ret_msg']:'';
                !$errMsg && $errMsg = 'fail';
                $this->retArr['msg']  = $errMsg;
            }
        }
        $this->returnCurl();
    }



    /*
     * 手动开奖处理 
     *      
     */
    public function handOpenAward() {
        $lottery_type = $this->getParame('lottery_type',1,'','int','开奖彩种为空或格式错误');
        $issue = $this->getParame('issue',1,'','checkLongNum','开奖期号为空或格式错误');
        
        $getLotteryTypeSql = "SELECT id,`name` FROM un_lottery_type";
        $lottery_type_arr = $this->db->getall($getLotteryTypeSql);
        $lottery_type_arr = array_column($lottery_type_arr, 'name', 'id');
        
         //更改提示音状态
        $sql = "UPDATE `un_music_tips` SET STATUS=1 WHERE record_id='{$lottery_type}_{$issue}';";
        $this->db->query($sql);
        
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
    }
    
    
    
    /**
     * 开奖处理         幸运28    加拿大28
     */
    private function dealOpendaWard_1_3($issue, $lottery_type) {
        //接收参数
        $numberA = $this->getParame('numberA', 1, '', 'int', '开奖号码A为纯数字');
        $numberB = $this->getParame('numberB', 1, '', 'int', '开奖号码B为纯数字');
        $numberC = $this->getParame('numberC', 1, '', 'int', '开奖号码C为纯数字');
        
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
        $ltj = $this->db->getone($sql);

        $tj=calculate_tj((int)$numResult,$ltj['spare_3']);

        $data = array(
            'open_result' => $numResult,
            'spare_1' => $spare_1,
            'spare_2' => $zwWay,
            'spare_3' => $tj,
            'state' => 1,
            'user_id' => $this->userId,
        );

        $datetime = getParame('open_time', 1, '', 'str', ['开奖时间不能为空', '开奖时间格式错误']);     //开奖时间
        $reArr = $this->db->getone("SELECT id FROM `un_open_award` WHERE (`issue`='{$issue}' AND lottery_type= {$lottery_type})");
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

        if (!$res) { //开奖失败
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'fail';
        }else{
            $redis = initCacheRedis();
            //shell派奖
            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->userId)));  //存开奖数据
            deinitCacheRedis($redis);
            // //此处进入开奖派彩的逻辑
            // //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数
            //D('workerman')->theLottery($data['qihao'],[$data['3x9'],$data['28'],$data['kjjg'],$data['tj']],$data['time'],$lt,0,0,array('frequency'=>1));
//                D('workerman')->theLottery($issue, [$spare_1, $numResult, $zwWay, $tj], strtotime($datetime), $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));
            $this->retArr['msg']  = 'success';
        }
        $this->returnCurl();
    } //添加到一行结束
    
    
    private function dealOpendaWard_5_6_11($issue, $lottery_type) {
        $arr = array('A', 'B', 'C', 'D', 'E');
        $data = array();
        foreach ($arr as $v) {
            $postNumber = $this->getParame('number'.$v, 1, '', 'int', '开奖号码'.$v.'为纯数字');

            if (!preg_match('/^\d{1,2}$/', $postNumber)) {
                $this->retArr['code'] = 1;
                $this->retArr['msg'] = 'Winning number ' . $v . ', please enter a number from 0 to 9';
                $this->returnCurl();
            }
            $data[] = $postNumber;
        }
        $open_time = $this->getParame('open_time', 1, '', 'str', '开奖时间为空或格式错误');     //开奖时间

        //将得到的数据更新到数据库中
        $final['issue'] = $issue;
        $final['lottery_type'] = $lottery_type;
        $final['lottery_result'] = implode($data, ',');
        $final['lottery_time'] = strtotime($open_time);
        $final['insert_time'] = time();
        $final['status'] = 1;       //1为手动开奖
        $final['user_id'] = $this->userId;
        $sql = "SELECT is_call_back,call_back_uid FROM un_ssc WHERE issue = '{$issue}' AND lottery_type = {$lottery_type}";
        $res = $this->db->getone($sql);
        $final['is_call_back'] = $res['is_call_back'];
        $final['call_back_uid'] = $res['call_back_uid'];
        $update_res = O('model')->db->replace('un_ssc', $final);
        lg('hand_open_log',var_export(array('SQL'=>$this->db->_sql(),'$final'=>$final,'$update_res'=>$update_res,'$data'=>$data),1));
        // dump($this->db->_sql());
        if ($update_res) {
            $redis = initCacheRedis();
            //shell派奖
            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->userId)));  //存开奖数据
            deinitCacheRedis($redis);
            $this->retArr['msg']  = 'success';

            //此处进入开奖派彩的逻辑
            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数
//                D('workerman')->theLottery($final['issue'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));
        } else {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'fail';
        }
        $this->returnCurl();
    }
    
    private function dealOpendaWard_13($issue, $lottery_type) {
        $arr = array('A', 'B', 'C');
        $data = array();
        foreach ($arr as $v) {
            $postNumber = $this->getParame('number'.$v, 1, '', 'int', '开奖号码'.$v.'为纯数字');

            if (!preg_match('/^\d{1,2}$/', $postNumber)) {
                $this->retArr['code'] = 1;
                $this->retArr['msg'] = 'Winning number' . $v . ', please enter the number from 1 to 6';
                $this->returnCurl();
            }
            $data[] = $postNumber;
        }
        $open_time = $this->getParame('open_time', 1, '', 'str', '开奖时间为空或格式错误');     //开奖时间

        //将得到的数据更新到数据库中
        $final['issue'] = $issue;
        $final['lottery_type'] = $lottery_type;
        $final['lottery_result'] = implode($data, ',');
        $final['lottery_time'] = strtotime($open_time);
        $final['insert_time'] = time();
        $final['status'] = 1;       //1为手动开奖
        $final['user_id'] = $this->userId;
        $sql = "SELECT is_call_back,call_back_uid FROM un_sb WHERE issue = '{$issue}' AND lottery_type = {$lottery_type}";
        $res = $this->db->getone($sql);
        $final['is_call_back'] = $res['is_call_back'];
        $final['call_back_uid'] = $res['call_back_uid'];
        $update_res = O('model')->db->replace('un_sb', $final);
        // dump($this->db->_sql());
        if ($update_res) {
            $redis = initCacheRedis();
            //shell派奖
            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->userId)));  //存开奖数据
            deinitCacheRedis($redis);
            $this->retArr['msg']  = 'success';
            //此处进入开奖派彩的逻辑
            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数
//                D('workerman')->theLottery($final['issue'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));
        } else {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'fail';
        }

        $this->returnCurl();
    }
    
    private function dealOpendaWard_2($issue, $lottery_type){
        $open_time = $this->getParame('open_time', 1, '', 'str', '开奖时间为空或格式错误');     //开奖时间
        $arr = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J");
        $data = array();
        foreach ($arr as $v) {
            $postNumber = $this->getParame('number'.$v, 1, '', 'int', '开奖号码'.$v.'为纯数字');

            if (!preg_match('/^\d{1,2}$/', $postNumber)) {
                $this->retArr['code'] = 1;
                $this->retArr['msg'] = 'Winning number ' . $v . ', please enter the number from 0 to 9';
                $this->returnCurl();
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
        $final['user_id'] = $this->userId;
        $sql = "SELECT is_call_back ,call_back_uid FROM un_bjpk10 WHERE qihao='{$issue}' and lottery_type={$lottery_type}";
        $res = $this->db->getone($sql);
        $final['is_call_back'] = $res['is_call_back'];
        $final['call_back_uid'] = $res['call_back_uid'];

        //REPLACE INTO `un_bjpk10` ( `qihao`,`kaijianghaoma`,`kaijiangshijian`,`insert_time`,`status`,`user_id` ) VALUES ('1111','1,2,3,4,5,6,7,8,9,10','2017-07-03 18:07:12','2017-07-03 18:08:01','1','44')
        $update_res = O('model')->db->replace('un_bjpk10', $final);
        if ($update_res) {
            $redis = initCacheRedis();
            //shell派奖
            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->userId)));  //存开奖数据
            deinitCacheRedis($redis);
            $this->retArr['msg']  = 'success';
            //此处进入开奖派彩的逻辑
            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数
//                    D('workerman')->theLottery($final['qihao'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));
        } else {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'fail';
        }
        $this->returnCurl();
    }
    
    private function dealOpendaWard_4($issue, $lottery_type){
        $open_time = $this->getParame('open_time', 1, '', 'str', '开奖时间为空或格式错误');     //开奖时间
            
        $arr = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J");
        $data = array();
        foreach ($arr as $v) {
            $postNumber = $this->getParame('number'.$v, 1, '', 'int', '开奖号码'.$v.'为纯数字');

            if (!preg_match('/^\d{1,2}$/', $postNumber)) {
                $this->retArr['code'] = 1;
		$this->retArr['msg'] = 'Winning number ' . $v . ', please enter the number from 0 to 9';
		$this->returnCurl();
            }
            $data[] = $postNumber;
        }


        //将得到的数据更新到数据库中
        $final['qihao'] = $issue;
        $final['kaijianghaoma'] = implode($data, ',');
        $final['kaijiangshijian'] = $open_time;
        $final['insert_time'] = date('Y-m-d H:i:s', time());
        $final['status'] = 1;
        $final['user_id'] = $this->userId;
        $sql = "SELECT is_call_back ,call_back_uid FROM un_xyft WHERE qihao='{$issue}'";
        $res = $this->db->getone($sql);
        $final['is_call_back'] = $res['is_call_back'];
        $final['call_back_uid'] = $res['call_back_uid'];
        //REPLACE INTO `un_bjpk10` ( `qihao`,`kaijianghaoma`,`kaijiangshijian`,`insert_time`,`status`,`user_id` ) VALUES ('1111','1,2,3,4,5,6,7,8,9,10','2017-07-03 18:07:12','2017-07-03 18:08:01','1','44')
        $update_res = O('model')->db->replace('un_xyft', $final);
        if ($update_res) {
            $redis = initCacheRedis();
            //shell派奖
            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->userId)));  //存开奖数据
            deinitCacheRedis($redis);
            $this->retArr['msg']  = 'success';
            //此处进入开奖派彩的逻辑
            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数
//                    D('workerman')->theLottery($final['qihao'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));
        } else {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'fail';
        }
        $this->returnCurl();
    }
    
    private function dealOpendaWard_9($issue, $lottery_type) {
        $open_time = $this->getParame('open_time', 1, '', 'str', '开奖时间为空或格式错误');     //开奖时间
        $arr = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J");
        $data = array();
        foreach ($arr as $v) {
            $postNumber = $this->getParame('number'.$v, 1, '', 'int', '开奖号码'.$v.'为纯数字');

            if (!preg_match('/^\d{1,2}$/', $postNumber)) {
                $this->retArr['code'] = 1;
		$this->retArr['msg'] = 'Winning number ' . $v . ', please enter the number from 0 to 9';
		$this->returnCurl();
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
        $final['user_id'] = $this->userId;
        $sql = "SELECT is_call_back ,call_back_uid FROM un_bjpk10 WHERE qihao='{$issue}' and lottery_type={$lottery_type}";
        $res = $this->db->getone($sql);
        $final['is_call_back'] = $res['is_call_back'];
        $final['call_back_uid'] = $res['call_back_uid'];

        //REPLACE INTO `un_bjpk10` ( `qihao`,`kaijianghaoma`,`kaijiangshijian`,`insert_time`,`status`,`user_id` ) VALUES ('1111','1,2,3,4,5,6,7,8,9,10','2017-07-03 18:07:12','2017-07-03 18:08:01','1','44')
        $update_res = O('model')->db->replace('un_bjpk10', $final);
        if ($update_res) {
            $redis = initCacheRedis();
            //shell派奖
            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->userId)));  //存开奖数据
            deinitCacheRedis($redis);
            $this->retArr['msg']  = 'success';
            //此处进入开奖派彩的逻辑
            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数
//                    D('workerman')->theLottery($final['qihao'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));
        } else {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'fail';
        }
        $this->returnCurl();
    }
    
    private function dealOpendaWard_10($issue, $lottery_type) {
        $open_time = $this->getParame('open_time', 1, '', 'str', '开奖时间为空或格式错误');     //开奖时间

        $arr = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',];
        $data = [];
        foreach ($arr as $v) {
            $postNumber = $this->getParame('number'.$v, 1, '', 'bairennn_input_filter', '开奖号码'.$v.'不是合法的扑克牌面');
            $data[] = poker2num($postNumber);
        }

        //将得到的数据更新到数据库中
        $final['lottery_type'] = $lottery_type;
        $final['issue'] = $issue;
        $final['lottery_result'] = implode($data, ',');
        $final['lottery_time'] = strtotime($open_time);
        $final['insert_time'] = time();
        $final['status'] = 1;
        $final['user_id'] = $this->userId;
        $sql = "SELECT is_call_back ,call_back_uid FROM un_nn WHERE issue='{$issue}' and lottery_type={$lottery_type}";
        $res = $this->db->getone($sql);
        $final['is_call_back'] = $res['is_call_back'];
        $final['call_back_uid'] = $res['call_back_uid'];

        $update_res = O('model')->db->replace('un_nn', $final);
        if ($update_res) {
            $redis = initCacheRedis();
            //shell派奖
            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->userId)));  //存开奖数据
            deinitCacheRedis($redis);
            $this->retArr['msg']  = 'success';
            //此处进入开奖派彩的逻辑
            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数
//                    D('workerman')->theLottery($issue, $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));
        } else {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'fail';
        }
        $this->returnCurl();
    }
    
    //分分PK10
    private function dealOpendaWard_14($issue, $lottery_type) {
        $arr = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J");
        $data = array();
        foreach ($arr as $v) {
            $postNumber = $this->getParame('number'.$v, 1, '', 'int', '开奖号码'.$v.'为纯数字');

            if (!preg_match('/^\d{1,2}$/', $postNumber)) {
                $this->retArr['code'] = 1;
		$this->retArr['msg'] = 'Winning number ' . $v . ', please enter the number from 1 to 10';
		$this->returnCurl();
            }
            $data[] = $postNumber;
        }

        $open_time = $this->getParame('open_time', 1, '', 'str', '开奖时间为空或格式错误');     //开奖时间

        //将得到的数据更新到数据库中
        $final['issue'] = $issue;
        $final['lottery_type'] = $lottery_type;
        $final['lottery_result'] = implode($data, ',');
        $final['lottery_time'] = strtotime($open_time);
        $final['insert_time'] = time();
        $final['status'] = 1;       //1为手动开奖
        $final['user_id'] = $this->userId;
        $sql = "SELECT is_call_back,call_back_uid FROM un_ffpk10 WHERE issue = '{$issue}' AND lottery_type = {$lottery_type}";
        $res = $this->db->getone($sql);
        $final['is_call_back'] = $res['is_call_back'];
        $final['call_back_uid'] = $res['call_back_uid'];
        $update_res = O('model')->db->replace('un_ffpk10', $final);
        lg('hand_open_log',var_export(array('SQL'=>$this->db->_sql(),'$final'=>$final,'$update_res'=>$update_res,'$data'=>$data),1));
        // dump($this->db->_sql());
        if ($update_res) {
            $redis = initCacheRedis();
            //shell派奖
            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->userId)));  //存开奖数据
            deinitCacheRedis($redis);
            $this->retArr['msg']  = 'success';
            //此处进入开奖派彩的逻辑
            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数
//                D('workerman')->theLottery($final['issue'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));
        } else {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'fail';
        }
        $this->returnCurl();
    }
    
    //六合彩           急速六合彩
    private function dealOpendaWard_7_8($issue, $lottery_type) {
        $open_time = $this->getParame('open_time', 1, '', 'str', '开奖时间为空或格式错误');     //开奖时间
        
        $arr = array('A', 'B', 'C', 'D', 'E','F','G');
        $data = array();
        foreach ($arr as $v) {
            $postNumber = $this->getParame('number'.$v, 1, '', 'int', '开奖号码'.$v.'为纯数字');

            if (!preg_match('/^\d{1,2}$/', $postNumber)) {
                $this->retArr['code'] = 1;
		$this->retArr['msg'] = 'Winning number ' . $v . ', please enter the number from 1 to 49';
		$this->returnCurl();
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
        $final['user_id'] = $this->userId;
        $sql = "SELECT is_call_back,call_back_uid FROM un_lhc WHERE issue = '{$issue}' AND lottery_type = {$lottery_type}";
        $res = $this->db->getone($sql);
        $final['is_call_back'] = $res['is_call_back'];
        $final['call_back_uid'] = $res['call_back_uid'];
        $update_res = O('model')->db->replace('un_lhc', $final);
        lg('hand_open_log',var_export(array('SQL'=>$this->db->_sql(),'$final'=>$final,'$update_res'=>$update_res,'$data'=>$data),1));
        // dump($this->db->_sql());
        if ($update_res) {
            $redis = initCacheRedis();
            //shell派奖
            $redis->hsetnx('pc_lottery_type:'.$lottery_type,$issue,encode(array('status'=>1,'uid'=>$this->userId)));  //存开奖数据
            deinitCacheRedis($redis);
            $this->retArr['msg']  = 'success';
            //此处进入开奖派彩的逻辑
            //int 期号，array 号码，int 时间，int 彩种，int 状态开奖状态 0自动, 1手动, 2未开，int 开奖人 0表示自动，array 其它 frequency 开奖次数
//                D('workerman')->theLottery($final['issue'], $final, $open_time, $lottery_type, 1, $this->admin['userid'], array('frequency' => 1));
        } else {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'fail';
        }
        $this->returnCurl();
    }
}