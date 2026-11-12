// assets/js/filter.js - Table search and multi-filtering

document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('tableSearchInput');
  const categoryFilter = document.getElementById('categoryFilter');
  const statusFilter = document.getElementById('statusFilter');
  const table = document.getElementById('dataTable');

  if (!table) return;
  const rows = table.querySelectorAll('tbody tr');

  function filterTable() {
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const catVal = categoryFilter ? categoryFilter.value.toLowerCase().trim() : '';
    const statusVal = statusFilter ? statusFilter.value.toLowerCase().trim() : '';

    let visibleCount = 0;

    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      const rowCat = (row.getAttribute('data-category') || '').toLowerCase();
      const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();

      const matchesQuery = !query || text.includes(query);
      const matchesCat = !catVal || rowCat === catVal || catVal === 'all';
      const matchesStatus = !statusVal || rowStatus === statusVal || statusVal === 'all';

      if (matchesQuery && matchesCat && matchesStatus) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    const emptyRow = document.getElementById('tableEmptyRow');
    if (emptyRow) {
      emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
    }
  }

  if (searchInput) searchInput.addEventListener('input', filterTable);
  if (categoryFilter) categoryFilter.addEventListener('change', filterTable);
  if (statusFilter) statusFilter.addEventListener('change', filterTable);
});
