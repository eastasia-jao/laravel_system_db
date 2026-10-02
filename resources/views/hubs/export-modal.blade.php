<div class="modal fade" id="exportProductModal" tabindex="-1" aria-labelledby="exportProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="productExportForm" action="{{ route('hub.products.export', $hub->id) }}" method="GET">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-success" id="exportProductModalLabel">
                        <i class="fa-solid fa-file-export me-2"></i> Advanced Item & Filter CSV Export
                    </h5>
                </div>
                
                <div class="modal-body">
                    <div id="exportStatus" class="alert alert-primary d-none align-items-center gap-2 py-2" role="status">
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        <span>Preparing your CSV file...</span>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="export_all" value="1" id="exportAllProducts">
                        <label class="form-check-label fw-semibold" for="exportAllProducts">Export all products in this store hub</label>
                        <div class="form-text">Includes every product in this store, regardless of search filters.</div>
                    </div>
                    <fieldset id="exportItemSelection">
                    <label class="form-label small fw-bold mb-1">Search & Select Specific Items</label>
                    
                    <!-- Search Bar with Dynamic Field Selector -->
                    <div class="input-group mb-2">
                        <!-- Search Field Type Selector -->
                        <select class="form-select bg-light text-secondary fw-semibold" id="searchFieldSelect" name="search_field" style="max-width: 170px;">
                            <option value="description" selected>Description / Name</option>
                            <option value="brand">Brand</option>
                            <option value="retail_group">Retail group</option>
                            <option value="retail_department">Retail department</option>
                            <option value="item_id">Item ID</option>
                            <option value="barcode">Barcode</option>
                        </select>
                        
                        <!-- Dynamic Input Container (Switches between Text Input and Select Dropdown) -->
                        <div id="dynamicInputContainer" class="flex-grow-1">
                            <input type="text" id="productSearchInput" class="form-control" placeholder="Type keyword to search...">
                        </div>

                        <button class="btn btn-primary" type="button" id="searchBtn">
                            <i class="fa-solid fa-search"></i> Search
                        </button>

                        <!-- Refresh / Reset Button Added Here -->
                        <button class="btn btn-outline-secondary" type="button" id="resetSearchBtn" title="Reset/Refresh Search Results">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </div>
                    <div class="form-text text-muted mb-3 small" id="searchHelpText">Type a keyword and click search to load matching items below.</div>

                    <!-- Hidden data templates for JavaScript to switch options dynamically -->
                    <div class="d-none">
                        <!-- Brands Options -->
                        <select id="brandOptionsTemplate">
                            <option value="">-- Select Brand --</option>
                            @foreach($brands ?? [] as $brand)
                                @php $bName = is_object($brand) ? ($brand->brand_name ?? $brand->name) : $brand; @endphp
                                <option value="{{ $bName }}">{{ $bName }}</option>
                            @endforeach
                        </select>

                        <!-- Retail Groups Options -->
                        <select id="groupOptionsTemplate">
                            <option value="">-- Select Retail Group --</option>
                            @foreach($groups ?? [] as $group)
                                @php $gName = is_object($group) ? ($group->name ?? $group->group_name) : $group; @endphp
                                <option value="{{ $gName }}">{{ $gName }}</option>
                            @endforeach
                        </select>

                        <!-- Retail Departments Options -->
                        <select id="deptOptionsTemplate">
                            <option value="">-- Select Retail Department --</option>
                            @foreach($departments ?? [] as $dept)
                                @php $dName = is_object($dept) ? ($dept->name ?? $dept->dept_name) : $dept; @endphp
                                <option value="{{ $dName }}">{{ $dName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Dynamic Selected Items Container -->
                    <div class="border rounded p-2 bg-light mb-3" id="searchResultsContainer" style="max-height: 180px; overflow-y: auto;">
                        <div class="text-muted small text-center py-2">No specific items searched yet. Use the search box above.</div>
                    </div>
                    </fieldset>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="exportSubmitBtn" class="btn btn-success">
                        <i class="fa-solid fa-download me-1"></i> Download CSV
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000;">
    <div id="exportSuccessToast" class="toast text-bg-success border-0 shadow-lg" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold">
                <i class="fa-solid fa-circle-check me-2"></i>Export completed successfully. Your CSV file has been downloaded.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
    <div id="exportErrorToast" class="toast text-bg-danger border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><span id="exportErrorMessage">The export could not be completed.</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchFieldSelect = document.getElementById('searchFieldSelect');
    const dynamicInputContainer = document.getElementById('dynamicInputContainer');
    const searchHelpText = document.getElementById('searchHelpText');
    const searchBtn = document.getElementById('searchBtn');
    const resetSearchBtn = document.getElementById('resetSearchBtn');
    const resultsContainer = document.getElementById('searchResultsContainer');
    const exportForm = document.getElementById('productExportForm');
    const exportSubmitBtn = document.getElementById('exportSubmitBtn');
    const exportStatus = document.getElementById('exportStatus');
    const hubId = "{{ $hub->id }}";
    const exportAll = document.getElementById('exportAllProducts');
    const itemSelection = document.getElementById('exportItemSelection');
    exportAll.addEventListener('change', () => {
        itemSelection.disabled = exportAll.checked;
        itemSelection.classList.toggle('opacity-50', exportAll.checked);
    });

    // Switch input element dynamically based on selected criteria category
    searchFieldSelect.addEventListener('change', function() {
        let field = this.value;
        if (field === 'brand') {
            let optionsHtml = document.getElementById('brandOptionsTemplate').innerHTML;
            dynamicInputContainer.innerHTML = `<select id="productSearchInput" class="form-select">${optionsHtml}</select>`;
            searchHelpText.textContent = 'Select a brand and click search to load items below.';
        } else if (field === 'retail_group') {
            let optionsHtml = document.getElementById('groupOptionsTemplate').innerHTML;
            dynamicInputContainer.innerHTML = `<select id="productSearchInput" class="form-select">${optionsHtml}</select>`;
            searchHelpText.textContent = 'Select a retail group and click search to load items below.';
        } else if (field === 'retail_department') {
            let optionsHtml = document.getElementById('deptOptionsTemplate').innerHTML;
            dynamicInputContainer.innerHTML = `<select id="productSearchInput" class="form-select">${optionsHtml}</select>`;
            searchHelpText.textContent = 'Select a retail department and click search to load items below.';
        } else {
            let placeholder = field === 'item_id' ? 'Type item ID...' : (field === 'barcode' ? 'Type barcode...' : 'Type keyword to search...');
            dynamicInputContainer.innerHTML = `<input type="text" id="productSearchInput" class="form-control" placeholder="${placeholder}">`;
            searchHelpText.textContent = 'Type a keyword and click search to load matching items below.';
        }
    });

    function performSearch() {
        const searchInput = document.getElementById('productSearchInput');
        if (!searchInput) return;

        let query = searchInput.value.trim();
        let field = searchFieldSelect.value;
        
        if (!query) {
            AppAlert.show('Please enter a keyword or select an option from the dropdown.');
            return;
        }

        // Clear placeholder text if it's the first search
        if (resultsContainer.querySelector('.text-muted.text-center')) {
            resultsContainer.innerHTML = '';
        }

        // Fetch matching items via AJAX route passing query and chosen field type
        fetch(`/hub/${hubId}/products/search-ajax?q=` + encodeURIComponent(query) + `&field=` + encodeURIComponent(field))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Server returned status ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (!Array.isArray(data) || data.length === 0) {
                    AppAlert.show('No items found matching your selection.');
                    return;
                }

                data.forEach(prod => {
                    // Avoid duplicating items already visible in the list
                    if (!document.getElementById('wrapper_' + prod.id)) {
                        let wrapper = document.createElement('div');
                        wrapper.className = 'form-check mb-1';
                        wrapper.id = 'wrapper_' + prod.id;
                        
                        let itemIdText = prod.item_id || prod.barcode || prod.sku || 'No ID';
                        // Omit brand label entirely if missing or equals "No Brand"
                        let brandText = (prod.brand && prod.brand !== 'No Brand') ? `(${prod.brand})` : '';
                        let prodName = prod.name || prod.description || 'Unnamed Item';

                        const input = document.createElement('input');
                        Object.assign(input, { className: 'form-check-input item-checkbox', type: 'checkbox', name: 'product_ids[]', value: prod.id, id: 'prod_' + prod.id, checked: true });
                        const label = document.createElement('label');
                        label.className = 'form-check-label small';
                        label.htmlFor = input.id;
                        const identifier = document.createElement('strong');
                        identifier.textContent = '[' + itemIdText + ']';
                        const brand = document.createElement('span');
                        brand.className = 'text-muted';
                        brand.textContent = brandText;
                        label.append(identifier, ' ' + prodName + ' ', brand);
                        wrapper.append(input, label);
                        
                        resultsContainer.appendChild(wrapper);

                        // Add event listener: if unchecked, remove it from the list container immediately
                        let checkbox = wrapper.querySelector('input');
                        checkbox.addEventListener('change', function() {
                            if (!this.checked) {
                                wrapper.remove();
                                
                                // If container becomes completely empty, show helper notice
                                if (resultsContainer.children.length === 0) {
                                    resultsContainer.innerHTML = '<div class="text-muted small text-center py-2">No specific items selected.</div>';
                                }
                            }
                        });
                    }
                });

                // Clear input box only if it's a text input
                if (searchInput.tagName === 'INPUT') {
                    searchInput.value = '';
                }
            })
            .catch(error => {
                console.error('Error loading search results:', error);
                AppAlert.show('We could not load the products. Please try searching again.', 'error', { title: 'Search could not finish' });
            });
    }

    // Reset/Refresh Button Logic
    resetSearchBtn.addEventListener('click', function() {
        const searchInput = document.getElementById('productSearchInput');
        if (searchInput) {
            searchInput.value = '';
        }
        searchFieldSelect.selectedIndex = 0;
        // Trigger change event to reset input to text box state if needed
        searchFieldSelect.dispatchEvent(new Event('change'));
        
        // Clear out results container back to default state
        resultsContainer.innerHTML = '<div class="text-muted small text-center py-2">No specific items searched yet. Use the search box above.</div>';
    });

    searchBtn.addEventListener('click', performSearch);
    
    // Listen for enter key globally inside the dynamic container wrapper
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && document.activeElement && document.activeElement.id === 'productSearchInput') {
            e.preventDefault();
            performSearch();
        }
    });

    exportForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const selectedItems = exportForm.querySelectorAll('input[name="product_ids[]"]:checked');
        if (!exportAll.checked && selectedItems.length === 0) {
            document.getElementById('exportErrorMessage').textContent = 'Select at least one product before exporting.';
            AppAlert.show(document.getElementById('exportErrorMessage').textContent, 'error');
            return;
        }

        exportSubmitBtn.disabled = true;
        exportSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Exporting...';
        exportStatus.classList.remove('d-none');
        exportStatus.classList.add('d-flex');

        try {
            const params = new URLSearchParams(new FormData(exportForm));
            const response = await fetch(`${exportForm.action}?${params.toString()}`, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(Object.values(error.errors || {}).flat().join('\n') || error.message || 'The server could not create the export file.');
            }

            if ((response.headers.get('Content-Type') || '').includes('application/json')) {
                const result = await response.json();
                if (result.redirect) { window.location.assign(result.redirect); return; }
                throw new Error('The export status page is unavailable.');
            }
            const blob = await response.blob();
            const disposition = response.headers.get('Content-Disposition') || '';
            const fileNameMatch = disposition.match(/filename="?([^";]+)"?/i);
            const fileName = fileNameMatch ? fileNameMatch[1] : `exporting_products_${new Date().toISOString().replace(/[-:.]/g, '')}.csv`;
            const downloadUrl = URL.createObjectURL(blob);
            const downloadLink = document.createElement('a');
            downloadLink.href = downloadUrl;
            downloadLink.download = fileName;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            downloadLink.remove();
            URL.revokeObjectURL(downloadUrl);

            bootstrap.Modal.getInstance(document.getElementById('exportProductModal'))?.hide();
            AppAlert.show('Your selected products have been exported. The CSV file is ready in your downloads.', 'success', { title: 'Your export is ready', buttonLabel: 'Done' });
        } catch (error) {
            document.getElementById('exportErrorMessage').textContent = error.message || 'The export could not be completed.';
            AppAlert.show(document.getElementById('exportErrorMessage').textContent, 'error');
        } finally {
            exportSubmitBtn.disabled = false;
            exportSubmitBtn.innerHTML = '<i class="fa-solid fa-download me-1"></i> Download CSV';
            exportStatus.classList.add('d-none');
            exportStatus.classList.remove('d-flex');
        }
    });
});
</script>
