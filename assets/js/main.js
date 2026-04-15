document.addEventListener("DOMContentLoaded", function () {
  if (typeof AOS !== "undefined") {
    AOS.init({
      duration: 850,
      once: true,
      easing: "ease-out-cubic"
    });
  }

  const menuBtn = document.getElementById("menu-btn");
  const mobileMenu = document.getElementById("mobile-menu");
  const mobileMenuOverlay = document.getElementById("mobile-menu-overlay");
  const mobileLinks = document.querySelectorAll(".mobile-link");

  const contactForm = document.getElementById("contact-form");
  const formSuccess = document.getElementById("form-success");

  const requiredFields = [
    { input: document.getElementById("name"), error: document.getElementById("name-error") },
    { input: document.getElementById("email"), error: document.getElementById("email-error") },
    { input: document.getElementById("message"), error: document.getElementById("message-error") }
  ];

  function closeMenu() {
    if (!mobileMenu || !mobileMenuOverlay || !menuBtn) {
      return;
    }
    mobileMenu.classList.add("invisible", "opacity-0", "translate-y-2");
    mobileMenuOverlay.classList.add("opacity-0", "pointer-events-none");
    document.body.classList.remove("overflow-hidden");
    menuBtn.setAttribute("aria-expanded", "false");
  }

  function openMenu() {
    if (!mobileMenu || !mobileMenuOverlay || !menuBtn) {
      return;
    }
    mobileMenu.classList.remove("invisible", "opacity-0", "translate-y-2");
    mobileMenuOverlay.classList.remove("opacity-0", "pointer-events-none");
    document.body.classList.add("overflow-hidden");
    menuBtn.setAttribute("aria-expanded", "true");
  }

  if (menuBtn && mobileMenu) {
    menuBtn.addEventListener("click", function () {
      if (mobileMenu.classList.contains("invisible")) {
        openMenu();
      } else {
        closeMenu();
      }
    });
  }

  if (mobileMenuOverlay) {
    mobileMenuOverlay.addEventListener("click", closeMenu);
  }

  mobileLinks.forEach(function (link) {
    link.addEventListener("click", closeMenu);
  });

  window.addEventListener("resize", function () {
    if (window.innerWidth >= 1024) {
      closeMenu();
    }
  });

  requiredFields.forEach(function (field) {
    if (!field.input || !field.error) {
      return;
    }
    field.input.addEventListener("input", function () {
      field.error.classList.add("hidden");
      field.input.classList.remove("border-rose-400");
    });
  });

  if (contactForm) {
    contactForm.addEventListener("submit", function (event) {
      event.preventDefault();
      let isValid = true;

      requiredFields.forEach(function (field) {
        if (!field.input || !field.error) {
          return;
        }

        const value = field.input.value.trim();
        const isEmail = field.input.id === "email";
        const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        const fieldValid = value.length > 0 && (!isEmail || validEmail);

        if (!fieldValid) {
          isValid = false;
          field.error.classList.remove("hidden");
          field.input.classList.add("border-rose-400");
        } else {
          field.error.classList.add("hidden");
          field.input.classList.remove("border-rose-400");
        }
      });

      if (!isValid) {
        if (formSuccess) {
          formSuccess.classList.add("hidden");
        }
        return;
      }

      if (formSuccess) {
        formSuccess.classList.remove("hidden");
      }
      contactForm.reset();
    });
  }
});
