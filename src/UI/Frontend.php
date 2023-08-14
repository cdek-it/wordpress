<?php
namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\UI {

    use Cdek\Loader;

    class Frontend {
        public static function registerScripts(): void {
            if(!is_checkout()) {
                return;
            }

            wp_enqueue_script('cdek-map', Loader::getPluginUrl().'assets/js/map.js', ['jquery'], Loader::getPluginVersion(),
                true);
            wp_localize_script('cdek-map', 'cdek_map', [
                'icons' => [
                    'office' => Loader::getPluginUrl().'assets/img/point.svg',
                    'postamat' => Loader::getPluginUrl().'assets/img/postamat.svg',
                ],
                'tmp_pvz_code' => rest_url('/cdek/v1/set-pvz-code-tmp'),
            ]);
        }

        public static function registerStyles(): void {
            if(!is_checkout()) {
                return;
            }

            wp_enqueue_style('cdek-css', Loader::getPluginUrl().'assets/css/cdek-map.css', [], Loader::getPluginVersion());
        }
        public function __invoke(): void {
            add_action('wp_enqueue_scripts', [__CLASS__, 'registerScripts']);
            add_action('wp_enqueue_scripts', [__CLASS__, 'registerStyles']);

            add_action('wp_enqueue_scripts', new YandexMap);
        }
    }

}
