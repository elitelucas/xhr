<?php

/**

 *  action.php 前台控制基类

 *

 * @lastmodify			2013-08-21   by Chan

 */

ini_set('max_execution_time', '0');

ini_set('memory_limit','1024M');

!defined('IN_SNYNI') && die('Access Denied!');



class Action {



    public $admin;

    public $db;

    protected $tong_ji_start_time;



    public function __construct() {

        $this->tong_ji_start_time = microtime(true);

        define('IN_ADMIN', TRUE); //定义后台入口标识符

        O('session', '', 0);

        Session::start();

        O('form', '', 0);

        $this->db = getconn();

        $this->checkloin();

        $admin = Session::get('admin');

        if(!empty($admin))

        {

            $rows = $this->db->getone("select sessionid from un_session where user_id = {$admin['userid']} and is_admin = 1");

            $this->db->update("un_session",['lastvisit'=>time()],['sessionid'=>$rows['sessionid']]);

            $conf = $this->db->result('select value from un_config where find_in_set('.$admin['roleid'].',value) and nid="list_total_conf"');

            if(!$conf){

                Session::set('style',"display:none");

            }else{

                Session::set('style',"");

            }

        }else{

            Session::set('style',"");

        }



        /*博饼活动定时任务*/

        $activityModel = D('admin/activity');

        $activityModel->checkActivityState(['activity_type'=>1,'state'=>1]);

        $activityModel->checkActivityState(['activity_type'=>2,'state'=>1]);

        $activityModel->checkActivityState(['activity_type'=>3,'state'=>1]);

        $activityModel->checkActivityState(['activity_type'=>4,'state'=>1]);

        $activityModel->checkActivityState(['activity_type'=>5,'state'=>1]);

        $activityModel->autoTaskState();

//        $activityModel->boBinAutoSendPrize();自动添加派奖

        $activityModel->addBoBinWinList();//添加中奖记录表



    }



    //如果用户登录30分钟无操作就自动退出

    protected function autoLogOut($last_access){

        if (Session::get('admin')) {

            $time=time();

           /* dump($time);

            dump($last_access);*/

            if (($time-$last_access)>86400) {

                $redis= initCacheRedis();

                $redis->del('last_access');

                $redis->close();

                self::logout('您已超过30分钟无操作，请重新登录','--退出系统');

            }

        }

    }



    private function logout($msg,$content){

        $this->db->insert("un_admin_log",array(

            "user_id" => $this->admin['userid'],

            "type" => 34,

            "content" => $this->admin['username'] . "--" . date('Y-m-d H:i:s') . $content,

            "loginip" => ip(),

            "logintime" => time()

        ));

        $user = Session::get("admin");

        if(!empty($user['userid'])){

            $this->db->delete("un_session",['user_id'=>$user['userid'],"is_admin"=>1]);

        }

        Session::del('admin');



        alert($msg, url('', 'login', 'index'), 0, 1);

    }





    // 验证是否登陆 及权限

    protected function checkloin() {

        if (ROUTE_M == 'admin' && ROUTE_C == 'login' && in_array(ROUTE_A, array('index', 'login', 'loginout', 'public_login_screenlock','verificationCode'))) {

            return true;

        }

//        var_dump($_SESSION);

//        echo "<br/>";

//        die(var_dump($admin));

        $admin = Session::get('admin');




        if(empty($admin)){

            alert('您登陆已经超时，请重新登陆！', url('', 'login', 'index'));

        }

        $lock_screen = Session::get('lock_screen');

        $sessionAdmin = $this->db->getone("select sessionid from un_session where user_id = {$admin['userid']}");





        //IP登录限制

        $is_open = D('config')->getOneCoupon('value',"nid='is_open_whiteList'")['value'];

        if($is_open) {

            $admins = D('admin/admin');

            $writeList = $admins->writeList();

            $ip = ip();

            if (!in_array($ip, $writeList)) {

                alert('您所在的网络状态无登录权限!当前IP:' . ip(), url('admin', 'login', 'index'), 1, 1);

            }

        }



        if (empty($admin)  ) {

            alert('您登陆已经超时，请重新登陆！', url('', 'login', 'index'));

        } else {

            //判断是否拉黑

            $rows = $this->db->getone("select * from un_admin where userid = {$admin['userid']}");

            if($rows['disabled'] == 1)

            {

                alert('该管理员已被禁用，请从新登陆！', url('', 'login', 'index'));

            }

            

            $this->admin = $admin;

            $roleidArray = explode(",", $this->admin['roleid']);

            //管理员角色的用户不进行权限验证,default控制器不验证权限,public_字符串开头的方法不验证权限

            if (!in_array(1, $roleidArray) && ROUTE_C != 'default' && substr(ROUTE_A, 0, 7) != 'public_') {

                $verify = D('admin/auth')->checkPower($this->admin['roleid']);

                if (!$verify) {

                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

                        echo json_encode(array("rt" => "-10000")); //-10000 权限验证不通过

                    } else {

                        if ($_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'POST') {

                            echo "<script src='statics/admin/js/jquery.min.js'></script>";

                            echo '<script src="statics/admin/js/layer/2.1/layer.js"></script>';

                            echo "<script>$(function(){layer.msg('没有权限执行此操作！！！',{icon:5, shade: [0.5, '#393D49']});})</script>";

                        }

                    }

                    die;

                }

            }

        }




    }



    public function upfile($id, $filename, $filepath = 'up_files', $upfile = '') {

        $name = $upfile["name"];

        //上传文件的文件名

        $type = $upfile["type"];

        //上传文件的类型

        $size = $upfile["size"];

        //上传文件的大小

        $tmp_name = $upfile["tmp_name"];

        //上传文件的临时存放路径

        //判断是否为图片

        switch ($type) {

            case 'image/pjpeg' :

                $okType = true;

                break;

            case 'image/jpeg' :

                $okType = true;

                break;

            case 'image/gif' :

                $okType = true;

                break;

            case 'image/png' :

                $okType = true;

                break;

        }

        if ($okType) {



            //上传后系统返回的值

            //把上传的临时文件移动到up目录下面

            $filepath .= "/{$id}";

            // $file_path = $filepath . date('Y') . '/' . date('md');

            $this->createDir($filepath);

            $name = end(explode('.', $upfile['name']));

            $newfile = $filepath . '/' . $filename . '.' . $name;

//            $newfile = $filepath . '/' . $filename.mt_rand(1000000,9999999). '.' . $name;

            $filename = $filename . '.' . $name;

            //$savepath = "content/" . date('Y') . '/' . date('md') . '/' . date('Ymdhis') . '.' . $name;

            $file = move_uploaded_file($tmp_name, $newfile);

            if ($file) {

                return $filepath . "/" . $filename;

            }

        }

    }



    public function createDir($aimUrl) {

        $aimUrl = str_replace('', '/', $aimUrl);

        $aimDir = '';

        $arr = explode('/', $aimUrl);

        $result = true;

        foreach ($arr as $str) {

            $aimDir .= $str . '/';

            if (!file_exists($aimDir)) {

                $result = mkdir($aimDir);

            }

        }

        return $result;



        echo $result;

        exit;

        return $result;

    }



    // 发送短信

    public function sendSMS($mobile = '', $content = '') {

        $url = C('sms_server'); //请求地

        $post_data = array(

            'action' => 'send',

            'userid' => C('sms_company_id'),

            'account' => C('sms_account'),

            'password' => C('sms_password'),

            'mobile' => $mobile,

            'content' => $content . '【Commercial Bank Era】'

        );

        //对发送的数据存储起来

        $postdata = http_build_query($post_data);

        $options = array(

            'http' => array(

                'method' => 'POST',

                'header' => 'http://121.199.50.122:8888/sms.aspx',

                'content' => $postdata,

                'timeout' => 15 * 60 // 超时时间（单位:s）

            )

        );

        $context = stream_context_create($options);

        $obj = file_get_contents($url, false, $context);

        return $obj;

    }



    //随机生成验证码

    function GetRandStr($len) {

        $chars = array(

            "0", "1", "2", "3", "4", "5", "6", "7", "8", "9"

        );

        $charsLen = count($chars) - 1;

        shuffle($chars);

        $output = "";

        for ($i = 0; $i < $len; $i++) {

            $output .= $chars[mt_rand(0, $charsLen)];

        }

        return $output;

    }



    /**

     * 刷新缓存

     * @method POST

     * @param $action string 方法名 刷新全部 all

     * @param $param string 参数  刷新全部 all

     * @return array

     */

    protected function refreshRedis($action, $param) {

        if (empty($action) || empty($param)) {

            return array('status' => 100002, 'data' => " 缺少刷新参数");

        }



        $arr=array();

        $param = array(

            'pass' => C('pass'),

            'action' => $action,

            'param' => $param

        );

        //组装URL

        foreach (C('home_arr') as $k=>$v){

            $url  =  $v."/index.php?m=api&c=initCache&a=index";

            lg('do_init_cache','url'.$url);

            $arr[$k]=http_post_json($url,$param);

        }

        return $arr;

//        $url = C('home_url') . "/index.php?m=api&c=initCache&a=index";

//        $param = array(

//            'pass' => C('pass'),

//            'action' => $action,

//            'param' => $param

//        );

//        //$result = signa($url, $param);

//        $result = curl_post($url,$param);

//        return $result;

    }



    /**

     * 数据查询

     * @return mixed sql

     */

    public function recursive_query_limit($id, $field = '*', $where = '',$limit='') {

        $sql ="SELECT user_id FROM `un_user_tree` WHERE pids LIKE '%,$id,%' $limit ";

//        dump($sql);

//        $sql = "SELECT {$field} FROM un_user WHERE parent_id = {$id} {$where}";

        $ress = O('model')->db->getAll($sql);

//        dump($sql);

//        dump($ress);

        $res = array();

        foreach ($ress as $k=>$v){

            $sql = "SELECT {$field} FROM un_user WHERE id={$v['user_id']}";

            $re  = $this->db->getone($sql);

//            dump($re);

            $res[$k]=$re;



        }

//        dump($res);

//        if ($res) {

//            foreach ($res as $v) {

//                $res_c = $this->recursive_query($v['id'], $field, $where);

//                $res = array_merge($res, $res_c);

//            }

//        }

        return $res;

    }



    /**

     * 数据查询

     * @return mixed sql

     */

    public function recursive_query($id, $field = '*', $where = '') {

        $sql = "SELECT {$field} FROM un_user WHERE parent_id = {$id} {$where}";

        $res = O('model')->db->getAll($sql);

        if ($res) {

            foreach ($res as $v) {

                $res_c = $this->recursive_query($v['id'], $field, $where);

                $res = array_merge($res, $res_c);

            }

        }

        return $res;

    }



    //管理员操作日志   目前记录日志 额度调整 线下充值 线上充值 提现管理

    public function operLog($userid, $type, $content) {

        $dictID = $this->db->getone("select id from un_dictionary where classid = 14 and value='{$type}'");

        $dictID = $dictID['id'];



        $time = date('Y-m-d H:i:s');

        $data = array(

            "user_id" => $userid,

            "type" => $dictID,

            "content" => $content . "Operating time:{$time};",

            "loginip" => ip(),

            "logintime" => time()

        );

        $this->db->insert("un_admin_log",$data);

    }





    protected function getTeamLists($teamData) {

        $self = array_filter(explode(',',$teamData['team_id_str']));

        array_unshift($self, $teamData['uid']);

        return array_unique($self);

    }



    protected function getLeaguer($teamData) {

        $self = array_filter(explode(',',$teamData['under_id_str']));

        array_unshift($self, $teamData['uid']);

        return array_unique($self);

    }



    /**

     * 团队id 包含自身

     * @return json

     */

    protected function teamLists($userId)

    {

        $sql = "SELECT user_id FROM `un_user_tree` WHERE `pids` LIKE '%,{$userId},%' ";

        $res = O('model')->db->getAll($sql);

        $self = array('user_id' => $userId);

        if (empty($res)) {

            return array($self);

        } else {

            array_push($res, $self);

            return $res;

        }

    }



    //直属会员    包括自己

    public function leaguer($userId)

    {

        $sql = "SELECT user_id AS id FROM `un_user_tree` WHERE `pids` LIKE '%,{$userId},'";

        $res = O('model')->db->getAll($sql);

        $self = array('id' => $userId);

        if (empty($res)) {



            return array($self);

        } else {

            array_push($res, $self);



            return $res;

        }

    }



    /**

     * Ajax方式返回数据到客户端

     * @param mixed $data 要返回的数据

     * @param mixed $info 要返回的信息描述

     * @param mixed $status 要返回的状态码

     * @return json

     */

    public function ajaxReturn($data,$info='',$status=200){

        $map = array();

        $map['data']   =   $data;

        $map['info']   =   $info;

        $map['status'] =   $status;

        // 返回JSON数据格式到客户端 包含状态信息

        header('Content-Type:application/json; charset=utf-8');

        exit(json_encode($map));

    }



    /**

     * 后台上传文件公用方法

     * @method GET

     * @return json

     * 2017-11-03 update-merge

     */

    public function newUploadImg($dirPath)

    {

        $error = array();

        if ($_FILES['file']['error'] > 0) {

            jsonReturn(array('status' => 200000, 'data' => '图片上传失败'));

        } else {

            if ($_FILES['file']['size'] > 600 * 1024) { // 图片大于2MB

                jsonReturn(array('status' => 200001, 'data' => '图片大小超过了600KB，上传失败'));

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



                $FileName = md5(time()) . '.' . $suffix;



                $path = $this->getAvatarUrl($FileName, $dirPath, 0);



                if (!move_uploaded_file($_FILES['file']['tmp_name'], $path)) {

                    jsonReturn(array('status' => 200001, 'data' => '图片上传失败'));

                }

                jsonReturn(array('status' => 0, 'data' => '/' . C('upfile_path') . $dirPath . $FileName));

            }

        }

    }



    /**

     * 生成文件名（包含路径）

     * @return string filename

     * 2017-11-03 update-merge

     */

    private function getAvatarUrl($avatarFileName, $dirPath, $isRand = 1)

    {

        if (empty($avatarFileName)) {

            return '';

        }

        $avatarUrl = S_ROOT . C('upfile_path') . $dirPath;

        if ($isRand) {

            $avatarUrl .= ('?rand=' . time());

        }

        if (!file_exists($avatarUrl)) {

            @mkdir($avatarUrl, 0777, true);

        }



        return $avatarUrl . $avatarFileName;

    }



}

