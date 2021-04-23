<?php

/**
 *  config.php 惯例配置文件
 *
 * @copyright			(C) 2011 snyni.com
 * @lastmodify			2011-10-20   by snyni
 */
!defined('IN_SNYNI') && die('Access Denied!');

return array(
    /* 系统设置 */
    'time_zone' => 'Etc/GMT-8', // 设置系统时区 Etc/GMT-8 实际表示的是 GMT+8
    'time_limit' => 30, // 脚本运行时间
    'gzip' => 0, // 是否Gzip压缩后输出

    /** 项目设置 */
    'app_home' => 'http://localhost/xtz/server', //** 不带 '/'
    'app_dir' => '/xtz/server', //程序安装目录，不带'/'
    'cdn_path' => '/xtz/server/statics/', //cdn访问地址
    'auth_key' => 'MZFcm4Iagyy9ypeFPleg', //密钥

    /** 数据库设置 */
    'db_host' => 'kusumi.kinimi.com:51818', //Server
    'db_name' => 'p2p', //数据库名称
    'db_user' => 'p2p', //用户名
    'db_pwd' => '123456', //密码 123456
    'db_prefix' => '', //数据前缀
    'db_lang' => 'utf8', //数据库编码
    'db_pcon' => 0, //连接模式，0为正常连接，1为持久连接
    'db_debug' => 1, //是否显示错误信息


    /* dubug模式 */
    'debug_mode' => 1, //异常处理方式，true为前台显示，false为记录到错误日志文件里
    'errorlog_size' => 3, //错误日志自动清理大小，单位（MB） 超过此大小系统会自动清理日志文件~~最好不要超过 5 MB


    /* 上传设置 */
    'upfile_path' => 'up_files', // 上传文件夹 不带 '/'
    'upfile_exts' => 'png,gif,bmp,jpg,jpeg,rar,apk', // 上传允许的后缀名
    'upfile_style' => 'Y-m', // 图片保存路径
    'upload_maxsize' => 2048, // 上传大小

    /* 图片设置 */
    'cut_on' => 1, // 是否自动缩小大的图片
    'cut_width' => 1000, // 最大宽度    0为原始大小
    'cut_height' => 1000, // 最大高度
    'thumb_width' => 100, // 缩略图宽度
    'thumb_height' => 100, // 缩略图高度
    'thumb_method' => 1, // 缩略图算法(0：等比例，1：裁剪缩小，2：裁剪,3:填充)
    'water_pct' => 80, // jpg图片合成质量
    'watermark' => "statics/images/watermark.png",
    /* 模块和操作设置 */
    'var_module' => 'm', // 默认模块获取变量
    'var_controllers' => 'c', // 默认控制获取变量
    'var_action' => 'a', // 默认操作获取变量
    'default_module' => 'content', // 默认模块名称
    'default_controllers' => 'default', // 默认模块名称
    'default_action' => 'index', // 默认操作名称

    /* Cookie设置 */
    'cookie_domain' => '', // Cookie有效域名
    'cookie_prefix' => 'SN_', // Cookie前缀 避免冲突

    /* URL模式 */
    'url_mode' => 0, // 网站模式，0:动态  1:伪静态
    'url_line' => '-', // URL 分隔符 不能用 '/\'
    'url_suffix' => '.html', // 默认网页后缀名 如.shtml

    /*第三方合作p2p平台(红包)*/
    'thirdp2p' => array(
        'cht' => array(
            'default' => 1, //设为默认
            'secret' => 'A2exErhsx8rRC3Bc2qRKzqxGGLuen2tQT9SPrGyPYwsb2qfPrzRFQ3W6LnKAFyCC',  //接口请求密钥
            'urls' => array(
                1 => 'http://tg.szypwl.com/hbp2p/api/xtz.php?do=1', //根据邮箱和手机获取匹配的p2p平台帐号
                2 => 'http://tg.szypwl.com/hbp2p/api/xtz.php?do=2', //根据openid自动注册p2p平台账号兼登录
                3 => 'http://tg.szypwl.com/hbp2p/api/xtz.php?do=3', //使用p2p平台的账号token直接登录
                4 => 'http://tg.szypwl.com/hbp2p/api/xtz.php?do=4', //使用p2p平台账号登录
                5 => 'http://tg.szypwl.com/hbp2p/api/xtz.php?do=5', //注册p2p平台账号并登录
            ),
        ),
    ),

    /*短信配置*/
    'sms_server'    => 'http://121.199.50.122:8888/sms.aspx',//短信服务器
    'sms_account'   => 'SZYPWL',//短信账号
    'sms_password'  => '12345678',//短信密码
    'sms_company_id' => '1019',//短信企业编号
);
?>
