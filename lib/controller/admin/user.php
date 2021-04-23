<?php
/**
 * @copyright
 */
!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');

include S_CORE . 'class' . DS . 'page.php';

class UserAction extends Action {

    //导出用户信息时要验证密码
    public function export_pw(){
        if($_REQUEST['do']==1){
            $data = $_REQUEST;
            $adminInfo = $this->admin;
            unset($adminInfo['password']);
            lg("change_export_pw",var_export(array(
                '$data[\'paypassword\']'=>$data['paypassword'],
                '$data[\'paypasswords\']'=>$data['paypasswords'],
                'amdin_info'=>$adminInfo,
            ),1));
            unset($data['m'],$data['c'],$data['a']);
            if($data['paypassword'] != $data['paypasswords']){
                $json = encode(array(
                    'rt'=>0,
                    'msg'=>'密码不一致，请重试',
                ));
            }else{
                $pw = md5($data['paypassword']);
                $sql = "UPDATE un_config SET `value`='{$pw}' WHERE nid='userinfo_export_passwd'";
                $rt = $this->db->query($sql);
                $json = encode(array(
                    'rt'=>$rt,
                    'msg'=>'修改成功',
                ));
            }
            echo $json;
        }else{
            include template('export_pw');
        }
    }

    public function do_export_userinfo(){
        //验证权限 只有超级管理员才有权限
        $admin = $this->admin;
        $roleid = $admin['roleid'];
        $sql = "SELECT power_config FROM un_admin_role WHERE roleid={$roleid}";
        $re = $this->db->result($sql);
        $arr = decode($re);

        $msql = "SELECT id FROM un_menu WHERE `name`='导出密码' AND a='export_pw' AND m='admin' AND c='user'";
        $mid = $this->db->result($msql);

        $date = $_REQUEST['date'];
        lg('export_userinfo',var_export(array(
            '$roleid'=>$roleid,
            '$sql'=>$sql,
            '$re'=>$re,
            '$msql'=>$msql,
            '$mid'=>$mid,
            '$date'=>$date,
        ),1));
        if(!in_array($mid,$arr) && $admin['userid']!=1) {
            echo encode(array(
                'code' => 1,
                'msg' => '你没有权限',
            ));
            return false;
        }

        //导出数据
        $btime = strtotime($date);
        $etime = strtotime($date.' +1 day');
        $sql = "SELECT u.id, u.username,ug.`name`,u.`realname`,u.`qq`,u.`weixin`,u.`mobile`,u.`email`,
FROM_UNIXTIME(u.`regtime`, '%Y-%m-%d %H:%i:%S') as regtime,
u.`source`,u.`regip`, 
FROM_UNIXTIME(u.`lastlogintime`, '%Y-%m-%d %H:%i:%S') as lastlogintime,
(SELECT `username` FROM un_user WHERE id=u.`parent_id`) AS pid,
(SELECT SUM(money) FROM `un_account_log` WHERE `user_id` = u.id AND `type` = 10) AS totalZC,
((SELECT SUM(money) FROM `un_account_log` WHERE `user_id` = u.id AND `type` IN (13,120)) - (SELECT SUM(money) FROM `un_account_log` WHERE `user_id` = u.id AND `type` IN (12,14))) AS totalYL
FROM un_user AS u,`un_user_group` AS ug 
WHERE 
u.`group_id`=ug.`id` AND 
u.`regtime` >= {$btime} AND 
u.`regtime`<= {$etime} AND
u.`reg_type` NOT IN (0,8,9,11)";

        $res  = $this->db->getall($sql);
        lg('export_userinfo',var_export(array(
            '$sql'=>$sql,
            '$res'=>$res,
        ),1));
        $time = date("Y-m-d",$btime);
        $data = $res;
        $title = array(
            "id",
            "会员账号",
            "会员组",
            "真实姓名",
            "QQ",
            "微信",
            "手机号",
            "邮箱",
            "注册时间",
            "用户来源",
            "注册IP",
            "最后登录时间",
            "直属上级",
            "历史总充值",
            "历史总盈亏",
        );

        $filename = $time."注册会员列表";
//        exportexcel($res,$title,$filename);
        include S_CORE . 'class' . DS . 'PHPExcel.php';
        include S_CORE . 'class' . DS . 'PHPExcel' . DS . 'Writer' . DS . 'Excel2007.php';

        $c = [];

        $key = ord("A");
        $key2 = ord("@");
        foreach($title as $v) {
            if($key>ord("Z")){
                $key2 += 1;
                $key = ord("A");
                $c[] = chr($key2).chr($key);//超过26个字母时才会启用
            }else{
                if($key2>=ord("A")){
                    $c[] = chr($key2).chr($key);//超过26个字母时才会启用
                }else{
                    $c[] = chr($key);
                }
            }
            $key += 1;
        }

        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);

        if (!empty($title)) {
            foreach ($title as $k => $v) {
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($c[$k] . '1', $v, PHPExcel_Cell_DataType::TYPE_STRING);
            }
        }

        foreach ($res as $key => $val) {
            $i = 0;
            foreach ($val as $ck => $cv) {
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($c[$i] . ($key + 2), $cv, PHPExcel_Cell_DataType::TYPE_STRING);
                $i++;
            }
        }

        $objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename="' . $filename . '.xlsx"');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
        return false;
    }

    /**
     * 导出昨天的注册用户数据
     * @method GET
     * @param token string
     * @return  mixed
     */
    public function export_userinfo(){
        $pws = $_REQUEST['pws'];

        //验证权限
        $admin = $this->admin;
        if($admin['userid'] != 1){
            echo encode(array(
                'code'=>1,
                'msg'=>'你没有权限',
            ));
            return false;
        }
        $roleid = $admin['roleid'];
        $sql = "SELECT power_config FROM un_admin_role WHERE roleid={$roleid}";
        $re = $this->db->result($sql);
        $arr = decode($re);
        if($admin['userid']==1){
            $arr = array('1');
        }

        $msql = "SELECT id FROM un_menu WHERE `name`='导出密码' AND a='export_pw' AND m='admin' AND c='user'";
        $mid = $this->db->result($msql);

        lg('export_userinfo',var_export(array(
            '$roleid'=>$roleid,
            '$sql'=>$sql,
            '$re'=>$re,
            '$msql'=>$msql,
            '$mid'=>$mid,
        ),1));
        if(!in_array($mid,$arr) && $admin['userid']!=1){
            echo encode(array(
                'code'=>1,
                'msg'=>'你没有权限',
            ));
//            echo '你没有权限！';
            return false;
        }else{
            //密码验证
            $sql = "SELECT `value` FROM `un_config` WHERE `nid` = 'userinfo_export_passwd'";
            $pw = $this->db->result($sql);
            if($pw != md5($pws)){
                echo encode(array(
                    'code'=>1,
                    'msg'=>'密码错误',
                ));
                return false;
            }else{
                echo encode(array(
                    'code'=>0,
                    'msg'=>'',
                ));
            }
        }
        return false;
    }

    //团队集合ID  包含自身
    function teamLists($userId)
    {
        $sql = "SELECT user_id FROM `un_user_tree` WHERE `pids` LIKE '%,{$userId},%' ";
        $res = $this->db->getall($sql);
        $self = array('user_id' => $userId);
        if (empty($res)) {
            return array($self);
        } else {
            array_push($res, $self);
            return $res;
        }
    }

    //直属id集合
    function sonsList($userId)
    {
        $sql = "SELECT id as user_id FROM `un_user` WHERE `parent_id` = {$userId}";
        $res = $this->db->getall($sql);
        return $res;
    }

    //用户列表
    public function lst() {
        //验证权限
        $admin = $this->admin;
        $roleid = $admin['roleid'];
        $sql = "SELECT power_config FROM un_admin_role WHERE roleid={$roleid}";
        $re = $this->db->result($sql);
        $arr = decode($re);
        if($admin['userid']==1){
            $arr = array('1');
        }

        $msql = "SELECT id FROM un_menu WHERE `name`='导出密码' AND a='export_pw' AND m='admin' AND c='user'";
        $mid = $this->db->result($msql);
        $ex_role=0;
        if(in_array($mid,$arr) || $admin['userid']==1) {
            $ex_role = 1;
        }

        lg('export_userinfo_list',var_export(array(
            '$roleid'=>$roleid,
            '$sql'=>$sql,
            '$re'=>$re,
            '$msql'=>$msql,
            '$mid'=>$mid,
            '$ex_role'=>$ex_role,
        ),1));

        $data = $_REQUEST;
        unset($data['m']);
        unset($data['c']);
        unset($data['a']);
        $show_user_info = $this->admin['show_user_info'];  //管理员有没有权限查看用户敏感信息 1，有;0，没有
        //获取会员数据
        $user = D('admin/user');
        $common = D("common");
        $pagesize = 20;
        $data['page'] = empty($data['page']) ? 1 : $data['page'];
        $offer = $pagesize * ($data['page'] - 1);
        $count_data = count($data);
        if($count_data==1) $userinfo = [];
        else $userinfo = $user->sousuo($data, $offer, $pagesize);

        $group = $common->getGroup();
        $layer = $common->getUserLayer();

        $countNum = 0;
        foreach ($userinfo as $key => $value) {
            if($value['count'] == 0 && $data['sort']==4){
                unset($userinfo[$key]);
                continue;
            }
            $countNum += $value['money'];
            $userinfo[$key]['regtime'] = date('Y-m-d H:i:s', $value['regtime']);
            $userinfo[$key]['logintime'] = date('Y-m-d H:i:s', $value['logintime']);

            /*
            //添加最后登录设备字段
            $tmp_fetch_device_sql = "SELECT flag FROM un_user_login_log 
                WHERE user_id = {$value['id']}
                ORDER BY addtime DESC LIMIT 1";
            $device_flag = $this->db->result($tmp_fetch_device_sql);
            $userinfo[$key]['device_flag'] = $device_flag;
            */
            //查直属上级
            $sql = "SELECT username FROM `un_user` WHERE id={$value['parent_id']}";
            $userinfo[$key]['parent_name'] = $this->db->result($sql);

            //查直属和团队人数
//            $re = $this->teamLists($value['id']);
//            $reamNum = array_column($re,'user_id');
////            dump(array('$re'=>$re,'$reamNum'=>$reamNum));
//            $len = count($reamNum);
//            if($len<2){
//                $len=0;
//            }
//            $userinfo[$key]['teamNum'] = $len;
//
//            $re = $this->sonsList($value['id']);
//            $sonNum = array_column($re,'user_id');
//            $len = count($sonNum);
//            $userinfo[$key]['sonNum'] = $len;

            switch ($value['state']) {
                case 0:
                    $userinfo[$key]['state_str'] = "正常";
                    break;
                case 1:
                    $userinfo[$key]['state_str'] = "监控";
                    break;
                case 2:
                    $userinfo[$key]['state_str'] = "禁用";
                    break;
            }
            if ($value['parent_id'] == 0) {
                $userinfo[$key]['parent'] = '-';
            } else {
                $parent = $user->userDB($value['parent_id']);
                $userinfo[$key]['parent'] = empty($parent) ? "上级ID不存在:" . $value['parent_id'] : $parent['username'];
            }
        }
        $post_run_data = [
            'report_type'=> 2,
            'platform_id'=> C('platform_id'),
            'modules_id' => '1010',
            'domain_name'=> C('app_home'),
            'url'=> $_SERVER["QUERY_STRING"],
            'php_run_time' => intval(str_replace("ms","",getRunTime(microtime(true),$this->tong_ji_start_time))),
            'app_type'=>4,
            'network'=>'line'
        ];
        $sql = "SELECT display FROM un_menu WHERE `name`='导出密码' AND a='export_pw'";
        $exportDisplay = $this->db->result($sql);
        include template('list');
    }

    public function getTeam(){
        $id = $_POST["id"];
        $re = $this->teamLists($id);
        $reamNum = array_column($re,'user_id');
        $len = count($reamNum);
        if($len<2){
            $len=0;
        }
        $data = [];
        $data["count"] = $len;
        echo json_encode($len);
    }

    public function getSon(){
        $id = $_POST["id"];
        $re = $this->sonsList($id);
        $sonNum = array_column($re,'user_id');
        $len = count($sonNum);
        $data = [];
        $data["count"] = $len;
        echo json_encode($len);
    }

    public function listPage()
    {
        $data = $_REQUEST;
        unset($data['m']);
        unset($data['c']);
        unset($data['a']);
        $user = D('admin/user');
        $common = D("common");
        $count = $user->getSearchCount($data);
    
        //$moneyTJ = $user->getUserInfoTJ($data);

        $pagesize = 20;
        $url = '?m=admin&c=user&a=lst';
        $page = new page($count, $pagesize, $url, $data);
        $show = $page->show();
        $group = $common->getGroup();
        $layer = $common->getUserLayer();
    
        echo json_encode([
            'code' => 0,
            'msg' => '获取数据成功',
            'data' => [
                'show' => $show,
                //'countMoney' => empty($moneyTJ[0]['countMoney']) ? 0 : $moneyTJ[0]['countMoney'],
                'pagecount' => empty($page->pagecount) ? 0 : $page->pagecount
            ]
        ]);
    }
    
    public function hplus() {
        include template('hplus');
    }
    
    
    
    //用户列表
    public function hplusList() {
        $data = $_REQUEST;
        $show_user_info = $this->admin['show_user_info'];  //管理员有没有权限查看用户敏感信息 1，有;0，没有
        //        if(empty($show_user_info)){
        //            $show_user_info = 0;
        //        }
        //        dump($show_user_info);
        //        die();
        //获取会员数据
        $user = D('admin/user');
        $count = $user->getSearchCount($data);
        $moneyTJ = $user->getUserInfoTJ($data);
        //echo $count;die;
        //每页显示15条记录
        $pagesize = 20;
        $url = '?m=admin&c=user&a=lst';
        $page = new page($count, $pagesize, $url, $data);
        $show = $page->show();
        $group = $user->get_group();
        $layer = $user->getUserLayer();
        $userinfo = $user->sousuo($data, $page->offer, $pagesize);
        $countNum = 0;
        foreach ($userinfo as $key => $value) {
            $countNum += $value['money'];
            $userinfo[$key]['regtime'] = date('Y-m-d H:i:s', $value['regtime']);
            $userinfo[$key]['logintime'] = date('Y-m-d H:i:s', $value['logintime']);
    
            //添加最后登录设备字段
            $tmp_fetch_device_sql = "SELECT flag FROM un_user_login_log
            WHERE user_id = {$value['id']}
            ORDER BY addtime DESC LIMIT 1";
            $device_flag = $this->db->result($tmp_fetch_device_sql);
            $userinfo[$key]['device_flag'] = $device_flag;
    
            switch ($value['state']) {
                case 0:
                    $userinfo[$key]['state'] = "正常";
                    break;
                case 1:
                    $userinfo[$key]['state'] = "监控";
                    break;
                case 2:
                    $userinfo[$key]['state'] = "禁用";
                    break;
            }
            if ($value['parent_id'] == 0) {
                $userinfo[$key]['parent'] = '-';
            } else {
                $parent = $user->userDB($value['parent_id']);
                $userinfo[$key]['parent'] = empty($parent) ? "上级ID不存在:" . $value['parent_id'] : $parent['username'];
            }
        }
    
        echo json_encode($userinfo);
    }
    
    public function bootstrap() {
        $data = $_REQUEST;
        $show_user_info = $this->admin['show_user_info'];  //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $user = D('admin/user');
        $count = $user->getSearchCount($data);
        $moneyTJ = $user->getUserInfoTJ($data);
        //echo $count;die;
        //每页显示15条记录
        $pagesize = 20;
        $url = '?m=admin&c=user&a=lst';
        $page = new page($count, $pagesize, $url, $data);
        $show = $page->show();
        $group = $user->get_group();
        $layer = $user->getUserLayer();
        $userinfo = $user->sousuo($data, $page->offer, $pagesize);
        $countNum = 0;
        foreach ($userinfo as $key => $value) {
            $countNum += $value['money'];
            $userinfo[$key]['regtime'] = date('Y-m-d H:i:s', $value['regtime']);
            $userinfo[$key]['logintime'] = date('Y-m-d H:i:s', $value['logintime']);
    
            //添加最后登录设备字段
            $tmp_fetch_device_sql = "SELECT flag FROM un_user_login_log
            WHERE user_id = {$value['id']}
            ORDER BY addtime DESC LIMIT 1";
            $device_flag = $this->db->result($tmp_fetch_device_sql);
            $userinfo[$key]['device_flag'] = $device_flag;
    
            switch ($value['state']) {
                case 0:
                    $userinfo[$key]['state'] = "正常";
                    break;
                case 1:
                    $userinfo[$key]['state'] = "监控";
                    break;
                case 2:
                    $userinfo[$key]['state'] = "禁用";
                    break;
            }
            if ($value['parent_id'] == 0) {
                $userinfo[$key]['parent'] = '-';
            } else {
                $parent = $user->userDB($value['parent_id']);
                $userinfo[$key]['parent'] = empty($parent) ? "上级ID不存在:" . $value['parent_id'] : $parent['username'];
            }
        }

        include template('bootstrap');
    }
    
    //直属会员列表
    public function leaguerList() {
        $data = $_REQUEST;
        $show_user_info = $this->admin['show_user_info'];  //管理员有没有权限查看用户敏感信息 1，有;0，没有

        //获取会员数据
        $user = D('admin/user');
        $count = $user->getSearchCount($data, 1);
        $moneyTJ = $user->getUserInfoTJ($data);
        //echo $count;die;
        //每页显示15条记录
        $pagesize = 20;
        $url = '?m=admin&c=user&a=leaguerList';
        $page = new page($count, $pagesize, $url, $data);
        $show = $page->show();
        $group = $user->get_group();
        $layer = $user->getUserLayer();
        $userinfo = $user->sousuo($data, $page->offer, $pagesize, 1);
        $countNum = 0;
        foreach ($userinfo as $key => $value) {
            $countNum += $value['money'];
            $userinfo[$key]['regtime'] = date('Y-m-d H:i:s', $value['regtime']);
            $userinfo[$key]['logintime'] = date('Y-m-d H:i:s', $value['logintime']);
            switch ($value['state']) {
                case 0:
                    $userinfo[$key]['state_str'] = "正常";
                    break;
                case 1:
                    $userinfo[$key]['state_str'] = "监控";
                    break;
                case 2:
                    $userinfo[$key]['state_str'] = "禁用";
                    break;
            }
            if ($value['parent_id'] == 0) {
                $userinfo[$key]['parent'] = '-';
            } else {
                $parent = $user->userDB($value['parent_id']);
                $userinfo[$key]['parent'] = empty($parent) ? "上级ID不存在:" . $value['parent_id'] : $parent['username'];
                $userinfo[$key]['parent_name'] = $parent['username'];
            }
        }

        include template('leager');
    }

    //用户信息详情
    public function detail() {
        $id = $_REQUEST['id'];
        $user = D('admin/user');
        $data = $user->getInfoOne($id);
        $real_man=1;
        if(in_array($data['reg_type'],array('',0,8,9,11))){
            $real_man=0;
        };
        $data['account_name']=decrypt($data['account_name']);
        $honor = get_level_honor($id);
        
        $show_user_info = $this->admin['show_user_info'];  //管理员有没有权限查看用户敏感信息 
        /*
        if ($show_user_info == 1) {
            $data['weixin'] = decrypt($data['weixin']);
            $data['email'] = decrypt($data['email']);
            $data['mobile'] = decrypt($data['mobile']);
        } else {
            $data['weixin'] = '';
            $data['email'] = '';
            $data['mobile'] = '';
            $data['realname'] = '';
        }
        */

        //添加最后登录设备字段
        $tmp_fetch_device_sql = "SELECT flag FROM un_user_login_log 
            WHERE user_id = {$id}
            ORDER BY addtime DESC LIMIT 1";
        $device_flag = $this->db->result($tmp_fetch_device_sql);
        if ($device_flag == '1') {
            $data['device_name'] = 'iOS';
        } elseif ($device_flag == '2') {
            $data['device_name'] = 'Android';
        } elseif ($device_flag == '3') {
            $data['device_name'] = 'H5';
        } elseif ($device_flag == '4') {
            $data['device_name'] = 'PC';
        } else {
            $data['device_name'] = '--';
        }
        
        include template('detailinfo');
    }

    public function update_remark(){
        $data = $_REQUEST;
        $adminInfo = $this->admin;
        $sql = "UPDATE un_user SET remark = '{$data['remark']}' WHERE id={$data['id']}";
        $res = $this->db->query($sql);

        if($res){
            $json = array(
                'status'=>1,
                'msg'=>'修改成功',
            );
        }else{
            $json = array(
                'status'=>0,
                'msg'=>'修改失败',
            );
        }
        lg('update_remark',var_export(array(
            '$adminInfo[\'userid\']'=>$adminInfo['userid'],
            '$data'=>$data,
            '$sql'=>$sql,
            '$res'=>$res,
            '$json'=>$json,
        ),1));
        echo encode($json);
    }

    //用户信息详情冻结账号
    public function update_detail() {
        $id = $_REQUEST['id'];
        $state = $_REQUEST['state'];
        $user = D('admin/user');
        $res = $user->update_detail($id, $state);
    }

    //用户信息的修改
    public function update_user() {
        $show_user_info = $this->admin['show_user_info'];
        $id = $_REQUEST['id'];
        $user = D('admin/user');
        $data = $user->getInfoOne($id);
        $data['weixin']=decrypt($data['weixin']);
        $data['email']=decrypt($data['email']);
        $data['mobile']=decrypt($data['mobile']);
        $data['qq']=decrypt($data['qq']);
        $group = $user->get_group();
        $layer =$user->getUserLayer();

        //查日志
        $sql = "SELECT change_name,ip,FROM_UNIXTIME(`addtime`) AS `time` FROM `un_user_change_log` WHERE user_id={$id} AND `type`=1 ORDER BY id DESC";
        $list = $this->db->getall($sql);

        include template('update_user');
    }

    //用户信息的修改
    public function update_user_ok() {
        $data = $_REQUEST;
        $arr = array(
            'id' => $data['id'],
            'group_id' => $data['group_id'],
            'layer_id' => $data['layer_id'],
            'username' => $data['username'],
            'mobile' => encrypt($data['mobile']),
            'email' => encrypt($data['email']),
            'qq' => encrypt($data['qq']),
            'realname' => $data['realname'],
            'weixin' => encrypt($data['weixin']),
            //'is_realname' => $data['is_realname']
        );
//        dump($arr);
        if(!isset($data['mobile'])) unset($arr['mobile']);
        if(!isset($data['email'])) unset($arr['email']);
        if(!isset($data['qq'])) unset($arr['qq']);
        if(!isset($data['weixin'])) unset($arr['weixin']);
        if(!isset($data['realname'])) unset($arr['realname']);

        lg('update_user_ok',var_export(array(
            '$data[\'mobile\']'=>$data['mobile'],
            '!isset($data[\'mobile\'])'=>!isset($data['mobile']),
        ),1));

        $user = D('admin/user');
        $res = $user->update_user($arr);

        //记录操作日志
        $opInfo = $this->admin;
        $sql  = "INSERT INTO `un_user_change_log` (`user_id`, `record_id`, `type`, `tab`, `change_name`, `ip`, `addtime`) VALUES ('{$data['id']}', '{$data['id']}', 1,'un_user', '{$opInfo['username']}', '{$opInfo['lastloginip']}', '".time()."')";
        $this->db->query($sql);

        echo json_encode(array("rt" => $res));
    }

    //修改密码跳转
    public function update_user_pass() {
        $id = $_REQUEST['id'];
        $user = D('admin/user');
        $data = $user->getInfoOne($id);

        //查日志
        $sql = "SELECT change_name,ip,FROM_UNIXTIME(`addtime`) AS `time` FROM `un_user_change_log` WHERE user_id={$id} AND `type`=2 ORDER BY id DESC";
        $list = $this->db->getall($sql);

        include template('update_user_pass');
    }

    //处理修改密码
    public function update_user_pass_ok() {
        $data = $_REQUEST;
        $arr = array(
            'id' => $data['id'],
            'password' => md5($data['password'])
        );

        $user = D('admin/user');
        $res = $user->update_user($arr);

        //记录操作日志
        $opInfo = $this->admin;
        $sql  = "INSERT INTO `un_user_change_log` (`user_id`, `record_id`, `type`, `tab`, `change_name`, `ip`, `addtime`) VALUES ('{$data['id']}', '{$data['id']}', 2,'un_user', '{$opInfo['username']}', '{$opInfo['lastloginip']}', '".time()."')";
        $this->db->query($sql);

        echo json_encode(array("rt" => $res));
    }

    //修改交易密码跳转
    public function update_user_repaypass() {
        $id = $_REQUEST['id'];
        $user = D('admin/user');
        $data = $user->getInfoOne($id);

        //查日志
        $sql = "SELECT change_name,ip,FROM_UNIXTIME(`addtime`) AS `time` FROM `un_user_change_log` WHERE user_id={$id} AND `type`=3 ORDER BY id DESC";
        $list = $this->db->getall($sql);

        include template('update_user_repaypass');
    }

    //处理修改交易密码
    public function update_user_repaypass_ok() {
        $data = $_REQUEST;
        $arr = array(
            'id' => $data['id'],
            'paypassword' => md5($data['paypassword'])
        );

        $user = D('admin/user');
        $res = $user->update_user($arr);

        $data['type']="clear_user_cash_passwd"; //清除玩家密码次数限制
        unset($data['paypassword']);
        $data['json']=encode(array());
        lg('clear_user_cash_passwd','发送前数据::'.encode($data));
        send_home_data($data);

        //记录操作日志
        $opInfo = $this->admin;
        $sql  = "INSERT INTO `un_user_change_log` (`user_id`, `record_id`, `type`, `tab`, `change_name`, `ip`, `addtime`) VALUES ('{$data['id']}', '{$data['id']}', 3,'un_user', '{$opInfo['username']}', '{$opInfo['lastloginip']}', '".time()."')";
        $this->db->query($sql);

        echo json_encode(array("rt" => $res));
    }

    //用户来源地址
    public function source(){
        $data = json_decode(D('config')->db->result("select value from un_config where nid='source'"),true);

        include template('source');
    }

    //添加用户来源
    public function source_add(){
        $source = json_decode(D('config')->db->result("select value from un_config where nid='source'"));
        $source[] = ['url'=>$_POST['source'],'time'=>time()];
        $data = json_encode($source,true);
        $res = D('config')->save(['value'=>$data],"nid='source'");
        $msg = '添加';
        if($res){
            exit(json_encode(['code'=>1,'msg'=>$msg.'成功~']));
        }else{
            exit(json_encode(['code'=>0,'msg'=>$msg.'失败~']));
        }
    }

    //删除用户来源
    public function source_del(){
        $k = $_POST['k'];
        $source = json_decode(D('config')->db->result("select value from un_config where nid='source'"));
        unset($source[$k]);
        $data = json_encode($source,true);
        $res = D('config')->save(['value'=>$data],"nid='source'");
        $msg = '删除';
        if($res){
            exit(json_encode(['code'=>1,'msg'=>$msg.'成功~']));
        }else{
            exit(json_encode(['code'=>0,'msg'=>$msg.'失败~']));
        }
    }

    //用户银行信息更改
    public function user_bank() {
        $user_id = $_REQUEST['id'];
        $user = D('admin/user');
        $bank = $user->get_banks();
        $data = $user->get_bank_one($user_id);
        $user = $user->userDB($user_id);

        $list = array();
        foreach ($data as $value) {
            //隐藏QQ钱包
            /*
            if(in_array($value['bank'],array(1,2, 124))){
                if ($value['bank'] == 124) {
                    $value['branch'] = 'QQ钱包';
                }else {
                    $value['branch'] = $value['bank']==2?"支付宝":"微信";
                }
            }
            */
            if(in_array($value['bank'],array(1,2))){
                $value['branch'] = $value['bank']==2?"支付宝":"微信";
            }
            if ($value['state'] == 2) {
                foreach ($bank as $b) {
                    if ($value['bank'] == $b['id']) {
                        $value['bank'] = $b['name'];
                        break;
                    }
                }
                $value['addtime'] = date('Y-m-d H:i:s', $value['addtime']);

                $value['last_mod_name']=$value['last_mod_name']?$value['last_mod_name']:'未知';

                $list['log'][] = $value;
            }
            if ($value['state'] == 1) {
                $list['new'] = $value;
            }
        }
        $list['user_id'] = $user_id;
        $list['mobile'] = $user['mobile'];

        include template('user_bank');
    }

    //用户银行信息更改--修改用户真实姓名
    public function user_bank_ok()
    {
        $data = $_REQUEST;

        $insert = array(
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'account' => $data['account'],
            'bank' => $data['bank'],
            'branch' => $data['branch'],
            'addtime' => time(),
            'addip' => ip(),
            'state' => 1,
            'last_mod_name' => '客服::'.$this->admin['username'],
        ); //插入新纪录--更新用户其它记录状态为无效

        $user = D('admin/user');
        $user->update_user(array("id" => $data['user_id'], "realname" => $data['name'], "mobile" => $data['mobile']));
        $res = $user->user_bank_up($insert);
        echo json_encode(array("rt" => $res));
    }

    //用户银行信息更改--修改用户真实姓名
    public function user_bank_reset()
    {
        $data = $_REQUEST;
        $userid = $data['user_id'];
        $this->db->delete('un_user_bank', array('user_id' => $userid));
        echo json_encode(array("rt" => true));
    }

    //用户信息详情冻结账号
    public function undongjie() {
        $id = $_REQUEST['id'];
        $state = $_REQUEST['state'];
        $user = D('admin/user');
        $res = $user->undongjie($id, $state);
        echo json_encode(array("rt" => $res));
    }

    //会员组管理
    public function man_group() {

        $group = D('admin/user');
        $groupList = $group->man_group();
        $reypment = $group->getPaymentType();
        include template('man_group');
    }

    //增加会员组
    public function add_group() {
        $data = $_POST;
        $powers = implode(',', $data['powers']);
        $online_type = implode(',', $data['online_type']);
        
        $data['powers'] = $powers;
        $data['online_type'] = $online_type;
        $data['addtime'] = time();
        
        $user = D('admin/user');
        
        $res = $user->add_group($data);
        
        echo json_encode(array("rt" => $res));
    }

    //删除会员组
    public function del_group() {
        $id = $_REQUEST['id'];
        $user = D('admin/user');
        $res = $user->del_group($id);
        echo json_encode(array("rt" => $res));
    }

    //修改会员管理组
    public function update_user_group()
    {
        $id = $_REQUEST['id'];
        $user = D('admin/user');
        $data = $user->getUserGroupPaymentType($id);
        $paymentType = $user->getPaymentType();
        $offlineId = explode(',', $data['powers']);
        if (empty($data['online_type'])) {
            $onlineId  = [];
        }else {
            $onlineId  = explode(',', $data['online_type']);
        }
        
        //var_dump($data);die;
        include template('update_group');
    }

    public function update_user_group_ok()
    {
        $data = $_REQUEST;
        
        $arr = array(
            'id' => $data['id'],
            'name' => $data['name'],
            'remark' => $data['remark'],
        );
        
        if (empty($data['powers'])) {
            $arr['powers'] = 0;
        }else {
            $arr['powers'] = implode(',', $data['powers']);
        }
        
        if (empty($data['online_type'])) {
            $arr['online_type'] = 0;
        }else {
            $arr['online_type'] = implode(',', $data['online_type']);
        }

        $user = D('admin/user');
        $res = $user->update_user_group_ok($arr);
        if ($res) {
            $code = array(
                'id' => $arr['id'],
                'state' => 1
            );

            echo json_encode($code);
        } else {

            $code = array(
                'id' => $arr['id'],
                'state' => 0
            );

            echo json_encode($code);
        }
    }

    //会员累计金额统计
    public function money_tj()
    {
        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];
        $user = D('admin/user');
        $url = '?m=admin&c=user&a=money_tj';
        if($_REQUEST['create_flag']==1){
            $data=$user->create_user_money_count();
            if($data) {
                alert("数据生成成功");
            }
        }
        $param = $_REQUEST;
        $where=$this->create_money_tj_seach_where($param);
        $groupRt = $user->get_group();
        $group = array();
        foreach ($groupRt as $value) {
            $group[$value['id']] = $value['name'];
        }
        $count = $user->user_amount_count($where);
        $pagesize = 10;
        $page = new page($count, $pagesize, $url, $param);
        $show = $page->show();
        $param['pagestart'] = $page->offer;
        $param['pagesize'] = $pagesize;
        $creatime=0;
        $data = $user->get_user_amount_count_list($where,$param);
        foreach ($data as $k=>$val){
            if($creatime==0) {
                $creatime = date("Y-m-d H:i:s",$val['create_time']);
            }
            $data[$k]['reg_money']=$val['recharge_amount'];
            $data[$k]['tz_money']=$val['betting_amount'];
            $data[$k]['zj_money']=$val['winning_amount'];
        }
        
        //获取数据生成时间
        if ($creatime == 0) {
            $creatime = date("Y-m-d H:i:s",$user->get_user_amount_count_list_time());
        }
        
        
        
        include template('money_tj');
    }

    //风险监控
    public function fx_watch() {
        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];
        $user = D('admin/user');
        $param = $_REQUEST;
        if(!isset($param['state'])){
            $param['state'] = 1;
        }
        $count = $user->fx_watch_cnt($param);
        $pagesize = 20;
        $url = '?m=admin&c=user&a=fx_watch';
        $page = new page($count, $pagesize, $url, $param);
        $show = $page->show();
        $param['pagestart'] = $page->offer;
        $param['pagesize'] = $pagesize;
        $data = $user->fx_watch($param);
        foreach ($data as $k=>$v) {
            //查日志
            $sql = "SELECT `type`,change_name,ip,FROM_UNIXTIME(`addtime`) AS `time` FROM `un_user_change_log` WHERE user_id={$v['id']} AND (`type`=4 OR `type`=5) ORDER BY id DESC";
            $re = $this->db->getone($sql);
            if(!empty($re)){
                if($re['type']==4){
                    $data[$k]['mark'] = "{$re['change_name']}把[{$v['username']}]冻结帐号, 时间:{$re['time']}, IP:{$re['ip']}";
                }else{
                    $data[$k]['mark'] = "{$re['change_name']}把[{$v['username']}]标记风险会员, 时间:{$re['time']}, IP:{$re['ip']}";
                }
            }
        }
        
        include template('fx_watch');
    }

    //风险监控删除
    public function del_watch() {
        $id = $_REQUEST['id'];
        $user = D('admin/user');
        $res = $user->del_watch($id);
        echo json_encode(array("rt" => $res));
    }

    //黑名单
    public function blacklist() {
        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];
        $user = D('admin/user');
        $param = $_REQUEST;
        $data = $user->blacklist($param);
        
        //管理员有没有权限查看用户敏感信息 1，有;0，没有
        $show_user_info = $this->admin['show_user_info'];
        
        include template('blacklist');
    }

    //代理等级管理
    public function agent_manage() {
        $lottery_type = !empty($_REQUEST['lottery_type'])?$_REQUEST['lottery_type']:1;

        $son_team = $_REQUEST['son_team'];
        if($son_team==1){
            $name = '直属';
        }else{
            $name = '团队';
        }

        $back_type = !empty($_REQUEST['back_type'])?$_REQUEST['back_type']:1;

//        $sql = "SELECT back_type FROM un_agent_group ORDER BY id DESC LIMIT 0,1";
//        $back_type = $this->db->result($sql);
        $redis = initCacheRedis();
        $ids  = $redis->lrange('LotteryTypeIds',0,-1);
        sort($ids);
        foreach ($ids as $k=>$v){
            if($v>12){
                $lotteryTypeIds[$k+1]  = $redis->hmget('LotteryType:'.$v,array('id','name'));
            }else{
                $lotteryTypeIds[$k]  = $redis->hmget('LotteryType:'.$v,array('id','name'));
            }
        }
        deinitCacheRedis($redis);
        if(empty($back_type)){
            $back_type=1;
            include template('agent_manage');
            return false;
        }
//        $back_type = !empty($_REQUEST['back_type'])?$_REQUEST['back_type']:1;

        $user = D('admin/user');
        $data = $user->agent_manage($lottery_type,$back_type,$son_team);
        $last = end($data);

        include template('agent_manage');
        return false;
    }


    //修改返水类型
    public function set_back_type() {
        $back_type = $_REQUEST['type'];
        $user = D('admin/user');
        $res = $user->set_back_type(array('back_type'=>$back_type));
        $this->refreshRedis('agent', 'all'); //刷新缓存
        echo json_encode(array('rt' => $res));
    }

    //代理等级管理-删除
    public function del_agent() {
        $id = $_REQUEST['id'];
        $user = D('admin/user');
        $res = $user->del_agent($id);

        if($res > 0) {
            $log_remark = $this->admin['username'] . "--" . date('Y-m-d H:i:s') . "--删除返水";
            admin_operation_log($this->admin['userid'], 40, $log_remark, $id);
        }
        echo json_encode(array('rt' => $res));
    }

    //代理等级管理-新增
    public function add_agent() {
        $data = array();
        $data['lottery_type'] = $_REQUEST['lottery_type'];
        $data['back_type'] = $_REQUEST['back_type'];
        $data['son_team'] = $_REQUEST['son_team'];
        $data['lower'] = $_REQUEST['lower'];
        $data['upper'] = $_REQUEST['upper'];
        $data['backwater'] = $_REQUEST['backwater'];
        $data['effective_person'] = $_REQUEST['effective_person'];
        $data['uid'] = $this->admin['userid'];
        $data['insert_time'] = time();
        $sql = "SELECT `lower`,`upper` FROM `un_agent_group` WHERE lottery_type={$data['lottery_type']} and back_type={$data['back_type']} and son_team={$data['son_team']} ORDER BY  id DESC LIMIT 0,1";
        $re = $this->db->getone($sql);
        if($data['lower'] < $re['upper']){
            $datas = array(
                'code'=>1,
                'msg'=>'添加失败，下限必须大于:'.$re['upper'],
            );
            echo encode($datas);
            return false;
        }

        if($data['upper'] < $re['upper']){
            $datas = array(
                'code'=>1,
                'msg'=>'添加失败，上限必须大于:'.$re['upper'],
            );
            echo encode($datas);
            return false;
        }

        $user = D('admin/user');
        $res = $user->add_agent($data);

        if($res > 0) {
            $getLotteryTypeSql = "SELECT id,`name` FROM un_lottery_type";
            $lottery_type_arr = $this->db->getall($getLotteryTypeSql);
            $lottery_type_arr = array_column($lottery_type_arr, 'name', 'id');

            $back_type_arr = [1 => '投注', 2 => '输分'];

            $back_type = $data['son_team'] == 1?'直属返水':'团队返水';
            $log_remark = "新增$back_type--彩种:".$lottery_type_arr[$data['lottery_type']];
            $log_remark .= '--返水类型:'.$back_type_arr[$data['back_type']];
            $log_remark .= '--投注下限:'.$data['lower'];
            $log_remark .= '--投注上限:'.$data['upper'];
            $log_remark .= '--回水比例:'.$data['backwater'];
            $log_remark .= '--有效人数:'.$data['effective_person'];
            admin_operation_log($this->admin['userid'], 40, $log_remark, $res);
        }

        $this->refreshRedis('agent', 'all'); //刷新缓存
        echo json_encode(array('rt' => $res,'code'=>0));
        return false;
    }

    //代理等级管理-修改
    public function update_agent() {

        $id = $_REQUEST['id'];
        $user = D('admin/user');
        $data = $user->get_agent_one($id);
        $back_type = $data['back_type'];
        $son_team = $data['son_team'];
        $redis = initCacheRedis();
        $ids  = $redis->lrange('LotteryTypeIds',0,-1);
        sort($ids);
        foreach ($ids as $v){
            $lotteryTypeIds[]  = $redis->hmget('LotteryType:'.$v,array('id','name'));
        }
        deinitCacheRedis($redis);
//        dump($data);
        include template('update_agent');
    }

    public function update_agent_ok() {
        $data = array();
        $data['lottery_type'] = $_REQUEST['lottery_type'];
        $data['back_type'] = $_REQUEST['back_type'];
        $data['lower'] = $_REQUEST['lower'];
        $data['upper'] = $_REQUEST['upper'];
        $data['backwater'] = $_REQUEST['backwater'];
        $data['effective_person'] = $_REQUEST['effective_person'];
        $user = D('admin/user');
        $res = $user->update_agent($data, array("id" => $_REQUEST['id']));
        $this->refreshRedis('agent', 'all'); //刷新缓存
        echo json_encode(array('rt' => $res));
    }

    //用户的的搜索
    public function user_search() {
        $data = $_REQUEST;
        $arr = array();
        $arr['username'] = $data['username'];
        $arr['loginip'] = $data['loginip'];
        $arr['state'] = $data['state'];
        $arr['group_id'] = $data['group_id'];
        $arr['regtime'] = $data['regtime'];
        $arr['lastlogintime'] = $data['lastlogintime'];

        $_SESSION['arr'] = $arr;
        //获取会员数据
        $user = D('admin/user');
        $count = $user->getSearchCount($data);
        //echo $count;die;
        //每页显示15条记录
        $pagesize = 20;
        $url = '?m=admin&c=user&a=searPage';
        $page = new page($count, $pagesize, $url);
        $show = $page->show();
        $group = $user->get_group();
        $userinfo = $user->sousuo($arr, $page->offer, $pagesize);
        include template('list');
    }

    public function searPage() {

        $arr = $_SESSION['arr'];
        $user = D('admin/user');
        $count = $user->getSearchCount($arr);
        $pagesize = 20;
        $url = '?m=admin&c=user&a=searPage';
        $group = $user->get_group();
        $page = new page($count, $pagesize, $url);
        $show = $page->show();
        $userinfo = $user->sousuo($arr, $page->offer, $pagesize);

        include template('list');
    }

    //用户所在组的修改
    public function set_user_group() {
        $user_id = $_REQUEST['user_id'];
        $user = D('admin/user');
        $row = $user->set_user_group($user_id);
        $group = $user->get_group();
        include template('set_user_group');
    }

    public function set_user_group_ok() {
        $data = $_REQUEST;
        $arr = array(
            'id' => $data['user_id'],
            'group_id' => $data['group_id'],
        );
        $user = D('admin/user');
        $res = $user->set_user_group_ok($arr);
        echo json_encode(array("rt" => $res));
    }

    //额度调整
    public function adjust() {
        $username = $_REQUEST['name'];
        $user_id = $_REQUEST['user_id'];
        //获取总的金额
        $account = D('admin/user')->get_account($user_id);
        $bet_amount = D('admin/user')->getBetAmount($user_id);
        if(empty($bet_amount)) $bet_amount = 0;
        $sql = "SELECT regtime FROM `un_user` WHERE `id` = '{$user_id}'";
        $time = $this->db->result($sql);
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

        $limit = bcadd(bcsub(bcmul($nRt,$regRt,2),$betRt,2),$bet_amount,2);
        if($limit<0||$nRt==0) $limit = 0;
        include template('adjust');
    }

    public function adjust_ok() {

        $data = $_REQUEST;

//        $redis = initCacheRedis();
        $username = $data['username'];
//        $co_str = 'adjAcount:'.$username;
//        if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去
//            $redis->expire($co_str,10); //设置它的超时
//            lg('adj_acount_log','超时时间::'.$redis->ttl($co_str));
//            deinitCacheRedis($redis);
//        }else{
//            lg('adj_acount_log','并发操作::'.'$username::'.$username.',$co_str::'.$co_str);
//            $arr['status'] = 1;
//            $arr['msg'] = $redis->ttl($co_str)."秒后重试给当前玩家调整余额!";
//            deinitCacheRedis($redis);
//            echo encode($arr);
//            return false;
//        }

        $arr = array(
            'id' => $data['id'],
            'money' => $data['money'],
            'username' => $data['username'],
            'account' => $data['account'],
            'bet_amount' => $data['bet_amount'],
            'flag' => $data['state'],
            'bet_state'=>$data['bet_state'],
            'old_bet_amount'=>$data['old_bet_amount'],
            'oper'=> $this->admin['username'],
            'operid'=> $this->admin['userid'],
        );


        $user = D('admin/user');
        $res = $user->adjust_ok($arr);

        if ($res == false) {
            $status = 1;
            $msg = '额度调整失败，请检查调整金额';
        } else {
            $status = 0;
            $msg = '';
        }
        echo json_encode(array('status'=>$status,'rt'=>$res,'msg'=>$msg));
    }

    //累计金额的搜索
    public function mount_sousuo() {
        $data = $_REQUEST;
        $arr = array(
            'username' => $data['username'],
            'weixin' => $data['weixin'],
            'group' => $data['group'],
            'cz_min' => $data['cz_min'],
            'cz_max' => $data['cz_max'],
            'tz_min' => $data['tz_min'],
            'tz_max' => $data['tz_max']
        );
        $_SESSION['arr_mount'] = $arr;
        $user = D('admin/user');
        $data = $user->mount_sousuo($arr);
        include template('money_tj');
    }

    //标记风险会员
    public function biaoji() {
        $id = $_REQUEST['id'];
        $state = $_REQUEST['state'];
        $user = D('admin/user');
        $res = $user->biaoji($id, $state);

        //记录操作日志
        $opInfo = $this->admin;
        if($state==2){ //冻结
            $sql  = "INSERT INTO `un_user_change_log` (`user_id`, `record_id`, `type`, `tab`, `change_name`, `ip`, `addtime`) VALUES ('{$id}', '{$id}', 4,'un_user', '{$opInfo['username']}', '{$opInfo['lastloginip']}', '".time()."')";
            $this->db->query($sql);
        }else if($state==1){
            $sql  = "INSERT INTO `un_user_change_log` (`user_id`, `record_id`, `type`, `tab`, `change_name`, `ip`, `addtime`) VALUES ('{$id}', '{$id}', 5,'un_user', '{$opInfo['username']}', '{$opInfo['lastloginip']}', '".time()."')";
            $this->db->query($sql);
        }

        echo json_encode(array("rt" => $res));
    }

    //冻结用户
    public function dongjie() {
        $id = $_REQUEST['id'];
        $user = D('admin/user');
        $data = $user->getdongjie($id);

        //查日志
        $sql = "SELECT change_name,ip,FROM_UNIXTIME(`addtime`) AS `time` FROM `un_user_change_log` WHERE user_id={$id} AND `type`=4 ORDER BY id DESC";
        $list = $this->db->getall($sql);

        include template('dongjie');
    }

    public function up_remark() {
        $id = $_REQUEST['id'];
        $state = $_REQUEST['state'];
        $remark = $_REQUEST['remark'];
        $user = D('admin/user');
        $res = $user->up_remark($id, $state, $remark);

        //记录操作日志
        $opInfo = $this->admin;
        $sql  = "INSERT INTO `un_user_change_log` (`user_id`, `record_id`, `type`, `tab`, `change_name`, `ip`, `addtime`) VALUES ('{$id}', '{$id}', 4,'un_user', '{$opInfo['username']}', '{$opInfo['lastloginip']}', '".time()."')";
        $this->db->query($sql);

        echo json_encode(array("rt" => $res));
    }

    //强制踢线
    public function forcedKick()
    {
        $id = $_REQUEST['uid'];
        $arr = D('admin/user')->forcedKick($id);
        echo json_encode($arr);
    }

    /**
     * 用户登录ip
     * @param id int 用户id
     */
    public function getUserIP() {
        $id = $_REQUEST['id'];
        $sql = "SELECT * FROM `un_user_login_log` WHERE `user_id` = {$id} ORDER BY `id` DESC LIMIT 0, 20";
        $res = O('model')->db->getAll($sql);

        include template('get-user-ip');
    }


    /**
     * 用户登录ip
     * @param id int 用户id
     */
    public function dummy() {
        $honor_info = $this->db->getall('select name,sort from un_honor where status = 1');

        include template('user-dummy');
    }

    public function adddummy() {
        include template('add-dummy');
    }


    public function addroob(){
        $number = $_POST['number']+0;

        for($v=1;$v<=$number;$v++){
            
            $nicheng_tou=array('快乐的','冷静的','醉熏的','潇洒的','糊涂的','积极的','冷酷的','深情的','粗暴的','温柔的','可爱的','愉快的','义气的','认真的','威武的','帅气的','传统的','潇洒的','漂亮的','自然的','专一的','听话的','昏睡的','狂野的','等待的','搞怪的','幽默的','魁梧的','活泼的','开心的','高兴的','超帅的','留胡子的','坦率的','直率的','轻松的','痴情的','完美的','精明的','无聊的','有魅力的','丰富的','繁荣的','饱满的','炙热的','暴躁的','碧蓝的','俊逸的','英勇的','健忘的','故意的','无心的','土豪的','朴实的','兴奋的','幸福的','淡定的','不安的','阔达的','孤独的','独特的','疯狂的','时尚的','落后的','风趣的','忧伤的','大胆的','爱笑的','矮小的','健康的','合适的','玩命的','沉默的','斯文的','香蕉','苹果','鲤鱼','鳗鱼','任性的','细心的','粗心的','大意的','甜甜的','酷酷的','健壮的','英俊的','霸气的','阳光的','默默的','大力的','孝顺的','忧虑的','着急的','紧张的','善良的','凶狠的','害怕的','重要的','危机的','欢喜的','欣慰的','满意的','跳跃的','诚心的','称心的','如意的','怡然的','娇气的','无奈的','无语的','激动的','愤怒的','美好的','感动的','激情的','激昂的','震动的','虚拟的','超级的','寒冷的','精明的','明理的','犹豫的','忧郁的','寂寞的','奋斗的','勤奋的','现代的','过时的','稳重的','热情的','含蓄的','开放的','无辜的','多情的','纯真的','拉长的','热心的','从容的','体贴的','风中的','曾经的','追寻的','儒雅的','优雅的','开朗的','外向的','内向的','清爽的','文艺的','长情的','平常的','单身的','伶俐的','高大的','懦弱的','柔弱的','爱笑的','乐观的','耍酷的','酷炫的','神勇的','年轻的','唠叨的','瘦瘦的','无情的','包容的','顺心的','畅快的','舒适的','靓丽的','负责的','背后的','简单的','谦让的','彩色的','缥缈的','欢呼的','生动的','复杂的','慈祥的','仁爱的','魔幻的','虚幻的','淡然的','受伤的','雪白的','高高的','糟糕的','顺利的','闪闪的','羞涩的','缓慢的','迅速的','优秀的','聪明的','含糊的','俏皮的','淡淡的','坚强的','平淡的','欣喜的','能干的','灵巧的','友好的','机智的','机灵的','正直的','谨慎的','俭朴的','殷勤的','虚心的','辛勤的','自觉的','无私的','无限的','踏实的','老实的','现实的','可靠的','务实的','拼搏的','个性的','粗犷的','活力的','成就的','勤劳的','单纯的','落寞的','朴素的','悲凉的','忧心的','洁净的','清秀的','自由的','小巧的','单薄的','贪玩的','刻苦的','干净的','壮观的','和谐的','文静的','调皮的','害羞的','安详的','自信的','端庄的','坚定的','美满的','舒心的','温暖的','专注的','勤恳的','美丽的','腼腆的','优美的','甜美的','甜蜜的','整齐的','动人的','典雅的','尊敬的','舒服的','妩媚的','秀丽的','喜悦的','甜美的','彪壮的','强健的','大方的','俊秀的','聪慧的','迷人的','陶醉的','悦耳的','动听的','明亮的','结实的','魁梧的','标致的','清脆的','敏感的','光亮的','大气的','老迟到的','知性的','冷傲的','呆萌的','野性的','隐形的','笑点低的','微笑的','笨笨的','难过的','沉静的','火星上的','失眠的','安静的','纯情的','要减肥的','迷路的','烂漫的','哭泣的','贤惠的','苗条的','温婉的','发嗲的','会撒娇的','贪玩的','执着的','眯眯眼的','花痴的','想人陪的','眼睛大的','高贵的','傲娇的','心灵美的','爱撒娇的','细腻的','天真的','怕黑的','感性的','飘逸的','怕孤独的','忐忑的','高挑的','傻傻的','冷艳的','爱听歌的','还单身的','怕孤单的','懵懂的');
            $nicheng_wei=array('嚓茶','凉面','便当','毛豆','花生','可乐','灯泡','哈密瓜','野狼','背包','眼神','缘分','雪碧','人生','牛排','蚂蚁','飞鸟','灰狼','斑马','汉堡','悟空','巨人','绿茶','自行车','保温杯','大碗','墨镜','魔镜','煎饼','月饼','月亮','星星','芝麻','啤酒','玫瑰','大叔','小伙','哈密瓜，数据线','太阳','树叶','芹菜','黄蜂','蜜粉','蜜蜂','信封','西装','外套','裙子','大象','猫咪','母鸡','路灯','蓝天','白云','星月','彩虹','微笑','摩托','板栗','高山','大地','大树','电灯胆','砖头','楼房','水池','鸡翅','蜻蜓','红牛','咖啡','机器猫','枕头','大船','诺言','钢笔','刺猬','天空','飞机','大炮','冬天','洋葱','春天','夏天','秋天','冬日','航空','毛衣','豌豆','黑米','玉米','眼睛','老鼠','白羊','帅哥','美女','季节','鲜花','服饰','裙子','白开水','秀发','大山','火车','汽车','歌曲','舞蹈','老师','导师','方盒','大米','麦片','水杯','水壶','手套','鞋子','自行车','鼠标','手机','电脑','书本','奇迹','身影','香烟','夕阳','台灯','宝贝','未来','皮带','钥匙','心锁','故事','花瓣','滑板','画笔','画板','学姐','店员','电源','饼干','宝马','过客','大白','时光','石头','钻石','河马','犀牛','西牛','绿草','抽屉','柜子','往事','寒风','路人','橘子','耳机','鸵鸟','朋友','苗条','铅笔','钢笔','硬币','热狗','大侠','御姐','萝莉','毛巾','期待','盼望','白昼','黑夜','大门','黑裤','钢铁侠','哑铃','板凳','枫叶','荷花','乌龟','仙人掌','衬衫','大神','草丛','早晨','心情','茉莉','流沙','蜗牛','战斗机','冥王星','猎豹','棒球','篮球','乐曲','电话','网络','世界','中心','鱼','鸡','狗','老虎','鸭子','雨','羽毛','翅膀','外套','火','丝袜','书包','钢笔','冷风','八宝粥','烤鸡','大雁','音响','招牌','胡萝卜','冰棍','帽子','菠萝','蛋挞','香水','泥猴桃','吐司','溪流','黄豆','樱桃','小鸽子','小蝴蝶','爆米花','花卷','小鸭子','小海豚','日记本','小熊猫','小懒猪','小懒虫','荔枝','镜子','曲奇','金针菇','小松鼠','小虾米','酒窝','紫菜','金鱼','柚子','果汁','百褶裙','项链','帆布鞋','火龙果','奇异果','煎蛋','唇彩','小土豆','高跟鞋','戒指','雪糕','睫毛','铃铛','手链','香氛','红酒','月光','酸奶','银耳汤','咖啡豆','小蜜蜂','小蚂蚁','蜡烛','棉花糖','向日葵','水蜜桃','小蝴蝶','小刺猬','小丸子','指甲油','康乃馨','糖豆','薯片','口红','超短裙','乌冬面','冰淇淋','棒棒糖','长颈鹿','豆芽','发箍','发卡','发夹','发带','铃铛','小馒头','小笼包','小甜瓜','冬瓜','香菇','小兔子','含羞草','短靴','睫毛膏','小蘑菇','跳跳糖','小白菜','草莓','柠檬','月饼','百合','纸鹤','小天鹅','云朵','芒果','面包','海燕','小猫咪','龙猫','唇膏','鞋垫','羊','黑猫','白猫','万宝路','金毛','山水','音响');
            $tou_num=rand(0,331);
            $wei_num=rand(0,325);
            $nicheng=$nicheng_tou[$tou_num].$nicheng_wei[$wei_num];
            $key='';
            $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';  
            for($i=0;$i<8;$i++){   
                $key .= $pattern[mt_rand(0,35)];    //生成php随机数   
            }
            $post_data['username']       = $key;
            $post_data['password']          = '123456';
            $post_data['password2']         = '123456';
            $post_data['nickname']    = $nicheng;
            $post_data['honor_upgrade']    = 5;
            $post_data['avatar']    = D('Avatar')->fetchRandomPic();
            
            $param = array_map('deal_array', $post_data);
            $username = strtolower(trim($param['username']));
            $password = trim($param['password']);
            $password2 = trim($param['password2']);
            $avatar = trim($param['avatar']);
            $user = D('user');
            //验证参数
            if ($user->getUserInfo('username', array('username' => $username), '', '', '', true) || preg_match('/.*(script).*/', $username)) {
                $this->ajaxReturn(100001, '用户名已存在', 1);
            }
            if (!preg_match('/^[a-zA-Z0-9]{6,15}$/', $username)) {
                $this->ajaxReturn(100002, '用户名仅限英文字母和数字，6到15个字符', 1);
            }
            if (!preg_match('/^[a-zA-Z0-9_]{6,15}$/', $password)) {
                $this->ajaxReturn(100003, '密码仅限英文字母、数字和下划线，6到15个字符', 1);
            }
            if ($password != $password2) {
                $this->ajaxReturn(100004, '两次密码输入不一致', 1);
            }
            $nickname = trim($param['nickname']);
            if(!$nickname)  $this->ajaxReturn(100004, '呢称不能为空', 1);
            if(mb_strlen($nickname) > 8 || !preg_match( "/^[\x{4e00}-\x{9fa5}a-zA-Z]+$/u", $nickname)) {
                $this->ajaxReturn(100004, '昵称格式错误,格式为1-8位字母或中文'.$nickname, 1);
            }

            $honor_upgrade = getParame('honor_upgrade',0, 1);
            if($honor_upgrade) {
                if(!preg_match("/^\d*$/",$honor_upgrade)) $this->ajaxReturn(100004, '等级为纯数字', 1);

                $sScore = $eScore = 0;
                $honorInfo = $this->db->getone("select name, icon, sort, score, grade,status from un_honor where sort = $honor_upgrade");
                if(!$honorInfo)  $this->ajaxReturn(100004, '该等级不存在或已删除,请选择其他有效等级', 1);
                if($honorInfo['status'] != 1)  $this->ajaxReturn(100004, '该等级已经禁用,请选择其他有效等级', 1);
                $sScore = $honorInfo['score'];

                $nextLevel = $honor_upgrade + 1;
                $nextHonorInfo = $this->db->getone("select name, icon, sort, score, grade from un_honor where status = 1 and sort = $nextLevel");
                if(!$nextHonorInfo){
                    $eScore = $sScore + 1000;
                }else {
                    $eScore = $nextHonorInfo['score'];
                }

                $honor_score = rand($sScore, $eScore);
            }else {
                $honor_score = 0;
            }

            if(!$avatar) $avatar = D('Avatar')->fetchRandomPic();

            //添加用户
            $data = array(
                'username' => $username,
                'password' => md5($password),
                'nickname' => $nickname,
                'avatar' => $avatar,
                'regtime' => SYS_TIME,
                'birthday' => SYS_TIME,
                'regip' => ip(),
                'loginip' => ip(),
                'logintime' => SYS_TIME,
                'logintimes' => 0,
                'honor_score' => $honor_score,
                'honor_upgrade' => $honor_upgrade,
                'reg_type' => 11,//11假人
                'entrance' => 4//4 pc
            );

            $userId = $user->add(array_filter($data));

            //添加资金账户
            $map = array(
                'user_id' => $userId
            );
            D('account')->add($map);
            
        }

        if (!$userId) {
            $this->ajaxReturn(100005, '添加失败', 1);
        }


        $this->ajaxReturn(100006, '添加成功', 0);

    }

    /**
     * 添加假人功能
     * @param id int 用户id
     */
    public function setDummy() {
        //接收参数
        $param = array_map('deal_array', $_POST);
        $username = strtolower(trim($param['username']));
        $password = trim($param['password']);
        $password2 = trim($param['password2']);
        $avatar = trim($param['avatar']);
        $user = D('user');
        //验证参数
        if ($user->getUserInfo('username', array('username' => $username), '', '', '', true) || preg_match('/.*(script).*/', $username)) {
            $this->ajaxReturn(100001, '用户名已存在', 1);
        }
        if (!preg_match('/^[a-zA-Z0-9]{6,15}$/', $username)) {
            $this->ajaxReturn(100002, '用户名仅限英文字母和数字，6到15个字符', 1);
        }
        if (!preg_match('/^[a-zA-Z0-9_]{6,15}$/', $password)) {
            $this->ajaxReturn(100003, '密码仅限英文字母、数字和下划线，6到15个字符', 1);
        }
        if ($password != $password2) {
            $this->ajaxReturn(100004, '两次密码输入不一致', 1);
        }
        $nickname = trim($param['nickname']);
        if(!$nickname)  $this->ajaxReturn(100004, '呢称不能为空', 1);
        if(mb_strlen($nickname) > 8 || !preg_match( "/^[\x{4e00}-\x{9fa5}a-zA-Z]+$/u", $nickname)) {
            $this->ajaxReturn(100004, '昵称格式错误,格式为1-8位字母或中文', 1);
        }

        $honor_upgrade = getParame('honor_upgrade',0, 1);
        if($honor_upgrade) {
            if(!preg_match("/^\d*$/",$honor_upgrade)) $this->ajaxReturn(100004, '等级为纯数字', 1);

            $sScore = $eScore = 0;
            $honorInfo = $this->db->getone("select name, icon, sort, score, grade,status from un_honor where sort = $honor_upgrade");
            if(!$honorInfo)  $this->ajaxReturn(100004, '该等级不存在或已删除,请选择其他有效等级', 1);
            if($honorInfo['status'] != 1)  $this->ajaxReturn(100004, '该等级已经禁用,请选择其他有效等级', 1);
            $sScore = $honorInfo['score'];

            $nextLevel = $honor_upgrade + 1;
            $nextHonorInfo = $this->db->getone("select name, icon, sort, score, grade from un_honor where status = 1 and sort = $nextLevel");
            if(!$nextHonorInfo){
                $eScore = $sScore + 1000;
            }else {
                $eScore = $nextHonorInfo['score'];
            }

            $honor_score = rand($sScore, $eScore);
        }else {
            $honor_score = 0;
        }

        if(!$avatar) $avatar = D('Avatar')->fetchRandomPic();

        //添加用户
        $data = array(
            'username' => $username,
            'password' => md5($password),
            'nickname' => $nickname,
            'avatar' => $avatar,
            'regtime' => SYS_TIME,
            'birthday' => SYS_TIME,
            'regip' => ip(),
            'loginip' => ip(),
            'logintime' => SYS_TIME,
            'logintimes' => 0,
            'honor_score' => $honor_score,
            'honor_upgrade' => $honor_upgrade,
            'reg_type' => 11,//11假人
            'entrance' => 4//4 pc
        );

        $userId = $user->add(array_filter($data));

        if (!$userId) {
            $this->ajaxReturn(100005, '添加失败', 1);
        }

        //添加资金账户
        $map = array(
            'user_id' => $userId
        );
        D('account')->add($map);
        $this->ajaxReturn(100006, '添加成功', 0);
    }

    /**
     * 用户注册查询账户是否可注册
     */
    public function selectName()
    {

        if (isset($_REQUEST['param']) && !empty($_REQUEST['param'])) {
            $username = trim($_REQUEST['param']);
            if (D('user')->getUserInfo('username', array('username' => $username), '', '', '', true)) {
                $this->ajaxReturn(100009, '账号已存在!', 'n');
            } else {
                $this->ajaxReturn(200002, '账号可以用!', 'y');
            }

        }
        $this->ajaxReturn(100008, '账号不能为空!', 'n');
    }

    /**
     * 添加/编辑 会员层级
     * @return bool|mixed|void
     */
    public function setUserLayer(){
        $id = $_REQUEST['ids'];
        $layer =D('admin/user')->getUserLayer();
        if(!empty($id)){
            $sql = "SELECT id, username,layer_id FROM `un_user` WHERE `id` IN ({$id})";
            $users = O("model")->db->getall($sql);
            $re = end($users);
            $lid = $re['layer_id'];
        }

        include template('set-user-layer');
    }

    /**
     * 添加/编辑 会员组
     * @return bool|mixed|void
     */
    public function setUserGroup(){
        $id = $_REQUEST['ids'];
        $group =D('admin/user')->getGroup();
        if(!empty($id)){
            $sql = "SELECT id, username,group_id FROM `un_user` WHERE `id` IN ({$id})";
            $users = O("model")->db->getall($sql);
            $re = end($users);
            $gid = $re['group_id'];
        }

        include template('set-user-group');
    }

    /**
     * 添加/编辑 会员组
     * @return bool|mixed|void
     */
    public function editUserGroup(){
        $id = $_REQUEST['ids'];
        $data['group_id'] = $_REQUEST['group_id'];
        $res = O("model")->db->update("un_user",$data,"id IN ({$id})");
        if($res){
            $this->refreshRedis('group','all');
            jsonReturn(array('status' => 0, 'msg' => "设置成功"));
        }else{
            jsonReturn(array('status' => 1, 'msg' => "设置失败"));
        }
    }


    /**
     * 添加/编辑 会员层级
     * @return bool|mixed|void
     */
    public function editUserLayer(){
        $id = $_REQUEST['ids'];
        $data['layer_id'] = $_REQUEST['layer_id'];
        $res = O("model")->db->update("un_user",$data,"id IN ({$id})");
        if($res){
            $this->refreshRedis('layer','all');
            jsonReturn(array('status' => 0, 'msg' => "设置成功"));
        }else{
            jsonReturn(array('status' => 1, 'msg' => "设置失败"));
        }
    }

    //游客进入钱包页面提示
    public function tourist_tips(){
        $tips = D('config')->getOneCoupon('value',"nid='tourist_tips'")['value'];
        if(!empty($tips)){
            $tips = json_decode($tips,true);
            $tips['msg'] = base64_decode($tips['msg']);
        }

        if($_POST['msg']){
            $_POST['msg'] = base64_encode($_POST['msg']);
            $data['value'] = json_encode($_POST,JSON_UNESCAPED_UNICODE);
            if(!empty($tips)){
                $res = D('config')->save($data,'nid="tourist_tips"');
            }else{
                $data['nid'] = 'tourist_tips';
                $data['name'] = '游客进入钱包页面提示';
                $res = D('config')->add($data);
            }

            $msg = '操作';
            if($res){
                exit(json_encode(['code'=>1,'msg'=>$msg.'成功~']));
            }else{
                exit(json_encode(['code'=>0,'msg'=>$msg.'失败~']));
            }
        }
        include template('tourist_tips_info');
    }


    /**
     * 会员累计金额统计-组合搜索条件
     * @author king
     * @date 2017/09/18
     * @param $param
     * @return string
     */
    public function create_money_tj_seach_where($param) {
        $username=$param['username'];
        $weixin=$param['weixin'];
        $group_id=$param['group_id'];
        $rg_type=$param['rg_type'];
        $sreg_money=$param['sreg_money'];
        $ereg_money=$param['ereg_money'];
        $stz_money=$param['stz_money'];
        $etz_money=$param['etz_money'];
        $where=" 1=1 ";
        
        if(!empty($username)){
            $where.= " AND b.username='$username' ";
        }

        if(!empty($weixin)){
            $where.=" AND b.weixin='$weixin'";
        }

        if(!empty($group_id)){
            $where.="AND b.group_id=$group_id ";
        }

        if($rg_type==0){
            $where.="AND a.reg_type not in (0,8,9,10,11) ";
        }else{
			$where.="AND a.reg_type=$rg_type ";
		}

        if(!empty($sreg_money) && !empty($ereg_money)){

            $where.=" AND  a.recharge_amount BETWEEN $sreg_money AND $ereg_money ";

        }else if(!empty($sreg_money) && empty($ereg_money)){

            $where.=" AND  a.recharge_amount>$sreg_money ";

        }else if(empty($sreg_money) && !empty($ereg_money)){
            $where.=" AND  a.recharge_amount<$ereg_money ";
        }


        if(!empty($stz_money) && !empty($etz_money)){

            $where.=" AND  a.betting_amount BETWEEN $stz_money AND $etz_money ";

        }else if(!empty($stz_money) && empty($etz_money)){

            $where.=" AND  a.betting_amount>$stz_money ";

        }else if(empty($stz_money) && !empty($etz_money)){
            $where.=" AND  a.betting_amount<$etz_money ";
        }
        return $where;
    }

    //动态获取用户名称
    public function searchUsername()
    {
        $username = $_REQUEST['username'];
        
        if (empty($username)) {
            echo json_encode(["code"=> 0,'msg'=>'查找用户名为空']);
            return;
        }
        
        $sql = "SELECT `username` FROM un_user WHERE username LIKE '" . $username . "%' AND reg_type NOT IN (8,9,11) LIMIT 6";
        $userData = $this->db->getall($sql);
        
        if (empty($userData)) {
            echo json_encode(["code"=> 0,'msg'=>'查找用户结果为空']);
            return;
        }

        echo json_encode(["code"=> 1,'msg'=>'', 'data' => $userData]);
    }
    
    //取用户名称对应的ID
    public function getUsernameId()
    {
        $username = $_REQUEST['username'];
    
        if (empty($username)) {
            echo json_encode(["code"=> 0,'msg'=>'查找用户名为空']);
            return;
        }
    
        $sql = "SELECT `id`, `username` FROM un_user WHERE username = '" . $username . "' AND reg_type NOT IN (8,9,11)";
        $userData = $this->db->getone($sql);
    
        if (empty($userData)) {
            echo json_encode(["code"=> 0,'msg'=>'用户不存在']);
            return;
        }
    
        echo json_encode(["code"=> 1,'msg'=>'', 'data' => $userData]);
    }
    
    /**
     * author: Aho
     * 用户敏感信息权限查看配置页面
     */
    public function show_set_register()
    {
        $registerText = ['weixin' => '微信号', 'qq' => 'QQ', 'mobile' => '电话号码', 'email' => '邮箱','register'=>"注册"];
    
        $registerJson = $this->db->getone("SELECT `id`, `value` FROM `un_config` WHERE `nid` = 'set_register_info'");
        
        if (empty($registerJson['id'])) {
            $insert = [
                'nid'   => 'set_register_info',
                'name'  => '注册项设置状态',
                'desc'  => '注册时，后台配置用户注册时需要额外填写哪些信息,0:关闭，1：打开',
                'is_file' => ''
            ];
            
            $value['status'] = '1';
            $value['register'] = [
                'weixin' => 0,
                'qq' => 0,
                'mobile' => 0,
                'email' => 0,
                'register' => 0
            ];
            $value['limit'] = [
                'register_limit' => 0,
                'register_times' => 0
                ];
            $insert['value'] = json_encode($value, JSON_UNESCAPED_UNICODE);
            $this->db->insert('un_config', $insert);
            
            $registerData = $value['register'];
        }else {
            $register = json_decode($registerJson['value'],true);
            $registerData = $register['register'];
            $limit = $register['limit'];
        }
    
        include template('show_set_register');
    }
    
    /**
     * author: Aho
     * 设置用户敏感信息权限查看配置
     */
    public function set_register_info()
    {
        $arrInfo = [];
        
        $setInfo = $_REQUEST;
        if(empty($setInfo))
        {
            $arr['code'] = -1;
            $arr['msg'] = "提交数据错误！";
            jsonReturn($arr);
        }
        
        if ($setInfo['status'] != 1) {
            $setInfo['status'] == 0;
        }
    
        $registerJson = $this->db->getone("SELECT `value` FROM `un_config` WHERE `nid` = 'set_register_info'");
        $register = json_decode($registerJson['value'],true);
        $registerData = $register['register'];
        $limit = [];
        $limit["register_limit"] = $setInfo["register_limit"];
        $limit["register_times"] = $setInfo["register_times"];

        foreach ($registerData as $ks => $vs) {
            if (isset($setInfo[$ks])) {
                $arrInfo[$ks] = 1;
            } else {
                $arrInfo[$ks] = 0;
            }
        }

        $setJson = json_encode(['status' => $setInfo['status'], 'register' => $arrInfo , 'limit'=>$limit]);
        $this->db->update('un_config', ['value' => $setJson], ['nid' => 'set_register_info']);
        
        $this->refreshRedis('config', 'all'); //刷新缓存

        exit(json_encode(['code'=>1,'msg'=> '操作成功']));
    }
    
    /**
     * 修改会员上级
     * @return bool|mixed|void
     */
    public function setUserClass()
    {
        $strId = '';
        $id = $_REQUEST['ids'];
        
        if(!empty($id)){
            $sql = "SELECT id, username,parent_id FROM `un_user` WHERE `id` IN ({$id})";
            $users = O("model")->db->getall($sql);
            
            foreach ($users as $ku => $vu) {
                if (empty($vu['parent_id'])) {
                    $strId .= $vu['id'] . ',';
                }
            }
            
            if (!empty($strId)) {
                $strId = rtrim($strId, ',');
            }
        }

        if(!$strId) exit('选择会员已有上级');

        include template('set-user-class');
    }
    
    /**
     * 批量修改会员上级
     * @return bool|mixed|void
     */
    public function editUserClass()
    {
        $username = $_REQUEST['username'];
        $strId = $_REQUEST['strId'];
        
        if (empty($strId)) {
            jsonReturn(array('status' => 1, 'msg' => "批量用户账号不能为空！"));
        }
        
        $sql = "SELECT u.id, u.username,u.parent_id,u.reg_type,ut.pids FROM `un_user` u LEFT JOIN un_user_tree ut ON u.id = ut.user_id WHERE u.`username` = '{$username}'";
        $userData = $this->db->getone($sql);
        if (empty($userData)) {
            jsonReturn(array('status' => 1, 'msg' => "上级用户账号不存在！"));
        }
        if(in_array($userData['reg_type'], [0,8,9,11])) jsonReturn(array('status' => 1, 'msg' => "上级用户为游客、假人或机器人！"));
        
        $sql = "SELECT id, username,parent_id FROM `un_user` WHERE `id` IN ({$strId})";
        $arrUser = O("model")->db->getall($sql);
        if (empty($arrUser)) {
            jsonReturn(array('status' => 1, 'msg' => "所选用户账号不存在！"));
        }
        
        foreach ($arrUser as $ka => $va) {
            if (!empty($va['parent_id']) ) {
                continue;
            }
            
            if ($userData['id'] == $va['id']) {
                continue;
            }
            
            $this->db->update('un_user', ['parent_id' => $userData['id']], ['id' => $va['id']]);
            $this->db->update('un_user_tree', ['pids' => $userData['pids'] . $userData['id'] . ',' ], ['user_id' => $va['id']]);
            
            $sql = "SELECT `user_id`, `pids` FROM `un_user_tree` WHERE `pids` like ',{$va['id']}%'";
            $underUser = $this->db->getall($sql);
            if (empty($underUser)) {
                continue;
            }
            
            foreach ($underUser as $kuu => $vuu) {
                $this->db->update('un_user_tree', ['pids' => $userData['pids'] . $userData['id'] . $vuu['pids']], ['user_id' => $vuu['user_id']]);
            }
        }
        
        jsonReturn(array('status' => 0, 'msg' => "修改用户账号上级成功！"));
    }
    
    
    
}
