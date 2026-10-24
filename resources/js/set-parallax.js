// One passive scroll handler, one compositor update per frame, no animation loop.
export function setupSetParallax(browser = window, root = document) {
    const motion = browser.matchMedia('(prefers-reduced-motion: reduce)');
    let pending = false;
    const update = () => {
        pending = false;
        const offset = motion.matches ? 0 : -Math.min(Math.max(0, browser.scrollY) * 0.65, 900);
        for (const layer of root.querySelectorAll('.set-visual-background [data-set-art]')) {
            layer.style.setProperty('--set-parallax-y', `${offset}px`);
        }
    };
    const schedule = () => {
        if (pending) return;
        pending = true;
        browser.requestAnimationFrame(update);
    };
    browser.addEventListener('scroll', schedule, { passive: true });
    browser.addEventListener('resize', schedule, { passive: true });
    motion.addEventListener('change', schedule);
    schedule();
    return schedule;
}
