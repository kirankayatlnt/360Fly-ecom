<?php

/**
 * Product:       Xtento_TrackingImport (2.2.1)
 * ID:            UkPw/HNCTGTNeNACl67A1tsc5/yF+olcWhzGXPJ/t28=
 * Packaged:      2016-09-21T14:35:43+00:00
 * Last Modified: 2013-11-10T16:18:43+01:00
 * File:          app/code/local/Xtento/TrackingImport/Model/Import/Condition/Address/Billing.php
 * Copyright:     Copyright (c) 2016 XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

class Xtento_TrackingImport_Model_Import_Condition_Address_Billing extends Xtento_TrackingImport_Model_Import_Condition_Object
{
    public function loadAttributeOptions()
    {
        $attributes = array(
            'postcode' => Mage::helper('salesrule')->__('Billing Postcode'),
            'region' => Mage::helper('salesrule')->__('Billing Region'),
            'region_id' => Mage::helper('salesrule')->__('Billing State/Province'),
            'country_id' => Mage::helper('salesrule')->__('Billing Country'),
        );

        $this->setAttributeOption($attributes);
        return $this;
    }

    /**
     * Validate Address Rule Condition
     *
     * @param Varien_Object $object
     * @return bool
     */
    public function validate(Varien_Object $object)
    {
        $address = $object;
        if (!$address instanceof Mage_Sales_Model_Order_Address) {
            $address = $object->getBillingAddress();
        }

        return $this->validateAttribute($address->getData($this->getAttribute()));
    }
}
