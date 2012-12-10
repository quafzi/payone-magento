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
 * @subpackage      Handler
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @author          Matthias Walter <info@noovias.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */

/**
 *
 * @category        Payone
 * @package         Payone_Core_Model
 * @subpackage      Handler
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */
class Payone_Core_Model_Handler_Verification_Creditrating
    extends Payone_Core_Model_Handler_Verification_Abstract
    implements Payone_Core_Model_Handler_Verification_Interface
{
    /** @var Payone_Core_Model_Config_Protect_Creditrating */
    protected $config = null;

    protected $prefix = 'payone_protect';

    /**
     * Handle Creditrating by Payone API response:
     *
     * @param Payone_Api_Response_Interface $response
     * @return array|bool will return true if all methods are available
     * @throws Exception|Mage_Payment_Exception
     */
    public function handle(Payone_Api_Response_Interface $response)
    {
        $address = $this->getAddress();
        $scoreAddressCheck = $address->getPayoneAddresscheckScore();

        $allowedMethods = array();
        if ($response instanceof Payone_Api_Response_Consumerscore_Valid) {
            /** @var $response Payone_Api_Response_Consumerscore_Valid */
            $scoreProtect = $this->getProtectScore($scoreAddressCheck, $response->getScore());

            $allowedMethods = $this->handleProtectScore($scoreProtect);

            $address->setPayoneProtectDate(now());
            $address->setPayoneProtectHash($this->helper()->createAddressHash($address));

            $this->saveCustomerAddress($address);
        }
        elseif ($response instanceof Payone_Api_Response_Consumerscore_Invalid) {
            /** @var $response Payone_Api_Response_Consumerscore_Invalid*/
            $allowedMethods = $this->handleProtectScore(Payone_Api_Enum_ConsumerscoreScore::RED);

            $address->setPayoneProtectDate(now());
            $address->setPayoneProtectHash($this->helper()->createAddressHash($address));

            $this->saveCustomerAddress($address);
        }
        elseif ($response instanceof Payone_Api_Response_Error) {
            /** @var $response Payone_Api_Response_Error */

            $allowedMethods = $this->handleError(null, $response);
        }
        return $allowedMethods;
    }

    /**
     * @param Exception|null $ex
     * @return array|bool
     *
     */
    public function handleException(Exception $ex = null)
    {
        return $this->handleError($ex);
    }

    /**
     * Endpoint for handling all kinds of errors and exceptions,
     * Notifies Magento admin of error (by email), allows displaying of ALL payment methods
     *
     * Takes Exception and/or Error response as parameter, to provide information for error email
     *
     * @param null|Exception $ex
     * @param null|Payone_Api_Response_Error $response
     * @throws Mage_Payment_Exception
     * @return bool
     */
    protected function handleError(Exception $ex = null, Payone_Api_Response_Error $response = null)
    {
        $config = $this->getConfig();
        if ($config->isIntegrationEventAfterPayment()) {
            if ($config->onErrorStopCheckout()) {
                // Mage_Payment_Exception is caught in checkout and message gets displayed to customer.
                throw new Mage_Payment_Exception($config->getStopCheckoutMessage());
            }
            return true;
        }

        $additionalInfo = array();
        if (!empty($response)) {
            $errorName = 'Creditrating check ERROR. Code: ' . $response->getErrorcode();
            $errorMessage = $response->getErrormessage();
            $stackTrace = '';
            $additionalInfo['customermessage'] = $response->getCustomermessage();
        }
        elseif (!empty($ex)) {
            $errorName = 'Creditrating check Exception. ' . get_class($ex);
            $errorMessage = $ex->getMessage();
            $stackTrace = $ex->getTraceAsString();
        }
        else {
            $errorName = 'Creditrating check unexpected error. ';
            $errorMessage = 'An unexpected error occured during creditrating check.';
            $stackTrace = '';
        }

        $helperEmail = $this->helperEmail();
        $helperEmail->setStoreId($this->getConfigStore()->getStoreId());
        $helperEmail->sendEmailError($errorName, $errorMessage, $stackTrace, $additionalInfo);
        return true;
    }


    /**
     * Handle Creditrating by Protect score (G)reen, (Y)ellow, (R)ed
     *
     * @param $scoreProtect
     * @return array|bool will return true if all methods are available
     */
    public function handleProtectScore($scoreProtect)
    {

        $config = $this->getConfig();
        $address = $this->getAddress();
        $configuredMethods = array();
        $allowedMethods = array();

        if ($scoreProtect === Payone_Api_Enum_AddressCheckScore::RED) {
            $configuredMethods = $config->getAllowPaymentMethodsRed();
        }
        elseif ($scoreProtect === Payone_Api_Enum_AddressCheckScore::YELLOW) {
            $configuredMethods = $config->getAllowPaymentMethodsYellow();
        }
        elseif ($scoreProtect === Payone_Api_Enum_AddressCheckScore::GREEN) {
            $configuredMethods = true;
        }

        $address->setPayoneProtectScore($scoreProtect);

        if ($configuredMethods === true) {
            return true;
        }

        foreach ($configuredMethods as $value) {
            $allowedMethods[$value] = 1;
        }

        return $allowedMethods;
    }

    /**
     * Compares addresscheck score and creditrating score, determine total score (worst result)
     *
     * @param $scoreAddressCheck
     * @param $scoreCreditratingCheck
     */
    protected function getProtectScore($scoreAddressCheck, $scoreCreditratingCheck)
    {
        switch ($scoreAddressCheck) {
            case Payone_Api_Enum_AddressCheckScore::YELLOW :
                if ($scoreCreditratingCheck === Payone_Api_Enum_AddressCheckScore::GREEN) {
                    return $scoreAddressCheck;
                }
                break;
            case Payone_Api_Enum_AddressCheckScore::RED :
                return $scoreAddressCheck; // score1 is worst or equal to score2.
                break;
        }
        return $scoreCreditratingCheck;
    }

    /**
     * @param Payone_Core_Model_Config_Protect_Creditrating $config
     */
    public function setConfig(Payone_Core_Model_Config_Protect_Creditrating $config)
    {
        $this->config = $config;
    }

    /**
     * @return Payone_Core_Model_Config_Protect_Creditrating
     */
    public function getConfig()
    {
        return $this->config;
    }
}
