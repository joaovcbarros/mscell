<?php

use MsCell\Models\Loja;
use MsCell\Services\AuthService;

$papelAtual = AuthService::papelAtual();
$paginaAtual = $paginaAtual ?? '';

$itensMenu = [
    ['chave' => 'dashboard', 'href' => '/dashboard.php', 'label' => 'Dashboard', 'papeis' => ['admin', 'funcionario', 'usuario']],
    ['chave' => 'produtos', 'href' => '/produtos/index.php', 'label' => 'Produtos', 'papeis' => ['admin', 'funcionario', 'usuario']],
    ['chave' => 'vendas', 'href' => '/vendas/index.php', 'label' => $papelAtual === 'funcionario' ? 'Minhas Vendas' : 'Vendas', 'papeis' => ['admin', 'funcionario', 'usuario']],
    ['chave' => 'estoque', 'href' => '/estoque/index.php', 'label' => 'Estoque', 'papeis' => ['admin', 'funcionario', 'usuario']],
    ['chave' => 'whatsapp', 'href' => '/whatsapp/index.php', 'label' => 'Mensagens WhatsApp', 'papeis' => ['admin', 'funcionario']],
    ['chave' => 'bonificacao', 'href' => '/bonificacao/index.php', 'label' => 'Bonificação', 'papeis' => ['admin']],
    ['chave' => 'lojas', 'href' => '/lojas/index.php', 'label' => 'Lojas', 'papeis' => ['admin']],
    ['chave' => 'usuarios', 'href' => '/usuarios/index.php', 'label' => 'Usuários', 'papeis' => ['admin']],
];
?>
<div class="d-flex">
    <nav class="mscell-sidebar p-3" style="width: 250px;">
        <a href="/dashboard.php" class="brand-row mb-4">
            <img src="/assets/img/logo.png" alt="MsCell" class="brand-logo">
            <span class="brand-wordmark">MsCell</span>
        </a>
        <ul class="nav nav-pills flex-column mb-auto">
            <?php foreach ($itensMenu as $item): ?>
                <?php if (in_array($papelAtual, $item['papeis'], true)): ?>
                    <li class="nav-item">
                        <a href="<?= $item['href'] ?>" class="nav-link <?= $paginaAtual === $item['chave'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
        <hr class="text-secondary">
        <div class="small text-secondary mb-2">
            <?= htmlspecialchars($_SESSION['usuario_nome'] ?? '') ?><br>
            <?= htmlspecialchars(\MsCell\Helpers\Formatador::papel($papelAtual ?? '')) ?>
        </div>
        <a href="/logout.php" class="btn btn-outline-light btn-sm w-100">Sair</a>
    </nav>
    <main class="flex-grow-1 p-4">
        <?php if (AuthService::podeVerTodasLojas()): ?>
            <?php $lojasParaSeletor = Loja::todas(true); ?>
            <form method="post" action="/lojas/selecionar.php" class="d-flex align-items-center gap-2 mb-3">
                <input type="hidden" name="voltar" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/dashboard.php') ?>">
                <label class="small text-muted mb-0">Visualizando:</label>
                <select name="loja_id" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="" <?= AuthService::lojaEfetiva() === null ? 'selected' : '' ?>>Todas as lojas</option>
                    <?php foreach ($lojasParaSeletor as $lojaOpcao): ?>
                        <option value="<?= (int) $lojaOpcao['id'] ?>" <?= AuthService::lojaEfetiva() === (int) $lojaOpcao['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lojaOpcao['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php elseif (AuthService::lojaId() !== null): ?>
            <?php $minhaLoja = Loja::buscarPorId(AuthService::lojaId()); ?>
            <div class="small text-muted mb-3">Loja: <strong><?= htmlspecialchars($minhaLoja['nome'] ?? '—') ?></strong></div>
        <?php endif; ?>

        <?php if (isset($_GET['erro']) && $_GET['erro'] === 'acesso_negado'): ?>
            <div class="alert alert-danger">Você não tem permissão para acessar essa página.</div>
        <?php endif; ?>
