<?php
namespace Mavenbird\Core\Block\Adminhtml\System\Config;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ModuleVersion extends Field
{
    protected $moduleList;
    protected $scopeConfig;

    const STYLE_BLOCKED = 'color: #fff; background: #d32f2f; font-weight: bold; padding: 2px 8px; border-radius: 3px;';
    const STYLE_WARNING = 'color: #fff; background: #fbc02d; font-weight: bold; padding: 2px 8px; border-radius: 3px;';
    const STYLE_VALID = 'color: #388e3c; font-weight: bold;';
    const STYLE_UNKNOWN = 'color: #757575;';

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        ModuleListInterface $moduleList,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->moduleList = $moduleList;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $html = '<div class="mavenbird-module-versions">';
        $html .= '<table class="admin__table-secondary">
            <thead>
                <tr>
                    <th>' . __('Product') . '</th>
                    <th>' . __('Version') . '</th>
                    <th>' . __('License Status') . '</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($this->moduleList->getAll() as $module) {
            if (strpos($module['name'], 'Mavenbird_') !== 0) {
                continue;
            }

            $moduleName = str_replace('Mavenbird_', '', $module['name']);
            $displayName = ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $moduleName));
            $displayName = $this->escapeHtml($displayName);

            $version = isset($module['setup_version']) ? $this->escapeHtml($module['setup_version']) : 'N/A';

            $licenseInfo = $this->getLicenseStatusHtml($module['name']);

            $html .= '<tr>
                        <td>' . $displayName . '</td>
                        <td>' . $version . '</td>
                        <td>' . $licenseInfo . '</td>
                      </tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * Get formatted license status HTML
     */
    private function getLicenseStatusHtml(string $moduleName): string
    {
        $configPath = 'mavenbird_license_status/' . strtolower($moduleName);
        $statusJson = $this->scopeConfig->getValue($configPath);
        $statusData = is_string($statusJson) ? json_decode($statusJson, true) : [];

        $status = $statusData['status'] ?? 'unknown';
        $attempts = $statusData['attempt_count'] ?? null;

        $attemptText = $attempts !== null ? ' (' . (int)$attempts . ' attempts)' : '';

        switch ($status) {
            case 'blocked':
                $label = 'Blocked' . $attemptText;
                $style = self::STYLE_BLOCKED;
                break;
            case 'warning':
                $label = 'Warning' . $attemptText;
                $style = self::STYLE_WARNING;
                break;
            case 'valid':
                $label = 'Valid';
                $style = self::STYLE_VALID;
                break;
            default:
                $label = 'Unknown';
                $style = self::STYLE_UNKNOWN;
        }

        return '<span style="' . $style . '">' . $this->escapeHtml($label) . '</span>';
    }
}
