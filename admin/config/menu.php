<?php
/**
 * Admin Sidebar Menu Configuration
 *
 * Each top-level item can have:
 *   - label:    Display text
 *   - icon:     Font Awesome class (without 'fas fa-')
 *   - url:      Direct link (for non-collapsible items)
 *   - pages:    Array of PHP filenames that activate this item
 *   - children: Array of sub-menu items (makes it collapsible)
 *   - divider:  If true, renders a visual separator before this item
 *
 * Each child item can have:
 *   - label:    Display text
 *   - icon:     Font Awesome class
 *   - url:      Link href
 *   - pages:    Array of filenames; active when current page matches
 *   - params:   Array of GET params that must also match for active state
 */
return [
    // ── Dashboard ─────────────────────────────────────────────
    [
        'label' => 'Dashboard',
        'icon'  => 'fa-tachometer-alt',
        'url'   => 'index.php',
        'pages' => ['index.php'],
    ],

    // ── Analytics ─────────────────────────────────────────────
    [
        'label' => 'Analytics',
        'icon'  => 'fa-chart-line',
        'url'   => 'analytics.php',
        'pages' => ['analytics.php', 'analytics_product.php'],
    ],

    // ── Products ──────────────────────────────────────────────
    [
        'label'    => 'Products',
        'icon'     => 'fa-box',
        'pages'    => ['manage_products.php', 'manage_categories.php'],
        'children' => [
            [
                'label'  => 'All Products',
                'icon'   => 'fa-list',
                'url'    => 'manage_products.php?action=list',
                'pages'  => ['manage_products.php'],
                'params' => ['action' => ['list', null, '']],
            ],
            [
                'label'  => 'Add Product',
                'icon'   => 'fa-plus',
                'url'    => 'manage_products.php?action=add',
                'pages'  => ['manage_products.php'],
                'params' => ['action' => ['add']],
            ],
            [
                'label' => 'Categories',
                'icon'  => 'fa-tags',
                'url'   => 'manage_categories.php',
                'pages' => ['manage_categories.php'],
            ],
        ],
    ],

    // ── Orders ────────────────────────────────────────────────
    [
        'label'    => 'Orders',
        'icon'     => 'fa-shopping-cart',
        'pages'    => ['manage_orders.php', 'manage_order_tracking.php', 'manage_cod_blacklist.php', 'manage_couriers.php', 'courier_logs.php'],
        'children' => [
            [
                'label'  => 'All Orders',
                'icon'   => 'fa-list-ol',
                'url'    => 'manage_orders.php?status=all',
                'pages'  => ['manage_orders.php'],
                'params' => ['status' => ['all', null, '']],
            ],
            [
                'label'  => 'Pending',
                'icon'   => 'fa-clock',
                'url'    => 'manage_orders.php?status=pending',
                'pages'  => ['manage_orders.php'],
                'params' => ['status' => ['pending']],
            ],
            [
                'label'  => 'Processing',
                'icon'   => 'fa-cog',
                'url'    => 'manage_orders.php?status=processing',
                'pages'  => ['manage_orders.php'],
                'params' => ['status' => ['processing']],
            ],
            [
                'label'  => 'Partially Shipped',
                'icon'   => 'fa-box-open',
                'url'    => 'manage_orders.php?status=partially_shipped',
                'pages'  => ['manage_orders.php'],
                'params' => ['status' => ['partially_shipped']],
            ],
            [
                'label'  => 'Shipped',
                'icon'   => 'fa-truck',
                'url'    => 'manage_orders.php?status=shipped',
                'pages'  => ['manage_orders.php'],
                'params' => ['status' => ['shipped']],
            ],
            [
                'label'  => 'Delivered',
                'icon'   => 'fa-clipboard-check',
                'url'    => 'manage_orders.php?status=delivered',
                'pages'  => ['manage_orders.php'],
                'params' => ['status' => ['delivered']],
            ],
            [
                'label'  => 'Completed',
                'icon'   => 'fa-check-circle',
                'url'    => 'manage_orders.php?status=completed',
                'pages'  => ['manage_orders.php'],
                'params' => ['status' => ['completed']],
            ],
            [
                'label'  => 'Cancelled',
                'icon'   => 'fa-times-circle',
                'url'    => 'manage_orders.php?status=cancelled',
                'pages'  => ['manage_orders.php'],
                'params' => ['status' => ['cancelled']],
            ],
            [
                'label' => 'Order Tracking',
                'icon'  => 'fa-shipping-fast',
                'url'   => 'manage_order_tracking.php',
                'pages' => ['manage_order_tracking.php'],
            ],
            [
                'label' => 'Courier Integrations',
                'icon'  => 'fa-truck-moving',
                'url'   => 'manage_couriers.php',
                'pages' => ['manage_couriers.php'],
            ],
            [
                'label' => 'Courier Sync Logs',
                'icon'  => 'fa-history',
                'url'   => 'courier_logs.php',
                'pages' => ['courier_logs.php'],
            ],
            [
                'label' => 'COD Blacklist',
                'icon'  => 'fa-ban',
                'url'   => 'manage_cod_blacklist.php',
                'pages' => ['manage_cod_blacklist.php'],
            ],
        ],
    ],

    // ── Invoices ──────────────────────────────────────────────
    [
        'label' => 'Invoices',
        'icon'  => 'fa-file-invoice',
        'url'   => 'manage_invoices.php',
        'pages' => ['manage_invoices.php', 'invoice_view.php'],
    ],

    // ── Customers ─────────────────────────────────────────────
    [
        'label'    => 'Customers',
        'icon'     => 'fa-users',
        'pages'    => ['manage_users.php'],
        'children' => [
            [
                'label' => 'All Customers',
                'icon'  => 'fa-user-friends',
                'url'   => 'manage_users.php',
                'pages' => ['manage_users.php'],
            ],
        ],
    ],

    // ── Media / Gallery ──────────────────────────────────────
    [
        'label'    => 'Media / Gallery',
        'icon'     => 'fa-photo-video',
        'pages'    => ['manage_media.php'],
        'children' => [
            [
                'label'  => 'All Media',
                'icon'   => 'fa-th',
                'url'    => 'manage_media.php?type=all',
                'pages'  => ['manage_media.php'],
                'params' => ['type' => ['all', null, '']],
            ],
            [
                'label'  => 'Images',
                'icon'   => 'fa-image',
                'url'    => 'manage_media.php?type=image',
                'pages'  => ['manage_media.php'],
                'params' => ['type' => ['image']],
            ],
            [
                'label'  => 'Videos',
                'icon'   => 'fa-video',
                'url'    => 'manage_media.php?type=video',
                'pages'  => ['manage_media.php'],
                'params' => ['type' => ['video']],
            ],
        ],
    ],

    // ── Frontend Content ──────────────────────────────────────
    [
        'label'    => 'Frontend Content',
        'icon'     => 'fa-desktop',
        'pages'    => [
            'manage_homepage.php', 'hero-slider-settings.php', 'manage-slides.php',
            'manage_homepage_features.php', 'manage_banners.php',
            'manage_pages.php', 'manage_about.php',
            'manage_site_content.php', 'manage_product_share.php'
        ],
        'children' => [
            [
                'label' => 'Homepage Sections',
                'icon'  => 'fa-th-large',
                'url'   => 'manage_homepage.php',
                'pages' => ['manage_homepage.php'],
            ],
            [
                'label' => 'Hero Slider',
                'icon'  => 'fa-layer-group',
                'url'   => 'hero-slider-settings.php',
                'pages' => ['hero-slider-settings.php', 'manage-slides.php'],
            ],
            [
                'label' => 'Feature Icons',
                'icon'  => 'fa-star',
                'url'   => 'manage_homepage_features.php',
                'pages' => ['manage_homepage_features.php'],
            ],
            [
                'label' => 'Homepage Banners',
                'icon'  => 'fa-images',
                'url'   => 'manage_banners.php',
                'pages' => ['manage_banners.php'],
            ],
            [
                'label' => 'Static Pages',
                'icon'  => 'fa-file-alt',
                'url'   => 'manage_pages.php',
                'pages' => ['manage_pages.php'],
            ],
            [
                'label' => 'About Us Page',
                'icon'  => 'fa-info-circle',
                'url'   => 'manage_about.php',
                'pages' => ['manage_about.php'],
            ],
            [
                'label' => 'Contact Us Page',
                'icon'  => 'fa-envelope-open-text',
                'url'   => 'manage_contact.php',
                'pages' => ['manage_contact.php'],
            ],
            [
                'label' => 'Footer Settings',
                'icon'  => 'fa-level-down-alt',
                'url'   => 'manage_site_content.php',
                'pages' => ['manage_site_content.php'],
            ],
            [
                'label' => 'Product Share',
                'icon'  => 'fa-share-alt',
                'url'   => 'manage_product_share.php',
                'pages' => ['manage_product_share.php'],
            ],
            [
                'label' => 'Testimonials',
                'icon'  => 'fa-comment-dots',
                'url'   => 'manage_testimonials.php',
                'pages' => ['manage_testimonials.php'],
            ],
        ],
    ],

    // ── Marketing ─────────────────────────────────────────────
    [
        'label'    => 'Marketing',
        'icon'     => 'fa-bullhorn',
        'pages'    => ['manage_ai_chatbot.php', 'manage_google_merchant.php', 'manage_whatsapp_settings.php', 'view_email_logs.php', 'manage_email_templates.php', 'manage_abandoned_carts.php'],
        'children' => [
            [
                'label' => 'AI ChatBot Assistant',
                'icon'  => 'fa-robot',
                'url'   => 'manage_ai_chatbot.php',
                'pages' => ['manage_ai_chatbot.php'],
            ],
            [
                'label' => 'Cart Recovery',
                'icon'  => 'fa-cart-arrow-down',
                'url'   => 'manage_abandoned_carts.php',
                'pages' => ['manage_abandoned_carts.php'],
            ],
            [
                'label' => 'Google Merchant',
                'icon'  => 'fab fa-google',
                'url'   => 'manage_google_merchant.php',
                'pages' => ['manage_google_merchant.php'],
            ],
            [
                'label' => 'WhatsApp Notifs',
                'icon'  => 'fab fa-whatsapp',
                'url'   => 'manage_whatsapp_settings.php',
                'pages' => ['manage_whatsapp_settings.php'],
            ],
            [
                'label' => 'Email Templates',
                'icon'  => 'fa-envelope-open-text',
                'url'   => 'manage_email_templates.php',
                'pages' => ['manage_email_templates.php'],
            ],
            [
                'label' => 'Email Logs',
                'icon'  => 'fa-envelope',
                'url'   => 'view_email_logs.php',
                'pages' => ['view_email_logs.php'],
            ],
        ],
    ],

    // ── Social Media ─────────────────────────────────────────
    [
        'label'    => 'Social Media',
        'icon'     => 'fa-share-alt',
        'pages'    => [
            'social-media/index.php', 'social-media/accounts.php',
            'social-media/queue.php', 'social-media/bulk-schedule.php',
            'social-media/templates.php', 'social-media/schedules.php',
            'social-media/analytics.php', 'social-media/logs.php',
            'social-media/settings.php',
        ],
        'children' => [
            [
                'label' => 'Dashboard',
                'icon'  => 'fa-tachometer-alt',
                'url'   => 'social-media/index.php',
                'pages' => ['social-media/index.php'],
            ],
            [
                'label' => 'Accounts',
                'icon'  => 'fa-plug',
                'url'   => 'social-media/accounts.php',
                'pages' => ['social-media/accounts.php'],
            ],
            [
                'label' => 'Post Queue',
                'icon'  => 'fa-stream',
                'url'   => 'social-media/queue.php',
                'pages' => ['social-media/queue.php'],
            ],
            [
                'label' => 'Bulk Schedule',
                'icon'  => 'fa-layer-group',
                'url'   => 'social-media/bulk-schedule.php',
                'pages' => ['social-media/bulk-schedule.php'],
            ],
            [
                'label' => 'Templates',
                'icon'  => 'fa-file-alt',
                'url'   => 'social-media/templates.php',
                'pages' => ['social-media/templates.php'],
            ],
            [
                'label' => 'Schedules',
                'icon'  => 'fa-clock',
                'url'   => 'social-media/schedules.php',
                'pages' => ['social-media/schedules.php'],
            ],
            [
                'label' => 'Analytics',
                'icon'  => 'fa-chart-bar',
                'url'   => 'social-media/analytics.php',
                'pages' => ['social-media/analytics.php'],
            ],
            [
                'label' => 'Logs',
                'icon'  => 'fa-clipboard-list',
                'url'   => 'social-media/logs.php',
                'pages' => ['social-media/logs.php'],
            ],
            [
                'label' => 'Settings',
                'icon'  => 'fa-cog',
                'url'   => 'social-media/settings.php',
                'pages' => ['social-media/settings.php'],
            ],
        ],
    ],

    // ── Appearance ────────────────────────────────────────────
    [
        'label'    => 'Appearance',
        'icon'     => 'fa-paint-brush',
        'pages'    => ['manage_theme.php'],
        'children' => [
            [
                'label' => 'Theme Customizer',
                'icon'  => 'fa-palette',
                'url'   => 'manage_theme.php',
                'pages' => ['manage_theme.php'],
            ],
        ],
    ],

    // ── Settings & Configs ────────────────────────────────────
    [
        'label'    => 'Settings &amp; Configs',
        'icon'     => 'fa-cogs',
        'pages'    => ['manage_settings.php', 'system_optimize.php', 'manage_seo.php', 'manage_tracking.php', 'manage_scripts.php'],
        'children' => [
            [
                'label'  => 'Global Properties',
                'icon'   => 'fa-sliders-h',
                'pages'  => ['manage_settings.php'],
                'children' => [
                    [
                        'label'  => 'General Settings',
                        'icon'   => 'fa-cog',
                        'url'    => 'manage_settings.php?tab=general',
                        'pages'  => ['manage_settings.php'],
                        'params' => ['tab' => ['general', null, '']],
                    ],
                    [
                        'label'  => 'Payment Gateways',
                        'icon'   => 'fa-wallet',
                        'url'    => 'manage_settings.php?tab=payment',
                        'pages'  => ['manage_settings.php'],
                        'params' => ['tab' => ['payment']],
                    ],
                    [
                        'label'  => 'Shipping Logic',
                        'icon'   => 'fa-truck',
                        'url'    => 'manage_settings.php?tab=shipping',
                        'pages'  => ['manage_settings.php'],
                        'params' => ['tab' => ['shipping']],
                    ],
                    [
                        'label'  => 'Build User Menus',
                        'icon'   => 'fa-sitemap',
                        'url'    => 'manage_settings.php?tab=menus',
                        'pages'  => ['manage_settings.php'],
                        'params' => ['tab' => ['menus']],
                    ]
                ]
            ],
            [
                'label' => 'Refresh &amp; Optimize',
                'icon'  => 'fa-broom',
                'url'   => 'system_optimize.php',
                'pages' => ['system_optimize.php'],
            ],
            [
                'label' => 'WEBSEO Module',
                'icon'  => 'fa-search',
                'url'   => 'manage_seo.php',
                'pages' => ['manage_seo.php'],
            ],
            [
                'label' => 'Order Tracking Config',
                'icon'  => 'fa-map-marker-alt',
                'url'   => 'manage_tracking.php',
                'pages' => ['manage_tracking.php'],
            ],
            [
                'label' => 'Headers &amp; Footers',
                'icon'  => 'fa-code',
                'url'   => 'manage_scripts.php',
                'pages' => ['manage_scripts.php'],
            ],
        ],
    ],
    // ── Divider ───────────────────────────────────────────────
    ['divider' => true],
];
