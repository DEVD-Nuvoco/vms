            </div>
        </main>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="<?= $lieoAssets ?>js/azia.js"></script>

<?php
$lieoTestMails = function_exists('lieo_consume_test_mails') ? lieo_consume_test_mails() : [];
if ($lieoTestMails):
?>
<style>
  .lieo-mail-test {
    --mt-bg: #ffffff;
    --mt-panel: #f8fafc;
    --mt-border: #e2e8f0;
    --mt-text: #0f172a;
    --mt-muted: #64748b;
    --mt-link: #15803d;
    --mt-preview-border: #cbd5e1;
    position: fixed;
    inset: 12px;
    z-index: 1080;
    display: flex;
    flex-direction: column;
    background: var(--mt-bg);
    color: var(--mt-text);
    border: 1px solid var(--mt-border);
    border-radius: 10px;
    box-shadow: 0 20px 60px rgba(15, 23, 42, .25);
    overflow: hidden;
  }
  @media (prefers-color-scheme: dark) {
    .lieo-mail-test {
      --mt-bg: #0b1220;
      --mt-panel: #111827;
      --mt-border: #334155;
      --mt-text: #f8fafc;
      --mt-muted: #94a3b8;
      --mt-link: #4ade80;
      --mt-preview-border: #475569;
    }
  }
  .lieo-mail-test-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: .75rem 1rem;
    border-bottom: 1px solid var(--mt-border);
    background: var(--mt-panel);
  }
  .lieo-mail-test-head strong { font-size: .95rem; }
  .lieo-mail-test-body {
    overflow: auto;
    padding: 1rem 1.1rem 1.5rem;
    flex: 1;
  }
  .lieo-mail-test-meta {
    font-size: .875rem;
    line-height: 1.55;
    margin-bottom: 1rem;
  }
  .lieo-mail-test-meta .k { color: var(--mt-muted); font-weight: 700; }
  .lieo-mail-test-meta a { color: var(--mt-link); }
  .lieo-mail-test-preview-label {
    font-weight: 700;
    margin: 0 0 .5rem;
    font-size: .9rem;
  }
  .lieo-mail-test-preview {
    border: 1px solid var(--mt-preview-border);
    border-radius: 8px;
    overflow: auto;
    max-height: min(62vh, 720px);
    background: transparent;
  }
  .lieo-mail-test-preview iframe {
    width: 100%;
    min-height: 520px;
    border: 0;
    display: block;
    background: transparent;
  }
  .lieo-mail-test-sep {
    border: 0;
    border-top: 1px dashed var(--mt-border);
    margin: 1.25rem 0;
  }
</style>

<div class="lieo-mail-test" id="lieoMailTestPanel" role="dialog" aria-label="LIEO email test">
  <div class="lieo-mail-test-head">
    <strong>LIEO email test (TO / CC)</strong>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="lieoMailTestHide">Hide</button>
  </div>
  <div class="lieo-mail-test-body">
    <?php foreach ($lieoTestMails as $i => $m): ?>
      <?php
        $toName = trim((string) ($m['to_name'] ?? ''));
        $toEmail = trim((string) ($m['to'] ?? ''));
        $toRole = trim((string) ($m['to_role'] ?? ''));
        $ccList = is_array($m['cc'] ?? null) ? $m['cc'] : [];
        $ccRoles = is_array($m['cc_roles'] ?? null) ? $m['cc_roles'] : [];
        $context = trim((string) ($m['context'] ?? 'Notification'));
        $subject = (string) ($m['subject'] ?? '');
        $at = (string) ($m['at'] ?? '');
        $frameId = 'lieoMailFrame' . $i;
      ?>
      <div class="lieo-mail-test-meta">
        <div><span class="k">PREVIEW ·</span> <?= htmlspecialchars($context) ?></div>
        <div><?= htmlspecialchars($at) ?> · <?= htmlspecialchars($subject) ?></div>
        <div><span class="k">Subject:</span> <?= htmlspecialchars($subject) ?></div>
        <div>
          <span class="k">TO:</span>
          <?= htmlspecialchars($toName !== '' ? $toName : $toEmail) ?>
          — <a href="mailto:<?= htmlspecialchars($toEmail) ?>"><?= htmlspecialchars($toEmail) ?></a>
          <?php if ($toRole !== ''): ?>(<?= htmlspecialchars($toRole) ?>)<?php endif; ?>
        </div>
        <div>
          <span class="k">CC:</span>
          <?php if (!$ccList): ?>
            —
          <?php else: ?>
            <?php foreach ($ccList as $ci => $ccAddr): ?>
              <?php
                $ccAddr = trim((string) $ccAddr);
                $ccRole = trim((string) ($ccRoles[$ccAddr] ?? ''));
              ?>
              <?php if ($ci > 0): ?><br><span class="k" style="visibility:hidden">CC:</span><?php endif; ?>
              <a href="mailto:<?= htmlspecialchars($ccAddr) ?>"><?= htmlspecialchars($ccAddr) ?></a>
              <?php if ($ccRole !== ''): ?>(<?= htmlspecialchars($ccRole) ?>)<?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="lieo-mail-test-preview-label">Mail preview (exact send UI):</div>
      <div class="lieo-mail-test-preview">
        <iframe id="<?= htmlspecialchars($frameId) ?>" title="LIEO mail preview <?= (int) ($i + 1) ?>"></iframe>
        <textarea id="<?= htmlspecialchars($frameId) ?>Src" hidden><?= htmlspecialchars((string) ($m['body'] ?? ''), ENT_QUOTES) ?></textarea>
      </div>
      <?php if ($i < count($lieoTestMails) - 1): ?><hr class="lieo-mail-test-sep"><?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<script>
(function () {
  var panel = document.getElementById('lieoMailTestPanel');
  if (!panel) return;
  document.querySelectorAll('[id^="lieoMailFrame"]').forEach(function (frame) {
    if (!frame.id || frame.id.indexOf('Src') >= 0) return;
    var src = document.getElementById(frame.id + 'Src');
    if (!src) return;
    var doc = frame.contentDocument || frame.contentWindow.document;
    doc.open();
    doc.write(src.value);
    doc.close();
    setTimeout(function () {
      try {
        var h = Math.max(480, (doc.body && doc.body.scrollHeight) || 520);
        frame.style.height = h + 'px';
      } catch (e) {}
    }, 50);
  });
  var hideBtn = document.getElementById('lieoMailTestHide');
  if (hideBtn) {
    hideBtn.addEventListener('click', function () {
      panel.style.display = 'none';
    });
  }
})();
</script>
<?php endif; ?>

<!-- Shared LIEO confirm / alert popup (replaces browser confirm/alert) -->
<div class="modal fade" id="lieoDialogModal" tabindex="-1" role="dialog" aria-labelledby="lieoDialogTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width:420px;">
        <div class="modal-content lieo-dialog-content border-0 shadow">
            <div class="modal-body p-4 text-center">
                <div class="lieo-dialog-icon mb-3" id="lieoDialogIcon" aria-hidden="true">
                    <i class="typcn typcn-warning-outline"></i>
                </div>
                <h5 class="lieo-dialog-title mb-2" id="lieoDialogTitle">Confirm</h5>
                <p class="lieo-dialog-message text-muted mb-0" id="lieoDialogMessage"></p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary px-4" id="lieoDialogCancel" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-lieo px-4" id="lieoDialogOk">OK</button>
            </div>
        </div>
    </div>
</div>

<style>
#lieoDialogModal {
    z-index: 2000 !important;
}
#lieoDialogModal .modal-dialog,
#lieoDialogModal .modal-content {
    pointer-events: auto;
    position: relative;
    z-index: 2001;
}
.modal-backdrop.lieo-dialog-backdrop,
.modal-backdrop.show:last-of-type {
    z-index: 1990 !important;
}
.lieo-dialog-content { border-radius: 14px; overflow: hidden; }
.lieo-dialog-icon {
    width: 56px;
    height: 56px;
    margin: 0 auto;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    background: rgba(66, 187, 82, 0.12);
    color: var(--lieo-green, #42bb52);
}
.lieo-dialog-icon.is-danger {
    background: rgba(220, 53, 69, 0.12);
    color: #dc3545;
}
.lieo-dialog-icon.is-info {
    background: rgba(14, 165, 233, 0.12);
    color: #0284c7;
}
.lieo-dialog-title {
    font-weight: 700;
    color: #0f172a;
    font-size: 1.1rem;
}
.lieo-dialog-message {
    font-size: .9375rem;
    line-height: 1.5;
    white-space: pre-wrap;
}
#lieoDialogModal .btn-danger {
    background: #dc3545;
    border-color: #dc3545;
}
#lieoDialogModal .btn-danger:hover {
    background: #c82333;
    border-color: #bd2130;
}
</style>
<script>
(function (window, $) {
    function whenJq(fn) {
        if (window.jQuery) {
            fn(window.jQuery);
            return;
        }
        var n = 0;
        var t = setInterval(function () {
            n += 1;
            if (window.jQuery) {
                clearInterval(t);
                fn(window.jQuery);
            } else if (n > 200) {
                clearInterval(t);
            }
        }, 25);
    }

    whenJq(function ($) {
        function ensureDialogOnBody() {
            var $m = $('#lieoDialogModal');
            if ($m.length) {
                $m.appendTo('body');
            }
            return $m;
        }

        function cleanupBackdrops() {
            // Remove orphaned overlays that block clicks
            if (!$('.modal.show').length) {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css({ overflow: '', paddingRight: '' });
            }
        }

        function showDialog($m) {
            ensureDialogOnBody();
            // Drop stale backdrops before opening
            $('.modal-backdrop').remove();
            $m.css('z-index', 2000);
            $m.modal({ backdrop: 'static', keyboard: true, show: true });
            setTimeout(function () {
                $('.modal-backdrop').last().addClass('lieo-dialog-backdrop').css('z-index', 1990);
            }, 10);
        }

        window.lieoConfirm = function (opts) {
            opts = opts || {};
            if (typeof opts === 'string') {
                opts = { message: opts };
            }
            var $m = ensureDialogOnBody();
            var $icon = $('#lieoDialogIcon');
            var $ok = $('#lieoDialogOk');
            var $cancel = $('#lieoDialogCancel');
            var variant = opts.variant || (opts.danger ? 'danger' : 'success');

            $('#lieoDialogTitle').text(opts.title || 'Please confirm');
            $('#lieoDialogMessage').text(opts.message || 'Are you sure?');
            $cancel.text(opts.cancelText || 'Cancel').show();
            $ok.text(opts.confirmText || 'Confirm')
                .removeClass('btn-lieo btn-danger btn-primary')
                .addClass(variant === 'danger' ? 'btn-danger' : 'btn-lieo');

            $icon.removeClass('is-danger is-info')
                .addClass(variant === 'danger' ? 'is-danger' : (variant === 'info' ? 'is-info' : ''))
                .html(variant === 'danger'
                    ? '<i class="typcn typcn-trash"></i>'
                    : (variant === 'info'
                        ? '<i class="typcn typcn-info-large-outline"></i>'
                        : '<i class="typcn typcn-warning-outline"></i>'));

            return new Promise(function (resolve) {
                var settled = false;
                function finish(ok) {
                    if (settled) { return; }
                    settled = true;
                    $m.off('hidden.bs.modal.lieoDialog');
                    $ok.off('click.lieoDialog');
                    $cancel.off('click.lieoDialog');
                    $m.modal('hide');
                    setTimeout(cleanupBackdrops, 200);
                    resolve(!!ok);
                }
                $ok.off('click.lieoDialog').on('click.lieoDialog', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    finish(true);
                });
                $cancel.off('click.lieoDialog').on('click.lieoDialog', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    finish(false);
                });
                $m.off('hidden.bs.modal.lieoDialog').on('hidden.bs.modal.lieoDialog', function () {
                    cleanupBackdrops();
                    finish(false);
                });
                showDialog($m);
            });
        };

        window.lieoAlert = function (opts) {
            opts = opts || {};
            if (typeof opts === 'string') {
                opts = { message: opts };
            }
            var $m = ensureDialogOnBody();
            var $icon = $('#lieoDialogIcon');
            var $ok = $('#lieoDialogOk');
            var $cancel = $('#lieoDialogCancel');
            var variant = opts.variant || 'info';

            $('#lieoDialogTitle').text(opts.title || 'Notice');
            $('#lieoDialogMessage').text(opts.message || '');
            $cancel.hide();
            $ok.text(opts.confirmText || 'OK')
                .removeClass('btn-danger btn-lieo')
                .addClass(variant === 'danger' ? 'btn-danger' : 'btn-lieo');
            $icon.removeClass('is-danger is-info')
                .addClass(variant === 'danger' ? 'is-danger' : 'is-info')
                .html(variant === 'danger'
                    ? '<i class="typcn typcn-warning-outline"></i>'
                    : '<i class="typcn typcn-info-large-outline"></i>');

            return new Promise(function (resolve) {
                var settled = false;
                function finish() {
                    if (settled) { return; }
                    settled = true;
                    $m.off('hidden.bs.modal.lieoDialog');
                    $ok.off('click.lieoDialog');
                    $m.modal('hide');
                    setTimeout(cleanupBackdrops, 200);
                    resolve();
                }
                $ok.off('click.lieoDialog').on('click.lieoDialog', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    finish();
                });
                $m.off('hidden.bs.modal.lieoDialog').on('hidden.bs.modal.lieoDialog', function () {
                    cleanupBackdrops();
                    finish();
                });
                showDialog($m);
            });
        };

        // Clear any leftover overlay on page load
        cleanupBackdrops();

        <?php if (!empty($_SESSION['lieo_alert']) && is_array($_SESSION['lieo_alert'])): ?>
        <?php
            $lieoAlertPayload = [
                'title' => (string) ($_SESSION['lieo_alert']['title'] ?? 'Notice'),
                'message' => (string) ($_SESSION['lieo_alert']['message'] ?? ''),
                'variant' => (string) ($_SESSION['lieo_alert']['variant'] ?? 'info'),
            ];
            unset($_SESSION['lieo_alert']);
        ?>
        lieoAlert(<?= json_encode($lieoAlertPayload, JSON_UNESCAPED_UNICODE) ?>);
        <?php endif; ?>

        $(document).on('submit', 'form[data-lieo-confirm]', function (e) {
            var form = this;
            if (form.getAttribute('data-lieo-confirmed') === '1') {
                form.removeAttribute('data-lieo-confirmed');
                return true;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            var msg = form.getAttribute('data-lieo-confirm') || 'Are you sure?';
            var title = form.getAttribute('data-lieo-confirm-title') || 'Please confirm';
            var danger = form.getAttribute('data-lieo-danger') === '1';
            var confirmText = form.getAttribute('data-lieo-confirm-ok') || (danger ? 'Remove' : 'Confirm');
            window.lieoConfirm({
                title: title,
                message: msg,
                danger: danger,
                confirmText: confirmText
            }).then(function (ok) {
                if (!ok) { return; }
                form.setAttribute('data-lieo-confirmed', '1');
                HTMLFormElement.prototype.submit.call(form);
            });
            return false;
        });
    });
})(window, window.jQuery);
</script>

<script>
$(function () {
    var $trailModal = $('#lieoTrailModal');
    if ($trailModal.length && !$trailModal.parent().is('body')) {
        $trailModal.appendTo('body');
    }

    if ($('.lieo-datatable').length) {
        $('.lieo-datatable').DataTable({
            pageLength: 10,
            order: [],
            dom: '<"row mx-0"<"col-sm-6"l><"col-sm-6"f>>rt<"row mx-0"<"col-sm-6"i><"col-sm-6"p>>',
            language: { emptyTable: 'No records to display' }
        });
    }

    if ($('.lieo-history-table').length && $.fn.DataTable) {
        $('.lieo-history-table').DataTable({
            pageLength: 10,
            lengthMenu: [[10, 25, 50], [10, 25, 50]],
            order: [[0, 'desc']],
            autoWidth: false,
            columnDefs: [
                { orderable: false, targets: [6, 7, 8] }
            ],
            dom: '<"row px-3 pt-2"<"col-sm-6"l><"col-sm-6"f>>rt<"row px-3 pb-2"<"col-sm-6"i><"col-sm-6"p>>'
        });
    }

    // Open native date/time picker when clicking anywhere on the input (not only the icon).
    $(document).on('click', 'input[type="date"], input[type="datetime-local"], input[type="time"]', function () {
        var el = this;
        if (typeof el.showPicker === 'function') {
            try {
                el.showPicker();
            } catch (err) {
                // Older browsers / non-secure contexts may reject showPicker.
            }
        }
    });

    function lieoShowTrailModal() {
        var $m = $('#lieoTrailModal');
        if ($.fn.modal) {
            $m.modal('show');
            return;
        }
        $m.addClass('show').css('display', 'block').attr('aria-hidden', 'false');
        $('body').addClass('modal-open');
        if (!$('.modal-backdrop').length) {
            $('<div class="modal-backdrop fade show"></div>').appendTo('body');
        }
    }

    $(document).on('click', '.lieo-trail-open', function (e) {
        e.preventDefault();
        var id = this.getAttribute('data-target-id');
        var appNo = this.getAttribute('data-app-no') || '';
        var $src = $();
        if (id) {
            var el = document.getElementById(id);
            if (el) {
                $src = $(el);
            }
        }
        if (!$src.length) {
            $src = $(this).closest('td').find('.lieo-trail-source').first();
        }
        var $body = $('#lieoTrailModalBody');
        var $title = $('#lieoTrailModalAppNo');
        if (!$src.length || !$body.length) {
            return;
        }
        $body.html($src.html());
        $title.text(appNo);
        lieoShowTrailModal();
    });

    $(document).on('click', '#lieoTrailModal [data-dismiss="modal"], #lieoTrailModal .close', function () {
        var $m = $('#lieoTrailModal');
        if ($.fn.modal) {
            $m.modal('hide');
        } else {
            $m.removeClass('show').hide().attr('aria-hidden', 'true');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        }
    });
});
</script>
</body>
</html>
