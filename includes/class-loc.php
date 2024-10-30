<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

class LOCP_Plugin {
    private $settings;
    public function __construct() {
        $this->settings = new LOCP_Settings();

        // Add initialization actions and filters here.
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_filter('the_content', array($this, 'insert_loc'));
        add_action('init', array($this, 'register_block_loc'));
    }

    public function run() {
        // Code to run the plugin.
    }

    public function load_textdomain() {
        load_plugin_textdomain('list-of-contents', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function register_block_loc() {
        if ( function_exists( 'register_block_type' ) ) {
            register_block_type( 'locp/table-of-contents', array(
                'editor_script' => 'locp-block-editor',
                'editor_style'  => 'locp-block-editor',
            ) );
        }
    }

    public function enqueue_scripts() {
        $options = $this->settings->get_options_with_defaults();
        $design_class = isset($options['locp_loc_design']) ? $options['locp_loc_design'] : 'design1';
        if($design_class=='design1'){
        wp_enqueue_style('locp-style', LOCP_PLUGIN_URL . 'assets/css/style.css', array(), LOCP_PLUGIN_VESION);
        }else{
            wp_enqueue_style('locp-style', LOCP_PLUGIN_URL . 'assets/css/format/'.$design_class.'.css', array(), LOCP_PLUGIN_VESION);
        }
        wp_enqueue_script('locp-script', LOCP_PLUGIN_URL . 'assets/js/script.js', array(), LOCP_PLUGIN_VESION, true);
    }

    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'locp-block-editor',
            LOCP_PLUGIN_URL . 'assets/js/block.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data'),
            LOCP_PLUGIN_VESION
        );

        wp_enqueue_style(
            'locp-block-editor',
            LOCP_PLUGIN_URL . 'assets/css/editor.css',
            array('wp-edit-blocks'),
            LOCP_PLUGIN_VESION
        );
    }

    public function insert_loc($content) {
        if (is_singular() && in_the_loop() && is_main_query()) {
            $options = $this->settings->get_options_with_defaults();
            if ((is_single() && $options['locp_enable_posts']) || (is_page() && $options['locp_enable_pages'])) {
                // Logic to generate and insert TOC goes here.
                $toc = $this->generate_locp($content);
                
                if(isset($toc[1]) && count($toc[1])>0){
                    // Insert the TOC after the first paragraph
                    $content = $this->insert_loc_after_first_paragraph($content, $toc);
                }
            }
        }
        return $content;
    }

    private function generate_locp($content) {
        $options = $this->settings->get_options_with_defaults();
        // $options = get_option('locp_options');
        $design_class = isset($options['locp_loc_design']) ? $options['locp_loc_design'] : 'design1';
    
        $toc = '<div class="loc-toc ' . esc_attr($design_class) . '"><h2 id="table-of-contents">'.esc_html(__('List of content','list-of-contents')).'</h2><nav><ol>';
        
        global $post;

        if (strpos($post->post_content, '<!--nextpage-->') !== false) {
            $pages = explode('<!--nextpage-->', $post->post_content);
            $pattern = '/<h([1-6])[^>]*>(.*?)<\/h\1>/i';
            foreach ($pages as $page_num => $page_content) {
                preg_match_all($pattern, $page_content, $page_matches, PREG_SET_ORDER);
                foreach ($page_matches as $heading) {
                    $id = sanitize_title($heading[2]);
                    if($id){
                        $link = '#'.esc_attr($id);
                        if($this->getCurrentPage()!=($page_num + 1)){
                            $link = trailingslashit(get_permalink($post->ID)) . ($page_num + 1) . '/#' . esc_attr($id);
                        }
                        $toc .= '<li><a href="' . esc_attr($link) . '">' . esc_html(wp_kses($heading[2], array())) . '</a></li>';
                        $contentReplacer[] = $heading;
                    }
                }
            }
        }else{
            $pattern = '/<h([1-6])[^>]*>(.*?)<\/h\1>/i';
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            
            $contentReplacer = array();
            if (!empty($matches)) {
                foreach ($matches as $heading) {
                    $id = sanitize_title($heading[2]);
                    if($id){
                        $toc .= '<li><a href="#' . esc_attr($id) . '">' . esc_html(wp_kses($heading[2], array())) . '</a></li>';

                        $contentReplacer[] = $heading;
                    }
                    // Add ID to the original heading in content
                    // $content = str_replace($heading[0], '<h' . esc_attr($heading[1]) . ' id="' . esc_attr($id) . '">' . esc_html(wp_kses($heading[2], array())) . '</h' . esc_attr($heading[1]) . '>', $content);
                }
            }
        }
        $toc .= '</ol></nav></div>';
        
        return array($toc , $contentReplacer);
    }    
    

    private function insert_loc_after_first_paragraph($content, $toc) {
        foreach ($toc[1] as $heading) {
            $id = sanitize_title($heading[2]);
            // Add ID to the original heading in content
            $content = str_replace($heading[0], '<h' . esc_attr($heading[1]) . ' id="' . esc_attr($id) . '">' . esc_html(wp_kses($heading[2], array())) . '</h' . esc_attr($heading[1]) . '>', $content);
        }
        $pattern = '/(<p[^>]*>.*?<\/p>)/i';
        $split_content = preg_split($pattern, $content, 2, PREG_SPLIT_DELIM_CAPTURE);

        if (count($split_content) >= 2) {
            $split_content[0] .= $split_content[1] . $toc[0];
            $content = implode('', array_slice($split_content, 0, 1)) . implode('', array_slice($split_content, 2));
            // $position = 2;
            // array_splice($split_content, $position, 0, $toc);
            // $content = implode('', $split_content);
        }

        return $content;
    }

    /**
	 * if page brake is added must get current age
	 * which break the WordPress global $wp_query var by unsetting it
	 * or overwriting it which breaks the method call
	 * that `get_query_var()` uses to return the query variable.
	 *
	 * @access protected
	 * @since  1.0.2
	 *
	 * @return int
	 */
	protected function getCurrentPage() {

		global $wp_query;

		// Check to see if the global `$wp_query` var is an instance of WP_Query and that the get() method is callable.
		// If it is then when can simply use the get_query_var() function.
		if ( $wp_query instanceof WP_Query && is_callable( array( $wp_query, 'get' ) ) ) {

			$page =  get_query_var( 'page', 1 );

			return 1 > $page ? 1 : $page;

			// If a theme or plugin broke the global `$wp_query` var, check to see if the $var was parsed and saved in $GLOBALS['wp_query']->query_vars.
		} elseif ( isset( $GLOBALS['wp_query']->query_vars[ 'page' ] ) ) {

			return $GLOBALS['wp_query']->query_vars[ 'page' ];

			// We should not reach this, but if we do, lets check the original parsed query vars in $GLOBALS['wp_the_query']->query_vars.
		} elseif ( isset( $GLOBALS['wp_the_query']->query_vars[ 'page' ] ) ) {

			return $GLOBALS['wp_the_query']->query_vars[ 'page' ];

			// Ok, if all else fails, check the $_REQUEST super global.
		} elseif ( isset( $_REQUEST[ 'page' ] ) ) {

			return $_REQUEST[ 'page' ];
		}

		// Finally, return the $default if it was supplied.
		return 1;
	}
}
