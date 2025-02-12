<?php
namespace Mavenbird\Core\Block\Adminhtml\System\Config;

use Magento\Framework\Module\ModuleListInterface;

class ModuleVersion extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $moduleList;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        ModuleListInterface $moduleList,
        array $data = []
    ) {
        $this->moduleList = $moduleList;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $html = '<div class="mavenbird-module-versions">';
        $html .= '<table class="admin__table-secondary">
            <thead>
                <tr>
                    <th>Products</th>
                    <th>Version</th>
                </tr>
            </thead>
            <tbody>';
            
        foreach ($this->moduleList->getAll() as $module) {
            if (strpos($module['name'], 'Mavenbird_') === 0) {
                $moduleName = str_replace('Mavenbird_', '', $module['name']);
                $displayName = ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $moduleName));
                
                $html .= '<tr>
                    <td>' . $displayName . '</td>
                    <td>' . ($module['setup_version'] ?? 'N/A') . '</td>
                </tr>';
            }
        }
        
        $html .= '</tbody></table></div>';
        
        return $html;
    }
}
