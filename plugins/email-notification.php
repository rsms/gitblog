<?php
/**
 * @name    Email notification
 * @version 0.1
 * @author  Rasmus Andersson
 * @uri     http://gitblog.se/
 * 
 * Sends emails when things happen, like when new comments are added.
 */

class email_notification_plugin {
	static public $data;
	
	static function init($context) {
		self::$data = gb::data('plugins/'.gb_filenoext(basename(__FILE__)), array(
			'notify_new_comment' => true,
			'notify_pending_comment' => true,
			'notify_spam_comment' => false,
			'recipient' => 'author'
		));
		gb::observe('did-add-comment', array(__CLASS__, 'did_add_comment'));
		gb::observe('did-spam-comment', array(__CLASS__, 'did_spam_comment'));
		return true;
	}
	
	static function recipient($comment) {
		$recipient = self::$data['recipient'];
		if (is_array($recipient) || strpos($recipient, '@') !== false)
			$recipient = GBMail::normalizeRecipient($recipient);
		else
			$recipient = GBMail::normalizeRecipient($comment->post->author);
		if (!$recipient[0])
			$recipient = GBMail::normalizeRecipient(gb::data('email')->get('admin'));
		return $recipient;
	}

	static function comment_mkbody($comment, $header='', $footer='') {
		$indented_comment_body = "\t".str_replace("\n", "\n\t", trim($comment->body()));
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

	static function did_add_comment($comment) {
		if ($comment->spam)
			return;
		
		# really do this?
		if ($comment->approved && !self::$data['notify_new_comment'])
			return;
		elseif (!$comment->approved && !self::$data['notify_pending_comment'])
			return;
	
		$subject = '['.gb::$site_title.'] '
			.($comment->approved ? 'New' : 'Pending').' comment on "'.$comment->post->title.'"';
		
		$body = self::comment_mkbody($comment);
		$to = self::recipient($comment);
		
		if (!$to[0]) {
			gb::log(LOG_WARNING, 'failed to deduce recipient -- '
				.'please add your address to "admin" in data/email.json');
		}
		else {
			GBMail::compose($subject, $body, $to)->send(true);
		}
	}

	static function did_spam_comment($comment) {
		if (!self::$data['notify_spam_comment'])
			return;
		$subject = '['.gb::$site_title.'] Spam comment on "'.$comment->post->title.'"';
		$body = self::comment_mkbody($comment);
		$to = self::recipient($comment);
		GBMail::compose($subject, $body, $to)->send(true);
	}
}
?>