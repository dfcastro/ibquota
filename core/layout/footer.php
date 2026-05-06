<?php
/**
 * IFQUOTA - Rodapé Universal (Público e Admin com Contraste)
 * Mudança de marca: IBQUOTA -> IFQUOTA
 */
?>

<!-- FECHA A DIV PRINCIPAL (CONTAINER) ABERTA NO HEADER.PHP -->
</div> 

<footer class="footer text-center mt-5 pt-4 pb-4 shadow-sm bg-white border-top">
    <div class="container">

        <!-- O Nome Oficial agora é IFQUOTA -->
        <h5 class="text-dark mb-2 fw-bold">
            🖨️ Sistema de Controle de Impressão (IFQuota)
        </h5>

        <p class="text-muted small mb-3">
            &copy; <?php echo date("Y"); ?> Instituto Federal do Norte de Minas Gerais - Campus Almenara
        </p>

        <!-- Caixa de Contactos NTI -->
        <div class="text-muted small mb-3 p-3 rounded border d-inline-block bg-light border-light shadow-sm">
            <span class="d-block mb-1 text-dark">Problemas com o acesso? Procure o <b>Núcleo de Tecnologia da Informação (NTI)</b>:</span>
            <span class="mt-1 d-block">
                <strong>WhatsApp:</strong>
                <a href="https://wa.me/5533984447401" target="_blank" class="text-success text-decoration-none fw-bold">
                    +55 33 98444-7401
                </a> |
                <strong>E-mail:</strong>
                <a href="mailto:ti.almenara@ifnmg.edu.br" class="text-success text-decoration-none fw-bold">
                    ti.almenara@ifnmg.edu.br
                </a>
            </span>
        </div>

        <!-- Opção 3 de crédito que você escolheu, atualizada para a nova realidade -->
        <p class="mb-0 small fst-italic text-muted">
            Desenvolvido pelo NTI (IFNMG). Baseado no projeto open-source <a href="https://www.ib.unicamp.br/ibquota/" target="_blank" class="text-secondary fw-bold text-decoration-none">IBQUOTA</a>.
        </p>

    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>