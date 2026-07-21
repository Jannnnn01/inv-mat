<?php

use CodeIgniter\Pager\PagerRenderer;

/** @var PagerRenderer $pager */
$pager->setSurroundCount(2);
$links = $pager->links();
?>

<?php if (count($links) > 1): ?>
    <nav class="app-pagination" aria-label="Paginación de resultados">
        <ul class="pagination justify-content-center justify-content-sm-end mb-0">
            <li class="page-item <?= $pager->hasPrevious() ? '' : 'disabled' ?>">
                <a
                    class="page-link"
                    href="<?= $pager->hasPrevious() ? esc($pager->getPrevious()) : '#' ?>"
                    aria-label="Página anterior"
                    <?= $pager->hasPrevious() ? '' : 'aria-disabled="true" tabindex="-1"' ?>
                >Anterior</a>
            </li>

            <?php foreach ($links as $link): ?>
                <li class="page-item <?= $link['active'] ? 'active' : '' ?>">
                    <a class="page-link" href="<?= esc($link['uri']) ?>" <?= $link['active'] ? 'aria-current="page"' : '' ?>><?= esc($link['title']) ?></a>
                </li>
            <?php endforeach ?>

            <li class="page-item <?= $pager->hasNext() ? '' : 'disabled' ?>">
                <a
                    class="page-link"
                    href="<?= $pager->hasNext() ? esc($pager->getNext()) : '#' ?>"
                    aria-label="Página siguiente"
                    <?= $pager->hasNext() ? '' : 'aria-disabled="true" tabindex="-1"' ?>
                >Siguiente</a>
            </li>
        </ul>
    </nav>
<?php endif ?>
