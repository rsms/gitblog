# Content

Posts and pages are classed as *content* and internally represented by the class `GBExposedContent`.

A content object is compriced of a HTTP-like header and an optional body. Here's a quick example:

	title: Example of a simple post
	
	Hello <em>world</em>

## Header fields

Header filed names are case-insensitive and values can wrap over several rows (if subsequent rows are indented with at least one space or tab). The field name and value are separated by a colon (`":"`) and all of them are optional and replaced by default or deduced values if not specified.

<table>
	<tr>
		<th>Name</th><th>Alias</th><th>Value</th><th>Notes</th>
	</tr>
	<tr>
		<td valign="top">Title</td>
		<td></td>
		<td valign="top">Title of object.</td>
		<td valign="top">If not specified, the title will be deduced from the filename.</td>
	</tr>
	<tr>
		<td valign="top">Author</td>
		<td></td>
		<td valign="top">Name and/or email of author.</td>
		<td valign="top">
			Expects a git-style author format. (e.g. <tt>"Name Name &lt;e@ma.il&gt;"</tt>, 
			<tt>"Name"</tt>, <tt>"e@ma.il"</tt>, etc). If the author is not given by a
			header field, the author will be deduced by finding the initial commit for the
			object in question.
		</td>
	</tr>
	<tr>
		<td valign="top">Category</td>
		<td valign="top">Categories</td>
		<td valign="top">One or more categories separated by comma.</td>
		<td valign="top">
			Category names are case-insensitive and whitespace is trimmed (not stripped).
			If not specified, the object is not filed under any category
		</td>
	</tr>
	<tr>
		<td valign="top">Tags</td>
		<td valign="top">Tag</td>
		<td valign="top">One or more tags separated by comma.</td>
		<td valign="top">
			Tag names are case-insensitive and whitespace is trimmed (not stripped).
			If not specified, the object is not tagged with any tags.
		</td>
	</tr>
	<tr>
		<td valign="top">Publish</td>
		<td valign="top">Published</td>
		<td valign="top">Boolean[1] is published OR date and/or time when the object was or will be published.</td>
		<td valign="top">
			Defaults to the date parsed from the object file system path combined with
			(date and) time of the initial commit. If a date and/or time is specified without 
			timezone information, UTC is assumed. Examples: <tt>May 4, 2009 14:30 CEST</tt>, 
			<tt>12:47</tt>, <tt>2009-05-04 19:03:41 +0400</tt>, <tt>12:47 -0700</tt>. The date is
			parsed using a technique similar to the PHP function `strtotime` thus allowing for a
			wide array of different formats and resolutions.<br/><br/>
			
			Unless a complete (resoluton of a second) date and time is specified, the aforementioned
			merge algorithm is used. The logic is a s follows:<br/><br/>
			
			<pre><code>&lt;date and/or time parsed from file system path&gt;
  [ &lt;-- &lt;missing date resolution, time and zone from initial commit&gt; ]
  [ &lt;-- &lt;date and/or time and/or zone publish header field&gt; ]</code></pre><br/><br/>
			
			The later in the list the higher the priority. Commit date does not override file system
			path date, but completes it. If the date parsed from the filename expresses year and 
			month; day, hour, minute, second and zone are <em>added</em> from date of initial commit.
			However, the "publish" header field <em>overrides</em> any parts defined.
		</td>
	</tr>
	<tr>
		<td valign="top">Draft</td>
		<td></td>
		<td valign="top">Boolean[1] is draft (not published).</td>
		<td valign="top">Defaults to false if not specified.</td>
	</tr>
	<tr>
		<td valign="top">Comments</td>
		<td></td>
		<td valign="top">Boolean[1] allow comments.</td>
		<td valign="top">Defaults to true if not specified.</td>
	</tr>
	<tr>
		<td valign="top">Pingback</td>
		<td></td>
		<td valign="top">Boolean[1] send and receive pingbacks.</td>
		<td valign="top">Defaults to true if not specified.</td>
	</tr>
	<tr>
		<td valign="top">Hidden</td>
		<td valign="top">Hide, invisible</td>
		<td valign="top">Boolean[1] true the object should not appear in menus.</td>
		<td valign="top">Defaults to false (is visible) if not specified. Only applies to pages.</td>
	</tr>
	<tr>
		<td valign="top">Order</td>
		<td valign="top">Sort, priority</td>
		<td valign="top">Integer value explicitly setting the priority of menu order for a page object.</td>
		<td valign="top">Defaults to undefined if not specified. Menu items are sorted in two phases 
			-- first on order header field value, then on name/title.</td>
	</tr>
</table>

> **[1] Boolean values:** True values are `"true"`, `"yes"`, `"on"`, `"1"` or `""` (empty).
> Anything else is considered a false value. Values are case-insensitive.

Ancillary header fields -- not defined in this table -- will be passed through and made 
available in the `meta` property of `post` objects.

Example of presenting ancillary meta fields in a template:

	<h3>Meta fields</h3>
	<ul>
	<? foreach($post->meta as $name => $value): ?>
		<li><b><?= h($name) ?></b>: <?= h($value) ?></li>
	<? endforeach ?>
	</ul>

Plugins can use non-reserved header fields for special purposes. In such case a rebuild 
plugin should `unset` it's "special" fields from the post `meta` map:

	function on_reload_object($post) {
		if (isset($post->meta['my-special-field'])) {
			$special_field = $post->meta['my-special-field'];
			unset($post->meta['my-special-field']);
		}
		# do something with $special_field
	}

This construction (or guide or contract) exists because the fields in the post `meta` map 
should always be considered "harmless" metadata.

Plugins can of course also *set* meta fields which later can be read by templates. However 
as mentioned above, the `meta` map is defined as a sort of *opaque set of metadata* thus 
no standard templates make use of it.
