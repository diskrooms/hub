<?php

function getRandomStr($length = 16)
{
    
    $str = "";
    $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
    $max = strlen($str_pol) - 1;
    for ($i = 0; $i < $length; $i++) {
        $str .= $str_pol[mt_rand(0, $max)];
    }
    return $str;
}

class PKCS7Encoder
{
    public static $block_size = 32;
    
    function encode($text)
    {
        $block_size = PKCS7Encoder::$block_size;
        $text_length = strlen($text);
        $amount_to_pad = PKCS7Encoder::$block_size - ($text_length % PKCS7Encoder::$block_size);
        if ($amount_to_pad == 0) {
            $amount_to_pad = PKCS7Encoder::block_size;
        }
        $pad_chr = chr($amount_to_pad);
        $tmp = "";
        for ($index = 0; $index < $amount_to_pad; $index++) {
            $tmp .= $pad_chr;
        }
        return $text . $tmp;
    }
    
    function decode($text)
    {
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > PKCS7Encoder::$block_size) {
            $pad = 0;
        }
        return substr($text, 0, (strlen($text) - $pad));
    }
    
}


class Prpcrypt
{
    public $key;
    
    function __construct($k)
    {
        $this->key = base64_decode($k . "=");
    }
    
    public function encrypt($text, $corpid)
    {
        
        try {
            //获得16位随机字符串，填充到明文之前
            $random = getRandomStr();
            $text = $random . pack("N", strlen($text)) . $text . $corpid;
            
            // 网络字节序
           /*  $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            $iv = substr($this->key, 0, 16);
            //使用自定义的填充方式对明文进行补位填充
            $pkc_encoder = new PKCS7Encoder;
            $text = $pkc_encoder->encode($text);
            mcrypt_generic_init($module, $this->key, $iv);
            //加密
            $encrypted = mcrypt_generic($module, $text);
            mcrypt_generic_deinit($module);
            mcrypt_module_close($module); */


            //使用phpseclib代替mcrypt
            include '../vendor/autoload.php';
            $aes = new \phpseclib\Crypt\AES();
            $aes->setKey($this->key);
            $iv = substr($this->key, 0, 16);
            $aes->setIV($iv);
            $aes->disablePadding();         //非常重要 这个bug调试了两天
            $pkc_encoder = new PKCS7Encoder;
            $text = $pkc_encoder->encode($text);
            $encrypted = $aes->encrypt($text);
            
            //print(base64_encode($encrypted));
            //使用BASE64对加密后的字符串进行编码
            return array(ErrorCode::$OK, base64_encode($encrypted));
        } catch (Exception $e) {
            print $e;
            return array(ErrorCode::$EncryptAESError, null);
        }
    }
    
    public function decrypt($encrypted, $corpid)
    {
        
        try {
            $ciphertext_dec = base64_decode($encrypted);

            /*$module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            $iv = substr($this->key, 0, 16);
            mcrypt_generic_init($module, $this->key, $iv);
            
            $decrypted = mdecrypt_generic($module, $ciphertext_dec);
            mcrypt_generic_deinit($module);
            mcrypt_module_close($module);*/

            include '../vendor/autoload.php';
            $aes = new \phpseclib\Crypt\AES();
            //$aes->setKeyLength(32);
            $aes->setKey($this->key);
            $iv = substr($this->key, 0, 16);
            $aes->setIV($iv);
            $aes->disablePadding();
            $decrypted = $aes->decrypt($ciphertext_dec);

        } catch (Exception $e) {
            return array(ErrorCode::$DecryptAESError, null);
        }
        
        
        try {
            //去除补位字符
            $pkc_encoder = new PKCS7Encoder;
            $result = $pkc_encoder->decode($decrypted);
            //去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16)
                return "";
                $content = substr($result, 16, strlen($result));
                $len_list = unpack("N", substr($content, 0, 4));
                $xml_len = $len_list[1];
                $xml_content = substr($content, 4, $xml_len);
                $from_corpid = substr($content, $xml_len + 4);
        } catch (Exception $e) {
            print $e;
            return array(ErrorCode::$DecryptAESError, null);
        }
        if ($from_corpid != $corpid)
            return array(ErrorCode::$ValidateSuiteKeyError, null);
            return array(0, $xml_content);
            
    }
    
}

class ErrorCode
{
    public static $OK = 0;
    
    public static $IllegalAesKey = 900004;
    public static $ValidateSignatureError = 900005;
    public static $ComputeSignatureError = 900006;
    public static $EncryptAESError = 900007;
    public static $DecryptAESError = 900008;
    public static $ValidateSuiteKeyError = 900010;
}

class SHA1
{
    public function getSHA1($token, $timestamp, $nonce, $encrypt_msg)
    {
        try {
            $array = array($encrypt_msg, $token, $timestamp, $nonce);
            sort($array, SORT_STRING);
            $str = implode($array);
            return array(ErrorCode::$OK, sha1($str));
        } catch (Exception $e) {
            print $e . "\n";
            return array(ErrorCode::$ComputeSignatureError, null);
        }
    }
    
}

class DingtalkCrypt
{
    private $m_token;
    private $m_encodingAesKey;
    private $m_suiteKey;
    
    
    public function __construct($token, $encodingAesKey, $suiteKey)
    {
        $this->m_token = $token;
        $this->m_encodingAesKey = $encodingAesKey;
        $this->m_suiteKey = $suiteKey;
    }
    
    
    public function EncryptMsg($plain, $timeStamp, $nonce, &$encryptMsg)
    {
        $pc = new Prpcrypt($this->m_encodingAesKey);
        
        $array = $pc->encrypt($plain, $this->m_suiteKey);
        $ret = $array[0];
        if ($ret != 0) {
            return $ret;
        }
        
        if ($timeStamp == null) {
            $timeStamp = time();
        }
        $encrypt = $array[1];
        
        $sha1 = new SHA1;
        $array = $sha1->getSHA1($this->m_token, $timeStamp, $nonce, $encrypt);
        $ret = $array[0];
        if ($ret != 0) {
            return $ret;
        }
        $signature = $array[1];
        
        $encryptMsg = json_encode(array(
            "msg_signature" => $signature,
            "encrypt" => $encrypt,
            "timeStamp" => $timeStamp,
            "nonce" => $nonce
        ));
        return ErrorCode::$OK;
    }
    
    
    public function DecryptMsg($signature, $timeStamp = null, $nonce, $encrypt, &$decryptMsg)
    {
        if (strlen($this->m_encodingAesKey) != 43) {
            return ErrorCode::$IllegalAesKey;
        }
        
        $pc = new Prpcrypt($this->m_encodingAesKey);
        
        if ($timeStamp == null) {
            $timeStamp = time();
        }
        
        $sha1 = new SHA1;
        $array = $sha1->getSHA1($this->m_token, $timeStamp, $nonce, $encrypt);
        $ret = $array[0];
        
        if ($ret != 0) {
            return $ret;
        }
        
        $verifySignature = $array[1];
        if ($verifySignature != $signature) {
            return ErrorCode::$ValidateSignatureError;
        }
        
        $result = $pc->decrypt($encrypt, $this->m_suiteKey);
        if ($result[0] != 0) {
            return $result[0];
        }
        $decryptMsg = $result[1];
        
        return ErrorCode::$OK;
    }
    
}
