<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Étiquettes A4 · {{ count($labels) }}×</title>
    <style>
        /* ── Reset ──────────────────────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* ── Aperçu écran ────────────────────────────────────────── */
        body {
            background: #e5e7eb;
            font-family: Arial, sans-serif;
            padding: 16px;
        }

        .toolbar {
            background: #111827;
            color: #f9fafb;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .toolbar strong { font-size: 14px; }
        .btn-print {
            background: #16a34a;
            color: white;
            border: none;
            padding: 6px 18px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            margin-left: auto;
        }
        .btn-print:hover { background: #15803d; }

        .page {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 16px;
            padding: 4.7mm 7.4mm;
            box-shadow: 0 4px 16px rgba(0,0,0,.2);
        }

        /* ── Grille 3×8 — compatible Avery L7159 ────────────────── */
        /* Chaque étiquette : 63.5mm × 33.9mm, gouttière col 2.5mm  */
        .label-grid {
            display: grid;
            grid-template-columns: repeat(3, 63.5mm);
            grid-template-rows: repeat(8, 33.9mm);
            column-gap: 2.5mm;
            row-gap: 0;
        }

        /* ── Étiquette ───────────────────────────────────────────── */
        .label {
            width: 63.5mm;
            height: 33.9mm;
            padding: 1.8mm 2mm;
            border: 0.4px dashed #d0d0d0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            font-size: 7pt;
            color: #111;
        }

        .label-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .label-info { flex: 1; overflow: hidden; }

        .tenant-name {
            font-size: 5pt;
            text-transform: uppercase;
            color: #888;
            letter-spacing: .4px;
        }

        .product-name {
            font-size: 8pt;
            font-weight: bold;
            line-height: 1.2;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            max-width: 38mm;
        }

        .sku-text {
            font-family: 'Courier New', monospace;
            font-size: 6pt;
            color: #444;
            margin-top: 0.5mm;
        }

        .variant-badge {
            font-size: 5.5pt;
            background: #f0f0f0;
            color: #555;
            padding: 0.3mm 1mm;
            border-radius: 1mm;
            margin-top: 0.5mm;
            display: inline-block;
        }

        /* QR en haut à droite */
        .qr-thumb svg {
            width: 12mm;
            height: 12mm;
            display: block;
        }

        /* Barcode bas */
        .label-bottom { display: flex; align-items: flex-end; gap: 2mm; }

        .barcode-area {
            flex: 1;
            overflow: hidden;
        }

        .barcode-area svg {
            width: 100%;
            height: 9mm;
            display: block;
        }

        .price-badge {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            font-weight: bold;
            white-space: nowrap;
            text-align: right;
        }

        .sale-dot {
            width: 2mm;
            height: 2mm;
            background: #dc2626;
            border-radius: 50%;
            display: inline-block;
            vertical-align: middle;
        }

        /* ── Impression ──────────────────────────────────────────── */
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .toolbar { display: none; }
            .page {
                box-shadow: none;
                margin: 0;
                page-break-after: always;
                padding: 4.7mm 7.4mm;
            }
            .page:last-child { page-break-after: avoid; }
            .label { border-color: transparent; }
        }

        @page { size: A4; margin: 0; }
    </style>
</head>
<body>

<div class="toolbar no-print">
    <strong>🗒 Étiquettes A4 (3×8 = 24/page)</strong>
    <span>{{ count($labels) }} étiquette(s) · {{ ceil(count($labels) / 24) }} page(s)</span>
    <button class="btn-print" onclick="window.print()">🖨 Imprimer</button>
</div>

@foreach($labels->chunk(24) as $pageLabels)
<div class="page">
<div class="label-grid">
@foreach($pageLabels as $label)
<div class="label" data-sku="{{ $label['sku'] }}">

    <div class="label-top">
        <div class="label-info">
            <div class="tenant-name">{{ $label['tenant'] ?: 'ERP Africa' }}</div>
            <div class="product-name">{{ $label['name'] }}</div>
            <div class="sku-text">{{ $label['sku'] }}</div>
            @if($label['attributes'])
            <div class="variant-badge">{{ $label['attributes'] }}</div>
            @endif
        </div>

        @if($options['show_qr'] && $label['qr'])
        <div class="qr-thumb">{!! $label['qr'] !!}</div>
        @endif
    </div>

    <div class="label-bottom">
        <div class="barcode-area">{!! $label['barcode'] !!}</div>
        @if($options['show_price'])
        <div class="price-badge">
            @if($label['is_on_sale'])<span class="sale-dot"></span> @endif
            {{ $label['price'] }}
        </div>
        @endif
    </div>

</div>
@endforeach

{{-- Remplir les cases vides de la dernière page --}}
@for($empty = 0; $empty < 24 - count($pageLabels); $empty++)
<div class="label"></div>
@endfor

</div>{{-- /label-grid --}}
</div>{{-- /page --}}
@endforeach

</body>
</html>
