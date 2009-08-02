# Filters

*This document is early work in progress*

Gitblog applies filters -- a sorted list of callables -- to different objects. For instance post body, comments, etc.

Plugins which alter the content of a gitblog hooks itself into one or more filter chains.

See the [akismet](../plugins/akismet.php) and [markdown](../plugins/markdown.php) plugins for examples and look at [lib/GBFilter.php](../lib/GBFilter.php) for details and to see what filters exists and can be hooked into.
