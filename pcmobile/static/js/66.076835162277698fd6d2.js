webpackJsonp([66],{tnoL:function(t,e){},wgKh:function(t,e,a){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var n=a("4YfN"),i=a.n(n),s=a("9rMa"),c=(i()({},Object(s.c)(["bankedData"])),{data:function(){return{bank_id:null,bank:"",account:0}},created:function(){},mounted:function(){this.bank_id=this.$route.query.bank_id,this.bank=this.$route.query.bank,this.account=this.$route.query.account},computed:i()({},Object(s.c)(["bankedData"])),methods:{goToWallet:function(){this.$router.push({path:"/wallet"})}}}),r={render:function(){var t=this,e=t.$createElement,a=t._self._c||e;return a("div",[a("div",{staticClass:"headerWrap"},[a("x-header",{staticClass:"header",attrs:{title:"Binding succeeded"}})],1),t._v(" "),t._m(0),t._v(" "),1===this.bank_id?a("group",{staticClass:"weui-cells-mt"},[a("cell",{attrs:{title:t.bank[0],value:t.account.replace(/\s/g,"").replace(/(.{4})/g,"$1 ")}})],1):t._e(),t._v(" "),a("div",{staticClass:"submit-btn"},[a("x-button",{staticClass:"weui-btn_radius weui-btn_minRadius",attrs:{type:"warn","action-type":"button"},nativeOn:{click:function(e){return t.goToWallet(e)}}},[t._v("ok")])],1)],1)},staticRenderFns:[function(){var t=this.$createElement,e=this._self._c||t;return e("div",{staticClass:"complete"},[e("img",{attrs:{src:a("HYT1")}}),this._v(" "),e("p",[this._v("Binding succeeded")])])}]};var u=a("vSla")(c,r,!1,function(t){a("tnoL")},"data-v-11a652f6",null);e.default=u.exports}});
//# sourceMappingURL=66.076835162277698fd6d2.js.map