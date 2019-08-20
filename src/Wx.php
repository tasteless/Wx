<?php

namespace Hujing\Wx;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Session\SessionManager;
use Illuminate\Config\Repository;

use Hujing\Wx\Classes\ErrorCode;
use Hujing\Wx\Classes\WxBizDataCrypt;

use Hujing\Wx\Lib\CURL;

class Wx
{
	private $config;
	private $session;

	/**
	* @param config
	* @param session
	* @return void
	*/
	public function __construct(Repository $config, SessionManager $session){
		$this->config = $config;
		$this->session = $session;

      Log::info('app_id_key' . $this->config->get('wx.app_id_key'));
      Log::info('app_secret_key' . $this->config->get('wx.app_secret_key'));
	}


	/**
	* @param jscode
	* @return [errcode, errmsg / object]
	*/
	public function jscodeToSession($jscode){
      $reqUrl = 'https://api.weixin.qq.com/sns/jscode2session' . 
                '?appid=' . env(strval($this->config->get('wx.app_id_key'))) . 
                '&secret=' . env(strval($this->config->get('wx.app_secret_key'))) . 
                '&js_code=' . $jscode . 
                '&grant_type=authorization_code';

      $ret = file_get_contents($reqUrl);

      Log::info($ret);

      //保存用户openid或session key
      $obj = json_decode($ret);
      if (0 == $obj->errcode){
         return [$obj->errcode, $obj];
      }
      return [$obj->errcode, $obj->errmsg];
	}


   /**
   * 获取access token
   * @param void
   * @return [errcode, errmsg / access token]
   */
   public function accessToken(){
      $accessTokenRedisKey = env($this->config->get('wx.app_id_key')) . '_AccessToken';

      $accessToken = Redis::get($accessTokenRedisKey);

      //是否已经过期
      if (!$accessToken){
         $reqUrl = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential' . 
                   '&appid=' . env(strval($this->config->get('wx.app_id_key'))) . 
                   '&secret=' . env(strval($this->config->get('wx.app_secret_key')));

         $ret = file_get_contents($reqUrl);
         $obj = json_decode($ret);
         if (0 == $obj->errcode){
            $accessToken = $obj->access_token;
            Redis::setex($accessTokenRedisKey, 7000, $accessToken);

            return [$obj->errcode, $accessToken];
         }

         return [$obj->errcode, $obj->errmsg];
      }  

      return [ErrorCode::$OK, $accessToken];
   }

   /**
   * 解析电话号码数据
   * @param openid
   * @param encryptedData
   * @param iv
   */
   public function decryptPhoneNumberData($openid, $sessionKey, $encryptedData, $iv){
      $crypt = new WxBizDataCrypt($this->config->get('wx.app_id_key'), $sessionKey);

      $data = [];
      $ret = $crypt->decryptData($encryptedData, $iv, $data);

      if ($ret == ErrorCode::$OK){
         return [$ret, $data];
      }

      return [$ret, ErrorCode::msg($ret)];
   }  


   /**
   * 创建带参二维码
   */
   public function createQrcode($param, $mediaService){
      $reqUrl = 'https://api.weixin.qq.com/wxa/getwxacode?access_token=' . $this->accessToken();

      $qrcodeImageData = CURL::instance()->post($reqUrl, json_encode([
         'path' => 'pages/index/index?from=' . $param
      ]));

      $qrcodeFilePath = '/tmp/' . Uuid::generate(). '.png';
      file_put_contents($qrcodeFilePath, $qrcodeImageData);

      return $qrcodeFilePath;
   }

   /**
   * 图片鉴黄
   */
   public function imageCheck($filePath){
      $accessToken = $this->accessToken();      
      if (ErrorCode::$OK != $accessToken[0]){
         return $accessToken[0];
      }

      $ret = CURL::instance()->post('https://api.weixin.qq.com/wxa/img_sec_check?access_token=' . $accessToken[1], ['media' => new CURLFile($imageFilePath)], ['content-type: multipart/form-data;']);
      Log::info('鉴黄结果:' . $ret);
      $retObj = json_decode($ret);
      if (87014 == $retObj->errcode){
         return ErrorCode::$ContentIllegal;
      }

      return ErrorCode::$OK;
   }

   /**
   * 敏感词识别
   */
   public function textCheck($keyword){
      $accessToken = $this->accessToken();      
      if (ErrorCode::$OK != $accessToken[0]){
         return $accessToken[0];
      }

      $ret = CURL::instance()->post('https://api.weixin.qq.com/wxa/msg_sec_check?access_token=' . $accessToken[1], json_encode(['content' => $keyword], JSON_UNESCAPED_UNICODE), ['content-type: application/x-www-form-urlencoded;charset=UTF-8']);
      Log::info($keyword . '是否敏感词结果:' . $ret);
      $retObj = json_decode($ret);
      if (87014 == $retObj->errcode){
         return ErrorCode::$ContentIllegal;
      }      

      return ErrorCode::$OK;
   }
}