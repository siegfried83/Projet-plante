/* ============================================
   JAVASCRIPT CULTURES PAGE
   ============================================ */

// Rafraîchissement des images caméra sans freeze : préchargement avant swap
function refreshCamImages() {
    const images = document.querySelectorAll('.cam-image');
    const timestamp = new Date().getTime();
    images.forEach(function(img) {
        const baseSrc = img.getAttribute('data-base-src');
        if (!baseSrc) return;
        const preloader = new Image();
        preloader.onload = function() {
            img.src = preloader.src;
        };
        preloader.src = baseSrc + '?v=' + timestamp;
    });
}
setInterval(refreshCamImages, 3000);
