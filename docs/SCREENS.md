# Screens — Roster

| ID | Layar | Peran | Mode | Fitur | Mockup? |
|---|---|---|---|---|---|
| S01 | Login + 2FA | Semua | Operate | F1 | tidak |
| S02 | Dasbor HR (sesuai peran) | HR, Payroll, Manager | Operate | F1–F7 | **ya** |
| S03 | Karyawan: daftar & detail | HR Admin | Operate | F2 | tidak |
| S04 | Organisasi & lokasi (geofence) | HR Admin | Operate | F2, F3 | tidak |
| S05 | Clock-in (PWA) | Employee | Operate | F3, F6 | **ya** |
| S06 | Absensi & antrean koreksi | HR, Manager | Operate | F3 | tidak |
| S07 | Cuti: saldo, pengajuan, kalender tim, persetujuan | Semua | Operate | F4 | tidak |
| S08 | Payroll runs (daftar + status) | Payroll, HR | Operate | F5 | tidak |
| S09 | Tinjau run: rincian per karyawan | Payroll, HR | Operate | F5 | **ya** |
| S10 | Slip gaji | Employee, Payroll | Read | F5, F6 | tidak |
| S11 | Beranda karyawan (PWA) | Employee | Operate | F4, F6 | tidak |
| S12 | Editor rule pack | Super Admin | Operate | F5 | **ya** |
| S13 | Admin sistem & audit log | Super Admin, Auditor | Operate | F1 | tidak |
| S14 | Laporan | HR, Payroll, Auditor | Operate | F7 | tidak |

Mockup (diperbarui 2026-10-31 ke design system Base): S02 dashboard, S09, S12, S05. Tiga yang terakhir dipilih karena menunjukkan mekanisme khas Roster: rincian perhitungan yang bisa ditelusuri (S09), aturan negara sebagai data berversi (S12), dan pengalaman karyawan di ponsel (S05).

## S05 — Clock-in (PWA)
- **Tujuan:** mencatat kehadiran dalam satu ketukan dari lokasi kerja.
- **Konteks:** ponsel, berdiri di pintu kantor/lokasi, satu tangan, sering terburu-buru, cahaya luar ruang atau lobi.
- **Isi:** jam server sekarang, shift hari ini (09.00–18.00, toleransi 10 menit), status lokasi (di dalam radius "Kantor Jakarta – Kuningan", jarak 34 m), status hari ini (belum clock-in / masuk 08.52 / pulang), ringkasan minggu ini.
- **Aksi:** utama: Clock in / Clock out. Sekunder: ajukan koreksi, lihat riwayat.
- **State:** di luar radius (jarak 1,2 km, tombol nonaktif + penjelasan); izin lokasi ditolak; terlambat (masuk 09.17, 7 menit di luar toleransi); offline (gagal, tidak disimpan); sudah clock-out; hari libur (contoh: 25 Desember, tombol diganti keterangan libur).
- **Data contoh:** Dimas Pratama, Staff Gudang, PT Sinar Laut Logistik (fiktif).

## S09 — Tinjau run payroll
- **Tujuan:** memastikan setiap angka benar sebelum mengunci, dengan rincian yang bisa ditelusuri ke aturan dan versinya.
- **Konteks:** desktop, Payroll Officer, akhir bulan, fokus tinggi, sering membandingkan dengan bulan lalu.
- **Isi:** header run (PT Sinar Laut Logistik, Oktober 2026, 38 karyawan, status Calculated, persetujuan 0/2); total bruto/potongan/iuran pemberi kerja/neto dan selisih vs September; tabel karyawan (bruto, PPh 21, iuran karyawan, neto, selisih, penanda anomali); panel rincian karyawan terpilih: baris komponen (gaji pokok, tunjangan, lembur, potongan cuti tak berbayar), PPh 21 (kategori TER B, K/1, tarif x%), iuran BPJS per komponen dengan dasar terbatas batas upah, versi rule pack "ID 2026.2 berlaku 1 Jan 2026".
- **Aksi:** utama: Setujui (1/2). Sekunder: hitung ulang, ekspor, tandai untuk ditanyakan, buka karyawan.
- **State:** run belum dihitung; sedang menghitung (antrean, progres 21/38); anomali (neto naik 41% karena bonus); sudah terkunci (baca saja); gaji bersih negatif (error memblokir).
- **Data contoh:** angka IDR realistis; tarif yang tampil diberi catatan "contoh, perlu verifikasi".

## S12 — Editor rule pack
- **Tujuan:** mengubah tarif/batas/tabel negara sebagai versi baru berbatas tanggal, tanpa deploy kode, dengan persetujuan dua orang.
- **Konteks:** desktop, Super Admin, jarang (beberapa kali setahun), konsekuensi tinggi.
- **Isi:** paket "Indonesia" dengan daftar versi (2026.1 aktif s/d 31 Mar, 2026.2 aktif sejak 1 Apr, 2026.3 draft); bagian: metode pajak (TER bulanan + true-up Desember, terkunci di kode), tabel TER A/B/C, PTKP, iuran (JHT, JP, JKK, JKM, JKP, BPJS Kesehatan: tarif pemberi kerja/karyawan, batas bawah/atas), THR, pengali lembur, jenis cuti; diff versi draft vs aktif; kolom sumber + tanggal verifikasi per angka.
- **Aksi:** utama: Kirim untuk persetujuan. Sekunder: duplikasi versi, atur tanggal berlaku, bandingkan, batalkan draft.
- **State:** draft dengan perubahan belum disimpan; menunggu persetujuan (oleh orang lain); ditolak dengan alasan; angka tanpa sumber (peringatan); tanggal berlaku tumpang tindih (error).
- **Data contoh:** batas upah JP dengan catatan "sumber berbeda: 10.547.000 (BPJS TK) — perlu verifikasi".
