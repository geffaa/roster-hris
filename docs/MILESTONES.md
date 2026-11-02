# Milestones — Roster

Estimasi total: MVP inti (M1–M3) ±3 minggu paruh waktu; M1–M4 ±4 minggu; M5 opsional +3–4 hari. Payroll engine (M3) adalah bagian terberat.

## M1 — Fondasi: multi-company, auth, RBAC, karyawan (±3–4 hari)
- [x] Inisialisasi Laravel + Inertia/Vue + Vite + PostgreSQL; Pest, Pint, Larastan
- [x] Migrasi: companies, users, company_user, departments, locations, employees, employee_identifiers, contracts, compensation_history, documents
- [x] Trait `BelongsToCompany` + global scope + middleware perusahaan aktif
- [x] Tes isolasi lintas perusahaan untuk route karyawan (harus 404)
- [x] Peran & izin (spatie) + policy per model
- [x] 2FA TOTP wajib untuk peran admin
- [x] Audit log pada employees, compensation_history, employee_identifiers
- [x] Layar S03/S04: daftar & detail karyawan, riwayat gaji efektif-tanggal, masking identitas
- [x] Seeder fiktif: 2 perusahaan (Indonesia, Generic), ±40 karyawan
- **Fitur:** F1, F2
- **Verifikasi:** Pest lulus; pengguna perusahaan A tidak bisa membuka karyawan B; perubahan gaji tercatat di audit log; identitas tersamar untuk Manager.
- **Status 2026-10-31:** M1 selesai: org chart, impor CSV/XLSX dengan pratinjau per baris (impor diblokir bila ada error), dokumen karyawan (disk privat, kedaluwarsa, teraudit), semua halaman auth bergaya Roster. 62 tes Pest lulus di PostgreSQL, Larastan level 5 bersih, diuji di browser (1440 & 390 px).

## M2 — Absensi & cuti (±4–5 hari)
- [x] Migrasi work_patterns, attendance_records, attendance_corrections, holidays, leave_types, leave_balances, leave_requests
- [x] Clock-in/out dengan geofence (haversine, waktu server) — S05
- [x] Terlambat, status tidak lengkap, antrean koreksi berpersetujuan — S06
- [x] Jenis cuti dari rule pack, akrual prorata, masa tunggu, saldo, pengajuan + persetujuan + delegasi — S07
- [x] Kalender libur (Indonesia + Generic) dan kalender tim
- [x] Laporan absensi dan saldo cuti (dasar)
- **Fitur:** F3, F4
- **Verifikasi:** tes shift lewat tengah malam, clock-in di luar radius, cuti lintas akhir pekan, pembatalan cuti berjalan; uji manual di ponsel 390px.
- **Status 2026-10-31:** M2 selesai. Ditambah di luar daftar: lembur berpersetujuan, mesin persetujuan umum (delegasi atasan cuti, antrean HR, langkah kedua HR), jendela clock-in (2 jam sebelum shift sampai shift berakhir), papan absensi harian, kalender cuti bulanan, laporan + ekspor CSV. 98 tes Pest lulus, Larastan bersih, diuji di browser (1440 & 390 px). Belum: UI HR untuk mengelola work pattern dan jenis cuti (sementara lewat seeder; masuk M4 bersama pengaturan).

## M3 — Payroll engine + paket Indonesia + editor rule pack (±6–8 hari)
- [x] Migrasi rule_packs, rule_pack_versions, tax_tables, contributions, salary_components, employee_components, payroll_runs, payroll_items, payslips
- [x] Tulis tes golden lebih dulu dari contoh resmi (DJP/PMK 168/2023), lalu `PayrollCalculator` + DTO + strategi pajak
- [x] Data paket Indonesia: TER A/B/C, tarif Pasal 17, PTKP, iuran BPJS, THR, pengali lembur — tiap angka dengan `source_note` + tanggal verifikasi
- [x] True-up Desember, prorata masuk/keluar, segmen tarif tengah periode
- [x] Paket Generic (progresif + iuran persentase)
- [x] Editor rule pack (S12): versi baru dengan tanggal berlaku, persetujuan dua orang, versi tidak terhapus, tercatat di audit
- [x] Alur run (S08/S09): draft → hitung (antrean) → tinjau rincian & selisih → setujui dua orang → kunci → terbitkan
- [x] Slip PDF (S10) + berkas transfer CSV
- **Fitur:** F5
- **Verifikasi:** ≥10 golden Indonesia + ≥5 Generic lulus; tes tarif berubah tengah tahun; run terkunci tidak berubah setelah gaji/tarif diubah.
- **Status 2026-11-01:** M3 selesai.
  - **Hitung & tes:** mesin hitung murni (integer, tanpa DB/jam sistem), 14 golden Indonesia + 5 Generic, angka harapannya dihitung manual. Total 136 tes Pest lulus, Larastan bersih, detektor Impeccable bersih, diuji di browser (1440 & 390 px).
  - **Data paket:** TER A/B/C 44/40/41 baris dari Lampiran PP 58/2023. Batas JP Rp10.547.400 → Rp11.086.300 mulai Maret 2026 (dua versi, menguji perubahan tarif tengah tahun).
  - **Alur run:** run Jan–Sep terkunci di seeder; Oktober menunggu dua persetujuan. Ada contoh bonus, lembur, cuti tak berbayar, karyawan keluar dengan true-up, dan karyawan tanpa rekening.
  - **Slip & berkas:** slip di aplikasi + PDF, berkas transfer CSV (teraudit), CSV rekap iuran.
  - **Penyimpangan dari TECH-SPEC:** tabel tarif dan iuran disimpan di `rule_pack_versions.config` (jsonb), bukan tabel terpisah, supaya satu versi utuh bisa dibandingkan dan disetujui. Versi wajib mulai tanggal 1, sehingga tidak ada segmen tarif di tengah periode. Tabel TER hanya-baca di editor.
  - **Perlu verifikasi:** batas Rp12 juta BPJS Kesehatan 2026 (sumber berita saja), dan tarif JKK sesuai kelas risiko perusahaan.

## M4 — Self-service PWA, laporan, polesan (±3–4 hari)
- [x] Dasbor karyawan & manager (S02, S11), notifikasi email + dalam aplikasi
- [x] PWA: manifest, service worker (`vite-plugin-pwa`), ikon, install ke layar utama; tanpa clock-in offline
- [x] Web push untuk persetujuan & slip terbit (uji Android; iOS setelah install)
- [x] Persetujuan perubahan rekening bank
- [x] Laporan (S14): rekap payroll, daftar iuran, absensi, saldo cuti, ekspor XLSX
- [x] Halaman audit log (S13 bagian audit) dengan filter
- [x] Lokalisasi ID/EN untuk UI dan slip
- [x] README, diagram rule pack, tangkapan layar, video 2 menit
- **Fitur:** F6, F7 (+ penyempurnaan F1)
- **Verifikasi:** total laporan = total slip (otomatis); Playwright alur karyawan & run payroll; semua peran dijelajahi; kontras AA.
- **Status 2026-11-02:** M4 selesai, kecuali video 2 menit yang dipindah ke M5 supaya merekam versi final. 154 tes Pest dan 3 tes E2E Playwright lulus; Larastan bersih; diuji di browser (1440 & 390 px).
  - **Ditambah di luar daftar:** palet pencarian ⌘K, pengaturan pola kerja dan jenis cuti untuk HR, status "shift later", dan data demo yang mengikuti tanggal hari ini (bulan berjalan menunggu review, bulan sebelumnya terkunci).
  - **Penyimpangan dari TECH-SPEC:**
    - Service worker ditulis manual di `public/sw.js`, bukan memakai `vite-plugin-pwa`, karena lebih sederhana untuk aplikasi Laravel dan aturan "tanpa aksi offline" jadi eksplisit.
    - Lokalisasi mencakup aplikasi karyawan, pesan server, email, dan slip. Ruang kerja admin tetap berbahasa Inggris.
  - **Belum diuji:** web push di perangkat fisik Android/iOS. Alur langganan dan pengiriman sudah dites di level server.

## M5 — Asisten AI HR (opsional, ±3–4 hari)
- [x] Unggah dokumen kebijakan, potong teks + embedding (pgvector)
- [x] Tanya jawab kebijakan dengan kutipan; menolak di luar dokumen
- [x] Pertanyaan data pribadi ("sisa cuti saya") lewat tool terbatas pada akun login
- [x] Ringkasan anomali payroll untuk Payroll Officer
- **Verifikasi:** 20 pertanyaan uji berjawaban diketahui; tidak ada kebocoran antar-karyawan/antar-perusahaan.
- **Status 2026-11-02:** M5 selesai.
  - **Penyimpangan:** pencarian kebijakan memakai full-text search PostgreSQL (`simple`), bukan pgvector. Claude tidak menyediakan API embedding, dan dokumen kebijakan pendek; 20 dari 20 pertanyaan uji menemukan kebijakan yang benar di 3 hasil teratas.
  - **Data pribadi:** disusun server khusus untuk akun yang login, bukan lewat tool yang bisa dipanggil model, jadi kebocoran antar-karyawan tidak mungkin terjadi secara struktur (ada tes).
  - **Model:** `claude-opus-5-5` dengan `fallbacks: "default"`. Tanpa API key, asisten tampil dalam mode ekstraktif berlabel.
  - **Ditambah:** klaim reimbursement (dari FEATURE-MAP), video 2 menit (`docs/demo.mp4`), studi kasus (`docs/CASE-STUDY.md`).

## Tambahan dari FEATURE-MAP (2026-10-31)
Disisipkan ke milestone yang ada; estimasi MVP menjadi ±6 minggu paruh waktu.
- [x] M1: org chart (departemen, atasan) dan impor karyawan CSV/XLSX dengan validasi baris
- [x] M2: mesin persetujuan bertingkat (tabel approvals, aturan per jenis permintaan) + kotak masuk Approvals; dipakai cuti, koreksi absensi
- [x] M2: alur lembur (pengajuan → persetujuan → jam lembur tercatat)
- [x] M3: lembur dan reimbursement masuk komponen payroll; reimbursement (foto struk, kategori dengan batas, persetujuan)
- [x] M4: dashboard HR (headcount, kehadiran hari ini, cuti berjalan, persetujuan menunggu, status payroll, kontrak habis) dan PWA diperluas (klaim, lembur)
- **Verifikasi:** setiap jenis permintaan melewati mesin persetujuan yang sama (tes); lembur dan klaim yang disetujui muncul di run payroll berikutnya.

## Definisi selesai
Repo + README + demo (atau alasan ditunda) + tangkapan layar + studi kasus singkat + N-036 diperbarui.
- **Status 2026-11-02:** repo lokal (git) + README + tangkapan layar + video + studi kasus selesai. Demo online ditunda karena butuh server PHP 8.5 + PostgreSQL; repo GitHub publik menunggu user membuatnya (pembuatan repo publik diblokir pemeriksa keamanan Claude Code).
