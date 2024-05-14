<?php
defined('ABSPATH') or exit;
/**
 * @var $layerMap
 * @var $points
 * @var $city
 * @var $mapAutoClose
 * @var $cityInput
 */
?>
<div class="open-pvz-btn"
     data-points="<?php
     echo esc_attr($points) ?>"
    data-city="<?php echo esc_attr($cityInput)?>"><?php _e('Choose pick-up', 'cdekdelivery') ?>
</div>
<input name="pvz_code" class="cdek-office-code" type="hidden" data-map-auto-close="<?php echo esc_attr($mapAutoClose)
?>">
