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
        heroSection: '.hero-section',
        navbar: '#navbar'
    },
    menuImages: [
        { src: 'menu-page-1.svg', alt: 'Jídelní lístek strana 1 - Burgery a předkrmy' },
        { src: 'menu-page-2.svg', alt: 'Jídelní lístek strana 2 - Hlavní chody a steaky' },
        { src: 'menu-page-3.svg', alt: 'Jídelní lístek strana 3 - Saláty a dezerty' },
        { src: 'menu-page-4.svg', alt: 'Jídelní lístek strana 4 - Nápojový lístek' }
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
    initDynamicScrollPadding();
    initDailyMenuLoader();
}

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

function initDynamicScrollPadding() {
    const navbar = document.querySelector(CONFIG.selectors.navbar);
    if (!navbar) return;

    const updateScrollPadding = () => {
        const headerHeight = navbar.offsetHeight;
        document.documentElement.style.scrollPaddingTop = `${headerHeight}px`;
    };

    updateScrollPadding();
    window.addEventListener('resize', updateScrollPadding, { passive: true });
    window.addEventListener('orientationchange', () => {
        setTimeout(updateScrollPadding, 100);
    });
}

function initStickyNavbar() {
    const navbar = document.querySelector(CONFIG.selectors.navbar);
    const backdrop = document.querySelector('.nav-backdrop');
    if (!navbar || !backdrop) return;

    const hero = document.querySelector(CONFIG.selectors.heroSection);
    let ticking = false;

    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                const heroHeight = hero ? hero.offsetHeight : window.innerHeight;
                const progress = Math.min(window.scrollY / (heroHeight * 0.5), 1);
                
                backdrop.style.opacity = progress.toFixed(2);
                navbar.style.backgroundColor = `rgba(0,0,0,${(Math.round(progress * 230)/255).toFixed(2)})`;
                navbar.style.boxShadow = progress > 0.5 ? `0 2px 20px rgba(0,0,0,${(progress * 0.8).toFixed(2)})` : '';
                navbar.style.borderBottom = progress > 0.5 ? `1px solid rgba(212,163,115,${(progress * 0.15).toFixed(2)})` : '1px solid transparent';
                
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });
}

function initPreloader() {
    const preloader = document.querySelector(CONFIG.selectors.preloader);
    
    if (!preloader) {
        initConsentAndMaps();
        return;
    }

    let isTimerDone = false;
    let isLoadDone = false;

    const checkAndFade = () => {
        if (isTimerDone && isLoadDone) {
            preloader.style.opacity = '0';
            setTimeout(() => {
                preloader.style.display = 'none';
                initConsentAndMaps();
            }, CONFIG.animation.fadeDuration);
        }
    };

    setTimeout(() => {
        isTimerDone = true;
        checkAndFade();
    }, CONFIG.animation.preloaderDelay);

    if (document.readyState === 'complete') {
        isLoadDone = true;
        checkAndFade();
    } else {
        window.addEventListener('load', () => {
            isLoadDone = true;
            checkAndFade();
        }, { once: true });
    }
}

function initDynamicYear() {
    const yearSpan = document.querySelector(CONFIG.selectors.currentYear);
    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }
}

function initMobileMenu() {
    const menuBtn = document.querySelector(CONFIG.selectors.menuBtn);
    const mobileMenu = document.querySelector(CONFIG.selectors.mobileMenu);

    if (!menuBtn || !mobileMenu) return;

    const icon = menuBtn.querySelector('i');
    let isOpen = false;

    mobileMenu.style.visibility = 'hidden';

    const toggleMenu = () => {
        isOpen = !isOpen;

        mobileMenu.classList.toggle('menu-closed', !isOpen);
        mobileMenu.classList.toggle('menu-open', isOpen);
        
        document.body.style.overflow = isOpen ? 'hidden' : '';
        mobileMenu.style.visibility = isOpen ? 'visible' : 'hidden';

        menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (icon) {
            icon.classList.toggle('fa-bars', !isOpen);
            icon.classList.toggle('fa-times', isOpen);
        }
        
        if (isOpen) {
            const firstLink = mobileMenu.querySelector('a');
            if (firstLink) setTimeout(() => firstLink.focus(), 300);
        }
    };

    menuBtn.addEventListener('click', toggleMenu);

    document.querySelectorAll(CONFIG.selectors.menuLinks).forEach(link => {
        link.addEventListener('click', () => {
            if (isOpen) toggleMenu();
        });
    });
}

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
            img.src = CONFIG.menuImages[index].src;
        }
    };

    const updateMenu = () => {
        elements.currentImg.style.opacity = '0';

        setTimeout(() => {
            elements.currentImg.src = CONFIG.menuImages[currentIndex].src;
            elements.currentImg.alt = CONFIG.menuImages[currentIndex].alt;

            if (elements.indicator) {
                elements.indicator.textContent = `STRANA ${currentIndex + 1} / ${CONFIG.menuImages.length}`;
            }

            elements.currentImg.onload = () => { 
                elements.currentImg.style.opacity = '1'; 
                preloadImage(currentIndex + 1);
                preloadImage(currentIndex - 1);
            };
            
            if (elements.currentImg.complete) {
                elements.currentImg.onload();
            }

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

/**
 * Daily Menu Loader with Navigation
 */
function initDailyMenuLoader() {
    const loadingEl = document.getElementById('menu-loading');
    const displayEl = document.getElementById('menu-display');
    const closedEl = document.getElementById('menu-closed');
    const errorEl = document.getElementById('menu-error');
    
    if (!loadingEl || !displayEl || !closedEl || !errorEl) return;
    
    let currentDayOffset = 0;
    
    const showState = (state) => {
        [loadingEl, displayEl, closedEl, errorEl].forEach(el => el.classList.add('hidden'));
        if (state) state.classList.remove('hidden');
    };
    
    const loadMenu = (dayOffset) => {
        showState(loadingEl);
        
        fetch(`get_today_menu.php?day=${dayOffset}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    showState(errorEl);
                    return;
                }
                
                // Zavřeno nebo prázdné?
                if (data.is_closed || data.is_empty) {
                    document.getElementById('menu-date-closed').textContent = data.date || '';
                    document.getElementById('menu-closed-message').textContent = 
                        data.is_closed ? 'Restaurace má zavřeno.' : 'Menu ještě nebylo zadáno.';
                    
                    const prevBtnClosed = document.getElementById('menu-prev-day-closed');
                    const nextBtnClosed = document.getElementById('menu-next-day-closed');
                    
                    if (prevBtnClosed) {
                        prevBtnClosed.disabled = !data.navigation.has_prev;
                        prevBtnClosed.onclick = () => {
                            currentDayOffset--;
                            loadMenu(currentDayOffset);
                        };
                    }
                    
                    if (nextBtnClosed) {
                        nextBtnClosed.disabled = !data.navigation.has_next;
                        nextBtnClosed.onclick = () => {
                            currentDayOffset++;
                            loadMenu(currentDayOffset);
                        };
                    }
                    
                    showState(closedEl);
                    return;
                }
                
                // Zobraz menu
                document.getElementById('menu-date').textContent = data.date || 'Menu';
                
                // Polévka
                const soupSection = document.getElementById('soup-section');
                if (data.soup) {
                    document.getElementById('soup-name').textContent = data.soup.name;
                    document.getElementById('soup-price').textContent = data.soup.price + ' Kč';
                    soupSection.classList.remove('hidden');
                } else {
                    soupSection.classList.add('hidden');
                }
                
                // Jídla - OPRAVENÝ LAYOUT s odděleným číslem
                const mealsContainer = document.getElementById('meals-content');
                mealsContainer.innerHTML = '';
                
                if (data.meals) {
                    data.meals.forEach(meal => {
                        const mealDiv = document.createElement('div');
                        mealDiv.className = 'flex items-start gap-2 bg-black/30 p-4 rounded-sm hover:bg-black/40 transition';
                        
                        // Struktura: [Číslo] [Text - flex-1] [Cena]
                        const numberHtml = meal.number 
                            ? `<span class="text-brand-gold font-bold shrink-0">${meal.number}.</span>` 
                            : '';
                        
                        mealDiv.innerHTML = `
                            ${numberHtml}
                            <div class="flex-1 min-w-0 text-white text-base">${meal.name}</div>
                            <div class="text-brand-gold font-bold text-lg whitespace-nowrap shrink-0">${meal.price} Kč</div>
                        `;
                        mealsContainer.appendChild(mealDiv);
                    });
                }
                
                // Navigation
                const prevBtn = document.getElementById('menu-prev-day');
                const nextBtn = document.getElementById('menu-next-day');
                
                if (prevBtn) {
                    prevBtn.disabled = !data.navigation.has_prev;
                    prevBtn.onclick = () => {
                        currentDayOffset--;
                        loadMenu(currentDayOffset);
                    };
                }
                
                if (nextBtn) {
                    nextBtn.disabled = !data.navigation.has_next;
                    nextBtn.onclick = () => {
                        currentDayOffset++;
                        loadMenu(currentDayOffset);
                    };
                }
                
                showState(displayEl);
            })
            .catch(err => {
                console.error('Menu load error:', err);
                showState(errorEl);
            });
    };
    
    // Načti dnešek
    loadMenu(0);
}

// --- DOKONALÝ SCROLL SPY (Zvýraznění menu) ---
document.addEventListener('DOMContentLoaded', () => {
    // Vezmeme všechny sekce, které mají nějaké ID
    const sections = document.querySelectorAll('section[id]');
    // Vezmeme všechny odkazy v PC navigaci i v mobilním menu
    const navLinks = document.querySelectorAll('#navbar a, #mobile-menu a');

    window.addEventListener('scroll', () => {
        let current = '';

        // Zjistíme, nad kterou sekcí zrovna jsme (s posunem 150px kvůli horní liště)
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            if (window.scrollY >= sectionTop - 150) {
                current = section.getAttribute('id');
            }
        });

        // Projdeme všechny odkazy a obarvíme ten správný
        navLinks.forEach(link => {
            // Nejprve všem sebereme zlatou barvu
            link.classList.remove('text-brand-gold');
            
            if (current) {
                // Pokud jsme u nějaké sekce, obarvíme odkaz, který k ní vede
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('text-brand-gold');
                }
            } else {
                // Pokud jsme úplně nahoře (nejsme u sekce), obarvíme odkaz "DOMŮ"
                if (link.getAttribute('href') === '#') {
                    link.classList.add('text-brand-gold');
                }
            }
        });
    });
});