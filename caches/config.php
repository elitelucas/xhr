<?php

/**
 *  config.php 惯例配置文件
 * @copyright                   (C) 2011 snyni.com
 * @lastmodify                      2011-10-20   by snyni
 */
!defined('IN_SNYNI') && die('Access Denied!');

return [
    /* 系统设置 */
    'time_zone'           => 'Etc/GMT-8', // 设置系统时区 Etc/GMT-8 实际表示的是 GMT+8
    'time_limit'          => 30, // 脚本运行时间
    'gzip'                => 0, // 是否Gzip压缩后输出

    /** 项目设置 */
    'app_home'            => 'http://365belotto.com', //** 不带 '/'
    'api_host'            => 'http://365belotto.com', //** 不带 '/'
    'app_dir'             => '/', //程序安装目录，不带'/'
    'cdn_path'            => '/statics/', //cdn访问地址
    'auth_key'            => 'MZFcm4Iagyy9ypeFPleg', //密钥
    'app_webname'         => '365GAME', //项目名称
    'signa'        => [
        'key' => '2378b622b16e5fae82dc1789877bf52a',
        'secret_key' => 'dabfc5c6bcdb09bbcf1a59dc86a4932a',
    ],
    'bicp_token'      => '75acd47b8c0df897',
    'home_arr'        => ['http://365belotto.com'],
    'is_host_admin' => '1',
    /** 数据库设置 */
    'db_host'             => 'localhost', //Server
    'db_name'             => 'belotto', //数据库名称
    'db_user'             => 'belotto', //用户名
    'db_pwd'              => 'qweQWE123!@#', //密码 123456
    'db_prefix'           => 'un_', //数据前缀
    'db_lang'             => 'utf8', //数据库编码
    'db_pcon'             => 0, //连接模式，0为正常连接，1为持久连接
    'db_debug'            => 0, //是否显示错误信息*

    /* dubug模式 */
    'debug_mode'          => true, //异常处理方式，true为前台显示，false为记录到错误日志文件里
    'errorlog_size'       => 3, //错误日志自动清理大小，单位（MB） 超过此大小系统会自动清理日志文件~~最好不要超过 5 MB


    /* 上传设置 */
    'upfile_path'         => 'up_files', // 上传文件夹 不带 '/'
    'upfile_exts'         => 'png,gif,bmp,jpg,jpeg,rar,apk', // 上传允许的后缀名
    'upfile_style'        => 'Y-m', // 图片保存路径
    'upload_maxsize'      => 2048, // 上传大小

    /* 图片设置 */
    'cut_on'              => 1, // 是否自动缩小大的图片
    'cut_width'           => 1000, // 最大宽度    0为原始大小
    'cut_height'          => 1000, // 最大高度
    'thumb_width'         => 100, // 缩略图宽度
    'thumb_height'        => 100, // 缩略图高度
    'thumb_method'        => 1, // 缩略图算法(0：等比例，1：裁剪缩小，2：裁剪,3:填充)
    'water_pct'           => 80, // jpg图片合成质量
    'watermark'           => "statics/images/watermark.png",
    /* 模块和操作设置 */
    'var_module'          => 'm', // 默认模块获取变量
    'var_controllers'     => 'c', // 默认控制获取变量
    'var_action'          => 'a', // 默认操作获取变量
    'default_module'      => 'web', // 默认模块名称
    'default_controllers' => 'app', // 默认模块名称
    'default_action'      => 'index', // 默认操作名称

    /* Cookie设置 */
    'cookie_domain'       => '', // Cookie有效域名
    'cookie_prefix'       => 'SN_', // Cookie前缀 避免冲突

    /* URL模式 */
    'url_mode'            => 0, // 网站模式，0:动态  1:伪静态
    'url_line'            => '-', // URL 分隔符 不能用 '/\'
    'url_suffix'          => '.html', // 默认网页后缀名 如.shtml

    /*第三方合作p2p平台(红包)*/
    'thirdp2p'            => [
        'cht' => [
            'default' => 1, //设为默认
            'secret'  => 'A2exErhsx8rRC3Bc2qRKzqxGGLuen2tQT9SPrGyPYwsb2qfPrzRFQ3W6LnKAFyCC',  //接口请求密钥
            'urls'    => [
                1 => 'http://tg.szypwl.com/hbp2p/api/xtz.php?do=1', //根据邮箱和手机获取匹配的p2p平台帐号
                2 => 'http://tg.szypwl.com/hbp2p/api/xtz.php?do=2', //根据openid自动注册p2p平台账号兼登录
                3 => 'http://tg.szypwl.com/hbp2p/api/xtz.php?do=3', //使用p2p平台的账号token直接登录
                4 => 'http://tg.szypwl.com/hbp2p/api/xtz.php?do=4', //使用p2p平台账号登录
                5 => 'http://tg.szypwl.com/hbp2p/api/xtz.php?do=5', //注册p2p平台账号并登录
            ],
        ],
    ],

    /*短信配置*/
    'sms_server'          => 'http://121.199.50.122:8888/sms.aspx',//短信服务器
    'sms_account'         => 'SZYPWL',//短信账号
    'sms_password'        => '12345678',//短信密码
    'sms_company_id'      => '1019',//短信企业编号

//设置token过期时间为2天
    'token_ttl'           => 3600 * 24 * 2,

//redis 配置
    'redis_config'        => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'pass' => '00112233',
    ],

//刷新缓存密码
    'pass'                => 'undsuifgu564',
//推送服务器
    'Gateway'             => '127.0.0.1:1239',

    'weixin' => 'weixin',
    'qq'     => '123456',

    'version'                  => "v1.7.4",
//联合会员中间站API：设置PC手游用户在线状态
    'transfer_site_set_status' => '',
];
