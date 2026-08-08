<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Helpers\Formatador;
use MsCell\Models\MetaBonificacao;
use MsCell\Services\AuthService;
use MsCell\Services\BonificacaoService;

AuthService::exigirPapel(['admin']);

$lojaEfetiva = AuthService::lojaEfetiva();
$mostrarColunaLoja = $lojaEfetiva === null;

$status = in_array($_GET['status'] ?? '', ['ativa', 'processada'], true) ? $_GET['status'] : 'ativa';
$periodoInicio = $_GET['periodo_inicio'] ?? date('Y-m-01');
$periodoFim = $_GET['periodo_fim'] ?? date('Y-m-t');

$metas = MetaBonificacao::todas($lojaEfetiva, $status, $periodoInicio, $periodoFim);

$linhas = array_map(function (array $meta) use ($status) {
    if ($status === 'processada') {
        $progresso = [
            'quantidade_alcancada' => (int) $meta['quantidade_alcancada'],
            'valor_alcancado' => (float) $meta['valor_alcancado'],
            'atingida' => (bool) $meta['meta_batida'],
            'percentual_alcancado' => (float) $meta['percentual_alcancado'],
        ];
    } else {
        $progresso = BonificacaoService::avaliar($meta);
        $progresso['percentual_alcancado'] = BonificacaoService::calcularPercentualAlcancado(
            $meta,
            $progresso['quantidade_alcancada'],
            $progresso['valor_alcancado']
        );
    }

    return ['meta' => $meta, 'progresso' => $progresso];
}, $metas);

$tituloPagina = 'Bonificação';
$paginaAtual = 'bonificacao';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Bonificação</h2>
    <a href="/bonificacao/form.php" class="btn btn-mscell">+ Nova meta</a>
</div>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="alert alert-success">Meta salva com sucesso.</div>
<?php endif; ?>
<?php if (isset($_GET['processado'])): ?>
    <div class="alert alert-success">Competência processada com sucesso. A meta do próximo mês já foi criada.</div>
<?php endif; ?>
<?php if (isset($_GET['excluida'])): ?>
    <div class="alert alert-success">Meta excluída com sucesso.</div>
<?php endif; ?>
<?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_GET['erro']) ?></div>
<?php endif; ?>

<div class="card card-stat p-3 mb-3">
    <form class="row g-2 align-items-end" method="get">
        <div class="col-auto">
            <label class="form-label small mb-0">Período — de</label>
            <input type="date" name="periodo_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($periodoInicio) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">até</label>
            <input type="date" name="periodo_fim" class="form-control form-control-sm" value="<?= htmlspecialchars($periodoFim) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="ativa" <?= $status === 'ativa' ? 'selected' : '' ?>>Ativas</option>
                <option value="processada" <?= $status === 'processada' ? 'selected' : '' ?>>Processadas</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Buscar</button>
        </div>
    </form>
</div>

<div class="card card-stat p-3 mb-3">
    <table class="table table-sm align-middle">
        <thead>
        <tr>
            <th>Funcionário</th>
            <?php if ($mostrarColunaLoja): ?><th>Loja</th><?php endif; ?>
            <th>Período</th>
            <th>Critérios</th>
            <th class="text-end">Progresso</th>
            <th class="text-end">Bônus</th>
            <th>Status</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($linhas as $linha): ?>
            <?php $meta = $linha['meta']; $progresso = $linha['progresso']; ?>
            <tr>
                <td><?= htmlspecialchars($meta['usuario_nome']) ?></td>
                <?php if ($mostrarColunaLoja): ?><td><?= htmlspecialchars($meta['loja_nome'] ?? '—') ?></td><?php endif; ?>
                <td class="small"><?= date('d/m/Y', strtotime($meta['data_inicio'])) ?> – <?= date('d/m/Y', strtotime($meta['data_fim'])) ?></td>
                <td class="small">
                    <?php if ($meta['quantidade_vendas_min'] !== null): ?>
                        ≥ <?= (int) $meta['quantidade_vendas_min'] ?> vendas<br>
                    <?php endif; ?>
                    <?php if ($meta['valor_minimo'] !== null): ?>
                        ≥ <?= Formatador::moeda((float) $meta['valor_minimo']) ?>
                    <?php endif; ?>
                </td>
                <td class="text-end small">
                    <?= $progresso['quantidade_alcancada'] ?> vendas<br>
                    <?= Formatador::moeda($progresso['valor_alcancado']) ?>
                </td>
                <td class="text-end"><?= number_format((float) $meta['percentual_bonus'], 2, ',', '.') ?>%</td>
                <td>
                    <?php if ($status === 'processada'): ?>
                        <?php if ($progresso['atingida']): ?>
                            <span class="badge badge-meta-batida">Processada — meta batida</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Processada — alcançou <?= number_format($progresso['percentual_alcancado'], 0) ?>%</span>
                        <?php endif; ?>
                    <?php elseif ($progresso['atingida']): ?>
                        <span class="badge badge-meta-batida">Meta batida</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Em andamento — <?= number_format($progresso['percentual_alcancado'], 0) ?>%</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <?php if ($status === 'processada'): ?>
                        <a href="/bonificacao/relatorio.php?id=<?= (int) $meta['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Relatório</a>
                    <?php else: ?>
                        <a href="/bonificacao/form.php?id=<?= (int) $meta['id'] ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#modalExcluirMeta"
                                data-meta-id="<?= (int) $meta['id'] ?>"
                                data-meta-descricao="<?= htmlspecialchars($meta['usuario_nome'] . ' (' . date('d/m/Y', strtotime($meta['data_inicio'])) . ' – ' . date('d/m/Y', strtotime($meta['data_fim'])) . ')', ENT_QUOTES) ?>">
                            Excluir
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($linhas)): ?>
            <tr><td colspan="<?= 6 + ($mostrarColunaLoja ? 1 : 0) ?>" class="text-center text-muted py-4">Nenhuma meta encontrada nesse período.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($status === 'ativa' && !empty($linhas)): ?>
<div class="text-end">
    <button type="button" class="btn btn-mscell" data-bs-toggle="modal" data-bs-target="#modalProcessar">
        Processar bonificação dessa competência
    </button>
</div>

<div class="modal fade" id="modalProcessar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/bonificacao/processar.php">
                <div class="modal-header">
                    <h5 class="modal-title">Processar bonificação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>
                        Deseja processar a bonificação (salário base + percentual aplicado por
                        funcionário) da competência de
                        <strong><?= date('d/m/Y', strtotime($periodoInicio)) ?> a <?= date('d/m/Y', strtotime($periodoFim)) ?></strong>
                        para os <strong><?= count($linhas) ?></strong> funcionário(s) listados,
                        e iniciar a bonificação do próximo mês?
                    </p>
                    <div class="alert alert-warning small mb-0">
                        Essa ação fecha essas metas (não dá mais pra editar) e já cria a meta do
                        mês seguinte automaticamente, com os mesmos critérios.
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="periodo_inicio" value="<?= htmlspecialchars($periodoInicio) ?>">
                    <input type="hidden" name="periodo_fim" value="<?= htmlspecialchars($periodoFim) ?>">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-mscell">Processar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalExcluirMeta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/bonificacao/excluir.php">
                <div class="modal-header">
                    <h5 class="modal-title">Excluir meta de bonificação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Tem certeza que deseja excluir a meta de
                        <strong id="modalExcluirMetaDescricao">—</strong>?
                        Essa ação não pode ser desfeita.
                    </p>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="id" id="modalExcluirMetaId" value="">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Excluir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('modalExcluirMeta').addEventListener('show.bs.modal', function (evento) {
    const botao = evento.relatedTarget;
    document.getElementById('modalExcluirMetaDescricao').textContent = botao.dataset.metaDescricao;
    document.getElementById('modalExcluirMetaId').value = botao.dataset.metaId;
});
</script>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
