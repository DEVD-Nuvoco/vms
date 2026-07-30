<?php
/**
 * Shared CLGP page chrome — page header, panels, empty states.
 */

function clgp_page_header(string $title, string $lead = '', string $actionsHtml = ''): void
{
    ?>
    <div class="clgp-page-header">
        <div class="clgp-page-header-text">
            <h1 class="clgp-page-title"><?= htmlspecialchars($title) ?></h1>
            <?php if ($lead !== ''): ?>
            <p class="clgp-page-lead"><?= htmlspecialchars($lead) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($actionsHtml !== ''): ?>
        <div class="clgp-page-header-actions"><?= $actionsHtml ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/** @param int|null $count Optional badge count beside title */
function clgp_panel_open(string $title, ?int $count = null, string $subtitle = ''): void
{
    ?>
    <section class="clgp-panel mb-4">
        <header class="clgp-panel-head">
            <div>
                <h2 class="clgp-panel-title"><?= htmlspecialchars($title) ?></h2>
                <?php if ($subtitle !== ''): ?>
                <p class="clgp-panel-subtitle mb-0"><?= htmlspecialchars($subtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($count !== null): ?>
            <span class="clgp-panel-count"><?= (int) $count ?></span>
            <?php endif; ?>
        </header>
        <div class="clgp-panel-body">
    <?php
}

function clgp_panel_close(): void
{
    echo '</div></section>';
}

function clgp_empty_state(string $message, string $hint = ''): void
{
    ?>
    <div class="clgp-empty-state">
        <div class="clgp-empty-icon" aria-hidden="true"><i class="typcn typcn-document-text"></i></div>
        <p class="clgp-empty-message"><?= htmlspecialchars($message) ?></p>
        <?php if ($hint !== ''): ?>
        <p class="clgp-empty-hint"><?= htmlspecialchars($hint) ?></p>
        <?php endif; ?>
    </div>
    <?php
}
