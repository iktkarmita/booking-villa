<?php

return [
    'open_booking' => 'View booking',
    'open_panel' => 'Open owner panel',

    'created' => [
        'subject' => 'Booking :code received - :tenant',
        'body' => "Hello :name,\n\nWe have received your booking at :tenant.\nRoom: :room\nDates: :dates\nTotal: :total\n\nPlease complete the payment of :amount before :deadline so your room is not released.",
    ],
    'owner_created' => [
        'subject' => 'New booking :code',
        'body' => "New booking from :name.\nRoom: :room\nDates: :dates\nTotal: :total\n\nAwaiting payment.",
    ],
    'confirmed' => [
        'subject' => 'Booking :code confirmed - :tenant',
        'body' => "Hello :name,\n\nWe have received your payment of :amount. Your booking is confirmed.\nRoom: :room\nDates: :dates\nRemaining balance: :remaining\n\nSee you soon!",
    ],
    'paid_more' => [
        'subject' => 'Payment received for booking :code',
        'body' => "Hello :name,\n\nWe have received your payment of :amount for booking :code.\nRemaining balance: :remaining",
    ],
    'owner_paid' => [
        'subject' => 'Payment received for :code',
        'body' => "Payment of :amount received for booking :code (:name).\nRemaining balance: :remaining",
    ],
    'cancelled' => [
        'subject' => 'Booking :code cancelled',
        'body' => "Hello :name,\n\nBooking :code (:room, :dates) has been cancelled.\nIf a refund applies, you will receive a separate email.",
    ],
    'refund_requested' => [
        'subject' => 'Cancellation request for booking :code',
        'body' => "Guest :name requested to cancel booking :code (:dates).\nReason: :reason\nRefund per policy: :suggested\n\nPlease decide in the owner panel (Refunds menu).",
    ],
    'refund_approved' => [
        'subject' => 'Cancellation of booking :code approved',
        'body' => "Hello :name,\n\nThe cancellation of booking :code is approved. The owner will transfer a refund of :amount to the account you provided.\n:note",
    ],
    'refund_approved_none' => [
        'subject' => 'Cancellation of booking :code approved',
        'body' => "Hello :name,\n\nThe cancellation of booking :code is approved. Under the cancellation policy, no refund applies.\n:note",
    ],
    'refund_rejected' => [
        'subject' => 'Cancellation request for booking :code declined',
        'body' => "Hello :name,\n\nThe cancellation request for booking :code could not be approved; your booking stays active.\n:note",
    ],
    'refund_paid' => [
        'subject' => 'Refund for booking :code transferred',
        'body' => "Hello :name,\n\nThe owner has transferred your refund of :amount for booking :code. Please check your account.",
    ],
    'balance_reminder' => [
        'subject' => 'Balance reminder for booking :code',
        'body' => "Hello :name,\n\nThe remaining balance of :remaining for booking :code is due on :due.\nPlease pay it through your booking page.",
    ],
];
