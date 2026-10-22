@once
@push('css')
<style>
    .report-filter { padding: 16px; border: 1px solid #e5e7eb; border-radius: 6px; background: #fff; margin-bottom: 16px; }
    .nav-tabs-custom > .nav-tabs > li > a { font-weight: 600; }
</style>
@endpush
@endonce
<div class="report-filter">{{ $slot }}</div>
