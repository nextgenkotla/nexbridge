(function () {
  var toggle = document.querySelector(".nav-toggle");
  var nav = document.getElementById("site-nav");
  var mobile = window.matchMedia("(max-width: 1100px)");

  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", String(open));
      toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
      document.body.classList.toggle("nav-open", open);
    });
  }

  document.querySelectorAll(".sub-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var item = btn.closest(".has-menu");
      var open = !item.classList.contains("open");
      document.querySelectorAll(".has-menu.open").forEach(function (other) {
        if (other !== item) {
          other.classList.remove("open");
          other.querySelector(".sub-toggle").setAttribute("aria-expanded", "false");
        }
      });
      item.classList.toggle("open", open);
      btn.setAttribute("aria-expanded", String(open));
    });
  });

  document.addEventListener("keydown", function (ev) {
    if (ev.key !== "Escape") return;
    document.querySelectorAll(".has-menu.open").forEach(function (item) {
      item.classList.remove("open");
      item.querySelector(".sub-toggle").setAttribute("aria-expanded", "false");
    });
    if (nav && nav.classList.contains("open") && mobile.matches) toggle.click();
  });

  document.addEventListener("click", function (ev) {
    if (mobile.matches || ev.target.closest(".has-menu")) return;
    document.querySelectorAll(".has-menu.open").forEach(function (item) {
      item.classList.remove("open");
      item.querySelector(".sub-toggle").setAttribute("aria-expanded", "false");
    });
  });

  // Pre-select a service passed as ?interest= on booking links.
  var params = new URLSearchParams(window.location.search);
  var interest = params.get("interest");
  if (interest) {
    document.querySelectorAll("select[data-interest]").forEach(function (sel) {
      var match = Array.prototype.find.call(sel.options, function (o) {
        return o.value.toLowerCase() === interest.toLowerCase();
      });
      if (match) sel.value = match.value;
      else if (interest === "apply") {
        var msg = sel.form.querySelector("textarea");
        if (msg && !msg.value) msg.value = "I would like to apply.";
      }
    });
  }

  document.querySelectorAll("[data-page-field]").forEach(function (f) {
    f.value = window.location.pathname;
  });

  document.querySelectorAll("[data-min-today]").forEach(function (input) {
    var d = new Date();
    input.min = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  });

  // Show a server-side validation error when the form handler redirects back.
  if (params.get("form_error")) {
    var status = document.querySelector("[data-form-error]");
    if (status) {
      status.hidden = false;
      status.closest("form").scrollIntoView({ block: "center" });
    }
  }

  document.querySelectorAll("form[data-validate]").forEach(function (form) {
    form.addEventListener("submit", function (ev) {
      var ok = true;
      form.querySelectorAll("[required]").forEach(function (field) {
        var wrap = field.closest(".field");
        var valid = field.checkValidity();
        if (wrap) wrap.classList.toggle("invalid", !valid);
        if (!valid) ok = false;
      });
      var status = form.querySelector("[data-form-error]");
      if (!ok) {
        ev.preventDefault();
        if (status) status.hidden = false;
        var first = form.querySelector(".invalid input, .invalid select, [required]:invalid");
        if (first) first.focus();
      } else {
        var btn = form.querySelector("button[type=submit]");
        if (btn) { btn.disabled = true; btn.lastChild.textContent = " Sending..."; }
      }
    });
  });

  // Knowledge Center category filter.
  var chips = document.querySelectorAll(".chip[data-filter]");
  var posts = document.querySelectorAll("[data-posts] [data-category]");
  var empty = document.querySelector("[data-empty]");
  chips.forEach(function (chip) {
    chip.addEventListener("click", function () {
      var cat = chip.getAttribute("data-filter");
      chips.forEach(function (c) { c.classList.toggle("active", c === chip); });
      var shown = 0;
      posts.forEach(function (p) {
        var match = cat === "all" || p.getAttribute("data-category") === cat;
        p.hidden = !match;
        if (match) shown++;
      });
      if (empty) empty.hidden = shown !== 0;
    });
  });
})();
