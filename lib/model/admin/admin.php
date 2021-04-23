<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/18
 * Time: 18:09
 * desc; 充值记录表
 */
!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'model' . DS . 'common.php');

class AdminModel extends CommonModel {

    public function check($username, $password) {
        $sql = "select * from un_admin where username='$username' and password='$password'";
        $data = $this->db->getone($sql);
        if (!empty($data) && $data['disabled'] == 1) {
            return -1; //禁用
        }
        
        //获取管理员有没有查看用户敏感信息的权限
        if (!empty($data)) {
            $sql = "select `roleid`, `is_show` from un_admin_role where `roleid` = " . $data['roleid'];
            $roleData = $this->db->getone($sql);
            if($data['roleid'] == 1){
                $data['show_user_info'] = [1,2,3,4,5];
            }elseif(!empty($roleData) && !empty($roleData['is_show'])){
                $data['show_user_info'] = explode(',', $roleData['is_show']);
            }else{
                $data['show_user_info'] = array(0);  //没有任何权限,如果没有任何权限，数组元素为0
            }
        }

        if (!empty($data) && $data['disabled'] == 0) {
            $ipData = getIp(); //登录ip归属地
            Session::set("admin", $data);
            $this->db->update("un_admin", array("lastloginip" => $ipData['ip'], "lastloginip_attribution" => $ipData['attribution'], "lastlogintime" => time()), array("userid" => $data['userid']));
            return 1;
        }
        return 0;
    }
    
    //白名单
    public function writeList(){
        $rt = $this->db->getall("select ip from un_whitelist where status = 0");
        $array = array();
        foreach ($rt as $value) {
            $array[] = $value['ip'];
        }
        return $array;
    }

}
