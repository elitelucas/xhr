<?php

//!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_CORE . 'class' . DS . 'withdraw' . DS . 'mima.php');
class WithdrawFactory
{
    public function getInterface($nid)
    {
        switch ($nid) {
            case "mi_man_withdraw":
                return new mima();
            default:
                ErrorCode::errorResponse(200055, '代付方式不存在');
        }
    }
}
