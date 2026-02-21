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
        menuLinks: '#mobile-menu a',
        heroSection: '.hero-section'
    },
    menuImages: [
        'menu-page-1.svg',
        'menu-page-2.svg',
        'menu-page-3.svg',
        'menu-page-4.svg'
    ],
    animation: {
        preloaderDelay: 1800, 
        fadeDuration: 600,    
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
    initHeroHeightFix();
    initStickyNavbar();
    fetchDynamicData(); // <-- Přidáno volání pro načtení dat z backendu
}

/**
 * Fetch dynamic data from Node.js backend
 * Aktualizuje otevírací dobu a kontakty z data.json dle zvoleného režimu
 */
async function fetchDynamicData() {
    try {
        const response = await fetch('/api/data');
        if (!response.ok) throw new Error('Failed to fetch data');
        
        const data = await response.json();
        
        // 1. Update Opening Hours (based on active mode)
        if (data.openHoursMode && data.openHours && data.openHours[data.openHoursMode]) {
            const hoursContainer = document.querySelector('#contact ul');
            if (hoursContainer) {
                const activeHours = data.openHours[data.openHoursMode];
                
                // Vygenerování HTML pro zvolený režim dnů
                let htmlContent = '';
                activeHours.forEach((item, index) => {
                    // Poslední položka (často neděle/zavřeno) nemá border-b, ale pt-1
                    const isLast = index === activeHours.length - 1;
                    const liClass = isLast ? "flex justify-between pt-1" : "flex justify-between border-b border-white/10 pb-2";
                    const timeClass = (item.time.toUpperCase() === "ZAVŘENO") ? "text-brand-gold font-bold" : "";
                    
                    htmlContent += `
                        <li class="${liClass}">
                            <span class="font-bold text-white">${item.label}</span>
                            <span class="${timeClass}">${item.time}</span>
                        </li>
                    `;
                });
                
                hoursContainer.innerHTML = htmlContent;
            }
        }

        // 2. Update Contacts
        if (data.contacts) {
            // Update phone numbers in hero
            const heroPhone = document.querySelector('.hero-section a[href^="tel:"]');
            if (heroPhone && data.contacts.phoneMain) {
                const rawPhone = data.contacts.phoneMain.replace(/\s/g, '');
                heroPhone.href = `tel:${rawPhone}`;
                heroPhone.querySelector('span:last-child').innerHTML = `<i class="fas fa-phone-alt text-xs mr-1"></i> ${data.contacts.phoneMain}`;
            }

            // Update contacts in footer/contact section
            const contactPhones = document.querySelectorAll('#contact a[href^="tel:"]');
            if (contactPhones.length >= 2) {
                if (data.contacts.phoneMain) {
                    contactPhones[0].href = `tel:${data.contacts.phoneMain.replace(/\s/g, '')}`;
                    contactPhones[0].textContent = data.contacts.phoneMain;
                }
                if (data.contacts.phoneAlt) {
                    contactPhones[1].href = `tel:${data.contacts.phoneAlt.replace(/\s/g, '')}`;
                    contactPhones[1].textContent = data.contacts.phoneAlt;
                }
            }

            const contactEmail = document.querySelector('#contact a[href^="mailto:"]');
            if (contactEmail && data.contacts.email) {
                contactEmail.href = `mailto:${data.contacts.email}`;
                contactEmail.textContent = data.contacts.email;
            }
        }
    } catch (error) {
        console.error('Error loading dynamic data:', error);
        // Pokud backend neběží nebo chybí data, necháme tam původní statické HTML jako fallback
    }
}

/**
 * FIXED HERO HEIGHT (Prevents jump on mobile scroll)
 */
function initHeroHeightFix() {
    const hero = document.querySelector(CONFIG.selectors.heroSection);
    if (!hero) return;

    let lastWidth = window.innerWidth;

    const setHeight = () => {
        hero.style.minHeight = `${window.innerHeight}px`;
    };

    setHeight();

    window.addEventListener('resize', () => {
        if (window.innerWidth !== lastWidth) {
            lastWidth = window.innerWidth;
            setHeight();
        }
    });

    window.addEventListener('orientationchange', () => {
        setTimeout(setHeight, 100);
    });
}

/**
 * Sticky Navbar
 */
function initStickyNavbar() {
    const navbar = document.querySelector('#navbar');
    if (!navbar) return;

    const hero = document.querySelector(CONFIG.selectors.heroSection);
    let ticking = false;

    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                const heroHeight = hero ? hero.offsetHeight : window.innerHeight;
                const progress = Math.min(window.scrollY / (heroHeight * 0.5), 1);
                const alpha = Math.round(progress * 230);
                navbar.style.backgroundColor = `rgba(0,0,0,${(alpha/255).toFixed(2)})`;
                navbar.style.backdropFilter = progress > 0.1 ? `blur(${(progress * 12).toFixed(1)}px)` : '';
                navbar.style.webkitBackdropFilter = navbar.style.backdropFilter;
                navbar.style.boxShadow = progress > 0.5 ? `0 2px 20px rgba(0,0,0,${(progress * 0.8).toFixed(2)})` : '';
                navbar.style.borderBottom = progress > 0.5 ? `1px solid rgba(212,163,115,${(progress * 0.15).toFixed(2)})` : '';
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });
}

/**
 * Preloader Fade Out (Cinematic Timing)
 */
function initPreloader() {
    const preloader = document.querySelector(CONFIG.selectors.preloader);
    
    if (!preloader) {
        initConsentAndMaps();
        return;
    }

    const fadeOut = () => {
        setTimeout(() => {
            preloader.style.opacity = '0';
            setTimeout(() => {
                preloader.style.display = 'none';
                initConsentAndMaps();
            }, CONFIG.animation.fadeDuration);
        }, CONFIG.animation.preloaderDelay);
    };

    if (document.readyState === 'complete') {
        fadeOut();
    } else {
        window.addEventListener('load', fadeOut, { once: true });
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

        menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (icon) {
            icon.classList.toggle('fa-bars', !isOpen);
            icon.classList.toggle('fa-times', isOpen);
        }
    };

    menuBtn.addEventListener('click', toggleMenu);

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

    const preloadImage = (index) => {
        if (index >= 0 && index < CONFIG.menuImages.length) {
            const img = new Image();
            img.src = CONFIG.menuImages[index];
        }
    };

    const updateMenu = () => {
        elements.currentImg.style.opacity = '0';

        setTimeout(() => {
            elements.currentImg.src = CONFIG.menuImages[currentIndex];

            if (elements.indicator) {
                elements.indicator.textContent = `STRANA ${currentIndex + 1} / ${CONFIG.menuImages.length}`;
            }

            const fadeIn = () => { 
                elements.currentImg.style.opacity = '1'; 
                preloadImage(currentIndex + 1);
                preloadImage(currentIndex - 1);
            };
            elements.currentImg.addEventListener('load', fadeIn, { once: true });
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

    if (elements.container) {
        let touchStartX = 0;

        elements.container.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        elements.container.addEventListener('touchend', e => {
            const touchEndX = e.changedTouches[0].screenX;
            const diff = touchStartX - touchEndX;

            if (Math.abs(diff) > CONFIG.swipeThreshold) {
                changePage(diff > 0 ? 1 : -1);
            }
        }, { passive: true });
    }
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

/**
 * GDPR Consent - Google Maps
 */
function initConsentAndMaps() {
    const STORAGE_KEY = 'consent_google_maps';

    const banner = document.querySelector('#consent-banner');
    const acceptBtn = document.querySelector('#consent-accept');
    const rejectBtn = document.querySelector('#consent-reject');
    const settingsLink = document.querySelector('#cookie-settings');
    const placeholderBtn = document.querySelector('#map-consent-accept');
    const placeholder = document.querySelector('#map-placeholder');
    const host = document.querySelector('#map-iframe-host');

    const getState = () => localStorage.getItem(STORAGE_KEY);
    const setState = (v) => localStorage.setItem(STORAGE_KEY, v);

    const hideBanner = () => { if (banner) banner.classList.add('hidden'); };
    const showBanner = () => { if (banner) banner.classList.remove('hidden'); };

    const mountMap = () => {
        if (!host || host.dataset.mounted === '1') return;

        const iframe = document.createElement('iframe');
        iframe.title = 'Mapa restaurace America Pod Věží';
        iframe.src = 'https://maps.google.com/maps?q=America+Pod+V%C4%9B%C5%BE%C3%AD%2C+Komensk%C3%A9ho+n%C3%A1m%C4%9Bst%C3%AD+61%2C+Mlad%C3%A1+Boleslav&t=&z=17&ie=UTF8&iwloc=&output=embed';
        iframe.width = '100%';
        iframe.height = '100%';
        iframe.style.border = '0';
        iframe.loading = 'lazy';
        iframe.allowFullscreen = true;

        host.innerHTML = '';
        host.appendChild(iframe);
        host.classList.remove('hidden');
        host.dataset.mounted = '1';

        if (placeholder) placeholder.classList.add('hidden');
    };

    const unmountMap = () => {
        if (!host) return;
        host.innerHTML = '';
        host.classList.add('hidden');
        host.dataset.mounted = '0';
        if (placeholder) placeholder.classList.remove('hidden');
    };

    const apply = () => {
        const state = getState();
        if (state === 'granted') {
            mountMap();
            hideBanner();
        } else if (state === 'denied') {
            unmountMap();
            hideBanner();
        } else {
            unmountMap();
            showBanner();
        }
    };

    const grant = () => { setState('granted'); apply(); };
    const deny  = () => { setState('denied');  apply(); };

    if (acceptBtn)     acceptBtn.addEventListener('click', grant);
    if (rejectBtn)     rejectBtn.addEventListener('click', deny);
    if (placeholderBtn) placeholderBtn.addEventListener('click', grant);

    if (settingsLink) {
        settingsLink.addEventListener('click', (e) => {
            e.preventDefault();
            localStorage.removeItem(STORAGE_KEY);
            apply();
            const contact = document.querySelector('#contact');
            if (contact) contact.scrollIntoView({ behavior: 'smooth' });
        });
    }

    apply();
}
