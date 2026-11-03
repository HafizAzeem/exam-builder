// Enhance long native selects while preserving Vue v-model, change handlers,
// option value types, required validation, and normal form submission.
const states = new WeakMap();
let sequence = 0;

function mount(select) {
    let popup, input, list, filtered = [], active = 0;
    const id = `searchable-${++sequence}`;
    const original = new Map(['aria-expanded', 'aria-controls', 'aria-haspopup'].map(name => [name, select.getAttribute(name)]));
    const options = () => Array.from(select.options).filter(option => !option.hidden && !option.disabled && !option.parentElement.disabled);
    const enabled = () => !select.disabled && !select.multiple && select.size <= 1 && select.options.length > 8;
    const label = () => select.getAttribute('aria-label') || Array.from(select.labels || []).map(el => {
        const copy = el.cloneNode(true);
        copy.querySelectorAll('select, input, button').forEach(control => control.remove());
        return copy.textContent.trim();
    }).join(' ') || 'options';

    function close(focus = false) {
        if (!popup) return;
        popup.remove();
        popup = input = list = null;
        for (const [name, value] of original) {
            if (value === null) select.removeAttribute(name);
            else select.setAttribute(name, value);
        }
        document.removeEventListener('pointerdown', outside, true);
        document.removeEventListener('focusin', focusOutside);
        window.removeEventListener('resize', position);
        document.removeEventListener('scroll', scroll, true);
        if (focus && select.isConnected) select.focus();
    }

    function position() {
        if (!popup) return;
        const rect = select.getBoundingClientRect();
        const viewport = window.visualViewport;
        const width = viewport?.width || window.innerWidth;
        const height = viewport?.height || window.innerHeight;
        const left = viewport?.offsetLeft || 0;
        const top = viewport?.offsetTop || 0;
        popup.style.width = `${Math.min(Math.max(rect.width, 250), width - 16)}px`;
        popup.style.maxHeight = `${Math.max(100, height - 24)}px`;
        const popupHeight = popup.getBoundingClientRect().height;
        popup.style.left = `${Math.max(left + 8, Math.min(rect.left, left + width - popup.getBoundingClientRect().width - 8))}px`;
        popup.style.top = `${Math.max(top + 8, Math.min(rect.bottom + 5, top + height - popupHeight - 8))}px`;
    }

    function choose(option) {
        if (!option || !enabled()) return;
        select.selectedIndex = option.index;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
        close(true);
    }

    function highlight() {
        Array.from(list.children).forEach((item, index) => {
            item.classList.toggle('searchable-option-active', index === active);
        });
        if (filtered[active]) input.setAttribute('aria-activedescendant', `${id}-${active}`);
        else input.removeAttribute('aria-activedescendant');
    }

    function render() {
        if (!popup) return;
        filtered = options().filter(option => option.label.toLocaleLowerCase().includes(input.value.trim().toLocaleLowerCase()));
        active = Math.min(active, Math.max(0, filtered.length - 1));
        list.replaceChildren();
        filtered.forEach((option, index) => {
            const item = document.createElement('li');
            item.id = `${id}-${index}`;
            item.role = 'option';
            item.setAttribute('aria-selected', String(option.selected));
            item.className = 'searchable-option';
            item.textContent = option.label;
            item.addEventListener('pointerdown', event => event.preventDefault());
            item.addEventListener('click', () => choose(option));
            item.addEventListener('pointermove', () => { active = index; highlight(); });
            list.append(item);
        });
        if (!filtered.length) {
            const empty = document.createElement('li');
            empty.className = 'searchable-empty';
            empty.role = 'status';
            empty.textContent = 'No matching options';
            list.append(empty);
        }
        highlight();
        position();
    }

    function key(event) {
        if (event.key === 'Escape' && popup) {
            event.preventDefault();
            event.stopPropagation();
            close(true);
        } else if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            active = Math.max(0, Math.min(filtered.length - 1, active + (event.key === 'ArrowDown' ? 1 : -1)));
            highlight();
            list.children[active]?.scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter') {
            event.preventDefault();
            choose(filtered[active]);
        } else if (event.key === 'Tab') {
            // Return to the original control so Tab continues in form order.
            close(true);
        }
    }

    function open(query = '') {
        if (!enabled() || popup) return;
        popup = document.createElement('div');
        popup.dataset.searchablePopup = '';
        popup.className = 'searchable-popup';
        popup.dir = getComputedStyle(select).direction;
        popup.setAttribute('popover', 'manual');
        input = document.createElement('input');
        input.type = 'search';
        input.className = 'searchable-input';
        input.placeholder = 'Type to search…';
        input.setAttribute('aria-label', `Search ${label()}`);
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'true');
        input.setAttribute('aria-controls', id);
        input.value = query;
        list = document.createElement('ul');
        list.id = id;
        list.role = 'listbox';
        list.setAttribute('aria-label', label());
        list.className = 'searchable-options';
        popup.append(input, list);
        // Keep focus inside native modal dialogs. Popover escapes clipping and
        // stacking contexts in sidebars and scrollable tables.
        (select.closest('dialog') || document.body).append(popup);
        popup.showPopover?.();
        select.setAttribute('aria-expanded', 'true');
        select.setAttribute('aria-controls', id);
        select.setAttribute('aria-haspopup', 'listbox');
        active = Math.max(0, options().findIndex(option => option.selected));
        input.addEventListener('input', () => { active = 0; render(); });
        popup.addEventListener('keydown', key);
        render();
        input.focus({ preventScroll: true });
        list.children[active]?.scrollIntoView({ block: 'nearest' });
        document.addEventListener('pointerdown', outside, true);
        document.addEventListener('focusin', focusOutside);
        window.addEventListener('resize', position);
        document.addEventListener('scroll', scroll, true);
    }

    function outside(event) { if (event.target !== select && !popup?.contains(event.target)) close(); }
    function focusOutside(event) { if (event.target !== select && !popup?.contains(event.target)) close(); }
    function scroll(event) { if (!popup?.contains(event.target)) position(); }
    function pointer(event) {
        if (!enabled()) return;
        event.preventDefault();
        if (popup) close(true);
        else open();
    }
    function triggerKey(event) {
        if (!enabled()) return;
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            open();
        } else if (event.key.length === 1 && !event.ctrlKey && !event.altKey && !event.metaKey) {
            event.preventDefault();
            open(event.key);
        }
    }
    const observer = new MutationObserver(() => {
        if (!enabled()) close();
        else render();
    });
    observer.observe(select, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'hidden', 'label', 'value', 'multiple', 'size'] });
    select.addEventListener('mousedown', pointer);
    select.addEventListener('keydown', triggerKey);
    states.set(select, () => {
        close();
        observer.disconnect();
        select.removeEventListener('mousedown', pointer);
        select.removeEventListener('keydown', triggerKey);
    });
}

export default {
    mounted: mount,
    beforeUnmount(select) { states.get(select)?.(); states.delete(select); },
};
