@php
    $insuranceEmailNotice = session('insurance_email_notice');
@endphp
@if(is_array($insuranceEmailNotice))
    @php
        $insuranceEmailSent = ! empty($insuranceEmailNotice['sent']);
        $insuranceEmailAction = ($insuranceEmailNotice['action'] ?? '') === 'remove' ? 'removal' : 'add';
        $insuranceEmailTo = array_values(array_filter((array) ($insuranceEmailNotice['to'] ?? [])));
        $insuranceEmailCc = array_values(array_filter((array) ($insuranceEmailNotice['cc'] ?? [])));
        $insuranceEmailReason = trim((string) ($insuranceEmailNotice['reason'] ?? ''));
    @endphp
    <div class="modal fade" id="insuranceEmailNoticeModal" tabindex="-1" role="dialog" aria-labelledby="insuranceEmailNoticeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 28rem;">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title d-flex align-items-center mb-0" id="insuranceEmailNoticeModalLabel">
                        <span class="rounded-circle d-inline-flex align-items-center justify-content-center mr-75"
                              style="width:2.25rem;height:2.25rem;background:{{ $insuranceEmailSent ? 'rgba(40,199,111,.12)' : 'rgba(234,84,85,.12)' }};color:{{ $insuranceEmailSent ? '#28c76f' : '#ea5455' }};">
                            <i class="fa {{ $insuranceEmailSent ? 'fa-check' : 'fa-exclamation' }}"></i>
                        </span>
                        <span>Insurance email</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pt-1 pb-2">
                    <p class="mb-1 text-body" style="line-height: 1.55;">
                        @if($insuranceEmailSent)
                            The insurance {{ $insuranceEmailAction }} email was sent.
                        @else
                            The insurance {{ $insuranceEmailAction }} email was not sent.
                        @endif
                    </p>
                    @if($insuranceEmailSent)
                        <p class="mb-50"><strong>To:</strong> {{ $insuranceEmailTo === [] ? 'None' : implode(', ', $insuranceEmailTo) }}</p>
                        <p class="mb-0"><strong>CC:</strong> {{ $insuranceEmailCc === [] ? 'None' : implode(', ', $insuranceEmailCc) }}</p>
                    @elseif($insuranceEmailReason !== '')
                        <p class="mb-0 text-muted">{{ $insuranceEmailReason }}</p>
                    @endif
                    <button type="button" class="btn btn-outline-secondary btn-block mt-1 mb-0" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @push('js')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.jQuery) {
                    window.jQuery('#insuranceEmailNoticeModal').modal('show');
                }
            });
        </script>
    @endpush
@endif
