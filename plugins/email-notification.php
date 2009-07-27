<?
if (0 && function_exists('mb_encode_mimeheader')) {
	function email_notification_quoted_printable($utf8text) {
		return mb_encode_mimeheader($utf8text, 'UTF-8', 'Q');
	}
}
elseif (false && function_exists('imap_8bit')) {
	function email_notification_quoted_printable($utf8text) {
		return imap_8bit($utf8text);
	}
}
else {
	function email_notification_quoted_printable($utf8text) {
	  $aLines = explode(chr(13).chr(10), $utf8text);
		
	  for ($i=0;$i<count($aLines);$i++) {
	    $sLine =& $aLines[$i];
	    if (strlen($sLine)===0) continue; // do nothing, if empty

	    $sRegExp = '/[^\x09\x20\x21-\x3C\x3E-\x7E]/e';

	    // imap_8bit encodes x09 everywhere, not only at lineends,
	    // for EBCDIC safeness encode !"#$@[\]^`{|}~,
	    // for complete safeness encode every character :)
	    # imap_8bit compliance
	    $sRegExp = '/[^\x20\x21-\x3C\x3E-\x7E]/e';

	    $sReplmt = 'sprintf( "=%02X", ord ( "$0" ) ) ;';
	    $sLine = preg_replace( $sRegExp, $sReplmt, $sLine );  

	    // encode x09,x20 at lineends
	    {
	      $iLength = strlen($sLine);
	      $iLastChar = ord($sLine{$iLength-1});

	      //              !!!!!!!!    
	      // imap_8_bit does not encode x20 at the very end of a text,
	      // here is, where I don't agree with imap_8_bit, 
	      // please correct me, if I'm wrong, 
	      // or comment next line for RFC2045 conformance, if you like
	      #if (!($bEmulate_imap_8bit && ($i==count($aLines)-1)))
				
	      if (($iLastChar==0x09)||($iLastChar==0x20)) {
	        $sLine{$iLength-1}='=';
	        $sLine .= ($iLastChar==0x09)?'09':'20';
	      }
	    }    // imap_8bit encodes x20 before chr(13), too
	    // although IMHO not requested by RFC2045, why not do it safer :)
	    // and why not encode any x20 around chr(10) or chr(13)
	    #if ($bEmulate_imap_8bit) {
	    #$sLine=str_replace(' =0D','=20=0D',$sLine);
	      //$sLine=str_replace(' =0A','=20=0A',$sLine);
	      //$sLine=str_replace('=0D ','=0D=20',$sLine);
	      //$sLine=str_replace('=0A ','=0A=20',$sLine);
	    #}

	    // finally split into softlines no longer than 76 chars,
	    // for even more safeness one could encode x09,x20 
	    // at the very first character of the line 
	    // and after soft linebreaks, as well,
	    // but this wouldn't be caught by such an easy RegExp                   
	    preg_match_all( '/.{1,73}([^=]{0,2})?/', $sLine, $aMatch );
	    $sLine = implode( '=' . chr(13).chr(10), $aMatch[0] ); // add soft crlf's
	  }

	  // join lines into text
	  return implode(chr(13).chr(10),$aLines);
	}
}

function email_notification_escape($name) {
	$name = email_notification_quoted_printable($name);
	$was_esc = strpos($name, '=');
	$name = str_replace('"', '=22', $name);
	if ($was_esc !== false)
		$name = '=?UTF-8?Q?'.$name.'?=';
	if (strpos($name, ',') !== false)
		$name = '"'.$name.'"';
	return $name;
}

function email_notification_send($subject, $message, $to_addr=null, $to_name=null,
                                 $from_addr=null, $from_name=null, $headers=null)
{
	if ($to_addr === null || !preg_match('/^.+@.+\..+$/', $to_addr)) $to_addr = gb::$settings['email']['admin_address'];
	if ($to_name === null || !trim($to_name)) $to_name = gb::$settings['email']['admin_name'];
	if ($from_addr === null || !preg_match('/^.+@.+\..+$/', $from_addr)) $from_addr = gb::$settings['email']['from_address'];
	if ($from_name === null || !trim($from_name)) $from_name = gb::$settings['email']['from_name'];
	if ($headers === null) $headers = '';
	
	# encode
	$to = $to_name ? email_notification_escape($to_name).' <'.$to_addr.'>' : $to_addr;
	$from = $from_name ? email_notification_escape($from_name).' <'.$from_addr.'>' : $from_addr;
	$subject = email_notification_escape($subject);
	$message = email_notification_quoted_printable($message);
	
	# build raw headers
	$headers .= 
		'From: '.$from."\r\n".
		'X-Mailer: Gitblog/'.gb::$version.' PHP/'.phpversion()."\r\n".
		"Content-Type: text/plain; charset=utf-8\r\n".
		"Content-Transfer-Encoding: Quoted-printable";
	
	# send
	if (!mail($to, $subject, $message, $headers)) {
		gb::log(LOG_ERR, 'email-notification: failed to send email to '.$to);
		return false;
	}
	return true;
}

function email_notification_did_add_comment($comment) {
	$subject = '['.gb::$site_title.'] New comment on "'.$comment->post->title
		.'" by '.$comment->name;
	$indented_comment_body = "\t".str_replace("\n", "\n\t", trim($comment->body));
	$comment_url = $comment->commentURL();
	$remove_url = $comment->removeURL();
	$comments_url = $comment->post->url().'#comments';
	$author_origin = $comment->ipAddress;
	$hostname = @gethostbyaddr($comment->ipAddress);
	if ($hostname && $hostname !== $comment->ipAddress)
		$author_origin .= ', '.$hostname;
	$msg = <<<MESSAGE
$indented_comment_body

On "{$comment->post->title}" $comment_url

Author:     $comment->name ($author_origin)
Email:      $comment->email
URI:        $comment->uri
Whois:      http://ws.arin.net/whois/?queryinput=$comment->ipAddress
Date:       $comment->date

View all comments on this post: $comments_url

Delete this comment: $remove_url (Note that the comment is instantly removed when you click this link).
MESSAGE;
	
	email_notification_send($subject, $msg, 
		$comment->post->author->email, $comment->post->author->name);
}

function email_notification_did_spam_comment($comment) {
	
}

function email_notification_init($context) {
	if ($context !== 'admin')
		return false;
	
	# Make sure we have configuration
	if (gb::$settings['email'] === null) {
		$siteurl = new GBURL(gb::$site_url);
		
		# construct "from"
		$pathm = preg_replace('/[^A-Za-z0-9_\.-]+/', '-', trim($siteurl->path,'/'));
		$from_addr = 'gitblog'.($pathm ? '+'.$pathm : '').'@'.$siteurl->host;
		$from_name = $siteurl->host.($pathm ? $siteurl->path : '');
		
		# admin
		$admin = GBUserAccount::getAdmin();
		
		# set...settings!
		gb::$settings['email'] = array(
			'from_address' => $from_addr,
			'from_name' => $from_name,
			'admin_address' => $admin ? $admin->email : 'postmaster@'.$siteurl->host,
			'admin_name' => $admin ? $admin->name : $siteurl->host,
		);
	}
	
	# observe events
	gb::observe('did-add-comment', 'email_notification_did_add_comment');
	gb::observe('did-spam-comment', 'email_notification_did_spam_comment');
}

?>