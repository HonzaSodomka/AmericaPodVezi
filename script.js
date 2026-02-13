/**
 * America Pod Věží - Main Script
 */

document.addEventListener('DOMContentLoaded', () => {
    initPreloader();
    initMobileMenu();
    initMenuViewer();
    initScrollAnimations();
    initDynamicYear();
});

/**
 * Preloader Fade Out (Cinematic Timing)
 */
function initPreloader() {
    const preloader = document.getElementById('preloader');
    if (preloader) {
        window.addEventListener('load', () => {
            // Wait for the cinematic animations to complete (approx 2.5s)
            
            setTimeout(() => {
                preloader.style.opacity = '0';
                setTimeout(() => {
                    preloader.style.display = 'none';
                    // Re-enable scrolling if blocked (optional)
                    document.body.style.overflow = '';
                }, 1000); // Matches CSS transition-opacity duration
            }, 2600); // Wait slightly longer than the longest animation (2.5s)
        });
    }
}

/**
 * Dynamic Year in Footer
 */
function initDynamicYear() {
    const yearSpan = document.getElementById('current-year');
    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }
}

/**
 * Mobile Navigation Toggle
 */
function initMobileMenu() {
    const menuBtn = document.getElementById('menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const icon = menuBtn ? menuBtn.querySelector('i') : null;
    let isOpen = false;

    if (!menuBtn || !mobileMenu) return;

    function toggleMenu() {
        isOpen = !isOpen;
        if (isOpen) {
            mobileMenu.classList.remove('menu-closed');
            mobileMenu.classList.add('menu-open');
            if(icon) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            }
        } else {
            mobileMenu.classList.remove('menu-open');
            mobileMenu.classList.add('menu-closed');
            if(icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }
    }

    menuBtn.addEventListener('click', toggleMenu);

    // Close menu when clicking a link
    mobileMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            if (isOpen) toggleMenu();
        });
    });
}

/**
 * Static Menu Viewer (Image Gallery) with Touch Swipe
 */
function initMenuViewer() {
    // UPDATED: Using SVG placeholders instead of missing JPGs
    const menuImages = [
        'menu-page-1.svg', 
        'menu-page-2.svg', 
        'menu-page-3.svg', 
        'menu-page-4.svg'
    ];
    
    let currentIndex = 0;
    const container = document.getElementById('menu-container'); // Swipe target
    const currentImg = document.getElementById('current-menu-image');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    const indicator = document.getElementById('page-indicator');

    if (!currentImg) return; 

    function updateMenu() {
        // Fade out
        currentImg.style.opacity = '0';
        
        setTimeout(() => {
            // Change source
            currentImg.src = menuImages[currentIndex];
            
            // Update indicator text
            if (indicator) {
                indicator.textContent = `STRANA ${currentIndex + 1} / ${menuImages.length}`;
            }

            // Handle load event to fade in only when ready
            currentImg.onload = () => { 
                currentImg.style.opacity = '1'; 
            };
            
            // Fallback if cached
            if (currentImg.complete) {
                currentImg.style.opacity = '1';
            }
        }, 150); 
    }

    function goNext() {
        if (currentIndex < menuImages.length - 1) {
            currentIndex++;
            updateMenu();
        }
    }

    function goPrev() {
        if (currentIndex > 0) {
            currentIndex--;
            updateMenu();
        }
    }

    if (prevBtn) prevBtn.addEventListener('click', goPrev);
    if (nextBtn) nextBtn.addEventListener('click', goNext);

    // --- Touch Swipe Logic ---
    let touchStartX = 0;
    let touchEndX = 0;

    if (container) {
        container.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, {passive: true});

        container.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, {passive: true});
    }

    function handleSwipe() {
        const threshold = 50; // Min distance to trigger swipe
        if (touchStartX - touchEndX > threshold) {
            goNext(); // Swipe Left -> Next
        }
        if (touchEndX - touchStartX > threshold) {
            goPrev(); // Swipe Right -> Prev
        }
    }

    // Initialize first state
    updateMenu();
}

/**
 * Scroll Animations (Intersection Observer)
 */
function initScrollAnimations() {
    const observerOptions = {
        root: null, // viewport
        rootMargin: '0px',
        threshold: 0.1 // Trigger when 10% of element is visible
    };\n\n    const observer = new IntersectionObserver((entries, observer) => {\n        entries.forEach(entry => {\n            if (entry.isIntersecting) {\n                // Add the animation class\n                entry.target.classList.add('animate-enter');\n                // Remove the initial wait class (opacity: 0)\n                entry.target.classList.remove('scroll-wait');\n                // Stop observing once animated\n                observer.unobserve(entry.target);\n            }\n        });\n    }, observerOptions);\n\n    // Find all elements waiting for scroll animation\n    const scrollElements = document.querySelectorAll('.scroll-wait');\n    scrollElements.forEach(el => observer.observe(el));\n}