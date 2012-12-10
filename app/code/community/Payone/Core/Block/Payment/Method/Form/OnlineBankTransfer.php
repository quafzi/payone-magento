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
 * @package         Payone_Core_Block
 * @subpackage      Payment
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @author          Matthias Walter <info@noovias.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */

/**
 *
 * @category        Payone
 * @package         Payone_Core_Block
 * @subpackage      Payment
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */
class Payone_Core_Block_Payment_Method_Form_OnlineBankTransfer
    extends Payone_Core_Block_Payment_Method_Form_Abstract
{
    protected $hasTypes = true;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payone/core/payment/method/form/onlinebanktransfer.phtml');
    }

    /**
     * Returns quote country
     * @return string
     */
    public function getCountry()
    {
        $quote = $this->getQuote();
        return $quote->getBillingAddress()->getCountry();
    }

    /**
     * @return array
     */
    protected function getSystemConfigMethodTypes()
    {
        return $this->getFactory()->getModelSystemConfigOnlinebanktransferType()->toSelectArray();
    }

    /**
     * @return bool
     */
    public function isAvailableAccountNumber()
    {
        $country = $this->getCountry();
        return $country == 'DE' || $country == 'AT' || $country == 'NL';
    }

    /**
     * @return bool
     */
    public function isAvailableBankCode()
    {
        $country = $this->getCountry();
        return $country == 'DE' || $country == 'AT';
    }

    /**
     * @return bool
     */
    public function isAvailableBankGroup()
    {
        $country = $this->getCountry();
        return $country == 'AT';
    }

    public function getBlockHtmlBankGroup()
    {
        /** @var $block Mage_Core_Block_Template */
        $block = $this->getLayout()->createBlock('core/template');
        $block->setTemplate('payone/core/payment/method/form/onlinebanktransfer/bankgroup.phtml');
        $block->setMethodCode($this->getMethodCode());
        $block->setCountry($this->getCountry());
        $html = $block->toHtml();
        return $html;
    }
}