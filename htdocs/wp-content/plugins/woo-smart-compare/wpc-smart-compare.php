<?php
/*
Plugin Name: WPC Smart Compare for WooCommerce
Plugin URI: https://wpclever.net/
Description: Smart products compare for WooCommerce.
Version: 4.2.0
Author: WPClever
Author URI: https://wpclever.net
Text Domain: woo-smart-compare
Domain Path: /languages/
Requires at least: 4.0
Tested up to: 5.8
WC requires at least: 3.0
WC tested up to: 5.8
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WOOSC_VERSION' ) && define( 'WOOSC_VERSION', '4.2.0' );
! defined( 'WOOSC_URI' ) && define( 'WOOSC_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOSC_PATH' ) && define( 'WOOSC_PATH', plugin_dir_path( __FILE__ ) );
! defined( 'WOOSC_SUPPORT' ) && define( 'WOOSC_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=woosc&utm_campaign=wporg' );
! defined( 'WOOSC_REVIEWS' ) && define( 'WOOSC_REVIEWS', 'https://wordpress.org/support/plugin/woo-smart-compare/reviews/?filter=5' );
! defined( 'WOOSC_CHANGELOG' ) && define( 'WOOSC_CHANGELOG', 'https://wordpress.org/plugins/woo-smart-compare/#developers' );
! defined( 'WOOSC_DISCUSSION' ) && define( 'WOOSC_DISCUSSION', 'https://wordpress.org/support/plugin/woo-smart-compare' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WOOSC_URI );

include 'includes/wpc-dashboard.php';
include 'includes/wpc-menu.php';
include 'includes/wpc-kit.php';
include 'includes/wpc-notice.php';

if ( ! function_exists( 'woosc_init' ) ) {
	add_action( 'plugins_loaded', 'woosc_init', 11 );

	function woosc_init() {
		// load text-domain
		load_plugin_textdomain( 'woo-smart-compare', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'woosc_notice_wc' );

			return;
		}

		if ( ! class_exists( 'WPCleverWoosc' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWoosc {
				protected static $woosc_localization = array();
				protected static $woosc_fields = array();
				protected static $woosc_attributes = array();

				function __construct() {
					self::$woosc_fields = array(
						'image'             => esc_html__( 'Image', 'woo-smart-compare' ),
						'sku'               => esc_html__( 'SKU', 'woo-smart-compare' ),
						'rating'            => esc_html__( 'Rating', 'woo-smart-compare' ),
						'price'             => esc_html__( 'Price', 'woo-smart-compare' ),
						'stock'             => esc_html__( 'Stock', 'woo-smart-compare' ),
						'availability'      => esc_html__( 'Availability', 'woo-smart-compare' ),
						'add_to_cart'       => esc_html__( 'Add to cart', 'woo-smart-compare' ),
						'description'       => esc_html__( 'Description', 'woo-smart-compare' ),
						'content'           => esc_html__( 'Content', 'woo-smart-compare' ),
						'weight'            => esc_html__( 'Weight', 'woo-smart-compare' ),
						'dimensions'        => esc_html__( 'Dimensions', 'woo-smart-compare' ),
						'additional'        => esc_html__( 'Additional information', 'woo-smart-compare' ),
						'attributes'        => esc_html__( 'Attributes', 'woo-smart-compare' ),
						'custom_attributes' => esc_html__( 'Custom attributes', 'woo-smart-compare' ),
						'custom_fields'     => esc_html__( 'Custom fields', 'woo-smart-compare' ),
					);

					// init
					add_action( 'init', array( $this, 'woosc_init' ) );
					add_action( 'wp_footer', array( $this, 'woosc_wp_footer' ) );
					add_action( 'wp_enqueue_scripts', array( $this, 'woosc_wp_enqueue_scripts' ) );
					add_action( 'admin_enqueue_scripts', array( $this, 'woosc_admin_enqueue_scripts' ) );
					add_filter( 'wp_dropdown_cats', array( $this, 'woosc_dropdown_cats_multiple' ), 10, 2 );

					// search
					add_action( 'wp_ajax_woosc_search', array( $this, 'woosc_search' ) );
					add_action( 'wp_ajax_nopriv_woosc_search', array( $this, 'woosc_search' ) );

					// after user login
					add_action( 'wp_login', array( $this, 'woosc_wp_login' ), 10, 2 );

					// ajax load bar items
					add_action( 'wp_ajax_woosc_load_bar', array( $this, 'woosc_load_bar' ) );
					add_action( 'wp_ajax_nopriv_woosc_load_bar', array( $this, 'woosc_load_bar' ) );

					// ajax load compare table
					add_action( 'wp_ajax_woosc_load_table', array( $this, 'woosc_load_table' ) );
					add_action( 'wp_ajax_nopriv_woosc_load_table', array( $this, 'woosc_load_table' ) );

					// settings page
					add_action( 'admin_menu', array( $this, 'woosc_admin_menu' ) );

					// settings link
					add_filter( 'plugin_action_links', array( $this, 'woosc_action_links' ), 10, 2 );
					add_filter( 'plugin_row_meta', array( $this, 'woosc_row_meta' ), 10, 2 );

					// menu items
					add_filter( 'wp_nav_menu_items', array( $this, 'woosc_nav_menu_items' ), 99, 2 );
				}

				function woosc_init() {
					self::$woosc_fields = apply_filters( 'woosc_fields', self::$woosc_fields );

					// localization
					self::$woosc_localization = (array) get_option( 'woosc_localization' );

					// attributes
					$wc_attributes = wc_get_attribute_taxonomies();

					if ( $wc_attributes ) {
						foreach ( $wc_attributes as $wc_attribute ) {
							self::$woosc_attributes[ $wc_attribute->attribute_name ] = $wc_attribute->attribute_label;
						}
					}

					// shortcode
					add_shortcode( 'woosc', array( $this, 'woosc_shortcode' ) );
					add_shortcode( 'woosc_list', array( $this, 'woosc_shortcode_list' ) );

					// image sizes
					add_image_size( 'woosc-large', 600, 600, true );
					add_image_size( 'woosc-small', 96, 96, true );

					// add button for archive
					$button_archive = apply_filters( 'woosc_button_position_archive', get_option( 'woosc_button_archive', 'after_add_to_cart' ) );

					switch ( $button_archive ) {
						case 'after_title':
							add_action( 'woocommerce_shop_loop_item_title', array( $this, 'woosc_add_button' ), 11 );
							break;

						case 'after_rating':
							add_action( 'woocommerce_after_shop_loop_item_title', array(
								$this,
								'woosc_add_button'
							), 6 );
							break;

						case 'after_price':
							add_action( 'woocommerce_after_shop_loop_item_title', array(
								$this,
								'woosc_add_button'
							), 11 );
							break;

						case 'before_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', array( $this, 'woosc_add_button' ), 9 );
							break;

						case 'after_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', array( $this, 'woosc_add_button' ), 11 );
							break;
					}

					// add button for single
					$button_single = apply_filters( 'woosc_button_position_single', get_option( 'woosc_button_single', '31' ) );

					if ( ! empty( $button_single ) ) {
						add_action( 'woocommerce_single_product_summary', array(
							$this,
							'woosc_add_button'
						), (int) $button_single );
					}
				}

				function woosc_localization( $key = '', $default = '' ) {
					$str = '';

					if ( ! empty( $key ) && ! empty( self::$woosc_localization[ $key ] ) ) {
						$str = self::$woosc_localization[ $key ];
					} elseif ( ! empty( $default ) ) {
						$str = $default;
					}

					return apply_filters( 'woosc_localization_' . $key, $str );
				}

				function woosc_wp_login( $user_login, $user ) {
					if ( isset( $user->data->ID ) ) {
						$user_products = get_user_meta( $user->data->ID, 'woosc_products', true );
						$user_fields   = get_user_meta( $user->data->ID, 'woosc_fields', true );

						if ( ! empty( $user_products ) ) {
							setcookie( 'woosc_products_' . md5( 'woosc' . $user->data->ID ), $user_products, time() + 604800, '/' );
						}

						if ( ! empty( $user_fields ) ) {
							setcookie( 'woosc_fields_' . md5( 'woosc' . $user->data->ID ), $user_fields, time() + 604800, '/' );
						}
					}
				}

				function woosc_wp_enqueue_scripts() {
					// hint
					wp_enqueue_style( 'hint', WOOSC_URI . 'assets/libs/hint/hint.min.css' );

					// table head fixer
					wp_enqueue_script( 'table-head-fixer', WOOSC_URI . 'assets/libs/table-head-fixer/table-head-fixer.js', array( 'jquery' ), WOOSC_VERSION, true );

					// perfect srollbar
					if ( get_option( 'woosc_perfect_scrollbar', 'yes' ) === 'yes' ) {
						wp_enqueue_style( 'perfect-scrollbar', WOOSC_URI . 'assets/libs/perfect-scrollbar/css/perfect-scrollbar.min.css' );
						wp_enqueue_style( 'perfect-scrollbar-wpc', WOOSC_URI . 'assets/libs/perfect-scrollbar/css/custom-theme.css' );
						wp_enqueue_script( 'perfect-scrollbar', WOOSC_URI . 'assets/libs/perfect-scrollbar/js/perfect-scrollbar.jquery.min.js', array( 'jquery' ), WOOSC_VERSION, true );
					}

					// frontend css & js
					wp_enqueue_style( 'woosc-frontend', WOOSC_URI . 'assets/css/frontend.css', array(), WOOSC_VERSION );
					wp_enqueue_script( 'woosc-frontend', WOOSC_URI . 'assets/js/frontend.js', array(
						'jquery',
						'jquery-ui-sortable'
					), WOOSC_VERSION, true );

					wp_localize_script( 'woosc-frontend', 'woosc_vars', array(
							'ajaxurl'            => admin_url( 'admin-ajax.php' ),
							'user_id'            => md5( 'woosc' . get_current_user_id() ),
							'page_url'           => self::woosc_get_page_url(),
							'open_button'        => $this->woosc_nice_class_id( get_option( 'woosc_open_button', '' ) ),
							'open_button_action' => get_option( 'woosc_open_button_action', 'open_popup' ),
							'menu_action'        => get_option( 'woosc_menu_action', 'open_popup' ),
							'open_table'         => get_option( 'woosc_open_immediately', 'yes' ) === 'yes' ? 'yes' : 'no',
							'open_bar'           => get_option( 'woosc_open_bar_immediately', 'no' ) === 'yes' ? 'yes' : 'no',
							'bar_bubble'         => get_option( 'woosc_bar_bubble', 'no' ),
							'click_again'        => get_option( 'woosc_click_again', 'no' ) === 'yes' ? 'yes' : 'no',
							'hide_empty'         => get_option( 'woosc_hide_empty', 'no' ),
							'click_outside'      => get_option( 'woosc_click_outside', 'yes' ),
							'freeze_column'      => get_option( 'woosc_freeze_column', 'yes' ),
							'freeze_row'         => get_option( 'woosc_freeze_row', 'yes' ),
							'scrollbar'          => get_option( 'woosc_perfect_scrollbar', 'yes' ),
							'limit'              => get_option( 'woosc_limit', '100' ),
							'button_text_change' => get_option( 'woosc_button_text_change', 'yes' ),
							'remove_all'         => self::woosc_localization( 'bar_remove_all_confirmation', esc_html__( 'Do you want to remove all products from the compare?', 'woo-smart-compare' ) ),
							'limit_notice'       => self::woosc_localization( 'limit', esc_html__( 'You can add a maximum of {limit} products to the compare table.', 'woo-smart-compare' ) ),
							'button_text'        => apply_filters( 'woosc_button_text', self::woosc_localization( 'button', esc_html__( 'Compare', 'woo-smart-compare' ) ) ),
							'button_text_added'  => apply_filters( 'woosc_button_text_added', self::woosc_localization( 'button_added', esc_html__( 'Compare', 'woo-smart-compare' ) ) ),
							'nonce'              => wp_create_nonce( 'woosc-nonce' ),
						)
					);
				}

				function woosc_admin_enqueue_scripts( $hook ) {
					wp_enqueue_style( 'woosc-backend', WOOSC_URI . 'assets/css/backend.css', array(), WOOSC_VERSION );

					if ( strpos( $hook, 'woosc' ) ) {
						wp_enqueue_style( 'wp-color-picker' );
						wp_enqueue_script( 'woosc-backend', WOOSC_URI . 'assets/js/backend.js', array(
							'jquery',
							'wp-color-picker',
							'jquery-ui-sortable'
						), WOOSC_VERSION, true );
					}
				}

				function woosc_action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings         = '<a href="' . admin_url( 'admin.php?page=wpclever-woosc&tab=settings' ) . '">' . esc_html__( 'Settings', 'woo-smart-compare' ) . '</a>';
						$links['premium'] = '<a href="' . admin_url( 'admin.php?page=wpclever-woosc&tab=premium' ) . '">' . esc_html__( 'Premium Version', 'woo-smart-compare' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function woosc_row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = array(
							'support' => '<a href="' . esc_url( WOOSC_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'woo-smart-compare' ) . '</a>',
						);

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function woosc_admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'WPC Smart Compare', 'woo-smart-compare' ), esc_html__( 'Smart Compare', 'woo-smart-compare' ), 'manage_options', 'wpclever-woosc', array(
						$this,
						'woosc_settings_page'
					) );
				}

				function woosc_dropdown_cats_multiple( $output, $r ) {
					if ( isset( $r['multiple'] ) && $r['multiple'] ) {
						$output = preg_replace( '/^<select/i', '<select multiple', $output );
						$output = str_replace( "name='{$r['name']}'", "name='{$r['name']}[]'", $output );

						foreach ( array_map( 'trim', explode( ',', $r['selected'] ) ) as $value ) {
							$output = str_replace( "value=\"{$value}\"", "value=\"{$value}\" selected", $output );
						}
					}

					return $output;
				}

				function woosc_settings_page() {
					add_thickbox();
					$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Smart Compare', 'woo-smart-compare' ) . ' ' . WOOSC_VERSION; ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'woo-smart-compare' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WOOSC_REVIEWS ); ?>"
                                   target="_blank"><?php esc_html_e( 'Reviews', 'woo-smart-compare' ); ?></a> | <a
                                        href="<?php echo esc_url( WOOSC_CHANGELOG ); ?>"
                                        target="_blank"><?php esc_html_e( 'Changelog', 'woo-smart-compare' ); ?></a>
                                | <a href="<?php echo esc_url( WOOSC_DISCUSSION ); ?>"
                                     target="_blank"><?php esc_html_e( 'Discussion', 'woo-smart-compare' ); ?></a>
                            </p>
                        </div>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosc&tab=settings' ); ?>"
                                   class="<?php echo( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'woo-smart-compare' ); ?>
                                </a>
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosc&tab=localization' ); ?>"
                                   class="<?php echo $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'Localization', 'woo-smart-compare' ); ?>
                                </a>
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosc&tab=premium' ); ?>"
                                   class="<?php echo( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>"
                                   style="color: #c9356e">
									<?php esc_html_e( 'Premium Version', 'woo-smart-compare' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>"
                                   class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'woo-smart-compare' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'settings' ) { ?>
                                <form method="post" action="options.php">
									<?php wp_nonce_field( 'update-options' ) ?>
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'General', 'woo-smart-compare' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Open compare bar immediately', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="checkbox"
                                                       name="woosc_open_bar_immediately"
                                                       value="yes" <?php echo( get_option( 'woosc_open_bar_immediately', 'no' ) === 'yes' ? 'checked' : '' ); ?>/>
                                                <span class="description">
											<?php esc_html_e( 'Check it if you want to open the compare bar immediately on page loaded.', 'woo-smart-compare' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Open compare table immediately', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="checkbox"
                                                       name="woosc_open_immediately"
                                                       value="yes" <?php echo( get_option( 'woosc_open_immediately', 'yes' ) === 'yes' ? 'checked' : '' ); ?>/>
                                                <span class="description">
											<?php esc_html_e( 'Check it if you want to open the compare table immediately when click to compare button. If not, it just add product to the compare bar.', 'woo-smart-compare' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Hide on cart & checkout page', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_hide_checkout">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_hide_checkout', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_hide_checkout', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
											<?php esc_html_e( 'Hide the compare table and compare bar on the cart & checkout page?', 'woo-smart-compare' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Hide if empty', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_hide_empty">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_hide_empty', 'no' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_hide_empty', 'no' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
													<?php esc_html_e( 'Hide the compare table and compare bar if haven\'t any product.', 'woo-smart-compare' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Limit', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input name="woosc_limit" type="number" min="1" max="100" step="1"
                                                       value="<?php echo get_option( 'woosc_limit', '100' ); ?>"/>
                                                <span class="description">
													<?php esc_html_e( 'The maximum of products can be added to the compare table.', 'woo-smart-compare' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Compare page', 'woo-smart-compare' ); ?></th>
                                            <td>
												<?php wp_dropdown_pages( array(
													'selected'          => get_option( 'woosc_page_id', '' ),
													'name'              => 'woosc_page_id',
													'show_option_none'  => esc_html__( 'Choose a page', 'woo-smart-compare' ),
													'option_none_value' => '',
												) ); ?>
                                                <span class="description">
											<?php printf( esc_html__( 'Add shortcode %s to display the compare table on this page.', 'woo-smart-compare' ), '<code>[woosc_list]</code>' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Custom button', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" name="woosc_open_button" class="regular-text"
                                                       value="<?php echo get_option( 'woosc_open_button', '' ); ?>"
                                                       placeholder="<?php esc_html_e( 'button class or id', 'woo-smart-compare' ); ?>"/>
                                                <span class="description"><?php printf( esc_html__( 'Example %s or %s', 'woo-smart-compare' ), '<code>.open-compare-btn</code>', '<code>#open-compare-btn</code>' ); ?></span>
                                                <p class="description"><?php esc_html_e( 'The class or id of the button, when clicking on this button the compare page or compare table will be opened.', 'woo-smart-compare' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Custom button action', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_open_button_action">
                                                    <option
                                                            value="open_page" <?php echo( get_option( 'woosc_open_button_action', 'open_popup' ) === 'open_page' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open page', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="open_popup" <?php echo( get_option( 'woosc_open_button_action', 'open_popup' ) === 'open_popup' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open popup', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select> <span class="description">
											<?php esc_html_e( 'Action when clicking on the "custom button".', 'woo-smart-compare' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Compare button', 'woo-smart-compare' ); ?>
                                            </th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Type', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_button_type">
                                                    <option
                                                            value="button" <?php echo( get_option( 'woosc_button_type', 'button' ) === 'button' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Button', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="link" <?php echo( get_option( 'woosc_button_type', 'button' ) === 'link' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Link', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Extra class (optional)', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" name="woosc_button_class" class="regular-text"
                                                       value="<?php echo get_option( 'woosc_button_class', '' ); ?>"/>
                                                <span class="description">
													<?php esc_html_e( 'Add extra class for action button/link, split by one space.', 'woo-smart-compare' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position on products list', 'woo-smart-compare' ); ?></th>
                                            <td>
												<?php $button_archive = apply_filters( 'woosc_button_position_archive', 'default' ); ?>
                                                <select name="woosc_button_archive" <?php echo( $button_archive !== 'default' ? 'disabled' : '' ); ?>>
													<?php if ( $button_archive === 'default' ) {
														$button_archive = get_option( 'woosc_button_archive', 'after_add_to_cart' );
													} ?>
                                                    <option value="after_title" <?php echo( $button_archive === 'after_title' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under title', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="after_rating" <?php echo( $button_archive === 'after_rating' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under rating', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="after_price" <?php echo( $button_archive === 'after_price' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under price', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="before_add_to_cart" <?php echo( $button_archive === 'before_add_to_cart' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Above add to cart', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="after_add_to_cart" <?php echo( $button_archive === 'after_add_to_cart' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under add to cart', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="0" <?php echo( ! $button_archive || ( $button_archive === '0' ) ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'None (hide it)', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position on single product page', 'woo-smart-compare' ); ?></th>
                                            <td>
												<?php $button_single = apply_filters( 'woosc_button_position_single', 'default' ); ?>
                                                <select name="woosc_button_single" <?php echo( $button_single !== 'default' ? 'disabled' : '' ); ?>>
													<?php if ( $button_single === 'default' ) {
														$button_single = get_option( 'woosc_button_single', '31' );
													} ?>
                                                    <option value="6" <?php echo( $button_single === '6' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under title', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="11" <?php echo( $button_single === '11' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under price & rating', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="21" <?php echo( $button_single === '21' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under excerpt', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="29" <?php echo( $button_single === '29' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Above add to cart', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="31" <?php echo( $button_single === '31' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under add to cart', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="41" <?php echo( $button_single === '41' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under meta', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="51" <?php echo( $button_single === '51' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under sharing', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option value="0" <?php echo( ! $button_single || ( $button_single === '0' ) ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'None (hide it)', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Manual', 'woo-smart-compare' ); ?></th>
                                            <td>
												<span class="description">
													<?php
													printf( esc_html__( 'You can use the shortcode %s, eg. %s for the product with ID is 99.', 'woo-smart-compare' ), '<code>[woosc id="{product id}"]</code>', '<code>[woosc id="99"]</code>' );
													?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Categories', 'woo-smart-compare' ); ?></th>
                                            <td>
												<?php
												$selected_cats = get_option( 'woosc_search_cats' );

												if ( empty( $selected_cats ) ) {
													$selected_cats = array( '0' );
												}

												wc_product_dropdown_categories(
													array(
														'name'             => 'woosc_search_cats',
														'hide_empty'       => 0,
														'value_field'      => 'id',
														'multiple'         => true,
														'show_option_all'  => esc_html__( 'All categories', 'woo-smart-compare' ),
														'show_option_none' => '',
														'selected'         => implode( ',', $selected_cats )
													) );
												?>
                                                <span class="description">
													<?php esc_html_e( 'Only show the compare button for products in selected categories.', 'woo-smart-compare' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Change button text', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_button_text_change">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_button_text_change', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_button_text_change', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
													<?php esc_html_e( 'Change the button text after adding to the compare. If not, only add the class CSS name \'woosc-added\'.', 'woo-smart-compare' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Remove when clicking again', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_click_again">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_click_again', 'no' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_click_again', 'no' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
											<?php esc_html_e( 'Do you want to remove product when clicking again?', 'woo-smart-compare' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Compare table', 'woo-smart-compare' ); ?>
                                            </th>
                                            <td>

                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Fields', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <ul class="woosc-fields">
													<?php
													$saved_fields = $saved_fields_arr = array();

													if ( is_array( get_option( 'woosc_fields' ) ) ) {
														$saved_fields = get_option( 'woosc_fields' );
													} else {
														$saved_fields = array_keys( self::$woosc_fields );
													}

													foreach ( $saved_fields as $sf ) {
														if ( isset( self::$woosc_fields[ $sf ] ) ) {
															$saved_fields_arr[ $sf ] = self::$woosc_fields[ $sf ];
														}
													}

													$fields_merge = array_merge( $saved_fields_arr, self::$woosc_fields );

													foreach ( $fields_merge as $key => $value ) {
														echo '<li class="woosc-fields-item"><input type="checkbox" name="woosc_fields[]" value="' . $key . '" ' . ( in_array( $key, $saved_fields, false ) ? 'checked' : '' ) . '/><span class="label">' . $value . '</span></li>';
													}
													?>
                                                </ul>
                                                <span class="description">
                                                    <?php esc_html_e( 'Please choose the fields you want to show on the compare table. You also can drag/drop to rearrange these fields.', 'woo-smart-compare' ); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Attributes', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <ul class="woosc-attributes">
													<?php
													$saved_attributes = $saved_attributes_arr = array();

													if ( is_array( get_option( 'woosc_attributes' ) ) ) {
														$saved_attributes = get_option( 'woosc_attributes' );
													}

													foreach ( $saved_attributes as $sa ) {
														if ( isset( self::$woosc_attributes[ $sa ] ) ) {
															$saved_attributes_arr[ $sa ] = self::$woosc_attributes[ $sa ];
														}
													}

													$attributes_merge = array_merge( $saved_attributes_arr, self::$woosc_attributes );

													foreach ( $attributes_merge as $key => $value ) {
														echo '<li class="woosc-attributes-item"><input type="checkbox" name="woosc_attributes[]" value="' . $key . '" ' . ( in_array( $key, $saved_attributes, false ) ? 'checked' : '' ) . '/><span class="label">' . $value . '</span></li>';
													}
													?>
                                                </ul>
                                                <span class="description">
													<?php esc_html_e( 'Please choose the attributes you want to show on the compare table. You also can drag/drop to rearrange these attributes.', 'woo-smart-compare' ); ?>
												</span>
                                                <p class="description" style="color: #c9356e">
                                                    * This feature only available on Premium Version. Click <a
                                                            href="https://wpclever.net/downloads/woocommerce-smart-compare?utm_source=pro&utm_medium=woosc&utm_campaign=wporg"
                                                            target="_blank">here</a> to buy, just $29!
                                                </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Custom attributes', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <textarea name="woosc_custom_attributes" rows="10" cols="50"
                                                          class="large-text"><?php echo get_option( 'woosc_custom_attributes' ); ?></textarea>
                                                <span class="description">
													<?php esc_html_e( 'Add custom attribute names, split by a comma.', 'woo-smart-compare' ); ?>
                                                     E.g: <code>Custom attribute 1, Custom attribute 2</code>
												</span>
                                                <p class="description" style="color: #c9356e">
                                                    * This feature only available on Premium Version. Click <a
                                                            href="https://wpclever.net/downloads/woocommerce-smart-compare?utm_source=pro&utm_medium=woosc&utm_campaign=wporg"
                                                            target="_blank">here</a> to buy, just $29!
                                                </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Custom fields', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <textarea name="woosc_custom_fields" rows="10" cols="50"
                                                          class="large-text"><?php echo get_option( 'woosc_custom_fields' ); ?></textarea>
                                                <span class="description">
													<?php esc_html_e( 'Add custom field names/slugs and labels, split by a comma.', 'woo-smart-compare' ); ?>
                                                     E.g: <code>Field name 1 | Label 1, field-slug-2, Field name 3</code>
												</span>
                                                <p class="description" style="color: #c9356e">
                                                    * This feature only available on Premium Version. Click <a
                                                            href="https://wpclever.net/downloads/woocommerce-smart-compare?utm_source=pro&utm_medium=woosc&utm_campaign=wporg"
                                                            target="_blank">here</a> to buy, just $29!
                                                </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Image size', 'woo-smart-compare' ); ?></th>
                                            <td>
												<?php
												$image_size          = get_option( 'woosc_image_size', 'woosc-large' );
												$image_sizes         = $this->woosc_get_image_sizes();
												$image_sizes['full'] = array(
													'width'  => '',
													'height' => '',
													'crop'   => false
												);

												if ( ! empty( $image_sizes ) ) {
													echo '<select name="woosc_image_size">';

													foreach ( $image_sizes as $image_size_name => $image_size_data ) {
														echo '<option value="' . esc_attr( $image_size_name ) . '" ' . ( $image_size_name === $image_size ? 'selected' : '' ) . '>' . esc_attr( $image_size_name ) . ( ! empty( $image_size_data['width'] ) ? ' ' . $image_size_data['width'] . '&times;' . $image_size_data['height'] : '' ) . ( $image_size_data['crop'] ? ' (cropped)' : '' ) . '</option>';
													}

													echo '</select>';
												}
												?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Link to individual product', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_link">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_link', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, open in the same tab', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="yes_blank" <?php echo( get_option( 'woosc_link', 'yes' ) === 'yes_blank' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, open in the new tab', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="yes_popup" <?php echo( get_option( 'woosc_link', 'yes' ) === 'yes_popup' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, open quick view popup', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_link', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select> <span class="description">If you choose "Open quick view popup", please install <a
                                                            href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-compare&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                            class="thickbox" title="Install WPC Smart Quick View">WPC Smart Quick View</a> to make it work.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Freeze first column', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_freeze_column">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_freeze_column', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_freeze_column', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select> <span class="description">
													<?php esc_html_e( 'Freeze the first column (fields and attributes title) when scrolling horizontally.', 'woo-smart-compare' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Freeze first row', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_freeze_row">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_freeze_row', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_freeze_row', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select> <span class="description">
													<?php esc_html_e( 'Freeze the first row (product name) when scrolling vertically.', 'woo-smart-compare' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Use perfect-scrollbar', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_perfect_scrollbar">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_perfect_scrollbar', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_perfect_scrollbar', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
											<?php printf( esc_html__( 'Read more about %s', 'woo-smart-compare' ), '<a href="https://github.com/mdbootstrap/perfect-scrollbar" target="_blank">perfect-scrollbar</a>' ); ?>.
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Close button', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_close_button">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_close_button', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_close_button', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
											<?php esc_html_e( 'Enable the close button at top-right conner of compare table?', 'woo-smart-compare' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Compare bar', 'woo-smart-compare' ); ?>
                                            </th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Bubble', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_bar_bubble">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_bar_bubble', 'no' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_bar_bubble', 'no' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
													<?php esc_html_e( 'Use the bubble instead of a fully compare bar.', 'woo-smart-compare' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( '"Settings" button', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_bar_settings">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_bar_settings', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_bar_settings', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
													<?php esc_html_e( 'Show the settings popup to customize fields (show/ hide / rearrange).', 'woo-smart-compare' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( '"Add more" button', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_bar_add">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_bar_add', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_bar_add', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
													<?php esc_html_e( 'Add the button to search product and add to compare list immediately.', 'woo-smart-compare' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( '"Add more" count', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="number" min="1" max="100" name="woosc_search_count"
                                                       value="<?php echo get_option( 'woosc_search_count', 10 ); ?>"/>
                                                <span class="description">
													<?php esc_html_e( 'The result count of search function when clicking on "Add more" button.', 'woo-smart-compare' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( '"Remove all" button', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_bar_remove">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_bar_remove', 'no' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_bar_remove', 'no' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
											<?php esc_html_e( 'Add the button to remove all products from compare immediately.', 'woo-smart-compare' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Background color', 'woo-smart-compare' ); ?></th>
                                            <td>
												<?php $woosc_bar_bg_color_default = apply_filters( 'woosc_bar_bg_color_default', '#292a30' ); ?>
                                                <input type="text" name="woosc_bar_bg_color"
                                                       value="<?php echo get_option( 'woosc_bar_bg_color', $woosc_bar_bg_color_default ); ?>"
                                                       class="woosc_color_picker"/>
                                                <span class="description">
											<?php printf( esc_html__( 'Choose the background color for the compare bar, default %s', 'woo-smart-compare' ), '<code>' . $woosc_bar_bg_color_default . '</code>' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Button color', 'woo-smart-compare' ); ?></th>
                                            <td>
												<?php
												$woosc_bar_btn_color_default = apply_filters( 'woosc_bar_btn_color_default', '#00a0d2' );
												?>
                                                <input type="text" name="woosc_bar_btn_color"
                                                       value="<?php echo get_option( 'woosc_bar_btn_color', $woosc_bar_btn_color_default ); ?>"
                                                       class="woosc_color_picker"/>
                                                <span class="description">
											<?php printf( esc_html__( 'Choose the color for the button on compare bar, default %s', 'woo-smart-compare' ), '<code>' . $woosc_bar_btn_color_default . '</code>' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_bar_pos">
                                                    <option
                                                            value="bottom" <?php echo( get_option( 'woosc_bar_pos', 'bottom' ) === 'bottom' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Bottom', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="top" <?php echo( get_option( 'woosc_bar_pos', 'bottom' ) === 'top' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Top', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Align', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_bar_align">
                                                    <option
                                                            value="right" <?php echo( get_option( 'woosc_bar_align', 'right' ) === 'right' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Right', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="left" <?php echo( get_option( 'woosc_bar_align', 'right' ) === 'left' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Left', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Click outside to hide', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_click_outside">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosc_click_outside', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="yes_empty" <?php echo( get_option( 'woosc_click_outside', 'yes' ) === 'yes_empty' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes if empty', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosc_click_outside', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Menu', 'woo-smart-compare' ); ?>
                                            </th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Menu(s)', 'woo-smart-compare' ); ?></th>
                                            <td>
												<?php
												$nav_args  = array(
													'hide_empty' => false,
													'fields'     => 'id=>name',
												);
												$nav_menus = get_terms( 'nav_menu', $nav_args );

												if ( $nav_menus ) {
													$saved_menus = get_option( 'woosc_menus', array() );

													foreach ( $nav_menus as $nav_id => $nav_name ) {
														echo '<input type="checkbox" name="woosc_menus[]" value="' . $nav_id . '" ' . ( is_array( $saved_menus ) && in_array( $nav_id, $saved_menus, false ) ? 'checked' : '' ) . '/><label>' . $nav_name . '</label><br/>';
													}
												} else {
													echo '<p>' . esc_html__( 'Haven\'t any menu yet. Please go to Appearance > Menus to create one.', 'woo-smart-compare' ) . '</p>';
												}
												?>
                                                <span class="description">
											<?php esc_html_e( 'Choose the menu(s) you want to add the "compare menu" at the end.', 'woo-smart-compare' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Action', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <select name="woosc_menu_action">
                                                    <option
                                                            value="open_page" <?php echo( get_option( 'woosc_menu_action', 'open_popup' ) === 'open_page' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open page', 'woo-smart-compare' ); ?>
                                                    </option>
                                                    <option
                                                            value="open_popup" <?php echo( get_option( 'woosc_menu_action', 'open_popup' ) === 'open_popup' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open popup', 'woo-smart-compare' ); ?>
                                                    </option>
                                                </select> <span class="description">
											<?php esc_html_e( 'Action when clicking on the "compare menu".', 'woo-smart-compare' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
                                                <input type="submit" name="submit" class="button button-primary"
                                                       value="<?php esc_html_e( 'Update Options', 'woo-smart-compare' ); ?>"/>
                                                <input type="hidden" name="action" value="update"/>
                                                <input type="hidden" name="page_options"
                                                       value="woosc_open_button,woosc_open_button_action,woosc_button_type,woosc_button_text_change,woosc_button_class,woosc_button_archive,woosc_button_single,woosc_open_bar_immediately,woosc_open_immediately,woosc_hide_checkout,woosc_click_again,woosc_close_button,woosc_hide_empty,woosc_bar_bubble,woosc_bar_settings,woosc_bar_add,woosc_bar_remove,woosc_bar_bg_color,woosc_bar_btn_color,woosc_bar_pos,woosc_bar_align,woosc_click_outside,woosc_limit,woosc_page_id,woosc_fields,woosc_attributes,woosc_custom_attributes,woosc_custom_fields,woosc_image_size,woosc_link,woosc_freeze_column,woosc_freeze_row,woosc_perfect_scrollbar,woosc_search_count,woosc_search_cats,woosc_menus,woosc_menu_action"/>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'localization' ) { ?>
                                <form method="post" action="options.php">
									<?php wp_nonce_field( 'update-options' ) ?>
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th scope="row"><?php esc_html_e( 'General', 'woo-smart-compare' ); ?></th>
                                            <td>
												<?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'woo-smart-compare' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Limit notice', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[limit]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'limit' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'You can add a maximum of {limit} products to the compare table.', 'woo-smart-compare' ); ?>"/>
                                                <span class="description"><?php esc_html_e( 'The notice when reaching the limit. Use {limit} to show the number.', 'woo-smart-compare' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th scope="row"><?php esc_html_e( 'Compare button', 'woo-smart-compare' ); ?></th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Button text', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[button]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'button' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Compare', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Button (added) text', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[button_added]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'button_added' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Compare', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th scope="row"><?php esc_html_e( 'Compare table', 'woo-smart-compare' ); ?></th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Close', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[table_close]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'table_close' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Close', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Empty', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[table_empty]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'table_empty' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'No product is added to the compare table.', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th scope="row"><?php esc_html_e( 'Compare bar', 'woo-smart-compare' ); ?></th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Button text', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[bar_button]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'bar_button' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Compare', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Add product', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[bar_add]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'bar_add' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Add product', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Search placeholder', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[bar_search_placeholder]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'bar_search_placeholder' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Type any keyword to search...', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'No results', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[bar_search_no_results]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'bar_search_no_results' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'No results found for "%s"', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Remove', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[bar_remove]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'bar_remove' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Remove', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Remove all', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[bar_remove_all]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'bar_remove_all' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Remove all', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Remove all confirmation', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[bar_remove_all_confirmation]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'bar_remove_all_confirmation' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Do you want to remove all products from the compare?', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Select fields', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[bar_select_fields]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'bar_select_fields' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Select fields', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Select fields description', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[bar_select_fields_desc]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'bar_select_fields_desc' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Select the fields to be shown. Others will be hidden. Drag and drop to rearrange the order.', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Click outside', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[bar_click_outside]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'bar_click_outside' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Click outside to hide the compare bar', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th scope="row"><?php esc_html_e( 'Menu', 'woo-smart-compare' ); ?></th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Menu item label', 'woo-smart-compare' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosc_localization[menu]"
                                                       value="<?php echo esc_attr( self::woosc_localization( 'menu' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Compare', 'woo-smart-compare' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
                                                <input type="submit" name="submit" class="button button-primary"
                                                       value="<?php esc_attr_e( 'Update Options', 'woo-smart-compare' ); ?>"/>
                                                <input type="hidden" name="action" value="update"/>
                                                <input type="hidden" name="page_options" value="woosc_localization"/>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'premium' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>Get the Premium Version just $29! <a
                                                href="https://wpclever.net/downloads/woocommerce-smart-compare?utm_source=pro&utm_medium=woosc&utm_campaign=wporg"
                                                target="_blank">https://wpclever.net/downloads/woocommerce-smart-compare</a>
                                    </p>
                                    <p><strong>Extra features for Premium Version:</strong></p>
                                    <ul style="margin-bottom: 0">
                                        <li>- Support customization of all attributes.</li>
                                        <li>- Support customization of all product fields, custom fields.</li>
                                        <li>- Free support of compare button’s adjustment to customers’ theme design.
                                        </li>
                                        <li>- Get the lifetime update & premium support.</li>
                                    </ul>
                                </div>
							<?php } ?>
                        </div>
                    </div>
					<?php
				}

				function woosc_load_bar() {
					echo $this->woosc_get_bar();
					wp_die();
				}

				function woosc_get_bar() {
					// get items
					$woosc_bar      = '';
					$woosc_products = array();

					if ( isset( $_POST['products'] ) && ( $_POST['products'] !== '' ) ) {
						$woosc_products = explode( ',', $_POST['products'] );
					} else {
						$woosc_cookie = 'woosc_products_' . md5( 'woosc' . get_current_user_id() );

						if ( isset( $_COOKIE[ $woosc_cookie ] ) && ! empty( $_COOKIE[ $woosc_cookie ] ) ) {
							$woosc_products = explode( ',', $_COOKIE[ $woosc_cookie ] );
						}
					}

					if ( ! empty( $woosc_products ) ) {
						foreach ( $woosc_products as $woosc_product ) {
							$woosc_product_obj = wc_get_product( $woosc_product );

							if ( ! $woosc_product_obj ) {
								continue;
							}

							$woosc_product_id   = $woosc_product_obj->get_id();
							$woosc_product_name = apply_filters( 'woosc_product_name', $woosc_product_obj->get_name() );

							$woosc_bar .= '<div class="woosc-bar-item" data-id="' . $woosc_product_id . '">';
							$woosc_bar .= '<span class="woosc-bar-item-img hint--top" aria-label="' . esc_attr( apply_filters( 'woosc_product_name', wp_strip_all_tags( $woosc_product_name ), $woosc_product_obj ) ) . '">' . $woosc_product_obj->get_image( 'woosc-small' ) . '</span>';
							$woosc_bar .= '<span class="woosc-bar-item-remove hint--top" aria-label="' . esc_attr( self::woosc_localization( 'bar_remove', esc_html__( 'Remove', 'woo-smart-compare' ) ) ) . '" data-id="' . $woosc_product_id . '"></span></div>';
						}
					}

					return apply_filters( 'woosc_get_bar', $woosc_bar );
				}

				function woosc_load_table() {
					echo $this->woosc_get_table();
					wp_die();
				}

				function woosc_get_table( $ajax = true ) {
					// get items
					$woosc_table         = '';
					$woosc_products      = array();
					$woosc_products_data = array();

					if ( isset( $_POST['products'] ) && ( $_POST['products'] !== '' ) ) {
						$woosc_products = explode( ',', $_POST['products'] );
					} else {
						$woosc_cookie = 'woosc_products_' . md5( 'woosc' . get_current_user_id() );

						if ( isset( $_COOKIE[ $woosc_cookie ] ) && ! empty( $_COOKIE[ $woosc_cookie ] ) ) {
							if ( is_user_logged_in() ) {
								update_user_meta( get_current_user_id(), 'woosc_products', $_COOKIE[ $woosc_cookie ] );
							}

							$woosc_products = explode( ',', $_COOKIE[ $woosc_cookie ] );
						}
					}

					if ( is_array( $woosc_products ) && ( count( $woosc_products ) > 0 ) ) {
						$link = get_option( 'woosc_link', 'yes' );

						if ( is_array( get_option( 'woosc_fields' ) ) ) {
							$saved_fields = get_option( 'woosc_fields' );
						} else {
							$saved_fields = array_keys( self::$woosc_fields );
						}

						foreach ( $woosc_products as $woosc_product ) {
							$product        = wc_get_product( $woosc_product );
							$parent_product = false;

							if ( ! $product ) {
								continue;
							}

							$woosc_products_data[ $woosc_product ]['id'] = $woosc_product;

							$product_name = apply_filters( 'woosc_product_name', $product->get_name() );

							if ( $link !== 'no' ) {
								$woosc_products_data[ $woosc_product ]['name'] = apply_filters( 'woosc_product_name', '<a ' . ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . $woosc_product . '" data-context="woosc"' : '' ) . ' href="' . $product->get_permalink() . '" draggable="false" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . wp_strip_all_tags( $product_name ) . '</a>', $product );
							} else {
								$woosc_products_data[ $woosc_product ]['name'] = apply_filters( 'woosc_product_name', wp_strip_all_tags( $product_name ), $product );
							}

							if ( in_array( 'image', $saved_fields, true ) ) {
								if ( $link !== 'no' ) {
									$woosc_products_data[ $woosc_product ]['image'] = apply_filters( 'woosc_product_image', '<a ' . ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . $woosc_product . '" data-context="woosc"' : '' ) . ' href="' . $product->get_permalink() . '" draggable="false" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . $product->get_image( get_option( 'woosc_image_size', 'woosc-large' ), array( 'draggable' => 'false' ) ) . '</a>', $product );
								} else {
									$woosc_products_data[ $woosc_product ]['image'] = apply_filters( 'woosc_product_image', $product->get_image( get_option( 'woosc_image_size', 'woosc-large' ), array( 'draggable' => 'false' ) ), $product );
								}
							}

							if ( in_array( 'sku', $saved_fields, true ) ) {
								$woosc_products_data[ $woosc_product ]['sku'] = apply_filters( 'woosc_product_sku', $product->get_sku(), $product );
							}

							if ( in_array( 'price', $saved_fields, true ) ) {
								$woosc_products_data[ $woosc_product ]['price'] = apply_filters( 'woosc_product_price', $product->get_price_html(), $product );
							}

							if ( in_array( 'stock', $saved_fields, true ) ) {
								$woosc_products_data[ $woosc_product ]['stock'] = apply_filters( 'woosc_product_stock', wc_get_stock_html( $product ), $product );
							}

							if ( in_array( 'add_to_cart', $saved_fields, true ) ) {
								$woosc_products_data[ $woosc_product ]['add_to_cart'] = apply_filters( 'woosc_product_add_to_cart', do_shortcode( '[add_to_cart style="" show_price="false" id="' . $woosc_product . '"]' ), $product );
							}

							if ( in_array( 'description', $saved_fields, true ) ) {
								if ( $product->is_type( 'variation' ) ) {
									$woosc_products_data[ $woosc_product ]['description'] = apply_filters( 'woosc_product_description', $product->get_description(), $product );
								} else {
									$woosc_products_data[ $woosc_product ]['description'] = apply_filters( 'woosc_product_description', $product->get_short_description(), $product );
								}
							}

							if ( in_array( 'content', $saved_fields, true ) ) {
								$woosc_products_data[ $woosc_product ]['content'] = apply_filters( 'woosc_product_content', do_shortcode( $product->get_description() ), $product );
							}

							if ( in_array( 'additional', $saved_fields, true ) ) {
								ob_start();
								wc_display_product_attributes( $product );
								$additional = ob_get_clean();

								$woosc_products_data[ $woosc_product ]['additional'] = apply_filters( 'woosc_product_additional', $additional, $product );
							}

							if ( in_array( 'weight', $saved_fields, true ) ) {
								$woosc_products_data[ $woosc_product ]['weight'] = apply_filters( 'woosc_product_weight', $product->get_weight(), $product );
							}

							if ( in_array( 'dimensions', $saved_fields, true ) ) {
								$woosc_products_data[ $woosc_product ]['dimensions'] = apply_filters( 'woosc_product_dimensions', wc_format_dimensions( $product->get_dimensions( false ) ), $product );
							}

							if ( in_array( 'rating', $saved_fields, true ) ) {
								$woosc_products_data[ $woosc_product ]['rating'] = apply_filters( 'woosc_product_rating', wc_get_rating_html( $product->get_average_rating() ), $product );
							}

							if ( in_array( 'availability', $saved_fields, true ) ) {
								$product_availability                                  = $product->get_availability();
								$woosc_products_data[ $woosc_product ]['availability'] = apply_filters( 'woosc_product_availability', $product_availability['availability'], $product );
							}
						}

						$product_count     = count( $woosc_products_data );
						$woosc_table_class = 'woosc_table has-' . $product_count;
						$minimum_columns   = intval( apply_filters( 'woosc_get_table_minimum_columns', 3, $woosc_products_data ) );

						if ( $minimum_columns > $product_count ) {
							for ( $i = 1; $i <= ( $minimum_columns - $product_count ); $i ++ ) {
								$woosc_products_data[ 'p' . $i ]['name'] = '';
							}
						}

						$woosc_table .= '<table ' . ( $ajax ? 'id="woosc_table"' : '' ) . ' class="' . esc_attr( $woosc_table_class ) . '"><thead><tr><th></th>';

						foreach ( $woosc_products_data as $woosc_product ) {
							if ( $woosc_product['name'] !== '' ) {
								$woosc_table .= '<th>' . $woosc_product['name'] . '</th>';
							} else {
								$woosc_table .= '<th class="th-placeholder"></th>';
							}
						}

						$woosc_table .= '</tr></thead><tbody>';

						$cookie_fields = $this->woosc_get_cookie_fields( $saved_fields );
						$saved_fields  = array_unique( array_merge( $cookie_fields, $saved_fields ), SORT_REGULAR );

						foreach ( $saved_fields as $saved_field ) {
							if ( ! isset( self::$woosc_fields[ $saved_field ] ) ) {
								continue;
							}

							$woosc_field = '';

							if ( ! in_array( $saved_field, array(
								'attributes',
								'custom_attributes',
								'custom_fields'
							) ) ) {
								$woosc_field .= '<tr class="tr-default tr-' . esc_attr( $saved_field ) . ' ' . ( ! in_array( $saved_field, $cookie_fields, false ) ? 'tr-hide' : '' ) . '"><td class="td-label">' . esc_html( self::$woosc_fields[ $saved_field ] ) . '</td>';

								foreach ( $woosc_products_data as $woosc_product ) {
									if ( $woosc_product['name'] !== '' ) {
										if ( isset( $woosc_product[ $saved_field ] ) ) {
											$woosc_field_value = $woosc_product[ $saved_field ];
										} else {
											$woosc_field_value = '';
										}

										$woosc_field .= '<td>' . apply_filters( 'woosc_field_value', $woosc_field_value, $saved_field, $woosc_product ) . '</td>';
									} else {
										$woosc_field .= '<td class="td-placeholder"></td>';
									}
								}

								$woosc_field .= '</tr>';
							}

							if ( ! empty( $woosc_field ) ) {
								$woosc_table .= $woosc_field;
							}
						}

						$woosc_table .= '</tbody></table>';
					} else {
						$woosc_table = '<div class="woosc-no-result">' . self::woosc_localization( 'table_empty', esc_html__( 'No product is added to the compare table.', 'woo-smart-compare' ) ) . '</div>';
					}

					return apply_filters( 'woosc_get_table', $woosc_table );
				}

				function woosc_search() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woosc-nonce' ) ) {
						die( 'Permissions check failed' );
					}

					$keyword       = sanitize_text_field( $_POST['keyword'] );
					$selected_cats = get_option( 'woosc_search_cats' );

					if ( empty( $selected_cats ) ) {
						$selected_cats = array( '0' );
					}

					$woosc_query_args = array(
						's'              => $keyword,
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => get_option( 'woosc_search_count', 10 )
					);

					if ( is_array( $selected_cats ) && ( count( $selected_cats ) > 0 ) && ( $selected_cats[0] !== '0' ) ) {
						$woosc_query_args['tax_query'] = array(
							array(
								'taxonomy' => 'product_cat',
								'field'    => 'term_id',
								'terms'    => $selected_cats,
							),
						);
					}

					$woosc_query = new WP_Query( $woosc_query_args );

					if ( $woosc_query->have_posts() ) {
						echo '<ul>';

						while ( $woosc_query->have_posts() ) {
							$woosc_query->the_post();
							echo '<li>';
							echo '<div class="item-inner">';
							echo '<div class="item-image">' . get_the_post_thumbnail( get_the_ID(), 'woosc-small' ) . '</div>';
							echo '<div class="item-name">' . get_the_title() . '</div>';
							echo '<div class="item-add woosc-item-add" data-id="' . get_the_ID() . '"><span>+</span></div>';
							echo '</div>';
							echo '</li>';
						}

						echo '</ul>';
						wp_reset_postdata();
					} else {
						echo '<ul><span>' . sprintf( self::woosc_localization( 'bar_search_no_results', esc_html__( 'No results found for "%s"', 'woo-smart-compare' ) ), $keyword ) . '</span></ul>';
					}

					wp_die();
				}

				function woosc_add_button() {
					echo do_shortcode( '[woosc]' );
				}

				function woosc_shortcode( $atts ) {
					$output = '';

					$atts = shortcode_atts( array(
						'id'   => null,
						'type' => get_option( 'woosc_button_type', 'button' )
					), $atts );

					if ( ! $atts['id'] ) {
						global $product;

						if ( $product ) {
							$atts['id'] = $product->get_id();
						}
					}

					if ( $atts['id'] ) {
						// check cats
						$selected_cats = get_option( 'woosc_search_cats' );

						if ( ! empty( $selected_cats ) && ( $selected_cats[0] !== '0' ) ) {
							if ( ! has_term( $selected_cats, 'product_cat', $atts['id'] ) ) {
								return '';
							}
						}

						// button text
						$button_text = self::woosc_localization( 'button', esc_html__( 'Compare', 'woo-smart-compare' ) );

						if ( $atts['type'] === 'link' ) {
							$output = '<a href="#" class="woosc-btn woosc-btn-' . esc_attr( $atts['id'] ) . ' ' . get_option( 'woosc_button_class' ) . '" data-id="' . esc_attr( $atts['id'] ) . '">' . esc_html( $button_text ) . '</a>';
						} else {
							$output = '<button class="woosc-btn woosc-btn-' . esc_attr( $atts['id'] ) . ' ' . get_option( 'woosc_button_class' ) . '" data-id="' . esc_attr( $atts['id'] ) . '">' . esc_html( $button_text ) . '</button>';
						}
					}

					return apply_filters( 'woosc_button_html', $output, $atts['id'] );
				}

				function woosc_shortcode_list( $atts ) {
					return '<div class="woosc_list woosc_page">' . $this->woosc_get_table( false ) . '</div>';
				}

				function woosc_wp_footer() {
					if ( is_admin() ) {
						return;
					}

					$woosc_class = 'woosc-area';
					$woosc_class .= ' woosc-bar-' . get_option( 'woosc_bar_pos', 'bottom' ) . ' woosc-bar-' . get_option( 'woosc_bar_align', 'right' ) . ' woosc-bar-click-outside-' . str_replace( '_', '-', get_option( 'woosc_click_outside', 'yes' ) );

					if ( get_option( 'woosc_hide_checkout', 'yes' ) === 'yes' ) {
						$woosc_class .= ' woosc-hide-checkout';
					}

					$woosc_bar_bg_color_default  = apply_filters( 'woosc_bar_bg_color_default', '#292a30' );
					$woosc_bar_btn_color_default = apply_filters( 'woosc_bar_btn_color_default', '#00a0d2' );

					if ( get_option( 'woosc_bar_add', 'yes' ) === 'yes' ) { ?>
                        <div class="woosc-popup woosc-search">
                            <div class="woosc-popup-inner">
                                <div class="woosc-popup-content">
                                    <div class="woosc-popup-content-inner">
                                        <div class="woosc-popup-close"></div>
                                        <div class="woosc-search-input">
                                            <input type="search" id="woosc_search_input"
                                                   placeholder="<?php echo esc_attr( self::woosc_localization( 'bar_search_placeholder', esc_html__( 'Type any keyword to search...', 'woo-smart-compare' ) ) ); ?>"/>
                                        </div>
                                        <div class="woosc-search-result"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
					<?php }

					if ( get_option( 'woosc_bar_settings', 'yes' ) === 'yes' ) { ?>
                        <div class="woosc-popup woosc-settings">
                            <div class="woosc-popup-inner">
                                <div class="woosc-popup-content">
                                    <div class="woosc-popup-content-inner">
                                        <div class="woosc-popup-close"></div>
										<?php echo self::woosc_localization( 'bar_select_fields_desc', esc_html__( 'Select the fields to be shown. Others will be hidden. Drag and drop to rearrange the order.', 'woo-smart-compare' ) ); ?>
                                        <ul class="woosc-settings-fields">
											<?php
											if ( is_array( get_option( 'woosc_fields' ) ) ) {
												$saved_fields = get_option( 'woosc_fields' );
											} else {
												$saved_fields = array_keys( self::$woosc_fields );
											}

											$cookie_fields = $this->woosc_get_cookie_fields( $saved_fields );
											$fields_merge  = array_unique( array_merge( $cookie_fields, $saved_fields ), SORT_REGULAR );

											foreach ( $fields_merge as $field ) {
												if ( isset( self::$woosc_fields[ $field ] ) ) {
													echo '<li class="woosc-settings-field-li"><input type="checkbox" class="woosc-settings-field" value="' . $field . '" ' . ( in_array( $field, $cookie_fields, false ) ? 'checked' : '' ) . '/><span class="label">' . self::$woosc_fields[ $field ] . '</span></li>';
												}
											}
											?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
					<?php } ?>

                    <div id="woosc-area" class="<?php echo esc_attr( $woosc_class ); ?>"
                         data-bg-color="<?php echo apply_filters( 'woosc_bar_bg_color', get_option( 'woosc_bar_bg_color', $woosc_bar_bg_color_default ) ); ?>"
                         data-btn-color="<?php echo apply_filters( 'woosc_bar_btn_color', get_option( 'woosc_bar_btn_color', $woosc_bar_btn_color_default ) ); ?>">
                        <div class="woosc-inner">
                            <div class="woosc-table">
                                <div class="woosc-table-inner">
									<?php if ( 'yes' === get_option( 'woosc_close_button', 'yes' ) ) { ?>
                                        <a href="javascript:void(0);" id="woosc-table-close"
                                           class="woosc-table-close hint--left"
                                           aria-label="<?php echo esc_attr( self::woosc_localization( 'table_close', esc_html__( 'Close', 'woo-smart-compare' ) ) ); ?>"><span
                                                    class="woosc-table-close-icon"></span></a>
									<?php } ?>
                                    <div class="woosc-table-items"></div>
                                </div>
                            </div>
                            <div class="woosc-bar <?php echo esc_attr( get_option( 'woosc_bar_bubble', 'no' ) === 'yes' ? 'woosc-bar-bubble' : '' ); ?>">
								<?php if ( get_option( 'woosc_click_outside', 'yes' ) !== 'no' && get_option( 'woosc_bar_bubble', 'no' ) !== 'yes' ) { ?>
                                    <div class="woosc-bar-notice">
										<?php echo self::woosc_localization( 'bar_click_outside', esc_html__( 'Click outside to hide the compare bar', 'woo-smart-compare' ) ); ?>
                                    </div>
								<?php }

								if ( get_option( 'woosc_bar_settings', 'yes' ) === 'yes' ) { ?>
                                    <a href="javascript:void(0);" class="woosc-bar-settings hint--top"
                                       aria-label="<?php echo esc_attr( self::woosc_localization( 'bar_select_fields', esc_html__( 'Select fields', 'woo-smart-compare' ) ) ); ?>"></a>
								<?php }

								if ( get_option( 'woosc_bar_add', 'yes' ) === 'yes' ) { ?>
                                    <a href="javascript:void(0);" class="woosc-bar-search hint--top"
                                       aria-label="<?php echo esc_attr( self::woosc_localization( 'bar_add', esc_html__( 'Add product', 'woo-smart-compare' ) ) ); ?>"></a>
								<?php }

								echo '<div class="woosc-bar-items"></div>';

								if ( get_option( 'woosc_bar_remove', 'no' ) === 'yes' ) { ?>
                                    <div class="woosc-bar-remove hint--top"
                                         aria-label="<?php echo esc_attr( self::woosc_localization( 'bar_remove_all', esc_html__( 'Remove all', 'woo-smart-compare' ) ) ); ?>"></div>
								<?php } ?>

                                <div class="woosc-bar-btn woosc-bar-btn-text">
                                    <div class="woosc-bar-btn-icon-wrapper">
                                        <div class="woosc-bar-btn-icon-inner"><span></span><span></span><span></span>
                                        </div>
                                    </div>
									<?php echo apply_filters( 'woosc_bar_btn_text', self::woosc_localization( 'bar_button', esc_html__( 'Compare', 'woo-smart-compare' ) ) ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function woosc_get_cookie_fields( $saved_fields ) {
					$cookie_fields = 'woosc_fields_' . md5( 'woosc' . get_current_user_id() );

					if ( isset( $_COOKIE[ $cookie_fields ] ) && ! empty( $_COOKIE[ $cookie_fields ] ) ) {
						$woosc_fields = explode( ',', $_COOKIE[ $cookie_fields ] );
					} else {
						$woosc_fields = $saved_fields;
					}

					return $woosc_fields;
				}

				public static function woosc_get_count() {
					$woosc_products = array();

					if ( isset( $_POST['products'] ) && ( $_POST['products'] !== '' ) ) {
						$woosc_products = explode( ',', $_POST['products'] );
					} else {
						$woosc_cookie = 'woosc_products_' . md5( 'woosc' . get_current_user_id() );

						if ( isset( $_COOKIE[ $woosc_cookie ] ) && ! empty( $_COOKIE[ $woosc_cookie ] ) ) {
							$woosc_products = explode( ',', $_COOKIE[ $woosc_cookie ] );
						}
					}

					return count( $woosc_products );
				}

				public static function woosc_get_page_url() {
					$page_id  = get_option( 'woosc_page_id' );
					$page_url = ! empty( $page_id ) ? get_permalink( $page_id ) : '#';

					return esc_url( $page_url );
				}

				function woosc_nav_menu_items( $items, $args ) {
					$selected    = false;
					$saved_menus = get_option( 'woosc_menus', array() );

					if ( ! is_array( $saved_menus ) || empty( $saved_menus ) || ! property_exists( $args, 'menu' ) ) {
						return $items;
					}

					if ( $args->menu instanceof WP_Term ) {
						// menu object
						if ( in_array( $args->menu->term_id, $saved_menus, false ) ) {
							$selected = true;
						}
					} elseif ( is_numeric( $args->menu ) ) {
						// menu id
						if ( in_array( $args->menu, $saved_menus, false ) ) {
							$selected = true;
						}
					} elseif ( is_string( $args->menu ) ) {
						// menu slug or name
						$menu = get_term_by( 'name', $args->menu, 'nav_menu' );

						if ( ! $menu ) {
							$menu = get_term_by( 'slug', $args->menu, 'nav_menu' );
						}

						if ( $menu && in_array( $menu->term_id, $saved_menus, false ) ) {
							$selected = true;
						}
					}

					if ( $selected ) {
						$menu_item = '<li class="' . apply_filters( 'woosc_menu_item_class', 'menu-item woosc-menu-item menu-item-type-woosc' ) . '"><a href="' . self::woosc_get_page_url() . '"><span class="woosc-menu-item-inner" data-count="' . $this->woosc_get_count() . '">' . apply_filters( 'woosc_menu_item_label', self::woosc_localization( 'menu', esc_html__( 'Compare', 'woo-smart-compare' ) ) ) . '</span></a></li>';
						$items     .= apply_filters( 'woosc_menu_item', $menu_item );
					}

					return $items;
				}

				function woosc_get_image_sizes() {
					global $_wp_additional_image_sizes;
					$sizes = array();

					foreach ( get_intermediate_image_sizes() as $_size ) {
						if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
							$sizes[ $_size ]['width']  = get_option( "{$_size}_size_w" );
							$sizes[ $_size ]['height'] = get_option( "{$_size}_size_h" );
							$sizes[ $_size ]['crop']   = (bool) get_option( "{$_size}_crop" );
						} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
							$sizes[ $_size ] = array(
								'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
								'height' => $_wp_additional_image_sizes[ $_size ]['height'],
								'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
							);
						}
					}

					return $sizes;
				}

				function woosc_nice_class_id( $str ) {
					return preg_replace( '/[^a-zA-Z0-9#._-]/', '', $str );
				}
			}

			new WPCleverWoosc();
		}
	}
} else {
	add_action( 'admin_notices', 'woosc_notice_premium' );
}

if ( ! function_exists( 'woosc_notice_wc' ) ) {
	function woosc_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Smart Compare</strong> require WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}

if ( ! function_exists( 'woosc_notice_premium' ) ) {
	function woosc_notice_premium() {
		?>
        <div class="error">
            <p>Seems you're using both free and premium version of <strong>WPC Smart Compare</strong>. Please
                deactivate the free version when using the premium version.</p>
        </div>
		<?php
	}
}
