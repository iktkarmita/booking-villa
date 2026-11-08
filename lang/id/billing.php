<?php

return [
    'nav' => 'Tagihan',
    'platform_nav' => 'Tagihan langganan',
    'model' => 'Tagihan',
    'pay_instructions' => 'Transfer ke: :bank. Cantumkan nomor tagihan pada berita transfer; langganan aktif setelah pembayaran dikonfirmasi admin.',
    'fields' => [
        'number' => 'No. tagihan',
        'tenant' => 'Tenant',
        'amount' => 'Jumlah',
        'status' => 'Status',
        'period' => 'Periode',
        'due_date' => 'Jatuh tempo',
        'paid_at' => 'Dibayar',
        'note' => 'Catatan',
    ],
    'status' => ['unpaid' => 'Belum dibayar', 'paid' => 'Lunas', 'void' => 'Dibatalkan'],
    'actions' => [
        'mark_paid' => 'Tandai lunas',
        'void' => 'Batalkan',
        'suspend' => 'Tangguhkan',
        'activate' => 'Aktifkan',
        'done' => 'Berhasil diproses.',
    ],
    'errors' => ['not_payable' => 'Tagihan ini tidak dapat dibayar.'],
    'mail' => [
        'open_panel' => 'Buka panel owner',
        'invoice_created' => [
            'subject' => 'Tagihan langganan :number - :tenant',
            'body' => "Tagihan langganan baru untuk :tenant.\nNo. tagihan: :number\nPeriode: :period\nJumlah: :amount\nJatuh tempo: :due\n\nTransfer ke: :bank",
        ],
        'invoice_reminder' => [
            'subject' => 'Pengingat tagihan :number',
            'body' => "Tagihan :number sebesar :amount jatuh tempo :due.\nTransfer ke: :bank\n\nBila tidak dibayar, layanan villa Anda dapat ditangguhkan setelah masa tenggang.",
        ],
        'invoice_paid' => [
            'subject' => 'Pembayaran tagihan :number diterima',
            'body' => "Terima kasih. Pembayaran :amount untuk periode :period sudah kami terima dan langganan :tenant aktif.",
        ],
        'suspended' => [
            'subject' => 'Layanan :tenant ditangguhkan',
            'body' => "Langganan :tenant belum diperpanjang hingga masa tenggang berakhir, sehingga situs booking ditutup sementara.\nData Anda aman. Bayar tagihan terbuka untuk mengaktifkan kembali.",
        ],
    ],
];
