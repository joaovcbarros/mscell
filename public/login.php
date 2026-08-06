<?php

require_once __DIR__ . '/../src/bootstrap.php';

use MsCell\Services\AuthService;

if (AuthService::usuarioLogado()) {
    header('Location: /dashboard.php');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (AuthService::login($email, $senha)) {
        header('Location: /dashboard.php');
        exit;
    }

    $erro = 'E-mail ou senha inválidos, ou usuário inativo.';
}

$tituloPagina = 'Entrar';
require __DIR__ . '/partials/head.php';
?>
<div class="login-wrapper">
    <div class="login-box">
        <img src="/assets/img/logo.png" alt="MsCell" class="login-logo">
        <p class="login-tagline">Gestão de produtos, vendas e estoque</p>

        <div class="card login-card p-4">
            <div class="card-body">
                <?php if ($erro): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($erro) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" required autofocus
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha</label>
                        <input type="password" name="senha" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-mscell w-100">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/foot.php'; ?>
