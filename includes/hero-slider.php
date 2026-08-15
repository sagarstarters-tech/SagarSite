<?php
// includes/hero-slider.php
if (!isset($conn)) {
    include 'db_connect.php';
}

$settings_q = $conn->query("SELECT * FROM hero_slider_settings LIMIT 1");
$settings = $settings_q->fetch_assoc();

if (!$settings || !$settings['is_active']) {
    return; // Don't show slider if inactive or settings don't exist
}

$slides_q = $conn->query("SELECT * FROM hero_slides WHERE is_active = 1 ORDER BY display_order ASC");
if ($slides_q->num_rows == 0) {
    return; // No active slides
}

// Generate inline CSS variables for the settings
$desktop_height = !empty($settings['desktop_height']) ? $settings['desktop_height'] : '560px';
$tablet_height = !empty($settings['tablet_height']) ? $settings['tablet_height'] : '460px';
$mobile_height = !empty($settings['mobile_height']) ? $settings['mobile_height'] : '380px';
$container_width = !empty($settings['container_width']) ? $settings['container_width'] : '100%';
$image_fit = !empty($settings['image_fit']) ? $settings['image_fit'] : 'cover';

$layout_class = ($settings['layout'] ?? 'full') === 'boxed' ? 'container rounded-4 overflow-hidden mt-4' : 'container-fluid p-0';
$max_width_style = (($settings['layout'] ?? 'full') === 'boxed' && $container_width !== '100%') ? "max-width: " . htmlspecialchars($container_width) . ";" : '';

$transition_speed = $settings['transition_speed'] ?? 800;
$autoplay_delay = $settings['autoplay_delay'] ?? 5000;
$autoplay_enabled = ($settings['autoplay'] && $slides_q->num_rows > 1) ? 'true' : 'false';
$transition_type = $settings['transition_type'] ?? 'slide'; // fade, slide, zoom
?>

<div class="hero-slider-wrapper <?php echo $layout_class; ?>" 
     style="--desktop-height: <?php echo htmlspecialchars($desktop_height); ?>; 
            --tablet-height: <?php echo htmlspecialchars($tablet_height); ?>; 
            --mobile-height: <?php echo htmlspecialchars($mobile_height); ?>;
            --slider-fit: <?php echo htmlspecialchars($image_fit); ?>;
            --transition-speed: <?php echo $transition_speed; ?>ms;
            <?php echo $max_width_style; ?>">
    
    <div class="hero-slider" 
         data-autoplay="<?php echo $autoplay_enabled; ?>" 
         data-delay="<?php echo $autoplay_delay; ?>" 
         data-transition="<?php echo $transition_type; ?>">
        
        <div class="hero-slider-track">
            <?php 
            $slide_index = 0;
            while($slide = $slides_q->fetch_assoc()): 
                $active_class = $slide_index === 0 ? 'active' : '';
                $visibility_class = 'show-all';
                if ($slide['device_visibility'] === 'desktop') $visibility_class = 'd-none d-md-flex';
                elseif ($slide['device_visibility'] === 'mobile') $visibility_class = 'd-flex d-md-none';
            ?>
            
            <div class="hero-slide <?php echo $active_class; ?> <?php echo $visibility_class; ?>" id="slide-<?php echo $slide_index; ?>">
                
                <!-- Background Layer -->
                <div class="hero-bg">
            <?php if($slide['bg_type'] === 'image' && $slide['media_path']): ?>
                        <img src="<?php echo htmlspecialchars(resolve_image_url($slide['media_path'])); ?>" 
                             alt="<?php echo htmlspecialchars($slide['title'] ?: 'Hero Banner'); ?>" 
                             loading="<?php echo $slide_index === 0 ? 'eager' : 'lazy'; ?>"
                             <?php if($slide_index === 0): ?>fetchpriority="high" decoding="async"<?php endif; ?>
                             width="1920" height="700">
                    <?php elseif($slide['bg_type'] === 'video' && $slide['media_path']): ?>
                        <video src="<?php echo htmlspecialchars(resolve_image_url($slide['media_path'])); ?>" autoplay loop muted playsinline <?php echo $slide_index !== 0 ? 'data-lazy-video' : ''; ?>></video>
                    <?php elseif($slide['bg_type'] === 'color'): ?>
                        <div style="background-color: <?php echo htmlspecialchars($slide['bg_color']); ?>; width: 100%; height: 100%;"></div>
                    <?php elseif($slide['bg_type'] === 'gradient'): ?>
                        <div style="background: <?php echo htmlspecialchars($slide['bg_color']); ?>; width: 100%; height: 100%;"></div>
                    <?php endif; ?>
                    
                    <!-- Overlay -->
                    <div class="hero-overlay" style="background-color: <?php echo htmlspecialchars($slide['overlay_color']); ?>;"></div>
                </div>

                <!-- Content Layer -->
                <div class="hero-content text-<?php echo htmlspecialchars($slide['content_alignment']); ?>">
                    <div class="container">
                        <div class="row <?php echo $slide['content_alignment'] === 'center' ? 'justify-content-center' : ($slide['content_alignment'] === 'right' ? 'justify-content-end' : 'justify-content-start'); ?>">
                            <div class="col-md-8 col-lg-7">
                                
                                <?php if($slide['subtitle']): ?>
                                <h4 class="slide-subtitle text-animation" 
                                    data-animation="<?php echo htmlspecialchars($slide['text_animation']); ?>" 
                                    style="--animation-duration: <?php echo htmlspecialchars($slide['animation_duration']); ?>ms; --animation-delay: 200ms;">
                                    <?php echo htmlspecialchars($slide['subtitle']); ?>
                                </h4>
                                <?php endif; ?>

                                <?php if($slide['title']): ?>
                                <h1 class="slide-title text-animation" 
                                    data-animation="<?php echo htmlspecialchars($slide['text_animation']); ?>" 
                                    style="--animation-duration: <?php echo htmlspecialchars($slide['animation_duration']); ?>ms; --animation-delay: 400ms;">
                                    <?php echo htmlspecialchars($slide['title']); ?>
                                </h1>
                                <?php endif; ?>

                                <?php if($slide['description']): ?>
                                <p class="slide-description text-animation" 
                                   data-animation="<?php echo htmlspecialchars($slide['text_animation']); ?>" 
                                   style="--animation-duration: <?php echo htmlspecialchars($slide['animation_duration']); ?>ms; --animation-delay: 600ms;">
                                    <?php echo nl2br(htmlspecialchars($slide['description'])); ?>
                                </p>
                                <?php endif; ?>

                                <?php 
                                // Provide fallbacks and ensure buttons show even if animation is missing
                                $btn_animation = !empty($slide['text_animation']) ? htmlspecialchars($slide['text_animation']) : 'fade';
                                $btn_duration = !empty($slide['animation_duration']) ? intval($slide['animation_duration']) : 1000;
                                ?>
                                <div class="slide-buttons text-animation" 
                                     data-animation="<?php echo $btn_animation; ?>" 
                                     style="--animation-duration: <?php echo $btn_duration; ?>ms; --animation-delay: 800ms;">
                                    
                                    <?php if(!empty($slide['btn_primary_text'])): ?>
                                    <a href="<?php echo htmlspecialchars($slide['btn_primary_link'] ?? '#'); ?>" class="btn btn-<?php echo htmlspecialchars($slide['btn_primary_style'] ?: 'primary'); ?> btn-lg hero-btn">
                                        <?php echo htmlspecialchars($slide['btn_primary_text']); ?>
                                    </a>
                                    <?php endif; ?>

                                    <?php if(!empty($slide['btn_secondary_text'])): ?>
                                    <a href="<?php echo htmlspecialchars($slide['btn_secondary_link'] ?? '#'); ?>" class="btn btn-<?php echo htmlspecialchars($slide['btn_secondary_style'] ?: 'outline-light'); ?> btn-lg hero-btn ms-sm-3 mt-3 mt-sm-0">
                                        <?php echo htmlspecialchars($slide['btn_secondary_text']); ?>
                                    </a>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php 
                $slide_index++;
            endwhile; 
            $total_slides = $slide_index;
            ?>
        </div>
        
        <!-- Controls -->
        <?php if($settings['show_arrows'] && $total_slides > 1): ?>
        <button class="hero-arrow hero-prev <?php echo $settings['arrow_style']; ?>" aria-label="Previous slide"><i class="fas fa-chevron-left"></i></button>
        <button class="hero-arrow hero-next <?php echo $settings['arrow_style']; ?>" aria-label="Next slide"><i class="fas fa-chevron-right"></i></button>
        <?php endif; ?>

        <?php if($settings['show_dots'] && $total_slides > 1): ?>
        <div class="hero-dots <?php echo $settings['dot_style']; ?>">
            <?php for($i=0; $i<$total_slides; $i++): ?>
                <button class="hero-dot <?php echo $i===0 ? 'active' : ''; ?>" data-slide="<?php echo $i; ?>" aria-label="Go to slide <?php echo $i+1; ?>"></button>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- hero-slider CSS is preloaded in <head> via header.php -->
<script src="<?php echo ASSETS_URL; ?>/js/hero-slider-script.js?v=1.5" defer></script>
