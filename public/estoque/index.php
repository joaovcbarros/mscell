<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Helpers\Formatador;
use MsCell\Models\MovimentacaoEstoque;
use MsCell\Models\Produto;
use MsCell\Services\AuthService;
use MsCell\Services\EstoqueService;

AuthService::exigirLogin();

$podeAjustar = in_array(AuthService::papelAtual(), ['admin', 'funcionario'], true);
$lojaEfetiva = AuthService::lojaEfetiva();
$mostrarColunaLoja = $lojaEfetiva === null;
$erro = null;

if ($podeAjustar && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        EstoqueService::ajustarManualmente(
            (int) $_POST['produto_id'],
            $_POST['tipo'],
            (int) $_POST['quantidade'],
            trim($_POST['motivo'] ?? ''),
            AuthService::idAtual(),
            $lojaEfetiva
        );

        header('Location: /estoque/index.php?sucesso=1');
        exit;
    } catch (\Throwable $e) {
        $erro = $e->getMessage();
    }
}

$produtos = Produto::todos(true, $lojaEfetiva);
$movimentacoes = MovimentacaoEstoque::recentes(50, $lojaEfetiva);

$tituloPagina = 'Estoque';
$paginaAtual = 'estoque';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<h2 class="mb-4">Estoque</h2>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="alert alert-success">Estoque ajustado com sucesso.</div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<?php if ($podeAjustar): ?>
<div class="card card-stat p-4 mb-4" style="max-width: 720px;">
    <h5>Ajuste manual</h5>
    <form method="post" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Produto</label>
            <select name="produto_id" class="form-select form-select-sm" required>
                <?php foreach ($produtos as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?> (atual: <?= (int) $p['quantidade_estoque'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Tipo</label>
            <select name="tipo" class="form-select form-select-sm">
                <option value="entrada">Entrada</option>
                <option value="saida">Saída</option>
                <option value="ajuste">Ajuste (+)</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Quantidade</label>
            <input type="number" name="quantidade" class="form-control form-control-sm" min="1" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Motivo</label>
            <input type="text" name="motivo" class="form-control form-control-sm" placeholder="ex: compra de reposição">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-mscell btn-sm w-100">OK</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card card-stat p-3">
    <h5>Movimentações recentes</h5>
    <table class="table table-sm align-middle">
        <thead><tr><th>Data</th><?php if ($mostrarColunaLoja): ?><th>Loja</th><?php endif; ?><th>Produto</th><th>Tipo</th><th class="text-end">Qtd</th><th>Motivo</th><th>Usuário</th></tr></thead>
        <tbody>
        <?php foreach ($movimentacoes as $m): ?>
            <tr>
                <td><?= Formatador::data($m['criado_em']) ?></td>
                <?php if ($mostrarColunaLoja): ?><td><?= htmlspecialchars($m['loja_nome'] ?? '—') ?></td><?php endif; ?>
                <td><?= htmlspecialchars($m['produto_nome']) ?></td>
                <td>
                    <?php
                    $badgeClasse = match ($m['tipo']) {
                        'entrada' => 'bg-success',
                        'saida' => 'bg-danger',
                        default => 'bg-info',
                    };
                    ?>
                    <span class="badge <?= $badgeClasse ?>"><?= ucfirst($m['tipo']) ?></span>
                </td>
                <td class="text-end"><?= (int) $m['quantidade'] ?></td>
                <td><?= htmlspecialchars($m['motivo'] ?? '—') ?></td>
                <td><?= htmlspecialchars($m['usuario_nome'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($movimentacoes)): ?>
            <tr><td colspan="<?= 5 + ($mostrarColunaLoja ? 1 : 0) ?>" class="text-center text-muted py-4">Nenhuma movimentação registrada.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
