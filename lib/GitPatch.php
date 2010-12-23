<?php
# Fore details of format, see section "GENERATING PATCHES WITH -P" in man git-diff

class GitPatch {
	const EDIT_IN_PLACE = 'M';
	const COPY = 'C';
	const RENAME = 'R';
	const CREATE = 'A';
	const DELETE = 'D';
	
	public $action;
	public $lines;
	public $prevobj;
	public $prevname;
	public $currobj;
	public $currname;
	public $delta;
	public $meta;
	public $mode;
	
	function __construct($action) {
		$this->action = $action;
		$this->lines = array();
		$this->meta = array();
	}

	static function parse($udiff) {
		$patches = array();
		$currpatch = null;
		$passed_delta = false;
		
		while ( ($line = gb_sreadline($p, $udiff)) !== null) {
			#var_dump($line);
			if (!$line)
				continue;
		
			$start3 = substr($line, 0, 3);
		
			# line 1 -- new set
			if ($start3 === 'dif') {
				# flush previous
				if ($currpatch !== null)
					$patches[] = $currpatch;
				# new
				$currpatch = new GitPatch(GitPatch::EDIT_IN_PLACE);
				
				$s = explode(' ', $line, 3);
				if (isset($s[2])) {
					$s = explode(' ', $s[2]);
					$n = intval(count($s)/2);
					$s = trim(implode(' ', array_slice($s, $n)));
					$s = substr($s, strpos($s, '/')+1);
					$currpatch->currname = $s;
				}
				$passed_delta = false;
			}
		
			# content
			elseif($passed_delta) {
				$currpatch->lines[] = $line;
			}
		
			# prev/curr name
			elseif ($start3 === '---' || $start3 === '+++') {
				$s = rtrim(substr($line, 4));
				if ($s === '/dev/null')
					$s = null;
				else
					$s = substr($s, 2); # remove trailing "a/"
				if ($start3 === '---')
					$currpatch->prevname = $s;
				else
					$currpatch->currname = $s;
			}
		
			# curr name
			elseif ($start3 === '@@ ') {
				$currpatch->delta = substr($line, 3, -3);
				$passed_delta = true;
			}
		
			# header lines
			# old  old mode <mode>
			# new  new mode <mode>
			#      new file mode <mode>
			# del  deleted file mode <mode>
			# cop  copy from <path>
			#      copy to <path>
			# ren  rename from <path>
			#      rename to <path>
			# sim  similarity index <number>
			# dis  dissimilarity index <number>
			# ind  index <hash>..<hash> <mode>
		
			# old mode <mode>
			elseif ($start3 === 'old') {
				$currpatch->meta['old_mode'] = intval(substr($line, 9));
			}
		
			# new mode <mode>
			# new file mode <mode>
			elseif ($start3 === 'new') {
				if (substr($line, 4, 4) === 'file') {
					$currpatch->mode = intval(substr($line, 14));
					$currpatch->meta['new_file_mode'] = $currpatch->mode;
					$currpatch->action = self::CREATE;
				}
				else {
					$currpatch->mode = intval(substr($line, 9));
					$currpatch->meta['new_mode'] = $currpatch->mode;
				}
			}
		
			# deleted file mode <mode>
			elseif ($start3 === 'del') {
				$currpatch->meta['deleted_file_mode'] = intval(substr($line, 18));
				$currpatch->action = self::DELETE;
			}
		
			# copy from <path>
			# copy to <path>
			elseif ($start3 === 'cop') {
				$x = strpos($line, ' ', 5);
				$currpatch->meta['copy_'.substr($line, 5, $x-5)] = rtrim(substr($line, $x+1));
				$currpatch->action = self::COPY;
			}
		
			# rename from <path>
			# rename to <path>
			elseif ($start3 === 'ren') {
				$x = strpos($line, ' ', 7);
				$currpatch->meta['rename_'.substr($line, 7, $x-7)] = rtrim(substr($line, $x+1));
				$currpatch->action = self::RENAME;
			}
		
			# similarity index <number>
			elseif ($start3 === 'sim') {
				$currpatch->meta['similarity_index'] = intval(substr($line, 17));
			}
		
			# dissimilarity index <number>
			elseif ($start3 === 'dis') {
				$currpatch->meta['dissimilarity_index'] = intval(substr($line, 20));
			}
		
			# index <hash>..<hash> <mode>
			elseif ($start3 === 'ind') {
				$v = explode(' ', substr($line, 6));
				$objs = explode('..', $v[0]);
				$currpatch->prevobj = $objs[0] === '0000000000000000000000000000000000000000' ? null : $objs[0];
				$currpatch->currobj = $objs[1] === '0000000000000000000000000000000000000000' ? null : $objs[1];
				if (isset($v[1]))
					$currpatch->mode = intval($v[1]);
			}
		
		}
		# flush
		if ($currpatch !== null)
			$patches[] = $currpatch;
		
		return $patches;
	}
}
?>