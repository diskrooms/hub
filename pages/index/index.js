import Utils from '../../assets/js/utils.js';

let app = getApp();
Page({
  data: {
    show: false,        //是否显示域名填写框
    showAddServerModal:false,  //是否显示添加服务器对话框
    validDomain:false,  //JS正则匹配所填写域名是否合法
    domain:'',          //存储填写域名值
    listData:[
      {"ip":"39.98.73.255","desc":"阿里云服务器","opt":"查看用户"},
      {"ip":"198.181.39.45","desc":"搬瓦工","opt":"查看用户"}
    ],
    showAuthUser:false,
  },
  
  Utils: new Utils(),

  onLoad() {
      let that = this
      
      //查找缓存中是否已经存放域名 未存放则弹出添加域名对话框提示添加
      dd.getStorage({
        key: 'hubDomain',
        success: function(res) {
          if(!res.data){
            that.setData({'show':true})
          } else {
            that.setData({'domain':res.data})
            console.log(res)
            //请求接口获取服务资源数据
            dd.getAuthCode({
                success:function(authRes){
                  dd.httpRequest({
                    url: 'http://'+res.data+'/dingtalk.php?opt=listServer&code='+authRes.authCode,
                    method: 'GET',
                    data: {},
                    dataType: 'json',
                    timeout:2000,
                    success: function(res) {

                    },
                    fail: function(res) {
                      console.log(res)
                    }
                  });
                },
                fail:function(err){

                }
            });
          }
        },
        fail: function(res){
          dd.alert({content: res.errorMessage});
        }
      });
  },

  onShow(){
      dd.getAuthCode({
          success:function(res){
            //dd.alert({content: res.authCode});
          },
          fail:function(err){

          }
      })   
  },

  //打开“添加服务器”对话框
  showAddServerModal(){
    this.setData({"showAddServerModal":true})
  },

  //关闭“添加服务器”对话框
  closeAddServerModal(){
      this.setData({"showAddServerModal":false})
  },

  //执行添加服务器操作


  //执行添加域名操作
  addDomain(e){
      let that = this
      let domain = e.detail.value.domain

      //获取小程序免登code
      dd.getAuthCode({
          success:function(res){
            //console.log(res) 662e6b09e81633ca9784795a270a2728
            let code = res.authCode
            //测试域名是否部署了服务
            dd.httpRequest({
              url: 'http://'+domain+'/dingtalk.php?opt=test&code='+code,
              method: 'POST',
              data: {'opt':'test','code':code},
              dataType: 'json',
              timeout:2000,
              success: function(res) {
                dd.setStorage({
                  key: 'hubDomain',
                  data:domain,
                  success: function() {
                    that.setData({'show':false})
                    dd.alert({content: '添加成功'});
                  },
                  fail: function(res){
                    dd.alert({content: res.errorMessage});
                  }
                });
              },
              fail: function(res) {
                console.log(res)
              },
              complete: function(res) {
                console.log(res)
              }
            });
          },
          fail:function(err){

          }
      });

      


      
  },

  //对实时输入的 url 作检测 看是否是合法的域名
  checkDomain(e){
    let domain = e.detail.value
    if(this.Utils.validDomain(domain)){
      this.setData({"validDomain":true})
    } else {
      this.setData({"validDomain":false})
    }
  },

  //显示授权用户面板
  showAuthUsers(e){
      this.setData({"showAuthUser":true})
  },
  
  //关闭授权用户面板
  hiddenAuthUsers(e){
      this.setData({"showAuthUser":false})
  },

  addAuthUser(e){
    this.setData({"showAuthUser":false})
  }
});