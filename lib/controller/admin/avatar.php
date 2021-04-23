<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'pages.php';

/**
 * 后台默认头像控制器
 * 2017-11-24
 */
class AvatarAction extends Action
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 活动管理
     * 2017-11-02
     */
    public function list()
    {

        //分页带上的条件
        $params = $_REQUEST;
        unset($params['m']);
        unset($params['c']);
        unset($params['a']);

        $sql = 'SELECT COUNT(*) AS rows_cnt FROM un_default_avatar';

        $countInfo = $this->db->getOne($sql);
        $listCnt = $countInfo['rows_cnt'];
        $pagesize = 10;
        $url = '?m=admin&c=avatar&a=list';
        $page = new pages($listCnt, $pagesize, $url, $params);
        $show = $page->show();

        $page_start = $page->offer;
        $page_size = $pagesize;

        $limit = "limit {$page_start},{$page_size}";

        //查询字段
        $field = 'id,avatar_url,last_updatetime';

        //查询列表
        $sql2 = "SELECT {$field} FROM un_default_avatar ORDER BY id DESC {$limit}";
        $list = $this->db->getAll($sql2);

        include template('avatar-list');
    }

    /**
     * 上传图片
     * @method GET
     * @return json
     */
    public function uploadImg()
    {
        $dirPath = '/avatar/default/';
        return $this->newUploadImg($dirPath);
    }

    /**
     * 保存图片数据
     * 2017-11-25
     */
    public function saveAvatar()
    {
        $id = intval($_POST['id']);
        $avatar_url = trim($_POST['avatar_url']);

        $flag = D('Avatar')->updateAvatar($id, $avatar_url);
        echo json_encode(['rt' => 1, 'flag' => $flag]);
    }

}
