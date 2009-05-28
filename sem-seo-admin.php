<?php
/**
 * sem_seo_admin
 *
 * @package Semiologic SEO
 **/

add_action('settings_page_seo', array('sem_seo_admin', 'save_options'), 0);

class sem_seo_admin {
	/**
	 * save_options()
	 *
	 * @return void
	 **/

	function save_options() {
		if ( !$_POST )
			return;
		
		check_admin_referer('sem_seo');
		
		$meta_fields = array_keys(sem_seo_admin::get_fields('ext_meta'));
		
		foreach ( $meta_fields as $field ) {
			switch ( $field ) {
			case 'title':
			case 'keywords':
			case 'description':
				$$field = trim(strip_tags(stripslashes($_POST[$field])));
				break;
			
			default:
				$$field = isset($_POST[$field]);
				break;
			}
		}
		
		$archive_fields = array_keys(sem_seo_admin::get_fields('archives'));
		
		foreach ( $archive_fields as $field ) {
			if ( in_array($_POST[$field], array('list', 'raw_list', 'excerpts')) )
				$$field = $_POST[$field];
			else
				$$field = false;
		}
		
		update_option('sem_seo', compact(array_merge($meta_fields, $archive_fields)));
		delete_option('sem_seo');
		sem_seo::init_options();
		echo '<div class="updated">' . "\n"
			. '<p>'
				. '<strong>'
				. __('Settings saved.', 'sem-seo')
				. '</strong>'
			. '</p>' . "\n"
			. '</div>' . "\n";
	} # save_options()
	
	
	/**
	 * edit_options()
	 *
	 * @return void
	 **/

	function edit_options() {
		$options = sem_seo::get_options();
		
		echo '<div class="wrap">' . "\n"
			. '<form method="post" action="">' . "\n";
		
		wp_nonce_field('sem_seo');
		
		screen_icon();
		
		echo '<h2>'
			. __('SEO Settings', 'sem-seo')
			. '</h2>' . "\n";
		
		echo '<h3>'
			. __('Title &amp; Meta', 'sem-seo')
			. '</h3>' . "\n";
		
		echo '<table class="form-table">' . "\n";
		
		foreach ( sem_seo_admin::get_fields('ext_meta') as $field => $details ) {
			switch ( $field ) {
			case 'description':
				echo '<tr valign="top">'
					. '<th scope="row">'
					. $details['label']
					. '</th>'
					. '<td>'
					. '<textarea name="' . $field . '" cols="58" rows="4" class="widefat">'
					. format_to_edit($options[$field])
					. '</textarea>'
					. $details['desc'] . "\n"
					. '</td>'
					. '</tr>' . "\n";
				break;

			case 'title':
			case 'keywords':
				echo '<tr valign="top">'
					. '<th scope="row">'
					. $details['label']
					. '</th>'
					. '<td>'
					. '<input type="text" name="' . $field . '" size="58" class="widefat"'
						. ' value="' . esc_attr($options[$field]) . '"'
						. ' />'
					. $details['desc'] . "\n"
					. '</td>'
					. '</tr>' . "\n";
				break;
			
			default:
				echo '<tr valign="top">'
					. '<th scope="row">'
					. $details['label']
					. '</th>'
					. '<td>'
					. '<label>'
					. '<input type="checkbox" name="' . $field . '"'
						. ( $options[$field]
							? ' checked="checked"'
							: ''
							)
						. ' />'
					. '&nbsp;'
					. $details['desc']
					. '</label>'
					. '</td>'
					. '</tr>' . "\n";
				break;
			}
		}
		
		echo '</table>' . "\n";
		
		echo '<h3>'
			. __('Archive Pages', 'sem-seo')
			. '</h3>' . "\n";
		
		echo '<p>'
			. __('The general idea here is to prevent archive pages from outperforming your blog posts. Pages with very similar content on a site get clustered (see the SEO crash course further down).', 'sem-seo')
			. '</p>' . "\n";
		
		echo '<p>'
			. __('By sticking to short excerpts or lists of post titles on archive pages, you prevent these pages from competing with your posts, while continuing to benefit from archive pages with high ranking power. In WordPress, archives pages include category, tag, author and date archives.', 'sem-seo')
			. '</p>' . "\n";
		
		echo '<p>'
			. __('On most sites, you\'ll want to stick to the defaults: post excerpts on category pages, and lists of posts everywhere else. An exception might be a site with very few categories, with posts each in a single category, and the opt-in front page enabled. In the latter case, it makes sense to output a normal blog page for categories.', 'sem-seo')
			. '</p>' . "\n";
		
		echo '<table class="form-table">' . "\n";
		
		foreach ( sem_seo_admin::get_fields('archives') as $field => $details ) {
			echo '<tr valign="top">'
				. '<th scope="row">'
				. $details['label']
				. '</th>'
				. '<td>'
				. '<select name="' . $field . '">' . "\n";
			
			foreach ( array(
				'' => __('Display a normal blog page', 'sem-seo'),
				'excerpts' => __('Display a normal blog page, but stick to excerpts', 'sem-seo'),
				'list' => __('Display a list of post titles, grouped by date', 'sem-seo'),
				'raw_list' => __('Display a raw list of post titles, without dates', 'sem-seo'),
				) as $k => $v ) {
				echo '<option value="' . $k . '"'
					. selected((string) $options[$field], $k, false)
					. '>'
					. $v
					. '</option>' . "\n";
			}
			
			echo '</select>' . "\n"
				. '</td>'
				. '</tr>' . "\n";
		}
		
		echo '</table>' . "\n";
		
		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . esc_attr(__('Save Changes', 'sem-seo')) . '"'
				. ' />'
			. '</p>' . "\n";
		
		echo '</form>' . "\n";
		
		sem_seo_admin::crash_course();
		
		echo '</div>' . "\n";
	} # edit_options()
	
	
	/**
	 * entry_editor()
	 *
	 * @param object $post
	 * @return void
	 **/

	function entry_editor($post) {
		echo '<table class="form-table">' . "\n";
		
		foreach ( sem_seo_admin::get_fields('meta') as $field => $details ) {
			switch ( $field ) {
			case 'description':
				echo '<tr valign="top">'
					. '<th scope="row">'
					. $details['label']
					. '</th>'
					. '<td>'
					. '<textarea name="meta[' . $field . ']" cols="58" rows="4" class="widefat" tabindex="5">'
					. format_to_edit(get_post_meta($post->ID, '_' . $field, true))
					. '</textarea>'
					. '</td>'
					. '</tr>' . "\n";
				break;

			case 'title':
			case 'keywords':
				echo '<tr valign="top">'
					. '<th scope="row">'
					. $details['label']
					. '</th>'
					. '<td>'
					. '<input type="text" name="meta[' . $field . ']" size="58" class="widefat" tabindex="5"'
						. ' value="' . esc_attr(get_post_meta($post->ID, '_' . $field, true)) . '"'
						. ' />'
					. '</td>'
					. '</tr>' . "\n";
				break;
			}
		}
		
		echo '</table>' . "\n";
	} # entry_editor()
	
	
	/**
	 * save_entry()
	 *
	 * @param int $post_ID
	 * @return void
	 **/

	function save_entry($post_ID) {
		if ( !isset($_POST['meta']) )
			return;
		
		extract($_POST['meta']);
		
		foreach ( array_keys(sem_seo_admin::get_fields('post_meta')) as $field ) {
			$$field = trim(strip_tags($$field));
			if ( $$field )
				update_post_meta($post_ID, '_' . $field, $$field);
			else
				delete_post_meta($post_ID, '_' . $field);
		}
	} # save_entry()
	
	
	/**
	 * get_fields()
	 *
	 * @param string $context
	 * @return array $fields
	 **/

	function get_fields($context) {
		$fields = array(
			'title' => array(
					'label' => __('Title', 'sem-seo'),
					'desc' => '<p>' . __('The title field lets you override the &lt;title&gt; tag of the blog\'s main page. It defaults to the site\'s tagline (<a href="options-general.php">Settings / General</a>)', 'sem-seo') . '</p>' . "\n",
					),
			'site_name' => array(
					'label' => __('Site Name', 'sem-seo'),
					'desc' => __('Append the name of the site to the title of each web page.', 'sem-seo'),
					),
			'keywords' => array(
					'label' => __('Meta Keywords', 'sem-seo'),
					'desc' => '<p>' . __('The meta keywords field lets you override the &lt;meta name=&quot;keywords&quot;&gt; tag of blog pages. Given the uselessness of this field, it is usually best left untouched. It defaults to the keywords (categories and tags) of every entry on the web page.', 'sem-seo') . '</p>' . "\n",
					),
			'description' => array(
					'label' => __('Meta Description', 'sem-seo'),
					'desc' => '<p>' . __('The meta description field lets you override the &lt;meta name=&quot;description&quot;&gt; tag of blog pages. Given the uselessness of this field, it is usually best left untouched. It defaults to the site\'s tagline (<a href="options-general.php">Settings / General</a>).', 'sem-seo') . '</p>' . "\n",
					),
			'categories' => array(
					'label' => __('Category Archives', 'sem-seo'),
					),
			'tags' => array(
					'label' => __('Tag Archives', 'sem-seo'),
					),
			'authors' => array(
					'label' => __('Author Archives', 'sem-seo'),
					),
			'dates' => array(
					'label' => __('Date Archives', 'sem-seo'),
					),
			);
		
		$_fields = array();
		
		if ( $context == 'meta' ) {
			foreach ( array('title', 'keywords', 'description') as $field )
				$_fields[$field] = $fields[$field];
		} elseif ( $context == 'ext_meta' ) {
			foreach ( array('title', 'site_name', 'keywords', 'description') as $field )
				$_fields[$field] = $fields[$field];
		} elseif ( $context = 'archives' ) {
			foreach ( array('categories', 'tags', 'authors', 'dates') as $field )
				$_fields[$field] = $fields[$field];
		}
		
		return $_fields;
	} # get_fields()
	
	
	/**
	 * crash_course()
	 *
	 * @return void
	 **/

	function crash_course() {
		echo '<h3>'
			. __('SEO Crash Course', 'sem-seo')
			. '</h3>' ."\n";
		
		echo '<p>'
			. __('<strong>Note</strong>: What follows will occasionally contradict with what you\'ll hear in SEO forums, SEO blogs and from SEO celebrities. Keep in mind that most who talk about SEO have learned it by reading the same forums, blogs and celebrities -- and haven\'t the slightest idea of how a semantic indexing algorithm works.', 'sem-seo')
			. '</p>' . "\n";
		
		echo '<ol>' . "\n";
		
		echo '<li>'
			. '<h3>' . __('The Basics', 'sem-seo') . '</h3>' . "\n"
			. '<ul class="ul-square">' . "\n";
		
			echo '<li>'
				. __('At the end of the day, the only thing that really counts is end-user experience. Deliver quality content, use the likes of Silo widgets and Nav Menu widgets to enhance your site\'s usability and navigability, and focus on your marketing and conversion rates.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Your page\'s &lt;title&gt; tag is very important. In addition to being important from a semantic standpoint, it appears as the top line of the entry when your site is listed by the search engines. A descriptive title can make the difference between a click or not in search results and RSS readers.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Meta keyword and description fields are mostly useless. The only reason they\'re included in the Semiologc SEO plugin is because a few users failed to grasp that Google\'s key innovation was to ignore them. Your time will be better spent on marketing than on filling meta fields.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Link anchor text counts, as does their context. Picture Google as a gargantuan tagging engine where tags are the link texts, in the context of their neighboring text. And keep in mind that, nowadays, its algorithms are driven more by the need to eliminate spammy sites than anything.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Links often get discounted, but in the end, inbound links <em>always</em> count -- even if in a negligible manner. It\'s much better to have a link from an authoritative site, however. (Yahoo\'s directory is authoritative, by the way.)', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Your posts\' and pages\' introductions and conclusions also make a difference; don\'t neglect them, as they\'ll also enhance your site\'s readability. Split your content with h2/h3 tags if it\'s excessively long.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('The Semiologic and Semiologic Reloaded themes have heading tags, in sidebars for instance, that SEO forum regulars may (wrongly) find erroneous. This is to semantically split your page into distinct sections, so as to semantically insulate your content from your site\'s cosmetic and navigation elements.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('New and/or updated content counts, and that is one of the reasons blogs fare well in search engines. While a small update can give a boost to your ranking, a huge update can harm it -- in that a page whose content was entirely rewritten gets treated as an entirely new page. Use at least 5 posts per post page. Better yet, stick to WordPress and Semiologic SEO defaults.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Your keywords can be noise words just as much as &quot;in&quot;, &quot;the&quot;, &quot;a&quot;, etc. if you abuse their usage. To perceive a black dot on a white page, it needs to contrast with its surroundings. Much like eye perception (or any signal detection for that matter), meaning comes from derivatives, i.e. difference and contrast, rather than mere presence and amplitude.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Pick your fights. Uncompetitive keywords are easier to conquer, and these small victories will ultimately give you an edge when fighting the more harder battles.', 'sem-seo')
				. '</li>' . "\n";
			
		echo '</ul>' . "\n"
			. '</li>' . "\n";
		
		echo '<li>'
			. '<h3>' . __('On Duplicate Content', 'sem-seo') . '</h3>' . "\n"
			. '<ul class="ul-square">' . "\n";
		
			echo '<li>'
				. __('Duplicate content issues on third party sites are very real. Fight content theft by either putting your key content on static pages (which don\'t show up in feeds), or by serving excerpts in your feeds -- or both. And don\'t plagiarize content from third party sites using RSS aggregators.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Duplicate content &quot;issues&quot; on your own site are a <a href="http://www.semiologic.com/resources/seo/demystifying-duplicate-content/">fallacy</a>. Similar pages get returned as clusters in search results. In other words, they\'re grouped together in search results, and the highest ranking page in the the group gets returned. To ensure that your individual posts and static pages rank high in a given cluster of pages, output titles or excerpts on archive pages.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('<strong>Stand clear of &quot;SEO&quot; plugins that &quot;deal&quot; with duplicate content issues by unindexing pages on your site</strong>. You can be sure that their authors have little idea of how a search engine works. Having archive and section pages with high ranking power will help your posts and child pages rank better and faster. It is thus <strong>always</strong> preferrable.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('In addition to being useless, adding nofollow attributes to internal links is a sure way to ultimately get your site penalized on grounds that you\'re trying to game search engines. Nofollow was introduced to mark outbound links (usually in comments) that are irrelevant to the post\'s or page\'s contents. Use it as such.', 'sem-seo')
				. '</li>' . "\n";
		
		echo '</ul>' . "\n"
			. '</li>' . "\n";
		
		echo '<li>'
			. '<h3>' . __('On Links', 'sem-seo') . '</h3>' . "\n"
			. '<ul class="ul-square">' . "\n";
			
			echo '<li>'
				. __('Don\'t give too much attention to the number of links in your pages\' cosmetic and navigation areas (i.e. header, sidebar, footer). It is <strong>trivial</strong> to algorithmically extract a page\'s contents from its cosmetic and navigation areas. You compare two or three pages on a site; the difference between them will reveal where the real content is located.', 'sem-seo')
				. '</li>' . "\n";
			
			echo '<li>'
				. __('The links that really count are those in your content, surrounded by context. Add links to your posts and pages within the contents of your posts and pages.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('The number of outbound links on your pages can have an impact, because spammers have been abusing it. But unless your page starts to feel like a link directory, you\'ve nothing to worry about.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('The rate at which you gain inbound links can have an impact, because spammers have been abusing it. If it looks like you\'re comment spamming the web or equivalent, they\'ll get discounted. A regular stream of new links is better than a massive, one-off stream of new links.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('On the topic of link exchanges, page a on site A linking to page b on site B and reciprocally is easily detected and discounted. Page a on site A linking to page b on site B, while page c on site B links to page d on site A, is much harder to detect.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Concluding on the last couple of points, better a single link in the content of an authoritative page than a site-wide link in the footer or sidebar of an unauthoritative site.', 'sem-seo')
				. '</li>' . "\n";
			
		echo '</ul>' . "\n"
			. '</li>' . "\n";
		
		echo '<li>'
			. '<h3>' . __('On Pinging And Performance', 'sem-seo') . '</h3>' . "\n"
			. '<ul class="ul-square">' . "\n";
		
			echo '<li>'
				. __('XML sitemaps are useful to the extent that they\'ll get your site indexed <em>faster</em>. The XML sitemaps specs say that the (optional) link attributes are indications to search engines. They won\'t have the slightest impact on how <em>well</em> individual pages on your site will get indexed.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Performance counts. A huge ping list can have a severe performance impact on your site, and harm your rankings by degrading your server\'s response time. Stick to using pingomatic, and perhaps a few specialized ping services that relate to your site or region. Or Feedburner\'s equivalent service. Don\'t install plugins that offer to fix ping service notifications -- they\'re already throttled in WordPress.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Some permalink structures (<a href="options-permalinks.php">Settings / Permalinks</a>) have a negative impact on your site\'s performance. Worst offenders in this arena are /category/postname/ and /postname/. Avoid those two, and their siblings, like the Plague. Structures that start with a date, i.e. the &quot;Day and name&quot; or &quot;Month and name&quot; structures, are just as optimized, they perform well and they\'ve the added benefit of being the best for usability.', 'sem-seo')
				. '</li>' . "\n";
			
		echo '</ul>' . "\n"
			. '</li>' . "\n";
		
		echo '<li>'
			. '<h3>' . __('On End-User Feedback', 'sem-seo') . '</h3>' . "\n"
			. '<ul class="ul-square">' . "\n";
		
			echo '<li>'
				. __('Search engines are increasingly taking end-user feedback into account. Consider how these potential feedback loops can give indications to Google on how worthwhile your site and its content might be: Search Results (click-through rates, ignore rates, speed of clicks), GMail (the same and popularity), Google Bookmarks (the same), Google Analytics (the same and visit duration, visitor loyalty), the Google Bar (the same), Feedburner (the same), AdSense (the same).', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('Until 1995, people would start by asking their contacts when they searched for information. Now, consider how search has evolved in the past years, and where it\'s heading. It\'ll give pre-eminence to your contacts\' bookmarks. Because your contacts\' opinions count more, to you, than the opinions of people you don\'t know. Keep this in mind while you market your site.', 'sem-seo')
				. '</li>' . "\n";
			
		echo '</ul>' . "\n"
			. '</li>' . "\n";
		
		echo '<li>'
			. '<h3>' . __('Concluding Notes', 'sem-seo') . '</h3>' . "\n"
			. '<ul class="ul-square">' . "\n";
		
			echo '<li>'
				. __('Always keep this in mind that there is a conflict of interest in a search engine\'s business model. <strong>Google\'s better interest is to return &quot;relevant enough&quot; results in a mostly random order</strong>, in order to sell more ads to those who depend on a consistent stream of search engine traffic.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('As a rule, view your search engine traffic as a bonus, and focus on alternative sources of traffic, such as mailing lists, affiliate networks, social networks, ebay, classifieds, word-of-mouth (online and offline forums), etc.', 'sem-seo')
				. '</li>' . "\n";
		
			echo '<li>'
				. __('The only thing that really counts at the end of the day is end-user experience. Deliver quality content, use the likes of Silo widgets and Nav Menu widgets to enhance your site\'s usability and navigability, and focus on your marketing and conversion rates.', 'sem-seo')
				. '</li>' . "\n";
			
		echo '</ul>' . "\n"
			. '</li>' . "\n";
		
		echo '</ol>' . "\n";
	} # crash_course()
} # sem_seo_admin
?>