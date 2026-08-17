document.addEventListener("DOMContentLoaded", function () {
    // ---- Configuration ----
    // Phone number and message are injected by PHP via window.WA_WIDGET_CONFIG (see footer.php).
    // Fallback values are used if the config is missing (e.g., static HTML preview).
    const _cfg = window.WA_WIDGET_CONFIG || {};
    const phoneNumber = _cfg.phoneNumber || "918573934013"; // Replace with your WhatsApp number without '+'
    const prefillMessage = _cfg.prefillMessage || "Hello, I have a question about your products.";

    // Advanced Options
    const autoShowDelay = 5000; // Auto-show after 5 seconds (in ms)

    // Working Hours Configuration
    const restrictWorkingHours = false; // Set to true to show ONLY during working hours
    const startHour = 9; // 9 AM
    const endHour = 18;  // 6 PM
    // -----------------------

    const widget = document.getElementById("whatsapp-widget");
    const link = document.getElementById("whatsapp-link");

    if (!widget || !link) return;

    // Set href attribute dynamically with the phone number and prefill message
    const encodedMessage = encodeURIComponent(prefillMessage);
    link.href = `https://wa.me/${phoneNumber}?text=${encodedMessage}`;

    // Function to check if current time is within working hours
    function isWithinWorkingHours() {
        if (!restrictWorkingHours) return true;
        const currentHour = new Date().getHours();
        return currentHour >= startHour && currentHour < endHour;
    }

    // Show widget based on rules
    if (isWithinWorkingHours()) {
        setTimeout(function () {
            widget.classList.add("show");
        }, autoShowDelay);
    } else {
        // You can leave it completely hidden or log it
        console.log("WhatsApp widget hidden: Outside working hours");
    }
});
