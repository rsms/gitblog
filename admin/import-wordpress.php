<?
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');
require_once '_base.php';

# todo: auth

/*class GBPost {
	# GBContent:
	public $name; # relative to root tree
	public $id;
	public $mimeType = null;
	public $author = null;
	public $modified = false; # timestamp
	public $published = false; # timestamp
	
	# GBExposedContent:
	public $slug;
	public $meta;
	public $title;
	public $body;
	public $tags = array();
	public $categories = array();
	public $comments;
	public $commentsOpen = true;
	
	# GBPost:
	public $excerpt;
}*/

class WPPost extends GBPost {
	public $wp_is_published;
}
class WPPage extends GBPage {
	public $wp_is_published;
}

class WPComment extends GBComment {
	/*
	$date;
	$ipAddress;
	$email;
	$uri;
	$name;
	$message;
	$approved;
	$comments;
	*/
	public $wpid = 0; # used for sorting
	public $wpdateutc = 0; # used for sorting
}

class WordpressImporter {
	public $doc;
	
	function __construct() {
	}
	
	function import(DOMDocument $doc) {
		$this->doc = $doc;
		foreach ($doc->getElementsByTagName('channel') as $channel) {
			if ($channel->nodeType !== XML_ELEMENT_NODE)
				continue;
			$this->importChannel($channel);
		}
	}
	
	function importChannel(DOMNode $channel) {
		$fallbackTZOffset = $this->deduceChannelTimezoneOffset($channel);
		foreach ($channel->getElementsByTagName('item') as $item) {
			if ($item->nodeType !== XML_ELEMENT_NODE)
				continue;
			$obj = $this->importItem($item, $fallbackTZOffset);
			var_export($obj);
		}
	}
	
	function createItemObject(DOMNode $item) {
		$type = $item->getElementsByTagName('post_type')->item(0);
		# "page", "post", "attachment"
		switch ($type ? $type->nodeValue : '?') {
			case 'post': return new WPPost();
			case 'page': return new WPPage();
			# todo: handle attachments
		}
		return null;
	}
	
	function importItem(DOMNode $item, $fallbackTZOffset=0) {
		$obj = $this->createItemObject($item);
		if ($obj === null) {
			gb::log(LOG_NOTICE, 'discarding unknown item %s', $this->doc->saveXML($item));
			return null;
		}
		
		$obj->mimeType = 'text/html';
		$datelocalstr = null;
		$datelocal = false;
		$dateutc = false;
		
		foreach ($item->childNodes as $n) {
			if ($n->nodeType !== XML_ELEMENT_NODE)
				continue;
			
			# we're doing doing this to avoid a bug in php where accessing this property
			# from strpos or str_replace causes a silent hang.
			$name = ''.$n->nodeName;
			
			if ($name === 'title') {
				$obj->title = $n->nodeValue;
			}
			elseif ($name === 'content:encoded') {
				$obj->body = $n->nodeValue;
			}
			elseif ($name === 'excerpt:encoded') {
				# will be derived from body by the content rebuilder, so in case WP
				# adds this in the future, just discard it. (In WP 2.6 this is never
				# present anyway.)
			}
			elseif ($name === 'category') {
				if ( ($domain = $n->attributes->getNamedItem('domain')) !== null) {
					if ($domain->nodeValue === 'category' 
						&& $n->nodeValue !== 'Uncategorized' 
						&& $n->attributes->getNamedItem('nicename') !== 'uncategorized' 
						&& !in_array($n->nodeValue, $obj->categories))
					{
						$obj->categories[] = $n->nodeValue;
					}
					elseif ($domain->nodeValue === 'tag' && !in_array($n->nodeValue, $obj->tags)) {
						$obj->tags[] = $n->nodeValue;
					}
				}
			}
			elseif ($name === 'wp:comment_status') {
				$obj->commentsOpen = ($n->nodeValue === 'open');
			}
			elseif ($name === 'wp:ping_status') {
				$obj->pingbackOpen = ($n->nodeValue === 'open');
			}
			elseif ($name === 'wp:post_name') {
				$obj->slug = $n->nodeValue;
			}
			elseif ($name === 'wp:post_date_gmt') {
				if ($n->nodeValue !== '0000-00-00 00:00:00')
					$dateutc = strtotime($n->nodeValue);
			}
			elseif ($name === 'wp:post_date') {
				$datelocalstr = $n->nodeValue;
				$datelocal = strtotime($n->nodeValue);
				$obj->wpdate = $datelocal;
			}
			elseif ($name === 'wp:status') {
				$obj->wp_is_published = ($n->nodeValue !== 'draft');
			}
			elseif ($name === 'wp:post_id') {
				$obj->wpid = (int)$n->nodeValue;
			}
			elseif ($name === 'wp:postmeta') {
				# ahh... just discard this stuff
				#$k = $v = null;
				#foreach ($n->childNodes as $n2) {
				#	if ($n2->nodeType !== XML_ELEMENT_NODE)
				#		continue;
				#	if ($n->nodeName === 'wp:meta_key')
				#		$k = 
				#}
			}
			elseif ($name === 'wp:post_type') {
				# discard
			}
			elseif ($name === 'dc:creator') {
				$obj->author = (object)array(
					'name' => $n->nodeValue,
					'email' => GBUserAccount::getAdmin()->email
				);
			}
			elseif ($name === 'wp:comment') {
				if ($obj->comments === null)
					$obj->comments = array();
				$obj->comments[] = $this->parseComment($n);
			}
			elseif (substr($name, 0, 3) === 'wp:' && trim($n->nodeValue)) {
				$obj->meta[str_replace(array(':','_'),'-',$name)] = $n->nodeValue;
			}
		}
		
		# date
		$tzoffset = $dateutc !== false ? $datelocal-$dateutc : $fallbackTZOffset;
		$obj->modified = $obj->published = str_replace(' ','T',$datelocalstr).($tzoffset < 0 ? '-':'+').date('Hi', $tzoffset);
		
		return $obj;
	}
	
	function parseComment(DOMNode $comment) {
		static $map = array(
			'author' => 'name',
			'author_email' => 'email',
			'author_url' => 'uri',
			'author_IP' => 'ipAddress',
			'content' => 'message'
		);
		$datelocal = 0;
		$datelocalstr = 0;
		$dateutc = 0;
		$c = new WPComment();
		foreach ($comment->childNodes as $n) {
			if ($n->nodeType !== XML_ELEMENT_NODE)
				continue;
			$name = ''.$n->nodeName;
			$k = substr($name, 11);
			if ($k === 'date_gmt') {
				$dateutc = strtotime($n->nodeValue);
				$c->wpdateutc = strtotime($n->nodeValue.' UTC');
			}
			elseif ($k === 'date') {
				$datelocal = strtotime($n->nodeValue);
				$datelocalstr = $n->nodeValue;
			}
			elseif ($k === 'approved') {
				$c->approved = (bool)$n->nodeValue;
			}
			elseif ($k === 'id') {
				$c->wpid = (int)$n->nodeValue;
			}
			elseif ($k === 'type') {
				$c->type = ($n->nodeValue === 'pingback') ? 
					GBComment::TYPE_PINGBACK : GBComment::TYPE_COMMENT;
			}
			elseif (isset($map[$k])) {
				$dst = $map[$k];
				$c->$dst = $n->nodeValue;
			}
			else {
				gb::log(LOG_INFO, 'discarding '.$name);
			}
		}
		
		# date
		$tzoffset = $datelocal-$dateutc;
		$c->date = str_replace(' ','T',$datelocalstr).($tzoffset < 0 ? '-':'+').date('Hi', $tzoffset);
		
		# fix message
		if ($c->type === GBComment::TYPE_PINGBACK)
			$c->message = html_entity_decode($c->message, ENT_COMPAT, 'UTF-8');
		
		return $c;
	}
	
	function deduceChannelTimezoneOffset($channel) {
		# find timezone
		$diffs = array();
		foreach ($channel->getElementsByTagName('item') as $item) {
			if ($item->nodeType !== XML_ELEMENT_NODE)
				continue;
			$localdate = '';
			$utcdate = '';
			foreach ($item->childNodes as $n) {
				if ($n->nodeType !== XML_ELEMENT_NODE)
					continue;
				$nname = ''.$n->nodeName;
				if ($nname === 'wp:post_date')
					$localdate = $n->nodeValue;
				elseif ($nname === 'wp:post_date_gmt')
					$utcdate = $n->nodeValue;
			}
			if ($utcdate !== '0000-00-00 00:00:00') {
				# lets guess the timezone. yay
				$diff = strtotime($localdate)-strtotime($utcdate);
				if (isset($diffs[$diff]))
					$diffs[$diff]++;
				else
					$diffs[$diff] = 1;
			}
		}
		#var_export($diffs);
		if (count($diffs) === 1)
			return key($diffs);
		$k = array_keys($diffs);
		$mindiff = min($k[0],$k[1]);
		$difference = max($k[0],$k[1]) - $mindiff;
		if (count($diffs) === 2) {
			#$v = array_values($diffs);
			#echo "distribution ";var_dump(max($v[0],$v[1]) - min($v[0],$v[1]));
			#echo "difference ";var_dump($difference);
			#echo "variation ";var_dump(count($diffs));#floatval(max($k[0],$k[1])) / floatval($mindiff));
			#echo "occurance min/max ", min($v[0],$v[1]), '/', max($v[0],$v[1]), PHP_EOL;
			#echo "offsets min/max ", $mindiff, '/', max($k[0],$k[1]), PHP_EOL;
			if ($difference === $mindiff)
				return $mindiff; # most likely DST causing the variation, so 
		}
		gb::log(LOG_WARNING, 'unable to exactly deduce timezone -- guessing %s%d',
			($mindiff < 0 ? '-':'+'), $mindiff);
		return $mindiff;
	}
}

if (isset($_FILES['wpxml'])) {
	header('Content-type: text/plain; charset=utf-8');
	if ($_FILES['wpxml']['error'])
		exit(gb::log(LOG_ERR, 'file upload failed with unknown error'));
	$importer = new WordpressImporter();
	$importer->import(DOMDocument::load($_FILES['wpxml']['tmp_name']));
	exit(0);
}


gb::$title[] = 'Import Wordpress';
include '_header.php';
?>
<h2>Import a Wordpress blog</h2>
<form enctype="multipart/form-data" method="post" action="import-wordpress.php">
	<p>
		In your Wordpress (version &gt;=2.6) blog admin, go to tools &rarr; export and click the big button. Choose the file that was downloaded and click the "Import" button below. Yay. Let's hope this works.
	</p>
	<p>
		<input type="file" name="wpxml" />
	</p>
	<p>
		<input type="submit" value="Import" />
	</p>
</form>
<? include '_footer.php' ?>