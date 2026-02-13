/**
 * America Pod Věží - Main Script
 */

document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initMenuViewer();
    initScrollAnimations();
    initMap();
});

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
            // Optional: Prevent scrolling when menu is open
            // document.body.style.overflow = 'hidden'; 
        } else {
            mobileMenu.classList.remove('menu-open');
            mobileMenu.classList.add('menu-closed');
            if(icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
            // document.body.style.overflow = '';
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
 * Static Menu Viewer (Image Gallery)
 */
function initMenuViewer() {
    const menuImages = [
        'menu-page-1.jpg', 
        'menu-page-2.jpg', 
        'menu-page-3.jpg', 
        'menu-page-4.jpg'
    ];
    
    let currentIndex = 0;
    const currentImg = document.getElementById('current-menu-image');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    const indicator = document.getElementById('page-indicator');

    if (!currentImg) return; // Exit if element doesn't exist

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
        }, 150); // Matches CSS transition time
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentIndex > 0) {
                currentIndex--;
                updateMenu();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentIndex < menuImages.length - 1) {
                currentIndex++;
                updateMenu();
            }
        });
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
    };

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Add the animation class
                entry.target.classList.add('animate-enter');
                // Remove the initial wait class (opacity: 0)
                entry.target.classList.remove('scroll-wait');
                // Stop observing once animated
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Find all elements waiting for scroll animation
    const scrollElements = document.querySelectorAll('.scroll-wait');
    scrollElements.forEach(el => observer.observe(el));
}

/**
 * Leaflet Map Initialization
 */
function initMap() {
    const mapElement = document.getElementById('map');
    if (!mapElement) return;

    // Corrected Coordinates for America Pod Věží
    const lat = 50.41235680840374; 
    const lng = 14.903186442396233;

    const map = L.map('map', {
        center: [lat, lng],
        zoom: 17, // Slightly closer zoom for better detail
        scrollWheelZoom: false
    });

    // Stadia Alidade Smooth Dark (Grey/Black/White) - still dark but higher contrast/sharper? 
    // OR CartoDB Voyager (Light/Pastel) - clean but very bright.
    // OR 'Jawg Light' or similar. 
    // Let's try "CartoDB Voyager" - it is clean, legible, and professional. 
    // If user said "moc tmavy", they want light.
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);

    // Custom Gold Marker
    const goldIcon = L.divIcon({
        className: 'custom-div-icon',
        html: `<div style="background-color: #d4a373; width: 1.5rem; height: 1.5rem; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.5);"></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });

    // Add Marker
    L.marker([lat, lng], { icon: goldIcon }).addTo(map)
        .bindPopup('<div style="color: black; font-weight: bold; font-family: sans-serif; text-align: center;">America Pod Věží<br>Vchod z náměstí</div>');
}