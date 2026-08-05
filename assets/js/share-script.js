document.addEventListener("DOMContentLoaded", function () {
    const container = document.getElementById("productShareContainer");
    if (!container) return; // Share section not on this page

    const WA_BTN = container.querySelector(".share-whatsapp");
    const FB_BTN = container.querySelector(".share-facebook");
    const TG_BTN = container.querySelector(".share-telegram");
    const COPY_BTN = container.querySelector(".share-copylink");

    // Dynamically grab current URL and Product Name
    let currentHref = window.location.href;
    if (currentHref.indexOf('?') === -1) {
        currentHref += '?v=' + Math.floor(Date.now() / 3600000);
    }
    const productUrl = encodeURIComponent(currentHref);
    const productNameRaw = container.getAttribute("data-title") || document.title;
    const productName = encodeURIComponent(productNameRaw);

    if (WA_BTN) {
        WA_BTN.addEventListener("click", () => {
            const waLink = `https://wa.me/?text=${productName}%20${productUrl}`;
            window.open(waLink, "_blank", "width=800,height=600");
        });
    }

    if (FB_BTN) {
        FB_BTN.addEventListener("click", () => {
            const fbLink = `https://www.facebook.com/sharer/sharer.php?u=${productUrl}`;
            window.open(fbLink, "_blank", "width=600,height=500");
        });
    }

    if (TG_BTN) {
        TG_BTN.addEventListener("click", () => {
            const tgLink = `https://t.me/share/url?url=${productUrl}&text=${productName}`;
            window.open(tgLink, "_blank", "width=600,height=500");
        });
    }

    if (COPY_BTN) {
        COPY_BTN.addEventListener("click", () => {
            const urlToCopy = window.location.href;
            const tooltip = COPY_BTN.nextElementSibling;

            // Fallback for older browsers
            if (navigator.clipboard) {
                navigator.clipboard.writeText(urlToCopy).then(() => {
                    showTooltip(tooltip);
                }).catch(err => {
                    console.error("Failed to copy link: ", err);
                });
            } else {
                // Ugly hack for very old browsers if needed, but modern API preferred
                const tempInput = document.createElement("input");
                tempInput.value = urlToCopy;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand("copy");
                document.body.removeChild(tempInput);
                showTooltip(tooltip);
            }
        });
    }

    function showTooltip(tooltipElement) {
        if (!tooltipElement) return;

        // Remove d-none if it's there
        if (tooltipElement.classList.contains("d-none")) {
            tooltipElement.classList.remove("d-none");
        }

        // Add show class for CSS transitions
        tooltipElement.classList.add("show");

        // Hide after 2 seconds
        setTimeout(() => {
            tooltipElement.classList.remove("show");
            setTimeout(() => {
                tooltipElement.classList.add("d-none");
            }, 300); // Wait for transition to finish
        }, 2000);
    }
});
