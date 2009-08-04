# Content

Posts and pages are classed as *content* and internally represented by the class `GBExposedContent`.

A content object is compriced of a HTTP-like header and an optional body. Here's a quick example:

	title: Example of a simple post
	custom-field: Values can span over
	  several rows.
	
	Hello <em>world</em>

The header is terminated by two linebreaks (`LF LF` or `CR LF CR LF`).

## Header fields

Field names are case-insensitive and values can wrap over several rows (if subsequent rows are indented with at least one space or tab). The field name and value are separated by a colon (`":"`).

### Standard header fields

All of these are optional and replaced by default values if not specified.

<table>
	<tr>
		<th>Name</th><th>Alias</th><th>Value</th><th>Notes</th>
	</tr>
	<tr>
		<td valign="top">Title</td>
		<td></td>
		<td valign="top">Title of object.</td>
		<td valign="top">If not specified, the title will be deduced from the filename.
			Sets the <code>title</code> property.</td>
	</tr>
	<tr>
		<td valign="top">Author</td>
		<td></td>
		<td valign="top">Name and/or email of author.</td>
		<td valign="top">
			Expects a git-style author format. (e.g. <code>"Name Name &lt;e@ma.il&gt;"</code>, 
			<code>"Name"</code>, <code>"e@ma.il"</code>, etc). If the author is not given by a
			header field, the author will be deduced by finding the initial commit for the
			object in question. Sets the <code>author</code> property (an anonymous object with 
			properties <code>name</code> and <code>email</code>).
		</td>
	</tr>
	<tr>
		<td valign="top">Category</td>
		<td valign="top">Categories</td>
		<td valign="top">One or more categories separated by comma.</td>
		<td valign="top">
			Category names are case-insensitive and whitespace is trimmed (not stripped).
			If not specified, the object is not filed under any category.
			Sets the <code>categories</code> property (an ordered list).
		</td>
	</tr>
	<tr>
		<td valign="top">Tags</td>
		<td valign="top">Tag</td>
		<td valign="top">One or more tags separated by comma.</td>
		<td valign="top">
			Tag names are case-insensitive and whitespace is trimmed (not stripped).
			If not specified, the object is not tagged with any tags.
			Sets the <code>tags</code> property (an ordered list).
		</td>
	</tr>
	<tr>
		<td valign="top">Publish</td>
		<td valign="top">Published</td>
		<td valign="top">Boolean[1] is published OR date and/or time when the object was or will be published.</td>
		<td valign="top">
			Defaults to the date parsed from the object file system path combined with
			(date and) time of the initial commit. If a date and/or time is specified without 
			timezone information, UTC is assumed. Examples: <code>May 4, 2009 14:30 CEST</code>, 
			<code>12:47</code>, <code>2009-05-04 19:03:41 +0400</code>, <code>12:47 -0700</code>. The date is
			parsed using a technique similar to the PHP function `strtotime` thus allowing for a
			wide array of different formats and resolutions.<br/><br/>
			
			Unless a complete (resoluton of a second) date and time is specified, the aforementioned
			merge algorithm is used. The logic is a s follows:
			
			<pre><code>&lt;date and/or time parsed from file system path&gt;
  [ &lt;-- &lt;missing date resolution, time and zone from initial commit&gt; ]
  [ &lt;-- &lt;date and/or time and/or zone publish header field&gt; ]</code></pre>
			
			The later in the list the higher the priority. Commit date does not override file system
			path date, but completes it. If the date parsed from the filename expresses year and 
			month; day, hour, minute, second and zone are <em>added</em> from date of initial commit.
			However, the "publish" header field <em>overrides</em> any parts defined.<br/><br/>
			
			Sets the <code>published</code> property (an instance of <code>GBDateTime</code>).
		</td>
	</tr>
	<tr>
		<td valign="top">Draft</td>
		<td></td>
		<td valign="top">Boolean[1] is draft (not published).</td>
		<td valign="top">Defaults to false if not specified. Sets the <code>draft</code> property.</td>
	</tr>
	<tr>
		<td valign="top">Comments</td>
		<td></td>
		<td valign="top">Boolean[1] allow comments.</td>
		<td valign="top">Defaults to true if not specified. Sets the <code>commentsOpen</code> property.</td>
	</tr>
	<tr>
		<td valign="top">Pingback</td>
		<td></td>
		<td valign="top">Boolean[1] send and receive pingbacks.</td>
		<td valign="top">Defaults to true if not specified. Sets the <code>pingbackOpen</code> property.</td>
	</tr>
	<tr>
		<td valign="top">Hidden</td>
		<td valign="top">Hide, invisible</td>
		<td valign="top">Boolean[1] true the object should not appear in menus.</td>
		<td valign="top">Defaults to false (is visible) if not specified. Only applies to pages.
			Sets the <code>hidden</code> property on `GBPage`s.</td>
	</tr>
	<tr>
		<td valign="top">Order</td>
		<td valign="top">Sort, priority</td>
		<td valign="top">Integer value explicitly setting the priority of menu order for a page object.</td>
		<td valign="top">Defaults to undefined if not specified. Menu items are sorted in two phases 
			-- first on order header field value, then on name/title.
			Sets the <code>order</code> property on `GBPage`s.</td>
	</tr>
</table>

> **[1] Boolean values:** True values are `"true"`, `"yes"`, `"on"`, `"1"` or `""` (empty).
> Anything else is considered a false value. Values are case-insensitive.


### Ancillary header fields

Ancillary header fields (not defined in "Standard header fields") will be passed through and made 
available in the `meta` property of `post` objects.

Example of presenting ancillary meta fields in a template:

	<h3>Meta fields</h3>
	<ul>
	<? foreach($post->meta as $name => $value): ?>
		<li><b><?= h($name) ?></b>: <?= h($value) ?></li>
	<? endforeach ?>
	</ul>

Plugins can use non-reserved header fields for special purposes. In such case a rebuild 
plugin is recommended to remove it's "special" fields from the `meta` map:

	class example_plugin {
		static function init($context) {
			gb::observe('did-reload-object', __CLASS__.'::will_reload_object');
		}
		
		static function did_reload_object($post) {
			if (isset($post->meta['my-special-field'])) {
				$special_field = $post->meta['my-special-field'];
				unset($post->meta['my-special-field']);
				# do something with $special_field ...
			}
		}
	}

Plugins can of course also *set* meta fields which later can be read by templates. However, the `meta` map is an *opaque set of metadata* in the eyes of gitblog core, thus no standard templates make use of it at the moment.
