const menuButton = document.getElementById("mobileMenuBtn");
const mobileMenu = document.getElementById("mobileMenu");

if (menuButton && mobileMenu) {
    menuButton.addEventListener("click", () => {
        mobileMenu.classList.toggle("active");
    });
}

window.addEventListener("resize", () => {
    if (window.innerWidth > 700) {
        mobileMenu.classList.remove("active");
    }
});