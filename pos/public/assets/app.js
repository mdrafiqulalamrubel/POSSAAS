/* app.js — SaaS POS */
 
// NOTE: calcItems() and addItemRow() are defined inline in income-add.php
// because they need page-specific context (currency symbol, correct table ID).
// They must NOT be redefined here — doing so overwrites the working versions.
 
// init listeners once DOM ready
document.addEventListener('DOMContentLoaded', function() {
  // auto-dismiss flash messages after 5s
  var alert = document.querySelector('.alert');
  if (alert) setTimeout(function(){ alert.style.display = 'none'; }, 5000);
});