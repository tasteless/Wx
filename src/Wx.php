<?php

namespace Hujing\Wx;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Session\SessionManager;
use Illuminate\Config\Repository;

use Hujing\Wx\Classes\ErrorCode;
use Hujing\Wx\Classes\WxBizDataCrypt;

use Webpatser\Uuid\Uuid;
use Hujing\Wx\Lib\CURL;

use Qcloud\Cos\Client as QcloudCOSClient;

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
	}


	/**
	* @param jscode
	* @return [errcode, errmsg / object]
	*/
	public function jscodeToSession($appId, $jscode){
      Log::info('app id: ' . $appId);
      Log::info('app secret: ' . $this->config->get('wx.' . $appId));
      $reqUrl = 'https://api.weixin.qq.com/sns/jscode2session' . 
                '?appid=' . $appId . 
                '&secret=' . strval($this->config->get('wx.' . $appId)) . 
                '&js_code=' . $jscode . 
                '&grant_type=authorization_code';

      $ret = file_get_contents($reqUrl);
      Log::info($ret);

      //保存用户openid或session key
      $obj = json_decode($ret);
      if (!property_exists($obj, 'errcode')){
         return [ErrorCode::$OK, $obj];
      }
      return [$obj->errcode, $obj->errmsg];
	}


   /**
   * 获取access token
   * @param void
   * @return [errcode, errmsg / access token]
   */
   public function accessToken($appId){
      $accessTokenRedisKey = $appId . '_AccessToken';

      $accessToken = Redis::get($accessTokenRedisKey);

      //是否已经过期
      if (!$accessToken){
         $reqUrl = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential' . 
                   '&appid=' . $appId . 
                   '&secret=' . strval($this->config->get('wx.' . $appId));

         $ret = file_get_contents($reqUrl);

         Log::info($ret);
   
         $obj = json_decode($ret);
         if (!property_exists($obj, 'errcode')){
            $accessToken = $obj->access_token;
            Redis::setex($accessTokenRedisKey, 7000, $accessToken);

            return [ErrorCode::$OK, $accessToken];
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
   public function decryptPhoneNumberData($appId, $openid, $sessionKey, $encryptedData, $iv){
      $crypt = new WxBizDataCrypt($appId, $sessionKey);

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
   public function createQrcode($appId, $param, $mediaService){
      $reqUrl = 'https://api.weixin.qq.com/wxa/getwxacode?access_token=' . $this->accessToken($appId);

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
   public function imageCheck($appId, $filePath){
      $accessToken = $this->accessToken($appId);      
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
   public function textCheck($appId, $keyword){
      $accessToken = $this->accessToken($appId);      
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

   /**
   * 上传照片到对象存储
   */
   public function uploadImage($filePath, $extension){
      $cosClient = new QcloudCOSClient([
              'region' => $this->config->get('wx.qcloud_cos_region'),
              'credentials'=> [
                  'appId' => $this->config->get('wx.qcloud_app_id'),
                  'secretId'  => $this->config->get('wx.qcloud_secret_id'),
                  'secretKey' => $this->config->get('wx.qcloud_secret_key')
               ]
            ]);
      $result = false;
      $fileName = Uuid::generate()->string . '.' . $extension;
      try { 
         $cosClient->putObject(array( 
              'Bucket' =>  $this->config->get('wx.bucket'),
              'Key' => $fileName, 
              'Body' => fopen($filePath, 'rb'), 
          )); 
         $result = true;
      } catch (\Exception $e) { 
         return [ErrorCode::$CallException, $e->getMessage()];          
      }

      return [ErrorCode::$OK, 'https://public-1257306603.cos.ap-shanghai.myqcloud.com/' . $fileName]; 
   }
}