/* Nexbridge admin panel. Plain JavaScript, no build step. */
(function () {
  "use strict";

  var S = { csrf: "", user: null, schema: null, dirty: false, publishTimer: null, savedAt: 0 };
  var app = document.getElementById("app");

  // ------------------------------------------------------------ helpers
  function h(tag, attrs) {
    var el = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === null || v === undefined || v === false) return;
        if (k === "class") el.className = v;
        else if (k === "text") el.textContent = v;
        else if (k.slice(0, 2) === "on") el.addEventListener(k.slice(2), v);
        else if (k === "value") el.value = v;
        else if (k === "checked") el.checked = !!v;
        else el.setAttribute(k, v === true ? "" : v);
      });
    }
    for (var i = 2; i < arguments.length; i++) add(el, arguments[i]);
    return el;
  }
  function add(el, c) {
    if (arguments.length > 2) { for (var i = 1; i < arguments.length; i++) add(el, arguments[i]); return; }
    if (c === null || c === undefined || c === false) return;
    if (Array.isArray(c)) { c.forEach(function (x) { add(el, x); }); return; }
    el.appendChild(typeof c === "string" || typeof c === "number" ? document.createTextNode(String(c)) : c);
  }
  function clear(el) { while (el.firstChild) el.removeChild(el.firstChild); return el; }

  function api(action, opts) {
    opts = opts || {};
    var url = "/admin/api.php?a=" + encodeURIComponent(action) + (opts.query || "");
    var init = { method: opts.body ? "POST" : "GET", credentials: "same-origin", headers: {} };
    if (opts.body) {
      init.headers["Content-Type"] = "application/json";
      init.headers["X-CSRF-Token"] = S.csrf;
      init.body = JSON.stringify(opts.body);
    }
    return fetch(url, init).then(function (res) {
      return res.json().catch(function () { return { error: "The server sent an unexpected response (" + res.status + ")." }; }).then(function (data) {
        if (res.status === 401 && action !== "login") {
          S.user = null;
          S.dirty = false;
          render();
        }
        if (!res.ok || data.error) {
          var err = new Error(data.error || "Something went wrong (" + res.status + ").");
          err.status = res.status;
          throw err;
        }
        return data;
      });
    }, function () { throw new Error("Could not reach the server. Check your internet connection."); });
  }

  var toastBox;
  function toast(msg, kind) {
    if (!toastBox) { toastBox = h("div", { class: "toasts", role: "status", "aria-live": "polite" }); document.body.appendChild(toastBox); }
    var t = h("div", { class: "toast " + (kind || "") }, msg);
    toastBox.appendChild(t);
    setTimeout(function () { t.classList.add("out"); setTimeout(function () { t.remove(); }, 300); }, kind === "error" ? 7000 : 3500);
  }

  function slugify(s) {
    return String(s || "").toLowerCase().normalize("NFKD").replace(/[̀-ͯ]/g, "")
      .replace(/ı/g, "i").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 80);
  }
  function today() { var d = new Date(); return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }
  function pad(n) { return (n < 10 ? "0" : "") + n; }
  function fmtDate(s) {
    if (!s) return "";
    var d = new Date(s.replace(" UTC", "Z").replace(" ", "T"));
    if (isNaN(d)) return s;
    return d.toLocaleString(undefined, { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
  }
  function icon(name) {
    var paths = {
      home: "M3 11l9-7 9 7v9H3zM9 20v-6h6v6", inbox: "M3 13h5l1 3h6l1-3h5M5 5h14l2 8v6H3v-6z", edit: "M4 20h4L19 9l-4-4L4 16zM14 6l4 4",
      user: "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0", out: "M15 12H4M8 8l-4 4 4 4M13 4h7v16h-7",
      ext: "M14 4h6v6M20 4l-9 9M18 14v6H4V6h6", up: "M12 19V5M6 11l6-6 6 6", down: "M12 5v14M18 13l-6 6-6-6", trash: "M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13",
      plus: "M12 5v14M5 12h14", menu: "M3 6h18M3 12h18M3 18h18", img: "M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M15 9a1 1 0 1 0 0-.01",
      phone: "M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2", mail: "M3 6h18v12H3zM3 7l9 6 9-6",
      chat: "M4 5h16v11H8l-4 4z", dl: "M12 4v12M6 10l6 6 6-6M4 20h16", check: "M5 12l5 5 9-10"
    };
    var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("viewBox", "0 0 24 24"); svg.setAttribute("class", "i"); svg.setAttribute("aria-hidden", "true");
    var p = document.createElementNS("http://www.w3.org/2000/svg", "path");
    p.setAttribute("d", paths[name] || ""); svg.appendChild(p);
    return svg;
  }
  function imgSrc(path) { return path; }
  function rawUrl(path) { return "https://raw.githubusercontent.com/" + S.schema.repo + "/" + S.schema.branch + path; }
  function previewImg(path, cls) {
    var img = h("img", { class: cls || "", alt: "", src: imgSrc(path), loading: "lazy" });
    img.addEventListener("error", function once() {
      img.removeEventListener("error", once);
      if (S.schema) img.src = rawUrl(path); // newly uploaded images exist on GitHub before they reach the website
    });
    return img;
  }

  // ------------------------------------------------------------ routing
  function route() { return (location.hash || "#/").slice(1); }
  window.addEventListener("beforeunload", function (e) { if (S.dirty) { e.preventDefault(); e.returnValue = ""; } });
  var lastRoute = route();
  window.addEventListener("hashchange", function () {
    if (S.dirty && route() !== lastRoute) {
      if (!confirm("You have unsaved changes. Leave this page without saving?")) {
        history.replaceState(null, "", "#" + lastRoute);
        return;
      }
      S.dirty = false;
    }
    lastRoute = route();
    render();
  });

  function start() {
    api("state").then(function (st) {
      S.csrf = st.csrf; S.user = st.user; S.configured = st.configured;
      render();
    }).catch(function (err) { clear(app); app.className = "boot"; add(app, h("div", { class: "boot-msg" }, err.message)); });
  }

  function render() {
    if (!S.configured) return setupView();
    if (!S.user) return loginView();
    if (!S.schema) {
      return api("schema").then(function (s) { S.schema = s; render(); }).catch(function (e) { toast(e.message, "error"); });
    }
    layout();
  }

  // ------------------------------------------------------------ setup & login
  function authShell(title, lead, form) {
    clear(app); app.className = "auth";
    add(app, h("div", { class: "auth-card" },
      h("img", { src: "/assets/img/logo.png", alt: "Nexbridge Solutions", class: "auth-logo", width: 180 }),
      h("h1", null, title), lead ? h("p", { class: "muted" }, lead) : null, form));
  }
  function errBox() { return h("div", { class: "form-error", role: "alert", hidden: true }); }
  function showErr(box, msg) { box.textContent = msg; box.hidden = !msg; }
  function field(label, input, help) {
    return h("label", { class: "fld" }, h("span", { class: "fld-label" }, label), input, help ? h("span", { class: "fld-help" }, help) : null);
  }

  function setupView() {
    var token = h("input", { type: "password", autocomplete: "off", required: true, spellcheck: "false" });
    var user = h("input", { type: "text", autocomplete: "username", required: true, value: "admin" });
    var pass = h("input", { type: "password", autocomplete: "new-password", required: true, minlength: 10 });
    var pass2 = h("input", { type: "password", autocomplete: "new-password", required: true });
    var err = errBox();
    var btn = h("button", { class: "btn primary block", type: "submit" }, "Finish setup");
    var form = h("form", { onsubmit: function (e) {
      e.preventDefault();
      if (pass.value !== pass2.value) return showErr(err, "The two passwords do not match.");
      btn.disabled = true; btn.textContent = "Checking the token…";
      api("setup", { body: { token: token.value, username: user.value, password: pass.value } }).then(function (r) {
        S.configured = true; S.user = r.user; render();
      }).catch(function (e2) { showErr(err, e2.message); btn.disabled = false; btn.textContent = "Finish setup"; });
    } },
      h("ol", { class: "setup-steps" },
        h("li", null, "Sign in to GitHub as the owner of the ", h("strong", null, "nexbridge"), " repository and open ",
          h("a", { href: "https://github.com/settings/personal-access-tokens/new", target: "_blank", rel: "noopener" }, "Settings › Developer settings › Fine-grained tokens › Generate new token"), "."),
        h("li", null, "Repository access: ", h("strong", null, "Only select repositories"), " › nexbridge."),
        h("li", null, "Permissions: ", h("strong", null, "Contents: Read and write"), " and ", h("strong", null, "Actions: Read-only"), ". Set the expiration as long as you are comfortable with."),
        h("li", null, "Generate the token, copy it and paste it below. It is stored only on this server.")),
      field("GitHub access token", token),
      field("Admin username", user),
      field("Admin password", pass, "At least 10 characters."),
      field("Repeat password", pass2),
      err, btn);
    authShell("Set up the admin panel", "One-time setup. The admin panel saves your edits to the website's GitHub repository, which then rebuilds and publishes the site automatically.", form);
  }

  function loginView() {
    var user = h("input", { type: "text", autocomplete: "username", required: true, autofocus: true });
    var pass = h("input", { type: "password", autocomplete: "current-password", required: true });
    var err = errBox();
    var btn = h("button", { class: "btn primary block", type: "submit" }, "Log in");
    var form = h("form", { onsubmit: function (e) {
      e.preventDefault(); btn.disabled = true;
      api("login", { body: { username: user.value, password: pass.value } }).then(function (r) {
        S.user = r.user; render();
      }).catch(function (e2) { showErr(err, e2.message); btn.disabled = false; pass.value = ""; pass.focus(); });
    } }, field("Username", user), field("Password", pass), err, btn);
    authShell("Admin login", null, form);
    user.focus();
  }

  // ------------------------------------------------------------ layout
  var main, publishBar;
  function layout() {
    clear(app); app.className = "shell";
    var r = route();
    var groups = {};
    Object.keys(S.schema.collections).forEach(function (k) {
      var c = S.schema.collections[k];
      (groups[c.group] = groups[c.group] || []).push([k, c.label]);
    });
    var nav = h("nav", { class: "side-nav", "aria-label": "Admin sections" },
      navLink("#/", "Dashboard", "home", r === "/"),
      navLink("#/inquiries", "Inquiries & bookings", "inbox", r === "/inquiries"),
      Object.keys(groups).map(function (g) {
        return [h("div", { class: "nav-group" }, g), groups[g].map(function (x) { return navLink("#/edit/" + x[0], x[1], "edit", r === "/edit/" + x[0]); })];
      }),
      h("div", { class: "nav-group" }, "Account"),
      navLink("#/account", "Users & settings", "user", r === "/account"),
      h("a", { class: "nav-link", href: "/", target: "_blank", rel: "noopener" }, icon("ext"), h("span", null, "View website")),
      h("button", { class: "nav-link", type: "button", onclick: logout }, icon("out"), h("span", null, "Log out"))
    );
    var side = h("aside", { class: "side" },
      h("a", { class: "side-brand", href: "#/" }, h("img", { src: "/assets/img/logo-white.png", alt: "Nexbridge Solutions", width: 150 }), h("span", null, "Admin")),
      nav);
    var toggle = h("button", { class: "menu-btn", type: "button", "aria-label": "Menu", onclick: function () { document.body.classList.toggle("side-open"); } }, icon("menu"));
    publishBar = h("div", { class: "publish-bar", hidden: true });
    main = h("main", { class: "main", id: "main" });
    add(app, [side, h("div", { class: "content" }, h("header", { class: "topbar" }, toggle, h("span", { class: "who" }, "Signed in as ", h("strong", null, S.user))), publishBar, main)]);
    document.body.classList.remove("side-open");
    if (S.publishTimer) showPublishing();

    if (r === "/") dashboard();
    else if (r === "/inquiries") inquiries();
    else if (r === "/account") account();
    else if (r.indexOf("/edit/") === 0 && S.schema.collections[r.slice(6)]) editor(r.slice(6));
    else { location.hash = "#/"; }
    window.scrollTo(0, 0);
  }
  function navLink(href, label, ic, active) {
    return h("a", { class: "nav-link" + (active ? " active" : ""), href: href, "aria-current": active ? "page" : null }, icon(ic), h("span", null, label));
  }
  function logout() {
    if (S.dirty && !confirm("You have unsaved changes. Log out anyway?")) return;
    api("logout", { body: {} }).then(function (r) { S.csrf = r.csrf; S.user = null; S.schema = null; S.dirty = false; location.hash = "#/"; render(); });
  }
  function pageHead(title, lead, actions) {
    return h("div", { class: "page-head" }, h("div", null, h("h1", null, title), lead ? h("p", { class: "muted" }, lead) : null), actions ? h("div", { class: "page-actions" }, actions) : null);
  }

  // ------------------------------------------------------------ publishing status
  function startPublishWatch() {
    S.savedAt = Date.now();
    if (S.publishTimer) clearInterval(S.publishTimer);
    S.publishState = "waiting";
    showPublishing();
    S.publishTimer = setInterval(checkPublish, 8000);
    setTimeout(checkPublish, 4000);
  }
  function checkPublish() {
    api("build_status").then(function (r) {
      var run = r.run;
      if (!run) { S.publishState = "unknown"; return finishPublish(); }
      var started = Date.parse(run.created_at);
      if (started < S.savedAt - 60000) { if (Date.now() - S.savedAt > 180000) { S.publishState = "unknown"; finishPublish(); } return; }
      if (run.status !== "completed") { S.publishState = "building"; return showPublishing(); }
      S.publishState = run.conclusion === "success" ? "done" : "failed";
      S.publishUrl = run.url;
      finishPublish();
    }).catch(function () { S.publishState = "unknown"; finishPublish(); });
  }
  function finishPublish() { clearInterval(S.publishTimer); S.publishTimer = null; showPublishing(); }
  function showPublishing() {
    if (!publishBar) return;
    clear(publishBar); publishBar.hidden = false;
    var st = S.publishState;
    publishBar.className = "publish-bar " + st;
    if (st === "waiting" || st === "building") add(publishBar, [h("span", { class: "spinner" }), "Publishing your changes. This usually takes 1 to 2 minutes; you can keep working."]);
    else if (st === "done") add(publishBar, [icon("check"), "Published. Your changes will be live on the website within a minute (refresh the page to see them). ", closeBtn()]);
    else if (st === "failed") add(publishBar, ["The website could not be rebuilt from your last change, so the live site was not changed. ",
      S.publishUrl ? h("a", { href: S.publishUrl, target: "_blank", rel: "noopener" }, "See the details") : null, " or contact your developer. ", closeBtn()]);
    else add(publishBar, ["Saved. Your changes will appear on the website in a few minutes. ", closeBtn()]);
  }
  function closeBtn() { return h("button", { class: "link-btn", type: "button", onclick: function () { publishBar.hidden = true; S.publishState = null; } }, "Dismiss"); }

  // ------------------------------------------------------------ dashboard
  function dashboard() {
    var newCount = h("strong", { class: "stat-num" }, "…");
    add(main, pageHead("Welcome, " + S.user, "Manage the Nexbridge Solutions website. Changes you save are published automatically."));
    var tiles = h("div", { class: "tiles" },
      h("a", { class: "tile stat", href: "#/inquiries" }, icon("inbox"), newCount, h("span", null, "new inquiries and bookings")));
    Object.keys(S.schema.collections).forEach(function (k) {
      var c = S.schema.collections[k];
      add(tiles, h("a", { class: "tile", href: "#/edit/" + k }, icon("edit"), h("strong", null, c.label), h("span", null, c.intro || c.group)));
    });
    add(main, tiles);
    add(main, h("section", { class: "panel help" }, h("h2", null, "How publishing works"),
      h("ol", null,
        h("li", null, "Edit a section and press ", h("strong", null, "Save and publish"), "."),
        h("li", null, "The website rebuilds itself automatically; a bar at the top shows the progress."),
        h("li", null, "About two minutes later the change is live. Refresh the website to see it.")),
      h("p", { class: "muted" }, "Form submissions from the website appear under Inquiries & bookings straight away.")));
    api("inquiries").then(function (r) {
      newCount.textContent = r.rows.filter(function (x) { return x.status === "new"; }).length;
    }).catch(function () { newCount.textContent = "–"; });
  }

  // ------------------------------------------------------------ inquiries
  function inquiries() {
    var rows = [], filter = { status: "open", type: "all", q: "" };
    var list = h("div", { class: "inq-list" }, h("p", { class: "muted" }, "Loading…"));
    var statusSel = h("select", { onchange: function () { filter.status = statusSel.value; draw(); } },
      h("option", { value: "open" }, "New and contacted"), h("option", { value: "new" }, "New"), h("option", { value: "contacted" }, "Contacted"),
      h("option", { value: "closed" }, "Closed"), h("option", { value: "all" }, "All"));
    var typeSel = h("select", { onchange: function () { filter.type = typeSel.value; draw(); } },
      h("option", { value: "all" }, "Inquiries and bookings"), h("option", { value: "inquiry" }, "Inquiries only"), h("option", { value: "booking" }, "Bookings only"));
    var search = h("input", { type: "search", placeholder: "Search name, phone, email, service…", oninput: function () { filter.q = search.value.toLowerCase(); draw(); } });
    add(main, pageHead("Inquiries & bookings", "Messages and appointment requests sent through the website forms.",
      h("a", { class: "btn", href: "/admin/api.php?a=inquiries_csv" }, icon("dl"), "Download spreadsheet")));
    add(main, h("div", { class: "filters" }, statusSel, typeSel, search), list);

    function load() {
      api("inquiries").then(function (r) { rows = r.rows; draw(); }).catch(function (e) { clear(list); add(list, h("p", { class: "form-error" }, e.message)); });
    }
    function draw() {
      clear(list);
      var shown = rows.filter(function (r) {
        if (filter.status === "open" && r.status === "closed") return false;
        if (filter.status !== "open" && filter.status !== "all" && r.status !== filter.status) return false;
        if (filter.type !== "all" && r.type !== filter.type) return false;
        if (filter.q && [r.name, r.phone, r.email, r.service, r.message].join(" ").toLowerCase().indexOf(filter.q) === -1) return false;
        return true;
      });
      if (!rows.length) return add(list, h("div", { class: "empty" }, icon("inbox"), h("p", null, "No submissions yet. When someone sends the inquiry or booking form on the website, it appears here.")));
      if (!shown.length) return add(list, h("p", { class: "muted" }, "Nothing matches these filters."));
      shown.forEach(function (r) { add(list, card(r)); });
    }
    function card(r) {
      var phoneDigits = (r.phone || "").replace(/\D/g, "");
      var wa = phoneDigits ? "https://wa.me/" + (phoneDigits.indexOf("0") === 0 ? "92" + phoneDigits.slice(1) : phoneDigits) : null;
      var details = h("dl", { class: "inq-details" },
        r.type === "booking" ? [dt("Preferred date", r.date || "Not given"), dt("Preferred time", r.time || "Not given"), dt("Meeting type", r.mode || "Not given")] : null,
        dt("Sent from page", r.page || ""), dt("Received", fmtDate(r.received)));
      var setStatus = function (st) {
        return function () {
          api("inquiry_status", { body: { ids: [r.id], status: st } }).then(function () { r.status = st; draw(); toast("Marked as " + st + "."); }).catch(function (e) { toast(e.message, "error"); });
        };
      };
      return h("details", { class: "inq " + r.status },
        h("summary", null,
          h("span", { class: "pill " + r.status }, r.status),
          h("span", { class: "inq-name" }, r.name || "(no name)"),
          h("span", { class: "inq-svc" }, (r.type === "booking" ? "Booking · " : "") + (r.service || "General inquiry")),
          h("time", null, fmtDate(r.received))),
        h("div", { class: "inq-body" },
          r.message ? h("p", { class: "inq-msg" }, r.message) : h("p", { class: "muted" }, "No message."),
          h("div", { class: "inq-contact" },
            r.phone ? h("a", { class: "btn small", href: "tel:" + r.phone.replace(/\s/g, "") }, icon("phone"), r.phone) : null,
            wa ? h("a", { class: "btn small wa", href: wa, target: "_blank", rel: "noopener" }, icon("chat"), "WhatsApp") : null,
            r.email ? h("a", { class: "btn small", href: "mailto:" + r.email + "?subject=" + encodeURIComponent("Your inquiry to Nexbridge Solutions") }, icon("mail"), r.email) : null),
          details,
          h("div", { class: "inq-actions" },
            r.status !== "contacted" ? h("button", { class: "btn small", type: "button", onclick: setStatus("contacted") }, "Mark contacted") : null,
            r.status !== "closed" ? h("button", { class: "btn small", type: "button", onclick: setStatus("closed") }, "Close") : null,
            r.status !== "new" ? h("button", { class: "btn small", type: "button", onclick: setStatus("new") }, "Mark as new") : null,
            h("button", { class: "btn small danger", type: "button", onclick: function () {
              if (!confirm("Delete this submission from " + (r.name || "this person") + "? This cannot be undone.")) return;
              api("inquiry_delete", { body: { ids: [r.id] } }).then(function () { rows = rows.filter(function (x) { return x !== r; }); draw(); toast("Deleted."); }).catch(function (e) { toast(e.message, "error"); });
            } }, icon("trash"), "Delete"))));
    }
    function dt(k, v) { return [h("dt", null, k), h("dd", null, v)]; }
    load();
  }

  // ------------------------------------------------------------ content editor
  function editor(key) {
    var col = S.schema.collections[key];
    var doc = null, sha = "";
    var form = h("div", { class: "editor" }, h("p", { class: "muted" }, "Loading…"));
    var saveBtn = h("button", { class: "btn primary", type: "button", onclick: save }, "Save and publish");
    var status = h("span", { class: "save-status" });
    var bar = h("div", { class: "save-bar" }, status, saveBtn);
    add(main, pageHead(col.label, col.intro), form, bar);
    S.dirty = false;
    setDirty(false);

    api("load", { query: "&c=" + encodeURIComponent(key) }).then(function (r) {
      doc = r.data; sha = r.sha; draw();
    }).catch(function (e) { clear(form); add(form, h("p", { class: "form-error" }, e.message)); });

    function draw() {
      clear(form);
      if (col.type === "list") add(form, listField(col, doc, doc, function (v) { doc = v; }, true));
      else add(form, objectFields(col.fields, doc, doc));
    }
    function setDirty(d) {
      S.dirty = d;
      status.textContent = d ? "You have unsaved changes" : "All changes saved";
      bar.classList.toggle("is-dirty", d);
    }
    form.addEventListener("input", function () { setDirty(true); });
    form.addEventListener("change", function () { setDirty(true); });
    form.addEventListener("nx-change", function () { setDirty(true); });

    function save() {
      if (!doc) return;
      var bad = form.querySelector(":invalid");
      if (bad) { openParents(bad); bad.reportValidity(); return; }
      saveBtn.disabled = true; saveBtn.textContent = "Saving…";
      api("save", { body: { c: key, data: doc, sha: sha } }).then(function (r) {
        sha = r.sha; doc = r.data; setDirty(false); draw();
        toast("Saved. Publishing to the website…", "ok");
        startPublishWatch();
      }).catch(function (e) {
        toast(e.message, "error");
      }).then(function () { saveBtn.disabled = false; saveBtn.textContent = "Save and publish"; });
    }
  }

  function openParents(el) {
    for (var p = el.parentElement; p; p = p.parentElement) if (p.tagName === "DETAILS") p.open = true;
  }
  function changed(el) { el.dispatchEvent(new CustomEvent("nx-change", { bubbles: true })); }

  function objectFields(fields, obj, root) {
    return h("div", { class: "fields" }, fields.map(function (fd) { return fieldFor(fd, obj, root); }));
  }

  function fieldFor(fd, obj, root, opts) {
    opts = opts || {};
    var t = fd.type, v = obj[fd.key];
    var set = function (val) { obj[fd.key] = val; if (opts.onset) opts.onset(val); };
    var help = fd.help ? h("span", { class: "fld-help" }, fd.help) : null;
    var label = h("span", { class: "fld-label" }, fd.label, fd.required && t !== "checkbox" ? h("span", { class: "req", "aria-hidden": "true" }, " *") : null);

    if (t === "object") {
      if (!obj[fd.key] || typeof obj[fd.key] !== "object") obj[fd.key] = {};
      return h("fieldset", { class: "group" }, h("legend", null, fd.label), help, objectFields(fd.fields, obj[fd.key], root));
    }
    if (t === "list") {
      if (!Array.isArray(obj[fd.key])) obj[fd.key] = [];
      return h("div", { class: "fld" }, label, help, listField(fd, obj[fd.key], root, set));
    }
    if (t === "checkbox") {
      var cb = h("input", { type: "checkbox", checked: !!v, required: fd.required ? true : null, onchange: function () { set(cb.checked); } });
      return h("label", { class: "fld check" }, cb, h("span", null, fd.label), help);
    }
    var input;
    if (t === "textarea" || t === "markdown" || t === "strings" || t === "paragraphs") {
      var val = t === "strings" ? (v || []).join("\n") : t === "paragraphs" ? (v || []).join("\n\n") : (v || "");
      input = h("textarea", { rows: fd.rows || (t === "strings" ? Math.min(Math.max((v || []).length + 1, 3), 12) : 3), value: val, required: fd.required ? true : null });
      input.addEventListener("input", function () {
        if (t === "strings") set(input.value.split("\n").map(function (s) { return s.trim(); }).filter(Boolean));
        else if (t === "paragraphs") set(input.value.split(/\n\s*\n/).map(function (s) { return s.replace(/\s*\n\s*/g, " ").trim(); }).filter(Boolean));
        else set(input.value);
      });
      if (t === "strings" && !fd.help) help = h("span", { class: "fld-help" }, "One item per line.");
      if (t === "paragraphs" && !fd.help) help = h("span", { class: "fld-help" }, "Leave an empty line between paragraphs.");
      if (t === "markdown") return markdownField(fd, label, input, v);
    } else if (t === "select" || t === "icon" || t === "flag") {
      var options = [];
      if (t === "icon") options = S.schema.icons.map(function (x) { return [x, x]; });
      else if (t === "flag") options = S.schema.flags.map(function (x) { return [x, x.toUpperCase()]; });
      else if (fd.options) options = Object.keys(fd.options).map(function (k) { return [k, fd.options[k]]; });
      else if (fd.options_from) options = (root[fd.options_from] || []).map(function (x) { return [x, x]; });
      if (v && !options.some(function (o) { return o[0] === v; })) options.unshift([v, v]);
      if (v === undefined || v === "") {
        if (fd.default) v = fd.default;
        else if (fd.required && options.length) v = options[0][0];
        if (v) obj[fd.key] = v;
      }
      input = h("select", { onchange: function () { set(input.value); if (preview) preview.src = flagSrc(input.value); } },
        fd.required ? null : h("option", { value: "" }, "None"),
        options.map(function (o) { return h("option", { value: o[0] }, o[1]); }));
      input.value = v || "";
      var preview = t === "flag" ? h("img", { class: "flag-prev", alt: "", src: flagSrc(v) }) : null;
      if (preview) return h("label", { class: "fld" }, label, h("div", { class: "row" }, preview, input), help);
    } else if (t === "image") {
      return imageField(fd, label, help, v, set);
    } else {
      var type = { email: "email", url: "url", tel: "tel", date: "date" }[t] || "text";
      if (t === "date" && !v && fd.default === "today") { v = today(); obj[fd.key] = v; }
      input = h("input", { type: type, value: v || "", placeholder: fd.placeholder || null, required: fd.required ? true : null,
        pattern: fd.pattern || (t === "slug" ? "[a-z0-9]+(-[a-z0-9]+)*" : null), readonly: fd.locked && v ? true : null, spellcheck: t === "slug" ? "false" : null });
      if (t === "url") input.placeholder = fd.placeholder || "https://";
      input.addEventListener("input", function () { set(input.value); });
      if (t === "slug") return slugField(fd, label, input, obj, help);
    }
    return h("label", { class: "fld" }, label, input, help);
  }
  function flagSrc(code) { return code ? "/assets/img/flags/" + code + ".webp" : "data:,"; }

  function slugField(fd, label, input, obj, help) {
    var touched = !!obj[fd.key];
    var hint = h("span", { class: "fld-help" }, fd.locked ? "This web address is fixed." :
      "Part of the page's web address" + (fd.prefix ? ": " + fd.prefix + "…/" : "") + ". Lowercase letters, numbers and hyphens. Avoid changing it once the page is shared.");
    input.addEventListener("input", function () { touched = true; });
    if (fd.from && !touched) {
      // fill in automatically from the title/name until the user edits it
      setTimeout(function () {
        var wrap = input.closest(".fields");
        var src = wrap && wrap.querySelector('[data-key="' + fd.from + '"]');
        if (src) src.addEventListener("input", function () {
          if (touched) return;
          input.value = slugify(src.value); obj[fd.key] = input.value;
        });
      });
    }
    return h("label", { class: "fld" }, label, input, help || hint);
  }

  function markdownField(fd, label, input, v) {
    var prev = h("div", { class: "md-preview prose", hidden: true });
    var tab = h("button", { class: "link-btn", type: "button", onclick: function () {
      var show = prev.hidden;
      if (show) { clear(prev); renderMarkdown(input.value, prev); }
      prev.hidden = !show; input.hidden = show; tab.textContent = show ? "Back to editing" : "Preview";
    } }, "Preview");
    return h("div", { class: "fld" }, h("div", { class: "row between" }, label, tab), input, prev,
      h("details", { class: "fld-help md-help" }, h("summary", null, "Formatting help"),
        h("ul", null,
          h("li", null, "Leave an empty line between paragraphs."),
          h("li", null, "Start a line with ", h("code", null, "## "), " for a heading."),
          h("li", null, "Start lines with ", h("code", null, "- "), " for a bullet list, or ", h("code", null, "1. "), " for a numbered list."),
          h("li", null, h("code", null, "**bold text**"), " for bold."),
          h("li", null, h("code", null, "[link text](https://example.com)"), " for a link."))));
  }
  function renderMarkdown(text, into) {
    text.replace(/\r\n/g, "\n").trim().split(/\n\s*\n/).forEach(function (block) {
      var lines = block.split("\n").map(function (l) { return l.trim(); }).filter(Boolean);
      if (!lines.length) return;
      if (/^#/.test(lines[0])) { add(into, h(lines[0].indexOf("###") === 0 ? "h3" : "h2", null, inline(lines[0].replace(/^#+\s*/, "")))); lines = lines.slice(1); }
      if (!lines.length) return;
      if (lines.every(function (l) { return /^[-*] /.test(l); })) add(into, h("ul", null, lines.map(function (l) { return h("li", null, inline(l.slice(2))); })));
      else if (lines.every(function (l) { return /^\d+[.)] /.test(l); })) add(into, h("ol", null, lines.map(function (l) { return h("li", null, inline(l.replace(/^\d+[.)] /, ""))); })));
      else add(into, h("p", null, lines.map(function (l, i) { return [i ? h("br") : null, inline(l)]; })));
    });
  }
  function inline(s) {
    var out = [], re = /\*\*(.+?)\*\*|\[([^\]]+)\]\(((?:https?:\/\/|\/)[^\s)]*)\)/g, last = 0, m;
    while ((m = re.exec(s))) {
      out.push(s.slice(last, m.index));
      out.push(m[1] ? h("strong", null, m[1]) : h("a", { href: m[3], target: "_blank", rel: "noopener" }, m[2]));
      last = re.lastIndex;
    }
    out.push(s.slice(last));
    return out;
  }

  function imageField(fd, label, help, v, set) {
    var box = h("div", { class: "img-field" });
    var file = h("input", { type: "file", accept: "image/jpeg,image/png,image/webp", hidden: true, onchange: function () {
      if (!file.files[0]) return;
      upload(file.files[0]);
      file.value = "";
    } });
    var current = v || "";
    function draw(msg) {
      clear(box);
      add(box, current ? previewImg(current, "img-prev") : h("div", { class: "img-empty" }, icon("img"), "No image"));
      add(box, h("div", { class: "img-actions" },
        msg ? h("span", { class: "muted" }, msg) : [
          h("button", { class: "btn small", type: "button", onclick: function () { file.click(); } }, current ? "Replace image" : "Upload image"),
          current ? h("button", { class: "btn small danger", type: "button", onclick: function () { current = ""; set(""); changed(box); draw(); } }, "Remove") : null],
        file));
    }
    function upload(f) {
      if (!/^image\/(jpeg|png|webp)$/.test(f.type)) return toast("Please choose a JPG, PNG or WebP image.", "error");
      draw("Preparing image…");
      resize(f, fd.max || 1600, !!fd.keep_png).then(function (dataUrl) {
        draw("Uploading…");
        return api("upload", { body: { name: f.name, data: dataUrl.split(",")[1] } });
      }).then(function (r) {
        current = r.path; set(r.path); changed(box); draw();
        toast("Image uploaded. Press Save and publish to use it.", "ok");
      }).catch(function (e) { toast(e.message, "error"); draw(); });
    }
    draw();
    return h("div", { class: "fld" }, label, box, help);
  }

  function resize(f, max, keepPng) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(f), img = new Image();
      img.onload = function () {
        var scale = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
        var w = Math.round(img.naturalWidth * scale), hgt = Math.round(img.naturalHeight * scale);
        var c = document.createElement("canvas"); c.width = w; c.height = hgt;
        c.getContext("2d").drawImage(img, 0, 0, w, hgt);
        URL.revokeObjectURL(url);
        var type = keepPng && f.type === "image/png" ? "image/png" : "image/webp";
        var out = c.toDataURL(type, 0.82);
        if (out.indexOf("data:" + type) !== 0) out = c.toDataURL(f.type === "image/png" ? "image/png" : "image/jpeg", 0.85); // browsers without WebP encoding
        resolve(out);
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error("This image could not be opened.")); };
      img.src = url;
    });
  }

  function listField(def, arr, root, set, isRoot) {
    var wrap = h("div", { class: "list" + (isRoot ? " root" : "") });
    var fixed = !!def.fixed;
    function title(item, i) {
      var t = item && item[def.item_label];
      if (Array.isArray(t)) t = t[0];
      var sub = def.item_sub && item[def.item_sub];
      if (def.item_sub && def.fields) {
        var sf = def.fields.filter(function (x) { return x.key === def.item_sub; })[0];
        if (sf && sf.options && sf.options[sub]) sub = sf.options[sub];
      }
      return [h("span", { class: "item-title" }, t || "Untitled " + (i + 1)), sub ? h("span", { class: "item-sub" }, sub) : null,
        item && item.draft ? h("span", { class: "pill closed" }, "draft") : null];
    }
    function draw(openIndex) {
      clear(wrap);
      if (!arr.length) add(wrap, h("p", { class: "muted empty-list" }, "Nothing here yet."));
      var addBtn = fixed ? null : h("button", { class: "btn small add", type: "button", onclick: function () {
        var item = {};
        def.fields.forEach(function (fd) {
          if (fd.type === "list" || fd.type === "strings" || fd.type === "paragraphs") item[fd.key] = [];
          else if (fd.type === "object") item[fd.key] = {};
          else if (fd.type === "checkbox") item[fd.key] = false;
          else item[fd.key] = fd.default === "today" ? today() : fd.default || "";
        });
        if (def.add_first) arr.unshift(item); else arr.push(item);
        changed(wrap);
        draw(def.add_first ? 0 : arr.length - 1);
      } }, icon("plus"), def.add_label || "Add");
      if (addBtn && def.add_first) add(wrap, addBtn);
      arr.forEach(function (item, i) {
        var head = h("span", { class: "item-head" }, def.item_image && item[def.item_image] ? previewImg(item[def.item_image], "item-thumb") : null, title(item, i));
        var tools = fixed ? null : h("span", { class: "item-tools" },
          i > 0 ? h("button", { type: "button", class: "icon-btn", title: "Move up", "aria-label": "Move up", onclick: function (e) { e.preventDefault(); move(i, -1); } }, icon("up")) : null,
          i < arr.length - 1 ? h("button", { type: "button", class: "icon-btn", title: "Move down", "aria-label": "Move down", onclick: function (e) { e.preventDefault(); move(i, 1); } }, icon("down")) : null,
          h("button", { type: "button", class: "icon-btn danger", title: "Delete", "aria-label": "Delete", onclick: function (e) {
            e.preventDefault();
            var name = item[def.item_label] || "this item";
            if (!confirm("Delete \"" + name + "\"? It will be removed from the website when you save.")) return;
            arr.splice(i, 1); changed(wrap); draw();
          } }, icon("trash")));
        var summary = h("summary", null, head, tools);
        var body = h("div", { class: "item-body" });
        var det = h("details", { class: "item", open: i === openIndex ? true : null }, summary, body);
        var built = false;
        function build() {
          if (built) return; built = true;
          var fields = h("div", { class: "fields" });
          def.fields.forEach(function (fd) {
            var el = fieldFor(fd, item, root);
            var inp = el.querySelector("input,textarea,select");
            if (inp) inp.setAttribute("data-key", fd.key);
            add(fields, el);
          });
          add(body, fields);
          fields.addEventListener("input", function (e) {
            if (e.target.getAttribute("data-key") === def.item_label || e.target.getAttribute("data-key") === def.item_sub) {
              var ht = head.querySelectorAll(".item-title, .item-sub, .pill");
              ht.forEach(function (n) { n.remove(); });
              add(head, title(item, i));
            }
          });
        }
        det.addEventListener("toggle", function () { if (det.open) build(); });
        if (i === openIndex) { build(); setTimeout(function () { det.scrollIntoView({ block: "nearest", behavior: "smooth" }); var f = det.querySelector("input,textarea"); if (f) f.focus(); }); }
        add(wrap, det);
      });
      if (addBtn && !def.add_first) add(wrap, addBtn);
    }
    function move(i, d) {
      var x = arr[i]; arr.splice(i, 1); arr.splice(i + d, 0, x); changed(wrap); draw();
    }
    draw();
    // validation needs every required field in the DOM, so open lazily-built items on save attempts
    wrap.addEventListener("invalid", function (e) { openParents(e.target); }, true);
    return wrap;
  }

  // ------------------------------------------------------------ account
  function account() {
    add(main, pageHead("Users & settings", "Admin accounts, your password, email notifications and the GitHub connection."));
    var box = h("div", { class: "cols" }, h("p", { class: "muted" }, "Loading…"));
    add(main, box);
    api("account").then(function (acc) {
      clear(box);
      add(box, [passwordPanel(), usersPanel(acc), notifyPanel(acc), tokenPanel(acc)]);
    }).catch(function (e) { clear(box); add(box, h("p", { class: "form-error" }, e.message)); });
  }
  function panel(title, lead, body) { return h("section", { class: "panel" }, h("h2", null, title), lead ? h("p", { class: "muted" }, lead) : null, body); }
  function simpleForm(inputs, btnText, action, payload, done) {
    var err = errBox(), btn = h("button", { class: "btn primary", type: "submit" }, btnText);
    var form = h("form", { onsubmit: function (e) {
      e.preventDefault(); btn.disabled = true; showErr(err, "");
      var body = payload();
      if (typeof body === "string") { showErr(err, body); btn.disabled = false; return; }
      api(action, { body: body }).then(function (r) { done(r, form); }).catch(function (e2) { showErr(err, e2.message); }).then(function () { btn.disabled = false; });
    } }, inputs, err, btn);
    return form;
  }
  function passwordPanel() {
    var cur = h("input", { type: "password", autocomplete: "current-password", required: true });
    var n1 = h("input", { type: "password", autocomplete: "new-password", required: true, minlength: 10 });
    var n2 = h("input", { type: "password", autocomplete: "new-password", required: true });
    return panel("Change your password", null, simpleForm([field("Current password", cur), field("New password", n1, "At least 10 characters."), field("Repeat new password", n2)],
      "Change password", "password", function () { return n1.value !== n2.value ? "The new passwords do not match." : { current: cur.value, "new": n1.value }; },
      function (r, form) { form.reset(); toast("Password changed.", "ok"); }));
  }
  function usersPanel(acc) {
    var list = h("ul", { class: "user-list" }, acc.users.map(function (u) {
      return h("li", null, h("span", null, u, u === acc.me ? h("span", { class: "muted" }, " (you)") : null),
        u === acc.me ? null : h("button", { class: "btn small danger", type: "button", onclick: function () {
          if (!confirm("Remove the admin account \"" + u + "\"? They will no longer be able to log in.")) return;
          api("user_delete", { body: { username: u } }).then(function () { toast("Account removed.", "ok"); render(); }).catch(function (e) { toast(e.message, "error"); });
        } }, "Remove"));
    }));
    var u = h("input", { type: "text", autocomplete: "off", required: true });
    var p = h("input", { type: "password", autocomplete: "new-password", required: true, minlength: 10 });
    return panel("Admin accounts", "Everyone listed here can log in and edit the website.", [list,
      h("h3", null, "Add an account"),
      simpleForm([field("Username", u), field("Temporary password", p, "Share it privately; they can change it after logging in.")], "Add account", "user_add",
        function () { return { username: u.value, password: p.value }; }, function () { toast("Account added.", "ok"); render(); })]);
  }
  function notifyPanel(acc) {
    var to = h("input", { type: "email", value: acc.notify.notify_email || "", placeholder: "info@nexbridge-solutions.com" });
    var from = h("input", { type: "email", value: acc.notify.from_email || "", placeholder: "website@nexbridge-solutions.com" });
    return panel("Email notifications", "Get an email each time someone sends a form. Submissions are always saved under Inquiries & bookings as well.",
      simpleForm([field("Send notifications to", to), field("Send from", from, "Use a mailbox on your own domain, created in Hostinger's Emails section, so the messages are not marked as spam.")],
        "Save notification settings", "notify", function () { return { notify_email: to.value, from_email: from.value }; },
        function () { toast("Notification settings saved.", "ok"); }));
  }
  function tokenPanel(acc) {
    var t = h("input", { type: "password", autocomplete: "off", required: true, spellcheck: "false" });
    return panel("GitHub connection", "The admin panel publishes changes through the website's GitHub repository. Replace the token here when it expires.",
      [h("p", null, "Current token: ", h("code", null, acc.token_hint || "none")),
        simpleForm([field("New access token", t, "Fine-grained token with Contents: Read and write and Actions: Read-only on the nexbridge repository.")],
          "Replace token", "token", function () { return { token: t.value }; }, function (r, form) { form.reset(); toast("Token replaced.", "ok"); render(); })]);
  }

  start();
})();
