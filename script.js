// Carousel global variables
let carouselCurrentSlide = 0;
let carouselItems = [];
let carouselDots = [];
let carouselInterval = null;

// Initialize carousel saat DOM siap
function initializeCarousel() {
    carouselItems = document.querySelectorAll('.carousel-item');
    carouselDots = document.querySelectorAll('.dot');
    
    console.log('Carousel initialized with ' + carouselItems.length + ' items');
    
    if (carouselItems.length > 0) {
        showCarouselSlide(0);
        startAutoSlide();
    }
}

// Tampilkan slide tertentu
function showCarouselSlide(n) {
    if (carouselItems.length === 0) return;
    
    // Normalize index
    if (n >= carouselItems.length) {
        carouselCurrentSlide = 0;
    } else if (n < 0) {
        carouselCurrentSlide = carouselItems.length - 1;
    } else {
        carouselCurrentSlide = n;
    }
    
    // Remove active dari semua
    carouselItems.forEach(item => {
        item.classList.remove('active');
    });
    carouselDots.forEach(dot => {
        dot.classList.remove('active');
    });
    
    // Add active ke slide sekarang
    if (carouselItems[carouselCurrentSlide]) {
        carouselItems[carouselCurrentSlide].classList.add('active');
    }
    if (carouselDots[carouselCurrentSlide]) {
        carouselDots[carouselCurrentSlide].classList.add('active');
    }
    
    console.log('Slide changed to: ' + carouselCurrentSlide);
}

// Next slide
function nextSlide() {
    clearInterval(carouselInterval);
    carouselCurrentSlide = (carouselCurrentSlide + 1) % carouselItems.length;
    showCarouselSlide(carouselCurrentSlide);
    startAutoSlide();
}

// Previous slide  
function prevSlide() {
    clearInterval(carouselInterval);
    carouselCurrentSlide = (carouselCurrentSlide - 1 + carouselItems.length) % carouselItems.length;
    showCarouselSlide(carouselCurrentSlide);
    startAutoSlide();
}

// Go to specific slide
function currentSlide(n) {
    clearInterval(carouselInterval);
    carouselCurrentSlide = n;
    showCarouselSlide(n);
    startAutoSlide();
}

// Auto slide setiap 5 detik
function startAutoSlide() {
    carouselInterval = setInterval(() => {
        carouselCurrentSlide = (carouselCurrentSlide + 1) % carouselItems.length;
        showCarouselSlide(carouselCurrentSlide);
    }, 5000);
}

// Get elements
const loginBtn = document.getElementById('loginBtn');
const loginBtnPrompt = document.getElementById('loginBtnPrompt');

// Login button event - now redirects to login.php instead of opening modal
if (loginBtn) {
    loginBtn.onclick = function(e) {
        e.preventDefault();
        window.location.href = 'login.php';
    }
}

// Login prompt button event
if (loginBtnPrompt) {
    loginBtnPrompt.onclick = function(e) {
        e.preventDefault();
        window.location.href = 'login.php';
    }
}

// Initialize carousel ketika DOM sudah siap
document.addEventListener('DOMContentLoaded', function() {
    initializeCarousel();
});
