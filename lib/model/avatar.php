<?php

/**
 * 默认头像
 * 2017-11-24
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class AvatarModel extends CommonModel
{
    // protected $table='un_default_avatar';

    //保存图片
    public function updateAvatar($id, $avatar_url)
    {
        $now_time = time();
        $update_sql = "UPDATE un_default_avatar
            SET avatar_url = '{$avatar_url}',
            last_updatetime = '{$now_time}'
            WHERE id = {$id} ";
        return $this->db->query($update_sql);
    }

    //获取所有头像图片
    public function fetchList($field_arg = '', $limit_num = 9)
    {
        if ($field_arg == '') {
            $field = 'id, avatar_url, last_updatetime';
        } else {
            $field = $field_arg;
        }
        $fetch_all_sql = "SELECT {$field} FROM un_default_avatar LIMIT {$limit_num}";
        return $this->db->getAll($fetch_all_sql);
    }

    //获取随机头像图片
    public function fetchRandomPic()
    {
        //取随机数据，语句比较复杂，但效率高
        $fetch_random_sql = "SELECT self_table.avatar_url FROM un_default_avatar AS self_table
            INNER JOIN (SELECT ROUND(RAND() * (SELECT MAX(id) FROM un_default_avatar)) AS id) AS tmp_table 
            WHERE self_table.id >= tmp_table.id LIMIT 1";
        $random_data = $this->db->result($fetch_random_sql);
        return $random_data;
    }
}
