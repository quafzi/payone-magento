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
class Payone_Core_Model_Observer_TransactionStatus_Forwarding extends Payone_Core_Model_Observer_Abstract
{
    /**
     * @var Payone_Core_Model_Service_TransactionStatus_Forward
     */
    protected $serviceForwarding = null;

    /**
     * @param Varien_Event_Observer $observer
     */
    public function onAll(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        /**
         * @var $config Payone_Core_Model_Config_Interface
         */
        $config = $event->getConfig();
        /**
         * @var $transactionStatus Payone_Core_Model_Domain_Protocol_TransactionStatus
         */
        $transactionStatus = $event->getTransactionStatus();

        $configForwarding = $config->getMisc()->getTransactionstatusForwarding();

        if (!$configForwarding->isActive()) {
            return;
        }

        $currentTxAction = $transactionStatus->getTxaction();
        if (!$configForwarding->canForwardTxAction($currentTxAction)) {
            return;
        }

        $this->getServiceForwarding()->setConfigForwarding($configForwarding);
        $success = $this->getServiceForwarding()->forward($transactionStatus);

        if(!$success){
            $exceptions = $this->getServiceForwarding()->getExceptions();

            $msg = '';
            foreach ($exceptions as $url => $e) {
                /**
                 * @var $e Exception
                 */
                $msg .= $url .': '.get_class($e).' > '.$e->getMessage();
            }

            $helperEmail = $this->helperEmail();
            $helperEmail->setStoreId($config->getStoreId());
            $helperEmail->sendEmailError(
                'TransactionStatus Forwarding',
                $msg,
                __METHOD__
            );
        }
    }

    /**
     * @param Payone_Core_Model_Service_TransactionStatus_Forward $service
     */
    public function setServiceForwarding(Payone_Core_Model_Service_TransactionStatus_Forward $service)
    {
        $this->serviceForwarding = $service;
    }

    /**
     * @return Payone_Core_Model_Service_TransactionStatus_Forward
     */
    public function getServiceForwarding()
    {
        if($this->serviceForwarding === null){
            $this->serviceForwarding = $this->getFactory()->getServiceTransactionStatusForward();
        }
        return $this->serviceForwarding;
    }

}