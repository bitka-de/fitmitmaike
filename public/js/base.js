class ResponsiveHeader {
  static DEFAULTS = {
    selectors: {
      header: ".site-header",
      toggle: ".nav-toggle",
      close: ".nav-close",
      overlay: ".nav-overlay",
      drawer: ".mobile-drawer",
      mobileLinks: ".mobile-nav a",
    },
    classes: {
      open: "is-open",
      hidden: "is-hidden",
      bodyOpen: "nav-open",
    },
    breakpoints: {
      desktop: 992,
    },
    timings: {
      overlayHideDelay: 240,
    },
    scroll: {
      topRevealOffset: 12,
      minDelta: 6,
      hideAfter: 90,
      showAfter: 40,
    },
    scrollIntoView: {
      behavior: "smooth",
      block: "start",
    },
  };

  constructor(options = {}) {
    this.options = this.#mergeOptions(ResponsiveHeader.DEFAULTS, options);
    const { selectors } = this.options;

    this.header = document.querySelector(selectors.header);
    this.toggle = document.querySelector(selectors.toggle);
    this.closeBtn = document.querySelector(selectors.close);
    this.overlay = document.querySelector(selectors.overlay);
    this.drawer = document.querySelector(selectors.drawer);
    this.mobileLinks = [...document.querySelectorAll(selectors.mobileLinks)];

    if (!this.header) {
      throw new Error('ResponsiveHeader: ".site-header" wurde nicht gefunden.');
    }

    this.lastY = window.scrollY;
    this.currentY = window.scrollY;
    this.downDistance = 0;
    this.upDistance = 0;
    this.isTicking = false;
    this.overlayHideTimeout = null;

    this.onToggleClick = this.onToggleClick.bind(this);
    this.onCloseClick = this.onCloseClick.bind(this);
    this.onOverlayClick = this.onOverlayClick.bind(this);
    this.onDocumentKeydown = this.onDocumentKeydown.bind(this);
    this.onResize = this.onResize.bind(this);
    this.onScroll = this.onScroll.bind(this);
    this.handleHeaderScroll = this.handleHeaderScroll.bind(this);
    this.onMobileNavClick = this.onMobileNavClick.bind(this);
  }

  init() {
    this.#bindEvents();
    this.#syncInitialState();
    this.#resetScrollTracking();
    return this;
  }

  destroy() {
    this.#unbindEvents();
    this.#clearOverlayTimeout();
  }

  isMenuOpen() {
    return this.header.classList.contains(this.options.classes.open);
  }

  openMenu() {
    const { classes } = this.options;

    this.#clearOverlayTimeout();

    this.header.classList.add(classes.open);
    this.header.classList.remove(classes.hidden);

    this.toggle?.setAttribute("aria-expanded", "true");

    if (this.drawer) {
      this.drawer.setAttribute("aria-hidden", "false");
      this.drawer.inert = false;
    }

    if (this.overlay) {
      this.overlay.hidden = false;
    }

    document.body.classList.add(classes.bodyOpen);
    this.#resetScrollTracking();
  }

  closeMenu({ immediate = false, restoreFocus = true } = {}) {
    const { classes, timings } = this.options;

    const activeElement = document.activeElement;
    const focusIsInsideDrawer =
      this.drawer && activeElement && this.drawer.contains(activeElement);

    this.header.classList.remove(classes.open);
    this.toggle?.setAttribute("aria-expanded", "false");

    if (focusIsInsideDrawer) {
      if (restoreFocus && this.toggle) {
        this.toggle.focus();
      } else if (activeElement instanceof HTMLElement) {
        activeElement.blur();
      }
    }

    document.body.classList.remove(classes.bodyOpen);

    this.#clearOverlayTimeout();

    if (this.drawer) {
      this.drawer.inert = true;

      requestAnimationFrame(() => {
        this.drawer?.setAttribute("aria-hidden", "true");
      });
    }

    if (!this.overlay) return;

    if (immediate) {
      this.overlay.hidden = true;
      return;
    }

    this.overlayHideTimeout = window.setTimeout(() => {
      if (!this.isMenuOpen()) {
        this.overlay.hidden = true;
      }
      this.overlayHideTimeout = null;
    }, timings.overlayHideDelay);
  }

  toggleMenu() {
    this.isMenuOpen() ? this.closeMenu() : this.openMenu();
  }

  showHeader() {
    this.header.classList.remove(this.options.classes.hidden);
  }

  hideHeader() {
    this.header.classList.add(this.options.classes.hidden);
  }

  onToggleClick() {
    this.toggleMenu();
  }

  onCloseClick() {
    this.closeMenu();
  }

  onOverlayClick() {
    this.closeMenu();
  }

  onDocumentKeydown(event) {
    if (event.key === "Escape" && this.isMenuOpen()) {
      this.closeMenu();
    }
  }

  onResize() {
    if (window.innerWidth >= this.options.breakpoints.desktop) {
      this.closeMenu({ immediate: true });
    }
  }

  onScroll() {
    this.currentY = window.scrollY;

    if (this.isTicking) return;

    this.isTicking = true;
    window.requestAnimationFrame(this.handleHeaderScroll);
  }

  handleHeaderScroll() {
    const { scroll } = this.options;
    const currentY = Math.max(this.currentY, 0);
    const delta = currentY - this.lastY;

    if (this.isMenuOpen()) {
      this.showHeader();
      this.#resetScrollTracking(currentY);
      this.isTicking = false;
      return;
    }

    if (currentY <= scroll.topRevealOffset) {
      this.showHeader();
      this.#resetScrollTracking(currentY);
      this.isTicking = false;
      return;
    }

    if (Math.abs(delta) < scroll.minDelta) {
      this.isTicking = false;
      return;
    }

    if (delta > 0) {
      this.downDistance += delta;
      this.upDistance = 0;

      if (this.downDistance >= scroll.hideAfter) {
        this.hideHeader();
      }
    } else {
      this.upDistance += Math.abs(delta);
      this.downDistance = 0;

      if (this.upDistance >= scroll.showAfter) {
        this.showHeader();
      }
    }

    this.lastY = currentY;
    this.isTicking = false;
  }

  onMobileNavClick(event) {
    const href = event.currentTarget.getAttribute("href");
    if (!href || !href.startsWith("#")) return;

    event.preventDefault();

    const target = document.getElementById(href.slice(1));

    this.closeMenu({ restoreFocus: false });

    if (!target) return;

    window.setTimeout(() => {
      target.scrollIntoView(this.options.scrollIntoView);

      if (!target.hasAttribute("tabindex")) {
        target.setAttribute("tabindex", "-1");
      }

      target.focus({ preventScroll: true });
    }, this.options.timings.overlayHideDelay);
  }

  #bindEvents() {
    this.toggle?.addEventListener("click", this.onToggleClick);
    this.closeBtn?.addEventListener("click", this.onCloseClick);
    this.overlay?.addEventListener("click", this.onOverlayClick);

    this.mobileLinks.forEach((link) => {
      link.addEventListener("click", this.onMobileNavClick);
    });

    document.addEventListener("keydown", this.onDocumentKeydown);
    window.addEventListener("resize", this.onResize);
    window.addEventListener("scroll", this.onScroll, { passive: true });
  }

  #unbindEvents() {
    this.toggle?.removeEventListener("click", this.onToggleClick);
    this.closeBtn?.removeEventListener("click", this.onCloseClick);
    this.overlay?.removeEventListener("click", this.onOverlayClick);

    this.mobileLinks.forEach((link) => {
      link.removeEventListener("click", this.onMobileNavClick);
    });

    document.removeEventListener("keydown", this.onDocumentKeydown);
    window.removeEventListener("resize", this.onResize);
    window.removeEventListener("scroll", this.onScroll);
  }

  #syncInitialState() {
    const isOpen = this.isMenuOpen();

    this.toggle?.setAttribute("aria-expanded", String(isOpen));

    if (this.drawer) {
      this.drawer.setAttribute("aria-hidden", String(!isOpen));
      this.drawer.inert = !isOpen;
    }

    if (this.overlay) {
      this.overlay.hidden = !isOpen;
    }

    document.body.classList.toggle(this.options.classes.bodyOpen, isOpen);

    if (isOpen) {
      this.showHeader();
    }
  }

  #resetScrollTracking(currentY = window.scrollY) {
    this.downDistance = 0;
    this.upDistance = 0;
    this.lastY = currentY;
    this.currentY = currentY;
  }

  #clearOverlayTimeout() {
    if (this.overlayHideTimeout === null) return;
    window.clearTimeout(this.overlayHideTimeout);
    this.overlayHideTimeout = null;
  }

  #mergeOptions(base, override) {
    const output = { ...base };

    Object.keys(override).forEach((key) => {
      const baseValue = base[key];
      const overrideValue = override[key];

      output[key] =
        this.#isPlainObject(baseValue) && this.#isPlainObject(overrideValue)
          ? this.#mergeOptions(baseValue, overrideValue)
          : overrideValue;
    });

    return output;
  }

  #isPlainObject(value) {
    return value !== null && typeof value === "object" && !Array.isArray(value);
  }
}

class ServiceCardAnimator {
  constructor(selector = ".js-service-card") {
    this.cards = [...document.querySelectorAll(selector)];
    this.reduceMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    ).matches;

    if (!this.cards.length) return;

    this.initVisibility();
    this.initHoverEffect();
  }

  initVisibility() {
    if (this.reduceMotion || !("IntersectionObserver" in window)) {
      this.cards.forEach((card) => card.classList.add("is-visible"));
      return;
    }

    const observer = new IntersectionObserver(
      (entries, obs) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-visible");
          obs.unobserve(entry.target);
        });
      },
      {
        threshold: 0.15,
        rootMargin: "0px 0px -40px 0px",
      },
    );

    this.cards.forEach((card, index) => {
      card.style.transitionDelay = `${index * 70}ms`;
      observer.observe(card);
    });
  }

  initHoverEffect() {
    if (this.reduceMotion) return;

    this.cards.forEach((card) => {
      const mediaImage = card.querySelector(".service-card__media img");
      if (!mediaImage) return;

      card.addEventListener("mousemove", (event) => {
        const rect = card.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width;
        const y = (event.clientY - rect.top) / rect.height;
        const moveX = (x - 0.5) * 8;
        const moveY = (y - 0.5) * 8;

        mediaImage.style.transform = `scale(1.05) translate(${moveX}px, ${moveY}px)`;
      });

      card.addEventListener("mouseleave", () => {
        mediaImage.style.transform = "";
      });
    });
  }
}

class TimelineAnimator {
  constructor({
    timelineSelector = "#timeline",
    itemSelector = ".timeline-item",
    threshold = 0.18,
  } = {}) {
    this.timeline = document.querySelector(timelineSelector);
    this.items = [...document.querySelectorAll(itemSelector)];

    if (
      !this.timeline ||
      !this.items.length ||
      !("IntersectionObserver" in window)
    ) {
      return;
    }

    this.threshold = threshold;
    this.initObserver();
    this.bindHoverAndFocus();
  }

  initObserver() {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;

          if (entry.target === this.timeline) {
            this.timeline.classList.add("is-visible");
            return;
          }

          if (entry.target.classList.contains("timeline-item")) {
            entry.target.classList.add("is-visible");
          }
        });
      },
      { threshold: this.threshold },
    );

    observer.observe(this.timeline);
    this.items.forEach((item) => observer.observe(item));
  }

  bindHoverAndFocus() {
    this.items.forEach((item) => {
      item.addEventListener("mouseenter", () => this.setActive(item));
      item.addEventListener("focus", () => this.setActive(item));
      item.addEventListener("mouseleave", () =>
        item.classList.remove("is-active"),
      );
      item.addEventListener("blur", () => item.classList.remove("is-active"));
    });
  }

  setActive(activeItem) {
    this.items.forEach((item) => {
      item.classList.toggle("is-active", item === activeItem);
    });
  }
}

class CardScroller {
  constructor({
    scroller,
    cardSelector = ".news-card",
    prevBtn = null,
    nextBtn = null,
    threshold = 0.6,
  }) {
    this.scroller = document.querySelector(scroller);
    if (!this.scroller) return;

    this.cards = [...this.scroller.querySelectorAll(cardSelector)];
    this.prevBtn = prevBtn ? document.querySelector(prevBtn) : null;
    this.nextBtn = nextBtn ? document.querySelector(nextBtn) : null;
    this.threshold = threshold;

    this.init();
  }

  init() {
    this.bindButtons();
    this.bindScrollEvents();
    this.initObserver();
    this.updateButtons();
  }

  bindButtons() {
    this.prevBtn?.addEventListener("click", () => this.scroll(-1));
    this.nextBtn?.addEventListener("click", () => this.scroll(1));
  }

  bindScrollEvents() {
    this.scroller.addEventListener("scroll", () => this.updateButtons());
    window.addEventListener("resize", () => this.updateButtons());
  }

  getScrollAmount() {
    const firstCard = this.cards[0];
    if (!firstCard) return 300;

    const styles = getComputedStyle(this.scroller);
    const gap = parseInt(styles.columnGap || styles.gap || 16, 10);

    return firstCard.offsetWidth + gap;
  }

  scroll(direction = 1) {
    this.scroller.scrollBy({
      left: direction * this.getScrollAmount(),
      behavior: "smooth",
    });
  }

  updateButtons() {
    if (!this.prevBtn || !this.nextBtn) return;

    const scrollLeft = this.scroller.scrollLeft;
    const maxScroll = this.scroller.scrollWidth - this.scroller.clientWidth;

    this.prevBtn.disabled = scrollLeft <= 0;
    this.nextBtn.disabled = scrollLeft >= maxScroll - 1;
  }

  initObserver() {
    if (!("IntersectionObserver" in window)) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          entry.target.classList.toggle("outside", !entry.isIntersecting);
        });
      },
      {
        root: this.scroller,
        threshold: this.threshold,
      },
    );

    this.cards.forEach((card) => observer.observe(card));
  }
}

class FaqAccordion {
  constructor(root, options = {}) {
    this.root = root;
    if (!this.root) return;

    this.items = [...this.root.querySelectorAll(".faq-item")];
    this.singleOpen = options.singleOpen ?? true;

    this.init();
  }

  init() {
    this.items.forEach((item) => {
      const button = item.querySelector(".faq-question");
      const answer = item.querySelector(".faq-answer");

      if (!button || !answer) return;

      item.classList.contains("is-open")
        ? this.openItem(item, false)
        : this.closeItem(item, false);

      button.addEventListener("click", () => this.toggleItem(item));
    });
  }

  toggleItem(item) {
    const isOpen = item.classList.contains("is-open");

    if (isOpen) {
      this.closeItem(item);
      return;
    }

    if (this.singleOpen) {
      this.items.forEach((otherItem) => {
        if (otherItem !== item) this.closeItem(otherItem);
      });
    }

    this.openItem(item);
  }

  openItem(item) {
    const button = item.querySelector(".faq-question");
    const answer = item.querySelector(".faq-answer");
    const inner = item.querySelector(".faq-answer-inner");

    if (!button || !answer || !inner) return;

    item.classList.add("is-open");
    button.setAttribute("aria-expanded", "true");
    answer.style.maxHeight = `${inner.scrollHeight}px`;
  }

  closeItem(item) {
    const button = item.querySelector(".faq-question");
    const answer = item.querySelector(".faq-answer");

    if (!button || !answer) return;

    item.classList.remove("is-open");
    button.setAttribute("aria-expanded", "false");
    answer.style.maxHeight = "0px";
  }

  recalculate() {
    this.items.forEach((item) => {
      if (!item.classList.contains("is-open")) return;

      const answer = item.querySelector(".faq-answer");
      const inner = item.querySelector(".faq-answer-inner");

      if (answer && inner) {
        answer.style.maxHeight = `${inner.scrollHeight}px`;
      }
    });
  }

  closeAll() {
    this.items.forEach((item) => this.closeItem(item));
  }
}

class FaqFilter {
  constructor({
    groupSelector = "[data-category]",
    chipSelector = "[data-filter]",
    accordionSelector = "[data-accordion]",
  } = {}) {
    this.groups = [...document.querySelectorAll(groupSelector)];
    this.chips = [...document.querySelectorAll(chipSelector)];
    this.accordions = [...document.querySelectorAll(accordionSelector)].map(
      (accordion) => new FaqAccordion(accordion, { singleOpen: true }),
    );

    if (!this.groups.length || !this.chips.length) return;

    this.bind();
    window.addEventListener("resize", () => this.recalculate());
  }

  bind() {
    this.chips.forEach((chip) => {
      chip.addEventListener("click", () =>
        this.applyFilter(chip.dataset.filter, chip),
      );
    });
  }

  applyFilter(filter, activeChip) {
    this.chips.forEach((chip) => {
      const isActive = chip === activeChip;
      chip.classList.toggle("is-active", isActive);
      chip.setAttribute("aria-pressed", String(isActive));
    });

    this.groups.forEach((group) => {
      const matches = filter === "all" || group.dataset.category === filter;
      group.hidden = !matches;
    });

    this.recalculate();
  }

  recalculate() {
    this.accordions.forEach((accordion) => accordion.recalculate());
  }
}

class VideoPopoverCarousel {
  constructor(selector = "[data-vid]") {
    this.items = [...document.querySelectorAll(selector)];
    this.index = 0;

    if (!this.items.length) return;

    this.build();
    this.bind();
  }

  build() {
    this.overlay = document.createElement("div");
    this.overlay.className = "vp-overlay";
    this.overlay.hidden = true;

    this.overlay.innerHTML = `
      <div class="vp-backdrop" data-vp-backdrop></div>
      <div class="vp-dialog" data-vp-dialog role="dialog" aria-modal="true" aria-label="Video Player">
        <button type="button" class="vp-close" data-vp-close aria-label="Schließen">×</button>
        <button type="button" class="vp-prev" data-vp-prev aria-label="Vorheriges Video">‹</button>

        <div class="vp-video-wrap">
          <video class="vp-video" data-vp-video controls playsinline preload="metadata"></video>
        </div>

        <button type="button" class="vp-next" data-vp-next aria-label="Nächstes Video">›</button>

        <div class="vp-controls" hidden>
          <button type="button" class="vp-prev" data-vp-prev-mobile aria-label="Vorheriges Video">‹</button>
          <button type="button" class="vp-next" data-vp-next-mobile aria-label="Nächstes Video">›</button>
        </div>
      </div>
    `;

    document.body.appendChild(this.overlay);

    this.video = this.overlay.querySelector("[data-vp-video]");
    this.prevBtn = this.overlay.querySelector("[data-vp-prev]");
    this.nextBtn = this.overlay.querySelector("[data-vp-next]");
    this.closeBtn = this.overlay.querySelector("[data-vp-close]");
    this.backdrop = this.overlay.querySelector("[data-vp-backdrop]");
    this.mobilePrevBtn = this.overlay.querySelector("[data-vp-prev-mobile]");
    this.mobileNextBtn = this.overlay.querySelector("[data-vp-next-mobile]");
    this.mobileControls = this.overlay.querySelector(".vp-controls");

    this.updateResponsiveControls();
  }

  bind() {
    this.items.forEach((item, index) => {
      item.addEventListener("click", (event) => {
        event.preventDefault();
        this.open(index);
      });
    });

    this.closeBtn.addEventListener("click", () => this.close());
    this.backdrop.addEventListener("click", () => this.close());

    this.prevBtn.addEventListener("click", () => this.prev());
    this.nextBtn.addEventListener("click", () => this.next());
    this.mobilePrevBtn.addEventListener("click", () => this.prev());
    this.mobileNextBtn.addEventListener("click", () => this.next());

    document.addEventListener("keydown", (event) => {
      if (this.overlay.hidden) return;

      if (event.key === "Escape") this.close();
      if (event.key === "ArrowLeft") this.prev();
      if (event.key === "ArrowRight") this.next();
    });

    window.addEventListener("resize", () => this.updateResponsiveControls());

    this.video.addEventListener("ended", () => {
      if (this.items.length > 1) this.next();
    });
  }

  updateResponsiveControls() {
    const isMobile = window.matchMedia("(max-width: 768px)").matches;

    this.mobileControls.hidden = !isMobile;
    this.prevBtn.style.display = isMobile ? "none" : "";
    this.nextBtn.style.display = isMobile ? "none" : "";
  }

  updateNavState() {
    const disabled = this.items.length <= 1;

    this.prevBtn.disabled = disabled;
    this.nextBtn.disabled = disabled;
    this.mobilePrevBtn.disabled = disabled;
    this.mobileNextBtn.disabled = disabled;
  }

  open(index) {
    this.index = index;
    this.overlay.hidden = false;
    document.documentElement.style.overflow = "hidden";
    this.updateNavState();
    this.loadCurrentVideo();
  }

  close() {
    this.video.pause();
    this.video.removeAttribute("src");
    this.video.load();
    this.overlay.hidden = true;
    document.documentElement.style.overflow = "";
  }

  prev() {
    if (this.items.length <= 1) return;
    this.index = (this.index - 1 + this.items.length) % this.items.length;
    this.loadCurrentVideo();
  }

  next() {
    if (this.items.length <= 1) return;
    this.index = (this.index + 1) % this.items.length;
    this.loadCurrentVideo();
  }

  loadCurrentVideo() {
    const item = this.items[this.index];
    const src = item?.dataset?.vid;
    if (!src) return;

    this.video.muted = true;
    this.video.pause();
    this.video.src = src;
    this.video.load();

    const playPromise = this.video.play();
    playPromise?.catch?.(() => {});
  }
}

document.addEventListener("DOMContentLoaded", () => {
  new ResponsiveHeader().init();
  new ServiceCardAnimator();
  new TimelineAnimator();

  new CardScroller({
    scroller: "#newsScroller",
    prevBtn: "#prevBtn",
    nextBtn: "#nextBtn",
  });

  new FaqFilter();
  new VideoPopoverCarousel();
});

class FloatingContactCTA {
  constructor({
    heroSelector = "#hero",
    contactSelector = "#contact",
    footerSelector = "#page-footer",
    buttonSelector = "#floatingCta",
    visibleClass = "is-visible",
  } = {}) {
    this.hero = document.querySelector(heroSelector);
    this.contact = document.querySelector(contactSelector);
    this.footer = document.querySelector(footerSelector);
    this.button = document.querySelector(buttonSelector);
    this.visibleClass = visibleClass;

    this.heroOutOfView = false;
    this.contactInView = false;
    this.footerInView = false;

    if (!this.hero || !this.contact || !this.footer || !this.button) return;

    this.init();
  }

  init() {
    this.createObserver(this.hero, (entry) => {
      this.heroOutOfView = !entry.isIntersecting;
    });

    this.createObserver(this.contact, (entry) => {
      this.contactInView = entry.isIntersecting;
    });

    this.createObserver(this.footer, (entry) => {
      this.footerInView = entry.isIntersecting;
    });

    this.updateVisibility();
  }

  createObserver(element, callback, options = {}) {
    const observer = new IntersectionObserver(
      ([entry]) => {
        callback(entry);
        this.updateVisibility();
      },
      {
        threshold: 0.15,
        ...options,
      },
    );

    observer.observe(element);
  }

  updateVisibility() {
    const shouldShow =
      this.heroOutOfView && !this.contactInView && !this.footerInView;

    this.button.classList.toggle(this.visibleClass, shouldShow);
  }
}

document.addEventListener("DOMContentLoaded", () => {
  new FloatingContactCTA();
});

class LegalPopover {
  constructor({
    triggerSelector = "[data-legal]",
    popoverSelector = "#legalPopover",
    titleSelector = "#legalPopoverTitle",
    contentSelector = "#legalPopoverContent",
    closeSelector = "[data-legal-close]",
    openClass = "is-open",
    bodyClass = "legal-open",
  } = {}) {
    this.triggers = [...document.querySelectorAll(triggerSelector)];
    this.popover = document.querySelector(popoverSelector);
    this.title = document.querySelector(titleSelector);
    this.content = document.querySelector(contentSelector);
    this.closeButtons = [...document.querySelectorAll(closeSelector)];
    this.openClass = openClass;
    this.bodyClass = bodyClass;

    this.lastFocusedElement = null;

    if (
      !this.triggers.length ||
      !this.popover ||
      !this.title ||
      !this.content
    ) {
      return;
    }

    this.bind();
  }

  bind() {
    this.triggers.forEach((trigger) => {
      trigger.addEventListener("click", async (event) => {
        event.preventDefault();

        const url = trigger.dataset.legal;
        const label = trigger.textContent.trim() || "Rechtliches";

        if (!url) return;

        this.lastFocusedElement = trigger;
        this.open(label);
        await this.loadContent(url, label);
      });
    });

    this.closeButtons.forEach((button) => {
      button.addEventListener("click", () => this.close());
    });

    document.addEventListener("keydown", (event) => {
      if (this.popover.hidden) return;

      if (event.key === "Escape") {
        this.close();
      }
    });
  }

  open(label = "Rechtliches") {
    this.title.textContent = label;
    this.content.innerHTML = "<p>Inhalt wird geladen …</p>";

    this.popover.hidden = false;
    document.body.classList.add(this.bodyClass);

    requestAnimationFrame(() => {
      this.popover.classList.add(this.openClass);
    });
  }

  close() {
    this.popover.classList.remove(this.openClass);
    document.body.classList.remove(this.bodyClass);

    window.setTimeout(() => {
      this.popover.hidden = true;
      this.content.innerHTML = "";
      this.lastFocusedElement?.focus?.();
    }, 280);
  }

  async loadContent(url, label) {
    try {
      const response = await fetch(url, {
        headers: {
          "X-Requested-With": "fetch",
        },
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const html = await response.text();
      const parsed = new DOMParser().parseFromString(html, "text/html");

      const extracted =
        parsed.querySelector("main") ||
        parsed.querySelector("article") ||
        parsed.querySelector(".legal-content") ||
        parsed.body;

      this.title.textContent =
        parsed.querySelector("title")?.textContent?.trim() || label;

      this.content.innerHTML = extracted.innerHTML;
    } catch (error) {
      this.title.textContent = label;
      this.content.innerHTML = `
        <p>Der Inhalt konnte gerade nicht geladen werden.</p>
        <p>
          Du kannst die Seite alternativ direkt öffnen:
          <a href="${url}" target="_self" rel="noopener">${label}</a>
        </p>
      `;
      console.error("LegalPopover:", error);
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  new LegalPopover();
});

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("contactForm");
  const statusBox = document.getElementById("formStatus");

  if (!form) return;

  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    statusBox.textContent = "Deine Anfrage wird gesendet...";

    const formData = new FormData(form);

    try {
      const response = await fetch(form.action, {
        method: "POST",
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        form.innerHTML = `
          <div class="mail-send">
            Danke für deine Anfrage, ich melde mich zeitnah bei dir.
          </div>
        `;
      } else {
        statusBox.textContent =
          data.message || "Beim Senden ist ein Fehler aufgetreten.";
      }
    } catch (error) {
      statusBox.textContent =
        "Beim Senden ist ein Fehler aufgetreten. Bitte versuche es später erneut.";
    }
  });
});
