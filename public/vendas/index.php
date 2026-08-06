<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Helpers\Formatador;
use MsCell\Models\Usuario;
use MsCell\Models\Venda;
use MsCell\Services\AuthService;

AuthService::exigirLogin();

$podeRegistrar = in_array(AuthService::papelAtual(), ['admin', 'funcionario'], true);
$lojaEfetiva = AuthService::lojaEfetiva();
$mostrarColunaLoja = $lojaEfetiva === null;

$filtros = [
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
    'origem' => $_GET['origem'] ?? '',
];
$vendas = Venda::todas(array_filter($filtros), $lojaEfetiva);

$tituloPagina = 'Vendas';
$paginaAtual = 'vendas';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Vendas</h2>
    <?php if ($podeRegistrar): ?>
        <a href="/vendas/nova.php" class="btn btn-mscell">+ Nova venda</a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="alert alert-success">Venda registrada com sucesso.</div>
<?php endif; ?>

<div class="card card-stat p-3 mb-3">
    <form class="row g-2 align-items-end" method="get">
        <div class="col-auto">
            <label class="form-label small mb-0">De</label>
            <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['data_inicio']) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">Até</label>
            <input type="date" name="data_fim" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['data_fim']) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">Origem</label>
            <select name="origem" class="form-select form-select-sm">
                <option value="">Todas</option>
                <option value="sistema" <?= $filtros['origem'] === 'sistema' ? 'selected' : '' ?>>Sistema</option>
                <option value="whatsapp" <?= $filtros['origem'] === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrar</button>
        </div>
    </form>
</div>

<div class="card card-stat p-3">
    <table class="table table-sm align-middle">
        <thead>
        <tr>
            <th>Data</th>
            <?php if ($mostrarColunaLoja): ?><th>Loja</th><?php endif; ?>
            <th>Cliente</th>
            <th>Vendedor</th>
            <th>Pagamento</th>
            <th>Origem</th>
            <th class="text-end">Valor</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($vendas as $v): ?>
            <tr>
                <td><?= Formatador::data($v['criado_em']) ?></td>
                <?php if ($mostrarColunaLoja): ?><td><?= htmlspecialchars($v['loja_nome'] ?? '—') ?></td><?php endif; ?>
                <td><?= htmlspecialchars($v['cliente_nome'] ?: '—') ?></td>
                <td><?= htmlspecialchars($v['usuario_nome'] ?? '—') ?></td>
                <td><?= htmlspecialchars(ucfirst($v['forma_pagamento'])) ?></td>
                <td>
                    <?php if ($v['origem'] === 'whatsapp'): ?>
                        <span class="badge badge-origem-whatsapp">WhatsApp</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Sistema</span>
                    <?php endif; ?>
                </td>
                <td class="text-end"><?= Formatador::moeda((float) $v['valor_total']) ?></td>
                <td class="text-end"><a href="/vendas/ver.php?id=<?= (int) $v['id'] ?>" class="btn btn-sm btn-outline-secondary">Ver</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($vendas)): ?>
            <tr><td colspan="<?= 6 + ($mostrarColunaLoja ? 1 : 0) ?>" class="text-center text-muted py-4">Nenhuma venda encontrada.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
