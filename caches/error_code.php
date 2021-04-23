<?php

class ErrorCode {

    //登录注册
    const PWD_TOO_SHORT        = 1003; // 密码小于6位
    const PWD_TOO_LONG         = 1004; // 密码大于16位
    const INVALID_MOBILE       = 1008; // 无效的手机号
    const MOBILE_NOT_EXISTS    = 1009; // 该手机号未绑定
    const MOBILE_EXISTS        = 1010; // 该手机号已存在
    const TOO_MANY_SMS_REQUEST = 1011; // 短信请求过于频繁
    const SEND_MSM_FAILED      = 1012; // 发送短信失败
    const EXPIRE_RAND_CODE     = 1013; // 验证码已过期
    const LAST_ONE_BIND        = 1014; // 已经是最后一个登录方式，不允许解绑
    const PHONE_OR_PWD_INVALID = 1102; // 账户或密码错误
    const INVALID_TOKEN        = 1202; // token非法
    const UPD_USER_INFO_FAILED = 1301; // 修改用户个人资料失败
    const INVALID_RAND_CODE    = 1501; // 验证码无效或过期
    const INVALID_THIRD_TYPE   = 1601; // 第三方平台type不合法
    const INVALID_OPENID1      = 1602;
    const INVALID_NICKNAME     = 1605;
    const OPENID_IS_USED       = 1607;
    const AVATAR_TOO_BIG       = 1701; // 上传图片大小过大
    const INVALID_IMG_TYPE     = 1702; // 上传图片类型不合法
    const UPLOAD_IMG_ERR       = 1703; // 服务器接收文件失败
    const UPLOAD_IMG_EMPTY     = 1704; // 上传图片为空
    const INVALID_DEVICE_TOKEN = 1707; // device_token为空
    const INVALID_OS_TYPE      = 1708; // os type类型不正确
    const SHORT_PARAMS         = 1709; // 缺少参数
    const USER_FORMAT_WRONG    = 1710; // 用户名格式不正确
    const PWD_FORMAT_WRONG     = 1711; // 密码格式不正确
    const PWD_DIFFERENT        = 1712; // 两次密码输入不一致
    const USER_HAS_EXISTS      = 1713; // 用户名已存在
    const OLD_PWD_WRONG        = 1714; // 旧密码不正确
    const DEFAULT_MSG          = 1715; // 默认错误提示
    const USER_QQ              = 1716; // 用户QQ格式错误
    const USER_WEIXIN          = 1717; // 用户微信格式错误
    const USER_EMAIL           = 1718; // 用户邮箱格式错误
    const USER_MOBILE          = 1719; // 用户手机格式错误
    const USER_NEED            = 1720; // 用户注册毕添项
    
    //记账
    const ADD_ACCOUNT_FAILED   = 3001;
    const UPD_ACCOUNT_FAILED   = 3002;
    const INVALID_MONEY        = 3004;
    const INVALID_RATE         = 3005;
    const INVALID_TIME_LIMIT   = 3006;
    const INVALID_PID          = 3008;
    const INVALID_TIME_TYPE    = 3010;
    const STATUS_CANNOT_CHANGE = 3011; //回款状态不能再改
    const MUST_RETURN_PREVIOUS = 3012; //要先还完上一期
    const ACCOUNT_HAS_RETURN   = 3013; //已回款的账单不能再编辑
    const INVALID_RETURN_TYPE   = 3014; //请选择还款方式
    const INVALID_PERIOD_TYPE   = 3015; //周期类型请选择月

    //自动记账
    const NOT_SUPPORT_AUTO     = 4001; //此平台暂不支持自动记账
    const AUTO_LOGIN_FAILD     = 4002; //绑定账号登录失败（帐号或密码不正确）
    const SYNC_ACTION_BUSY     = 4003; //操作太频繁(每次同步要隔10分钟)
    const AUTO_LOGIN_NEED_CODE = 4004; //登录需要验证码

    //账户
    const INVALID_ACCOUNT         = 5001;
    const NOT_BIND         = 5002;
    const RESERVE_ERROR        = 5003; // 您已预约

    //活动
    const INVALID_SIGN         = 6006; //非法签名
    const EMPTY_ACTIVITY_DATA  = 6010; //当前没有活动数据

    //对外API
    const SIGN_FAILED = 7001; //签名失败
    const RECORD_FAILED = 7002; //记录添加失败
    const DATA_VOID = 7003; //数据不符合要求

    //系统
    const DB_ERROR             = 9000; //数据库错误

    //根据错误码返回错误信息
    public static function errorMsg($code,$message='') {
    	static $errorMsg = array(
    			self::PWD_TOO_SHORT        => 'Password cannot be less than 6 digits',
    			self::PWD_TOO_LONG         => 'Password cannot be greater than 16 digits',
    			self::INVALID_MOBILE       => 'Invalid mobile phone number',
    			self::MOBILE_NOT_EXISTS    => 'Mobile phone number is not bound',
    			self::MOBILE_EXISTS        => 'Phone number already exists',
    			self::TOO_MANY_SMS_REQUEST => 'SMS requests are too frequent',
    			self::SEND_MSM_FAILED      => 'Failed to send SMS',
    			self::EXPIRE_RAND_CODE     => 'The verification code has expired',
    			self::LAST_ONE_BIND        => 'Unbound mobile phones are not allowed to unbind',
    			self::PHONE_OR_PWD_INVALID => 'Incorrect username or password',
    			self::INVALID_TOKEN        => 'Invalid token',
    			self::UPD_USER_INFO_FAILED => 'Failed to modify user profile',
    			self::INVALID_RAND_CODE    => 'Invalid or expired verification code',
    			self::INVALID_THIRD_TYPE   => 'The third-party platform type is illegal',
    			self::INVALID_OPENID1      => 'Invalid third-party openid',
    			self::INVALID_NICKNAME     => 'Invalid nickname',
    			self::OPENID_IS_USED       => 'Account has been bound',
    			self::AVATAR_TOO_BIG       => 'Uploaded image size is too large',
    			self::INVALID_IMG_TYPE     => 'The upload image type is illegal',
    			self::UPLOAD_IMG_ERR       => 'The server failed to receive the file',
    			self::UPLOAD_IMG_EMPTY     => 'Uploaded image is empty',
    			self::INVALID_DEVICE_TOKEN => 'device_token is empty',
    			self::INVALID_OS_TYPE      => 'os_type is incorrect',
    			self::ADD_ACCOUNT_FAILED   => 'Failed to save bill',
    			self::UPD_ACCOUNT_FAILED   => 'Failed to update bill',
    			self::INVALID_MONEY        => 'Invalid amount',
    			self::INVALID_RATE         => 'Invalid interest rate',
    			self::INVALID_TIME_LIMIT   => 'Invalid investment period',
    			self::INVALID_PID          => 'Invalid pid',
    			self::INVALID_TIME_TYPE    => 'Invalid term type',
    			self::INVALID_PERIOD_TYPE  => 'Please select the month cycle type',
    			self::STATUS_CANNOT_CHANGE => 'The payment status cannot be changed',
    			self::MUST_RETURN_PREVIOUS => 'Go back to the previous issue before going back to this issue',
    			self::ACCOUNT_HAS_RETURN   => 'A bill that has been paid back can no longer be edited',
    			self::INVALID_RETURN_TYPE  => 'Please select the repayment method',
    			self::NOT_SUPPORT_AUTO     => 'This platform does not support automatic accounting',
    			self::AUTO_LOGIN_FAILD     => 'Failed to login to bind account',
    			self::SYNC_ACTION_BUSY     => 'Synchronization operations are too frequent',
    			self::AUTO_LOGIN_NEED_CODE => 'Login requires verification code',
    			self::INVALID_SIGN         => 'Illegal signature',
    			self::EMPTY_ACTIVITY_DATA  => 'There is currently no active data',
    			self::DB_ERROR             => 'Database error',
    			self::INVALID_ACCOUNT      => 'Bank card account number is 16-19 pure numbers',
    			self::NOT_BIND             => 'Not bound to this platform',
    			self::RESERVE_ERROR        => 'You have made an appointment',
    			self::SIGN_FAILED          => 'Signature failed',
    			self::RECORD_FAILED       => 'Failed to add record',
    			self::DATA_VOID           => 'The data does not meet the requirements',
                self::DEFAULT_MSG          => "Default error message"
    	);
    	
    	if(!empty($message))
    	{
    		return $message;
    	}
    	else 
    	{
    		if(isset($errorMsg[$code]))
    		{
    			return $errorMsg[$code];
    		}
    		else 
    		{
    			return 'ErrorCode:' . $code;
    		}	
    	}	
    }
    
    //返回错误json信息
    public static function errorResponse($code,$message='')
    {
    	jsonReturn(array(
    			'status' => $code,
    			'ret_msg' => self::errorMsg($code,$message)
    	));
    }
    
    //返回成功json信息
    public static function successResponse( Array $data=array() )
    {
    	$return = array('status' => 0);
    	if(!isset($data['ret_msg']))
    	{
    		$return['ret_msg'] = '';
    	}	
    	
    	if(!empty($data))
    	{
    		$return = array_merge($return,$data);
    	}	
    	
    	jsonReturn($return);
    }

}
