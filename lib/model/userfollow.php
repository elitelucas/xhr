<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

/**
 * 用户关注关系表model
 * 2018-05-29
 */
class UserfollowModel extends CommonModel
{

    /**
     * 获取关注的用户列表
     * @param int $user_id 用户ID
     * @return array 关注的用户列表
     * 2018-05-29
     */
    public function fetchFollowList($user_id)
    {
        $where_str = " user_id = {$user_id} ";
        $field = 'follow_user_id';
        $sql = "SELECT {$field} FROM un_user_follow WHERE {$where_str}";
        $data = $this->db->getAll($sql);
        return $data;
    }

    /**
     * 设置为关注用户
     * @param int $user_id 用户ID
     * @param int $follow_user_id 要设置为关注的用户ID
     * @return boolean 设置后的状态
     */
    public function setFollowUser($user_id, $follow_user_id)
    {
        $sql = "INSERT INTO un_user_follow VALUES (NULL, {$user_id}, {$follow_user_id})";
        return $this->db->query($sql);
    }

    /**
     * 取消关注用户
     * @param int $user_id 用户ID
     * @param int $follow_user_id 要取消关注的用户ID
     * @return boolean 设置后的状态
     */
    public function cancelFollowUser($user_id, $follow_user_id)
    {
        $where = "user_id = {$user_id} AND follow_user_id = {$follow_user_id}";
        $sql = "DELETE FROM un_user_follow WHERE {$where}";
        return $this->db->query($sql);
    }

}
