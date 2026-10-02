@push('scripts')
<script type="module">
    import { setupHubImport } from {{ Illuminate\Support\Js::from(asset('js/store-hub-import.js')) }};
    setupHubImport();
</script>
@endpush
<style>
    .import-loader-shell {
        background: linear-gradient(145deg, #f8fbff 0%, #eef5ff 100%);
        border: 1px solid #dbeafe;
        border-radius: 18px;
        padding: 28px;
    }
    .import-loader-visual { display: flex; align-items: center; justify-content: center; gap: 18px; min-height: 76px; }
    .import-loader-node {
        width: 58px; height: 58px; display: grid; place-items: center; border-radius: 16px;
        background: #fff; color: #2563eb; font-size: 23px;
        box-shadow: 0 8px 24px rgba(37, 99, 235, .12); border: 1px solid #dbeafe;
    }
    .import-loader-flow { position: relative; width: 100px; height: 3px; overflow: hidden; border-radius: 999px; background: #bfdbfe; }
    .import-loader-flow::after {
        content: ''; position: absolute; top: -3px; width: 10px; height: 10px; border-radius: 50%;
        background: #2563eb; box-shadow: 0 0 12px #3b82f6; animation: importDataFlow 1.25s ease-in-out infinite;
    }
    .import-indeterminate { height: 7px; overflow: hidden; border-radius: 999px; background: #dbeafe; }
    .import-indeterminate::before {
        content: ''; display: block; width: 38%; height: 100%; border-radius: inherit;
        background: linear-gradient(90deg, #2563eb, #60a5fa); animation: importProgress 1.4s ease-in-out infinite;
    }
    .import-step-dot {
        width: 22px; height: 22px; display: inline-grid; place-items: center;
        border-radius: 50%; flex: 0 0 auto; font-size: 10px;
    }
    @keyframes importDataFlow { from { left: -12px; } to { left: 102px; } }
    @keyframes importProgress { from { transform: translateX(-110%); } to { transform: translateX(275%); } }
    @media (prefers-reduced-motion: reduce) {
        .import-loader-flow::after, .import-indeterminate::before { animation-duration: 3s; }
    }
</style>

<div class="modal fade" id="importProductModal" tabindex="-1" aria-labelledby="importProductModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div x-data="{
                importing: false,
                fileName: '',
                elapsed: 0,
                activeStep: 0,
                elapsedTimer: null,
                stepTimer: null,
                steps: ['Uploading the CSV file', 'Checking the file format', 'Applying product updates', 'Recording the import activity'],
                startImport() {
                    if (this.importing) return;
                    this.importing = true;
                    this.elapsed = 0;
                    this.activeStep = 0;
                    this.elapsedTimer = setInterval(() => this.elapsed++, 1000);
                    this.stepTimer = setInterval(() => {
                        if (this.activeStep < this.steps.length - 1) this.activeStep++;
                    }, 2200);
                }
            }">
                <form id="storeHubImportForm" @submit.prevent="startImport(); $el.submit()" action="{{ route('hub.products.import', $hub->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="modal-header border-0 px-4 pt-4 pb-2">
                        <div>
                            <h5 class="modal-title fw-bold" id="importProductModalLabel"><i class="fa-solid fa-file-import text-success me-2"></i>Import Products</h5>
                            <div class="small text-muted mt-1" x-show="!importing">Upload a CSV file to import products directly into this branch.</div>
                            <div class="small text-muted mt-1">Branch imports keep existing shared product details. Head Office imports can update those details for all branches.</div>
                        </div>
                    </div>

                    <div class="modal-body px-4 pb-4">
                        <div x-show="!importing">
                            <label class="form-label fw-semibold">CSV inventory file</label>
                            <div class="border rounded-3 bg-light p-4 text-center">
                                <i class="fa-solid fa-cloud-arrow-up text-success fs-2 mb-3"></i>
                                <input type="file" name="file" accept=".csv,.txt,text/csv" required class="form-control" @change="fileName = $event.target.files[0]?.name || ''">
                                <div class="small text-muted mt-2">CSV or TXT format</div>
                                
                                <div id="storeHubImportError" class="alert alert-danger mt-3 mb-0 text-start d-none" role="alert" style="white-space: pre-line;"></div>
                            </div>
                        </div>

                        <div x-show="importing" style="display: none;" aria-live="polite">
                            <div class="import-loader-shell">
                                <div class="import-loader-visual mb-3" aria-hidden="true">
                                    <div class="import-loader-node"><i class="fa-solid fa-file-csv"></i></div>
                                    <div class="import-loader-flow"></div>
                                    <div class="import-loader-node"><i class="fa-solid fa-database"></i></div>
                                </div>

                                <div class="text-center mb-3">
                                    <h6 class="fw-bold mb-1">Importing products</h6>
                                    <div class="small text-muted text-truncate" x-text="fileName || 'Selected CSV file'"></div>
                                </div>

                                <div class="import-indeterminate mb-4" role="progressbar" aria-label="Import in progress"></div>

                                <div class="d-flex flex-column gap-2">
                                    <template x-for="(step, index) in steps" :key="index">
                                        <div class="d-flex align-items-center gap-2 small" :class="index > activeStep ? 'text-muted opacity-50' : 'text-dark'">
                                            <span class="import-step-dot" :class="index < activeStep ? 'bg-success text-white' : (index === activeStep ? 'bg-primary text-white' : 'bg-secondary-subtle text-secondary')">
                                                <i class="fa-solid" :class="index < activeStep ? 'fa-check' : (index === activeStep ? 'fa-spinner fa-spin' : 'fa-circle')"></i>
                                            </span>
                                            <span x-text="step" :class="index === activeStep ? 'fw-semibold' : ''"></span>
                                        </div>
                                    </template>
                                </div>

                                <div class="alert alert-light border small text-muted mt-4 mb-0 py-2">
                                    <i class="fa-solid fa-circle-info me-1 text-primary"></i>
                                    Keep this window open while the import completes. Large files may take several minutes.
                                    <span class="float-end font-monospace" x-text="Math.floor(elapsed / 60) + ':' + String(elapsed % 60).padStart(2, '0')"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 px-4 pb-4 pt-0" x-show="!importing">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-4"><i class="fa-solid fa-play me-2"></i>Start Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
