import { ref } from 'vue';

// Desktop sidebar: icons only by default, expanded on request. Kept at module level so every page
// (each mounts AppLayout fresh) starts in the same state, and remembered in this browser.
const KEY = 'metmemo.sidebarExpanded';

const read = () => {
    try { return localStorage.getItem(KEY) === '1'; } catch { return false; }
};

export const sidebarExpanded = ref(read());

export const toggleSidebar = () => {
    sidebarExpanded.value = ! sidebarExpanded.value;
    try { localStorage.setItem(KEY, sidebarExpanded.value ? '1' : '0'); } catch { /* private window: just don't remember */ }
};
