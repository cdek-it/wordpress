<?php

namespace Cdek;

use WC_Shipping_Method;
use Cdek\Model\FieldObjArray;

class CdekShippingMethod extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        parent::__construct($instance_id);
        $this->id = 'official_cdek';
        $this->instance_id = absint($instance_id);
        $this->method_title = 'Cdek Shipping';
        $this->method_description = 'Custom Shipping Method for Cdek';
        $this->supports = array(
            'settings',
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        $this->enabled = 'yes';
        $this->init();
    }

    public function init()
    {
        $this->title = 'CDEK Shipping';
        $this->init_settings();
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        $this->init_form_fields();
    }

    public function init_form_fields()
    {
        $fieldObjArray = FieldObjArray::get($this->settings);
        foreach ($fieldObjArray as $fieldObj) {
            $this->form_fields = array_merge($this->form_fields, $fieldObj->getFields());
        }
    }

    public function calculate_shipping($package = [])
    {
        $deliveryCalc = new DeliveryCalc();
        if ($deliveryCalc->calculate($package, $this->id)) {
            foreach ($deliveryCalc->rates as $rate) {
                $this->add_rate($rate);
            }
        }
    }

}