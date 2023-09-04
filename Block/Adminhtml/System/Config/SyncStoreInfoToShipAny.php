<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Block\Adminhtml\System\Config;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Integration\Model\Oauth\Token\Provider;

class SyncStoreInfoToShipAny extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $_integrationToken = '';
    protected $_storeURL = '';
    protected $_template = 'Eastz_AutoShipment::system/config/sync.phtml';

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_storeManager = $storeManager;
    }

    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getAjaxSyncUrl()
    {
        $objectManager = ObjectManager::getInstance();
        $api_endpoint = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('autoshipment/shipany/api_endpoint');
        return $this->getUrl($api_endpoint);
    }

    public function getApiToken()
    {
        $objectManager = ObjectManager::getInstance();
        $api_token = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('autoshipment/shipany/token');
        return $api_token;
    }

    public function getStore()
    {
        $storeManagerDataList = $this->_storeManager->getStores();
        $options = array();

        foreach ($storeManagerDataList as $key => $value) {
            $token_result = $this->processIntegrationToken();
            $options[] = array(
                'shop_name'         => $value['name'],
                'shop_id'           => $value->getStoreId(),
                'shop_code'         => $value['code'],
                'host_url'          => $value->getBaseUrl(UrlInterface::URL_TYPE_WEB, true),
                'integrate_token'   => $token_result['token'],
                'integrate_secret'  => $token_result['secret']
            );
        }

        $shop_list = array();
        $shop_list['shop_list'] = $options;
        return $shop_list;
    }

    public function processIntegrationToken()
    {
        $objectManager = ObjectManager::getInstance();
        $name = 'ShipAny';
        // $email = '';
        $integrationExists = $objectManager->get('Magento\Integration\Model\IntegrationFactory')->create()->load($name, 'name')->getData();
        if (empty($integrationExists)) {
            $integrationData = array(
                'name'          => $name,
                // 'email'      => $email,
                'status'        => '1',
                // 'endpoint'   => $endpoint,
                // 'setup_type' => '0'
            );

            try {
                // Code to create Integration
                $integrationFactory = $objectManager->get('Magento\Integration\Model\IntegrationFactory')->create();
                $integration = $integrationFactory->setData($integrationData);
                $integration->save();
                $integrationId = $integration->getId();

                $consumerName = 'Integration' . $integrationId;

                // Code to create consumer
                $oauthService = $objectManager->get('Magento\Integration\Model\OauthService');
                $consumer = $oauthService->createConsumer(['name' => $consumerName]);
                $consumerId = $consumer->getId();
                $integration->setConsumerId($consumer->getId());
                $integration->save();

                // Code to grant permission
                $authrizeService = $objectManager->get('Magento\Integration\Model\AuthorizationService');
                $authrizeService->grantAllPermissions($integrationId);

                // Code to Activate and Authorize
                $token = $objectManager->get('Magento\Integration\Model\Oauth\Token');
                $uri = $token->createVerifierToken($consumerId);
                $token->setType('access');
                $token->save();

                $token = $objectManager->get('Magento\Integration\Model\Oauth\Token\Provider');
                $result = $token->getIntegrationTokenByConsumerId($consumerId);

                return ['token' => $result->getToken(), 'secret' => $result->getSecret()];

            } catch (Exception $e) {
                echo 'Error : ' . $e->getMessage();
            }
        } else {
            $token = $objectManager->get('Magento\Integration\Model\Oauth\Token\Provider');
            $result = $token->getIntegrationTokenByConsumerId($integrationExists['consumer_id']);

            return ['token' => $result->getToken(), 'secret' => $result->getSecret()];
        }
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'webhook_activat_btn',
                'label' => 'Sync Store Information'
            ]
        );

        return $button->toHtml();
    }
}
