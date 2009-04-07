<?php
/*
Plugin Name: Semiologic SEO
Plugin URI: http://www.semiologic.com/software/sem-seo/
Description: All in one SEO plugin for WordPress
Version: 1.4.2 alpha
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


class sem_seo
{
	#
	# init()
	#
	
	function init()
	{
		$options = get_option('sem_seo');
		
		# kill generator tag
		remove_action('wp_head', 'wp_generator');
		
		# redirect static front page
		add_action('template_redirect', array('sem_seo', 'redirect'), 1000000);
		
		# page meta
		add_action('init', array('sem_seo', 'enforce_www'));
		
		# page title
		add_filter('wp_title', array('sem_seo', 'title'), 20, 2);
		
		# page meta
		add_action('wp_head', array('sem_seo', 'meta'));
		
		# google ad sections
		add_action('wp_head', array('sem_seo', 'google_wrap'), 0);
		add_action('loop_start', array('sem_seo', 'google_begin'), -1000);
		
		# process archives lists
		global $sem_seo_do_archives;
		global $sem_seo_doing_archives;
		
		$sem_seo_do_archives = $options['archives'];
		$sem_seo_doing_archives = false;

		add_action('loop_start', array('sem_seo', 'archives_begin'), 1000);
		add_filter('query_string', array('sem_seo', 'archives_query_string'), 0);
		
		# singular slug
		add_filter('wp_insert_post_data', array('sem_seo', 'post_slug'), 10, 2);
	} # init()
	
	
	#
	# post_slug()
	#
	
	function post_slug($data, $post_arr)
	{
		if ( !in_array($data['post_status'], array('publish', 'future'))
			|| !in_array($data['post_type'], array('post', 'page'))
			|| !preg_match("/-\d+$/", $data['post_name'])
			)
		{
			return $data;
		}
		
		$wp_post_name = $data['post_name'];
		$post_name = sanitize_title($data['post_title']);
		
		if ( !preg_match("/^$post_name-\d+$/", $wp_post_name) )
		{
			# ignore: it was set this way by the user
			return $data;
		}
		
		global $wpdb;
		
		if ( $data['post_type'] == 'page' )
		{
			$sql = "
				SELECT	ID
				FROM	$wpdb->posts
				WHERE	post_type = 'page'
				AND		post_status IN ('publish', 'future')
				AND		post_parent = " . intval($data['post_parent']) . "
				AND		post_name = '" . $wpdb->escape($post_name) . "'
				AND		ID <> " . intval($post_arr['ID']) . "
				LIMIT 1
				";
		}
		else
		{
			$sql = "
				SELECT	ID
				FROM	$wpdb->posts
				WHERE	post_type = 'post'
				AND		post_status IN ('publish', 'future')
				AND		CAST(post_date AS DATE) = CAST('" . $wpdb->escape($data['post_date']) . "' AS DATE)
				AND		post_name = '" . $wpdb->escape($post_name) . "'
				AND		ID <> " . intval($post_arr['ID']) . "
				LIMIT 1
				";
		}
		
		$change = !$wpdb->get_var($sql);
		
		if ( $change )
		{
			$data['post_name'] = $post_name;
		}
		
		return $data;
	} # post_slug()
	
	
	#
	# redirect()
	#
	
	function redirect()
	{
		if ( is_front_page() && !is_paged() && !is_robots() )
		{
			$home_url = user_trailingslashit(get_option('home'));
			$home_path = parse_url($home_url);
			$home_path = $home_path['path'];
			$request_path = parse_url($_SERVER['REQUEST_URI']);
			$request_path = $request_path['path'];
			
			if ( rtrim($request_path, '/') != rtrim($home_path, '/') )
			{
				wp_redirect($home_url, 301);
				die;
			}
		}
	} # redirect()
	
	
	#
	# title()
	#   Lightbox stuff&#8230;
	
	function title($title, $sep)
	{
		if ( is_feed() ) return $title;
		
		global $wp_query;
		
		$title = trim($title);

		if (!empty($sep))
		{
			if ( strpos($title, $sep) === 0 )
			{
				$title = trim(substr($title, strlen($sep), strlen($title)));
			}
		}
		
		#dump('</title>', $title, $sep);
		
		$options = sem_seo::get_options();
		$site_name = get_option('blogname');
		$add_site_name = $options['add_site_name'];
		
		if ( is_home() && !is_page() && !$wp_query->is_posts_page )
		{
			if ( $new_title = $options['title'] )
			{
				$title = $new_title;
			}
		}
		elseif ( is_front_page() || $wp_query->is_posts_page )
		{
			$post_id = $wp_query->get_queried_object_id();

			if ( ( $new_title = get_post_meta($post_id, '_title', true) )
				|| ( $new_title = $options['title'] )
				)
			{
				$title = $new_title;
			}
		}
		elseif ( is_singular() )
		{
			$post_id = $wp_query->get_queried_object_id();
			
			if ( ( $new_title = get_post_meta($post_id, '_title', true) ) )
			{
				$title = $new_title;
			}
		}
		elseif ( is_search() )
		{
			$title = __('Search:') . ' ' . implode(' ', $wp_query->query_vars['search_terms']);
		}
		
		if ( !$title )
		{
			$title = get_option('blogdescription');
		}
		
		if ( $add_site_name )
		{
			$title .= ' | ' . $site_name;
		}
		
		return $title;
	} # title()
	
	
	#
	# meta()
	#
	
	function meta()
	{
		global $wp_query;

		$options = sem_seo::get_options();
		
		$keywords = '';
		$description = '';
		
		if ( is_home() && !is_page() && !$wp_query->is_posts_page )
		{
			if ( !( $keywords = $options['keywords'] ) )
			{
				$keywords = implode(', ', sem_seo::get_keywords());
			}
			
			if ( !( $description = $options['description'] ) )
			{
				$description = trim(strip_tags(get_option('blogdescription')));
			}
		}
		elseif ( is_front_page() || $wp_query->is_posts_page )
		{
			$post_id = $wp_query->get_queried_object_id();

			if ( !( ( $keywords = get_post_meta($post_id, '_keywords', true) )
					|| ( $keywords = $options['keywords'] )
					)
				)
			{
				$keywords = implode(', ', sem_seo::get_keywords());
			}
			
			if ( !( ( $description = get_post_meta($post_id, '_description', true) )
					|| ( $description = $options['description'] )
					)
				)
			{
				$description = trim(strip_tags(get_option('blogdescription')));
			}
		}
		elseif ( is_singular() )
		{
			$post_id = $wp_query->get_queried_object_id();
			
			if ( !( $keywords = get_post_meta($post_id, '_keywords', true) ) )
			{
				$keywords = implode(', ', sem_seo::get_keywords());
			}
			
			if ( ( $description = get_post_meta($post_id, '_description', true) )
				|| ( $description = $options['description'] )
				);
		}
		else
		{
			if ( !( is_home() && ( $keywords = $options['keywords'] ) ) )
			{
				$keywords = implode(', ', sem_seo::get_keywords());
			}
			
			if ( !( is_home() && ( $description = $options['description'] ) )
			 	&& !( is_category()
						&& ( $description = trim(strip_tags(
								get_term_field('description', $GLOBALS['cat'], 'category')
								))
						)
					)
				)
			{
				$description = trim(strip_tags(get_option('blogdescription')));
			}
		}
		
		if ( $keywords )
		{
			echo '<meta name="keywords" content="' . htmlspecialchars($keywords) . '" />' . "\n";
		}
		
		if ( $description )
		{
			echo '<meta name="description" content="' . htmlspecialchars($description) . '" />' . "\n";
		}
	} # meta()
	
	
	#
	# get_keywords()
	#
	
	function get_keywords()
	{
		static $keywords;
		
		if ( !isset($keywords) )
		{
			global $wp_query;
			$keywords = array();
			$exclude = array();
			
			if ( defined('main_cat_id') && main_cat_id )
			{
				$exclude[] = main_cat_id;
			}
			
			if ( defined('highlights_cat_id') && highlights_cat_id )
			{
				$exclude[] = highlights_cat_id;
			}
			
			if ( !$wp_query->posts ) return $keywords;
			
			foreach ( $wp_query->posts as $post )
			{
				if ( $cats = get_the_category($post->ID) )
				{
					foreach ( $cats as $cat )
					{
						if ( !in_array($cat->term_id, $exclude) )
						{
							$keywords[] = $cat->name;
						}
					}
				}

				if ( $tags = get_the_tags($post->ID) )
				{
					foreach ( $tags as $tag )
					{
						$keywords[] = $tag->name;
					}
				}
			}
			
			$keywords = array_map('strtolower', $keywords);
			$keywords = array_unique($keywords);

			sort($keywords);
		}
		
		return $keywords;
	} # get_keywords()
	
	
	#
	# google_wrap()
	#
	
	function google_wrap()
	{
		if ( !is_feed() && !is_admin()  )
		{
			$GLOBALS['did_sem_seo'] = false;
			ob_start(array('sem_seo', 'google_wrap_ob'));
			add_action('wp_footer', array('sem_seo', 'ob_flush'), 1000000000);
		}
	} # google_wrap()
	
	
	#
	# google_wrap_ob()
	#
	
	function google_wrap_ob($buffer)
	{
		$buffer = preg_replace("/
			<\s*body(?:\s.*?)?\s*>
			/isx",
				"$0\n" . '<!-- google_ad_section_start(weight=ignore) -->',
				$buffer);
		
		$GLOBALS['did_sem_seo'] = true;
		
		return $buffer;
	} # google_wrap_ob()
	
	
	#
	# ob_flush()
	#
	
	function ob_flush()
	{
		echo '<!-- google_ad_section_end -->' . "\n";
		
		$i = 0;
		
		while ( !$GLOBALS['did_sem_seo'] && $i++ < 100 )
		{
			@ob_end_flush();
		}
	} # ob_flush()
	
	
	#
	# google_begin()
	#
	
	function google_begin()
	{
		if ( !is_feed() && !is_admin()  )
		{
			echo "\n"
				. '<!-- google_ad_section_end -->' . "\n"
				. '<!-- google_ad_section_start -->' . "\n";
			
			add_action('loop_end', array('sem_seo', 'google_end'), 1000);
		}
	} # google_begin()
	
	
	#
	# google_end()
	#
	
	function google_end()
	{
		if ( !is_feed() && !is_admin() )
		{
			echo "\n"
				. '<!-- google_ad_section_end -->' . "\n"
				. '<!-- google_ad_section_start(weight=ignore) -->' . "\n";
		}
	} # google_end()
	
	
	#
	# archives_begin()
	#
	
	function archives_begin()
	{
		global $sem_seo_do_archives;
		global $sem_seo_doing_archives;

		if ( !is_feed() && !is_admin() && !is_home() && ( is_archive() || is_search() )
			&& $sem_seo_do_archives )
		{
			global $wp_query;

			ob_start();
			add_action('loop_end', array('sem_seo', 'archives_end'), -1000);
		}
	} # archives_begin()
	
	
	#
	# archives_end()
	#
	
	function archives_end()
	{
		global $sem_seo_do_archives;
		global $sem_seo_doing_archives;

		if ( !is_feed() && !is_admin() && !is_home() && ( is_archive() || is_search() )
			&& $sem_seo_do_archives && !$sem_seo_doing_archives )
		{
			ob_end_clean();
			
			$sem_seo_do_archives = false;
			$sem_seo_doing_archives = true;

			sem_seo::archives();

			$sem_seo_do_archives = true;
			$sem_seo_doing_archives = false;
		}
	} # archives_end()
	
	
	#
	# archives_query_string()
	#
	
	function archives_query_string($query_string)
	{
		global $sem_seo_do_archives;
		
		if ( $sem_seo_do_archives )
		{
			global $wp_query;
			
			$wp_query->parse_query($query_string);

			if ( !is_feed() && is_archive() )
			{
				parse_str($query_string, $args);

				if ( !isset($args['posts_per_page']) )
				{
					if ( is_date() )
					{
						$args['posts_per_page'] = -1;
					}
					else
					{
						$args['posts_per_page'] = 20;
					}
				}

				$query_string = array();

				foreach ($args as $k => $v)
				{
					$query_string[] = $k . '=' . $v;
				}

				$query_string = implode('&', $query_string);
				#var_dump($query_string); die;
			}
		}
		
		return $query_string;
	} # archives_query_string()
	
	
	#
	# archives()
	#
	
	function archives()
	{
		global $wp_query;
		$wp_query->rewind_posts();
		unset($GLOBALS['previousday']);
		
		if ( have_posts() )
		{
			$i = 0;
			$options = sem_seo::get_options();
			
			$archives_date = is_date()
				|| $options['category_dates'] && is_category()
				|| $options['tag_dates'] && is_tag();
			
			$archives_excerpts = $options['category_excerpts'] && is_category()
				|| $options['tag_excerpts'] && is_tag()
				|| is_search();
			
			global $shortcode_tags;
			
			$shortcode_tags_backup = $shortcode_tags;
			$shortcode_tags = array(md5(time()) => array('sem_seo', 'fake_shortcode'));
			
			echo '<div class="post_list">' . "\n";
			
			if ( !defined('highlights_cat_id') )
			{
				global $wpdb;
				
				$highlights_cat_id = $wpdb->get_var("
					SELECT
						term_id
					FROM
						$wpdb->terms
					WHERE
						slug = 'highlights'
					");

				define('highlights_cat_id', $highlights_cat_id ? intval($highlights_cat_id) : false);
			}
			
			while ( have_posts() )
			{
				the_post();

				if ( $archives_date )
				{
					$the_date = the_date('', '', '', false);

					if ( $the_date )
					{
						if ( $i )
						{
							echo '</ul>' . "\n";
						}

						echo '<h3>' . $the_date . '</h3>' . "\n";

						echo '<ul>' . "\n";
					}
				}
				elseif ( !$i )
				{
					echo '<ul>' . "\n";
				}

				$i++;

				echo '<li>'
					. '<a href="';

				the_permalink();

				echo '">';

				if ( highlights_cat_id && in_category(highlights_cat_id) )
				{
					echo '<em>';
					the_title();
					echo '</em>';
				}
				else
				{
					the_title();
				}

				echo '</a>';

				edit_post_link(__('Edit'), ' <span class="admin_link edit_entry">&bull;&nbsp;', '</span>');
				
				if ( $archives_excerpts )
				{
					the_excerpt();
				}

				echo '</li>' . "\n";
			}	

			echo '</ul>' . "\n";

			echo '</div>' . "\n";
			
			$shortcode_tags = $shortcode_tags_backup;
		}
	} # archives()
	
	
	#
	# fake_shortcode()
	#
	
	function fake_shortcode($att = null, $content = '')
	{
		return $content;
	} # fake_shortcode()
	
	
	#
	# get_options()
	#
	
	function get_options()
	{
		if ( ( $o = get_option('sem_seo') ) === false )
		{
			$o = array(
				'title' => '',
				'add_site_name' => false,
				'archives' => true,
				'category_dates' => true,
				'category_excerpts' => true,
				'tag_dates' => true,
				'tag_excerpts' => false,
				'keywords' => '',
				'description' => ''
				);
			
			update_option('sem_seo', $o);
		}
		
		return $o;
	} # get_options()
	

	#
	# enforce_www()
	#

	function enforce_www()
	{
		if ( strpos($_SERVER['HTTP_HOST'], 'www.') === 0
			&& strpos(get_option('siteurl'), '://www.') === false
		 	|| strpos($_SERVER['HTTP_HOST'], 'www.') === false
			&& strpos(get_option('siteurl'), '://www.') !== false
			)
		{
			$root = get_option('home');
			
			preg_match("|(.+://[^/]+)|", $root, $root);
			
			$root = end($root);
			
			wp_redirect($root . $_SERVER['REQUEST_URI'], 301);
			die;
		}
	} # enforce_www()
} # sem_seo

sem_seo::init();

if ( is_admin() )
{
	include dirname(__FILE__) . '/sem-seo-admin.php';
}
?>