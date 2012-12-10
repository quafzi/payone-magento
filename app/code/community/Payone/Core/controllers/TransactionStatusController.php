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
 * @subpackage
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @author          Matthias Walter <info@noovias.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */

/**
 *
 * @category        Payone
 * @package         Payone_Core_controllers
 * @subpackage
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */
class Payone_Core_TransactionStatusController extends Payone_Core_Controller_Abstract
{
    /**
     * Receives TransactionStatus from Payone, maps and saves it in database.
     * Reports TSOK response to Payone if successful.
     * Processing of saved TransactionStatus does not happen immediately.
     *
     * @return mixed
     * @throws Payone_Core_Exception_OrderNotFound
     */
    public function indexAction()
    {
        try {
            // Show no-route page if no Post Request
            if (!$this->getRequest()->isPost()) {
                $this->norouteAction();
                return;
            }

            // only retrieve Data from POST
            $this->getRequest()->setParamSources(array('_POST'));

            // Get Reference (order increment_id)
            $reference = $this->getRequest()->getParam('reference');

            // Load Order by Reference
            $order = $this->getFactory()->getModelSalesOrder();
            $order->loadByIncrementId($reference);

            if (!$order->hasData()) {
                throw new Payone_Core_Exception_OrderNotFound();
            }

            // Get used config for this order
            $configPaymentMethod = $this->getConfigPaymentMethod($order);
            $configTransactionStatusProcessing = $this->getConfigTransactionStatusProcessing($order->getStoreId());

            // Prepare Transaction Status handler
            $key = $configPaymentMethod->getKey();
            $validIps = $configTransactionStatusProcessing->getValidIps();
            $service = $this->getFactory()->getServiceTransactionStatusHandleRequest($key, $validIps);

            // Handle Request:
            $response = $service->handleByPost();

            // Send Confirmation Message
            $this->getResponse()->setBody($response->getStatus());
        }
        catch( Payone_TransactionStatus_Exception_Validation $e)
        {
            // Throw generic error.
            $type = get_class($e);
            $message = 'ERROR='.$type;

            $this->getResponse()->setBody($message);
        }
        catch (Exception $e)
        {
            $type = get_class($e);

            $message = 'ERROR='.$type.'|MESSAGE='.$e->getMessage();

            // Send Confirmation Message
            $this->getResponse()->setBody($message);

            Mage::logException($e);
        }
    }

    /**
     * Detects used Config for this Order
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool|Payone_Core_Model_Config_Payment_Method_Interface
     */
    protected function getConfigPaymentMethod(Mage_Sales_Model_Order $order)
    {
        return $this->helperConfig()->getConfigPaymentMethodByOrder($order);
    }

    protected function getConfigTransactionStatusProcessing($storeId)
    {
        return $this->helperConfig()->getConfigMisc($storeId)->getTransactionstatusProcessing();
    }

}