<?php



!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');



/**

 * desc: APP 活动中心接口类

 */

class ActcenterAction extends Action

{



    public function __construct()

    {

        parent::__construct();

    }



    /**

     * 活动列表

     * 2017-12-14 update

     */

    public function fetchActList()

    {



        // $_REQUEST['token'] = '3s19kvpf18e52t9qad91g1j804';



        //验证token

        $this->checkAuth();

        $is_all =  getParame('is_all', 0, 0);     //是否显示全部活动  1是  0否



        $user_info = [

            'user_id' => $this->userId,

            'token' => $_REQUEST['token'],

        ];

        $count = D('Actcenter')->actCnt();



        //分页信息

        $page = intval($_REQUEST['page']) ? : 1;



        $page_size = 10;

        if($is_all == 1) $page_size = $count;



        $maxPage = ceil($count / $page_size);

        $act_list = D('Actcenter')->fetchActList($user_info, $page, $page_size);

        $rt_data = [

            'data' => $act_list,

            'pageNum' => $maxPage,

        ];



        ErrorCode::successResponse($rt_data);



    }



}

