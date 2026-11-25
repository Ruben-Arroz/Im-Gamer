<?php
// campo para escolher um jogo da rawg (com o pesquisa_jogo.js), usado em 4 formulários
// cada página diz antes o $jogoTexto e o $jogoIdInicial que aparecem da primeira vez
?>
<div class="pesquisa-jogo">
    <input type="text" id="jogo_pesquisa" name="jogo_pesquisa" autocomplete="off" placeholder="Escreve o nome do jogo"
        data-chave="<?= htmlspecialchars(RAWG_API_KEY) ?>"
        data-base="<?= BASE_URL ?>"
        value="<?= htmlspecialchars($_POST['jogo_pesquisa'] ?? $jogoTexto) ?>">
    <ul id="jogo_sugestoes" class="sugestoes"></ul>
</div>
<input type="hidden" name="jogo_id" value="<?= (int) ($_POST['jogo_id'] ?? $jogoIdInicial) ?>">
<input type="hidden" id="jogo_rawg_id" name="jogo_rawg_id" value="<?= htmlspecialchars($_POST['jogo_rawg_id'] ?? '') ?>">
<input type="hidden" id="jogo_nome" name="jogo_nome" value="<?= htmlspecialchars($_POST['jogo_nome'] ?? '') ?>">
<input type="hidden" id="jogo_slug" name="jogo_slug" value="<?= htmlspecialchars($_POST['jogo_slug'] ?? '') ?>">
<input type="hidden" id="jogo_imagem" name="jogo_imagem" value="<?= htmlspecialchars($_POST['jogo_imagem'] ?? '') ?>">
<input type="hidden" id="jogo_lancamento" name="jogo_lancamento" value="<?= htmlspecialchars($_POST['jogo_lancamento'] ?? '') ?>">
<p class="ajuda">Pesquisa feita na <a href="https://rawg.io" target="_blank" rel="noopener">RAWG</a>.</p>
