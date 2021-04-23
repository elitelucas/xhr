<?php

!defined('IN_SNYNI') && die('Access Denied!');

class Page
{
    public $pagesize;  //每页显示的记录数
    public $count;     //总记录数
    public $pagecount; //总页数
    public $offer;     //每页的起始位置（偏移量）
    public $page;      //当前页
    public $param;       //参数
    private $url;
    private $generate_url;

    public function __construct($count, $pagesize, $url, $array = array())
    {
        $this->count = $count;
        $this->pagesize = $pagesize;
        $this->url = $url;
        $this->param = $array;
        $this->generate_url = htmlspecialchars_decode($this->pageurl($this->url, "", $this->param));
    }

    public function show()
    {

        if ($this->count == 0) {
            $this->offer = 0;
            return '';
        }
        //求出总页数
        $this->pagecount = ceil($this->count / $this->pagesize);

        //当前页
        $this->page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;

        //越界处理
        if ($this->page < 1) {
            $this->page = 1;
        }
        if ($this->page > $this->pagecount) {
            $this->page = $this->pagecount;
        }
        //求得偏移量
        $this->offer = ($this->page - 1) * $this->pagesize;

        //上一页，和下一页
        $pageprev = $this->page - 1;
        $pagenext = $this->page + 1;

        if ($pageprev < 1) {
            $pageprev = 1;
        }
        if ($pagenext > $this->pagecount) {
            $pagenext = $this->pagecount;
        }

        //防百度分页
        $startPage = 1;
        $endPage = 1;

        //当当前页数小于等于3时，和大于3时
        if ($this->page <= 3) {
            $startPage = 1;
        } else {
            $startPage = $this->page - 2;
        }


        if ($startPage < 1) {
            $startPage = 1;
        }

        $endPage = $startPage + 4;

        //当起始页大于最后页减4时
        if ($startPage > $endPage - 4) {
            $startPage = $endPage - 4;
        }
        //当最后的页数大于总页数时
        if ($endPage > $this->pagecount) {
            $endPage = $this->pagecount;
        }
/*
        //分页方案-a
        //生成数字页码
        $pageNum = '';
        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($this->page == $i) {
                $pageNum .= "<li id='select'><a class='current' href='" . $this->pageurl($this->url, $i, $this->param) . "'>{$i}</a></li>";
            } else {
                $pageNum .= "<li><a href='" . $this->pageurl($this->url, $i, $this->param) . "'>{$i}</a></li>";
            }
        }
        //生成分页链接
        $pageStr = <<<EOT
                <ul>
                <li class='count'><a>当前有{$this->count}条记录</a></li>
                <li class='count'><a>共有{$this->pagecount}页</a></li>
                <li><input id="skip_text" type="text" style="width: 70px"></li>
                <li><a id="skip_page" style="cursor: pointer">跳页</a></li>
                <li><a href='{$this->pageurl($this->url, 1, $this->param)}'>首页</a></li>
                <li><a href='{$this->pageurl($this->url, $pageprev, $this->param)}'>上一页</a></li>
                {$pageNum}
                <li><a href='{$this->pageurl($this->url, $pagenext, $this->param)}'>下一页</a></li>
                <li><a href='{$this->pageurl($this->url, $this->pagecount, $this->param)}'>尾页</a></li>
                </ul>
                <script type="text/javascript">
                    function skip_page() {
                         var number = parseFloat($("#skip_text").val());
                         if( 0< number && number<= $this->pagecount){
                            var url = "$this->generate_url"+"&page="+number;
                            location.href=url;        
                         }else{
                            alert("请输入已有范围内的页码");     
                         }
                    }
                    $("#skip_page").click(skip_page);
                </script>
EOT;
*/

        //分页方案-b
        //分页禁止点击的样式-a
        if ($this->page == 1) {
            $url_first_a = 'javascript:;';
            $url_pre_a = 'javascript:;';
            $unable_class_a = 'unable';
        } else {
            $url_first_a = $this->pageurl($this->url, 1, $this->param);
            $url_pre_a = $this->pageurl($this->url, $pageprev, $this->param);
            $unable_class_a = '';
        }

        //分页禁止点击的样式-b
        if ($this->page == $this->pagecount) {
            $url_last_b = 'javascript:;';
            $url_next_b = 'javascript:;';
            $unable_class_b = 'unable';
        } else {
            $url_last_b = $this->pageurl($this->url, $this->pagecount, $this->param);
            $url_next_b = $this->pageurl($this->url, $pagenext, $this->param);
            $unable_class_b = '';
        }
        
        //生成分页链接        
        $pageStr = <<<EOT
                <div class="right back-page">
                    <span class="left">共&nbsp;{$this->count}&nbsp;条记录&nbsp;&nbsp;</span>

                    <select class="left" onchange="//showUsersPre(1,this.value)" style="display:none;">
                        <option value="5">每页5条</option>
                        <option value="10" selected="selected">每页10条</option>
                        <option value="20">每页20条</option>
                    </select>

                    <input type="text" value="" id="skip_text" class="inputVal"><button class="GOButton" onclick="skip_page()">GO</button>
                    <span class="back-page-num left">{$this->page}/{$this->pagecount}</span>

                    <a href="{$url_first_a}" title="首页" class="iconfont left {$unable_class_a}"><i class="fa fa-angle-double-left"></i> </a>
                    <a href="{$url_pre_a}" title="上一页" class="iconfont left {$unable_class_a}"><i class="fa fa-angle-left"></i> </a>

                    <a href="{$url_next_b}" title="下一页" class="iconfont left {$unable_class_b}"><i class="fa fa-angle-right"></i> </a>
                    <a href="{$url_last_b}" title="尾页" class="iconfont left {$unable_class_b}"><i class="fa fa-angle-double-right"></i> </a>
                </div>

                <script type="text/javascript">
                    function skip_page() {
                         var number = parseFloat($("#skip_text").val());
                         if( 0< number && number<= $this->pagecount){
                            var url = "$this->generate_url"+"&page="+number;
                            location.href=url;        
                         }else{
                            alert("请输入已有范围内的页码");     
                         }
                    }
                    $("#skip_page").click(skip_page);
                </script>
EOT;
        return $this->count <= $this->pagesize ? '' : $pageStr;
    }

    /**
     * 返回分页路径
     *
     * @param $page 当前页
     * @param $array 需要传递的数组，用于增加额外的方法
     * @return 完整的URL路径
     */
    public function pageurl($url, $page, $array = array())
    {
        if (is_array($array)) {
            $array['page'] = $page;
            if (strpos($url, "?") === false) {
                $url .= "?";
            }
            foreach ($array as $k => $v) {
                if (trim(strval($v)) != "") {
                    $url .= "&" . "$k=" . $v;
                }
            }
            $url = str_replace("?&", "?", $url);
        }
        return htmlspecialchars($url);
    }

}

?>