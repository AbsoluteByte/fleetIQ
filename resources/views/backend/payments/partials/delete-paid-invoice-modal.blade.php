@once('delete-paid-invoice-modal')
    <div class="modal fade" id="deletePaidInvoiceModal" tabindex="-1" role="dialog" aria-labelledby="deletePaidInvoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 28rem;">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title d-flex align-items-center mb-0" id="deletePaidInvoiceModalLabel">
                        <span class="rounded-circle d-inline-flex align-items-center justify-content-center mr-75"
                              style="width:2.25rem;height:2.25rem;background:rgba(234,84,85,.12);color:#ea5455;">
                            <i class="fa fa-trash"></i>
                        </span>
                        <span>Delete invoice</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="deletePaidInvoiceForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <div class="modal-body pt-1 pb-2">
                        <p class="mb-1 text-body" id="deletePaidInvoiceBody" style="line-height: 1.55;"></p>
                        <p class="small text-muted mb-1">The money already applied to this invoice can be deleted with it, or left as driver credit.</p>
                        <button type="submit" class="btn btn-outline-primary btn-block mb-75" name="payment_disposition" value="credit">
                            Add to driver credit
                        </button>
                        <button type="submit" class="btn btn-danger btn-block mb-75" name="payment_disposition" value="delete">
                            Delete the payment too
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-block mb-0" data-dismiss="modal">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modal = window.jQuery ? window.jQuery('#deletePaidInvoiceModal') : null;
                var form = document.getElementById('deletePaidInvoiceForm');
                var body = document.getElementById('deletePaidInvoiceBody');

                if (!form || !modal || !body) {
                    return;
                }

                document.addEventListener('click', function (event) {
                    var button = event.target.closest('.js-delete-paid-invoice');
                    if (!button) {
                        return;
                    }

                    var invoiceNo = button.getAttribute('data-invoice-no') || 'this invoice';
                    var paidAmount = button.getAttribute('data-paid-amount') || '0.00';
                    form.action = button.getAttribute('data-destroy-url') || '';
                    body.textContent = 'Delete ' + invoiceNo + '? £' + paidAmount + ' has already been paid on this invoice.';
                    modal.modal('show');
                });
            });
        </script>
    @endpush
@endonce
