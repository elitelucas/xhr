webpackJsonp([63],{"+hM6":function(t,s,e){"use strict";Object.defineProperty(s,"__esModule",{value:!0});var a=e("4YfN"),n=e.n(a),o=e("9rMa"),i=(n()({},Object(o.c)(["accountData"])),n()({},Object(o.b)(["SET_ACCOUNT_DATA"]),{setPsd:function(){var t=this,s={token:localStorage.getItem("token"),psd:t.psd,psd2:t.psd2};t.$vux.loading.show(),t.$http.post(t.urlRequest+"?m=api&c=user&a=setPayPSD",s).then(function(s){if(console.log(s),0==s.status){var e=t.accountData;e.is_set_paypassword="1",t.SET_ACCOUNT_DATA({Obj:e}),t.setSuccese=!1}else s.ret_msg&&""!=s.ret_msg&&t.$vux.toast.show({text:s.ret_msg});t.$vux.loading.hide()}).catch(function(s){console.log(s),t.$vux.loading.hide()})}}),{data:function(){return{psd:"",psd2:"",showPsd:!0,setSuccese:!0}},computed:n()({},Object(o.c)(["accountData"])),mounted:function(){var t=this;this.$nextTick(function(){t.$refs.psd.focus()})},methods:n()({},Object(o.b)(["SET_ACCOUNT_DATA"]),{setPsd:function(){var t=this,s={token:localStorage.getItem("token"),psd:t.psd,psd2:t.psd2};t.$vux.loading.show(),t.$http.post(t.urlRequest+"?m=api&c=user&a=setPayPSD",s).then(function(s){if(console.log(s),0==s.status){var e=t.accountData;e.is_set_paypassword="1",t.SET_ACCOUNT_DATA({Obj:e}),t.setSuccese=!1}else s.ret_msg&&""!=s.ret_msg&&t.$vux.toast.show({text:s.ret_msg});t.$vux.loading.hide()}).catch(function(s){console.log(s),t.$vux.loading.hide()})}}),watch:{psd:function(t,s){var e=this;if(isNaN(t)||-1!=t.indexOf("e")||-1!=t.indexOf("."))this.psd=s;else{for(var a=document.querySelectorAll(".inp span"),n=0;n<a.length;n++)a[n].style.backgroundSize="0";for(var o=0;o<this.psd.length;o++)a[o].style.backgroundSize="auto";6==this.psd.length&&(this.showPsd=!1,this.$nextTick(function(){e.$refs.psd2.focus()}))}},psd2:function(t,s){if(isNaN(t)||-1!=t.indexOf("e")||-1!=t.indexOf("."))this.psd2=s;else{for(var e=document.querySelectorAll(".inp2 span"),a=0;a<e.length;a++)e[a].style.backgroundSize="0";for(var n=0;n<this.psd2.length;n++)e[n].style.backgroundSize="auto";6==this.psd2.length&&(this.psd2!=this.psd?(this.$vux.toast.text("The two password inputs are inconsistent","bottom"),this.psd2=""):this.setPsd())}}}}),d={render:function(){var t=this,s=t.$createElement,a=t._self._c||s;return a("div",[a("div",{staticClass:"headerWrap"},[a("x-header",{staticClass:"header",attrs:{title:"Set fund password"}})],1),t._v(" "),t.setSuccese?[a("div",{directives:[{name:"show",rawName:"v-show",value:t.showPsd,expression:"showPsd"}],staticClass:"inp"},[a("p",[t._v("Please set your fund password for security verification")]),t._v(" "),a("span"),a("span"),a("span"),a("span"),a("span"),a("span"),t._v(" "),a("input",{directives:[{name:"model",rawName:"v-model",value:t.psd,expression:"psd"}],ref:"psd",attrs:{type:"text",maxlength:"6"},domProps:{value:t.psd},on:{input:function(s){s.target.composing||(t.psd=s.target.value)}}})]),t._v(" "),a("div",{directives:[{name:"show",rawName:"v-show",value:!t.showPsd,expression:"!showPsd"}],staticClass:"inp2"},[a("p",[t._v("Please confirm your fund password again")]),t._v(" "),a("span"),a("span"),a("span"),a("span"),a("span"),a("span"),t._v(" "),a("input",{directives:[{name:"model",rawName:"v-model",value:t.psd2,expression:"psd2"}],ref:"psd2",attrs:{type:"text",maxlength:"6"},domProps:{value:t.psd2},on:{input:function(s){s.target.composing||(t.psd2=s.target.value)}}})])]:t._e(),t._v(" "),a("transition",{attrs:{name:"slide"}},[t.setSuccese?t._e():a("div",[a("group",{staticClass:"weui-cells-mt"},[a("div",{staticClass:"complete"},[a("img",{attrs:{src:e("HYT1")}}),t._v(" "),a("h4",[t._v("Fund password set successfully")])])]),t._v(" "),a("div",{staticClass:"submit-btn"},[a("x-button",{attrs:{type:"warn","action-type":"button"},nativeOn:{click:function(s){t.$router.push("/wallet")}}},[t._v("ok")])],1)],1)])],2)},staticRenderFns:[]};var c=e("vSla")(i,d,!1,function(t){e("aYMf")},"data-v-15dfda3c",null);s.default=c.exports},aYMf:function(t,s){}});
//# sourceMappingURL=63.94412a4e66d29b9ce00c.js.map