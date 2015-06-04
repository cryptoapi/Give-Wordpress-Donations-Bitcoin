<?php
/**
 * Dashboard Columns
 *
 * @package     GIVE
 * @subpackage  Admin/Downloads
 * @copyright   Copyright (c) 2015, WordImpress
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Give Forms Columns
 *
 * Defines the custom columns and their order
 *
 * @since 1.0
 *
 * @param array $give_form_columns Array of forms columns
 *
 * @return array $form_columns Updated array of forms columns
 *  Post Type List Table
 */
function give_form_columns( $give_form_columns ) {

	//Standard columns
	$give_form_columns = array(
		'cb'            => '<input type="checkbox"/>',
		'title'         => __( 'Name', 'give' ),
		'form_category' => __( 'Categories', 'give' ),
		'form_tag'      => __( 'Tags', 'give' ),
		'price'         => __( 'Price', 'give' ),
		'goal'			=> __( 'Goal', 'give' ),
		'donations'     => __( 'Donations', 'give' ),
		'earnings'      => __( 'Income', 'give' ),
		'shortcode'     => __( 'Shortcode', 'give' ),
		'date'          => __( 'Date', 'give' )
	);

	//Does the user want categories / tags?
	if ( give_get_option( 'enable_categories' ) !== 'on' ) {
		unset( $give_form_columns['form_category'] );
	}
	if ( give_get_option( 'enable_tags' ) !== 'on' ) {
		unset( $give_form_columns['form_tag'] );
	}

	return apply_filters( 'give_forms_columns', $give_form_columns );
}

add_filter( 'manage_edit-give_forms_columns', 'give_form_columns' );

/**
 * Render Give Form Columns
 *
 * @since 1.0
 *
 * @param string $column_name Column name
 * @param int    $post_id     Give Form (Post) ID
 *
 * @return void
 */
function give_render_form_columns( $column_name, $post_id ) {
	if ( get_post_type( $post_id ) == 'give_forms' ) {
		global $give_options;

		$style = isset( $give_options['button_style'] ) ? $give_options['button_style'] : 'button';
		$color = isset( $give_options['checkout_color'] ) ? $give_options['checkout_color'] : 'blue';
		$color = ( $color == 'inherit' ) ? '' : $color;

		$purchase_text = ! empty( $give_options['add_to_cart_text'] ) ? $give_options['add_to_cart_text'] : __( 'Purchase', 'give' );

		switch ( $column_name ) {
			case 'form_category':
				echo get_the_term_list( $post_id, 'give_forms_category', '', ', ', '' );
				break;
			case 'form_tag':
				echo get_the_term_list( $post_id, 'give_forms_tag', '', ', ', '' );
				break;
			case 'price':
				if ( give_has_variable_prices( $post_id ) ) {
					echo give_price_range( $post_id );
				} else {
					echo give_price( $post_id, false );
					echo '<input type="hidden" class="formprice-' . $post_id . '" value="' . give_get_form_price( $post_id ) . '" />';
				}
				break;
			case 'goal':
				echo give_goal( $post_id, false );
				echo '<input type="hidden" class="formgoal-' . $post_id . '" value="' . give_get_form_goal( $post_id ) . '" />';
				break;
			case 'donations':
				if ( current_user_can( 'view_give_forms_stats', $post_id ) ) {
					echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=give_forms&page=give-reports&tab=logs&view=sales&form=' . $post_id ) ) . '">';
					echo give_get_form_sales_stats( $post_id );
					echo '</a>';
				} else {
					echo '-';
				}
				break;
			case 'earnings':
				if ( current_user_can( 'view_give_forms_stats', $post_id ) ) {
					echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=give_forms&page=give-reports&view=forms&form-id=' . $post_id ) ) . '">';
					echo give_currency_filter( give_format_amount( give_get_form_earnings_stats( $post_id ) ) );
					echo '</a>';
				} else {
					echo '-';
				}
				break;
			case 'shortcode':
				echo '<input onclick="this.setSelectionRange(0, this.value.length)" type="text" class="shortcode-input" readonly="" value="[give_form id=&#34;' . absint( $post_id ) . '&#34;]">';
				break;
		}
	}
}

add_action( 'manage_posts_custom_column', 'give_render_form_columns', 10, 2 );

/**
 * Registers the sortable columns in the list table
 *
 * @since 1.0
 *
 * @param array $columns Array of the columns
 *
 * @return array $columns Array of sortable columns
 */
function give_sortable_form_columns( $columns ) {
	$columns['price']    = 'price';
	$columns['sales']    = 'sales';
	$columns['earnings'] = 'earnings';
	$columns['goal']	 = 'goal';

	return $columns;
}

add_filter( 'manage_edit-give_forms_sortable_columns', 'give_sortable_form_columns' );

/**
 * Sorts Columns in the Forms List Table
 *
 * @since 1.0
 *
 * @param array $vars Array of all the sort variables
 *
 * @return array $vars Array of all the sort variables
 */
function give_sort_forms( $vars ) {
	// Check if we're viewing the "give_forms" post type
	if ( isset( $vars['post_type'] ) && 'give_forms' == $vars['post_type'] ) {
		// Check if 'orderby' is set to "sales"
		if ( isset( $vars['orderby'] ) && 'sales' == $vars['orderby'] ) {
			$vars = array_merge(
				$vars,
				array(
					'meta_key' => '_give_form_sales',
					'orderby'  => 'meta_value_num'
				)
			);
		}

		// Check if "orderby" is set to "earnings"
		if ( isset( $vars['orderby'] ) && 'earnings' == $vars['orderby'] ) {
			$vars = array_merge(
				$vars,
				array(
					'meta_key' => '_give_form_earnings',
					'orderby'  => 'meta_value_num'
				)
			);
		}

		// Check if "orderby" is set to "price"
		if ( isset( $vars['orderby'] ) && 'price' == $vars['orderby'] ) {
			$vars = array_merge(
				$vars,
				array(
					'meta_key' => '_give_set_price',
					'orderby'  => 'meta_value_num'
				)
			);
		}

		// Check if "orderby" is set to "goal"
		if ( isset( $vars['orderby'] ) && 'goal' == $vars['orderby'] ) {
			$vars = array_merge(
				$vars,
				array(
					'meta_key' => '_give_set_goal',
					'orderby'  => 'meta_value_num'
				)
			);
		}
	}

	return $vars;
}

/**
 * Sets restrictions on author of Forms List Table
 *
 * @since  1.0
 *
 * @param  array $vars Array of all sort varialbes
 *
 * @return array       Array of all sort variables
 */
function give_filter_forms( $vars ) {
	if ( isset( $vars['post_type'] ) && 'give_forms' == $vars['post_type'] ) {

		// If an author ID was passed, use it
		if ( isset( $_REQUEST['author'] ) && ! current_user_can( 'view_give_reports' ) ) {

			$author_id = $_REQUEST['author'];
			if ( (int) $author_id !== get_current_user_id() ) {
				// Tried to view the products of another person, sorry
				wp_die( __( 'You do not have permission to view this data.', 'give' ), __( 'Error', 'give' ), array( 'response' => 403 ) );
			}
			$vars = array_merge(
				$vars,
				array(
					'author' => get_current_user_id()
				)
			);

		}

	}

	return $vars;
}

/**
 * Form Load
 *
 * Sorts the form columns.
 *
 * @since 1.0
 * @return void
 */
function give_forms_load() {
	add_filter( 'request', 'give_sort_forms' );
	add_filter( 'request', 'give_filter_forms' );
}

add_action( 'load-edit.php', 'give_forms_load', 9999 );

/**
 * Remove Forms Month Filter
 *
 * Removes the default drop down filter for forms by date.
 *
 * @since  1.0
 *
 * @param array $dates   The preset array of dates
 *
 * @global      $typenow The post type we are viewing
 * @return array Empty array disables the dropdown
 */
function give_remove_month_filter( $dates ) {
	global $typenow;

	if ( $typenow == 'give_forms' ) {
		$dates = array();
	}

	return $dates;
}

add_filter( 'months_dropdown_results', 'give_remove_month_filter', 99 );

/**
 * Adds price field to Quick Edit options
 *
 * @since 1.0
 *
 * @param string $column_name Name of the column
 * @param string $post_type   Current Post Type (i.e. forms)
 *
 * @return void
 */
function give_price_field_quick_edit( $column_name, $post_type ) {
	if ( $column_name != 'price' || $post_type != 'give_forms' ) {
		return;
	}
	?>
	<fieldset class="inline-edit-col-left">
		<div id="give-give-data" class="inline-edit-col">
			<h4><?php echo sprintf( __( '%s Configuration', 'give' ), give_get_forms_label_singular() ); ?></h4>
			<label>
				<span class="title"><?php _e( 'Price', 'give' ); ?></span>
				<span class="input-text-wrap">
					<input type="text" name="_give_regprice" class="text regprice" />
				</span>
			</label>
			<br class="clear" />
		</div>
	</fieldset>
<?php
}

add_action( 'quick_edit_custom_box', 'give_price_field_quick_edit', 10, 2 );
add_action( 'bulk_edit_custom_box', 'give_price_field_quick_edit', 10, 2 );

/**
 * Updates price when saving post
 *
 * @since 1.0
 *
 * @param int $post_id Download (Post) ID
 *
 * @return void
 */
function give_price_save_quick_edit( $post_id ) {
	if ( ! isset( $_POST['post_type'] ) || 'give_forms' !== $_POST['post_type'] ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return $post_id;
	}

	if ( isset( $_REQUEST['_give_regprice'] ) ) {
		update_post_meta( $post_id, '_give_set_price', strip_tags( stripslashes( $_REQUEST['_give_regprice'] ) ) );
	}
}

add_action( 'save_post', 'give_price_save_quick_edit' );

/**
 * Process bulk edit actions via AJAX
 *
 * @since 1.0
 * @return void
 */
function give_save_bulk_edit() {

	$post_ids = ( isset( $_POST['post_ids'] ) && ! empty( $_POST['post_ids'] ) ) ? $_POST['post_ids'] : array();

	if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
		$price = isset( $_POST['price'] ) ? strip_tags( stripslashes( $_POST['price'] ) ) : 0;
		foreach ( $post_ids as $post_id ) {

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			if ( ! empty( $price ) ) {
				update_post_meta( $post_id, '_give_set_price', give_sanitize_amount( $price ) );
			}
		}
	}

	die();
}

add_action( 'wp_ajax_give_save_bulk_edit', 'give_save_bulk_edit' );
