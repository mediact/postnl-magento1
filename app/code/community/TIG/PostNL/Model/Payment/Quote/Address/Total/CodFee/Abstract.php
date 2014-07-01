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
abstract class TIG_PostNL_Model_Payment_Quote_Address_Total_CodFee_Abstract extends Mage_Tax_Model_Sales_Total_Quote_Tax
{
    /**
     * Xpath to the PostNL COD fee setting.
     */
    const XPATH_COD_FEE = 'payment/postnl_cod/fee';

    /**
     * Xpath to PostNL COD fee tax class.
     */
    const XPATH_COD_TAX_CLASS = 'tax/classes/postnl_cod_fee';

    /**
     * @var string
     */
    protected $_totalCode;

    public function __construct()
    {
        $this->setCode($this->_totalCode);
        $this->setTaxCalculation(Mage::getSingleton('tax/calculation'));

        $this->_helper = Mage::helper('tax');
        $this->_config = Mage::getSingleton('tax/config');
        $this->_weeeHelper = Mage::helper('weee');
    }

    /**
     * @return Mage_Tax_Model_Calculation
     */
    public function getTaxCalculation()
    {
        $taxCalculation = $this->_calculator;
        if ($taxCalculation) {
            return $taxCalculation;
        }

        $taxCalculation = Mage::getSingleton('tax/calculation');

        $this->setTaxCalculation($taxCalculation);
        return $taxCalculation;
    }

    /**
     * @param Mage_Tax_Model_Calculation $taxCalculation
     *
     * @return $this
     */
    public function setTaxCalculation(Mage_Tax_Model_Calculation $taxCalculation)
    {
        $this->_calculator = $taxCalculation;

        return $this;
    }

    /**
     * Get the tax request object for the current quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return bool|Varien_Object
     */
    protected function _getCodFeeTaxRequest(Mage_Sales_Model_Quote $quote)
    {
        $store = $quote->getStore();
        $taxCalculation = $this->getTaxCalculation();

        $customerTaxClass = $quote->getCustomerTaxClassId();
        $shippingAddress  = $quote->getShippingAddress();
        $billingAddress   = $quote->getBillingAddress();
        $codTaxClass      = Mage::getStoreConfig(self::XPATH_COD_TAX_CLASS, $store);

        if (!$codTaxClass) {
            return false;
        }

        $request = $taxCalculation->getRateRequest(
            $shippingAddress,
            $billingAddress,
            $customerTaxClass,
            $store
        );

        $request->setProductClassId($codTaxClass);

        return $request;
    }

    /**
     * Get the tax rate based on the previously created tax request.
     *
     * @param Varien_Object $request
     *
     * @return float
     */
    protected function _getCodFeeTaxRate($request)
    {
        $rate = $this->getTaxCalculation()->getRate($request);

        return $rate;
    }

    /**
     * Get the fee tax based on the shipping address and tax rate.
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param float                          $taxRate
     * @param float|null                     $fee
     *
     * @return float
     */
    protected function _getCodFeeTax($address, $taxRate, $fee = null)
    {
        if (is_null($fee)) {
            $fee = (float) $address->getBasePostnlCodFee();
        }

        $taxCalculation = $this->getTaxCalculation();

        $feeTax = $taxCalculation->calcTaxAmount(
            $fee,
            $taxRate,
            false,
            true
        );

        return $feeTax;
    }

    /**
     * Get the base fee tax based on the shipping address and tax rate.
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param float                          $taxRate
     * @param float|null                     $fee
     *
     * @return float
     */
    protected function _getBaseCodFeeTax($address, $taxRate, $fee = null)
    {
        if (is_null($fee)) {
            $fee = (float) $address->getBasePostnlCodFee();
        }

        $taxCalculation = $this->getTaxCalculation();

        $baseFeeTax = $taxCalculation->calcTaxAmount(
            $fee,
            $taxRate,
            false,
            true
        );

        return $baseFeeTax;
    }
}