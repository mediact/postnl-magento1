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
class TIG_PostNL_Adminhtml_ShipmentController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @var array
     */
    protected $_warnings = array();

    /**
     * @return array
     */
    public function getWarnings()
    {
        return $this->_warnings;
    }

    /**
     * @param array $warnings
     *
     * @return $this
     */
    public function setWarnings(array $warnings)
    {
        $this->_warnings = $warnings;

        return $this;
    }

    /**
     * @param array|string $warning
     *
     * @return $this
     */
    public function addWarning($warning)
    {
        if (!is_array($warning)) {
            $warning = array(
                'entity_id'   => null,
                'code'        => null,
                'description' => $warning,
            );
        }

        $warnings = $this->getWarnings();
        $warnings[] = $warning;

        $this->setWarnings($warnings);
        return $this;
    }

    /**
     * Print a shipping label for a single shipment
     *
     * @return $this
     */
    public function printLabelAction()
    {
        $helper = Mage::helper('postnl');
        if (!$this->_checkIsAllowed('print_label')) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        $shipmentId = $this->getRequest()->getParam('shipment_id');

        /**
         * If no shipment was selected, cause an error
         */
        if (is_null($shipmentId)) {
            $helper->addSessionMessage('adminhtml/session', null, 'error',
                $this->__('Please select a shipment.')
            );
            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        try {
            /**
             * Load the shipment and check if it exists and is valid.
             *
             * @var Mage_Sales_Model_Order_Shipment $shipment
             */
            $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
            $postnlShippingMethods = Mage::helper('postnl/carrier')->getPostnlShippingMethods();
            if (!in_array($shipment->getOrder()->getShippingMethod(), $postnlShippingMethods)) {
                throw new TIG_PostNL_Exception(
                    $this->__(
                        'This action is not available for shipment #%s, because it was not shipped using PostNL.',
                        $shipmentId
                    ),
                    'POSTNL-0009'
                );
            }

            /**
             * get the labels from CIF
             */
            $labels = $this->_getLabels($shipment);

            /**
             * We need to check for warnings before the label download response
             */
            $this->_checkForWarnings();

            /**
             * merge the labels and print them
             */
            $labelModel = Mage::getModel('postnl_core/label');
            $output = $labelModel->createPdf($labels);

            $filename = 'PostNL Shipping Labels' . date('YmdHis') . '.pdf';

            $this->_preparePdfResponse($filename, $output);
        } catch (TIG_PostNL_Model_Core_Cif_Exception $e) {
            Mage::helper('postnl/cif')->parseCifException($e);

            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        } catch (TIG_PostNL_Exception $e) {
            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        } catch (Exception $e) {
            $helper->logException($e);
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0010', 'error',
                $this->__('An error occurred while processing this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        return $this;
    }

    /**
     * Confirm a PosTNL shipment without printing a label
     *
     * @return $this
     */
    public function confirmAction()
    {
        $helper = Mage::helper('postnl');
        if (!$this->_checkIsAllowed('confirm')) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        $shipmentId = $this->getRequest()->getParam('shipment_id');

        /**
         * If no shipment was selected, cause an error
         */
        if (is_null($shipmentId)) {
            $helper->addSessionMessage('adminhtml/session', null, 'error',
                $this->__('Please select a shipment.')
            );
            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        try {
            /**
             * Load the shipment and check if it exists and is valid
             *
             * @var Mage_Sales_Model_Order_Shipment $shipment
             */
            $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
            $postnlShippingMethods = Mage::helper('postnl/carrier')->getPostnlShippingMethods();
            if (!in_array($shipment->getOrder()->getShippingMethod(), $postnlShippingMethods)) {
                throw new TIG_PostNL_Exception(
                    $this->__(
                        'This action is not available for shipment #%s, because it was not shipped using PostNL.',
                        $shipmentId
                    ),
                    'POSTNL-0009'
                );
            }

            /**
             * Confirm the shipment
             */
            $this->_confirmShipment($shipment);
        } catch (TIG_PostNL_Model_Core_Cif_Exception $e) {
            Mage::helper('postnl/cif')->parseCifException($e);

            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        } catch (TIG_PostNL_Exception $e) {
            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        } catch (Exception $e) {
            $helper->logException($e);
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0010', 'error',
                $this->__('An error occurred while processing this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        $this->_checkForWarnings();

        $helper->addSessionMessage('adminhtml/session', null, 'success',
            $this->__('The shipment has been successfully confirmed')
        );

        /**
         * Redirect to either the grid or the shipment view.
         */
        if ($this->getRequest()->getParam('return_to_view')) {
            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
        } else {
            $this->_redirect('adminhtml/sales_shipment/index');
        }
        return $this;
    }

    /**
     * Loads the status history tab on the shipment view page
     *
     * @return $this
     */
    public function statusHistoryAction()
    {
        $helper = Mage::helper('postnl');
        if (!$this->_checkIsAllowed('view_complete_status')) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        $shipmentId = $this->getRequest()->getParam('shipment_id');
        $postnlShipment = $this->_getPostnlShipment($shipmentId);
        Mage::register('current_postnl_shipment', $postnlShipment);

        /**
         * Get the postnl shipments' status history updated at timestamp and a reference timestamp of 15 minutes ago
         */
        $currentTimestamp = Mage::getModel('core/date')->gmtTimestamp();
        $fifteenMinutesAgo = strtotime("-15 minutes", $currentTimestamp);
        $statusHistoryUpdatedAt = $postnlShipment->getStatusHistoryUpdatedAt();

        /**
         * If this shipment's status history has not been updated in the last 15 minutes (if ever) update it
         */
        if ($postnlShipment->getId()
            && ($postnlShipment->getStatusHistoryUpdatedAt() === null
                || strtotime($statusHistoryUpdatedAt) < $fifteenMinutesAgo
            )
        ) {
            try {
                $postnlShipment->updateCompleteShippingStatus()
                               ->save();
            } catch (Exception $e) {
                /**
                 * This request may return a valid exception when the shipment could not be found
                 */
                Mage::helper('postnl')->logException($e);
            }
        }

        $this->loadLayout();
        $this->renderLayout();

        return $this;
    }

    /**
     * Manually sends a track & trace email to the customer.
     *
     * @return $this
     */
    public function sendTrackAndTraceAction()
    {
        $helper = Mage::helper('postnl');
        if (!$this->_checkIsAllowed('send_track_and_trace')) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        $shipmentId = $this->getRequest()->getParam('shipment_id');

        /**
         * If no shipment was selected, cause an error
         */
        if (is_null($shipmentId)) {
            $helper->addSessionMessage('adminhtml/session', null, 'error',
                $this->__('Shipment not found.')
            );
            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        }

        try {
            /**
             * Load the shipment and check if it exists and is valid.
             *
             * @var Mage_Sales_Model_Order_Shipment $shipment
             */
            $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
            $postnlShippingMethods = Mage::helper('postnl/carrier')->getPostnlShippingMethods();
            if (!in_array($shipment->getOrder()->getShippingMethod(), $postnlShippingMethods)) {
                throw new TIG_PostNL_Exception(
                    $this->__(
                        'This action is not available for shipment #%s, because it was not shipped using PostNL.',
                        $shipmentId
                    ),
                    'POSTNL-0009'
                );
            }

            $postnlShipment = $this->_getPostnlShipment($shipmentId);
            $postnlShipment->sendTrackAndTraceEmail(true, true);
        } catch (TIG_PostNL_Model_Core_Cif_Exception $e) {
            Mage::helper('postnl/cif')->parseCifException($e);

            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        } catch (TIG_PostNL_Exception $e) {
            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        } catch (Exception $e) {
            $helper->logException($e);
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0010', 'error',
                $this->__('An error occurred while processing this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        }

        $helper->addSessionMessage('adminhtml/session', null, 'success',
            $this->__('The track & trace email was sent.')
        );

        $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
        return $this;
    }

    /**
     * Resets a single shipment's confirmation status.
     *
     * @return $this
     */
    public function resetConfirmationAction()
    {
        $helper = Mage::helper('postnl');
        $shipmentId = $this->getRequest()->getParam('shipment_id');
        if (!$this->_checkIsAllowed(array('reset_confirmation', 'delete_labels'))) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        }


        /**
         * If no shipment was selected, cause an error
         */
        if (is_null($shipmentId)) {
            $helper->addSessionMessage('adminhtml/session', null, 'error',
                $this->__('Shipment not found.')
            );
            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        }

        try {
            /**
             * Load the shipment and check if it exists and is valid.
             *
             * @var Mage_Sales_Model_Order_Shipment $shipment
             */
            $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
            $postnlShippingMethods = Mage::helper('postnl/carrier')->getPostnlShippingMethods();
            if (!in_array($shipment->getOrder()->getShippingMethod(), $postnlShippingMethods)) {
                throw new TIG_PostNL_Exception(
                    $this->__(
                        'This action is not available for shipment #%s, because it was not shipped using PostNL.',
                        $shipmentId
                    ),
                    'POSTNL-0009'
                );
            }

            $postnlShipment = $this->_getPostnlShipment($shipmentId);
            $postnlShipment->resetConfirmation(true, true)->save();
        } catch (TIG_PostNL_Model_Core_Cif_Exception $e) {
            Mage::helper('postnl/cif')->parseCifException($e);

            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        } catch (TIG_PostNL_Exception $e) {
            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        } catch (Exception $e) {
            $helper->logException($e);
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0010', 'error',
                $this->__('An error occurred while processing this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        }

        $helper->addSessionMessage('adminhtml/session', null, 'success',
            $this->__("The shipment's confirmation has been undone.")
        );

        $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
        return $this;
    }

    /**
     * Remove a shipment's shipping labels.
     *
     * @return $this
     */
    public function removeLabelsAction()
    {
        $shipmentId = $this->getRequest()->getParam('shipment_id');
        $helper = Mage::helper('postnl');
        if (!$this->_checkIsAllowed('delete_labels')) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        }

        /**
         * If no shipment was selected, cause an error
         */
        if (is_null($shipmentId)) {
            $helper->addSessionMessage('adminhtml/session', null, 'error',
                $this->__('Shipment not found.')
            );
            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        }

        try {
            /**
             * Load the shipment and check if it exists and is valid.
             *
             * @var Mage_Sales_Model_Order_Shipment $shipment
             */
            $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
            $postnlShippingMethods = Mage::helper('postnl/carrier')->getPostnlShippingMethods();
            if (!in_array($shipment->getOrder()->getShippingMethod(), $postnlShippingMethods)) {
                throw new TIG_PostNL_Exception(
                    $this->__(
                        'This action is not available for shipment #%s, because it was not shipped using PostNL.',
                        $shipmentId
                    ),
                    'POSTNL-0009'
                );
            }

            $postnlShipment = $this->_getPostnlShipment($shipmentId);
            $postnlShipment->deleteLabels()
                           ->setLabelsPrinted(false)
                           ->save();
        } catch (TIG_PostNL_Model_Core_Cif_Exception $e) {
            Mage::helper('postnl/cif')->parseCifException($e);

            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        } catch (TIG_PostNL_Exception $e) {
            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        } catch (Exception $e) {
            $helper->logException($e);
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0010', 'error',
                $this->__('An error occurred while processing this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
            return $this;
        }

        $helper->addSessionMessage('adminhtml/session', null, 'success',
            $this->__("The shipment's shipping labels have been deleted.")
        );

        $this->_redirect('adminhtml/sales_shipment/view', array('shipment_id' => $shipmentId));
        return $this;
    }

    /**
     * Refreshes the status history grid after a filter or sorting request
     *
     * @return $this
     */
    public function statusHistoryGridAction()
    {
        $this->_checkIsAllowed('view_complete_status');

        $this->loadLayout(false);
        $this->renderLayout();

        return $this;
    }

    /**
     * Creates shipments for a supplied array of orders. This action is triggered by a massaction in the sales > order
     * grid.
     *
     * @return $this
     */
    public function massCreateShipmentsAction()
    {
        $helper = Mage::helper('postnl');
        if (!$this->_checkIsAllowed('create_shipment')) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_order/index');
            return $this;
        }

        $extraOptions = array();

        /**
         * Check if any options were selected. If not, the default will be used
         */
        $chosenOptions = $this->getRequest()->getParam('product_options', array());

        if (!empty($chosenOptions)) {
            Mage::register('postnl_product_option', $chosenOptions);
        }

        /**
         * Check if an extra cover amount was entered
         */
        $extraCoverValue = $this->getRequest()->getParam('extra_cover_value');
        if ($extraCoverValue) {
            $extraOptions['extra_cover_amount'] = $extraCoverValue;
        }

        /**
         * Check if a shipment type was specified
         */
        $shipmentType = $this->getRequest()->getParam('globalpack_shipment_type');
        if ($shipmentType) {
            $extraOptions['globalpack_shipment_type'] = $shipmentType;
        }

        /**
         * Check if a shipment should be treated as abandoned when it can't be delivered
         */
        $treatAsAbandoned = $this->getRequest()->getParam('globalpack_treat_as_abandoned');
        if ($treatAsAbandoned) {
            $extraOptions['treat_as_abandoned'] = $treatAsAbandoned;
        }

        /**
         * Register the extra options
         */
        if (!empty($extraOptions)) {
            Mage::register('postnl_additional_options', $extraOptions);

        }

        try {
            $orderIds = $this->_getOrderIds();

            /**
             * Create the shipments.
             */
            $errors = 0;
            foreach ($orderIds as $orderId) {
                try {
                    $this->_createShipment($orderId);
                } catch (TIG_PostNL_Exception $e) {
                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $orderId,
                            'code'        => $e->getCode(),
                            'description' => $e->getMessage(),
                        )
                    );
                    $errors++;
                } catch (Exception $e) {
                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $orderId,
                            'code'        => null,
                            'description' => $e->getMessage(),
                        )
                    );
                    $errors++;
                }
            }
        } catch (TIG_PostNL_Model_Core_Cif_Exception $e) {
            Mage::helper('postnl/cif')->parseCifException($e);

            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_order/index');
            return $this;
        } catch (TIG_PostNL_Exception $e) {
            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_order/index');
            return $this;
        } catch (Exception $e) {
            $helper->logException($e);
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0010', 'error',
                $this->__('An error occurred while processing this action.')
            );

            $this->_redirect('adminhtml/sales_order/index');
            return $this;
        }

        /**
         * Check for warnings.
         */
        $this->_checkForWarnings();

        /**
         * Add either a success or failure message and redirect the user accordingly.
         */
        if ($errors < count($orderIds)) {
            $helper->addSessionMessage(
                'adminhtml/session', null, 'success',
                $this->__('The shipments were successfully created.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
        } else {
            $helper->addSessionMessage(
                'adminhtml/session', null, 'error',
                $this->__('None of the shipments could be created. Please check the error messages for more details.')
            );

            $this->_redirect('adminhtml/sales_order/index');
        }

        return $this;
    }

    /**
     * Prints shipping labels and confirms selected shipments.
     *
     * Please note that if you use a different label than the default 'GraphicFile|PDF' you must overload the
     * 'postnl_core/label' model.
     *
     * @return $this
     */
    public function massPrintLabelsAndConfirmAction()
    {
        $helper = Mage::helper('postnl');
        if (!$this->_checkIsAllowed(array('print_label', 'confirm'))) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        $labels = array();
        try {
            $shipmentIds = $this->_getShipmentIds();

            /**
             * Validate the number of labels to be printed. Every shipment has at least 1 label. So if we have more than 200
             * shipments we can stop the process right here.
             *
             * @var $labelClassName TIG_PostNL_Model_Core_Label
             */
            $labelClassName = Mage::getConfig()->getModelClassName('postnl_core/label');
            if(count($shipmentIds) > $labelClassName::MAX_LABEL_COUNT
                && !Mage::helper('postnl/cif')->allowInfinitePrinting()
            ) {
                throw new TIG_PostNL_Exception(
                    $this->__('You can print a maximum of 200 labels at once.'),
                    'POSTNL-0014'
                );
            }

            /**
             * Load the shipments and check if they are valid
             */
            $shipments = $this->_loadAndCheckShipments($shipmentIds, true, false);

            /**
             * Get the labels from CIF.
             *
             * @var TIG_PostNL_Model_Core_Shipment $shipment
             */
            foreach ($shipments as $shipment) {
                try {
                    $shipmentLabels = $this->_getLabels($shipment, true);
                    $labels = array_merge($labels, $shipmentLabels);
                } catch (TIG_PostNL_Model_Core_Cif_Exception $e) {
                    Mage::helper('postnl/cif')->parseCifException($e);

                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $shipment->getShipmentIncrementId(),
                            'code'        => $e->getCode(),
                            'description' => $e->getMessage(),
                        )
                    );
                } catch (TIG_PostNL_Exception $e) {
                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $shipment->getShipmentIncrementId(),
                            'code'        => $e->getCode(),
                            'description' => $e->getMessage(),
                        )
                    );
                } catch (Exception $e) {
                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $shipment->getShipmentIncrementId(),
                            'code'        => null,
                            'description' => $e->getMessage(),
                        )
                    );
                }
            }

            /**
             * We need to check for warnings before the label download response
             */
            $this->_checkForWarnings();

            if (!$labels) {
                $helper->addSessionMessage('adminhtml/session', null, 'error',
                    $this->__(
                        'Unfortunately no shipments could be processed. Please check the error messages for more ' .
                        'details.'
                    )
                );

                $this->_redirect('adminhtml/sales_shipment/index');
                return $this;
            }

            /**
             * The label wills be base64 encoded strings. Convert these to a single pdf
             */
            $label = Mage::getModel('postnl_core/label');

            if ($this->getRequest()->getPost('print_start_pos')) {
                $label->setLabelCounter($this->getRequest()->getPost('print_start_pos'));
            }

            $output = $label->createPdf($labels);

            $filename = 'PostNL Shipping Labels' . date('YmdHis') . '.pdf';

            $this->_preparePdfResponse($filename, $output);
        } catch (TIG_PostNL_Exception $e) {
            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        } catch (Exception $e) {
            $helper->logException($e);
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0010', 'error',
                $this->__('An error occurred while processing this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        return $this;
    }

    /**
     * Prints shipping labels for selected shipments.
     *
     * Please note that if you use a different label than the default 'GraphicFile|PDF' you must overload the
     * 'postnl_core/label' model.
     *
     * @return $this
     */
    public function massPrintLabelsAction()
    {
        $helper = Mage::helper('postnl');
        if (!$this->_checkIsAllowed('print_label')) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        $labels = array();
        try {
            $shipmentIds = $this->_getShipmentIds();

            /**
             * @var $labelClassName TIG_PostNL_Model_Core_Label
             */
            $labelClassName = Mage::getConfig()->getModelClassName('postnl_core/label');
            if(count($shipmentIds) > $labelClassName::MAX_LABEL_COUNT
                && !Mage::helper('postnl/cif')->allowInfinitePrinting()
            ) {
                throw new TIG_PostNL_Exception(
                    $this->__('You can print a maximum of 200 labels at once.'),
                    'POSTNL-0014'
                );
            }

            /**
             * Load the shipments and check if they are valid.
             */
            $shipments = $this->_loadAndCheckShipments($shipmentIds, true, false);

            /**
             * Get the labels from CIF.
             *
             * @var TIG_PostNL_Model_Core_Shipment $shipment
             */
            foreach ($shipments as $shipment) {
                try {
                    $shipmentLabels = $this->_getLabels($shipment, true);
                    $labels = array_merge($labels, $shipmentLabels);
                } catch (TIG_PostNL_Exception $e) {
                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $shipment->getShipmentIncrementId(),
                            'code'        => $e->getCode(),
                            'description' => $e->getMessage(),
                        )
                    );
                } catch (Exception $e) {
                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $shipment->getShipmentIncrementId(),
                            'code'        => null,
                            'description' => $e->getMessage(),
                        )
                    );
                }
            }

            /**
             * We need to check for warnings before the label download response
             */
            $this->_checkForWarnings();

            if (!$labels) {
                $helper->addSessionMessage('adminhtml/session', null, 'error',
                    $this->__(
                        'Unfortunately no shipments could be processed. Please check the error messages for more ' .
                        'details.'
                    )
                );

                $this->_redirect('adminhtml/sales_shipment/index');
                return $this;
            }

            /**
             * The label wills be base64 encoded strings. Convert these to a single pdf
             */
            $label = Mage::getModel('postnl_core/label');

            if ($this->getRequest()->getPost('print_start_pos')) {
                $label->setLabelCounter($this->getRequest()->getPost('print_start_pos'));
            }

            $output = $label->createPdf($labels);

            $fileName = 'PostNL Shipping Labels' . date('YmdHis') . '.pdf';

            $this->_preparePdfResponse($fileName, $output);
        } catch (TIG_PostNL_Model_Core_Cif_Exception $e) {
            Mage::helper('postnl/cif')->parseCifException($e);

            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        } catch (TIG_PostNL_Exception $e) {
            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        } catch (Exception $e) {
            $helper->logException($e);
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0010', 'error',
                $this->__('An error occurred while processing this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        return $this;
    }

    /**
     * Prints shipping labels for selected shipments.
     *
     * Please note that if you use a different label than the default 'GraphicFile|PDF' you must overload the
     * 'postnl_core/label' model.
     *
     * @return $this
     */
    public function massPrintPackingSlipsAction()
    {
        $helper = Mage::helper('postnl');
        if (!$this->_checkIsAllowed('print_label')) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        try {
            if ($this->getRequest()->getParam('shipment_ids')) {
                $shipmentIds = $this->_getShipmentIds();
            } else {
                $orderIds = $this->_getOrderIds();

                $shipmentCollection = Mage::getResourceModel('sales/order_shipment_collection')
                                          ->addFieldToSelect('entity_id')
                                          ->addFieldToFilter('order_id', array('in', $orderIds));

                $shipmentIds = $shipmentCollection->getColumnValues('entity_id');
                unset($shipmentCollection);

                /**
                 * Check if a shipment was selected
                 */
                if (empty($shipmentIds)) {
                    throw new TIG_PostNL_Exception(
                        $this->__(
                            'None of the orders you have selected have any associated shipments. Please choose at least ' .
                            'one order that has a shipment.'
                        ),
                        'POSTNL-0171'
                    );
                }
            }

            /**
             * @var $labelClassName TIG_PostNL_Model_Core_Label
             */
            $labelClassName = Mage::getConfig()->getModelClassName('postnl_core/label');
            if(count($shipmentIds) > $labelClassName::MAX_LABEL_COUNT
                && !Mage::helper('postnl/cif')->allowInfinitePrinting()
            ) {
                throw new TIG_PostNL_Exception(
                    $this->__('You can print a maximum of 200 labels at once.'),
                    'POSTNL-0014'
                );
            }

            /**
             * Printing many packing slips can take a while, therefore we need to disable the PHP execution time limit.
             */
            set_time_limit(0);

            /**
             * Load the shipments and check if they are valid.
             */
            $shipments = $this->_loadAndCheckShipments($shipmentIds, true, false);

            /**
             * Get the packing slip model.
             */
            $packingSlipModel = Mage::getModel('postnl_core/packingSlip');

            /**
             * Get the current memory limit as an integer in bytes. Because printing packing slips can be very memory
             * intensive, we need to monitor memory usage.
             */
            $memoryLimit = $helper->getMemoryLimit();

            /**
             * Create the pdf's and add them to the main pdf object.
             *
             * @var TIG_PostNL_Model_Core_Shipment $shipment
             */
            $pdf = new Zend_Pdf();
            foreach ($shipments as $shipment) {
                try {
                    /**
                     * If the current memory usage exceeds 75%, end the script. Otherwise we risk other processes being
                     * unable to finish and throwing fatal errors.
                     */
                    $memoryUsage = memory_get_usage(true);

                    if ($memoryUsage / $memoryLimit > 0.75) {
                        throw new TIG_PostNL_Exception(
                            $this->__(
                                'Approaching memory limit for this operation. Please select fewer shipments and try ' .
                                'again.'
                            ),
                            'POSTNL-170'
                        );
                    }

                    $shipmentLabels = $this->_getLabels($shipment, false);
                    $packingSlipModel->createPdf($shipmentLabels, $shipment, $pdf);
                } catch (TIG_PostNL_Model_Core_Cif_Exception $e) {
                    Mage::helper('postnl/cif')->parseCifException($e);

                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $shipment->getShipmentIncrementId(),
                            'code'        => $e->getCode(),
                            'description' => $e->getMessage(),
                        )
                    );
                } catch (TIG_PostNL_Exception $e) {
                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $shipment->getShipmentIncrementId(),
                            'code'        => $e->getCode(),
                            'description' => $e->getMessage(),
                        )
                    );
                } catch (Exception $e) {
                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $shipment->getShipmentIncrementId(),
                            'code'        => null,
                            'description' => $e->getMessage(),
                        )
                    );
                }
            }
            unset($shipment, $shipments, $shipmentLabels, $packingSlip, $packingSlipModel);

            /**
             * We need to check for warnings before the label download response.
             */
            $this->_checkForWarnings();

            if (!$pdf->pages) {
                $helper->addSessionMessage('adminhtml/session', null, 'error',
                    $this->__(
                        'Unfortunately no shipments could be processed. Please check the error messages for more ' .
                        'details.'
                    )
                );

                $this->_redirect('adminhtml/sales_shipment/index');
                return $this;
            }

            /**
             * Render the pdf as a string.
             */
            $output = $pdf->render();

            $fileName = 'PostNL Packing Slips '
                      . date('Ymd-His', Mage::getSingleton('core/date')->timestamp())
                      . '.pdf';

            $this->_preparePdfResponse($fileName, $output);
        } catch (TIG_PostNL_Exception $e) {
            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        } catch (Exception $e) {
            $helper->logException($e);
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0010', 'error',
                $this->__('An error occurred while processing this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        return $this;
    }

    /**
     * Prints shipping labels and confirms selected shipments.
     *
     * Please note that if you use a different label than the default 'GraphicFile|PDF' you must overload the
     * 'postnl_core/label' model.
     *
     * @return $this
     */
    public function massConfirmAction()
    {
        $helper = Mage::helper('postnl');
        if (!$this->_checkIsAllowed('confirm')) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        try {
            $shipmentIds = $this->_getShipmentIds();

            /**
             * Load the shipments and check if they are valid
             */
            $shipments = $this->_loadAndCheckShipments($shipmentIds, true, false);

            /**
             * Confirm the shipments.
             *
             * @var TIG_PostNL_Model_Core_Shipment $shipment
             */
            $errors = 0;
            foreach ($shipments as $shipment) {
                try {
                    $this->_confirmShipment($shipment);
                } catch (TIG_PostNL_Model_Core_Cif_Exception $e) {
                    Mage::helper('postnl/cif')->parseCifException($e);

                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $shipment->getShipmentIncrementId(),
                            'code'        => $e->getCode(),
                            'description' => $e->getMessage(),
                        )
                    );
                    $errors++;
                } catch (TIG_PostNL_Exception $e) {
                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $shipment->getShipmentIncrementId(),
                            'code'        => $e->getCode(),
                            'description' => $e->getMessage(),
                        )
                    );
                    $errors++;
                } catch (Exception $e) {
                    $helper->logException($e);
                    $this->addWarning(
                        array(
                            'entity_id'   => $shipment->getShipmentIncrementId(),
                            'code'        => null,
                            'description' => $e->getMessage(),
                        )
                    );
                    $errors++;
                }
            }

        } catch (TIG_PostNL_Exception $e) {
            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        } catch (Exception $e) {
            $helper->logException($e);
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0010', 'error',
                $this->__('An error occurred while processing this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        $this->_checkForWarnings();

        if ($errors < count($shipments)) {
            $helper->addSessionMessage(
                'adminhtml/session', null, 'success',
                $this->__('The shipments have been confirmed successfully.')
            );
        } else {
            $helper->addSessionMessage(
                'adminhtml/session', null, 'error',
                $this->__(
                    'Unfortunately no shipments could be processed. Please check the error messages for more details.'
                )
            );
        }

        $this->_redirect('adminhtml/sales_shipment/index');
        return $this;
    }

    /**
     * Creates a Parcelware export file based on the selected shipments
     *
     * @return $this
     */
    public function massCreateParcelwareExportAction()
    {
        $helper = Mage::helper('postnl');
        if (!$this->_checkIsAllowed('create_parcelware_export')) {
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0155', 'error',
                $this->__('The current user is not allowed to perform this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        try {
            $shipmentIds = $this->_getShipmentIds();

            /**
             * Load the shipments and check if they are valid
             */
            $shipments = $this->_loadAndCheckShipments($shipmentIds, true);

            /**
             * @var TIG_PostNL_Model_Parcelware_Export $parcelwareExportModel
             */
            $parcelwareExportModel = Mage::getModel('postnl_parcelware/export');
            $csvContents = $parcelwareExportModel->exportShipments($shipments);

            $timestamp = date('Ymd_His', Mage::getModel('core/date')->timestamp());

            $this->_prepareDownloadResponse("PostNL_Parcelware_Export_{$timestamp}.csv", $csvContents);
        } catch (TIG_PostNL_Exception $e) {
            $helper->logException($e);
            $helper->addExceptionSessionMessage('adminhtml/session', $e);

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        } catch (Exception $e) {
            $helper->logException($e);
            $helper->addSessionMessage('adminhtml/session', 'POSTNL-0010', 'error',
                $this->__('An error occurred while processing this action.')
            );

            $this->_redirect('adminhtml/sales_shipment/index');
            return $this;
        }

        $this->_redirect('adminhtml/sales_shipment/index');
        return $this;
    }

    /**
     * Get shipment Ids from the request.
     *
     * @return array
     *
     * @throws TIG_PostNL_Exception
     */
    protected function _getShipmentIds()
    {
        $shipmentIds = $this->getRequest()->getParam('shipment_ids', array());

        /**
         * Check if a shipment was selected.
         */
        if (!is_array($shipmentIds) || empty($shipmentIds)) {
            throw new TIG_PostNL_Exception(
                $this->__('Please select one or more shipments.'),
                'POSTNL-0013'
            );
        }

        return $shipmentIds;
    }

    /**
     * Get order Ids from the request.
     *
     * @return array
     *
     * @throws TIG_PostNL_Exception
     */
    protected function _getOrderIds()
    {
        $orderIds = $this->getRequest()->getParam('order_ids', array());

        /**
         * Check if an order was selected.
         */
        if (!is_array($orderIds) || empty($orderIds)) {
            throw new TIG_PostNL_Exception(
                $this->__('Please select one or more orders.'),
                'POSTNL-0011'
            );
        }

        return $orderIds;
    }

    /**
     * Gets the postnl shipment associated with a shipment
     *
     * @param int $shipmentId
     *
     * @return TIG_PostNL_Model_Core_Shipment
     */
    protected function _getPostnlShipment($shipmentId)
    {
        $postnlShipment = Mage::getModel('postnl_core/shipment')->load($shipmentId, 'shipment_id');

        return $postnlShipment;
    }

    /**
     * Initialize shipment items QTY
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return array
     */
    protected function _getItemQtys($order)
    {
        $itemQtys = array();

        /**
         * @var Mage_Sales_Model_Order_Item $item
         */
        $items = $order->getAllVisibleItems();
        foreach ($items as $item) {
            /**
             * the qty to ship is the total remaining (not yet shipped) qty of every item
             */
            $itemQty = $item->getQtyOrdered() - $item->getQtyShipped();

            $itemQtys[$item->getId()] = $itemQty;
        }

        return $itemQtys;
    }

    /**
     * Creates a shipment of an order containing all available items
     *
     * @param int $orderId
     *
     * @return $this
     *
     * @throws TIG_PostNL_Exception
     */
    protected function _createShipment($orderId)
    {
        /**
         * @var Mage_Sales_Model_Order $order
         */
        $order = Mage::getModel('sales/order')->load($orderId);

        if (!$order->canShip()) {
            throw new TIG_PostNL_Exception(
                $this->__("Order #%s cannot be shipped at this time.", $order->getIncrementId()),
                'POSTNL-0015'
            );
        }

        $shipment = Mage::getModel('sales/service_order', $order)
                        ->prepareShipment($this->_getItemQtys($order));

        $shipment->register();
        $this->_saveShipment($shipment);

        return $this;
    }

    /**
     * Save shipment and order in one transaction
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment
     *
     * @return Mage_Adminhtml_Sales_Order_ShipmentController
     */
    protected function _saveShipment($shipment)
    {
        $shipment->getOrder()->setIsInProcess(true);
        Mage::getModel('core/resource_transaction')
            ->addObject($shipment)
            ->addObject($shipment->getOrder())
            ->save();

        return $this;
    }

    /**
     * Retrieves the shipping label for a given shipment ID.
     *
     * If the shipment has a stored label, it is returned. Otherwise a new one is generated.
     *
     * @param Mage_Sales_Model_Order_Shipment|TIG_PostNL_Model_Core_Shipment $shipment
     * @param boolean $confirm Optional parameter to also confirm the shipment
     *
     * @return array
     *
     * @throws TIG_PostNL_Exception
     */
    protected function _getLabels($shipment, $confirm = false)
    {
        /**
         * Load the PostNL shipment.
         */
        if ($shipment instanceof Mage_Sales_Model_Order_Shipment) {
            $postnlShipment = $this->_getPostnlShipment($shipment->getId());
        } else {
            $postnlShipment = $shipment;
        }

        /**
         * Check if the shipment already has any labels. If so, return those. If we also need to confirm the shipment,
         * do that first.
         */
        if ($postnlShipment->hasLabels()) {
            if ($confirm === true && !$postnlShipment->isConfirmed() && $postnlShipment->canConfirm()) {
                $this->_confirmShipment($postnlShipment);
            }

            return $postnlShipment->getlabels();
        }

        /**
         * If the PostNL shipment is new, set the magento shipment ID.
         */
        if (!$postnlShipment->getShipmentId()) {
            $postnlShipment->setShipmentId($shipment->getId());
        }

        /**
         * If the shipment does not have a barcode, generate one.
         */
        if (!$postnlShipment->getMainBarcode() && $postnlShipment->canGenerateBarcode()) {
            $postnlShipment->generateBarcodes();
        }

        if ($confirm === true
            && !$postnlShipment->hasLabels()
            && !$postnlShipment->isConfirmed()
            && $postnlShipment->canConfirm()
        ) {
            /**
             * Confirm the shipment and request a new label.
             */
            $postnlShipment->confirmAndGenerateLabel();

            if ($postnlShipment->canAddTrackingCode()) {
                $postnlShipment->addTrackingCodeToShipment();
            }

            $postnlShipment->save();
        } else {
            /**
             * generate new shipping labels without confirming.
             */
            $postnlShipment->generateLabel()
                           ->save();
        }

        $labels = $postnlShipment->getLabels();
        return $labels;
    }

    /**
     * Confirms the shipment without printing labels.
     *
     * @param Mage_Sales_Model_Order_Shipment|TIG_PostNL_Model_Core_Shipment $shipment
     *
     * @return $this
     *
     * @throws TIG_PostNL_Exception
     */
    protected function _confirmShipment($shipment)
    {
        /**
         * Load the PostNL shipment.
         */
        if ($shipment instanceof Mage_Sales_Model_Order_Shipment) {
            $postnlShipment = $this->_getPostnlShipment($shipment->getId());
        } else {
            $postnlShipment = $shipment;
        }

        /**
         * Prevent EU shipments from being confirmed if their labels are not yet printed.
         */
        if ($postnlShipment->isEuShipment() && !$postnlShipment->getLabelsPrinted()) {
            throw new TIG_PostNL_Exception(
                $this->__(
                    "Shipment #%s could not be confirmed, because for EU shipments you may only confirm a shipment " .
                    "after it's labels have been printed.",
                    $postnlShipment->getShipment()->getIncrementId()
                ),
                'POSTNL-0016'
            );
        }

        /**
         * If the PostNL shipment is new, set the magento shipment ID.
         */
        if (!$postnlShipment->getShipmentId()) {
            $postnlShipment->setShipmentId($shipment->getId());
        }

        /**
         * If the shipment does not have a main barcode, generate new barcodes.
         */
        if (!$postnlShipment->getMainBarcode() && $postnlShipment->canGenerateBarcode()) {
            $postnlShipment->generateBarcodes();
        }

        if ($postnlShipment->getConfirmStatus() === $postnlShipment::CONFIRM_STATUS_CONFIRMED) {
            /**
             * The shipment is already confirmed.
             */
            throw new TIG_PostNL_Exception(
                $this->__('Shipment #%s has already been confirmed.', $postnlShipment->getShipment()->getIncrementId()),
                'POSTNL-0017'
            );
        }

        if (!$postnlShipment->canConfirm()) {
            /**
             * The shipment cannot be confirmed at this time.
             */
            throw new TIG_PostNL_Exception(
                $this->__(
                    'Shipment #%s cannot be confirmed at this time.',
                    $postnlShipment->getShipment()->getIncrementId()
                ),
                'POSTNL-00018'
            );
        }

        /**
         * Confirm the shipment.
         */
        $postnlShipment->confirm();

        if ($postnlShipment->canAddTrackingCode()) {
            $postnlShipment->addTrackingCodeToShipment();
        }

        $postnlShipment->save();

        return $this;
    }

    /**
     * Load an array of shipments based on an array of shipmentIds and check if they're shipped using PostNL
     *
     * @param array|int $shipmentIds
     * @param boolean   $loadPostnlShipments Flag that determines whether the shipments will be loaded as
     *                                       Mage_Sales_Model_Shipment or TIG_PostNL_Model_Core_Shipment objects.
     * @param boolean   $throwException Flag whether an exception should be thrown when loading the shipment fails.
     *
     * @return array
     *
     * @throws TIG_PostNL_Exception
     */
    protected function _loadAndCheckShipments($shipmentIds, $loadPostnlShipments = false, $throwException = true)
    {
        if (!is_array($shipmentIds)) {
            $shipmentIds = array($shipmentIds);
        }

        $shipments = array();
        $postnlShippingMethods = Mage::helper('postnl/carrier')->getPostnlShippingMethods();
        foreach ($shipmentIds as $shipmentId) {
            /**
             * Load the shipment.
             *
             * @var Mage_Sales_Model_Order_Shipment|TIG_PostNL_Model_Core_Shipment|boolean $shipment
             */
            $shipment = $this->_loadShipment($shipmentId, $loadPostnlShipments, $postnlShippingMethods);

            if (!$shipment && $throwException) {
                throw new TIG_PostNL_Exception(
                    $this->__(
                        'This action is not available for shipment #%s, because it was not shipped using PostNL.',
                        $shipment->getIncrementId()
                    ),
                    'POSTNL-0009'
                );
            } elseif (!$shipment) {
                $this->addWarning(
                    array(
                        'entity_id'   => $shipment->getIncrementId(),
                        'code'        => 'POSTNL-0009',
                        'description' => $this->__(
                            'This action is not available for shipment #%s, because it was not shipped using PostNL.',
                            $shipment->getIncrementId()
                        ),
                    )
                );

                continue;
            }

            $shipments[] = $shipment;
        }

        return $shipments;
    }

    /**
     * Load a shipment based on a shipment ID.
     *
     * @param int     $shipmentId
     * @param boolean $loadPostnlShipments
     * @param array   $postnlShippingMethods
     *
     * @return boolean|Mage_Sales_Model_Order_Shipment|TIG_PostNL_Model_Core_Shipment
     */
    protected function _loadShipment($shipmentId, $loadPostnlShipments, $postnlShippingMethods)
    {
        if ($loadPostnlShipments === false) {
            /**
             * @var Mage_Sales_Model_Order_Shipment $shipment
             */
            $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
            if (!$shipment || !$shipment->getId()) {
                return false;
            }

            $shippingMethod = $shipment->getOrder()->getShippingMethod();
        } else {
            /**
             * @var TIG_PostNL_Model_Core_Shipment $shipment
             */
            $shipment = $this->_getPostnlShipment($shipmentId);
            if (!$shipment || !$shipment->getId()) {
                return false;
            }

            $shippingMethod = $shipment->getShipment()->getOrder()->getShippingMethod();
        }

        /**
         * Check if the shipping method used is allowed
         */
        if (!in_array($shippingMethod, $postnlShippingMethods)) {
            return false;
        }

        return $shipment;
    }

    /**
     * @param $filename
     *
     * @return $this
     * @throws Zend_Controller_Response_Exception
     */
    protected function _preparePdfResponse($filename, $output)
    {
        $this->getResponse()
             ->setHttpResponseCode(200)
             ->setHeader('Pragma', 'public', true)
             ->setHeader('Cache-Control', 'private, max-age=0, must-revalidate', true)
             ->setHeader('Content-type', 'application/pdf', true)
             ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
             ->setHeader('Last-Modified', date('r'))
             ->setBody($output);

        return $this;
    }

    /**
     * Checks if any warnings were received while processing the shipments and/or orders. If any warnings are found they
     * are added to the adminhtml session as a notice.
     *
     * @return $this
     */
    protected function _checkForWarnings()
    {
        /**
         * Check if any warnings were registered
         */
        $cifWarnings = Mage::registry('postnl_cif_warnings');

        if (is_array($cifWarnings) && !empty($cifWarnings)) {
            $this->_addWarningMessages($cifWarnings, $this->__('PostNL replied with the following warnings:'));
        }

        $warnings = $this->getWarnings();

        if (!empty($warnings)) {
            $this->_addWarningMessages(
                $warnings,
                $this->__('The following shipments or orders could not be processed:')
            );
        }

        return $this;
    }

    /**
     * Add an array of warning messages to the adminhtml session.
     *
     * @param        $warnings
     * @param string $headerText
     *
     * @return $this
     * @throws TIG_PostNL_Exception
     */
    protected function _addWarningMessages($warnings, $headerText = '')
    {
        $helper = Mage::helper('postnl');

        /**
         * Create a warning message to display to the merchant.
         */
        $warningMessage = $headerText;
        $warningMessage .= '<ul class="postnl-warning">';

        /**
         * Add each warning to the message.
         */
        foreach ($warnings as $warning) {
            /**
             * Warnings must have a description.
             */
            if (!array_key_exists('description', $warning)) {
                continue;
            }

            /**
             * Codes are optional for warnings, but must be present in the array. If no code is found in the warning we
             * add an empty one.
             */
            if (!array_key_exists('code', $warning)) {
                $warning['code'] = null;
            }

            /**
             * Get the formatted warning message.
             */
            $warningText = $helper->getSessionMessage(
                $warning['code'],
                'warning',
                $this->__($warning['description'])
            );

            /**
             * Prepend the warning's entity ID if present.
             */
            if (!empty($warning['entity_id'])) {
                $warningText = $warning['entity_id'] . ': ' . $warningText;
            }

            /**
             * Build the message proper.
             */
            $warningMessage .= '<li>' . $warningText . '</li>';
        }

        $warningMessage .= '</ul>';

        /**
         * Add the warnings to the session.
         */
        Mage::helper('postnl')->addSessionMessage('adminhtml/session', null, 'notice',
            $warningMessage
        );

        return $this;
    }

    /**
     * Checks if the specified actions are allowed.
     *
     * @param array $actions
     *
     * @throws TIG_PostNL_Exception
     *
     * @return bool
     */
    protected function _checkIsAllowed($actions = array())
    {
        $helper = Mage::helper('postnl');
        $isAllowed = $helper->checkIsPostnlActionAllowed($actions, false);

        return $isAllowed;
    }
}