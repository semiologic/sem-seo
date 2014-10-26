<?php
/*
Plugin Name: Semiologic SEO
Plugin URI: http://www.semiologic.com/software/sem-seo/
Description: The "just works" SEO plugin for WordPress
Version: 2.7
Author: Denis de Bernardy & Mike Koepke
Author URI: http://www.getsemiologic.com
Text Domain: sem-seo
Domain Path: /lang
License: Dual licensed under the MIT and GPLv2 licenses
*/

/*
Terms of use
------------

This software is copyright Denis de Bernardy & Mike Koepke, and is distributed under the terms of the MIT and GPLv2 licenses.
**/


/**
 * sem_seo
 *
 * @package Semiologic SEO
 **/

class sem_seo {
	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @type object
	 */
	protected static $instance = NULL;

	/**
	 * URL to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_url = '';

	/**
	 * Path to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_path = '';

	/**
	 * Access this plugin’s working instance
	 *
	 * @wp-hook plugins_loaded
	 * @return  object of this class
	 */
	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}


	/**
	 * Loads translation file.
	 *
	 * Accessible to other classes to load different language files (admin and
	 * front-end for example).
	 *
	 * @wp-hook init
	 * @param   string $domain
	 * @return  void
	 */
	public function load_language( $domain )
	{
		load_plugin_textdomain(
			$domain,
			FALSE,
			dirname(plugin_basename(__FILE__)) . '/lang'
		);
	}

	/**
	 * Constructor.
	 *
	 *
	 */

	public function __construct() {
		$this->plugin_url    = plugins_url( '/', __FILE__ );
		$this->plugin_path   = plugin_dir_path( __FILE__ );
		$this->load_language( 'sem-seo' );

		add_action( 'plugins_loaded', array ( $this, 'init' ) );
    }


	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		// more stuff: register actions and filters
		add_action('wp', array($this, 'www_pref'), -10);

		if ( !is_admin() ) {
			add_action('wp', array($this, 'paginated_post'), -10);
			add_action('wp', array($this, 'paginated_archive'), -10);

			add_filter('wp_title', array($this, 'wp_title'), 1000, 3);
			add_action('wp_head', array($this, 'wp_head'), 0);

			add_action('wp_head', array($this, 'ob_google_start'), 10000);
			add_action('loop_start', array($this, 'google_start'), -10000);
			add_action('loop_end', array($this, 'google_end'), 10000);

			add_filter('query_string', array($this, 'archive_query_string'), 20);
			add_action('loop_start', array($this, 'archive_start'), -1000);
			remove_action('wp_head', 'rel_canonical');
		}
		else {
			add_action('admin_menu', array($this, 'admin_menu'));
			add_action('admin_menu', array($this, 'meta_boxes'), 30);

			add_filter('user_contactmethods', array($this, 'update_contactmethods'));

			foreach ( array('post.php', 'post-new.php', 'page.php', 'page-new.php', 'settings_page_seo') as $hook )
				add_action("load-$hook", array($this, 'seo_seo_admin'));
		}
	}

	/**
	* seo_seo_admin()
	*
	* @return void
	**/
	function seo_seo_admin() {
		include_once $this->plugin_path . '/sem-seo-admin.php';
	}

    /**
	 * archive_query_string()
	 *
     * @param string
	 * @return string
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

        if ( $wp_the_query->is_feed() || !$wp_the_query->is_archive()
            || ($wp_the_query->is_category() && !in_array($o['categories'], array('list', 'raw_list')))
            || ($wp_the_query->is_tag() && !in_array($o['tags'], array('list', 'raw_list')))
            || ($wp_the_query->is_author() && !in_array($o['authors'], array('list', 'raw_list')))
            || ($wp_the_query->is_date() && !in_array($o['dates'], array('list', 'raw_list')))
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
		add_action('loop_end', array($this, 'archive_end'), 1000);
		
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
			
			add_filter('the_content', array($this, 'archive_excerpts'));
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
     * @return mixed|void $text
     */

	function archive_excerpts($text) {
		global $wp_filter;
		global $sem_seo_filter_backup;
		
		$wp_filter['the_content'] = $sem_seo_filter_backup;
		
		$text = apply_filters('the_excerpt', get_the_excerpt());
		
		unset($wp_filter['the_content']);
		
		add_filter('the_content', array($this, 'archive_excerpts'));
		
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
			global $post, $pages;									
			if ( !is_object($post) ) {
				$post = $wp_the_query->posts[0];
			}
			if ( !$pages ) {			
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
		$site_name = !empty($o['add_site_name']) ? get_option('blogname') : false;
		
		if ( is_singular() || sem_seo::is_posts_page() )
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
                
        $page = (is_singular() ? get_query_var('page') : get_query_var('paged'));
        if ( $page > 1 ) {
            $title = "$title | Page $page";
        }
                
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
		
		if ( is_singular() || sem_seo::is_posts_page() )
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
			} elseif ( is_home() || is_front_page() ) {
                $description = get_option('blogdescription');
            }
			$description = !is_wp_error($description)
				? trim(strip_tags($description))
				: '';
		}

		if ( !empty($keywords) )
				echo '<meta name="keywords" content="' . esc_attr($keywords) . '" />' . "\n";

		if ( !empty($description) ) {
			$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
			if ( $paged > 1 )
				$description = $description . ' - Page ' . $paged;

			echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
		}


		$meta_robots = sem_seo::get_robots();
		if ( $meta_robots ) {
			echo '<meta name="robots" content="' . esc_attr( $meta_robots ) . '" />' . "\n";
		}

        $canonical = sem_seo::get_canonical();
        if ( $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}

        $links = sem_seo::get_adjacent_rel_links();
        if (isset($links['prev']))
            echo '<link rel="prev" href="' . esc_url($links['prev']) . '" />' . "\n";
        if (isset($links['next']))
            echo '<link rel="next" href="' . esc_url($links['next']) . '" />' . "\n";
        // display google authorship
        sem_seo::add_authorship();
                
	} # wp_head()
	

    /**
	 * get_canonical()
	 *
     * @param bool $return_unpaged
	 * @return string canonical url
	 **/    
    function get_canonical($return_unpaged = false) {

        global $wp_the_query;

        $canonical = false;

        if ( is_singular() ) {
            global $post;
            if ( $post->ID != $wp_the_query->get_queried_object_id() )
                setup_postdata($wp_the_query->posts[0]);
            $url = apply_filters('the_permalink', get_permalink($post->ID));
            $page = get_query_var('page');
            if ( $page > 1 && !$return_unpaged) {
                if ( !get_option('permalink_structure') )
                    $url .= '&page=' . $page;
                else
                    $url = trailingslashit($url) . user_trailingslashit($page, 'single_paged');
            }
            $canonical = $url;

        } else {
            if ( is_search() ) {
                $canonical = get_search_link();
            } else if ( is_front_page() ) {
                $canonical = home_url( '/' );
            } else if ( sem_seo::is_posts_page() ) {
                $canonical = get_permalink( get_option( 'page_for_posts' ) );
            } else if ( is_tax() || is_tag() || is_category() ) {
                $term      = get_queried_object();
                $canonical = get_term_link( $term, $term->taxonomy );
            } else if ( function_exists( 'get_post_type_archive_link' ) && is_post_type_archive() ) {
                $canonical = get_post_type_archive_link( get_post_type() );
            } else if ( is_author() ) {
                $canonical = get_author_posts_url( get_query_var( 'author' ), get_query_var( 'author_name' ) );
            } else if ( is_archive() ) {
                if ( is_date() ) {
                    if ( is_day() ) {
                        $canonical = get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
                    } else if ( is_month() ) {
                        $canonical = get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
                    } else if ( is_year() ) {
                        $canonical = get_year_link( get_query_var( 'year' ) );
                    }
                }
            }

            if ( $canonical && $return_unpaged )
         		return $canonical;

            $page = get_query_var( 'paged' );
            if ( $canonical && $page > 1 ) {
                $canonical = sem_seo::add_paged_component( $canonical, $page );
            }
        }

        return $canonical;
    } # get_canonical()


	/**
	* get_robots()
	*
	* @return string
	**/
 function get_robots() {

	 $meta_robots = '';

     if  ( is_search() || is_tag() || is_author() || is_date() || is_404() ) {
	     $meta_robots = "noindex, follow";
     }
/*	 elseif ( is_category() ) {
		 $meta_robots = "noindex, follow";
    }
*/

     return $meta_robots;
 } # get_robots()


    /**
	 * get_adjacent_rel_links()
	 *
	 * @return array links
	 **/
   function get_adjacent_rel_links() {
   		global $wp_query;

        $links = array();
        $url = sem_seo::get_canonical(true);
   		if ( !is_singular() ) {
   			if ( $url ) {
   				$paged = get_query_var( 'paged' );

                if ( $paged == 0 )
                    $paged = 1;

   				if ( $paged > 1 )
                    $links["prev"] = sem_seo::add_paged_component($url, $paged - 1);

   				if ( $paged < $wp_query->max_num_pages )
                    $links["next"] = sem_seo::add_paged_component($url, $paged + 1);
   			}
   		} else {
            $page = get_query_var( 'page' );
            if ($page == 0)
                $page = 1;

            $total_pages = substr_count($wp_query->post->post_content, '<!--nextpage-->') + 1;
            if ( $page > 1 )
                $links["prev"] = sem_seo::add_paged_component($url, $page - 1);

            if ( $page < $total_pages )
                $links["next"] = sem_seo::add_paged_component($url, $page + 1);
        }

       return $links;
   	}

    /**
     * add_paged_arg()
     *
     * @param $canonical
     * @param $page
     * @internal param \canonical $string
     * @return string canonical url
     */

    function add_paged_component($canonical, $page) {
        global $wp_rewrite;
        if ( $page > 1) {
            if ( !$wp_rewrite->using_permalinks() ) {
                $canonical = add_query_arg( 'paged', $page, $canonical );
            } else {
                $canonical = user_trailingslashit( trailingslashit( $canonical ) . trailingslashit( $wp_rewrite->pagination_base ) . $page );
            }
        }
        return $canonical;
    }

	/**
	 * get_keywords()
	 *
	 * @return string $keywords
	 **/

	function get_keywords() {
		global $wp_the_query;
		
		if ( !$wp_the_query->posts )
			return "";
		
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
   	 * get_the_excerpt_rss()
   	 *
   	 * @return string
   	 **/
    function get_the_excerpt_rss() {
    	$output = get_the_excerpt();
    	return apply_filters('the_excerpt_rss', $output);
    }

	/**
	 * ob_start()
	 *
	 * @return void
	 **/

	function ob_google_start() {
		static $done = false;
		
		if ( $done || is_feed() )
			return;
		
		ob_start(array($this, 'ob_google_filter'));
		add_action('wp_footer', array($this, 'ob_google_flush'), 10000);
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

	static function get_options() {
		static $o;
		
		if ( !is_admin() && isset($o) )
			return $o;
		
		$o = get_option('sem_seo');
		                
		if ( $o === false || !isset($o['google_plus_publisher']) )
			$o = sem_seo::init_options();
		
		return $o;
	} # get_options()
	
	
	/**
	 * init_options()
	 *
	 * @return array $options
	 **/

	static function init_options() {
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
	        'google_plus_publisher' => '',
	        'google_plus_author' => '0',
			);

		if ( !$o ) {
			$o  = $defaults;
		}
		else {
			$o = wp_parse_args($o, $defaults);
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
			add_meta_box( 'sem_seo_admin', __('SEO Title &amp; Meta', 'sem-seo'), array('sem_seo_admin', 'entry_editor'), 'post', 'advanced', 'high' );
		
		if ( current_user_can('edit_pages') )
			add_meta_box( 'sem_seo_admin', __('SEO Title &amp; Meta', 'sem-seo'), array('sem_seo_admin', 'entry_editor'), 'page', 'advanced', 'high' );
	} # meta_boxes()
        
   /**
    * update_contactmethods()
    *
    * @param object $contactmethods
    * @return object $contactmethods
    **/
        
    function update_contactmethods( $contactmethods ) {
		// Add Google+
		$contactmethods['googleplus'] = __( 'Google+', 'sem-seo' );
		// Add Twitter
		$contactmethods['twitter'] = __( 'Twitter', 'sem-seo' );
		// Add Facebook
		$contactmethods['facebook'] = __( 'Facebook', 'sem-seo' );
                // Add LinkedIn
		$contactmethods['linkedin'] = __( 'LinkedIn', 'sem-seo' );
                
		return $contactmethods;
	} # update_contactmethods()
        
        
         /**
	 * add_authorship()
	 *
	 * @param void
	 * @return void
	 **/
    function add_authorship() {
        $gplus   = false;
        $options = sem_seo::get_options();

	    if ( is_singular() && !is_front_page() ) {
			global $post;
			$gplus = get_the_author_meta( 'googleplus', $post->post_author );
        }

        if ( is_author() && get_query_var( 'author' ) ) {
            $gplus = get_the_author_meta( 'googleplus', get_query_var( 'author' ) ) ;
 	    }

        if ( is_front_page() ) {
            if ( isset( $options['google_plus_publisher'] ) && !empty( $options['google_plus_publisher'] ) ) {
                echo '<link rel="publisher" href="' . esc_url( $options['google_plus_publisher'] ) . '"/>' . "\n";
            }
        }

        if ( $gplus )
           echo '<link rel="author" href="' . esc_url( $gplus ) . '"/>' . "\n";

    } # add_authorship()

                
         /**
	 * set_author_url()
	 *
	 * @param string
	 * @return string
	 **/
    function set_author_url($url) {
//      if (empty($url)) {

        $author_page_url = get_author_posts_url(get_the_author_meta( 'ID' ));
        if (!empty($author_page_url)) {
            $url = $author_page_url;
        }
//      }

    return $url;
	} # set_author_url()


    /**
     * Determine whether the current page is a static homepage.
     *
     * @return bool
     */
    function is_home_static_page() {
        return ( is_front_page() && 'page' == get_option( 'show_on_front' ) && is_page( get_option( 'page_on_front' ) ) );
    }

    /**
     * Determine whether this is the posts page, regardless of whether it's the frontpage or not.
     *
     * @return bool
     */
    function is_posts_page() {
        return ( is_home() && 'page' == get_option( 'show_on_front' ) );
    }

} # sem_seo

$sem_seo = sem_seo::get_instance();
