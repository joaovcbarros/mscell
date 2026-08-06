<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Helpers\Formatador;
use MsCell\Models\Produto;
use MsCell\Services\AuthService;

AuthService::exigirLogin();

$podeGerenciar = in_array(AuthService::papelAtual(), ['admin', 'funcionario'], true);
$lojaEfetiva = AuthService::lojaEfetiva();
$mostrarColunaLoja = $lojaEfetiva === null;
$produtos = Produto::todos(false, $lojaEfetiva);

$tituloPagina = 'Produtos';
$paginaAtual = 'produtos';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Produtos</h2>
    <?php if ($podeGerenciar): ?>
        <a href="/produtos/form.php" class="btn btn-mscell">+ Novo produto</a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="alert alert-success">Produto salvo com sucesso.</div>
<?php endif; ?>

<div class="card card-stat p-3">
    <table class="table table-sm align-middle">
        <thead>
        <tr>
            <th>Nome</th>
            <?php if ($mostrarColunaLoja): ?><th>Loja</th><?php endif; ?>
            <th>Categoria</th>
            <th>SKU</th>
            <th class="text-end">Preço venda</th>
            <th class="text-end">Estoque</th>
            <th>Status</th>
            <?php if ($podeGerenciar): ?><th></th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($produtos as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['nome']) ?></td>
                <?php if ($mostrarColunaLoja): ?><td><?= htmlspecialchars($p['loja_nome'] ?? '—') ?></td><?php endif; ?>
                <td><?= htmlspecialchars($p['categoria_nome'] ?? '—') ?></td>
                <td><?= htmlspecialchars($p['sku'] ?? '—') ?></td>
                <td class="text-end"><?= Formatador::moeda((float) $p['preco_venda']) ?></td>
                <td class="text-end">
                    <?= (int) $p['quantidade_estoque'] ?>
                    <?php if ($p['quantidade_estoque'] <= $p['estoque_minimo']): ?>
                        <span class="badge badge-baixo-estoque">baixo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($p['ativo']): ?>
                        <span class="badge bg-success">Ativo</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inativo</span>
                    <?php endif; ?>
                </td>
                <?php if ($podeGerenciar): ?>
                    <td class="text-end">
                        <a href="/produtos/form.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($produtos)): ?>
            <tr><td colspan="<?= 6 + ($mostrarColunaLoja ? 1 : 0) + ($podeGerenciar ? 1 : 0) ?>" class="text-center text-muted py-4">Nenhum produto cadastrado.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
