<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Helpers\Formatador;
use MsCell\Models\MensagemWhatsapp;
use MsCell\Services\AuthService;

AuthService::exigirPapel(['admin', 'funcionario']);

$lojaEfetiva = AuthService::lojaEfetiva();
$mostrarColunaLoja = $lojaEfetiva === null;
$mensagens = MensagemWhatsapp::recentes(50, $lojaEfetiva);

$tituloPagina = 'Mensagens WhatsApp';
$paginaAtual = 'whatsapp';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<h2 class="mb-4">Mensagens do WhatsApp</h2>
<p class="text-muted">
    Histórico de mensagens recebidas pela ponte do WhatsApp e como o sistema interpretou cada uma.
    Mensagens marcadas como <span class="badge bg-warning text-dark">revisão</span> não geraram venda
    automática — confira o texto original e cadastre a venda manualmente em
    <a href="/vendas/nova.php">Nova venda</a> se for o caso.
</p>

<div class="card card-stat p-3">
    <table class="table table-sm align-middle">
        <thead>
        <tr>
            <th>Data</th>
            <?php if ($mostrarColunaLoja): ?><th>Loja</th><?php endif; ?>
            <th>Número</th>
            <th>Mensagem</th>
            <th>Interpretação</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($mensagens as $m): ?>
            <?php $interpretacao = json_decode($m['interpretacao_json'] ?? '{}', true) ?: []; ?>
            <tr>
                <td><?= Formatador::data($m['criado_em']) ?></td>
                <?php if ($mostrarColunaLoja): ?><td><?= htmlspecialchars($m['loja_nome'] ?? '—') ?></td><?php endif; ?>
                <td><?= htmlspecialchars($m['numero_origem']) ?></td>
                <td><?= htmlspecialchars($m['mensagem_bruta']) ?></td>
                <td class="small">
                    <?php if (!empty($interpretacao['produto_nome'])): ?>
                        <?= htmlspecialchars($interpretacao['produto_nome']) ?>
                        (qtd <?= (int) ($interpretacao['quantidade'] ?? 1) ?>)
                        <?php if (isset($interpretacao['valor'])): ?>
                            — <?= Formatador::moeda((float) $interpretacao['valor']) ?>
                        <?php endif; ?>
                        <br><span class="text-muted">confiança: <?= (float) ($interpretacao['confianca_produto'] ?? 0) ?></span>
                    <?php else: ?>
                        <span class="text-muted">produto não identificado</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $badge = match ($m['status']) {
                        'processada' => 'bg-success',
                        'revisao' => 'bg-warning text-dark',
                        default => 'bg-danger',
                    };
                    ?>
                    <span class="badge <?= $badge ?>"><?= ucfirst($m['status']) ?></span>
                    <?php if ($m['venda_id']): ?>
                        <a href="/vendas/ver.php?id=<?= (int) $m['venda_id'] ?>" class="small d-block">ver venda #<?= (int) $m['venda_id'] ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($mensagens)): ?>
            <tr><td colspan="<?= 4 + ($mostrarColunaLoja ? 1 : 0) ?>" class="text-center text-muted py-4">Nenhuma mensagem recebida ainda.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
