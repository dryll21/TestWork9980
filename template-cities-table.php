<?php
/* Template Name: Cities Table */
get_header();
?>

<div class="city-search-container">
    <input type="text" id="city-search" placeholder="Search cities..." />
</div>

<?php
// Hook before the table starts
do_action( 'before_cities_table' );
?>

<div id="cities-table">
    <?php
    global $wpdb;
    $results = $wpdb->get_results( "SELECT p.ID, p.post_title, t.name AS country, pm.meta_value AS latitude, pm2.meta_value AS longitude 
        FROM {$wpdb->posts} p 
        LEFT JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
        LEFT JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
        LEFT JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
        LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = 'latitude')
        LEFT JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = 'longitude')
        WHERE p.post_type = 'cities' AND p.post_status = 'publish'" );
    ?>
    <table>
        <thead>
            <tr>
                <th>City</th>
                <th>Country</th>
                <th>Latitude</th>
                <th>Longitude</th>
                <th>Temperature</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $results as $city ) : ?>
                <tr>
                    <td><?php echo esc_html( $city->post_title ); ?></td>
                    <td><?php echo esc_html( $city->country ); ?></td>
                    <td><?php echo esc_html( $city->latitude ); ?></td>
                    <td><?php echo esc_html( $city->longitude ); ?></td>
                    <td><?php  $temperature = get_city_temperature( $city->latitude, $city->longitude );
                echo esc_html( $temperature ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    $('#city-search').on('keyup', function() {
        var searchQuery = $(this).val();
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'search_cities',
                query: searchQuery
            },
            success: function(response) {
                $('#cities-table').html(response);
            }
        });
    });
});
</script>

<?php
// Hook after the table ends
do_action( 'after_cities_table' );
?>

<?php get_footer(); ?>


