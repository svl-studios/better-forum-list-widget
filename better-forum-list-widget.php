<?php
/*
Plugin Name: Better Forum List Widget for bbPress
Description: The default bbPress Forum List widget is pretty bare bones. This plugin adds a topic count and organizes the forum categories differently.
Author: c.bavota
Version: 1.0.0
Author URI: http://www.bavotasan.com/
*/

/**
 * Register the widget
 */
add_action( 'widgets_init', function() {
     register_widget( 'BBP_Forums_Topic_Count_Widget' );
} );

class BBP_Forums_Topic_Count_Widget extends WP_Widget {

	/**
	 * Setting up the basic widget requirements
	 */
	public function __construct() {
		$widget_ops = array( 'classname' => 'bbp_forums_topic_count_widget_options', 'description' => __( 'A list of forums with their topic count' ) );
		parent::__construct( false, __( '(bbPress) Forums List with Topic Count' ), $widget_ops );
	}

	/**
	 * Everything that we need to display the widget on the front end
	 *
	 * @param	arr $args
	 * @param	arr $instance
	 *
	 * @uses	bbp_get_statistics()
	 * @uses	wp_list_pages()
	 * @uses	bbp_get_forum_post_type()
	 * @uses	bbp_get_forum_post_type()
	 */
	public function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'bbp_forum_widget_title', $instance['title'] );

		echo $before_widget;

		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;
		?>
		<ul>
			<?php
			wp_list_pages( array(
				'title_li' => '',
				'post_type' => bbp_get_forum_post_type(),
				'sort_column' => 'menu_order',
				'walker' => new Forum_List_Walker,
				'no_found_rows' => true,
			) );
			?>
		</ul>
		<?php
		echo $after_widget;
	}

	/**
	 * The widget admin options
	 *
	 * @param	arr $instance
	 *
	 * @uses	get_field_id()
	 * @uses	get_field_name()
	 */
	public function form( $instance ) {
		$title = empty( $instance['title'] ) ? '' : esc_attr( $instance['title'] );
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?>
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
			</label>
		</p>
		<?php
	}

	/**
	 * Validate the widget admin options
	 *
	 * @param	arr $new_instance
	 * @param	arr $old_instance
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}
}

class Forum_List_Walker extends Walker {
	var $tree_type = 'page';
	var $db_fields = array (
		'parent' => 'post_parent',
		'id' => 'ID'
	);

	/**
	 * Used to output the opening HTML tag at the beginning of our list item
	 *
	 * @param	str $output
	 * @param	int $depth
	 * @param	arr $args
	 */
	function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "\n$indent<ul class='children'>\n";
	}

	/**
	 * Used to output the closing HTML tag at the end of our list item
	 *
	 * @param	str $output
	 * @param	int $depth
	 * @param	arr $args
	 */
	function end_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "$indent</ul>\n";
	}

	/**
	 * Used to output the HTML at the beginning of our list
	 *
	 * @param	str $output
	 * @param	int $depth
	 * @param	arr $args
	 * @param	arr $page
	 */
	function start_el( &$output, $page, $depth, $args ) {
		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

		extract( $args, EXTR_SKIP );
		$css_class = array();
		$has_children = forum_list_widget_has_children( $page->ID );
		if ( ! bbp_is_single_user() && $current_page = get_the_ID() ) {
			$_current_page = get_post( $current_page );
			if ( in_array( $page->ID, $_current_page->ancestors ) )
				$css_class[] = 'current_forum_ancestor';
			if ( $has_children )
				$css_class[] = 'forum_category';
			if ( $page->ID == $current_page )
				$css_class[] = 'current_forum_item';
			elseif ( $_current_page && $page->ID == $_current_page->post_parent )
				$css_class[] = 'current_forum_parent';
		} elseif ( $page->ID == get_option( 'page_for_posts' ) ) {
			$css_class[] = 'current_forum_parent';
		}

		$css_class = implode( ' ', $css_class );
		$topic_count = ( $has_children ) ? '' : '<span class="topic-count">' . bbp_get_forum_topic_count( $page->ID ) . '</span>';
		$forum_item = ( $has_children ) ? '<span class="forum_category_title">' . apply_filters( 'the_title', $page->post_title, $page->ID ) . '</span>' . $topic_count : '<a href="' . get_permalink( $page->ID ) . '">' . apply_filters( 'the_title', $page->post_title, $page->ID ) . $topic_count . '</a>';

		$output .= $indent . '<li class="' . $css_class . '">' . $forum_item;
	}

	/**
	 * Used to output the HTML at the end of our list
	 *
	 * @param	str $output
	 * @param	int $depth
	 * @param	arr $args
	 * @param	arr $page
	 */
	function end_el( &$output, $page, $depth = 0, $args = array() ) {
		$output .= "</li>\n";
	}

}

/**
 * Conditional check to see if page has sub-pages
 *
 * @param	str $page_id
 *
 * @uses	bbp_get_forum_post_type()
 */
function forum_list_widget_has_children( $page_id ) {
	$children = get_pages( array(
		'child_of' => $page_id,
		'post_type' => bbp_get_forum_post_type(),
	) );
	if ( $children )
		return true;
	else
		return false;
}

add_action( 'wp_enqueue_scripts', 'forum_list_widget_styles' );
/**
 * Enqueue the stylesheet
 *
 * This function is attached to the 'wp_enqueue_scripts' action hook
 *
 * @uses	wp_enqueue_style()
 */
function forum_list_widget_styles() {
	wp_enqueue_style( 'forum_list_widget_styles', plugins_url( '/css/style.css', __FILE__ ) );
}