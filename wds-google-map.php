<?php
/*
Plugin Name: WDS Google Maps
Plugin URI: http://webdevstudios.com/support/wordpress-plugins/
Description: Plugin allows posts to be linked to specific addresses and coordinates and display plotted on a Google Map.  Use shortcode [google-map] to display map directly in your post/page.  Map shows plots for each address added to the post you are viewing.
Version: 1.0
Author: WebDevStudios.com
Author URI: http://webdevstudios.com
License: GPLv2
*/

class WDS_GoogleMaps {

    function __construct() {

        $this->wdsgmap_version = '1.0';

        $this->latitude           = '';
        $this->longitude          = '';
        $this->default_post_types = array( 'post', 'page' );

        $this->hooks();

    }


    /**
     * Add any hooks into WordPress here
     *
     * @since  1.0
     *
     * @return void
     */
    public function hooks() {

        add_action( 'add_meta_boxes', array( $this, 'map_metabox_add' ) );
        add_action( 'save_post', array( $this, 'map_metabox_save' ) );
        add_action( 'init', array( $this, 'register_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
        add_action( 'wp_ajax_wds_gmap_address_search', array( $this, 'google_address_search' ) );
        add_shortcode( 'wds-gmap', array( $this, 'wds_gmap_shortcode' ) );

    }


    public function register_scripts() {

        wp_register_script( 'google-maps', 'http://maps.google.com/maps/api/js?sensor=false', '', '20130224', true );

    }


    /**
     * Enqueue scripts for admin screens
     *
     * @since  1.0
     *
     * @return void
     */
    public function admin_scripts() {

        wp_enqueue_script( 'google-maps' );
        wp_enqueue_script( 'wds-google-maps', plugin_dir_url( __FILE__ ) . 'js/wdsgmap-admin.js', 'google-maps', '', true );
        wp_localize_script( 'wds-google-maps', 'wdsgmapAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );

    }


    /**
     * Add map metabox
     *
     * @since  1.0
     *
     * @return void
     */
    public function map_metabox_add() {

        $post_types = apply_filters( 'wdsgmap_post_types', $this->default_post_types );

        foreach ( $post_types as $post_type ) {
            add_meta_box( 'wdsgmap_meta_box', __( 'Location Map', 'wds-gmap' ), array( $this, 'map_metabox_show' ), $post_type );
        }

    }


    /**
     * Output metabox markup
     *
     * @since  1.0
     *
     * @return void
     */
    public function map_metabox_show() {

        global $post;

        $fields = $this->map_metabox_get_fields( $post->ID );

        ?>
        <h4><?php _e( 'Enter an address to plot on a Google Map.', 'wds-gmap' ) ?></h4>
        <div style="padding-bottom:20px;" class="wdsgmap_admin_map">
            <?php echo $this->map_markup( array( 'height' => '400px' ), true ); ?>
        </div>
        <table style="padding-bottom:10px">
            <tr>
                <th scope="row" style="text-align:right;"><label for="wdsgmap_address"><?php _e( 'Address Lookup', 'wds-gmap' ) ?></label></th>
                <td>
                    <input type="hidden" name="wdsgmap_nonce" value="<?php echo wp_create_nonce( 'wdsgmap_details' ); ?>" />
                    <input type="text" id="wdsgmap_address" name="wdsgmap_address" size="60" value="<?php echo sanitize_text_field( $fields['address'] ); ?>" />
                    <a id="wdsgmap_address_search_submit" class="button" /><?php _e( 'Search', 'wds-gmap' ) ?></a>
                    <a id="wdsgmap_address_clear" class="button" /><?php _e( 'Clear Map', 'wds-gmap' ) ?></a>
                    <input type="hidden" id="wdsgmap_latitude" name="wdsgmap_latitude" value="<?php echo sanitize_text_field( $fields['latitude'] ); ?>" />
                    <input type="hidden" id="wdsgmap_longitude" name="wdsgmap_longitude" value="<?php echo sanitize_text_field( $fields['longitude'] ); ?>" />
                </td>
            </tr>
        </table>
        <?php

    }


    /**
     * Save map metabox
     *
     * @since  1.0
     *
     * @param  int  $post_id The ID of the post we're saving
     *
     * @return int           The ID of the post we're saving
     */
    public function map_metabox_save( $post_id ) {

        // Verify nonce
        if ( ! isset( $_POST['wdsgmap_nonce'] ) || ! wp_verify_nonce( $_POST['wdsgmap_nonce'], 'wdsgmap_details' ) )
            return $post_id;

        // Make sure we're not doing an autosave
        if ( defined('DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return $post_id;

        // Check user permissions
        if ( !current_user_can( 'edit_post', $post_id ) )
            return $post_id;

        // Sanitize our fields
        $fields = $this->map_metabox_sanitize_fields();

        // If we have fields, save them, if not, attempt to delete them
        $meta = ( ! empty( $fields ) ? update_post_meta( $post_id, '_wdsgmap_details', $fields ) : delete_post_meta( $post_id, '_wdsgmap_details' ) );

        return $post_id;

    }


    /**
     * Sanitize metabox input fields
     *
     * @since  1.0
     *
     * @return array  An array of sanitized fields
     */
    private function map_metabox_sanitize_fields() {

        $fields = array();

        // Sanitize our input fields
        if ( $_POST['wdsgmap_address'] && $_POST['wdsgmap_latitude'] && $_POST['wdsgmap_longitude'] ) {
            $fields['address']   = sanitize_text_field( $_POST['wdsgmap_address'] );
            $fields['latitude']  = sanitize_text_field( $_POST['wdsgmap_latitude'] );
            $fields['longitude'] = sanitize_text_field( $_POST['wdsgmap_longitude'] );
        }

        return $fields;

    }


    /**
     * Get and set our field defaults for metabox output
     *
     * @since  1.0
     *
     * @param  int  $post   The post ID we're displaying our metabox on
     *
     * @return array        An array of fields
     */
    private function map_metabox_get_fields( $post_id = '' ) {

        $fields = get_post_meta( $post_id, '_wdsgmap_details', true );

        $fields['address']   = ( ! empty( $fields['address'] ) ? $fields['address'] : '' );
        $fields['latitude']  = ( ! empty( $fields['latitude'] ) ? $fields['latitude'] : '' );
        $fields['longitude'] = ( ! empty( $fields['longitude'] ) ? $fields['longitude'] : '' );

        //wp_die( var_dump( $fields ) );
        return $fields;

    }


    /**
     * AJAX callback to lookup an address on Google
     *
     * @since  1.0
     *
     * @return void
     */
    public function google_address_search() {

        //Empty vars in case we return nothing
        $latitude  = '';
        $longitude = '';

        // Sanitize our input
        $address = sanitize_text_field( $_REQUEST['address'] );

        $url = 'http://maps.google.com/maps/geo?q=' . urlencode( $address );

        //use the WordPress HTTP API to call the Google Maps API and get coordinates
        $result = wp_remote_get( esc_url( $url ) );

        if( ! is_wp_error( $result ) ) {

            $json = json_decode( $result['body'] );

            //set lat/long for address from JSON response
            $latitude  = $json->Placemark[0]->Point->coordinates[1];
            $longitude = $json->Placemark[0]->Point->coordinates[0];

        }

        // Send back our coordinates
        echo json_encode( array( 'latitude' => $latitude, 'longitude' => $longitude ) );
        die();

    }


    /**
     * Helper to generate our map markup
     *
     * @since  1.0
     *
     * @param  string  $height Height of our map container div
     * @param  string  $width  Width of our map container div
     *
     * @return string          A string of html markup
     */
    private function map_markup( $size = array(), $admin = false ) {

        // Check if we're passing custom values in our shortcode
        $custom = ( ! empty( $size['height'] ) || ! empty ( $size['width'] ) ? true : false );

        // Set defaults if empty
        if ( empty($size['height'] ) )
            $size['height'] = '200px';

        if ( empty($size['width'] ) )
            $size['width'] = '100%';

        //Don't apply our default filter if we pass a size or if we're calling our edit scren map
        if ( ! $admin && ! $custom )
            $size = apply_filters( 'wdsgmap_map_size', $size );

        // Return our map container markup
        return '<div id="map_canvas" style="height:' . esc_attr( $size['height'] ) . '; width:' . esc_attr( $size['width'] ) . ';"></div>';

    }


    /**
     * Google Map shortcode handler
     *
     * @since  1.0
     *
     * @param  array  $atts An array of shortcode attributes
     *
     * @return string       A string of html markup on success, empty on failure
     */
    public function wds_gmap_shortcode( $atts ) {

        // Try and grab our $post object.
        global $post;

        $map = '';

        // Set our shortcode args blank if not passed in
        extract( shortcode_atts( array(
            'height' => '',
            'width'  => '',
        ), $atts ) );

        // See if we have map post meta
        $coordinates = get_post_meta( $post->ID, '_wdsgmap_details', true);

        // If so, generate our map output
        if ( $coordinates['latitude'] && $coordinates['longitude'] ) {

            // Set our variables
            $this->latitude = $coordinates['latitude'];
            $this->longitude = $coordinates['longitude'];

            // Generate our base map markup to return
            $map = $this->map_markup( array( 'height' => $height, 'width' => $width ) );

            // Enqueue our google map js
            wp_enqueue_script( 'google-maps' );

            // Add our script tag to the footer
            add_action( 'wp_footer', array( $this, 'map_script' ), 30 );

        }

        return $map;

    }


    /**
     * Javascript to generate our map and plot our maker
     *
     * @since  1.0
     *
     * @return void
     */
    public function map_script() {

        $script  = '<script type="text/javascript">';
        $script .= 'function wds_gmap_initialize() {';
        $script .= '    var map = new google.maps.Map(document.getElementById(\'map_canvas\'),{';
        $script .= '        mapTypeId: google.maps.MapTypeId.ROADMAP';
        $script .= '    });';
        $script .= '    var marker = new google.maps.Marker({';
        $script .= '        position: new google.maps.LatLng(' . esc_js( $this->latitude ) . ',' . esc_js( $this->longitude ) . ')';
        $script .= '    });';
        $script .= '    map.setCenter(marker.position);';
        $script .= '    map.setZoom(16);';
        $script .= '    marker.setMap(map);';
        $script .= '}';
        $script .= 'setTimeout( "wds_gmap_initialize()", 10 );';
        $script .= '</script>';

        echo $script;

    }

}

$wds_gmap = new WDS_GoogleMaps();
