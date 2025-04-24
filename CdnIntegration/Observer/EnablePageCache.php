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
use Magento\Framework\App\Config\Storage\WriterInterface;

class EnablePageCache implements ObserverInterface
{
    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @param WriterInterface $configWriter
     */
    public function __construct(
        WriterInterface $configWriter
    ) {
        $this->configWriter = $configWriter;
    }

/**
 * @param Observer $observer
 * @return void
 */
public function execute(Observer $observer)
{
    if (!$this->helper->isEnabled() || !$this->helper->isFullPageCacheEnhancementEnabled()) {
        return;
    }
    
    // استخدم Varnish إذا كان مفعل في الإعدادات
    $cacheApplication = $this->helper->shouldUseVarnish() ? 2 : 1; // 2 = Varnish, 1 = Built-in
    
    // تمكين Full Page Cache
    $this->configWriter->save('system/full_page_cache/caching_application', $cacheApplication);
    
    // تعيين TTL
    $ttl = $this->helper->getFullPageCacheTtl();
    $this->configWriter->save('system/full_page_cache/ttl', $ttl);
    
    // تنظيف الكاش
    $this->cacheManager->clean(['full_page']);
}
}