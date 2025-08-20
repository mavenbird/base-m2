<?php
namespace Mavenbird\Core\Block\Adminhtml\System\Config;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Encryption\EncryptorInterface;

class ModuleVersion extends Field
{
    protected $moduleList;
    protected $scopeConfig;
    protected $encryptor;
  const STYLE_BLOCKED = '
        display: inline-block;
        color: #fff;
        background-color: #d32f2f; /* deep red */
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 4px;
        box-shadow: 0 2px 6px rgba(211, 47, 47, 0.5);
        font-family: Arial, sans-serif;
        font-size: 13px;
        text-transform: uppercase;
    ';

    const STYLE_WARNING = '
        display: inline-block;
        color: #444;
        background-color: #fff8e1; /* light yellow */
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 4px;
        border: 1px solid #fbc02d; /* yellow border */
        font-family: Arial, sans-serif;
        font-size: 13px;
        text-transform: uppercase;
    ';

    const STYLE_VALID = '
        display: inline-block;
        color: #2e7d32; /* dark green */
        background-color: #e8f5e9; /* light green */
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 4px;
        border: 1px solid #2e7d32;
        font-family: Arial, sans-serif;
        font-size: 13px;
        text-transform: uppercase;
    ';

    const STYLE_UNKNOWN = '
        display: inline-block;
        color: #757575;
        background-color: #f5f5f5;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 4px;
        font-family: Arial, sans-serif;
        font-size: 13px;
        text-transform: uppercase;
    ';

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        ModuleListInterface $moduleList,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        array $data = []
    ) {
        $this->moduleList = $moduleList;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $html = '<div class="mavenbird-module-versions" style="margin-top:50px;">';
        $html .= '<h2 style="margin-bottom:30px; color:#000; font-weight:bold;">' . __('Mavenbird Modules Version Information') . '</h2>';

        // Top Butto
        $html .= '<div style="margin-bottom:25px; margin-top:30px;">
                    <a href="https://www.mavenbird.com/customer/account/" target="_blank" target="_blank" type="button" style="background:#1976d2;color:#fff;border:none; padding:8px 16px;border-radius:4px; font-weight:800;cursor:pointer;">
                        ' . __('View Your Products Needs to be Updated') . '
                    </a>
                  </div>';

        // Table wrapper
        $html .= '<table style="width:100%;border-collapse:collapse; background:#fff;border:1px solid #000000ff; border-radius:6px;overflow:hidden;">';

        // Table head
        $html .= '<thead>
                    <tr style="background:#000000ff;color:#fff;">
                        <th style="text-align:left;padding:10px 12px;font-weight:800;">' . __('Product') . '</th>
                        <th style="text-align:left;padding:10px 12px;font-weight:800;">' . __('Version') . '</th>
                        <th style="text-align:left;padding:10px 12px;font-weight:800;">' . __('License Status') . '</th>
                        <th style="text-align:left;padding:10px 12px;font-weight:800;">' . __('Actions') . '</th>
                    </tr>
                  </thead>
                  <tbody>';

        // Load license JSON
        $licenseJson = $this->scopeConfig->getValue('mavenbird_license_status/mavenbird');
        $licenseData = is_string($licenseJson) ? json_decode($licenseJson, true) : [];

        foreach ($this->moduleList->getAll() as $module) {
            if (strpos($module['name'], 'Mavenbird_') !== 0) {
                continue;
            }

            $moduleName = str_replace('Mavenbird_', '', $module['name']);
            $displayName = ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $moduleName));
            $displayName = $this->escapeHtml($displayName);
            $version = $module['setup_version'] ?? 'N/A';

            $moduleKey = strtolower($module['name']);
            $statusHtml = $this->getLicenseStatusHtml($moduleKey, $licenseData);
            $actionsHtml = $this->getActionsHtml($moduleKey, $licenseData);

            $html .= '<tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px 12px;">' . $displayName . '</td>
                        <td style="padding:10px 12px;">' . $this->escapeHtml($version) . '</td>
                        <td style="padding:10px 12px;">' . $statusHtml . '</td>
                        <td style="padding:10px 12px;">' . $actionsHtml . '</td>
                      </tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    private function getLicenseStatusHtml(string $moduleKey, array $licenseData): string
    {
        if (!isset($licenseData[$moduleKey])) {
                    return '<span style="' . self::STYLE_UNKNOWN . '">' . __('Unknown') . '</span>';
                }

            $data = $licenseData[$moduleKey];
            $statusEnc = $data['status'] ?? null;
            $attemptEnc = $data['attempt_count'] ?? null;

            try {
                $status = $statusEnc ? $this->encryptor->decrypt($statusEnc) : 'unknown';
                $attempts = $attemptEnc ? (int)$this->encryptor->decrypt($attemptEnc) : null;
            } catch (\Exception $e) {
                $status = 'unknown';
                $attempts = null;
            }

            $attemptText = $attempts !== null ? ' (' . $attempts . ' attempts)' : '';

            switch ($status) {
                case 'blocked':
                    $label = __('Blocked') . $attemptText;
                    $style = self::STYLE_BLOCKED;
                    break;
                case 'warning':
                    $label = __('Warning') . $attemptText;
                    $style = self::STYLE_WARNING;
                    break;
                case 'valid':
                    $label = __('Valid');
                    $style = self::STYLE_VALID;
                    break;
                default:
                    $label = __('Unknown');
                    $style = self::STYLE_UNKNOWN;
            }

        return '<span style="' . $style . '">' . $this->escapeHtml($label) . '</span>';
    }

    private function getActionsHtml(string $moduleKey, array $licenseData): string
    {
        if (!isset($licenseData[$moduleKey])) {
                    return '<span style="' . self::STYLE_UNKNOWN . '">' . __('Unknown') . '</span>';
                }

        $data = $licenseData[$moduleKey];
        $statusEnc = $data['status'] ?? null;
        $attemptEnc = $data['attempt_count'] ?? null;

        try {
            $status = $statusEnc ? $this->encryptor->decrypt($statusEnc) : 'unknown';
            $attempts = $attemptEnc ? (int)$this->encryptor->decrypt($attemptEnc) : null;
        } catch (\Exception $e) {
            $status = 'unknown';
            $attempts = null;
        }

        $attemptText = $attempts !== null ? ' (' . $attempts . ' attempts)' : '';

        switch ($status) {
        case 'blocked':
            return '<a href="https://www.mavenbird.com/customer/account/" target="_blank" class="action-default scalable" style="text-decoration:none;padding:4px 12px;background:#d32f2f;color:#fff;border-radius:4px;font-weight:600;">' . __('Upgrade Your Plan') . '</a>';
        case 'warning':
            return '<a href="https://www.mavenbird.com/customer/account/" target="_blank" style="padding:4px 12px;background:#43a047; color:#fff; border:none;border-radius:4px;font-weight:600;cursor:pointer;">' . __('Re-Verify') . '</a>';
        case 'valid':
            return '<span style="color:#999;">' . __('None') . '</span>';
        default:
            return '<span style="color:#999;">' . __('No Action') . '</span>';
    
        }
    }
}
