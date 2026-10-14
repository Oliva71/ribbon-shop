<?php
/** @var array $breadcrumbs */
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <li class="breadcrumb-item">
                <?php if ($crumb['url']): ?>
                    <a href="<?= htmlspecialchars($crumb['url']) ?>">
                        <?= $crumb['title'] ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted fw-bold"><?= $crumb['title'] ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
