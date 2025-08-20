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
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\ScopeInterface;
use Magento\Email\Model\Template\Config as EmailConfig;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;

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
    protected $encryptor;
    protected $transportBuilder;
    protected $state;

    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        UserFactory $userFactory,
        Curl $curl,
        UrlInterface $urlBuilder,
        LoggerInterface $logger,
        ModuleListInterface $moduleList,
        Inbox $adminNotificationInbox,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor,
        TransportBuilder $transportBuilder,
        State $state,
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
        $this->encryptor = $encryptor;
        $this->transportBuilder = $transportBuilder;
        $this->state = $state;
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

        // Load existing config data (one path for all)
        $configPath = 'mavenbird_license_status/mavenbird';
        $existingJson = $this->scopeConfig->getValue($configPath) ?? '{}';
        $existingData = json_decode($existingJson, true) ?? [];

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
                    "country"         => $this->scopeConfig->getValue('general/country/default') ?: 'US',
                    "customerEmail"   => $this->scopeConfig->getValue('trans_email/ident_general/email') ?: 'NULL',
                    "customerDomain"  => parse_url($baseUrl, PHP_URL_HOST) ?: 'NULL',
                    "adminFirstname"  => $adminUser->getFirstname() ?: 'NULL',
                    "adminLastname"   => $adminUser->getLastname() ?: 'NULL',
                    "adminUsername"   => $adminUser->getUsername() ?: 'NULL',
                    "adminEmail"      => $adminUser->getEmail() ?: 'NULL',
                    "storeLanguage"   => $this->scopeConfig->getValue('general/locale/code') ?: 'en_US',
                    "storeName"       => $storeName ,
                    "storeAddress"    => $storeAddress ,
                    "storePhone"      => $storePhone,
                ];

                $this->logger->debug("License API payload for $moduleName: " . json_encode($payload));

                $apiUrl = 'https://app.mavenbird.test/rest/V1/license/validate';

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
                    $messageHtml = $responseData['message'] ?? null;
                  

                  // Also load old modules data if exists to merge
                    $modules = $responseData['modules'] ?? null;
                    $configPathModules = 'mavenbird_license/mavenbird/modules';
                    $existingJsonModules = $this->scopeConfig->getValue($configPathModules) ?? '{}';
                    $existingDataModules = json_decode($existingJsonModules, true) ?? [];
                    $existingDataModules = array_merge($existingDataModules, $modules ?? []);
                    $this->configWriter->save($configPathModules, json_encode($existingDataModules));
                    
                    // Encrypt status and attempt
                    $encryptedStatus = $this->encryptor->encrypt($status);
                    $encryptedAttempts = $attemptCount !== null ? $this->encryptor->encrypt((string)$attemptCount) : null;

                    // Save in the unified config key
                    $key = strtolower($moduleName);
                    $existingData[$key] = [
                        'status' => $encryptedStatus,
                        'attempt_count' => $encryptedAttempts,
                        'message' => $messageHtml
                    ];
                } else {
                    $this->logger->warning("License API failed for $moduleName - HTTP $httpStatus, Response: $response");
                }
            } catch (\Exception $e) {
                $this->logger->error("License check failed for $moduleName: " . $e->getMessage());
            }
        }

        // Save merged config
        $this->configWriter->save($configPath, json_encode($existingData));

        // Clear old admin notifications
        $this->adminNotificationInbox->getCollection()
            ->addFieldToFilter('title', ['like' => 'Module license validation%'])
            ->walk('delete');

        // Generate new admin notifications from config
        $blockedModules = [];
        $warningModules = [];

        foreach ($existingData as $moduleKey => $data) {
            $statusEnc = $data['status'] ?? null;
            $attemptEnc = $data['attempt_count'] ?? null;
            $messageHtml = $data['message'] ?? '';

            try {
                $status = $this->encryptor->decrypt($statusEnc);
                $attemptCount = $attemptEnc ? (int)$this->encryptor->decrypt($attemptEnc) : null;
            } catch (\Exception $e) {
                $this->logger->error("Decryption failed for $moduleKey: " . $e->getMessage());
                continue;
            }

            $shortName = str_replace('mavenbird_', '', $moduleKey);
            $label = ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $shortName));
            $attemptText = $attemptCount ? " ({$attemptCount} attempts)" : '';
            $label .= $attemptText;

            if ($status === 'blocked') {
                $blockedModules[] = ['label' => $label, 'message' => $messageHtml];
            } elseif ($status === 'warning') {
                $warningModules[] = ['label' => $label, 'message' => $messageHtml];
            }
        }

        // Show notifications
        if (!empty($blockedModules)) {
            $html = '<ul>';
            foreach ($blockedModules as $mod) {
                $html .= '<li><strong>' . $mod['label'] . '</strong>: ' . $mod['message'] . '</li>';
            }
            $html .= '</ul>';

            $this->adminNotificationInbox->addCritical(
                __('Module license validation - Blocked Modules'),
                $html,
                '',
                true
            );
        }

        if (!empty($warningModules)) {
            $html = '<ul>';
            foreach ($warningModules as $mod) {
                $html .= '<li><strong>' . $mod['label'] . '</strong>: ' . $mod['message'] . '</li>';
            }
            $html .= '</ul>';

            $this->adminNotificationInbox->addMajor(
                __('Module license validation - Warning Modules'),
                $html,
                '',
                true
            );
        }
   
        if (!empty($blockedModules) || !empty($warningModules)) {
            try {
                $this->state->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($blockedModules, $warningModules) {

                    $maxAttempts = 0;
                    foreach (array_merge($blockedModules, $warningModules) as $mod) {
                        $attemptEnc = $existingData[strtolower('mavenbird_' . str_replace(' ', '', $mod['label']))]['attempt_count'] ?? null;
                        if ($attemptEnc) {
                            try {
                                $attempt = (int) $this->encryptor->decrypt($attemptEnc);
                                if ($attempt > $maxAttempts) {
                                    $maxAttempts = $attempt;
                                }
                            } catch (\Exception $e) {
                                $this->logger->error("Failed to decrypt attempt count for email: " . $e->getMessage());
                            }
                        }
                    }
                    $warningHtml = '';
                    foreach ($warningModules as $mod) {
                        $warningHtml .= '<li><strong>' . $mod['label'] . '</strong></li>';
                    }

                    $blockedHtml = '';
                    foreach ($blockedModules as $mod) {
                        $blockedHtml .= '<li><strong>' . $mod['label'] . '</strong></li>';
                    }

                    $adminEmail = $this->scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE);
                    $adminName = $this->scopeConfig->getValue('trans_email/ident_general/name', ScopeInterface::SCOPE_STORE);

                    $transport = $this->transportBuilder
                        ->setTemplateIdentifier('mavenbird_license_alert') 
                        ->setTemplateOptions([
                            'area' => Area::AREA_ADMINHTML,
                            'store' => 0
                        ])
                        ->setTemplateVars([
                            'warning_modules' => $warningHtml,
                            'blocked_modules' => $blockedHtml,
                            'attemptCount' => $maxAttempts,
                            'adminName' => $adminName,
                        ])
                        ->setFromByScope('general') // Uses General Email Sender config
                        ->addTo($adminEmail, $adminName)
                        ->getTransport();

                    $transport->sendMessage();
                });
            } catch (\Exception $e) {
                $this->logger->error('Failed to send license email: ' . $e->getMessage());
            }
        }

        $this->logger->info('LicenseValidate cron job completed');
    }
}
