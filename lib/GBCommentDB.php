<?php
class GBCommentDB extends JSONStore {
	public $lastComment = false;
	public $post = null;
	public $autocommitToRepo = true;
	public $autocommitToRepoMessage = 'comment autocommit';
	
	function __construct($file='/dev/null', $post=null, $skeleton_file=null, 
	                     $createmode=0660, $autocommit=true, $pretty_output=true)
	{
		parent::__construct($file, $skeleton_file, $createmode, $autocommit, $pretty_output);
		$this->post = $post;
	}
	
	function parseData() {
		parent::parseData();
		foreach ($this->data as $k => $v) {
			$c = new GBComment($v);
			$c->post = $this->post;
			$this->data[$k] = $c;
		}
	}
	
	function encodeData() {
		$c = new GBComment();
		$c->comments = $this->data;
		$it = new GBCommentsIterator($c);
		foreach ($it as $k => $comment) {
			if (is_object($comment->date))
				$comment->date = strval($comment->date);
		}
		parent::encodeData();
	}
	
	function commit() {
		$this->autocommitToRepoAuthor = $this->lastComment ? $this->lastComment->gitAuthor() : null;
		$r = parent::commit();
		$this->lastComment = false;
		return $r;
	}
	
	protected function txWriteData() {
		$r = parent::txWriteData();
		if ($r === false && $this->data != $this->originalData && $this->readOnly)
			throw new RuntimeException($this->file.' is not writable');
		return $r;
	}
	
	function rollback($strict=true) {
		parent::rollback($strict);
		$this->lastComment = false;
	}
	
	function resolveIndexPath($indexpath, $comment, $skipIfSameAsComment=null) {
		foreach ($indexpath as $i) {
			if (!isset($comment->comments[$i]))
				return null;
			$comment = $comment->comments[$i];
			if ($skipIfSameAsComment !== null && $skipIfSameAsComment->duplicate($comment))
				return null;
		}
		return $comment;
	}
	
	function get($index=null) {
		if (is_string($index)) {
			$temptx = $this->txFp === false && $this->autocommit;
			if ($temptx)
				$this->begin();
			if ($this->data === null)
				$this->txReadData();
			$v = new GBComment();
			$v->post = $this->post;
			$v->comments =& $this->data;
			$v = $this->resolveIndexPath(explode('.', $index), $v);
			if ($temptx)
				$this->txEnd();
			return $v;
		}
		else {
			return parent::get($index);
		}
	}
	
	/**
	 * Set or remove a comment.
	 * 
	 * By not passing the second argument the action will be to remove a comment
	 * rather than setting it to null. You can also pass an array as the first
	 * and only argument in which case the whole underlying data set is replaced.
	 * You have been warned.
	 * 
	 * Return values:
	 * 
	 *  - After a set operation, true is returned on success, otherwise false.
	 * 
	 *  - After a remove operation, the removed comment is returned (if found and
	 *    removed), otherwise false.
	 */
	function set($index, GBComment $comment=null) {
		$return_value = true;
		if ($comment !== null && $comment->post === null)
			$comment->post = $this->post;
		$this->lastComment = $comment;
		if (is_string($index)) {
			$indexpath = explode('.', $index);
			if (!$indexpath)
				return false;
			if (count($indexpath) === 1)
				return parent::set($indexpath[0], $comment);
			$superCommentIndex = intval(array_shift($indexpath));
			$superComment = $this->get($superCommentIndex);
			$subCommentIndex = array_pop($indexpath);
			$parentComment = $indexpath ? 
				$this->resolveIndexPath($indexpath, $superComment) : $superComment;
			if ($comment === null) {
				# delete
				if (($return_value = isset($parentComment->comments[$subCommentIndex]))) {
					$return_value = $parentComment->comments[$subCommentIndex];
					unset($parentComment->comments[$subCommentIndex]);
				}
			}
			else {
				# set
				$parentComment->comments[$subCommentIndex] = $comment;
				$return_value = true;
			}
			parent::set($superCommentIndex, $superComment);
		}
		else {
			return parent::set($index, $comment);
		}
		return $return_value;
	}
	
	function append(GBComment $comment, $index=null, $skipDuplicate=true) {
		if ($comment->post === null)
			$comment->post = $this->post;
		$temptx = $this->txFp === false && $this->autocommit;
		# begin if in temporary tx
		if ($temptx)
			$this->begin();
		try {
			# assure data is loaded
			if ($this->data === null)
				$this->txReadData();
			# add
			$newindex = false;
			$this->lastComment = $comment;
			if ($index !== null) {
				if (!$this->data)
					throw new OutOfBoundsException('invalid comment index '.$index);
			
				$indexpath = explode('.', $index);
				if (!$indexpath)
					throw new InvalidArgumentException('$index is empty');
				if (count($indexpath) === 1) {
					if (!isset($this->data[$indexpath[0]]))
						throw new OutOfBoundsException('invalid comment index '.$index);
					$parentc = $this->data[$indexpath[0]];
				}
				else {
					$rootc = new GBComment();
					$rootc->comments =& $this->data;
					$parentc = $this->resolveIndexPath($indexpath, $rootc, $comment);
				}
			
				if (!$parentc)
					throw new OutOfBoundsException('invalid comment index '.$index);
			
				if ( ($skipDuplicate && !$parentc->duplicate($comment)) || !$skipDuplicate )
					$newindex = $index.'.'.$parentc->append($comment);
			}
			else {
				if (!$this->data) {
					$newindex = 1;
					$this->data = array(1 => $comment);
				}
				else {
					$newindex = array_pop(array_keys($this->data))+1;
					$skip = false;
					if ($skipDuplicate) {
						# look at previous comments and see if we have a dup
						$parentc = new GBComment(array('comments' => $this->data));
						$it = new GBCommentsIterator($parentc);
						foreach ($it as $c) {
							if ($c->duplicate($comment)) {
								$skip = true;
								break;
							}
						}
					}
					if ($skip)
						$newindex = false;
					else
						$this->data[$newindex] = $comment;
				}
			}
		}
		catch (Exception $e) {
			if ($temptx)
				$this->rollback();
			throw $e;
		}
		# commit if in temporary tx
		if ($temptx)
			$this->commit();
		
		# set comment->id and return it, unless false
		if ($newindex !== false) {
			$comment->id = strval($newindex);
			return $newindex;
		}
		return $newindex;
	}
	
	function remove($index) {
		return $this->set($index);
	}
}

/*
# tests
$c = new GBComment(array('name'=>'John Doe', 'email'=>'john@doe.com'));
$cdb = new GBCommentDB('/Users/rasmus/Desktop/comments.json');

# Dump & clear
var_export($cdb->get());
$cdb->set(array());

# Append super-comment
$cdb->append($c);
$cdb->append($c);
var_export($cdb->get());

# Remove super-comment
$cdb->remove(2);
var_export($cdb->get());

# Append sub-comment
$c = $cdb->get(1);
for ($i=0;$i<3;$i++) {
	$c2 = new GBComment(array('name'=>'Mos Master '.$i, 'email'=>'moset'.$i.'@gmail.com'));
	$c->append($c2);
	$c3 = new GBComment(array('name'=>'Yxi Kaksi '.$i, 'email'=>'yxan'.$i.'@hotmail.com'));
	$c2->append($c3);
}
$cdb->set(1, $c);
var_export($cdb->get());

# Work with sub comments and index paths
$c = $cdb->get('1.3');
var_export($c);
$c2 = new GBComment(array('name'=>'Rolf Von Bulgur', 'email'=>'roffe@intertubes.com'));
$cdb->set('1.3', $c2);
var_export($cdb->get('1.3'));
$cdb->set('1.3', $c);
var_export($cdb->get('1.3'));

# depth 3
$c = $cdb->get('1.2.1');
var_export($c);
$c2 = new GBComment(array('name'=>'Henry Lols', 'email'=>'lol@fluff.gr'));
$cdb->set('1.2.1', $c2);
var_export($cdb->get('1.2.1'));
$cdb->set('1.2.1', $c);
var_export($cdb->get('1.2.1'));
*/
?>