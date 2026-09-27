<?php
require_once 'SeoRepository.php';

class SeoService {
    private $repo;
    private $globalSettings;

    public function __construct($conn) {
        $this->repo = new SeoRepository($conn);
        $this->globalSettings = $this->repo->getGlobalSettings();
    }

    /**
     * Get base canonical URL prefix.
     */
    private function getBaseUrl() {
        if (defined('SITE_URL') && strpos(SITE_URL, 'http') === 0) {
            $parsed = parse_url(SITE_URL);
            $host = $parsed['host'] ?? '';
            if (!empty($host) && ($host === 'localhost' || $host === '127.0.0.1')) {
                if (!empty($this->globalSettings['site_domain'])) {
                    $domain = rtrim($this->globalSettings['site_domain'], '/');
                    return (strpos($domain, 'http') === 0) ? $domain : ('https://' . $domain);
                }
                return 'https://www.sagarstarters.com';
            }
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : ($parsed['scheme'] ?? 'https');
            $path = rtrim($parsed['path'] ?? '', '/');
            if (strpos($path, ':') !== false) {
                $path = '';
            }
            return $scheme . '://' . $host . $path;
        }
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'www.sagarstarters.com';
        if ($host === 'localhost' || $host === '127.0.0.1') {
            return 'https://www.sagarstarters.com';
        }
        return $scheme . '://' . $host;
    }

    /**
     * Get merged SEO data for a page.
     */
    public function getPageSeo($type, $id = 0, $fallbackData = []) {
        $metadata = $this->repo->getMetadata($type, $id);
        $baseUrl = $this->getBaseUrl();
        $autoCanonical = '';
        $autoRobots = $this->globalSettings['robots_default'] ?? 'index, follow';

        // 1. Fallback to table-specific metas & determine automatic canonical URLs
        if ($type === 'product' && $id > 0) {
            $res = $this->repo->getConnection()->query("SELECT name, slug, meta_description, short_description, description, image, brand, sku FROM products WHERE id=$id");
            if ($res && $res->num_rows > 0) {
                $p = $res->fetch_assoc();
                if (empty($fallbackData['title'])) $fallbackData['title'] = $p['name'];
                if (empty($fallbackData['description'])) {
                    $fallbackData['description'] = !empty($p['meta_description']) 
                        ? $p['meta_description'] 
                        : (!empty($p['short_description']) 
                            ? substr(strip_tags($p['short_description']), 0, 160) 
                            : substr(strip_tags($p['description']), 0, 160));
                }
                if (empty($fallbackData['image'])) {
                    $fallbackData['image'] = function_exists('resolve_product_image_url') ? resolve_product_image_url($p['image'] ?? '', $this->repo->getConnection(), $id) : ($p['image'] ?? '');
                }
                $autoCanonical = $baseUrl . '/product/' . (!empty($p['slug']) ? $p['slug'] : $id);
            }
        } elseif ($type === 'category' && $id > 0) {
            $res = $this->repo->getConnection()->query("SELECT name, slug, image FROM categories WHERE id=$id");
            if ($res && $res->num_rows > 0) {
                $c = $res->fetch_assoc();
                if (empty($fallbackData['title'])) $fallbackData['title'] = $c['name'];
                if (empty($fallbackData['description'])) {
                    $fallbackData['description'] = "Shop genuine " . $c['name'] . " at Sagar Starter's. High-performance motor protection, durable contactors, and reliable pump controls with fast pan-India shipping.";
                }
                if (empty($fallbackData['image'])) $fallbackData['image'] = $c['image'];
                $autoCanonical = $baseUrl . '/category/' . (!empty($c['slug']) ? $c['slug'] : $id);
            }
        } elseif ($type === 'page' && $id > 0) {
            $res = $this->repo->getConnection()->query("SELECT title, slug, meta_title, meta_description FROM pages WHERE id=$id");
            if ($res && $res->num_rows > 0) {
                $pg = $res->fetch_assoc();
                if (empty($fallbackData['title'])) $fallbackData['title'] = !empty($pg['meta_title']) ? $pg['meta_title'] : $pg['title'];
                if (empty($fallbackData['description'])) $fallbackData['description'] = $pg['meta_description'];
                $autoCanonical = $baseUrl . '/page/' . (!empty($pg['slug']) ? $pg['slug'] : $id);
            }
        } elseif ($type === 'shop') {
            $page_num = isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 1 ? (int)$_GET['page'] : 1;
            $autoCanonical = $baseUrl . '/shop.php' . ($page_num > 1 ? '?page=' . $page_num : '');
            if (!empty($_GET['search'])) {
                $autoRobots = 'noindex, follow';
                $search_query = trim($_GET['search']);
                if (empty($fallbackData['title'])) $fallbackData['title'] = 'Search results for "' . htmlspecialchars($search_query) . '"';
                if (empty($fallbackData['description'])) $fallbackData['description'] = 'Search results for ' . htmlspecialchars($search_query) . ' at Sagar Starter\'s online store.';
            } elseif (!empty($_GET['phase']) || !empty($_GET['hp']) || !empty($_GET['app']) || !empty($_GET['sort'])) {
                // Parameter-filtered URLs should not dilute primary catalog rankings
                $autoRobots = 'noindex, follow';
            }
        } elseif ($type === 'home') {
            $autoCanonical = $baseUrl . '/';
        } elseif ($type === 'about') {
            $autoCanonical = $baseUrl . '/about.php';
            if (empty($fallbackData['title'])) $fallbackData['title'] = 'About Us — Motor Starter & Panel Engineering';
            if (empty($fallbackData['description'])) $fallbackData['description'] = "Discover Sagar Starter's — leading Indian manufacturer of heavy-duty submersible pump starters, star-delta control panels, and agricultural motor protection devices.";
        } elseif ($type === 'contact') {
            $autoCanonical = $baseUrl . '/contact.php';
            if (empty($fallbackData['title'])) $fallbackData['title'] = 'Contact Us — Customer Support & Dealer Enquiries';
            if (empty($fallbackData['description'])) $fallbackData['description'] = "Get in touch with Sagar Starter's for motor starter sales, technical support, wholesale dealer inquiries, and pan-India order delivery.";
        } elseif ($type === 'catalogue') {
            $autoCanonical = $baseUrl . '/catalogue.php';
            if (empty($fallbackData['title'])) $fallbackData['title'] = 'Official Product Catalogue (PDF) — Motor Starters & Panels';
            if (empty($fallbackData['description'])) $fallbackData['description'] = "Download and view the official product catalogue of Sagar Starter's. Full technical specifications for single phase, 3-phase DOL, and star delta starters.";
        } elseif (in_array($type, ['cart', 'checkout', 'track_order', 'auth', 'account', '404'])) {
            $autoRobots = 'noindex, nofollow';
            if ($type === 'cart') {
                $autoCanonical = $baseUrl . '/cart.php';
                if (empty($fallbackData['title'])) $fallbackData['title'] = 'Shopping Cart';
            } elseif ($type === 'checkout') {
                $autoCanonical = $baseUrl . '/checkout.php';
                if (empty($fallbackData['title'])) $fallbackData['title'] = 'Secure Checkout';
            } elseif ($type === 'track_order') {
                $autoCanonical = $baseUrl . '/track_order.php';
                if (empty($fallbackData['title'])) $fallbackData['title'] = 'Track Order Status';
            } elseif ($type === 'auth') {
                $autoCanonical = $baseUrl . '/user/login.php';
                if (empty($fallbackData['title'])) $fallbackData['title'] = 'Customer Login & Registration';
            } elseif ($type === 'account') {
                $autoCanonical = $baseUrl . '/user/orders.php';
                if (empty($fallbackData['title'])) $fallbackData['title'] = 'My Account & Orders';
            } elseif ($type === '404') {
                if (empty($fallbackData['title'])) $fallbackData['title'] = 'Page Not Found — 404';
            }
        }

        $seo = [
            'title' => !empty($metadata['meta_title']) ? $metadata['meta_title'] : (!empty($fallbackData['title']) ? $fallbackData['title'] : ($this->globalSettings['default_meta_title'] ?? '')),
            'description' => !empty($metadata['meta_description']) ? $metadata['meta_description'] : (!empty($fallbackData['description']) ? $fallbackData['description'] : ($this->globalSettings['default_meta_description'] ?? '')),
            'keywords' => !empty($metadata['meta_keywords']) ? $metadata['meta_keywords'] : ($this->globalSettings['default_meta_keywords'] ?? ''),
            'og_title' => !empty($metadata['og_title']) ? $metadata['og_title'] : (!empty($metadata['meta_title']) ? $metadata['meta_title'] : (!empty($fallbackData['title']) ? $fallbackData['title'] : '')),
            'og_description' => !empty($metadata['og_description']) ? $metadata['og_description'] : (!empty($metadata['meta_description']) ? $metadata['meta_description'] : (!empty($fallbackData['description']) ? $fallbackData['description'] : '')),
            'og_image' => !empty($metadata['og_image']) ? $metadata['og_image'] : (!empty($fallbackData['image']) ? $fallbackData['image'] : ($this->globalSettings['og_default_image'] ?? '')),
            'og_type' => ($type === 'product') ? 'product' : 'website',
            'twitter_title' => !empty($metadata['twitter_title']) ? $metadata['twitter_title'] : (!empty($metadata['meta_title']) ? $metadata['meta_title'] : (!empty($fallbackData['title']) ? $fallbackData['title'] : '')),
            'twitter_description' => !empty($metadata['twitter_description']) ? $metadata['twitter_description'] : (!empty($metadata['meta_description']) ? $metadata['meta_description'] : (!empty($fallbackData['description']) ? $fallbackData['description'] : '')),
            'twitter_image' => !empty($metadata['twitter_image']) ? $metadata['twitter_image'] : (!empty($fallbackData['image']) ? $fallbackData['image'] : (!empty($metadata['og_image']) ? $metadata['og_image'] : ($this->globalSettings['og_default_image'] ?? ''))),
            'canonical' => !empty($metadata['canonical_url']) ? $metadata['canonical_url'] : $autoCanonical,
            'robots' => !empty($metadata['robots_tag']) ? $metadata['robots_tag'] : $autoRobots,
            'schema' => $metadata['schema_markup'] ?? '',
            'site_name' => $this->globalSettings['site_name'] ?? "Sagar Starter's",
            'favicon' => $this->globalSettings['site_favicon'] ?? ''
        ];

        // Apply sitename and separator to title ONLY if not already present (prevents duplicate suffix)
        $siteName = $this->globalSettings['site_name'] ?? "Sagar Starter's";
        $separator = $this->globalSettings['site_separator'] ?? '|';
        
        if ($type !== 'home' && !empty($siteName)) {
            if (stripos($seo['title'], $siteName) === false) {
                $seo['title'] = $seo['title'] . " $separator " . $siteName;
            }
        }

        return $seo;
    }

    /**
     * Generate JSON-LD Product Schema adhering to Google Rich Snippet standards.
     */
    public function generateProductSchema($product) {
        $baseUrl = $this->getBaseUrl();
        $assetsUrl = defined('ASSETS_URL') ? ASSETS_URL : '/assets';
        
        if (strpos($assetsUrl, 'http') !== 0) {
            $assetsUrl = $baseUrl . (strpos($assetsUrl, '/') === 0 ? '' : '/') . $assetsUrl;
        }

        $imagePath = "";
        if (!empty($product['image'])) {
            $imagePath = function_exists('resolve_product_image_url') 
                ? resolve_product_image_url($product['image'], $this->repo->getConnection(), (int)($product['id'] ?? 0))
                : $assetsUrl . "/images/" . $product['image'];
        }
        if (empty($imagePath)) {
            $imagePath = $baseUrl . "/assets/images/placeholder.svg";
        }

        $prodSlug = !empty($product['slug']) ? $product['slug'] : ($product['id'] ?? 'item');
        $prodUrl = $baseUrl . '/product/' . $prodSlug;
        $sku = !empty($product['sku']) ? $product['sku'] : ("SS-" . ($product['id'] ?? '0'));
        $brandName = !empty($product['brand']) ? $product['brand'] : "Sagar Starters";
        $currentPrice = ($product['sale_price'] > 0) ? floatval($product['sale_price']) : floatval($product['price'] ?? 0);
        $stock = isset($product['stock']) ? (int)$product['stock'] : 10;
        $validUntil = (date('Y') + 1) . '-12-31';

        $schema = [
            "@context" => "https://schema.org/",
            "@type" => "Product",
            "name" => $product['name'],
            "url" => $prodUrl,
            "image" => [$imagePath],
            "description" => !empty($product['meta_description']) 
                ? $product['meta_description'] 
                : (!empty($product['short_description']) 
                    ? strip_tags($product['short_description']) 
                    : strip_tags($product['description'] ?? $product['name'])),
            "sku" => $sku,
            "mpn" => !empty($product['mpn']) ? $product['mpn'] : $sku,
            "brand" => [
                "@type" => "Brand",
                "name" => $brandName
            ],
            "offers" => [
                "@type" => "Offer",
                "url" => $prodUrl,
                "priceCurrency" => "INR",
                "price" => number_format($currentPrice, 2, '.', ''),
                "priceValidUntil" => $validUntil,
                "itemCondition" => "https://schema.org/NewCondition",
                "availability" => ($stock > 0) ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
                "seller" => [
                    "@type" => "Organization",
                    "name" => "Sagar Starter's"
                ],
                "hasMerchantReturnPolicy" => [
                    "@type" => "MerchantReturnPolicy",
                    "applicableCountry" => "IN",
                    "returnPolicyCategory" => "https://schema.org/MerchantReturnFiniteReturnWindow",
                    "merchantReturnDays" => 7,
                    "returnMethod" => "https://schema.org/ReturnByMail",
                    "returnFees" => "https://schema.org/FreeReturn"
                ],
                "shippingDetails" => [
                    "@type" => "OfferShippingDetails",
                    "shippingRate" => [
                        "@type" => "MonetaryAmount",
                        "value" => "0.00",
                        "currency" => "INR"
                    ],
                    "shippingDestination" => [
                        "@type" => "DefinedRegion",
                        "addressCountry" => "IN"
                    ]
                ]
            ]
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate JSON-LD Organization Schema.
     */
    public function generateOrganizationSchema() {
        $baseUrl = $this->getBaseUrl();
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "Organization",
            "name" => "Sagar Starter's",
            "url" => $baseUrl . "/",
            "logo" => $baseUrl . "/assets/images/logo.jpg",
            "description" => "Manufacturer and supplier of heavy-duty agricultural & industrial motor starters, submersible pump panels, star-delta control boxes, and switchgear components.",
            "telephone" => "+918573934013",
            "email" => "sagarstarters@gmail.com",
            "address" => [
                "@type" => "PostalAddress",
                "streetAddress" => "Alipur Madra",
                "addressLocality" => "Jakhanian, Ghazipur",
                "addressRegion" => "Uttar Pradesh",
                "postalCode" => "275203",
                "addressCountry" => "IN"
            ],
            "sameAs" => [
                "https://www.facebook.com/share/1HSqEPGVYE/",
                "https://www.instagram.com/sagarstarter?igsh=dngwdnlmbHhncW9q",
                "https://www.linkedin.com/in/sagar-starter-s-b372a7238",
                "https://x.com/starter_s20880"
            ],
            "contactPoint" => [
                "@type" => "ContactPoint",
                "telephone" => "+918573934013",
                "contactType" => "customer service",
                "areaServed" => "IN",
                "availableLanguage" => ["en", "hi"]
            ]
        ];
        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate JSON-LD WebSite Schema with Sitelinks Searchbox.
     */
    public function generateWebSiteSchema() {
        $baseUrl = $this->getBaseUrl();
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "WebSite",
            "name" => "Sagar Starter's",
            "url" => $baseUrl . "/",
            "potentialAction" => [
                "@type" => "SearchAction",
                "target" => [
                    "@type" => "EntryPoint",
                    "urlTemplate" => $baseUrl . "/shop.php?search={search_term_string}"
                ],
                "query-input" => "required name=search_term_string"
            ]
        ];
        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate JSON-LD LocalBusiness Schema.
     */
    public function generateLocalBusinessSchema() {
        $baseUrl = $this->getBaseUrl();
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "LocalBusiness",
            "name" => "Sagar Starter's",
            "image" => $baseUrl . "/assets/images/logo.jpg",
            "url" => $baseUrl . "/",
            "telephone" => "+918573934013",
            "priceRange" => "₹₹",
            "address" => [
                "@type" => "PostalAddress",
                "streetAddress" => "Alipur Madra",
                "addressLocality" => "Jakhanian, Ghazipur",
                "addressRegion" => "Uttar Pradesh",
                "postalCode" => "275203",
                "addressCountry" => "IN"
            ],
            "geo" => [
                "@type" => "GeoCoordinates",
                "latitude" => "25.745028",
                "longitude" => "83.388050"
            ],
            "openingHoursSpecification" => [
                [
                    "@type" => "OpeningHoursSpecification",
                    "dayOfWeek" => ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
                    "opens" => "09:00",
                    "closes" => "18:00"
                ]
            ]
        ];
        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate JSON-LD BreadcrumbList Schema.
     */
    public function generateBreadcrumbSchema($items) {
        $itemList = [];
        $pos = 1;
        foreach ($items as $item) {
            $itemList[] = [
                "@type" => "ListItem",
                "position" => $pos++,
                "name" => $item['name'],
                "item" => $item['url']
            ];
        }

        $schema = [
            "@context" => "https://schema.org",
            "@type" => "BreadcrumbList",
            "itemListElement" => $itemList
        ];
        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

