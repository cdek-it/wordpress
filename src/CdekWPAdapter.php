<?php

namespace Cdek;

class CdekWPAdapter
{
    public function init()
    {
        add_action('admin_enqueue_scripts', [$this, 'cdek_admin_enqueue_script']);
    }

    public function cdek_admin_enqueue_script()
    {
        wp_enqueue_script('cdek-admin-delivery', plugin_dir_url(__FILE__) . 'assets/js/delivery-v5.js', array('jquery'), '1.7.0', true);
        wp_enqueue_script('cdek-admin-create-order', plugin_dir_url(__FILE__) . 'assets/js/create-order.js', array('jquery'), '1.7.0', true);
        wp_enqueue_script('cdek-admin-leaflet', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet-src.min.js');
        wp_enqueue_script('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet.markercluster-src.min.js');
        wp_enqueue_style('cdek-admin-leaflet', plugin_dir_url(__FILE__) . 'assets/css/leaflet.css');
        wp_enqueue_style('cdek-admin-leaflet-cluster-default', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.Default.min.css');
        wp_enqueue_style('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.min.css');
        wp_enqueue_style('cdek-admin-delivery', plugin_dir_url(__FILE__) . 'assets/css/delivery-v3.css');
//        addYandexMap();
    }
}