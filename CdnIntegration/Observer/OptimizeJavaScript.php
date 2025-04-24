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

class OptimizeJavaScript implements ObserverInterface
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
     * Optimize JavaScript loading
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

        // إضافة حدد معرفة المتصفحات الحديثة لتحسين أداء JS
        $modulePreload = '<script>window.mageSupportModulePreload=!!(document.createElement("link").relList.supports("modulepreload"));</script>';
        $html = str_replace('<head>', '<head>' . $modulePreload, $html);
        
        // تأجيل تحميل JavaScript غير الحرج
        $html = preg_replace_callback(
            '/<script([^>]*)src=[\'"]((?!require|jquery|checkout|customer|catalog).+\.js)[\'"]((?!defer|async)[^>]*)><\/script>/i',
            function($matches) {
                $before = $matches[1];
                $src = $matches[2];
                $after = $matches[3];
                
                // إضافة defer لكل JavaScript غير حرج
                return '<script' . $before . 'src="' . $src . '"' . $after . ' defer></script>';
            },
            $html
        );
        
        // الحد من عدد طلبات JS بتحديد نص ثابت في script
        $html = preg_replace_callback(
            '/<script>([^<]{1,50})<\/script>/i',
            function($matches) {
                $content = $matches[1];
                
                // إذا كان محتوى قصير، دمجه مع المحتويات الأخرى
                return '<script data-optimize="inline">' . $content . '</script>';
            },
            $html
        );

        $response->setBody($html);
    }
}