        // Animation on scroll
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize animations
            initAnimations();
            
            // Initialize counters
            initCounters();
            
            // Nav scroll effect
            window.addEventListener('scroll', function() {
                const nav = document.getElementById('nav');
                if (window.scrollY > 50) {
                    nav.classList.add('shrink');
                } else {
                    nav.classList.remove('shrink');
                }
            });
            
            // Tickets CTA pulse animation
            const ticketsCTA = document.getElementById('tickets-cta');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('pulse');
                    }
                });
            }, { threshold: 0.5 });
            
            observer.observe(ticketsCTA);
        });

        // --- 4. Animations: Parallax Tilt (Minimal implementation) ---
    // Note: A full tilt library (e.g., vanilla-tilt.js) is preferred for a production site.
    const mediaContainer = document.querySelector('.parallax-tilt-anim');
    if (mediaContainer && !reducedMotion) {
        mediaContainer.addEventListener('mousemove', (e) => {
            const rect = mediaContainer.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;

            const x = (e.clientX - centerX) / (rect.width / 2); // -1 to 1
            const y = (e.clientY - centerY) / (rect.height / 2); // -1 to 1

            const tiltX = y * -1 * 10; // Invert and multiply by strength 10
            const tiltY = x * 1 * 10;

            mediaContainer.style.transform = `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg) scale(1.02)`;
        });

        mediaContainer.addEventListener('mouseleave', () => {
            mediaContainer.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) scale(1)`;
        });
    }
        
        function initAnimations() {
            const fadeUpElements = document.querySelectorAll('.fade-up');
            const staggerElements = document.querySelectorAll('.stagger-fade');
            const slideUpElements = document.querySelectorAll('.slide-up');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate');
                        
                        // For stagger animations, animate children with delay
                        if (entry.target.classList.contains('stagger-fade')) {
                            const children = entry.target.children;
                            for (let i = 0; i < children.length; i++) {
                                children[i].style.transitionDelay = `${i * 0.12}s`;
                            }
                        }
                    }
                });
            }, { threshold: 0.1 });
            
            fadeUpElements.forEach(el => observer.observe(el));
            staggerElements.forEach(el => observer.observe(el));
            slideUpElements.forEach(el => observer.observe(el));
        }
        
        function initCounters() {
            const counters = document.querySelectorAll('.counter-value[data-target]');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const counter = entry.target;
                        const target = parseInt(counter.getAttribute('data-target'));
                        const duration = 1200; // ms
                        const step = target / (duration / 16); // 60fps
                        let current = 0;
                        
                        const timer = setInterval(() => {
                            current += step;
                            if (current >= target) {
                                current = target;
                                clearInterval(timer);
                            }
                            
                            if (counter.getAttribute('data-target').startsWith('$')) {
                                counter.textContent = '$' + Math.floor(current).toLocaleString();
                            } else {
                                counter.textContent = Math.floor(current).toLocaleString();
                            }
                        }, 16);
                        
                        observer.unobserve(counter);
                    }
                });
            }, { threshold: 0.5 });
            
            counters.forEach(counter => observer.observe(counter));
        }
 
 // -- Menu Toggle --       
 document.addEventListener("DOMContentLoaded", function() {
            const menuToggle = document.querySelector(".menu-toggle");
            const navLinks = document.querySelector(".nav-links");

            menuToggle.addEventListener("click", () => {
                navLinks.classList.toggle("show");
            });
        });


        //-- FORM SUBMISSION HANDLING

        // List of countries (ISO standard)
const countries = [
    "Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda","Argentina",
    "Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados",
    "Belarus","Belgium","Belize","Benin","Bhutan","Bolivia","Bosnia and Herzegovina",
    "Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cabo Verde","Cambodia",
    "Cameroon","Canada","Central African Republic","Chad","Chile","China","Colombia","Comoros",
    "Congo (Congo-Brazzaville)","Costa Rica","Croatia","Cuba","Cyprus","Czechia","Denmark",
    "Djibouti","Dominica","Dominican Republic","DR Congo","Ecuador","Egypt","El Salvador",
    "Equatorial Guinea","Eritrea","Estonia","Eswatini","Ethiopia","Fiji","Finland","France",
    "Gabon","Gambia","Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea",
    "Guinea-Bissau","Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran",
    "Iraq","Ireland","Israel","Italy","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kiribati",
    "Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein",
    "Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta",
    "Marshall Islands","Mauritania","Mauritius","Mexico","Micronesia","Moldova","Monaco",
    "Mongolia","Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepal",
    "Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Korea","North Macedonia",
    "Norway","Oman","Pakistan","Palau","Panama","Papua New Guinea","Paraguay","Peru",
    "Philippines","Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saint Kitts and Nevis",
    "Saint Lucia","Saint Vincent and the Grenadines","Samoa","San Marino","Sao Tome and Principe",
    "Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone","Singapore","Slovakia","Slovenia",
    "Solomon Islands","Somalia","South Africa","South Korea","South Sudan","Spain","Sri Lanka",
    "Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand",
    "Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey","Turkmenistan","Tuvalu",
    "Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan",
    "Vanuatu","Vatican City","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"
];

const countrySelect = document.getElementById("countrySelect");
countries.forEach(country => {
    const option = document.createElement("option");
    option.value = country;
    option.textContent = country;
    countrySelect.appendChild(option);
});

        // AJAX (NORELOAD) - at bottom of file, replace previous subscribe code
document.getElementById("newsletterForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(this);

    // Convert FormData to JSON-friendly object
    const obj = {};
    formData.forEach((v,k) => obj[k] = v);

    fetch("/api/subscribe", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(obj)
    })
    .then(res => res.json())
    .then(data => {
        if (data && data.success) {
            alert("Thank you! You are now subscribed.");
            form.reset();
        } else {
            alert(data.message || "Submission failed. Try again.");
        }
    })
    .catch(err => {
        console.error(err);
        alert("Submission failed. Try again.");
    });
});