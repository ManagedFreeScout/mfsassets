/* MFS Assets (card #426): asset form (assigned to, owner → departments/locations), confirmations. */
$(document).on('click', '[data-mfsassets-confirm]', function (e) {
    if (!confirm($(this).attr('data-mfsassets-confirm'))) {
        e.preventDefault();
    }
});
$(document).on('change', '.mfsassets-form input[name=assigned_mode]', function () {
    var mode = $(this).val();
    $('.mfsassets-assign').hide();
    $('.mfsassets-assign-' + mode).show();
});
$(document).on('change', '.mfsassets-form .mfsassets-owner', function () {
    var $form = $(this).closest('form');
    $.getJSON($form.attr('data-options-url'), { owner_id: $(this).val() }, function (data) {
        var $d = $form.find('.mfsassets-departments').empty().append($('<option value="">').text('–'));
        $.each(data.departments || {}, function (id, name) { $d.append($('<option>').val(id).text(name)); });
        var $l = $form.find('.mfsassets-locations'), keep = $l.val();
        $l.find('optgroup').remove();
        $.each(data.locations || {}, function (org, tree) {
            var $g = $('<optgroup>').attr('label', org);
            $.each(tree, function (id, path) { $g.append($('<option>').val(id).text(path)); });
            $l.append($g);
        });
        $l.val(keep);
    });
});
/* Assets block: search an asset and link it in one go (select2 is part of FreeScout). */
$(function () {
    $('.mfsassets-search').each(function () {
        var $s = $(this), $form = $s.closest('form');
        if (!$.fn.select2) { return; }
        $s.select2({
            width: '100%', minimumInputLength: 2, placeholder: $s.attr('data-placeholder'), allowClear: true,
            ajax: { url: $s.attr('data-url'), dataType: 'json', delay: 250, data: function (p) { return { q: p.term }; }, processResults: function (d) { return { results: d }; } }
        }).on('select2:select', function (e) {
            $form.attr('action', $form.attr('data-action-base') + '/' + e.params.data.id + '/link').submit();
        });
    });
});
