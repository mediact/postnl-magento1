<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@totalinternetgroup.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@totalinternetgroup.nl for more information.
 *
 * @copyright   Copyright (c) 2014 Total Internet Group B.V. (http://www.totalinternetgroup.nl)
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
class TIG_PostNL_Block_Adminhtml_System_Config_Form_Field_SupportTab
    extends TIG_PostNL_Block_Adminhtml_System_Config_Form_Field_TextBox_Abstract
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'postnl_adminhtml_system_config_form_field_supporttab';

    /**
     * Css files loaded for PostNL's system > config section
     */
    const SYSTEM_CONFIG_EDIT_CSS_FILE = 'css/TIG/PostNL/system_config_edit_postnl.css';
    const MAGENTO_16_CSS_FILE         = 'css/TIG/PostNL/system_config_edit_postnl_magento16.css';

    /**
     * Xpaths to URLs used in the support tab.
     */
    const POSTNL_REGISTER_URL_XPATH     = 'postnl/general/postnl_register_url';
    const KNOWLEDGEBASE_URL_XPATH       = 'postnl/general/knowledgebase_url';
    const NEW_TICKET_URL_XPATH          = 'postnl/general/new_ticket_url';
    const INSTALLATION_MANUAL_URL_XPATH = 'postnl/general/installation_manual_url';
    const USER_GUIDE_URL_XPATH          = 'postnl/general/user_guide_url';

    /**
     * Template file used
     *
     * @var string
     */
    protected $_template = 'TIG/PostNL/system/config/form/field/support_tab.phtml';

    /**
     * Add a new css file to the head. We couldn't do this from layout.xml, because it would have loaded for all
     * System > Config pages, rather than just PostNL's section.
     *
     * @return Mage_Adminhtml_Block_Abstract::_prepareLayout()
     *
     * @see Mage_Adminhtml_Block_Abstract::_prepareLayout()
     */
    protected function _prepareLayout()
    {
        /**
         * @var Mage_Adminhtml_Block_Page_Head $head
         */
        $head = $this->getLayout()
                     ->getBlock('head');

        $head->addCss(self::SYSTEM_CONFIG_EDIT_CSS_FILE);

        /**
         * For Magento 1.6 and 1.11 we need to add another css file.
         */
        $helper = Mage::helper('postnl');
        $isEnterprise = $helper->isEnterprise();

        /**
         * Get the minimum version requirement for the current Magento edition.
         */
        if($isEnterprise) {
            $minimumVersion = '1.12.0.0';
        } else {
            $minimumVersion = '1.7.0.0';
        }

        /**
         * Check if the current version is below the minimum version requirement.
         */
        $isBelowMinimumVersion = version_compare(Mage::getVersion(), $minimumVersion, '<');
        if ($isBelowMinimumVersion) {
            $head->addCss(self::MAGENTO_16_CSS_FILE);
        }

        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        $version =  Mage::helper('postnl')->getModuleVersion();

        return $version;
    }

    /**
     * @return string
     */
    public function getPostnlRegisterUrl()
    {
        $url = Mage::getStoreConfig(self::POSTNL_REGISTER_URL_XPATH, Mage_Core_Model_App::ADMIN_STORE_ID);

        return $url;
    }

    /**
     * @return string
     */
    public function getKnowledgebaseUrl()
    {
        $url = Mage::getStoreConfig(self::KNOWLEDGEBASE_URL_XPATH, Mage_Core_Model_App::ADMIN_STORE_ID);

        return $url;
    }

    /**
     * @return string
     */
    public function getNewTicketUrl()
    {
        $url = Mage::getStoreConfig(self::NEW_TICKET_URL_XPATH, Mage_Core_Model_App::ADMIN_STORE_ID);

        return $url;
    }

    /**
     * @return string
     */
    public function getInstallationManualUrl()
    {
        $url = Mage::getStoreConfig(self::INSTALLATION_MANUAL_URL_XPATH, Mage_Core_Model_App::ADMIN_STORE_ID);

        return $url;
    }

    /**
     * @return string
     */
    public function getUserGuideUrl()
    {
        $url = Mage::getStoreConfig(self::USER_GUIDE_URL_XPATH, Mage_Core_Model_App::ADMIN_STORE_ID);

        return $url;
    }

    /**
     * @return string
     */
    public function getChangelogUrl()
    {
        $url = Mage::helper('postnl')->getChangelogUrl();

        return $url;
    }
}