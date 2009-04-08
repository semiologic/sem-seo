<?php

class sem_seo_admin
{
	#
	# init()
	#
	
	function init()
	{
		add_action('admin_menu', array('sem_seo_admin', 'admin_menu'));
		add_action('admin_menu', array('sem_seo_admin', 'meta_boxes'), 30);
	} # init()
	
	
	#
	# admin_menu()
	#
	
	function admin_menu()
	{
		add_options_page(
			__('SEO'),
			__('SEO'),
			'manage_options',
			__FILE__,
			array('sem_seo_admin', 'options_page')
			);
	} # admin_menu()
	
	
	#
	# update_options()
	#
	
	function update_options()
	{
		check_admin_referer('sem_seo_meta');
		
		$options = sem_seo::get_options();
		
		$fields = array_keys(sem_seo_admin::get_fields());
		
		$fields = array_diff($fields, array('enforce_www_preference'));
		
		foreach ( $fields as $field )
		{
			switch ( $field )
			{
			case 'add_site_name':
			case 'archives':
			case 'category_dates':
			case 'category_excerpts':
			case 'tag_dates':
			case 'tag_excerpts':
				$$field = isset($_POST[$field]);
				break;
			default:
				$$field = stripslashes($_POST[$field]);
				$$field = strip_tags($$field);
				$$field = trim($$field);
				break;
			}
		}
		
		$options = compact($fields);
		
		#echo '<pre>';
		#var_dump($_POST, $options);
		#echo '</pre>';
		
		update_option('sem_seo', $options);
	} # update_options()
	
	
	#
	# options_page()
	#
	
	function options_page()
	{
		echo '<div class="wrap">'
			. '<form method="post" action="">'
			. '<input type="hidden" name="update_sem_seo_meta" value="1" />' . "\n";
		
		if ( $_POST['update_sem_seo_meta'] )
		{
			sem_seo_admin::update_options();

			echo '<div class="updated">' . "\n"
				. '<p>'
					. '<strong>'
					. __('Settings saved.')
					. '</strong>'
				. '</p>' . "\n"
				. '</div>' . "\n";
		}
		
		if ( function_exists('wp_nonce_field') ) wp_nonce_field('sem_seo_meta');
		
		$options = sem_seo::get_options();

		echo '<h2>'
			. 'SEO Settings'
			. '</h2>';
		
		$str = <<<EOF
EOF;

		echo $str;
		
		echo '<table class="form-table">';
		
		$fields = sem_seo_admin::get_fields();
		
		foreach ( $fields as $field => $details )
		{
			switch ( $field )
			{
			case 'enforce_www_preference':
				break;
			case 'description':
				echo '<tr valign="top">'
					. '<th scope="row">'
					. $details['label']
					. '</th>'
					. '<td>'
					. '<textarea name="' . $field . '" cols="58" rows="8" style="width: 90%;">'
					. format_to_edit($options[$field])
					. '</textarea>'
					. $details['desc']
					. '</td>'
					. '</tr>';
				break;
			
			case 'add_site_name':
			case 'archives':
			case 'category_dates':
			case 'category_excerpts':
			case 'tag_dates':
			case 'tag_excerpts':
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
					. '</tr>';
				break;
			
			default:
				echo '<tr valign="top">'
					. '<th scope="row">'
					. $details['label']
					. '</th>'
					. '<td>'
					. '<input type="text" name="' . $field . '" size="58" style="width: 90%;"'
					. ' value="' . attribute_escape($options[$field]) . '"'
					. ' />'
					. $details['desc']
					. '</td>'
					. '</tr>';
				break;
			}
		}
		
		echo '</table>';
		
		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . attribute_escape(__('Save Changes')) . '"'
				. ' />'
			. '</p>' . "\n";

		echo '</form>'
			. '</div>';
	} # options_page()
	
	
	#
	# meta_boxes()
	#
	
	function meta_boxes()
	{
		if ( current_user_can('manage_options') )
		{
			add_meta_box('sem_seo_admin', 'SEO', array('sem_seo_admin', 'entry_editor'), 'post');
			add_meta_box('sem_seo_admin', 'SEO', array('sem_seo_admin', 'entry_editor'), 'page');
			add_action('save_post', array('sem_seo_admin', 'save_entry'));
		}
	} # meta_boxes()
	
	
	#
	# entry_editor()
	#
	
	function entry_editor()
	{
		$post_ID = isset($GLOBALS['post_ID']) ? $GLOBALS['post_ID'] : $GLOBALS['temp_ID'];
		
		echo '<p>These fields let you override entry-specific SEO meta fields. They work in exactly the same way as their site-wide counterparts, which you can configure under <a href="' . trailingslashit(site_url()) . 'wp-admin/options-general.php?page=' . plugin_basename(__FILE__) . '" target="_blank">Settings / SEO Meta</a>.</p>';
		
		echo '<table style="width: 100%;">';
		
		$fields = sem_seo_admin::get_fields();
		$value = '';
		
		foreach ( $fields as $field => $details )
		{
			if ( in_array($field, array('add_site_name', 'archives', 'category_dates', 'category_excerpts', 'tag_dates', 'tag_excerpts', 'enforce_www_preference') ) ) continue;
			
			if ( $post_ID > 0 )
			{
				$value = get_post_meta($post_ID, '_' . $field, true);
			}
			
			switch ( $field )
			{
			case 'description':
				echo '<tr valign="top">'
					. '<th scope="row" width="120px;">'
					. $details['label']
					. '</th>'
					. '<td>'
					. '<textarea name="meta[' . $field . ']" cols="58" rows="4" style="width: 90%;" tabindex="5">'
					. format_to_edit($value)
					. '</textarea>'
					. '</td>'
					. '</tr>';
				break;
			
			default:
				echo '<tr valign="top">'
					. '<th scope="row" width="120px;">'
					. $details['label']
					. '</th>'
					. '<td>'
					. '<input type="text" name="meta[' . $field . ']" size="58" style="width: 90%;" tabindex="5"'
					. ' value="' . attribute_escape($value) . '"'
					. ' />'
					. '</td>'
					. '</tr>';
				break;
			}
		}
		
		echo '</table>';
	} # entry_editor()
	

	#
	# save_entry()
	#

	function save_entry($post_ID)
	{
		$post = get_post($post_ID);
		
		if ( $post->post_type == 'revision' ) return;
		
		if ( current_user_can('manage_options') )
		{
			foreach ( array_keys(sem_seo_admin::get_fields()) as $field )
			{
				if ( in_array($field, array('add_site_name', 'archives', 'category_dates', 'category_excerpts', 'tag_dates', 'tag_excerpts', 'enforce_www_preference') ) ) continue;

				delete_post_meta($post_ID, '_' . $field);

				$value = stripslashes($_POST['meta'][$field]);
				$value = strip_tags($value);
				$value = trim($value);

				if ( $value )
				{
					add_post_meta($post_ID, '_' . $field, $value, true);
				}
			}
		}
	} # save_entry()
	
	
	#
	# get_fields()
	#
	
	function get_fields()
	{
		$fields = array(
			'title' => array(
					'label' => 'Title',
					'desc' => <<<EOF
<p>The title field lets you override the &lt;title&gt; tag of the blog's main page.</p>
EOF
					),
			'add_site_name' => array(
					'label' => 'Site Name',
					'desc' => <<<EOF
Append the name of the site to the title of each web page.
EOF
					),
			'archives' => array(
					'label' => 'Archives',
					'desc' => <<<EOF
Display all archives as lists of posts, so as to eliminate potential duplicate content on the site.
EOF
					),
			'category_dates' => array(
					'label' => 'Category Dates',
					'desc' => <<<EOF
In archives as lists of posts, display post dates in category archives.
EOF
					),
			'category_excerpts' => array(
					'label' => 'Category Excerpts',
					'desc' => <<<EOF
In archives as lists of posts, display post excerpts in category archives.
EOF
					),
			'tag_dates' => array(
					'label' => 'Tag Dates',
					'desc' => <<<EOF
In archives as lists of posts, display post dates in tag archives.
EOF
					),
			'tag_excerpts' => array(
					'label' => 'Tag Excerpts',
					'desc' => <<<EOF
In archives as lists of posts, display post excerpts in tag archives. Important: If you're using more or less the same set of tag for each post on your site, you <b>will</b> have duplicate content issues.
EOF
					),
			'keywords' => array(
					'label' => 'Meta Keywords',
					'desc' => <<<EOF
<p>The meta keywords field lets you override the &lt;meta name=&quot;keywords&quot;&gt; tag of blog pages. It is usually best left untouched: It defaults to the keywords (categories and tags) of every post on the web page.</p>
EOF
					),
			'description' => array(
					'label' => 'Meta Description',
					'desc' => <<<EOF
<p>The meta description field lets you override the &lt;meta name=&quot;description&quot;&gt; tag of blog pages. It defaults to the site's tagline (Settings / General).</p>
EOF
					),
			);
		
		return $fields;
	} # get_fields()
} # sem_seo_admin

sem_seo_admin::init();

?>