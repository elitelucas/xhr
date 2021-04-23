<?php

/**
 * 后台授权验证码接口
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class LogincodeAction extends Action
{

    public function __construct()
    {
        parent::__construct();
    }


    /**
     * 检测设备，判断设备号是否存在
     * 2017-12-05
     * @method GET/POST  /?m=api&c=logincode&a=isDeviceCodeExists&device_code=abc
     * @param string device_code 设备码
     * @return json
     */
    public function isDeviceCodeExists()
    {

        //查看设备码是否已经存在
        $device_code = trim($_REQUEST['device_code']);

        $is_exists = D('Logincode')->isDeviceCodeExists($device_code);

        $rt_data = [
            'is_exists' => $is_exists,
        ];

        ErrorCode::successResponse($rt_data);
    }

    /**
     * 绑定设备
     * 2017-12-05
     * @method GET/POST  /?m=api&c=logincode&a=bindDevice&device_code=abc&random_code=123456
     * @param string device_code 设备码
     * @param string random_code 随机授权码
     * @return json
     */
    public function bindDevice()
    {
        //用设备码、随机码做绑定
        $device_code = trim($_REQUEST['device_code']);
        $random_code = trim($_REQUEST['random_code']);

        $logincode_model = D('Logincode');

        //判断 $device_code 和 $random_code 为空的情况
        if (! $device_code || ! $random_code) {
            ErrorCode::errorResponse(900001, 'The device code or random authorization code cannot be empty');
        }

        //检查随机码是否存在
        $is_random_code_exists = $logincode_model->isRandomCodeExists($random_code);
        if (! $is_random_code_exists) {
            ErrorCode::errorResponse(900101, 'Random authorization code does not exist');
        }

        $bind_info = $logincode_model->bindDevice($device_code, $random_code);
        $rt_data = [
            'bind_info' => $bind_info,
        ];

        ErrorCode::successResponse($rt_data);
    }

    /**
     * 根据设备号，获得随机码
     * 2017-12-05
     * @method GET/POST  /?m=api&c=logincode&a=fetchRandomCode&device_code=abc
     * @param string device_code 设备码
     * @return json
     */
    public function fetchRandomCode()
    {
        //用设备码去查询随机码
        $device_code = trim($_REQUEST['device_code']);

        //判断 $device_code 为空的情况
        if (! $device_code) {
            ErrorCode::errorResponse(900002, 'Device code cannot be empty');
        }

        $random_code = D('Logincode')->fetchRandomCode($device_code);
        $rt_data = [
            'random_code' => $random_code,
        ];

        ErrorCode::successResponse($rt_data);
    }
    
}
