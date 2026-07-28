<?php
/**
 * WAPI SaaS Platform - Landing Page
 * Fully dynamic - all content loaded from database
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

$db = Database::getInstance();
$settings = new Settings();

// Load dynamic content
$features = $db->fetchAll("SELECT * FROM features WHERE is_active = 1 ORDER BY sort_order ASC");
$plans = $db->fetchAll("SELECT p.*, GROUP_CONCAT(pf.feature_text, '|||', pf.is_included ORDER BY pf.sort_order SEPARATOR ';;;') as features_list FROM plans p LEFT JOIN plan_features pf ON p.id = pf.plan_id WHERE p.is_active = 1 GROUP BY p.id ORDER BY p.sort_order ASC");
$testimonials = $db->fetchAll("SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order ASC");
$faqs = $db->fetchAll("SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order ASC");

$pageTitle = 'Home';
$pageDescription = $settings->get('site_description', '');

include __DIR__ . '/includes/header.php';
?>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 fade-in">
                    <h1 class="hero-title">
                        <?= e($settings->get('hero_title', 'Supercharge Your Business with WhatsApp API')); ?>
                    </h1>
                    <p class="hero-subtitle">
                        <?= e($settings->get('hero_subtitle', 'Send bulk messages, manage contacts and grow your business with our powerful WhatsApp API.')); ?>
                    </p>
                    <div class="d-flex gap-3 flex-wrap justify-content-center justify-content-lg-start <?php if (empty($settings->get('hero_button_text'))): ?>d-none<?php endif; ?>">
                        <a href="<?= baseUrl($settings->get('hero_button_link', 'auth/register.php')); ?>" class="btn btn-primary btn-lg">
                            <?= e($settings->get('hero_button_text', 'Get Started Free')); ?>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="#demo" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-play-circle"></i> Watch Demo
                        </a>
                    </div>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-value">10K+</div>
                            <div class="hero-stat-label">Active Users</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-value">50M+</div>
                            <div class="hero-stat-label">Messages Sent</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-value">99.9%</div>
                            <div class="hero-stat-label">Uptime</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="hero-image text-center fade-in">
                        <!-- WhatsApp Chat Preview as Hero Image -->
                        <div class="whatsapp-phone mx-auto" style="max-width: 340px;">
                            <div class="whatsapp-screen">
                                <div class="wa-header">
                                    <div class="wa-avatar"><i class="bi bi-building"></i></div>
                                    <div>
                                        <div class="wa-name"><?= e($settings->get('site_name', 'WAPI')); ?> Business</div>
                                        <div class="wa-status">online</div>
                                    </div>
                                </div>
                                <div class="wa-messages" id="heroChatMessages">
                                    <div class="wa-bubble received">
                                        <div>👋 Welcome! How can we help you today?</div>
                                        <div class="wa-time">10:30 AM</div>
                                    </div>
                                    <div class="wa-bubble sent">
                                        <div>I want to integrate WhatsApp API</div>
                                        <div class="wa-time">10:31 AM ✓✓</div>
                                    </div>
                                    <div class="wa-bubble received">
                                        <div>Great choice! 🚀 With our API you can:<br>✅ Send bulk messages<br>✅ Manage contacts<br>✅ Track analytics</div>
                                        <div class="wa-time">10:31 AM</div>
                                    </div>
                                    <div class="wa-bubble sent">
                                        <div>That sounds amazing! Sign me up 🎉</div>
                                        <div class="wa-time">10:32 AM ✓✓</div>
                                    </div>
                                </div>
                                <div class="wa-input-bar">
                                    <input class="wa-input" placeholder="Type a message" readonly>
                                    <button class="wa-send-btn"><i class="bi bi-send-fill"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title"><?= e($settings->get('features_title', 'Everything You Need to Scale')); ?></h2>
                <p class="section-subtitle"><?= e($settings->get('features_subtitle', 'Our platform provides all the tools you need.')); ?></p>
            </div>
            <div class="row g-4">
                <?php foreach ($features as $feature): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card hover-lift">
                        <div class="feature-icon">
                            <i class="bi <?= e($feature['icon']); ?>"></i>
                        </div>
                        <h5><?= e($feature['title']); ?></h5>
                        <p><?= e($feature['description']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- WhatsApp Demo Section -->
    <section class="demo-section" id="demo">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="section-title mb-3">See It In Action</h2>
                    <p class="text-secondary mb-4" style="font-size: 1.125rem; line-height: 1.7;">
                        Experience how easy it is to send WhatsApp messages through our platform. 
                        Try our live demo and see the power of automated messaging.
                    </p>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="feature-icon" style="min-width: 48px; height: 48px; font-size: 1.25rem;">
                                <i class="bi bi-lightning-charge-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Instant Delivery</h6>
                                <p class="text-secondary mb-0" style="font-size: 0.9375rem;">Messages are delivered in milliseconds through Meta's official API.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <div class="feature-icon" style="min-width: 48px; height: 48px; font-size: 1.25rem;">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">100% Reliable</h6>
                                <p class="text-secondary mb-0" style="font-size: 0.9375rem;">Built on Meta's official WhatsApp Cloud API for maximum reliability.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <div class="feature-icon" style="min-width: 48px; height: 48px; font-size: 1.25rem;">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Real-time Tracking</h6>
                                <p class="text-secondary mb-0" style="font-size: 0.9375rem;">Track delivery status, read receipts, and engagement in real-time.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="whatsapp-phone">
                        <div class="whatsapp-screen">
                            <div class="wa-header">
                                <a href="#" style="color:white;"><i class="bi bi-arrow-left"></i></a>
                                <div class="wa-avatar">W</div>
                                <div style="flex:1;">
                                    <div class="wa-name">WAPI Demo</div>
                                    <div class="wa-status">online</div>
                                </div>
                                <i class="bi bi-camera-video"></i>
                                <i class="bi bi-telephone"></i>
                            </div>
                            <div class="wa-messages" id="demoMessages" style="min-height: 350px;">
                                <div class="wa-bubble received">
                                    <div>👋 Hello! Type a message below to see how our API works.</div>
                                    <div class="wa-time">Now</div>
                                </div>
                            </div>
                            <div class="wa-input-bar">
                                <i class="bi bi-emoji-smile" style="font-size: 1.25rem; color: #666; cursor:pointer;"></i>
                                <input class="wa-input" id="demoInput" placeholder="Type a message..." maxlength="200">
                                <button class="wa-send-btn" id="demoSendBtn"><i class="bi bi-send-fill"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing-section" id="pricing">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="section-title"><?= e($settings->get('pricing_title', 'Simple, Transparent Pricing')); ?></h2>
                <p class="section-subtitle"><?= e($settings->get('pricing_subtitle', 'Choose the plan that fits your business needs.')); ?></p>
            </div>

            <!-- Pricing Toggle -->
            <div class="pricing-toggle">
                <span class="active" id="monthlyLabel">Monthly</span>
                <div class="toggle-switch" id="pricingToggle"></div>
                <span id="yearlyLabel">Yearly</span>
                <span class="pricing-save">Save 17%</span>
            </div>

            <div class="row g-4 justify-content-center">
                <?php foreach ($plans as $plan): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card <?= $plan['is_popular'] ? 'popular' : ''; ?>">
                        <?php if ($plan['is_popular']): ?>
                            <div class="pricing-badge">Most Popular</div>
                        <?php endif; ?>
                        
                        <div class="pricing-plan-name" style="color: <?= e($plan['badge_color']); ?>">
                            <?= e($plan['name']); ?>
                        </div>
                        <p class="text-secondary" style="font-size: 0.875rem;"><?= e($plan['description']); ?></p>
                        
                        <div class="pricing-amount">
                            <span class="currency">₹</span>
                            <span class="price-value" 
                                  data-monthly="<?= e($plan['monthly_price']); ?>" 
                                  data-yearly="<?= e($plan['yearly_price']); ?>">
                                <?= number_format($plan['monthly_price'], 0); ?>
                            </span>
                            <span class="period price-period">/month</span>
                        </div>

                        <?php 
                        $planFeatures = [];
                        
                        // Add functional feature flags
                        $planFeatures[] = ['text' => 'Chatbot', 'included' => $plan['chatbot_enabled']];
                        $planFeatures[] = ['text' => 'Bulk Messaging', 'included' => $plan['bulk_messaging']];
                        $planFeatures[] = ['text' => 'Webhook Support', 'included' => $plan['webhook_enabled']];

                        // Add custom features from features_list
                        if (!empty($plan['features_list'])) {
                            $featureItems = explode(';;;', $plan['features_list']);
                            foreach ($featureItems as $item) {
                                $parts = explode('|||', $item);
                                if (count($parts) === 2) {
                                    // Avoid duplicating features already added above
                                    $ftLower = strtolower(trim(str_replace([' ', '-'], '', $parts[0])));
                                    if (!in_array($ftLower, ['chatbot', 'bulkmessaging', 'webhooksupport'])) {
                                        $planFeatures[] = ['text' => $parts[0], 'included' => $parts[1]];
                                    }
                                }
                            }
                        }
                        ?>
                        <ul class="pricing-features">
                            <?php foreach ($planFeatures as $pf): ?>
                            <li class="<?= $pf['included'] == '0' ? 'disabled' : ''; ?>">
                                <i class="bi <?= $pf['included'] == '1' ? 'bi-check-circle-fill' : 'bi-x-circle-fill'; ?>"></i>
                                <?= e($pf['text']); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <a href="<?= baseUrl('auth/register.php?plan=' . e($plan['slug'])); ?>" 
                           class="btn <?= $plan['is_popular'] ? 'btn-primary' : 'btn-outline-primary'; ?> w-100 btn-lg">
                            Get Started
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section" id="testimonials">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title"><?= e($settings->get('testimonials_title', 'Trusted by 10,000+ Businesses')); ?></h2>
            </div>
            <div class="row g-4">
                <?php foreach ($testimonials as $testimonial): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="testimonial-card">
                        <div class="testimonial-stars">
                            <?php for ($i = 0; $i < $testimonial['rating']; $i++): ?>
                                <i class="bi bi-star-fill"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="testimonial-text">"<?= e($testimonial['content']); ?>"</p>
                        <div class="testimonial-author">
                            <div class="testimonial-avatar">
                                <?= strtoupper(substr($testimonial['name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div class="testimonial-name"><?= e($testimonial['name']); ?></div>
                                <div class="testimonial-role">
                                    <?= e($testimonial['designation']); ?><?php if ($testimonial['company']): ?>, <?= e($testimonial['company']); ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section" id="faq">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title"><?= e($settings->get('faq_title', 'Frequently Asked Questions')); ?></h2>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <?php foreach ($faqs as $index => $faq): ?>
                    <div class="faq-item <?= $index === 0 ? 'active' : ''; ?>">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span><?= e($faq['question']); ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </div>
                        <div class="faq-answer" style="<?= $index === 0 ? 'max-height: 200px;' : ''; ?>">
                            <div class="faq-answer-inner">
                                <?= e($faq['answer']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section" style="padding: 6rem 0; background: linear-gradient(135deg, var(--primary) 0%, #4338ca 100%); color: white; text-align: center;">
        <div class="container px-4">
            <h2 class="cta-title mb-3" style="font-size: clamp(1.75rem, 5vw, 2.5rem); font-weight: 800;">
                <?= e($settings->get('cta_title', 'Ready to Get Started?')); ?>
            </h2>
            <p class="cta-subtitle mb-4 mx-auto" style="font-size: clamp(1rem, 3vw, 1.125rem); opacity: 0.9; max-width: 600px;">
                <?= e($settings->get('cta_subtitle', 'Join thousands of businesses using WAPI to power their WhatsApp communication.')); ?>
            </p>
            <a href="<?= baseUrl($settings->get('cta_button_link', 'auth/register.php?plan=trial')); ?>" class="btn btn-lg btn-white-glass" 
               style="background: white; color: var(--primary); font-weight: 700; padding: 0.875rem 2.5rem; border-radius: 12px; transition: var(--transition);">
                <?= e($settings->get('cta_button_text', 'Start 14 Days Free Trial')); ?> <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </section>

<?php include __DIR__ . '/includes/footer.php'; ?>
