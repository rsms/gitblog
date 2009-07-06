<?
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');
require_once '_base.php';

# todo: auth

if (isset($_FILES['wpxml'])) {
	header('Content-type: text/plain; charset=utf-8');
	if ($_FILES['wpxml']['error'])
		exit('file upload failed with unknown error (1)');
	
	$doc = DOMDocument::load($_FILES['wpxml']['tmp_name']);
	$channel = $doc->getElementsByTagName('channel')->item(0);
	# todo read channel
	$items = $channel->getElementsByTagName('item');
	
	# mappings
	static $item_to_member = array(
		'content:encoded' => 'body',
		'wp:post_name' => 'slug',
		'wp:status' => 'status',
		'pubDate' => 'published',
		#'wp:post_date' => 'published',
	);
	static $item_to_meta = array(
		'title' => 'title'
	);
	 
	foreach ($items as $item) {
		# content object primitive
		$obj = (object)array(
			
			#'type' => $item->getElementsByTagName('post_type')->item(0)->nodeValue, # "page", "post", "attachment", etc
			# available as $obj->meta['wp-post-type']
			
			'slug' => null,
			'meta' => array(),
			'comments' => array(),
			'published' => null,
			'body' => null,
			'status' => 'publish'
		);
		
		foreach ($item->childNodes as $n) {
			# only care about elements
			if ($n->nodeType !== XML_ELEMENT_NODE)
				continue;
			
			# we're doing doing this to avoid a bug in php where accessing this property
			# from strpos or str_replace causes a silent hang.
			$nname = ''.$n->nodeName;
			
			if ($nname === 'wp:comment') {
				$comment = array();
				foreach ($n->childNodes as $cn) {
					if ($cn->nodeType !== XML_ELEMENT_NODE)
						continue;
					$cnn = ''.$cn->nodeName;
					$comment[substr($cnn, 11)] = $cn->nodeValue;
				}
				$obj->comments[] = $comment;
			}
			elseif ($nname === 'wp:postmeta') {
				# todo
				#<wp:postmeta>
				#<wp:meta_key>_pingme</wp:meta_key>
				#<wp:meta_value>1</wp:meta_value>
				#</wp:postmeta>
			}
			elseif (isset($item_to_member[$nname]))
				$obj->$item_to_member[$nname] = $n->nodeValue;
			elseif (isset($item_to_meta[$nname]))
				$obj->meta[$item_to_meta[$nname]] = $n->nodeValue;
			elseif (substr($nname, 0, 3) === 'wp:')
				$obj->meta[str_replace(array(':','_'),'-',$nname)] = $n->nodeValue;
			
		}
		
		# published?
		if ($obj->status === 'publish') {
			$obj->published = strtotime($obj->published);
		}
		else {
			$obj->published = false;
		}
		# todo: take care of case "draft"
		
		var_dump($obj);
	}
	
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