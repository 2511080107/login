<?php
session_start();
require 'db.php';
require 'google_config.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: panel.php');
    exit;
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, nombre, email, password FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($pass, $usuario['password'])) {
        session_regenerate_id(true); // evita fijación de sesión
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre']     = $usuario['nombre'];
        $_SESSION['email']      = $usuario['email'];
        $_SESSION['foto']       = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($usuario['email']))) . '?d=identicon&s=64';
        header('Location: panel.php');
        exit;
    }
    $mensaje = 'Correo o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="estilos.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
<form method="post" class="caja">
    <h2>Iniciar sesión</h2>
    <?php if (isset($_GET['registrado'])): ?><p class="ok">Cuenta creada. Ya puedes entrar.</p><?php endif; ?>
    <?php if ($mensaje): ?><p class="error"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
    <input type="email" name="email" placeholder="Correo" required>
    <input type="password" name="password" placeholder="Contraseña" required>
    <button type="submit">Entrar</button>

    <div class="divisor">o</div>

    <div id="google-btn">
        <div id="g_id_onload"
             data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>"
             data-callback="handleGoogleCredential"
             data-auto_prompt="false"></div>
        <div class="g_id_signin"
             data-type="standard"
             data-size="large"
             data-theme="outline"
             data-text="signin_with"
             data-shape="rectangular"
             data-logo_alignment="left"></div>
    </div>

    <p><a href="registro.php">Crear una cuenta</a></p>
</form>

<script>
function handleGoogleCredential(response) {
    const form = new FormData();
    form.append('credential', response.credential);

    fetch('google_login.php', { method: 'POST', body: form })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                window.location.href = data.redirect || 'panel.php';
            } else {
                alert(data.error || 'No se pudo iniciar sesión con Google.');
            }
        })
        .catch(function () {
            alert('Error de conexión con el servidor.');
        });
}
</script>
</body>
</html>
