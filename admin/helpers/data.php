<?php
/**
 * REST interface to gb::data (POST, GET, DELETE)
 * 
 * Example:
 *
 * curl -H 'Content-type: application/json' -d '{"hej":12.3}' http://x/data.php/admin
 * {"hej": 12.3}
 * 
 * curl http://x/data.php/admin?hej
 * {"hej": 12.3}
 * 
 * curl -iH 'Content-type: application/json' -d '{"hej":{"mos":"xyz"}}' http://x/data.php/admin
 * {"hej": {
 *   "mos": "xyz"
 * }}
 * 
 * curl -iH 'Content-type: application/json' -d '{"hej/mos":4.5}' http://x/data.php/admin
 * {"hej": {
 *   "mos": 4.5
 * }}
 * 
 * curl -iH 'Content-type: application/json' -d '{"hej/foo":[1,"one"]}' http://x/data.php/admin
 * {"hej": {
 *   "mos": 4.5,
 *   "foo": [1, "one"]
 * }}
 * 
 * curl -iH 'Content-type: application/json' -d '{"hej":{"foo":"bar"}}' http://x/data.php/admin
 * {"hej": {
 *   "foo": "bar"
 * }}
 * 
 * curl http://x/data.php/admin?hej&jsoncallback=ondata
 * ondata( {"hej": {
 *   "foo": "bar"
 * }} );
 */
require '../_base.php';
gb::authenticate();
$jsonp_cb = false;

function rsp_ok($jsondata='', $status='200 OK') {
	global $jsonp_cb;
	header('HTTP/1.1 '.$status);
	if ($jsonp_cb) echo $jsonp_cb.'(';
	$jsondata = trim($jsondata);
	echo $jsondata ? $jsondata : '{}';
	if ($jsonp_cb) echo ');';
	exit;
}

function rsp_err($msg='', $status='400 Bad Request', $bt=null) {
	$rsp = array('message' => $msg);
	if ($bt)
		$rsp['bt'] = $bt;
	rsp_ok(json::pretty( array('error' => $rsp) ), $status);
}

function rsp_exc($e) {
	rsp_err(GBException::formatPlain($e, false, null, 0), '500 Internal Server Error',
		array_filter(array_map('trim', explode("\n",GBException::formatTrace($e, false, null, 0)))));
}

function stripslashes_deep($value) {
	return is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
}

# input params
$method = $_SERVER['REQUEST_METHOD'];
$store_id = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'],"\r\n\t/ ") : null;
$jsonp_cb = false;
if (isset($_GET['jsoncallback'])) {
	$jsonp_cb = $_GET['jsoncallback'];
	unset($_GET['jsoncallback']);
	header('Content-Type: text/javascript');
}
else {
	header('Content-Type: application/json');
}

# store
if (!$store_id)
	rsp_err('No store specified in path');
$store = gb::data($store_id);
if ($method !== 'POST' && !is_readable($store->file))
	rsp_err('No such store "'.$store_id.'"', '404 Not found');

try {
	# POST
	if ($method === 'POST') {
		$payload_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
		$payload_data = '';
		$payload_struct = null;
		
		if ($payload_type !== 'application/json') {
			rsp_err('Unsupported Media Type. Only accepts "application/json"',
				'415 Unsupported Media Type');
		}
		
		# parse json
		$input = fopen('php://input', 'r');
		while ($data = fread($input, 8192)) $payload_data .= $data;
		fclose($input);
		$pairs = json_decode($payload_data, true);
		if ($pairs === null)
			rsp_err('Failed to parse JSON payload');
		
		# set keys
		$store->storage()->begin();
		try {
			foreach ($pairs as $k => $v) {
				if (($k = trim($k, " \t\r\n/")))
					$store->put($k, $v);
			}
			$store->storage()->commit();
		}
		catch (Exception $e) {
			$store->storage()->rollback();
			throw $e;
		}
		# reply with 200 OK + current document
		rsp_ok($store->toJSON()); # OK
	}
	elseif ($method === 'GET') {
		if (get_magic_quotes_gpc())
			$_GET = stripslashes_deep($_GET);
		$keys = $_GET;
		# fetch specific keys
		if ($keys) {
			$rsp = array();
			foreach ($keys as $key => $fallback_value) {
				if (!$fallback_value)
					$fallback_value = null;
				$rsp[$key] = $store->get($key, $fallback_value);
			}
			rsp_ok(json::pretty($rsp));
		}
		# fetch complete store
		else {
			rsp_ok($store->toJSON());
		}
	}
	else {
		rsp_err($method.' method not allowed', '405 Method Not Allowed');
	}
}
/*catch (LogicException $e) {
	if (strpos($e->getMessage(), 'Failed to parse') !== false)
		rsp_ok('{}');
	rsp_exc($e);
}*/
catch (Exception $e) {
	rsp_exc($e);
}

?>