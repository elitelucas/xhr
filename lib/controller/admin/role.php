<?php



!defined('IN_SNYNI') && die('Access Denied!');



include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');



include S_CORE . 'class' . DS . 'page.php';

include S_CORE . 'class' . DS . 'pages.php';



class RoleAction extends Action {



    private $model;

    private $userModel;

    private $page_cnt;



    //private $db;



    public function __construct() {

        parent::__construct();

        $this->model = D('admin/role');

        $this->userModel = D('admin/user');

        //获取每页展示多少数据

        $redis = initCacheRedis();

        $page_cfg = $redis->hGetAll("Config:100009"); //获取每页展示多少数据

        $this->page_cnt = $page_cfg['value']?$page_cfg['value']:20;

        deinitCacheRedis($redis);

    }



    public function roomConnSet(){

        $data = $_REQUEST;

        if(!$data['do']){

            $redis = initCacheRedis();

            $lists = decode($redis->hget('Config:room_conn_set','value'));

            deinitCacheRedis($redis);

            $list = array();

            foreach ($lists as $v){

                foreach ($v as $kk=>$vv){

                    $list[$kk] = $vv;

                }

            }

            include template('roomConnSet');

        }else{

            lg('room_conn_set',var_export(array(

                '$data'=>$data,

            ),1));

            $json = encode($data['data']);

            $sql = "UPDATE un_config SET `value`='{$json}' WHERE nid='room_conn_set'";

            $res = $this->db->query($sql);

            if($res){

                $this->refreshRedis("config", "all");

                echo encode(array(

                    'state'=>1,

                    'msg'=>'修改成功',

                ));

            }else{

                echo encode(array(

                    'state'=>1,

                    'msg'=>'修改失败',

                ));

            }

        }

    }



    /**

     *

     * 足彩订单生效时间

     *

     */

    public function fbOrderDelay(){

        $redis = initCacheRedis();

        $res = $redis->hGet('Config:football_order_delay_time','value');

        $update_time = $redis->hGet('Config:football_odds_up_time','value'); //比分变动 延时生效时间

        deinitCacheRedis($redis);

        include template('fbOrderDelay');

    }



    public function saveOrderDelay(){

        $time = $_REQUEST['time'];

        $sql = "UPDATE `un_config` SET VALUE={$time} WHERE nid='football_order_delay_time'";

        $re = $this->db->query($sql);



        $update_time = $_REQUEST['update_time'];

        $sql2 = "UPDATE `un_config` SET VALUE={$update_time} WHERE nid='football_odds_up_time'";

        $re2 = $this->db->query($sql2);



        if($re && $re2){

            $this->refreshRedis("config", "all");

            $data = array(

                'code'=>0,

                'msg'=>'修改成功',

            );

        }else{

            $data = array(

                'code'=>0,

                'msg'=>'修改失败',

            );

        }

        echo encode($data);

    }



    /**

     *

     * 修改逆向投注

     *

     */

    public  function reverseSet()

    {

        $id = $_REQUEST['id']?$_REQUEST['id']:1;

        $sql = "SELECT id ,name FROM un_lottery_type where id not in(12) ORDER BY id ASC";

        $lottery_info = $this->db->getall($sql);



        $sql = "SELECT value FROM `un_config` WHERE `nid` = 'reverse_set'";

        $re = $this->db->result($sql);

        lg('reverse_log',"查数据库配置::{$sql},查到的结果::{$re}");

        $data = decode($re);

        $do = !empty($_REQUEST['do'])?$_REQUEST['do']:0;

        if($do==1){

            $type = $_REQUEST['type'];

            $state = $_REQUEST['state'];

            $data[$id][$type]['state']=$state;

            if(in_array($id,array(5,6,11)) && $type==6){ //把三个时时彩中的名 改成球

                $data[$id][$type]['data']=str_replace('名','球',$data[$id][$type]['data']);

            }

            $sql = "UPDATE `un_config` SET VALUE='".encode($data)."' WHERE `nid` = 'reverse_set'";

            lg('reverse_log','查数据库配置SQL::'.$sql);

            $this->db->query($sql);

            $this->refreshRedis("config","all"); //刷新redis

            $json = array(

                'status'=>0,

                'msg'=>'操作成功!',

            );

            echo encode($json);

        }else{

            $list = $data[$id];

            include template('reverse_set');

        }

    }



    

    /**

     *

     * 一键修改逆向投注

     *

     */

    public  function reverseSetAll()

    {

        $id = $_REQUEST['id']?$_REQUEST['id']:1;

        $status = $_REQUEST['status'];

        

        if ($status != 1) {

            $status = 0;

        }



        $sql = "SELECT value FROM `un_config` WHERE `nid` = 'reverse_set'";

        $re = $this->db->result($sql);

        $data = decode($re);

        

        foreach ($data[$id] as $kd =>$vd) {

            $data[$id][$kd]['state'] = $status;



            if(in_array($id,array(5,6,11)) && $kd==6){ //把三个时时彩中的名 改成球 debug

                $data[$id][$kd]['data']=str_replace('名','球',$data[$id][$kd]['data']);

            }

        }



        $sql = "UPDATE `un_config` SET VALUE='".encode($data)."' WHERE `nid` = 'reverse_set'";

        lg('reverse_log','查数据库配置SQL::'.$sql);

        $this->db->query($sql);

        $this->refreshRedis("config","all"); //刷新redis



        $json = array(

            'status'=>0,

            'msg'=>'操作成功!',

        );



        echo encode($json);

    }



    /**

     *  提现设置权限

     */

    public function withdraw_cash() {

        $role_arr=array();

        $adminRoleList = $this->db->getall("select roleid,rolename from un_admin_role");

        $tonePermissions = $this->db->getone("select value from un_config where nid = 'withdraw_cash_role'");

        if($tonePermissions['value']!=''){

            $role_arr=decode($tonePermissions['value']);

        }

        include template('withdraw_cash_role');

    }



    /**

     *  处理提交的数据::提现设置权限

     */

    public function do_withdraw_cash() {

        $data=$_REQUEST['group_id'];

        if(empty($data)){

            $code = array(

                'code' => 1,

                'msg' => '选项不得为空!',

            );

            echo encode($code);

            return false;

        }

        $sql = "UPDATE un_config SET value='".encode($data)."' WHERE nid='withdraw_cash_role';";

        $re = $this->db->query($sql);

        if($re){

            $this->refreshRedis("all", "all"); //刷新缓存

            $code = array(

                'code' => 0,

                'msg' => '成功!',

            );

        }else{

            $code = array(

                'code' => 1,

                'msg' => '失败!',

            );

        }

        echo encode($code);

        return 1;

    }







    /**

     * 撤单和回滚权限控制

     */

    public function cancal_callback_order() {

        $adminRoleList = $this->db->getall("select roleid,rolename from un_admin_role");

        $tonePermissions = $this->db->getone("select value from un_config where nid = 'cancal_callback_order'");

        $ccOrder=decode($tonePermissions['value']);

        $cancal_arr=explode(',',$ccOrder['calcal_order']);

        $callback_arr=explode(',',$ccOrder['callback']);

        include template('cancal_callback_order');

    }



    /**

     * 撤单和回滚权限控制

     * 处理提交过来的数据

     */

    public function do_ccOrder(){

        $input=$_REQUEST;

        if(empty($input['calcal_order'])){

            $code = array(

                'code' => 1,

                'msg' => '撤单选项不得为空!',

            );

            echo encode($code);

            return false;

        }

        if(empty($input['callback'])){

            $code = array(

                'code' => 1,

                'msg' => '回滚选项不得为空!',

            );

            echo encode($code);

            return false;

        }

        $data['calcal_order']=implode(',',$input['calcal_order']);

        $data['callback']=implode(',',$input['callback']);

        $sql = "UPDATE un_config SET value='".encode($data)."' WHERE nid='cancal_callback_order';";

        $re = $this->db->query($sql);

        if($re){

            $this->refreshRedis("all", "all"); //刷新缓存

            $code = array(

                'code' => 0,

                'msg' => '成功!',

            );

        }else{

            $code = array(

                'code' => 1,

                'msg' => '失败!',

            );

        }

        echo encode($code);

    }



    //角色列表

    public function role_lst() {

        $data = $this->model->getRole();

        include template('role_lst');

    }



    //角色权限

    public function role_auth() {

        $roleid = $_REQUEST['id'];

        $auth = $this->model->roleAuth($roleid); //用户拥有的权限

        $authlist = $auth['power_config'];



        $menuRt = $this->model->menuList(); //菜单列表  --  读库

        $menuList = array();

        foreach ($menuRt as $value) {

            if ($value['parentid'] == 0) {

                $tmp = array(

                    "id" => $value['id'],

                    "name" => $value['name'],

                    "child" => array()

                );

                $menuList[$value['id']] = $tmp;

            }

        } //循环第一次,一级菜单

        $twoMenuId = array(); //二级菜单的ID集合

        foreach ($menuRt as $value) {

            if (array_key_exists($value['parentid'], $menuList)) {

                $twoMenuId[$value['id']] = $value['parentid'];

                $tmp = array(

                    "id" => $value['id'],

                    "name" => $value['name'],

                    "child" => array()

                );

                $menuList[$value['parentid']]['child'][$value['id']] = $tmp;

            }

        } //循环第二次,二级菜单

        foreach ($menuRt as $value) {

            if (array_key_exists($value['parentid'], $twoMenuId)) {

                $tmp = array(

                    "id" => $value['id'],

                    "name" => $value['name'],

                    "child" => array()

                );

                $menuList[$twoMenuId[$value['parentid']]]['child'][$value['parentid']]['child'][] = $tmp;

            }

        } //循环第三次,三级菜单



        include template('role_auth');

    }



    //角色权限编辑

    public function role_auth_edit() {

        $auth_req = $_REQUEST['auth_id'];

        $auth_id = empty($auth_req) ? "" : implode(",", $auth_req);

        $roleid = $_REQUEST['roleid'];



        $rt = $this->model->upRoleAuth($roleid, $auth_id);

        echo json_encode(array("rt" => $rt));

    }



    //添加角色

    public function add_role() {

        $data = $_REQUEST;

        $arr['disabled'] = implode(',', $data['disabled']);

        $arr['rolename'] = $data['rolename'];

        $arr['description'] = $data['description'];

        $role = D('admin/role');

        $res = $role->add_role($arr);

        echo json_encode(array("rt" => $res));

    }



    //删除角色

    public function del_role() {

        $roleid = $_REQUEST['id'];

        $role = D('admin/role');

        $res = $role->del_role($roleid);

        echo json_encode(array("rt" => $res));

    }



    //修改角色

    public function update_role() {

        $roleid = $_REQUEST['id'];

        $role = D('admin/role');

        $data = $role->update_role($roleid);

        include template('update_role');

    }



    public function update_role_ok() {

        $data = $_REQUEST;

        $arr = array(

            'roleid' => $data['id'],

            'rolename' => $data['rolename'],

            'disabled' => implode(',', $data['disabled']),

            'description' => $data['description'],

        );



        $role = D('admin/role');

        $res = $role->update_role_ok($arr);

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



    //管理员列表

    public function admin_lst() {

        $role = D('admin/role');

        $data = $role->getAdmin();

        $roleData = $role->getRole();



        //从redis中取后台登录随机授权码开关设置

        $redis = initCacheRedis();

        $admin_random_code_setting = $redis->hGet('Config:admin_random_code_setting', 'value');



        //查看随机授权验证码的开关设置，以及超时时间

        $json_obj = json_decode($admin_random_code_setting, true);

        $expired_time = intval($json_obj['expired_time']);

        $is_open = intval($json_obj['is_open']);



        //当前时间，用于比较随机授权码是否过期用

        $now_time = time();



        //关闭redis

        deinitCacheRedis($redis);



        include template('admin_lst');

    }



    //增加管理员

    public function add_admin() {

        $data = $_REQUEST;

        //$arr['roleid'] = implode(',', $data['roleid']);

        $arr['roleid'] = $data['roleid'];

        $arr['username'] = $data['username'];

        $arr['email'] = $data['email'];

        $arr['password'] = md5($data['password']);

        $role = D('admin/role');

        $res = $role->add_admin($arr);

        echo json_encode($res);

    }



    //拉黑管理员

    public function update_dis() {

        $userid = $_REQUEST['userid'];

        $disabled = $_REQUEST['disabled'];

        $role = D('admin/role');

        $res = $role->update_dis($userid, $disabled);

        echo json_encode(array("rt" => $res));

    }



    //删除管理员

    public function del_admin() {

        $userid = $_REQUEST['userid'];

        $role = D('admin/role');

        $res = $role->del_admin($userid);

        $this->db->delete("#@_admin_log",['user_id'=>$userid]);

        echo json_encode(array("rt" => $res));

    }



    //修改管理员信息

    public function update_admin() {

        $userid = $_REQUEST['userid'];

        $role = D('admin/role');

        $data = $role->update_admin($userid);

        $roleData = $role->getRole();

        $dataRole = $data['roleid'];

        $dataRole = explode(',', $dataRole);



        if ($userid == 1) {

            include template('update_adminor');

        } else {

            include template('update_admin');

        }

    }



    public function update_admin_ok() {

        $data = $_REQUEST;

        $arr = array(

            'username' => $data['username'],

            'email' => $data['email'],

            'roleid' => $data['roleid'],

            'password' => md5($data['password']),

            'userid' => $data['userid']

        );



        $role = D('admin/role');

        $rt = $role->update_admin_ok($arr);

        echo json_encode(array("rt" => $rt));

    }



    //更新admin信息

    public function update_admin_okor() {

        $data = $_REQUEST;



        $role = D('admin/role');

        if (!$role->checkAdminPwd($data['oldpassword'])) {

            echo json_encode(array("rt" => "-999"));

            return;

        }



        $arr = array(

            'email' => $data['email'],

            'password' => md5($data['password']),

            'userid' => $data['userid']

        );

        $rt = $role->update_admin_ok($arr);

        echo json_encode(array("rt" => $rt));

    }



    //审核页面

    public function quota_audit() {

        $arr = $_REQUEST;

        $data = array(

            'user_id' => $arr['user_id'],

            'id' => $arr['id'],

            'stat' => $arr['stat'],

            'account' => $arr['account'],

            'leibieid' => $arr['leibieid'],

            'shenqingid' => $arr['shenqingid'],

            'order_num' => $arr['order_num'],

            'ip' => ip()

        );



        include template('sys_fcheck');

    }



    //审核过程

    public function sys_check_ok() {

        $arr = $_REQUEST;

        $data = array(

            'user_id' => $arr['user_id'],

            'id' => $arr['id'],

            'stat' => $arr['stat'],

            'account' => $arr['account'],

            'leibieid' => $arr['leibieid'],

            'shenqingid' => $arr['shenqingid'],

            'status' => $arr['status'],

            'remark' => $arr['remark'],

            'order_num' => $arr['order_num'],

            'shenheren' => $this->admin['username'],

            'operid' => $this->admin['userid'],

        );



        $res = D('admin/role')->sys_check_ok($data);

        if ($res) {

            $code = array(

                'state' => 1

            );

            echo json_encode($code);

        } else {

            $code = array(

                'state' => 0

            );

            echo json_encode($code);

        }

    }



    //投注显示设置

    public function tz_set() {



        $role = D('admin/role');

        $row = $role->getRow();

        $data = json_decode($row['value'], true);

        //var_dump($data);die;

        $value = $data['key'];

        $val = $data['value'];

        include template('tz_set');

    }



    //设置投注显示分隔符

    public function tz_set_ok() {

        $arr = array(

            'kongge' => ' ',

            ',' => ',',

            '-' => '-',

            '*' => '*',

            '+' => '+'

        );



        $data = $_REQUEST;

        $val = $data['val'];

        //var_dump($data) ;die;

        $row = array(

            'key' => $arr,

            'value' => $val

        );



        $row = json_encode($row);

        $role = D('admin/role');

        $res = $role->tz_set_ok($row);

        $this->refreshRedis("all", "all");

        if ($res) {

            $code = array(

                'state' => 1

            );

            echo json_encode($code);

        } else {

            $code = array(

                'state' => 0

            );

            echo json_encode($code);

        }

    }



    //日志搜索

    public function log_check() {

        $data = $_REQUEST;

        

        if (empty($data['min_time'])) {

            $data['min_time'] = date('Y-m-d');

        }

        

        if (empty($data['max_time'])) {

            $data['max_time'] = date('Y-m-d');

        }

        

        $arr = array(

            'type' => $data['type'],

            'username' => $data['username'],

            'min_time' => $data['min_time'],

            'max_time' => $data['max_time']

        );



        $role = D('admin/role');

        $count = $role->log_sousuo_count($arr);

        $pagesize = 20;

        $url = '?m=admin&c=role&a=log_check';

        $page = new page($count, $pagesize, $url, $arr);

        $show = $page->show();

        $data = $role->log_sousuo($arr, $page->offer, $pagesize);



        $tranList = $role->tranList();



        foreach ($data as $key => $value) {

            $data[$key]['name'] = $tranList[$value['type']];



            //按@符号，切割 check_id_str 字符数据为一个拥有2个元素的数组，下标0为userid，下标1为用户类别

            $tmp_split_arr = explode('@', $value['check_id_str']);



            //如果 check_id_str 字符数据中包含“admin”或“admin”子串，则调用 _fetchUsernameByType 方法查询其用户名

            if ($tmp_split_arr[1] == 'admin' || $tmp_split_arr[1] == 'user') {

                $data[$key]['username'] = $this->_fetchUsernameByType($tmp_split_arr[0], $tmp_split_arr[1]);

            }

        }



        include template('log_check');

    }



    /**

     * 按用户id查询用户名（包含后台管理员和前台用户）

     * 2018-02-24

     */

    public function _fetchUsernameByType($user_id, $user_type)

    {

        //根据后台管理员userid查管理员名称

        if ($user_type == 'admin') {

            $sql = "SELECT username FROM un_admin WHERE userid = {$user_id} LIMIT 1";

            $username = $this->db->result($sql);

        }



        //根据前台用户userid查管理员名称

        elseif ($user_type == 'user') {

            $sql = "SELECT username FROM un_user WHERE id = {$user_id} LIMIT 1";

            $username = $this->db->result($sql);

        }



        else {

            return '';

        }



        //如果没有查到该用户，则用“--”表示

        if (! $username) {

            $username = '--';

        }



        return $username;

    }



    //发言设置

    public function speak() {



        $role = D('admin/role');

        $speak = $role->speak();

        include template('speak');

    }



    //关闭发言设置

    public function speak_close() {

        $data = $_REQUEST;

        $arr = array(

            'lower_money' => $data['lower_money'],

            'lower_word' => $data['lower_word'],

            'visitor_limit' => $data['visitor_limit'],

            'type' => $data['type']

        );



        $role = D('admin/role');

        $res = $role->speak_close($arr);

        $this->refreshRedis("config", "all");

        echo json_encode(array("rt" => $res));

    }



    //白名单

    public function whitelist() {

        $role = D('admin/role');

        $data = $role->whitelist();

        $is_open = D('config')->getOneCoupon('value',"nid='is_open_whiteList'")['value'];

        include template('whitelist');

    }

    //

    public function setIsOpenWhiteList(){

        $data = $_POST;

        $res = D('config')->save($data,"nid='is_open_whiteList'");

        $msg = '设置';

        if($res){

            exit(json_encode(['code'=>1,'msg'=>$msg.'成功~']));

        }else{

            exit(json_encode(['code'=>0,'msg'=>$msg.'失败~']));

        }

    }



    //添加白敏单

    public function add_white() {



        $data = $_REQUEST;



        $arr = array(

            'ip' => $data['ip'],

            'status' => $data['status'],

            'beizhu' => $data['beizhu']

        );



        $role = D('admin/role');

        $res = $role->add_white($arr);



        if ($res) {



            $stat = array(

                'state' => 1

            );

            echo json_encode($stat);

        } else {



            $stat = array(

                'state' => 0

            );



            echo json_encode($stat);

        }

    }



    //白名单删除

    public function del_white() {



        $id = $_REQUEST['id'];

        $role = D('admin/role');

        $res = $role->del_white($id);



        if ($res) {



            $stat = array(

                'state' => 1

            );

            echo json_encode($stat);

        } else {



            $stat = array(

                'state' => 0

            );



            echo json_encode($stat);

        }

    }



    //版本更新

    public function version() {

        $where = $_REQUEST; //搜索条件

        $where['type'] = empty($where['type']) ? 1 : $where['type']; //默认IOS



        $pagesize = 20;

        $listCnt = $this->model->cntVersion($where);

        $url = '?m=admin&c=role&a=version';

        $page = new pages($listCnt, $pagesize, $url, $where);

        $show = $page->show();



        $where['page_start'] = $page->offer;

        $where['page_size'] = $pagesize;

        $list = $this->model->listVersion($where);



        $sql = 'SELECT `value` FROM un_config WHERE nid=\'appDownloadNum\'';

        $re = $this->db->result($sql);

        $data = decode($re);

        if($where['type']==1){

            $download_num = $data['ios'];

        }else{

            $download_num = $data['android'];

        }



        foreach ($list as $key => $value) {

            $list[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);

        }

        

        include template('list-version');

    }



    //新增版本

    public function addVersion() {

        include template('add-version');

    }



    //上传的包处理

    public function uploadAppPackage(){

        lg('add_version','$_FILES::'.var_export($_FILES,1));

        $allowtype=array("apk","ipa");

        $arr=explode(".", $_FILES["file"]["name"]);

        $hz=$arr[count($arr)-1];

        lg('add_version','$hz::'.var_export($hz,1));

        if(!in_array($hz, $allowtype)){

            $data = array(

                'code'=>1,

                'msg'=>'这是不充许的类型!',

            );

            echo encode($data);

            return false;

        }



        $appVersionDir = S_ROOT.'up_files'.DS.'app_version';

        mkdirs($appVersionDir);

        $appName = C('app_name');

        if(empty($appName)){

            $data = array(

                'code'=>1,

                'msg'=>'没有配置app_name!',

            );

            echo encode($data);

            return false;

        }

        $fileName ='';

        if($hz=='apk'){

            $fileName = $appName['android'];

        }else if($hz=='ipa'){

            $fileName = $appName['ios'];

        }



        lg('add_version','$appVersionDir.DS.$fileName::'.var_export($appVersionDir.DS.$fileName,1));



        if(is_uploaded_file($_FILES["file"]["tmp_name"])){

            if(move_uploaded_file($_FILES['file']["tmp_name"],$appVersionDir.DS.$fileName)){

                $data = array(

                    'code'=>0,

                    'msg'=>'上传成功!',

                    "data"=> array(

                        "src"=> 'up_files'.DS.'appVersion'.DS.$fileName,

                    )

                );

            }else{

                $data = array(

                    'code'=>1,

                    'msg'=>'上传失败!',

                );

            }

        }else{

            $data = array(

                'code'=>2,

                'msg'=>'不是一个上传文件!',

            );

        }

        echo encode($data);

    }



    //处理新增版本

    public function doAddVersion() {

        $data = $_REQUEST;

        $rt = $this->model->addVersion(array(

            "addtime" => time(),

            "type" => $data['type'],

            "version" => $data['version'],

            "versionCode" => $data['versionCode'],

            "url" => $data['url'],

            "url_2" => $data['url_2'],

            "url_3" => $data['url_3'],

            "content" => $data['contents']

        ));

        echo json_encode(array("rt" => $rt));

    }



    // 虚拟币

    public function xunibi() {

        $role = D('admin/role');

        $arr = $role->rmbname();

        include template('xunibi');

    }



    public function edit() {



        $data = $_REQUEST;

        unset($data['m'],$data['c'],$data['a']);



        $role = D('admin/role');

        foreach ($data['ids'] as $v){

            $arr = array(

                'minchen' => $data['minchen'],

                'bili' => $data['bili'],

                'id' => $v

            );

            $res = $role->edit($arr);

        }



        $this->refreshRedis("config", "all");

        if ($res) {

            $stat = array(

                'state' => 1

            );

            echo encode($stat);

        } else {

            $stat = array(

                'state' => 0

            );

            echo encode($stat);

        }

    }



    //用户的银行卡关闭设置

    public function switch_card() {

        $role = D('admin/role');

        $value = $role->switch_card();

        $conf = json_decode(D('config')->db->result("select value from un_config where nid='switch_card_type'"),true);

        $yinlian = D('role')->getConfig('binding_yinlian','value');

        $weixin = D('role')->getConfig('binding_weixin','value');

        $zhifubao = D('role')->getConfig('binding_zhifubao','value');

        $qqWallet = D('role')->getConfig('binding_qqWallet','value');

        include template('switch_card');

    }

    //绑定方式

    public function card_type_edit(){

        $data = json_encode($_POST,true);

        $res = D('config')->save(['value'=>$data],"nid='switch_card_type'");



        $msg = '操作';

        if($res){

            exit(json_encode(['code'=>1,'msg'=>$msg.'成功~']));

        }else{

            exit(json_encode(['code'=>0,'msg'=>$msg.'失败~']));

        }

    }

    public function up_switch() {

        $value = $_REQUEST['value'];

        $nid = $_REQUEST['nid'];



        $arr = array('binding_yinlian','binding_weixin','binding_zhifubao','binding_qqWallet','100010');

        if(in_array($nid,$arr)){

            $role = D('admin/role');

            $res = $role->up_switch(array("value" => $value), array("nid" => $nid));

            $this->refreshRedis("all", "all");

        }

        echo json_encode(array("state" => $res));

    }



    public function xiane() {



        $role = D('admin/role');

        $arr = $role->xiane();



        //快捷充值

        $redis = initCacheRedis();

        $re = $redis->hget('Config:quick_cash_set','value');

        $list = decode($re);

        sort($list);



        include template('xiane');

    }





    public  function quickCashSet(){

        $data = $_REQUEST;

        lg('quickCashSet',var_export(array('$data'=>$data),1));

        unset($data['m'],$data['c'],$data['a']);



        foreach ($data['data'] as $k=>$v){

            if(preg_match("/\s/",$v)){

                $msg =array(

                    'code'=>1,

                    'msg'=>'不能有空格!',

                );

                echo encode($msg);

                return false;

                break;

            }

            if(!preg_match("/^\d+(\.\d{1,2})?$/",$v)){

                $msg =array(

                    'code'=>1,

                    'msg'=>'请输入合法数据!',

                );

                echo encode($msg);

                return false;

                break;

            }



            //转化成整数

            $data['data'][$k]=$v;



//            if($v<1 || $v > 10000){

//                $msg =array(

//                    'code'=>1,

//                    'msg'=>'请输入1~10000值!',

//                );

//                echo encode($msg);

//                return false;

//                break;

//            }

//

//            if($v>10) {

//                if ($v < 100) {

//                    if ($v % 10 != 0) {

//                        $msg = array(

//                            'code' => 1,

//                            'msg' => '大于10，小于100，请输入10的倍数!',

//                        );

//                        echo encode($msg);

//                        return false;

//                        break;

//                    }

//                }

//            }

//

//            if($v>99 && $v<1000){

//                if($v%100!=0){

//                    $msg =array(

//                        'code'=>1,

//                        'msg'=>'大于100，小于1000，请输入100的倍数!',

//                    );

//                    echo encode($msg);

//                    return false;

//                    break;

//                }

//            }

//

//            if($v>999 && $v<=10000){

//                if($v%1000!=0){

//                    $msg =array(

//                        'code'=>1,

//                        'msg'=>'大于1000，请输入1000的倍数!',

//                    );

//                    echo encode($msg);

//                    return false;

//                    break;

//                }

//            }

        }

        $len = count($data['data']);

        if($len<1){

            $msg =array(

                'code'=>1,

                'msg'=>'至少要一个值!',

            );

            echo encode($msg);

            return false;

        }



        if($len>6){

            $msg =array(

                'code'=>1,

                'msg'=>'最多只能设置6个值!',

            );

            echo encode($msg);

            return false;

        }



        //判断是否有重复值

        $ndata = array_unique($data['data']);

        if(count($ndata) != $len){

            $msg =array(

                'code'=>1,

                'msg'=>'不允许有重复值!',

            );

            echo encode($msg);

            return false;

        }



        sort($data['data']);

        $sql = "UPDATE `un_config` SET `value`='".encode($data['data'])."' WHERE nid='quick_cash_set'";

        $this->db->query($sql);

        $this->refreshRedis('config', 'all'); //刷新缓存

        $msg =array(

            'code'=>0,

            'msg'=>'更新成功!',

        );

        echo encode($msg);

        return true;

    }



    //修改充值下限

    public function up_recharge() {



        $recharge = $_REQUEST['recharge'];

        $role = D('admin/role');

        $res = $role->up_recharge($recharge);

        $this->refreshRedis("config", "all");

        if ($res) {



            $stat = array(

                'state' => 1

            );

            echo json_encode($stat);

        } else {



            $stat = array(

                'state' => 0

            );



            echo json_encode($stat);

        }

    }





    //修改充值下限

    public function up_recharge_time() {



        $recharge_time = $_REQUEST['recharge_time'];

        $role = D('admin/role');

        $res = $role->up_recharge_time($recharge_time);

        $this->refreshRedis("config", "all");

        if ($res) {

            $stat = array(

                'state' => 1,

            );

            echo json_encode($stat);

        } else {

            $stat = array(

                'state' => 0

            );



            echo json_encode($stat);

        }

    }



    //修改免审核值

    public function unauditWithdral(){

        $amount = $_REQUEST['amount'];

        $role = D('admin/role');

        $res = $role->unauditWithdral($amount);

        if ($res) {

            $stat = array(

                'state' => 1

            );

            echo json_encode($stat);

        } else {

            $stat = array(

                'state' => 0

            );



            echo json_encode($stat);

        }

    }



    //修改提现的范围

    public function up_cash() {

        $cash_upper = $_REQUEST['upper'];

        $cash_lower = $_REQUEST['lower'];

        $role = D('admin/role');

        $res = $role->up_cash($cash_upper, $cash_lower);

        $this->refreshRedis("config", "all");

        if ($res) {

            $stat = array(

                'state' => 1

            );

            echo json_encode($stat);

        } else {

            $stat = array(

                'state' => 0

            );

            echo json_encode($stat);

        }

    }



    //开奖延续时长

    public function kj_time() {



        $role = D('admin/role');

        $arr = $role->kj_time();

        include template('kj_time');

    }



    //修改开奖时长

    public function up_length() {

        $arr = $_REQUEST['data'];

        $res = D('admin/role')->up_length($arr);

        if ($res !== false) {

            $stat = array('state' => 1, 'msg'=>'修改成功！',);

            $this->refreshRedis("all", "all");

        } else {

            $stat = array('state' => 0, 'msg'=>'修改失败！',);

        }

        echo json_encode($stat);

    }



    //系统审核的搜索

    public function sys_check() {

        $data = $_REQUEST;



        $arr = array(

            'leibieid' => $data['leibieid'],

            'faqiren' => $data['faqiren'],

            'neirong' => $data['username'],

            'min_time' => empty($data['min_time']) ? date('Y-m-d') : $data['min_time'],

            'max_time' => empty($data['max_time']) ? date('Y-m-d') : $data['max_time']

        );



        $role = D('admin/role');

        $res = $role->get_sys_count($arr);

        $count = $res['cnt'];

        $t = $res['total'];

        $total = 0;

        foreach ($t as $v) {

            if ($v['stat'] == 1) {

                $total += $v['account'];

            } elseif ($v['stat'] == 2) {

                $total = $total - $v['account'];

            }

        }



        $pagesize = 20;

        $url = '?m=admin&c=role&a=sys_check';

        $page = new page($count, $pagesize, $url, $arr);

        $show = $page->show();

        $data = $role->sys_check_search($arr, $page->offer, $pagesize);

        $pageTotal = 0;

        foreach ($data as $v) {

            if ($v['status'] == 1) {

                if ($v['stat'] == 1) {

                    $pageTotal += $v['account'];

                } elseif ($v['stat'] == 2) {

                    $pageTotal = $pageTotal - $v['account'];

                }

            }

        }

        include template('sys_check');

    }



    //修改白名单

    public function up_whitelist() {



        $id = $_REQUEST['id'];

        $role = D('admin/role');

        $data = $role->up_whitelist($id);

//        dump($data);die;

        include template('up_whitelist');

    }



    

    public function up_whitelist_ok() {



        $data = $_REQUEST;



        $arr = array(

            'id' => $data['id'],

            'ip' => $data['ip'],

            'ip_attribution' => $data['ip_attribution']?:'',

            'status' => $data['status'],

            'beizhu' => $data['beizhu'],

        );

        $role = D('admin/role');



        $res = $role->up_whitelist_ok($arr);



        if ($res) {



            $stat = array(

                'state' => 1

            );

            echo json_encode($stat);

        } else {



            $stat = array(

                'state' => 0

            );



            echo json_encode($stat);

        }

    }



    /**

     * 大厅配置

     * @method GET

     * @return json

     */

    public function lobby() {

        $data = $this->model->lobby();

        $list = array();

        foreach ($data as $value) {

            $list[$value['nid']] = $value['value'];

        }

        include template('role-lobby');

    }



    /**

     * 保存大厅配置数据

     * @method ajax

     * @return json

     */

    public function dolobby() {

        $where = array("nid" => $_REQUEST['nid']);

        $data = array("value" => $_REQUEST['val']);

        if (!in_array($where['nid'], array(100001, 100002, 100003))) {

            return false;

        }

        $rt = $this->model->dolobby($data, $where);

        $this->refreshRedis('config', 'all');

        echo json_encode(array("rt" => $rt));

    }



    /**

     * 轮播图

     * @method GET

     * @return json

     */

    public function banner() {

        $banner_type = ($_REQUEST['banner_type'] == 2) ? 2 : 1;

        $sql = "SELECT * FROM `un_banner` WHERE banner_type = {$banner_type} ORDER BY sort desc";

        $list = $this->db->getall($sql);

        include template('role-banner');

    }



    /**

     * 编辑轮播图

     * @method GET

     * @return json

     */

    public function editBanner() {

        $id = trim($_REQUEST['id']);

        if(!empty($id)){

            $sql = "SELECT * FROM `un_banner` WHERE id = " . $id;

            $list = $this->db->getOne($sql);

            $list['start_time'] = date("Y-m-d H:i:s",$list['start_time']);

            $list['end_time'] = date("Y-m-d H:i:s",$list['end_time']);

        }

        include template('role-edit-banner');

    }



    /**

     * 删除轮播图

     * @method GET

     * @return json

     */

    public function delBanner() {

        $id = $_REQUEST['id'];

        $res = $this->db->delete("un_banner",['id'=>$id]);

        if ($res) {

            $this->refreshRedis('banner', 'all');

            jsonReturn(array('status' => 0, 'data' => '信息删除成功'));

        } else {

            jsonReturn(array('status' => 200012, 'data' => '信息删除失败'));

        }

    }



    /**

     * 保存轮播图数据

     * @method GET

     * @return json

     */

    public function bannerAct() {

        $arr = [];

        $model = D("banner");

        $id = $_REQUEST['id'];

        $data = array(

            'title' => $_REQUEST['title'],

            'sort' => $_REQUEST['sort'],

            'default_path' => $_REQUEST['default_path'],

            'default_url' => $_REQUEST['default_url'],

            'replace_path' => $_REQUEST['replace_path'],

            'replace_url' => $_REQUEST['replace_url'],

            'start_time' => strtotime($_REQUEST['start_time']),

            'end_time' => strtotime($_REQUEST['end_time']),

            'is_show' => $_REQUEST['is_show'],

            'banner_type' => $_REQUEST['banner_type'],

        );

        if(empty($data['replace_path'])){

            unset($data['start_time']);

            unset($data['end_time']);

        }

        

        if(empty($data['title'])){

            $arr['code'] = -1;

            $arr['msg'] = "标题不能为空";

            jsonReturn($arr);

        }

        if(empty($data['sort'])){

            $arr['code'] = -1;

            $arr['msg'] = "排序不能为空";

            jsonReturn($arr);

        }

        if (!preg_match("/^[1-9]*$/",$data['sort'])) {

            $arr['code'] = -1;

            $arr['msg'] = "排序只能输入正整数";

            jsonReturn($arr);

        }

        if(empty($data['default_path'])){

            $arr['code'] = -1;

            $arr['msg'] = "默认图片不能为空";

            jsonReturn($arr);

        }



        if($data['start_time'] > $data['end_time']){

            $arr['code'] = -1;

            $arr['msg'] = "开始时间不能大于结束时间";

            jsonReturn($arr);

        }

        

        if (empty($id)) {

            $list = $this->db->getall("select sort from un_banner where banner_type = {$data['banner_type']} and sort = {$data['sort']}");

            if (!empty($list)) {

                $arr['code'] = -1;

                $arr['msg'] = "排序不能重复";

                jsonReturn($arr);

            }

        }



        $rs1 = $model->editBannerAct($id,$data);

        

        jsonReturn($rs1);

    }



    /**

     * 上传轮播图

     * @method GET

     * @return json

     */

    public function uploadImg() {

        $error = array();

        if ($_FILES['file']['error'] > 0) {

            jsonReturn(array('status' => 200000, 'msg' => '图片上传失败'));

        } else {

            if ($_FILES['file']['size'] > 600 * 1024) { // 图片大于2MB

                jsonReturn(array('status' => 200001, 'msg' => '图片大小超过了600KB，上传失败'));

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

                        jsonReturn(array('status' => 200001, 'msg' => '图片格式不正确'));

                }



                $FileName = md5(time()) . "." . $suffix;



                $path = $this->getAvatarUrl($FileName, 0);



                if (!move_uploaded_file($_FILES['file']['tmp_name'], $path)) {

                    jsonReturn(array('status' => 200001, 'msg' => '图片上传失败'));

                }

                jsonReturn(array('status' => 0, 'data' => "/" . C('upfile_path') . '/banner/' . $FileName));

            }

        }

    }



    private function getAvatarUrl($avatarFileName, $isRand = 1) {

        if (empty($avatarFileName)) {

            return '';

        }

        $avatarUrl = S_ROOT . C('upfile_path') . '/banner/';

        if ($isRand) {

            $avatarUrl .= ('?rand=' . time());

        }

        if (!file_exists($avatarUrl)) {

            @mkdir($avatarUrl, 0777, true);

        }



        return $avatarUrl . $avatarFileName;

    }



    /**

     * 扫码返现

     * @method GET

     * @return json

     */

    public function cash_back() {



        if (isset($_REQUEST['submit'])) {

            //接收参数

            $list = [];

            $arr = [];

            $index = 0;

            foreach ($_REQUEST as $k=>$i){

                if($k=='low_'.$index) $arr['low'] = $i;

                if($k=='upper_'.$index) $arr['upper'] = $i;

                if($k=='rate_'.$index) {

                    $arr['rate'] = $i;

                    $index++;

                    $list[] = $arr;

                }

            }

            $list =json_encode($list);



            $res = D('config')->save(array('value' => $list), array('nid' => 'cashBack'));



            $this->refreshRedis('config', 'all');

            echo json_encode(array("rt" => $res));

            exit;

        }



        $nid = 'cashBack';

        $list = D('config')->getOneCoupon('value', array('nid' => 'cashBack'));

        @$list = json_decode($list['value'],true);

        if(!is_array($list)){

            $list = [];

            $arr['low'] = 0;

            $arr['upper'] = 0;

            $arr['rate'] = 0;

            $list[] = $arr;

            $res = D('config')->save(array('value' => json_encode($list)), array('nid' => $nid));

            $this->refreshRedis('config', 'all');

        }



        include template('cash_back');

    }



    /**

     * 扫码返现

     * @method GET

     * @return json

     */

    public function odds_explain() {



        if (isset($_REQUEST['submit'])) {

            //接收参数

            $nid = $_REQUEST['nid'];

            $val = $_REQUEST['val'];



            $res = D('config')->save(array('value' => $val), array('nid' => $nid));



            $this->refreshRedis('config', 'all');

            echo json_encode(array("rt" => $res));

            exit;

        }



        $nid = 'oddsExplain';

        $list = D('config')->getOneCoupon('value', array('nid' => $nid));

        include template('odds-explain');

    }



    //昵称账号显示开关

    public function stage() {

        $stage = $this->model->getConfig('stage');

        $tznickname = $this->model->getConfig('tznickname');

        $direct = $this->model->getConfig('direct');

        include template('stage');

    }



    //昵称账号显示开关

    public function doStage() {

        $state = $_REQUEST['state'];

        $nid = $_REQUEST['nid'];

        if(!in_array($nid,array('direct','stage','tznickname'))){

            return json_encode('无此参数!!');

        }

        $rt = $this->model->doStage(array("value" => $state), array("nid" => $nid));

        $this->refreshRedis('config', 'all');

        echo json_encode(array("rt" => $rt));

    }



    //客服配置

    public function customerInfoSet() {

        $redis = initCacheRedis();

        $val = $redis->hget('Config:kefu_set','value');

        deinitCacheRedis($redis);

        $re  = decode($val);



        $weixin = $re["weixin"];

        $qq = $re["qq"];

        $kf = $re["kefu"];

        $type = $_REQUEST['type'];

        if(empty($type)){

            include template('customerInfoSet');

        }else{

            if($type=='weixin'){

                $title = '微信配置';

                $kf = $weixin;

            }elseif($type=='qq'){

                $title = 'QQ配置';

                $kf = $qq;

            }else{

                $title = '客服地址';

            }

            include template('modCustomerInfo');

        }

    }



    //客服配置方法

    public function customerInfoAct() {

        $data = $_POST;

        //取数据

        $redis = initCacheRedis();

        $val = $redis->hget('Config:kefu_set','value');

        deinitCacheRedis($redis);

        $config  = decode($val);



        $typeArr = [

            'weixin' => '微信',

            'qq' => 'QQ',

            'kefu' => '客服',

        ];



        if($config[$data['type']] == $data['value']) {

            $arr['code'] = 0;

            $arr['msg'] = "修改成功";

            echo jsonReturn($arr);

        }



        $log_remark = '更改'.$typeArr[$data['type']].'客服配置:'.$config[$data['type']].'=>'.$data['value'];



        $config[$data['type']] = $data['value'];



        //入库

        $sql  = "update un_config set value='".encode($config)."' where nid='kefu_set'";

        lg('kefu_set',"客服::".$this->admin['username']."更改客服配置,执行SQL::{$sql}");

        $re  = $this->db->query($sql);



        admin_operation_log($this->admin['userid'], 90, $log_remark);



        //刷新redis

        $this->refreshRedis('config', 'all');



        $arr = [];

        if (0 === $re) {

            $arr['code'] = -1;

            $arr['msg'] = "修改失败，请重试";

        } else {

            $arr['code'] = 0;

            $arr['msg'] = "修改成功";

        }

        echo jsonReturn($arr);

    }



    //第三方线上支付支持列表

    public function thirdConfigList() {

        $sql = "select * from un_payment_config where type in(67,68,75, 139) AND `id` < 117 order by sort asc";

        $data = $this->db->getall($sql);

//        foreach($data as $key=>$val)

//        {

//            $config = unserialize($val['config']);

//            $data[$key]['config'] = $config;

//        }

//        print_r($data);

        include template('thirdConfigList');

    }



    //编辑某给第三方支付

    public function thirdConfigEdit() {

        $id = $_GET['id'];

        $sql = "select * from un_payment_config where id = {$id}";

        $data = $this->db->getone($sql);

        $data['config'] = unserialize($data['config']);

        $payArrs = explode(',',$data['pay_layers']);

        $layerArr = $this->userModel->getUserLayer(); //所有的层级信息 取redis

        include template('thirdConfigEdit');

    }



    public function thirdConfigAct() {

        $data = $_POST;

        $id = $data['id'];

        unset($data['id']);

        //获取config配置

        $paymentInfo = D('paymentconfig')->getOneCoupon('config', array('id' => $id));

        $config = unserialize($paymentInfo['config']);

        //合并config配置（修改其中一些项）

        $configs = multiArrayMerge($config, $data['config']);

        $data['config'] = serialize($configs);



        $field = '';

        foreach ($data as $key => $val) {

            $field.= $key . " = '" . $val . "',";

        }

        $field = trim($field, ",");

        $sql = 'update un_payment_config set ' . $field . ' where id = ' . $id . ';';

        $rows = $this->db->query($sql);

        if ($rows === true) {

            $arr['code'] = 0;

            $arr['msg'] = "操作成功";

            $this->refreshRedis('paymentConfig', 'all'); //刷新缓存

        } else {

            $arr['code'] = -1;

            $arr['msg'] = "操作失败";

        }

        echo jsonReturn($arr);

    }



    //新第三方线上支付列表

    public function thirdPaymentList()

    {

        $type1 = ['wx', 'ali', 'wy', 'qq', 'yl', 'jd'];

        $type2 = ['wxwap', 'aliwap', 'wykj', 'qqwap', 'ylwap', 'jdwap'];

        $type3 = ['wxh', 'alih', 'wyh', 'qqh','ylh', 'jdh'];

    

        $sql = "select id, type, name, pay_layers, sort, canuse, config from un_payment_config where type in(67,68,75,139,214,215) AND `id` > 116 order by id desc";

        //$sql = "select * from un_payment_config where type in(67,68,75) order by sort asc";

        $data = $this->db->getall($sql);

    

        foreach ($data as $k => $v) {

            $payConfig = unserialize($v['config']);

    

            //同一支付，不同支付方式配置，（如微信：扫码、WAP、H5）

            if (!empty($payConfig['payType'])) {

                //兼容上一版本库

                foreach ($payConfig['payType'] as $kp =>$vp) {

                    $type = [];

                    if (in_array($kp, $type1)) {

                        $type['type'] = 1;

                        $type['name'] = '扫码';

                        

                        if ($v['type'] == 75) {

                            $type['name'] = '网银';

                        } 

                        

                        $type['status'] = isset($vp['payStatus']) ? $vp['payStatus'] : 1; //兼容老版本的支付没有这个字段

                    } elseif (in_array($kp, $type2)) {

                        $type['type'] = 2;

                        $type['name'] = 'WAP';

                        

                        if ($v['type'] == 75) {

                            $type['name'] = '快捷';

                        }



                        $type['status'] = isset($vp['payStatus']) ? $vp['payStatus'] : 1; //兼容老版本的支付没有这个字段

                    } elseif (in_array($kp, $type3)) {

                        $type['type'] = 3;

                        $type['name'] = 'H5';

                        $type['status'] = isset($vp['payStatus']) ? $vp['payStatus'] : 1; //兼容老版本的支付没有这个字段

                    } else {

                        continue;

                    }

    

                    $data[$k]['pay_type'][] = $type;

                }

            }

        }



        include template('thirdPaymentList');

    }

    

    //添加新第三方线上支付列表

    public function thirdPayment()

    {

        $flag = 0;

        $sort = 0;

        $error_status = 0;

        $error_msg = '';

        $pay_name     = [];

        $pay_type     = [];

        $configs      = [];

        $config_pay   = [];

        $config_type  = [];

        $arr_user_group = [];

        $pay_layers = '1,2,3,5,4,6,7,8,9,10,11';

        $pay_nid = isset($_REQUEST['pay_nid']) ? $_REQUEST['pay_nid'] : '';

       

        //没有具体支付类型就加载默认类型

        if (empty($pay_nid)) {

            $pay_nid = 'nid_pay';

        }



        $jsonData = file_get_contents(S_CORE . 'class' . DS . 'pay'  . DS . 'pay.json');

        $nidData = json_decode($jsonData,true);

        

        if (empty($nidData)) {

            $error_msg = 'json配置文件格式错误，无法导入支付配置列表，请检查pay.json文件！';

            $error_status = 1;

        } else {

            foreach ($nidData as $key => $value) {

                if ($pay_nid == $value['nid']) {

                    $data['config'] = $value;

                    $config_pay     = $value['payType'];

                    $data['name']   = $value['name'];

                    $data['nid']    = $value['nid'];

                }

                $pay_name[] = [

                    'name'  => $value['name'],

                    'value' => $value['nid']

                ];

            }

    

            if (!empty($pay_nid)) {

                $sql = "select * from un_payment_config where nid = '{$pay_nid}'";



                $pay_data = $this->db->getall($sql);

                if (!empty($pay_data)) {

                     foreach ($pay_data as $ks =>$vs) {

                        $config = unserialize($vs['config']);

                        if (!empty($config)) {

                            if (!empty($config['payType'])) {

                                $configs = $config;

                                foreach ($config['payType'] as $ky => $va) {

                                    if (!empty($va['id'])) {

                                        $config_type[] = $va['id'];

                                    }

                                }

                            }

                        }

                    }

                    

                    if (!empty($configs)) {

                        $data['config'] = $configs;

                    }



                    $data['canuse'] = 0;

                    $data['sort']   = 0;

                    $flag = 1;

                    $arr_user_group = explode(',', $pay_data[0]['user_group']);

                }

            }

        }



        $payArrs = explode(',',$pay_layers);

        $layerArr = $this->userModel->getUserLayer();

        

        $sql = "select `id`, `name` from `un_user_group`";

        $user_group = $this->db->getall($sql);



        include template('editThird');

    }



    //代付信息

    public function editWithdraw()

    {

        $json = file_get_contents(S_CORE . 'class' . DS . 'withdraw'  . DS . 'withdraw.json');



        $json = json_decode($json,true);

        $nid = $_REQUEST['nid'];

        $id = $_REQUEST['id'];

        $arr_user_group = [];

        $pay_layers = '1,2,3,5,4,6,7,8,9,10,11';



        $lastJsonData = $json[count($json)-1];

        if ($id) {

            $sql = "select id,nid, name , config,group_id,canuse,user_group,pay_layers from un_payment_config where id = ". $id;

            $data = $this->db->getone($sql);

            $trueNid = $data['nid'];

        } else {

            if ($nid) {

                foreach ($json as $k => $v) {

                    if ($nid == $v['nid']) {

                        $lastJsonData = $v;

                    }

                    break;

                }

            }

            $trueNid = $nid?$nid:$lastJsonData['nid'];

            $sql = "select id,nid, name , config,min_recharge,max_recharge,group_id,canuse,user_group,pay_layers from un_payment_config where nid = '{$trueNid}' ";

            $data = $this->db->getone($sql);

        }

        if ($data) {

            $flag = 1;

            $data['config'] = unserialize($data['config']);

            $data['config']['nid'] = $data['nid'];

            $lastJsonData = $data['config'];

            $arr_user_group = explode(',', $data['user_group']);

        } else {

            $data = $lastJsonData;

        }



        $payArrs = explode(',',$pay_layers);

        $layerArr = $this->userModel->getUserLayer();



        $sql = "select id, name from un_user_group where limit_state =1 ";

        $user_group = $this->db->getall($sql);



        include template('editWithdraw');



    }





    //代付方式列表

    public function withdrawList()

    {



        $sql = "select id, type, name, pay_layers,user_group, sort, canuse from un_payment_config where type =302 order by id desc";

        $data = $this->db->getall($sql);





        include template('withdrawList');

    }



    //添加代付方式

    public function addWithdraw()

    {

        $postData = $_POST;

        $id = $postData['id'];

        unset($postData['id']);

        $nid = $postData['nid'];

        $postData['name'] = $postData['config']['name'];

        $postData['config'] = serialize($postData['config']);

        $postData['type'] = 302; //代付dictionary ID

        $arr = array('code'=> -1, 'msg' => "操作失败");

        if ($id) {

            $whereid = " and id <> ".$id;

        } else {

            $whereid = "";

        }

        $sql = "select id from un_payment_config where type = 302 and canuse =1" .$whereid;

        $conuse = $this->db->getone($sql);

        if ($postData['canuse'] == 1 && $conuse['id']) {

            $arr['msg'] = '已有其它代付为打开状态';

            echo jsonReturn($arr);

            return;

        }



        if ($id) {

            $sql = "select min_recharge,config,max_recharge,name,group_id from un_payment_config where id = '{$id}' ";

            $where = ['id'=>$id];

            $idData = $this->db->getone($sql);

            if (!$idData) {

                $arr['msg'] = '请选择正确的数据';

                echo jsonReturn($arr);

                return;

            }

        }



        if ($nid && !$id) {

            $sql = "select min_recharge,config,max_recharge,name,group_id from un_payment_config where nid = '{$nid}' ";



            $where = ['nid'=>$nid];

            $nidData = $this->db->getone($sql);

        }

        if ($idData || $nidData) {

            $f = '更新';

            $result = $this->db->update("#@_payment_config",$postData,$where);

        } else {

            $f=  '添加';

            $result = $this->db->insert("#@_payment_config",$postData);

        }



        $canuseZh = $postData['canuse']?'开启':'停用';;

        $log_remarks = $f."代付:".$postData['name'].'--状态:'.$canuseZh;

        admin_operation_log($this->admin['userid'], 130, $log_remarks);



        if ($result) {

            $arr = ['code'=> 0, 'msg'=> '操作成功'];

        }



        echo jsonResult($arr);

        return;

    }





    //添加新第三方线上支付列表

    public function editThird()

    {

        $config = [];

        $data   = [];

        $bank_id = '';

        $pay_data = [];

        $bankData = [];

        $pay_name = '';

        $old_config = [];  //支付配置

        $post_data = $_POST;

        $id = $post_data['id'];

//        dump($post_data);

        unset($post_data['id']);

        $pay_type = $post_data['pay_type'];

        unset($post_data['pay_type']);

        

        $nid = $post_data['nid'];



        $canuseZh = $post_data['canuse']?'开启':'停用';

        

        //可支付银行是否为空

        if (!empty($post_data['pay_banks'])) {

            $pay_banks = $post_data['pay_banks'];

            $pay_banks = implode(',', array_unique(explode(',',$pay_banks)));

            $bankData = $pay_banks;

            //$configs['payType']['wy']['bank_id'] = $pay_banks;

        }

        

        if (isset($post_data['pay_banks'])) {

            unset($post_data['pay_banks']);

        }



        $this->db->query(BEGIN);

        try {

            //获取config配置

            $jsonData = file_get_contents(S_CORE . 'class' . DS . 'pay'  . DS . 'pay.json');

            $nidData = json_decode($jsonData,true);



            foreach ($nidData as $key => $value) {

                if ($nid == $value['nid']) {

                    $config = $post_data['config'];

                    $config['name'] = $value['name'];

                    unset($post_data['config']);

                    $pay_data = $value['payType'];

                    //$post_data['config']['payType'] = $value['payType'];

                    //$post_data['config']['name'] = $value['name'];

                    $post_data['name']  = $value['name'];

                    

                    //$post_data['config'] = serialize($post_data['config']);

                    if (!empty($value['payBank'])) {

                        $sql = 'SELECT * FROM `un_bank_info`';

                        $bank_info = $this->db->getone($sql);

                        $arr_feild = array_keys($bank_info);

                        

                        if (!in_array($nid, $arr_feild)) {

                            $create_feild_sql = 'alter table `un_bank_info` add column `' . $nid . '` varchar(20) NOT NULL default "" after `bank_code`';

                            $ret1 = $this->db->query($create_feild_sql);

                            

                            if (!$ret1) {

                                $this->db->query('ROLLBACK');

                                $arr['code'] = -1;

                                $arr['msg'] = "操作失败";

                            

                                echo jsonReturn($arr);

                                return;

                            }

                        }



                        foreach ($value['payBank'] as $k => $v) {

                            $sql = 'update `un_bank_info` set `' . $nid . '` = "' . $v['code'] . '" where `id` = ' . $v['bank_id'];

                            $this->db->query($sql);

                            $bank_id .= $v['bank_id'] . ',';

                        }

                        if (!empty($bank_id)) {

                            $bank_id = trim($bank_id, ',');

                        }

                    }



                    break;

                }

            }



            if (empty($pay_data)) {

                $this->db->query('ROLLBACK');

                $arr['code'] = -1;

                $arr['msg'] = "操作失败,无相关支付类型配置（pay.json）";



                echo jsonReturn($arr);

                return;

            }

            

            $pay_type = explode(',',$pay_type);

            if (!empty($pay_type)) {

                //如果已有相关支付配置，处理

                $sql = 'SELECT `config` FROM `un_payment_config` WHERE `nid` = "' . $nid . '"';

                $old_pay = $this->db->getone($sql);

                if (!empty($old_pay)) {

                    $old_config = $config;

                    $old_config['payType'] = [];

                    $old_configs = serialize($old_config);

                    

                    //旧配置更新新配置，但支付类型除外

                    $sql = "update un_payment_config set config = '" . $old_configs ."' WHERE `nid` = '" . $nid . "'";

                    $ret = $this->db->query($sql);

                }



                foreach ($pay_type as $ke => $vu) {

                    $type    = 0;

                    $field   = '';

                    $name    = '';

                    $addStr1 = '';

                    $addStr2 = '';

                    

                    if ($vu == 1 || $vu == 2 || $vu == 3) {         //微信扫码和微信WAP

                        $name = '微信';

                        $type = 67;

                        $addStr1 .= 'type,bank_id,logo) ';

                        $addStr2 .= '67,0,"/up_files/room/weixin.png") ';

                        if ($vu == 1) {

                            $field = 'wx';

                        } elseif ($vu == 2) {

                            $field = 'wxwap';

                        } elseif($vu == 3) {

                            $field = 'wxh';

                        }

                        

                    } elseif ($vu == 4 || $vu == 5 || $vu == 6) {   //支付宝扫码和支付WAP

                        $name = '支付宝';

                        $type = 68;

                        $addStr1 .= 'type,bank_id,logo) ';

                        $addStr2 .= '68,0,"/up_files/room/zhifubao.png") ';

                        if ($vu == 4) {

                            $field = 'ali';

                        } elseif ($vu == 5) {

                            $field = 'aliwap';

                        } elseif ($vu == 6) {

                            $field = 'alih';

                        }

                        

                    } elseif ($vu == 7 || $vu == 8 || $vu == 9) {   //QQ钱包扫码和QQ钱包WAP

                        $name = 'QQ钱包';

                        $type = 139;

                        $addStr1 .= 'type,bank_id,logo) ';

                        $addStr2 .= '139,0,"/up_files/room/qqWallet.png") ';

                        if ($vu == 7) {

                            $field = 'qq';

                        } elseif ($vu == 8) {

                            $field = 'qqwap';

                        } elseif ($vu == 9) {

                            $field = 'qqh';

                        }

                    

                    }elseif ($vu == 10 || $vu == 11 || $vu == 12) {   //银联跳转（带银行简码）和银联快捷

                        $name = '银联';

                        $type = 75;

                        $addStr1 .= 'type,bank_id,logo) ';

                        $addStr2 .= '75,0,"/up_files/room/yinlian.png") ';

                        if ($vu == 10) {

                            $field = 'wy';

                        } elseif ($vu == 11) {

                            $field = 'wyh';

                        } elseif ($vu == 12) {

                            $field = 'wykj';

                        }

                    }

                    elseif ($vu == 13 || $vu == 14 || $vu == 15) {   //京东钱包扫码和京东WAP

                        $name = '京东钱包';

                        $type = 215;

                        $addStr1 .= 'type,bank_id,logo) ';

                        $addStr2 .= '215,0,"/up_files/room/jindong.png") ';

                        if ($vu == 13) {

                            $field = 'jd';

                        } elseif ($vu == 14) {

                            $field = 'jdwap';

                        } elseif ($vu == 15) {

                            $field = 'jdh';

                        }

                        

                    }elseif ($vu == 16 || $vu == 17 || $vu == 18) {   //银联钱包扫码和银联钱包WAP

                        $name = '银联钱包';

                        $type = 214;

                        $addStr1 .= 'type,bank_id,logo) ';

                        $addStr2 .= '214,0,"/up_files/room/banklink.png") ';

                        if ($vu == 16) {

                            $field = 'yl';

                        } elseif ($vu == 17) {

                            $field = 'ylwap';

                        }elseif ($vu == 18) {

                            $field = 'ylh';

                        }

                    }

                    else {

                        $this->db->query('ROLLBACK');

                        $arr['code'] = -1;

                        $arr['msg'] = "操作失败，请选择正确的支付方式";



                        echo jsonReturn($arr);

                        return;

                    }

                    

                    $type_data = '';

                    foreach ($pay_data as $ks => $vs) {

                        if ($vs['id'] == $vu) {

                            $type_data = $vs;

                        }

                    }



                    $sql = 'SELECT `config` FROM `un_payment_config` WHERE `type` = ' . $type . ' AND `nid` = "' . $nid . '"';

                    $pay_config = $this->db->getone($sql);

                    $configs = $config;

                    if (!empty($pay_config)) {  //支付配置已经存在

                        if (!empty($type_data)) {

                            $configs['name'] = $configs['name'] . $name;



                            unset($post_data['name']);

                            $pay_config = unserialize($pay_config['config']);

                            //$configs = multiArrayMerge($configs, $post_data['config']);

                            $configs['payType'] = $pay_config['payType'];

                            $configs['payType'][$field] = $type_data;

                            $configs['payType'][$field]['name'] = $config['name'] . $configs['payType'][$field]['name'];

                            if ($field == 'wy' && !empty($bank_id)) {

                                $configs['payType'][$field]['bank_id'] = $bank_id;

                            }



                            $log_data[$configs['name']][] = [

                                'f' => 'save',

                                'type' => $configs['payType'][$field]['name'],

                            ];



                            $post_data['config'] = serialize($configs);

                            

                            $fields = '';

                            foreach ($post_data as $key => $val) {

                                $fields .= $key . " = '" . $val . "',";

                            }

                            $fields = trim($fields, ",");



                            $sql = 'update un_payment_config set ' . $fields . ' WHERE `type` = ' . $type . ' AND `nid` = "' . $nid . '"';

                            $ret = $this->db->query($sql);

                            

                            if (!$ret) {

                                $this->db->query('ROLLBACK');

                                $arr['code'] = -1;

                                $arr['msg'] = "操作失败";

                            

                                echo jsonReturn($arr);

                                return;

                            }

                        }

                    } else { //插入新支付配置

                        $configs['payType'][$field] = $type_data;

                        $pay_name  = $configs['name'];

                        $configs['name'] = $pay_name . $name;

                        $post_data['name'] = $pay_name . $name;

                        $configs['payType'][$field]['name'] = $pay_name . $configs['payType'][$field]['name'];



                        $log_data[$configs['name']][] = [

                            'f' => 'add',

                            'type' => $configs['payType'][$field]['name'],

                        ];



                        if ($field == 'wy' && !empty($bank_id)) {

                            $configs['payType'][$field]['bank_id'] = $bank_id;

                        }

                        

                        $post_data['config'] = serialize($configs);

                        

                        $addSql1 = "INSERT INTO `un_payment_config` (";

                        $addSql2 = " VALUES (";

                        foreach ($post_data as $ky => $vl) {

                            $addSql1 .= $ky . ',';

                            $addSql2 .= "'" . $vl . "',";

                        }

                        

                        $addSql = $addSql1 . $addStr1 . $addSql2 . $addStr2;

                        $ret = $this->db->query($addSql);



                        if (!$ret) {

                            $this->db->query('ROLLBACK');

                            $arr['code'] = -1;

                            $arr['msg'] = "操作失败";

                            

                            echo jsonReturn($arr);

                            return;

                        }

                    }

                }

            }



            $log_remarks = '添加第三方支付:';

            foreach($log_data as $k=>$log) {

                $log_remarks .= $k.':'.implode(';', array_column($log, 'type')).'--';

            }

            $log_remarks .= '状态:'.$canuseZh;

            admin_operation_log($this->admin['userid'], 130, $log_remarks);



            $this->db->query('COMMIT');

            $arr['code'] = 0;

            $arr['msg'] = "操作成功";

            $this->refreshRedis('paymentConfig', 'all'); //刷新缓存

        } catch (Exception $e) {

            $this->db->query('ROLLBACK');

            $arr['code'] = -1;

            $arr['msg'] = "操作失败";

        }



        echo jsonReturn($arr);

    }

    /*

    //添加新第三方线上支付列表

    public function editThird()

    {

        $config = [];

        $data   = [];

        $pay_data = [];

        $bankData = [];

        $pay_name = '';

        $post_data = $_POST;

        $id = $post_data['id'];

        unset($post_data['id']);

        $pay_type = $post_data['pay_type'];

        unset($post_data['pay_type']);

    

        $nid = $post_data['nid'];

    

        //可支付银行是否为空

        if (!empty($post_data['pay_banks'])) {

            $pay_banks = $post_data['pay_banks'];

            $pay_banks = implode(',', array_unique(explode(',',$pay_banks)));

            $bankData = $pay_banks;

            //$configs['payType']['wy']['bank_id'] = $pay_banks;

        }

    

        if (isset($post_data['pay_banks'])) {

            unset($post_data['pay_banks']);

        }

    

        $this->db->query(BEGIN);

        try {

            //获取config配置

            $jsonData = file_get_contents(S_CORE . 'class' . DS . 'pay'  . DS . 'pay.json');

            $nidData = json_decode($jsonData,true);

    

            foreach ($nidData as $key => $value) {

                if ($nid == $value['nid']) {

                    $config = $post_data['config'];

                    $config['name'] = $value['name'];

                    unset($post_data['config']);

                    $pay_data = $value['payType'];

                    //$post_data['config']['payType'] = $value['payType'];

                    //$post_data['config']['name'] = $value['name'];

                    $post_data['name']  = $value['name'];

    

                    $post_data['config'] = serialize($post_data['config']);

                    if (!empty($value['payBank'])) {

                        $sql = 'SELECT * FROM `un_bank_info`';

                        $bank_info = $this->db->getone($sql);

                        $arr_feild = array_keys($bank_info);

    

                        if (!in_array($nid, $arr_feild)) {

                            $create_feild_sql = 'alter table `un_bank_info` add column `' . $nid . '` varchar(20) NOT NULL default "" after `bank_code`';

                            $ret1 = $this->db->query($create_feild_sql);

    

                            if (!$ret1) {

                                $this->db->query('ROLLBACK');

                                $arr['code'] = -1;

                                $arr['msg'] = "操作失败";

    

                                echo jsonReturn($arr);

                                return;

                            }

                        }

    

                        foreach ($value['payBank'] as $k => $v) {

                            $sql = 'update `un_bank_info` set `' . $nid . '` = "' . $v['code'] . '" where `id` = ' . $v['bank_id'];

                            $this->db->query($sql);

                        }

                    }

    

                    break;

                }

            }

    

            if (empty($pay_data)) {

                $this->db->query('ROLLBACK');

                $arr['code'] = -1;

                $arr['msg'] = "操作失败,无相关支付类型配置（pay.json）";

    

                echo jsonReturn($arr);

                return;

            }

    

            $pay_type = explode(',',$pay_type);

            if (!empty($pay_type)) {

                foreach ($pay_type as $ke => $vu) {

                    $type    = 0;

                    $field   = '';

                    $name    = '';

                    $addStr1 = '';

                    $addStr2 = '';

    

                    if ($vu == 1 || $vu == 2 || $vu == 3) {         //微信扫码和微信WAP

                        $name = '微信';

                        $type = 67;

                        $addStr1 .= 'type,bank_id,logo) ';

                        $addStr2 .= '67,0,"/up_files/room/weixin.png") ';

                        if ($vu == 1) {

                            $field = 'wx';

                        } elseif ($vu == 2) {

                            $field = 'wxwap';

                        } elseif($vu == 3) {

                            $field = 'wxh';

                        }

    

                    } elseif ($vu == 4 || $vu == 5 || $vu == 6) {   //支付宝扫码和支付WAP

                        $name = '支付宝';

                        $type = 68;

                        $addStr1 .= 'type,bank_id,logo) ';

                        $addStr2 .= '68,0,"/up_files/room/zhifubao.png") ';

                        if ($vu == 4) {

                            $field = 'ali';

                        } elseif ($vu == 5) {

                            $field = 'aliwap';

                        } elseif ($vu == 6) {

                            $field = 'alih';

                        }

    

                    } elseif ($vu == 7 || $vu == 8 || $vu == 9) {   //QQ钱包扫码和QQ钱包WAP

                        $name = 'QQ钱包';

                        $type = 139;

                        $addStr1 .= 'type,bank_id,logo) ';

                        $addStr2 .= '139,0,"/up_files/room/qqWallet.png") ';

                        if ($vu == 7) {

                            $field = 'qq';

                        } elseif ($vu == 8) {

                            $field = 'qqwap';

                        } elseif ($vu == 9) {

                            $field = 'qqh';

                        }

    

                    }elseif ($vu == 10 || $vu == 11 || $vu == 12) {   //银联跳转（带银行简码）和银联快捷

                        $name = '银联';

                        $type = 75;

                        $addStr1 .= 'type,bank_id,logo) ';

                        $addStr2 .= '75,0,"/up_files/room/yinlian.png") ';

                        if ($vu == 10) {

                            $field = 'wy';

                        } elseif ($vu == 11) {

                            $field = 'wyh';

                        } elseif ($vu == 12) {

                            $field = 'wykj';

                        }

                    }

                   elseif ($vu == 13 || $vu == 14 || $vu == 15) {   //京东钱包扫码和京东WAP

                     $name = '京东钱包';

                     $type = 0;

                     $addStr1 .= 'type,bank_id,logo) ';

                     $addStr2 .= '0,0,"/up_files/room/weixin.png") ';

                     if ($vu == 13) {

                     $field = 'jd';

                     } elseif ($vu == 14) {

                     $field = 'jdwap';

                     } elseif ($vu == 15) {

                     $field = 'jdh';

                     }

    

                     }elseif ($vu == 16 || $vu == 17 || $vu == 18) {   //银联钱包扫码和银联钱包WAP

                     $name = '银联钱包';

                     $type = 0;

                     $addStr1 .= 'type,bank_id,logo) ';

                     $addStr2 .= '0,0,"/up_files/room/weixin.png") ';

                     if ($vu == 16) {

                     $field = 'yl';

                     } elseif ($vu == 17) {

                     $field = 'ylwap';

                     }elseif ($vu == 18) {

                     $field = 'ylh';

                     }

                     } 

                    else {

                        $this->db->query('ROLLBACK');

                        $arr['code'] = -1;

                        $arr['msg'] = "操作失败，请选择正确的支付方式";

    

                        echo jsonReturn($arr);

                        return;

                    }

    

                    $type_data = '';

                    foreach ($pay_data as $ks => $vs) {

                        if ($vs['id'] == $vu) {

                            $type_data = $vs;

                        }

                    }

                    $sql = 'SELECT `config` FROM `un_payment_config` WHERE `type` = ' . $type . ' AND `nid` = "' . $nid . '"';

                    $pay_config = $this->db->getone($sql);

                    $configs = $config;

                    if (!empty($pay_config)) {

                        if (!empty($type_data)) {

                            $configs['name'] = $configs['name'] . $name;

                            unset($post_data['name']);

                            $pay_config = unserialize($pay_config['config']);

                            $configs = multiArrayMerge($configs, $post_data['config']);

                            $configs['payType'] = $pay_config['payType'];

                            $configs['payType'][$field] = $type_data;

                            $configs['payType'][$field]['name'] = $config['name'] . $configs['payType'][$field]['name'];

                            $post_data['config'] = serialize($configs);

    

                            $fields = '';

                            foreach ($post_data as $key => $val) {

                                $fields .= $key . " = '" . $val . "',";

                            }

                            $fields = trim($fields, ",");

    

                            $sql = 'update un_payment_config set ' . $fields . ' WHERE `type` = ' . $type . ' AND `nid` = "' . $nid . '"';

                            $ret = $this->db->query($sql);

    

                            if (!$ret) {

                                $this->db->query('ROLLBACK');

                                $arr['code'] = -1;

                                $arr['msg'] = "操作失败";

    

                                echo jsonReturn($arr);

                                return;

                            }

                        }

                    } else {

    

                        $configs['payType'][$field] = $type_data;

                        $pay_name  = $configs['name'];

                        $configs['name'] = $pay_name . $name;

                        $post_data['name'] = $pay_name . $name;

                        $configs['payType'][$field]['name'] = $pay_name . $configs['payType'][$field]['name'];

                        $post_data['config'] = serialize($configs);

    

                        $addSql1 = "INSERT INTO `un_payment_config` (";

                        $addSql2 = " VALUES (";

                        foreach ($post_data as $ky => $vl) {

                            $addSql1 .= $ky . ',';

                            $addSql2 .= "'" . $vl . "',";

                        }

    

                        $addSql = $addSql1 . $addStr1 . $addSql2 . $addStr2;

                        $ret = $this->db->query($addSql);

    

                        if (!$ret) {

                            $this->db->query('ROLLBACK');

                            $arr['code'] = -1;

                            $arr['msg'] = "操作失败";

    

                            echo jsonReturn($arr);

                            return;

                        }

                    }

                }

            }

    

            $this->db->query('COMMIT');

            $arr['code'] = 0;

            $arr['msg'] = "操作成功";

            $this->refreshRedis('paymentConfig', 'all'); //刷新缓存

        } catch (Exception $e) {

            $this->db->query('ROLLBACK');

            $arr['code'] = -1;

            $arr['msg'] = "操作失败";

        }

    

        echo jsonReturn($arr);

    }

    */



    //编辑新第三方支付

    public function editThirdPayment()

    {

        $type_name = [];

        $id = $_GET['id'];



        $sql = "select * from un_payment_config where id = {$id}";

        $data = $this->db->getone($sql);

        $data['config'] = unserialize($data['config']);

        $arr_user_group = explode(',', $data['user_group']);

        

        $type = [];

        if ($data['type'] == 67) {

            $type = ['wx', 'wxwap', 'wxh'];

        }elseif ($data['type'] == 68) {

            $type = ['ali', 'aliwap', 'alih'];

        }elseif ($data['type'] == 75) {

            $type = ['wy', 'wykj', 'wyh'];

        }elseif ($data['type'] == 139) {

            $type = ['qq', 'qqwap', 'qqh'];

        }elseif ($data['type'] == 214) {

            $type = ['yl', 'ylwap', 'ylh'];

        } elseif ($data['type'] == 215) {

            $type = ['jd', 'jdwap', 'jdh']; 

        }



        

        if (empty($data['config']['payType'])) {

            $type_name = [];

        } else {

            foreach ($data['config']['payType'] as $ck => $cv) {

                //兼容上一版本

                if (in_array($ck, $type)) {

                    $type_name[$ck] = $cv['name']; 

                }

            }

        }



        if ($data['type'] == 75) {

            //判断银行列表中有没有该字段

            $bank_field = $this->db->result("DESCRIBE `un_bank_info` '" . $data['nid'] . "'");

            if(!empty($bank_field)) {

                $bank_info = $this->db->getall("select `id`, `name` from `un_bank_info` where status = 1 and " . $data['nid'] . ' != ""');

                if (isset($data['config']['payType']['wy']['bank_id'])) {

                    $pay_bank = explode(',',$data['config']['payType']['wy']['bank_id']);

                }

            }

        }



        $payArrs = explode(',',$data['pay_layers']);

        $layerArr = $this->userModel->getUserLayer(); //所有的层级信息 取redis

        

        $sql = "select `id`, `name` from `un_user_group`";

        $user_group = $this->db->getall($sql);

        

        include template('editthirdpayment');

    }

    

    //修改新第三方支付信息

    public function updateThirdPayment()

    {

        $data = $_POST;

        $id = $data['id'];

        unset($data['id']);



        //获取config配置

        $paymentInfo = D('paymentconfig')->getOneCoupon('type,config', array('id' => $id));

        $config = unserialize($paymentInfo['config']);

        //修改config配置信息（数组键值覆盖）

        $configs = multiArrayMerge($config, $data['config']);

        

        if (!empty($data['type_name'])) {

            foreach ($configs['payType'] as $k => $v) {

                if (!empty($data['type_name'][$k])) {

                    $configs['payType'][$k]['name'] = $data['type_name'][$k];

                }

            }

        }

        unset($data['type_name']);

        

        //可支付银行是否为空

        if ($paymentInfo['type'] == 75 && !empty($data['pay_banks'])) {

            $pay_banks = $data['pay_banks'];

            $pay_banks = implode(',', array_unique(explode(',',$pay_banks)));

            $configs['payType']['wy']['bank_id'] = $pay_banks;

        }

        

        if (isset($data['pay_banks'])) {

            unset($data['pay_banks']);

        }

        

        $data['config'] = serialize($configs);



        $field = '';

        foreach ($data as $key => $val) {

            $field.= $key . " = '" . $val . "',";

        }

        $field = trim($field, ",");



        $sql = 'update un_payment_config set ' . $field . ' where id = ' . $id . ';';

        $rows = $this->db->query($sql);

        if ($rows === true) {

            $arr['code'] = 0;

            $arr['msg'] = "操作成功";

            $this->refreshRedis('paymentConfig', 'all'); //刷新缓存

        } else {

            $arr['code'] = -1;

            $arr['msg'] = "操作失败";

        }



        echo jsonReturn($arr);

    }

    

    //新第三方线上支付银行列表

    public function thirdBankList()

    {

        $table_name = [];

        $pay_name   = [];

        

        $jsonData = file_get_contents(S_CORE . 'class' . DS . 'pay'  . DS . 'pay.json');

        $nidData = json_decode($jsonData,true);

        

        foreach ($nidData as $key => $value) {

            $pay_name[$value['nid']] = $value['name'];

        }



        $sql = "select * from un_bank_info order by `id` asc";

        $data = $this->db->getall($sql);

        

        foreach ($data[0] as $k => $v) {

            if ($k == 'id') $table_name['id'] = '序号';

            elseif ($k == 'name') $table_name['name'] = '银行名称';

            elseif ($k == 'bank_info') $table_name['bank_info'] = '银行信息';

            elseif ($k == 'bank_code') $table_name['bank_code'] = '银行简码';

            elseif ($k == 'sort') $table_name['sort'] = '银行排序';

            elseif ($k == 'status') $table_name['status'] = '状态';

            else {

                if (isset($pay_name[$k])) {

                    $table_name['code'][] = $pay_name[$k] . '简码'; 

                }

            }

            

        }



        include template('thirdBankList');

    }

    

    //编辑新第三方支付银行信息

    public function editThirdBank()

    {

        $id = $_GET['id'];

        $code_name = [];

        $pay_name   = [];

        

        $jsonData = file_get_contents(S_CORE . 'class' . DS . 'pay'  . DS . 'pay.json');

        $nidData = json_decode($jsonData,true);

        

        foreach ($nidData as $key => $value) {

            $pay_name[$value['nid']] = $value['name'];

        }

        

        $sql = "select * from un_bank_info where id = {$id}";

        $data = $this->db->getone($sql);

        

        if (empty($data)) {

            return '银行不存在！';

        }

        

        foreach ($data as $k => $v) {

            if (isset($pay_name[$k])) {

                $code_name[$k] = $pay_name[$k] . '简码';

            }

        }



        include template('editThirdBank');

    }

    

    //新第三方线上支付银行,启用与禁用操作

    public function modifyBankType()

    {

        $status = $_POST['status'];

        $id = $_POST['id'];

        $sql = 'update un_bank_info set status = ' . $status . '  where id = ' . $id . ';';

        $rows = $this->db->query($sql);

        if ($rows === true) {

            $arr['code'] = 0;

            $arr['msg'] = "操作成功";

        } else {

            $arr['code'] = -1;

            $arr['msg'] = "操作失败";

        }

        echo jsonReturn($arr);

    }

    

    //修改新第三方支付信息

    public function updateThirdBank()

    {

        $data = $_POST;

        $id = $data['id'];

        unset($data['id']);

        

        if (!empty($data['name'])) {

            unset($data['name']);   //银行名称禁止修改

        }

        

        if (!empty($data['bank_code'])) {

            unset($data['bank_code']);   //银行标准简码禁止修改

        }

    

        $field = '';

        foreach ($data as $key => $val) {

            $field.= $key . " = '" . $val . "',";

        }

        $field = trim($field, ",");

    

        $sql = 'update un_bank_info set ' . $field . ' where id = ' . $id . ';';

        $rows = $this->db->query($sql);

        if ($rows === true) {

            $arr['code'] = 0;

            $arr['msg'] = "操作成功";

            $this->refreshRedis('paymentConfig', 'all'); //刷新缓存

        } else {

            $arr['code'] = -1;

            $arr['msg'] = "操作失败";

        }

    

        echo jsonReturn($arr);

    }

    

    //证书文件上传

    public function uploadPaymentCert()

    {

        $data['code'] = 0;

        $data['msg'] = '';

        $nid = isset($_POST['nid'])? $_POST['nid'] : '';

        $merchantCert = $_FILES["merchantCert"];

        $platformCert = $_FILES["platformCert"];

        

        //上传商户私钥证书

        if (!empty($merchantCert['name']) && $merchantCert["error"] == 0) {

            $arrMerchantCertName = explode('.',$merchantCert['name']);

            

            if (end($arrMerchantCertName) == 'pfx' || end($arrMerchantCertName) == 'pem') {

                if ($platformCert["size"] > 600000) {

                    $data['msg'] = '用户私钥证书太大';

                }else { 

                    $path = 'up_files/certs';



                    if(!is_dir(S_ROOT . $path)) {

                        mkdir($path,0777,true);

                    }

                    

                    $merchantCertPath = $path . '/' . $nid . '_merchant_cert.' . end($arrMerchantCertName);

                    if (move_uploaded_file($merchantCert["tmp_name"], S_ROOT . $merchantCertPath)) {

                       $data['code'] = 1;

                       $data['msg']  = '用户私钥证书上传成功！';

                       $data['data']['path'] = '/' . $merchantCertPath; 

                    }else {

                        $data['msg'] = '用户私钥证书上传错误';

                    }

                }

            }else {

                $data['msg'] = '用户私钥证书格式错误';

            }

        }

        

        //上传平台公钥证书

        if (!empty($platformCert['name']) && $platformCert["error"] == 0) {

            $arrPlatformCertName = explode('.',$platformCert['name']);

            

            if (end($arrPlatformCertName) == 'cer' || end($arrPlatformCertName) == 'pem') {

                if ($platformCert["size"] > 600000) {

                    $data['msg'] = '平台公钥证书太大';

                }else {

                    $path = 'up_files/certs';

                    

                    if(!is_dir(S_ROOT . $path)) {

                        mkdir($path,0777,true);

                    }

                    $platformCertPath = $path . '/' . $nid . '_server_cert.' . end($arrPlatformCertName);

                    

                    if (move_uploaded_file($platformCert["tmp_name"], S_ROOT . $platformCertPath)) {

                        $data['code'] = 1;

                        $data['msg']  = '平台公钥证书上传成功！';

                        $data['data']['path'] = '/' . $platformCertPath;

                    }else {

                        $data['msg'] = '平台公钥证书上传错误';

                    }

                }

            }else {

                $data['msg'] = '平台公钥证书格式错误';

            }

        }



        echo jsonReturn($data);

    }

    

    public function modifyType() {

        $canuse = $_POST['canuse'];

        $id = $_POST['id'];

        $infos = $this->db->getone("select * from un_payment_config where id = $id");

        if(!$infos) {

            echo jsonReturn(['code' => -1, 'msg' => '支付方式不存在']);

        }



        $sql = 'update un_payment_config set canuse = ' . $canuse . '  where id = ' . $id . ';';

        $rows = $this->db->query($sql);

        if ($rows === true) {

            $staZh = [0 => '停用', 1 => '启用'];

            $log_remarks = "支付设置:".$staZh[$canuse].$infos['name'];

            admin_operation_log($this->admin['userid'], 130, $log_remarks);

            $arr['code'] = 0;

            $arr['msg'] = "操作成功";

        } else {

            $arr['code'] = -1;

            $arr['msg'] = "操作失败";

        }

        echo jsonReturn($arr);

    }



    public function modifyWithdrawType() {

        $canuse = $_POST['canuse'];

        $id = $_POST['id'];

        $infos = $this->db->getone("select * from un_payment_config where id = $id");

        if(!$infos) {

            echo jsonReturn(['code' => -1, 'msg' => '支付方式不存在']);

        }

//        dump($canuse);

        if ($canuse == 1) {

            $sql = "select id from un_payment_config where type = 302 and canuse = 1";

            $result = $this->db->getone($sql);

//            dump($result);

            if ($result['id']) {

                $arr['code'] = -1;

                $arr['msg'] = "请关闭其它代付方式";

                echo jsonReturn($arr); exit;

            }

        }

        $sql = 'update un_payment_config set canuse = ' . $canuse . '  where id = ' . $id . ';';

        $rows = $this->db->query($sql);

        if ($rows === true) {

            $staZh = [0 => '停用', 1 => '启用'];

            $log_remarks = "代付付设置:".$staZh[$canuse].$infos['name'];

            admin_operation_log($this->admin['userid'], 130, $log_remarks);

            $arr['code'] = 0;

            $arr['msg'] = "操作成功";

        } else {

            $arr['code'] = -1;

            $arr['msg'] = "操作失败";

        }

        echo jsonReturn($arr);

    }

    

    //修改线上支付类型状态

    public function modifyPayStatus()

    {

        $pay_type = '';

        $type = $_REQUEST['type']; //type，1:扫码或网银，2：wap或网银快捷，3：H5

        $payment_id = $_REQUEST['payment_id'];

        $status = $_REQUEST['status'];

        

        if ($status != 1 && $status != 2 || empty($payment_id)) {

            $arr['code'] = -1;

            $arr['msg'] = "操作失败";



            echo jsonReturn($arr);

            return;

        }

        

        $sql = "select id, type, name, config from un_payment_config where  `id` = " . $payment_id;

        $data = $this->db->getone($sql);

        

        if ($type == 1) {

            if ($data['type'] == 67) {

                $pay_type = 'wx';

            } elseif ($data['type'] == 68) {

                $pay_type = 'ali';

            } elseif ($data['type'] == 75) {

                $pay_type = 'wy';

            } elseif ($data['type'] == 139) {

                $pay_type = 'qq';

            } elseif ($data['type'] == 214) {

                $pay_type = 'yl';

            } elseif ($data['type'] == 215) {

                $pay_type = 'jd';

            }

        } elseif ($type == 2) {

            if ($data['type'] == 67) {

               $pay_type = 'wxwap';

            } elseif ($data['type'] == 68) {

                $pay_type = 'aliwap';

            } elseif ($data['type'] == 75) {

                $pay_type = 'wykj';

            } elseif ($data['type'] == 139) {

                $pay_type = 'qqwap';

            } elseif ($data['type'] == 214) {

                $pay_type = 'wy';

            } elseif ($data['type'] == 215) {

                $pay_type = 'qq';

            }

        } elseif ($type == 3) {

            if ($data['type'] == 67) {

                $pay_type = 'wxh';

            } elseif ($data['type'] == 68) {

                $pay_type = 'alih';

            } elseif ($data['type'] == 75) {

                $pay_type = 'wyh';

            } elseif ($data['type'] == 139) {

                $pay_type = 'qqh';

            } elseif ($data['type'] == 214) {

                $pay_type = 'yl';

            } elseif ($data['type'] == 215) {

                $pay_type = 'jd';

            }

        }



        $config = unserialize($data['config']);

        

        if (!empty($pay_type) && !empty($config['payType'][$pay_type])) {

            $config['payType'][$pay_type]['payStatus'] = $status;

            

            $configs = serialize($config);

            

            $sql = "update un_payment_config set config = '" . $configs . "'  where id = " . $payment_id;



            $rows = $this->db->query($sql);

            if ($rows) {

                $typeArr = [1 => '扫码||网银', 2 => 'wap||网银快捷', 3=>'H5'];

                $staZh = [1 => '启用', 2 => '停用'];

                $log_remarks = "支付方式设置:".$data['name'].'--'.$staZh[$status].'('.$typeArr[$type].')';

                admin_operation_log($this->admin['userid'], 130, $log_remarks, $payment_id);



                $arr['code'] = 0;

                $arr['msg'] = "操作成功";

            } else {

                $arr['code'] = -1;

                $arr['msg'] = "操作失败";

            }

        } else {

            $arr['code'] = -1;

            $arr['msg'] = "操作失败";

        }



        echo jsonReturn($arr);

    }



    public function stopSellSet() {

        $data = $_POST;

        if (!empty($data)) {

            $this->model->setAdminUser($this->admin);

            $res = $this->model->stopSellSet($data);

            $this->refreshRedis('config', 'all');

            echo jsonReturn($res);

        }else{

            $redis  = initCacheRedis();

            $lotterySet[1]  = decode($redis->hGet('Config:xy28_stop_or_sell','value'));

            $lotterySet[2]  = decode($redis->hGet('Config:bjpk10_stop_or_sell','value'));

            $lotterySet[3]  = decode($redis->hGet('Config:jnd28_stop_or_sell','value'));

            $lotterySet[4]  = decode($redis->hGet('Config:xyft_stop_or_sell','value'));

            $lotterySet[5]  = decode($redis->hGet('Config:cqssc_stop_or_sell','value'));

            $lotterySet[6]  = decode($redis->hGet('Config:sfc_stop_or_sell','value'));

            $lotterySet[7]  = decode($redis->hGet('Config:lhc_stop_or_sell','value'));

            $lotterySet[8]  = decode($redis->hGet('Config:jslhc_stop_or_sell','value'));

            $lotterySet[9]  = decode($redis->hGet('Config:jssc_stop_or_sell','value'));

            $lotterySet[10] = decode($redis->hGet('Config:nn_stop_or_sell','value'));

            $lotterySet[11] = decode($redis->hGet('Config:ffc_stop_or_sell','value'));

            $lotterySet[12] = decode($redis->hGet('Config:sjb_stop_or_sell','value'));

            $lotterySet[13] = decode($redis->hGet('Config:tb_stop_or_sell','value'));

            $lotterySet[14] = decode($redis->hGet('Config:ffpk10_stop_or_sell','value'));

            $lotterySet[1]['name'] = '幸运28';

            $lotterySet[2]['name'] = '北京PK10';

            $lotterySet[3]['name'] = '加拿大28';

            $lotterySet[4]['name'] = '幸运飞艇';

            $lotterySet[5]['name'] = '重庆时时彩';

            $lotterySet[6]['name'] = '三分彩';

            $lotterySet[7]['name'] = '六合彩';

            $lotterySet[8]['name'] = '急速六合彩';

            $lotterySet[9]['name'] = '急速赛车';

            $lotterySet[10]['name'] = '牛牛';

            $lotterySet[11]['name'] = '分分彩';

            $lotterySet[12]['name'] = '世界杯专场';

            $lotterySet[13]['name'] = '欢乐骰宝';

            $lotterySet[14]['name'] = '分分PK10';

            deinitCacheRedis($redis);

        }



        include template('stopSellSet');

    }

    

    public function editStopSellSet()

    {

        $lottery_id = $_REQUEST['id'];



        $lottery_type = [

            ['彩种配置nid'],

            ['xy28_stop_or_sell','幸运28'],

            ['bjpk10_stop_or_sell','北京PK10'],

            ['jnd28_stop_or_sell','加拿大28'],

            ['xyft_stop_or_sell','幸运飞艇'],

            ['cqssc_stop_or_sell','重庆时时彩'],

            ['sfc_stop_or_sell','三分彩'],

            ['lhc_stop_or_sell','六合彩'],

            ['jslhc_stop_or_sell','急速六合彩'],

            ['jssc_stop_or_sell','急速赛车'],

            ['nn_stop_or_sell','牛牛'],

            ['ffc_stop_or_sell','分分彩'],

            ['sjb_stop_or_sell','世界杯专场'],

            ['tb_stop_or_sell','欢乐骰宝'],

            ['ffpk10_stop_or_sell','分分PK10'],

        ];



        $redis  = initCacheRedis();

        $stopSet = decode($redis->hGet('Config:' . $lottery_type[$lottery_id][0],'value'));

        deinitCacheRedis($redis);

        include template('edit-stop-sell-set');

    }

    

    public function updateStopSellSet()

    {

        $data = $_REQUEST;

        unset($data['m']);

        unset($data['a']);

        unset($data['c']);



        $this->model->setAdminUser($this->admin);

        $res = $this->model->stopSellSet($data);

        

        $this->refreshRedis('config', 'all');

        

        echo jsonReturn($res);

    }

    

    public function updateStopSellStatus()

    {

        $data = $_REQUEST;

    

        $res = $this->model->setStopSellStatus($data);

    

        $this->refreshRedis('config', 'all');

    

        echo jsonReturn($res);

    }

    //机器人列表

    public function dummyList()

    {



        $where['username']=$_REQUEST['username'];

        $list = $this->model->getDummyList(0,0,$_REQUEST);

        $pageSize = 20;

        $count = count($list);

        $page = new page($count, $pageSize,"",$_REQUEST);

        $show = $page->show();

        $data = $this->model->getDummyList($page->offer,$pageSize,$_REQUEST);



        include template('dummyList');

    }





    //假人配置列表

    public function dummyConfList(){

        $p=!empty($_REQUEST['page'])?$_REQUEST['page']:1;

        $roomList = $this->db->getall("select id,title,low_yb,max_yb from un_room where passwd = ''");

        $count = $this->model->getDummyConfCount();//获取机器人配置列表

        $pageSize = 20;

        //$count = count($list);

        $page = new page($count, $pageSize,"",$_REQUEST);

        $show = $page->show();

        $data = $this->model->getDummyConfList($page->offer,$pageSize);





        //实例化redis

        $redis = initCacheRedis();



        foreach($data as $key=>$value) {

            $config = json_decode($value['value'],true);//获取房间配置信息

            unset($data[$key]['value']);

            $data[$key]['time'] = $config['startTime'].":00－".$config['endTime'].":00";//时间段

            $data[$key]['conut'] = count($config['ids']);//人数

            $data[$key]['num'] = $config['num'];//每人投注数

            $data[$key]['money'] = $config['money'];//投注金额

            $data[$key]['lottery_type'] = $config['lottery_type'];//彩种

            foreach($roomList as $val)

            {

                if($val['id'] == $config['room'])

                {

                    $data[$key]['title'] = $val['title'];//房间

                }

            }

            $list = [];

            //根据投注方式以及下注金额方式进行数据整理

            if($config['num']['type'] == 1){

                $list = $this->db->getall("select u.username,u.nickname,u.id from un_role r left join un_user u on r.user_id = u.id where r.conf_id = {$value['id']}");

                if($config['money']['type'] == 1){

                    foreach ($list as $k=>$v){

                        $list[$k]['num'] = $config['num']['data']/count($config['ids']);

                        $list[$k]['money'] = $config['money']['data']['start_money']."-".$config['money']['data']['end_money'];

                        $temp_id = $list[$k]['id'];

                        $temp_balance = $this->db->getone("SELECT money FROM un_account WHERE user_id = '$temp_id'");

                        $list[$k]['balance'] = $temp_balance["money"];

                    }

                } else {

                    foreach ($list as $k=>$v){

                        $list[$k]['num'] = $config['num']['data']/count($config['ids']);

                        $temp_id = $list[$k]['id'];

                        $temp_balance = $this->db->getone("SELECT money FROM un_account WHERE user_id = '$temp_id'");

                        $list[$k]['balance'] = $temp_balance["money"];

                        foreach ($config['money']['data'] as $vv){

                            if($vv['id'] == $v['id']){

                                $list[$k]['money'] = $vv['money_start']."-".$vv['money_end'];

                            }

                        }

                    }

                }

            } else if($config['num']['type'] == 2){

                if($config['money']['type'] == 1){

                    $list = $this->db->getall("select u.username,u.nickname,u.id from un_role r left join un_user u on r.user_id = u.id where r.conf_id = {$value['id']}");

                    foreach ($list as $k=>$v){

                        $list[$k]['num'] = $config['num']['data'];

                        $list[$k]['money'] = $config['money']['data']['start_money']."-".$config['money']['data']['end_money'];

                        $temp_id = $list[$k]['id'];

                        $temp_balance = $this->db->getone("SELECT money FROM un_account WHERE user_id = '$temp_id'");

                        $list[$k]['balance'] = $temp_balance["money"];

                    }

                } else {

                    foreach ($config['money']['data'] as $vv){

                        $tmp['id'] = $vv['id'];

                        $tmp['username'] = $vv['username'];

                        $tmp['nickname'] = $vv['nickname'];

                        $tmp['num'] = $config['num']['data'];

                        $tmp['money'] = $vv['money_start']."-".$vv['money_end'];

                        $temp_id = $vv['id'];

                        $temp_balance = $this->db->getone("SELECT money FROM un_account WHERE user_id = '$temp_id'");

                        $tmp['balance'] = $temp_balance["money"];

                        $list[] = $tmp;

                    }

                }

            } else if($config['num']['type'] == 3){

                if($config['money']['type'] == 1){

                    foreach ($config['num']['data'] as $vv){

                        $tmp['id'] = $vv['id'];

                        $tmp['username'] = $vv['username'];

                        $tmp['nickname'] = $vv['nickname'];

                        $tmp['num'] = $vv['num'];

                        $tmp['money'] = $config['money']['data']['start_money']."-".$config['money']['data']['end_money'];

                        $temp_id = $vv['id'];

                        $temp_balance = $this->db->getone("SELECT money FROM un_account WHERE user_id = '$temp_id'");

                        $tmp['balance'] = $temp_balance["money"];

                        $list[] = $tmp;

                    }

                } else {

                    foreach ($config['num']['data'] as $kk=>$vv){

                        $tmp['id'] = $vv['id'];

                        $tmp['username'] = $vv['username'];

                        $tmp['nickname'] = $vv['nickname'];

                        $tmp['num'] = $vv['num'];

                        $tmp['money'] = $config['money']['data'][$kk]['money_start']."-".$config['money']['data'][$kk]['money_end'];

                        $temp_id = $vv['id'];

                        $temp_balance = $this->db->getone("SELECT money FROM un_account WHERE user_id = '$temp_id'");

                        $tmp['balance'] = $temp_balance["money"];

                        $list[] = $tmp;

                    }

                }

            }

            $url=url('admin','role',"dummyConfList",array('page'=>$p));

            $data[$key]['list'] = $list;





            //彩种标题

            $lottery_title = $redis->hGet("LotteryType:{$config['lottery_type']}",'name');

            $data[$key]['lottery_title'] = $lottery_title;

        }



        //关闭redis链接

        deinitCacheRedis($redis);

        include template('dummyConfList');

    }



    //手动添加机器人页面

    public function editDummy()

    {

        $uid = $_REQUEST['uid'];

        if(!empty($uid))

        {

            $list = $this->db->getone("select b.money,b.user_id,a.username,a.avatar,a.nickname from un_user a left join un_account b on a.id = b.user_id where a.id = $uid");

        }

        include template('editDummy');

    }

    //自动新增机器人页面

    public function selfAddDummy()

    {

        include template('selfAddDummy');

    }



    public  function sysImg(){

        $sql2 = "SELECT avatar_url FROM un_default_avatar ORDER BY id DESC";

        $list = $this->db->getAll($sql2);

        include template('sysAvatar');

    }



    //编辑机器人操作

    public function modifyDummy()

    {

        $type = $_REQUEST['type'];

        $uid = $_REQUEST['uid'];

        $money = empty($_REQUEST['money']) ? 0 : $_REQUEST['money'] ;

        $nickname = $_REQUEST['nickname'];

        $avatar = $_REQUEST['avatar'];

        if(empty($avatar))

        {

            //添加随机头像

            $avatar = D('Avatar')->fetchRandomPic();

        }

        $typeArr = ['del','update'];

        if(!empty($type) && !empty($uid) && in_array($type,$typeArr))

        {

            $rows = $this->userModel->updateDummy($uid,$type,$nickname,$money,$avatar);

        }

        else

        {

            $rows['code'] = -1;

            $rows['msg'] = "非法请求";

        }

        echo json_encode($rows);

    }



    public function delAllDummy()

    {

        $userInfo = $_REQUEST['userInfo'];

        if(!empty($userInfo)){

            $rows = $this->userModel->delAllDummy($userInfo);

        } else {

            $rows['code'] = -1;

            $rows['msg'] = "非法请求";

        }

        echo json_encode($rows);

    }



    //编辑机器人配置操作

    public function modifyConf()

    {

        $type = $_REQUEST['type'];

        $typeArr = [1,2];

        if(empty($type) || !in_array($type,$typeArr))

        {

            $arr['code'] = -1;

            $arr['msg'] = "非法请求";

            echo json_encode($arr);

            exit;

        }

        if($type == 1)

        {

            $data = $_REQUEST;

            $rows = $this->model->updateDummyConf($data,"state");

        }

        elseif($type == 2)

        {

            $rows = $this->model->delDummyConf($_REQUEST['id']);

        }

        echo json_encode($rows);

    }



    //添加机器人配置页面

    public function setDummy()

    {

        $id = $_GET['id'];

        $roomList = $this->db->getall("select id,title,low_yb,max_yb,lottery_type,lower from un_room where passwd = ''");

        $optDummyNum = 0;

        if(!empty($id))

        {

            $row = $this->db->getone("select value,state from un_person_config where id = {$id}");

            $config = json_decode($row['value'],true);



            $config['status'] = $row['state'];

            foreach($config['ids'] as $val)

            {

                $optDummyNum++;

                $idInfo[]=$val['id'];

            }

            $ids = implode(",",$idInfo);

            unset($config['ids']);

            $config['ids']['room'] = $config['room'];

            $config['ids']['data'] = $this->db->getall("select a.id,a.username,a.nickname,b.money,a.avatar from un_user a left join un_account b on a.id = b.user_id where a.id in(".$ids.")");

        }



        //实例化redis

        $redis = initCacheRedis();

        foreach ($roomList as &$each_info) {

            //彩种标题

            $lottery_title = $redis->hGet("LotteryType:{$each_info['lottery_type']}",'name');

            $each_info['lottery_title'] = $lottery_title;

        }



        //关闭redis链接

        deinitCacheRedis($redis);



        include template('setDummy');

    }

    // 自动添加机器人
    public function selfMotionAddDummy(){

    	$number = trim($_POST['number']);

    	if(is_numeric($number)){

    		for( $v=1; $v<=$number;$v++){
    			//随机账号
    			$username = '';
    			$pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';  
    			for($i=0;$i<8;$i++){   
    			    $username .= $pattern[mt_rand(0,35)];    //生成php随机数   
    			}
					//随机昵称
    			$nicheng_tou=array('Fast','Calm','Drunk','Cool','Stupid','Positive','Cold','Affectionate','Rough','Soft','Lovely','Happy','Loyal','Serious','Mighty','Hanesome','Traditional','Nice','Pretty','Natural','Single','Obedient','Lethargic','Wild','Waiting','Funny','Humorous','Muscular','Breezy','Open-minded','Happy','Hanesome','Beard','Frank','Bluff','Relax','Infatuated','Perfect','Skilled','Boring','Attractive','Plentiful','Prosperous','Full','Hot','Grumpy','Blue','Beautiful','Brave','Forgetful','Old','Ugly','Tyrant','Honest','Excited','Happy','Calm','Anxious','Broad','Lonely','Unique','Crazy','Stylish','Lagging','Funny','Sad','Bold','Laughing','Short','Healthy','Suitable','Dead','Silent','Gentle','Banana','Apple','Carp','Eel','Expansive','Careful','negligent','Careless','Sweet','Cute','Strong','Smart','Domineering','Bright','Silently','Vigorously','Filial','Worried','Hurry','Nervous','kind','Fierce','Scared','Important','Crisis','Joyful','Comforting','Satisfying','Jumping','Sincere','Satisfactory','Wishful','Happy','Squeamish','Helpless','Speachless','Exciting','Angry','Wonderful','Admirable','Enthusiastic','Excited','Vibrating','Virtual','Super','Chilly','Tentative','Sensible','Hesitate','Gloomy','Lonely','Red','White','Modern','Gray','Steady','Pink','Implicit','Opened','Innocent','Hard','Naive','Elongated','Warm-hearted','Calm','Considerable','Windy','Already','Pursuing','Elegant','Graceful','Cheerful','Outgoing','Introverted','refreshing','Literary','Affectionate','Usual','single','Smart','Big','Weak','Thin','Laughing','hopeful','Cool','Ready','Brave','Young','Nagging','Skinny','Ruthless','Inclusive','Agreeable','Expedite','Comfortable','Light','Responsible','Behind','Simple','Modest','Colorful','Ethereal','Cheering','Vivid','Complicated','Kind','Benevolent','Magical','Unreal','Indifferent','injured','Snowy','High','Terrible','Successful','Glimmer','Shy','Slow','Quick','Superior','Clever','Ambiguous','Playful','Faint','Strong','Plain','Deligent','Capable','Agile','Friendly','Clever','Sly','Heavy','Canny','Frugal','Attentive','Lazy','delicious','Conscious','Selfless','Unlimited','Bright','Dark','Real','Reliable','Thick','Sharp','Smooth','Rough','Active','Accomplished','Large','Small','Double','Round','Sad','Worried','Clean');

    			$nicheng_wei=array('car','noodle','cake','bean','peanut','juice','bulb','melon','wolf','pack','eye','fate','spirite','life','steak','ant','bird','fox','zebra','hamburger','monkey','giant','tea','track','mug','bowl','sunglasses','mirror','pancake','moon','moon','star','sesame','beer','rose','uncle','guy','cabel','sun','leaf','celery','wasp','powder','bee','envelope','clothes','coat','skirt','elephant','cat','hen','lamp','sky','silver','moon','rainbow','smile','motorcycle','chestnut','mountain','earth','tree','bulb','brick','building','pool','wing','dragonfly','bull','coffe','viking','pillow','ship','promise','pen','hedgehog','sky','plane','cannon','winter','onion','spring','summer','autumn','ice','aviation','sweater','pea','rice','corn','eye','mouse','sheep','brother','beauty','season','flower','apparel','skirt','water','hair','hill','train','car','song','dance','teacher','professor','box','rice','oatmeal','cup','kettle','gloves','shoes','bicycle','mice','phone','computer','book','miracle','figure','cigarette','sunset','lamp','baby','future','belt','key','heart','tale','petal','skateboard','brush','paint','sister','clerk','power','cookies','BMW','passenger','white','time','stone','diamond','hippo','rhinoceros','cow','grass','drawer','cupboard','past','wind','passer','orange','earphone','ostrich','friend','slim','pencil','pen','coin','hotdog','hero','twin','triple','towel','expect','hope','daytime','night','gate','pants','iron','bell','bench','maple','lotus','tortoise','cactus','shirt','god','brush','morning','mood','jasmine','sand','snail','fighter','pluto','Cheetah','baseball','basketball','music','phone','internet','world','center','fish','chicken','dog','tiger','duck','rain','feather','wing','coat','fire','stocking','bag','pen','wind','necklace','earing','goose','sound','sign','carrot','popsicle','hat','pineapple','egg','perfume','peach','toast','stream','soy','cherry','pigeon','butterfly','popcorn','rabbit','cock','dolphin','journal','panda','pig','slacker','litchi','mirror','cookies','mushroom','squirrel','shrimp','dimple','seaweed','goldfish','grape','wine','bear','ring','boots','dragon','kiwi','egg','gloss','potato','heel');

    			$tou_num=rand(0,(count($nicheng_tou)-1));
    			$wei_num=rand(0,(count($nicheng_wei)-1));

    			$nicheng=$nicheng_tou[$tou_num]." ".$nicheng_wei[$wei_num];

    			//随机头像

    			$username = trim($username);

    			$nickname = trim($nicheng);

    			$money = rand(3000, 99999);

    			$avatar = D('Avatar')->fetchRandomPic();


    			$arr = $this->userModel->addDummy($username,$nickname,$money,$avatar);
    		}

    	}else{

    		$arr['code'] = -1;

    		$arr['msg'] = "输入数字";

    	}


    	echo json_encode($arr);
    }

    //添加机器人以及机器人配置操作

    public function addDummy()

    {

        $type = trim($_REQUEST['type']);

        if($type=="user")

        {

            //添加随机头像

            $random_avatar = D('Avatar')->fetchRandomPic();



            $username = trim($_REQUEST['username']);

            $nickname = trim($_REQUEST['nickname']);

            $money = intval(trim($_REQUEST['money']));

            $avatar = empty($_REQUEST['avatar'])? $random_avatar : trim($_REQUEST['avatar']);

            if(!empty($username) && !empty($money))

            {

                $arr = $this->userModel->addDummy($username,$nickname,$money,$avatar);

            }

            else

            {

                $arr['code'] = -1;

                $arr['msg'] = "参数错误";

            }

        }

        else if($type == "conf")

        {

            $tmp = $_POST;

            if(!empty($type) && !empty($tmp))

            {

                if(empty($tmp['id']))

                {

                    $arr = $this->model->addDummyConf($tmp);

                }

                else

                {

                    $arr = $this->model->updateDummyConf($tmp,'conf');

                }

            }

            else

            {

                $arr['code'] = -1;

                $arr['msg'] = "参数错误";

            }

        }

        echo json_encode($arr);

    }



    //获取可用机器人列表

    public function getDummyList()

    {

        $roomId = $_POST['room_id'];

        if(!empty($roomId))

        {

            $res = D("admin/user")->getDummyListByRoomId($roomId);

            $arr = $res;

        }

        else

        {

            $arr['code'] = -1;

            $arr['msg'] = "服务器错误！！";

            $arr['list'] = "";

        }



        echo json_encode($arr,JSON_UNESCAPED_SLASHES);

    }





    public function uploadFile()

    {

        $file = $_FILES['file'];

        $res = upLodeImg($file);

        echo str_replace('//','',json_encode($res));

    }



    /**

     * 游客名称配置

     */

    public function tourist(){

        if(isset($_POST['tourist'])){

            $sql = "UPDATE `un_config` SET `value`='{$_POST['tourist']}' WHERE (`nid`='tourist')";

            $res = O('model')->db->query($sql);

            if($res){

                $data = array(

                    'msg'=>'设置成功',

                    'state'=> true,

                    'code'=> 1

                );

            }else{

                $data = array(

                    'msg'=>'名称前缀为1-5个(中文,数字,下滑线)字符,不能以下滑线和0开头',

                    'state'=> false,

                    'code'=> -1

                );

            }



            echo json_encode($data);

            $this->refreshRedis('config', 'all');

            return false;

        }

        $sql = "SELECT * FROM `un_config` WHERE `nid` = 'tourist'";

        $res = O('model')->db->getOne($sql);

        include template('role-tourist');

    }



     /**

      * 提示音权限设置

      */

     public function tonePermissions()

     {

         $adminRoleList = $this->db->getall("select roleid,rolename from un_admin_role");

         $redis = initCacheRedis();

         $tonePermissions['value'] = decode($redis->hget('Config:tonePermissions','value'));

         deinitCacheRedis($redis);

         include template('tonePermissions');

     }



     /**

      * 提示音权限设置操作

      */

     public function tonePermissionsAct()

     {

         $groupIdInfo = $_REQUEST['group_id'];

         if(empty($groupIdInfo))

         {

             $arr['code'] = -1;

             $arr['msg'] = "请选择接收用户组";

             jsonReturn($arr);

         }

         $arr = $this->model->tonePermissions($groupIdInfo);

         jsonReturn($arr);

     }



    /**

     * 定时清除僵尸数据（长时间不用的数据）--配置页面

     */

    public function timingDel(){

        $sql='select value from un_config where nid="timingDel"';

        $res=O('model')->db->getOne($sql);

        if($res['value']){

            $data=json_decode($res['value'], true);

        }



        include template('timingDel');

    }

    

    /**

     * 定时清除僵尸数据（长时间不用的数据）--数据库操作

     */

    public function timingDelDb(){

        $sql='select value from un_config where nid="timingDel"';

        $res=O('model')->db->getOne($sql);

        if($res['value']){

            $res=json_decode($res['value'], true);

        }

        

        $data['isopen']=$_REQUEST['isopen'];

        $data['loginDay']=$_REQUEST['loginDay'];

        $data['chongzhi']=$_REQUEST['chongzhi'];

        $data['yue']=$_REQUEST['yue'];

        $data['yinhangka']=$_REQUEST['yinhangka'];

        $data['yueshu']=$_REQUEST['yueshu'];

        $data['ope_time']=$_REQUEST['ope_time'];

        if($res['ope_time']!=$data['ope_time']){

            $data['lasttime']=0;

        }else{

            $data['lasttime']=$res['lasttime'];

        }

        

        $data=json_encode($data);

        $sql="update un_config set value='$data' where nid='timingDel'";

        $res=O('model')->db->query($sql);

        jsonReturn($res);

    }



    /**

     * 在线客服人数

     */

    public function onlineCustomer()

    {

        $list = $this->db->getall("select b.username,a.ip, a.ip_attribution, a.lastvisit,c.rolename,b.lastlogintime from un_session as a left join un_admin as b on a.user_id = b.userid left join un_admin_role as c on c.roleid = b.roleid where a.is_admin = 1");

        include template('onlineCustomer');

    }



    /**

     * 管理员登录日志

     */

    public function adminLoginInfo()

    {

        $where = [];

        $pagesize = 20;

        $numArr = $this->db->getone("select COUNT(*) as num from un_admin_log");

        $page = new pages($numArr['num'], $pagesize, url('', '',''), $where);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;

        $list = $this->db->getall("select a.id,b.username,a.loginip,a.loginip_attribution,a.logintime,c.rolename,a.content from un_admin_log as a left join un_admin as b on a.user_id = b.userid left join un_admin_role as c on c.roleid = b.roleid order by a.id desc limit $limit");

        include template('adminLoginInfo');

    }

    

    /**

     * IP黑名单列表

     */

    public function ipBlacklist() {

        $data = $_REQUEST;

        $arr = array(

            'ip' => $data['ip'],

        );

    

        $role = D('admin/role');

        $count = $role->coutIpBlacklist($arr);

        $pagesize = 20;

        $url = '?m=admin&c=role&a=ipBlacklist';

        $page = new page($count, $pagesize, $url, $arr);

        $show = $page->show();

        $data = $role->ipBlacklist_model($arr, $page->offer, $pagesize);

    

        include template('ipBlacklist');

    }



    /*

     * 编辑添加IP黑名单页面

     */

    public function editIpBlack()

    {

        $list = [];

        $id = $_REQUEST['id'];

        

        if(!empty($id))

        {

            $sql = "select * from un_ipBlacklist where id = {$id}";

            $list = $this->db->getone($sql);

        }

        

        include template('editIpBlack');

    }



    /**

     * 编辑添加IP黑名单操作

     */

    public function ipBlackAct()

    {

        $ip  = empty($_REQUEST['ip']) ? '' : $_REQUEST['ip'];

        $mac = empty($_REQUEST['mac']) ? '' : $_REQUEST['mac'];

        $type = trim($_REQUEST['type']);

        if (empty($ip) && empty($mac) && $type == 1) {

            echo json_encode(['code' => 0, 'msg' => 'ip和mac不能都为空！']);

            return;

        }

        

        $res = $this->model->ipBlackAct($_REQUEST);

        echo json_encode($res);

    }

    /*

     *机器人下注列表

     */

    public function getRobotBetList()

    {

        $conf_id = intval(trim($_REQUEST['id']));

        $username = trim($_REQUEST['username']);

        $search_where="where conf_id = {$conf_id}";

        $search_where1="where conf_id = {$conf_id}";

        if(!empty($username)){

            $search_where .=" and username LIKE '%{$username}%' ";

            $search_where1 .=" and a.username LIKE '%{$username}%' ";

        }

        $pagesize = 20;

        $numArr = $this->db->getone("select COUNT(*) as num from un_bet_list $search_where");

        $page = new page($numArr['num'], $pagesize, url('', '',''), ['id'=>$conf_id,'username'=>$username]);

        $show = $page->show();

        $limit = $page->offer . ',' . $pagesize;

        $sql = "SELECT 

                  a.id, a.user_id, e.name as lottery_name,

                  b.id as bid, b.type as btype,d.title,

                  a.username, a.way, a.bet_money,

                  c.avatar, a.bet_time,a.nickname 

                FROM un_bet_list a 

                left join un_person_config b on a.conf_id = b.id 

                left join un_user c on c.id = a.user_id 

                left join un_room d on d.id = a.room_id 

                left join un_lottery_type e on a.lottery_type = e.id {$search_where1} 

                order by a.bet_time asc limit ".$limit;

        $list = $this->db->getall($sql);

        if($list[0]['btype'] == 1) $t = '机器人';

        if($list[0]['btype'] == 2) $t = '飘窗机器人';

        if($list[0]['btype'] == 3) $t = '假人';

        include template('getRobotBetList');

    }



    /**

     * author: Aho

     * 列表页统计权限查看配置页面

     */

    public function list_total(){

        $adminRoleList = $this->db->getall("select roleid,rolename from un_admin_role");

        $conf = $this->db->result('select value from un_config where nid="list_total_conf"');

        if($conf)

            $conf = explode(',',$conf);

        else $conf = array();



        include template('list-total-conf');

    }



    /**

     * author: Aho

     * 列表页统计权限查看配置操作

     */

    public function list_total_post(){

        $groupIdInfo = $_REQUEST['group_id'];

        if(empty($groupIdInfo))

        {

            $arr['code'] = -1;

            $arr['msg'] = "请选择用户组";

            jsonReturn($arr);

        }

        $data = implode(',',$groupIdInfo);

        if(!$this->db->getone('select value from un_config where nid="list_total_conf"')){

            $res = D('config')->add(['nid'=>'list_total_conf','value'=>$data,'name'=>'列表页统计权限查看配置']);

        }else{

            $res = D('config')->save(['value'=>$data],'nid="list_total_conf"');

        }

        $msg = '操作';

        if($res){

            exit(json_encode(['code'=>1,'msg'=>$msg.'成功~']));

        }else{

            exit(json_encode(['code'=>0,'msg'=>$msg.'失败~']));

        }



    }

    /*

     * 机器人批量价款操作

     */

    public function dummyBatchChargeMoneyAct()

    {

        $userInfo = $_REQUEST['userInfo'];

        $money = $_REQUEST['money'];

        $resources = [];

        if(!empty($userInfo) && !empty($money)){

            $resources = D("admin/user")->dummyBatchChargeMoney($userInfo,$money);

        } else {

            $resources['code'] = -1;

            $resources['msg'] = "缺少必要参数";

        }

        jsonReturn($resources);



    }



    /**

     * 清楚订单数据

     * @return bool|mixed|void

     */

    public function delOrders()

    {

        $map = array();

        $map['start_time'] = strtotime($_REQUEST['start_time']);

        $date = $this->getDaysByMonth($map['start_time']);

        $sql = "DELETE FROM `un_orders` WHERE (`addtime` BETWEEN {$date['start_time']} AND {$date['end_time']})";

        $res = $this->db->query($sql);

        if($res){

            $msg = array(

                'status' => true,

                'msg' => "删除成功!",

                'code' => 0,

            );

            echo json_encode($msg);

        }else{

            $msg = array(

                'status' => false,

                'msg' => "删除失败!",

                'code' => 1,

            );

            echo json_encode($msg);

        }



    }



    /*

     * 获取当月有多少天

     * @param date $unix

     * return array

     */

    public static function getDaysByMonth($unix){

        $month = date('m', $unix);

        $year = date('Y', $unix);

        $nextMonth = (($month+1)>12) ? 1 : ($month+1);

        $year      = ($nextMonth>12) ? ($year+1) : $year;

        $days   = date('d',mktime(0,0,0,$nextMonth,0,$year));

        $sDate = date("Y-m-1 00:00:00",$unix);

        $eDate = date("Y-m-{$days} 23:59:59",$unix);

        return array('start_date'=>$sDate,'end_date'=>$eDate,'start_time'=>strtotime($sDate),'end_time'=>strtotime($eDate),'days'=>$days);

    }



    //启动页列表

    public function startPageList(){

        $model = D("startpage");



        $count = $model->getCount("#@_start_page");

        $pageSize = $this->page_cnt;

        $page = new pages($count, $pageSize, "?m=admin&c=role&a=startPageList");

        $show = $page->show();

        $filed = 's.id,s.start_time,s.end_time,s.state,s.type,s.url,s.img_path,s.add_time,a.username';

        $order = 'order by s.add_time desc';

        $limit = "limit ".$page->offer.",".$pageSize;

        $sql = "select {$filed} from #@_start_page s left join #@_admin a on a.userid = s.add_admin $order $limit";

        $list = $this->db->getall($sql);

        $newTime = time();

        $new_list = [];

        foreach ($list as $key=>$val){

            if ($val['type'] == 1 || $val['type'] == 2) {

                if($val['end_time'] > $newTime && $val['start_time'] < $newTime){

                    $list[$key]['is_effective'] = "开启";

                    $new_list[] = $val;

                } else if($val['end_time'] < $newTime) {

                    $list[$key]['is_effective'] = "无效";

                } else if($val['start_time'] > $newTime && $val['end_time'] > $newTime){

                    $list[$key]['is_effective'] = "未开启";

                }

            } elseif ($val['type'] == 3) {

                $list[$key]['is_effective'] = "开启";

            }

            $list[$key]['img_path'] = str_replace('//','',$list[$key]['img_path']);

        }

        for($i=0;$i<count($new_list);$i++){

            for($j=$i+1;$j<count($new_list);$j++){

                if($new_list[$i]['start_time'] < $new_list[$j]['start_time']){

                    $a = $new_list[$i];

                    $new_list[$i] = $new_list[$j];

                    $new_list[$j] = $a;

                }

            }

        }

        foreach ($list as $key=>$val) {

            if($val['state'] == 1){

                $list[$key]['state'] = "上架";

            } else {

                $list[$key]['state'] = "下架";

            }

            $list[$key]['start_time'] = date("Y-m-d H:i:s",$val['start_time']);

            $list[$key]['end_time'] = date("Y-m-d H:i:s",$val['end_time']);

            $list[$key]['add_time'] = date("Y-m-d H:i:s",$val['add_time']);

            if(!empty($new_list[0])){

                if($new_list[0]['id'] == $val['id']){

                    $list[$key]['is_show'] = "1";

                } else {

                    $list[$key]['is_show'] = "0";

                }

            } else {

                if($val['type'] == 3){

                    $list[$key]['is_show'] = "1";

                } else {

                    $list[$key]['is_show'] = "0";

                }

            }

        }

        include template('startPageList');

    }



    //添加/编辑启动页

    public function startPageEdit(){

        $id = trim($_GET['id']);

        if(!empty($id)){

            $model = D("startpage");

            $list = $model->getOneCouponNew("id,start_time,end_time,state,type,url,img_path", ['id'=>$id], '', $model->table);

            $list['img_path'] = str_replace('//','',$list['img_path']);

            $list['start_time'] = date("Y-m-d H:i:s",$list['start_time']);

            $list['end_time'] = date("Y-m-d H:i:s",$list['end_time']);

        }

        include template('startPageEdit');

    }



    //删除启动页

    public function startPageDel(){

        $id = trim($_POST['id']);

        $model = D("startpage");

        $res = $this->db->delete($model->table,['id'=>$id]);

        if($res > 0){

            $arr['code'] = 0;

            $arr['msg'] = "操作成功";

        } else {

            $arr['code'] = -1;

            $arr['msg'] = "操作失败";

        }

        echo json_encode($arr);

        exit;

    }



    //添加/编辑启动页方法

    public function startPageAct(){



        $model = D("startpage");

        $id = trim($_POST['id']);

        $data = [

            'start_time' => strtotime(trim($_POST["start_time"])),

            'end_time' => strtotime(trim($_POST["end_time"])),

            'state' => trim($_POST["state"]),

            'type' => trim($_POST["type"]),

            'url' => trim($_POST["url"]),

            'img_path' => trim($_POST["img_path"])

        ];

        $res = $model->checkValue($data,$id);

        if($res !== true){

            echo json_encode($res);

            exit;

        }

        if(empty($id)){

            $data['add_time'] = time();

            $data['add_admin'] = Session::get("admin")['userid'];

            $res1 = $this->db->insert($model->table,$data);

        } else {

            $data['update_time'] = time();

            $res1 = $this->db->update($model->table,$data,['id'=>$id]);

        }



        if($res1 > 0 || $res1 !== false){

            $arr["code"] = 0;

            $arr['msg'] = "操作成功";

        } else {

            $arr["code"] = -1;

            $arr['msg'] = "操作失败";

        }

        echo json_encode($arr);

        exit;



    }



    /**

     * 开启机器人操作

     * @author bell <bell.gao@wiselinkcn.com>

     * @copyright 2017-10-30 17:28

     */

    public function testAct(){

        $conf_id = $_REQUEST['id'];

        $state = $_REQUEST['state'];

        $type = $_REQUEST['type'];

        if(empty($conf_id) || !in_array($state,[0,1]) || !in_array($type,[1,2])) {

            $arr['code'] = -1;

            $arr['msg'] = "非法请求";

            echo json_encode($arr);

            exit;

        }

        if($state == 1) {

            $state = 0;

        } else {

            $state = 1;

        }

        $redis = initCacheRedis();

        $redis->lPushx('list',$conf_id.':'.$state.":".$type);

        deinitCacheRedis($redis);

        $this->db->update("un_person_config", ['state' => $state], ['id'=>$conf_id]);

        $arr['code'] = 0;

        $arr['msg'] = "请求已提交，稍后请查看【机器人下注列表】";

        echo json_encode($arr);

    }



    public function batchStartOrStop(){

        $state = $_REQUEST['state'];

        $type = $_REQUEST['type'];

        if(!in_array($state,[0,1]) || !in_array($type,[1,2,3])) {

            $arr['code'] = -1;

            $arr['msg'] = "非法请求";

            echo json_encode($arr);

            exit;

        }

        switch ($state) {

            case 1: //批量开启投注机器人配置

                $sql = "select id,state from #@_person_config where type = {$type} and state = 0";

                break;

            case 0: //批量关闭投注机器人配置

                $sql = "select id,state from #@_person_config where type = {$type} and state = 1";

                break;

            default:

                break;

        }

        $list = $this->db->getall($sql);

        $redis = initCacheRedis();

        $ids = '';

        if (!empty($list)) {

            foreach ($list as $val) {

                $redis->lPush('list',$val['id'].':'.$state.":".$type);

                $ids .= $val['id'].",";

            }

            $update_sql = "update #@_person_config set state=".$state." where id in(".trim($ids,',').") and type = ".$type;

            $this->db->exec($update_sql);

        }

        deinitCacheRedis($redis);

        $arr['code'] = 0;

        $arr['msg'] = "请求已提交，稍后请查看【机器人下注列表】";

        echo json_encode($arr);



    }

    

    /**

     * author: Aho

     * 用户敏感信息权限查看配置页面

     */

    public function show_user_info()

    {

        $arr_show = ['','真实姓名', '微信号', '电话号码', '邮箱', 'QQ'];

        

        $adminRoleList = $this->db->getall("select roleid,rolename,is_show from un_admin_role where roleid > 1");

        foreach ($adminRoleList as $ka => $va) {

            $adminRoleList[$ka]['is_show'] = empty($va['is_show']) ? [] : explode(',', $va['is_show']);

        }



        include template('show_user_info');

    }

    

    /**

     * author: Aho

     * 设置用户敏感信息权限查看配置

     */

    public function set_show_user_info()

    {

        $roleInfo = $_REQUEST;

        if(empty($roleInfo))

        {

            $arr['code'] = -1;

            $arr['msg'] = "提交数据错误！";

            jsonReturn($arr);

        }

        

        $adminRoleList = $this->db->getall("select roleid,rolename,is_show from un_admin_role where roleid > 1");

        

        foreach ($adminRoleList as $ka => $va) {

            if (empty($roleInfo['role_' . $va['roleid']])) {

                $this->db->update('un_admin_role', ['is_show' => ''], ['roleid' => $va['roleid']]);

            } else {

                $this->db->update('un_admin_role', ['is_show' => implode(',', $roleInfo['role_' . $va['roleid']])], ['roleid' => $va['roleid']]);

            }

        }



        exit(json_encode(['code'=>1,'msg'=> '操作成功, 非超级管理员重新登录后生效！']));

    }



    //解绑/生成随机码

    public function unbindDevice()

    {

        $logincode_model = D('Logincode');

        $admin_user_id = intval($_REQUEST['userid']);



        $logincode_model->unbindDevice($admin_user_id);

        echo json_encode([

            'code' => 0,

            'msg' => '解绑/生成随机码成功',

        ]);

        exit;

    }

    

    //解绑/生成随机码

    public function saveRandomCodeSetting(){

        $logincode_model = D('Logincode');

        $random_code_is_open = intval($_REQUEST['random_code_is_open']);



        $logincode_model->saveRandomCodeSetting($random_code_is_open);



        //记录操作日志

        lg('randomCode_save', "操作人:{$this->admin['username']} id:{$this->admin['userid']},操作值:[{$random_code_is_open}]");



        //刷新redis

        $this->refreshRedis('config', 'all');

        echo json_encode([

            'code' => 0,

            'msg' => '保存成功',

        ]);

        exit;

    }



    //官方停售

    public function stopSell(){

        $data = $_POST;

        $list = $this->db->getall("select `id`,`name`,`tip`,`default` from un_lottery_type WHERE id <= 5");



        include template('stopSell');

    }





    public function editStopSell()

    {

        $lottery_id = $_REQUEST['id'];



        $lottery = $this->db->getone("select id,name,tip from un_lottery_type WHERE id = $lottery_id");

        include template('edit-stop-sell');

    }



    public function updateStopSell(){

        $id = $_POST["id"];

        $tip = $_POST["tip"];



        $this->db->update("un_lottery_type",['tip'=>$tip],['id'=>$id]);

        $arr = [];

        $arr['code'] = 0;

        $arr['msg'] = "操作成功";

        echo json_encode($arr);

    }

}

