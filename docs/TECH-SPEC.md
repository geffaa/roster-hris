# Tech Spec — Roster

## 1. Arsitektur
```
[Dashboard admin (Inertia/Vue)]   [PWA karyawan (Inertia/Vue + service worker)]
              \                      /
               Laravel (HTTP, policies, Inertia)
               |-- Domain: Employees, Attendance, Leave, Payroll, RulePacks, Audit
               |-- PayrollCalculator (murni, tanpa DB)
               |-- Queue workers (run payroll, PDF, ekspor, notifikasi)
               PostgreSQL (JSONB untuk rule pack & rincian)   Redis (antrean/cache, opsional)
```
Alasan: Laravel adalah keahlian utama dan paling umum di penawaran HRIS Upwork; PostgreSQL karena JSONB cocok untuk rule pack dan rincian perhitungan.

## 2. Stack & paket
- Laravel (stabil terbaru), PHP 8.3+, PostgreSQL 16+, antrean database (Redis opsional).
- Inertia + Vue 3 + Vite; `vite-plugin-pwa` untuk PWA.
- `spatie/laravel-permission` (RBAC), `owen-it/laravel-auditing` (audit), `pragmarx/google2fa-laravel` atau Fortify 2FA (TOTP), `barryvdh/laravel-dompdf` (slip; Browsershot bila tipografi butuh), `maatwebsite/excel` (ekspor).
- Pest, Laravel Pint, Larastan.
- Styling mengikuti DESIGN.md (ditulis setelah mockup).

## 3. Data model
Semua tabel tenant memiliki `company_id` + global scope `BelongsToCompany`. Kolom *terenkripsi* memakai cast `encrypted`.

- **companies** (id, name, country_code char(2), base_currency char(3), timezone, locale, rule_pack_id, proration_policy enum[calendar_days|working_days])
- **users** (id, name, email unique, password, two_factor_secret *terenkripsi*, last_login_at)
- **company_user** (company_id, user_id, role) unique(company_id, user_id)
- **locations** (id, company_id, name, lat decimal(9,6), lng decimal(9,6), radius_m int, allow_remote bool)
- **departments** (id, company_id, name, parent_id null)
- **employees** (id, company_id, user_id null, employee_no unique per company, full_name, hire_date, end_date null, department_id, manager_id null, location_id, status enum[active|on_leave|resigned], personal_data jsonb *terenkripsi*)
- **employee_identifiers** (id, employee_id, type enum[nik|npwp|bpjs_kes|bpjs_tk|ssn|ni|tin|other], value *terenkripsi*, value_last4)
- **contracts** (id, employee_id, type enum[permanent|fixed_term|intern|part_time], start_date, end_date null, work_pattern_id)
- **compensation_history** (id, employee_id, effective_from date, base_salary decimal(14,2), currency, tax_status string (PTKP/filing status), reason) index(employee_id, effective_from)
- **salary_components** (id, company_id, code, name, kind enum[earning|deduction], taxable bool, recurring bool)
- **employee_components** (id, employee_id, component_id, amount decimal(14,2), effective_from, effective_to null)
- **work_patterns** (id, company_id, name, start_time, end_time, crosses_midnight bool, grace_min int, working_days jsonb)
- **attendance_records** (id, employee_id, work_date, clock_in_at timestamptz, clock_out_at null, in_lat, in_lng, out_lat, out_lng, photo_path null, status enum[ok|late|incomplete|corrected], late_min int) unique(employee_id, work_date, clock_in_at)
- **attendance_corrections** (id, attendance_record_id, requested_by, new_in, new_out, reason, status enum[pending|approved|rejected], decided_by null, decided_at null)
- **holidays** (id, country_code, region null, date, name, collective bool)
- **leave_types** (id, rule_pack_id, code, name, paid bool, accrual jsonb, carry_over jsonb, min_service_months int, allow_negative bool, requires_second_approval bool)
- **leave_balances** (id, employee_id, leave_type_id, year, accrued decimal(5,1), used decimal(5,1), carried_over decimal(5,1))
- **leave_requests** (id, employee_id, leave_type_id, start_date, end_date, half_day enum[none|am|pm], days decimal(5,1), status, approver_id, delegated_to null)
- **rule_packs** (id, country_code, name, tax_method enum[ter_monthly_with_december_true_up|progressive_annual|no_withholding], is_template bool)
- **rule_pack_versions** (id, rule_pack_id, version int, effective_from date, status enum[draft|pending_approval|active|superseded], config jsonb (PTKP, pengali lembur, aturan THR, pembulatan), source_note text, created_by, approved_by null) — versi tidak bisa dihapus
- **rule_pack_tax_tables** (id, rule_pack_version_id, table_code [ter_a|ter_b|ter_c|art17|progressive], rows jsonb)
- **rule_pack_contributions** (id, rule_pack_version_id, code [jht|jp|jkk|jkm|jkp|bpjs_kes|...], employer_rate decimal(6,4), employee_rate decimal(6,4), wage_floor null, wage_cap null)
- **payroll_runs** (id, company_id, period_start, period_end, type enum[regular|adjustment|thr], status enum[draft|calculated|approved_1|locked|published], calculated_at, approved_by_1, approved_by_2, locked_at)
- **payroll_items** (id, payroll_run_id, employee_id, gross, taxable_gross, tax, employee_contributions, employer_contributions, other_deductions, net, breakdown jsonb, rule_pack_version_id) unique(payroll_run_id, employee_id)
- **payslips** (id, payroll_item_id, pdf_path, published_at)
- **bank_account_changes** (id, employee_id, new_account *terenkripsi*, status, decided_by)
- **documents** (id, employee_id, type, path, expires_at null)
- **audit_logs** (dari owen-it, ditambah company_id)
- **notifications**, **push_subscriptions** (bawaan / web push)

## 4. API / route (Inertia + JSON untuk PWA)

| Method | Path | Peran | Fitur |
|---|---|---|---|
| GET/POST/PUT | /employees, /employees/{id} | HR Admin | F2 |
| POST | /attendance/clock | Employee | F3 |
| POST | /attendance/{id}/corrections, PUT /corrections/{id} | Employee / Manager | F3 |
| GET/POST | /leave-requests, PUT /leave-requests/{id}/decision | Employee / Manager | F4 |
| GET/POST | /rule-packs/{id}/versions, PUT /versions/{id}/approve | Super Admin | F5 |
| POST | /payroll-runs, POST /payroll-runs/{id}/calculate | Payroll Officer | F5 |
| PUT | /payroll-runs/{id}/approve, /lock, /publish | HR Admin, Payroll Officer | F5 |
| GET | /payroll-runs/{id}/export/bank.csv | Payroll Officer | F5 |
| GET | /me/payslips/{id}.pdf | Employee (pemilik) | F6 |
| GET | /reports/{type} | HR Admin, Payroll Officer, Auditor | F7 |
| GET | /audit-logs | Auditor, Super Admin | F1 |

## 5. Logika inti: PayrollCalculator
- Input: `EmployeePeriodData` (gaji efektif per hari dalam periode, komponen, hari kerja, cuti tak berbayar, lembur, status pajak, YTD pajak & penghasilan) + `RulePackSnapshot` (versi yang berlaku pada akhir periode, atau per segmen bila tarif berubah di tengah periode).
- Output: `PayrollResult` dengan baris rincian (kode, label, dasar, tarif, nilai, sumber aturan) yang disimpan di `breakdown` dan ditampilkan di layar tinjau.
- Strategi pajak: `TerMonthlyWithDecemberTrueUp` (lookup kategori dari PTKP → tabel TER bulanan; Desember: PKP setahun → tarif Pasal 17 → kurangi potongan Jan–Nov), `ProgressiveAnnual` (Generic/UK-like), `NoWithholding`.
- Iuran: dasar = min(max(upah, floor), cap) per komponen; pembulatan sesuai config.
- Prorata: kebijakan perusahaan (hari kalender/hari kerja); segmen gaji dari `compensation_history`.
- Murni dan deterministik: tidak menyentuh DB atau waktu sistem; semua dites dengan kasus golden.

## 6. Integrasi pihak ketiga

| Layanan | Untuk | Batasan / biaya |
|---|---|---|
| Web Push (VAPID) | Notifikasi PWA | Gratis; iOS hanya PWA ter-install |
| SMTP (Mailpit di lokal) | Email notifikasi | Layanan email saat deploy: pilih nanti |
| Peta (OpenStreetMap/MapLibre) | Pilih titik geofence | Patuhi kebijakan tile OSM; untuk demo boleh tile publik ringan |
| Claude API (M5) | Asisten kebijakan | Berbayar per token; perlu verifikasi harga saat M5 |

## 7. Keamanan
Sesi Laravel + 2FA TOTP untuk admin; policy per model; global scope tenant + tes isolasi; enkripsi kolom; rate limit login dan clock-in; audit untuk model sensitif dan akses lihat-penuh identitas; slip PDF disajikan lewat route terotorisasi (bukan URL publik).

## 8. Strategi tes
- Unit: PayrollCalculator (≥10 golden Indonesia, ≥5 Generic, perubahan tarif tengah tahun, prorata, true-up Desember), akrual cuti, geofence (haversine), shift lewat tengah malam.
- Feature: setiap endpoint diuji dengan pengguna perusahaan lain (harus 404), peran tanpa izin (403), alur persetujuan dua orang.
- E2E (Playwright, M4): alur karyawan PWA dan run payroll.
- Laporan: total laporan = total slip.

## 9. Deploy & lingkungan
Deploy ditunda. Lokal: Laravel Herd/Sail + PostgreSQL + Mailpit. Seeder: 2 perusahaan (Indonesia, Generic), ±40 karyawan fiktif, 3 bulan riwayat absensi, 2 run payroll.
