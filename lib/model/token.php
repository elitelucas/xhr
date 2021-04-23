<?php
/**
 * token表model
 */
!defined('IN_SNYNI') && die('Access Denied!');

class tokenModel extends Model {

    /**
     * 创建token
     * @param int $userid
     * @return string
     */
    public function createToken($userid) {
        $this->deleteToken($userid);
        $token = substr(md5(uniqid() . $userid), 0, 16);
        $data = array(
            'user_id' => $userid,
            'token'   => $token,
        );
        $this->db->insert('#@_token', $data);
        if ($this->db->affected_rows()) {
            return $token;
        } else {
            return 0;
        }
    }

    /**
     * 根据token获得userid
     * @param string $token
     * @return int
     */
    public function getUseridByToken($token) {
        $sql = $this->db->c_sql(array('token' => $token), 'user_id', '#@_token');
        $res = $this->db->getone($sql);
        if (!empty($res)) {
            return $res['user_id'];
        } else {
            return 0;
        }
    }

    /**
     * 删除token
     */
    public function deleteToken($userid) {
        $this->db->delete('#@_token', array('user_id' => $userid));
    }

    /**
     * 根据id获取token
     * 2017-11-30
     */
    public function getTokenByUserid($user_id)
    {
        $user_id = floatval($user_id);
        $sql = "SELECT sessionid FROM un_session
            WHERE user_id = {$user_id}
            ORDER BY lastvisit DESC LIMIT 1";
        $token = $this->db->result($sql);
        return $token;
    }

}
