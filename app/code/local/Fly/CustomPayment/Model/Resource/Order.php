<?php
/**
 * Fly_CustomPayment extension
 * 
 * NOTICE OF LICENSE
 * 
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/mit-license.php
 * 
 * @category       Fly
 * @package        Fly_CustomPayment
 * @copyright      Copyright (c) 2017
 * @license        http://opensource.org/licenses/mit-license.php MIT License
 */
/**
 * Recurring resource model
 *
 * @category    Fly
 * @package     Fly_CustomPayment
 * @author      Ultimate Module Creator
 */
class Fly_CustomPayment_Model_Resource_Order
    extends Mage_Core_Model_Resource_Db_Abstract {
    /**
     * constructor
     * @access public
     * @author Ultimate Module Creator
     */
    public function _construct(){
        $this->_init('custompayment/order', 'profile_id');
    }
}
