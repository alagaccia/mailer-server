<?php

namespace App\Http\Controllers;

use App\Models\Email;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard principale: statistiche globali + coda filtrata e paginata.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'recipient' => trim((string) $request->query('filter_recipient', '')) ?: null,
            'subject' => trim((string) $request->query('filter_subject', '')) ?: null,
            'date_from' => $this->validDate($request->query('filter_date_from')),
            'date_to' => $this->validDate($request->query('filter_date_to')),
        ];

        $stats = Email::query()
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(status = 'pending') as pending")
            ->selectRaw("sum(status = 'sent') as sent")
            ->selectRaw("sum(status = 'failed') as failed")
            ->first();

        $emails = Email::query()
            ->select(['id', 'recipient', 'subject', 'status', 'attachments', 'created_at'])
            ->filter($filters)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Email $email): array => [
                'id' => $email->id,
                'recipient' => $email->recipient,
                'subject' => $email->subject,
                'status' => $email->status,
                'attachments_meta' => $email->attachmentsMeta(),
                'created_at' => $email->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'pending' => (int) ($stats->pending ?? 0),
                'sent' => (int) ($stats->sent ?? 0),
                'failed' => (int) ($stats->failed ?? 0),
                'total' => (int) ($stats->total ?? 0),
            ],
            'emails' => $emails,
            'filters' => [
                'filter_recipient' => $filters['recipient'] ?? '',
                'filter_subject' => $filters['subject'] ?? '',
                'filter_date_from' => $filters['date_from'] ?? '',
                'filter_date_to' => $filters['date_to'] ?? '',
            ],
        ]);
    }

    protected function validDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
