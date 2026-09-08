<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="{{ route('products.store.hub', $hub->id) }}" method="POST">
                @csrf
                <input type="hidden" name="store_hub_id" value="{{ request('hub_id') }}">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="addProductModalLabel">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="row g-4">
                        {{-- Right Column: Information & Pricing (Expanded to full width) --}}
                        <div class="col-12">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <h6 class="text-primary fw-bold mb-3"><i class="fa-solid fa-circle-info me-2"></i>General Information</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="small fw-bold text-muted">Item ID</label>
                                            <input type="text" name="item_id" class="form-control" placeholder="e.g. ITM-001" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-bold text-muted">Product Name</label>
                                            <input type="text" name="name" class="form-control" placeholder="e.g. Watercolor Set" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="small fw-bold text-muted">Description</label>
                                            <textarea name="description" class="form-control" rows="2" placeholder="Brief product description..."></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-bold text-muted">Barcode</label>
                                            <input type="text" name="barcode" class="form-control" placeholder="Scan or type barcode">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-bold text-muted">Brand Name</label>
                                            <select name="brand" class="form-select">
                                                <option value="">Select Brand</option>
                                                @foreach($brands as $brand)
                                                    <option value="{{ $brand }}">{{ $brand }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-bold text-muted">Retail Group</label>
                                            <select name="retail_group" class="form-select">
                                                <option value="">Select Group</option>
                                                @foreach($groups as $group)
                                                    <option value="{{ $group }}">{{ $group }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-bold text-muted">Retail Dept</label>
                                            <select name="retail_department" class="form-select">
                                                <option value="">Select Department</option>
                                                @foreach($departments as $department)
                                                    <option value="{{ $department }}">{{ $department }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="text-primary fw-bold mb-3"><i class="fa-solid fa-peso-sign me-2"></i>Pricing Details</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-muted">Cost Price</label>
                                            <div class="input-group"><span class="input-group-text">₱</span><input type="number" step="0.01" name="cost_price" class="form-control" placeholder="0.00"></div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-muted">Sales Price</label>
                                            <div class="input-group"><span class="input-group-text">₱</span><input type="number" step="0.01" name="sales_price" class="form-control" placeholder="0.00"></div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-muted">Wholesale</label>
                                            <div class="input-group"><span class="input-group-text">₱</span><input type="number" step="0.01" name="wholesale_price" class="form-control" placeholder="0.00"></div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-muted">Shopee</label>
                                            <div class="input-group"><span class="input-group-text">₱</span><input type="number" step="0.01" name="shopee_price" class="form-control" placeholder="0.00"></div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-muted">Lazada</label>
                                            <div class="input-group"><span class="input-group-text">₱</span><input type="number" step="0.01" name="lazada_price" class="form-control" placeholder="0.00"></div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-muted">TikTok</label>
                                            <div class="input-group"><span class="input-group-text">₱</span><input type="number" step="0.01" name="tiktok_price" class="form-control" placeholder="0.00"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Add Modal Image Preview Handler
    document.getElementById('product_image')?.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('image-preview');
                if(img) {
                    img.src = e.target.result;
                    img.classList.remove('d-none');
                }
                const prompt = document.getElementById('upload-prompt');
                if(prompt) {
                    prompt.classList.add('d-none');
                }
            }
            reader.readAsDataURL(file);
        }
    });
</script>