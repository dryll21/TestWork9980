<?php
/**
 * Storefront engine room
 *
 * @package storefront
 */

/**
 * Assign the Storefront version to a var
 */
$theme              = wp_get_theme( 'storefront' );
$storefront_version = $theme['Version'];

/**
 * Set the content width based on the theme's design and stylesheet.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 980; /* pixels */
}

$storefront = (object) array(
	'version'    => $storefront_version,

	/**
	 * Initialize all the things.
	 */
	'main'       => require 'inc/class-storefront.php',
	'customizer' => require 'inc/customizer/class-storefront-customizer.php',
);

require 'inc/storefront-functions.php';
require 'inc/storefront-template-hooks.php';
require 'inc/storefront-template-functions.php';
require 'inc/wordpress-shims.php';

if ( class_exists( 'Jetpack' ) ) {
	$storefront->jetpack = require 'inc/jetpack/class-storefront-jetpack.php';
}

if ( storefront_is_woocommerce_activated() ) {
	$storefront->woocommerce            = require 'inc/woocommerce/class-storefront-woocommerce.php';
	$storefront->woocommerce_customizer = require 'inc/woocommerce/class-storefront-woocommerce-customizer.php';

	require 'inc/woocommerce/class-storefront-woocommerce-adjacent-products.php';

	require 'inc/woocommerce/storefront-woocommerce-template-hooks.php';
	require 'inc/woocommerce/storefront-woocommerce-template-functions.php';
	require 'inc/woocommerce/storefront-woocommerce-functions.php';
}

if ( is_admin() ) {
	$storefront->admin = require 'inc/admin/class-storefront-admin.php';

	require 'inc/admin/class-storefront-plugin-install.php';
}

/**
 * NUX
 * Only load if wp version is 4.7.3 or above because of this issue;
 * https://core.trac.wordpress.org/ticket/39610?cversion=1&cnum_hist=2
 */
if ( version_compare( get_bloginfo( 'version' ), '4.7.3', '>=' ) && ( is_admin() || is_customize_preview() ) ) {
	require 'inc/nux/class-storefront-nux-admin.php';
	require 'inc/nux/class-storefront-nux-guided-tour.php';
	require 'inc/nux/class-storefront-nux-starter-content.php';
}

/**
 * Note: Do not add any custom code here. Please use a custom plugin so that your customizations aren't lost during updates.
 * https://github.com/woocommerce/theme-customisations
 */

function create_cities_cpt() {
    $labels = array(
        'name' => __( 'Cities' ),
        'singular_name' => __( 'City' ),
    );
    
    $args = array(
        'labels' => $labels,
        'public' => true,
        'supports' => array( 'title', 'editor', 'thumbnail' ),
        'has_archive' => true,
        'rewrite' => array('slug' => 'cities'),
    );
    
    register_post_type( 'cities', $args );
}
add_action( 'init', 'create_cities_cpt' );

function add_cities_meta_box() {
    add_meta_box(
        'city_lat_lon',
        'City Latitude and Longitude',
        'display_city_meta_box',
        'cities',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'add_cities_meta_box' );

function display_city_meta_box( $post ) {
    $lat = get_post_meta( $post->ID, 'latitude', true );
    $lon = get_post_meta( $post->ID, 'longitude', true );
    ?>
    <label for="latitude">Latitude:</label>
    <input type="text" name="latitude" value="<?php echo esc_attr( $lat ); ?>" />
    <br/>
    <label for="longitude">Longitude:</label>
    <input type="text" name="longitude" value="<?php echo esc_attr( $lon ); ?>" />
    <?php
}

function save_city_meta( $post_id ) {
    if ( isset( $_POST['latitude'] ) ) {
        update_post_meta( $post_id, 'latitude', sanitize_text_field( $_POST['latitude'] ) );
    }
    if ( isset( $_POST['longitude'] ) ) {
        update_post_meta( $post_id, 'longitude', sanitize_text_field( $_POST['longitude'] ) );
    }
}
add_action( 'save_post', 'save_city_meta' );

function create_countries_taxonomy() {
    $labels = array(
        'name' => _x( 'Countries', 'taxonomy general name' ),
        'singular_name' => _x( 'Country', 'taxonomy singular name' ),
    );
    
    $args = array(
        'hierarchical' => true,
        'labels' => $labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array( 'slug' => 'countries' ),
    );
    
    register_taxonomy( 'countries', array( 'cities' ), $args );
}
add_action( 'init', 'create_countries_taxonomy' );

class City_Temperature_Widget extends WP_Widget {
    function __construct() {
        parent::__construct(
            'city_temperature_widget',
            __( 'City Temperature', 'text_domain' ),
            array( 'description' => __( 'Displays a city name and temperature.', 'text_domain' ) )
        );
    }

    public function widget( $args, $instance ) {
        $city_id = ! empty( $instance['city_id'] ) ? $instance['city_id'] : '';
        $latitude = get_post_meta( $city_id, 'latitude', true );
        $longitude = get_post_meta( $city_id, 'longitude', true );

        // Fetch temperature using OpenWeatherMap API
        $api_key = 'fdc6190c6fc2c91e46f1f4d2915a795a';
        $response = wp_remote_get( "http://api.openweathermap.org/data/2.5/weather?lat={$latitude}&lon={$longitude}&units=metric&appid={$api_key}" );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        echo $args['before_widget'];
        echo $args['before_title'] . get_the_title( $city_id ) . $args['after_title'];
        echo '<p>Temperature: ' . $data->main->temp . '°C</p>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $city_id = isset( $instance['city_id'] ) ? $instance['city_id'] : '';
        ?>
        <label for="<?php echo $this->get_field_id( 'city_id' ); ?>">City:</label>
        <select name="<?php echo $this->get_field_name( 'city_id' ); ?>" id="<?php echo $this->get_field_id( 'city_id' ); ?>">
            <?php
            $cities = get_posts( array( 'post_type' => 'cities', 'numberposts' => -1 ) );
            foreach ( $cities as $city ) {
                echo '<option value="' . $city->ID . '"' . selected( $city_id, $city->ID, false ) . '>' . $city->post_title . '</option>';
            }
            ?>
        </select>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['city_id'] = ( ! empty( $new_instance['city_id'] ) ) ? strip_tags( $new_instance['city_id'] ) : '';
        return $instance;
    }
}
function get_city_temperature( $latitude, $longitude ) {
    $api_key = 'fdc6190c6fc2c91e46f1f4d2915a795a';
    $api_url = "https://api.openweathermap.org/data/2.5/weather?lat={$latitude}&lon={$longitude}&units=metric&appid={$api_key}";

    $response = wp_remote_get( $api_url );

    if ( is_wp_error( $response ) ) {
        return 'N/A';  // Return N/A if there is an error with the request
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body );

    if ( isset( $data->main->temp ) ) {
        return round( $data->main->temp ) . '°C';
    } else {
        return 'N/A';  // Return N/A if temperature is not available
    }
}
function register_city_temperature_widget() {
    register_widget( 'City_Temperature_Widget' );
}
add_action( 'widgets_init', 'register_city_temperature_widget' );

function search_cities_ajax() {
    global $wpdb;
    $query = esc_sql( $_POST['query'] );
    $results = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_title LIKE '%$query%' AND post_type = 'cities'" );

    // Output the table rows dynamically
    if ( $results ) {
        foreach ( $results as $city ) {
            echo '<tr>';
            echo '<td>' . esc_html( $city->post_title ) . '</td>';
            // Add other table data
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5">No cities found.</td></tr>';
    }
    wp_die();
}
add_action( 'wp_ajax_search_cities', 'search_cities_ajax' );
add_action( 'wp_ajax_nopriv_search_cities', 'search_cities_ajax' );

// Function for the 'before_cities_table' hook
function custom_before_table_action() {
    echo '<p>This is displayed before the cities table.</p>';
}
add_action( 'before_cities_table', 'custom_before_table_action' );

// Function for the 'after_cities_table' hook
function custom_after_table_action() {
    echo '<p>This is displayed after the cities table.</p>';
}
add_action( 'after_cities_table', 'custom_after_table_action' );


