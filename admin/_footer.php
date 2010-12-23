		<div class="breaker"></div>
		<address>
			Gitblog/<?php echo gb::$version ?>
			(Processing time: <?php echo gb_format_duration(microtime(true)-$gb_time_started) ?>,
			Git queries: <?php echo git::$query_count ?>)
		</address>
	</body>
</html>
