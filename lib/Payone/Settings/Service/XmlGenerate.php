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
 * Do not edit or add to this file if you wish to upgrade Payone to newer
 * versions in the future. If you wish to customize Payone for your
 * needs please refer to http://www.payone.de for more information.
 *
 * @category        Payone
 * @package         Payone_Settings
 * @subpackage      Service
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @author          Matthias Walter <info@noovias.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */

/**
 *
 * @category        Payone
 * @package         Payone_Settings
 * @subpackage      Service
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */
class Payone_Settings_Service_XmlGenerate
{
    const TAG_CONFIG_ROOT = 'config';
    const CLASS_PREFIX = 'Payone_Settings_Data_ConfigFile_';


    // @todo neue Methode generate mit gleichen Parametern
    // @todo wandelt anhand Shop "gruppiert" um und fügt mehrere Shops umwandlungen zusammen in ein config-root
    // @todo innerhalb von mapShop wird dann ein mapSystem, mapGlobal, mapClearingtypes und mapProtect gerufen
    // @todo innerhalb dieser methoden je nach bedarf weitere Methoden

    /**
     * Generates an XML string from a Root config object, including all settings (global, shop, payment, protect)
     *
     * @api
     *
     * @param Payone_Settings_Data_ConfigFile_Root $config
     * @return mixed @see SimpleXMLElement::asXml()
     */
    public function generate(Payone_Settings_Data_ConfigFile_Root $config)
    {
        // @todo wandelt anhand Shop "gruppiert" um und fügt mehrere Shops umwandlungen zusammen in ein config-root
        $configXml = new SimpleXMLElement('<' . $config->getKey() . '>' . '</' . $config->getKey() . '>');


        foreach ($config->getShop() as $key => $value) {
            $shop = $this->mapShop($value, $configXml);
        }

        $xmlString = $configXml->asXML();

        return $xmlString;
    }

    /**
     * Generates an XML string from a Root config object, including all settings (global, shop, payment, protect)
     *
     * @api
     *
     * @param Payone_Settings_Data_ConfigFile_Root $config
     * @return mixed @see SimpleXMLElement::asXml()
     */
    public function execute(Payone_Settings_Data_ConfigFile_Root $config)
    {
        // Recursively add the arrays to a SimpleXMLElement, forming a tree:
        $arrayData = $config->toArray();
        $xml = $this->simpleXmlFromNestedArray(self::TAG_CONFIG_ROOT, $arrayData);

        return $xml->asXML();
    }

    /**
     * @param string $name                tag name
     * @param array $array                data
     * @param null|SimpleXMLElement $root IF not set, $name will form the root element
     * @return SimpleXMLElement
     */
    public function simpleXmlFromNestedArray($name, $array, SimpleXMLElement $root = null)
    {
        if ($root === null) {
            $root = new SimpleXMLElement('<' . $name . '>' . '</' . $name . '>');
        }

        /** @var $parent SimpleXMLElement */
        $parent = $root->addChild($name);
        foreach ($array as $key => $value) {
            if (is_array($value)) {

                if (array_key_exists('attribute', $value)) {
                    //add node
                    $node = $parent->addChild($value['node']);
                    //add all attributes
                    foreach ($value['attribute'] as $attributKey => $attributData) {
                        $node->addAttribute($attributKey, $attributData);
                    }
                }
                else {
                    $this->simpleXmlFromNestedArray($key, $value, $parent);
                }
            }
            else {
                $parent->addChild($key, $value);
            }

        }
        return $parent;
    }

    /**
     * @param Payone_Settings_Data_ConfigFile_Shop $shopConfig
     * @param SimpleXMLElement $configXml
     * @return string
     */
    protected function mapShop(Payone_Settings_Data_ConfigFile_Shop $shopConfig, SimpleXMLElement $configXml)
    {
        $shopXml = $configXml->addChild($shopConfig->getKey());

        $this->addChild($shopXml, $shopConfig, 'code');
        $this->addChild($shopXml, $shopConfig, 'name');

        $shopXml = $this->mapSystem($shopConfig->getSystem(), $shopXml);

        $shopXml = $this->mapGlobal($shopConfig->getGlobal(), $shopXml);
        $shopXml = $this->mapClearingtypes($shopConfig->getClearingtypes(), $shopXml);
        $shopXml = $this->mapProtect($shopConfig->getProtect(), $shopXml);
        $shopXml = $this->mapMisc($shopConfig->getMisc(), $shopXml);

        return $shopXml;
    }

    /**
     * @param Payone_Settings_Data_ConfigFile_Shop_System $systemConfig
     * @param SimpleXMLElement $shopXml
     * @return SimpleXMLElement
     */
    protected function mapSystem(Payone_Settings_Data_ConfigFile_Shop_System $systemConfig, SimpleXMLElement $shopXml)
    {
        $systemXml = $shopXml->addChild($systemConfig->getKey());
        $this->addChild($systemXml, $systemConfig, 'name');
        $this->addChild($systemXml, $systemConfig, 'version');
        $this->addChild($systemXml, $systemConfig, 'edition');
        $this->addChild($systemXml, $systemConfig, 'modules');
        return $shopXml;
    }

    /**
     * @param Payone_Settings_Data_ConfigFile_Shop_Global $globalConfig
     * @param SimpleXMLElement $shopXml
     * @return SimpleXMLElement
     */
    protected function mapGlobal(Payone_Settings_Data_ConfigFile_Shop_Global $globalConfig, SimpleXMLElement $shopXml)
    {
        $globalXml = $shopXml->addChild($globalConfig->getKey());
        $this->addChild($globalXml, $globalConfig, 'mid');
        $this->addChild($globalXml, $globalConfig, 'aid');
        $this->addChild($globalXml, $globalConfig, 'portalid');
        $this->addChild($globalXml, $globalConfig, 'request_type');
        $this->addChild($globalXml, $globalConfig, 'parameter_invoice');
        $this->addStatusMapping($globalConfig, $globalXml);
        $this->addChild($globalXml, $globalConfig, 'payment_creditcard');
        return $shopXml;
    }

    /**
     * @param Payone_Settings_Data_ConfigFile_Shop_ClearingTypes $clearingTypes
     * @param SimpleXMLElement $shopXml
     * @return SimpleXMLElement
     */
    protected function mapClearingTypes(Payone_Settings_Data_ConfigFile_Shop_ClearingTypes $clearingTypes, SimpleXMLElement $shopXml)
    {
        $clearingTypesXml = $shopXml->addChild($clearingTypes->getKey());

        foreach ($clearingTypes->getClearingtypes() as $keyClearingType => $valueClearingType) {
            $clearingTypeNode = $clearingTypesXml->addChild($valueClearingType->getKey());

            $this->addChild($clearingTypeNode, $valueClearingType, 'title');
            $this->addChild($clearingTypeNode, $valueClearingType, 'id');
            $this->addChild($clearingTypeNode, $valueClearingType, 'mid');
            $this->addChild($clearingTypeNode, $valueClearingType, 'aid');
            $this->addChild($clearingTypeNode, $valueClearingType, 'portalid');
            $this->addFeeConfig($clearingTypeNode, $valueClearingType);
            $this->addChild($clearingTypeNode, $valueClearingType, 'min_order_total');
            $this->addChild($clearingTypeNode, $valueClearingType, 'max_order_total');

            if ($valueClearingType instanceof Payone_Settings_Data_ConfigFile_PaymentMethod_Financing) {
                /** @var $valueClearingType Payone_Settings_Data_ConfigFile_PaymentMethod_Creditcard */
                $this->addChild($clearingTypeNode, $valueClearingType, 'financingtype');
            }
            $this->addTypesOrGlobalInfo($clearingTypeNode, $valueClearingType);

        }

        return $shopXml;
    }

    /**
     * @param Payone_Settings_Data_ConfigFile_Shop_Protect $protectConfig
     * @param SimpleXMLElement $shopXml
     * @return SimpleXMLElement
     */
    protected function mapProtect(Payone_Settings_Data_ConfigFile_Shop_Protect $protectConfig, SimpleXMLElement $shopXml)
    {
        $protectXml = $shopXml->addChild($protectConfig->getKey());

        $protectXml = $this->mapConsumerscore($protectConfig->getConsumerscore(), $protectXml);
        $protectXml = $this->mapAddresscheck($protectConfig->getAddresscheck(), $protectXml);

        return $shopXml;
    }

    /**
     * @param Payone_Settings_Data_ConfigFile_Shop_Misc $miscConfig
     * @param SimpleXMLElement $shopXml
     * @return SimpleXMLElement
     */
    protected function mapMisc(Payone_Settings_Data_ConfigFile_Shop_Misc $miscConfig, SimpleXMLElement $shopXml)
    {
        $miscXml = $shopXml->addChild($miscConfig->getKey());
        $this->addTransactionstatusForwarding($miscConfig, $miscXml);
        $this->addChild($miscXml, $miscConfig, 'shipping_costs');
        return $shopXml;

    }

    /**
     * @param Payone_Settings_Data_ConfigFile_Protect_Consumerscore $consumerscoreConfig
     * @param SimpleXMLElement $protectXml
     * @return SimpleXMLElement
     */
    protected function mapConsumerscore(Payone_Settings_Data_ConfigFile_Protect_Consumerscore $consumerscoreConfig, SimpleXMLElement $protectXml)
    {
        $consumerscoreXml = $protectXml->addChild($consumerscoreConfig->getKey());
        $this->addChild($consumerscoreXml, $consumerscoreConfig, 'active');
        $this->addChild($consumerscoreXml, $consumerscoreConfig, 'mode');
        $this->addChild($consumerscoreXml, $consumerscoreConfig, 'min_order_total');
        $this->addChild($consumerscoreXml, $consumerscoreConfig, 'max_order_total');
        $this->addChild($consumerscoreXml, $consumerscoreConfig, 'addresscheck');
        $this->addChild($consumerscoreXml, $consumerscoreConfig, 'red');
        $this->addChild($consumerscoreXml, $consumerscoreConfig, 'yellow');
        $this->addChild($consumerscoreXml, $consumerscoreConfig, 'duetime');

        return $protectXml;
    }

    /**
     * @param Payone_Settings_Data_ConfigFile_Protect_Addresscheck $addresscheckConfig
     * @param SimpleXMLElement $protectXml
     * @return SimpleXMLElement
     */
    protected function mapAddresscheck(Payone_Settings_Data_ConfigFile_Protect_Addresscheck $addresscheckConfig, SimpleXMLElement $protectXml)
    {
        $addresscheckXml = $protectXml->addChild($addresscheckConfig->getKey());
        $this->addChild($addresscheckXml, $addresscheckConfig, 'active');
        $this->addChild($addresscheckXml, $addresscheckConfig, 'mode');
        $this->addChild($addresscheckXml, $addresscheckConfig, 'min_order_total');
        $this->addChild($addresscheckXml, $addresscheckConfig, 'max_order_total');
        $this->addChild($addresscheckXml, $addresscheckConfig, 'checkbilling');
        $this->addChild($addresscheckXml, $addresscheckConfig, 'checkshipping');
        $this->addChild($addresscheckXml, $addresscheckConfig, 'personstatusmapping');

        return $protectXml;
    }

    public function addTransactionstatusForwarding(Payone_Settings_Data_ConfigFile_Shop_Misc $miscConfig, SimpleXMLElement $miscXml)
    {
        $tasForwarding = $miscConfig->getTransactionstatusforwarding();
        $tasXml = $miscXml->addChild($tasForwarding->getKey());

        foreach ($tasForwarding->getTransactionstatusForwarding() as $keyTas => $config) {
            $configNode = $tasXml->addChild('config');

            foreach ($config as $key => $value) {
                $configNode->addAttribute($key, $value);
            }
        }
    }

    public function addStatusMapping(Payone_Settings_Data_ConfigFile_Shop_Global $globalConfig, SimpleXMLElement $globalXml)
    {
        $statusMapping = $globalConfig->getStatusMapping();
        $tasXml = $globalXml->addChild($statusMapping->getKey());

        foreach ($statusMapping->getStatusMapping() as $keyStatusMapping => $valueStatusMapping) {

            $parent = $tasXml->addChild($keyStatusMapping);

            foreach ($valueStatusMapping as $key => $value) {
                $mapNode = $parent->addChild('map');

                $this->addAttribute($mapNode, $key, $value);
            }
        }
    }

    public function addTypesOrGlobalInfo(SimpleXMLElement $cleatringTypeNode, Payone_Settings_Data_ConfigFile_PaymentMethod_Abstract $valueClearingType)
    {
        if ($valueClearingType->getTypes() !== NULL && $valueClearingType->getTypes() !== FALSE) {

            if ($valueClearingType instanceof Payone_Settings_Data_ConfigFile_PaymentMethod_Creditcard) {
                /** @var $valueClearingType Payone_Settings_Data_ConfigFile_PaymentMethod_Creditcard */
                $this->addChild($cleatringTypeNode, $valueClearingType, 'cvc2');
            }
            $this->addChild($cleatringTypeNode, $valueClearingType, 'types');
        }
        $this->addGlobal($cleatringTypeNode, $valueClearingType);
    }

    public function addGlobal($parent, $type)
    {

        $this->addChild($parent, $type, 'active');
        // Currently not in use
        //$this->addChild($parent, $type, 'neworderstatus');
        $this->addChild($parent, $type, 'countries');
        $this->addChild($parent, $type, 'authorization');
        $this->addChild($parent, $type, 'mode');
    }

    public function addFeeConfig(SimpleXMLElement $cleatringTypeNode, Payone_Settings_Data_ConfigFile_PaymentMethod_Abstract $valueClearingType)
    {
        $feeConfig = $valueClearingType->getFeeConfig();
        if (!empty($feeConfig)) {

            $feeConfigNode = $cleatringTypeNode->addChild('fee_config');
            foreach ($feeConfig as $keyFeeConfig => $valueFeeConfig) {
                if (array_key_exists('value', $valueFeeConfig) && array_key_exists('attribute', $valueFeeConfig)) {
                    $feeNode = $feeConfigNode->addChild('fee', $valueFeeConfig['value']);

                    foreach ($valueFeeConfig['attribute'] as $keyFee => $valueFee) {
                        $feeNode->addAttribute($keyFee, $valueFee);

                    }
                }
            }
        }
    }


    /**
     * @param SimpleXMLElement $parent
     * @param $object
     * @param $property
     * @return SimpleXMLElement
     */
    protected function addChild(SimpleXMLElement $parent, $object, $property)
    {
        $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $property)));
        $data = $object->$getter();
        $child = $parent;
        if (is_array($data)) {
            $parent = $parent->addChild($property);
            foreach ($data as $key => $value) {
                $child = $parent->addChild($key, $value);
            }
        }
        else {
            if (isset($data)) {
                $child = $parent->addChild($property, $data);
            }
        }
        return $child;
    }

    /**
     * @param SimpleXMLElement $mapNode
     * @param $name
     * @param $value
     * @return SimpleXMLElement
     */
    protected function addAttribute(SimpleXMLElement $mapNode, $name, $value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $data) {
                $mapNode->addAttribute($key, $data);
            }
        }
        else {
            if (!empty($data)) {
                $mapNode->addAttribute($name, $value);
            }
        }
        return $mapNode;
    }
}
