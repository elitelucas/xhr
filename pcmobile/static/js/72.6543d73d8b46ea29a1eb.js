webpackJsonp([72],{"+TIP":function(t,e){},Q9xm:function(t,e,s){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var a={data:function(){return{myCondition:!1,rules:[]}},created:function(){this.redpacketId=this.$route.query.redpacketId,this.redRule()},mounted:function(){},methods:{redRule:function(){var t=this,e={token:localStorage.getItem("token"),redpacket_id:this.redpacketId};this.$vux.loading.show(),this.$http.post(this.urlRequest+"?m=api&c=redpacket&a=redpacketRules",e).then(function(e){0==e.status?(t.$vux.loading.hide(),console.log(e),t.rules=e):e.ret_msg&&""!=e.ret_msg&&t.$vux.toast.show({text:e.ret_msg})}).catch(function(e){t.$vux.loading.hide(),t.$vux.toast.show({text:"Data request timed out"})})}}},i={render:function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"red-wrap"},[s("div",{staticClass:"headerWrap"},[s("x-header",{staticClass:"header"},[t._v("活动规则")])],1),t._v(" "),s("group",t._l(t.rules.redpacket_rules_arr,function(t,e){return s("cell",{key:e,attrs:{title:t}})})),t._v(" "),s("div",{staticClass:"valid-time text-gray"},[t._v("\n\t\t\t红包活动时间("),s("span",{staticClass:"text-red"},[t._v(t._s(t.rules.redpacket_duration))]),t._v(")内需满足会员组条件并同时满足其他条件中一个方可领取红包。\n\t\t")]),t._v(" "),s("div",{staticClass:"condition text-red",on:{click:function(e){t.myCondition=!0}}},[t._v("查看我的条件")]),t._v(" "),s("x-dialog",{staticClass:"global-dialog rule-dialog",model:{value:t.myCondition,callback:function(e){t.myCondition=e},expression:"myCondition"}},[s("div",{staticClass:"dialog-content"},[s("h1",{staticClass:"dialog-tit"},[t._v("我的条件")]),t._v(" "),s("div",{staticClass:"rule-content clearfix"},[s("ul",t._l(t.rules.self_reach_arr,function(e,a){return s("li",{key:a},[t._v(t._s(e.rules_txt)),1==e.is_reach?s("span",{staticClass:"request"},[s("i",{staticClass:"icon icon-yes"}),t._v("满足")]):t._e()])}))]),t._v(" "),s("x-button",{staticClass:"weui-btn_radius",attrs:{type:"warn"},nativeOn:{click:function(e){t.myCondition=!1}}},[t._v("我知道了")])],1)])],1)},staticRenderFns:[]};var n=s("vSla")(a,i,!1,function(t){s("+TIP")},"data-v-016fb002",null);e.default=n.exports}});
//# sourceMappingURL=72.6543d73d8b46ea29a1eb.js.map