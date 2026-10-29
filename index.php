<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../latiendita.mx/includes/db.php';
require_once __DIR__ . '/../latiendita.mx/includes/functions.php';

const ADMIN_PASSWORD = 'admin1234';

$vista = $_GET['vista'] ?? 'catalogo';
$productos = getProductos();
$mensaje = '';
$error = '';
$mensajeVenta = '';

// ---- Admin: login ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?vista=admin');
        exit;
    }
    $error = 'Contraseña incorrecta';
}

// ---- Admin: cerrar sesion ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cerrar_sesion'])) {
    unset($_SESSION['admin']);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?vista=admin');
    exit;
}

// ---- Admin: guardar producto ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_producto'])) {
    $pdo = conectar();
    $id = (int) ($_POST['producto_id'] ?? 0);
    $nombre = trim((string) ($_POST['nombre_producto'] ?? ''));
    $cantidad = (int) ($_POST['cantidad_producto'] ?? 0);
    $imagen = trim((string) ($_POST['imagen_producto'] ?? ''));

    // Si se subio una imagen desde el PC, se guarda en la carpeta uploads.
    if (isset($_FILES['imagen_archivo']) && (int) $_FILES['imagen_archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['imagen_archivo'];
        $ext = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $permitidas, true)) {
            $nombreArchivo = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $carpetaUploads = __DIR__ . '/uploads';
            if (is_dir($carpetaUploads) && move_uploaded_file((string) $archivo['tmp_name'], $carpetaUploads . '/' . $nombreArchivo)) {
                $imagen = 'uploads/' . $nombreArchivo;
            }
        }
    }

    if ($pdo !== null && $id > 0 && $nombre !== '') {
        $stmt = $pdo->prepare('UPDATE productos SET nombre = ?, cantidad = ?, imagen = ? WHERE `ID productos` = ?');
        $stmt->execute([$nombre, $cantidad, $imagen, $id]);
        $mensaje = 'Producto actualizado correctamente';
    } else {
        $error = 'No se pudo actualizar el producto';
    }
}

// ---- Admin: agregar nuevo producto ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_nuevo'])) {
    $pdo = conectar();
    $nombreNuevo = trim((string) ($_POST['nombre_nuevo'] ?? ''));
    $costoNuevo = (float) ($_POST['costo_nuevo'] ?? 0);
    $cantidadNuevo = (int) ($_POST['cantidad_nuevo'] ?? 0);
    $categoriaNuevo = trim((string) ($_POST['categoria_nuevo'] ?? 'General'));
    $descripcionNuevo = trim((string) ($_POST['descripcion_nuevo'] ?? ''));
    $imagenNuevo = trim((string) ($_POST['imagen_url_nuevo'] ?? ''));

    if (isset($_FILES['imagen_archivo_nuevo']) && (int) $_FILES['imagen_archivo_nuevo']['error'] === UPLOAD_ERR_OK) {
        $archivoNuevo = $_FILES['imagen_archivo_nuevo'];
        $extNuevo = strtolower(pathinfo((string) $archivoNuevo['name'], PATHINFO_EXTENSION));
        $permitidasNuevas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($extNuevo, $permitidasNuevas, true)) {
            $nombreArchivoNuevo = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extNuevo;
            $carpetaUploadsNuevo = __DIR__ . '/uploads';
            if (is_dir($carpetaUploadsNuevo) && move_uploaded_file((string) $archivoNuevo['tmp_name'], $carpetaUploadsNuevo . '/' . $nombreArchivoNuevo)) {
                $imagenNuevo = 'uploads/' . $nombreArchivoNuevo;
            }
        }
    }

    if ($pdo !== null && $nombreNuevo !== '') {
        $stmtNuevo = $pdo->prepare('INSERT INTO productos (nombre, costo, cantidad, categoria, descripcion, imagen, destacado) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmtNuevo->execute([$nombreNuevo, $costoNuevo, $cantidadNuevo, $categoriaNuevo, $descripcionNuevo, $imagenNuevo, 0]);
        $mensaje = 'Producto agregado correctamente';
    } else {
        $error = 'No se pudo agregar el producto';
    }
}

// ---- POS: agregar producto al ticket ----
$lineas = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar'])) {
    $idProducto = (int) ($_POST['nueva_id'] ?? 0);
    $cantidadProducto = (int) ($_POST['nueva_cantidad'] ?? 1);
    $productoEncontrado = null;
    foreach ($productos as $p) {
        if ((int) $p['id'] === $idProducto) {
            $productoEncontrado = $p;
            break;
        }
    }
    if ($productoEncontrado && $cantidadProducto > 0) {
        $lineas[] = [
            'id' => (int) $productoEncontrado['id'],
            'cantidad' => $cantidadProducto,
            'precio' => (float) $productoEncontrado['precio'],
        ];
        $_SESSION['ticket'][] = $lineas[count($lineas) - 1];
        header('Location: ' . $_SERVER['PHP_SELF'] . '?vista=pos');
        exit;
    }
}

// ---- POS: finalizar compra ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar'])) {
    $lineasFinalizar = [];
    foreach ($_POST['lineas'] as $l) {
        if ((int) ($l['id'] ?? 0) > 0 && (int) ($l['cantidad'] ?? 0) > 0) {
            $lineasFinalizar[] = [
                'id' => (int) $l['id'],
                'cantidad' => (int) $l['cantidad'],
                'precio' => (float) ($l['precio'] ?? 0),
            ];
        }
    }
    $ventaId = registrarVenta($lineasFinalizar);
    if ($ventaId > 0) {
        unset($_SESSION['ticket']);
        header('Location: ' . 'ticket.php?id=' . $ventaId);
        exit;
    }
    $error = 'No se pudo registrar la venta';
}

$esAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$ventasTicket = isset($_SESSION['ticket']) && is_array($_SESSION['ticket']) ? $_SESSION['ticket'] : [];

$subtotalTicket = 0;
foreach ($ventasTicket as $l) {
    $subtotalTicket += (float) $l['precio'] * (int) $l['cantidad'];
}

$productosMap = [];
foreach ($productos as $p) {
    $productosMap[(int) $p['id']] = $p;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La Tiendita de Ferrer | Catálogo simple</title>
    <meta name="description" content="Catálogo simple de la tienda La Tiendita de Ferrer.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../latiendita.mx/css/style.css">
    <style>
        /* Paleta azul - La Tiendita de Ferrer (version simple) */
        :root {
            --brand: #2563eb;
            --brand-dark: #1d4ed8;
            --brand-soft: #dbeafe;
            --accent: #60a5fa;
            --accent-dark: #3b82f6;
            --ink: #0f172a;
            --muted: #64748b;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --danger: #b91c1c;
            --shadow-lg: 0 24px 60px rgba(37, 99, 235, 0.18);
        }
        body {
            background:
                radial-gradient(1100px 480px at 85% -10%, rgba(37, 99, 235, 0.14), transparent 60%),
                linear-gradient(180deg, #dbeafe 0%, #f8fafc 340px, #f8fafc 100%);
        }
        .hero-simple { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; padding: 64px 0 72px; text-align: center; }
        .hero-simple h1 { font-family: var(--font-display); font-size: 46px; margin: 0 0 12px; }
        .hero-simple p { color: #bfdbfe; font-size: 18px; margin: 0 0 26px; }
        .hero-simple .hero-cta { display: inline-block; background: #fff; color: var(--brand-dark); border: 2px solid #fff; padding: 12px 30px; border-radius: 999px; font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>

    <header class="header">
        <div class="container header-inner">
            <a href="?vista=catalogo" class="logo" aria-label="Ir al inicio de La Tiendita de Ferrer">
                <span class="logo-mark" aria-hidden="true"></span>
                <span class="logo-text">La Tiendita de Ferrer</span>
            </a>
            <nav class="nav" aria-label="Navegación principal">
                <a href="?vista=catalogo">Inicio</a>
                <a href="?vista=catalogo">Productos</a>
                <a href="?vista=pos">Venta</a>
                <a href="?vista=admin">Admin</a>
            </nav>
            <div class="header-actions">
                <span class="cart-text" style="font-weight: 600; color: var(--muted); font-size: 14px;">Solo catálogo</span>
            </div>
        </div>
    </header>

    <?php if ($vista === 'admin'): ?>
        <main class="container catalog" id="admin" style="padding-top: 48px; padding-bottom: 80px;">
            <div class="section-head">
                <div>
                    <span class="eyebrow">🛠️ Panel de administrador</span>
                    <h2>Administrar productos</h2>
                </div>
            </div>

            <?php if ($mensaje !== ''): ?>
                <div class="sin-resultados" style="margin-bottom: 20px;">
                    <p style="color: var(--brand); font-weight: 600;"><?php echo htmlspecialchars($mensaje); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="sin-resultados" style="margin-bottom: 20px;">
                    <p style="color: var(--danger); font-weight: 600;"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <?php if (! $esAdmin): ?>
                <div class="sin-resultados" style="max-width: 460px; margin: 0 auto;">
                    <span class="sin-resultados-icon" aria-hidden="true">🔐</span>
                    <h3>Acceso restringido</h3>
                    <p>Ingresa la contraseña de administrador.</p>
                    <?php if ($error !== ''): ?>
                        <p style="color: var(--danger); font-weight: 600;"><?php echo htmlspecialchars($error); ?></p>
                    <?php endif; ?>
                    <form method="post" action="?vista=admin" class="form" style="margin-top: 18px;">
                        <div class="form-group">
                            <label for="admin_password">Contraseña</label>
                            <input type="password" id="admin_password" name="admin_password" required placeholder="admin1234">
                        </div>
                        <button class="btn btn-accent btn-block" type="submit">Entrar</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="sin-resultados" style="margin-bottom: 26px;">
                    <p>🛠️ Sesión de administrador activa.</p>
                    <a class="btn btn-ghost-light" style="background: var(--brand); color: #fff; border-color: var(--brand);" href="?vista=catalogo">← Volver al catálogo</a>
                    <form method="post" action="?vista=admin" style="margin-top: 14px;">
                        <input type="hidden" name="cerrar_sesion" value="1">
                        <button class="btn btn-ghost-light" type="submit">Cerrar sesión</button>
                    </form>
                </div>

                <form method="post" action="?vista=admin" enctype="multipart/form-data" class="admin-form" style="background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; margin-bottom: 16px;">
                    <p style="margin: 0 0 12px; font-weight: 700;">➕ Agregar nuevo producto</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                        <input type="text" name="nombre_nuevo" placeholder="Nombre del producto" style="flex: 2; min-width: 180px; padding: 10px; border: 1px solid var(--border); border-radius: 10px;">
                        <input type="number" name="costo_nuevo" step="0.01" min="0" placeholder="Precio" style="flex: 1; min-width: 110px; padding: 10px; border: 1px solid var(--border); border-radius: 10px;">
                        <input type="number" name="cantidad_nuevo" min="0" placeholder="Stock" style="width: 90px; padding: 10px; border: 1px solid var(--border); border-radius: 10px;">
                        <input type="text" name="categoria_nuevo" placeholder="Categoría" list="catsAdmin" style="flex: 1; min-width: 140px; padding: 10px; border: 1px solid var(--border); border-radius: 10px;">
                        <datalist id="catsAdmin">
                            <?php foreach (getCategorias() as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <input type="text" name="descripcion_nuevo" placeholder="Descripción" style="flex: 2; min-width: 180px; padding: 10px; border: 1px solid var(--border); border-radius: 10px;">
                        <input type="text" name="imagen_url_nuevo" placeholder="URL imagen (opcional)" style="flex: 2; min-width: 180px; padding: 10px; border: 1px solid var(--border); border-radius: 10px;">
                        <label class="admin-subida" style="flex: 1; min-width: 160px; font-size: 13px; color: var(--muted);">
                            <input type="file" name="imagen_archivo_nuevo" accept="image/*" style="display: block; margin-top: 6px;">
                            <span>Subir imagen (opcional)</span>
                        </label>
                        <button class="btn btn-accent" type="submit" name="guardar_nuevo" value="1">Agregar producto</button>
                    </div>
                </form>

                <?php foreach ($productos as $p): ?>
                    <form method="post" action="?vista=admin" enctype="multipart/form-data" class="admin-form" style="background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; margin-bottom: 16px;">
                        <input type="hidden" name="guardar_producto" value="1">
                        <input type="hidden" name="producto_id" value="<?php echo (int) $p['id']; ?>">
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                            <input type="text" name="nombre_producto" value="<?php echo htmlspecialchars($p['nombre']); ?>" placeholder="Nombre del producto" style="flex: 2; min-width: 180px; padding: 10px; border: 1px solid var(--border); border-radius: 10px;">
                            <input type="number" name="cantidad_producto" value="<?php echo (int) $p['stock']; ?>" min="0" title="Cantidad" style="width: 90px; padding: 10px; border: 1px solid var(--border); border-radius: 10px;">
                            <input type="text" name="imagen_producto" value="<?php echo htmlspecialchars((string) $p['imagen']); ?>" placeholder="URL imagen (opcional)" style="flex: 2; min-width: 180px; padding: 10px; border: 1px solid var(--border); border-radius: 10px;">
                            <label class="admin-subida" style="flex: 1; min-width: 180px; font-size: 13px; color: var(--muted);">
                                <input type="file" name="imagen_archivo" accept="image/*" style="display: block; margin-top: 6px;">
                                <span>Subir imagen desde PC</span>
                            </label>
                            <button class="btn btn-accent" type="submit">Guardar</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    <?php elseif ($vista === 'pos'): ?>
        <main class="container catalog" id="pos" style="padding-top: 48px; padding-bottom: 80px;">
            <div class="section-head">
                <div>
                    <span class="eyebrow">🧾 Punto de venta</span>
                    <h2>Ticket de venta</h2>
                </div>
            </div>

            <?php if ($mensajeVenta === 'ok'): ?>
                <div class="sin-resultados" style="margin-bottom: 20px;">
                    <span class="sin-resultados-icon" aria-hidden="true">✅</span>
                    <h3>¡Venta registrada!</h3>
                    <p>La venta fue registrada correctamente en la base de datos.</p>
                    <a class="btn btn-accent" href="ticket.php?id=<?php echo (int) $_GET['id']; ?>">Ver ticket</a>
                </div>
            <?php endif; ?>

            <div class="grid" id="posFichas" style="margin-bottom: 26px;">
                <?php foreach ($productos as $p): ?>
                    <?php
                        $stockPos = (int) $p['stock'];
                        $agotadoPos = $stockPos <= 0;
                        $imgFicha = !empty($p['imagen']) ? (string) $p['imagen'] : '';
                    ?>
                    <article class="producto-card">
                        <div class="producto-cuerpo">
                            <?php if ($imgFicha !== ''): ?>
                                <img class="producto-img-real" src="<?php echo htmlspecialchars($imgFicha); ?>" alt="<?php echo htmlspecialchars($p['nombre']); ?>">
                            <?php endif; ?>
                            <span class="producto-cat"><?php echo htmlspecialchars($p['categoria']); ?></span>
                            <h3 class="producto-nombre"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                            <?php if (trim((string) $p['descripcion']) !== ''): ?>
                                <p class="producto-desc"><?php echo htmlspecialchars($p['descripcion']); ?></p>
                            <?php endif; ?>
                            <div class="producto-pie">
                                <span class="precio"><?php echo formatPrice((float) $p['precio']); ?></span>
                                <span class="producto-stock <?php echo $agotadoPos ? 'stock-out' : 'stock-ok'; ?>"><?php echo $agotadoPos ? 'Agotado' : 'En stock'; ?></span>
                            </div>
                            <form method="post" action="?vista=pos" style="margin-top: 12px;">
                                <input type="hidden" name="nueva_id" value="<?php echo (int) $p['id']; ?>">
                                <input type="hidden" name="nueva_cantidad" value="1">
                                <button class="btn btn-accent btn-block" type="submit" name="agregar" value="1">Agregar al ticket</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (! empty($ventasTicket)): ?>
                <form method="post" action="?vista=pos">
                    <?php foreach ($ventasTicket as $i => $l): ?>
                        <input type="hidden" name="lineas[<?php echo (int) $i; ?>][id]" value="<?php echo (int) $l['id']; ?>">
                        <input type="hidden" name="lineas[<?php echo (int) $i; ?>][cantidad]" value="<?php echo (int) $l['cantidad']; ?>">
                        <input type="hidden" name="lineas[<?php echo (int) $i; ?>][precio]" value="<?php echo (float) $l['precio']; ?>">
                        <div class="drawer-item" style="margin-bottom: 10px;">
                            <?php $imgLinea = !empty($productosMap[(int) $l['id']]['imagen']) ? (string) $productosMap[(int) $l['id']]['imagen'] : ''; ?>
                            <?php if ($imgLinea !== ''): ?>
                                <img class="drawer-emoji-img" src="<?php echo htmlspecialchars($imgLinea); ?>" alt="<?php echo htmlspecialchars((string) ($productosMap[(int) $l['id']]['nombre'] ?? '')); ?>">
                            <?php else: ?>
                                <span class="drawer-emoji">🧺</span>
                            <?php endif; ?>
                            <div class="drawer-info">
                                <strong><?php echo htmlspecialchars((string) ($productosMap[(int) $l['id']]['nombre'] ?? 'Producto')); ?></strong>
                                <span class="drawer-peso">$<?php echo number_format((float) $l['precio'], 2); ?> c/u</span>
                                <div class="qty">
                                    <span class="qty-num"><?php echo (int) $l['cantidad']; ?></span>
                                </div>
                            </div>
                            <div class="drawer-linea">$<?php echo number_format((float) $l['precio'] * (int) $l['cantidad'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                    <input type="hidden" name="finalizar" value="1">
                    <div class="drawer-resumen">
                        <span>Subtotal</span>
                        <strong>$<?php echo number_format($subtotalTicket, 2); ?></strong>
                    </div>
                    <button class="btn btn-accent btn-block" type="submit">Finalizar compra</button>
                </form>
            <?php else: ?>
                <div class="sin-resultados" style="max-width: 460px; margin: 0 auto;">
                    <span class="sin-resultados-icon" aria-hidden="true">🧾</span>
                    <h3>Ticket vacío</h3>
                    <p>Agrega productos desde las fichas para iniciar una venta.</p>
                </div>
            <?php endif; ?>
        </main>
    <?php else: ?>
        <main class="container catalog" id="catalogo-simple" style="padding-top: 0; padding-bottom: 80px;">
            <section class="hero-simple">
                <div class="container">
                    <span class="eyebrow" style="color: #bfdbfe;">🧺 Bienvenidos</span>
                    <h1>La Tiendita de Ferrer</h1>
                    <p>Todo lo que tu hogar necesita, en una sola tienda.</p>
                    <a class="btn hero-cta" href="#productosSimples">Ver productos</a>
                </div>
            </section>

            <div class="section-head">
                <div>
                    <span class="eyebrow">🗂️ Catálogo</span>
                    <h2>Productos de la tienda</h2>
                </div>
                <p class="muted">Mostrando <strong><?php echo (int) count($productos); ?></strong> productos</p>
            </div>

            <div class="grid" id="productosSimples">
                <?php foreach ($productos as $p): ?>
                    <?php
                        $stock = (int) $p['stock'];
                        $agotado = $stock <= 0;
                        $precio = (float) $p['precio'];
                        $claseStock = $agotado ? 'stock-out' : ($stock <= 5 ? 'stock-low' : 'stock-ok');
                        $etiquetaStock = $agotado ? 'Agotado' : ($stock <= 5 ? '¡Solo quedan ' . $stock . '!' : 'En stock');
                    ?>
                    <article class="producto-card">
                        <div class="producto-cuerpo">
                            <?php if (!empty($p['imagen'])): ?>
                                <img class="producto-img-real" src="<?php echo htmlspecialchars((string) $p['imagen']); ?>" alt="<?php echo htmlspecialchars($p['nombre']); ?>">
                            <?php endif; ?>
                            <span class="producto-cat"><?php echo htmlspecialchars($p['categoria']); ?></span>
                            <h3 class="producto-nombre"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                            <?php if (trim((string) $p['descripcion']) !== ''): ?>
                                <p class="producto-desc"><?php echo htmlspecialchars($p['descripcion']); ?></p>
                            <?php endif; ?>
                            <div class="producto-pie">
                                <span class="precio"><?php echo formatPrice($precio); ?></span>
                                <span class="producto-stock <?php echo $claseStock; ?>"><?php echo $etiquetaStock; ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </main>
    <?php endif; ?>

    <footer class="footer" style="margin-top: 0;">
        <div class="container footer-grid">
            <div class="footer-col footer-brand">
                <a href="?vista=catalogo" class="logo logo-light" aria-label="Ir al inicio de La Tiendita de Ferrer">
                    <span class="logo-mark" aria-hidden="true"></span>
                    <span class="logo-text">La Tiendita de Ferrer</span>
                </a>
                <p>Catálogo simple de productos.</p>
            </div>
            <div class="footer-col">
                <h4>Acceso rápido</h4>
                <ul>
                    <li><a href="?vista=catalogo">Catálogo</a></li>
                    <li><a href="?vista=pos">Punto de venta</a></li>
                    <li><a href="?vista=admin">Admin</a></li>
                </ul>
            </div>
        </div>
        <div class="container footer-base">
            <p>© 2026 La Tiendita de Ferrer · Hecho con ❤️ en México</p>
        </div>
    </footer>

</body>
</html>
