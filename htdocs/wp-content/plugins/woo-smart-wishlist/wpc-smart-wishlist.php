<?php
/*
Plugin Name: WPC Smart Wishlist for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Smart Wishlist is a simple but powerful tool that can help your customer save products for buy later.
Version: 2.8.8
Author: WPClever
Author URI: https://wpclever.net
Text Domain: woo-smart-wishlist
Domain Path: /languages/
Requires at least: 4.0
Tested up to: 5.8
WC requires at least: 3.0
WC tested up to: 5.8
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WOOSW_VERSION' ) && define( 'WOOSW_VERSION', '2.8.8' );
! defined( 'WOOSW_URI' ) && define( 'WOOSW_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOSW_REVIEWS' ) && define( 'WOOSW_REVIEWS', 'https://wordpress.org/support/plugin/woo-smart-wishlist/reviews/?filter=5' );
! defined( 'WOOSW_CHANGELOG' ) && define( 'WOOSW_CHANGELOG', 'https://wordpress.org/plugins/woo-smart-wishlist/#developers' );
! defined( 'WOOSW_DISCUSSION' ) && define( 'WOOSW_DISCUSSION', 'https://wordpress.org/support/plugin/woo-smart-wishlist' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WOOSW_URI );

include 'includes/wpc-dashboard.php';
include 'includes/wpc-menu.php';
include 'includes/wpc-kit.php';
include 'includes/wpc-notice.php';

// plugin activate
register_activation_hook( __FILE__, 'woosw_plugin_activate' );

// plugin init
if ( ! function_exists( 'woosw_init' ) ) {
	add_action( 'plugins_loaded', 'woosw_init', 11 );

	function woosw_init() {
		// load text-domain
		load_plugin_textdomain( 'woo-smart-wishlist', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'woosw_notice_wc' );

			return;
		}

		if ( ! class_exists( 'WPCleverWoosw' ) ) {
			class WPCleverWoosw {
				protected static $added_products = array();
				protected static $localization = array();

				function __construct() {
					// add query var
					add_filter( 'query_vars', array( $this, 'query_vars' ), 1 );

					add_action( 'init', array( $this, 'init' ) );

					// menu
					add_action( 'admin_menu', array( $this, 'admin_menu' ) );

					// frontend scripts
					add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

					// backend scripts
					add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

					// quickview
					add_action( 'wp_ajax_wishlist_quickview', array( $this, 'wishlist_quickview' ) );

					// add
					add_action( 'wp_ajax_wishlist_add', array( $this, 'wishlist_add' ) );
					add_action( 'wp_ajax_nopriv_wishlist_add', array( $this, 'wishlist_add' ) );

					// added to cart
					if ( get_option( 'woosw_auto_remove', 'no' ) === 'yes' ) {
						add_action( 'woocommerce_add_to_cart', array( $this, 'add_to_cart' ), 10, 2 );
					}

					// remove
					add_action( 'wp_ajax_wishlist_remove', array( $this, 'wishlist_remove' ) );
					add_action( 'wp_ajax_nopriv_wishlist_remove', array( $this, 'wishlist_remove' ) );

					// empty
					add_action( 'wp_ajax_wishlist_empty', array( $this, 'wishlist_empty' ) );
					add_action( 'wp_ajax_nopriv_wishlist_empty', array( $this, 'wishlist_empty' ) );

					// load
					add_action( 'wp_ajax_wishlist_load', array( $this, 'wishlist_load' ) );
					add_action( 'wp_ajax_nopriv_wishlist_load', array( $this, 'wishlist_load' ) );

					// load count
					add_action( 'wp_ajax_wishlist_load_count', array( $this, 'wishlist_load_count' ) );
					add_action( 'wp_ajax_nopriv_wishlist_load_count', array( $this, 'wishlist_load_count' ) );

					// link
					add_filter( 'plugin_action_links', array( $this, 'action_links' ), 10, 2 );
					add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );

					// menu items
					add_filter( 'wp_nav_menu_items', array( $this, 'woosw_nav_menu_items' ), 99, 2 );

					// footer
					add_action( 'wp_footer', array( $this, 'wp_footer' ) );

					// product columns
					add_filter( 'manage_edit-product_columns', array( $this, 'woosw_product_columns' ), 10 );
					add_action( 'manage_product_posts_custom_column', array(
						$this,
						'woosw_product_posts_custom_column'
					), 10, 2 );
					add_filter( 'manage_edit-product_sortable_columns', array(
						$this,
						'woosw_product_sortable_columns'
					) );
					add_filter( 'request', array( $this, 'woosw_product_request' ) );

					// user login & logout
					add_action( 'wp_login', array( $this, 'woosw_wp_login' ), 10, 2 );
					add_action( 'wp_logout', array( $this, 'woosw_wp_logout' ), 10, 1 );

					// user columns
					add_filter( 'manage_users_columns', array( $this, 'woosw_user_table' ) );
					add_filter( 'manage_users_custom_column', array( $this, 'woosw_user_table_row' ), 10, 3 );

					// dropdown multiple
					add_filter( 'wp_dropdown_cats', array( $this, 'dropdown_cats_multiple' ), 10, 2 );
				}

				function query_vars( $vars ) {
					$vars[] = 'woosw_id';

					return $vars;
				}

				function init() {
					// localization
					self::$localization = (array) get_option( 'woosw_localization' );

					// added products
					$key = isset( $_COOKIE['woosw_key'] ) ? $_COOKIE['woosw_key'] : '#';

					if ( get_option( 'woosw_list_' . $key ) ) {
						self::$added_products = get_option( 'woosw_list_' . $key );
					}

					// rewrite
					if ( $page_id = self::get_page_id() ) {
						$page_slug = get_post_field( 'post_name', $page_id );

						if ( $page_slug !== '' ) {
							add_rewrite_rule( '^' . $page_slug . '/([\w]+)/?', 'index.php?page_id=' . $page_id . '&woosw_id=$matches[1]', 'top' );
						}
					}

					// shortcode
					add_shortcode( 'woosw', array( $this, 'shortcode' ) );
					add_shortcode( 'woosw_list', array( $this, 'list_shortcode' ) );

					// add button for archive
					$button_position_archive = apply_filters( 'woosw_button_position_archive', get_option( 'woosw_button_position_archive', 'after_add_to_cart' ) );

					switch ( $button_position_archive ) {
						case 'after_title':
							add_action( 'woocommerce_shop_loop_item_title', array( $this, 'add_button' ), 11 );
							break;
						case 'after_rating':
							add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'add_button' ), 6 );
							break;
						case 'after_price':
							add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'add_button' ), 11 );
							break;
						case 'before_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', array( $this, 'add_button' ), 9 );
							break;
						case 'after_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', array( $this, 'add_button' ), 11 );
							break;
					}

					// add button for single
					$button_position_single = apply_filters( 'woosw_button_position_single', get_option( 'woosw_button_position_single', '31' ) );

					if ( ! empty( $button_position_single ) ) {
						add_action( 'woocommerce_single_product_summary', array(
							$this,
							'add_button'
						), (int) $button_position_single );
					}
				}

				function localization( $key = '', $default = '' ) {
					$str = '';

					if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
						$str = self::$localization[ $key ];
					} elseif ( ! empty( $default ) ) {
						$str = $default;
					}

					return apply_filters( 'woosw_localization_' . $key, $str );
				}

				function add_to_cart( $cart_item_key, $product_id ) {
					$key = self::get_key();

					if ( $key !== '#' ) {
						$products = array();

						if ( get_option( 'woosw_list_' . $key ) ) {
							$products = get_option( 'woosw_list_' . $key );
						}

						if ( array_key_exists( $product_id, $products ) ) {
							unset( $products[ $product_id ] );
							update_option( 'woosw_list_' . $key, $products );
							$this->update_product_count( $product_id, 'remove' );
						}
					}
				}

				function wishlist_add() {
					$return = array( 'status' => 0 );
					$key    = self::get_key();

					if ( ( $product_id = absint( $_POST['product_id'] ) ) > 0 ) {
						if ( $key === '#' ) {
							$return['status'] = 0;
							$return['notice'] = self::localization( 'login_message', esc_html__( 'Please log in to use the wishlist!', 'woo-smart-wishlist' ) );
							$return['value']  = '<div class="woosw-content-mid-notice">' . self::localization( 'empty_message', esc_html__( 'There are no products on the wishlist!', 'woo-smart-wishlist' ) ) . '</div>';
							$return['image']  = WOOSW_URI . 'assets/images/heart_error.svg';
						} else {
							$products = array();

							if ( get_option( 'woosw_list_' . $key ) ) {
								$products = get_option( 'woosw_list_' . $key );
							}

							if ( ! array_key_exists( $product_id, $products ) ) {
								// insert if not exists
								$products = array(
									            $product_id => array(
										            'time' => time(),
										            'note' => ''
									            )
								            ) + $products;
								update_option( 'woosw_list_' . $key, $products );
								$this->update_product_count( $product_id, 'add' );
								$return['notice'] = self::localization( 'added_message', esc_html__( 'Added to the wishlist!', 'woo-smart-wishlist' ) );
								$return['image']  = WOOSW_URI . 'assets/images/heart_add.svg';
							} else {
								$return['notice'] = self::localization( 'already_message', esc_html__( 'Already in the wishlist!', 'woo-smart-wishlist' ) );
								$return['image']  = WOOSW_URI . 'assets/images/heart_duplicate.svg';
							}

							$return['status'] = 1;
							$return['count']  = count( $products );

							if ( get_option( 'woosw_button_action', 'list' ) === 'list' ) {
								$return['value'] = $this->get_items( $key );
							}
						}
					} else {
						$product_id       = 0;
						$return['status'] = 0;
						$return['notice'] = self::localization( 'error_message', esc_html__( 'Have an error, please try again!', 'woo-smart-wishlist' ) );
						$return['image']  = WOOSW_URI . 'assets/images/heart_error.svg';
					}

					do_action( 'woosw_add', $product_id, $key );

					echo json_encode( $return );
					die();
				}

				function wishlist_remove() {
					$return = array( 'status' => 0 );
					$key    = self::get_key();

					if ( ( $product_id = absint( $_POST['product_id'] ) ) > 0 ) {
						if ( $key === '#' ) {
							$return['notice'] = self::localization( 'login_message', esc_html__( 'Please log in to use the wishlist!', 'woo-smart-wishlist' ) );
						} else {
							$products = array();

							if ( get_option( 'woosw_list_' . $key ) ) {
								$products = get_option( 'woosw_list_' . $key );
							}

							if ( array_key_exists( $product_id, $products ) ) {
								unset( $products[ $product_id ] );
								update_option( 'woosw_list_' . $key, $products );
								$this->update_product_count( $product_id, 'remove' );
								$return['count']  = count( $products );
								$return['status'] = 1;
								$return['notice'] = self::localization( 'removed_message', esc_html__( 'Removed from wishlist!', 'woo-smart-wishlist' ) );

								if ( empty( $products ) ) {
									$return['value'] = '<div class="woosw-content-mid-notice">' . self::localization( 'empty_message', esc_html__( 'There are no products on the wishlist!', 'woo-smart-wishlist' ) ) . '</div>';
								}
							} else {
								$return['notice'] = self::localization( 'not_exist_message', esc_html__( 'The product does not exist on the wishlist!', 'woo-smart-wishlist' ) );
							}
						}
					} else {
						$product_id       = 0;
						$return['notice'] = self::localization( 'error_message', esc_html__( 'Have an error, please try again!', 'woo-smart-wishlist' ) );
					}

					do_action( 'woosw_remove', $product_id, $key );

					echo json_encode( $return );
					die();
				}

				function wishlist_empty() {
					$return = array( 'status' => 0 );
					$key    = self::get_key();

					if ( $key === '#' ) {
						$return['notice'] = self::localization( 'login_message', esc_html__( 'Please log in to use the wishlist!', 'woo-smart-wishlist' ) );
					} else {
						if ( get_option( 'woosw_list_' . $key ) ) {
							$products = get_option( 'woosw_list_' . $key );

							if ( ! empty( $products ) ) {
								foreach ( array_keys( $products ) as $product_id ) {
									// update count
									$this->update_product_count( $product_id, 'remove' );
								}
							}
						}

						// remove option
						delete_option( 'woosw_list_' . $key );
						$return['status'] = 1;
						$return['count']  = 0;
						$return['notice'] = self::localization( 'empty_notice', esc_html__( 'All products were removed from your wishlist!', 'woo-smart-wishlist' ) );
						$return['value']  = '<div class="woosw-content-mid-notice">' . self::localization( 'empty_message', esc_html__( 'There are no products on the wishlist!', 'woo-smart-wishlist' ) ) . '</div>';
					}

					do_action( 'woosw_empty', $key );

					echo json_encode( $return );
					die();
				}

				function wishlist_load() {
					$return = array( 'status' => 0 );
					$key    = self::get_key();

					if ( $key === '#' ) {
						$return['notice'] = self::localization( 'login_message', esc_html__( 'Please log in to use wishlist!', 'woo-smart-wishlist' ) );
					} else {
						$products = array();

						if ( get_option( 'woosw_list_' . $key ) ) {
							$products = get_option( 'woosw_list_' . $key );
						}

						$return['status'] = 1;
						$return['count']  = count( $products );
						$return['value']  = $this->get_items( $key );
					}

					do_action( 'woosw_load', $key );

					echo json_encode( $return );
					die();
				}

				function wishlist_load_count() {
					$return = array( 'status' => 0, 'count' => 0 );
					$key    = self::get_key();

					if ( $key === '#' ) {
						$return['notice'] = self::localization( 'login_message', esc_html__( 'Please log in to use wishlist!', 'woo-smart-wishlist' ) );
					} else {
						$products = array();

						if ( get_option( 'woosw_list_' . $key ) ) {
							$products = get_option( 'woosw_list_' . $key );
						}

						$return['status'] = 1;
						$return['count']  = count( $products );
					}

					do_action( 'wishlist_load_count', $key );

					echo json_encode( $return );
					die();
				}

				function add_button() {
					echo do_shortcode( '[woosw]' );
				}

				function shortcode( $atts ) {
					$output = '';

					$atts = shortcode_atts( array(
						'id'   => null,
						'type' => get_option( 'woosw_button_type', 'button' )
					), $atts, 'woosw' );

					if ( ! $atts['id'] ) {
						global $product;

						if ( $product ) {
							$atts['id'] = $product->get_id();
						}
					}

					if ( $atts['id'] ) {
						// check cats
						$selected_cats = get_option( 'woosw_cats', array() );

						if ( ! empty( $selected_cats ) && ( $selected_cats[0] !== '0' ) ) {
							if ( ! has_term( $selected_cats, 'product_cat', $atts['id'] ) ) {
								return '';
							}
						}

						$class = 'woosw-btn woosw-btn-' . esc_attr( $atts['id'] );

						if ( array_key_exists( $atts['id'], self::$added_products ) ) {
							$class .= ' woosw-added';
							$text  = apply_filters( 'woosw_button_text_added', self::localization( 'button_added', esc_html__( 'Browse wishlist', 'woo-smart-wishlist' ) ) );
						} else {
							$text = apply_filters( 'woosw_button_text', self::localization( 'button', esc_html__( 'Add to wishlist', 'woo-smart-wishlist' ) ) );
						}

						if ( get_option( 'woosw_button_class', '' ) !== '' ) {
							$class .= ' ' . esc_attr( get_option( 'woosw_button_class' ) );
						}

						if ( $atts['type'] === 'link' ) {
							$output = '<a href="#" class="' . esc_attr( $class ) . '" data-id="' . esc_attr( $atts['id'] ) . '">' . esc_html( $text ) . '</a>';
						} else {
							$output = '<button class="' . esc_attr( $class ) . '" data-id="' . esc_attr( $atts['id'] ) . '">' . esc_html( $text ) . '</button>';
						}
					}

					return apply_filters( 'woosw_button_html', $output, $atts['id'] );
				}

				function list_shortcode() {
					if ( get_query_var( 'woosw_id' ) ) {
						$key = get_query_var( 'woosw_id' );
					} else {
						$key = self::get_key();
					}

					$share_url_raw = self::get_url( $key, true );
					$share_url     = urlencode( $share_url_raw );
					$return_html   = '<div class="woosw-list">';
					$return_html   .= $this->get_items( $key );
					$return_html   .= '<div class="woosw-actions">';

					if ( get_option( 'woosw_page_share', 'yes' ) === 'yes' ) {
						$facebook  = esc_html__( 'Facebook', 'woo-smart-wishlist' );
						$twitter   = esc_html__( 'Twitter', 'woo-smart-wishlist' );
						$pinterest = esc_html__( 'Pinterest', 'woo-smart-wishlist' );
						$mail      = esc_html__( 'Mail', 'woo-smart-wishlist' );

						if ( get_option( 'woosw_page_icon', 'yes' ) === 'yes' ) {
							$facebook = $twitter = $pinterest = $mail = "<i class='woosw-icon'></i>";
						}

						$woosw_page_items = get_option( 'woosw_page_items' );

						if ( ! empty( $woosw_page_items ) ) {
							$return_html .= '<div class="woosw-share">';
							$return_html .= '<span class="woosw-share-label">' . esc_html__( 'Share on:', 'woo-smart-wishlist' ) . '</span>';
							$return_html .= ( in_array( "facebook", $woosw_page_items ) ) ? '<a class="woosw-share-facebook" href="https://www.facebook.com/sharer.php?u=' . $share_url . '" target="_blank">' . $facebook . '</a>' : '';
							$return_html .= ( in_array( "twitter", $woosw_page_items ) ) ? '<a class="woosw-share-twitter" href="https://twitter.com/share?url=' . $share_url . '" target="_blank">' . $twitter . '</a>' : '';
							$return_html .= ( in_array( "pinterest", $woosw_page_items ) ) ? '<a class="woosw-share-pinterest" href="https://pinterest.com/pin/create/button/?url=' . $share_url . '" target="_blank">' . $pinterest . '</a>' : '';
							$return_html .= ( in_array( "mail", $woosw_page_items ) ) ? '<a class="woosw-share-mail" href="mailto:?body=' . $share_url . '" target="_blank">' . $mail . '</a>' : '';
							$return_html .= '</div><!-- /woosw-share -->';
						}

					}

					if ( get_option( 'woosw_page_copy', 'yes' ) === 'yes' ) {
						$return_html .= '<div class="woosw-copy">';
						$return_html .= '<span class="woosw-copy-label">' . esc_html__( 'Wishlist link:', 'woo-smart-wishlist' ) . '</span>';
						$return_html .= '<span class="woosw-copy-url"><input id="woosw_copy_url" type="url" value="' . $share_url_raw . '" readonly/></span>';
						$return_html .= '<span class="woosw-copy-btn"><input id="woosw_copy_btn" type="button" value="' . esc_html__( 'Copy', 'woo-smart-wishlist' ) . '"/></span>';
						$return_html .= '</div><!-- /woosw-copy -->';
					}

					$return_html .= '</div><!-- /woosw-actions -->';
					$return_html .= '</div><!-- /woosw-list -->';

					return $return_html;
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', 'WPC Smart Wishlist', 'Smart Wishlist', 'manage_options', 'wpclever-woosw', array(
						&$this,
						'admin_menu_content'
					) );
				}

				function admin_menu_content() {
					add_thickbox();
					$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo 'WPC Smart Wishlist ' . WOOSW_VERSION; ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'woo-smart-wishlist' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WOOSW_REVIEWS ); ?>"
                                   target="_blank"><?php esc_html_e( 'Reviews', 'woo-smart-wishlist' ); ?></a> | <a
                                        href="<?php echo esc_url( WOOSW_CHANGELOG ); ?>"
                                        target="_blank"><?php esc_html_e( 'Changelog', 'woo-smart-wishlist' ); ?></a>
                                | <a href="<?php echo esc_url( WOOSW_DISCUSSION ); ?>"
                                     target="_blank"><?php esc_html_e( 'Discussion', 'woo-smart-wishlist' ); ?></a>
                            </p>
                        </div>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosw&tab=settings' ); ?>"
                                   class="<?php echo $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'Settings', 'woo-smart-wishlist' ); ?>
                                </a>
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosw&tab=localization' ); ?>"
                                   class="<?php echo $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'Localization', 'woo-smart-wishlist' ); ?>
                                </a>
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosw&tab=premium' ); ?>"
                                   class="<?php echo $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>"
                                   style="color: #c9356e">
									<?php esc_html_e( 'Premium Version', 'woo-smart-wishlist' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>"
                                   class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'woo-smart-wishlist' ); ?>
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
												<?php esc_html_e( 'General', 'woo-smart-wishlist' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Disable the wishlist for unauthenticated users', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_disable_unauthenticated">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_disable_unauthenticated', 'no' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_disable_unauthenticated', 'no' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Auto remove', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_auto_remove">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_auto_remove', 'no' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_auto_remove', 'no' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description"><?php esc_html_e( 'Auto remove product from the wishlist after adding to the cart.', 'woo-smart-wishlist' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Button', 'woo-smart-wishlist' ); ?>
                                            </th>
                                            <td>
												<?php esc_html_e( 'Settings for "Add to wishlist" button.', 'woo-smart-wishlist' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Type', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_button_type">
                                                    <option
                                                            value="button" <?php echo( get_option( 'woosw_button_type', 'button' ) === 'button' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Button', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="link" <?php echo( get_option( 'woosw_button_type', 'button' ) === 'link' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Link', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Action', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_button_action">
                                                    <option
                                                            value="message" <?php echo( get_option( 'woosw_button_action', 'list' ) === 'message' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Show message', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="list" <?php echo( get_option( 'woosw_button_action', 'list' ) === 'list' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Show product list', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_button_action', 'list' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Add to wishlist solely', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description"><?php esc_html_e( 'Action triggered by clicking on the wishlist button.', 'woo-smart-wishlist' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Action (added)', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_button_action_added">
                                                    <option
                                                            value="popup" <?php echo( get_option( 'woosw_button_action_added', 'popup' ) === 'popup' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open wishlist popup', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="page" <?php echo( get_option( 'woosw_button_action_added', 'popup' ) === 'page' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open wishlist page', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description"><?php esc_html_e( 'Action triggered by clicking on the wishlist button after adding an item to the wishlist.', 'woo-smart-wishlist' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Extra class (optional)', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" name="woosw_button_class" class="regular-text"
                                                       value="<?php echo get_option( 'woosw_button_class', '' ); ?>"/>
                                                <span class="description"><?php esc_html_e( 'Add extra class for action button/link, split by one space.', 'woo-smart-wishlist' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position on archive page', 'woo-smart-wishlist' ); ?></th>
                                            <td>
												<?php $position_archive = apply_filters( 'woosw_button_position_archive', 'default' ); ?>
                                                <select name="woosw_button_position_archive" <?php echo( $position_archive !== 'default' ? 'disabled' : '' ); ?>>
													<?php if ( $position_archive === 'default' ) {
														$position_archive = get_option( 'woosw_button_position_archive', 'after_add_to_cart' );
													} ?>
                                                    <option value="after_title" <?php echo( $position_archive === 'after_title' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under title', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="after_rating" <?php echo( $position_archive === 'after_rating' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under rating', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="after_price" <?php echo( $position_archive === 'after_price' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under price', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="before_add_to_cart" <?php echo( $position_archive === 'before_add_to_cart' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Above add to cart button', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="after_add_to_cart" <?php echo( $position_archive === 'after_add_to_cart' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under add to cart button', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="0" <?php echo( ! $position_archive || ( $position_archive === '0' ) ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'None (hide it)', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position on single page', 'woo-smart-wishlist' ); ?></th>
                                            <td>
												<?php $position_single = apply_filters( 'woosw_button_position_single', 'default' ); ?>
                                                <select name="woosw_button_position_single" <?php echo( $position_single !== 'default' ? 'disabled' : '' ); ?>>
													<?php if ( $position_single === 'default' ) {
														$position_single = get_option( 'woosw_button_position_single', '31' );
													} ?>
                                                    <option value="6" <?php echo( $position_single === '6' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under title', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="11" <?php echo( $position_single === '11' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under price & rating', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="21" <?php echo( $position_single === '21' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under excerpt', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="29" <?php echo( $position_single === '29' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Above add to cart button', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="31" <?php echo( $position_single === '31' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under add to cart button', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="41" <?php echo( $position_single === '41' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under meta', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="51" <?php echo( $position_single === '51' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under sharing', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option value="0" <?php echo( ! $position_single || ( $position_single === '0' ) ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'None (hide it)', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Shortcode', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <span class="description">
                                                    <?php printf( esc_html__( 'You can add a button manually by using the shortcode %s, eg. %s for the product whose ID is 99.', 'woo-smart-wishlist' ), '<code>[woosw id="{product id}"]</code>', '<code>[woosw id="99"]</code>' ); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Categories', 'woo-smart-wishlist' ); ?></th>
                                            <td>
												<?php
												$selected_cats = get_option( 'woosw_cats' );

												if ( empty( $selected_cats ) ) {
													$selected_cats = array( 0 );
												}

												wc_product_dropdown_categories(
													array(
														'name'             => 'woosw_cats',
														'hide_empty'       => 0,
														'value_field'      => 'id',
														'multiple'         => true,
														'show_option_all'  => esc_html__( 'All categories', 'woo-smart-wishlist' ),
														'show_option_none' => '',
														'selected'         => implode( ',', $selected_cats )
													) );
												?>
                                                <span class="description"><?php esc_html_e( 'Only show the wishlist button for products in selected categories.', 'woo-smart-wishlist' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Popup', 'woo-smart-wishlist' ); ?>
                                            </th>
                                            <td>
												<?php esc_html_e( 'Settings for the wishlist popup.', 'woo-smart-wishlist' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Use perfect-scrollbar', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_perfect_scrollbar">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_perfect_scrollbar', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_perfect_scrollbar', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description"><?php printf( esc_html__( 'Read more about %s', 'woo-smart-wishlist' ), '<a href="https://github.com/mdbootstrap/perfect-scrollbar" target="_blank">perfect-scrollbar</a>' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Color', 'woo-smart-wishlist' ); ?></th>
                                            <td>
												<?php $color_default = apply_filters( 'woosw_color_default', '#5fbd74' ); ?>
                                                <input type="text" name="woosw_color"
                                                       value="<?php echo get_option( 'woosw_color', $color_default ); ?>"
                                                       class="woosw_color_picker"/>
                                                <span class="description"><?php printf( esc_html__( 'Choose the color, default %s', 'woo-smart-wishlist' ), '<code>' . $color_default . '</code>' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Link to individual product', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_link">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_link', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, open in the same tab', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="yes_blank" <?php echo( get_option( 'woosw_link', 'yes' ) === 'yes_blank' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, open in the new tab', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="yes_popup" <?php echo( get_option( 'woosw_link', 'yes' ) === 'yes_popup' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, open quick view popup', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_link', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">If you choose "Open quick view popup", please install <a
                                                            href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                            class="thickbox" title="Install WPC Smart Quick View">WPC Smart Quick View</a> to make it work.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Show note', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_show_note">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_show_note', 'no' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_show_note', 'no' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description"><?php esc_html_e( 'Show note on each product for all visitors. Only wishlist owner can add/edit these notes.', 'woo-smart-wishlist' ); ?></span>
                                                <p class="description" style="color: #c9356e">
                                                    This feature is only available on the Premium Version. Click <a
                                                            href="https://wpclever.net/downloads/woocommerce-smart-wishlist?utm_source=pro&utm_medium=woosw&utm_campaign=wporg"
                                                            target="_blank">here</a> to buy, just $29.
                                                </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Empty wishlist button', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_empty_button">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_empty_button', 'no' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_empty_button', 'no' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description"><?php esc_html_e( 'Show empty wishlist button on the popup?', 'woo-smart-wishlist' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Continue shopping link', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="url" name="woosw_continue_url"
                                                       value="<?php echo get_option( 'woosw_continue_url' ); ?>"
                                                       class="regular-text code"/>
                                                <span class="description"><?php esc_html_e( 'By default, the wishlist popup will only be closed when customers click on the "Continue Shopping" button.', 'woo-smart-wishlist' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Page', 'woo-smart-wishlist' ); ?>
                                            </th>
                                            <td>
												<?php esc_html_e( 'Settings for wishlist page.', 'woo-smart-wishlist' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Wishlist page', 'woo-smart-wishlist' ); ?></th>
                                            <td>
												<?php wp_dropdown_pages( array(
													'selected'          => get_option( 'woosw_page_id', '' ),
													'name'              => 'woosw_page_id',
													'show_option_none'  => esc_html__( 'Choose a page', 'woo-smart-wishlist' ),
													'option_none_value' => '',
												) ); ?>
                                                <span class="description"><?php printf( esc_html__( 'Add shortcode %s to display the wishlist on a page.', 'woo-smart-wishlist' ), '<code>[woosw_list]</code>' ); ?></span>
                                                <p class="description"><?php esc_html_e( 'After choosing a page, please go to Setting >> Permalinks and press Save Changes.', 'woo-smart-wishlist' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Share buttons', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_page_share">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_page_share', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_page_share', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description"><?php esc_html_e( 'Enable share buttons on the wishlist page?', 'woo-smart-wishlist' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Use font icon', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_page_icon">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_page_icon', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_page_icon', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Social links', 'woo-smart-wishlist' ); ?></th>
                                            <td>
												<?php
												$woosw_page_items = get_option( 'woosw_page_items' );

												if ( empty( $woosw_page_items ) ) {
													$woosw_page_items = array();
												}
												?>
                                                <select multiple name="woosw_page_items[]" id='woosw_page_items'>
                                                    <option <?php echo ( in_array( "facebook", $woosw_page_items ) ) ? "selected" : ""; ?>
                                                            value="facebook"><?php esc_html_e( 'Facebook', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option <?php echo ( in_array( "twitter", $woosw_page_items ) ) ? "selected" : ""; ?>
                                                            value="twitter"><?php esc_html_e( 'Twitter', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option <?php echo ( in_array( "pinterest", $woosw_page_items ) ) ? "selected" : ""; ?>
                                                            value="pinterest"><?php esc_html_e( 'Pinterest', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option <?php echo ( in_array( "mail", $woosw_page_items ) ) ? "selected" : ""; ?>
                                                            value="mail"><?php esc_html_e( 'Mail', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Copy link', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_page_copy">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_page_copy', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_page_copy', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description"><?php esc_html_e( 'Enable copy wishlist link to share?', 'woo-smart-wishlist' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Menu', 'woo-smart-wishlist' ); ?>
                                            </th>
                                            <td>
												<?php esc_html_e( 'Settings for the wishlist menu item.', 'woo-smart-wishlist' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Menu(s)', 'woo-smart-wishlist' ); ?></th>
                                            <td>
												<?php
												$nav_args    = array(
													'hide_empty' => false,
													'fields'     => 'id=>name',
												);
												$nav_menus   = get_terms( 'nav_menu', $nav_args );
												$saved_menus = get_option( 'woosw_menus', array() );

												foreach ( $nav_menus as $nav_id => $nav_name ) {
													echo '<input type="checkbox" name="woosw_menus[]" value="' . $nav_id . '" ' . ( is_array( $saved_menus ) && in_array( $nav_id, $saved_menus, false ) ? 'checked' : '' ) . '/><label>' . $nav_name . '</label><br/>';
												}
												?>
                                                <span class="description"><?php esc_html_e( 'Choose the menu(s) you want to add the "wishlist menu" at the end.', 'woo-smart-wishlist' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Action', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <select name="woosw_menu_action">
                                                    <option
                                                            value="open_page" <?php echo( get_option( 'woosw_menu_action', 'open_page' ) === 'open_page' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open page', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                    <option
                                                            value="open_popup" <?php echo( get_option( 'woosw_menu_action', 'open_page' ) === 'open_popup' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open popup', 'woo-smart-wishlist' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description"><?php esc_html_e( 'Action when clicking on the "wishlist menu".', 'woo-smart-wishlist' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
                                                <input type="submit" name="submit" class="button button-primary"
                                                       value="<?php esc_html_e( 'Update Options', 'woo-smart-wishlist' ); ?>"/>
                                                <input type="hidden" name="action" value="update"/>
                                                <input type="hidden" name="page_options"
                                                       value="woosw_disable_unauthenticated,woosw_auto_remove,woosw_link,woosw_show_note,woosw_page_id,woosw_page_share,woosw_page_icon,woosw_page_items,woosw_page_copy,woosw_button_type,woosw_button_text,woosw_button_action,woosw_button_text_added,woosw_button_action_added,woosw_button_class,woosw_button_position_archive,woosw_button_position_single,woosw_cats,woosw_perfect_scrollbar,woosw_color,woosw_empty_button,woosw_continue_url,woosw_menus,woosw_menu_action"/>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'localization' ) { ?>
                                <form method="post" action="options.php">
									<?php wp_nonce_field( 'update-options' ) ?>
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th scope="row"><?php esc_html_e( 'Localization', 'woo-smart-wishlist' ); ?></th>
                                            <td>
												<?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'woo-smart-wishlist' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Button text', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[button]"
                                                       value="<?php echo esc_attr( self::localization( 'button' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Add to wishlist', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Button text (added)', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[button_added]"
                                                       value="<?php echo esc_attr( self::localization( 'button_added' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Browse wishlist', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Wishlist popup heading', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[popup_heading]"
                                                       value="<?php echo esc_attr( self::localization( 'popup_heading' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Wishlist', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Empty wishlist button', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[empty_button]"
                                                       value="<?php echo esc_attr( self::localization( 'empty_button' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Remove all', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Add note', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[add_note]"
                                                       value="<?php echo esc_attr( self::localization( 'add_note' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Add note', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Save note', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[save_note]"
                                                       value="<?php echo esc_attr( self::localization( 'save_note' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Save', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Open wishlist page', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[open_page]"
                                                       value="<?php echo esc_attr( self::localization( 'open_page' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Open wishlist page', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Continue shopping', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[continue]"
                                                       value="<?php echo esc_attr( self::localization( 'continue' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Continue shopping', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Menu item label', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[menu_label]"
                                                       value="<?php echo esc_attr( self::localization( 'menu_label' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Wishlist', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th scope="row"><?php esc_html_e( 'Message', 'woo-smart-wishlist' ); ?></th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Added to the wishlist', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[added_message]"
                                                       value="<?php echo esc_attr( self::localization( 'added_message' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Added to the wishlist!', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Already in the wishlist', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[already_message]"
                                                       value="<?php echo esc_attr( self::localization( 'already_message' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Already in the wishlist!', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Removed from wishlist', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[removed_message]"
                                                       value="<?php echo esc_attr( self::localization( 'removed_message' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Removed from wishlist!', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Empty wishlist confirm', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[empty_confirm]"
                                                       value="<?php echo esc_attr( self::localization( 'empty_confirm' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Are you sure? This cannot be undone.', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Empty wishlist notice', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[empty_notice]"
                                                       value="<?php echo esc_attr( self::localization( 'empty_notice' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'All products were removed from your wishlist!', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Empty wishlist', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[empty_message]"
                                                       value="<?php echo esc_attr( self::localization( 'empty_message' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'There are no products on the wishlist!', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Product does not exist', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[not_exist_message]"
                                                       value="<?php echo esc_attr( self::localization( 'not_exist_message' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'The product does not exist on the wishlist!', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Need to login', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[login_message]"
                                                       value="<?php echo esc_attr( self::localization( 'login_message' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Please log in to use the wishlist!', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Copied wishlist link', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[copied]"
                                                       value="<?php echo esc_attr( self::localization( 'copied' ) ); ?>"
                                                       placeholder="<?php esc_html_e( 'Copied the wishlist link:', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Have an error', 'woo-smart-wishlist' ); ?></th>
                                            <td>
                                                <input type="text" class="regular-text"
                                                       name="woosw_localization[error_message]"
                                                       value="<?php echo esc_attr( self::localization( 'error_message' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Have an error, please try again!', 'woo-smart-wishlist' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
                                                <input type="submit" name="submit" class="button button-primary"
                                                       value="<?php esc_attr_e( 'Update Options', 'woo-smart-wishlist' ); ?>"/>
                                                <input type="hidden" name="action" value="update"/>
                                                <input type="hidden" name="page_options" value="woosw_localization"/>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'premium' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>Get the Premium Version just $29! <a
                                                href="https://wpclever.net/downloads/woocommerce-smart-wishlist?utm_source=pro&utm_medium=woosw&utm_campaign=wporg"
                                                target="_blank">https://wpclever.net/downloads/woocommerce-smart-wishlist</a>
                                    </p>
                                    <p><strong>Extra features for Premium Version:</strong></p>
                                    <ul style="margin-bottom: 0">
                                        <li>- Enable note for each product.</li>
                                        <li>- Get lifetime update & premium support.</li>
                                    </ul>
                                </div>
							<?php } ?>
                        </div>
                    </div>
					<?php
				}

				function wp_enqueue_scripts() {
					// perfect srollbar
					if ( get_option( 'woosw_perfect_scrollbar', 'yes' ) === 'yes' ) {
						wp_enqueue_style( 'perfect-scrollbar', WOOSW_URI . 'assets/libs/perfect-scrollbar/css/perfect-scrollbar.min.css' );
						wp_enqueue_style( 'perfect-scrollbar-wpc', WOOSW_URI . 'assets/libs/perfect-scrollbar/css/custom-theme.css' );
						wp_enqueue_script( 'perfect-scrollbar', WOOSW_URI . 'assets/libs/perfect-scrollbar/js/perfect-scrollbar.jquery.min.js', array( 'jquery' ), WOOSW_VERSION, true );
					}

					// feather icons
					wp_enqueue_style( 'woosw-feather', WOOSW_URI . 'assets/libs/feather/feather.css' );

					// main style
					wp_enqueue_style( 'woosw-frontend', WOOSW_URI . 'assets/css/frontend.css', array(), WOOSW_VERSION );
					$color_default = apply_filters( 'woosw_color_default', '#5fbd74' );
					$color         = apply_filters( 'woosw_color', get_option( 'woosw_color', $color_default ) );
					$custom_css    = ".woosw-area .woosw-inner .woosw-content .woosw-content-bot .woosw-notice { background-color: {$color}; } ";
					$custom_css    .= ".woosw-area .woosw-inner .woosw-content .woosw-content-bot .woosw-content-bot-inner .woosw-page a:hover, .woosw-area .woosw-inner .woosw-content .woosw-content-bot .woosw-content-bot-inner .woosw-continue:hover { color: {$color}; } ";
					wp_add_inline_style( 'woosw-frontend', $custom_css );

					// main js
					wp_enqueue_script( 'woosw-frontend', WOOSW_URI . 'assets/js/frontend.js', array( 'jquery' ), WOOSW_VERSION, true );

					// localize
					wp_localize_script( 'woosw-frontend', 'woosw_vars', array(
							'ajax_url'            => admin_url( 'admin-ajax.php' ),
							'menu_action'         => get_option( 'woosw_menu_action', 'open_page' ),
							'perfect_scrollbar'   => get_option( 'woosw_perfect_scrollbar', 'yes' ),
							'wishlist_url'        => self::get_url(),
							'button_action'       => get_option( 'woosw_button_action', 'list' ),
							'button_action_added' => get_option( 'woosw_button_action_added', 'popup' ),
							'empty_confirm'       => self::localization( 'empty_confirm', esc_html__( 'Are you sure? This cannot be undone.', 'woo-smart-wishlist' ) ),
							'copied_text'         => self::localization( 'copied', esc_html__( 'Copied the wishlist link:', 'woo-smart-wishlist' ) ),
							'menu_text'           => apply_filters( 'woosw_menu_item_label', self::localization( 'menu_label', esc_html__( 'Wishlist', 'woo-smart-wishlist' ) ) ),
							'button_text'         => apply_filters( 'woosw_button_text', self::localization( 'button', esc_html__( 'Add to wishlist', 'woo-smart-wishlist' ) ) ),
							'button_text_added'   => apply_filters( 'woosw_button_text_added', self::localization( 'button_added', esc_html__( 'Browse wishlist', 'woo-smart-wishlist' ) ) ),
						)
					);
				}

				function admin_enqueue_scripts() {
					wp_enqueue_style( 'wp-color-picker' );
					wp_enqueue_style( 'woosw-backend', WOOSW_URI . 'assets/css/backend.css', array(), WOOSW_VERSION );
					wp_enqueue_script( 'woosw-backend', WOOSW_URI . 'assets/js/backend.js', array(
						'jquery',
						'wp-color-picker',
						'jquery-ui-dialog'
					), WOOSW_VERSION, true );
					wp_localize_script( 'woosw-backend', 'woosw_vars', array(
						'nonce' => wp_create_nonce( 'woosw_nonce' )
					) );
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings         = '<a href="' . admin_url( 'admin.php?page=wpclever-woosw&tab=settings' ) . '">' . esc_html__( 'Settings', 'woo-smart-wishlist' ) . '</a>';
						$links['premium'] = '<a href="' . admin_url( 'admin.php?page=wpclever-woosw&tab=premium' ) . '" style="color: #c9356e">' . esc_html__( 'Premium Version', 'woo-smart-wishlist' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = array(
							'support' => '<a href="' . esc_url( WOOSW_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'woo-smart-wishlist' ) . '</a>',
						);

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function get_items( $key ) {
					$products = get_option( 'woosw_list_' . $key );
					$link     = get_option( 'woosw_link', 'yes' );
					ob_start();

					do_action( 'woosw_before_items', $key, $products );

					if ( is_array( $products ) && ( count( $products ) > 0 ) ) {
						echo '<table class="woosw-content-items">';

						do_action( 'woosw_wishlist_items_before', $key, $products );

						foreach ( $products as $product_id => $product_data ) {
							$product = wc_get_product( $product_id );

							if ( ! $product ) {
								continue;
							}

							if ( is_array( $product_data ) && isset( $product_data['time'] ) ) {
								$product_time = date_i18n( get_option( 'date_format' ), $product_data['time'] );
							} else {
								// for old version
								$product_time = date_i18n( get_option( 'date_format' ), $product_data );
							} ?>
                            <tr class="woosw-content-item woosw-content-item-<?php echo esc_attr( $product_id ); ?>"
                                data-id="<?php echo esc_attr( $product_id ); ?>"
                                data-key="<?php echo esc_attr( $key ); ?>">

								<?php do_action( 'woosw_wishlist_item_before', $product, $product_id, $key ); ?>

								<?php if ( self::can_edit( $key ) ) { ?>
                                    <td class="woosw-content-item--remove"><span></span></td>
								<?php } ?>

                                <td class="woosw-content-item--image">
									<?php if ( $link !== 'no' ) { ?>
                                        <a <?php echo ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . $product_id . '" data-context="woosw"' : '' ) . ' href="' . $product->get_permalink() . '" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ); ?>>
											<?php echo $product->get_image(); ?>
                                        </a>
									<?php } else {
										echo $product->get_image();
									}

									do_action( 'woosw_wishlist_item_image', $product, $product_id, $key ); ?>
                                </td>

                                <td class="woosw-content-item--info">
									<?php if ( $link !== 'no' ) {
										echo apply_filters( 'woosw_item_name', '<div class="woosw-content-item--name"><a ' . ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . $product_id . '" data-context="woosw"' : '' ) . ' href="' . $product->get_permalink() . '" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . $product->get_name() . '</a></div>', $product );
									} else {
										echo apply_filters( 'woosw_item_name', '<div class="woosw-content-item--name">' . $product->get_name() . '</div>', $product );
									}

									echo apply_filters( 'woosw_item_price', '<div class="woosw-content-item--price">' . $product->get_price_html() . '</div>', $product );

									echo apply_filters( 'woosw_item_time', '<div class="woosw-content-item--time">' . $product_time . '</div>', $product );

									do_action( 'woosw_wishlist_item_info', $product, $product_id, $key ); ?>
                                </td>

                                <td class="woosw-content-item--actions">
                                    <div class="woosw-content-item--stock">
										<?php echo( $product->is_in_stock() ? esc_html__( 'In stock', 'woo-smart-wishlist' ) : esc_html__( 'Out of stock', 'woo-smart-wishlist' ) ); ?>
                                    </div>

                                    <div class="woosw-content-item--add">
										<?php echo do_shortcode( '[add_to_cart style="" show_price="false" id="' . $product_id . '"]' ); ?>
                                    </div>

									<?php do_action( 'woosw_wishlist_item_actions', $product, $product_id, $key ); ?>
                                </td>

								<?php do_action( 'woosw_wishlist_item_after', $product, $product_id, $key ); ?>
                            </tr>
						<?php }

						do_action( 'woosw_wishlist_items_after', $key, $products );

						echo '</table>';
					} else { ?>
                        <div class="woosw-content-mid-notice">
							<?php echo self::localization( 'empty_message', esc_html__( 'There are no products on the wishlist!', 'woo-smart-wishlist' ) ); ?>
                        </div>
					<?php }

					do_action( 'woosw_after_items', $key, $products );

					$items_html = ob_get_clean();

					return apply_filters( 'woosw_wishlist_items', $items_html, $key, $products );
				}

				function woosw_nav_menu_items( $items, $args ) {
					$selected    = false;
					$saved_menus = get_option( 'woosw_menus', array() );

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
						$menu_item = '<li class="' . apply_filters( 'woosw_menu_item_class', 'menu-item woosw-menu-item menu-item-type-woosw' ) . '"><a href="' . self::get_url() . '"><span class="woosw-menu-item-inner" data-count="' . self::get_count() . '">' . apply_filters( 'woosw_menu_item_label', self::localization( 'menu_label', esc_html__( 'Wishlist', 'woo-smart-wishlist' ) ) ) . '</span></a></li>';
						$items     .= apply_filters( 'woosw_menu_item', $menu_item );
					}

					return $items;
				}

				function wp_footer() {
					if ( is_admin() ) {
						return;
					}
					?>
                    <div id="woosw-area" class="woosw-area">
                        <div class="woosw-inner">
                            <div class="woosw-content">
                                <div class="woosw-content-top">
									<?php echo self::localization( 'popup_heading', esc_html__( 'Wishlist', 'woo-smart-wishlist' ) ); ?>
                                    <span class="woosw-count"><?php echo count( self::$added_products ); ?></span>
									<?php if ( get_option( 'woosw_empty_button', 'no' ) === 'yes' ) {
										echo '<a class="woosw-empty" href="javascript:void(0);">' . self::localization( 'empty_button', esc_html__( 'Remove all', 'woo-smart-wishlist' ) ) . '</a>';
									} ?>
                                    <span class="woosw-close"></span>
                                </div>
                                <div class="woosw-content-mid"></div>
                                <div class="woosw-content-bot">
                                    <div class="woosw-content-bot-inner">
                                        <a class="woosw-page"
                                           href="<?php echo self::get_url( self::get_key() ); ?>"><?php echo self::localization( 'open_page', esc_html__( 'Open wishlist page', 'woo-smart-wishlist' ) ); ?></a>
                                        <a class="woosw-continue" href="javascript:void(0);"
                                           data-url="<?php echo esc_url( get_option( 'woosw_continue_url' ) ); ?>"><?php echo self::localization( 'continue', esc_html__( 'Continue shopping', 'woo-smart-wishlist' ) ); ?></a>
                                    </div>
                                    <div class="woosw-notice"></div>
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function update_product_count( $product_id, $action = 'add' ) {
					$meta_count = 'woosw_count';
					$meta_time  = ( $action === 'add' ? 'woosw_add' : 'woosw_remove' );
					$count      = get_post_meta( $product_id, $meta_count, true );
					$new_count  = 0;

					if ( $action === 'add' ) {
						if ( $count ) {
							$new_count = absint( $count ) + 1;
						} else {
							$new_count = 1;
						}
					} elseif ( $action === 'remove' ) {
						if ( $count && ( absint( $count ) > 1 ) ) {
							$new_count = absint( $count ) - 1;
						} else {
							$new_count = 0;
						}
					}

					update_post_meta( $product_id, $meta_count, $new_count );
					update_post_meta( $product_id, $meta_time, time() );
				}

				public static function generate_key() {
					$key         = '';
					$key_str     = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
					$key_str_len = strlen( $key_str );

					for ( $i = 0; $i < 6; $i ++ ) {
						$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
					}

					return apply_filters( 'woosw_generate_key', $key );
				}

				public static function exists_key( $key ) {
					if ( get_option( 'woosw_list_' . $key ) ) {
						return true;
					}

					return false;
				}

				public static function can_edit( $key ) {
					if ( is_user_logged_in() ) {
						if ( get_user_meta( get_current_user_id(), 'woosw_key', true ) === $key ) {
							return true;
						}
					} else {
						if ( isset( $_COOKIE['woosw_key'] ) && ( $_COOKIE['woosw_key'] === $key ) ) {
							return true;
						}
					}

					return false;
				}

				public static function get_page_id() {
					if ( get_option( 'woosw_page_id' ) ) {
						return absint( get_option( 'woosw_page_id' ) );
					}

					return false;
				}

				public static function get_key() {
					if ( ! is_user_logged_in() && ( get_option( 'woosw_disable_unauthenticated', 'no' ) === 'yes' ) ) {
						return '#';
					}

					if ( is_user_logged_in() && ( ( $user_id = get_current_user_id() ) > 0 ) ) {
						$user_key = get_user_meta( $user_id, 'woosw_key', true );

						if ( empty( $user_key ) ) {
							$user_key = self::generate_key();

							while ( self::exists_key( $user_key ) ) {
								$user_key = self::generate_key();
							}

							// set a new key
							update_user_meta( $user_id, 'woosw_key', $user_key );
						}

						return $user_key;
					}

					if ( isset( $_COOKIE['woosw_key'] ) ) {
						return esc_attr( $_COOKIE['woosw_key'] );
					}

					return 'WOOSW';
				}

				public static function get_url( $key = null, $full = false ) {
					$url = home_url( '/' );

					if ( $page_id = self::get_page_id() ) {
						if ( $full ) {
							if ( ! $key ) {
								$key = self::get_key();
							}

							if ( get_option( 'permalink_structure' ) !== '' ) {
								$url = trailingslashit( get_permalink( $page_id ) ) . $key;
							} else {
								$url = get_permalink( $page_id ) . '&woosw_id=' . $key;
							}
						} else {
							$url = get_permalink( $page_id );
						}
					}

					return apply_filters( 'woosw_wishlist_url', $url, $key );
				}

				public static function get_count( $key = null ) {
					if ( ! $key ) {
						$key = self::get_key();
					}

					if ( ( $key != '' ) && ( $products = get_option( 'woosw_list_' . $key ) ) && is_array( $products ) ) {
						$count = count( $products );
					} else {
						$count = 0;
					}

					return apply_filters( 'woosw_wishlist_count', $count, $key );
				}

				function woosw_product_columns( $columns ) {
					$columns['woosw'] = esc_html__( 'Wishlist', 'woo-smart-wishlist' );

					return $columns;
				}

				function woosw_product_posts_custom_column( $column, $postid ) {
					if ( $column == 'woosw' ) {
						if ( ( $count = (int) get_post_meta( $postid, 'woosw_count', true ) ) > 0 ) {
							echo '<a href="#" class="woosw_action" data-pid="' . $postid . '">' . $count . '</a>';
						}
					}
				}

				function woosw_product_sortable_columns( $columns ) {
					$columns['woosw'] = 'woosw';

					return $columns;
				}

				function woosw_product_request( $vars ) {
					if ( isset( $vars['orderby'] ) && 'woosw' == $vars['orderby'] ) {
						$vars = array_merge( $vars, array(
							'meta_key' => 'woosw_count',
							'orderby'  => 'meta_value_num'
						) );
					}

					return $vars;
				}

				function woosw_wp_login( $user_login, $user ) {
					if ( isset( $user->data->ID ) ) {
						$user_key = get_user_meta( $user->data->ID, 'woosw_key', true );

						if ( empty( $user_key ) ) {
							$user_key = self::generate_key();

							while ( self::exists_key( $user_key ) ) {
								$user_key = self::generate_key();
							}

							// set a new key
							update_user_meta( $user->data->ID, 'woosw_key', $user_key );
						}

						$secure   = apply_filters( 'woosw_cookie_secure', wc_site_is_https() && is_ssl() );
						$httponly = apply_filters( 'woosw_cookie_httponly', true );

						if ( isset( $_COOKIE['woosw_key'] ) && ! empty( $_COOKIE['woosw_key'] ) ) {
							wc_setcookie( 'woosw_key_ori', $_COOKIE['woosw_key'], time() + 604800, $secure, $httponly );
						}

						wc_setcookie( 'woosw_key', $user_key, time() + 604800, $secure, $httponly );
					}
				}

				function woosw_wp_logout( $user_id ) {
					if ( isset( $_COOKIE['woosw_key_ori'] ) && ! empty( $_COOKIE['woosw_key_ori'] ) ) {
						$secure   = apply_filters( 'woosw_cookie_secure', wc_site_is_https() && is_ssl() );
						$httponly = apply_filters( 'woosw_cookie_httponly', true );

						wc_setcookie( 'woosw_key', $_COOKIE['woosw_key_ori'], time() + 604800, $secure, $httponly );
					} else {
						unset( $_COOKIE['woosw_key_ori'] );
						unset( $_COOKIE['woosw_key'] );
					}
				}

				function dropdown_cats_multiple( $output, $r ) {
					if ( isset( $r['multiple'] ) && $r['multiple'] ) {
						$output = preg_replace( '/^<select/i', '<select multiple', $output );
						$output = str_replace( "name='{$r['name']}'", "name='{$r['name']}[]'", $output );

						foreach ( array_map( 'trim', explode( ",", $r['selected'] ) ) as $value ) {
							$output = str_replace( "value=\"{$value}\"", "value=\"{$value}\" selected", $output );
						}
					}

					return $output;
				}

				function woosw_user_table( $column ) {
					$column['woosw'] = esc_html__( 'Wishlist', 'woo-smart-wishlist' );

					return $column;
				}

				function woosw_user_table_row( $val, $column_name, $user_id ) {
					if ( $column_name === 'woosw' ) {
						$user_key = get_user_meta( $user_id, 'woosw_key', true );

						if ( ! empty( $user_key ) && ( $products = get_option( 'woosw_list_' . $user_key, true ) ) ) {
							if ( is_array( $products ) && ( $count = count( $products ) ) ) {
								$val = '<a href="#" class="woosw_action" data-key="' . $user_key . '">' . $count . '</a>';
							}
						}
					}

					return $val;
				}

				function wishlist_quickview() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woosw_nonce' ) ) {
						die( esc_html__( 'Permissions check failed', 'woo-smart-wishlist' ) );
					}

					global $wpdb;
					$wishlist_html = '';

					if ( isset( $_POST['key'] ) && $_POST['key'] != '' ) {
						$key      = $_POST['key'];
						$products = get_option( 'woosw_list_' . $_POST['key'], true );
						$count    = count( $products );
						ob_start();

						if ( count( $products ) > 0 ) {
							echo '<div class="woosw-quickview-items">';

							$user = $wpdb->get_results( 'SELECT user_id FROM `' . $wpdb->prefix . 'usermeta` WHERE `meta_key` = "woosw_key" AND `meta_value` = "' . $key . '" LIMIT 1', OBJECT );

							echo '<div class="woosw-quickview-item">';
							echo '<div class="woosw-quickview-item-image"><a href="' . self::get_url( $key, true ) . '" target="_blank">#' . $key . '</a></div>';
							echo '<div class="woosw-quickview-item-info">';

							if ( ! empty( $user ) ) {
								$user_id   = $user[0]->user_id;
								$user_data = get_userdata( $user_id );

								echo '<div class="woosw-quickview-item-title"><a href="' . get_edit_user_link( $user_id ) . '" target="_blank">' . $user_data->user_login . '</a></div>';
								echo '<div class="woosw-quickview-item-data">' . $user_data->user_email . ' | ' . sprintf( _n( '%s product', '%s products', $count, 'woo-smart-wishlist' ), number_format_i18n( $count ) ) . '</div>';
							} else {
								echo '<div class="woosw-quickview-item-title">' . esc_html__( 'Guest', 'woo-smart-wishlist' ) . '</div>';
								echo '<div class="woosw-quickview-item-data">' . sprintf( _n( '%s product', '%s products', $count, 'woo-smart-wishlist' ), number_format_i18n( $count ) ) . '</div>';
							}

							echo '</div><!-- /woosw-quickview-item-info -->';
							echo '</div><!-- /woosw-quickview-item -->';

							foreach ( $products as $pid => $data ) {
								$_product = wc_get_product( $pid );

								if ( $_product ) {
									echo '<div class="woosw-quickview-item">';
									echo '<div class="woosw-quickview-item-image">' . $_product->get_image() . '</div>';
									echo '<div class="woosw-quickview-item-info">';
									echo '<div class="woosw-quickview-item-title"><a href="' . $_product->get_permalink() . '" target="_blank">' . $_product->get_name() . '</a></div>';
									echo '<div class="woosw-quickview-item-data">' . date_i18n( get_option( 'date_format' ), $data['time'] ) . ' <span class="woosw-quickview-item-links">| ID: ' . $pid . ' | <a href="' . get_edit_post_link( $pid ) . '" target="_blank">' . esc_html__( 'Edit', 'woo-smart-wishlist' ) . '</a> | <a href="#" class="woosw_action" data-pid="' . $pid . '">' . esc_html__( 'See in wishlist', 'woo-smart-wishlist' ) . '</a></span></div>';
									echo '</div><!-- /woosw-quickview-item-info -->';
									echo '</div><!-- /woosw-quickview-item -->';
								}
							}

							echo '</div>';
						} else {
							echo '<div style="text-align: center">' . esc_html__( 'Empty Wishlist', 'woo-smart-wishlist' ) . '<div>';
						}

						$wishlist_html = ob_get_clean();
					} elseif ( isset( $_POST['pid'] ) ) {
						$pid = $_POST['pid'];
						ob_start();

						$keys  = $wpdb->get_results( 'SELECT option_name FROM `' . $wpdb->prefix . 'options` WHERE `option_name` LIKE "%woosw_list_%" AND `option_value` LIKE "%i:' . $pid . ';%"', OBJECT );
						$count = count( $keys );

						if ( $count > 0 ) {
							echo '<div class="woosw-quickview-items">';

							$_product = wc_get_product( $pid );

							if ( $_product ) {
								echo '<div class="woosw-quickview-item">';
								echo '<div class="woosw-quickview-item-image">' . $_product->get_image() . '</div>';
								echo '<div class="woosw-quickview-item-info">';
								echo '<div class="woosw-quickview-item-title"><a href="' . $_product->get_permalink() . '" target="_blank">' . $_product->get_name() . '</a></div>';
								echo '<div class="woosw-quickview-item-data">ID: ' . $pid . ' | ' . sprintf( _n( '%s wishlist', '%s wishlists', $count, 'woosw' ), number_format_i18n( $count ) ) . ' <span class="woosw-quickview-item-links">| <a href="' . get_edit_post_link( $pid ) . '" target="_blank">' . esc_html__( 'Edit', 'woo-smart-wishlist' ) . '</a></span></div>';
								echo '</div><!-- /woosw-quickview-item-info -->';
								echo '</div><!-- /woosw-quickview-item -->';
							}

							foreach ( $keys as $item ) {
								$products = get_option( $item->option_name );
								$count    = count( $products );
								$key      = str_replace( 'woosw_list_', '', $item->option_name );
								$user     = $wpdb->get_results( 'SELECT user_id FROM `' . $wpdb->prefix . 'usermeta` WHERE `meta_key` = "woosw_key" AND `meta_value` = "' . $key . '" LIMIT 1', OBJECT );

								echo '<div class="woosw-quickview-item">';
								echo '<div class="woosw-quickview-item-image"><a href="' . self::get_url( $key, true ) . '" target="_blank">#' . $key . '</a></div>';
								echo '<div class="woosw-quickview-item-info">';

								if ( ! empty( $user ) ) {
									$user_id   = $user[0]->user_id;
									$user_data = get_userdata( $user_id );


									echo '<div class="woosw-quickview-item-title"><a href="' . get_edit_user_link( $user_id ) . '" target="_blank">' . $user_data->user_login . '</a></div>';
									echo '<div class="woosw-quickview-item-data">' . $user_data->user_email . '  | <a href="#" class="woosw_action" data-key="' . $key . '">' . sprintf( _n( '%s product', '%s products', $count, 'woo-smart-wishlist' ), number_format_i18n( $count ) ) . '</a></div>';
								} else {
									echo '<div class="woosw-quickview-item-title">' . esc_html__( 'Guest', 'woo-smart-wishlist' ) . '</div>';
									echo '<div class="woosw-quickview-item-data"><a href="#" class="woosw_action" data-key="' . $key . '">' . sprintf( _n( '%s product', '%s products', $count, 'woo-smart-wishlist' ), number_format_i18n( $count ) ) . '</a></div>';
								}

								echo '</div><!-- /woosw-quickview-item-info -->';
								echo '</div><!-- /woosw-quickview-item -->';
							}

							echo '</div>';
						}

						$wishlist_html = ob_get_clean();
					}

					echo $wishlist_html;
					die();
				}
			}

			new WPCleverWoosw();
		}
	}
} else {
	add_action( 'admin_notices', 'woosw_notice_premium' );
}

if ( ! function_exists( 'woosw_plugin_activate' ) ) {
	function woosw_plugin_activate() {
		// create wishlist page
		$wishlist_page = get_page_by_path( 'wishlist', OBJECT );

		if ( empty( $wishlist_page ) ) {
			$wishlist_page_data = array(
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_author'    => 1,
				'post_name'      => 'wishlist',
				'post_title'     => esc_html__( 'Wishlist', 'woo-smart-wishlist' ),
				'post_content'   => '[woosw_list]',
				'post_parent'    => 0,
				'comment_status' => 'closed'
			);
			$wishlist_page_id   = wp_insert_post( $wishlist_page_data );

			update_option( 'woosw_page_id', $wishlist_page_id );
		}
	}
}

if ( ! function_exists( 'woosw_notice_wc' ) ) {
	function woosw_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Smart Wishlist</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}

if ( ! function_exists( 'woosw_notice_premium' ) ) {
	function woosw_notice_premium() {
		?>
        <div class="error">
            <p>Seems you're using both free and premium version of <strong>WPC Smart Wishlist</strong>. Please
                deactivate the free version when using the premium version.</p>
        </div>
		<?php
	}
}