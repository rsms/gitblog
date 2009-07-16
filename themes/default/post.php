<div class="wrapper">
	<div class="posts single">
		<div class="post">
			<h1><?= $post->title ?></h1>
			<p class="meta">
				<?= $post->published->age() ?>
				by <?= h($post->author->name) . $post->tagLinks(', tagged ') . $post->categoryLinks(', categorized as ')  ?>
				<?= $post->comments ? '('.$post->numberOfComments().')' : '' ?>
			</p>
			<div class="body">
				<?= $post->body ?>
			</div>
		</div>
		<div class="breaker"></div>
	</div>
	<? include gb::$theme_dir.'/sidebar.php' ?>
	<div class="breaker"></div>
</div>
<? flush() ?>
<div id="comments">
	<div class="wrapper">
	<? if ($post->comments): ?>
		<h3><?= $post->numberOfComments() ?></h3>
		<ul>
		<?
			$prevlevel = 0;
			foreach($post->comments as $level => $comment):
				if ($level > $prevlevel)
					echo '<ul>';
				elseif ($level < $prevlevel)
					echo str_repeat('</ul>', $prevlevel-$level);
				$prevlevel = $level;
			?>
			<li class="comment" id="comment-<?= $comment->id ?>">
				<img class="avatar" 
					src="http://www.gravatar.com/avatar.php?gravatar_id=<?= md5($comment->email) ?>&amp;size=48" />
				<div class="message-wrapper">
					<? if ($post->commentsOpen): ?>
					<div class="reply-link">
						<a href="javascript:reply('<?= $comment->id ?>');" title="Reply to this comment"><span>&#x21A9;</span></a>
					</div>
					<? endif; ?>
					<div class="author">
						<a href="#comment-<?= $comment->id ?>"><?= $comment->date->age() ?></a> <?= $comment->nameLink() ?> said
					</div>
					<div class="message">
						<?= $comment->body ?>
					</div>
				</div>
				<div class="breaker"></div>
			</li>
			<li class="breaker"></li>
		<? endforeach; echo str_repeat('</ul>', $prevlevel); ?>
		</ul>
		<div class="breaker"></div>
		<? if ($post->comments->countUnapproved()): ?>
			<p><small>
				<?= $post->numberOfUnapprovedComments() ?> awaiting approval
				<? if ($post->comments->countShadowed()): ?>
					— <?= $post->numberOfShadowedComments(
						'approved comment is', 'approved comments are', 'no', 'one') ?>
					therefore not visible.
				<? endif; ?>
			</small></p>
		<? endif; ?>
	<? endif; # comments ?>
	<? if ($post->commentsOpen): ?>
		<div id="reply"></div>
		<h3 id="reply-title">Add a comment</h3>
		<form id="comment-form" action="<?= gb::$site_url ?>gitblog/helpers/post-comment.php" method="POST">
			<?= gb_comment_fields() ?>
			<p>
				<textarea id="comment-reply-message" name="reply-message" rows="3"></textarea>
			</p>
			<p>
				<?= gb_comment_author_field('email', 'Email') 
					. gb_comment_author_field('name', 'Name')
				 	. gb_comment_author_field('url', 'Website (optional)') ?>
			</p>
			<p class="buttons">
				<input type="submit" value="Add comment" />
			</p>
			<div class="breaker"></div>
		</form>
		<script type="text/javascript">
			//<![CDATA[
			function trim(s) {
				return s.replace(/(^[ \t\s\n\r]+|[ \t\s\n\r]+$)/g, '');
			}
			document.getElementById('comment-form').onsubmit = function(e) {
				function check_filled(id, default_value) {
					var elem = document.getElementById(id);
					if (!elem)
						return false;
					elem.value = trim(elem.value);
					if (elem.value == default_value || elem.value == '') {
						elem.select();
						return false;
					}
					return true;
				}
				if (!check_filled('comment-reply-message', ''))
					return false;
				if (!check_filled('comment-author-name', 'Name'))
					return false;
				if (!check_filled('comment-author-email', 'Email'))
					return false;
				return true;
			}
	
			// reply-to
			var reply_to_comment = null;
	
			function reply(comment_id) {
				reply_to_comment = document.getElementById('comment-'+comment_id);
				document.getElementById('comment-reply-to').value = comment_id;
			}
	
			var reply_to = document.getElementById('comment-reply-to');
			var reply_to_lastval = "";
			var form_parent = null;
			var cancel_button = null;
	
			reply_to.onchange = function(e) {
				reply_to.value = trim(reply_to.value);
				var title = document.getElementById('reply-title');
				var form = document.getElementById('comment-form');

				// remove any cancel button
				if (cancel_button != null) {
					if (cancel_button.parentNode)
						cancel_button.parentNode.removeChild(cancel_button);
					cancel_button = null;
				}
		
				if (reply_to.value != "") {
					if (reply_to_comment == null) {
						reply_to.value = "";
						return;
					}
			
					if (form_parent == null)
						form_parent = form.parentNode;
			
					cancel_button = document.createElement('input');
					cancel_button.setAttribute('type', 'button');
					cancel_button.setAttribute('value', 'Cancel');
					cancel_button.onclick = function(e) { document.getElementById('comment-reply-to').value = ""; };
			
					// find submit button and append the form to its parent
					var inputs = form.getElementsByTagName("input");
					for (var i=0; i<inputs.length; i++) {
						var elem = inputs.item(i);
						if (elem.getAttribute('type') == 'submit') {
							elem.parentNode.appendChild(cancel_button);
							break;
						}
					}
					
					form.className = "inline-reply";
					reply_to_comment.appendChild(form);
					title.style.display = 'none';
			
					//document.location.hash = "comment-"+reply_to.value;
				}
				else {
					form.className = "";
					if (form_parent != null)
						form_parent.appendChild(form);
					title.style.display = '';
					document.location.hash = "reply";
				}	
				setTimeout(function(){
					document.getElementById('comment-reply-message').select()
				},100);
			}
			setInterval(function(e){
				if (reply_to_lastval != reply_to.value)
					reply_to.onchange(e);
				reply_to_lastval = reply_to.value;
			},200);
	
			// select message on reply
			setTimeout(function(){if (document.location.hash == '#reply')
				document.getElementById('comment-reply-message').select();
			},100);
		//]]>
		</script>
	<? else: ?>
		<p>Comments are closed.</p>
	<? endif; ?>
	</div>
</div>