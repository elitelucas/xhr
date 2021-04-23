<?php

/**
 * 默认头像接口
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class AvatarAction extends Action
{

    /**
     * 数据表
     */
    private $avatar_model;

    public function __construct()
    {
        parent::__construct();
        $this->avatar_model = D('Avatar');
    }


    /**
     * 获取系统默认头像
     * 2017-11-27
     * @method GET/POST  /index.php?m=api&c=avatar&a=avatarList&token=usertoken&avatar_num=9
     * @param string token 用户token
     * @param int avatar_num 获取的头像个数
     * @return json
     */
    public function avatarList()
    {

        //验证token
        $this->checkAuth();

        //如果没有传图片数量，则默认取9张
        $avatar_num = intval($_REQUEST['avatar_num']) ? : 9;

        $field = 'id, avatar_url';
        $data_list = $this->avatar_model->fetchList($field, $avatar_num);

        $rt_data = [
            'avatar_list' => $data_list,
        ];

        ErrorCode::successResponse($rt_data);
    }
    
}
