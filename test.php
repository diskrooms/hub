<?php 
_registerDingtalkCallback('4f7775be54223bda93d91757a931459d');

function _registerDingtalkCallback($access_token = ''){
    $registerCallbackUrl = 'https://oapi.dingtalk.com/call_back/register_call_back?access_token='.$access_token;
    $postData = array(
        "call_back_tag"=>array("user_leave_org"),
        "token"=>"123456",
        "aes_key"=>"xxxxxxxxlvdhntotr3x9qhlbytb18zyz5zxxxxxxxxx",
        "url"=>"http://dd.ehangoa.com/dingtalk.php?opt=callback"
    );
    $postJson = json_encode($postData);
    //echo $postJson;
    print_r(requestPost($registerCallbackUrl, $postJson, array('Accept:application/json','Content-Type:application/json')));
    exit();
}


/**
 * POST请求数据 如果有curl扩展 就使用curl进行请求 如果没有相应模块 就是用file_get_contents函数
 * url 		要请求的url地址
 * data		发送数据 数组格式或者json字符串 '{"abc":"123","def":"123"}'
 * header	自定义请求头	array('accept:application/json','content-type:application/json')
 * timeout 	超时时间
 * count	请求总数(超时重发)
 */
function requestPost($url = '', $data = array(), $header = array(), $timeout = 6, $count = 3){
    static $index = 0 ;
    $index++;
    if(empty($url) || (strpos($url, 'http') === false)){
        throw new exception('缺少url参数或者url格式不合法,url应包含http或者https协议');
        exit();
    } else {
        //如果是https协议的url,检查openssl组件
        //用检测函数存在的方式进行检查 检测组件是否加载的方式不一定准确
        //有些组件被直接编译进了php,并不一定是通过加载组件的方式进行加载的，比如说 openssl
        if(strpos($url, 'https') !== false){
            if(!function_exists('openssl_open')){
                throw new exception('缺少openssl组件支持,请确保openssl扩展已经正确加载或已经编译进php');
                exit();
            }
        }
    }
    $ch = null;
    if(function_exists('curl_init')){
        $ch = curl_init();
    }
    if($ch){
        //初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE); //不验证 https 证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        if(empty($header)){
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
        } else {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        //curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1'); //代理服务器地址
        //curl_setopt($ch, CURLOPT_PROXYPORT,'8888'); 		//代理服务器端口
        //curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, 1);				// post方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);	// post数据 php数组格式或字符串
        $content = curl_exec($ch);
        if($content === false){
            if(curl_errno($ch) == CURLE_OPERATION_TIMEDOUT){
                if($index < $count){
                    //请求重发
                    requestPost($url,$data,$header);
                }
            }
            
        }
        
        curl_close($ch);
    } else {
        //检查 allow_url_fopen 配置
        //开启返回 "1" 关闭返回 ""
        //allow_url_fopen的修改范围是PHP_INI_SYSTEM，这个选项只能在php.ini或httpd.conf中修改，不能在脚本中修改
        if(ini_get('allow_url_fopen') == ''){
            //ini_set('allow_url_fopen', '1');
            throw new Exception('请检查allow_url_fopen配置项是否在php.ini中开启');
            exit();
        }
        $data = http_build_query($data);
        $context = array(
            'http'=>array(
                'method'=>'POST',
                'content'=>$data
            )
        );
        $context  = stream_context_create($context);
        $content = file_get_contents($url,false,$context);
    }
    return $content;
}
?>