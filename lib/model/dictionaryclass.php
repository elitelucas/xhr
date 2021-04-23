<?php

/**
 * Created by PhpStorm.
 * User: wangrui
 * Date: 2016/11/18
 * Time: 23:09
 * desc: 字典表
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class DictionaryClassModel extends CommonModel {
    protected $table = '#@_dictionary_class';

}