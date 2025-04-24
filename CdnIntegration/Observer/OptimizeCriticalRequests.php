<?php
/**
 * MagoArab CdnIntegration
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @category   MagoArab
 * @package    MagoArab_CdnIntegration
 * @copyright  Copyright (c) 2025 MagoArab (https://www.mago-ar.com/)
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
 namespace MagoArab\CdnIntegration\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MagoArab\CdnIntegration\Helper\Data;

class OptimizeCriticalRequests implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Optimize critical request chains
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->helper->isEnabled()) {
            return;
        }

        $response = $observer->getEvent()->getResponse();
        if (!$response) {
            return;
        }

        $html = $response->getBody();
        if (empty($html)) {
            return;
        }

        // استخراج الموارد الحرجة (CSS وJS الأساسية)
        $criticalResources = $this->extractCriticalResources($html);
        
        // إنشاء علامات preload للموارد الحرجة
        $preloadTags = '';
        foreach ($criticalResources as $resource) {
            $type = $resource['type'];
            $href = $resource['href'];
            
            $preloadTags .= '<link rel="preload" href="' . $href . '" as="' . $type . '" crossorigin="anonymous">' . PHP_EOL;
        }
        
        // إدراج علامات preload في head
        $html = str_replace('</head>', $preloadTags . '</head>', $html);
        
        $response->setBody($html);
    }

    /**
     * Extract critical CSS and JS resources
     *
     * @param string $html
     * @return array
     */
    private function extractCriticalResources($html)
    {
        $criticalResources = [];
        
        // استخراج الـ CSS الحرج
        preg_match_all('/<link[^>]*rel=[\'"]stylesheet[\'"][^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $cssMatches);
        if (!empty($cssMatches[1])) {
            // أخذ أول 3 ملفات CSS كموارد حرجة
            $criticalCss = array_slice($cssMatches[1], 0, 3);
            foreach ($criticalCss as $css) {
                $criticalResources[] = [
                    'type' => 'style',
                    'href' => $css
                ];
            }
        }
        
        // استخراج الـ JS الحرج
        preg_match_all('/<script[^>]*src=[\'"]([^\'"]+(require|jquery)[^\'"]+)[\'"][^>]*>/i', $html, $jsMatches);
        if (!empty($jsMatches[1])) {
            // أخذ أول ملفين JS كموارد حرجة
            $criticalJs = array_slice($jsMatches[1], 0, 2);
            foreach ($criticalJs as $js) {
                $criticalResources[] = [
                    'type' => 'script',
                    'href' => $js
                ];
            }
        }
        
        return $criticalResources;
    }
}