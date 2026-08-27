<?php
/**
 * Shared LIEO page chrome — page header, panels, empty states.
 */

function lieo_page_header(string $title, string $lead = '', string $actionsHtml = ''): void
{
    ?>
    <div class="lieo-page-header">
        <div class="lieo-page-header-text">
            <h1 class="lieo-page-title"><?= htmlspecialchars($title) ?></h1>
            <?php if ($lead !== ''): ?>
            <p class="lieo-page-lead"><?= htmlspecialchars($lead) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($actionsHtml !== ''): ?>
        <div class="lieo-page-header-actions"><?= $actionsHtml ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/** @param int|null $count Optional badge count beside title */
function lieo_panel_open(string $title, ?int $count = null, string $subtitle = ''): void
{
    ?>
    <section class="lieo-panel mb-4">
        <header class="lieo-panel-head">
            <div>
                <h2 class="lieo-panel-title"><?= htmlspecialchars($title) ?></h2>
                <?php if ($subtitle !== ''): ?>
                <p class="lieo-panel-subtitle mb-0"><?= htmlspecialchars($subtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($count !== null): ?>
            <span class="lieo-panel-count"><?= (int) $count ?></span>
            <?php endif; ?>
        </header>
        <div class="lieo-panel-body">
    <?php
}

function lieo_panel_close(): void
{
    echo '</div></section>';
}

function lieo_empty_state(string $message, string $hint = ''): void
{
    ?>
    <div class="lieo-empty-state">
        <div class="lieo-empty-icon" aria-hidden="true"><i class="typcn typcn-document-text"></i></div>
        <p class="lieo-empty-message"><?= htmlspecialchars($message) ?></p>
        <?php if ($hint !== ''): ?>
        <p class="lieo-empty-hint"><?= htmlspecialchars($hint) ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Display mobile numbers as readable groups, e.g. 9999900001 → 999 990 0001.
 * Supports multiple numbers separated by / or ,.
 */
function lieo_format_mobile(?string $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }
    $parts = preg_split('/\s*[\/,|;]\s*/', $raw) ?: [];
    $formatted = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        $digits = preg_replace('/\D+/', '', $part) ?? '';
        if (strlen($digits) === 10) {
            $formatted[] = substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 4);
        } elseif ($digits !== '') {
            $formatted[] = $digits;
        } else {
            $formatted[] = $part;
        }
    }
    return $formatted ? implode(' / ', $formatted) : $raw;
}
