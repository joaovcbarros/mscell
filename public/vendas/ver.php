<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Helpers\Formatador;
use MsCell\Models\Venda;
use MsCell\Services\AuthService;

AuthService::exigirLogin();

$id = (int) ($_GET['id'] ?? 0);
$venda = Venda::buscarPorId($id, AuthService::lojaEfetiva());

if (!$venda) {
    header('Location: /vendas/index.php');
    exit;
}

$itens = Venda::itens($id);

$tituloPagina = 'Venda #' . $id;
$paginaAtual = 'vendas';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<h2 class="mb-4">Venda #<?= $id ?></h2>

<div class="card card-stat p-4 mb-3" style="max-width: 720px;">
    <dl class="row mb-0">
        <dt class="col-sm-3">Data</dt><dd class="col-sm-9"><?= Formatador::data($venda['criado_em']) ?></dd>
        <dt class="col-sm-3">Cliente</dt><dd class="col-sm-9"><?= htmlspecialchars($venda['cliente_nome'] ?: '—') ?></dd>
        <dt class="col-sm-3">Vendedor</dt><dd class="col-sm-9"><?= htmlspecialchars($venda['usuario_nome'] ?? '—') ?></dd>
        <dt class="col-sm-3">Pagamento</dt><dd class="col-sm-9"><?= htmlspecialchars(ucfirst($venda['forma_pagamento'])) ?></dd>
        <dt class="col-sm-3">Origem</dt><dd class="col-sm-9"><?= $venda['origem'] === 'whatsapp' ? 'WhatsApp (automático)' : 'Sistema' ?></dd>
        <dt class="col-sm-3">Total</dt><dd class="col-sm-9"><strong><?= Formatador::moeda((float) $venda['valor_total']) ?></strong></dd>
    </dl>
</div>

<div class="card card-stat p-3" style="max-width: 720px;">
    <h5>Itens</h5>
    <table class="table table-sm">
        <thead><tr><th>Produto</th><th class="text-end">Qtd</th><th class="text-end">Preço unit.</th><th class="text-end">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($itens as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['produto_nome']) ?></td>
                <td class="text-end"><?= (int) $item['quantidade'] ?></td>
                <td class="text-end"><?= Formatador::moeda((float) $item['preco_unitario']) ?></td>
                <td class="text-end"><?= Formatador::moeda((float) $item['subtotal']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<a href="/vendas/index.php" class="btn btn-outline-secondary mt-3">← Voltar</a>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
