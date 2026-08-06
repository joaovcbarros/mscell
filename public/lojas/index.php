<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Models\Loja;
use MsCell\Services\AuthService;

AuthService::exigirPapel(['admin']);

$lojas = Loja::todas();

$tituloPagina = 'Lojas';
$paginaAtual = 'lojas';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Lojas</h2>
    <a href="/lojas/form.php" class="btn btn-mscell">+ Nova loja</a>
</div>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="alert alert-success">Loja salva com sucesso.</div>
<?php endif; ?>
<?php if (isset($_GET['excluida'])): ?>
    <div class="alert alert-success">Loja excluída com sucesso.</div>
<?php endif; ?>
<?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_GET['erro']) ?></div>
<?php endif; ?>

<div class="card card-stat p-3">
    <table class="table table-sm align-middle">
        <thead>
        <tr>
            <th>Nome</th>
            <th>Endereço</th>
            <th>Número do WhatsApp</th>
            <th>Status</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($lojas as $loja): ?>
            <tr>
                <td><?= htmlspecialchars($loja['nome']) ?></td>
                <td><?= htmlspecialchars($loja['endereco'] ?: '—') ?></td>
                <td><?= htmlspecialchars($loja['numero_whatsapp'] ?: '—') ?></td>
                <td>
                    <?php if ($loja['ativa']): ?>
                        <span class="badge bg-success">Ativa</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inativa</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <a href="/lojas/form.php?id=<?= (int) $loja['id'] ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#modalExcluirLoja"
                            data-loja-id="<?= (int) $loja['id'] ?>"
                            data-loja-nome="<?= htmlspecialchars($loja['nome'], ENT_QUOTES) ?>">
                        Excluir
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($lojas)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma loja cadastrada.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="modalExcluirLoja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/lojas/excluir.php">
                <div class="modal-header">
                    <h5 class="modal-title">Excluir loja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Tem certeza que deseja excluir a loja
                        <strong id="modalExcluirLojaNome">—</strong>?
                    </p>
                    <div class="alert alert-warning small mb-0">
                        Essa ação não pode ser desfeita. Só é possível excluir lojas sem produtos,
                        vendas, usuários ou mensagens vinculados — nesse caso, desative-a em vez de excluir.
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="id" id="modalExcluirLojaId" value="">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Excluir loja</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('modalExcluirLoja').addEventListener('show.bs.modal', function (evento) {
    const botao = evento.relatedTarget;
    document.getElementById('modalExcluirLojaNome').textContent = botao.dataset.lojaNome;
    document.getElementById('modalExcluirLojaId').value = botao.dataset.lojaId;
});
</script>
<?php
require __DIR__ . '/../partials/layout_end.php';
require __DIR__ . '/../partials/foot.php';
