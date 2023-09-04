<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Helper;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
use Magento\Tax\Model\Config;

/**
 * Catalog data helper
 *
 * @api
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class ShipAny extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

	/**
	 * Api endpoinst
	 */
	protected $apiEndpoint;

	/**
	 * Api Token
	 */
	protected $apiToken;

	/**
	 * AddressType
	 */
	const ADDRESS_TYPE_1		= 1;

	/**
	 * CurrencyUnit
	 */
	const CURRENCY_UNIT_HKD 	= 'HKD';
	const CURRENCY_UNIT_USD		= 'USD';

	/**
	 * LangType
	 */
	const LANG_TYPE_CHT			= 'CHT';
	const LANG_TYPE_CHS			= 'CHS';
	const LANG_TYPE_ENG			= 'ENG';

	/**
	 * OnlineStoreType
	 */
	const ONLINE_STORE_TYPE		= 'Magento';

	/**
	 * OrderStatusType
	 */
	const ORDER_STATUS_DRAFTED	= 'Order Drafted';

    /**
     * Data constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->_objectManager = $objectManager;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
        $this->initConfig();

        parent::__construct($context);
    }

	/**
	 * Init config for shipany
	 * @return
	 */
	public function initConfig()
	{
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $apiEndpoint = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('autoshipment/shipany/api_endpoint');
        $apiToken = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('autoshipment/shipany/token');

        $this->apiEndpoint = $apiEndpoint;
        $this->apiToken = $apiToken;
	}

    /**
     * @param $apiEndpoint
     */
    public function setApiEndpoint($apiEndpoint)
    {
        $this->apiEndpoint = $apiEndpoint;
    }

    /**
     * @param $token
     */
    public function setToken($token)
    {
        $this->apiToken = $token;
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
     * httpPost
     * @param $url
     * @param array $params
     * @return mixed|null
     */
    public function httpPost($url, $paramsArr = [])
    {
        $headerArr = [
            'api-tk: ' . $this->apiToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiEndpoint . $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsArr);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArr);

        $response = curl_exec($ch);
        if ($response === false) {
            $error_msg = 'Unable to post request, underlying exception of ' . curl_error($ch);
            curl_close($ch);
            throw new \Exception($error_msg);
        }
        curl_close($ch);

//        debug_time('httpPost ' . $this->apiEndpoint . $url . ' ' . $this->apiToken);
//        debug_time($paramsArr);
//        debug_time($response);

    // Initialize the logger
    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/debug.log');
    $logger = new \Zend\Log\Logger();
    $logger->addWriter($writer);
    
    // Log the required details
    $logger->info('httpPost ' . $this->apiEndpoint . $url . ' ' . $this->apiToken);
    $logger->info(print_r($paramsArr, true));
    $logger->info($response);

        $responseData = json_decode($response, true);
        return $responseData;
    }

    /**
     * httpGet
     * @param $url
     * @return mixed|string
     * @throws \Exception
     */
    public function httpGet($url, $paramsArr = [])
    {
    	$paramsStr = is_array($paramsArr) ? urlencode(http_build_query($paramsArr)) : urlencode($paramsArr);
        if ($paramsStr) {
            $url .= '?' . urldecode($paramsStr);
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL 			=> $this->apiEndpoint . $url,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_RETURNTRANSFER 	=> true,
            CURLOPT_HTTPHEADER 		=> array(
                'api-tk: ' . $this->apiToken,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($ch);
        if ($response === false) {
            $error_msg = 'Unable to get request, underlying exception of ' . curl_error($ch);
            curl_close($ch);
            throw new \Exception($error_msg);
        }
        curl_close($ch);

        $responseData = json_decode($response, true);
        return $responseData;
    }

	/**
	 * Get merchant information
	 * 
	 * @return
	 */
	public function getMerchantInformation()
	{
		$url = '/merchants/self/';

		return $this->httpGet($url);
	}

	/**
	 * Get shipping service locations
	 * 
	 * @return
	 */
	public function getShippingLocations()
	{
		$url = '/courier-service-location/published-locations/';

        return $this->httpGet($url);
	}

	/**
	 * Get Pickup / Delivery Time Options
	 * 
	 * @return
	 */
	public function getPickupTimeOptions($type = 'sf-plus')
	{
        switch ($type) {
            case 'sf-plus':
                // Get Zeek2Door's pickup and delivery time options
                $url = '/couriers-connector/sf-plus/pickup-delivery-time-options/';
                break;

            case 'ups':
                // Get UPS pickup time options
                $url = '/couriers-connector/ups/pickup-delivery-time-options/';
                break;

            case 'alfred-locker':
                // Get Alfred Locker's pickup time options
                $url = '/couriers-connector/alfred-locker/pickup-delivery-time-options/';
                break;

            default:
                $url = '/couriers-connector/sf-plus/pickup-delivery-time-options/';
                break;
        }

        return $this->httpGet($url);
	}

	/**
	 * Get list of available couriers
	 * 
	 * @return
	 */
	public function getCouriers()
	{
		$url = '/couriers/';

		return $this->httpGet($url);
	}

	/**
	 * Query shipping rate
	 * 
	 * @return
	 */
	public function getShippingRate($orderId)
	{
		$url = '/couriers-connector/query-rate/';

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create(\Magento\Sales\Model\Order::class)->loadByIncrementId($orderId);
        $orderData = $this->prepareOrderRate($order);

        return $this->httpPost($url, $orderData);
	}

	/**
	 * Create shipping order
	 * 
	 * @return
	 */
//	public function createShipOrder($orderId)
//	{
//		$url = '/orders/';
//
//		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
//		$order = $objectManager->create(\Magento\Sales\Model\Order::class)->loadByIncrementId($orderId);
//		$orderData = $this->prepareOrderDetail($order);
//        debug_time('createShipOrder ' . $orderId);
//        debug_time($orderData);
//
//		return $this->httpPost($url, $orderData);
//	}

public function createShipOrder($orderId)
{
    $url = '/orders/';

    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $order = $objectManager->create(\Magento\Sales\Model\Order::class)->loadByIncrementId($orderId);
    $orderData = $this->prepareOrderDetail($order);

    // Initialize the logger
    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/debug.log');
    $logger = new \Zend\Log\Logger();
    $logger->addWriter($writer);
    
    // Log the required details
    $logger->info('createShipOrder ' . $orderId);
    $logger->info(print_r($orderData, true));

    return $this->httpPost($url, $orderData);
}

	/**
	 * Get shipping order
	 * 
	 * @return
	 */
	public function getShipOrder($orderId)
	{
		$url = '/orders/' . $orderId . '/';

		return $this->httpGet($url);
	}

	/**
	 * List shipping orders
	 * 
	 * @return
	 */
	public function listShipOrders($params = [])
	{
		$url = '/orders/';

		return $this->httpGet($url);
	}

	/**
	 * Send order pickup request to courier
	 * 
	 * @return
	 */
	public function sendPickupToCourier($orderId)
	{
		$url = '/orders/send-pickup-request?order-id=' . $orderId;

		return $this->httpPost($url);
	}

	/**
	 * Cancel shipping order
	 * 
	 * @return
	 */
	public function cancelShipOrder($orderId)
	{
		$url = '/orders/cancel-order?order-id=' . $orderId;

		return $this->httpPost($url);
	}

    /**
     * Prepare order data for query rate
     * @param $order
     * @return
     */
    public function prepareOrderRate($order)
    {
        // Prepare for the sndr information
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeInformation = $objectManager->create('Magento\Store\Model\Information');
        $store = $objectManager->create('Magento\Store\Model\Store');
        $storeInfo = $storeInformation->getStoreInformationObject($store);

        $order_json = array(
            'wt' => array(
                'val' => $order->getWeight() > 0 ? $order->getWeight() : 1,
                // 'unt' => ($this->getWeightUnit() == 'kgs') ? 'KG' : $this->getWeightUnit()
            ),
            'dim' => array(
                'hgt' => 1,
                'len' => 1,
                'wid' => 1
            ),
            'mch_ttl_val' => array(
                'ccy' => $order->getOrderCurrencyCode(),
                'val' => intval($order->getGrandTotal() - $order->getShippingAmount())
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
            'stg' => 'Normal',
        );

        return $order_json;
    }

	/**
	 * Prepare order detail
	 * @param $order
	 * @return
	 */
	public function prepareOrderDetail($order)
	{
        // Prepare for the sndr information
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeInformation = $objectManager->create('Magento\Store\Model\Information');
        $store = $objectManager->create('Magento\Store\Model\Store');
        $storeInfo = $storeInformation->getStoreInformationObject($store);

        $child_items = array();

        foreach ($order->getAllItems() as $item) {
            $obj = array(
                'name'          => $item->getName(),
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
                'stg'           => 'Normal',
                'sku'           => $item->getSku(),
            );
            $child_items[] = $obj;
        }

        $order_json = array(
            'cour_uid'          => '0a2e1f15-3a79-4756-8495-300ee443f06e',
            'mch_uid'           => 'd3667c50-0188-4889-9282-0fc3df80833b',
            'ext_order_ref'     => $order->getIncrementId(),
            'ext_order_id'      => $order->getIncrementId(),
            'wt' => array(
                'val'           => $order->getWeight() > 0 ? $order->getWeight() : 1,
                // 'unt'        => ($this->getWeightUnit() == 'kgs') ? 'KG' : $this->getWeightUnit()
            ),
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
            // 'packages'          => array(
            //     array(
            //         // 'desc'   => '',
            //         'wt'        => array(
            //             'val'   => $order->getWeight(),
            //             'unt'   => ($this->getWeightUnit() == 'kgs') ? 'KG' : $this->getWeightUnit()
            //         ),
            //         'dim'       => array(
            //             'hgt'   => 1,
            //             'len'   => 1,
            //             'wid'   => 1
            //         ),
            //         'child_items' => $child_items
            //     ),
            // ),
            'mch_ttl_val'       => array(
                'ccy'           => $order->getOrderCurrencyCode(),
                'val'           => intval($order->getGrandTotal() - $order->getShippingAmount())
            ),
            'cour_ttl_cost'     => array(
                'ccy'           => $order->getOrderCurrencyCode(),
                'val'           => intval($order->getShippingAmount())
            ),
            'cour_svc_pl'       => 'SF Express',
            'webhook'           => ['https://shop.baisilai.com/autoshipment/shipany/notify'],
            'quots_uid'         => $order->getShippingDescription(),
        );

        return json_encode($order_json);
	}

    /**
     * Process order status result
     * @return
     */
    public function processShipOrderResult($result)
    {
        if (!is_array($result)) {
            $result = json_decode($result, true);
        }

        $trackList = [];

        if (isset($result['result']) && $result['result']['code'] == 201) {
            if (is_array($result['data']) && is_array($result['data']['objects'])) {
                foreach ($result['data']['objects'] as $item) {
                    // $ext_order_id = $item['ext_order_id'];
                    $ext_order_ref = $item['ext_order_ref'];
                    $track_no = $item['trk_no'];
                    $cour_name = $item['cour_name'];

                    if (strpos($ext_order_ref, '_') > -1) {
                        $ext_order_ref = substr($ext_order_ref, 0, strpos($ext_order_ref, '_'));
                    }

                    $status = '';
                    if (isset($item['states']) && is_array($item['states'])) {
                        foreach ($item['states'] as $state) {
                            if (isset($state['stat']) && $state['stat'] == 'Order Delivered') {
                                $status = 'complete';
                                break;
                            }
                            if (isset($state['stat']) && $state['stat'] == 'Collected By Courier') {
                                $status = 'shipped';
                            }
                            if (isset($state['stat']) && $state['stat'] == 'In Transit') {
                                $status = 'shipped';
                            }
                        }
                    }

                    $trackList[] = [
                        'increment_id'  => $ext_order_ref,
                        'track_no'      => $track_no,
                        'cour_name'     => $cour_name,
                        'status'        => $status,
                    ];
                }
            }
        }
        if (isset($result['results']) && is_array($result['results'])) {
            foreach ($result['results'] as $item) {
                // $ext_order_id = $item['ext_order_id'];
                $ext_order_ref = $item['ext_order_ref'];
                $track_no = $item['trk_no'];
                $cour_name = $item['cour_name'];

                if (strpos($ext_order_ref, '_') > -1) {
                    $ext_order_ref = substr($ext_order_ref, 0, strpos($ext_order_ref, '_'));
                }

                $status = '';
                if (isset($item['states']) && is_array($item['states'])) {
                    foreach ($item['states'] as $state) {
                        if (isset($state['stat']) && $state['stat'] == 'Order Delivered') {
                            $status = 'complete';
                            break;
                        }
                        if (isset($state['stat']) && $state['stat'] == 'Collected By Courier') {
                            $status = 'shipped';
                        }
                        if (isset($state['stat']) && $state['stat'] == 'In Transit') {
                            $status = 'shipped';
                        }
                    }
                }

                $trackList[] = [
                    'increment_id'  => $ext_order_ref,
                    'track_no'      => $track_no,
                    'cour_name'     => $cour_name,
                    'status'        => $status,
                ];
            }
        }

        return $trackList;
    }
}
