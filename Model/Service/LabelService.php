<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Model\Service;

class LabelService
{

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Store manager
     *
     * @var \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader
     */
    protected $_shipmentLoader;

    /**
     * Store manager
     *
     * @var \Magento\Shipping\Model\Shipping\LabelGenerator
     */
    protected $_labelGenerator;

    /**
     * Constructor.
     * @param State $state
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->_objectManager = $objectManager;
        $this->_storeManager = $storeManager;

        $this->_shipmentLoader = $objectManager->get(\Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader::class);
        $this->_labelGenerator = $objectManager->get(\Magento\Shipping\Model\Shipping\LabelGenerator::class);
    }

    /**
     * Process orders
     */
    public function processOrders()
    {
        $date = $this->_objectManager->get(\Magento\Framework\Stdlib\DateTime\DateTime::class)->date('Y-m-d_H-i-s');

        $orderCollectionFactory = $this->_objectManager->create(\Magento\Sales\Model\ResourceModel\Order\CollectionFactory::class);
        $orderCollection = $orderCollectionFactory->create()
                ->addAttributeToFilter('shipany_status', 0)
                ->addAttributeToFilter('status', ['in'=> ['processing', 'complete']]);

        foreach($orderCollection as $order) {
            // echo '[ORDER]' . ' ' . $order->getId() . ' ' . $order->getIncrementId() . PHP_EOL;
            // echo '[STATUS] ' . $order->getStatus() . ' ' . $order->getState() . PHP_EOL;

            $shipments = $order->getShipmentsCollection();
            foreach($shipments as $shipment) {
                // echo '[SHIPMENT]' . ' ' . $shipment->getId() . ' ' . $shipment->getIncrementId() . PHP_EOL;

                $this->processShipmentLabel($shipment);
            }
        }
    }

    /**
     * Process shipments
     */
    public function processShipments()
    {
        $date = $this->_objectManager->get(\Magento\Framework\Stdlib\DateTime\DateTime::class)->date('Y-m-d_H-i-s');

        $shipmentCollectionFactory = $this->_objectManager->create(\Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory::class);
        $shipmentCollection = $shipmentCollectionFactory->create()
                ->addAttributeToFilter('created_at', ['gt' => '2022-07-01']);

        foreach($shipmentCollection as $shipment) {
            // echo '[SHIPMENT]' . ' ' . $shipment->getId() . ' ' . $shipment->getIncrementId() . PHP_EOL;

            $this->processShipmentLabel($shipment);
        }
    }

    /**
     * Process shipment label
     */
    public function processShipmentLabel($shipment)
    {
        $state = $shipment->getOrder()->getState();
        $status = $shipment->getOrder()->getStatus();
        if (!in_array($state, ['complete', 'processing'])) {
            return;
        }
        $shippingMethod = $shipment->getOrder()->getShippingMethod();
        if (strpos($shippingMethod, 'freeshipping') !== false) {
            return;
        }

        $labelContent = $shipment->getShippingLabel();
        if ($labelContent) {
            $path = BP . '/pub/media/shipment/shippinglabel/';
            // $file = 'shippinglabel' . '-' . $shipment->getOrder()->getIncrementId() . '-' . $shipment->getIncrementId() . '.pdf';
            $file = 'ShippingLabel-' . $shipment->getOrder()->getIncrementId() . '.pdf';
            if (file_exists($path . $file)) {
                return;
            }

            $pdfContent = null;
            if (stripos($labelContent, '%PDF-') !== false) {
                $pdfContent = $labelContent;
            } else {
                $pdf = new \Zend_Pdf();
                $page = $this->_labelGenerator->createPdfPageFromImageString($labelContent);
                if (!$page) {
                    $this->messageManager->addError(
                        __(
                            'We don\'t recognize or support the file extension in this shipment: %1.',
                            $shipment->getIncrementId()
                        )
                    );
                }
                $pdf->pages[] = $page;
                $pdfContent = $pdf->render();
            }

            file_put_contents($path . $file, $pdfContent);
        } else {
            $this->generateShipmentLabel($shipment);
        }
    }

    /**
     * Process shipment label
     */
    public function processShipmentLabelById($shipmentId)
    {
        // $this->_shipmentLoader->setOrderId($orderId);
        $this->_shipmentLoader->setShipmentId($shipmentId);
        // $this->_shipmentLoader->setShipment($shipment);
        // $this->_shipmentLoader->setTracking($tracking);
        $shipment = $this->_shipmentLoader->load();

        $this->processShipmentLabel($shipment);
    }


    /**
     * Generate shipment label
     */
    public function generateShipmentLabel($shipment)
    {
        $items = [];
        $price = 0;
        $weight = 0;
        foreach ($shipment->getAllItems() as $item) {
            if ($item->getOrderItem()->getParentItem()) {
                continue;
            }

            if ($item->getOrderItem()->getProduct() && $item->getOrderItem()->getProduct()->getName()) {
                $productName = $item->getOrderItem()->getProduct()->getName();
            } else {
                $productName = $item->getSku();
            }

            $items[$item->getId()] = [
                'itemDetails'   => [
                    'qty'           => $item->getQty() * 1,
                    'qtyToShip'     => $item->getQty() * 1,
                    'productId'     => $item->getProductId(),
                    'sku'           => $item->getSku(),
                    'productName'   => $productName,
                    'price'         => number_format($item->getPrice(), 2),
                ],
            ];
            $price += $item->getPrice() * $item->getQty() * 1;
            $weight += $item->getWeight() * $item->getQty() * 1;
        }

        $requestData = [
            'packages'  => [
                [
                    'packageId'     => 1,

                    'items'         => $items,

                    'package'       => [
                        'packageDetails'    => [
                            'productCode'   => 'V01PAK',
                            'weightUnit'    => 'KILOGRAM',
                            'sizeUnit'      => 'CENTIMETER',
                            'weight'        => floor($weight + 5),
                        ],
                    ],

                    // 'params'        => [
                    //     'container'         => '',
                    //     'weight'            => floor($weight + 5),
                    //     'customs_value'     => $price,
                    //     'length'            => '',
                    //     'width'             => '',
                    //     'height'            => '',
                    //     'weight_units'      => 'POUND',
                    //     'dimension_units'   => 'INCH',
                    //     'content_type'      => '',
                    //     'content_type_other'=> '',
                    //     'shipping_product'  => '',
                    // ],

                    'service'       => [
                        'parcelAnnouncement'    => [
                            'enabled'           => false,
                        ],
                        'printOnlyIfCodeable'   => [
                            'enabled'           => false,
                        ],
                        'returnShipment'        => [
                            'enabled'           => false,
                        ],
                        'additionalInsurance'   => [
                            'enabled'           => false,
                        ],
                        'parcelOutletRouting'   => [
                            'enabled'           => false,
                        ],
                        'bulkyGoods'            => [
                            'enabled'           => false,
                        ],
                        'visualCheckOfAge'      => [
                            'enabled'           => false,
                        ],
                    ],
                ],
            ],

            'shipment'          => [
                'shipmentComment'       => '',
                'sendEmail'             => false,
                'notifyCustomer'        => false,
            ],
        ];
        $jsonData = json_encode($requestData);

        $dataConverter = $this->_objectManager->create(\Netresearch\ShippingCore\Api\PackagingPopup\RequestDataConverterInterface::class);
        $requestParams = $dataConverter->getParams($jsonData);

        $labelGenerator = $this->_objectManager->create(\Eastz\AutoShipment\Model\Shipping\LabelGenerator::class);
        $labelGenerator->create($shipment, $requestParams);
        $shipment->save();

        // email notify
        $this->_objectManager->create('Magento\Shipping\Model\ShipmentNotifier')->notify($shipment);
    }
}
