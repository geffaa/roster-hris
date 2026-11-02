# PRD — Roster (HRIS multi-negara)

Status: draft · Tanggal: 2026-10-31 · Sumber: ~/Notes/clients/portfolio-roadmap N-036, N-041; breakdown v1 (docs/.breakdown-v1.md)

## 1. Ringkasan
Roster adalah HRIS web multi-company dan multi-negara untuk perusahaan 50–500 karyawan yang ingin membangun atau mengganti HRIS internal. Intinya adalah **payroll berbasis rule pack**: aturan pajak, iuran jaminan sosial, cuti, dan lembur per negara disimpan sebagai data berversi dengan tanggal berlaku, bukan dikode keras. Indonesia menjadi paket aturan lengkap pertama (PPh 21 TER, BPJS, THR); paket Generic membuktikan engine-nya netral negara; UK adalah kandidat paket nyata kedua. Admin memakai dashboard web, karyawan memakai PWA. Proyek ini dipajang di Upwork sebagai bukti kemampuan membangun HRMS end-to-end, bukan produk untuk pasar Indonesia.

## 2. Masalah
- HRIS internal sering menulis logika payroll dengan percabangan per negara yang tersebar di kode. Menambah negara atau mengubah tarif berarti mengubah dan men-deploy ulang kode.
- Alternatif: Gadjian, Talenta/Mekari, KaryaOne (lengkap, tapi terkunci ke Indonesia); BambooHR, Gusto, Deel, Rippling, Personio (global, biaya per karyawan, kustomisasi terbatas); Odoo HR (modular, lokalisasi payroll bergantung modul per negara). Klien Upwork yang meminta "custom HRMS" biasanya sudah menolak opsi di atas karena butuh alur khusus, kontrol data, atau integrasi internal.
- Portfolio HRIS freelancer umumnya berhenti di CRUD karyawan + absensi sederhana, payroll satu negara dengan angka hardcode, tanpa audit log dan pemisahan peran.

## 3. Tujuan & metrik sukses
**Tujuan**
1. Membuktikan kemampuan membangun HRMS lengkap (karyawan, absensi, cuti, payroll, self-service, laporan) dengan kualitas produksi.
2. Menunjukkan desain engine yang netral negara: menambah negara = menambah data + (bila perlu) satu strategi pajak.
3. Menjadi bahan proposal untuk lowongan HRMS/payroll/attendance di Upwork.

**Metrik**
- Teknis: minimal 10 tes golden payroll Indonesia + 5 Generic lulus; tes isolasi multi-company lulus untuk setiap endpoint; demo berjalan dengan 2 perusahaan dan ±40 karyawan fiktif.
- Pasar: jumlah balasan/undangan dari proposal yang menyertakan Roster dalam 4 minggu setelah dipajang.

**Non-goal:** menyetor pajak/BPJS, e-filing, membayar gaji, nasihat pajak/hukum, dijual ke pasar HRIS Indonesia.

## 4. Persona & konteks

| Peran | Siapa & situasi | Pekerjaan utama | Frustrasi |
|---|---|---|---|
| HR Admin | Staf HR di kantor, desktop, setiap hari | Mengelola data karyawan, kebijakan cuti, menyetujui payroll | Data tersebar di spreadsheet; perubahan kebijakan tidak tercatat |
| Payroll Officer | Finance/HR, desktop, intens di akhir bulan | Menjalankan, meninjau, dan mengunci payroll | Perhitungan sulit ditelusuri; tarif berubah tiap tahun |
| Manager | Kepala tim, sering di ponsel | Menyetujui cuti dan koreksi absensi | Notifikasi persetujuan tenggelam di chat |
| Employee | Karyawan, ponsel, beberapa kali sehari | Clock-in/out, ajukan cuti, lihat slip gaji | Harus minta slip ke HR; tidak tahu sisa cuti |
| Auditor | Finance/eksternal, desktop, berkala | Membaca laporan dan jejak perubahan | Tidak ada jejak siapa mengubah apa |
| Super Admin | Pemilik sistem/IT | Mengelola perusahaan, pengguna, rule pack | Menambah negara butuh developer |

## 5. Asumsi

| # | Asumsi | Risiko jika salah |
|---|---|---|
| A1 | Produk portfolio (demo + repo), bukan SaaS berbayar: tanpa billing, onboarding publik, atau landing page pemasaran | Rendah |
| A2 | Laravel + PostgreSQL + Inertia (Vue 3); Laravel paling sering muncul di penawaran HRIS Upwork dan adalah keahlian utama | Sedang |
| A3 | Satu database, isolasi per perusahaan dengan `company_id` + global scope | Sedang (wajib dites) |
| A4 | Paket aturan MVP: Indonesia (lengkap) + Generic. Paket nyata kedua: UK sebagai kandidat (dipilih karena mudah dibuktikan, bukan data permintaan); diputuskan setelah M3. Filipina/Singapura masuk "Nanti" | Rendah |
| A5 | Semua angka regulasi adalah titik awal, disimpan sebagai data berversi dengan sumber dan tanggal verifikasi, dan wajib diverifikasi ke sumber resmi sebelum demo | **Tinggi** |
| A6 | Demo memakai data fiktif sepenuhnya | Rendah |
| A7 | Absensi MVP: clock-in/out web dengan geofence (lat/lng + radius) dan foto opsional. Integrasi biometrik masuk "Nanti" | Sedang |
| A8 | Satu mata uang dasar per perusahaan; kurs konsolidasi diinput manual | Rendah |
| A9 | Output payroll: slip PDF + berkas transfer CSV; pembayaran nyata di luar scope | Rendah |
| A10 | Asisten AI HR bukan MVP; milestone 5 opsional sebagai pembeda | Rendah |
| A11 | Dashboard web untuk admin/HR + PWA untuk karyawan. Tanpa clock-in offline; waktu selalu dari server. Push iOS hanya setelah PWA di-install. Native hanya jika ada klien yang meminta | Sedang |
| A12 | Angka regulasi diubah lewat editor rule pack di UI (versi, tanggal berlaku, audit, persetujuan dua orang). Metode perhitungan baru tetap berupa kode (strategi pajak) | Sedang |

## 6. Scope
> **Cakupan lengkap per fase ada di [FEATURE-MAP.md](FEATURE-MAP.md)** (diperluas 2026-10-31: org chart, dashboard HR, lembur, reimbursement, mesin persetujuan, impor karyawan masuk MVP; rekrutmen, kinerja, shift, pinjaman di P2).

**MVP**
- F1 Multi-company, RBAC, audit log, 2FA admin
- F2 Data karyawan (profil, kontrak, riwayat jabatan/gaji efektif-tanggal, dokumen)
- F3 Absensi (clock-in/out, shift, geofence, koreksi berpersetujuan)
- F4 Cuti dan izin (jenis per paket negara, saldo/akrual, persetujuan, kalender libur)
- F5 Payroll engine berbasis rule pack (Indonesia + Generic), editor rule pack berversi, run bulanan dengan persetujuan dua orang, slip PDF, berkas transfer
- F6 Self-service karyawan (PWA)
- F7 Laporan (rekap payroll, daftar iuran, absensi, saldo cuti, ekspor)

**Nanti:** rekrutmen/ATS, onboarding/offboarding, kinerja/OKR, reimbursement & pinjaman, lembur dengan alur persetujuan penuh (MVP: input HR), integrasi biometrik dan akuntansi, paket negara tambahan, asisten AI HR (M5), aplikasi native.

**Bukan tujuan:** membayar gaji, menyetor pajak/BPJS, e-filing, nasihat pajak/hukum, menjual ke pasar Indonesia.

## 7. User stories
- F1: Sebagai Super Admin, saya ingin menambah perusahaan dan memilih negaranya, supaya rule pack yang benar terpasang otomatis. Sebagai Auditor, saya ingin melihat siapa mengubah gaji dan kapan, supaya perubahan bisa dipertanggungjawabkan.
- F2: Sebagai HR Admin, saya ingin mencatat kenaikan gaji dengan tanggal efektif di masa depan, supaya payroll memakai angka yang benar tanpa saya ingat-ingat.
- F3: Sebagai karyawan, saya ingin clock-in dari ponsel saat tiba di kantor, supaya kehadiran tercatat tanpa mesin absen. Sebagai manager, saya ingin menyetujui koreksi absensi dari ponsel.
- F4: Sebagai karyawan, saya ingin melihat sisa cuti dan mengajukan cuti, supaya tidak perlu bertanya ke HR. Sebagai manager, saya ingin melihat kalender tim sebelum menyetujui.
- F5: Sebagai Payroll Officer, saya ingin melihat rincian perhitungan tiap karyawan dan selisih dari bulan lalu sebelum mengunci, supaya kesalahan tertangkap. Sebagai Super Admin, saya ingin memperbarui tarif BPJS lewat editor dengan tanggal berlaku, supaya tidak perlu deploy kode.
- F6: Sebagai karyawan, saya ingin mengunduh slip gaji saya, supaya tidak perlu meminta ke HR.
- F7: Sebagai Payroll Officer, saya ingin daftar iuran per komponen, supaya penyetoran mudah.

## 8. Fitur MVP secara detail

### F1. Multi-company, RBAC, audit log
Peran: Super Admin, HR Admin, Payroll Officer, Manager, Employee, Auditor. Satu pengguna bisa terhubung ke beberapa perusahaan dengan peran berbeda. Layar: S01, S12, S13.
- Ketika pengguna perusahaan A meminta data karyawan perusahaan B lewat URL/API, maka respons 404 dan percobaan tercatat.
- Ketika Payroll Officer mengubah gaji karyawan, maka audit log mencatat pengguna, waktu, nilai lama, dan nilai baru.
- Ketika Employee membuka halaman payroll admin, maka akses ditolak.
- Ketika peran admin login tanpa 2FA terpasang, maka diarahkan ke pemasangan TOTP.
- Edge case: pengguna di dua perusahaan dengan peran berbeda; peran dicabut saat sesi aktif; karyawan yang juga manager.

### F2. Data karyawan
Profil, kontrak (tetap/kontrak/magang/paruh waktu), riwayat jabatan dan gaji efektif-tanggal, struktur organisasi, dokumen dengan kedaluwarsa. Layar: S03, S04.
- Ketika HR mengubah gaji dengan tanggal efektif di masa depan, maka payroll periode itu memakai gaji lama sampai tanggal efektif lalu gaji baru (prorata).
- Ketika NIK/NPWP disimpan, maka kolom terenkripsi; hanya HR Admin/Payroll Officer melihat nilai penuh, peran lain melihat versi tersamar.
- Ketika karyawan resign, maka status berubah, akses login dicabut pada tanggal terakhir, dan riwayat tetap tersimpan.
- Edge case: NIK duplikat; atasan melingkar; karyawan pindah negara di tengah tahun (rule pack berubah).

### F3. Absensi
Clock-in/out via PWA, shift terjadwal, geofence per lokasi, ringkasan harian/bulanan, permintaan koreksi. Layar: S05, S06.
- Ketika karyawan clock-in di luar radius geofence, maka ditolak dengan pesan jarak, kecuali lokasi bertanda "remote diizinkan".
- Ketika clock-in melewati jam mulai shift + toleransi, maka ditandai terlambat dan menit keterlambatan dihitung.
- Ketika karyawan lupa clock-out, maka rekaman ditandai "tidak lengkap" dan permintaan koreksi muncul ke atasan.
- Ketika koreksi disetujui, maka rekaman asli tetap ada dan versi koreksi tercatat di audit log.
- Edge case: shift melewati tengah malam; zona waktu berbeda dari server; clock-in ganda dalam hitungan detik; hari libur; jam perangkat salah (selalu waktu server).

### F4. Cuti dan izin
Jenis cuti per rule pack, saldo dan akrual, pengajuan, persetujuan satu/dua tingkat, kalender tim, kalender libur per negara/wilayah. Layar: S07.
- Ketika karyawan mengajukan cuti melebihi saldo, maka ditolak kecuali jenis cuti mengizinkan saldo negatif.
- Ketika rentang cuti mencakup akhir pekan/libur, maka hanya hari kerja yang dipotong.
- Ketika cuti yang sudah disetujui dibatalkan setelah dimulai, maka saldo dikembalikan hanya untuk hari yang belum berjalan.
- Ketika karyawan Indonesia belum 12 bulan bekerja, maka cuti tahunan belum bisa diajukan (sesuai aturan paket, dapat dikonfigurasi).
- Edge case: masuk di tengah tahun (akrual prorata); setengah hari; atasan sedang cuti (delegasi); cuti lintas tahun.

### F5. Payroll engine berbasis rule pack
Run per perusahaan per periode: komponen gaji + absensi/cuti tak berbayar + lembur + rule pack. Rule pack berisi metode pajak, tabel tarif berversi, komponen iuran (persentase, pihak, batas upah), komponen berjadwal (THR), pengali lembur, batas upah minimum, jenis cuti. Alur: Draft → Hitung → Tinjau (selisih vs bulan lalu) → Setujui (dua orang) → Kunci → Terbitkan → Ekspor. Layar: S08, S09, S10.
- Ketika run dikunci, maka angka tidak berubah walau rule pack atau gaji diubah; koreksi lewat run penyesuaian.
- Ketika karyawan Indonesia dihitung Januari–November, maka pajak memakai TER kategori sesuai PTKP; ketika Desember, maka pajak setahun dihitung progresif Pasal 17 dan dikurangi pajak yang sudah dipotong.
- Ketika upah di atas batas JP/BPJS Kesehatan, maka dasar iuran dibatasi pada batas yang berlaku pada tanggal periode.
- Ketika karyawan masuk/keluar di tengah bulan, maka gaji dan iuran prorata sesuai kebijakan perusahaan (hari kalender/hari kerja).
- Ketika tarif berubah di tengah tahun lewat editor, maka periode sebelum tanggal berlaku tetap memakai versi lama.
- Ketika perubahan rule pack belum disetujui orang kedua, maka versi baru tidak aktif.
- Ketika dua orang berbeda belum menyetujui run, maka tombol Kunci nonaktif.
- Edge case: gaji bersih negatif; THR untuk masa kerja <12 bulan; kenaikan retroaktif; dua kontrak dalam satu bulan; aturan pembulatan; bonus yang menaikkan kategori TER.

### F6. Self-service karyawan (PWA)
Dasbor karyawan: clock-in, saldo & pengajuan cuti, riwayat absensi, slip gaji, ubah kontak, unggah dokumen. Bisa di-install ke layar utama. Layar: S05, S11.
- Ketika slip diterbitkan, maka karyawan mendapat notifikasi dan PDF hanya bisa diunduh pemiliknya.
- Ketika karyawan mengubah rekening bank, maka perubahan menunggu persetujuan HR sebelum berlaku di payroll berikutnya.
- Ketika jaringan putus saat clock-in, maka aplikasi menampilkan gagal dan tidak menyimpan rekaman lokal.

### F7. Laporan
Rekap payroll per departemen/perusahaan, daftar iuran per komponen, rekap absensi/keterlambatan, saldo cuti, ekspor CSV/XLSX. Layar: S14.
- Ketika total laporan payroll dibandingkan dengan jumlah slip periode yang sama, maka selisihnya nol (dites otomatis).

## 9. User flow
**Utama (payroll bulanan):**
1. Super Admin membuat perusahaan dan memilih negara; rule pack terpasang (S13).
2. HR Admin menambah departemen, lokasi + geofence (S04), dan karyawan (S03).
3. Karyawan clock-in/out (S05) dan mengajukan cuti (S11 → S07); manager menyetujui (S07, S06).
4. Akhir periode: Payroll Officer membuat run dan menghitung (S08).
5. Payroll Officer meninjau rincian per karyawan dan selisih (S09).
6. HR Admin menyetujui; run dikunci (S08).
7. Slip terbit (S10), berkas transfer dan daftar iuran diekspor (S14).

**Karyawan:** buka PWA (S11) → clock-in (S05) → ajukan cuti (S11) → lihat slip (S10).

**Perubahan tarif:** Super Admin membuka editor rule pack (S12) → membuat versi baru dengan tanggal berlaku → orang kedua menyetujui → versi aktif mulai tanggal itu.

**Menambah negara:** Super Admin menduplikasi paket Generic (S12) → mengubah tarif/iuran/cuti → mengaktifkan untuk perusahaan baru (S13).

## 10. Kebutuhan non-fungsional
- **Keamanan & privasi:** enkripsi kolom sensitif (NIK, NPWP, rekening, identitas lain); 2FA untuk peran admin; isolasi tenant; log akses data sensitif; rate limit login. Kerangka regulasi data pribadi: UU PDP No. 27/2022 (Indonesia), GDPR (UK/EU). Hak akses dan penghapusan data karyawan tersedia lewat HR Admin.
- **Performa:** run payroll 500 karyawan selesai < 60 detik lewat antrean; halaman daftar < 1 detik untuk 500 baris dengan paginasi.
- **Aksesibilitas:** WCAG 2.2 AA; PWA bisa dipakai satu tangan di 390px.
- **Lokalisasi:** UI dan slip dua bahasa (ID/EN); zona waktu per perusahaan; mata uang per perusahaan.
- **Keandalan:** run payroll idempoten; run terkunci tidak dapat diubah; versi rule pack tidak bisa dihapus.

## 11. Risiko & pertanyaan terbuka

| Risiko | Cara menguji termurah |
|---|---|
| **Angka regulasi salah** (TER, batas upah BPJS, pengali lembur berubah tiap tahun). Pencarian 2026-10-31 menemukan sumber tidak konsisten untuk batas upah JP, tarif JKK, dan batas BPJS Kesehatan | Simpan sebagai data berversi + sumber + tanggal verifikasi; verifikasi ke PMK 168/2023, DJP, peraturan BPJS terbaru, PP 35/2021, Permenaker 6/2016 sebelum demo; disclaimer "bukan nasihat pajak" |
| Perhitungan Desember (true-up) salah | Tulis tes golden dari contoh resmi DJP sebelum kode |
| Isolasi multi-company bocor | Tes otomatis untuk setiap endpoint dengan pengguna perusahaan lain |
| Data pribadi | Seluruh demo fiktif; enkripsi kolom |
| Scope membengkak | Daftar "Nanti" dijaga; tambahan hanya setelah M4 |
| Geofence tidak akurat di dalam gedung | Radius konfigurabel + foto opsional; batasan didokumentasikan |
| Klien ingin stack lain | Engine murni + rule pack mudah dijelaskan lintas stack |

**Pertanyaan terbuka:** negara paket nyata kedua (kandidat UK). Putuskan setelah M3 dengan mengumpulkan 15–20 lowongan HRMS/payroll Upwork (termasuk N-041) dan menghitung negara, stack, modul.

## 12. Distribusi (portfolio)
- Demo dengan akun per peran + data fiktif, repo GitHub, README dengan diagram rule pack, video 2 menit.
- Judul profil Upwork: "Multi-country HRIS with config-driven payroll (Laravel)". Kata kunci: HRMS, payroll, attendance, leave management.
- Template proposal: petakan kebutuhan klien ke modul, sorot rule pack dan audit.
- Lanjut bila: ada undangan/balasan dari proposal yang menyertakan Roster dalam 4 minggu.

## Lampiran: perbandingan kebutuhan Indonesia vs global
Kerangkanya sama (karyawan, absensi, cuti, payroll, laporan); isi aturannya berbeda. Itu alasan rule pack.

| Area | Indonesia | Contoh global | Implikasi desain |
|---|---|---|---|
| Pajak penghasilan | PPh 21: Jan–Nov TER kategori A/B/C sesuai PTKP (sejak 1 Jan 2024, PP 58/2023, PMK 168/2023); Desember dihitung ulang progresif Pasal 17 setahun dikurangi potongan Jan–Nov | AS: withholding federal + state (W-4), FICA. UK: PAYE + NI. Singapura: pemberi kerja umumnya tidak memotong. Filipina: tabel withholding progresif | Strategi pajak per metode; kolom "metode" di rule pack |
| Jaminan sosial | BPJS Kesehatan (4% pemberi kerja / 1% karyawan, ada batas upah); BPJS Ketenagakerjaan: JHT 3,7%/2%, JP 2%/1% (ada batas upah), JKK 0,24–1,74% (risiko), JKM 0,3%, JKP | Filipina: SSS, PhilHealth, Pag-IBIG. Singapura: CPF. India: PF, ESI. UK: NI + pensiun auto-enrolment. AS: FICA | Komponen iuran: persentase per pihak, batas bawah/atas, tanggal berlaku |
| Komponen wajib | THR: minimal 1 bulan upah untuk masa kerja ≥12 bulan, prorata di bawahnya, dibayar sebelum hari raya | Filipina: gaji ke-13; sebagian EU: gaji ke-13/14 atau uang liburan | Komponen berjadwal dengan aturan kelayakan |
| Cuti | Tahunan 12 hari kerja setelah 12 bulan; melahirkan 3 bulan; libur nasional + cuti bersama | UK: minimum 5,6 minggu termasuk libur. AS: tanpa minimum federal. EU: minimum 4 minggu. Filipina: 5 hari service incentive leave | Jenis cuti, akrual, carry-over, kalender per negara/wilayah |
| Jam kerja & lembur | 40 jam/minggu; lembur dengan pengali bertingkat (PP 35/2021) | AS FLSA: 1,5× di atas 40 jam untuk non-exempt. UK: tanpa pengali wajib | Tabel pengali lembur |
| Upah minimum | UMP/UMK per provinsi/kota | AS: federal + state. UK: National Living Wage berdasar umur | Validasi peringatan |
| Pelaporan | Bukti potong PPh 21, pelaporan BPJS | W-2/1099 (AS), RTI/P60 (UK), CPF (SG) | Ekspor data; e-filing di luar scope |
| Data pribadi | UU PDP No. 27/2022 | GDPR, CCPA, PDPA | Enkripsi, akses berperan, log akses, hak hapus |
| Identitas | NIK, NPWP, nomor BPJS | SSN, NI number, TIN | Tabel identitas fleksibel |

> Semua angka di lampiran ini perlu verifikasi ke sumber resmi (lihat Risiko).
