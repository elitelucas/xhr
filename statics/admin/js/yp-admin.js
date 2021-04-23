var num = 0;
//创建一个iframe窗口
function creatIframe(href, titleName){
    var topWindow=$(window.parent.document);
    var iframe_box = topWindow.find('.admin');
    var iframeBox = iframe_box.find('.show_iframe');
    var show_nav=topWindow.find('#min_title_list'); //获取标题列表
    show_nav.find('li').removeClass("active");
    show_nav.append('<li class="active"><span data-href="'+href+'">'+titleName+'</span><em class="close"></em></li>'); //添加标题

    //计算更多是否显示
    var taballwidth=0,
            $tabNav = topWindow.find(".acrossTab"),
            $tabNavitem = topWindow.find(".acrossTab li"),
            $tabNavmore =topWindow.find(".guna");
    if (!$tabNav[0]){return}
    $tabNavitem.each(function(index, element) {
        taballwidth+=Number(parseFloat($(this).width()))
    });
    var w = $tabNav.width();
    if(1287<=w){
            $tabNavmore.show()}
    else{
            $tabNavmore.hide();
            $tabNav.css({left:0})
    }
    
    iframeBox.hide();
    iframe_box.append('<div class="show_iframe" style="width: 100%;height: 100%;"><iframe scrolling="auto" rameborder="0" src="'+ href + '" name="right" width="100%" height="100%"></iframe></div>');
    
}

function yp_admin_tab(obj){
    if($(obj).attr('_href')){
            var bStop=false;
            var bStopIndex=0;
            var _href=$(obj).attr('_href');  //获取跳转地址
            if($(obj).attr("data-tit")){
                var _titleName=$(obj).attr("data-tit"); //获取菜单名称
            }else{
                var _titleName=$(obj).text(); //获取菜单名称
            }

            var topWindow=$(window.parent.document);
            var show_navLi=topWindow.find("#min_title_list li");
            console.log(_href,_titleName);
            show_navLi.each(function() {
                    if($(this).find('span').attr("data-href")==_href){
                            bStop=true;
                            bStopIndex=show_navLi.index($(this));
                            return false;
                    }
            });
            if(!bStop){
                    creatIframe(_href, _titleName);
            }else{
                var active_navLi=topWindow.find("#min_title_list .active");
                // active_navLi.children("span").html(_titleName);

                show_navLi.removeClass("active").eq(bStopIndex).addClass("active");
                var iframe_box=topWindow.find(".admin");
                iframe_box.find(".show_iframe").hide().eq(bStopIndex).show().find("iframe").attr("src",_href);
            }
    }
}

$(".leftBtn").click(function() {
    
});

//头部选项卡
$(document).on("click","#min_title_list li",function(){
        var bStopIndex=$(this).index();
        var iframe_box=$(".admin");
        $("#min_title_list li").removeClass("active").eq(bStopIndex).addClass("active");
        iframe_box.find(".show_iframe").hide().eq(bStopIndex).show();
});
//关闭按钮
$(document).on("click","#min_title_list li em",function(){
        var aCloseIndex=$(this).parents("li").index();
        $(this).parent().remove();
        $('.admin').find('.show_iframe').eq(aCloseIndex).remove();	
        num==0?num=0:num--;
        tabNavallwidth();
});

$(function() {
    //上一个
    $("#js-tabNav-next").click(function(){
        num == $("#min_title_list").find('li').length-1 ? num = $("#min_title_list").find('li').length-1 : num++;
        toNavPos();
    });
    //下一个
    $("#js-tabNav-prev").click(function(){
        num==0?num=0:num--;
        toNavPos();
    });  
});

function toNavPos(){
    $("#min_title_list").stop().animate({'left':-num*100},100);
}



//得到事件
function getEvent(){
	 if(window.event)    {return window.event;}
	 func=getEvent.caller;
	 while(func!=null){
		 var arg0=func.arguments[0];
		 if(arg0){
			 if((arg0.constructor==Event || arg0.constructor ==MouseEvent
				|| arg0.constructor==KeyboardEvent)
				||(typeof(arg0)=="object" && arg0.preventDefault
				&& arg0.stopPropagation)){
				 return arg0;
			 }
		 }
		 func=func.caller;
	 }
	 return null;
}
//阻止冒泡
function cancelBubble()
{
	var e=getEvent();
	if(window.event){
		//e.returnValue=false;//阻止自身行为
		e.cancelBubble=true;//阻止冒泡
	 }else if(e.preventDefault){
		//e.preventDefault();//阻止自身行为
		e.stopPropagation();//阻止冒泡
	 }
} 