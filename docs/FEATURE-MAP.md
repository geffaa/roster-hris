# Feature map — Roster (lengkap, bertahap)

Dibuat 2026-10-31 karena MVP awal terlalu sempit untuk HRIS "lengkap" yang diminta klien Upwork. Peta ini mengikuti modul yang umum di HRIS komersial (Talenta, BambooHR, Personio, Rippling) dan menandai mana yang masuk MVP.

Legenda fase: **MVP** = M1–M5 di MILESTONES.md · **P2** = fase kedua · **P3** = nanti/opsional.

| Area | Fitur | Fase |
|---|---|---|
| Core HR | Data karyawan, kontrak, riwayat jabatan & gaji efektif-tanggal, dokumen | MVP |
| | **Struktur organisasi + org chart**, departemen, lokasi, cost center | MVP (org chart ditambahkan) |
| | Custom field karyawan | P2 |
| | **Dashboard HR**: headcount, turnover, kehadiran hari ini, cuti berjalan, kontrak habis, payroll berikutnya | MVP (ditambahkan) |
| | Pengumuman perusahaan + konfirmasi baca | P2 |
| Kehadiran | Clock-in PWA dengan geofence, foto opsional | MVP |
| | **Jadwal shift & rotasi** (kalender penjadwalan, tukar shift) | P2 |
| | Koreksi absensi berpersetujuan | MVP |
| | **Lembur: pengajuan → persetujuan → masuk payroll** | MVP (dinaikkan dari Nanti) |
| | Integrasi mesin fingerprint (impor CSV/API) | P3 |
| Cuti | Jenis cuti per negara, akrual, carry-over, saldo, kalender tim, libur nasional | MVP |
| | Delegasi persetujuan | MVP |
| Payroll | Engine rule pack berversi (Indonesia + Generic), editor tarif dengan persetujuan dua orang | MVP |
| | Run bulanan, tinjau selisih, kunci, slip PDF, berkas bank | MVP |
| | Run THR & run penyesuaian | MVP |
| | **Reimbursement/klaim biaya** (foto struk, batas per kategori, persetujuan, masuk payroll) | MVP (dinaikkan) |
| | Pinjaman/kasbon karyawan dengan cicilan potong gaji | P2 |
| | Benefit & tunjangan per golongan | P2 |
| | Bukti potong pajak tahunan & laporan iuran | P2 |
| | Paket negara nyata kedua (UK kandidat) | P2 |
| | Jurnal akuntansi (ekspor ke Xero/Jurnal/Accurate) | P3 |
| Persetujuan | **Mesin persetujuan bertingkat** yang dipakai semua modul (cuti, lembur, klaim, koreksi, perubahan data) + kotak masuk persetujuan | MVP (ditambahkan) |
| Rekrutmen | Lowongan, kandidat, pipeline tahap, jadwal interview, offer | P2 |
| | Onboarding/offboarding checklist (aset, akses, dokumen) | P2 |
| Kinerja | Siklus review (self, manager), OKR/KPI, 1:1 notes | P2 |
| | Feedback 360 | P3 |
| Pelatihan | Katalog training, kehadiran, sertifikat kedaluwarsa | P3 |
| Aset | Inventaris aset (laptop, kendaraan) per karyawan | P3 |
| Self-service | PWA: beranda, clock-in, cuti, lembur, klaim, slip gaji, dokumen, profil | MVP (diperluas) |
| Laporan | Laporan payroll, iuran, absensi, cuti, headcount; ekspor XLSX | MVP |
| | Builder laporan kustom | P3 |
| Platform | Multi-company & multi-negara, RBAC, 2FA, audit log | MVP |
| | Impor karyawan CSV/XLSX dengan validasi | MVP (ditambahkan) |
| | API + webhook (karyawan baru, payroll terkunci) | P2 |
| | Asisten AI kebijakan HR + ringkasan anomali payroll | MVP opsional (M5) |
| | Bahasa ID/EN | MVP |

## Dampak ke MVP
Ditambahkan ke MVP: org chart, dashboard HR, alur lembur, reimbursement, mesin persetujuan + kotak masuk, impor karyawan, PWA diperluas. Estimasi MVP naik dari ±4 minggu menjadi **±6 minggu paruh waktu** (payroll tetap bagian terberat). P2 ±3–4 minggu.
