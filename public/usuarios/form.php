<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Models\Loja;
use MsCell\Models\Usuario;
use MsCell\Services\AuthService;

AuthService::exigirPapel(['admin']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$usuario = $id ? Usuario::buscarPorId($id) : null;
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $papel = $_POST['papel'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $lojaId = $papel === Usuario::PAPEL_ADMIN
        ? null
        : (!empty($_POST['loja_id']) ? (int) $_POST['loja_id'] : null);
    $salarioBase = trim($_POST['salario_base'] ?? '') !== ''
        ? (float) str_replace(',', '.', $_POST['salario_base'])
        : null;

    if ($nome === '') {
        $erros[] = 'Nome é obrigatório.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'E-mail inválido.';
    }
    if (!in_array($papel, Usuario::papeisValidos(), true)) {
        $erros[] = 'Papel inválido.';
    }
    if ($papel !== Usuario::PAPEL_ADMIN && $lojaId === null) {
        $erros[] = 'Selecione a loja do funcionário.';
    }
    if (!$id && strlen($senha) < 6) {
        $erros[] = 'Senha deve ter pelo menos 6 caracteres.';
    }

    if (empty($erros)) {
        if ($id) {
            Usuario::atualizar($id, $nome, $email, $papel, $lojaId, $salarioBase);
            if ($senha !== '') {
                Usuario::redefinirSenha($id, $senha);
            }
        } else {
            Usuario::criar($nome, $email, $senha, $papel, $lojaId, $salarioBase);
        }

        header('Location: /usuarios/index.php?sucesso=1');
        exit;
    }

    $usuario = ['nome' => $nome, 'email' => $email, 'papel' => $papel, 'loja_id' => $lojaId, 'salario_base' => $salarioBase];
}

$lojas = Loja::todas(true);

$tituloPagina = $id ? 'Editar usuário' : 'Novo usuário';
$paginaAtual = 'usuarios';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<h2 class="mb-4"><?= $id ? 'Editar usuário' : 'Novo usuário' ?></h2>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($erro) ?></div>
<?php endforeach; ?>

<div class="card card-stat p-4" style="max-width: 560px;">
    <form method="post" id="form-usuario" action="/usuarios/form.php<?= $id ? '?id=' . $id : '' ?>">
        <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">E-mail</label>
            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Papel</label>
            <select name="papel" id="campo-papel" class="form-select">
                <?php foreach (Usuario::papeisValidos() as $p): ?>
                    <option value="<?= $p ?>" <?= ($usuario['papel'] ?? '') === $p ? 'selected' : '' ?>>
                        <?= \MsCell\Helpers\Formatador::papel($p) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3" id="grupo-loja">
            <label class="form-label">Loja</label>
            <select name="loja_id" id="campo-loja" class="form-select">
                <option value="">Selecione...</option>
                <?php foreach ($lojas as $l): ?>
                    <option value="<?= (int) $l['id'] ?>" <?= (int) ($usuario['loja_id'] ?? 0) === (int) $l['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Obrigatório para funcionário/usuário. Admin enxerga todas as lojas, não precisa escolher.</div>
        </div>
        <div class="mb-3" id="grupo-salario">
            <label class="form-label">Salário base (opcional)</label>
            <input type="text" name="salario_base" class="form-control" placeholder="ex: 1800,00"
                   value="<?= htmlspecialchars((string) ($usuario['salario_base'] ?? '')) ?>">
            <div class="form-text">Usado no cálculo do módulo de Bonificação.</div>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= $id ? 'Nova senha (deixe em branco para manter)' : 'Senha' ?></label>
            <input type="password" name="senha" class="form-control" <?= $id ? '' : 'required minlength="6"' ?>>
        </div>
        <button type="submit" class="btn btn-mscell">Salvar</button>
        <a href="/usuarios/index.php" class="btn btn-outline-secondary">Cancelar</a>
    </form>
</div>
<script>
function atualizarCampoLoja() {
    const papel = document.getElementById('campo-papel').value;
    const ehAdmin = papel === 'admin';
    document.getElementById('grupo-loja').style.display = ehAdmin ? 'none' : '';
    document.getElementById('grupo-salario').style.display = ehAdmin ? 'none' : '';
}
document.getElementById('campo-papel').addEventListener('change', atualizarCampoLoja);
atualizarCampoLoja();
</script>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
