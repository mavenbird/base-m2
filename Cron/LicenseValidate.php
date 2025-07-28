<?php
namespace Mavenbird\Core\Cron;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\User\Model\UserFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\AdminNotification\Model\Inbox;
use Magento\Framework\App\Config\Storage\WriterInterface;

class LicenseValidate
{
    protected $storeManager;
    protected $scopeConfig;
    protected $userFactory;
    protected $curl;
    protected $urlBuilder;
    protected $logger;
    protected $moduleList;
    protected $adminNotificationInbox;
    protected $configWriter;

    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        UserFactory $userFactory,
        Curl $curl,
        UrlInterface $urlBuilder,
        LoggerInterface $logger,
        ModuleListInterface $moduleList,
        Inbox $adminNotificationInbox,
        WriterInterface $configWriter
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->userFactory = $userFactory;
        $this->curl = $curl;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
        $this->moduleList = $moduleList;
        $this->adminNotificationInbox = $adminNotificationInbox;
        $this->configWriter = $configWriter;
    }

    public function execute()
    {
        $this->logger->info('LicenseValidate cron job started');

        $mavenbirdModules = [];
        foreach ($this->moduleList->getAll() as $module) {
            if (strpos($module['name'], 'Mavenbird_') === 0) {
                $mavenbirdModules[] = $module['name'];
            }
        }

        if (empty($mavenbirdModules)) {
            $this->logger->info('No Mavenbird modules found for license validation.');
            return;
        }

        $blockedModules = [];
        $warningModules = [];

        foreach ($mavenbirdModules as $moduleName) {
            try {
                $store = $this->storeManager->getStore();
                $baseUrl = $store->getBaseUrl();
                $adminUser = $this->userFactory->create()->getCollection()->setPageSize(1)->getFirstItem();
                $storeName = $this->scopeConfig->getValue('general/store_information/name') ?: 'Not Set';
                $storeAddress = $this->scopeConfig->getValue('general/store_information/street_line1') ?: 'Not Set';
                $storePhone = $this->scopeConfig->getValue('general/store_information/phone') ?: 'Not Set';

                $payload = [
                    "moduleName"      => $moduleName,
                    "ipAddress"       => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
                    "country"         => $this->scopeConfig->getValue('general/country/default'),
                    "customerEmail"   => $this->scopeConfig->getValue('trans_email/ident_general/email'),
                    "customerDomain"  => parse_url($baseUrl, PHP_URL_HOST),
                    "adminFirstname"  => $adminUser->getFirstname(),
                    "adminLastname"   => $adminUser->getLastname(),
                    "adminUsername"   => $adminUser->getUsername(),
                    "adminEmail"      => $adminUser->getEmail(),
                    "storeLanguage"   => $this->scopeConfig->getValue('general/locale/code'),
                    "storeName"       => $storeName,
                    "storeAddress"    => $storeAddress,
                    "storePhone"      => $storePhone,
                ];

                $this->logger->debug("License API payload for $moduleName: " . json_encode($payload));

                $apiUrl = 'https://mbdev.magemoto.com/rest/V1/license/validate';

                $this->curl->addHeader("Content-Type", "application/json");
                $this->curl->post($apiUrl, json_encode($payload));

                $response = $this->curl->getBody();
                $httpStatus = $this->curl->getStatus();

                $this->logger->debug("License API response for $moduleName: HTTP $httpStatus, Body: $response");

                if ($httpStatus >= 200 && $httpStatus < 300) {
                    $responseData = json_decode($response, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->logger->error("JSON decode error for $moduleName response: " . json_last_error_msg());
                        continue;
                    }

                    $status = $responseData['status'] ?? 'unknown';
                    $attemptCount = isset($responseData['attempt_count']) ? (int)$responseData['attempt_count'] : null;

                    if (!in_array($status, ['valid', 'warning', 'blocked'])) {
                        $status = 'unknown';
                    }

                    // Save status to config
                    $configPath = 'mavenbird_license_status/' . strtolower($moduleName);
                    $statusData = [
                        'status' => $status,
                        'attempt_count' => $attemptCount
                    ];
                    $this->configWriter->save($configPath, json_encode($statusData));
                } else {
                    $this->logger->warning("License API failed for $moduleName - HTTP $httpStatus, Response: $response");
                }
            } catch (\Exception $e) {
                $this->logger->error("License check failed for $moduleName: " . $e->getMessage());
            }
        }

        // Delete old admin messages related to license validation
        $this->adminNotificationInbox->getCollection()
            ->addFieldToFilter('title', ['like' => 'Module license validation%'])
            ->walk('delete');

        // Rebuild grouped messages from latest config
        foreach ($mavenbirdModules as $moduleName) {
            $configPath = 'mavenbird_license_status/' . strtolower($moduleName);
            $statusJson = $this->scopeConfig->getValue($configPath);
            $statusData = is_string($statusJson) ? json_decode($statusJson, true) : [];
            $status = $statusData['status'] ?? 'unknown';
            $attemptCount = isset($statusData['attempt_count']) ? (int)$statusData['attempt_count'] : null;
            $attemptText = $attemptCount ? " ({$attemptCount} attempts)" : '';

            $shortName = str_replace('Mavenbird_', '', $moduleName);
            $label = ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $shortName)) . $attemptText;

            if ($status === 'blocked') {
                $blockedModules[] = $label;
            } elseif ($status === 'warning') {
                $warningModules[] = $label;
            }
        }

        if (!empty($blockedModules)) {
            $message = 'Mavenbird Notice : The following Mavenbird modules are <strong>blocked</strong>: ' . implode(', ', $blockedModules) . '. Please contact <a href="mailto:support@mavenbird.com">support@mavenbird.com</a>.';
            $this->adminNotificationInbox->addCritical(
                __('Module license validation - Blocked Modules'),
                $message,
                '',
                true
            );
        }

        if (!empty($warningModules)) {
            $message = 'Mavenbird Notice : The following Mavenbird modules have <strong>license warnings</strong>: ' . implode(', ', $warningModules) . '. Please verify or contact <a href="mailto:support@mavenbird.com">support@mavenbird.com</a>.';
            $this->adminNotificationInbox->addMajor(
                __('Module license validation - Warning Modules'),
                $message,
                '',
                true
            );
        }

        $this->logger->info('LicenseValidate cron job completed');
    }
}
