<?php

//!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'admin' . DS. 'mima.php');
include_cache(S_PAGE . 'model' . DS . 'admin' . DS. 'quanyin.php');
class WithdrawFactory
{
    public function getInterface($nid)
    {
        switch ($nid) {
            case "mi_man_withdraw":
                return new mima();
            case "quan_yin_withdraw":
                return new quanyin();
            default:
                echo json_encode(['code'=>-1,'msg'=>'代付方式不存在']);
        }
    }
}
