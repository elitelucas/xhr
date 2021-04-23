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

class AuthModel extends CommonModel {

    protected $table = "#@_menu";
    protected $table1 = "#@_admin_role";

    //验证用户权限
    public function checkPower($roleid) {
        $m = ROUTE_M;
        $c = ROUTE_C;
        $a = ROUTE_A;
        $idRt = $this->db->getone("select id from " . $this->table . " where m='{$m}' and c='{$c}' and a='{$a}'"); //用户的请求对应的权限表id
        $id = $idRt['id'];
        if (empty($id)) { //如果没有配置该权限,默认通过
            return true;
        }

        $auth_list = array(); //用户-角色所拥有的权限list
        $authRt = $this->userAuth($roleid);
        foreach ($authRt as $value) {
            if (empty($value['power_config'])) {
                continue;
            }
            $au = json_decode($value['power_config'], true);
            $auth_list = array_merge($auth_list, $au);
        }
        $auth_str = implode(",", $auth_list);

        $sql = "select * from " . $this->table . " where parentid in($auth_str) and id = $id"; //用户请求的权限ID、且父ID在用户角色权限里面
        $rt = $this->db->getone($sql);
        if (empty($rt)) {
            return false;
        } else {
            return true;
        }
    }

    //根据角色ID字符串  获取权限
    public function userAuth($roleid) {
        return $this->db->getall("select power_config from " . $this->table1 . " where roleid in ($roleid)");
    }

}
