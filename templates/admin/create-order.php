<?php
defined('ABSPATH') or exit;
/** @var $order */
/** @var $orderNumber */
/** @var $orderIdWP */
/** @var $orderUuid */
/** @var $items */
/** @var $hasPackages */
/** @var $dateMin */
/** @var $dateMax */
/** @var $courierNumber */
/** @var $fromDoor */
?>
<div id="cdek-loader" style="display: none"></div>
<?php
if ($hasPackages) {
    include 'form_package_many.php';
} else {
    include 'form_package.php';
}
include 'order_created.php';
