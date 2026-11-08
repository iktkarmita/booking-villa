<?php

return [
    'nav' => 'Invoices',
    'platform_nav' => 'Subscription invoices',
    'model' => 'Invoice',
    'pay_instructions' => 'Transfer to: :bank. Put the invoice number in the transfer note; your plan is activated once an admin confirms the payment.',
    'fields' => [
        'number' => 'Invoice no.',
        'tenant' => 'Tenant',
        'amount' => 'Amount',
        'status' => 'Status',
        'period' => 'Period',
        'due_date' => 'Due date',
        'paid_at' => 'Paid',
        'note' => 'Note',
    ],
    'status' => ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'void' => 'Void'],
    'actions' => [
        'mark_paid' => 'Mark paid',
        'void' => 'Void',
        'suspend' => 'Suspend',
        'activate' => 'Activate',
        'done' => 'Done.',
    ],
    'errors' => ['not_payable' => 'This invoice cannot be paid.'],
    'mail' => [
        'open_panel' => 'Open owner panel',
        'invoice_created' => [
            'subject' => 'Subscription invoice :number - :tenant',
            'body' => "New subscription invoice for :tenant.\nInvoice: :number\nPeriod: :period\nAmount: :amount\nDue: :due\n\nTransfer to: :bank",
        ],
        'invoice_reminder' => [
            'subject' => 'Reminder: invoice :number',
            'body' => "Invoice :number for :amount is due :due.\nTransfer to: :bank\n\nIf unpaid, your villa service may be suspended after the grace period.",
        ],
        'invoice_paid' => [
            'subject' => 'Payment received for invoice :number',
            'body' => "Thank you. Payment of :amount for :period is received and the :tenant subscription is active.",
        ],
        'suspended' => [
            'subject' => ':tenant service suspended',
            'body' => "The :tenant subscription was not renewed before the grace period ended, so the booking site is temporarily closed.\nYour data is safe. Pay the open invoice to reactivate.",
        ],
    ],
];
