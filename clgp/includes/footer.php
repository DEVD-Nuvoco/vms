            </div>
        </main>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="<?= $clgpAssets ?>js/azia.js"></script>
<script>
$(function () {
    var $trailModal = $('#clgpTrailModal');
    if ($trailModal.length && !$trailModal.parent().is('body')) {
        $trailModal.appendTo('body');
    }

    if ($('.clgp-datatable').length) {
        $('.clgp-datatable').DataTable({
            pageLength: 10,
            order: [],
            dom: '<"row mx-0"<"col-sm-6"l><"col-sm-6"f>>rt<"row mx-0"<"col-sm-6"i><"col-sm-6"p>>',
            language: { emptyTable: 'No records to display' }
        });
    }

    if ($('.clgp-history-table').length && $.fn.DataTable) {
        $('.clgp-history-table').DataTable({
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

    function clgpShowTrailModal() {
        var $m = $('#clgpTrailModal');
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

    $(document).on('click', '.clgp-trail-open', function (e) {
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
            $src = $(this).closest('td').find('.clgp-trail-source').first();
        }
        var $body = $('#clgpTrailModalBody');
        var $title = $('#clgpTrailModalAppNo');
        if (!$src.length || !$body.length) {
            return;
        }
        $body.html($src.html());
        $title.text(appNo);
        clgpShowTrailModal();
    });

    $(document).on('click', '#clgpTrailModal [data-dismiss="modal"], #clgpTrailModal .close', function () {
        var $m = $('#clgpTrailModal');
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
