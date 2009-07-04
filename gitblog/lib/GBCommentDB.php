<?
class GBCommentDB extends JSONDB {
	public $lastComment = false;
	
	protected function txReadData() {
		parent::txReadData();
		foreach ($this->data as $k => $v)
			$this->data[$k] = new GBComment($v);
	}
	
	function commit() {
		parent::commit();
		#GitBlog::add($this->file);
		#$author = $this->lastComment ? $this->lastComment->gitAuthor() : null;
		#GitBlog::commit('comment', $author);
		#$this->lastComment = false;
	}
	
	function rollback() {
		parent::rollback();
		$this->lastComment = false;
	}
	
	function resolveIndexPath($indexpath, GBComment $comment) {
		foreach ($indexpath as $i) {
			if (!isset($comment->comments[$i]))
				return null;
			$comment = $comment->comments[$i];
		}
		return $comment;
	}
	
	function get($index=null) {
		if (is_string($index)) {
			$temptx = $this->txFp === false and $this->autocommit;
			if ($temptx)
				$this->begin();
			if ($this->data === null)
				$this->txReadData();
			$v = new GBComment(array('comments' => $this->data));
			$v = $this->resolveIndexPath(explode('.', $index), $v);
			if ($temptx)
				$this->txEnd();
			return $v;
		}
		else {
			return parent::get($index);
		}
	}
	
	function set($index, GBComment $comment=null) {
		$this->lastComment = $comment;
		if (is_string($index)) {
			$indexpath = explode('.', $index);
			if (!$indexpath)
				return;
			if (count($indexpath) === 1)
				return parent::set($indexpath[0], $comment);
			
			$superCommentIndex = intval(array_shift($indexpath));
			$superComment = $this->get($superCommentIndex);
			$subCommentIndex = array_pop($indexpath);
			$parentComment = $indexpath ? 
				$this->resolveIndexPath($indexpath, $superComment) : $superComment;
			if ($comment === null)
				unset($parentComment->comments[$subCommentIndex]);
			else
				$parentComment->comments[$subCommentIndex] = $comment;
			parent::set($superCommentIndex, $superComment);
		}
		else {
			parent::set($index, $comment);
		}
	}
	
	function append(GBComment $comment) {
		$temptx = $this->txFp === false and $this->autocommit;
		# begin if in temporary tx
		if ($temptx)
			$this->begin();
		# assure data is loaded
		if ($this->data === null)
			$this->txReadData();
		# add
		$this->lastComment = $comment;
		if (!$this->data)
			$this->data = array(1 => $comment);
		else
			$this->data[array_pop(array_keys($this->data))+1] = $comment;
		# commit if in temporary tx
		if ($temptx)
			$this->commit();
	}
	
	function remove($index) {
		$this->set($index);
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