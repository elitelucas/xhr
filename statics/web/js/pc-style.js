/**
 * Created by win10 on 2017/8/17.
 */
$(function(){
    $(".content-nav").on("click","li",function(){
        $(this).addClass("active").siblings().removeClass("active");
        $(".layout > .content-room ").eq($(this).index()).show().siblings().hide();
    })
    $(".my-fl > ul >li").on("click",function(){
        $(this).children("a").addClass("active");
        $(this).siblings().children("a").removeClass("active");
        $(".my-fl > ul >li").find(".jqShow").removeClass("ico-bot2").addClass("ico-bot1");
        $(".my-fl > ul >li").children("ul").hide();
        if($(this).children("ul").css("display") =="none"){
            $(this).children("ul").show()
            $(this).find(".jqShow").removeClass("ico-bot1").addClass("ico-bot2");

        }else{
            $(this).children("ul").hide();
            $(this).find(".jqShow").removeClass("ico-bot2").addClass("ico-bot1");
        }

    })
})