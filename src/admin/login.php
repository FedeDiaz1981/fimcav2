<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';
    // Cambia estos datos por los que quieras
    if ($user === 'admin' && $pass === 'tu_clave_segura') {
        $_SESSION['admin'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Usuario o clave incorrectos';
    }
}
?>
<form method="post">
    <input name="user" placeholder="Usuario">
    <input name="pass" type="password" placeholder="Clave">
    <button type="submit">Entrar</button>
    <?php if (!empty($error)) echo "<p>$error</p>"; ?>
</form>