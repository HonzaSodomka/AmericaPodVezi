/**
 * America Pod Věží - Main Script
 */

const CONFIG = {
    selectors: {
        preloader: '#preloader',
        currentYear: '#current-year',
        menuBtn: '#menu-btn',
        mobileMenu: '#mobile-menu',
        menuContainer: '#menu-container',
        currentMenuImage: '#current-menu-image',
        prevPageBtn: '#prev-page',
        nextPageBtn: '#next-page',
        pageIndicator: '#page-indicator',
        scrollWait: '.scroll-wait',
        menuLinks: '#mobile-menu a'
    },
    menuImages: [
        'menu-page-1.svg', 
        'menu-page-2.svg', 
        'menu-page-3.svg', 
        'menu-page-4.svg'
    ],
    animation: {
        preloaderDelay: 2200, // Reduced from 2600 for faster load
        fadeDuration: 800,   // Reduced from 1000
        menuFadeTime: 150
    },
    swipeThreshold: 50
};

document.addEventListener('DOMContentLoaded', initApp);

function initApp() {
    initPreloader();
    initMobileMenu();
    initMenuViewer();
    initScrollAnimations();
    initDynamicYear();
}

/**
 * Preloader Fade Out (Cinematic Timing)
 */
function initPreloader() {
    const preloader = document.querySelector(CONFIG.selectors.preloader);
    if (!preloader) return;

    const fadeOut = () => {
        setTimeout(() => {
            preloader.style.opacity = '0';
            setTimeout(() => {
                preloader.style.display = 'none';
                document.body.style.overflow = '';
            }, CONFIG.animation.fadeDuration);
        }, CONFIG.animation.preloaderDelay);
    };

    // Check if page is already loaded
    if (document.readyState === 'complete') {
        fadeOut();
    } else {
        window.addEventListener('load', fadeOut);
    }
}

/**
 * Dynamic Year in Footer
 */
function initDynamicYear() {
    const yearSpan = document.querySelector(CONFIG.selectors.currentYear);
    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }
}

/**
 * Mobile Navigation Toggle
 */
function initMobileMenu() {
    const menuBtn = document.querySelector(CONFIG.selectors.menuBtn);
    const mobileMenu = document.querySelector(CONFIG.selectors.mobileMenu);
    
    if (!menuBtn || !mobileMenu) return;
    
    const icon = menuBtn.querySelector('i');
    let isOpen = false;

    const toggleMenu = () => {
        isOpen = !isOpen;
        
        mobileMenu.classList.toggle('menu-closed', !isOpen);
        mobileMenu.classList.toggle('menu-open', isOpen);
        
        if (icon) {
            icon.classList.toggle('fa-bars', !isOpen);
            icon.classList.toggle('fa-times', isOpen);
        }
    };

    menuBtn.addEventListener('click', toggleMenu);

    // Close menu when clicking a link
    document.querySelectorAll(CONFIG.selectors.menuLinks).forEach(link => {
        link.addEventListener('click', () => {
            if (isOpen) toggleMenu();
        });
    });
}

/**
 * Static Menu Viewer (Image Gallery) with Touch Swipe
 */
function initMenuViewer() {
    const elements = {
        container: document.querySelector(CONFIG.selectors.menuContainer),
        currentImg: document.querySelector(CONFIG.selectors.currentMenuImage),
        prevBtn: document.querySelector(CONFIG.selectors.prevPageBtn),
        nextBtn: document.querySelector(CONFIG.selectors.nextPageBtn),
        indicator: document.querySelector(CONFIG.selectors.pageIndicator)
    };

    if (!elements.currentImg) return; 

    let currentIndex = 0;

    const updateMenu = () => {
        elements.currentImg.style.opacity = '0';
        
        setTimeout(() => {
            elements.currentImg.src = CONFIG.menuImages[currentIndex];
            
            if (elements.indicator) {
                elements.indicator.textContent = `STRANA ${currentIndex + 1} / ${CONFIG.menuImages.length}`;
            }

            const fadeIn = () => elements.currentImg.style.opacity = '1';
            
            elements.currentImg.onload = fadeIn;
            if (elements.currentImg.complete) fadeIn();
            
        }, CONFIG.animation.menuFadeTime); 
    };

    const changePage = (direction) => {
        const newIndex = currentIndex + direction;
        if (newIndex >= 0 && newIndex < CONFIG.menuImages.length) {
            currentIndex = newIndex;
            updateMenu();
        }
    };

    if (elements.prevBtn) elements.prevBtn.addEventListener('click', () => changePage(-1));
    if (elements.nextBtn) elements.nextBtn.addEventListener('click', () => changePage(1));

    // Touch Swipe Logic
    if (elements.container) {
        let touchStartX = 0;

        elements.container.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, {passive: true});

        elements.container.addEventListener('touchend', e => {
            const touchEndX = e.changedTouches[0].screenX;
            const diff = touchStartX - touchEndX;

            if (Math.abs(diff) > CONFIG.swipeThreshold) {
                if (diff > 0) changePage(1); // Swipe Left -> Next
                else changePage(-1);       // Swipe Right -> Prev
            }
        }, {passive: true});
    }

    // Initialize
    updateMenu();
}

/**
 * Scroll Animations (Intersection Observer)
 */
function initScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-enter');
                entry.target.classList.remove('scroll-wait');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll(CONFIG.selectors.scrollWait).forEach(el => observer.observe(el));
}