<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/11
 * Time: 11:55
 * desc: 系统收款账户信息
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class PaymentConfigModel extends CommonModel {
    protected $table = '#@_payment_config';

}