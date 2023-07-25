<?php
/**
 * @var $layerMap
 * @var $postamat
 * @var $weight
 */
?>
<div class="open-pvz-btn" data-layer-map="<?php echo $layerMap ?>" data-postamat="<?php echo $postamat; ?>"
     data-weight="<?php echo $weight; ?>">Выбрать ПВЗ
</div>
<div>
    <input name="pvz_info" id="pvz-info" type="hidden">
    <input name="pvz_code" id="pvz-code" type="hidden">
    <input name="city_code" id="city-code" type="hidden">
</div>

<div id="map-frame" style="">
    <div id="main-map-container">
        <div id="map-pvz-list">
            <div id="map-pvz-list-search-container">
                <input id="map-pvz-list-search" type="text" placeholder="Введите название улицы">
                <input id="map-pvz-list-search-clear" type="button" value="x">
            </div>
            <div id="map-pvz-list-container">
                <ul id="map-pvz-item-list" class="item-list"></ul>
            </div>
        </div>
        <div id="map-container">
            <div id="cdek-map">
                <div id="map-loader"></div>
            </div>
        </div>
    </div>
    <div id="background"></div>
</div>

<?php ?>
