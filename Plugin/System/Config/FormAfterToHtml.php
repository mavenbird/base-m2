<?php
/**
 * Mavenbird Core - Form AfterToHtml Plugin
 *
 * @category Mavenbird
 * @package Mavenbird_Core
 * @author Mavenbird Team
 */

declare(strict_types=1);

namespace Mavenbird\Core\Plugin\System\Config;

use Magento\Config\Block\System\Config\Form;
use Magento\Config\Model\Config\Structure;
use Magento\Framework\View\LayoutInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin to add Mavenbird module header to all Mavenbird configuration sections
 */
class FormAfterToHtml
{
    /**
     * Manual mapping for Mavenbird modules based on configuration section code
     */
    private const MODULE_MAP = [
        'blog' => [
            'module_name'  => 'Mavenbird_Blog',
            'display_name' => 'Ultimate Blog',
            'guide_url'    => 'https://mavenbird.com/guide/docs/ai_content_generator'
        ],
        'order_details' => [
            'module_name'  => 'Mavenbird_OrderInformation',
            'display_name' => 'Order Information on Success Page',
        ]
        // Add more manual mappings here:
        // 'section_code' => [
        //     'module_name'  => 'Mavenbird_ModuleName',
        //     'display_name' => 'Friendly Name',
        //     'guide_url'    => 'https://docs.link'
        // ]
    ];

    /**
     * @var LayoutInterface
     */
    private $layout;

    /**
     * @var Structure
     */
    private $configStructure;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    private $moduleList;

    /**
     * @param LayoutInterface $layout
     * @param Structure $configStructure
     * @param LoggerInterface $logger
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     */
    public function __construct(
        LayoutInterface $layout,
        Structure $configStructure,
        LoggerInterface $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList
    ) {
        $this->layout = $layout;
        $this->configStructure = $configStructure;
        $this->logger = $logger;
        $this->moduleList = $moduleList;
    }

    /**
     * After plugin for toHtml method
     *
     * @param Form $subject
     * @param string $html
     * @return string
     */
    public function afterToHtml(Form $subject, string $html): string
    {
        try {
            $sectionCode = $subject->getSectionCode();
            // Exclude core module configuration page
            if ($sectionCode === 'mavenbird_core') {
                return $html;
            }

            /** @var \Magento\Config\Model\Config\Structure\Element\Section $section */
            $section = $this->configStructure->getElement($sectionCode);
            if (!$section) {
                return $html;
            }

            // Check if section is under mavenbird tab or starts with mavenbird_
            $tab = $section->getAttribute('tab');
            $isMavenbirdSection = ($tab === 'mavenbird' || str_starts_with($sectionCode, 'mavenbird_'));

            if ($isMavenbirdSection) {
                // 1. Set fallback defaults
                $moduleName = 'Mavenbird_' . ucfirst($sectionCode);

                // Dynamically extract module name from section resource attribute if available
                $resource = $section->getAttribute('resource');
                if ($resource && strpos($resource, '::') !== false) {
                    list($extractedModule) = explode('::', $resource);
                    if ($extractedModule && str_starts_with($extractedModule, 'Mavenbird_')) {
                        $moduleName = $extractedModule;
                    }
                }

                $displayName = $section->getLabel() ?: $moduleName;
                $guideUrl = 'https://store.mavenbird.com/guide/';

                // 2. Override with manual mapping if defined
                if (isset(self::MODULE_MAP[$sectionCode])) {
                    $map = self::MODULE_MAP[$sectionCode];
                    if (!empty($map['module_name'])) {
                        $moduleName = $map['module_name'];
                    }
                    if (!empty($map['display_name'])) {
                        $displayName = $map['display_name'];
                    }
                    if (!empty($map['guide_url'])) {
                        $guideUrl = $map['guide_url'];
                    }
                }

                // 3. Get module version from ModuleList
                $moduleVersion = '1.0.0';
                try {
                    $moduleInfo = $this->moduleList->getOne($moduleName);
                    if (isset($moduleInfo['setup_version'])) {
                        $moduleVersion = $moduleInfo['setup_version'];
                    }
                } catch (\Exception $e) {
                    // Fallback if module is not installed or mismatch
                }

                /** @var \Magento\Framework\View\Element\Template $headerBlock */
                $headerBlock = $this->layout->createBlock(\Magento\Framework\View\Element\Template::class);
                $headerBlock->setTemplate('Mavenbird_Core::system/config/module-header.phtml');
                $headerBlock->setData('mavenbird_module_name', $displayName);
                $headerBlock->setData('mavenbird_module_version', $moduleVersion);
                $headerBlock->setData('mavenbird_guide_url', $guideUrl);
                $headerHtml = $headerBlock->toHtml();
                
                // Append the header block HTML to the form; JavaScript inside the template
                // will dynamically reposition it before the first group section.
                $html .= $headerHtml;
            }

            return $html;
        } catch (\Exception $e) {
            $this->logger->error('Mavenbird Plugin Error: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return $html;
        }
    }
}