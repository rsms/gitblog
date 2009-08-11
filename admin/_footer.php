		<div class="breaker"></div>
		<address>
			Gitblog/<?= gb::$version ?>
			(Processing time: <?= gb_format_duration(microtime(true)-$gb_time_started) ?>,
			Git queries: <?= git::$query_count ?>)
		</address>
	</body>
</html>
