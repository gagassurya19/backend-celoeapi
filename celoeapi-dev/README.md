# Celoe API Dev (CodeIgniter 3)

Layanan REST API untuk ETL (Extract-Transform-Load) dan analitik data Moodle. Dibangun dengan CodeIgniter 3 menggunakan `REST_Controller` dan dokumentasi Swagger/OpenAPI yang terintegrasi.

## Fitur Utama
- **Swagger UI**: Dokumentasi OpenAPI 3.0 yang di-generate otomatis dengan auto-discovery endpoint
- **Layanan ETL**:
  - Student Activity Summary (SAS) - Ringkasan aktivitas mahasiswa
  - Course Performance (CP) dan export - Performa kursus dan ekspor data
- **Welcome/Health**: Halaman kesehatan sederhana untuk konfirmasi web/runtime
- **Dockerized**: Berjalan bersama Moodle dan MySQL melalui `docker-compose`

## Tech Stack
- PHP 7.4, Apache (mod_rewrite, mod_headers)
- CodeIgniter 3 (framework)
- `REST_Controller` (library) untuk pola REST
- MySQL 5.7

---

## Quick Start

**Prerequisites**: Docker, Docker Compose

Dari root workspace (`moodle-docker`), jalankan stack:
```bash
docker-compose up -d --build
```

**Services dan ports**:
- Moodle web: `http://localhost:8080`
- Celoe API: `http://localhost:8081`
- MySQL: host `localhost`, port `3302` (mapped ke container `db:3306`)

**Pengecekan awal**:
- Welcome/health: `http://localhost:8081/` (menampilkan pesan singkat)
- Swagger UI: `http://localhost:8081/swagger`

**Jalankan migrasi database ETL** (dari container API):
```bash
docker-compose exec celoeapi php run_migrations.php
```

**Catatan**: Migration 007 menambahkan optimization indexes ke tabel yang sudah ada untuk performa yang lebih baik dengan dataset besar.

---

## Quick Setup (Satu Perintah)

Script ini akan: memeriksa dependencies, menjalankan containers, memastikan database `celoeapi` ada, menjalankan migrasi, dan smoke-test endpoints.

```bash
# Dari root workspace
./celoeapi-dev/quick-setup.sh
```

**Environment overrides** (opsional):
```bash
DB_NAME=celoeapi DB_USER=moodleuser DB_PASS=moodlepass DB_ROOT_PASS=root ./celoeapi-dev/quick-setup.sh
```

---

## Manajemen ETL

Gunakan script unified `etl_manager.sh` untuk semua operasi ETL:

```bash
# Jalankan ETL untuk tanggal tertentu
./etl_manager.sh run                    # Kemarin (default)
./etl_manager.sh run 2024-01-15        # Tanggal spesifik

# Jalankan ETL untuk range tanggal
./etl_manager.sh range                  # 7 hari terakhir (default)
./etl_manager.sh range 2024-01-01 2024-01-31

# Monitor status ETL
./etl_manager.sh monitor                # Setiap 5 detik (default)
./etl_manager.sh monitor 10            # Setiap 10 detik

# Setup cron jobs otomatis
./etl_manager.sh setup-cron

# Tampilkan status saat ini
./etl_manager.sh status

# Tampilkan bantuan
./etl_manager.sh help
```

**Fitur**:
- **Interface Unified**: Satu script untuk semua operasi ETL
- **Penanganan Tanggal Fleksibel**: Tanggal default dengan opsi override
- **Monitoring Real-time**: Update status live dengan output berwarna
- **Setup Cron Otomatis**: Konfigurasi eksekusi ETL terjadwal
- **Logging Komprehensif**: Semua operasi di-log dengan timestamp

---

## Konfigurasi

**File konfigurasi utama** (di dalam `celoeapi-dev/application/config/`):

### `database.php`
- Auto-detect Docker vs Host untuk set DB host:
  - Di dalam container: host `db`, DBs `celoeapi` dan `moodle`
  - Di host: `localhost:3302` (mapped port)
- Default credentials: `moodleuser` / `moodlepass`

### `rest.php`
- Default format: JSON
- Authentication: disabled (`rest_auth = none`), IP whitelist disabled
- CORS: permissive by default (`allow_any_cors_domain = TRUE`)

### `swagger.php`
- Metadata OpenAPI (title, description, servers)

### `routes.php`
- Default controller: `welcome`
- Swagger routes: `/swagger`, `/swagger/spec`, `/swagger/yaml`, `/api-docs`, `/docs`
- ETL routes (lihat Endpoints di bawah)

Docker image entrypoint auto-run `setup-swagger-auto.sh` untuk verifikasi file Swagger di container.

---

## Endpoints (Public)

**Base URL** (local): `http://localhost:8081`

### Swagger UI dan dokumentasi:
- `GET /swagger` → Swagger UI
- `GET /swagger/spec` → OpenAPI JSON
- `GET /swagger/yaml` → OpenAPI YAML

### Welcome/health:
- `GET /` → pesan kesehatan sederhana (Welcome controller)

### Student Activity Summary (SAS):
- `POST /api/etl_sas/run` → jalankan pipeline (async). Optional JSON body:
```json
{
  "start_date": "YYYY-MM-DD",
  "concurrency": 4
}
```
- `GET /api/etl_sas/export` → ekspor data
- `POST /api/etl_sas/clean` → bersihkan data
- `GET /api/etl_sas/logs` → log
- `GET /api/etl_sas/status` → status

### Course Performance (CP):
- `POST /api/etl_cp/run` → jalankan (async). Optional JSON body sama dengan SAS
- `POST /api/etl_cp/clean` → bersihkan
- `GET /api/etl_cp/logs` → log
- `GET /api/etl_cp/status` → status
- `GET /api/etl_cp/export` → ekspor

### Export query parameters (dimana berlaku):
- `table` (string) – ekspor satu tabel
- `tables` (csv string) – ekspor subset tabel
- `limit` (int), `offset` (int)
- `debug` (bool)

### Contoh request:
```bash
# Jalankan SAS ETL backfill dari 2024-01-01 dengan 4 workers
curl -X POST http://localhost:8081/api/etl_sas/run \
  -H 'Content-Type: application/json' \
  -d '{"start_date":"2024-01-01","concurrency":4}'

# Cek status SAS
curl http://localhost:8081/api/etl_sas/status

# Ekspor data CP (subset tabel)
curl "http://localhost:8081/api/etl_cp/export?tables=cp_course_summary,cp_activity_summary&limit=100&offset=0"
```

**Catatan**: Endpoint run bersifat asynchronous; cek endpoint logs/status untuk progress.

---

## Arsitektur Sistem

Sistem ETL dibagi menjadi **3 proses terpisah** yang bekerja bersama:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ ActivityCounts  │    │   UserCounts    │    │  Main ETL       │
│ ETL             │    │   ETL           │    │  (Merge)        │
│                 │    │                 │    │                 │
│ • File Views    │    │ • Num Students  │    │ • Final Report  │
│ • Video Views   │    │ • Num Teachers  │    │ • Calculations  │
│ • Forum Views   │    │                 │    │ • Aggregations  │
│ • Quiz Views    │    │                 │    │                 │
│ • Assignment    │    │                 │    │                 │
│ • URL Views     │    │                 │    │                 │
│ • Active Days   │    │                 │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │   user_activity_etl     │
                    │   (Final Results)       │
                    └─────────────────────────┘
```

---

## Flow Proses ETL

Setiap ETL mengikuti **logika flowchart autentikasi dan scheduler**:

### **1. Authentication Flow**
```
Start → Decode Token → Username Found? → Set Privilege → Has Admin Role? → Set User Session
  │                          │                              │
  NO                        NO                             NO
  │                          │                              │
  ▼                          ▼                              ▼
Error                   Set Filter                  Set Filter
                    Directorate & Unit          Directorate & Unit
```

### **2. Scheduler Flow** 
```
Get Data from Scheduler → Not Empty Data? → Status Not Running? → End Date Valid? → Run Extraction
      │                        │                    │                    │
     NO                       NO                   NO                   NO
      │                        │                    │                    │
      ▼                        ▼                    ▼                    ▼
     End                      End                  End                  End
```

### **3. Data Processing Flow**
```
Extract Data → Transform Data → Load Data → Update Scheduler → Delete Log Data → End
     │              │              │              │              │
     └──────────────┴──────────────┴──────────────┴──────────────┘
                          (Pagination Loop)
```

---

## Schema Database

Project menggunakan schema ETL komprehensif dengan grup tabel utama berikut:

### Course Performance (CP) Tables
- `cp_activity_summary` - Metrik aktivitas per kursus
- `cp_course_summary` - Overview kursus dengan jumlah mahasiswa
- `cp_student_profile` - Informasi mahasiswa
- `cp_student_quiz_detail` - Detail attempt quiz
- `cp_student_assignment_detail` - Detail submission assignment
- `cp_student_resource_access` - Tracking akses resource
- `cp_etl_logs` - Log eksekusi ETL
- `cp_etl_watermarks` - Tracking progress ETL

### Student Activity Summary (SAS) Tables
- `sas_courses` - Katalog kursus (direferensikan oleh tabel SAS)
- `sas_user_activity_etl` - Metrik aktivitas harian per kursus
- `sas_activity_counts_etl` - Count tipe aktivitas
- `sas_user_counts_etl` - Count mahasiswa/guru
- `sas_etl_logs` - Log eksekusi SAS ETL
- `sas_etl_watermarks` - Tracking progress SAS ETL

### Fitur Schema
- UTF8MB4 character set dengan unicode collation
- Indexing yang tepat pada field yang sering di-query
- Foreign key constraints dimana sesuai
- Field timestamp untuk audit trail
- Auto-increment primary keys

Schema lengkap didefinisikan di migrations 001-006, dengan optimization indexes ditambahkan via migration 007 untuk dataset besar.

---

## Optimisasi Performa

Project mencakup database optimization indexes untuk menangani dataset besar secara efisien:

### Optimisasi Tabel CP
- **Activity Summary**: Composite indexes pada `(course_id, activity_type)`, `(course_id, section)`, dan `created_at DESC`
- **Student Quiz**: Indexes pada `(course_id, user_id)`, `waktu_mulai DESC`, dan `nilai DESC`
- **Student Assignment**: Index pada `waktu_submit DESC`
- **Resource Access**: Index pada `waktu_akses DESC`

### Optimisasi Tabel SAS
- **Activity Counts**: Composite index pada `(courseid, extraction_date)`
- **User Counts**: Composite index pada `(courseid, extraction_date)`
- **ETL Logs**: Composite index pada `(process_name, status, extraction_date)`

### Fitur Performa Tambahan
- **Course Summary**: Index pada `jumlah_mahasiswa DESC` untuk sorting
- **Student Profile**: Index pada `program_studi` untuk filtering
- **Courses**: Composite index pada `(visible, program_id)` untuk visibility queries

Optimisasi ini memastikan performa query yang efisien bahkan dengan jutaan record.

---

## Detail Aliran Data

### **1. ActivityCounts ETL**
**Source**: `mdl_logstore_standard_log` (Database Moodle)
**Logic**: 
```sql
SELECT
    courseid,
    COUNT(CASE WHEN component = 'mod_resource' THEN 1 END) AS File_Views,
    COUNT(CASE WHEN component = 'mod_page' THEN 1 END) AS Video_Views,
    COUNT(CASE WHEN component = 'mod_forum' THEN 1 END) AS Forum_Views,
    COUNT(CASE WHEN component = 'mod_quiz' THEN 1 END) AS Quiz_Views,
    COUNT(CASE WHEN component = 'mod_assign' THEN 1 END) AS Assignment_Views,
    COUNT(CASE WHEN component = 'mod_url' THEN 1 END) AS URL_Views,
    DATEDIFF(FROM_UNIXTIME(MAX(timecreated)), FROM_UNIXTIME(MIN(timecreated))) + 1 AS ActiveDays
FROM mdl_logstore_standard_log
WHERE contextlevel = 70 AND action = 'viewed'
AND timecreated BETWEEN [start_date] AND [end_date]
GROUP BY courseid
```

### **2. UserCounts ETL**
**Source**: `mdl_role_assignments` + `mdl_context` (Database Moodle)
**Logic**:
```sql
SELECT
    ctx.instanceid AS courseid,
    COUNT(DISTINCT CASE WHEN ra.roleid = 5 THEN ra.userid END) AS Num_Students,
    COUNT(DISTINCT CASE WHEN ra.roleid IN (3, 4) THEN ra.userid END) AS Num_Teachers
FROM mdl_role_assignments ra
JOIN mdl_context ctx ON ra.contextid = ctx.id
WHERE ctx.contextlevel = 50
GROUP BY ctx.instanceid
```

### **3. Main ETL (Merge)**
**Source**: `activity_counts_etl` + `user_counts_etl` + `mdl_course` + `mdl_course_categories`
**Logic**: Implementasi query `finals_query_mysql57.sql` dengan cross-database joins
```sql
SELECT 
    categories.idnumber AS course_id,
    subjects.idnumber AS id_number,
    subjects.fullname AS course_name,
    subjects.shortname AS course_shortname,
    COALESCE(uc.num_teachers, 0) AS num_teachers,
    COALESCE(uc.num_students, 0) AS num_students,
    COALESCE(ac.file_views, 0) AS file_views,
    -- ... field aktivitas lainnya
    ROUND(
        (total_views) / NULLIF(uc.num_students, 0) / NULLIF(ac.active_days, 0), 2
    ) AS avg_activity_per_student_per_day
FROM mdl_course subjects
LEFT JOIN mdl_course_categories categories ON subjects.category = categories.id
LEFT JOIN celoeapi.activity_counts_etl ac ON subjects.id = ac.courseid AND ac.extraction_date = ?
LEFT JOIN celoeapi.user_counts_etl uc ON subjects.id = uc.courseid AND uc.extraction_date = ?
WHERE subjects.visible = 1 AND categories.idnumber IS NOT NULL
```

### **Dukungan Pagination**
Semua proses ETL menggunakan **pagination** untuk menangani dataset besar:
- **ActivityCounts**: Memproses kursus dalam batch 1000
- **UserCounts**: Memproses kursus dalam batch 1000  
- **Main ETL**: Memproses data merged dalam batch 500

### **Arsitektur Cross-Database**
Sistem ETL beroperasi di dua database:
- **`moodle`**: Data source (logs, courses, users, roles)
- **`celoeapi`**: Hasil ETL dan tabel processing

---

## Monitoring & Status

### **Nilai Status ETL**
- `not_started` - ETL belum dijalankan
- `queued` - ETL menunggu untuk dimulai
- `running` - ETL sedang memproses
- `completed` - ETL selesai dengan sukses
- `failed` - ETL mengalami error

### **Nilai Status Scheduler**
- `1` - Selesai
- `2` - Sedang berjalan  
- `3` - Gagal
- `4` - Antrian

### **Monitoring Status**
```bash
# Cek status CLI
docker exec celoe-api php index.php cli user_activity_etl status

# Cek status API
curl -H "Authorization: Bearer token" http://localhost:8081/api/user_activity_etl/status

# Cek status database
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
SELECT batch_name, status, start_date, end_date, 
       TIMESTAMPDIFF(SECOND, start_date, end_date) as duration_seconds
FROM log_scheduler 
WHERE start_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY start_date DESC;"
```

---

## Troubleshooting

### **Masalah Umum dan Solusi**

#### 1. ETL Sudah Berjalan
**Masalah**: "ETL process is already running"
**Solusi**: 
```bash
# Bersihkan proses yang stuck
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
UPDATE log_scheduler SET status = 3, end_date = NOW(), error_details = 'Cleared manually' WHERE status = 2;"
```

#### 2. Tabel ETL Tidak Ada
**Masalah**: Table 'celoeapi.activity_counts_etl' doesn't exist
**Solusi**: 
```bash
# Jalankan setup migrasi CLI
cd celoeapi-dev/
./migrate.sh run
```

#### 3. Tidak Ada Data di Hasil
**Masalah**: ETL selesai tapi tidak ada data
**Solusi**: 
- Cek apakah data source ada di Moodle logs
- Verifikasi range tanggal memiliki data aktivitas
- Cek database joins di models
- Pastikan konfigurasi cross-database benar

#### 4. Error Koneksi Database
**Masalah**: Tidak bisa connect ke database
**Solusi**: 
```bash
# Cek apakah service database berjalan
docker-compose ps

# Restart service database
docker-compose restart db
```

### **Perintah Debug**
```bash
# Cek data tabel ETL
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
SELECT 'activity_counts_etl' as table_name, COUNT(*) as records FROM activity_counts_etl
UNION ALL
SELECT 'user_counts_etl', COUNT(*) FROM user_counts_etl
UNION ALL  
SELECT 'user_activity_etl', COUNT(*) FROM user_activity_etl;"

# Cek status scheduler
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
SELECT id, batch_name, status, start_date, end_date FROM log_scheduler ORDER BY id DESC LIMIT 10;"

# Cek ketersediaan data log Moodle
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass moodle -e "
SELECT COUNT(*) as total_logs, COUNT(DISTINCT courseid) as unique_courses, COUNT(DISTINCT userid) as unique_users
FROM mdl_logstore_standard_log WHERE courseid > 1;"
```

---

## Struktur Project (bagian utama)

```
celoeapi-dev/
  application/
    config/
      database.php     # Konfigurasi DB (auto Docker/Host)
      rest.php         # Pengaturan REST
      swagger.php      # Metadata OpenAPI
      routes.php       # Routes (Swagger, ETL)
    controllers/
      Welcome.php      # Halaman health
      Swagger.php      # Swagger UI/spec
      api/
        etl_sas.php    # Endpoint SAS ETL
        etl_cp.php     # Endpoint CP ETL
        etl_cp_export.php
    helpers/
      swagger_helper.php  # Auto-discovery untuk OpenAPI
    libraries/
      REST_Controller.php  # Base REST
    views/
      swagger/index.php    # View Swagger UI
  run_migrations.php    # CLI untuk menjalankan migrasi ETL
  setup-swagger-auto.sh # Pengecekan swagger container
  Dockerfile            # Image service
```

**Logs** (di dalam container): `application/logs/`
- `etl_background.log`, `etl_background_range.log`, `cron_etl_auto.log`, dll.

---

## Development dan Operations

**Rebuild dan restart hanya service API**:
```bash
docker-compose build celoeapi && docker-compose up -d celoeapi
```

**Jalankan migrasi lagi jika diperlukan**:
```bash
docker-compose exec celoeapi php run_migrations.php
```

**Health check umum**:
```bash
curl http://localhost:8081/
curl http://localhost:8081/swagger
curl http://localhost:8081/swagger/spec
```

---

## Kustomisasi

- Update `application/config/swagger.php` untuk menyesuaikan title, description, servers.
- Tambah controller baru di bawah `application/controllers/api/` untuk auto-muncul di Swagger.
- Tune behavior ETL di controllers/models; endpoint run tetap async.

---

## Keamanan

Environment ini menonaktifkan authentication (`rest_auth = none`) dan mengizinkan domain CORS apapun untuk development. Untuk production:
- Enable auth di `rest.php` dan konfigurasi `auth_override_*` rules
- Pertimbangkan IP whitelisting dan rate limiting
- Harden MySQL credentials/secrets

---

## Lisensi

MIT (lihat `application/config/swagger.php` untuk metadata lisensi)


