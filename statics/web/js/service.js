/**
 * Created by HCHT- on 2016/12/9.
 */
var port ="7273";
var url = "wss://"+window.location.hostname+":"+port;
// var url = "ws://47.90.125.237:"+port;
$(function(){
    initWebSocket();
    setTimeout(function() {
        $('.customService').css("padding-bottom",$(".customNews").outerHeight()+98);
        var pageHeight =$(".customService ul").outerHeight();
        $('.customService').scrollTop(pageHeight);
    }, 100);
    $("#sUpdate").live("click", function(){
        var text =$(".textArea").text();
        if(text ==""){
            fnSet.alert("请输入内容");
        }else{
            
            var contentHtml = '<li><div class="girl right"><h3>'+ userinfo.nickname +'</h3><img src="'+ userinfo.head_url +'"/></div><div class="customContent right"><pre>'+ text +'</pre></div></li>';
            $(".customService ul").append(contentHtml);
            var param = {
                "commandid": "4002",
                "content": text
            };
            wsSendMsg(param);
            addMsgCues();
            $(".textArea").text("");
        }
    })

    $(".textArea").live("keyup",function(e){
        e = e? e : (window.event ? window.event : null);
        if(e.keyCode==13)//Enter
        {
            document.getElementById("sUpdate").click();
        }
    })
    $(".textArea").live("input", function(){
        $('.customService').css("padding-bottom",$(".customNews").outerHeight()+98);
        setTimeout(function() {

            var pageHeight =$(".customService ul").outerHeight();
            $('.customService').scrollTop(pageHeight);
        }, 100);
    })

    function addMsgCues()
    {
        $.ajax({
            url: "/index.php?m=api&c=app&a=addMsgCues",
            dataType: 'json',
            error: function () {
                console.log("服务器错误")
            },
            success: function (data) {
                console.log(data);
            }
        });
    }
})