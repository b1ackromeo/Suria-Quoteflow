@extends('layouts.app', ['title' => 'Audit Trail'])

@section('content')
<section class="panel">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Record</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($audits as $audit)
                <tr>
                    <td>{{ $audit->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $audit->user?->name ?? 'System' }}</td>
                    <td>{{ str_replace('_', ' ', $audit->action) }}</td>
                    <td>{{ class_basename($audit->auditable_type) }} #{{ $audit->auditable_id }}</td>
                    <td>{{ $audit->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-cell">No audit events yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $audits->links() }}</div>
</section>
@endsection
