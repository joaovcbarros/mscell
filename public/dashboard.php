<?php

require_once __DIR__ . '/../src/bootstrap.php';

use MsCell\Helpers\Formatador;
use MsCell\Models\MensagemWhatsapp;
use MsCell\Models\Produto;
use MsCell\Models\Venda;
use MsCell\Services\AuthService;

AuthService::exigirLogin();

$lojaEfetiva = AuthService::lojaEfetiva();
$mostrarColunaLoja = $lojaEfetiva === null;

$resumoDia = Venda::resumoDoDia($lojaEfetiva);
$resumoMes = Venda::resumoDoMes($lojaEfetiva);
$resumoPorLoja = $mostrarColunaLoja ? Venda::resumoDoDiaPorLoja() : [];
$estoqueBaixo = Produto::comEstoqueBaixo($lojaEfetiva);
$vendasRecentes = Venda::recentes(8, $lojaEfetiva);
$mensagensPendentes = in_array(AuthService::papelAtual(), ['admin', 'funcionario'], true)
    ? MensagemWhatsapp::pendentesDeRevisao($lojaEfetiva)
    : [];

$tituloPagina = 'Dashboard';
$paginaAtual = 'dashboard';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/layout_start.php';
?>
<h2 class="mb-4">Dashboard</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-stat p-3">
            <div class="text-muted small">Vendas hoje</div>
            <div class="stat-value"><?= (int) $resumoDia['total_vendas'] ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat p-3">
            <div class="text-muted small">Faturado hoje</div>
            <div class="stat-value"><?= Formatador::moeda((float) $resumoDia['total_valor']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat p-3">
            <div class="text-muted small">Vendas no mês</div>
            <div class="stat-value"><?= (int) $resumoMes['total_vendas'] ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat p-3">
            <div class="text-muted small">Faturado no mês</div>
            <div class="stat-value"><?= Formatador::moeda((float) $resumoMes['total_valor']) ?></div>
        </div>
    </div>
</div>

<?php if ($mostrarColunaLoja && !empty($resumoPorLoja)): ?>
<div class="card card-stat p-3 mb-4">
    <h5>Vendas de hoje por loja</h5>
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Loja</th><th class="text-end">Vendas</th><th class="text-end">Faturado</th></tr></thead>
        <tbody>
        <?php foreach ($resumoPorLoja as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['loja_nome']) ?></td>
                <td class="text-end"><?= (int) $r['total_vendas'] ?></td>
                <td class="text-end"><?= Formatador::moeda((float) $r['total_valor']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card card-stat p-3">
            <h5>Últimas vendas</h5>
            <?php if (empty($vendasRecentes)): ?>
                <p class="text-muted">Nenhuma venda registrada ainda.</p>
            <?php else: ?>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Data</th><?php if ($mostrarColunaLoja): ?><th>Loja</th><?php endif; ?><th>Cliente</th><th>Origem</th><th class="text-end">Valor</th></tr></thead>
                    <tbody>
                    <?php foreach ($vendasRecentes as $v): ?>
                        <tr>
                            <td><?= Formatador::data($v['criado_em']) ?></td>
                            <?php if ($mostrarColunaLoja): ?><td><?= htmlspecialchars($v['loja_nome'] ?? '—') ?></td><?php endif; ?>
                            <td><?= htmlspecialchars($v['cliente_nome'] ?: '—') ?></td>
                            <td>
                                <?php if ($v['origem'] === 'whatsapp'): ?>
                                    <span class="badge badge-origem-whatsapp">WhatsApp</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Sistema</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= Formatador::moeda((float) $v['valor_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="/vendas/index.php" class="small">Ver todas as vendas →</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card card-stat p-3 mb-3">
            <h5>Estoque baixo</h5>
            <?php if (empty($estoqueBaixo)): ?>
                <p class="text-muted">Nenhum produto abaixo do estoque mínimo.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($estoqueBaixo as $p): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><?= htmlspecialchars($p['nome']) ?><?php if ($mostrarColunaLoja): ?> <span class="text-muted small">(<?= htmlspecialchars($p['loja_nome'] ?? '—') ?>)</span><?php endif; ?></span>
                            <span class="badge badge-baixo-estoque"><?= (int) $p['quantidade_estoque'] ?> un.</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if (in_array(AuthService::papelAtual(), ['admin', 'funcionario'], true)): ?>
        <div class="card card-stat p-3">
            <h5>Mensagens do WhatsApp pendentes</h5>
            <?php if (empty($mensagensPendentes)): ?>
                <p class="text-muted">Nenhuma mensagem aguardando revisão.</p>
            <?php else: ?>
                <p class="mb-2"><?= count($mensagensPendentes) ?> mensagem(ns) precisam de revisão manual.</p>
                <a href="/whatsapp/index.php" class="btn btn-mscell btn-sm">Revisar agora</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
require __DIR__ . '/partials/layout_end.php';
require __DIR__ . '/partials/foot.php';
