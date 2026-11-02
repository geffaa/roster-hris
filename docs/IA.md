# Information architecture — Roster (sampai fase akhir)

Dibuat 2026-10-31. Tujuan: memutuskan struktur navigasi dan alur dari bentuk akhir produk (MVP → P2 → P3), bukan dari MVP saja. Sumber cakupan: docs/FEATURE-MAP.md + riset modul HRIS (Talenta, BambooHR, Personio, Rippling).

## 1. Peran dan permukaan

| Peran | Permukaan utama | Yang mereka lihat |
|---|---|---|
| Super Admin (pemilik sistem) | Desktop | Semua perusahaan, rule pack, pengaturan, audit |
| HR Admin | Desktop | Semua modul untuk perusahaannya |
| Payroll Officer / Finance | Desktop | Payroll, klaim, pinjaman, laporan keuangan, rule pack (baca) |
| Recruiter (P2) | Desktop | Rekrutmen, onboarding |
| Manager | Desktop + ponsel | Tim saya: persetujuan, absensi tim, cuti tim, review kinerja, 1:1 |
| Employee | Ponsel (PWA), sesekali desktop | Diri sendiri: absen, cuti, lembur, klaim, slip gaji, dokumen, tujuan/review, helpdesk |
| Auditor | Desktop | Baca saja: laporan, audit log |

Konsekuensi: ada **dua aplikasi dengan navigasi berbeda**:
1. **Workspace admin/manager (desktop)**: navigasi sesuai izin peran.
2. **Employee app (ponsel)**: bottom tab bar 4–5 item (Today, Time off, Pay, Me, + Inbox bila manager).

## 2. Peta modul akhir

| Grup | Modul | Fase | Halaman di dalamnya |
|---|---|---|---|
| — | Home | MVP | Beranda per peran (HR, manager, employee) |
| — | Inbox (approvals & tasks) | MVP | Semua persetujuan lintas modul, tugas onboarding, pengingat |
| People | Directory | MVP | Daftar, profil (tab: overview, job & pay, time off, attendance, documents, assets, goals, activity) |
| People | Org chart | MVP | Struktur, departemen, lokasi, cost center |
| People | Onboarding & offboarding | P2 | Checklist per karyawan, template, akses & aset, exit interview, final pay |
| Time | Attendance | MVP | Harian, koreksi, geofence/lokasi, validasi wajah (P2), mesin fingerprint (P3) |
| Time | Shifts & schedules | P2 | Kalender penjadwalan, rotasi, tukar shift |
| Time | Leave | MVP | Pengajuan, saldo, kalender tim, kebijakan per negara |
| Time | Overtime | MVP | Pengajuan, persetujuan, ke payroll |
| Pay | Payroll runs | MVP | Run bulanan, THR, penyesuaian, final pay (P2) |
| Pay | Claims (reimbursement) | MVP | Klaim, kategori & batas, persetujuan |
| Pay | Loans & advances | P2 | Pinjaman, cicilan potong gaji |
| Pay | Benefits | P2 | Paket per golongan, enrolment |
| Pay | Tax & statutory filings | P2 | Bukti potong tahunan, daftar iuran BPJS, ekspor e-filing |
| Pay | Rule packs | MVP | Per negara, versi, editor, persetujuan |
| Talent | Recruitment (ATS) | P2 | Lowongan, kandidat, pipeline, interview, offer |
| Talent | Performance | P2 | Siklus review, OKR/KPI, 1:1, feedback 360 (P3) |
| Talent | Learning | P3 | Katalog, sesi, sertifikat kedaluwarsa |
| Workplace | Assets | P3 | Inventaris, serah terima, pengembalian saat offboarding |
| Workplace | Documents & e-sign | P2 | Kontrak, surat, tanda tangan, kedaluwarsa |
| Workplace | Announcements | P2 | Pengumuman + konfirmasi baca |
| Workplace | Helpdesk | P3 | Tiket karyawan ke HR, SLA |
| Insights | Reports | MVP | Headcount, turnover, absensi, cuti, payroll, iuran, custom (P3) |
| Insights | AI assistant | MVP opsional / P2 | Tanya kebijakan, anomali payroll |
| Admin | Settings | MVP | Perusahaan, lokasi, pola kerja, kalender libur, jenis cuti, komponen gaji, persetujuan, notifikasi, integrasi, API & webhook (P2) |
| Admin | Users & roles | MVP | Pengguna, peran, matriks izin |
| Admin | Audit log | MVP | Jejak perubahan & akses data sensitif |

Jumlah area tingkat atas di fase akhir: **Home, Inbox + 6 grup berisi 18 modul + Admin**. Tidak muat di navigasi atas.

## 3. Alur end-to-end (fase akhir)

1. **Hire to onboard (P2):** lowongan → kandidat → interview → offer ditandatangani (e-sign) → profil karyawan dibuat otomatis → checklist onboarding (dokumen, rekening, NPWP, aset, akun) → hari pertama: aplikasi karyawan aktif, geofence lokasi → probation review terjadwal.
2. **Attend to pay (MVP):** clock-in harian → koreksi & lembur disetujui → cuti tak berbayar tercatat → klaim disetujui → run payroll mengambil semua data periode → tinjau selisih & isu → dua persetujuan → kunci → slip terkirim → berkas bank → pembayaran → laporan iuran BPJS & PPh.
3. **Request to approve (MVP):** karyawan mengajukan (cuti/lembur/klaim/koreksi/perubahan data) → mesin persetujuan bertingkat → manager menyetujui dari Inbox (desktop atau ponsel) → delegasi bila cuti → notifikasi → dampak ke saldo/payroll.
4. **Month end (MVP):** cut-off absensi → kunci periode absensi → run payroll → approve → bayar → tutup periode.
5. **Year end (P2):** true-up PPh Desember → bukti potong tahunan → arsip → reset saldo cuti + carry-over sesuai kebijakan.
6. **Rate change (MVP):** regulasi berubah → draft versi rule pack → sumber dilampirkan → persetujuan kedua → aktif pada tanggal berlaku → payroll berikutnya otomatis memakai versi baru.
7. **Perform & grow (P2–P3):** siklus review dibuka → self-review → manager review → kalibrasi → hasil → kenaikan gaji efektif-tanggal masuk payroll; training & sertifikat.
8. **Offboard to final pay (P2):** resign diajukan → persetujuan → checklist offboarding (aset kembali, akses dicabut, exit interview) → final pay (prorata, sisa cuti, THR prorata bila berlaku) → akun dinonaktifkan pada tanggal terakhir → data disimpan sesuai kebijakan retensi.

## 4. Keputusan navigasi

### Trade-off
| Kriteria | Navigasi atas | Sidebar |
|---|---|---|
| Jumlah area fase akhir (8 grup, 18+ modul) | Tidak muat; butuh mega-menu atau "More" yang menyembunyikan modul | Muat dengan grup berlabel, bisa dilipat |
| Navigasi berbasis peran (manager melihat 5 item, HR 20) | Lebar menu berubah-ubah per peran, terasa tidak stabil | Daftar memendek dengan wajar |
| Lebar konten untuk tabel payroll | Unggul (+248px) | Dapat diatasi dengan sidebar yang bisa diciutkan ke rail ikon (64px) |
| Penanda lokasi (di mana saya) | Satu level saja | Grup + modul + tab halaman: tiga level jelas |
| Badge hitungan (Inbox 7, Claims 3) | Sempit | Alami di tiap item |
| Pindah perusahaan (multi-company) | Ada di kanan atas | Di kepala sidebar, dekat konteks |
| Familiar bagi HR | Ya untuk app kecil | Ya: Talenta, BambooHR, Personio, Rippling semua memakai sidebar |

### Keputusan
**Sidebar untuk workspace desktop**, karena bentuk akhir Roster punya 18+ modul dalam 8 grup dan navigasi yang berbeda per peran. Navigasi atas hanya tepat untuk MVP dan akan dibongkar di P2. Agar karakter tenang dan lebar konten tetap terjaga:
- Sidebar terang (bukan gelap), grup berlabel, modul fase berikutnya muncul otomatis saat diaktifkan.
- Bisa diciutkan menjadi rail ikon 64px (diingat per pengguna); otomatis diciutkan di halaman lebar seperti payroll run bila layar < 1440px.
- Di dalam modul: header halaman + tab (mis. Payroll: Runs · Claims · Loans · Benefits · Filings).
- Inbox persetujuan sebagai item kedua dengan hitungan.
- Pemilih perusahaan di kepala sidebar.

**Employee app (ponsel):** bottom tab bar Today · Time off · Pay · Me; manager mendapat tab Inbox tambahan. Fitur P2–P3 untuk karyawan (dokumen, tujuan, helpdesk, pengumuman) masuk lewat tab Me dan kartu di Today, sehingga tab bar tidak bertambah.

### Struktur sidebar akhir
```
[Company switcher]
Home
Inbox                         7
People      Directory · Org chart · Onboarding (P2)
Time        Attendance · Shifts (P2) · Leave · Overtime
Pay         Payroll · Claims · Loans (P2) · Benefits (P2) · Filings (P2) · Rule packs
Talent (P2) Recruitment · Performance · Learning (P3)
Workplace   Documents (P2) · Announcements (P2) · Assets (P3) · Helpdesk (P3)
Insights    Reports · Assistant
──────────
Settings · Users & roles · Audit log
[User]
```
Manager melihat: Home, Inbox, My team (attendance, leave, reviews), Reports tim. Employee di desktop melihat versi web dari aplikasi ponsel.
