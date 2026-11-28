</main>

<footer class="site-footer">
    <p>ESAN, Universidade de Aveiro. Ano letivo 2026/2027.</p>
</footer>

<?php if (Auth::autenticado()): ?>
    <script src="<?= BASE_URL ?>/projeto/assets/js/tarefa.js?v=1" data-base="<?= BASE_URL ?>" data-token="<?= Auth::token() ?>" defer></script>
<?php endif; ?>

</body>
</html>
