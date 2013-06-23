<?php
ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '200M');
require_once '../_base.php';
gb::authenticate();

class WPPost extends GBPost {
	public $wpid = 0;
	public $wpparent;
	
	function buildHeaderFields() {
		$header = parent::buildHeaderFields();
		$header['wp-id'] = $this->wpid;
		if ($this->wpparent)
			$header['wp-parent'] = $this->wpparent;
		return $header;
	}
}

class WPPage extends GBPage {
	public $wpid = 0;
	public $wpparent;
	
	function buildHeaderFields() {
		$header = parent::buildHeaderFields();
		$header['wp-id'] = $this->wpid;
		if ($this->wpparent)
			$header['wp-parent'] = $this->wpparent;
		return $header;
	}
}

class WPAttachment extends GBContent {
	public $wpid = 0;
	public $wpparent;
	public $wpfilename;
	public $wpurl;
	public $wpmeta = array();
}

class WPComment extends GBComment {
	/*
	$date;
	$ipAddress;
	$email;
	$uri;
	$name;
	$body;
	$approved;
	$comments;
	*/
	public $wpid = 0; # used for sorting
	public $wpdateutc = 0; # used for sorting
}

class WordpressImporter {
	public $doc;
	public $objectCount;
	public $defaultAuthorEmail;
	
	public $includeAttachments = true;
	public $includeComments = true;
	
	public $debug = false;
	
	function __construct() {
		$this->importedObjectsCount = 0;
		#$this->defaultAuthorEmail = GBUser::findAdmin() ? GBUser::findAdmin()->email : '';
		$this->defaultAuthorEmail = '';
	}
	
	function writemsg($timestr, $msgstr, $cssclass='') {
		echo '<div class="msg '.$cssclass.'">'
				. '<p class="time">'.$timestr.'</p>'
				. '<p class="msg">'.$msgstr.'</p>'
				. '<div class="breaker"></div>'
			. '</div>'
			. '<script type="text/javascript" charset="utf-8">setTimeout(\'window.scrollBy(0,999999);\',50)</script>';
		gb_flush();
	}
	
	function report($msg) {
		$vargs = func_get_args();
		if(count($vargs) > 1) {
			$fmt = array_shift($vargs);
			$msg .= vsprintf($fmt, $vargs);
		}
		return $this->writemsg(date('H:i:s').substr(microtime(), 1, 4), $msg);
	}

	function reportError($msg) {
		$vargs = func_get_args();
		if(count($vargs) > 1) {
			$fmt = array_shift($vargs);
			$msg .= vsprintf($fmt, $vargs);
		}
		return $this->writemsg(date('H:i:s').substr(microtime(), 1, 4), $msg, 'error');
	}
	
	function dump($obj, $name=null) {
		return $this->writemsg(h($name !== null ? $name : gettype($obj)), '<pre>'.h(var_export($obj,1)).'</pre>');
	}
	
	function import(DOMDocument $doc, $commitChannels=true) {
		$this->doc = $doc;
		$count = 0;
		$exception = null;
		$timer = microtime(1);
		
		try {
			foreach ($doc->getElementsByTagName('channel') as $channel) {
				if ($channel->nodeType !== XML_ELEMENT_NODE)
					continue;
				$this->importChannel($channel, $commitChannels);
				$count++;
			}
		}
		catch (Exception $e) {
			$exception = $e;
		}
		
		if ($exception !== null)
			throw $exception;
		
		$timer = microtime(1)-$timer;
		
		$this->report('Imported '.counted($count, 'channel', 'channels', 'zero', 'one')
			. ' in '.gb_format_duration($timer));
	}
	
	function importChannel(DOMNode $channel, $commit) {
		$channel_name = $channel->getElementsByTagName('title')->item(0)->nodeValue;
		$this->report('Importing channel "'.h($channel_name).'"');
		$fallbackTZOffset = $this->deduceChannelTimezoneOffset($channel);
		$count_posts = 0;
		$count_pages = 0;
		$count_attachments = 0;
		$timer = microtime(1);
		
		git::reset(); # rollback any previously prepared commit
		
		try {
			foreach ($channel->getElementsByTagName('item') as $item) {
				if ($item->nodeType !== XML_ELEMENT_NODE)
					continue;
				$obj = $this->importItem($item, $fallbackTZOffset);
				if (!$obj)
					continue;
				if ($obj instanceof GBExposedContent) {
					$this->postProcessExposedContent($obj);
					$this->report('Importing '.($obj instanceof WPPost ? 'post' : 'page').' '.h($obj->name)
						.' "'.h($obj->title).'" by '.h($obj->author->name).' published '.$obj->published);
					if ($this->writeExposedContent($obj)) {
						if ($obj instanceof WPPost)
							$count_posts++;
						else
							$count_pages++;
					}
				}
				elseif ($obj instanceof WPAttachment) {
					$this->postProcessAttachment($obj);
					$this->report('Importing attachment '.h($obj->name).' ('.h($obj->wpurl).')');
					if ($this->writeAttachment($obj))
						$count_attachments++;
				}
				if ($this->debug)
					$this->dump($obj);
			}
		
			$timer = microtime(1)-$timer;
			$count = $count_posts+$count_pages+$count_attachments;
			$this->importedObjectsCount += $count;
			
			$message = 'Imported '
				. counted($count, 'object', 'objects', 'zero', 'one')
				. ' ('
				. counted($count_posts, 'post', 'posts', 'zero', 'one')
				. ', '
				. counted($count_pages, 'page', 'pages', 'zero', 'one')
				. ' and '
				. counted($count_attachments, 'attachment', 'attachments', 'zero', 'one')
				. ')';
		
			$this->report($message.' from channel "'.h($channel_name).'"'
				. ' in '.gb_format_duration($timer));
			
			if ($commit) {
				$this->report('Creating commit...');
				try {
					git::commit($message.' from Wordpress blog '.$channel_name,
						GBUser::findAdmin()->gitAuthor());
					$this->report('Committed to git repository');
				}
				catch (GitError $e) {
					if (strpos($e->getMessage(), 'nothing added to commit') !== false)
						$this->report('Nothing committed because no changes where done');
					else
						throw $e;
				}
			}
		}
		catch (Exception $e) {
			git::reset(); # rollback prepared commit
			throw $e;
		}
	}
	
	function postProcessExposedContent(GBExposedContent $obj) {
		# Draft objects which have never been published does not have a slug, so
		# we derive one from the title:
		if (!$obj->slug)
			$obj->slug = gb_cfilter::apply('sanitize-title', $obj->title);
		else
			$obj->slug = preg_replace('/\/+/', '-', urldecode($obj->slug));
		# pathspec
		if ($obj instanceof WPPost)
			$obj->name = 'content/posts/'.$obj->published->utcformat('%Y/%m/%d-').$obj->slug.'.html';
		else
			$obj->name = 'content/pages/'.$obj->slug.'.html';
	}
	
	function postProcessAttachment(WPAttachment $obj) {
		# pathspec
		$obj->name = 'attachments/'.$obj->published->utcformat('%Y/%m/').basename($obj->wpfilename);
	}
	
	function mkdirs($path, $maxdepth, $mode=0775) {
		if ($maxdepth <= 0)
			return;
		$parent = dirname($path);
		if (!is_dir($parent))
			$this->mkdirs($parent, $maxdepth-1, $mode);
		mkdir($path, $mode);
		@chmod($path, $mode);
	}
	
	function writeExposedContent(GBExposedContent $obj) {
		gb_admin::write_content($obj);
		git::add($obj->name);
		if ($this->includeComments)
			$this->writeComments($obj);
		return true;
	}
	
	function writeComments(GBExposedContent $obj) {
		if (!$obj->comments)
			return;
		
		# sort
		ksort($obj->comments);
		
		# init comments db
		$cdb = $obj->getCommentsDB();
		$cdb->autocommitToRepo = false;
		$cdb->begin(true);
		try {
			foreach ($obj->comments as $comment)
				$cdb->append($comment);
		}
		catch (Exception $e) {
			$cdb->rollback();
			throw $e;
		}
		$cdb->commit();
		git::add($cdb->file);
	}
	
	function writeAttachment(WPAttachment $obj) {
		$dstpath = gb::$site_dir.'/'.$obj->name;
		
		$dstpathdir = dirname($dstpath);
		if (!is_dir($dstpathdir))
			$this->mkdirs($dstpathdir, count(explode('/', trim($obj->name, '/'))));
		
		try {
			copy($obj->wpurl, $dstpath);
			@chmod($dstpath, 0664);
			return true;
		}
		catch (RuntimeException $e) {
			$this->reportError($e->getMessage());
			return false;
		}
	}
	
	function createItemObject(DOMNode $item) {
		$type = $item->getElementsByTagName('post_type')->item(0);
		# "page", "post", "attachment"
		switch ($type ? $type->nodeValue : '?') {
			case 'post': return new WPPost();
			case 'page': return new WPPage();
			case 'attachment': return new WPAttachment();
		}
		return null;
	}
	
	function importItem(DOMNode $item, $fallbackTZOffset=0) {
		$obj = $this->createItemObject($item);
		if ($obj === null) {
			$this->report('discarded unknown item <code>%s<code>', h($this->doc->saveXML($item)));
			return null;
		}
		
		if (!$this->includeAttachments && $obj instanceof WPAttachment)
			return null;
		
		$is_exposed = !($obj instanceof WPAttachment);
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
			
			if ($is_exposed && $name === 'title') {
				$obj->title = $n->nodeValue;
			}
			elseif ($is_exposed && $name === 'content:encoded') {
				$obj->body = $n->nodeValue;
			}
			elseif ($name === 'excerpt:encoded') {
				# will be derived from body by the content rebuilder, so in case WP
				# adds this in the future, just discard it. (In WP 2.6 this is never
				# present anyway.)
			}
			elseif ($is_exposed && $name === 'category') {
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
			elseif ($is_exposed && $name === 'wp:comment_status') {
				$obj->commentsOpen = ($n->nodeValue === 'open');
			}
			elseif ($is_exposed && $name === 'wp:ping_status') {
				$obj->pingbackOpen = ($n->nodeValue === 'open');
			}
			elseif ($is_exposed && $name === 'wp:post_name') {
				$obj->slug = $n->nodeValue;
			}
			elseif ($name === 'wp:post_date_gmt') {
				if ($n->nodeValue !== '0000-00-00 00:00:00')
					$dateutc = new GBDateTime($n->nodeValue);
			}
			elseif ($name === 'wp:post_date') {
				$datelocalstr = $n->nodeValue;
				$datelocal = new GBDateTime($n->nodeValue);
				$obj->wpdate = $datelocal;
			}
			elseif ($name === 'wp:menu_order' && ($obj instanceof WPPage)) {
				$obj->order = intval($n->nodeValue);
			}
			elseif ($is_exposed && $name === 'wp:status') {
				$obj->draft = ($n->nodeValue === 'draft');
			}
			elseif ($name === 'wp:post_id') {
				$obj->wpid = (int)$n->nodeValue;
			}
			elseif ($name === 'wp:postmeta') {
				if ($is_exposed === false) {
					# get attachment filename
					$k = $v = null;
					foreach ($n->childNodes as $n2) {
						if ($n2->nodeType !== XML_ELEMENT_NODE)
							continue;
						if ($n2->nodeName === 'wp:meta_key') {
							$k = $n2->nodeValue;
						}
						elseif ($n2->nodeName === 'wp:meta_value') {
							if ($k === '_wp_attached_file')
								$obj->wpfilename = $n2->nodeValue;
							elseif ($k === '_wp_attachment_metadata') {
								$obj->wpmeta = @unserialize(trim($n2->nodeValue));
								if ($obj->wpmeta === false)
									$this->wpmeta = array();
							}
						}
					}
				}
			}
			elseif ($name === 'wp:post_parent') {
				$obj->wpparent = $n->nodeValue;
			}
			elseif ($is_exposed === false && $name === 'wp:attachment_url') {
				$obj->wpurl = $n->nodeValue;
			}
			elseif ($name === 'wp:post_type') {
				# discard
			}
			elseif ($name === 'dc:creator') {
				$obj->author = new GBAuthor($n->nodeValue, $this->defaultAuthorEmail);
			}
			elseif ($is_exposed && $name === 'wp:comment') {
				if ($obj->comments === null)
					$obj->comments = array();
				$comment = $this->parseComment($n, $wpid);
				$comment->post = $obj;
				$obj->comments[$wpid] = $comment;
			}
			elseif ($is_exposed && substr($name, 0, 3) === 'wp:' && trim($n->nodeValue)) {
				$obj->meta[str_replace(array(':','_'),'-',$name)] = $n->nodeValue;
			}
		}
		
		if ($is_exposed && $obj->comments)
			$this->report('Imported '.counted(count($obj->comments), 'comment', 'comments', 'zero', 'one'));
		
		# date
		$obj->modified = $obj->published = $this->_mkdatetime($datelocal, $dateutc, $datelocalstr, $fallbackTZOffset);
		
		return $obj;
	}
	
	function _mkdatetime($local, $utc, $localstr, $fallbackTZOffset) {
		$tzoffset = $utc !== false ? $local->time - $utc->time : $fallbackTZOffset;
		if ($tzoffset !== 0)
			return new GBDateTime(str_replace(' ','T',$localstr).($tzoffset < 0 ? '-':'+').date('Hi', $tzoffset));
		else
			return $utc;
	}
	
	function parseComment(DOMNode $comment, &$wpid) {
		static $map = array(
			'author' => 'name',
			'author_email' => 'email',
			'author_url' => 'uri',
			'author_IP' => 'ipAddress',
			'content' => 'body'
		);
		$datelocal = 0;
		$datelocalstr = 0;
		$dateutc = 0;
		$c = new GBComment();
		$wpid = 0;
		
		foreach ($comment->childNodes as $n) {
			if ($n->nodeType !== XML_ELEMENT_NODE)
				continue;
			$name = ''.$n->nodeName;
			$k = substr($name, 11);
			if ($k === 'date_gmt') {
				$dateutc = new GBDateTime($n->nodeValue);
			}
			elseif ($k === 'date') {
				$datelocal = new GBDateTime($n->nodeValue);
				$datelocalstr = $n->nodeValue;
			}
			elseif ($k === 'approved') {
				$c->approved = (bool)$n->nodeValue;
			}
			elseif ($k === 'id') {
				$wpid = (int)$n->nodeValue;
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
		$c->date = $this->_mkdatetime($datelocal, $dateutc, $datelocalstr, 0);
		
		# fix pingback message
		#if ($c->type === GBComment::TYPE_PINGBACK)
		#	$c->body = html_entity_decode($c->body, ENT_COMPAT, 'UTF-8');
		
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

@ini_set('max_execution_time', '0');
set_time_limit(0);

gb::$title[] = 'Import Wordpress';

if (isset($_FILES['wpxml'])) {
	if ($_FILES['wpxml']['error'])
		gb::$errors[] = 'file upload failed with unknown error (maybe you forgot to select the file?).';
	
	if (!gb::$errors) {
		$importer = new WordpressImporter();
		$importer->includeAttachments = @gb_strbool($_POST['include-attachments']);
		$importer->includeComments = @gb_strbool($_POST['include-comments']);
	}
	
	include_once '../_header.php';
	?><style type="text/css">
		div.msg {
			border-top:1px solid #ddd;
		}
		div.msg.error { background:#fed; }
		div.msg p.time {
			float:left;
			font-family:monospace;
			color:#357;
			width:15%;
			margin:5px 0;
		}
		div.msg.error p.time { color:#942; }
		div.msg.error p.msg { color:#720; }
		div.msg p.msg {
			float:left;
			width:85%;
			margin:5px 0;
		}
		div.msg p.msg pre {
			font-size:80%;
		}
		p.done {
			font-size:500%;
			text-align:center;
			padding:50px 0 30px 0;
			font-family:didot,georgia;
			color:#496;
		}
		p.donelink {
			text-align:center;
			padding:10px 0 100px 0;
		}
		p.failure {
			font-size:200%;
			text-align:center;
			padding:50px 0 30px 0;
			color:#946;
		}
		p.failure pre {
			text-align:left;
			font-size:50%;
		}
	</style><?php
	
	if (!gb::$errors) {
		echo '<h2>Importing '. h(basename($_FILES['wpxml']['name'])) .'</h2>';
		try {
			$importer->import(DOMDocument::load($_FILES['wpxml']['tmp_name']));
			?>
			<p class="done">
				Yay! I've imported <?php echo counted($importer->importedObjectsCount, 'object','objects','zero','one') ?>.
			</p>
			<p class="donelink">
				<a href="<?php echo gb::$site_url ?>">Have a look at your blog &rarr;</a>
			</p>
			<?php
		}
		catch (Exception $e) {
			?>
			<p class="failure">
				Import failed: <em><?php echo h($e->getMessage()) ?></em>
			</p>
			<p>
				<pre><?php echo h(strval($e)) ?></pre>
			</p>
			<?php
		}
		echo '<script type="text/javascript" charset="utf-8">setTimeout(\'window.scrollBy(0,999999);\',50)</script>';
	}
}
if (!isset($_FILES['wpxml']) || gb::$errors) {
	include_once '../_header.php';
?>
<style type="text/css">
	ol.steps { padding-left:3em; }
	ol.steps li {   font-size:1.6em; font-weight:bold;   color:#ff7368; width:500px; border-top:1px solid #eee; }
	ol.steps li p { font-size:13px;  font-weight:normal; color:#333; }
	input.submit {
		background:#0cc771;  width:100px; height:25px; font-size:16px; font-weight:bold;
		color:#00331a;
		text-shadow:#9ce0b3 0px 1px 0px;
		border:none;
		-moz-border-radius:     12px;
		-khtml-border-radius:   12px;
		-webkit-border-radius:  12px;
		border-radius:          12px;
		margin:0 1em;
	}
	input.submit:hover { background:#1ad47e; }
	input.submit:active { background:#07ac60; }
	label { display:block; margin:.5em 0; }
	label small { display:block; margin:.5em 0 1em 22px; color:#999; font-size:10px; 
		font-family:"lucida grande", tahoma,sans-serif; }
	input[type=checkbox] { margin-right:5px; }
	form { margin-bottom:5em; }
	#disclaimer { width:540px; color:#888; border-top:1px solid #eee; padding-top:10px; }
	#disclaimer h3 { color:#666; }
</style>
<div id="content" class="<?php echo gb_admin::$current_domid ?> margins">
	<h2>Import a Wordpress blog</h2>
	<div id="the-important-part">
		<p>
			This tool lets you import one or more Wordpress blogs.
		</p>
		<form enctype="multipart/form-data" method="post" action="import-wordpress.php">
			<ol class="steps">
				<li><p>
					In your Wordpress blog admin, go to "Tools" &rarr; 
					<a href="http://codex.wordpress.org/Tools_Export_SubPanel">"Export"</a>
					and click the big button "Download Export File".
				</p></li>
				<li><p>
					Find the downloaded file (named something like "wordpress.2009-07-15.xml"):<br/>
					<input type="file" name="wpxml" class="wpxml" />
				</p></li>
				<li>
					<p>
						<b>Optional...options:</b>
					</p>
					<p>
						<label>
							<input type="checkbox" name="include-attachments" value="true" checked="checked" />
							Download attachments
							<small>
								If you uncheck this, no attachment files will be downloaded but links to attachments (i.e. embedded images) in posts and pages will remain. Unchecking this might be a good idea if your Wordpress blog does no longer exist as there is then no point in trying to download non-existing files :P
							</small>
						</label>
						<label>
							<input type="checkbox" name="include-comments" value="true" checked="checked" />
							Include comments
							<small>
								Uncheck this if you do not wish to have comments imported. It's a good idea if you're a prick and people said bad things about you. This is your chance to a clean slate! Seriously, you can easily remove all or some comments afterwards so we recommend you to keep this option checked.
							</small>
						</label>
					</p>
				</li>
				<li>
					<p>
						<b>Click this pretty button to initiate the import:</b>
						<input type="submit" value="Import" class="submit" />
					</p>
					<p>
						<em>You will get live feedback during the import process. Watch this space.</em>
					</p>
				</li>
			</ol>
		</form>
	</div>
	<div id="disclaimer">
		<h3>Compatibility and in-case-of-emergency</h3>
		<p>
			Currently this has only been tested with Wordpress 2.6 and 2.7 — importing older versions might work (worst case scenario: you'll get an error message). But older versions should work, as the WordPress eXtended RSS or WXR file was introduced in 2006 and have remained more or less the same since then.
		</p>
		<p>
			Due to the nature of Gitblog, the import is done in one transaction per "RSS channel". This means that if an error occurs, you abort the process or something else causes the import process to end prematurely, nothing will be imported into the live stage but will be left in your working stage. Use the regular <tt>git status</tt> to get a list of what was added or modified.
		</p>
	</div>
</div>
<?php
} # end if posted file
include '../_footer.php' ?>