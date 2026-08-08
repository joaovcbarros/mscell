<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Models\Loja;
use MsCell\Models\Produto;
use MsCell\Models\Usuario;
use MsCell\Services\AuthService;
use MsCell\Services\VendaService;

AuthService::exigirPapel(['admin', 'funcionario']);

$erro = null;

// Loja da venda: funcionario sempre usa a sua; admin usa a do seletor do
// topo se houver uma especifica escolhida, senao precisa escolher aqui
// (via ?loja_id=) antes de poder lancar a venda.
$lojaId = AuthService::lojaEfetiva();
if ($lojaId === null && AuthService::podeVerTodasLojas() && !empty($_GET['loja_id'])) {
    $lojaId = (int) $_GET['loja_id'];
}

if ($lojaId === null) {
    $lojasParaEscolher = Loja::todas(true);

    $tituloPagina = 'Nova venda';
    $paginaAtual = 'vendas';
    require __DIR__ . '/../partials/head.php';
    require __DIR__ . '/../partials/layout_start.php';
    ?>
    <h2 class="mb-4">Nova venda</h2>
    <div class="card card-stat p-4" style="max-width: 480px;">
        <p>Você está vendo "todas as lojas". Escolha para qual loja é essa venda:</p>
        <form method="get" class="d-flex gap-2">
            <select name="loja_id" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($lojasParaEscolher as $l): ?>
                    <option value="<?= (int) $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-mscell text-nowrap">Continuar</button>
        </form>
    </div>
    <?php
    require __DIR__ . '/../partials/layout_end.php';
    require __DIR__ . '/../partials/foot.php';
    exit;
}

// Vendedor: quem recebe o credito da venda (usado no dashboard e na
// bonificacao) — pode ser qualquer funcionario da loja, ou o proprio
// usuario logado (inclusive o dono/admin, se for ele quem vende).
// Diferente de "quem registrou", que e sempre o usuario logado de fato.
$vendedoresDisponiveis = array_values(array_filter(
    Usuario::todos($lojaId),
    fn ($u) => $u['papel'] === Usuario::PAPEL_FUNCIONARIO
));
$usuarioAtual = Usuario::buscarPorId(AuthService::idAtual());
if ($usuarioAtual && !in_array((int) $usuarioAtual['id'], array_column($vendedoresDisponiveis, 'id'), true)) {
    $vendedoresDisponiveis[] = $usuarioAtual;
}
usort($vendedoresDisponiveis, fn ($a, $b) => strcmp($a['nome'], $b['nome']));
$vendedorIdsValidos = array_column($vendedoresDisponiveis, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produtoIds = $_POST['produto_id'] ?? [];
    $quantidades = $_POST['quantidade'] ?? [];
    $precos = $_POST['preco_unitario'] ?? [];

    $itens = [];
    foreach ($produtoIds as $i => $produtoId) {
        if ($produtoId === '') {
            continue;
        }
        $itens[] = [
            'produto_id' => (int) $produtoId,
            'quantidade' => (int) ($quantidades[$i] ?? 1),
            'preco_unitario' => (float) str_replace(',', '.', $precos[$i] ?? '0'),
        ];
    }

    // Vendedor: so aceita um id que realmente esteja na lista de
    // funcionarios/usuario atual dessa loja (evita atribuir a venda a
    // alguem fora do escopo via requisicao adulterada).
    $vendedorId = (int) ($_POST['vendedor_id'] ?? 0);
    if (!in_array($vendedorId, $vendedorIdsValidos, true)) {
        $vendedorId = AuthService::idAtual();
    }

    try {
        if (empty($itens)) {
            throw new RuntimeException('Adicione pelo menos um item à venda.');
        }

        VendaService::registrar(
            // usa o $lojaId ja resolvido no servidor (nao o do POST, que
            // um funcionario mal-intencionado poderia adulterar para
            // tentar vender em nome de outra loja).
            lojaId: $lojaId,
            itens: $itens,
            formaPagamento: $_POST['forma_pagamento'] ?? 'outro',
            clienteNome: trim($_POST['cliente_nome'] ?? '') ?: null,
            usuarioId: $vendedorId,
            origem: 'sistema',
            registradoPorId: AuthService::idAtual()
        );

        header('Location: /vendas/index.php?sucesso=1');
        exit;
    } catch (\Throwable $e) {
        $erro = $e->getMessage();
    }
}

$produtos = Produto::todos(true, $lojaId);

$tituloPagina = 'Nova venda';
$paginaAtual = 'vendas';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<h2 class="mb-4">Nova venda</h2>

<?php if ($erro): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="card card-stat p-4" style="max-width: 900px;">
    <form method="post" id="form-venda">
        <input type="hidden" name="loja_id" value="<?= (int) $lojaId ?>">
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Vendedor</label>
                <select name="vendedor_id" class="form-select">
                    <?php foreach ($vendedoresDisponiveis as $v): ?>
                        <option value="<?= (int) $v['id'] ?>" <?= (int) $v['id'] === AuthService::idAtual() ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['nome']) ?><?= $v['papel'] === 'admin' ? ' (dono)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Quem recebe o crédito dessa venda (conta pra bonificação). Por padrão, você mesmo.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Forma de pagamento</label>
                <select name="forma_pagamento" class="form-select">
                    <option value="dinheiro">Dinheiro</option>
                    <option value="pix">Pix</option>
                    <option value="debito">Débito</option>
                    <option value="credito">Crédito</option>
                    <option value="outro">Outro</option>
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Cliente (opcional)</label>
                <input type="text" name="cliente_nome" class="form-control">
            </div>
        </div>

        <h6>Itens</h6>
        <table class="table table-sm" id="tabela-itens">
            <thead>
            <tr>
                <th style="width: 40%">Produto</th>
                <th style="width: 15%">Quantidade</th>
                <th style="width: 20%">Preço unit.</th>
                <th style="width: 20%">Subtotal</th>
                <th></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
        <button type="button" id="btn-add-item" class="btn btn-sm btn-outline-secondary mb-3">+ Adicionar item</button>

        <div class="text-end mb-3">
            <strong>Total: <span id="total-venda">R$ 0,00</span></strong>
        </div>

        <button type="submit" class="btn btn-mscell">Registrar venda</button>
        <a href="/vendas/index.php" class="btn btn-outline-secondary">Cancelar</a>
    </form>
</div>

<template id="template-linha-item">
    <tr>
        <td>
            <select name="produto_id[]" class="form-select form-select-sm select-produto" required>
                <option value="">Selecione...</option>
                <?php foreach ($produtos as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" data-preco="<?= (float) $p['preco_venda'] ?>" data-estoque="<?= (int) $p['quantidade_estoque'] ?>">
                        <?= htmlspecialchars($p['nome']) ?> (estoque: <?= (int) $p['quantidade_estoque'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" name="quantidade[]" class="form-control form-control-sm input-qtd" value="1" min="1" required></td>
        <td><input type="text" name="preco_unitario[]" class="form-control form-control-sm input-preco" required></td>
        <td class="text-end subtotal-linha align-middle">R$ 0,00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remover-linha">×</button></td>
    </tr>
</template>

<script>
const tabela = document.querySelector('#tabela-itens tbody');
const template = document.getElementById('template-linha-item');
const totalEl = document.getElementById('total-venda');

function formatarMoeda(valor) {
    return 'R$ ' + valor.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d)(?=,))/g, '.');
}

function recalcularTotal() {
    let total = 0;
    tabela.querySelectorAll('tr').forEach(linha => {
        const qtd = parseFloat(linha.querySelector('.input-qtd').value) || 0;
        const preco = parseFloat(linha.querySelector('.input-preco').value.replace(',', '.')) || 0;
        const subtotal = qtd * preco;
        linha.querySelector('.subtotal-linha').textContent = formatarMoeda(subtotal);
        total += subtotal;
    });
    totalEl.textContent = formatarMoeda(total);
}

function adicionarLinha() {
    const linha = template.content.cloneNode(true);
    tabela.appendChild(linha);
    const novaLinha = tabela.lastElementChild;

    novaLinha.querySelector('.select-produto').addEventListener('change', function () {
        const opt = this.selectedOptions[0];
        const preco = opt.dataset.preco || '0';
        novaLinha.querySelector('.input-preco').value = parseFloat(preco).toFixed(2);
        recalcularTotal();
    });
    novaLinha.querySelector('.input-qtd').addEventListener('input', recalcularTotal);
    novaLinha.querySelector('.input-preco').addEventListener('input', recalcularTotal);
    novaLinha.querySelector('.btn-remover-linha').addEventListener('click', function () {
        novaLinha.remove();
        recalcularTotal();
    });
}

document.getElementById('btn-add-item').addEventListener('click', adicionarLinha);
adicionarLinha();
</script>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
