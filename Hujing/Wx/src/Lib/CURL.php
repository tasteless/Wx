<?php

namespace Hujing\Wx\Lib;

use Illuminate\Support\Facades\Log;

class CURL{
    public static $__inst = null;

    public function __construct(){
    }

    public static function instance(){
        if (null == CURL::$__inst){
            CURL::$__inst = new CURL();
        }

        return CURL::$__inst;
    }

    /*
     * post接口
     */
    public function post($url, $data, $header = false, $cert = false, $key = false, $rootca = false){
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($cert){
                curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
                curl_setopt($ch, CURLOPT_SSLCERT, $_SERVER['DOCUMENT_ROOT'] . '/cert/' . $wid . '/apiclient_cert.pem');
        }
        if ($key){
                curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                curl_setopt($ch, CURLOPT_SSLKEY, $_SERVER['DOCUMENT_ROOT'] . '/cert/' . $wid . '/apiclient_key.pem');
        }
        if ($rootca){
                curl_setopt($ch, CURLOPT_CAINFO, $_SERVER['DOCUMENT_ROOT'] . '/cert/' . $wid . '/rootca.pem');
        }
        curl_setopt($ch, CURLOPT_POST, true);
        // if (is_array($data) || is_object($data)){
        //     curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        // }
        // else{
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);            
        // }
        if ($header){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        }

        $ret = curl_exec($ch);

        curl_close($ch);
        return $ret;
    }

    /*
    * get接口
    */
    public function get($url, $header = false){
        $ch = curl_init();

        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_TIMEOUT, 30);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        if ($header){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        }

        $ret = curl_exec($ch);
        // Log::info(curl_getinfo($ch, CURLINFO_HEADER_OUT));

        curl_close($ch);
        return $ret;        
    }
}