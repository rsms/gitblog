<?
function email_notification_recipient($comment) {
	$recipient = gb::$settings->get('email-notification/recipient');
	if (is_array($recipient) || strpos($recipient, '@') !== false)
		return $recipient;
	else
		return array($comment->post->author->email, $comment->post->author->name);
}

function email_notification_comment_mkbody($comment, $header='', $footer='') {
	$indented_comment_body = "\t".str_replace("\n", "\n\t", trim($comment->body));
	$comments_url = $comment->post->url().'#comments';
	$comment_url = $comment->approved ? $comment->commentURL() : $comments_url;
	
	$comments = $comment->commentsObject();
	$comments_name = $comments ? $comments->name : '?';
	
	$author_origin = $comment->ipAddress;
	$hostname = @gethostbyaddr($comment->ipAddress);
	if ($hostname && $hostname !== $comment->ipAddress)
		$author_origin .= ', '.$hostname;
	
	if (!is_string($header))
		$header = strval($header);
	if (!is_string($footer))
		$footer = strval($footer);
	
	# add stuff to footer
	if ($comment->approved)
		$footer .= "\nUnapprove this comment: ".$comment->unapproveURL(null, false)."\n";
	else
		$footer .= "\nApprove this comment: ".$comment->approveURL(null, false)."\n";
	
	if (!$comment->spam)
		$footer .= "\nMark as spam and delete: ".$comment->spamURL(null, false)."\n";
	
	$footer .= "\nDelete: ".$comment->removeURL(null, false)."\n";
	
	$ipv4_addr = $comment->ipAddress;
	if (preg_match('/([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})/', $comment->ipAddress, $m))
		$ipv4_addr = $m[1];
	
	# compile message
	$msg = <<<MESSAGE
{$header}{$indented_comment_body}

On "{$comment->post->title}" $comment_url

Author:     $comment->name ($author_origin)
Email:      $comment->email
URI:        $comment->uri
Whois:      http://www.db.ripe.net/whois?searchtext=$ipv4_addr
Date:       $comment->date

View all comments on this post: $comments_url
$footer
--
$comments_name
MESSAGE;
	return $msg;
}


function email_notification_did_add_comment($comment) {
	if ($comment->spam)
		return;
	
	# really do this?
	if ($comment->approved && !gb::$settings->get('email-notification/notify-new-comment'))
		return;
	elseif (!$comment->approved && !gb::$settings->get('email-notification/notify-pending-comment'))
		return;
	
	$subject = '['.gb::$site_title.'] '
		.($comment->approved ? 'New' : 'Pending').' comment on "'.$comment->post->title.'"';
	
	$body = email_notification_comment_mkbody($comment);
	$to = email_notification_recipient($comment);
	GBMail::compose($subject, $body, $to)->send();
}


function email_notification_did_spam_comment($comment) {
	if (!gb::$settings->get('email-notification/notify-spam-comment'))
		return;
	$subject = '['.gb::$site_title.'] Spam comment on "'.$comment->post->title.'"';
	$body = email_notification_comment_mkbody($comment);
	$to = email_notification_recipient($comment);
	GBMail::compose($subject, $body, $to)->send();
}


function email_notification_init($context) {
	if ($context !== 'admin')
		return false;
	
	# Setup default configuration if missing
	if (!is_array(gb::$settings['email-notification'])) {
		gb::$settings['email-notification'] = array(
			'notify-new-comment' => true,
			'notify-pending-comment' => true,
			'notify-spam-comment' => false,
			'recipient' => 'author'
		);
	}
	
	# observe events
	gb::observe('did-add-comment', 'email_notification_did_add_comment');
	gb::observe('did-spam-comment', 'email_notification_did_spam_comment');
}

?>