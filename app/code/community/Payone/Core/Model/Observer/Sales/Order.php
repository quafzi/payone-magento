<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License (GPL 3)
 * that is bundled with this package in the file LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Payone_Core to newer
 * versions in the future. If you wish to customize Payone_Core for your
 * needs please refer to http://www.payone.de for more information.
 *
 * @category        Payone
 * @package         Payone_Core_Model
 * @subpackage      Observer
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @author          Matthias Walter <info@noovias.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */

/**
 *
 * @category        Payone
 * @package         Payone_Core_Model
 * @subpackage      Observer
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */
class Payone_Core_Model_Observer_Sales_Order
    extends Payone_Core_Model_Observer_Abstract
{
    /**
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function updateOrderGrid(Varien_Event_Observer $observer)
    {
        /**
         * @var $resource Mage_Sales_Model_Mysql4_Order
         */
        $resource = $observer->getEvent()->getResource();

        $resource->addVirtualGridColumn(
            'payone_payment_method',
            'order_payment',
            array('entity_id' => 'parent_id'),
            'method'
        );
    }

    /**
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function incrementSampleCounter(Varien_Event_Observer $observer)
    {
        $storeId = $observer->getEvent()->getOrder()->getStoreId();

        $this->helperConfig()->incrementCreditratingSampleCounter($storeId);
    }

    /**
     *
     * @param Varien_Event_Observer $observer (has data 'payment' with a payment info instance (Mage_Sales_Model_Order_Payment))
     */
    public function cancelPayment(Varien_Event_Observer $observer)
    {
        /** @var $payment Mage_Sales_Model_Order_Payment */
        $payment = $observer->getPayment();

        $methodInstance = $payment->getMethodInstance();

        if ($methodInstance instanceof Payone_Core_Model_Payment_Method_Abstract) {
            $methodInstance->cancel($payment);
        }
    }
}