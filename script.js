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

const ALL_DAYS = [
    { id: 'mo', name: 'Pondělí' },
    { id: 'tu', name: 'Úterý' },
    { id: 'we', name: 'Středa' },
    { id: 'th', name: 'Čtvrtek' },
    { id: 'fr', name: 'Pátek' },
    { id: 'sa', name: 'Sobota' },
    { id: 'su', name: 'Neděle' }
];

document.addEventListener('DOMContentLoaded', initApp);

function initApp() {
    initPreloader();
    initMobileMenu();
    initMenuViewer();
    initScrollAnimations();
    initDynamicYear();
    initHeroHeightFix();
    initStickyNavbar();
    fetchDynamicData(); 
}

/**
 * Helper pro chytré formátování dnů.
 * Vyřeší jak posloupnosti (Pondělí - Středa), tak mezery (Pondělí, Středa).
 */
function formatDaysLabel(dayIds) {
    if (!dayIds || dayIds.length === 0) return '';
    
    // Seřazení podle pořadí v týdnu
    const sortedIndexes = dayIds
        .map(id => ALL_DAYS.findIndex(d => d.id === id))
        .filter(idx => idx !== -1)
        .sort((a, b) => a - b);
        
    if (sortedIndexes.length === 0) return '';

    let streaks = [];
    let currentStreak = [sortedIndexes[0]];

    for (let i = 1; i < sortedIndexes.length; i++) {
        if (sortedIndexes[i] === currentStreak[currentStreak.length - 1] + 1) {
            currentStreak.push(sortedIndexes[i]);
        } else {
            streaks.push(currentStreak);
            currentStreak = [sortedIndexes[i]];
        }
    }
    streaks.push(currentStreak);

    const parts = streaks.map(streak => {
        if (streak.length === 1) {
            return ALL_DAYS[streak[0]].name;
        } else {
            // Délka 2 a více -> např. "Sobota - Neděle" nebo "Pondělí - Pátek"
            return `${ALL_DAYS[streak[0]].name} - ${ALL_DAYS[streak[streak.length - 1]].name}`;
        }
    });

    // Pospojování mezer mezi streaky čárkou
    return parts.join(', ');
}


/**
 * Fetch dynamic data from Node.js backend
 * Aktualizuje otevírací dobu, kontakty, rozvoz, akce a denní menu
 */
async function fetchDynamicData() {
    try {
        const response = await fetch('/api/data');
        if (!response.ok) throw new Error('Failed to fetch data');
        
        const data = await response.json();
        
        // 1. Update Flexible Opening Hours
        if (data.flexibleHours) {
            const hoursContainer = document.querySelector('#contact ul.text-gray-300'); 
            
            if (hoursContainer) {
                let activeGroups = [...data.flexibleHours];
                
                const usedDays = activeGroups.flatMap(g => g.days);
                const missingDays = ALL_DAYS.map(d => d.id).filter(id => !usedDays.includes(id));
                
                missingDays.forEach(dayId => {
                    activeGroups.push({
                        days: [dayId],
                        time: "ZAVŘENO"
                    });
                });
                
                let expandedDays = [];
                activeGroups.forEach(group => {
                   group.days.forEach(dayId => {
                       expandedDays.push({
                           dayId: dayId,
                           time: group.time
                       });
                   });
                });

                expandedDays.sort((a, b) => {
                    const idxA = ALL_DAYS.findIndex(d => d.id === a.dayId);
                    const idxB = ALL_DAYS.findIndex(d => d.id === b.dayId);
                    return idxA - idxB;
                });

                let finalGroups = [];
                if(expandedDays.length > 0) {
                    let currentGroup = { days: [expandedDays[0].dayId], time: expandedDays[0].time };
                    
                    for(let i = 1; i < expandedDays.length; i++) {
                        if(expandedDays[i].time.toLowerCase() === currentGroup.time.toLowerCase()) {
                            currentGroup.days.push(expandedDays[i].dayId);
                        } else {
                            finalGroups.push(currentGroup);
                            currentGroup = { days: [expandedDays[i].dayId], time: expandedDays[i].time };
                        }
                    }
                    finalGroups.push(currentGroup);
                }

                let htmlContent = '';
                
                finalGroups.forEach((group, index) => {
                    const isLast = index === finalGroups.length - 1;
                    const liClass = isLast ? "flex justify-between pt-1" : "flex justify-between border-b border-white/10 pb-2";
                    const isClosed = group.time.toUpperCase() === "ZAVŘENO" || group.time.toLowerCase().includes("zavřeno");
                    const timeClass = isClosed ? "text-brand-gold font-bold" : "";
                    const label = formatDaysLabel(group.days);

                    htmlContent += `
                        <li class="${liClass}">
                            <span class="font-bold text-white">${label}</span>
                            <span class="${timeClass}">${group.time}</span>
                        </li>
                    `;
                });
                
                hoursContainer.innerHTML = htmlContent;
            }
        }

        // 2. Update Contacts
        if (data.contacts) {
            const heroPhone = document.querySelector('.hero-section a[href^="tel:"]');
            if (heroPhone) {
                if (data.contacts.phoneMain && data.contacts.phoneMain.trim() !== '') {
                    const rawPhone = data.contacts.phoneMain.replace(/\s/g, '');
                    heroPhone.href = `tel:${rawPhone}`;
                    heroPhone.querySelector('span:last-child').innerHTML = `<i class="fas fa-phone-alt text-xs mr-1"></i> ${data.contacts.phoneMain}`;
                    heroPhone.style.display = ''; 
                } else {
                    heroPhone.style.display = 'none'; 
                }
            }

            const contactPhones = document.querySelectorAll('#contact a[href^="tel:"]');
            const contactEmail = document.querySelector('#contact a[href^="mailto:"]');

            if (contactPhones.length > 0) {
                if (data.contacts.phoneMain && data.contacts.phoneMain.trim() !== '') {
                    contactPhones[0].href = `tel:${data.contacts.phoneMain.replace(/\s/g, '')}`;
                    contactPhones[0].textContent = data.contacts.phoneMain;
                    contactPhones[0].style.display = ''; 
                } else {
                    contactPhones[0].style.display = 'none'; 
                }
            }

            if (contactPhones.length > 1) {
                if (data.contacts.phoneAlt && data.contacts.phoneAlt.trim() !== '') {
                    contactPhones[1].href = `tel:${data.contacts.phoneAlt.replace(/\s/g, '')}`;
                    contactPhones[1].textContent = data.contacts.phoneAlt;
                    contactPhones[1].style.display = ''; 
                } else {
                    contactPhones[1].style.display = 'none'; 
                }
            }

            if (contactEmail) {
                if (data.contacts.email && data.contacts.email.trim() !== '') {
                    contactEmail.href = `mailto:${data.contacts.email}`;
                    contactEmail.textContent = data.contacts.email;
                    contactEmail.style.display = ''; 
                    
                    const footerEmail = document.querySelector('footer a[href^="mailto:"]');
                    if(footerEmail) {
                        footerEmail.href = `mailto:${data.contacts.email}`;
                        footerEmail.textContent = data.contacts.email;
                        footerEmail.style.display = '';
                    }
                } else {
                    contactEmail.style.display = 'none'; 
                    const footerEmail = document.querySelector('footer a[href^="mailto:"]');
                    if(footerEmail) {
                        footerEmail.style.display = 'none';
                    }
                }
            }
        }

        // 3. Update Delivery Buttons
        if (data.delivery) {
            const heroSection = document.querySelector('.hero-section');
            if(heroSection) {
                const woltLink = heroSection.querySelector('a[href*="wolt"]');
                const foodoraLink = heroSection.querySelector('a[href*="foodora"]');
                
                let woltVisible = false;
                let foodoraVisible = false;

                if (woltLink) {
                    if (data.delivery.wolt && data.delivery.wolt.trim() !== '') {
                        woltLink.href = data.delivery.wolt;
                        woltLink.style.display = '';
                        woltVisible = true;
                    } else {
                        woltLink.style.display = 'none';
                    }
                }

                if (foodoraLink) {
                    if (data.delivery.foodora && data.delivery.foodora.trim() !== '') {
                        foodoraLink.href = data.delivery.foodora;
                        foodoraLink.style.display = '';
                        foodoraVisible = true;
                    } else {
                        foodoraLink.style.display = 'none';
                    }
                }

                if (woltLink) {
                    const separator = woltLink.nextElementSibling;
                    if (separator && separator.tagName === 'SPAN') {
                        separator.style.display = (woltVisible && foodoraVisible) ? '' : 'none';
                    }
                }

                const deliveryContainer = heroSection.querySelector('.bg-black\\/50.backdrop-blur-md');
                if (deliveryContainer) {
                    if (!woltVisible && !foodoraVisible) {
                        deliveryContainer.style.display = 'none';
                    } else {
                        deliveryContainer.style.display = ''; 
                    }
                }
            }
        }

        // 4. Promo Popup
        if (data.promoPopup) {
            const popup = document.getElementById('promo-popup');
            const popupContent = document.getElementById('promo-popup-content');
            const closeBtn = document.getElementById('promo-close');
            const img = document.getElementById('promo-image');

            if (popup && data.promoPopup.active && data.promoPopup.imageData) {
                const today = new Date().toISOString().split('T')[0];
                let show = true;

                if (data.promoPopup.dateFrom && today < data.promoPopup.dateFrom) show = false;
                if (data.promoPopup.dateTo && today > data.promoPopup.dateTo) show = false;

                const isClosed = sessionStorage.getItem('promo_closed_v1');

                if (show && !isClosed) {
                    img.src = data.promoPopup.imageData;
                    
                    setTimeout(() => {
                        popup.classList.remove('hidden');
                        popup.classList.add('flex');
                        void popup.offsetWidth; 
                        popup.classList.remove('opacity-0');
                        popup.classList.add('opacity-100');
                        popupContent.classList.remove('scale-95');
                        popupContent.classList.add('scale-100');
                    }, 2500);

                    const closePopup = () => {
                        popup.classList.remove('opacity-100');
                        popup.classList.add('opacity-0');
                        popupContent.classList.remove('scale-100');
                        popupContent.classList.add('scale-95');
                        setTimeout(() => {
                            popup.classList.remove('flex');
                            popup.classList.add('hidden');
                        }, 500);
                        sessionStorage.setItem('promo_closed_v1', 'true');
                    };

                    closeBtn.addEventListener('click', closePopup);
                    popup.addEventListener('click', (e) => {
                        if (e.target === popup) closePopup();
                    });
                }
            }
        }

        // 5. Update Daily Menu
        if (data.dailyMenu && data.dailyMenu.length > 0) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Získáme všechny menu, která jsou dnes nebo v budoucnu
            const validMenus = data.dailyMenu.filter(item => {
                const itemDate = new Date(item.date);
                itemDate.setHours(0, 0, 0, 0);
                return itemDate >= today;
            });

            if (validMenus.length > 0) {
                // Seskupit podle data
                const groupedByDate = validMenus.reduce((acc, item) => {
                    if (!acc[item.date]) acc[item.date] = [];
                    acc[item.date].push(item);
                    return acc;
                }, {});

                // Najít nejbližší datum
                const closestDateStr = Object.keys(groupedByDate).sort()[0];
                const closestMenu = groupedByDate[closestDateStr];

                const dateParts = closestDateStr.split('-');
                const formattedDate = `${dateParts[2]}.${dateParts[1]}.${dateParts[0]}`;
                
                const closestDateObj = new Date(closestDateStr);
                closestDateObj.setHours(0, 0, 0, 0);
                const isToday = closestDateObj.getTime() === today.getTime();

                document.getElementById('daily-menu-date').innerHTML = isToday ? `PLATÍ PRO DNES <span class="text-white/50 mx-2">|</span> ${formattedDate}` : `PLATNÉ PRO <span class="text-white/50 mx-2">|</span> ${formattedDate}`;

                const menuListContainer = document.getElementById('daily-menu-list');
                let menuHtml = '';

                closestMenu.forEach(item => {
                    menuHtml += `
                        <div class="flex items-end justify-between gap-2 md:gap-4 group relative py-2">
                            <div class="text-brand-gold/70 font-mono text-xs md:text-sm whitespace-nowrap w-12 md:w-16 text-right shrink-0 mb-1">
                                ${item.amount ? item.amount + item.unit : ''}
                            </div>
                            <div class="text-white text-lg md:text-xl font-light mb-0.5 shrink-0 max-w-[60%] md:max-w-[70%]">
                                ${item.desc}
                            </div>
                            <div class="flex-grow border-b-2 border-dotted border-white/20 mb-2 opacity-30 group-hover:border-brand-gold/50 group-hover:opacity-100 transition duration-300"></div>
                            <div class="text-brand-gold font-heading font-bold text-xl md:text-2xl whitespace-nowrap shrink-0">
                                ${item.price} <span class="text-sm md:text-base opacity-80 font-normal">Kč</span>
                            </div>
                        </div>
                    `;
                });

                menuListContainer.innerHTML = menuHtml;
                document.getElementById('daily-menu-wrapper').classList.remove('hidden');
            }
        }

    } catch (error) {
        console.error('Error loading dynamic data:', error);
    }
}

function initHeroHeightFix() {
    const hero = document.querySelector(CONFIG.selectors.heroSection);
    if (!hero) return;
    let lastWidth = window.innerWidth;
    const setHeight = () => { hero.style.minHeight = `${window.innerHeight}px`; };
    setHeight();
    window.addEventListener('resize', () => {
        if (window.innerWidth !== lastWidth) {
            lastWidth = window.innerWidth;
            setHeight();
        }
    });
    window.addEventListener('orientationchange', () => { setTimeout(setHeight, 100); });
}

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

function initDynamicYear() {
    const yearSpan = document.querySelector(CONFIG.selectors.currentYear);
    if (yearSpan) yearSpan.textContent = new Date().getFullYear();
}

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
        link.addEventListener('click', () => { if (isOpen) toggleMenu(); });
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
            img.src = CONFIG.menuImages[index];
        }
    };
    const updateMenu = () => {
        elements.currentImg.style.opacity = '0';
        setTimeout(() => {
            elements.currentImg.src = CONFIG.menuImages[currentIndex];
            if (elements.indicator) elements.indicator.textContent = `STRANA ${currentIndex + 1} / ${CONFIG.menuImages.length}`;
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
            if (Math.abs(diff) > CONFIG.swipeThreshold) changePage(diff > 0 ? 1 : -1);
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
