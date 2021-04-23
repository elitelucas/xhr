<?php

/**
 *  mail.php 简体中文发送信息语言包
 *
 * @copyright			(C) 2011 snyni.com
 * @lastmodify			2011-10-20   by snyni
 */
!defined('IN_SNYNI') && die('Access Denied!');

return Array(
    //注册
    'reg.subject' => '感谢注册{sitename}，请完成您的邮箱验证!',
    'reg.content' => '亲爱的会员:{username},您好!<br/><br/>
    感谢您注册{sitename}—安全可靠的民间借贷公司-致力于打造国内口碑很好的P2P网络借贷平台.<br>
    请点击下面的链接验证您的Email：<br/><a href="{url}" target="_blank">{url}</a><br/><br/> 
    您在诚汇通可以享受到以下服务：<br/> 1)您可以通过{sitename}发布借款请求以解决资金紧缺需要.<br/>
    2)可以将自己的闲散资金出借给有需要的人获得资金回报.<br/><br/>
    希望您能在诚汇通上得到更多人的帮助，也希望您能在{sitename}上帮助更多的人，并从中获得乐趣！<br/>
    {sitename}首页：<a href="http://www.chenghuitong.net">http://www.chenghuitong.net</a>
    <br  />',
    'verify.subject' => '感谢注册{sitename}，请完成您的邮箱验证!',
    'verify.content' => '亲爱的会员,您好!<br/><br/>
    感谢您注册{sitename}—安全可靠的民间借贷公司-致力于打造国内口碑很好的P2P网络借贷平台.<br><br>
    请验证您的邮箱,您的验证码为:{attachment}<br><br>
    您在诚汇通可以享受到以下服务：<br/> 1)您可以通过{sitename}发布借款请求以解决资金紧缺需要.<br/>
    2)可以将自己的闲散资金出借给有需要的人获得资金回报.<br/><br/>
    希望您能在诚汇通上得到更多人的帮助，也希望您能在{sitename}上帮助更多的人，并从中获得乐趣！<br/>
    {sitename}首页：<a href="http://www.chenghuitong.net">http://www.chenghuitong.net</a>
    <br  />',
    // 密码找回
    'forgetpass.subject' => '密码找回-{sitename}，请验证Email',
    'forgetpass.content' => 'Hi {username},<br/>感谢您在{sitename}发起了密码找回，请点击下面的找回您的密码：<br  /><br/><a href="{url}" target="_blank">{url}</a><br/><br/>-- <br/>{sitename}',
    //系统
    'error.system' => '系统错误',
    'error.position' => '错误位置',
    'error.errinfo' => '错误信息',
    'error.nomodel' => '指定的模型不存在: ',
    'error.noview' => '指定的视图不存在: ',
    'error.nofile' => '指定的文件不存在: ',
    'error.noparam' => 'URL组装参数不正确!',
    //公共
    'select' => '您可以选择',
    'or' => '或者',
    'gohome' => '返回首页',
    'tautology' => '重试',
    'back' => '返回',
    //系统
    'sys.name' => 'CHT',
    'sys.var' => 'v1.0 Release 20131025',
);
?>