<?php
// Prevent direct access
if (!defined('HEADER_INCLUDED')) {
    die("Direct access not permitted");
}

$testi_enabled  = $global_settings['testimonial_section_enabled'] ?? '1';
if ($testi_enabled == '0') return;

$section_title    = $global_settings['testimonial_section_title'] ?? 'What Our Customers Say';
$section_subtitle = $global_settings['testimonial_section_subtitle'] ?? 'Read honest reviews from people who love our products.';
$display_count    = intval($global_settings['testimonial_show_count'] ?? 10);

// Ensure testimonials table exists safely
$testimonials_q = false;
try {
    $conn->query("CREATE TABLE IF NOT EXISTS `testimonials` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `client_name` VARCHAR(255) NOT NULL,
        `designation` VARCHAR(255) DEFAULT NULL,
        `testimonial` TEXT NOT NULL,
        `rating` TINYINT(1) DEFAULT 5,
        `image_url` VARCHAR(500) DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $testimonials_q = $conn->query("SELECT * FROM testimonials WHERE is_active = 1 ORDER BY rating DESC, id DESC LIMIT $display_count");
} catch (Throwable $e) {
    $testimonials_q = false;
}

if ($testimonials_q && $testimonials_q->num_rows > 0):
?>

<div class="container mt-5 pt-4 pb-5" data-aos="fade-up">
    <div class="text-center mb-5">
        <h2 class="montserrat fw-bold position-relative d-inline-block pb-2">
            <?php echo htmlspecialchars($section_title); ?>
            <span style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:60px; height:4px; border-radius:2px; background: linear-gradient(135deg, #667eea, #764ba2);"></span>
        </h2>
        <p class="text-muted mt-3"><?php echo htmlspecialchars($section_subtitle); ?></p>
    </div>

    <!-- Swiper CSS (Non-blocking preload) -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"></noscript>
    
    <style>
        .testimonial-slider {
            padding: 20px 10px 60px 10px;
        }
        .testimonial-card {
            background: var(--mdb-surface-color, #fff);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0,0,0,0.03);
        }
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        
        .testimonial-quote-icon {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 3rem;
            color: rgba(102, 126, 234, 0.1);
            z-index: -1;
        }
        
        .testimonial-text {
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 25px;
            flex-grow: 1;
            font-style: italic;
            color: var(--mdb-body-color, #555);
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
        }
        
        .author-img {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid #667eea;
            padding: 2px;
            background: #fff;
        }
        .author-placeholder {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            margin-right: 15px;
            border: 2px solid #eef1ff;
        }
        
        .author-info h5 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0 0 3px 0;
            color: var(--mdb-heading-color, #222);
        }
        
        .author-info p {
            font-size: 0.8rem;
            margin: 0;
            color: #888;
        }
        
        .testimonial-rating {
            margin-bottom: 15px;
        }
        .testimonial-rating i {
            color: #ffc107;
            font-size: 0.9rem;
        }
        
        .swiper-pagination-bullet-active {
            background-color: #667eea !important;
        }
        
        [data-bs-theme="dark"] .testimonial-card {
            background: #2b2b2b;
            border-color: #444;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        [data-bs-theme="dark"] .testimonial-text {
            color: #ccc;
        }
        [data-bs-theme="dark"] .author-info h5 {
            color: #f8f9fa;
        }
    </style>

    <div class="swiper testimonial-slider">
        <div class="swiper-wrapper">
            <?php while($row = $testimonials_q->fetch_assoc()): ?>
                <div class="swiper-slide h-auto">
                    <div class="testimonial-card">
                        <i class="fas fa-quote-right testimonial-quote-icon"></i>
                        
                        <div class="testimonial-rating">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $row['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        
                        <div class="testimonial-text">
                            "<?php echo htmlspecialchars($row['testimonial']); ?>"
                        </div>
                        
                        <div class="testimonial-author">
                            <?php if(!empty($row['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars(SITE_URL . '/' . ltrim($row['image_url'], '/')); ?>" class="author-img" alt="<?php echo htmlspecialchars($row['client_name']); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="author-placeholder">
                                    <?php echo strtoupper(substr($row['client_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="author-info">
                                <h5><?php echo htmlspecialchars($row['client_name']); ?></h5>
                                <?php if(!empty($row['designation'])): ?>
                                    <p><?php echo htmlspecialchars($row['designation']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <!-- Add Pagination -->
        <div class="swiper-pagination"></div>
    </div>
</div>

<!-- Swiper JS - Loaded on-demand when scrolled near viewport -->
<script>
(function() {
    function initTestimonialsSwiper() {
        if (typeof Swiper === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js';
            script.async = true;
            script.onload = function() {
                startSwiper();
            };
            document.body.appendChild(script);
        } else {
            startSwiper();
        }
    }

    function startSwiper() {
        new Swiper('.testimonial-slider', {
            slidesPerView: 1,
            spaceBetween: 20,
            autoplay: {
                delay: 4000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                640: {
                    slidesPerView: 1,
                    spaceBetween: 20,
                },
                768: {
                    slidesPerView: 2,
                    spaceBetween: 30,
                },
                1024: {
                    slidesPerView: 3,
                    spaceBetween: 30,
                },
            }
        });
    }

    if ('IntersectionObserver' in window) {
        const sliderEl = document.querySelector('.testimonial-slider');
        if (sliderEl) {
            const observer = new IntersectionObserver(function(entries, obs) {
                if (entries[0].isIntersecting) {
                    initTestimonialsSwiper();
                    obs.disconnect();
                }
            }, { rootMargin: '300px' });
            observer.observe(sliderEl);
        }
    } else {
        window.addEventListener('load', initTestimonialsSwiper);
    }
})();
</script>

<?php endif; ?>
