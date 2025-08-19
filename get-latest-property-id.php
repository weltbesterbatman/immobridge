<?php
require_once 'wp-load.php';

$args = [
    'post_type' => 'immo_property',
    'posts_per_page' => 1,
    'orderby' => 'date',
    'order' => 'DESC',
    'fields' => 'ids',
];

$properties = new WP_Query($args);

if ($properties->have_posts()) {
    echo $properties->posts[0];
} else {
    echo 'No properties found.';
}
