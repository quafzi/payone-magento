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
 * @package         Payone_Core_controllers
 * @subpackage      Checkout
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @author          Matthias Walter <info@noovias.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */

require_once 'Mage' . DS . 'Checkout' . DS . 'controllers' . DS . 'OnepageController.php';

/**
 *
 * @category        Payone
 * @package         Payone_Core_controllers
 * @subpackage      Checkout
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */
class Payone_Core_Checkout_OnepageController extends Mage_Checkout_OnepageController
{
    protected $eventPrefix = 'payone_core_checkout_onepage';

    /**
     * verfiy payment ajax action
     *
     * Sets either redirect or a JSON response
     */
    public function verifyPaymentAction()
    {
        if ($this->_expireAjax()) {
            return;
        }
        try {
            if (!$this->getRequest()->isPost()) {
                $this->_ajaxRedirectResponse();
                return;
            }

            // Dispatch Event
            $settings = $this->dispatchEvent();

            $result = array();
            if ($settings->getData('have_to_filter_methods') == true) {

                // register Allowed methods
                $allowedMethods = $settings->getData('allowed_methods');
                Mage::register('payment_methods_allowed_methods', $allowedMethods, true);

                $result['goto_section'] = 'payment';
                $result['update_section'] = array(
                    'name' => 'payment-method',
                    'html' => $this->_getPaymentMethodsHtml()
                );
            }
            else {
                $this->_forward('savePayment', 'onepage', 'checkout');
                return;
            }
        }
        catch (Mage_Payment_Exception $e) {
            if ($e->getFields()) {
                $result['fields'] = $e->getFields();
            }
            $result['error'] = $e->getMessage();
        }
        catch (Mage_Core_Exception $e) {
            $result['error'] = $e->getMessage();
        }
        catch (Exception $e) {
            Mage::logException($e);
            $result['error'] = $this->__('Unable to set Payment Method.');
        }

        $this->getResponse()->setBody(
            Mage::helper('core')->jsonEncode($result)
        );
    }

    /**
     * @return Varien_Object
     */
    protected function dispatchEvent()
    {
        $settings = new Varien_Object();
        $settings->setData('have_to_filter_methods', false);

        $allowedMethods = new Varien_Object();
        $settings->setData('allowed_methods', $allowedMethods);

        $paymentData = $this->getRequest()->getPost('payment', array());
        $selectedMethod = $paymentData['method'];

        $parameters = array(
            'settings' => $settings,
            'quote' => $this->getOnepage()->getQuote(),
            'selected_method' => $selectedMethod,
            'payment_data' => $paymentData,
            'full_action_name' => $this->getFullActionName('/'),
        );

        // Dispatch Event
        Mage::dispatchEvent($this->eventPrefix . '_verify_payment', $parameters);

        return $settings;
    }

}