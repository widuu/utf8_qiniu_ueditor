<?php
/*
 *@description   图片管理基类
 *@author widuu  http://www.widuu.com
 *@mktime 08/01/2014
 */
class Qiniu_Mac {

	public $AccessKey;
	public $SecretKey;

	public function __construct($accessKey, $secretKey)
	{
		$this->AccessKey = $accessKey;
		$this->SecretKey = $secretKey;
	}

	public function Sign($data) // => $token
	{
		$sign = hash_hmac('sha1', $data, $this->SecretKey, true);
		return $this->AccessKey . ':' . Qiniu_Encode($sign);
	}

	public function SignWithData($data) // => $token
	{
		$data = Qiniu_Encode($data);
		return $this->Sign($data) . ':' . $data;
	}

	public function SignRequest($req, $incbody) // => ($token, $error)
	{
		$url = $req->URL;
		$url = parse_url($url['path']);
		$data = '';
		if (isset($url['path'])) {
			$data = $url['path'];
		}
		if (isset($url['query'])) {
			$data .= '?' . $url['query'];
		}
		$data .= "\n";

		if ($incbody) {
			$data .= $req->Body;
		}
		return $this->Sign($data);
	}
}

function Qiniu_RequireMac($mac) // => $mac
{
	if (isset($mac)) {
		return $mac;
	}

	global $QINIU_ACCESS_KEY;
	global $QINIU_SECRET_KEY;

	return new Qiniu_Mac($QINIU_ACCESS_KEY, $QINIU_SECRET_KEY);
}

function Qiniu_Sign($mac, $data) // => $token
{
	return Qiniu_RequireMac($mac)->Sign($data);
}

function Qiniu_SignWithData($mac, $data) // => $token
{
	return Qiniu_RequireMac($mac)->SignWithData($data);
}

class Qiniu_Error
{
	public $Err;	 // string
	public $Reqid;	 // string
	public $Details; // []string
	public $Code;	 // int

	public function __construct($code, $err)
	{
		$this->Code = $code;
		$this->Err = $err;
	}
}

// --------------------------------------------------------------------------------
// class Qiniu_Request

class Qiniu_Request
{
	public $URL;
	public $Header;
	public $Body;

	public function __construct($url, $body)
	{
		$this->URL = $url;
		$this->Header = array();
		$this->Body = $body;
	}
}

// --------------------------------------------------------------------------------
// class Qiniu_Response

class Qiniu_Response
{
	public $StatusCode;
	public $Header;
	public $ContentLength;
	public $Body;

	public function __construct($code, $body)
	{
		$this->StatusCode = $code;
		$this->Header = array();
		$this->Body = $body;
		$this->ContentLength = strlen($body);
	}
}

// --------------------------------------------------------------------------------
// class Qiniu_Header

function Qiniu_Header_Get($header, $key) // => $val
{
	$val = @$header[$key];
	if (isset($val)) {
		if (is_array($val)) {
			return $val[0];
		}
		return $val;
	} else {
		return '';
	}
}

function Qiniu_ResponseError($resp) // => $error
{
	$header = $resp->Header;
	$details = Qiniu_Header_Get($header, 'X-Log');
	$reqId = Qiniu_Header_Get($header, 'X-Reqid');
	$err = new Qiniu_Error($resp->StatusCode, null);

	if ($err->Code > 299) {
		if ($resp->ContentLength !== 0) {
			if (Qiniu_Header_Get($header, 'Content-Type') === 'application/json') {
				$ret = json_decode($resp->Body, true);
				$err->Err = $ret['error'];
			}
		}
	}
	return $err;
}

// --------------------------------------------------------------------------------
// class Qiniu_Client

function Qiniu_Client_incBody($req) // => $incbody
{
	$body = $req->Body;
	if (!isset($body)) {
		return false;
	}

	$ct = Qiniu_Header_Get($req->Header, 'Content-Type');
	if ($ct === 'application/x-www-form-urlencoded') {
		return true;
	}
	return false;
}

function Qiniu_Client_do($req) // => ($resp, $error)
{
	$ch = curl_init();
	$url = $req->URL;
	$options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_CUSTOMREQUEST  => 'POST',
		CURLOPT_URL => $url['path']
	);
	$httpHeader = $req->Header;
	if (!empty($httpHeader))
	{
		$header = array();
		foreach($httpHeader as $key => $parsedUrlValue) {
			$header[] = "$key: $parsedUrlValue";
		}
		$options[CURLOPT_HTTPHEADER] = $header;
	}
	$body = $req->Body;
	if (!empty($body)) {
		$options[CURLOPT_POSTFIELDS] = $body;
	}
	curl_setopt_array($ch, $options);
	$result = curl_exec($ch);
	$ret = curl_errno($ch);
	if ($ret !== 0) {
		$err = new Qiniu_Error(0, curl_error($ch));
		curl_close($ch);
		return array(null, $err);
	}
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	curl_close($ch);
	$resp = new Qiniu_Response($code, $result);
	$resp->Header['Content-Type'] = $contentType;
	return array($resp, null);
}

class Qiniu_HttpClient
{
	public function RoundTrip($req) // => ($resp, $error)
	{
		return Qiniu_Client_do($req);
	}
}

class Qiniu_MacHttpClient
{
	public $Mac;

	public function __construct($mac)
	{
		$this->Mac = Qiniu_RequireMac($mac);
	}

	public function RoundTrip($req) // => ($resp, $error)
	{
		$incbody = Qiniu_Client_incBody($req);
		$token = $this->Mac->SignRequest($req, $incbody);
		$req->Header['Authorization'] = "QBox $token";
		return Qiniu_Client_do($req);
	}
}

// --------------------------------------------------------------------------------

function Qiniu_Client_ret($resp) // => ($data, $error)
{
	$code = $resp->StatusCode;
	$data = null;
	if ($code >= 200 && $code <= 299) {
		if ($resp->ContentLength !== 0) {
			$data = json_decode($resp->Body, true);
			if ($data === null) {
				$err_msg = function_exists('json_last_error_msg') ? json_last_error_msg() : "error with content:" . $resp->Body;
				$err = new Qiniu_Error(0, $err_msg);
				return array(null, $err);
			}
		}
		if ($code === 200) {
			return array($data, null);
		}
	}
	return array($data, Qiniu_ResponseError($resp));
}

function Qiniu_Client_Call($self, $url) // => ($data, $error)
{
	$u = array('path' => $url);
	$req = new Qiniu_Request($u, null);
	list($resp, $err) = $self->RoundTrip($req);
	if ($err !== null) {
		return array(null, $err);
	}
	return Qiniu_Client_ret($resp);
}

function Qiniu_Client_CallNoRet($self, $url) // => $error
{
	$u = array('path' => $url);
	$req = new Qiniu_Request($u, null);
	list($resp, $err) = $self->RoundTrip($req);
	if ($err !== null) {
		return array(null, $err);
	}
	if ($resp->StatusCode === 200) {
		return null;
	}
	return Qiniu_ResponseError($resp);
}

function Qiniu_Client_CallWithForm(
	$self, $url, $params, $contentType = 'application/x-www-form-urlencoded') // => ($data, $error)
{
	$u = array('path' => $url);
	if ($contentType === 'application/x-www-form-urlencoded') {
		if (is_array($params)) {
			$params = http_build_query($params);
		}
	}
	$req = new Qiniu_Request($u, $params);
	if ($contentType !== 'multipart/form-data') {
		$req->Header['Content-Type'] = $contentType;
	}
	list($resp, $err) = $self->RoundTrip($req);
	if ($err !== null) {
		return array(null, $err);
	}
	return Qiniu_Client_ret($resp);
}

// --------------------------------------------------------------------------------

function Qiniu_Client_CallWithMultipartForm($self, $url, $fields, $files)
{
	list($contentType, $body) = Qiniu_Build_MultipartForm($fields, $files);
	return Qiniu_Client_CallWithForm($self, $url, $body, $contentType);
}

function Qiniu_Build_MultipartForm($fields, $files) // => ($contentType, $body)
{
	$data = array();
	$mimeBoundary = md5(microtime());

	foreach ($fields as $name => $val) {
		array_push($data, '--' . $mimeBoundary);
		array_push($data, "Content-Disposition: form-data; name=\"$name\"");
		array_push($data, '');
		array_push($data, $val);
	}

	foreach ($files as $file) {
		array_push($data, '--' . $mimeBoundary);
		list($name, $fileName, $fileBody) = $file;
		$fileName = Qiniu_escapeQuotes($fileName);
		array_push($data, "Content-Disposition: form-data; name=\"$name\"; filename=\"$fileName\"");
		array_push($data, 'Content-Type: application/octet-stream');
		array_push($data, '');
		array_push($data, $fileBody);
	}

	array_push($data, '--' . $mimeBoundary . '--');
	array_push($data, '');

	$body = implode("\r\n", $data);
	$contentType = 'multipart/form-data; boundary=' . $mimeBoundary;
	return array($contentType, $body);
}

function Qiniu_escapeQuotes($str)
{
	$find = array("\\", "\"");
	$replace = array("\\\\", "\\\"");
	return str_replace($find, $replace, $str);
}

function Qiniu_Encode($str) // URLSafeBase64Encode
{
	$find = array('+', '/');
	$replace = array('-', '_');
	return str_replace($find, $replace, base64_encode($str));
}

define('Qiniu_RSF_EOF', 'EOF');

/**
 * 1. 首次请求 marker = ""
 * 2. 无论 err 值如何，均应该先看 items 是否有内容
 * 3. 如果后续没有更多数据，err 返回 EOF，markerOut 返回 ""（但不通过该特征来判断是否结束）
 */
function Qiniu_RSF_ListPrefix(
	$self, $bucket, $prefix = '', $marker = '', $limit = 0) // => ($items, $markerOut, $err)
{
	global $QINIU_RSF_HOST;

	$query = array('bucket' => $bucket);
	if (!empty($prefix)) {
		$query['prefix'] = $prefix;
	}
	if (!empty($marker)) {
		$query['marker'] = $marker;
	}
	if (!empty($limit)) {
		$query['limit'] = $limit;
	}

	$url =  $QINIU_RSF_HOST . '/list?' . http_build_query($query);
	list($ret, $err) = Qiniu_Client_Call($self, $url);
	if ($err !== null) {
		return array(null, '', $err);
	}

	$items = $ret['items'];
	if (empty($ret['marker'])) {
		$markerOut = '';
		$err = Qiniu_RSF_EOF;
	} else {
		$markerOut = $ret['marker'];
	}
	return array($items, $markerOut, $err);
}

