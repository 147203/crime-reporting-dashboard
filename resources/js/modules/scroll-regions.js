// Wide tables scroll sideways inside their box; a focusable, named box lets keyboard users scroll it and tells
// screen-reader users what it holds. Every box gets this because whether it overflows depends on the screen width.
export function initScrollRegions() {
    document.querySelectorAll('.table-responsive:not([tabindex])').forEach((box) => {
        const caption = box.querySelector('caption')?.textContent?.trim();
        box.setAttribute('tabindex', '0');
        box.setAttribute('role', 'region');
        box.setAttribute('aria-label', caption || 'Table');
    });
}
