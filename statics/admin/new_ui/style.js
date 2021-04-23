
// define(['domReady!','jquery','dialog', 'validate','messages'],function(doc,$){
    $(function(){
        var title,
            titleBox = $("<div class='titlebox'></div>");
        $('body').on('mouseover','[data-title]',function(event){
            title = $(this).attr("data-title");
            titleBox.text(title);
            $(this).attr("title","");
            titleBox.appendTo("body").fadeIn(300);
            $(this).mousemove(function(event){
                var x = event.pageX,
                    y = event.pageY;
                titleBox.css({
                    "left": x-10,
                    "top": y+25
                });
            });
        }).on('mouseout','[data-title]',function(){
            $(this).data("title",titleBox.text());
            titleBox.remove();
        });
    });
