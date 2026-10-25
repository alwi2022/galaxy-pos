(function ($) {
    'use strict';
    $(function () {
        var root = $('.galaxy-business');
        var tables = {};
        var tabs = root.find('.nav-tabs a[data-toggle="tab"]');
        var selectedTab = new URLSearchParams(window.location.search).get('tab');
        var savedTab = tabs.filter(function () { return this.getAttribute('href') === selectedTab; });
        if (savedTab.length) savedTab.tab('show');

        function initializeTables() {
            root.find('.business-datatable').filter(function () {
                var pane = $(this).closest('.tab-pane');
                return !pane.length || pane.hasClass('active');
            }).each(function () {
                var element = this;
                if ($.fn.dataTable.isDataTable(element)) return;
                var key = element.dataset.tableKey;
                tables[key] = $(element).DataTable({
                    responsive: false,
                    processing: true,
                    serverSide: true,
                    autoWidth: false,
                    scrollX: true,
                    order: [],
                    columnDefs: [{targets: 0, searchable: false, orderable: false}],
                    ajax: {
                        url: root.data('source'),
                        data: function (data) {
                            data.offset = data.start;
                            delete data.start;
                            data.data_table = key;
                            root.find('.business-filters').serializeArray().forEach(function (field) { data[field.name] = field.value; });
                        },
                        dataSrc: function (response) {
                            if (key === 'bank_page') {
                                root.find('.business-dialogs').html(response.dialogs);
                                var restoreAction = root.attr('data-restore-action');
                                if (restoreAction) {
                                    root.find('.business-dialogs .modal').filter(function () {
                                        var form = $(this).find('form');
                                        return form.find('[name="_action"]').val() === restoreAction && form.find('[name="id"]').val() === root.attr('data-restore-id');
                                    }).first().modal('show');
                                    root.removeAttr('data-restore-action');
                                }
                            }
                            return response.data;
                        }
                    }
                });
            });
            root.find('.business-static-table').filter(':visible').each(function () {
                if ($.fn.dataTable.isDataTable(this)) return;
                $(this).DataTable({responsive:false, processing:true, autoWidth:false, scrollX:true, order:[]});
            });
            $.fn.dataTable.tables({visible: true, api: true}).columns.adjust();
        }

        function activateTab() {
            var target = tabs.filter(function () { return $(this).parent().hasClass('active'); }).attr('href') || '';
            root.find('.business-filters input[name="tab"]').val(target);
            root.find('.business-status-filter').toggle(!target || target === '#tab-service_page' || target === '#tab-tax_page');
            root.find('.business-account-filter').toggle(!target || target === '#tab-journal');
            initializeTables();
        }
        tabs.on('shown.bs.tab', activateTab);
        activateTab();
        root.on('shown.bs.modal', '.modal', function () {
            $(this).find('input:not([type=hidden]):visible,select:visible,textarea:visible').first().trigger('focus');
        });
        root.on('click', '[data-service-id]', function () {
            $('#service-pay-id').val(this.dataset.serviceId);
            $('#service-pay-amount').val(this.dataset.serviceAmount).attr('max',this.dataset.serviceAmount);
        });
        root.on('click', '[data-bank-account]', function () {
            var bank = JSON.parse(this.dataset.bankAccount);
            $('#form-bank-account-update [name=id]').val(bank.id);
            ['bank_name', 'account_number', 'account_holder', 'address', 'city', 'phone', 'active'].forEach(function (field) {
                $('#bank-account-update-' + field).val(bank[field] === null ? '' : String(bank[field]));
            });
        });
        root.on('submit', '.business-form', function () {
            if (this.checkValidity()) $(this).find('button[type=submit]').prop('disabled',true).text('Menyimpan...');
        });
    });
})(jQuery);
