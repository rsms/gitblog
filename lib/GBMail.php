<?
/**
 * Email.
 * 
 * The settings reside under the key "email" in settings.json.
 * 
 * Example settings.json for Google Mail SMTP delivery:
 * 
 *   "email": {
 *     "admin": ["Your Self", "you@domain.com"],
 *     "smtp": {
 *       "secure": "ssl",
 *       "host": "smtp.gmail.com",
 *       "port": 465,
 *       "username": "my.account@gmail.com",
 *       "password": "secret"
 *     }
 *   }
 * 
 * 
 * Example of sending mail using the convenience method "compose":
 * 
 *   GBMail::compose('Hello', 'This is a mail', array('John Doe', 'john@doe.com'))->send();
 * 
 */
class GBMail {
	public $mailer;
	
	function __construct($subject=null, $textbody=null) {
		$this->mailer = self::mkmailer();
		
		if ($subject !== null)
			$this->mailer->Subject = $subject;
		if ($textbody !== null)
			$this->mailer->Body = $textbody;
		
		if ($this->needAuthorizedFromAddress()) {
			$addr = $this->authorizedFromAddress();
			#$this->addAddress($addr, 'replyto');
			$this->mailer->Sender = $addr[0];
		}
	}
	
	function needAuthorizedFromAddress() {
		# currently only gmail
		return (substr($this->mailer->Host, 'smtp.gmail.com') !== false);
	}
	
	function authorizedFromAddress() {
		$addr = gb::$settings->get('email/sender');
		if ($addr === null)
			$addr = gb::$settings->get('email/smtp/username');
		if ($addr === null)
			$addr = gb::$settings->get('email/admin', self::$default_from);
		if ($addr === null)
			$addr = gb::$settings->get('email/reply_to');
		$addr = self::normalizeRecipient($addr);
		if ($addr[1])
			return $addr;
		return array($addr[0], gb::$site_title);
	}
	
	function formatAddress($addr) {
		if (!is_array($addr))
			$addr = self::normalizeRecipient($addr);
		return $this->mailer->AddrFormat($addr);
	}
	
	function addAddress($var, $type='to') {
		$type = strtolower($type);
		$func = 'Add'.($type === 'bcc' ? 'BCC' : ($type === 'cc' ? 'CC' : 
			($type === 'replyto' ? 'ReplyTo' : 'Address')));
		if (is_array($var)) {
			if (is_array($var[0])) {
				# multiple recipients
				# [ {addr, name}, {addr, name} .. ]
				foreach ($var as $t) {
					list($addr, $name) = self::normalizeRecipient($t);
					$this->mailer->$func($addr, $name);
				}
			}
			else {
				# single recipient {addr, name}
				list($addr, $name) = self::normalizeRecipient($var);
				$this->mailer->$func($addr, $name);
			}
		}
		else {
			$this->mailer->$func($var);
		}
	}
	
	function setFrom($var) {
		list($this->mailer->From, $this->mailer->FromName) = self::normalizeRecipient($var);
	}
	
	function rawRecipients() {
		$r = array();
		static $toks = array('to'=>'to', 'cc'=>'cc', 'bcc'=>'bcc');
		foreach ($toks as $propname => $keyname) {
			if (!$this->mailer->$propname)
				continue;
			$v = array();
			foreach ($this->mailer->$propname as $t) {
				if ($t && ($t = $this->mailer->AddrFormat($t)))
					$v[] = $t;
			}
			$r[$keyname] = $v;
		}
		return $r;
	}
	
	function send($deferred=false) {
		if ($deferred && gb::defer(array($this, 'send'), false))
			return true;
		
		$return_value = true;
		
		$r = $this->rawRecipients();
		$to = array();
		foreach ($r as $addrs)
			$to = array_filter(array_merge($to, $addrs));
		$to = implode(', ', $to);
		
		# from must be set
		if (!$this->mailer->From) {
			list($this->mailer->From, $this->mailer->FromName) = $this->authorizedFromAddress();
		}
		
		# since PHPMailer is one ugly piece of software
		$orig_error_reporting = error_reporting(E_ALL ^ E_NOTICE);
		
		gb::log('sending email to %s with subject %s', $to, r($this->mailer->Subject));
		if(!$this->mailer->Send()) {
			gb::log(LOG_ERR, 'failed to send email to %s: %s', $to, $this->mailer->ErrorInfo);
			$return_value = false;
		}
		else {
			gb::log('sent email to %s with subject %s', $to, r($this->mailer->Subject));
		}
		
		# reset error reporting
		error_reporting($orig_error_reporting);
		
		return $return_value;
	}
	
	static function compose($subject, $textbody, $to=null, $from=null) {
		$m = new self($subject, $textbody);
		if ($from) $m->setFrom($from);
		if ($to) $m->addAddress($to);
		return $m;
	}
	
	# returns array(addr, name | "")
	static function normalizeRecipient($v) {
		if (is_array($v)) {
			if (isset($v[0])) {
				if (strpos($v[0], '@') !== false) {
					$r = array($v[0], '');
					if (isset($v[1]))
						$r[1] = $v[1];
					return $r;
				}
				elseif (isset($v[1]) && strpos($v[1], '@') !== false) {
					return array($v[1], $v[0]);
				}
			}
			elseif (isset($v['address'])) {
				return array($v['address'], isset($v['name']) ? $v['name'] : '');
			}
		}
		else {
			return array(strval($v), '');
		}
	}
	
	static public $default_from;
	
	static function setDefaultConfig() {
		$siteurl = new GBURL(gb::$site_url);
		$admin = GBUser::admin();
		gb::$settings['email'] = array(
			'admin' => array($admin ? $admin->name : $siteurl->host,
				$admin ? $admin->email : 'root@'.$siteurl->host)
		);
	}
	
	static function mkmailer() {
		# setup conf
		if (!is_array(gb::$settings['email']))
			self::setDefaultConfig();
		
		# since PHPMailer is one ugly piece of software
		$orig_error_reporting = error_reporting(E_ALL ^ E_NOTICE);
		
		# setup phpmailer
		$e = new PHPMailer();
		$e->From = '';
		$e->FromName = '';
		$e->PluginDir = gb::$dir . '/lib/PHPMailer/';
		$conf = gb::$settings['email'];
		
		# SMTP
		if (isset($conf['smtp'])) {
			$c = $conf['smtp'];
			$e->IsSMTP(); # enable SMTP
			
			# authenitcation?
			$e->SMTPAuth = isset($c['password']) || isset($c['username']);
			
			# secure?
			if (isset($c['secure']) && $c['secure']) {
				static $allowed = array('ssl'=>1, 'tls'=>1, ''=>1);
				$c['secure'] = is_string($c['secure']) ? strtolower($c['secure']) : ($c['secure'] ? 'ssl' : '');
				if (!isset($allowed[$c['secure']])) {
					gb::log(LOG_WARNING,
						'malformed configuration: bad value for "secure": %s -- only "ssl" or "tls" is allowed',
						$c['secure']);
				}
				else {
					$e->SMTPSecure = $c['secure'];
				}
			}
			
			# support for multiple hosts
			if (isset($c['host']))
				$e->Host = $c['host'];
			elseif (isset($c['hosts']))
				$e->Host = $c['hosts'];
			if (is_array($e->Host)) {
				$hosts = $e->Host;
				$e->Host = array();
				foreach ($hosts as $host) {
					if (is_array($host)) {
						if (!isset($host['name'])) {
							gb::log(LOG_WARNING, 'malformed configuration: missing "name" for host %s',
								var_export($host,1));
						}
						else {
							$v[] = $host['name'].(isset($host['port']) ? ':'.$host['port'] : '');
						}
					}
					else
						$v[] = $host;
				}
				$e->Host = implode(';', $e->Host);
			}
			
			# default port
			if (isset($c['port']) && ($port = intval($c['port'])) > 0)
				$e->Port = $port;
			
			# username
			if (isset($c['username']))
				$e->Username = $c['username'];
			
			# password
			if (isset($c['password']))
				$e->Password = $c['password'];
			
			# connection timeout
			if (isset($c['timeout']) && ($i = intval($c['timeout'])) > 0)
				$e->Timeout = $i;
		}
		
		# gitblog <heart> UTF-8
		$e->CharSet = 'utf-8';
		
		# Default from
		if (isset($conf['from'])) {
			list($e->From, $e->FromName) = self::normalizeRecipient($conf['from']);
		}
		
		# Default sender
		if (isset($conf['sender']))
			list($e->Sender, $discard) = self::normalizeRecipient($conf['sender']);
		elseif (isset($conf['admin']))
			list($e->Sender, $discard) = self::normalizeRecipient($conf['admin']);
		
		# default priority
		if (isset($conf['priority']))
			$e->Priority = intval($conf['priority']);
		
		# reset error reporting
		error_reporting($orig_error_reporting);
		
		return $e;
	}
}

# set some default values
$siteurl = new GBURL(gb::$site_url);
$pathm = preg_replace('/[^A-Za-z0-9_\.-]+/', '-', trim($siteurl->path,'/'));
GBMail::$default_from = array('gitblog'.($pathm ? '+'.$pathm : '').'@'.$siteurl->host,
	$siteurl->host.($pathm ? $siteurl->path : ''));
unset($siteurl);
unset($pathm);
# done
?>