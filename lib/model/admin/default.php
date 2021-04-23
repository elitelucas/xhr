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

class DefaultModel extends CommonModel {

    protected $table = '#@_admin_role';
    protected $table1 = '#@_menu';
    protected $table2 = '#@_admin_log';

    protected $stock_menu;

    //根据角色ID字符串  获取权限
    public function userAuth($roleid) {
        return $this->db->getall("select power_config from " . $this->table . " where roleid in ($roleid)");
    }

    public function getMenuById($menu_id) {
        return $this->db->getone("select * from $this->table1 where id = $menu_id");
    }

    //前台菜单   所有
    public function indexMenu($stock_menu_json = '') {
        $stock_menu_res = [];
        $stock_menu_arr = [];
        if($stock_menu_json) {
            $stock_menu_arr = json_decode($stock_menu_json, 1);
        }

        $menuRt = $this->db->getall("select * from " . $this->table1 . " where display = 1 order  by listorder");
        $menuList = array();
        foreach ($menuRt as $value) {
            if ($value['parentid'] == 0) {
                $tmp = array(
                    "id" => $value['id'],
                    "name" => $value['name'],
                    "data" => $value['data'],
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
                    "child" => array(),
                    "url" => "?m={$value['m']}&c={$value['c']}&a={$value['a']}{$value['data']}"
                );
                $menuList[$value['parentid']]['child'][$value['id']] = $tmp;
                if(in_array($value['id'], $stock_menu_arr)) {
                    $stock_menu_res[] = $tmp;
                }
            }
        } //循环第二次,二级菜单
        foreach ($menuRt as $value) {
            if (array_key_exists($value['parentid'], $twoMenuId)) {
                $tmp = array(
                    "id" => $value['id'],
                    "name" => $value['name'],
                    "child" => array(),
                    "url" => "?m={$value['m']}&c={$value['c']}&a={$value['a']}{$value['data']}"
                );
                $menuList[$twoMenuId[$value['parentid']]]['child'][$value['parentid']]['child'][] = $tmp;
                if(in_array($value['id'], $stock_menu_arr)) {
                    $stock_menu_res[] = $tmp;
                }
            }
        } //循环第三次,三级菜单
        $this->stock_menu = $stock_menu_res;

        return $menuList;
    }

    public function getStockMenu() {
        return $this->stock_menu;
    }

    //所有权限   列表
    public function allAuth() {
        $rt = $this->db->getall("select id from " . $this->table1 . " where display = 1");
        $array = array();
        foreach ($rt as $value) {
            $array[] = $value['id'];
        }
        return $array;
    }



}
