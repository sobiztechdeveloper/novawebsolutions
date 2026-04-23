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
  const formNextUrl = document.getElementById("form-next-url");
  const formError = document.getElementById("form-error");
  const successPopup = document.getElementById("success-popup");
  const successPopupClose = document.getElementById("success-popup-close");

  const requiredFields = [
    { input: document.getElementById("name"), error: document.getElementById("name-error") },
    { input: document.getElementById("email"), error: document.getElementById("email-error") },
    { input: document.getElementById("message"), error: document.getElementById("message-error") }
  ];

  function showSuccessPopup() {
    if (!successPopup) {
      return;
    }
    successPopup.classList.remove("hidden");
    successPopup.classList.add("flex");
  }

  function closeSuccessPopup() {
    if (!successPopup) {
      return;
    }
    successPopup.classList.add("hidden");
    successPopup.classList.remove("flex");
  }

  if (successPopupClose) {
    successPopupClose.addEventListener("click", closeSuccessPopup);
  }

  if (successPopup) {
    successPopup.addEventListener("click", function (event) {
      if (event.target === successPopup) {
        closeSuccessPopup();
      }
    });
  }

  const params = new URLSearchParams(window.location.search);
  if (params.get("submitted") === "1") {
    showSuccessPopup();
    params.delete("submitted");
    const cleanQuery = params.toString();
    const newUrl =
      window.location.pathname +
      (cleanQuery ? "?" + cleanQuery : "") +
      window.location.hash;
    window.history.replaceState({}, "", newUrl);
  }

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
        if (formError) {
          formError.classList.add("hidden");
        }
        return;
      }

      if (window.location.protocol === "file:") {
        if (formError) {
          formError.textContent = "FormSubmit does not work from file:/// preview. Please upload to Hostinger or run a local web server (for example: python -m http.server) and try again.";
          formError.classList.remove("hidden");
        }
        return;
      }

      if (formNextUrl) {
        formNextUrl.value = window.location.origin + window.location.pathname + "?submitted=1#contact";
      }

      if (formError) {
        formError.classList.add("hidden");
      }

      contactForm.submit();
    });
  }
});
