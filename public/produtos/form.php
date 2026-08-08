<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Models\Loja;
use MsCell\Models\Produto;
use MsCell\Services\AuthService;
use MsCell\Services\ProdutoService;

AuthService::exigirPapel(['admin', 'funcionario']);

$podeVerCusto = AuthService::papelAtual() === 'admin';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Admin enxerga/edita produto de qualquer loja; funcionario so os da sua.
$restricaoLoja = AuthService::podeVerTodasLojas() ? null : AuthService::lojaId();
$produto = $id ? Produto::buscarPorId($id, $restricaoLoja) : null;

if ($id && !$produto) {
    header('Location: /produtos/index.php');
    exit;
}

$erros = [];
$lojas = AuthService::podeVerTodasLojas() ? Loja::todas(true) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // loja_id: fixo pra quem nao e admin; obrigatorio escolher pra admin
    // (so faz sentido definir a loja na criacao, nao muda depois).
    if (!$id) {
        $lojaId = AuthService::podeVerTodasLojas()
            ? (int) ($_POST['loja_id'] ?? 0)
            : AuthService::lojaId();
    } else {
        $lojaId = (int) $produto['loja_id'];
    }

    // Funcionario nao ve/envia preco de custo: preserva o valor que ja existia
    // (edicao) ou fica 0 numa criacao nova, ate o admin completar depois.
    $precoCusto = $podeVerCusto
        ? str_replace(',', '.', $_POST['preco_custo'] ?? '0')
        : (string) ($produto['preco_custo'] ?? 0);

    $dados = [
        'loja_id' => $lojaId,
        'nome' => trim($_POST['nome'] ?? ''),
        'apelidos' => trim($_POST['apelidos'] ?? '') ?: null,
        'categoria_id' => $_POST['categoria_id'] ?? null,
        'sku' => trim($_POST['sku'] ?? '') ?: null,
        'preco_custo' => $precoCusto,
        'preco_venda' => str_replace(',', '.', $_POST['preco_venda'] ?? '0'),
        'quantidade_estoque' => $_POST['quantidade_estoque'] ?? 0,
        'estoque_minimo' => $_POST['estoque_minimo'] ?? 0,
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
    ];

    $erros = ProdutoService::validar($dados);

    if (empty($lojaId)) {
        $erros[] = 'Selecione a loja.';
    }

    if (empty($erros)) {
        if ($id) {
            ProdutoService::atualizar($id, $dados);
        } else {
            ProdutoService::criar($dados);
        }

        header('Location: /produtos/index.php?sucesso=1');
        exit;
    }

    $produto = $dados;
}

$categorias = Produto::categorias();

$tituloPagina = $id ? 'Editar produto' : 'Novo produto';
$paginaAtual = 'produtos';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<h2 class="mb-4"><?= $id ? 'Editar produto' : 'Novo produto' ?></h2>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($erro) ?></div>
<?php endforeach; ?>

<div class="card card-stat p-4" style="max-width: 640px;">
    <form method="post" action="/produtos/form.php<?= $id ? '?id=' . $id : '' ?>">
        <?php if ($id): ?>
            <div class="mb-3">
                <label class="form-label">Loja</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($produto['loja_nome'] ?? '') ?>" disabled>
            </div>
        <?php elseif (AuthService::podeVerTodasLojas()): ?>
            <div class="mb-3">
                <label class="form-label">Loja</label>
                <select name="loja_id" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($lojas as $l): ?>
                        <option value="<?= (int) $l['id'] ?>" <?= AuthService::lojaEfetiva() === (int) $l['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" class="form-control" required
                   value="<?= htmlspecialchars($produto['nome'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Apelidos (usados pelo reconhecimento via WhatsApp)</label>
            <input type="text" name="apelidos" class="form-control"
                   placeholder="ex: iphone 15 pro max|15 pro max|15pm"
                   value="<?= htmlspecialchars($produto['apelidos'] ?? '') ?>">
            <div class="form-text">Separe variações do nome com "|". Ajuda o sistema a reconhecer o produto em mensagens livres do WhatsApp.</div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Categoria</label>
                <select name="categoria_id" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= ($produto['categoria_id'] ?? null) == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($produto['sku'] ?? '') ?>">
            </div>
        </div>
        <div class="row">
            <?php if ($podeVerCusto): ?>
            <div class="col-md-6 mb-3">
                <label class="form-label">Preço de custo</label>
                <input type="text" name="preco_custo" class="form-control" required
                       value="<?= htmlspecialchars((string) ($produto['preco_custo'] ?? '0')) ?>">
            </div>
            <?php endif; ?>
            <div class="<?= $podeVerCusto ? 'col-md-6' : 'col-md-12' ?> mb-3">
                <label class="form-label">Preço de venda</label>
                <input type="text" name="preco_venda" class="form-control" required
                       value="<?= htmlspecialchars((string) ($produto['preco_venda'] ?? '0')) ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Quantidade em estoque</label>
                <input type="number" name="quantidade_estoque" class="form-control" min="0"
                       <?= $id ? 'readonly' : '' ?>
                       value="<?= htmlspecialchars((string) ($produto['quantidade_estoque'] ?? '0')) ?>">
                <?php if ($id): ?>
                    <div class="form-text">Para alterar o estoque de um produto existente, use a tela de <a href="/estoque/index.php">Estoque</a>.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Estoque mínimo</label>
                <input type="number" name="estoque_minimo" class="form-control" min="0"
                       value="<?= htmlspecialchars((string) ($produto['estoque_minimo'] ?? '0')) ?>">
            </div>
        </div>
        <?php if ($id): ?>
        <div class="form-check mb-3">
            <input type="checkbox" name="ativo" class="form-check-input" id="ativo"
                   <?= ($produto['ativo'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ativo">Produto ativo</label>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-mscell">Salvar</button>
        <a href="/produtos/index.php" class="btn btn-outline-secondary">Cancelar</a>
    </form>
</div>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
