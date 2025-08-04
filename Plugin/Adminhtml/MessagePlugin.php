<?php
namespace Mavenbird\Core\Plugin\Adminhtml;

use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Backend\App\Action;
use Magento\Framework\Encryption\EncryptorInterface;

class MessagePlugin
{
    protected $scopeConfig;
    protected $moduleList;
    protected $messageManager;
    protected $encryptor;
    protected $alreadyProcessed = false;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ModuleListInterface $moduleList,
        ManagerInterface $messageManager,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->moduleList = $moduleList;
        $this->messageManager = $messageManager;
        $this->encryptor = $encryptor;
    }

    public function beforeDispatch(Action $subject, RequestInterface $request)
    {
        if ($this->alreadyProcessed) {
            return;
        }
        $this->alreadyProcessed = true;

        $configPath = 'mavenbird_license_status/mavenbird';
        $statusJson = $this->scopeConfig->getValue($configPath);
        $statusData = is_string($statusJson) ? json_decode($statusJson, true) : [];

        if (empty($statusData)) {
            return;
        }

        foreach ($statusData as $moduleKey => $data) {
            $statusEnc = $data['status'] ?? null;
            $attemptEnc = $data['attempt_count'] ?? null;
            $message = $data['message'] ?? '';

            try {
                $status = $this->encryptor->decrypt($statusEnc);
                $attemptCount = $attemptEnc ? (int)$this->encryptor->decrypt($attemptEnc) : null;
            } catch (\Exception $e) {
                continue;
            }

            $shortName = str_replace('mavenbird_', '', $moduleKey);
            $label = ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $shortName));
            $attemptText = $attemptCount ? " ({$attemptCount} attempts)" : '';
            $fullMessage = "Mavenbird Notice - $label: $message$attemptText";

            if ($status === 'blocked') {
                $this->messageManager->addErrorMessage($fullMessage);
            } elseif ($status === 'warning') {
                $this->messageManager->addWarningMessage($fullMessage);
            }
        }
    }
}
