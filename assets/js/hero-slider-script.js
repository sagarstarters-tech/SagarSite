document.addEventListener('DOMContentLoaded', () => { 
    const wrapper = document.querySelector('.hero-slider-wrapper');
    const slider = document.querySelector('.hero-slider'); 
    if (!slider || !wrapper) return; 
    
    const slides = slider.querySelectorAll('.hero-slide'); 
    if (slides.length <= 1) return; 
    
    const prevBtn = slider.querySelector('.hero-prev'); 
    const nextBtn = slider.querySelector('.hero-next'); 
    const dots = slider.querySelectorAll('.hero-dot'); 
    
    const autoplayEnabled = slider.getAttribute('data-autoplay') === 'true'; 
    const autoplayDelay = parseInt(slider.getAttribute('data-delay')) || 5000; 
    const transitionType = slider.getAttribute('data-transition') || 'slide'; 
    
    // Get transition speed from CSS variable
    const computedStyle = getComputedStyle(wrapper);
    const transitionSpeedRaw = computedStyle.getPropertyValue('--transition-speed').trim();
    const transitionSpeed = parseInt(transitionSpeedRaw) || 800;

    let currentIndex = 0; 
    let autoplayInterval; 
    let isTransitioning = false; 

    function loadVideoForSlide(index) { 
        const slide = slides[index]; 
        const lazyVideo = slide.querySelector('video[data-lazy-video]'); 
        if (lazyVideo && !lazyVideo.src) { 
            lazyVideo.src = lazyVideo.getAttribute('src'); 
            lazyVideo.removeAttribute('data-lazy-video'); 
        } 
    } 

    function resetAnimations(slide) { 
        const elements = slide.querySelectorAll('.text-animation'); 
        elements.forEach(el => { 
            el.style.animation = 'none'; 
            el.offsetHeight; 
            el.style.animation = null; 
        }); 
    } 

    function goToSlide(index, direction = 'next') { 
        if (isTransitioning || index === currentIndex) return; 
        isTransitioning = true; 
        
        const currentSlide = slides[currentIndex]; 
        let nextIndex = index; 
        if (nextIndex >= slides.length) nextIndex = 0; 
        if (nextIndex < 0) nextIndex = slides.length - 1; 
        
        const nextSlide = slides[nextIndex]; 
        loadVideoForSlide(nextIndex); 
        resetAnimations(nextSlide); 

        if (transitionType === 'slide') { 
            nextSlide.style.transition = 'none'; 
            if (direction === 'next') { 
                nextSlide.style.transform = 'translateX(100%)'; 
            } else { 
                nextSlide.style.transform = 'translateX(-100%)'; 
            } 
            nextSlide.offsetHeight; 
            nextSlide.style.transition = ''; 
            
            setTimeout(() => { 
                currentSlide.classList.remove('active'); 
                if (direction === 'next') { 
                    currentSlide.style.transform = 'translateX(-100%)'; 
                } else { 
                    currentSlide.style.transform = 'translateX(100%)'; 
                } 
                nextSlide.classList.add('active'); 
                nextSlide.style.transform = 'translateX(0)'; 
            }, 50); 
        } else { 
            currentSlide.classList.remove('active'); 
            nextSlide.classList.add('active'); 
        } 

        if (dots.length > 0) { 
            dots[currentIndex].classList.remove('active'); 
            dots[nextIndex].classList.add('active'); 
        } 
        
        currentIndex = nextIndex; 
        
        // Use the dynamic transition speed for the lock
        setTimeout(() => { 
            isTransitioning = false; 
            if (transitionType === 'slide') currentSlide.style.transform = ''; 
        }, transitionSpeed); 
    } 

    function nextSlide() { goToSlide(currentIndex + 1, 'next'); } 
    function prevSlide() { goToSlide(currentIndex - 1, 'prev'); } 
    
    if (nextBtn) nextBtn.addEventListener('click', nextSlide); 
    if (prevBtn) prevBtn.addEventListener('click', prevSlide); 
    
    dots.forEach((dot, index) => { 
        dot.addEventListener('click', () => { 
            const direction = index > currentIndex ? 'next' : 'prev'; 
            goToSlide(index, direction); 
        }); 
    }); 

    function startAutoplay() { 
        if (autoplayEnabled && !autoplayInterval) { 
            autoplayInterval = setInterval(nextSlide, autoplayDelay); 
        } 
    } 

    function stopAutoplay() { 
        if (autoplayInterval) { 
            clearInterval(autoplayInterval); 
            autoplayInterval = null; 
        } 
    } 

    // Touch Swipe Navigation for Mobile & Tablet
    let touchStartX = 0;
    let touchStartY = 0;
    let touchEndX = 0;
    let touchEndY = 0;
    const minSwipeDistance = 45;

    slider.addEventListener('touchstart', (e) => {
        if (!e.changedTouches || e.changedTouches.length === 0) return;
        touchStartX = e.changedTouches[0].screenX;
        touchStartY = e.changedTouches[0].screenY;
        stopAutoplay();
    }, { passive: true });

    slider.addEventListener('touchend', (e) => {
        if (!e.changedTouches || e.changedTouches.length === 0) return;
        touchEndX = e.changedTouches[0].screenX;
        touchEndY = e.changedTouches[0].screenY;
        handleSwipe();
        startAutoplay();
    }, { passive: true });

    function handleSwipe() {
        const deltaX = touchEndX - touchStartX;
        const deltaY = touchEndY - touchStartY;
        
        // Trigger only when horizontal swipe is dominant and exceeds threshold
        if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > minSwipeDistance) {
            if (deltaX < 0) {
                nextSlide();
            } else {
                prevSlide();
            }
        }
    }

    if (autoplayEnabled) { 
        startAutoplay(); 
        slider.addEventListener('mouseenter', stopAutoplay); 
        slider.addEventListener('mouseleave', startAutoplay); 
    } 
});
