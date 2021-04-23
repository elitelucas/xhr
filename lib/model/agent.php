<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/9
 * Time: 17:56
 * desc: 代理等级
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class AgentModel extends CommonModel {
    protected $table = '#@_agent_group';

}