<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Models\MetaBonificacao;
use MsCell\Models\Produto;
use MsCell\Models\Usuario;
use MsCell\Services\AuthService;

AuthService::exigirPapel(['admin']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$meta = $id ? MetaBonificacao::buscarPorId($id) : null;

if ($id && !$meta) {
    header('Location: /bonificacao/index.php');
    exit;
}

$produtosSelecionados = $id ? array_column(MetaBonificacao::produtosDaMeta($id), 'id') : [];
$erros = [];

$lojaEfetiva = AuthService::lojaEfetiva();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    $qtdMin = trim($_POST['quantidade_vendas_min'] ?? '') !== '' ? (int) $_POST['quantidade_vendas_min'] : null;
    $valorMin = trim($_POST['valor_minimo'] ?? '') !== '' ? (float) str_replace(',', '.', $_POST['valor_minimo']) : null;
    $percentual = str_replace(',', '.', $_POST['percentual_bonus'] ?? '0');
    $produtosSelecionados = array_map('intval', $_POST['produtos'] ?? []);

    if ($usuarioId <= 0) {
        $erros[] = 'Selecione o funcionário.';
    }
    if ($dataInicio === '' || $dataFim === '') {
        $erros[] = 'Informe o período (início e fim).';
    } elseif ($dataFim < $dataInicio) {
        $erros[] = 'A data final não pode ser antes da data inicial.';
    }
    if ($qtdMin === null && $valorMin === null) {
        $erros[] = 'Defina pelo menos um critério: quantidade mínima de vendas ou valor mínimo.';
    }
    if (!is_numeric($percentual) || (float) $percentual <= 0) {
        $erros[] = 'Informe um percentual de bônus válido, maior que zero.';
    }

    $dados = [
        'usuario_id' => $usuarioId,
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim,
        'quantidade_vendas_min' => $qtdMin,
        'valor_minimo' => $valorMin,
        'percentual_bonus' => $percentual,
    ];

    if (empty($erros)) {
        if ($id) {
            MetaBonificacao::atualizar($id, $dados);
            $metaId = $id;
        } else {
            $metaId = MetaBonificacao::criar($dados);
        }
        MetaBonificacao::definirProdutos($metaId, $produtosSelecionados);

        header('Location: /bonificacao/index.php?sucesso=1');
        exit;
    }

    $meta = $dados;
}

$funcionarios = array_filter(Usuario::todos($lojaEfetiva), fn ($u) => $u['papel'] === Usuario::PAPEL_FUNCIONARIO);
$produtos = Produto::todos(true, $lojaEfetiva);

$tituloPagina = $id ? 'Editar meta' : 'Nova meta';
$paginaAtual = 'bonificacao';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<h2 class="mb-4"><?= $id ? 'Editar meta' : 'Nova meta de bonificação' ?></h2>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($erro) ?></div>
<?php endforeach; ?>

<div class="card card-stat p-4" style="max-width: 640px;">
    <form method="post" action="/bonificacao/form.php<?= $id ? '?id=' . $id : '' ?>">
        <div class="mb-3">
            <label class="form-label">Funcionário</label>
            <select name="usuario_id" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($funcionarios as $f): ?>
                    <option value="<?= (int) $f['id'] ?>" <?= (int) ($meta['usuario_id'] ?? 0) === (int) $f['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['nome']) ?><?= $lojaEfetiva === null ? ' — ' . htmlspecialchars($f['loja_nome'] ?? '') : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Início do período</label>
                <input type="date" name="data_inicio" class="form-control" required
                       value="<?= htmlspecialchars($meta['data_inicio'] ?? '') ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Fim do período</label>
                <input type="date" name="data_fim" class="form-control" required
                       value="<?= htmlspecialchars($meta['data_fim'] ?? '') ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Quantidade mínima de vendas</label>
                <input type="number" name="quantidade_vendas_min" class="form-control" min="1"
                       value="<?= htmlspecialchars((string) ($meta['quantidade_vendas_min'] ?? '')) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Valor mínimo faturado</label>
                <input type="text" name="valor_minimo" class="form-control" placeholder="ex: 5000,00"
                       value="<?= htmlspecialchars((string) ($meta['valor_minimo'] ?? '')) ?>">
            </div>
        </div>
        <div class="form-text mb-3">Defina pelo menos um dos dois critérios acima. Se definir os dois, o funcionário precisa bater ambos.</div>
        <div class="mb-3">
            <label class="form-label">Produtos-alvo (opcional)</label>
            <select name="produtos[]" class="form-select" multiple size="6">
                <?php foreach ($produtos as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $produtosSelecionados, true) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nome']) ?><?= $lojaEfetiva === null ? ' — ' . htmlspecialchars($p['loja_nome'] ?? '') : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Se nenhum produto for selecionado, a meta considera todas as vendas do funcionário. Se selecionar algum, só conta vendas desses produtos (Ctrl/Cmd + clique para selecionar mais de um).</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Percentual de bônus sobre o salário base</label>
            <div class="input-group" style="max-width: 200px;">
                <input type="text" name="percentual_bonus" class="form-control" placeholder="ex: 5"
                       value="<?= htmlspecialchars((string) ($meta['percentual_bonus'] ?? '')) ?>">
                <span class="input-group-text">%</span>
            </div>
        </div>
        <button type="submit" class="btn btn-mscell">Salvar</button>
        <a href="/bonificacao/index.php" class="btn btn-outline-secondary">Cancelar</a>
    </form>
</div>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
