/* ============================================
   JAVASCRIPT MENU - Toggles et interactions
   ============================================ */

function toggleAccountMenu() {
    var menu = document.getElementById("accountDropdown");
    if (menu.style.display === "block") {
        menu.style.display = "none";
    } else {
        menu.style.display = "block";
    }
}

window.onclick = function(event) {
    if (!event.target.closest('.account-menu')) {
        var dropdown = document.getElementById("accountDropdown");
        if (dropdown) dropdown.style.display = "none";
    }
}
