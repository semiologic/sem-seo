<?php
/**
 * sem_seo_admin
 *
 * @package Semiologic SEO
 **/

class sem_seo_admin {
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
	 * Constructor.
	 *
	 *
	 */

	public function __construct() {
		$this->plugin_url    = plugins_url( '/', __FILE__ );
		$this->plugin_path   = plugin_dir_path( __FILE__ );

		$this->init();
    }

	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		$this->plugin_url    = plugins_url( '/', __FILE__ );
		$this->plugin_path   = plugin_dir_path( __FILE__ );

		// more stuff: register actions and filters
		add_action('settings_page_seo', array($this, 'save_options'), 0);
        add_action('save_post', array($this, 'save_entry'));
	}

    /**
	 * save_options()
	 *
	 * @return void
	 **/

	function save_options() {
		if ( !$_POST || !current_user_can('manage_options') )
			return;
		
		check_admin_referer('sem_seo');
		
		$meta_fields = array_keys(sem_seo_admin::get_fields('ext_meta'));

		foreach ( $meta_fields as $field ) {
			switch ( $field ) {
			case 'title':
			case 'keywords':
			case 'description':
				$data = sanitize_text_field( $_POST[$field] );
				$$field = $data;
				break;
			case 'google_plus_author':
			case 'google_plus_publisher':
                $$field = esc_url_raw( $_POST[$field] );
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
		
		echo '<div class="updated">' . "\n"
			. '<p>'
				. '<strong>'
				. __('Settings saved.', 'sem-seo')
				. '</strong>'
			. '</p>' . "\n"
			. '</div>' . "\n";
                
        do_action('update_option_sem_seo');
	} # save_options()
	
	
	/**
	 * edit_options()
	 *
	 * @return void
	 **/

	static function edit_options() {
		$options = sem_seo::get_options();
		
		echo '<div class="wrap">' . "\n"
			. '<form method="post" action="">' . "\n";
		
		wp_nonce_field('sem_seo');

		echo '<h2>'
			. __('SEO Settings', 'sem-seo')
			. '</h2>' . "\n";
		
		echo '<h3>'
			. __('Site Title &amp; Meta', 'sem-seo')
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
					. esc_html($options[$field])
					. '</textarea>'
					. $details['desc'] . "\n"
					. '</td>'
					. '</tr>' . "\n";
				break;

			case 'title':
			case 'keywords':                         
            case 'google_plus_publisher':
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
						. ( !empty($options[$field])
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
		
		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . esc_attr(__('Save Changes', 'sem-seo')) . '"'
				. ' />'
			. '</p>' . "\n";
		
		echo '<h3>'
			. __('Archive Pages', 'sem-seo')
			. '</h3>' . "\n";
		
		echo '<p>'
			. __('The general idea here is to prevent archive pages from outperforming your blog posts. Pages with very similar content on a site get clustered.', 'sem-seo')
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


		/* TODO:  add robots control  */

		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . esc_attr(__('Save Changes', 'sem-seo')) . '"'
				. ' />'
			. '</p>' . "\n";
		
		echo '</form>' . "\n";

		echo '</div>' . "\n";
	} # edit_options()
	
	
	/**
	 * entry_editor()
	 *
	 * @param object $post
	 * @return void
	 **/

	static function entry_editor($post) {
		echo '<table class="form-table">' . "\n";
		
		foreach ( sem_seo_admin::get_fields('meta') as $field => $details ) {
			switch ( $field ) {
			case 'description':
				echo '<tr valign="top">'
					. '<th scope="row">'
					. $details['label']
					. '</th>'
					. '<td>'
					. '<textarea name="sem_seo[' . $field . ']" cols="58" rows="4" class="widefat" tabindex="5">'
					. esc_html(get_post_meta($post->ID, '_' . $field, true))
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
					. '<input type="text" name="sem_seo[' . $field . ']" size="58" class="widefat" tabindex="5"'
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
     * @param $post_id
     * @internal param int $post_ID
     * @return void
     */

	function save_entry($post_id) {
		if ( !isset($_POST['sem_seo']) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id) )
			return;
		
		extract($_POST['sem_seo']);
		
		foreach ( array_keys(sem_seo_admin::get_fields('meta')) as $field ) {
			$$field = sanitize_text_field( $$field );
			if ( $$field )
				update_post_meta($post_id, '_' . $field, $$field);
			else
				delete_post_meta($post_id, '_' . $field);
		}
	} # save_entry()
	
	
	/**
	 * get_fields()
	 *
	 * @param string $context
	 * @return array $fields
	 **/

	static function get_fields($context) {
		$fields = array(
			'title' => array(
					'label' => __('Page Title', 'sem-seo'),
					'desc' => '<p>' . __('The title field lets you override the &lt;title&gt; tag of the site\'s home page. It defaults to the site\'s tagline (<a href="options-general.php">Settings / General</a>)', 'sem-seo') . '</p>' . "\n",
					),
			'add_site_name' => array(
					'label' => __('Site Name', 'sem-seo'),
					'desc' => __('Append the name of the site to the title of each web page.', 'sem-seo'),
					),
			'keywords' => array(
					'label' => __('Page Meta Keywords', 'sem-seo'),
					'desc' => '<p>' . __('The meta keywords field lets you override the &lt;meta name=&quot;keywords&quot;&gt; tag of the site\'s home page. Given the uselessness of this field, it is usually best left untouched. It defaults to the keywords (categories and tags) of every entry on the web page.', 'sem-seo') . '</p>' . "\n",
					),
			'description' => array(
					'label' => __('Page Meta Description', 'sem-seo'),
					'desc' => '<p>' . __('The meta description field lets you override the &lt;meta name=&quot;description&quot;&gt; tag of the site\'s home page. Given the uselessness of this field, it is usually best left untouched. It defaults to the site\'s tagline (<a href="options-general.php">Settings / General</a>).', 'sem-seo') . '</p>' . "\n",
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
            'google_plus_publisher' => array(
                    'label' => __('Google Publisher Page', 'sem-seo'),
                    'desc' => '<p>' . __('If you have a Google+ page for your business, add that URL here and link it on your Google+ page\'s about page.', 'sem-seo') . '</p>' . "\n",
                    ),
			);
             
                
		$_fields = array();
		
		if ( $context == 'meta' ) {
			foreach ( array('title', 'keywords', 'description') as $field )
				$_fields[$field] = $fields[$field];
		} elseif ( $context == 'ext_meta' ) {
			foreach ( array('title', 'add_site_name', 'keywords', 'description', 'google_plus_publisher') as $field ) {
				$_fields[$field] = $fields[$field];
				if ( isset( $fields[$field]['label'] ) )
					$_fields[$field]['label'] = __('Home ', 'sem-seo') . $_fields[$field]['label'];
			}
		} elseif ( $context == 'archives' ) {
			foreach ( array('categories', 'tags', 'authors', 'dates') as $field )
				$_fields[$field] = $fields[$field];
		}
		
		return $_fields;
	} # get_fields()
} # sem_seo_admin

$sem_seo_admin = sem_seo_admin::get_instance();
