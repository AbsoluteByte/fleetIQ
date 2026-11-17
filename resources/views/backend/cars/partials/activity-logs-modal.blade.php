@if(!empty($canViewCarLogs))
    <div class="modal fade" id="carActivityLogsModal" tabindex="-1" role="dialog" aria-labelledby="carActivityLogsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document" style="max-width: 40rem;">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title" id="carActivityLogsModalLabel">Car logs</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @forelse($carActivityLogs ?? [] as $log)
                        <div class="mb-1 {{ $loop->last ? '' : 'pb-1 border-bottom' }}">
                            <div class="text-muted small">{{ $log->created_at?->format('d M Y H:i') }}</div>
                            <div>{{ $log->summary }}</div>
                            @if($log->changeLines() !== [])
                                <ul class="mb-0 pl-1 mt-50">
                                    @foreach($log->changeLines() as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @empty
                        <p class="mb-0 text-muted">No activity has been recorded for this car.</p>
                    @endforelse
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif
