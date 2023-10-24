<?php
/** @var $orderNumber */
/** @var $orderUuid */
/** @var $dateMin */
/** @var $dateMax */
/** @var $courierNumber */
/** @var $fromDoor */
?>

<div id="cdek-courier-block">
    <div>
        <div>
            <p>Дата ожидания курьера:</p>
            <input id="cdek-courier-date" type="date" min='<?php echo $dateMin; ?>' max='<?php echo $dateMax; ?>'>
        </div>
        <div>
            <p>Время ожидания курьера:</p>
            <label for="cdek-courier-startime">с</label>
            <input id="cdek-courier-startime" type="time" list="avail">
            <label for="cdek-courier-endtime">по</label>
            <input id="cdek-courier-endtime" type="time" list="avail">
            <datalist id="avail">
                <option value="09:00">
                <option value="10:00">
                <option value="11:00">
                <option value="12:00">
                <option value="13:00">
                <option value="14:00">
                <option value="15:00">
                <option value="16:00">
                <option value="17:00">
                <option value="18:00">
                <option value="19:00">
                <option value="20:00">
                <option value="21:00">
                <option value="22:00">
            </datalist>
        </div>
    </div>
    <input id="cdek-courier-name" type="text" placeholder="ФИО">
    <input id="cdek-courier-phone" type="tel" min="0" placeholder="Телефон">
    <?php $tip = "Должен передаваться в международном формате: код страны (для России +7) и сам номер (10 и более цифр)"; echo wc_help_tip($tip, false);?>
    <input id="cdek-courier-address" title="tooltip" type="text" placeholder="Адрес">
    <label for="cdek-courier-address">
        <?php $tip = "Город берется из настроек плагина. В поле 'Адрес' вводится только улица, дом, кв"; echo wc_help_tip($tip, false);?>
    </label>
    <input id="cdek-courier-comment" type="text" placeholder="Комментарий">
    <?php if (!$fromDoor) { ?>
    <input id="cdek-courier-package-desc" type="text" placeholder="Описание груза">
    <div>
        <div style="display: inline-flex; margin-top: 5px; align-items: center;">
            <p style="margin: auto">Габариты</p>
            <?php $tip = "Для тарифов 'От склада' можно отправить сразу несколько заказов. 
                    Поэтому габариты могут отличаться от тех что указывались при создании заказа. 
                    Для тарифов 'От двери' можно продублировать те что указывались при создании заказа"; echo wc_help_tip($tip, false);?>
        </div>

        <input id="cdek-courier-weight" type="number" min="0" placeholder="Вес в кг">
        <input id="cdek-courier-length" type="number" min="0" placeholder="Длина в см">
        <input id="cdek-courier-width" type="number"  min="0"placeholder="Ширина в см">
        <input id="cdek-courier-height" type="number" min="0" placeholder="Высота в см">
    </div>
    <?php } ?>
    <div>
        <label for="cdek-courier-startime">Необходим звонок</label>
        <input id="cdek-courier-call" type="checkbox">
    </div>
    <p id="cdek-courier-error" style="display: none"></p>
    <input id="cdek-courier-send-call" class="button save_order button-primary" type="button" value="Отправить">
</div>
