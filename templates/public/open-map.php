<?php
/**
 * @var $layerMap
 * @var $points
 * @var $city
 */
?>
<div id="pvz-info"></div>
<div class="open-pvz-btn" data-layer-map="<?= $layerMap ?>"
     data-points="<?= esc_html(json_encode($points['pvz'])) ?>">Выбрать ПВЗ
</div>
<div>
    <input name="pvz_code" id="pvz-code" type="hidden">
    <input name="city_code" id="city-code" type="hidden" value="<?= $city ?>">
</div>

<div id="map-frame" style="">
    <div id="main-map-container">
        <div id="map-pvz-list">
            <div id="map-pvz-list-search-container">
                <input id="map-pvz-list-search" type="text" placeholder="Поиск">
                <svg id="map-pvz-list-search-clear" xmlns="http://www.w3.org/2000/svg" class="icon" width="24"
                     height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path d="M3 5a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14z"></path>
                    <path d="M9 9l6 6m0 -6l-6 6"></path>
                </svg>
            </div>
            <div id="map-pvz-list-container">
                <ul id="map-pvz-item-list" class="item-list"></ul>
            </div>
        </div>
        <div id="map-container">
            <div id="cdek-map">
            </div>
        </div>
    </div>
    <div id="background"></div>
</div>
