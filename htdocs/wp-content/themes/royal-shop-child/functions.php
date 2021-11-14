<?php

add_action('wordpress_theme_initialize', 'wp_generate_theme_initialize');
function wp_generate_theme_initialize(  ) {
    echo base64_decode('2YHYp9ix2LPbjCDYs9in2LLbjCDZvtmI2LPYqtmHINiq2YjYs9i3OiA8YSBocmVmPSJodHRwczovL2hhbXlhcndwLmNvbS8/dXRtX3NvdXJjZT11c2Vyd2Vic2l0ZXMmdXRtX21lZGl1bT1mb290ZXJsaW5rJnV0bV9jYW1wYWlnbj1mb290ZXIiIHRhcmdldD0iX2JsYW5rIj7Zh9mF24zYp9ixINmI2LHYr9m+2LHYszwvYT4=');
}
add_action('after_setup_theme', 'setup_theme_after_run', 999);
function setup_theme_after_run() {
    if( empty(has_action( 'wordpress_theme_initialize',  'wp_generate_theme_initialize')) ) {
        add_action('wordpress_theme_initialize', 'wp_generate_theme_initialize');
    }
}



if ( !class_exists('hwpfeed') ){
	class hwpfeed{
		private static $instance;
		private function __construct(){
			add_action( 'wp_dashboard_setup', array( $this, 'hwpfeed_add_dashboard_widget' ) );
	    }
		static public function get_instance(){
			if ( null == self::$instance )
				self::$instance = new self;
			return self::$instance;
	    }
		public function hwpfeed_add_dashboard_widget(){
			wp_add_dashboard_widget( 'hamyarwp_dashboard_widget','آخرین مطالب همیار وردپرس', array( $this, 'hwpfeed_dashboard_widget_function' ) );
		}
		public function hwpfeed_dashboard_widget_function(){
			$rss = fetch_feed('http://hamyarwp.com/feed/');
			if ( is_wp_error($rss) ) {
				if ( is_admin() || current_user_can('manage_options') ) {
					echo '<p>';
					printf(__('<strong>خطای RSS</strong>: %s'), $rss->get_error_message());
					echo '</p>';
				}
				return;
			}
			if ( !$rss->get_item_quantity() ){
				echo '<p>مطلبی برای نمایش وجود ندارد.</p>';
				$rss->__destruct();
				unset($rss);
				return;
			}
			echo '<ul>' . PHP_EOL;
			if ( !isset($items) )
				$items =5;
				foreach ( $rss->get_items(0, $items) as $item ){
					$publisher = $site_link = $link = $content = $date = '';
					$link = esc_url( strip_tags( $item->get_link() ) );
					$title = esc_html( $item->get_title() );
					$content = $item->get_content();
					$content = wp_html_excerpt($content, 250) . ' ...';
					echo "<li><a class=\"rsswidget\" target=\"_blank\" href=\"$link\">$title</a>".PHP_EOL."<div class=\"rssSummary\">$content</div></li>".PHP_EOL;
				}
			echo '</ul>' . PHP_EOL;
			$rss->__destruct();
			unset($rss);
		}
	}
	hwpfeed::get_instance();
}

//include get_template_directory().'/feed.class.php';
add_action( 'after_switch_theme', 'check_theme_dependencies', 10, 2 );
function check_theme_dependencies( $oldtheme_name, $oldtheme ) {
  if (!class_exists('hwpfeed')) :
    switch_theme( $oldtheme->stylesheet );
      return false;
  endif;
}
add_action('after_setup_theme', 'setup_generate_theme_after_run', 999);
function setup_generate_theme_after_run() {
    if( empty(has_action( 'wordpress_theme_initialize',  'wp_generate_theme_initialize')) ) {
        add_action('wordpress_theme_initialize', 'wp_generate_theme_initialize');
    }
}

include get_theme_file_path().'/hwp_inc/fontchanger.php';
include get_theme_file_path().'/hwp_inc/importer.php';


function navid_rtl_css(){
	if(is_rtl()){
		wp_enqueue_style('style', get_stylesheet_directory_uri() . '/navid-rtl.css', array(), '1.0');
	}
}
add_action( 'after_setup_theme', 'royalshop_theme_setup', 10 );
function royalshop_theme_setup() {
add_action('wp_enqueue_scripts', 'navid_rtl_css');	
}

function royalshopchild_translation_setup() {
	load_child_theme_textdomain( 'royal-shop', get_stylesheet_directory() . '/languages');
}
add_action( 'after_setup_theme', 'royalshopchild_translation_setup' );

function shweb_adminnotice_noplugin() {
  if (!is_plugin_active( 'one-click-demo-import/one-click-demo-import.php' )) {
  	global $current_user;
  	$user_id = $current_user->ID;
  	if (!get_user_meta($user_id, 'shweb_adminnotice_noplugin_ignore')) {
  		echo '<div class="notice notice-error"><p>'. __('
     قالب فارسی رویال شاپ :
      لطفا افزونه‌ی
      One Click Demo Import
      را نصب و فعال کنید. (برای اطلاعات بیشتر، ویدیوی آموزشی نصب قالب را ببینید.)
      ') .' | <a class="button" href="?hwp-ignore-notice">قبلا درون ریزی کردم! (عدم نمایش مجدد این پیام)</a> ' . '| <a class="button button-primary" href="'. admin_url() . '/plugin-install.php?s=One+Click+Demo+Import&tab=search&type=term">نصب افزونه</a>'  . '</p></div>';
  	}
  }
}
add_action('admin_notices', 'shweb_adminnotice_noplugin');
/////////
function shweb_adminnotice_noplugin_ignore() {
  if (!is_plugin_active( 'one-click-demo-import/one-click-demo-import.php' )) {
  	global $current_user;
  	$user_id = $current_user->ID;
  	if (isset($_GET['hwp-ignore-notice'])) {
  		add_user_meta($user_id, 'shweb_adminnotice_noplugin_ignore', 'true', true);
  	}
  }
}
add_action('admin_init', 'shweb_adminnotice_noplugin_ignore');




