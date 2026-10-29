<?php
declare(strict_types=1);

require_once __DIR__ . '/../latiendita.mx/includes/db.php';
require_once __DIR__ . '/../latiendita.mx/includes/functions.php';

$ventaId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$venta = getVenta($ventaId);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La Tiendita de Ferrer | Ticket de venta</title>
    <link rel="stylesheet" href="../latiendita.mx/css/style.css">
    <style>
        body {
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            padding: 30px 12px;
            min-height: 100vh;
        }
        .ticket {
            background: #fff;
            width: 360px;
            max-width: 100%;
            padding: 30px 26px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            font-family: var(--font-body);
            box-shadow: var(--shadow-md);
        }
        .ticket-head { text-align: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid var(--brand-soft); }
        .ticket-head h1 { font-family: var(--font-display); font-size: 27px; color: var(--brand); margin: 0 0 4px; }
        .ticket-head p { margin: 0; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 2px; font-size: 11px; }
        .ticket .fila { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 14px; }
        .ticket .linea-prod { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; margin: 10px 0; }
        .ticket .linea-prod .fila strong { color: var(--brand-dark); }
        .ticket .divider { border-top: 2px dashed #94a3b8; margin: 12px 0; }
        .ticket .total { display: flex; justify-content: space-between; align-items: center; padding-top: 12px; margin-top: 6px; border-top: 2px dashed #94a3b8; font-size: 22px; font-weight: 800; color: var(--brand-dark); }
        .ticket .gracias { text-align: center; margin: 16px 0 0; color: var(--muted); font-size: 14px; }
        .ticket .barcode { height: 46px; margin: 18px auto 6px; background: repeating-linear-gradient(90deg, #0f172a 0 2px, transparent 2px 5px, #0f172a 5px 1px, transparent 6px 8px, #0f172a 8px 4px, transparent 9px 12px, #0f172a 12px 2px, transparent 13px 15px); opacity: 0.85; }
        @media print {
            body { background: #fff; padding: 0; }
            .ticket { box-shadow: none; border: none; width: auto; }
            button { display: none; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="ticket-head">
            <h1>La Tiendita de Ferrer</h1>
            <p>Ticket de venta</p>
        </div>
        <?php if ($venta): ?>
            <div class="fila"><span>Folio:</span> <strong>#<?php echo $venta['id']; ?></strong></div>
            <div class="fila"><span>Fecha:</span> <span><?php echo htmlspecialchars((string) $venta['fecha']); ?></span></div>
            <div class="fila"><span>Vendedor:</span> <span>La Tiendita de Ferrer</span></div>
            <div class="divider"></div>
            <div class="linea-prod">
                <div class="fila"><span>Producto:</span> <strong><?php echo htmlspecialchars((string) ($venta['producto']['nombre'] ?? '')); ?></strong></div>
                <div class="fila"><span>Cantidad:</span> <span><?php echo $venta['cantidad']; ?></span></div>
                <div class="fila"><span>Precio:</span> <span>$<?php echo number_format((float) $venta['total'] / max((int) $venta['cantidad'], 1), 2); ?></span></div>
                <div class="fila"><span>Total línea:</span> <strong>$<?php echo number_format((float) $venta['total'], 2); ?></strong></div>
            </div>
            <div class="fila"><span>Subtotal:</span> <span>$<?php echo number_format((float) $venta['total'], 2); ?></span></div>
            <div class="total"><span>TOTAL:</span> <span>$<?php echo number_format((float) $venta['total'], 2); ?></span></div>
            <div class="barcode"></div>
            <p class="gracias">¡Gracias por tu compra!</p>
        <?php else: ?>
            <div class="fila"><p>No se encontró la venta solicitada.</p></div>
        <?php endif; ?>
        <button class="btn btn-accent" type="button" onclick="window.print()">Imprimir ticket</button>
    </div>
</body>
</html>
