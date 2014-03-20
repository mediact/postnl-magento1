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
 *
 * @method boolean                                  hasQuote()
 * @method TIG_PostNL_Model_DeliveryOptions_Service setQuote(Mage_Sales_Model_Quote $quote)
 * @method boolean                                  hasPostnlOrder()
 * @method TIG_PostNL_Model_DeliveryOptions_Service setPostnlOrder(TIG_PostNL_Model_Checkout_Order $postnlOrder)
 */
class TIG_PostNL_Model_DeliveryOptions_Service extends Varien_Object
{
    /**
     * Newly added 'pakje_gemak' address type.
     */
    const ADDRESS_TYPE_PAKJEGEMAK = 'pakje_gemak';

    /**
     * Gets a PostNL Order. If none is set; load one.
     *
     * @return TIG_PostNL_Model_Checkout_Order
     */
    public function getPostnlOrder()
    {
        if ($this->hasPostnlOrder()) {
            $postnlOrder = $this->_getData('postnl_order');

            return $postnlOrder;
        }

        $quote = $this->getQuote();

        $postnlOrder = Mage::getModel('postnl_checkout/order');
        $postnlOrder->load($quote->getId(), 'quote_id');

        $this->setPostnlOrder($postnlOrder);
        return $postnlOrder;
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if ($this->hasQuote()) {
            $quote = $this->_getData('quote');

            return $quote;
        }

        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $this->setQuote($quote);
        return $quote;
    }

    /**
     * @param float|int $costs
     *
     * @throws InvalidArgumentException
     *
     * @return TIG_PostNL_Model_DeliveryOptions_Service
     */
    public function saveOptionCosts($costs)
    {
        if (!is_float($costs) && !is_int($costs)) {
            throw new InvalidArgumentException(
                Mage::helper('postnl')->__('Invalid parameter. Expected a float or an int.')
            );
        }

        $quote = $this->getQuote();

        $postnlOrder = $this->getPostnlOrder();
        $postnlOrder->setQuoteId($quote->getId())
                    ->setIsActive(true)
                    ->setShipmentCosts($costs)
                    ->save();

        return $this;
    }

    /**
     * @param $data
     *
     * @return $this
     */
    public function saveDeliveryOption($data)
    {
        $quote = $this->getQuote();

        /**
         * @var TIG_PostNL_Model_Checkout_Order $postnlOrder
         */
        $postnlOrder = $this->getPostnlOrder();
        $postnlOrder->setQuoteId($quote->getId())
                    ->setIsActive(true)
                    ->setIsPakjeGemak(false)
                    ->setType($data['type'])
                    ->setShipmentCosts($data['costs']);

        /**
         * Remove any existing PakjeGemak addresses.
         *
         * @var Mage_Sales_Model_Quote_Address $quoteAddress
         */
        foreach ($quote->getAllAddresses() as $quoteAddress) {
            if ($quoteAddress->getAddressType() == self::ADDRESS_TYPE_PAKJEGEMAK) {
                $quoteAddress->isDeleted(true);
            }
        }

        /**
         * Add an optional PakjeGemak address.
         */
        if (array_key_exists('address', $data)) {
            $address = $data['address'];

            $pakjeGemakAddress = Mage::getModel('sales/quote_address');
            $pakjeGemakAddress->setAddressType(self::ADDRESS_TYPE_PAKJEGEMAK);
            $pakjeGemakAddress->setCity($address['city'])
                              ->setCountryId($address['countryCode'])
                              ->setStreet1($address['street'])
                              ->setStreet2($address['houseNumber'])
                              ->setPostcode($address['postcode']);

            if (array_key_exists('houseNumberExtension', $address)) {
                $pakjeGemakAddress->setStreet3($address['houseNumberExtension']);
            }

            $quote->addAddress($pakjeGemakAddress)
                  ->save();

            $postnlOrder->setIsPakjeGemak(true);
        }

        $postnlOrder->save();

        return $this;
    }
}