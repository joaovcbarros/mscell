<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Helpers\Formatador;
use MsCell\Models\MetaBonificacao;
use MsCell\Services\AuthService;
use MsCell\Services\BonificacaoService;

AuthService::exigirPapel(['admin']);

$id = (int) ($_GET['id'] ?? 0);
$meta = MetaBonificacao::buscarPorId($id);

if (!$meta || $meta['status'] !== 'processada') {
    header('Location: /bonificacao/index.php');
    exit;
}

$itens = BonificacaoService::itensContabilizados($meta);
$totalItens = array_sum(array_column($itens, 'subtotal'));

$tituloPagina = 'Relatório de Bonificação';
require __DIR__ . '/../partials/head.php';
?>
<div class="container py-4" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-start mb-4 no-print">
        <a href="/bonificacao/index.php" class="btn btn-outline-secondary btn-sm">← Voltar</a>
        <button type="button" class="btn btn-mscell btn-sm" onclick="window.print()">Imprimir / Salvar como PDF</button>
    </div>

    <div class="d-flex align-items-center gap-2 mb-4">
        <img src="/assets/img/logo.png" alt="MsCell" style="height: 40px;">
        <div>
            <div class="fw-bold">MsCell</div>
            <div class="small text-muted">Relatório de Bonificação — conferência</div>
        </div>
    </div>

    <table class="table table-sm table-borderless mb-4">
        <tbody>
        <tr><th style="width: 200px;">Funcionário</th><td><?= htmlspecialchars($meta['usuario_nome']) ?></td></tr>
        <tr><th>Loja</th><td><?= htmlspecialchars($meta['loja_nome'] ?? '—') ?></td></tr>
        <tr><th>Competência</th><td><?= date('d/m/Y', strtotime($meta['data_inicio'])) ?> a <?= date('d/m/Y', strtotime($meta['data_fim'])) ?></td></tr>
        <tr><th>Processado em</th><td><?= Formatador::data($meta['processada_em']) ?></td></tr>
        </tbody>
    </table>

    <h5>Critérios da meta</h5>
    <table class="table table-sm mb-4">
        <thead><tr><th>Critério</th><th class="text-end">Exigido</th><th class="text-end">Alcançado</th></tr></thead>
        <tbody>
        <?php if ($meta['quantidade_vendas_min'] !== null): ?>
            <tr>
                <td>Quantidade de vendas</td>
                <td class="text-end"><?= (int) $meta['quantidade_vendas_min'] ?></td>
                <td class="text-end"><?= (int) $meta['quantidade_alcancada'] ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($meta['valor_minimo'] !== null): ?>
            <tr>
                <td>Valor faturado</td>
                <td class="text-end"><?= Formatador::moeda((float) $meta['valor_minimo']) ?></td>
                <td class="text-end"><?= Formatador::moeda((float) $meta['valor_alcancado']) ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="card card-stat p-3 mb-4">
        <h5>Resultado</h5>
        <?php if ($meta['meta_batida']): ?>
            <div class="alert alert-success py-2 mb-3">✅ Meta batida — bônus aplicado integralmente.</div>
        <?php else: ?>
            <div class="alert alert-warning py-2 mb-3">
                ⚠️ Meta não atingida — o funcionário chegou a <?= number_format((float) $meta['percentual_alcancado'], 0) ?>%
                do exigido. Sem bônus nessa competência (paga só o salário base).
            </div>
        <?php endif; ?>
        <table class="table table-sm table-borderless mb-0">
            <tbody>
            <tr><th style="width: 260px;">Salário base</th><td><?= Formatador::moeda((float) $meta['salario_base_registrado']) ?></td></tr>
            <tr><th>Percentual de bônus da meta</th><td><?= number_format((float) $meta['percentual_bonus'], 2, ',', '.') ?>%</td></tr>
            <tr><th>Bônus recebido</th><td><?= Formatador::moeda((float) $meta['valor_bonus']) ?></td></tr>
            <tr class="fw-bold"><th>Total pago (salário + bônus)</th><td><?= Formatador::moeda((float) $meta['valor_total_pago']) ?></td></tr>
            </tbody>
        </table>
    </div>

    <h5>Itens vendidos considerados</h5>
    <table class="table table-sm">
        <thead><tr><th>Data</th><th>Cliente</th><th>Produto</th><th class="text-end">Qtd</th><th class="text-end">Preço unit.</th><th class="text-end">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($itens as $item): ?>
            <tr>
                <td><?= Formatador::data($item['criado_em']) ?></td>
                <td><?= htmlspecialchars($item['cliente_nome'] ?: '—') ?></td>
                <td><?= htmlspecialchars($item['produto_nome']) ?></td>
                <td class="text-end"><?= (int) $item['quantidade'] ?></td>
                <td class="text-end"><?= Formatador::moeda((float) $item['preco_unitario']) ?></td>
                <td class="text-end"><?= Formatador::moeda((float) $item['subtotal']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($itens)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">Nenhum item vendido no período.</td></tr>
        <?php else: ?>
            <tr class="fw-bold"><td colspan="5" class="text-end">Total</td><td class="text-end"><?= Formatador::moeda($totalItens) ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <p class="small text-muted mt-4">Documento gerado em <?= date('d/m/Y H:i') ?> pelo sistema MsCell.</p>
</div>
<?php require __DIR__ . '/../partials/foot.php'; ?>
