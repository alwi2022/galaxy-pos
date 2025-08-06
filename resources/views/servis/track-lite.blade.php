@extends('layouts.track')

@section('title', 'Cek Status Servis')

@section('content')
    <div class="container">
        <div class="status-header">
            <h2>Status Servis</h2>
        </div>

        @php
            $statusText = [
                'diproses' => 'Barang sedang diproses',
                'selesai'  => 'Servis selesai',
                'diambil'  => 'Barang telah diambil oleh pelanggan',
            ];
        @endphp

        <!-- Status Timeline -->
        <div class="timeline-section">
            <h4>Riwayat Status</h4>
            <div class="timeline">
                @forelse ($servis->logs as $log)
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-date">{{ $log->created_at->format('d/m/Y H:i') }}</div>
                            <div class="timeline-status">{{ $statusText[$log->status] ?? ucfirst($log->status) }}</div>
                            <div class="timeline-user">Diperbarui oleh {{ $log->user->name }}</div>
                        </div>
                    </div>
                @empty
                    <div class="timeline-empty">
                        <div class="empty-state">Belum ada riwayat status</div>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Service Details -->
        <div class="details-section">
            <h4>Detail Servis</h4>
            <div class="details-grid">
                <div class="detail-item">
                    <label>Kode Servis</label>
                    <span class="detail-value code">{{ $servis->kode_servis }}</span>
                </div>
                
                <div class="detail-item">
                    <label>Nama Pelanggan</label>
                    <span class="detail-value">{{ $servis->nama_pelanggan }}</span>
                </div>
                
                <div class="detail-item">
                    <label>Telepon</label>
                    <span class="detail-value">{{ $servis->telepon ?? '-' }}</span>
                </div>
                
                <div class="detail-item">
                    <label>Tipe Barang</label>
                    <span class="detail-value">{{ $servis->tipe_barang }}</span>
                </div>
                
                <div class="detail-item full-width">
                    <label>Kerusakan</label>
                    <span class="detail-value">{{ $servis->kerusakan }}</span>
                </div>
                
                <div class="detail-item">
                    <label>Biaya Servis</label>
                    <span class="detail-value price">Rp {{ number_format($servis->biaya_servis, 0, ',', '.') }}</span>
                </div>
                
                <div class="detail-item">
                    <label>Status Saat Ini</label>
                    <span class="status-badge status-{{ $servis->status }}">{{ strtoupper($servis->status) }}</span>
                </div>
                
                <div class="detail-item full-width">
                    <label>Update Terakhir</label>
                    <span class="detail-value">{{ tanggal_indonesia($servis->updated_at, true) }}</span>
                </div>
            </div>
        </div>
    </div>

    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .status-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .status-header h2 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
            font-size: 28px;
        }

        /* Timeline Styles */
        .timeline-section {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .timeline-section h4 {
            color: #495057;
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-marker {
            position: absolute;
            left: -18px;
            top: 4px;
            width: 12px;
            height: 12px;
            background: #007bff;
            border: 3px solid #ffffff;
            border-radius: 50%;
            box-shadow: 0 0 0 2px #007bff;
        }

        .timeline-content {
            padding-left: 20px;
        }

        .timeline-date {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
        }

        .timeline-status {
            color: #2c3e50;
            font-weight: 600;
            margin: 2px 0;
        }

        .timeline-user {
            color: #6c757d;
            font-size: 13px;
        }

        .timeline-empty {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state {
            color: #6c757d;
            font-style: italic;
        }

        /* Details Section */
        .details-section {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .details-section h4 {
            color: #495057;
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-item.full-width {
            grid-column: 1 / -1;
        }

        .detail-item label {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            color: #2c3e50;
            font-size: 16px;
            font-weight: 500;
            word-break: break-word;
        }

        .detail-value.code {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            border: 1px solid #e9ecef;
            display: inline-block;
            word-break: break-all;
            max-width: 100%;
            box-sizing: border-box;
        }

        .detail-value.price {
            color: #28a745;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: fit-content;
        }

        .status-badge.status-diproses {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-badge.status-selesai {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .status-badge.status-diambil {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Enhanced Responsive Design */
        
        /* Large tablets and small desktops */
        @media (max-width: 1024px) {
            .container {
                max-width: 90%;
                padding: 16px;
            }
        }

        /* Tablets */
        @media (max-width: 768px) {
            .container {
                max-width: 100%;
                padding: 15px;
                margin: 0;
            }

            .status-header {
                margin-bottom: 20px;
                padding-bottom: 15px;
            }

            .status-header h2 {
                font-size: 24px;
            }

            .timeline-section,
            .details-section {
                padding: 20px;
                margin-bottom: 20px;
            }

            .timeline-section h4,
            .details-section h4 {
                font-size: 16px;
                margin-bottom: 15px;
                padding-bottom: 10px;
            }

            .details-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .detail-item.full-width {
                grid-column: 1;
            }

            .timeline {
                padding-left: 25px;
            }

            .timeline-marker {
                left: -16px;
                width: 10px;
                height: 10px;
            }

            .timeline-content {
                padding-left: 15px;
            }

            .timeline-date {
                font-size: 13px;
            }

            .timeline-status {
                font-size: 15px;
            }

            .timeline-user {
                font-size: 12px;
            }

            .detail-value {
                font-size: 15px;
            }

            .detail-item label {
                font-size: 13px;
                margin-bottom: 4px;
            }
        }

        /* Large phones */
        @media (max-width: 640px) {
            .container {
                padding: 12px;
            }

            .status-header {
                margin-bottom: 16px;
                padding-bottom: 12px;
            }

            .status-header h2 {
                font-size: 22px;
            }

            .timeline-section,
            .details-section {
                padding: 16px;
                margin-bottom: 16px;
            }

            .details-grid {
                gap: 14px;
            }

            .timeline-empty {
                padding: 30px 15px;
            }

            .detail-value.code {
                padding: 6px 10px;
                font-size: 14px;
            }

            .status-badge {
                padding: 5px 10px;
                font-size: 11px;
            }
        }

        /* Small phones */
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }

            .status-header h2 {
                font-size: 20px;
            }

            .timeline-section,
            .details-section {
                padding: 14px;
                margin-bottom: 14px;
                border-radius: 6px;
            }

            .timeline-section h4,
            .details-section h4 {
                font-size: 15px;
                margin-bottom: 12px;
                padding-bottom: 8px;
            }

            .details-grid {
                gap: 12px;
            }

            .timeline {
                padding-left: 20px;
            }

            .timeline::before {
                left: 10px;
            }

            .timeline-marker {
                left: -14px;
                width: 8px;
                height: 8px;
                border: 2px solid #ffffff;
            }

            .timeline-content {
                padding-left: 12px;
            }

            .timeline-date {
                font-size: 12px;
            }

            .timeline-status {
                font-size: 14px;
            }

            .timeline-user {
                font-size: 11px;
            }

            .detail-value {
                font-size: 14px;
            }

            .detail-item label {
                font-size: 12px;
                margin-bottom: 3px;
            }

            .detail-value.code {
                padding: 5px 8px;
                font-size: 13px;
            }

            .status-badge {
                padding: 4px 8px;
                font-size: 10px;
                border-radius: 15px;
            }

            .timeline-empty {
                padding: 25px 10px;
            }

            .empty-state {
                font-size: 14px;
            }
        }

        /* Extra small devices */
        @media (max-width: 360px) {
            .container {
                padding: 8px;
            }

            .status-header {
                margin-bottom: 12px;
                padding-bottom: 10px;
            }

            .status-header h2 {
                font-size: 18px;
            }

            .timeline-section,
            .details-section {
                padding: 12px;
                margin-bottom: 12px;
            }

            .timeline-section h4,
            .details-section h4 {
                font-size: 14px;
                margin-bottom: 10px;
                padding-bottom: 6px;
            }

            .details-grid {
                gap: 10px;
            }

            .detail-value {
                font-size: 13px;
            }

            .detail-item label {
                font-size: 11px;
            }

            .detail-value.code {
                font-size: 12px;
                padding: 4px 6px;
            }
        }

        /* Landscape orientation for mobile devices */
        @media (max-height: 500px) and (orientation: landscape) {
            .container {
                padding: 8px;
            }

            .status-header {
                margin-bottom: 15px;
                padding-bottom: 10px;
            }

            .status-header h2 {
                font-size: 20px;
            }

            .timeline-section,
            .details-section {
                padding: 15px;
                margin-bottom: 15px;
            }

            .timeline-empty {
                padding: 20px 15px;
            }
        }

        /* Print styles */
        @media print {
            .container {
                max-width: none;
                padding: 0;
            }

            .timeline-section,
            .details-section {
                box-shadow: none;
                border: 1px solid #ccc;
                break-inside: avoid;
            }

            .details-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
@endsection