@include('quality-evaluations._stats')

<div id="quality-evaluations-table-container">
    <div id="table-view">
        @include('quality-evaluations._table')
    </div>
    <div id="grid-view" style="display: none;">
        @include('quality-evaluations._grid')
    </div>
</div>

<script>
    // Ensure the current view (table/grid) is preserved after AJAX update
    (function() {
        var currentView = localStorage.getItem('quality_evaluations_view') || 'table';
        if (typeof switchView === 'function') {
            switchView(currentView);
        } else if (currentView === 'grid') {
            document.getElementById('table-view').style.display = 'none';
            document.getElementById('grid-view').style.display = '';
        } else {
            document.getElementById('grid-view').style.display = 'none';
            document.getElementById('table-view').style.display = '';
        }
    })();
</script>
