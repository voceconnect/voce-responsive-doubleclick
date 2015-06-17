<?php

class AdWidget extends WP_Widget {

	function AdWidget() {
		parent::WP_Widget( false, 'AdWidget' );
	}

	function widget( $args, $instance ) {
		extract( $args );

		echo $before_widget;
		echo '<div class="sponsored-post_ad">';

		echo voce_get_the_ad(apply_filters( 'double_click_widget_sizes', array( 0 => '320x250') ), array('loc_id' => 'R'));
		echo '</div>';
		echo $after_widget;
	}
}

add_action( 'widgets_init', create_function( '', 'return register_widget("AdWidget");' ) );