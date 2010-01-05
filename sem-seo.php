<?php
/*
Plugin Name: Semiologic SEO
Plugin URI: http://www.semiologic.com/software/sem-seo/
Description: All-in-one SEO plugin for WordPress
Version: 2.0.1
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: sem-seo
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('sem-seo', false, dirname(plugin_basename(__FILE__)) . '/lang');


/**
 * sem_seo
 *
 * @package Semiologic SEO
 **/

class sem_seo {
	/**
	 * archive_query_string()
	 *
	 * @return void
	 **/

	function archive_query_string($query_string) {
		parse_str($query_string, $qv);
		unset($qv['paged'], $qv['debug']);
		
		if ( empty($qv) )
			return $query_string;
		
		foreach ( array(
			'pagename',
			'feed',
			'p',
			'page_id',
			'attachment_id',
			) as $bail ) {
			if ( !empty($qv[$bail]) )
				return $query_string;
		}
		
		global $wp_the_query;
		$o = sem_seo::get_options();
		
		$wp_the_query->parse_query($query_string);
		
		if ( is_feed() || !is_archive()
			|| is_category() && !in_array($o['categories'], array('list', 'raw_list'))
			|| is_tag() && !in_array($o['tags'], array('list', 'raw_list'))
			|| is_author() && !in_array($o['authors'], array('list', 'raw_list'))
			|| is_date() && !in_array($o['dates'], array('list', 'raw_list'))
			)
			return $query_string;
		
		parse_str($query_string, $args);
		
		if ( !isset($args['posts_per_page']) ) {
			if ( is_date() )
				$args['posts_per_page'] = -1;
			else
				$args['posts_per_page'] = 20;
		}
		
		$query_string = http_build_query($args);
		
		return $query_string;
	} # archive_query_string()
	
	
	/**
	 * archive_start()
	 *
	 * @param object &$wp_query
	 * @return void
	 **/

	function archive_start(&$wp_query) {
		global $wp_the_query;
		
		if ( $wp_query !== $wp_the_query || is_feed() || !is_archive() )
			return;
		
		static $done = false;
		
		if ( $done )
			return;
		
		$done = true;
		$o = sem_seo::get_options();
		add_action('loop_end', array('sem_seo', 'archive_end'), 1000);
		
		if ( is_category() && $o['categories'] == 'excerpts'
			|| is_tag() && $o['tags'] == 'excerpts'
			|| is_author() && $o['authors'] == 'excerpts'
			|| is_date() && $o['dates'] == 'excerpts'
			|| !is_category() && !is_tag() && !is_author() && !is_date()
			) {
			global $wp_filter;
			global $sem_seo_filter_backup;
			
			$sem_seo_filter_backup = $wp_filter['the_content'];
			unset($wp_filter['the_content']);
			
			add_filter('the_content', array('sem_seo', 'archive_excerpts'));
		} else {
			ob_start();
		}
	} # archive_start()
	
	
	/**
	 * archive_end()
	 *
	 * @param object &$wp_query
	 * @return void
	 **/

	function archive_end(&$wp_query) {
		global $wp_the_query;
		
		if ( $wp_query !== $wp_the_query )
			return;
		
		static $done = false;
		
		if ( $done )
			return;
		
		$done = true;
		$o = sem_seo::get_options();
		
		if ( is_category() && $o['categories'] == 'excerpts'
			|| is_tag() && $o['tags'] == 'excerpts'
			|| is_author() && $o['authors'] == 'excerpts'
			|| is_date() && $o['dates'] == 'excerpts'
			|| !is_category() && !is_tag() && !is_author() && !is_date()
			) {
			global $wp_filter;
			global $sem_seo_filter_backup;
			
			$wp_filter['the_content'] = $sem_seo_filter_backup;
			
			return;
		} else {
			ob_end_clean();
			sem_seo::archive_titles();
		}
	} # archive_end()
	
	
	/**
	 * archive_excerpts()
	 *
	 * @param $text
	 * @return $text
	 **/

	function archive_excerpts($text) {
		global $wp_filter;
		global $sem_seo_filter_backup;
		
		$wp_filter['the_content'] = $sem_seo_filter_backup;
		
		$text = apply_filters('the_excerpt', get_the_excerpt());
		
		unset($wp_filter['the_content']);
		
		add_filter('the_content', array('sem_seo', 'archive_excerpts'));
		
		return $text;
	} # archive_excerpts()
	
	
	/**
	 * archive_titles()
	 *
	 * @return void
	 **/

	function archive_titles() {
		global $wp_the_query;
		$o = sem_seo::get_options();
		
		$wp_the_query->rewind_posts();
		unset($GLOBALS['day'], $GLOBALS['previousday']);
		
		$show_post_date = ( is_category() && $o['categories'] == 'list'
			|| is_tag() && $o['tags'] == 'list'
			|| is_author() && $o['authors'] == 'list'
			|| is_date() && $o['dates'] == 'list'
			);
		
		$i = 0;
		
		echo '<div class="entry">' . "\n"
			. '<div class="entry_top"><div class="hidden"></div></div>' . "\n"
			. '<div class="post_list">' . "\n";
		
		while ( $wp_the_query->have_posts() ) {
			$wp_the_query->the_post();
			
			$date = false;
			if ( $show_post_date && ( is_single() || !is_singular() ) )
				$date = the_date('', '', '', false);
			
			$title = the_title('', '', false);
			
			$permalink = apply_filters('the_permalink', get_permalink());
			
			$edit_link = get_edit_post_link();
			
			if ( !$title )
				$title = __('Untitled', 'sem-seo');
			
			if ( defined('higlights_cat_id') && in_category(higlights_cat_id) )
				$title = '<em>' . $title . '</em>';
			
			$title = '<a href="' . esc_url($permalink) . '" title="' . esc_attr($title) . '">'
				. $title
				. '</a>';
			
			if ( $edit_link ) {
				$edit_link = '<a class="post-edit-link"'
					. ' href="' . esc_url($edit_link) . '"'
					. ' title="' . esc_attr(__('Edit', 'sem-reloaded')) . '">'
					. __('Edit', 'sem-reloaded')
					. '</a>';
				$edit_link = apply_filters('edit_post_link', $edit_link, get_the_ID());
				
				$title .= '&nbsp;<span class="edit_entry">'
					. $edit_link
					. '</span>' . "\n";
			}
			
			if ( $i && $date )
				echo '</ul>' . "\n";
			
			if ( $date )
				echo '<h3 class="post_list_date">' . $date . '</h3>' . "\n";
			
			if ( !$i || $date )
				echo '<ul>' . "\n";
			
			echo '<li>' . $title . '</li>' . "\n";
			
			$i++;
		}
		
		echo '</ul>' . "\n"
			. '</div>' . "\n"
			. '<div class="entry_bottom"><div class="hidden"></div></div>' . "\n"
			. '</div>' . "\n";
	} # archive_titles()
	
	
	/**
	 * www_pref()
	 *
	 * @return void
	 **/

	function www_pref() {
		$host = strtolower($_SERVER['HTTP_HOST']);
		$site_url = strtolower(get_option('siteurl'));
		
		if ( strpos($host, 'www.') === 0 && strpos($site_url, '://www.') === false
		 	|| strpos($host, 'www.') === false && strpos($site_url, '://www.') !== false
			) {
			$root = get_option('siteurl');
			preg_match("|^([^/]+://[^/]+)|", $root, $root);
			$root = end($root);
			
			wp_redirect($root . $_SERVER['REQUEST_URI'], 301);
			die;
		}
	} # www_pref()
	
	
	/**
	 * paginated_post()
	 *
	 * @return void
	 **/

	function paginated_post() {
		if ( !is_singular() )
			return;
		
		$page = get_query_var('page');
		
		if ( !$page )
			return;
		
		global $wp_the_query;
		$permalink = apply_filters('the_permalink', get_permalink($wp_the_query->get_queried_object_id()));
		if ( $page == 1 ) {
			wp_redirect($permalink, 301);
			die;
		} else {
			global $post;
			global $pages;
			if ( !is_object($post) ) {
				$post = $wp_the_query->posts[0];
				setup_postdata($post);
			}
			if ( count($pages) < $page ) {
				wp_redirect($permalink, 301);
				die;
			}
		}
	} # paginated_post()
	
	
	/**
	 * paginate_archive()
	 *
	 * @return void
	 **/

	function paginated_archive() {
		if ( is_singular() || is_404() || is_search() )
			return;
		
		$paged = get_query_var('paged');
		if ( !$paged )
			return;
		
		global $wp_the_query;
		
		if ( $paged == 1 || $paged > $wp_the_query->max_num_pages ) {
			if ( is_front_page() ) {
				$url = user_trailingslashit(get_option('home'));
				wp_redirect($url, 301);
				die;
			} else {
				$url = ( is_ssl() ? 'https://' : 'http://' )
					. $_SERVER['HTTP_HOST']
					. $_SERVER['REQUEST_URI'];
				
				if ( !get_option('permalink_structure') ) {
					$url = str_replace('&paged=' . $paged, '', $url);
				} else {
					$url = str_replace('/page/' . $paged, '', $url);
				}
				wp_redirect($url, 301);
				die;
			}
		}
	} # paginated_archive()
	
	
	/**
	 * wp_title
	 *
	 * @param string $title
	 * @param string $sep
	 * @param string $seplocation
	 * @return string $title
	 **/

	function wp_title($title, $sep, $seplocation) {
		if ( is_feed() )
			return $title;
		
		global $wp_the_query;
		
		$o = sem_seo::get_options();
		
		$title = trim($title);
		$site_name = $o['add_site_name'] ? get_option('blogname') : false;
		
		if ( is_singular() || $wp_the_query->is_posts_page )
			$post_id = $wp_the_query->get_queried_object_id();
		else
			$post_id = false;
		
		$new_title = false;
		
		switch ( $post_id !== false ) {
		case true:
			$new_title = get_post_meta($post_id, '_title', true);
			if ( $new_title )
				break;
		default:
			if ( is_home() || is_front_page() )
				$new_title = $o['title'];
		}
		
		if ( $new_title )
			$title = $new_title;
		elseif ( !$title )
			$title = get_option('blogdescription');
		
		if ( $site_name )
			$title = "$title | $site_name";
		
		return $title;
	} # wp_title()
	
	
	/**
	 * wp_head()
	 *
	 * @return void
	 **/

	function wp_head() {
		if ( is_search() || is_404() )
			return;
		
		global $wp_the_query;
		
		$o = sem_seo::get_options();
		
		if ( is_singular() || $wp_the_query->is_posts_page )
			$post_id = $wp_the_query->get_queried_object_id();
		else
			$post_id = false;
		
		foreach ( array('keywords', 'description') as $var ) {
			$$var = false;
			switch ( $post_id !== false ) {
			case true:
				$$var = get_post_meta($post_id, '_' . $var, true);
				if ( $$var )
					break;
			default:
				if ( is_home() || is_front_page() )
					$$var = $o[$var];
			}
		}
		
		if ( !$keywords )
			$keywords = sem_seo::get_keywords();
		
		if ( !$description ) {
			if ( is_category() ) {
				$description = get_term_field('description', get_query_var('cat'), 'category');
			} elseif ( is_tag() ) {
				$description = get_term_field('description', get_query_var('tag_id'), 'post_tag');
			}
			$description = trim(strip_tags($description));
		}
		
		foreach ( array('keywords', 'description') as $var ) {
			if ( $$var )
				echo '<meta name="' . $var . '" content="' . esc_attr($$var) . '" />' . "\n";
		}
		
		if ( is_singular() ) {
			global $post;
			if ( $post->ID != $wp_the_query->get_queried_object_id() )
				setup_postdata($wp_the_query->posts[0]);
			$url = apply_filters('the_permalink', get_permalink($post->ID));
			if ( $page = get_query_var('page') ) {
				if ( !get_option('permalink_structure') )
					$url .= '&page=' . $page;
				else
					$url = trailingslashit($url) . user_trailingslashit($page, 'single_paged');
			}
			echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
		}
	} # wp_head()
	
	
	/**
	 * get_keywords()
	 *
	 * @return string $keywords
	 **/

	function get_keywords() {
		global $wp_the_query;
		
		if ( !$wp_the_query->posts )
			return;
		
		$keywords = array();
		$exclude = array();
		
		if ( defined('main_cat_id') && main_cat_id )
			$exclude[] = main_cat_id;
		
		if ( defined('highlights_cat_id') && highlights_cat_id )
			$exclude[] = highlights_cat_id;
		
		foreach ( $wp_the_query->posts as $post ) {
			foreach ( (array) get_the_category($post->ID) as $term ) {
				if ( in_array($term->term_id, $exclude) )
					continue;
				if ( isset($term->name) )
					$keywords[] = $term->name;
			}
			
			foreach ( (array) get_the_tags($post->ID) as $term ) {
				if ( isset($term->name) )
					$keywords[] = $term->name;
			}
		}
		
		$keywords = array_map('strtolower', $keywords);
		$keywords = array_unique($keywords);
		
		sort($keywords);
		
		return implode(', ', $keywords);
	} # get_keywords()
	
	
	/**
	 * ob_start()
	 *
	 * @return void
	 **/

	function ob_google_start() {
		static $done = false;
		
		if ( $done || is_feed() )
			return;
		
		ob_start(array('sem_seo', 'ob_google_filter'));
		add_action('wp_footer', array('sem_seo', 'ob_google_flush'), 10000);
		$done = true;
	} # ob_google_start()
	
	
	/**
	 * ob_google_filter
	 *
	 * @param string $text
	 * @return string $text
	 **/

	function ob_google_filter($text) {
		$text = preg_replace("/
			^.*?
			<\s*body(?:\s.*?)>
			/isx", "$0\n" . '<!-- google_ad_section_start(weight=ignore) -->', $text);
		
		return $text;
	} # ob_google_filter()
	
	
	/**
	 * ob_google_flush()
	 *
	 * @return void
	 **/

	function ob_google_flush() {
		static $done = false;
		
		if ( $done || is_feed() )
			return;
		
		ob_end_flush();
		echo '<!-- google_ad_section_end -->' . "\n";
		$done = true;
	} # ob_google_flush()
	
	
	/**
	 * google_start()
	 *
	 * @param object &$wp_query
	 * @return void
	 **/

	function google_start(&$wp_query) {
		global $wp_the_query;
		
		if ( $wp_query !== $wp_the_query || is_feed() )
			return;
		
		echo "\n"
			. '<!-- google_ad_section_end -->' . "\n"
			. '<!-- google_ad_section_start -->' . "\n";
	} # google_start()
	
	
	/**
	 * google_end()
	 *
	 * @param object &$wp_query
	 * @return void
	 **/

	function google_end(&$wp_query) {
		global $wp_the_query;
		
		if ( $wp_query !== $wp_the_query || is_feed() )
			return;
		
		echo "\n"
			. '<!-- google_ad_section_end -->' . "\n"
			. '<!-- google_ad_section_start(weight=ignore) -->' . "\n";
	} # google_end()
	
	
	/**
	 * get_options()
	 *
	 * @return array $options
	 **/

	function get_options() {
		static $o;
		
		if ( !is_admin() && isset($o) )
			return $o;
		
		$o = get_option('sem_seo');
		
		if ( $o === false || isset($o['archives']) )
			$o = sem_seo::init_options();
		
		return $o;
	} # get_options()
	
	
	/**
	 * init_options()
	 *
	 * @return array $options
	 **/

	function init_options() {
		$o = get_option('sem_seo');
		
		$defaults = array(
			'title' => '',
			'keywords' => '',
			'description' => '',
			'site_name' => false,
			'categories' => 'excerpts',
			'tags' => 'list',
			'authors' => 'list',
			'dates' => 'list',
			);
		
		if ( !$o ) {
			$o  = $defaults;
		} elseif ( isset($o['archives']) ) {
			$o = wp_parse_args($o, $defaults);
			
			if ( $o['archives'] ) {
				$o['dates'] = 'list';
				foreach ( array('category' => 'categories', 'tag' => 'tags') as $type => $types ) {
					if ( $o[$type . '_excerpts'] )
						$o[$types] = 'excerpts';
					elseif ( $o[$type . '_dates'] )
						$o[$types] = 'list';
					elseif ( !$o[$type . '_dates'] )
						$o[$types] = 'raw_list';
				}
				$o['authors'] = 'list';
			} else {
				$o['dates'] = false;
				$o['categories'] = false;
				$o['tags'] = false;
				$o['authors'] = false;
			}
			
			extract($o, EXTR_SKIP);
			$o = compact(array_keys($defaults));
		} else {
			$o = $defaults;
		}
		
		update_option('sem_seo', $o);
		
		return $o;
	} # init_options()
	
	
	/**
	 * admin_menu()
	 *
	 * @return void
	 **/

	function admin_menu() {
		add_options_page(
			__('SEO', 'sem-seo'),
			__('SEO', 'sem-seo'),
			'manage_options',
			'seo',
			array('sem_seo_admin', 'edit_options')
			);
	} # admin_menu()
	
	
	/**
	 * meta_boxes()
	 *
	 * @return void
	 **/

	function meta_boxes() {
		if ( current_user_can('edit_posts') )
			add_meta_box('sem_seo_admin', __('Title &amp; Meta', 'sem-seo'), array('sem_seo_admin', 'entry_editor'), 'post');
		
		if ( current_user_can('edit_pages') )
			add_meta_box('sem_seo_admin', __('Title &amp; Meta', 'sem-seo'), array('sem_seo_admin', 'entry_editor'), 'page');
	} # meta_boxes()
} # sem_seo


function seo_seo_admin() {
	include dirname(__FILE__) . '/sem-seo-admin.php';
}

foreach ( array('post.php', 'post-new.php', 'page.php', 'page-new.php', 'settings_page_seo') as $hook )
	add_action("load-$hook", 'seo_seo_admin');


add_action('wp', array('sem_seo', 'www_pref'), -10);
if ( !is_admin() ) {
	add_action('wp', array('sem_seo', 'paginated_post'), -10);
	add_action('wp', array('sem_seo', 'paginated_archive'), -10);
}

add_filter('wp_title', array('sem_seo', 'wp_title'), 1000, 3);
add_action('wp_head', array('sem_seo', 'wp_head'), 0);

add_action('wp_head', array('sem_seo', 'ob_google_start'), 10000);
add_action('loop_start', array('sem_seo', 'google_start'), -10000);
add_action('loop_end', array('sem_seo', 'google_end'), 10000);

add_filter('query_string', array('sem_seo', 'archive_query_string'), 20);
add_action('loop_start', array('sem_seo', 'archive_start'), -1000);

add_action('admin_menu', array('sem_seo', 'admin_menu'));
add_action('admin_menu', array('sem_seo', 'meta_boxes'), 30);

remove_action('wp_head', 'rel_canonical'); 
?>