<?php
/* =========================================
 * Enqueues child theme stylesheet
 * ========================================= */

function rt_reservation_page_styles() {
	$style_path = get_stylesheet_directory() . '/style.css'; // File system path
	$style_uri  = get_stylesheet_directory_uri() . '/style.css'; // URL
	

	$version = file_exists($style_path) ? filemtime($style_path) : null;

	//wp_enqueue_style( 'gosolar-zozo-child-style', $style_uri, array(), $version );
	wp_enqueue_style( 'bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array(), '5.3.3' );

}
add_action( 'wp_enqueue_scripts', 'rt_reservation_page_styles', 30 );



/* Reservation Page and API */

function load_reservation_scripts() {
    if (is_page_template('page-reservation.php')) {
        wp_enqueue_script('vue-js', 'https://cdn.jsdelivr.net/npm/vue@2', [], null, true);

        $script_path = get_stylesheet_directory() . '/js/reservation-app.js';
        if (file_exists($script_path)) {
            wp_enqueue_script(
                'reservation-app',
                get_stylesheet_directory_uri() . '/js/reservation-app.js',
                ['vue-js'],
                filemtime($script_path),
                true
            );
            wp_localize_script('reservation-app', 'reservationData', [
                'root' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest')
            ]);
        } else {
            error_log('reservation-app.js not found at ' . $script_path);
        }
    }
}
add_action('wp_enqueue_scripts', 'load_reservation_scripts');



add_action('rest_api_init', function () {
    register_rest_route('reservation/v1', '/submit', [
        'methods' => 'POST',
        'callback' => 'handle_reservation_submission',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('reservation/v1', '/check', [
        'methods' => 'GET',
        'callback' => 'handle_reservation_check',
        'permission_callback' => '__return_true',
    ]);
});

function handle_reservation_submission($request) {
    $params = $request->get_json_params();
    $name = sanitize_text_field($params['name']);
    $phone = sanitize_text_field($params['phone']);
    $email = sanitize_email($params['email']);
    $arrival = sanitize_text_field($params['arrival']);
    $leaving = sanitize_text_field($params['leaving']);
    $room_type = sanitize_text_field($params['room_type']);


    $today = date('Y-m-d');
    if ($arrival < $today || $leaving < $today || $leaving < $arrival) {
        return new WP_Error('invalid_date', 'Dates must be valid and in the future', ['status' => 400]);
    }

    $post_id = wp_insert_post([
        'post_type' => 'reservation',
        'post_status' => 'pending',
        'post_title' => $name,
        'meta_input' => [
            'phone' => $phone,
            'email' => $email,
            'arrival_date' => $arrival,
            'leaving_date' => $leaving,
            'room_type' => $room_type
        ]
    ]);

    if (is_wp_error($post_id)) {
        return new WP_Error('insert_error', 'Could not create reservation', ['status' => 500]);
    }
    
    $headers = array('From: Guest House "Lodoz" <info@lodoz-obzor.com>');

   wp_mail($email, 'Your Reservation is Pending/Вашата резервация е в процес на обработка', 
    "Hi $name,\n\nYour reservation has been received/Вашата резервация бе получена:\n" .
    "Room Type/Тип стая: $room_type\nArrival/Пристигане: $arrival\nLeaving/Напускане: $leaving\n\nWe'll contact you to confirm it shortly./Очаквайте да се свържем с Вас за потвърждение", $headers);

    wp_mail(get_option('admin_email'), 'New Reservation Received/Получихте Нова резервация',
    "A new reservation has been submitted:\n\nName: $name\nPhone: $phone\nEmail: $email\n" .
    "Room Type: $room_type\nArrival: $arrival\nLeaving: $leaving\n\nCheck admin panel to approve.", $headers);


    return ['success' => true];
}

function handle_reservation_check($request) {
    $email = sanitize_email($request->get_param('email'));
    $arrival = sanitize_text_field($request->get_param('arrival'));

    $query = new WP_Query([
        'post_type' => 'reservation',
        'post_status' => ['publish', 'pending', 'draft'],
        'meta_query' => [
            ['key' => 'email', 'value' => $email],
            ['key' => 'arrival_date', 'value' => $arrival],
        ]
    ]);

    if ($query->have_posts()) {
        $post = $query->posts[0];
        return ['status' => get_post_status($post)];
    }

    return new WP_Error('not_found', 'Reservation not found', ['status' => 404]);
}

/* Register custom post type */

add_action('init', 'register_reservation_post_type');

function register_reservation_post_type()
{
    register_post_type('reservation', [
        'labels' => [
            'name' => 'Reservations',
            'singular_name' => 'Reservation'
        ],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title', 'custom-fields']
    ]);
}



/* Room Types */

add_filter('manage_reservation_posts_columns', function($columns) {
    $columns['room_type'] = 'Room Type';
    return $columns;
});

add_action('manage_reservation_posts_custom_column', function($column, $post_id) {
    if ($column === 'room_type') {
        echo esc_html(get_post_meta($post_id, 'room_type', true));
    }
}, 10, 2);


// Add a new column
add_filter('manage_reservation_posts_columns', 'add_reservation_status_column');
function add_reservation_status_column($columns) {
    $columns['reservation_status'] = 'Status';
    return $columns;
}

// Show content in the new column
add_action('manage_reservation_posts_custom_column', 'show_reservation_status_column', 10, 2);
function show_reservation_status_column($column, $post_id) {
    if ($column === 'reservation_status') {
        echo ucfirst(get_post_status($post_id));
    }
}

// Add custom statuses
function register_custom_reservation_statuses() {
    register_post_status('confirmed', [
        'label'                     => _x('Confirmed', 'post'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Confirmed <span class="count">(%s)</span>', 'Confirmed <span class="count">(%s)</span>'),
    ]);

    register_post_status('cancelled', [
        'label'                     => _x('Cancelled', 'post'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>'),
    ]);
}
add_action('init', 'register_custom_reservation_statuses');

//Show custom status

add_filter('display_post_states', 'add_custom_reservation_states');
function add_custom_reservation_states($states) {
    global $post;

    if ($post->post_type == 'reservation') {
        if ($post->post_status == 'confirmed') {
            $states[] = __('Confirmed');
        }
        if ($post->post_status == 'cancelled') {
            $states[] = __('Cancelled');
        }
    }

    return $states;
}

// Show custom post statuses in the dropdown list with statuses

add_action('post_submitbox_misc_actions', 'add_custom_status_to_dropdown');
function add_custom_status_to_dropdown() {
    global $post;
    
    // Only for 'reservation' post type
    if ($post->post_type !== 'reservation') return;

    $current = $post->post_status;
    $custom = ['confirmed' => 'Confirmed', 'cancelled' => 'Cancelled'];

    echo '<script>';
    echo 'jQuery(document).ready(function($){';
    foreach ($custom as $status => $label) {
        echo '$("<option>").val("' . esc_js($status) . '").text("' . esc_js($label) . '").appendTo("#post_status");';
    }

    // Update label to reflect current custom status
    if (array_key_exists($current, $custom)) {
        echo '$("#post-status-display").text("' . esc_js($custom[$current]) . '");';
    }
    echo '});';
    echo '</script>';
}

// Show the statuses in the admin post list
add_filter('display_post_states', function ($states) {
    global $post;
    if ($post->post_type === 'reservation') {
        if ($post->post_status === 'confirmed') {
            $states[] = 'Confirmed';
        } elseif ($post->post_status === 'cancelled') {
            $states[] = 'Cancelled';
        }
    }
    return $states;
});

