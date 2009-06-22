<?

function pp_blob($matches) {
	return $matches[1];
}

function preprocess($file) {
	$s = file_get_contents($file);
	$s = preg_replace_callback('/<%blob ([^ \t\n\r]+)[ \t\n\r]*%>/', 'pp_blob', $s);
	return $s;
}

header('content-type: text/plain; charset=utf-8');
var_dump(preprocess('test2-input.php'));

?>