<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Helper;

use Magento\Sales\Model\Order;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;

class ShipUtil
{
    protected $_order;
    protected $_scopeConfig;
    protected $_orderHistoryFactory;
    protected $_orderStatusHistoryRepositoryInterface;
    protected $_storeManager;

    public function __construct(
        Order $order,
        ScopeConfigInterface $scopeConfig,
        HistoryFactory $orderHistoryFactory,
        OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepositoryInterface,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_order = $order;
        $this->_scopeConfig = $scopeConfig;
        $this->_orderHistoryFactory = $orderHistoryFactory;
        $this->_orderStatusHistoryRepositoryInterface = $orderStatusHistoryRepositoryInterface;
        $this->_storeManager = $storeManager;
    }

    function getStoreEmail()
    {
        return $this->_scopeConfig->getValue('trans_email/ident_sales/email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    function getWeightUnit()
    {
        return $this->_scopeConfig->getValue('general/locale/weight_unit', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Prepare order detail
     * 
     * @return
     */
    function prepareOrderDetail($order)
    {
        // Prepare for the sndr information
        $objectManager = ObjectManager::getInstance();
        $storeInformation = $objectManager->create('Magento\Store\Model\Information');
        $store = $objectManager->create('Magento\Store\Model\Store');
        $storeInfo = $storeInformation->getStoreInformationObject($store);

        $child_items = array();

        foreach ($order->getAllItems() as $item) {
            $obj = array(
                'name'          => $item->getName(),
                // 'typ'        => 'Normal',
                // 'descr'      => '',
                // 'ori'        => '',
                'unt_price'     => array(
                    'val'       => $item->getPrice(),
                    'ccy'       => $order->getOrderCurrencyCode()
                ),
                'qty'           => intval($item->getQtyOrdered()),
                'wt'            => array(
                    'val'       => intval($item->getWeight()),
                    'unt'       => ($this->getWeightUnit() == 'kgs') ? 'KG' : $this->getWeightUnit()
                ),
                'dim'           => array(
                    'hgt'       => 1,
                    'len'       => 1,
                    'wid'       => 1
                ),
                'cbm'           => 0,
                'stg'           => 'Normal',
                'sku'           => $item->getSku(),
            );
            $child_items[] = $obj;
        }

        $order_json = array(
            'shopid'            => $this->_storeManager->getStore()->getId(),
            'ext_order_ref'     => $order->getIncrementId(),
            'ext_order_id'      => $order->getIncrementId(),
            'dim'               => array(
                'hgt'           => 1,
                'len'           => 1,
                'wid'           => 1
            ),
            'sndr_ctc'          => array(
                'addr'          => array(
                    'city'      => $storeInfo->getData('city'),
                    'ln'        => $storeInfo->getData('street_line1'),
                    'ln2'       => $storeInfo->getData('street_line2'),
                    'ln3'       => '',
                    'zc'        => $storeInfo->getData('postcode'),
                    'state'     => $storeInfo->getData('region_id'),
                    'typ'       => '',
                    'cnty'      => $storeInfo->getData('country_id')
                    // 'cnty'   => $objectManager->create('\Magento\Directory\Model\Country')->load($storeInfo->getData('country_id'))->getName()
                ),
                'ctc'           => array(
                    'f_name'    => $storeInfo->getData('name'),
                    'l_name'    => '',
                    'co_name'   => $storeInfo->getData('name'),
                    'phs'       => array(
                        array(
                            'typ'   => '',
                            // 'cnty_code' => '',
                            'num'   => $storeInfo->getData('phone')
                        )
                    ),
                    'email'     => $this->getStoreEmail()
                )
            ),
            'rcvr_ctc'          => array(
                'addr'          => array(
                    'city'      => $order->getShippingAddress()->getCity(),
                    'ln'        => $order->getShippingAddress()->getStreetLine(1),
                    'ln2'       => $order->getShippingAddress()->getStreetLine(2),
                    'ln3'       => $order->getShippingAddress()->getStreetLine(3),
                    'zc'        => ($order->getShippingAddress()->getCountryId() == 'HK') ? '000000' : $order->getShippingAddress()->getPostcode(),
                    // 'zc'     => $order->getShippingAddress()->getPostcode(),
                    'state'     => $order->getShippingAddress()->getRegion(),
                    'typ'       => '',
                    'cnty'      => $order->getShippingAddress()->getCountryId(),
                    // 'cnty'   => $objectManager->create('\Magento\Directory\Model\Country')->load($order->getShippingAddress()->getCountryId())->getName()
                ),
                'ctc'           => array(
                    'f_name'    => $order->getShippingAddress()->getFirstname(),
                    'l_name'    => $order->getShippingAddress()->getLastname(),
                    'co_name'   => $order->getShippingAddress()->getCompany(),
                    'phs'       => array(
                        array(
                            'typ'   => '',
                            // 'cnty_code' => '',
                            'num'   => $order->getShippingAddress()->getTelephone()
                        )
                    ),
                    'email'     => $order->getCustomerEmail()
                )
            ),
            'items'             => array(
                array(
                    'sku'       => '',
                    'name'      => '',
                    'typ'       => 'Package',
                    // 'descr'  => '',
                    'ori'       => '',
                    'unt_price' => array(
                        'val'   => 0,
                        'unt'   => $order->getOrderCurrencyCode()
                    ),
                    'qty'       => 1,
                    'wt'        => array(
                        'val'   => $order->getWeight(),
                        'unt'   => ($this->getWeightUnit() == 'kgs') ? 'KG' : $this->getWeightUnit()
                    ),
                    'dim'       => new \ArrayObject(),
                    'cbm'       => 0,
                    'stg'       => 'Normal',
                    'child_items' => $child_items
                ),
            ),
            'mch_ttl_val'       => array(
                'ccy'           => $order->getOrderCurrencyCode(),
                'val'           => intval($order->getGrandTotal() - $order->getShippingAmount())
            ),
            'cour_ttl_cost'     => array(
                'ccy'           => $order->getOrderCurrencyCode(),
                'val'           => intval($order->getShippingAmount())
            ),
            'quots_uid'         => $order->getShippingDescription(),
        );

        return json_encode($order_json);
    }

    /**
     * Create shipany order
     * 
     * @return
     */
    function createShipanyOrder($body)
    {
        $ch = curl_init();

        $objectManager = ObjectManager::getInstance();
        $name = 'ShipAny';
        $api_endpoint = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('autoshipment/shipany/api_endpoint');
        $api_token = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('autoshipment/shipany/token');
        $integrationExists = $objectManager->get('Magento\Integration\Model\IntegrationFactory')->create()->load($name, 'name')->getData();
        $token = $objectManager->get('Magento\Integration\Model\Oauth\Token\Provider');
        $result = $token->getIntegrationTokenByConsumerId($integrationExists['consumer_id']);

        curl_setopt_array($ch, array(
            CURLOPT_URL => $api_endpoint . '/magento/webhooks/receive-orders-paid/',
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'api-tk: ' . $api_token,
                'magento-tk: '.$result->getToken(),
                'Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => $body
        ));

        $response = curl_exec($ch);
        $http_info = curl_getinfo($ch);

        if ($response === false) {
            die(curl_error($ch));
        }

        $responseData = json_decode($response, true);
        
//	debug_time('createShipanyOrder ' . $response);
//      debug_time($responseData);

// Initialize the logger
    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/debug.log');
    $logger = new \Zend\Log\Logger();
    $logger->addWriter($writer);
    
    // Log the required details
    $logger->info('createShipanyOrder ' . $response);
    $logger->info(print_r($responseData, true));

        return $responseData;
    }

    /**
     * Cancel shipany order
     *
     * @return
     */
    function cancelShipanyOrder($body, $orderId)
    {
        $ch = curl_init();

        $objectManager = ObjectManager::getInstance();
        $api_endpoint = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('autoshipment/shipany/api_endpoint');
        $api_token = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('autoshipment/shipany/token');

        curl_setopt_array($ch, array(
            CURLOPT_URL => $api_endpoint . '/magento/webhooks/receive-orders-cancel/',
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'api-tk: ' . $api_token,
                'Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => $body
        ));
        $response = curl_exec($ch);
        $http_info = curl_getinfo($ch);
        curl_close($ch);

        if ($http_info['http_code'] == 200) {
            $this->addCommentToOrder($orderId, $orderId . ' shipany cancel success');
        } else {
            $this->addCommentToOrder($orderId, $orderId . ' shipany cancel fail');
        }

        $responseData = json_decode($response, true);
//        debug_time('cancelShipanyOrder ' . $response);
//        debug_time($responseData);

        return $responseData;
    }

    /**
     * Update shipany order
     * 
     * @return
     */
    function updateShipanyOrder($body)
    {
        $ch = curl_init();

        $objectManager = ObjectManager::getInstance();
        $api_endpoint = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('shipany_config/general/api_endpoint');
        $api_token = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('shipany_config/general/token');

        curl_setopt_array($ch, array(
            CURLOPT_URL => $api_endpoint . '/magento/webhooks/receive-orders-update/',
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'api-tk: ' . $api_token,
                'Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => $body
        ));

        $response = curl_exec($ch);
        $http_info = curl_getinfo($ch);

        if ($response === false) {
            die(curl_error($ch));
        }

        $responseData = json_decode($response, true);
//        debug_time('updateShipanyOrder ' . $response);
//        debug_time($responseData);

    // Initialize the logger
    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/debug.log');
    $logger = new \Zend\Log\Logger();
    $logger->addWriter($writer);
    
    // Log the required details
    $logger->info('updateShipanyOrder ' . $response);
    $logger->info(print_r($responseData, true));

        return $responseData;
    }

    /**
     * Comment
     * 
     * @return
     */
    public function addCommentToOrder($orderId, $comment)
    {
        $order = $this->_order->loadByIncrementId($orderId);
        $status = $order->getStatus();
        $parentId = $order->getId();

        $history = $this->_orderHistoryFactory->create();
        $history->setParentId($parentId)
            ->setStatus(false)
            ->setComment($comment)
            ->setIsVisibleOnFront(true)
            ->setIsCustomerNotified(false);
        $this->_orderStatusHistoryRepositoryInterface->save($history);
    }
}
