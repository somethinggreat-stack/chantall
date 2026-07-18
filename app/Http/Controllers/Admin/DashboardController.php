<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Models\Enrollment;
use App\Models\FundingLead;
use App\Models\Payment;
use App\Models\PopupSubmission;
use App\Models\TaxLead;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Allowed status values for any lead. */
    public const STATUSES = ['new', 'in_progress', 'replied', 'won', 'closed'];

    /**
     * Registry describing every lead type: model, labels and how to render it
     * in lists and detail views.
     */
    private function types(): array
    {
        return [
            'enrollments' => [
                'model'    => Enrollment::class,
                'label'    => 'Paid Credit Repair Clients',
                'singular' => 'Credit Repair Client',
                'icon'     => '&#9873;',
                'name'     => fn ($r) => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: 'Unnamed',
                'summary'  => fn ($r) => trim(($r->plan ?? '') . ($r->amount ? ' · $' . number_format((float) $r->amount, 2) : '') . ($r->payment_status === 'paid' ? ' · PAID' : '')),
                'sections' => [
                    'Client' => [
                        'first_name' => 'First Name',
                        'last_name'  => 'Last Name',
                        'email'      => 'Email',
                        'phone'      => 'Phone',
                    ],
                    'Mailing Address' => [
                        'address' => 'Street Address',
                        'city'    => 'City',
                        'state'   => 'State',
                        'zip'     => 'ZIP Code',
                    ],
                    'Program & Payment' => [
                        'plan'           => 'Plan',
                        'amount'         => 'Program Fee',
                        'payment_status' => 'Payment Status',
                        'transaction_id' => 'Transaction ID',
                        'auth_code'      => 'Auth Code',
                        'card_type'      => 'Card Type',
                        'card_last4'     => 'Card (last 4)',
                        'invoice_number' => 'Invoice #',
                    ],
                    'Agreements Accepted' => [
                        'agree_terms'     => 'Service Agreement',
                        'agree_privacy'   => 'Privacy Policy',
                        'agree_marketing' => 'Marketing Opt-in',
                    ],
                ],
            ],
            'funding' => [
                'model'    => FundingLead::class,
                'label'    => 'Funding Leads',
                'singular' => 'Funding Lead',
                'icon'     => '&#36;',
                'name'     => fn ($r) => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: 'Unnamed',
                'summary'  => fn ($r) => trim(($r->funding_goal ?? '') . ($r->credit_score ? ' · ' . $r->credit_score : '')),
                'sections' => [
                    'Applicant' => [
                        'first_name' => 'First Name',
                        'last_name'  => 'Last Name',
                        'email'      => 'Email',
                        'phone'      => 'Phone',
                    ],
                    'Funding Profile' => [
                        'funding_goal'       => 'Funding Goal',
                        'business_situation' => 'Business Situation',
                        'annual_income'      => 'Annual Income',
                    ],
                    'Credit Profile' => [
                        'credit_cards'       => 'Combined Card Limits',
                        'credit_utilization' => 'Credit Utilization',
                        'credit_score'       => 'FICO Score',
                        'credit_profile'     => 'Negative Marks',
                        'confirmation'       => 'Accuracy Confirmation',
                    ],
                ],
            ],
            'contacts' => [
                'model'    => ContactSubmission::class,
                'label'    => 'Contact Us Submissions',
                'singular' => 'Contact Submission',
                'icon'     => '&#9993;',
                'name'     => fn ($r) => $r->name ?: 'Unnamed',
                'summary'  => fn ($r) => $r->subject ?: '—',
                'sections' => [
                    'Submitter' => [
                        'name'  => 'Full Name',
                        'email' => 'Email',
                        'phone' => 'Phone',
                    ],
                    'Inquiry' => [
                        'subject' => 'Subject',
                    ],
                    'Message' => [
                        'message' => 'Message',
                    ],
                ],
            ],
            'tax' => [
                'model'    => TaxLead::class,
                'label'    => 'Tax Leads',
                'singular' => 'Tax Lead',
                'icon'     => '&#9638;',
                'name'     => fn ($r) => $r->name ?: 'Unnamed',
                'summary'  => fn ($r) => $r->service ?: '—',
                'sections' => [
                    'Submitter' => [
                        'name'  => 'Full Name',
                        'email' => 'Email',
                        'phone' => 'Phone',
                    ],
                    'Request' => [
                        'service' => 'Service Needed',
                    ],
                    'Message' => [
                        'message' => 'Message',
                    ],
                ],
            ],
            'popups' => [
                'model'    => PopupSubmission::class,
                'label'    => 'Popup Submissions',
                'singular' => 'Popup Submission',
                'icon'     => '&#9733;',
                'name'     => fn ($r) => $r->name ?: 'Unnamed',
                'summary'  => fn ($r) => $r->interests ?: '—',
                'sections' => [
                    'Submitter' => [
                        'name'  => 'Full Name',
                        'email' => 'Email',
                        'phone' => 'Phone',
                    ],
                    'Details' => [
                        'interests' => 'Interested In',
                        'source'    => 'Source',
                        'page'      => 'Submitted From',
                    ],
                ],
            ],
        ];
    }

    private function resolveType(string $type): array
    {
        $types = $this->types();
        abort_unless(isset($types[$type]), 404);

        return $types[$type] + ['slug' => $type];
    }

    /**
     * Build a lightweight row array for list / latest rendering.
     */
    private function row(array $meta, $record): array
    {
        return [
            'id'         => $record->id,
            'name'       => ($meta['name'])($record),
            'email'      => $record->email ?? null,
            'phone'      => $record->phone ?? null,
            'summary'    => ($meta['summary'])($record),
            'status'     => $record->status ?? 'new',
            'created_at' => $record->created_at,
        ];
    }

    /**
     * Main dashboard — stat cards + latest of every type.
     */
    public function index()
    {
        $today  = now()->startOfDay();
        $cards  = [];
        $latest = [];
        $total  = 0;
        $totalToday = 0;

        foreach ($this->types() as $slug => $meta) {
            $model = $meta['model'];
            $count = $model::count();
            $todayCount = $model::where('created_at', '>=', $today)->count();
            $newCount = $model::where('status', 'new')->count();

            $total += $count;
            $totalToday += $todayCount;

            $cards[$slug] = [
                'slug'  => $slug,
                'label' => $meta['label'],
                'icon'  => $meta['icon'],
                'count' => $count,
                'today' => $todayCount,
                'new'   => $newCount,
            ];

            $latest[$slug] = [
                'label' => $meta['label'],
                'rows'  => $model::latest()->take(5)->get()->map(fn ($r) => $this->row($meta, $r))->all(),
            ];
        }

        // ---- Payment KPIs ----
        $kpis = [
            'captures_today_count' => Payment::whereIn('type', ['initial', 'recurring'])->where('status', 'captured')->where('charged_at', '>=', $today)->count(),
            'captures_today_sum'   => (float) Payment::whereIn('type', ['initial', 'recurring'])->where('status', 'captured')->where('charged_at', '>=', $today)->sum('amount'),
            'declines_today'       => Payment::where('status', 'failed')->where('charged_at', '>=', $today)->count(),
            'refunds_today_count'  => Payment::whereIn('type', ['refund', 'void'])->where('charged_at', '>=', $today)->count(),
            'refunds_today_sum'    => (float) Payment::whereIn('type', ['refund', 'void'])->where('charged_at', '>=', $today)->sum('amount'),
            'gross_lifetime'       => (float) Payment::whereIn('type', ['initial', 'recurring'])->where('status', 'captured')->sum('amount'),
            'held_review'          => WebhookEvent::where('event_type', 'net.authorize.payment.fraud.held')->count(),
        ];

        // ---- System health ----
        $health = [
            'last_webhook_at' => optional(WebhookEvent::latest('received_at')->first())->received_at,
            'sig_ok_today'    => WebhookEvent::where('signature_valid', true)->where('received_at', '>=', $today)->count(),
            'sig_bad_today'   => WebhookEvent::where('signature_valid', false)->where('received_at', '>=', $today)->count(),
            'webhooks_today'  => WebhookEvent::where('received_at', '>=', $today)->count(),
        ];

        $recentPayments = Payment::latest('charged_at')->take(8)->get();
        $recentWebhooks = WebhookEvent::latest('received_at')->take(8)->get();

        return view('admin.dashboard', compact('cards', 'latest', 'total', 'totalToday', 'kpis', 'health', 'recentPayments', 'recentWebhooks'));
    }

    /**
     * Full payments list (money rows) with type tabs, filters and totals.
     */
    public function payments(Request $request)
    {
        $query = Payment::query();

        if ($term = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($term) {
                foreach (['customer_name', 'customer_email', 'invoice_number', 'transaction_id'] as $col) {
                    $q->orWhere($col, 'like', "%{$term}%");
                }
            });
        }
        if (($type = $request->query('type')) && in_array($type, ['initial', 'recurring', 'refund', 'void', 'auth_only'], true)) {
            $query->where('type', $type);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('charged_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('charged_at', '<=', $to);
        }

        $paginator = (clone $query)->latest('charged_at')->paginate(25)->withQueryString();

        // Summary tiles for the current filter
        $all      = (clone $query)->get();
        $summary  = [
            'count'   => $all->count(),
            'gross'   => (float) $all->whereIn('type', ['initial', 'recurring'])->where('status', 'captured')->sum('amount'),
            'refunds' => (float) $all->whereIn('type', ['refund', 'void'])->sum('amount'),
        ];
        $summary['net'] = $summary['gross'] - $summary['refunds'];

        return view('admin.payments', [
            'paginator' => $paginator,
            'summary'   => $summary,
            'q'         => $request->query('q'),
            'type'      => $type ?? '',
            'from'      => $from,
            'to'        => $to,
            'tabs'      => [
                'all'       => Payment::count(),
                'initial'   => Payment::where('type', 'initial')->count(),
                'recurring' => Payment::where('type', 'recurring')->count(),
                'refund'    => Payment::where('type', 'refund')->count(),
                'void'      => Payment::where('type', 'void')->count(),
            ],
        ]);
    }

    /**
     * Webhook event log with filters.
     */
    public function webhooks(Request $request)
    {
        $query = WebhookEvent::query();

        if ($term = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($term) {
                foreach (['customer_email', 'description', 'entity_id', 'invoice_number', 'event_type'] as $col) {
                    $q->orWhere($col, 'like', "%{$term}%");
                }
            });
        }
        if ($eventType = $request->query('event_type')) {
            $query->where('event_type', $eventType);
        }
        if (($sig = $request->query('sig')) !== null && $sig !== '') {
            if ($sig === 'ok')      $query->where('signature_valid', true);
            if ($sig === 'invalid') $query->where('signature_valid', false);
            if ($sig === 'unverified') $query->whereNull('signature_valid');
        }

        $paginator = $query->latest('received_at')->paginate(25)->withQueryString();

        $today = now()->startOfDay();

        return view('admin.webhooks', [
            'paginator'  => $paginator,
            'q'          => $request->query('q'),
            'eventType'  => $eventType,
            'sig'        => $request->query('sig'),
            'eventTypes' => WebhookEvent::selectRaw('event_type, count(*) as c')->groupBy('event_type')->orderByDesc('c')->pluck('c', 'event_type'),
            'stats'      => [
                'today'    => WebhookEvent::where('received_at', '>=', $today)->count(),
                'hour'     => WebhookEvent::where('received_at', '>=', now()->subHour())->count(),
                'week'     => WebhookEvent::where('received_at', '>=', now()->subDays(7))->count(),
                'sig_ok'   => WebhookEvent::where('signature_valid', true)->count(),
                'sig_bad'  => WebhookEvent::where('signature_valid', false)->count(),
                'last'     => optional(WebhookEvent::latest('received_at')->first())->received_at,
            ],
        ]);
    }

    public function webhookDetail(int $id)
    {
        $event = WebhookEvent::findOrFail($id);

        return view('admin.webhook-detail', [
            'event'    => $event,
            'enrollment' => $event->enrollment,
        ]);
    }

    /**
     * Declines & failed/held transactions — recovery surface.
     */
    public function declines(Request $request)
    {
        $paginator = WebhookEvent::where(function ($q) {
                $q->where('event_type', 'net.authorize.payment.fraud.declined')
                  ->orWhereIn('response_code', ['2', '3', '4']);
            })
            ->latest('received_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.declines', [
            'paginator' => $paginator,
            'total'     => $paginator->total(),
        ]);
    }

    /**
     * Combined "All Leads" feed across every type.
     */
    public function all(Request $request)
    {
        $items = collect();

        foreach ($this->types() as $slug => $meta) {
            $model = $meta['model'];
            $model::latest()->take(50)->get()->each(function ($r) use (&$items, $meta, $slug) {
                $row = $this->row($meta, $r);
                $row['type']      = $slug;
                $row['type_label'] = $meta['singular'];
                $items->push($row);
            });
        }

        $rows = $items->sortByDesc('created_at')->values();

        return view('admin.all', [
            'rows'  => $rows,
            'total' => $rows->count(),
        ]);
    }

    /**
     * List view for a single type (with search + status filter).
     */
    public function list(string $type, Request $request)
    {
        $meta  = $this->resolveType($type);
        $model = $meta['model'];

        $query = $model::query();

        if ($term = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($term, $model) {
                foreach ((new $model)->getFillable() as $col) {
                    $q->orWhere($col, 'like', "%{$term}%");
                }
            });
        }

        if (($status = $request->query('status')) && in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        $records = $query->latest()->paginate(20)->withQueryString();
        $rows = $records->getCollection()->map(fn ($r) => $this->row($meta, $r));

        return view('admin.list', [
            'meta'     => $meta,
            'rows'     => $rows,
            'paginator' => $records,
            'total'    => $model::count(),
            'q'        => $request->query('q'),
            'status'   => $request->query('status'),
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * Detail view for a single record.
     */
    public function show(string $type, int $id)
    {
        $meta   = $this->resolveType($type);
        $model  = $meta['model'];
        $record = $model::findOrFail($id);

        $sections = [];
        foreach ($meta['sections'] as $sectionLabel => $fields) {
            $rows = [];
            foreach ($fields as $field => $fieldLabel) {
                $rows[$fieldLabel] = $this->displayValue($record->{$field});
            }
            $sections[$sectionLabel] = $rows;
        }

        return view('admin.show', [
            'meta'     => $meta,
            'record'   => $record,
            'title'    => ($meta['name'])($record),
            'sections' => $sections,
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * Update a record's follow-up status.
     */
    public function updateStatus(string $type, int $id, Request $request)
    {
        $meta   = $this->resolveType($type);
        $model  = $meta['model'];
        $record = $model::findOrFail($id);

        $status = $request->input('status');
        if (in_array($status, self::STATUSES, true)) {
            $record->update(['status' => $status]);
        }

        return back();
    }

    private function displayValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    }
}
