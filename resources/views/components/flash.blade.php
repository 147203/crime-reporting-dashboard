@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show d-flex gap-2 align-items-start" role="status">
        <i class="bi bi-check-circle" aria-hidden="true"></i>
        <div>{{ session('status') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show d-flex gap-2 align-items-start" role="status">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
        <div>{{ session('warning') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <div class="fw-semibold"><i class="bi bi-x-octagon" aria-hidden="true"></i> Please correct the highlighted fields.</div>
    </div>
@endif
