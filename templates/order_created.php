<?php
defined('ABSPATH') or exit;

use Cdek\Helpers\UI;

/**
 * @var \Cdek\Model\Order $order
 * @var \Cdek\Model\ShippingItem $shipping
 */

$intake = $order->getIntake();
?>
<div id="cdek-loader" style="display: none"></div>

<div id="cdek-info-order" <?php
if (empty($order->number)) { ?>style="display: none" <?php
} ?>>
    <div>
        <div id="cdek-order-number-block">
            <div>
                <p id="cdek-order-number">№ <b><?php
                        echo esc_html($order->number) ?></b></p>
                <div id="cdek-order-status-block">
                    <?php
                    include 'status_list.php';
                    ?>
                </div>
                <a id="cdek-order-waybill"
                   href="<?php
                   echo esc_url(UI::buildRestUrl("order/$order->id/waybill")) ?>"><?php
                    esc_html_e('Print waybill', 'cdekdelivery') ?></a>
                <a id="cdek-order-barcode"
                   href="<?php
                   echo esc_url(UI::buildRestUrl("order/$order->id/barcode")) ?>"><?php
                    esc_html_e('Print barcode', 'cdekdelivery') ?></a>
                <?php
                if (!$order->isLocked()) { ?>
                    <p id="cdek-order-courier">
                        <?php
                        esc_html_e('Call the courier', 'cdekdelivery') ?></p>
                    <?php
                } ?>
            </div>

            <div id="cdek-courier-result-block"
                 <?php
                 if (empty($intake->number)) { ?>style="display: none;" <?php
            } else { ?> style="margin-top: 10px;" <?php
            } ?>>
                <hr>
                <p id="cdek-courier-info"><?php
                    esc_html_e('Request number', 'cdekdelivery') ?>: <?php
                    echo esc_html($intake->number) ?></p>
                <p id="cdek-courier-delete"
                   data-action="<?php
                   echo esc_url(UI::buildRestUrl("order/$order->id/courier/delete")) ?>"><?php
                    esc_html_e("Cancel the courier request", 'cdekdelivery') ?></p>
            </div>

            <div id="call-courier-form">
                <?php
                include 'call_courier_form.php'; ?>
            </div>

        </div>
    </div>
    <?php
    if (!$order->isLocked()) { ?>
        <hr>
        <div>
            <p id="cdek-delete-order-error" class="form-field form-field-wide wc-order-status"
               style="display: none"></p>
            <a id="delete-order-btn" href="<?php
            echo esc_url(UI::buildRestUrl("order/$order->id/delete")) ?>"><?php
                esc_html_e("Delete waybill", 'cdekdelivery') ?></a>
        </div>
        <?php
    } ?>
</div>
