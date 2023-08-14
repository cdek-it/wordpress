<?php

namespace Cdek\UI;

use Cdek\Loader;

class Leaflet {
    public static function registerScripts(): void {
        wp_enqueue_script('leaflet', Loader::getPluginUrl().'assets/js/lib/leaflet-src.min.js');
        wp_enqueue_script('leaflet-cluster', Loader::getPluginUrl().'assets/js/lib/leaflet.markercluster-src.min.js');
    }

    public static function registerStyles(): void {
        wp_enqueue_style('leaflet', Loader::getPluginUrl().'assets/css/leaflet.css');
        wp_enqueue_style('leaflet-cluster-default',
            Loader::getPluginUrl().'assets/css/MarkerCluster.Default.min.css');
        wp_enqueue_style('leaflet-cluster', Loader::getPluginUrl().'assets/css/MarkerCluster.min.css');
    }

    public function __invoke(): void {
        add_action('admin_enqueue_scripts', [__CLASS__, 'registerScripts']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'registerStyles']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'registerScripts']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'registerStyles']);
    }
}
