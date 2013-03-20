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
 * @subpackage      Mapper
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @author          Matthias Walter <info@noovias.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */

/**
 *
 * @category        Payone
 * @package         Payone_Core_Model
 * @subpackage      Mapper
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */
abstract class Payone_Core_Model_Mapper_ApiRequest_Payment_Authorize_Abstract
    extends Payone_Core_Model_Mapper_ApiRequest_Payment_Abstract
{
    /**
     * @return Payone_Api_Request_Authorization_Abstract
     */
    abstract protected function getRequest();

    /**
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Payone_Api_Request_Preauthorization|Payone_Api_Request_Authorization
     */
    public function mapFromPayment(Mage_Sales_Model_Order_Payment $payment)
    {
        $this->init($payment);

        $request = $this->getRequest();

        $this->beforeMapFromPayment($request);

        // Add Default Api Parameters
        $this->mapDefaultParameters($request);

        // Add Default Authorize Parameters
        $this->mapDefaultAuthorizeParameters($request);

        // PersonalData
        $personalData = $this->mapPersonalParameters();
        $request->setPersonalData($personalData);

        // ShippingData, only for non-virtual orders.
        if ($payment->getOrder()->getIsNotVirtual()) {
            $deliveryData = $this->mapDeliveryParameters();
            $request->setDeliveryData($deliveryData);
        }
        // Only add Invoiceing Parameters if enabled / required by payment method
        if ($this->mustTransmitInvoiceData()) {
            $invoicing = $this->mapInvoicingParameters();
            $request->setInvoicing($invoicing);
        }

        $payment = $this->mapPaymentParameters();

        // Not every Paymentmethod has an extra Parameter Set
        if ($payment !== null) {
            $request->setPayment($payment);
        }

        $this->afterMapFromPayment($request);

        return $request;
    }

    /**
     * @param Payone_Api_Request_Authorization_Abstract $request
     */
    public function beforeMapFromPayment(Payone_Api_Request_Authorization_Abstract $request)
    {

    }

    /**
     * @param Payone_Api_Request_Authorization_Abstract $request
     */
    public function afterMapFromPayment(Payone_Api_Request_Authorization_Abstract $request)
    {

    }


    /**
     * @param Payone_Api_Request_Authorization_Abstract $request
     */
    protected function mapDefaultAuthorizeParameters(Payone_Api_Request_Authorization_Abstract $request)
    {
        $order = $this->getOrder();
        $paymentMethod = $this->getPaymentMethod();

        $request->setRequest($this->configPayment->getRequestType());
        $request->setAid($this->configPayment->getAid());
        $request->setClearingtype($this->mapClearingType($paymentMethod));
        $request->setCurrency($order->getOrderCurrencyCode());
        $request->setReference($order->getIncrementId());
        $request->setParam(''); // @comment currently empty

        $narrativeText = '';
        /** load correct narrative text from config */
        if ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Creditcard) {
            $narrativeText = $this->getNarrativeText('creditcard');
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_DebitPayment) {
            $narrativeText = $this->getNarrativeText('debit_payment');
        }
        $request->setNarrativeText($narrativeText);

        $request->setAmount($order->getGrandTotal());
    }


    /**
     * @return Payone_Api_Request_Parameter_Authorization_PersonalData
     */
    protected function mapPersonalParameters()
    {
        $helper = $this->helper();
        $order = $this->getOrder();
        $billingAddress = $order->getBillingAddress();
        $billingCountry = $billingAddress->getCountry();
        $customer = $order->getCustomer();

        $personalData = new Payone_Api_Request_Parameter_Authorization_PersonalData();
        $personalData->setCustomerid($customer->getIncrementId());
        $personalData->setTitle($billingAddress->getPrefix());
        $personalData->setFirstname($billingAddress->getFirstname());
        $personalData->setLastname($billingAddress->getLastname());
        $personalData->setCompany($billingAddress->getCompany());

        $street = $helper->normalizeStreet($billingAddress->getStreet());
        $personalData->setStreet($street);
        $personalData->setAddressaddition('');
        $personalData->setZip($billingAddress->getPostcode());
        $personalData->setCity($billingAddress->getCity());
        $personalData->setCountry($billingCountry);
        $personalData->setEmail($billingAddress->getEmail());
        $personalData->setTelephonenumber($billingAddress->getTelephone());

        $birthday = $this->formatBirthday($order->getCustomerDob());
        $personalData->setBirthday($birthday);

        $language = $helper->getDefaultLanguage();
        $personalData->setLanguage($language);
        $personalData->setVatid($order->getCustomerTaxvat());

        $global = $this->getConfigGeneral()->getGlobal();
        // Send Ip when enabled
        if ($global->getTransmitIp()) {
            if ($global->getProxyMode()) {
                // Use X-Forwarded-For when in Proxy-Mode
                $remoteIp = $order->getXForwardedFor();
            }
            else {
                $remoteIp = $order->getRemoteIp();
            }

            // Multiple Ips could be included, we only send the last one.
            $remoteIps = explode(',', $remoteIp);
            $ip = array_pop($remoteIps);

            $personalData->setIp($ip);
        }

        // US and CA always need state and shipping_state paramters
        if ($billingCountry == 'US' or $billingCountry == 'CA') {
            $personalData->setState($billingAddress->getRegionCode());
        }

        return $personalData;
    }

    /**
     * @return Payone_Api_Request_Parameter_Authorization_DeliveryData
     */
    protected function mapDeliveryParameters()
    {
        $helper = $this->helper();
        $paymentMethod = $this->getPaymentMethod();
        $info = $paymentMethod->getInfoInstance();
        if ($paymentMethod instanceof Payone_Core_Model_Payment_Method_SafeInvoice
                and $info->getPayoneSafeInvoiceType() === Payone_Api_Enum_FinancingType::BSV
        ) {
            $address = $this->getOrder()->getBillingAddress();
        } // Always use same address for BillSAFE
        else {
            $address = $this->getOrder()->getShippingAddress();
        }

        $deliveryData = new Payone_Api_Request_Parameter_Authorization_DeliveryData();

        $shippingCountry = $address->getCountry();

        $deliveryData->setShippingFirstname($address->getFirstname());
        $deliveryData->setShippingLastname($address->getLastname());
        $deliveryData->setShippingCompany($address->getCompany());
        $street = $helper->normalizeStreet($address->getStreet());
        $deliveryData->setShippingStreet($street);
        $deliveryData->setShippingZip($address->getPostcode());
        $deliveryData->setShippingCity($address->getCity());
        $deliveryData->setShippingCountry($shippingCountry);

        // US and CA always need shipping_state paramters
        if ($shippingCountry == 'US' or $shippingCountry == 'CA') {
            $deliveryData->setShippingState($address->getRegionCode());
        }

        return $deliveryData;
    }

    /**
     * @return Payone_Api_Request_Parameter_Invoicing_Transaction
     */
    protected function mapInvoicingParameters()
    {
        $order = $this->getOrder();

        $invoiceAppendix = $this->getInvoiceAppendix();

        $invoicing = new Payone_Api_Request_Parameter_Invoicing_Transaction();
        $invoicing->setInvoiceappendix($invoiceAppendix);

        // Order items:
        foreach ($order->getItemsCollection() as $key => $itemData) {
            /** @var $itemData Mage_Sales_Model_Order_Item */
            if ($itemData->isDummy()) {
                continue; // Do not map dummy items
            }

            $number = $itemData->getQtyToInvoice();
            if ($number <= 0) {
                continue; // Do not map items with zero quanity
            }

            $params['id'] = $itemData->getSku();
            $params['pr'] = $itemData->getPriceInclTax();
            $params['no'] = $number;
            $params['de'] = $itemData->getName();
            $params['va'] = number_format($itemData->getTaxPercent(), 0, '.', '');

            if ($this->getPaymentMethod()->mustTransmitInvoicingItemTypes()) {
                $params['it'] = Payone_Api_Enum_InvoicingItemType::GOODS;
            }


            $item = new Payone_Api_Request_Parameter_Invoicing_Item();
            $item->init($params);
            $invoicing->addItem($item);
        }

        // Shipping / Fees:
        if ($order->getShippingInclTax() > 0) {
            $invoicing->addItem($this->mapShippingFeeAsItem());
        }

        // Discounts:
        $discountAmount = $order->getDiscountAmount(); // Discount Amount is negative on order.
        if ($discountAmount > 0 || $discountAmount < 0) {
            $invoicing->addItem($this->mapDiscountAsItem($discountAmount));
        }
        return $invoicing;
    }

    /**
     * @return Payone_Api_Request_Parameter_Authorization_3dsecure
     */
    protected function map3dSecureParameters()
    {
        $secure3d = new Payone_Api_Request_Parameter_Authorization_3dsecure();
        // @comment 3D Secure is currently not available in Magento
        return $secure3d;
    }

    /**
     * @return Payone_Api_Request_Parameter_Authorization_PaymentMethod_Abstract
     */
    protected function mapPaymentParameters()
    {
        $payment = null;
        $paymentMethod = $this->getPaymentMethod();
        $info = $paymentMethod->getInfoInstance();
        $isRedirect = false;

        if ($paymentMethod instanceof Payone_Core_Model_Payment_Method_CashOnDelivery) {
            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_CashOnDelivery();
            $payment->setShippingprovider(Payone_Api_Enum_Shippingprovider::DHL);
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Creditcard) {
            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_CreditCard();

            // check if it is an adminorder and set ecommercemode to moto
            if ($this->getIsAdmin()) {
                $payment->setEcommercemode('moto');
            }
            $payment->setPseudocardpan($info->getPayonePseudocardpan());
            $isRedirect = true;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_OnlineBankTransfer) {
            $country = $this->getOrder()->getBillingAddress()->getCountry();

            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_OnlineBankTransfer();
            $payment->setBankcountry($country);
            $payment->setBankaccount($info->getPayoneAccountNumber());
            $payment->setBankcode($info->getPayoneBankCode());
            $payment->setBankgrouptype($info->getPayoneBankGroup());
            $payment->setOnlinebanktransfertype($info->getPayoneOnlinebanktransferType());

            $isRedirect = true;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Financing) {

            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_Financing();
            $payment->setFinancingtype($info->getPayoneFinancingType());

            $isRedirect = true;
        }

        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_SafeInvoice) {

            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_Financing();
            $payment->setFinancingtype($info->getPayoneSafeInvoiceType());

            $isRedirect = true;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Wallet) {
            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_Wallet();
            // @comment currently hardcoded because there is no other Type
            $payment->setWallettype(Payone_Api_Enum_WalletType::PAYPAL_EXPRESS);

            $isRedirect = true;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_DebitPayment) {
            $country = $this->getOrder()->getBillingAddress()->getCountry();

            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_DebitPayment();
            $payment->setBankcountry($country);
            $payment->setBankaccount($info->getPayoneAccountNumber());
            $payment->setBankaccountholder($info->getPayoneAccountOwner());
            $payment->setBankcode($info->getPayoneBankCode());
        }

        if ($isRedirect === true) {
            $successurl = $this->helperUrl()->getSuccessUrl();
            $errorurl = $this->helperUrl()->getErrorUrl();
            $backurl = $this->helperUrl()->getBackUrl();

            $payment->setSuccessurl($successurl);
            $payment->setErrorurl($errorurl);
            $payment->setBackurl($backurl);
        }

        return $payment;
    }

    /**
     * @param Payone_Core_Model_Payment_Method_Abstract $paymentMethod
     * @return string
     */
    protected function mapClearingType(Payone_Core_Model_Payment_Method_Abstract $paymentMethod)
    {
        $clearingType = '';

        if ($paymentMethod instanceof Payone_Core_Model_Payment_Method_CashOnDelivery) {
            $clearingType = Payone_Enum_ClearingType::CASHONDELIVERY;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Creditcard) {
            $clearingType = Payone_Enum_ClearingType::CREDITCARD;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_OnlineBankTransfer) {
            $clearingType = Payone_Enum_ClearingType::ONLINEBANKTRANSFER;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Wallet) {
            $clearingType = Payone_Enum_ClearingType::WALLET;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Invoice) {
            $clearingType = Payone_Enum_ClearingType::INVOICE;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_AdvancePayment) {
            $clearingType = Payone_Enum_ClearingType::ADVANCEPAYMENT;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_DebitPayment) {
            $clearingType = Payone_Enum_ClearingType::DEBITPAYMENT;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Financing) {
            $clearingType = Payone_Enum_ClearingType::FINANCING;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_SafeInvoice) {
            $clearingType = Payone_Enum_ClearingType::FINANCING;
        }

        return $clearingType;
    }

    /**
     * @param $date
     * @return string
     */
    public function formatBirthday($date)
    {
        if (strlen($date) > 0) {
            $date = substr($date, 0, 4) . substr($date, 5, 2) . substr($date, 8, 2);
        }
        return $date;
    }

    /**
     * Returns the narrative text and substitutes the placeholder if neccessary
     * @param $type
     * @return string
     */
    protected function getNarrativeText($type)
    {
        $general = $this->getConfigGeneral();
        $parameterNarrativeText = $general->getParameterNarrativeText();

        $narrativeText = '';
        if ($type === 'creditcard') {
            $narrativeText = $parameterNarrativeText->getCreditcard();
        }
        elseif ($type === 'debit_payment') {
            $narrativeText = $parameterNarrativeText->getDebitPayment();
        }

        $substitutionArray = array(
            '{{order_increment_id}}' => $this->getOrder()->getIncrementId()
        );

        $narrativeText = str_replace(array_keys($substitutionArray), array_values($substitutionArray), $narrativeText);

        return $narrativeText;
    }
}