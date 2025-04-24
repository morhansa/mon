<?php
namespace MagoArab\CdnIntegration\Observer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MagoArab\CdnIntegration\Helper\Data;
class PerformanceOptimizer implements ObserverInterface
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
     * Optimize page performance
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
{
    if (!$this->helper->isEnabled() || !$this->helper->isPerformanceOptimizationEnabled()) {
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

    // Log the start of optimization
    $this->helper->log("Performance optimization started", 'info');

    // 1. Add script error handling to fix JavaScript errors (always do this first)
    $html = $this->addScriptErrorHandling($html);
    $this->helper->log("Script error handling added", 'info');
    
    // 2. Fix Content Security Policy issues (security fixes should be early)
    $html = $this->fixContentSecurityPolicy($html);
    $this->helper->log("Content Security Policy issues fixed", 'info');

    // 3. Check if we should use extreme progressive loading
    $useProgressiveLoading = $this->helper->getConfig('cdnintegration/performance/use_progressive_loading');
    
    if ($useProgressiveLoading) {
        // If using extreme optimization, just do that and skip other optimizations
        // that might conflict with it
        $html = $this->forceProgressiveLoading($html);
        $this->helper->log("Forced progressive loading applied", 'info');
    } else {
        // Standard optimizations when not using extreme progressive loading
        
        // 4. Optimize network payloads (large JS files)
        $html = $this->optimizeNetworkPayloads($html);
        $this->helper->log("Network payloads optimized", 'info');
        
        // 5. Implement HTML streaming for faster initial render
        $html = $this->implementHtmlStreaming($html);
        $this->helper->log("HTML streaming implemented", 'info');
        
        // 6. Optimize images - use either advanced or standard method, not both
        if ($this->helper->isImageOptimizationEnabled()) {
            // Use the more advanced method that works with any theme
            $html = $this->optimizeImagesAdvanced($html);
            $this->helper->log("Images optimized with advanced technique", 'info');
        }
        
        // 7. Optimize JavaScript if enabled
        if ($this->helper->isJsOptimizationEnabled()) {
            $html = $this->optimizeJavaScript($html);
            $this->helper->log("JavaScript optimization applied", 'info');
        }
        
        // 8. Optimize Google Tag Manager and analytics
        $html = $this->optimizeGtmAdvanced($html);
        $this->helper->log("GTM and analytics optimized", 'info');
        
        // 9. Optimize critical path if enabled
        if ($this->helper->isCriticalPathOptimizationEnabled()) {
            $html = $this->optimizeCriticalPath($html);
            $this->helper->log("Critical path optimization applied", 'info');
        }
        
        // 10. Optimize tracking scripts (always apply when performance optimization is enabled)
        $html = $this->optimizeTracking($html);
        $this->helper->log("Tracking scripts optimization applied", 'info');
        
        // 11. Fix layout shift issues (always apply)
        $html = $this->fixLayoutShift($html);
        $this->helper->log("Layout shift fixes applied", 'info');
        
        // 12. Standard progressive loading (if not using extreme version)
        $html = $this->prioritizeAboveTheFold($html);
        $this->helper->log("Above-the-fold content prioritized", 'info');
        
        $html = $this->implementProgressiveLoading($html);
        $this->helper->log("Progressive loading implemented", 'info');
    }

    // Set the modified HTML
    $response->setBody($html);
    $this->helper->log("Performance optimization completed", 'info');
}
/**
 * Prioritize above-the-fold content loading
 *
 * @param string $html
 * @return string
 */
private function prioritizeAboveTheFold($html)
{
    // Extract the <head> section
    preg_match('/<head>(.*?)<\/head>/s', $html, $headMatches);
    $head = $headMatches[1] ?? '';
    // Extract critical CSS (first stylesheet)
    preg_match('/<link[^>]*rel=[\'"]stylesheet[\'"][^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i', $head, $cssMatch);
    $criticalCssUrl = $cssMatch[1] ?? '';
    // Create a script to inline critical CSS and defer everything else
    $criticalCssLoader = '
    <script>
    // Critical CSS loader
    (function() {
        // Inline critical CSS
        var criticalCssUrl = "' . $criticalCssUrl . '";
        if (criticalCssUrl) {
            var xhr = new XMLHttpRequest();
            xhr.open("GET", criticalCssUrl, true);
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    var style = document.createElement("style");
                    style.textContent = xhr.responseText;
                    document.head.appendChild(style);
                }
            };
            xhr.send();
        }
        // Function to check if element is in viewport
        function isInViewport(el) {
            if (!el) return false;
            var rect = el.getBoundingClientRect();
            return (
                rect.top <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.left <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }
        // Load images when they come into view
        function lazyLoadImages() {
            var lazyImages = [].slice.call(document.querySelectorAll("img[data-lazy-src]"));
            if ("IntersectionObserver" in window) {
                var imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var lazyImage = entry.target;
                            lazyImage.src = lazyImage.dataset.lazySrc;
                            if (lazyImage.dataset.lazySrcset) {
                                lazyImage.srcset = lazyImage.dataset.lazySrcset;
                            }
                            lazyImage.removeAttribute("data-lazy-src");
                            lazyImage.removeAttribute("data-lazy-srcset");
                            imageObserver.unobserve(lazyImage);
                        }
                    });
                });
                lazyImages.forEach(function(lazyImage) {
                    imageObserver.observe(lazyImage);
                });
            } else {
                // Fallback for browsers without intersection observer
                var active = false;
                function lazyLoad() {
                    if (active === false) {
                        active = true;
                        setTimeout(function() {
                            lazyImages.forEach(function(lazyImage) {
                                if (isInViewport(lazyImage)) {
                                    lazyImage.src = lazyImage.dataset.lazySrc;
                                    if (lazyImage.dataset.lazySrcset) {
                                        lazyImage.srcset = lazyImage.dataset.lazySrcset;
                                    }
                                    lazyImage.removeAttribute("data-lazy-src");
                                    lazyImage.removeAttribute("data-lazy-srcset");
                                    lazyImages = lazyImages.filter(function(image) {
                                        return image !== lazyImage;
                                    });
                                    if (lazyImages.length === 0) {
                                        document.removeEventListener("scroll", lazyLoad);
                                        window.removeEventListener("resize", lazyLoad);
                                        window.removeEventListener("orientationchange", lazyLoad);
                                    }
                                }
                            });
                            active = false;
                        }, 200);
                    }
                }
                document.addEventListener("scroll", lazyLoad);
                window.addEventListener("resize", lazyLoad);
                window.addEventListener("orientationchange", lazyLoad);
                lazyLoad();
            }
        }
        // Lazy load HTML elements
        function lazyLoadElements() {
            var lazyElements = [].slice.call(document.querySelectorAll("[data-lazy-html]"));
            if ("IntersectionObserver" in window) {
                var elementObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var lazyElement = entry.target;
                            lazyElement.innerHTML = lazyElement.dataset.lazyHtml;
                            lazyElement.removeAttribute("data-lazy-html");
                            elementObserver.unobserve(lazyElement);
                        }
                    });
                });
                lazyElements.forEach(function(lazyElement) {
                    elementObserver.observe(lazyElement);
                });
            } else {
                // Fallback for older browsers
                function checkElements() {
                    lazyElements.forEach(function(lazyElement) {
                        if (isInViewport(lazyElement)) {
                            lazyElement.innerHTML = lazyElement.dataset.lazyHtml;
                            lazyElement.removeAttribute("data-lazy-html");
                            lazyElements = lazyElements.filter(function(element) {
                                return element !== lazyElement;
                            });
                        }
                    });
                    if (lazyElements.length === 0) {
                        document.removeEventListener("scroll", checkElements);
                    }
                }
                document.addEventListener("scroll", checkElements);
                checkElements();
            }
        }
        // Run when DOM is loaded
        document.addEventListener("DOMContentLoaded", function() {
            lazyLoadImages();
            lazyLoadElements();
        });
    })();
    </script>
    ';
    // Add the critical CSS loader to head
    $html = str_replace('</head>', $criticalCssLoader . '</head>', $html);
    // Modify image tags for lazy loading
    $html = preg_replace_callback(
        '/<img([^>]*)src=[\'"]((?!data:)[^\'"]+)[\'"]((?!loading=|data-lazy-src)[^>]*)>/i',
        function($matches) {
            $before = $matches[1];
            $src = $matches[2];
            $after = $matches[3];
            // Skip images that are likely to be in the viewport
            if (strpos($before . $after, 'above-the-fold') !== false) {
                return $matches[0];
            }
            // Extract srcset if it exists
            $srcset = '';
            $srcsetAttr = '';
            if (preg_match('/srcset=[\'"](.*?)[\'"]/i', $before . $after, $srcsetMatch)) {
                $srcset = $srcsetMatch[1];
                $srcsetAttr = ' data-lazy-srcset="' . $srcset . '"';
                // Remove the original srcset
                $before = preg_replace('/srcset=[\'"](.*?)[\'"]/i', '', $before);
                $after = preg_replace('/srcset=[\'"](.*?)[\'"]/i', '', $after);
            }
            // Create placeholder
            $placeholder = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 1\'%3E%3C/svg%3E';
            // Return lazy loading image
            return '<img' . $before . 'src="' . $placeholder . '" data-lazy-src="' . $src . '"' . $srcsetAttr . ' loading="lazy"' . $after . '>';
        },
        $html
    );
    // Lazy load non-critical HTML blocks
    $html = preg_replace_callback(
        '/<div([^>]*)class=[\'"](.*?footer|widget|sidebar|additional|block-bottom|newsletter|social-links|copyright|links|menu-footer|secondary.*?)[\'"](.*?)>(.*?)<\/div>/is',
        function($matches) {
            $before = $matches[1];
            $class = $matches[2];
            $after = $matches[3];
            $content = $matches[4];
            // Skip small content blocks
            if (strlen($content) < 500) {
                return $matches[0];
            }
            // Create a placeholder
            return '<div' . $before . 'class="' . $class . '"' . $after . ' data-lazy-html="' . htmlspecialchars($content, ENT_QUOTES) . '"></div>';
        },
        $html
    );
    return $html;
}
/**
 * Aggressively optimize JavaScript execution time
 *
 * @param string $html
 * @return string
 */
private function optimizeJsExecution($html)
{
    // 1. Extract all large JavaScript files (especially the jsdelivr one)
    preg_match_all('/<script[^>]*src=[\'"]((?:https?:)?\/\/(?:[^\/]*(?:jsdelivr|cdn|static|_cache)[^\/]*)[^\'"]+)[\'"][^>]*><\/script>/i', $html, $matches, PREG_SET_ORDER);
    $largeScripts = [];
    foreach ($matches as $match) {
        $src = $match[1];
        $largeScripts[] = $src;
        // Remove the original script tag completely
        $html = str_replace($match[0], '', $html);
    }
    // 2. Extract Google Tag Manager scripts
    preg_match_all('/<script[^>]*src=[\'"]((?:https?:)?\/\/(?:[^\/]*(?:google|gtm|tag|analytics)[^\/]*)[^\'"]+)[\'"][^>]*><\/script>/i', $html, $matches, PREG_SET_ORDER);
    $analyticsScripts = [];
    foreach ($matches as $match) {
        $src = $match[1];
        $analyticsScripts[] = $src;
        // Remove the original script tag completely
        $html = str_replace($match[0], '', $html);
    }
    // 3. Also extract inline GTM scripts (these often initialize GTM)
    preg_match_all('/<script[^>]*>\s*(?:window\.dataLayer|window\.gtag|!function\(w,d,s,l,i\)|\(function\(w,d,s,l,i\)).*?<\/script>/s', $html, $inlineMatches);
    $inlineGtmScripts = [];
    foreach ($inlineMatches[0] as $script) {
        if (strpos($script, 'googletagmanager') !== false || 
            strpos($script, 'gtag') !== false || 
            strpos($script, 'dataLayer') !== false) {
            $inlineGtmScripts[] = $script;
            // Remove the original script
            $html = str_replace($script, '', $html);
        }
    }
    // 4. Create a script that will load all these scripts after the page is interactive
    $scriptSrcs = array_merge($largeScripts, $analyticsScripts);
    $scriptSrcsJson = json_encode($scriptSrcs);
    $inlineGtmScriptsJson = json_encode($inlineGtmScripts);
    $delayedLoader = '
    <script>
    // Delayed script loader - loads scripts after page is interactive
    (function() {
        // Scripts to load after page is interactive
        var scripts = ' . $scriptSrcsJson . ';
        var inlineScripts = ' . $inlineGtmScriptsJson . ';
        var loaded = false;
        // Function to load scripts in sequence
        function loadScripts() {
            if (loaded) return;
            loaded = true;
            console.log("🚀 Loading deferred scripts");
            // Load each script in sequence with a small delay
            var index = 0;
            function loadNextScript() {
                if (index < scripts.length) {
                    var script = document.createElement("script");
                    script.src = scripts[index];
                    script.async = true;
                    // When this script is loaded, load the next one
                    script.onload = function() {
                        index++;
                        setTimeout(loadNextScript, 100); // 100ms delay between scripts
                    };
                    // If error, still try to load the next one
                    script.onerror = function() {
                        index++;
                        setTimeout(loadNextScript, 100);
                    };
                    document.head.appendChild(script);
                } else {
                    // After all external scripts, add inline scripts
                    inlineScripts.forEach(function(scriptText) {
                        try {
                            var script = document.createElement("script");
                            script.textContent = scriptText.replace(/<\/?script[^>]*>/g, "");
                            document.head.appendChild(script);
                        } catch (e) {
                            console.error("Error executing inline script", e);
                        }
                    });
                }
            }
            loadNextScript();
        }
        // Different triggers for loading scripts
        // 1. Load after user interaction
        function onInteraction() {
            document.removeEventListener("scroll", onInteraction);
            document.removeEventListener("click", onInteraction);
            document.removeEventListener("mousemove", onInteraction);
            document.removeEventListener("touchstart", onInteraction);
            setTimeout(loadScripts, 1500); // Load 1.5s after interaction
        }
        document.addEventListener("scroll", onInteraction, {passive: true, once: true});
        document.addEventListener("click", onInteraction, {passive: true, once: true});
        document.addEventListener("mousemove", onInteraction, {passive: true, once: true});
        document.addEventListener("touchstart", onInteraction, {passive: true, once: true});
        // 2. Load after a timeout (fallback)
        setTimeout(loadScripts, 5000); // 5 second timeout
        // 3. Load on idle if supported
        if ("requestIdleCallback" in window) {
            requestIdleCallback(loadScripts, {timeout: 4000});
        }
    })();
    </script>
    ';
    // Add the delayed loader before closing body
    $html = str_replace('</body>', $delayedLoader . '</body>', $html);
    return $html;
}
/**
 * Force progressive loading with direct DOM manipulation
 *
 * @param string $html
 * @return string
 */
private function forceProgressiveLoading($html)
{
    // 1. Extract above-the-fold content
    preg_match('/<body[^>]*>(.*?)(?:<div[^>]*(?:class|id)=[\'"](?:footer|sidebar|secondary|menu-footer|newsletter))/is', $html, $aboveMatches);
    $aboveFold = $aboveMatches[1] ?? '';
    // 2. Create a minimal shell page
    $shellOpen = '<!DOCTYPE html><html><head>';
    // 3. Extract critical CSS
    preg_match_all('/<link[^>]*rel=[\'"]stylesheet[\'"][^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $cssMatches);
    $firstCssUrl = $cssMatches[1][0] ?? '';
    // 4. Create critical CSS loader
    $criticalCssLoader = '
    <style>
    /* Basic styles for shell rendering */
    body { opacity: 1; transition: opacity 0.2s; display: block; }
    .lazy-content { opacity: 0; transition: opacity 0.5s; }
    .lazy-content.loaded { opacity: 1; }
    /* Placeholder styles */
    .image-placeholder {
        background-color: #f0f0f0;
        display: inline-block;
        position: relative;
    }
    /* Spinner for loading state */
    .loading-spinner {
        width: 40px;
        height: 40px;
        margin: 20px auto;
        border: 4px solid rgba(0, 0, 0, 0.1);
        border-left-color: #7986cb;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        position: absolute;
        top: 50%;
        left: 50%;
        margin-top: -20px;
        margin-left: -20px;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    </style>
    <!-- Fetch and apply critical CSS -->
    <script>
    (function() {
        // Critical CSS URL
        var cssUrl = "' . $firstCssUrl . '";
        // Fetch critical CSS
        if (cssUrl) {
            fetch(cssUrl)
                .then(function(response) {
                    return response.text();
                })
                .then(function(css) {
                    // Extract only critical selectors (header, banner, first visible elements)
                    var criticalSelectors = [
                        "body", "header", ".logo", ".navigation", ".banner", 
                        ".block-search", ".minicart-wrapper", ".page-wrapper",
                        ".page-header", ".navbar", ".main", "h1", "h2", "p", "a"
                    ];
                    var criticalRules = "";
                    // Very simple CSS parser
                    css.replace(/([^{]+)({[^}]*})/g, function(match, selector, rules) {
                        selector = selector.trim();
                        // Check if this selector contains any critical parts
                        if (criticalSelectors.some(function(criticalSelector) {
                            return selector.indexOf(criticalSelector) !== -1;
                        })) {
                            criticalRules += selector + rules + "\n";
                        }
                    });
                    // Apply critical CSS
                    var style = document.createElement("style");
                    style.textContent = criticalRules;
                    document.head.appendChild(style);
                });
        }
    })();
    </script>
    ';
    // 5. Add progressive loading script
    $progressiveLoader = '
    <script>
    // Progressive content loader
    (function() {
        // Main loader function
        function initProgressiveLoading() {
            console.log("🚀 Progressive loader initialized");
            // 1. Load remaining CSS files non-blocking
            document.querySelectorAll("link[rel=\'stylesheet\']").forEach(function(link, index) {
                if (index > 0) { // Skip the first (critical) CSS
                    link.setAttribute("media", "print");
                    link.setAttribute("onload", "this.media=\'all\'");
                }
            });
            // 2. Lazy load images
            var lazyImages = Array.from(document.querySelectorAll("img"));
            var loadedImages = 0;
            function lazyLoadImage(img) {
                if (img.dataset.src) {
                    var src = img.dataset.src;
                    var temp = new Image();
                    temp.onload = function() {
                        img.src = src;
                        img.classList.add("loaded");
                        loadedImages++;
                    };
                    temp.src = src;
                }
            }
            // 3. Load full page content
            function loadFullContent() {
                console.log("📄 Loading full page content");
                var contentPlaceholder = document.getElementById("remaining-content");
                if (contentPlaceholder && window.fullPageContent) {
                    contentPlaceholder.innerHTML = window.fullPageContent;
                    contentPlaceholder.classList.add("loaded");
                    // Initialize lazy loading for newly added images
                    initLazyImages(contentPlaceholder);
                }
            }
            // 4. Initialize lazy loading for a container
            function initLazyImages(container) {
                var images = container.querySelectorAll("img[data-src]");
                images.forEach(function(img) {
                    if (isInViewport(img)) {
                        lazyLoadImage(img);
                    } else {
                        lazyImagesObserver.observe(img);
                    }
                });
            }
            // 5. Check if element is in viewport
            function isInViewport(el) {
                var rect = el.getBoundingClientRect();
                return (
                    rect.top <= (window.innerHeight || document.documentElement.clientHeight) + 200 &&
                    rect.bottom >= 0 &&
                    rect.left <= (window.innerWidth || document.documentElement.clientWidth) + 200 &&
                    rect.right >= 0
                );
            }
            // 6. Set up intersection observer for lazy loading
            var lazyImagesObserver;
            if ("IntersectionObserver" in window) {
                lazyImagesObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            lazyLoadImage(entry.target);
                            lazyImagesObserver.unobserve(entry.target);
                        }
                    });
                });
                lazyImages.forEach(function(image) {
                    if (image.dataset.src) {
                        lazyImagesObserver.observe(image);
                    }
                });
            } else {
                // Fallback for browsers without intersection observer
                lazyImages.forEach(function(image) {
                    if (image.dataset.src && isInViewport(image)) {
                        lazyLoadImage(image);
                    }
                });
                // Check on scroll
                window.addEventListener("scroll", function() {
                    lazyImages.forEach(function(image) {
                        if (image.dataset.src && !image.classList.contains("loaded") && isInViewport(image)) {
                            lazyLoadImage(image);
                        }
                    });
                }, {passive: true});
            }
            // 7. Load full content when:
            // After initial content is visible
            setTimeout(loadFullContent, 1000);
            // On user interaction
            ["mousemove", "click", "keydown", "touchstart", "scroll"].forEach(function(event) {
                document.addEventListener(event, function() {
                    loadFullContent();
                }, {once: true, passive: true});
            });
        }
        // Initialize when DOM is loaded
        if (document.readyState !== "loading") {
            initProgressiveLoading();
        } else {
            document.addEventListener("DOMContentLoaded", initProgressiveLoading);
        }
    })();
    </script>
    ';
    // 6. Combine all parts
    $shellHead = $shellOpen . $criticalCssLoader . $progressiveLoader;
    // 7. Extract all HEAD content
    preg_match('/<head>(.*?)<\/head>/s', $html, $headMatches);
    $headContent = $headMatches[1] ?? '';
    // 8. Modify image tags in above-fold content
    $aboveFold = preg_replace_callback(
        '/<img[^>]*src=[\'"]((?!data:)[^\'"]+)[\'"][^>]*>/i',
        function($matches) {
            $fullTag = $matches[0];
            $src = $matches[1];
            // Skip small icons or logos
            if (strpos($fullTag, 'logo') !== false || 
                strpos($fullTag, 'icon') !== false || 
                strpos($fullTag, 'width="') !== false && preg_match('/width="(\d+)"/', $fullTag, $widthMatch) && $widthMatch[1] < 60) {
                return $fullTag;
            }
            // Replace src with data-src
            $newTag = str_replace('src="' . $src . '"', 'src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 1\'%3E%3C/svg%3E" data-src="' . $src . '"', $fullTag);
            return $newTag;
        },
        $aboveFold
    );
    // 9. Store remaining content
    $remainingContent = preg_replace('/^.*?<body[^>]*>(.*?)(?:<div[^>]*(?:class|id)=[\'"](?:footer|sidebar|secondary|menu-footer|newsletter))/is', '$1', $html, 1);
    $remainingContent = '<div class="lazy-content" id="remaining-content"><div class="loading-spinner"></div></div>';
    $remainingContentScript = '
    <script>
    // Store full page content for later loading
    window.fullPageContent = ' . json_encode(str_replace(['<script', '</script>'], ['<scr" + "ipt', '</scr" + "ipt>'], substr($html, strpos($html, '<body') + 6, strpos($html, '</body>') - strpos($html, '<body') - 6))) . ';
    </script>
    ';
    // 10. Build the final shell page
    $shellPage = $shellHead . '</head><body>' . $aboveFold . $remainingContent . $remainingContentScript . '</body></html>';
    return $shellPage;
}
/**
 * Implement progressive page loading
 *
 * @param string $html
 * @return string
 */
private function implementProgressiveLoading($html)
{
    // Progressive loading script
    $progressiveLoader = '
    <script>
    // Progressive page loading
    (function() {
        // Priorities for resource loading
        var priorities = {
            critical: [], // Load immediately
            high: [],     // Load during idle time in first 2 seconds
            medium: [],   // Load after first paint
            low: []       // Load after page is interactive
        };
        // Classify resources by priority
        function classifyResources() {
            // Process stylesheets
            document.querySelectorAll("link[rel=\'stylesheet\']").forEach(function(link) {
                // First stylesheet is critical
                if (priorities.critical.length === 0) {
                    priorities.critical.push(link);
                } else {
                    priorities.medium.push(link);
                }
            });
            // Process scripts
            document.querySelectorAll("script[src]").forEach(function(script) {
                if (script.src.includes("jquery") || script.src.includes("require")) {
                    priorities.high.push(script);
                } else if (script.src.includes("google") || script.src.includes("facebook") || 
                           script.src.includes("analytics") || script.src.includes("pixel")) {
                    priorities.low.push(script);
                } else {
                    priorities.medium.push(script);
                }
            });
            // Process images
            document.querySelectorAll("img").forEach(function(img) {
                if (isInViewport(img) || img.classList.contains("logo") || 
                    img.closest("[data-content-type=\'banner\']")) {
                    priorities.high.push(img);
                } else {
                    priorities.medium.push(img);
                }
            });
        }
        // Function to check if element is in viewport
        function isInViewport(el) {
            if (!el) return false;
            var rect = el.getBoundingClientRect();
            return (
                rect.top <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.left <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }
        // Load resources by priority
        function loadByPriority() {
            // Load critical resources immediately
            priorities.critical.forEach(loadResource);
            // Load high priority after 100ms
            setTimeout(function() {
                priorities.high.forEach(loadResource);
            }, 100);
            // Load medium priority after first paint (500ms)
            setTimeout(function() {
                priorities.medium.forEach(loadResource);
            }, 500);
            // Load low priority resources after page is interactive (3s)
            setTimeout(function() {
                priorities.low.forEach(loadResource);
            }, 3000);
        }
        // Load a specific resource
        function loadResource(resource) {
            if (resource.tagName === "LINK") {
                // Load stylesheet
                resource.media = "all";
            } else if (resource.tagName === "SCRIPT") {
                // Clone and replace script to force loading
                var newScript = document.createElement("script");
                newScript.src = resource.src;
                newScript.async = true;
                resource.parentNode.replaceChild(newScript, resource);
            } else if (resource.tagName === "IMG") {
                // Load image if it has data-lazy-src
                if (resource.dataset.lazySrc) {
                    resource.src = resource.dataset.lazySrc;
                    if (resource.dataset.lazySrcset) {
                        resource.srcset = resource.dataset.lazySrcset;
                    }
                }
            }
        }
        // Initialize when DOM is ready
        if (document.readyState !== "loading") {
            classifyResources();
            loadByPriority();
        } else {
            document.addEventListener("DOMContentLoaded", function() {
                classifyResources();
                loadByPriority();
            });
        }
        // Progressive enhancement for interactions
        window.addEventListener("scroll", function() {
            // Load all medium priority resources on first scroll
            priorities.medium.forEach(loadResource);
        }, {once: true, passive: true});
        // Load all resources on user interaction
        ["mousedown", "keydown", "touchstart"].forEach(function(event) {
            window.addEventListener(event, function() {
                // Load all remaining resources on user interaction
                priorities.medium.forEach(loadResource);
                priorities.low.forEach(loadResource);
            }, {once: true, passive: true});
        });
    })();
    </script>
    ';
    // Add progressive loader after opening body tag
    $html = preg_replace('/<body([^>]*)>/', '<body$1>' . $progressiveLoader, $html);
    // Add non-blocking CSS loading
    $html = preg_replace_callback(
        '/<link([^>]*)rel=[\'"]stylesheet[\'"]([^>]*)href=[\'"]((?!print)[^\'"]+)[\'"]((?!media=")[^>]*)>/i',
        function($matches) {
            $before = $matches[1];
            $rel = $matches[2];
            $href = $matches[3];
            $after = $matches[4];
            // Skip the first CSS file (treat as critical)
            static $firstCss = true;
            if ($firstCss) {
                $firstCss = false;
                return $matches[0];
            }
            // Non-blocking CSS loading
            return '<link' . $before . 'rel="stylesheet"' . $rel . 'href="' . $href . '" media="print" onload="this.media=\'all\'"' . $after . '>';
        },
        $html
    );
    return $html;
}
/**
 * Advanced payload optimization with code splitting
 *
 * @param string $html
 * @return string
 */
private function optimizeNetworkPayloads($html)
{
// 1. Identify large files (JS, CSS, images)
    $largeFiles = [];
   // 1.1 Searching for large JS files
    preg_match_all('/<script[^>]*src=[\'"]((?:https?:)?\/\/(?:[^\/]*(?:jsdelivr|cdn|static|_cache)[^\/]*)[^\'"]+)[\'"][^>]*><\/script>/i', $html, $jsMatches);
    foreach ($jsMatches[1] as $src) {
        $largeFiles[] = [
            'type' => 'js',
            'url' => $src
        ];
    }
    // 1.2 Finding large CSS files
    preg_match_all('/<link[^>]*rel=[\'"]stylesheet[\'"][^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $cssMatches);
    foreach ($cssMatches[1] as $index => $src) {
        // The first CSS is considered critical, so we skip it.
        if ($index === 0) continue;
        $largeFiles[] = [
            'type' => 'css',
            'url' => $src
        ];
    }
    // Remove large files from HTML
    foreach ($largeFiles as $file) {
        if ($file['type'] === 'js') {
            $html = preg_replace('/<script[^>]*src=[\'"]' . preg_quote($file['url'], '/') . '[\'"][^>]*><\/script>/i', '', $html);
        } else if ($file['type'] === 'css' && strpos($file['url'], 'critical') === false) {
            $html = preg_replace('/<link[^>]*href=[\'"]' . preg_quote($file['url'], '/') . '[\'"][^>]*>/i', '', $html);
        }
    }
    // Convert data to JSON
    $largeFilesJson = json_encode($largeFiles);
    // Create an advanced download system
    $codeLoader = '
    <script>
   // Advanced upload system for large files
    (function() {
       // List of files to be uploaded
        var filesToLoad = ' . $largeFilesJson . ';
        var loadedFiles = {};
        // Function to record time
        function logTiming(label, url) {
            if (window.performance && window.performance.mark) {
                window.performance.mark(label + "-" + url.substring(0, 40));
            }
        }
     // Function to split and upload a large JS file
        function loadAndSplitJsFile(url) {
            logTiming("start-load", url);
            return fetch(url)
                .then(response => {
                    logTiming("response-received", url);
                    return response.text();
                })
                .then(content => {
                    logTiming("content-parsed", url);
                   // Split the code into small parts (about 100KB each)
                    var chunkSize = 100000;
                    var chunks = [];
                    for (var i = 0; i < content.length; i += chunkSize) {
                        chunks.push(content.slice(i, i + chunkSize));
                    }
                    console.log("🔄 File " + url + " split into " + chunks.length + " chunks");
                  // Execute parts with a time interval between them
                    return new Promise((resolve) => {
                        var index = 0;
                        function executeNextChunk() {
                            if (index < chunks.length) {
                                try {
                                  // Use Function constructor to execute the code
                                    var scriptContent = chunks[index];
                                    new Function(scriptContent)();
                                    index++;
                                    // Slight delay between parts to prevent interface blocking
                                    setTimeout(executeNextChunk, 10);
                                } catch (e) {
                                    console.error("Error executing chunk " + index + " of " + url, e);
                                    index++;
                                    setTimeout(executeNextChunk, 10);
                                }
                            } else {
                                logTiming("execution-complete", url);
                                console.log("✅ Successfully loaded and executed: " + url);
                                resolve();
                            }
                        }
                       // Start executing parts
                        executeNextChunk();
                    });
                })
                .catch(error => {
                    console.error("❌ Error loading file: " + url, error);
                    // Use the traditional loading method as a backup plan
                    return new Promise((resolve) => {
                        var script = document.createElement("script");
                        script.src = url;
                        script.onload = function() {
                            console.log("✅ Loaded via fallback method: " + url);
                            resolve();
                        };
                        script.onerror = function() {
                            console.error("❌ Failed to load via fallback: " + url);
                            resolve(); // We continue even with an error
                        };
                        document.head.appendChild(script);
                    });
                });
        }
       // Function to load the CSS file
        function loadCssFile(url) {
            return new Promise((resolve) => {
                var link = document.createElement("link");
                link.rel = "stylesheet";
                link.href = url;
                link.onload = function() {
                    console.log("✅ CSS loaded: " + url);
                    resolve();
                };
                link.onerror = function() {
                    console.error("❌ Failed to load CSS: " + url);
                    resolve();
                };
                document.head.appendChild(link);
            });
        }
        // Function to upload files in stages
        function loadFilesByPriority() {
            // Sort files by priority
            var cssFiles = filesToLoad.filter(file => file.type === "css");
            var jsFiles = filesToLoad.filter(file => file.type === "js");
            // Load CSS files first (because they affect visual presentation)
            Promise.all(cssFiles.map(file => loadCssFile(file.url)))
                .then(() => {
                    console.log("CSS files loaded, now loading JS files sequentially");
                    // Load JS files sequentially (one by one)
                    return jsFiles.reduce((promise, file) => {
                        return promise.then(() => {
                           // Skip already downloaded files
                            if (loadedFiles[file.url]) {
                                console.log("⏩ Already loaded: " + file.url);
                                return Promise.resolve();
                            }
                            console.log("🔄 Now loading: " + file.url);
                            loadedFiles[file.url] = true;
                            return loadAndSplitJsFile(file.url);
                        });
                    }, Promise.resolve());
                })
                .then(() => {
                    console.log("✨ All files loaded successfully!");
                })
                .catch(error => {
                    console.error("Error in loading sequence:", error);
                });
        }
     //Log start download
        console.log("🚀 Initializing advanced resource loader");
        // Determine the best time to download files
        function scheduleLoading() {
            // 1. Download after the initial offer ends
            if (document.readyState === "complete") {
                setTimeout(loadFilesByPriority, 500);
            } else {
                window.addEventListener("load", function() {
                    setTimeout(loadFilesByPriority, 500);
                });
            }
         // 2. Or when the user interacts
            var hasInteracted = false;
            function onUserInteraction() {
                if (!hasInteracted) {
                    hasInteracted = true;
                    loadFilesByPriority();
                }
            }
            ["mousemove", "click", "keydown", "scroll", "touchstart"].forEach(function(eventType) {
                document.addEventListener(eventType, onUserInteraction, {once: true, passive: true});
            });
            // 3. Or when the browser is idle
            if ("requestIdleCallback" in window) {
                requestIdleCallback(function() {
                    loadFilesByPriority();
                }, {timeout: 3000});
            } else {
                setTimeout(loadFilesByPriority, 3000);
            }
        }
       // Start download scheduling
        scheduleLoading();
    })();
    </script>
    ';
  // Add loading system before closing head tag
    $html = str_replace('</head>', $codeLoader . '</head>', $html);
    return $html;
}
/**
 * Advanced image optimization
 *
 * @param string $html
 * @return string
 */
private function optimizeImagesAdvanced($html)
{
    // إنشاء نظام LQIP (Low Quality Image Placeholders)
    $lqipSystem = '
    <script>
   // Progressive image loading system
    (function() {
        var imgObserver;
        var lazyImages = [];
        function setupObserver() {
            if ("IntersectionObserver" in window) {
                imgObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            loadImage(entry.target);
                            imgObserver.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: "200px" // Load images 200px before they appear
                });
            }
        }
        function loadImage(img) {
            var src = img.dataset.src;
            if (!src) return;
            // Create a temporary image element for preloading
            var tempImg = new Image();
            tempImg.onload = function() {
                // Update the actual image after loading the temporary image
                img.src = src;
                img.removeAttribute("data-src");
                if (img.dataset.srcset) {
                    img.srcset = img.dataset.srcset;
                    img.removeAttribute("data-srcset");
                }
                // Add a class for visual effect
                img.classList.add("img-loaded");
            };
            tempImg.onerror = function() {
                console.error("Failed to load image:", src);
                // Update the image anyway to prevent it from being stuck in loading state
                img.src = src;
                img.removeAttribute("data-src");
            };
          // Start loading the image
            tempImg.src = src;
        }
        function processPendingImages() {
            if (imgObserver) {
                lazyImages.forEach(function(img) {
                    imgObserver.observe(img);
                });
            } else {
                // Alternative plan for browsers that do not support IntersectionObserver
                lazyImages.forEach(function(img) {
                    loadImage(img);
                });
            }
        }
        // Setting up the kThumbnails system to reduce CLS
        function setupThumbnailSystem() {
            var style = document.createElement("style");
            style.textContent = `
                .lazy-image-container {
                    position: relative;
                    overflow: hidden;
                    background-color: #f0f0f0;
                }
                .lazy-image-container img {
                    transition: opacity 0.3s ease;
                }
                .lazy-image-container img:not(.img-loaded) {
                    opacity: 0;
                }
                .lazy-image-container .img-placeholder {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    filter: blur(8px);
                    transform: scale(1.05);
                    transition: opacity 0.3s ease;
                }
                .lazy-image-container .img-loaded + .img-placeholder {
                    opacity: 0;
                }
            `;
            document.head.appendChild(style);
        }
   // Register images for delayed loading
        function registerLazyImages() {
            lazyImages = Array.from(document.querySelectorAll("img[data-src]"));
            // Convert images to LQIP
            lazyImages.forEach(function(img) {
                if (img.complete && img.naturalWidth !== 0) {
                   // Image already loaded - skip
                    return;
                }
              // If the image is not already inside a container
                if (!img.parentNode.classList.contains("lazy-image-container")) {
               // Save original dimensions
                    var width = img.width || 0;
                    var height = img.height || 0;
                    var aspectRatio = "";
                    if (width && height) {
                        aspectRatio = "padding-bottom: " + (height / width * 100) + "%;";
                    } else {
                        aspectRatio = "padding-bottom: 56.25%;"; // نسبة 16:9 كاحتياطي
                    }
                    // Create a container
                    var container = document.createElement("div");
                    container.className = "lazy-image-container";
                    container.style = aspectRatio;
                    // Create a placeholder image
                    var placeholder = document.createElement("div");
                    placeholder.className = "img-placeholder";
                    //Move image to container
                    img.parentNode.insertBefore(container, img);
                    container.appendChild(img);
                    container.appendChild(placeholder);
                }
            });
            processPendingImages();
        }
        // System configuration
        function init() {
            setupThumbnailSystem();
            setupObserver();
            registerLazyImages();
            // Rescan after page load
            window.addEventListener("load", registerLazyImages);
            // Rescan when new content is added
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length) {
                        registerLazyImages();
                    }
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
        // Start when document is ready
        if (document.readyState !== "loading") {
            init();
        } else {
            document.addEventListener("DOMContentLoaded", init);
        }
    })();
    </script>
    ';
    // Add LQIP system
    $html = str_replace('</head>', $lqipSystem . '</head>', $html);
   // Convert images to delayed loading
    $html = preg_replace_callback(
        '/<img([^>]*)src=[\'"]((?!data:)[^\'"]+)[\'"]([^>]*)>/i',
        function($matches) {
            $beforeAttrs = $matches[1];
            $src = $matches[2];
            $afterAttrs = $matches[3];
            // Skip small images like logos and icons
            if (strpos($beforeAttrs . $afterAttrs, 'logo') !== false || 
                strpos($beforeAttrs . $afterAttrs, 'icon') !== false ||
                strpos($src, 'icon') !== false || 
                strpos($src, 'logo') !== false) {
                return $matches[0];
            }
            // Extract srcset if it exists
            $srcsetAttr = '';
            if (preg_match('/srcset=[\'"](.*?)[\'"]/i', $beforeAttrs . $afterAttrs, $srcsetMatch)) {
                $srcset = $srcsetMatch[1];
                $srcsetAttr = ' data-srcset="' . $srcset . '"';
               // Remove the original srcset
                $beforeAttrs = preg_replace('/srcset=[\'"](.*?)[\'"]/i', '', $beforeAttrs);
                $afterAttrs = preg_replace('/srcset=[\'"](.*?)[\'"]/i', '', $afterAttrs);
            }
           // Create a simple placeholder
            $placeholder = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 1\'%3E%3C/svg%3E';
            // Create an image with delayed loading
            return '<img' . $beforeAttrs . 'src="' . $placeholder . '" data-src="' . $src . '"' . $srcsetAttr . ' loading="lazy"' . $afterAttrs . '>';
        },
        $html
    );
    return $html;
}
/**
 * Advanced analytics and GTM optimization
 *
 * @param string $html
 * @return string
 */
private function optimizeGtmAdvanced($html)
{
    // 1. Extract GTM codes
    preg_match_all('/<script[^>]*src=[\'"]((?:https?:)?\/\/(?:[^\/]*(?:google|gtag|gtm|analytics|facebook)[^\/]*)[^\'"]+)[\'"][^>]*><\/script>/i', $html, $matches, PREG_SET_ORDER);
    $analyticsScripts = [];
    foreach ($matches as $match) {
        $src = $match[1];
        $analyticsScripts[] = $src;
     // Remove original text
        $html = str_replace($match[0], '', $html);
    }
    // 2. Extract internal GTM codes
    preg_match_all('/<script[^>]*>\s*(?:window\.dataLayer|window\.gtag|!function\(w,d,s,l,i\)|\(function\(w,d,s,l,i\)).*?<\/script>/s', $html, $inlineMatches);
    $inlineGtmScripts = [];
    foreach ($inlineMatches[0] as $script) {
        if (strpos($script, 'googletagmanager') !== false || 
            strpos($script, 'gtag') !== false || 
            strpos($script, 'dataLayer') !== false ||
            strpos($script, 'fbq') !== false ||
            strpos($script, 'google') !== false) {
            $inlineGtmScripts[] = $script;
            // Remove original text
            $html = str_replace($script, '', $html);
        }
    }
   // 3. Extract ID from GTM
    $gtmId = '';
    foreach ($analyticsScripts as $script) {
        if (preg_match('/GTM-[A-Z0-9]+/i', $script, $idMatch)) {
            $gtmId = $idMatch[0];
            break;
        }
    }
    // 4. Create an optimized loading system
    $gtmLoader = '
    <script>
    // Enhanced loading system for Google Tag Manager
    (function() {
        // Initialize dataLayer
        window.dataLayer = window.dataLayer || [];
        function gtag() {
            dataLayer.push(arguments);
        }
        // Configure Facebook Pixel
        window.fbq = window.fbq || function() {
            (window._fbq = window._fbq || []).push(arguments);
        };
        // List of analysis texts
        var analyticsScripts = ' . json_encode($analyticsScripts) . ';
        var inlineScripts = ' . json_encode(array_map(function($script) {
            return preg_replace('/<\/?script[^>]*>/i', '', $script);
        }, $inlineGtmScripts)) . ';
        var gtmId = "' . $gtmId . '";
        var hasLoadedAnalytics = false;
        // Create a fake gtag
        gtag("js", new Date());
        // Load analytics function
        function loadAnalytics() {
            if (hasLoadedAnalytics) return;
            hasLoadedAnalytics = true;
            console.log("📊 Loading analytics...");
            // 1. Load GTM in an optimized way (if available)
            if (gtmId) {
                (function(w,d,s,l,i){
                    w[l]=w[l]||[];
                    w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});
                    var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";
                    j.async=true;
                    j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl+"&gtm_auth=&gtm_preview=&gtm_cookies_win=x";
                    f.parentNode.insertBefore(j,f);
                })(window,document,"script","dataLayer",gtmId);
                console.log("✅ GTM loaded with ID:", gtmId);
            }
            // 2. Implementing internal texts
            inlineScripts.forEach(function(scriptText) {
                try {
                    new Function(scriptText)();
                } catch (e) {
                    console.error("Error executing inline script:", e);
                }
            });
            // 3. Download the rest of the external texts
            analyticsScripts.forEach(function(src) {
                if (src.indexOf("gtm.js") !== -1 && gtmId) {
                    // Skip GTM if its already loaded
                    return;
                }
                var script = document.createElement("script");
                script.async = true;
                script.src = src;
                document.head.appendChild(script);
                console.log("📊 Loading:", src);
            });
        }
        // Load after user interaction
        function onUserInteraction() {
            ["scroll", "click", "mousemove", "touchstart"].forEach(function(eventType) {
                document.removeEventListener(eventType, onUserInteraction, {passive: true});
            });
            setTimeout(loadAnalytics, 2000);
        }
        ["scroll", "click", "mousemove", "touchstart"].forEach(function(eventType) {
            document.addEventListener(eventType, onUserInteraction, {passive: true});
        });
        // Load after a while in all cases
        setTimeout(loadAnalytics, 5000);
        // Load when browser is idle
        if ("requestIdleCallback" in window) {
            requestIdleCallback(function() {
                loadAnalytics();
            }, {timeout: 5000});
        }
       // Handling log events while waiting for GTM to load
        var originalPush = Array.prototype.push;
        dataLayer.push = function() {
            for (var i = 0; i < arguments.length; i++) {
                originalPush.call(this, arguments[i]);
                // Instant loading upon purchase or conversion events
                var event = arguments[i] && arguments[i].event;
                if (event === "purchase" || event === "conversion" || event === "add_to_cart") {
                    loadAnalytics();
                }
            }
        };
    })();
    </script>
    ';
   // Add improved loading system
    $html = str_replace('</head>', $gtmLoader . '</head>', $html);
    return $html;
}
/**
 * Implement instant page preloading
 *
 * @param string $html
 * @return string
 */
private function implementInstantPage($html)
{
    // Instant page script
    $instantPageScript = '
    <script>
    // Instant Page preloading
    (function() {
        var mouseoverTimer;
        var lastTouchTimestamp;
        var prefetches = {};
        var prefetchElement = document.createElement("link");
        var isSupported = prefetchElement.relList && 
                          prefetchElement.relList.supports && 
                          prefetchElement.relList.supports("prefetch");
        if (!isSupported) {
            return;
        }
        // Detect mobile devices
        var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        // Prefetch a URL
        function prefetch(url) {
            if (prefetches[url]) {
                return;
            }
            // Limit to 5 prefetches
            if (Object.keys(prefetches).length >= 5) {
                return;
            }
            prefetches[url] = true;
            var link = document.createElement("link");
            link.rel = "prefetch";
            link.href = url;
            document.head.appendChild(link);
        }
        // Handle mouseover events
        function mouseoverListener(e) {
            var linkElement = e.target.closest("a");
            if (!linkElement || 
                !linkElement.href ||
                linkElement.href.indexOf(window.location.hostname) === -1 ||
                linkElement.href.indexOf("#") !== -1 || 
                linkElement.hasAttribute("download") ||
                linkElement.dataset.noPrefetch !== undefined) {
                return;
            }
            // Prefetch after hovering for 65ms
            mouseoverTimer = setTimeout(function() {
                prefetch(linkElement.href);
            }, 65);
        }
        // Handle mouseout events
        function mouseoutListener(e) {
            if (mouseoverTimer) {
                clearTimeout(mouseoverTimer);
                mouseoverTimer = null;
            }
        }
        // Handle touchstart events
        function touchstartListener(e) {
            lastTouchTimestamp = performance.now();
            var linkElement = e.target.closest("a");
            if (!linkElement || 
                !linkElement.href ||
                linkElement.href.indexOf(window.location.hostname) === -1) {
                return;
            }
            prefetch(linkElement.href);
        }
        // Add event listeners
        document.addEventListener("mouseover", mouseoverListener, { passive: true });
        document.addEventListener("mouseout", mouseoutListener, { passive: true });
        if (isMobile) {
            document.addEventListener("touchstart", touchstartListener, { passive: true });
        }
    })();
    </script>
    ';
    // Add instant page script before closing body
    $html = str_replace('</body>', $instantPageScript . '</body>', $html);
    return $html;
}
/**
 * Implement HTML streaming for faster initial render
 *
 * @param string $html
 * @return string
 */
private function implementHtmlStreaming($html)
{
    // Extract head content
    preg_match('/<head>(.*?)<\/head>/s', $html, $headMatches);
    $headContent = $headMatches[1] ?? '';
    // Extract critical CSS
    preg_match('/<style[^>]*>(.*?)<\/style>/s', $headContent, $styleMatches);
    $criticalCss = $styleMatches[1] ?? '';
    // Extract critical parts of the page (above the fold)
    preg_match('/<body[^>]*>(.*?)<div[^>]*class=[\'"](?:footer|sidebar|secondary)/is', $html, $bodyMatches);
    $aboveTheFold = $bodyMatches[1] ?? '';
    // Create streaming HTML template
    $streamingTemplate = '
    <script>
    // HTML Streaming for faster initial render
    (function() {
        // Store original HTML rendering
        var originalRender = window.requestAnimationFrame;
        // Speed up first paint
        window.requestAnimationFrame = function(callback) {
            setTimeout(callback, 0);
        };
        // Restore original rendering after first paint
        setTimeout(function() {
            window.requestAnimationFrame = originalRender;
        }, 100);
        // Add flush hint for browsers
        document.documentElement.style.display = "block";
    })();
    </script>
    ';
    // Add streaming template at the top of head
    $html = str_replace('<head>', '<head>' . $streamingTemplate, $html);
    // Add flushing hint for Magento
    $flushingHint = '
    <!-- FLUSH BUFFER HERE FOR FASTER INITIAL RENDER -->
    <script>
    // Tell browser we\'re ready to render
    document.documentElement.style.visibility = "visible";
    </script>
    ';
    // Add flushing hint after key elements
    $html = str_replace('</head>', '</head>' . $flushingHint, $html);
    return $html;
}
    /**
     * Optimize images by adding WebP format and lazy loading
     *
     * @param string $html
     * @return string
     */
    private function optimizeImages($html)
    {
        // Add responsive image sizes to all images without width/height
        $html = preg_replace_callback(
            '/<img([^>]*)src=[\'"]((?!data:)[^\'"]+\.(jpg|jpeg|png|gif))[\'"]((?!srcset)[^>]*)>/i',
            function($matches) {
                $before = $matches[1];
                $src = $matches[2];
                $ext = $matches[3];
                $after = $matches[4];
                // Check if width/height are already set
                $hasWidth = strpos($before . $after, 'width=') !== false;
                $hasHeight = strpos($before . $after, 'height=') !== false;
                // Only add attributes if needed
                $sizeAttrs = '';
                if (!$hasWidth || !$hasHeight) {
                    $sizeAttrs = ' style="aspect-ratio: auto; max-width: 100%;"';
                }
                // Add loading="lazy" if not in the first viewport
                $loadingAttr = '';
                if (strpos($before . $after, 'loading=') === false && 
                    strpos($before . $after, 'above-the-fold') === false) {
                    $loadingAttr = ' loading="lazy"';
                }
                return '<img' . $before . 'src="' . $src . '"' . $sizeAttrs . $loadingAttr . $after . '>';
            },
            $html
        );
        // Convert images to WebP using picture element if configured
        if ($this->helper->isWebpConversionEnabled()) {
            $html = preg_replace_callback(
                '/<img([^>]*)src=[\'"]((?!data:)[^\'"]+\.(jpg|jpeg|png))[\'"]((?!srcset)[^>]*)>/i',
                function($matches) {
                    $before = $matches[1];
                    $src = $matches[2];
                    $ext = $matches[3];
                    $after = $matches[4];
                    // Generate WebP path
                    $webpSrc = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $src);
                    // Create picture element with WebP source
                    return '<picture>' .
                           '<source srcset="' . $webpSrc . '" type="image/webp">' .
                           '<img' . $before . 'src="' . $src . '"' . $after . '>' .
                           '</picture>';
                },
                $html
            );
        }
        return $html;
    }
    /**
     * Optimize tracking scripts
     *
     * @param string $html
     * @return string
     */
    private function optimizeTracking($html)
    {
        // Universal tracking scripts loader
        $trackingLoader = '
        <script>
        // Detect when page becomes idle or user interactions occur
        var idleTime = 0;
        function resetIdleTime() { idleTime = 0; }
        // Add all common user events to detect interaction
        ["mousemove", "keypress", "scroll", "click", "touchstart"].forEach(function(event) {
            document.addEventListener(event, resetIdleTime, { passive: true });
        });
        // Initialize tracking scripts only after page becomes idle or after user interaction
        function loadTracking() {
            if (window.trackingLoaded) return;
            window.trackingLoaded = true;
            // Find tracking script placeholders and replace with actual scripts
            document.querySelectorAll("[data-tracking-src]").forEach(function(placeholder) {
                var script = document.createElement("script");
                script.src = placeholder.getAttribute("data-tracking-src");
                script.async = true;
                document.head.appendChild(script);
                placeholder.parentNode.removeChild(placeholder);
            });
        }
        // Load tracking after 4 seconds of idle time or any user interaction
        setInterval(function() {
            idleTime += 1;
            if (idleTime >= 4) loadTracking();
        }, 1000);
        // Also load tracking on page idle or when user starts to leave page
        window.addEventListener("beforeunload", loadTracking);
        if ("requestIdleCallback" in window) {
            requestIdleCallback(loadTracking, { timeout: 5000 });
        } else {
            setTimeout(loadTracking, 5000);
        }
        </script>
        ';
        // Replace tracking scripts with placeholders
        $html = preg_replace_callback(
            '/<script([^>]*)src=[\'"]((?:https?:)?\/\/(?:[^\/]*(?:google-analytics|googletagmanager|facebook|fbcdn|analytics|pixel|gtm|tag)[^\/]*)[^\'"]+)[\'"]((?!noOptimize)[^>]*)><\/script>/i',
            function($matches) {
                $before = $matches[1];
                $src = $matches[2];
                $after = $matches[3];
                // Create placeholder for deferred loading
                return '<script data-tracking-src="' . $src . '" type="text/plain"></script>';
            },
            $html
        );
        // Add tracking loader before closing body
        $html = str_replace('</body>', $trackingLoader . '</body>', $html);
        return $html;
    }
    /**
     * Optimize JavaScript loading
     *
     * @param string $html
     * @return string
     */
    private function optimizeJavaScript($html)
    {
        // Add module preload detection
        $modulePreload = '<script>window.mageSupportModulePreload=!!(document.createElement("link").relList.supports("modulepreload"));</script>';
        $html = str_replace('<head>', '<head>' . $modulePreload, $html);
        // Universal script loader that delays non-critical scripts
        $scriptLoader = '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Function to check if an element is in viewport
            function isInViewport(element) {
                const rect = element.getBoundingClientRect();
                return (
                    rect.top >= 0 &&
                    rect.left >= 0 &&
                    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
                );
            }
            // Find all scripts (advertising, tracking, social) and delay them
            var trackerScripts = document.querySelectorAll("script[src*=\'google\'], script[src*=\'facebook\'], script[src*=\'analytics\'], script[src*=\'pixel\'], script[src*=\'tag\']");
            trackerScripts.forEach(function(script) {
                if(script.src) {
                    var newScript = document.createElement("script");
                    newScript.src = script.src;
                    newScript.async = true;
                    script.parentNode.removeChild(script);
                    // Delay loading by 3 seconds or when user interacts
                    setTimeout(function() {
                        document.head.appendChild(newScript);
                    }, 3000);
                }
            });
            // Find very large scripts (over 100KB) and load them on demand
            var scripts = document.querySelectorAll("script[src*=\'.js\']");
            scripts.forEach(function(script) {
                if(!script.hasAttribute("critical") && 
                   !script.src.includes("jquery") && 
                   !script.src.includes("require")) {
                    script.setAttribute("defer", "");
                }
            });
        });
        </script>
        ';
        // Add script loader before closing body
        $html = str_replace('</body>', $scriptLoader . '</body>', $html);
        // Defer non-critical scripts
        $html = preg_replace_callback(
            '/<script([^>]*)src=[\'"]((?!require|jquery|checkout|customer|catalog).+\.js)[\'"]((?!defer|async|critical)[^>]*)><\/script>/i',
            function($matches) {
                $before = $matches[1];
                $src = $matches[2];
                $after = $matches[3];
                // Add defer to all non-critical JavaScript
                return '<script' . $before . 'src="' . $src . '"' . $after . ' defer></script>';
            },
            $html
        );
        return $html;
    }
    /**
     * Optimize critical path by adding preload directives
     *
     * @param string $html
     * @return string
     */
    private function optimizeCriticalPath($html)
    {
        // Extract the most important CSS and JavaScript resources
        $criticalResources = [];
        // Get main CSS files - first 2 are typically most critical
        preg_match_all('/<link[^>]*rel=[\'"]stylesheet[\'"][^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $cssMatches);
        if (!empty($cssMatches[1])) {
            $criticalCss = array_slice($cssMatches[1], 0, 2);
            foreach ($criticalCss as $css) {
                if (strpos($css, 'print') === false) {  // Exclude print styles
                    $criticalResources[] = [
                        'type' => 'style',
                        'href' => $css
                    ];
                }
            }
        }
        // Get essential JS - jQuery and require.js are most critical
        preg_match_all('/<script[^>]*src=[\'"]([^\'"]+(?:require\.js|jquery[^\/]*\.js))[\'"][^>]*>/i', $html, $jsMatches);
        if (!empty($jsMatches[1])) {
            foreach ($jsMatches[1] as $js) {
                $criticalResources[] = [
                    'type' => 'script',
                    'href' => $js
                ];
            }
        }
        // Create preload tags
        $preloadTags = '';
        foreach ($criticalResources as $resource) {
            $type = $resource['type'];
            $href = $resource['href'];
            $preloadTags .= '<link rel="preload" href="' . $href . '" as="' . $type . '" crossorigin="anonymous">' . PHP_EOL;
        }
        // Add preload tags to head
        $html = str_replace('</head>', $preloadTags . '</head>', $html);
        return $html;
    }
/**
 * Fix Content Security Policy issues
 *
 * @param string $html
 * @return string
 */
private function fixContentSecurityPolicy($html)
{
    // Create a meta tag to extend the CSP
    $cspMeta = '<meta http-equiv="Content-Security-Policy" content="' .
        'connect-src ' . $this->getCSPConnectSrcDomains() . ' \'self\'; ' .
        'img-src ' . $this->getCSPImgSrcDomains() . ' data: \'self\'; ' .
        'script-src ' . $this->getCSPScriptSrcDomains() . ' \'unsafe-inline\' \'unsafe-eval\' \'self\'; ' .
        'style-src ' . $this->getCSPStyleSrcDomains() . ' \'unsafe-inline\' \'self\'; ' .
        'frame-src ' . $this->getCSPFrameSrcDomains() . ' \'self\'; ' .
        'worker-src blob: \'self\'; ' .
        'child-src blob: \'self\'; ' .
        'font-src * data: \'self\'' .
        '">';
    // Add meta tag to head
    $html = str_replace('<head>', '<head>' . $cspMeta, $html);
    return $html;
}
/**
 * Get domains for connect-src CSP directive
 *
 * @return string
 */
private function getCSPConnectSrcDomains()
{
    return '*.google.com *.google-analytics.com *.analytics.google.com *.googletagmanager.com ' .
           '*.doubleclick.net *.facebook.com *.facebook.net *.fbcdn.net connect.facebook.net ' . 
           '*.googleapis.com *.gstatic.com *.ccm.collect *.nr-data.net *.newrelic.com';
}
/**
 * Get domains for img-src CSP directive
 *
 * @return string
 */
private function getCSPImgSrcDomains()
{
    return '*.google.com *.google-analytics.com *.googletagmanager.com *.google.com.eg ' .
           '*.googleapis.com *.gstatic.com *.doubleclick.net *.google.com ' .
           '*.facebook.com *.facebook.net *.fbcdn.net';
}
/**
 * Get domains for script-src CSP directive
 * 
 * @return string
 */
private function getCSPScriptSrcDomains()
{
    return '*.google.com *.google-analytics.com *.googletagmanager.com *.googleapis.com ' .
           '*.gstatic.com *.doubleclick.net *.facebook.com *.facebook.net connect.facebook.net ' .
           '*.fbcdn.net *.tabby.ai *.jsdelivr.net';
}
/**
 * Get domains for style-src CSP directive
 *
 * @return string
 */
private function getCSPStyleSrcDomains()
{
    return '*.googleapis.com *.gstatic.com *.jsdelivr.net';
}
/**
 * Add error handling for scripts
 *
 * @param string $html
 * @return string
 */
private function addScriptErrorHandling($html)
{
    $errorHandlingScript = '
    <script>
    // Fix common JavaScript errors
    (function() {
        // Create a safer querySelector/querySelectorAll that doesn\'t throw when element not found
        var originalQuerySelector = Document.prototype.querySelector;
        var originalQuerySelectorAll = Document.prototype.querySelectorAll;
        Document.prototype.querySelector = function(selector) {
            try {
                return originalQuerySelector.call(this, selector);
            } catch(e) {
                console.warn("Error in querySelector for: " + selector);
                return null;
            }
        };
        Document.prototype.querySelectorAll = function(selector) {
            try {
                return originalQuerySelectorAll.call(this, selector);
            } catch(e) {
                console.warn("Error in querySelectorAll for: " + selector);
                return [];
            }
        };
        // Fix addEventListener on null elements
        var originalAddEventListener = EventTarget.prototype.addEventListener;
        EventTarget.prototype.addEventListener = function(type, listener, options) {
            if (this === null || this === undefined) {
                console.warn("Cannot add event listener to null/undefined element");
                return;
            }
            return originalAddEventListener.call(this, type, listener, options);
        };
        // Polyfill for missing functions that might cause errors
        window.fbq = window.fbq || function() { 
            console.log("Facebook pixel not loaded yet"); 
        };
        // Fix common tracking script errors
        window.ga = window.ga || function() {};
        window.gtag = window.gtag || function() {};
        window._fbq = window._fbq || function() {};
    })();
    </script>
    ';
    // Add script to head (before other scripts)
    $html = str_replace('<head>', '<head>' . $errorHandlingScript, $html);
    return $html;
}
/**
 * Get domains for frame-src CSP directive
 *
 * @return string
 */
private function getCSPFrameSrcDomains()
{
    return '*.doubleclick.net *.google.com *.facebook.com *.facebook.net';
}
    /**
     * Fix layout shift (CLS) issues
     *
     * @param string $html
     * @return string
     */
    private function fixLayoutShift($html)
    {
        // Add CSS to fix layout shift issues
        $clsFixStyles = '
        <style>
            /* Fix CLS for rows and columns */
            [data-content-type="row"][data-appearance="contained"][data-element="main"] {
                overflow: hidden;
                box-sizing: border-box;
                contain: layout style;
            }
            /* Set aspect ratios for images that may cause shifts */
            .image-cls-fix, .pagebuilder-mobile-hidden {
                aspect-ratio: 16/9; /* Default aspect ratio */
                max-width: 100%;
                height: auto;
                contain: strict;
            }
            /* Fix for banners */
            .porto-ibanner a {
                position: relative;
                display: block;
                overflow: hidden;
                contain: layout;
            }
            /* Fix for specific images */
            img[alt="WhatsApp Chat"] {
                width: 60px !important;
                height: 60px !important;
            }
        </style>
        ';
        // Add CSS to head
        $html = str_replace('</head>', $clsFixStyles . '</head>', $html);
        return $html;
    }
}