<?php

/**
 *  template.php 模板解析类
 *
 * @copyright			(C) 2011 snyni.com
 * @lastmodify			2011-09-03   by snyni
 */
!defined('IN_SNYNI') && die('Access Denied!');

class template {

    public $templates_dir = 'template/default/';    //模板路径;
    public $templates_cache = 'caches/cache_tpl/';   //缓存模板路径;
    public $templates_new = false;       //设置当次更新;
    public $templates_time = 0;      //过期时间,分钟计算;
    //以下的默认设置
    public $templates_charset = 'utf-8';
    public $templates_postfix = '.html';   //模板后缀;
    public $templates_auto = true;    //自动更新模板;
    public $templates_html = false;    //静态化html;
    public $templates_caching = '.php';   //缓存后缀;
    public $templates_debug = array();   //错误信息;
    public $templates_lonlabel = true;   //长标签;
    public $templates_space = false;   //清除无意义字符
    public $templates_html_dir = null; //静态缓存生成目录
    public $templates_php = '<?php !defined(\'IN_SNYNI\') && die(\'Access Denied!\');?>'; //为每个缓存头部增加PHP码;
    //结果集;
    public $templates_file = array();     //模板文件
    public $templates_cache_file = null;            //缓存文件;
    public $templates_name = null;      //标识名
    public $templates_message = null;     //html内容;
    public $templates_update = 0;      //更新次数
    public $templates_assign = array();     //用户用smarty模式;
    //缓存控制;
    public $templates_menu = array();   //用于记录每个模板信息;
    public $templates_html_cache = false;  //用于智能更新;
    public $replace = array();     //用于智能更新;
    public $on = false;

    //构造函数
    public function __construct() {
        //为属性作初级判断;
        $this->templates_auto = (boolean) $this->templates_auto;
        $this->templates_lonlabel = (boolean) $this->templates_lonlabel;
        $this->templates_space = (boolean) $this->templates_space;
        $this->templates_new = (boolean) $this->templates_new;
    }

    //解析开始;
    public function display($file_name, $on = false) {
        //为控制设置流程;
        if (is_file($this->templates_cache . 'templates_caceh.php') && $this->templates_auto === true)
            $this->templates_menu = @include($this->templates_cache . 'templates_caceh.php');
        if ($on !== false)
            $this->on = $on;
        if ($this->on === true)
            $this->on = true;
        //取得路径;
        $this->templates_file[$file_name] = $this->get_path($file_name);
        //取得文件名字;
        $this->templates_name = $file_name;
        //取得缓存路径;
        $this->templates_cache_file[$file_name] = $this->get_path();

        //自动更新模板关闭时
        if ($this->templates_auto === false) {
            return $this->templates_cache_file[$file_name];
        }
        //当次更新开启时
        if ($this->templates_new === true) {
            $this->templates_menu = array();
        }
        //判断是否需要编译更新
        if ($this->check($this->templates_name) === true) {
            return $this->templates_cache_file[$file_name];
        }
        $this->templates_message = null;
        if (is_file($this->templates_file[$this->templates_name]) == true)
            $this->templates_message = $this->replace_html(file_get_contents($this->templates_file[$this->templates_name], LOCK_EX));
        //处理配置;
        $this->templates();
        return $this->fileplus();
    }

    //生成缓存
    private function fileplus() {
        @file_put_contents($this->templates_cache_file[$this->templates_name], $this->templates_message, LOCK_EX);
        $this->templates_message = array();
        return $this->templates_cache_file[$this->templates_name];
    }

    //更新控制配置
    private function templates() {
        if (isset($this->templates_menu[$this->templates_name]))
            unset($this->templates_menu[$this->templates_name]);
        if (is_array($this->templates_menu) === false)
            $this->templates_menu = array();
        if (is_file($this->templates_file[$this->templates_name]) == true)
            $this->templates_menu[$this->templates_name] = md5_file($this->templates_file[$this->templates_name]);
        $php = null;
        if ($this->templates_php !== false)
            $php = $this->templates_php . "\r\n";
        @file_put_contents($this->templates_cache . 'templates_caceh.php', sprintf($php . "<?php \r\n return %s;", var_export($this->templates_menu, true)), LOCK_EX);
    }

    //路径处理
    private function get_path($file_name = null) {
        //为默认路径做完整路径抓取;
        $this->path();
        if ($file_name !== null) {
            $file_all = $this->templates_dir . $file_name . $this->templates_postfix;

            if ($this->templates_dir === null || is_file($file_all) === false) {
                $file_all = realpath('template/default/') . DIRECTORY_SEPARATOR . $file_name . $this->templates_postfix;
            }
            if (is_file($file_all) === false) {
                $this->templates_debug[] = 'Template file does not exist or an error';
                return false;
            }
            return (string) $file_all;
        } else {
            $file = $this->templates_file[$this->templates_name];
            $file_arr = pathinfo($file);
            //$postfix = basename(dirname($file));
            $postfix = str_replace(DS, "_", str_replace(S_THEMES, "", $file_arr['dirname']));
            return $this->templates_cache . $postfix . '_' . $file_arr['filename'] . '_cache' . $this->templates_caching;
        }
        return false;
    }

    // 路径规范处理;
    private function path() {
        $this->templates_dir = realpath(strtr($this->templates_dir, array('./' => '', '//' => '/', '\\\\' => '\\'))) . DIRECTORY_SEPARATOR;
        $this->templates_cache = realpath(strtr($this->templates_cache, array('./' => '', '//' => '/', '\\\\' => '\\'))) . DIRECTORY_SEPARATOR;
        $this->templates_html_dir = $this->templates_cache . 'cache_html/';
        if (strlen($this->templates_dir) <= 2) {
            @mkdir('caches/cache_tpl/cache_html', 0777, true);
            exit('Templates Catalog Error');
        }
        if (strlen($this->templates_cache) <= 2) {
            @mkdir('caches/cache_tpl/cache_html', 0777, true);
            exit('Cache Catalog Error');
        }
        if (is_dir($this->templates_html_dir) === false) {
            mkdir($this->templates_html_dir, 0777);
        }
    }

    //控制判断
    private function check($name) {
        //对文件及目录判断
        if (strlen($this->templates_name) === 0) {
            $this->templates_name = 'index';
        }
        if (is_dir($this->templates_dir) === false) {
            exit('Templates directory does not exist');
        }
        if (is_dir($this->templates_cache) === false) {
            exit('Template cache directory does not exist');
        }
        if (is_file($this->templates_cache_file[$name]) === false) {
            return false;
        }
        if (is_file($this->templates_file[$name]) === false) {
            exit('Template does not exist');
        }

        // true 让判断成真,即返回缓存文件 // false 将执行编译;
        if (empty($this->templates_menu) === true)
            return false;

        if ($this->templates_time && time() - @fileatime($this->templates_cache . 'templates_caceh.php') >= $this->templates_time * 60) {
            @unlink($this->templates_cache . 'templates_caceh.php');
            return false;
        }
        if (isset($this->templates_menu[$name]) === true && md5_file($this->templates_file[$name]) === $this->templates_menu[$name]) {
            return true;
        }
        return false;
    }

    //假如用户用强制性;
    public function assign($phpnew_var, $phpnew_value = null) {
        $php_key = $php_val = null;
        if ($phpnew_var == '')
            return false;
        if (is_array($phpnew_var) === true) {
            foreach ($phpnew_var as $php_key => $php_val) {
                if (is_array($php_val) === true) {
                    $this->assign($php_val);
                }
                if (is_numeric($php_key) === false || $php_key{0} != '_')
                    $this->templates_assign[$php_key] = $php_val;
            }
        }else {
            if (is_numeric($phpnew_var) === false || $phpnew_var{0} != '_')
                $this->templates_assign[$phpnew_var] = $phpnew_value;
        }
    }

    //替换函数;
    protected function replace_html($template) {
        //替换
        $template = preg_replace_callback(
                "/[\n\r\t]*\{templatesub\s+(.+)\}[\n\r\t]*/isU", function($match){return $this->get_contents($match[1]);}, $template
        );
        //直接输出php
        $template = preg_replace_callback('/[\n\r\t]*\<\?php.*\\?\>[\n\r\t]*/isU', function($match){return '[php]'.base64_encode($match[0]).'[/php]';}, $template
        );

        //静态替换
        $template = preg_replace_callback(
                "/[\n\r\t]*\{html\s+(.+?)\}[\n\r\t]*/is", function($match){return $this->get_var($match[1]);}, $template
        );
        //替换模板载入命令
        $template = preg_replace(
                "/[\n\r\t]*\{template\s+\\$(.+)\}[\n\r\t]*/isU", '<? include template(-%-$1); ?>', $template
        );
        $template = preg_replace(
                "/[\n\r\t]*\{template\s+(.+)\}[\n\r\t]*/isU", "<? include template('$1'); ?>", $template
        );

        //过滤 <!--{}-->
        $template = preg_replace("/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $template);
        //替换 PHP 换行符
        $template = str_replace("{LF}", "<?=\"\\n\"?>", $template);
        //替换直接变量输出
        $varRegexp = "((\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)"
                . "(\[[a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff]+\])*)";
        $template = preg_replace("/\{(\\\$[a-zA-Z0-9_\[\]\'\"\$\.\x7f-\xff]+)\}/s", "<?=\\1?>", $template);
        /* $template = preg_replace("/$varRegexp/es", "\$this->addquote('<?=\\1?>')", $template); */
        $template = preg_replace_callback("/\<\?\=\<\?\=$varRegexp\?\>\?\>/s", function($match){return $this->addquote('<?='.$match[1].'?>');}, $template);
        //替换语言包变量
        $template = preg_replace("/\{lang\s+(.+?)\}/is", "<?=\$language['\\1']?>", $template);
        //替换特定函数
        $template = preg_replace_callback(
                "/[\n\r\t]*\{eval\s+(.+?)\}[\n\r\t]*/is", function($match){return $this->stripvtags('<? '.$match[1].'; ?>','');}, $template
        );
        $template = preg_replace_callback(
                "/[\n\r\t]*\{echo\s+(.+?)\}[\n\r\t]*/is", function($match){return $this->stripvtags('<? '.$match[1].'; ?>','');}, $template
        );
        $template = preg_replace_callback(
                "/([\n\r\t]*)\{elseif\s+(.+?)\}([\n\r\t]*)/is", function($match){return $this->stripvtags($match[1].'<? } elseif('.$match[2].') { ?>'.$match[3],'');}, $template
        );
        $template = preg_replace(
                "/([\n\r\t]*)\{else\}([\n\r\t]*)/is", "\\1<? } else { ?>\\2", $template
        );
        //替换循环函数及条件判断语句
        for ($i = 0; $i < 6; $i++) {
            $template = preg_replace_callback(
                    "/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\}[\n\r]*(.+?)[\n\r]*\{\/loop\}[\n\r\t]*/is", function($match){return $this->stripvtags('<? if(is_array('.$match[1].')) { foreach('.$match[1].' as '.$match[2].') { ?>',$match[3].'<? } } ?>');}, $template
            );
            $template = preg_replace_callback(
                    "/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}[\n\r\t]*(.+?)[\n\r\t]*\{\/loop\}[\n\r\t]*/is", function($match){return $this->stripvtags('<? if(is_array('.$match[1].')) { foreach('.$match[1].' as '.$match[2].' => '.$match[3].') { ?>',$match[4].'<? } } ?>');}, $template
            );
            $template = preg_replace_callback(
                    "/([\n\r\t]*)\{if\s+(.+?)\}([\n\r]*)(.+?)([\n\r]*)\{\/if\}([\n\r\t]*)/is", function($match){return $this->stripvtags($match[1].'<? if('.$match[2].') { ?>'.$match[3], $match[4].$match[5].'<? } ?>'.$match[6]);}, $template
            );
        }
        //常量替换
        $template = preg_replace(
                "/\{([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\}/s", "<?=\\1?>", $template
        );
        //删除 PHP 代码断间多余的空格及换行
        $template = preg_replace("/ \?\>[\n\r]*\<\? /s", " ", $template);

        //其他替换
        $template = preg_replace_callback(
                "/\"(http)?[\w\.\/:]+\?[^\"]+?&[^\"]+?\"/", function($match){return $this->transamp($match[0]);}, $template
        );

        $template = preg_replace_callback(
                "/[\n\r\t]*\{block\s+([a-zA-Z0-9_]+)\}(.+?)\{\/block\}/is", function($match){return $this->stripblock($match[1], $match[2]);}, $template
        );
        $template = preg_replace_callback('/[\n\r\t]*\[php\](.*)\[\/php\][\n\r\t]*/isU', function($match){return base64_decode($match[1]);}, $template);
        $template = preg_replace_callback('/<script.+script>/isU', function($match){return '[script]'.base64_encode($match[0]).'[/script]';}, $template);
        if ($this->templates_space === true) {
            $template = preg_replace(
                    array('/\r\n/isU', '/<<<EOF/isU'), array('', "\r\n<<<EOF\r\n"), $template);
        }
        $template = preg_replace_callback('/[\n\r\t]*\[script\](.+)\[\/script\][\n\r\t]*/isU', function($match){return base64_decode($match[1]);}, $template);
        $template = strtr($template, array('-%-' => '$', '\"' => '"', '<style>' => '<style type="text/css">', '<script>' => '<script type="text/javascript">'));
        if ($this->templates_php !== false)
            $template = $this->templates_php . "\r\n" . $template;

        $template = strtr($template, array('include display' => '$this->display'));
        $template = strtr($template, array('../../statics/' => CDN_PATH));
        if ($this->templates_lonlabel === true) {
            $template = strtr($template, array('<?php' => '<?', '<?php echo' => '<?='));
            $template = strtr($template, array('<?' => '<?php ', '<?=' => '<?php echo '));
        }
        $template = strtr($template, $this->replace);
        $this->templates_update +=1;
        return $template;
    }

    //模板引擎需要的函数. 开始.
    protected function get_contents($filename) {
        $this->templates_file[$filename] = $this->get_path($filename);
        if (is_file($this->get_path($filename)) === true) {
            $files = file_get_contents($this->templates_file[$filename], LOCK_EX);
            //替换
            $files = preg_replace_callback(
                    "/[\n\r\t]*\{templatesub\s+(.+)\}[\n\r\t]*/isU", function($match){return $this->get_contents($match[1]);}, $files
            );
            return $files;
        }
        return false;
    }

    protected function get_var($phpnew_name) {
        if (isset($GLOBALS[$phpnew_name]))
            return $GLOBALS[$phpnew_name];
        if (isset($this->templates_assign[$phpnew_name]))
            return $this->templates_assign[$phpnew_name];

        return 'Static variable is not defined';
    }

    protected function transamp($template) {
        $template = str_replace('&', '&amp;', $template);
        $template = str_replace('&amp;amp;', '&amp;', $template);
        $template = str_replace('\"', '"', $template);
        return $template;
    }

    protected function stripvtags($expr, $statement) {
        $expr = str_replace("\\\"", "\"", preg_replace("/\<\?\=(\\\$.+?)\?\>/s", "\\1", $expr));
        $statement = str_replace("\\\"", "\"", $statement);
        return $expr . $statement;
    }

    protected function addquote($var) {
        return str_replace("\\\"", "\"", preg_replace("/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\]/s", "['\\1']", $var));
    }

    protected function stripblock($var, $s) {
        $s = str_replace('\\"', '"', $s);
        $s = preg_replace("/<\?=\\\$(.+?)\?>/", "{\$\\1}", $s);
        preg_match_all("/<\?=(.+?)\?>/e", $s, $constary);
        $constadd = '';
        $constary[1] = array_unique($constary[1]);
        foreach ($constary[1] as $const) {
            $constadd .= '$__' . $const . ' = ' . $const . ';';
        }
        $s = preg_replace("/<\?=(.+?)\?>/", "{\$__\\1}", $s);
        $s = str_replace('?>', "\n\$$var .= <<<EOF\n", $s);
        $s = str_replace('<?', "\nEOF;\n", $s);
        return "<?php\n$constadd\$$var = <<<EOF" . $s . "\nEOF;\n?>";
    }

    //删除缓存;
    public function cache_dele($path = null) {
        if ($path === null) {
            $path = $this->templates_cache;
        }
        $file_arr = scandir($path);
        foreach ($file_arr as $val) {
            if ($val === '.' || $val === '..') {
                continue;
            }
            if (is_dir($path . $val) === true)
                $this->cache_dele($path . $val . '/');
            if (is_file($path . $val) === true && $val !== 'index.html')
                unlink($path . $val);
        }
    }

    // 查错函数;
    public function show() {
        //设置一个通用的函数;
        if (empty($this->templates_debug) === false) {
            $this->templates_debug = implode('<br />', $this->templates_debug);
            echo $this->templates_debug;
            exit();
        }
    }

}

