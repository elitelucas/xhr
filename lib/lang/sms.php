<?php

/**
 *  sms.php 简体中文发手机短信送信息语言包
 */
!defined('IN_SNYNI') && die('Access Denied!');

return Array(
    //验证手机
    'veryphone.subject' => '请验证手机',
    'veryphone.content' => 'Hi,感谢您验证手机,您的验证码为:{attachment}',
    //支付密码修改,手机验证
    'paypass_very.subject' => '支付密码修改,手机验证',
    'paypass_very.content' => 'Hi,您正在修改支付密码,您的验证码为:{attachment}',
    //修改提现银行，手机验证
    'bank_very.subject' => '提现银行修改,手机验证',
    'bank_very.content' => 'Hi,您正在修改提现银行,您的验证码为:{attachment}',
    //注册验证
    'reg_very.subject' => '注册验证',
    'reg_very.content' => 'Hi,您正在注册验证,您的验证码为:{attachment}',
    //重置密码
    'rest_very.subject' => '重置密码',
    'rest_very.content' => 'Hi,您正在重置密码,您的验证码为:{attachment}',
);
?>