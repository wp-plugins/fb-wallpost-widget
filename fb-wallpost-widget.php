<?php
/*
Plugin Name: FB Wallpost Widget
Plugin URI: http://wordpress.org/extend/plugins/fb-wallpost-widget/
Description: Widget that displays latest wall posts from a Facebook page without any hassle.
Version: 0.3.1
Author: Bjørn Johansen
Author URI: http://twitter.com/bjornjohansen
License: GPL2
Text Domain: fb-wallpost-widget
Domain Path: /languages/

    Copyright 2013 Bjørn Johansen (email : post@bjornjohansen.no)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/


class FB_Wallpost_Widget extends WP_Widget {

	function __construct() {
		parent::__construct( false, __( 'Facebook Wall Post', 'fb-wallpost-widget' ), array( 'description' => __( 'Display latest wall posts from a Facebook page', 'fb-wallpost-widget' ) ) );
	}

	public static function read_rss( $url ) {
		// Create a stream so that we can set a User-Agent Facebook accepts
		$opts = array(
			'http' => array(
				'method' => "GET",
				'header' => "Accept-language: en\r\n" .
							"User-Agent: Mozilla/5.0 (MSIE 9.0; Windows NT 6.1; Trident/5.0)\r\n"
			)
		);
		$context = stream_context_create( $opts );
		$contents = @file_get_contents( $url, false, $context );
		$xml = @simplexml_load_string( $contents) ;
		return $xml;
	}

	public static function instance_transient_key( $instance ) {
		return 'fbwallfeed' . $instance['FB_UID'] . $instance['display'] . $instance['num_posts'];
	}

	
	function widget( $args, $instance ) {
		extract( $args );

		if ( ! isset( $instance['FB_UID'] ) ) {
			return;
		}

		if ( ! isset( $instance['title'] ) ) {
			$instance['title'] = '';
		}

		if ( ! isset( $instance['display'] ) ) {
			$instance['display'] = 'title';
		}

		if ( ! isset( $instance['num_posts'] ) ) {
			$instance['num_posts'] = 1;
		}

		$transient_key = self::instance_transient_key( $instance );

		if ( false === ( $fb_content = get_transient( $transient_key ) ) ) {

			$fb_content_items = array();

			$rss = self::read_rss( 'http://www.facebook.com/feeds/page.php?format=atom10&id=' . $instance['FB_UID'] );

			if ( ! $rss ) {
				return;
			}

			for ( $i = 0, $c = count( $rss->entry ); $i < $c && count( $fb_content_items ) < $instance['num_posts']; $i++ ) {

				if ( 'title' == $instance['display'] ) {
					if ( strlen( trim( $rss->entry[$i]->title ) ) ) {
						$fb_content_items[] = sprintf( '<a href="%s" target="_blank">%s</a>', $rss->entry[$i]->link['href'], $rss->entry[$i]->title );
					}
				} else {
					$fb_content_items[] = $rss->entry[$i]->content;
				}
			}

			$fb_content = '';
			if ( count( $fb_content_items ) ) {
				$fb_content = sprintf( '<ul class="fb-wallposts"><li class="fb-wallpost">%s</li></ul>', implode( '</li><li class="fb-wallpost">', $fb_content_items ) );
				set_transient( $transient_key, $fb_content, 3600 );
			}
			
		}


		echo $before_widget;
		echo $before_title . $instance['title'] . $after_title;
		
		echo $fb_content;

		echo $after_widget;
	
	}
	
	function update( $new_instance, $old_instance ) {

		if ( $new_instance['fb_page_url'] != $old_instance['fb_page_url'] ) {
			$graphdata = json_decode( file_get_contents( 'http://graph.facebook.com/?id=' . $new_instance['fb_page_url'] ) );
			$new_instance['FB_UID'] = $graphdata->id;
		} else {
			$new_instance['FB_UID'] = $old_instance['FB_UID'];
		}

		$transient_key = self::instance_transient_key( $new_instance );
		delete_transient( $transient_key );

		return $new_instance;
	}
	
	function form( $instance ) {

		if ( ! isset( $instance['title'] ) ) {
			$instance['title'] = '';
		}
		if ( ! isset( $instance['fb_page_url'] ) ) {
			$instance['fb_page_url'] = '';
		}
		if ( ! isset( $instance['display'] ) ) {
			$instance['display'] = 'title';
		}
		if ( ! isset( $instance['num_posts'] ) ) {
			$instance['num_posts'] = 1;
		}

		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">
				<?php _e( 'Title', 'fb-wallpost-widget' ); ?>:
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>">
			</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'fb_page_url' ); ?>">
				<?php _e( 'FB Page URL', 'fb-wallpost-widget' ); ?>:
				<input class="widefat" id="<?php echo $this->get_field_id( 'fb_page_url' ); ?>" name="<?php echo $this->get_field_name( 'fb_page_url' ); ?>" type="text" value="<?php echo esc_attr( $instance['fb_page_url'] ); ?>">
			</label>
			<div class="description"><?php _e( 'Example: http://www.facebook.com/metronet/', 'fb-wallpost-widget' ); ?></div>
		</p>
		<?php if ( false ) : /* Not a good idea to show full post ATM, due to the links FB creates in the content. But we'll keep the code here to be ready if it'll ever change. */ ?>
		<p>
			<label for="<?php echo $this->get_field_id( 'display' ); ?>">
				<?php _e( 'Display', 'fb-wallpost-widget' ); ?>:
				<select id="<?php echo $this->get_field_id( 'display' ); ?>" name="<?php echo $this->get_field_name( 'display' ); ?>">
					<option value="title" <?php selected( $instance['display'], 'title' ); ?>><?php _e( 'Title (with link)', 'fb-wallpost-widget' ); ?></option>
					<option value="fullpost" <?php selected( $instance['display'], 'fullpost' ); ?>><?php _e( 'Full post', 'fb-wallpost-widget' ); ?></option>
				</select>
			</label>
		</p>
		<?php endif; ?>
		<p>
			<label for="<?php echo $this->get_field_id( 'num_posts' ); ?>">
				<?php _e( 'Num posts', 'fb-wallpost-widget' ); ?>:
				<select id="<?php echo $this->get_field_id( 'num_posts' ); ?>" name="<?php echo $this->get_field_name( 'num_posts' ); ?>">
					<?php for ( $i = 1, $c = 11; $i < $c; $i++ ) : ?>
						<option value="<?php echo $i; ?>" <?php selected( $instance['num_posts'], $i ); ?>><?php echo $i; ?></option>
					<?php endfor; ?>
				</select>
			</label>
		</p>
		<?php
	}
}

add_action( 'plugins_loaded', create_function( '', 'load_plugin_textdomain( "fb-wallpost-widget", false, dirname( plugin_basename( __FILE__ ) ) . "/languages/" );' ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("FB_Wallpost_Widget");' ) );

