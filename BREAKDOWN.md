# Roster — HRIS multi-negara (ringkasan)

HRIS web multi-company dan multi-negara untuk perusahaan 50–500 karyawan. Payroll digerakkan oleh **rule pack** berversi per negara (pajak, iuran, THR, cuti, lembur) yang diubah lewat editor dengan tanggal berlaku dan persetujuan dua orang, bukan lewat kode. Indonesia jadi paket lengkap pertama, Generic membuktikan engine netral negara, UK kandidat paket kedua. Admin memakai dashboard web, karyawan memakai PWA. Proyek portfolio Upwork (N-036).

## Dokumen
| File | Isi |
|---|---|
| [docs/PRD.md](docs/PRD.md) | Masalah, tujuan, persona, asumsi, scope, user stories, fitur + acceptance criteria, flow, NFR, risiko, lampiran Indonesia vs global |
| [docs/TECH-SPEC.md](docs/TECH-SPEC.md) | Arsitektur, data model, route, PayrollCalculator, integrasi, keamanan, tes |
| [docs/SCREENS.md](docs/SCREENS.md) | 14 layar dengan mode, isi, aksi, state; 3 layar mockup |
| [docs/MILESTONES.md](docs/MILESTONES.md) | M1–M5 dengan checklist dan verifikasi |
| [PRODUCT.md](PRODUCT.md) | Product record untuk Impeccable |
| [DESIGN.md](DESIGN.md) | Design system Roster v2: tenang, sidebar akordeon (dari IA fase akhir), Source Sans 3, komponen custom |
| [docs/IA.md](docs/IA.md) | Information architecture fase akhir: peran, 18+ modul, alur end-to-end, keputusan navigasi |
| [docs/FEATURE-MAP.md](docs/FEATURE-MAP.md) | Peta fitur lengkap per fase (MVP / P2 / P3) |
| [mockups/](mockups/) | Home HR, review payroll, rule pack, profil karyawan, komponen, app karyawan (absen, slip gaji) |
| [docs/.breakdown-v1.md](docs/.breakdown-v1.md) | Versi breakdown pertama (arsip) |

## Fitur MVP
F1 multi-company/RBAC/audit/2FA · F2 karyawan · F3 absensi geofence · F4 cuti · F5 payroll engine + editor rule pack · F6 self-service PWA · F7 laporan

## Asumsi paling berisiko
- **A5** Angka regulasi belum terverifikasi; sumber tidak konsisten untuk batas upah JP, tarif JKK, batas BPJS Kesehatan.
- A3 Isolasi tenant dengan satu database wajib dites di setiap endpoint.
- A4 UK sebagai paket kedua belum berdasar data permintaan.

## Status
Dokumen produk & teknis: selesai (draft). Desain: Roster v2 (2026-10-31) — design system sendiri, navigasi dari IA fase akhir, ikon Lucide asli, komponen form custom, 7 halaman mockup diaudit; versi lama di mockups/_archive-*. Kode: belum dimulai.
