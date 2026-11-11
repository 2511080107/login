<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require 'db.php';
require 'config.php';

// Asegura que exista la tabla de asistencia
$pdo->exec("CREATE TABLE IF NOT EXISTS asistencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    estado ENUM('ausente','presente') NOT NULL DEFAULT 'ausente',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// ============================================================
// Usuario en sesión y permisos
// ============================================================
$emailActual  = $_SESSION['email'] ?? '';
$nombreActual = $_SESSION['nombre'] ?? 'Usuario';

if ($emailActual === '') {
    $stmt = $pdo->prepare('SELECT email, nombre FROM usuarios WHERE id = ?');
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuarioSesion = $stmt->fetch();
    if ($usuarioSesion) {
        $emailActual  = $usuarioSesion['email'];
        $nombreActual = $usuarioSesion['nombre'] ?: $nombreActual;
    }
}

// Solo el correo definido en config.php puede modificar la asistencia
$esAdmin = strcasecmp(trim($emailActual), ADMIN_EMAIL) === 0;

// Foto para el nav (Google o avatar generado por correo)
$fotoActual = $_SESSION['foto'] ?? '';
if ($fotoActual === '') {
    $fotoActual = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($emailActual))) . '?d=identicon&s=64';
}

$msg = '';
$msgTipo = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$esAdmin) {
    $msg = 'Solo el administrador puede modificar la asistencia.';
    $msgTipo = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $esAdmin) {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear') {
            $nombre    = trim($_POST['nombre'] ?? '');
            $apellidos = trim($_POST['apellidos'] ?? '');

            if ($nombre === '' || $apellidos === '') {
                throw new InvalidArgumentException('Apellidos y nombre son obligatorios.');
            }

            $stmt = $pdo->prepare('INSERT INTO asistencias (nombre, apellidos) VALUES (?, ?)');
            $stmt->execute([$nombre, $apellidos]);
            header('Location: panel.php?msg=creado#asistencia');
            exit;
        }

        if ($accion === 'actualizar') {
            $id        = (int) ($_POST['id'] ?? 0);
            $nombre    = trim($_POST['nombre'] ?? '');
            $apellidos = trim($_POST['apellidos'] ?? '');
            $estado    = $_POST['estado'] ?? 'ausente';
            $estado    = in_array($estado, ['ausente', 'presente'], true) ? $estado : 'ausente';

            if ($id <= 0 || $nombre === '' || $apellidos === '') {
                throw new InvalidArgumentException('Datos inválidos para actualizar.');
            }

            $stmt = $pdo->prepare('UPDATE asistencias SET nombre = ?, apellidos = ?, estado = ? WHERE id = ?');
            $stmt->execute([$nombre, $apellidos, $estado, $id]);
            header('Location: panel.php?msg=actualizado#asistencia');
            exit;
        }

        if ($accion === 'estado') {
            $id     = (int) ($_POST['id'] ?? 0);
            $estado = $_POST['estado'] ?? 'ausente';
            $estado = in_array($estado, ['ausente', 'presente'], true) ? $estado : 'ausente';

            if ($id <= 0) {
                throw new InvalidArgumentException('Registro inválido.');
            }

            $stmt = $pdo->prepare('UPDATE asistencias SET estado = ? WHERE id = ?');
            $stmt->execute([$estado, $id]);
            header('Location: panel.php?msg=estado#asistencia');
            exit;
        }

        if ($accion === 'eliminar') {
            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new InvalidArgumentException('Registro inválido.');
            }

            $stmt = $pdo->prepare('DELETE FROM asistencias WHERE id = ?');
            $stmt->execute([$id]);
            header('Location: panel.php?msg=eliminado#asistencia');
            exit;
        }
    } catch (InvalidArgumentException $e) {
        $msg = $e->getMessage();
        $msgTipo = 'error';
    } catch (Throwable $e) {
        $msg = 'Ocurrió un error al procesar la solicitud.';
        $msgTipo = 'error';
    }
}

$mensajes = [
    'creado'      => 'Usuario agregado correctamente.',
    'actualizado' => 'Datos actualizados correctamente.',
    'estado'      => 'Estado de asistencia actualizado.',
    'eliminado'   => 'Usuario eliminado correctamente.',
];
if ($msg === '' && isset($_GET['msg'], $mensajes[$_GET['msg']])) {
    $msg = $mensajes[$_GET['msg']];
}

// Usuario en edición (solo administrador)
$editar = null;
if ($esAdmin && isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM asistencias WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch() ?: null;
}

// Listado de asistentes
$asistencias = $pdo->query('SELECT * FROM asistencias ORDER BY apellidos ASC, nombre ASC')->fetchAll();

// Estadísticas
$total     = count($asistencias);
$presentes = 0;
foreach ($asistencias as $a) {
    if ($a['estado'] === 'presente') {
        $presentes++;
    }
}
$ausentes = $total - $presentes;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel</title>

    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <!-- My CSS -->
    <link rel="stylesheet" href="op/css.css">

    <title>AdminHub</title>
</head>
<body>

	<!-- SIDEBAR -->
	<section id="sidebar">
		<a href="#" class="brand">
			<i class='bx bxs-smile  bx-lg'></i>
			<span class="text">AdminHub</span>
		</a>
		<ul class="side-menu top">
			<li class="active">
				<a href="#">
					<i class='bx bxs-dashboard bx-sm' ></i>
					<span class="text">Dashboard</span>
				</a>
			</li>
			<li>
				<a href="#asistencia">
					<i class='bx bxs-group bx-sm' ></i>
					<span class="text">Asistencia</span>
				</a>
			</li>
			<li>
				<a href="#">
					<i class='bx bxs-shopping-bag-alt bx-sm' ></i>
					<span class="text">My Store</span>
				</a>
			</li>
			<li>
				<a href="#">
					<i class='bx bxs-doughnut-chart bx-sm' ></i>
					<span class="text">Analytics</span>
				</a>
			</li>
			<li>
				<a href="#">
					<i class='bx bxs-message-dots bx-sm' ></i>
					<span class="text">Message</span>
				</a>
			</li>
		</ul>
		<ul class="side-menu bottom">
			<li>
				<a href="#">
					<i class='bx bxs-cog bx-sm bx-spin-hover' ></i>
					<span class="text">Settings</span>
				</a>
			</li>
			<li>
				<a href="logout.php" class="logout">
					<i class='bx bx-power-off bx-sm bx-burst-hover' ></i>
					<span class="text">Logout</span>
				</a>
			</li>
		</ul>
	</section>
	<!-- SIDEBAR -->



	<!-- CONTENT -->
	<section id="content">
		<!-- NAVBAR -->
    <nav>
        <i class='bx bx-menu bx-sm' ></i>
        <a href="#" class="nav-link">Categories</a>
        <form action="#">
            <div class="form-input">
                <input type="search" placeholder="Search...">
                <button type="submit" class="search-btn"><i class='bx bx-search' ></i></button>
            </div>
        </form>
        <p class="nav-user">Bienvenido, <?= htmlspecialchars($nombreActual) ?><?php if ($esAdmin): ?> <span class="rol-admin">Admin</span><?php endif; ?></p>

        <input type="checkbox" class="checkbox" id="switch-mode" hidden />
        <label class="swith-lm" for="switch-mode">
            <i class="bx bxs-moon"></i>
            <i class="bx bx-sun"></i>
            <div class="ball">
            </div>
        </label>

        <!-- Notification Bell -->
        <a href="#" class="notification" id="notificationIcon">
            <i class='bx bxs-bell bx-tada-hover' ></i>
            <span class="num">8</span>
        </a>
        <div class="notification-menu" id="notificationMenu">
            <ul>
                <li>New message from John</li>
                <li>Your order has been shipped</li>
                <li>New comment on your post</li>
                <li>Update available for your app</li>
                <li>Reminder: Meeting at 3PM</li>
            </ul>
        </div>

        <!-- Profile Menu -->
        <a href="#" class="profile" id="profileIcon">
            <img src="<?= htmlspecialchars($fotoActual) ?>" alt="<?= htmlspecialchars($nombreActual) ?>">
        </a>
        <div class="profile-menu" id="profileMenu">
            <ul>
                <li><a href="#">My Profile</a></li>
                <li><a href="#">Settings</a></li>
                <li><a href="logout.php">Log Out</a></li>
            </ul>
        </div>
    </nav>
<!-- NAVBAR -->


		<!-- MAIN -->
		<main>
			<div class="head-title">
				<div class="left">
					<h1>Asistencia</h1>
					<ul class="breadcrumb">
						<li>
							<a href="#">Dashboard</a>
						</li>
						<li><i class='bx bx-chevron-right' ></i></li>
						<li>
							<a class="active" href="#">Asistencia</a>
						</li>
					</ul>
				</div>
			</div>

			<ul class="box-info asistencia-stats">
				<li>
					<i class='bx bxs-group' ></i>
					<span class="text">
						<h3><?= $total ?></h3>
						<p>Total usuarios</p>
					</span>
				</li>
				<li>
					<i class='bx bxs-check-circle' ></i>
					<span class="text">
						<h3><?= $presentes ?></h3>
						<p>Presentes</p>
					</span>
				</li>
				<li>
					<i class='bx bxs-x-circle' ></i>
					<span class="text">
						<h3><?= $ausentes ?></h3>
						<p>Ausentes</p>
					</span>
				</li>
			</ul>


			<div class="table-data" id="asistencia">
				<div class="order">
					<div class="head">
						<h3>Control de asistencia</h3>
						<span class="badge-total"><?= $total ?> registrados</span>
					</div>

					<?php if ($msg): ?>
						<p class="alerta <?= $msgTipo === 'error' ? 'alerta-error' : 'alerta-ok' ?>"><?= htmlspecialchars($msg) ?></p>
					<?php endif; ?>

					<?php if (!$esAdmin): ?>
						<p class="alerta alerta-error"><i class='bx bx-lock-alt'></i> Solo el administrador puede agregar, editar o eliminar registros.</p>
					<?php elseif ($editar): ?>
						<form method="post" class="form-asistencia">
							<h4><i class='bx bx-edit'></i> Editar usuario #<?= (int) $editar['id'] ?></h4>
							<input type="hidden" name="accion" value="actualizar">
							<input type="hidden" name="id" value="<?= (int) $editar['id'] ?>">
							<input type="text" name="apellidos" value="<?= htmlspecialchars($editar['apellidos']) ?>" placeholder="Apellidos" maxlength="100" required>
							<input type="text" name="nombre" value="<?= htmlspecialchars($editar['nombre']) ?>" placeholder="Nombre" maxlength="100" required>
							<select name="estado">
								<option value="ausente" <?= $editar['estado'] === 'ausente' ? 'selected' : '' ?>>Ausente</option>
								<option value="presente" <?= $editar['estado'] === 'presente' ? 'selected' : '' ?>>Presente</option>
							</select>
							<button type="submit" class="btn-primario"><i class='bx bx-save'></i> Guardar cambios</button>
							<a href="panel.php#asistencia" class="btn-secundario">Cancelar</a>
						</form>
					<?php else: ?>
						<form method="post" class="form-asistencia">
							<h4><i class='bx bx-user-plus'></i> Agregar usuario</h4>
							<input type="hidden" name="accion" value="crear">
							<input type="text" name="apellidos" placeholder="Apellidos" maxlength="100" required>
							<input type="text" name="nombre" placeholder="Nombre" maxlength="100" required>
							<button type="submit" class="btn-primario"><i class='bx bx-plus'></i> Agregar</button>
						</form>
					<?php endif; ?>

					<table>
						<thead>
							<tr>
								<th>Apellidos y nombres</th>
								<th>Estado</th>
								<?php if ($esAdmin): ?><th>Acciones</th><?php endif; ?>
							</tr>
						</thead>
						<tbody>
							<?php if (!$asistencias): ?>
								<tr>
									<td colspan="<?= $esAdmin ? 3 : 2 ?>" class="vacio">No hay usuarios registrados todavía.</td>
								</tr>
							<?php else: ?>
								<?php foreach ($asistencias as $a): ?>
									<tr>
										<td class="celda-nombre">
											<i class='bx bx-user-circle'></i>
											<p><?= htmlspecialchars($a['apellidos'] . ' ' . $a['nombre']) ?></p>
										</td>
										<td>
											<span class="status <?= $a['estado'] === 'presente' ? 'completed' : 'pending' ?>">
												<?= $a['estado'] === 'presente' ? 'Presente' : 'Ausente' ?>
											</span>
										</td>
										<?php if ($esAdmin): ?>
										<td>
											<div class="acciones">
												<form method="post">
													<input type="hidden" name="accion" value="estado">
													<input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
													<input type="hidden" name="estado" value="<?= $a['estado'] === 'presente' ? 'ausente' : 'presente' ?>">
													<button type="submit" class="btn-accion btn-estado">
														<i class='bx <?= $a['estado'] === 'presente' ? 'bx-toggle-right' : 'bx-toggle-left' ?>'></i>
														<?= $a['estado'] === 'presente' ? 'Marcar ausente' : 'Marcar presente' ?>
													</button>
												</form>
												<a href="panel.php?editar=<?= (int) $a['id'] ?>#asistencia" class="btn-accion btn-editar"><i class='bx bx-edit'></i> Editar</a>
												<form method="post" onsubmit="return confirm('¿Eliminar a <?= htmlspecialchars($a['apellidos'] . ' ' . $a['nombre'], ENT_QUOTES) ?>?');">
													<input type="hidden" name="accion" value="eliminar">
													<input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
													<button type="submit" class="btn-accion btn-eliminar"><i class='bx bx-trash'></i> Eliminar</button>
												</form>
											</div>
										</td>
										<?php endif; ?>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</main>
		<!-- MAIN -->
	</section>
	<!-- CONTENT -->


	<script src="op/js.js"></script>
</body>
</html>
