<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=58mm">
    <title>Étiquettes thermique · {{ count($labels) }}×</title>
    <style>
        /* ── Reset ──────────────────────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* ── Aperçu écran ────────────────────────────────────────── */
        body {
            background: #e5e7eb;
            font-family: 'Courier New', monospace;
            padding: 16px;
        }

        .toolbar {
            background: #111827;
            color: #f9fafb;
            font-family: sans-serif;
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

        .label-wrap {
            display: inline-block;
            margin: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
            background: white;
        }

        /* ── Étiquette 58mm ──────────────────────────────────────── */
        .label {
            width: 58mm;
            padding: 1.5mm 2mm 2mm;
            background: white;
            font-size: 7pt;
            color: #111;
            overflow: hidden;
        }

        .label-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 0.4px solid #bbb;
            padding-bottom: 1mm;
            margin-bottom: 1.5mm;
        }

        .tenant-name {
            font-size: 5.5pt;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #555;
            max-width: 30mm;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .label-date {
            font-size: 5pt;
            color: #999;
        }

        .product-name {
            font-family: Arial, sans-serif;
            font-size: 8.5pt;
            font-weight: bold;
            line-height: 1.2;
            margin-bottom: 0.8mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 54mm;
        }

        .sku-row {
            font-size: 6.5pt;
            color: #333;
            font-family: 'Courier New', monospace;
            margin-bottom: 1.5mm;
        }

        .variant-attrs {
            font-size: 6pt;
            color: #666;
            background: #f5f5f5;
            padding: 0.5mm 1mm;
            border-radius: 1mm;
            margin-bottom: 1mm;
        }

        /* Codes zone : QR à gauche, barcode à droite */
        .codes-zone {
            display: flex;
            align-items: center;
            gap: 2mm;
            margin-bottom: 1.5mm;
        }

        .qr-box svg {
            width: 14mm;
            height: 14mm;
            display: block;
        }

        .barcode-box {
            flex: 1;
            text-align: center;
            overflow: hidden;
        }

        .barcode-box svg {
            width: 100%;
            height: 10mm;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            border-top: 0.4px solid #bbb;
            padding-top: 1mm;
        }

        .price-label {
            font-size: 5.5pt;
            color: #666;
            text-transform: uppercase;
        }

        .price-value {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            font-weight: bold;
            color: #111;
        }

        .sale-badge {
            font-size: 5pt;
            background: #dc2626;
            color: white;
            padding: 0.5mm 1mm;
            border-radius: 1mm;
            font-weight: bold;
        }

        /* ── Impression ──────────────────────────────────────────── */
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .toolbar { display: none; }
            .label-wrap { box-shadow: none; margin: 0; display: block; }
            .label { border: none; page-break-after: always; }
            .label:last-child { page-break-after: avoid; }
        }

        @page { size: 58mm auto; margin: 0; }
    </style>
</head>
<body>

<div class="toolbar no-print">
    <strong>🏷 Étiquettes thermique 58mm</strong>
    <span>{{ count($labels) }} étiquette(s) | {{ $format }}</span>
    <button class="btn-print" onclick="window.print()">🖨 Imprimer</button>
</div>

@foreach($labels as $label)
<div class="label-wrap" data-sku="{{ $label['sku'] }}">
<div class="label">

    {{-- En-tête : tenant + date --}}
    <div class="label-header">
        <span class="tenant-name">{{ $label['tenant'] ?: 'ERP Africa' }}</span>
        <span class="label-date">{{ now()->format('d/m/y') }}</span>
    </div>

    {{-- Nom produit --}}
    <div class="product-name">{{ $label['name'] }}</div>

    {{-- SKU --}}
    <div class="sku-row">SKU: {{ $label['sku'] }}</div>

    {{-- Attributs variante (Couleur / Taille) --}}
    @if($label['attributes'])
    <div class="variant-attrs">{{ $label['attributes'] }}</div>
    @endif

    {{-- QR + Barcode côte à côte --}}
    <div class="codes-zone">
        @if($options['show_qr'] && $label['qr'])
        <div class="qr-box">{!! $label['qr'] !!}</div>
        @endif
        <div class="barcode-box">{!! $label['barcode'] !!}</div>
    </div>

    {{-- Prix --}}
    @if($options['show_price'])
    <div class="price-row">
        <span class="price-label">Prix</span>
        <div style="display:flex;align-items:baseline;gap:2mm">
            @if($label['is_on_sale'])
            <span class="sale-badge">PROMO</span>
            @endif
            <span class="price-value">{{ $label['price'] }}</span>
        </div>
    </div>
    @endif

</div>
</div>
@endforeach

</body>
</html>
