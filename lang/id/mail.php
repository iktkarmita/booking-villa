<?php

return [
    'open_booking' => 'Lihat booking',
    'open_panel' => 'Buka panel owner',

    'created' => [
        'subject' => 'Booking :code diterima - :tenant',
        'body' => "Halo :name,\n\nBooking Anda di :tenant sudah kami terima.\nKamar: :room\nTanggal: :dates\nTotal: :total\n\nMohon selesaikan pembayaran :amount sebelum :deadline agar kamar tidak dilepas.",
    ],
    'owner_created' => [
        'subject' => 'Booking baru :code',
        'body' => "Ada booking baru dari :name.\nKamar: :room\nTanggal: :dates\nTotal: :total\n\nMenunggu pembayaran.",
    ],
    'confirmed' => [
        'subject' => 'Booking :code terkonfirmasi - :tenant',
        'body' => "Halo :name,\n\nPembayaran :amount sudah kami terima. Booking Anda terkonfirmasi.\nKamar: :room\nTanggal: :dates\nSisa pembayaran: :remaining\n\nSampai jumpa!",
    ],
    'paid_more' => [
        'subject' => 'Pembayaran booking :code diterima',
        'body' => "Halo :name,\n\nPembayaran :amount untuk booking :code sudah kami terima.\nSisa pembayaran: :remaining",
    ],
    'owner_paid' => [
        'subject' => 'Pembayaran masuk untuk :code',
        'body' => "Pembayaran :amount diterima untuk booking :code (:name).\nSisa pembayaran: :remaining",
    ],
    'cancelled' => [
        'subject' => 'Booking :code dibatalkan',
        'body' => "Halo :name,\n\nBooking :code (:room, :dates) telah dibatalkan.\nBila ada pengembalian dana, Anda akan mendapat email terpisah.",
    ],
    'refund_requested' => [
        'subject' => 'Pengajuan pembatalan booking :code',
        'body' => "Tamu :name mengajukan pembatalan booking :code (:dates).\nAlasan: :reason\nRefund sesuai kebijakan: :suggested\n\nPutuskan di panel owner (menu Refund).",
    ],
    'refund_approved' => [
        'subject' => 'Pembatalan booking :code disetujui',
        'body' => "Halo :name,\n\nPembatalan booking :code disetujui. Refund :amount akan ditransfer owner ke rekening yang Anda isi.\n:note",
    ],
    'refund_approved_none' => [
        'subject' => 'Pembatalan booking :code disetujui',
        'body' => "Halo :name,\n\nPembatalan booking :code disetujui. Sesuai kebijakan pembatalan, tidak ada pengembalian dana.\n:note",
    ],
    'refund_rejected' => [
        'subject' => 'Pengajuan pembatalan booking :code ditolak',
        'body' => "Halo :name,\n\nPengajuan pembatalan booking :code belum dapat disetujui; booking Anda tetap berjalan.\n:note",
    ],
    'refund_paid' => [
        'subject' => 'Refund booking :code sudah ditransfer',
        'body' => "Halo :name,\n\nRefund :amount untuk booking :code sudah ditransfer oleh owner. Mohon cek rekening Anda.",
    ],
    'balance_reminder' => [
        'subject' => 'Pengingat pelunasan booking :code',
        'body' => "Halo :name,\n\nSisa pembayaran booking :code sebesar :remaining jatuh tempo :due.\nSilakan lunasi lewat halaman booking.",
    ],
];
