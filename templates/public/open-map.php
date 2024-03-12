<?php
defined('ABSPATH') or exit;
/**
 * @var $layerMap
 * @var $points
 * @var $city
 * @var $mapAutoClose
 */
?>
<div class="open-pvz-btn"
     data-points="<?php
     echo esc_html($points) ?>">Выбрать ПВЗ
</div>
<input name="pvz_code" class="cdek-office-code" type="hidden" data-map-auto-close="<?php echo $mapAutoClose ?>">
