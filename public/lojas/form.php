<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Models\Loja;
use MsCell\Services\AuthService;

AuthService::exigirPapel(['admin']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$loja = $id ? Loja::buscarPorId($id) : null;
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $numeroWhatsapp = preg_replace('/\D/', '', $_POST['numero_whatsapp'] ?? '');
    $ativa = isset($_POST['ativa']);

    if ($nome === '') {
        $erros[] = 'Nome é obrigatório.';
    }

    if (empty($erros)) {
        try {
            if ($id) {
                Loja::atualizar($id, $nome, $endereco, $numeroWhatsapp, $ativa);
            } else {
                Loja::criar($nome, $endereco, $numeroWhatsapp);
            }

            header('Location: /lojas/index.php?sucesso=1');
            exit;
        } catch (\PDOException $e) {
            $erros[] = str_contains($e->getMessage(), 'numero_whatsapp')
                ? 'Esse número de WhatsApp já está em uso por outra loja.'
                : 'Não foi possível salvar: ' . $e->getMessage();
        }
    }

    $loja = ['nome' => $nome, 'endereco' => $endereco, 'numero_whatsapp' => $numeroWhatsapp, 'ativa' => $ativa];
}

$tituloPagina = $id ? 'Editar loja' : 'Nova loja';
$paginaAtual = 'lojas';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<h2 class="mb-4"><?= $id ? 'Editar loja' : 'Nova loja' ?></h2>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($erro) ?></div>
<?php endforeach; ?>

<div class="card card-stat p-4" style="max-width: 560px;">
    <form method="post" action="/lojas/form.php<?= $id ? '?id=' . $id : '' ?>">
        <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" class="form-control" required
                   value="<?= htmlspecialchars($loja['nome'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Endereço (opcional)</label>
            <input type="text" name="endereco" class="form-control"
                   value="<?= htmlspecialchars($loja['endereco'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Número do WhatsApp da loja</label>
            <input type="text" name="numero_whatsapp" class="form-control"
                   placeholder="ex: 5511999999999"
                   value="<?= htmlspecialchars($loja['numero_whatsapp'] ?? '') ?>">
            <div class="form-text">
                Só dígitos (DDI+DDD+número). É o número que a ponte Node dessa loja vai parear —
                usado para reconhecer de qual loja vem cada mensagem. Deixe em branco se ainda não for usar o WhatsApp nessa loja.
            </div>
        </div>
        <?php if ($id): ?>
        <div class="form-check mb-3">
            <input type="checkbox" name="ativa" class="form-check-input" id="ativa"
                   <?= ($loja['ativa'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ativa">Loja ativa</label>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-mscell">Salvar</button>
        <a href="/lojas/index.php" class="btn btn-outline-secondary">Cancelar</a>
    </form>
</div>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
