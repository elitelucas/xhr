webpackJsonp([33],{g5vS:function(t,e){},x1MD:function(t,e,n){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var a=n("/dSo"),i=n.n(a),o={data:function(){return{info:null}},created:function(){this._getTel()},methods:{_getTel:function(){var t=this;t.$vux.loading.show(),t.$http.post(t.urlRequest+"?m=api&c=app&a=customerType").then(function(e){t.$vux.loading.hide(),0==e.status&&(t.info=e.info)}).catch(function(t){console.log(t)})},copy:function(t,e){var n=this;e="weixin"==e?"line":"Facebook";var a=new i.a(".tag-read");a.on("success",function(t){n.$vux.alert.show({text:"Copy succeeded",content:e+" is copied successfully. You can open it by adding or following "+e}),a.destroy()}),a.on("error",function(t){n.$vux.alert.show({text:"This browser does not support automatic replication"}),a.destroy()})}}},c={render:function(){var t=this,e=t.$createElement,n=t._self._c||e;return n("div",[n("div",{staticClass:"headerWrap"},[n("x-header",{staticClass:"header",attrs:{title:"contact us"}})],1),t._v(" "),t.info?n("group",[n("cell",{staticClass:"tag-read",attrs:{title:"line","is-link":"","data-clipboard-text":"365server"},nativeOn:{click:function(e){t.copy(e,t.info[0].name)}}},[n("i",{staticClass:"cell-icon cell-icon-weixin",attrs:{slot:"icon"},slot:"icon"}),t._v(t._s("365server"))]),
t._v(" "),n()],1):t._e()],1)},staticRenderFns:[]};var s=n("vSla")(o,c,!1,function(t){n("g5vS")},"data-v-8acc8a32",null);e.default=s.exports}});
//# sourceMappingURL=33.0dce3179e622779eeb30.js.map