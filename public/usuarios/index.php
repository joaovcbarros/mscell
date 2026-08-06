<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Helpers\Formatador;
use MsCell\Models\Usuario;
use MsCell\Services\AuthService;

AuthService::exigirPapel(['admin']);

if (isset($_GET['acao'], $_GET['id']) && in_array($_GET['acao'], ['ativar', 'desativar'], true)) {
    Usuario::definirAtivo((int) $_GET['id'], $_GET['acao'] === 'ativar');
    header('Location: /usuarios/index.php');
    exit;
}

$lojaEfetiva = AuthService::lojaEfetiva();
$mostrarColunaLoja = $lojaEfetiva === null;
$usuarios = Usuario::todos($lojaEfetiva);

$tituloPagina = 'Usuários';
$paginaAtual = 'usuarios';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Usuários</h2>
    <a href="/usuarios/form.php" class="btn btn-mscell">+ Novo usuário</a>
</div>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="alert alert-success">Usuário salvo com sucesso.</div>
<?php endif; ?>

<div class="card card-stat p-3">
    <table class="table table-sm align-middle">
        <thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><?php if ($mostrarColunaLoja): ?><th>Loja</th><?php endif; ?><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['nome']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= Formatador::papel($u['papel']) ?></td>
                <?php if ($mostrarColunaLoja): ?><td><?= htmlspecialchars($u['loja_nome'] ?? '—') ?></td><?php endif; ?>
                <td>
                    <?php if ($u['ativo']): ?>
                        <span class="badge bg-success">Ativo</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inativo</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <a href="/usuarios/form.php?id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                    <?php if ($u['id'] != AuthService::idAtual()): ?>
                        <?php if ($u['ativo']): ?>
                            <a href="/usuarios/index.php?acao=desativar&id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-danger">Desativar</a>
                        <?php else: ?>
                            <a href="/usuarios/index.php?acao=ativar&id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-success">Ativar</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
