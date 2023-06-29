<?php
/** @var $order */
/** @var $orderNumber */
/** @var $orderIdWP */
/** @var $orderUuid */
/** @var $items */
/** @var $hasPackages */
?>
<?php if ($hasPackages) {
    include 'form_package_many.php';
} else {
    include 'form_package.php';
}
include 'order_created.php';
?>
