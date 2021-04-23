$(function() {
    setTimeout(function() {
        $('.customService').css("padding-bottom",$(".customNews").outerHeight()+98);
        var pageHeight =$(".customService ul").outerHeight();
        $('.customService').scrollTop(pageHeight);
    }, 100);
    
    //发送
    $("#sUpdate").live("click", function(){
        var text =$(".textArea").text();
        if(text ==""){
            fnSet.alert("请输入内容");
        }else{
            var contentHtml = '<li><div class="right"><h3><span></span><label>客服</label></h3><p>'+ text +'</p></div></li>';
            $(".serContent ul").append(contentHtml);
            var param = {
                "commandid": "4102",
                "client_id": client_id,
                "content": text
            };
            
            //调用父级方法发送消息
            parent.wsSendMsg(param);
            $(".textArea").text("");
            
            //回复内容
            console.log(param);
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
        $('.serContent').css("padding-bottom",$(".serFs").outerHeight()+98);
        setTimeout(function() {
            var pageHeight =$(".serContent ul").outerHeight();
            $('.serContent').scrollTop(pageHeight);
        }, 100);
    });
    
});

