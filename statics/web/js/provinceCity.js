
//省市联动
(function($){
    /**
     * 省市联动
     * @param {Array} areaData 地区数组 格式：[{pro: '北京', cities: {-1: 北京, 0: 东城区, ...}},{...}]
     * @param {Object} options 一些配置选项
     * @returns {Object} jQuery对象
     */
    $.fn.citySelect = function(areaData, options){
        if(!$.isArray(areaData)) return;
        var opts = $.extend({
            provinceID: -1,
            cityID: -1,
            isShowDefaultVal: true,
            isDealComArea: false
        }, options);
        var $province = $(this).find('select').eq(0);
        var $city = $(this).find('select').eq(1);
        //-1是直辖市信息
        var provicneID = opts.provinceID;
        var cityID = opts.cityID;
        var isShowDefaultVal = opts.isShowDefaultVal;
        var isDealComArea = opts.isDealComArea;
        var defaultData = ['请选择', '-2'];
        var tmpArr = [];

        //增加下拉项到临时数组
        var addOpt = function(val,text,defVal){
            tmpArr.push("<option value='"+val+"' "+(parseInt(defVal,10)==parseInt(val,10)+''?"selected":"")+">"+text+"</option>");
        };

        //省份变更联动城市下拉
        var changeHandler = function(){
            var provinceID = $province.val();
            tmpArr = [];

            //非省【请选择】情况下，不显示地市【请选择】
            provinceID == -2 && isShowDefaultVal && addOpt(defaultData[1],defaultData[0]);
            if(provinceID != -2) {
                $.each(areaData[provinceID]['cities'], function(cid, city) {
                    //是否只显示直辖市
                    if(isDealComArea && provinceID < 4) {
                        addOpt(cityID, areaData[provinceID]['pro'], cityID);
                        return false;
                    }else {
                        cid != -1 && addOpt(cid, city, cityID);
                    }
                });
            }
            
            $city.html(tmpArr.join(''));
            $city.siblings(".inputW").text($city.children('option:selected').text())
        };

        //初始化省份
        var initProvince = function() {
            tmpArr = [];
            //默认省级下拉
            isShowDefaultVal && addOpt(defaultData[1],defaultData[0]);
            $.each(areaData, function(pid,details){
                addOpt(pid,details.pro,provicneID);
            });
            $province.html(tmpArr.join(''));
        };

        //初始化事件
        var init = function() {
            initProvince();
            //省级联动 控制
            $province.on('change', changeHandler);
            changeHandler();
        }

        init();

        return this;
    };
}(jQuery));