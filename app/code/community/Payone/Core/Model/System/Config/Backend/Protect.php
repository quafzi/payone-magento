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
 * @subpackage      System
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @author          Matthias Walter <info@noovias.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */

/**
 *
 * @category        Payone
 * @package         Payone_Core_Model
 * @subpackage      System
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */
class Payone_Core_Model_System_Config_Backend_Protect extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        $addressCheckEnabled = $this->getData('groups/address_check/fields/enabled/value');
        $creditratingEnabled = $this->getData('groups/creditrating/fields/enabled/value');

        if ($addressCheckEnabled == 1 or $creditratingEnabled == 1) {
            $protectValue = 1;
        }
        else {
            $protectValue = 0;
        }

        $path = 'payone_protect/general/enabled';

        /**
         * @var $protect Mage_Core_Model_Config_Data
         */
        $protect = Mage::getModel('core/config_data');
        $protect->setScope($this->getScope());
        $protect->setScopeId($this->getScopeId());

        $protect->setPath($path);

        /** We must check wheter there is a DB entry for the unique constraint 'config_scope'
         *  in Magento versions < 1.6 (in newer versions this check is done by Magento)
         *
         * @see Mage_Core_Model_Resource_Config_Data::_checkUnique() since Magento 1.6.0.0
         */
        if(version_compare($this->helper()->getMagentoVersion(), '1.6','<')) {
            $protect = $this->checkConfigUnique($protect);
        }

        $protect->setValue($protectValue);

        $protect->save();

        parent::_beforeSave();
    }


    /**
     * @param Mage_Core_Model_Config_Data $object
     * @return Mage_Core_Model_Config_Data
     */
    protected function checkConfigUnique(Mage_Core_Model_Config_Data $object)
    {
        /** @var $collection Mage_Core_Model_Mysql4_Config_Data_Collection | Mage_Core_Model_Resource_Config_Data_Collection */
        $collection = Mage::getModel('core/config_data')->getCollection();
        $collection->addFieldToFilter('scope', $object->getScope());
        $collection->addFieldToFilter('scope_id', $object->getScopeId());
        $collection->addFieldToFilter('path', $object->getPath());
        $collection->load();

        if($collection->count() > 0) {
            /** @var $config Mage_Core_Model_Config_Data */
            $config = $collection->getFirstItem();
            $object->setId($config->getId());
        }

        return $object;
    }

    /**
     * @return Payone_Core_Helper_Data
     */
    protected function helper()
    {
        return Mage::helper('payone_core');
    }
}
