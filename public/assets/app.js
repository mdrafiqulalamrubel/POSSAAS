/* app.js — SaaS POS */
 
// NOTE: calcItems() and addItemRow() are defined inline in income-add.php
// because they need page-specific context (currency symbol, correct table ID).
// They must NOT be redefined here — doing so overwrites the working versions.
 
// init listeners once DOM ready
document.addEventListener('DOMContentLoaded', function() {
  // auto-dismiss flash messages after 5s
  var alert = document.querySelector('.alert');
  if (alert) setTimeout(function(){ alert.style.display = 'none'; }, 5000);

  // ── Restore nav group collapse states ──────────────────────
  try {
    var states = JSON.parse(localStorage.getItem('nav_groups') || '{}');
    Object.keys(states).forEach(function(id) {
      var group = document.getElementById(id);
      if (!group) return;
      if (states[id]) {
        group.classList.add('open');
      } else {
        group.classList.remove('open');
      }
    });
  } catch(e) {}

  // Auto-open the group containing the current active link
  var activeLink = document.querySelector('.nav-link.active');
  if (activeLink) {
    var body = activeLink.closest('.nav-group-body');
    if (body) {
      var group = body.closest('.nav-group');
      if (group) group.classList.add('open');
    }
  }
});

// ── Nav group collapse ─────────────────────────────────────
function toggleNavGroup(id) {
  // Don't toggle when sidebar is in icon-only (collapsed) mode
  if (document.body.classList.contains('sidebar-collapsed')) return;
  var group = document.getElementById(id);
  if (!group) return;
  var isOpen = group.classList.toggle('open');
  // Persist state in localStorage
  try {
    var states = JSON.parse(localStorage.getItem('nav_groups') || '{}');
    states[id] = isOpen;
    localStorage.setItem('nav_groups', JSON.stringify(states));
  } catch(e) {}
}