webpackJsonp([59],{"0hAs":function(t,s,a){"use strict";Object.defineProperty(s,"__esModule",{value:!0});var i=a("4YfN"),e=a.n(i),v=a("9rMa"),l=(e()({},Object(v.c)(["accountData"])),{data:function(){return{leadList:[],elseList:[],avatar:"",isTourist:!1}},created:function(){},mounted:function(){this.accountData&&1===this.accountData.is_tourist&&(this.isTourist=!0),this.getLeadList()},computed:e()({},Object(v.c)(["accountData"])),methods:{getLeadList:function(){var t=this;this.$vux.loading.show(),this.$http.post(this.urlRequest+"?m=api&c=user&a=bet_rank",{}).then(function(s){t.$vux.loading.hide(),0==s.status?t.leadList=s.data:s.ret_msg&&""!=s.ret_msg&&t.$vux.toast.show({text:s.ret_msg})})},setFollow:function(t,s){var a=this;this.isTourist?this.$vux.toast.show({text:"Visitors can't follow"}):this.$http.post(this.urlRequest+"?m=api&c=user&a=addFollowUser",{user_id:t}).then(function(t){t.ret_msg&&""!=t.ret_msg&&a.$vux.toast.show({text:t.ret_msg}),0==t.status&&(a.leadList[s].is_follow=1),a.$vux.loading.hide()})}}}),n={render:function(){var t=this,s=t.$createElement,i=t._self._c||s;return i("div",[i("div",{staticClass:"topPanel"},[i("div",{staticClass:"topTitle"},[i("svg",{staticClass:"vux-x-icon vux-x-icon-ios-arrow-left leftArrow",attrs:{type:"ios-arrow-left",xmlns:"http://www.w3.org/2000/svg",width:"24",height:"24",viewBox:"0 0 512 512"},on:{click:function(s){t.$router.go(-1)}}},[i("path",{attrs:{d:"M352 115.4L331.3 96 160 256l171.3 160 20.7-19.3L201.5 256z"}})]),t._v(" "),i("div",[t._v("七日英雄榜")])]),t._v(" "),t.leadList.length>0?i("div",{staticClass:"qiansan"},[i("div",{staticClass:"qsPanel"},[t.leadList[1].avatar?i("img",{staticClass:"avaImg",attrs:{src:t.imgRequest+t.leadList[1].avatar,alt:""}}):i("img",{staticClass:"avaImg",attrs:{src:a("keT3")}}),t._v(" "),i("div",[t._v(t._s(t.leadList[1].nickname)+" "),i("img",{attrs:{src:t.imgRequest+t.leadList[1].level.icon,alt:""}})]),t._v(" "),i("div",[i("s",{staticClass:"ybIcon"}),t._v(t._s(parseInt(t.leadList[1].bet_money)))]),t._v(" "),0==t.leadList[1].is_follow?i("div",{staticClass:"gzBtn vux-1px",on:{click:function(s){t.setFollow(t.leadList[1].user_id,1)}}},[i("svg",{staticClass:"vux-x-icon vux-x-icon-ios-plus-empty gzIcon",attrs:{type:"ios-plus-empty",xmlns:"http://www.w3.org/2000/svg",width:"24",height:"24",viewBox:"0 0 512 512"}},[i("path",{attrs:{d:"M384 265H264v119h-17V265H128v-17h119V128h17v120h120v17z"}})]),t._v("\n                  \n                  关注\n                  ")]):i("div",[t._v("\n                  已关注\n                  ")])]),t._v(" "),i("div",{staticClass:"qsPanel"},[t.leadList[0].avatar?i("img",{staticClass:"avaImg",attrs:{src:t.imgRequest+t.leadList[0].avatar,alt:""}}):i("img",{staticClass:"avaImg",attrs:{src:a("keT3")}}),t._v(" "),i("div",[t._v(t._s(t.leadList[0].nickname)+" "),i("img",{attrs:{src:t.imgRequest+t.leadList[0].level.icon,alt:""}})]),t._v(" "),i("div",[i("s",{staticClass:"ybIcon"}),t._v(t._s(parseInt(t.leadList[0].bet_money)))]),t._v(" "),0==t.leadList[0].is_follow?i("div",{staticClass:"gzBtn vux-1px",on:{click:function(s){t.setFollow(t.leadList[0].user_id,0)}}},[i("svg",{staticClass:"vux-x-icon vux-x-icon-ios-plus-empty gzIcon",attrs:{type:"ios-plus-empty",xmlns:"http://www.w3.org/2000/svg",width:"24",height:"24",viewBox:"0 0 512 512"}},[i("path",{attrs:{d:"M384 265H264v119h-17V265H128v-17h119V128h17v120h120v17z"}})]),t._v("\n                  关注\n                  ")]):i("div",[t._v("\n                  已关注\n                  ")])]),t._v(" "),i("div",{staticClass:"qsPanel"},[t.leadList[2].avatar?i("img",{staticClass:"avaImg",attrs:{src:t.imgRequest+t.leadList[2].avatar,alt:""}}):i("img",{staticClass:"avaImg",attrs:{src:a("keT3")}}),t._v(" "),i("div",[t._v(t._s(t.leadList[2].nickname)+" "),i("img",{attrs:{src:t.imgRequest+t.leadList[2].level.icon,alt:""}})]),t._v(" "),i("div",[i("s",{staticClass:"ybIcon"}),t._v(t._s(parseInt(t.leadList[2].bet_money)))]),t._v(" "),0==t.leadList[2].is_follow?i("div",{staticClass:"gzBtn vux-1px",on:{click:function(s){t.setFollow(t.leadList[2].user_id,2)}}},[i("svg",{staticClass:"vux-x-icon vux-x-icon-ios-plus-empty gzIcon",attrs:{type:"ios-plus-empty",xmlns:"http://www.w3.org/2000/svg",width:"24",height:"24",viewBox:"0 0 512 512"}},[i("path",{attrs:{d:"M384 265H264v119h-17V265H128v-17h119V128h17v120h120v17z"}})]),t._v("\n                  关注\n                  ")]):i("div",[t._v("\n                  已关注\n                  ")])])]):t._e()]),t._v(" "),t.leadList.length>0?i("div",{staticClass:"listPanel"},t._l(t.leadList.slice(3),function(s,e){return i("div",{key:e,staticClass:"listBox vux-1px-b"},[i("div",{staticClass:"listBoxLeft"},[i("div",[t._v(t._s(e+4))]),t._v(" "),s.avatar?i("img",{attrs:{src:t.imgRequest+s.avatar,alt:""}}):i("img",{attrs:{src:a("keT3"),alt:""}}),t._v(" "),i("div",[i("span",[t._v(t._s(s.nickname))]),t._v(" "),i("img",{attrs:{src:t.imgRequest+s.level.icon,alt:""}}),t._v(" "),i("div",[i("s",{staticClass:"ybIcon"}),t._v(t._s(parseInt(s.bet_money)))])])]),t._v(" "),i("div",[0==s.is_follow?i("div",{staticClass:"gzBtn1 vux-1px",on:{click:function(a){t.setFollow(s.user_id,e+3)}}},[i("svg",{staticClass:"vux-x-icon vux-x-icon-ios-plus-empty gzIcon1",attrs:{type:"ios-plus-empty",xmlns:"http://www.w3.org/2000/svg",width:"24",height:"24",viewBox:"0 0 512 512"}},[i("path",{attrs:{d:"M384 265H264v119h-17V265H128v-17h119V128h17v120h120v17z"}})]),t._v("\n                  关注\n              ")]):i("div",{staticClass:"ygz"},[i("svg",{staticClass:"vux-x-icon vux-x-icon-ios-checkmark-empty",attrs:{type:"ios-checkmark-empty",xmlns:"http://www.w3.org/2000/svg",width:"24",height:"24",viewBox:"0 0 512 512"}},[i("path",{attrs:{d:"M223.9 329.7c-2.4 2.4-5.8 4.4-8.8 4.4s-6.4-2.1-8.9-4.5l-56-56 17.8-17.8 47.2 47.2L340 177.3l17.5 18.1-133.6 134.3z"}})]),t._v("\n                  已关注\n              ")])])])})):t._e()])},staticRenderFns:[]};var o=a("vSla")(l,n,!1,function(t){a("2GNK")},"data-v-20ef9c74",null);s.default=o.exports},"2GNK":function(t,s){}});
//# sourceMappingURL=59.4eebaf50bc11b17894ca.js.map