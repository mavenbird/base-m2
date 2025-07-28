<?php
namespace Mavenbird\Core\Plugin\Adminhtml;

use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Backend\App\Action;

class MessagePlugin
{
    protected $scopeConfig;
    protected $moduleList;
    protected $messageManager;
    protected $alreadyProcessed = false;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ModuleListInterface $moduleList,
        ManagerInterface $messageManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->moduleList = $moduleList;
        $this->messageManager = $messageManager;
    }

    public function beforeDispatch(Action $subject, RequestInterface $request)
    {
        if ($this->alreadyProcessed) {
            return;
        }
        $this->alreadyProcessed = true;

        $blockedModules = [];
        $warningModules = [];

        foreach ($this->moduleList->getAll() as $module) {
            if (strpos($module['name'], 'Mavenbird_') !== 0) {
                continue;
            }

            $configPath = 'mavenbird_license_status/' . strtolower($module['name']);
            $statusJson = $this->scopeConfig->getValue($configPath);
            $statusData = is_string($statusJson) ? json_decode($statusJson, true) : [];            

            $status = $statusData['status'] ?? 'unknown';
            $attemptCount = isset($statusData['attempt_count']) ? (int)$statusData['attempt_count'] : null;
            $attemptText = $attemptCount ? " ({$attemptCount} attempts)" : '';

            $moduleName = str_replace('Mavenbird_', '', $module['name']);
            $label = ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $moduleName)) . $attemptText;

            if ($status === 'blocked') {
                $blockedModules[] = $label;
            } elseif ($status === 'warning') {
                $warningModules[] = $label;
            }
        }

        if (!empty($blockedModules)) {
            $last = array_pop($blockedModules);
            $formattedBlocked = empty($blockedModules)
                ? $last
                : implode(', ', $blockedModules) . ' and ' . $last;
        
            $this->messageManager->addErrorMessage(
                __('Mavenbird Notice : The following Mavenbird modules are blocked: %1. Please contact support@mavenbird.com.', $formattedBlocked)
            );
        }
        
        if (!empty($warningModules)) {
            $last = array_pop($warningModules);
            $formattedWarning = empty($warningModules)
                ? $last
                : implode(', ', $warningModules) . ' and ' . $last;
        
            $this->messageManager->addWarningMessage(
                __('Mavenbird Notice : The following Mavenbird modules have license warnings: %1. Please verify their license or contact support@mavenbird.com.', $formattedWarning)
            );
        }
        
    }
}
