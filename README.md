# AI Auto Post 404 Generator

WordPress Plugin สำหรับสร้างบทความ Blog อัตโนมัติจาก URL ที่เป็น 404 โดยใช้ AI (OpenAI GPT, Anthropic Claude, Google Gemini) พร้อมระบบ SEO, รูปภาพ Featured Image, และ Internal Linking

**Version:** 1.0.0
**Author:** [Endlessedwork](https://endlessedwork.com)
**License:** GPL v2 or later
**Requires:** WordPress 5.8+ / PHP 7.4+

---

## สารบัญ

- [ภาพรวมการทำงาน](#ภาพรวมการทำงาน)
- [การติดตั้ง](#การติดตั้ง)
- [การตั้งค่าเบื้องต้น](#การตั้งค่าเบื้องต้น)
- [โครงสร้างโปรเจค](#โครงสร้างโปรเจค)
- [คลาสและฟังก์ชัน](#คลาสและฟังก์ชัน)
  - [Main Entry Point](#main-entry-point)
  - [Plugin Class](#plugin-class)
  - [Interceptor Class](#interceptor-class)
  - [ContentGenerator Class](#contentgenerator-class)
  - [PostCreator Class](#postcreator-class)
  - [AI Providers](#ai-providers)
  - [AdminSettings Class](#adminsettings-class)
- [Flow การทำงาน](#flow-การทำงาน)
- [การตั้งค่าขั้นสูง](#การตั้งค่าขั้นสูง)
- [WordPress Hooks](#wordpress-hooks)
- [ความเข้ากันได้กับ SEO Plugins](#ความเข้ากันได้กับ-seo-plugins)
- [ระบบความปลอดภัย](#ระบบความปลอดภัย)
- [การแก้ปัญหา](#การแก้ปัญหา)

---

## ภาพรวมการทำงาน

```
ผู้ใช้เข้า URL ที่ไม่มีอยู่ (404)
       │
       ▼
Plugin ดักจับ 404 → ตรวจสอบ keyword
       │
       ▼
แสดงหน้า Loading → เรียก AI สร้างเนื้อหา
       │
       ▼
สร้าง WordPress Post พร้อม SEO + รูปภาพ
       │
       ▼
Redirect ผู้ใช้ไปหน้าแรก (Post สร้างใน Background)
```

เมื่อมีคนเข้า URL ที่ไม่มีอยู่ในเว็บ (เช่น `/how-to-make-coffee/`) Plugin จะ:

1. ดักจับ 404 request
2. ดึง keyword จาก URL (`how to make coffee`)
3. ตรวจสอบว่า keyword ถูกต้องและไม่ซ้ำ
4. เรียก AI API สร้างบทความ SEO-optimized
5. สร้าง WordPress Post พร้อม featured image และ metadata
6. Redirect ผู้ใช้กลับหน้าแรก

---

## การติดตั้ง

1. คัดลอกโฟลเดอร์ `ai-auto-post-generator/` ไปไว้ที่ `/wp-content/plugins/`
2. เข้า WordPress Admin → Plugins → เปิดใช้งาน "Ai auto post 404 generator"
3. ไปที่ **Settings → AI Auto Post** เพื่อตั้งค่า

---

## การตั้งค่าเบื้องต้น

| ขั้นตอน | การดำเนินการ |
|---------|-------------|
| 1. เลือก AI Provider | เลือก OpenAI, Anthropic Claude หรือ Google Gemini |
| 2. ใส่ API Key | ใส่ API Key ของ provider ที่เลือก |
| 3. ทดสอบการเชื่อมต่อ | กดปุ่ม "Test Connection" ในหน้า Settings |
| 4. ตั้งค่าภาษา | เลือกภาษาเนื้อหา (ค่าเริ่มต้น: ไทย) |
| 5. เปิด Plugin | สลับ Master Switch เป็น ON |

### ค่าเริ่มต้นหลังติดตั้ง

```php
'plugin_enabled'        => false,       // ปิดอยู่ (ต้องเปิดเอง)
'ai_provider'           => 'openai',    // ใช้ OpenAI
'openai_model'          => 'gpt-4o',
'anthropic_model'       => 'claude-sonnet-4-20250514',
'gemini_model'          => 'gemini-1.5-flash',
'max_tokens'            => 8000,
'post_status'           => 'publish',   // เผยแพร่ทันที
'post_author'           => 1,
'default_category'      => 1,
'enable_featured_image' => true,
'enable_seo'            => true,
'rate_limit_per_day'    => 50,          // สร้างได้ 50 โพสต์/วัน
'content_language'      => 'th',        // ภาษาไทย
'min_word_count'        => 500,
```

---

## โครงสร้างโปรเจค

```
ai-auto-post-generator/
├── ai-auto-post-generator.php          # ไฟล์หลัก, constants, autoloader, hooks
├── admin/
│   ├── class-admin-settings.php        # จัดการหน้า Settings
│   └── views/
│       └── settings-page.php           # Template หน้า Settings UI
├── includes/
│   ├── class-plugin.php                # คลาสหลัก orchestration
│   ├── class-interceptor.php           # ดักจับ 404 และตรวจสอบ keyword
│   ├── class-content-generator.php     # สร้างเนื้อหาด้วย AI
│   ├── class-post-creator.php          # สร้าง WordPress Post
│   └── ai-providers/
│       ├── abstract-ai-provider.php    # Base class สำหรับ AI providers
│       ├── class-openai.php            # OpenAI GPT + DALL-E
│       ├── class-anthropic.php         # Anthropic Claude
│       └── class-gemini.php            # Google Gemini
├── assets/
│   ├── js/admin.js                     # JavaScript สำหรับ Admin UI
│   └── css/admin.css                   # CSS สำหรับ Admin UI
└── templates/
    └── loading-page.php                # Template หน้า Loading
```

---

## คลาสและฟังก์ชัน

### Main Entry Point

**ไฟล์:** `ai-auto-post-generator.php`

ไฟล์หลักของ Plugin รับผิดชอบ constants, autoloader, และ activation hooks

#### Constants

| Constant | ค่า | คำอธิบาย |
|----------|-----|---------|
| `AIAPG_VERSION` | `'1.0.0'` | เวอร์ชัน Plugin |
| `AIAPG_PLUGIN_DIR` | `plugin_dir_path()` | Path ไปยังโฟลเดอร์ Plugin |
| `AIAPG_PLUGIN_URL` | `plugin_dir_url()` | URL ของ Plugin |
| `AIAPG_PLUGIN_BASENAME` | `plugin_basename()` | Basename ของ Plugin |

#### Functions

```php
aiapg_init()
```
- **Hook:** `plugins_loaded`
- **หน้าที่:** โหลด text domain และสร้าง instance ของ `AIAPG\Plugin`

```php
aiapg_activate()
```
- **Hook:** `register_activation_hook`
- **หน้าที่:** ตั้งค่า default options ใน `wp_options` และ flush rewrite rules

```php
aiapg_deactivate()
```
- **Hook:** `register_deactivation_hook`
- **หน้าที่:** Flush rewrite rules

#### SPL Autoloader

ระบบ autoload สำหรับ namespace `AIAPG\*` — แปลงชื่อคลาส (เช่น `ContentGenerator`) เป็นไฟล์ (เช่น `class-content-generator.php`) อัตโนมัติ

---

### Plugin Class

**ไฟล์:** `includes/class-plugin.php`
**Namespace:** `AIAPG\Plugin`

คลาสหลักที่เชื่อมส่วนต่างๆ ของ Plugin เข้าด้วยกัน

#### Properties

| Property | Type | คำอธิบาย |
|----------|------|---------|
| `$settings` | `array` | ค่า settings จาก database |
| `$interceptor` | `Interceptor` | Instance สำหรับดักจับ 404 |
| `$admin_settings` | `AdminSettings` | Instance สำหรับ admin UI |

#### Methods

```php
public function init(): void
```
เริ่มต้น Plugin — สร้าง admin settings (ถ้าอยู่ใน admin), interceptor, และลงทะเบียน hooks

```php
public function ajax_generate_post(): void
```
- **AJAX Handler** สำหรับสร้าง Post
- **Parameters:** `$_POST['token']` (string) — token ที่ได้จาก transient
- **Process:**
  1. ตรวจสอบ token → ดึง keyword จาก transient
  2. สร้างเนื้อหาด้วย `ContentGenerator`
  3. สร้าง Post ด้วย `PostCreator`
  4. เพิ่ม rate limit counter
- **Response:** JSON `{ success: true, data: { post_id, url } }` หรือ error

```php
public function enqueue_admin_assets(string $hook): void
```
โหลด CSS/JS เฉพาะหน้า Settings ของ Plugin (`settings_page_ai-auto-post-generator`)

```php
public function add_settings_link(array $links): array
```
เพิ่มลิงก์ "Settings" ในหน้า Plugins list

```php
public function get_settings(?string $key = null): mixed
```
ดึงค่า settings ทั้งหมด หรือค่าเฉพาะ key

---

### Interceptor Class

**ไฟล์:** `includes/class-interceptor.php`
**Namespace:** `AIAPG\Interceptor`

คลาสหลักสำหรับดักจับ 404 requests และเริ่มกระบวนการสร้างเนื้อหา

#### Methods

```php
public function init(): void
```
ลงทะเบียน hook `template_redirect` สำหรับดักจับ 404

```php
public function intercept_404(): void
```
- **Hook:** `template_redirect` (priority 1)
- **หน้าที่หลัก:** ดักจับ 404 → ตรวจสอบทุกเงื่อนไข → เริ่มสร้าง Post
- **เงื่อนไขที่ตรวจสอบ:**
  1. Plugin เปิดใช้งาน?
  2. เป็นหน้า 404?
  3. มี API Key ตั้งค่าถูกต้อง?
  4. ไม่เกิน rate limit?
  5. Keyword ถูกต้อง?
  6. ไม่มี Post ซ้ำ?

```php
private function is_configured(): bool
```
ตรวจสอบว่ามี API Key สำหรับ provider ที่เลือกหรือไม่

```php
private function check_rate_limit(): bool
```
ตรวจสอบว่ายังสร้าง Post ได้อีกหรือไม่ (ใช้ WordPress Transients เก็บ counter รายวัน)

```php
private function extract_keyword_from_url(): string
```
ดึง keyword จาก `$_SERVER['REQUEST_URI']` — เอา segment สุดท้ายของ URL path

```php
private function clean_keyword(string $keyword): string
```
ทำความสะอาด keyword:
- URL decode
- แปลง `-` และ `_` เป็นเว้นวรรค
- ลบ patterns ที่เป็น code (MD5, Base64, Hex, URL encoded, HTML entities)
- เก็บเฉพาะตัวอักษรไทย/ลาติน, ตัวเลข, เว้นวรรค
- ลบตัวเลข 5 หลักขึ้นไปที่เป็น standalone

```php
private function post_exists(string $keyword): bool
```
ตรวจสอบว่ามี Post ที่มี slug ตรงกับ keyword อยู่แล้วหรือไม่

```php
private function is_valid_keyword(string $keyword): bool
```
Validation ครบถ้วน:
- ต้องมีตัวอักษร ≥ 3 ตัว
- ความยาวไม่เกิน 200 ตัวอักษร
- ตัวเลขต้องไม่มากกว่าตัวอักษร
- ต้องมีสระ (ป้องกัน random code)
- ไม่อยู่ใน blocked keywords list
- ไม่ตรง blocked URL patterns
- ไม่ใช่ file extension (`.php`, `.js`, `.css`, ฯลฯ)
- ไม่ใช่ WordPress system paths (`wp-admin`, `wp-content`, ฯลฯ)
- ไม่ใช่ hex string หรือ numeric-only

```php
private function process_keyword(string $keyword): void
```
สร้าง unique token (MD5) → เก็บ keyword ใน transient (300 วินาที) → แสดงหน้า loading

```php
private function show_loading_page_ajax(string $keyword, string $token): void
```
แสดงหน้า HTML พร้อม:
- Gradient background + spinner animation
- JavaScript ส่ง Fetch request ไปยัง `admin-ajax.php`
- Redirect ไปหน้าแรกหลัง 1.5 วินาที (ไม่รอ AI เสร็จ)

```php
private function is_blocked_keyword(string $keyword): bool
```
ตรวจสอบ keyword กับ blocked list จาก settings (case-insensitive, partial match)

```php
private function is_blocked_pattern(string $keyword): bool
```
ตรวจสอบ keyword กับ URL patterns จาก settings (รองรับ wildcard `*` และ `?`)

---

### ContentGenerator Class

**ไฟล์:** `includes/class-content-generator.php`
**Namespace:** `AIAPG\ContentGenerator`

คลาสหลักสำหรับสร้างเนื้อหา Blog ด้วย AI

#### Properties

| Property | Type | คำอธิบาย |
|----------|------|---------|
| `$settings` | `array` | ค่า settings |
| `$provider` | `AbstractAIProvider` | AI provider ที่เลือก |
| `$openai_for_images` | `OpenAI\|null` | Fallback สำหรับสร้างรูปภาพ (ถ้า provider หลักไม่รองรับ) |

#### Methods

```php
public function generate(string $keyword): array|WP_Error
```
- **หน้าที่หลัก:** สร้างเนื้อหาทั้งหมดสำหรับ keyword
- **Return:**
  ```php
  [
      'content'     => 'HTML เนื้อหาบทความ',
      'title'       => 'หัวข้อบทความ',
      'excerpt'     => 'คำอธิบายสั้น',
      'meta_title'  => 'SEO Title',
      'meta_desc'   => 'Meta Description',
      'image_url'   => 'URL รูปภาพ Featured Image',
  ]
  ```
- **ขั้นตอน:**
  1. สร้าง prompt → เรียก AI สร้างเนื้อหาหลัก
  2. สร้าง SEO metadata (ถ้าเปิดใช้)
  3. สร้าง Featured Image (ถ้าเปิดใช้)
  4. เพิ่ม Internal Links (ถ้าเปิดใช้)

```php
private function build_content_prompt(string $keyword, string $language, int $min_words): string
```
สร้าง prompt สำหรับ AI:
- ถ้าตั้งค่า custom prompt → ใช้ custom prompt พร้อมแทนที่ `{keyword}`, `{language}`, `{min_words}`
- ถ้าไม่ → ใช้ default prompt ที่มี SEO requirements ครบถ้วน (keyword density, content structure, E-E-A-T signals, FAQ format)

```php
private function generate_seo_data(string $keyword, string $content, string $language): array|WP_Error
```
- ส่ง prompt ไปยัง AI เพื่อสร้าง metadata ในรูปแบบ JSON
- Parse JSON response (ลบ markdown code blocks ถ้ามี)
- Fallback: return basic title ถ้า JSON parse ไม่สำเร็จ

```php
private function generate_featured_image(string $keyword, string $language): string|WP_Error
```
- ใช้ provider หลักถ้ารองรับ image → ถ้าไม่ก็ fallback ไปใช้ OpenAI DALL-E
- Image styles: `professional`, `illustration`, `realistic`, `minimalist`, `vibrant`

```php
private function add_internal_links(string $content, string $main_keyword, array $secondary_keywords = []): string
```
เพิ่ม internal links ในเนื้อหา:
- รวม keywords จาก SEO data + custom keywords จาก settings
- เรียงตามความยาว (ยาวกว่าก่อน ป้องกัน partial match)
- แทนที่เฉพาะ occurrence แรกของแต่ละ keyword
- ไม่ link keyword ที่อยู่ใน `<a>` tag อยู่แล้ว
- จำกัดจำนวน links สูงสุดตาม settings

```php
private function get_language_name(string $code): string
```
แปลง language code เป็นชื่อภาษา:
- `th` → `Thai (ภาษาไทย)`
- `en` → `English`
- `zh` → `Chinese`
- `ja` → `Japanese`
- `ko` → `Korean`

---

### PostCreator Class

**ไฟล์:** `includes/class-post-creator.php`
**Namespace:** `AIAPG\PostCreator`

คลาสสำหรับสร้าง WordPress Post จากเนื้อหาที่ AI สร้าง

#### Methods

```php
public function create(string $keyword, array $content): int|WP_Error
```
- **หน้าที่หลัก:** สร้าง Post ใหม่ใน WordPress
- **ขั้นตอน:**
  1. เตรียม post data (title, content, excerpt, status, author, slug)
  2. `wp_insert_post()` สร้าง Post
  3. ตั้ง Featured Image (ถ้ามี)
  4. ตั้ง SEO metadata (ถ้าเปิดใช้)
  5. บันทึก custom meta สำหรับ tracking (`_aiapg_generated`, `_aiapg_keyword`, `_aiapg_generated_at`, `_aiapg_provider`)
  6. Fire action `aiapg_post_created`
- **Return:** Post ID หรือ WP_Error

```php
private function set_featured_image(int $post_id, string $image_url, string $keyword): int|false
```
- Download รูปจาก URL → สร้าง WordPress attachment → ตั้งเป็น post thumbnail
- ตั้ง alt text เป็น keyword
- Cleanup temp file หลัง upload

```php
private function set_seo_meta(int $post_id, array $content): void
```
บันทึก SEO metadata ลง post meta:
- **Custom fields:** `_aiapg_meta_title`, `_aiapg_meta_description`, `_aiapg_focus_keyphrase`, `_aiapg_secondary_keywords`
- **Yoast SEO:** `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_focuskw`
- **Rank Math:** `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`
- **All in One SEO:** `_aioseo_title`, `_aioseo_description`, `_aioseo_keyphrases`

```php
private function set_schema_meta(int $post_id, array $content): void
```
บันทึก Schema.org Article JSON-LD ลง `_aiapg_schema_data`

---

### AI Providers

ทุก provider สืบทอดจาก `AbstractAIProvider` ซึ่งกำหนด interface ร่วมกัน

#### AbstractAIProvider (Base Class)

**ไฟล์:** `includes/ai-providers/abstract-ai-provider.php`

| Method | Return | คำอธิบาย |
|--------|--------|---------|
| `generate_text($prompt)` | `string\|WP_Error` | **(abstract)** สร้างข้อความ |
| `generate_image($prompt)` | `string\|WP_Error` | **(abstract)** สร้างรูปภาพ |
| `make_request($url, $body, $headers)` | `array\|WP_Error` | HTTP request ผ่าน `wp_remote_post()` (timeout: 120s) |
| `get_name()` | `string` | **(abstract)** ชื่อ provider |
| `supports_image_generation()` | `bool` | รองรับสร้างรูปหรือไม่ (default: `false`) |

#### OpenAI Provider

**ไฟล์:** `includes/ai-providers/class-openai.php`

| คุณสมบัติ | ค่า |
|-----------|-----|
| API URL | `https://api.openai.com/v1` |
| Default Model | `gpt-4` |
| Text Endpoint | `/chat/completions` |
| Image Endpoint | `/images/generations` (DALL-E 3) |
| Image Size | `1792x1024` |
| Temperature | `0.7` |
| Max Tokens | `4000` |
| **รองรับ Image** | **Yes** |

#### Anthropic Claude Provider

**ไฟล์:** `includes/ai-providers/class-anthropic.php`

| คุณสมบัติ | ค่า |
|-----------|-----|
| API URL | `https://api.anthropic.com/v1` |
| Default Model | `claude-3-sonnet-20240229` |
| Endpoint | `/messages` |
| API Version Header | `2023-06-01` |
| Max Tokens | `4096` |
| **รองรับ Image** | **No** (ใช้ OpenAI fallback ถ้ามี key) |

#### Google Gemini Provider

**ไฟล์:** `includes/ai-providers/class-gemini.php`

| คุณสมบัติ | ค่า |
|-----------|-----|
| API URL | `https://generativelanguage.googleapis.com/v1beta` |
| Default Model | `gemini-pro` |
| Endpoint | `/models/{model}:generateContent?key={api_key}` |
| Temperature | `0.7` |
| Max Output Tokens | `4096` |
| **รองรับ Image** | **No** (ใช้ OpenAI fallback ถ้ามี key) |

---

### AdminSettings Class

**ไฟล์:** `admin/class-admin-settings.php`

จัดการหน้า Settings, API testing, และ diagnostic tools

#### Constants

| Constant | ค่า |
|----------|-----|
| `OPTION_NAME` | `'aiapg_settings'` |
| `PAGE_SLUG` | `'ai-auto-post-generator'` |

#### Methods

```php
public function ajax_test_api(): void
```
- **AJAX handler** ทดสอบการเชื่อมต่อ API
- **Parameters:** `provider`, `api_key`
- ส่ง test prompt ไปยัง AI → return ผลลัพธ์

```php
public function ajax_test_intercept(): void
```
- **AJAX handler** จำลองการดักจับ 404
- ตรวจสอบทุกเงื่อนไข: plugin enabled, API key, rate limit, keyword validity, post existence
- Return array พร้อมผล pass/fail ของแต่ละ check

```php
public function sanitize_settings(array $input): array
```
Validate และ sanitize ทุก field ก่อนบันทึก:
- Boolean: `sanitize_text_field()` → cast to truthy
- Text: `sanitize_text_field()`
- Numbers: `absint()`
- Textareas: `sanitize_textarea_field()`
- HTML (custom prompt): `wp_kses_post()`

---

## Flow การทำงาน

### 1. การสร้าง Post อัตโนมัติ (404 → Post)

```
1. ผู้ใช้เข้า URL /how-to-make-coffee/
2. WordPress ส่ง 404 status
3. Interceptor::intercept_404() ถูกเรียก (hook: template_redirect)
4. ตรวจสอบเงื่อนไขทั้งหมด ✓
5. extract_keyword_from_url() → "how to make coffee"
6. clean_keyword() → ทำความสะอาด keyword
7. is_valid_keyword() → ตรวจสอบ keyword
8. process_keyword() → สร้าง token + เก็บ transient (300s)
9. show_loading_page_ajax() → แสดง HTML loading page
10. JavaScript: Fetch → admin-ajax.php?action=aiapg_generate_post
11. Plugin::ajax_generate_post() ทำงาน:
    a. ดึง keyword จาก transient
    b. ContentGenerator::generate() → สร้างเนื้อหา AI
    c. PostCreator::create() → สร้าง WordPress Post
    d. ตั้ง featured image + SEO meta + schema
12. Response JSON → { post_id, url }
13. Redirect ผู้ใช้ไปหน้าแรก (ทำทันทีหลัง 1.5s ไม่รอ AI)
```

### 2. Admin Settings Flow

```
Settings → AI Auto Post
   ├── Master Switch (เปิด/ปิด)
   ├── Test & Debug
   │   ├── Test API Connection
   │   └── Test 404 Interception (จำลอง)
   ├── AI Provider Settings
   │   ├── เลือก Provider
   │   ├── API Keys
   │   └── Model Selection
   ├── Content Settings
   │   ├── ภาษา
   │   ├── จำนวนคำขั้นต่ำ
   │   └── Custom Prompt
   ├── Post Settings
   │   ├── Status (Draft/Pending/Published)
   │   ├── Author
   │   └── Category
   ├── Featured Image Settings
   │   └── Image Style (5 แบบ)
   ├── Internal Linking
   │   ├── เปิด/ปิด
   │   ├── จำนวน Link สูงสุด
   │   └── Custom Keywords
   ├── Blocked Keywords (ใส่ทีละบรรทัด)
   ├── Blocked URL Patterns (รองรับ * และ ?)
   └── Rate Limiting (จำนวน Post/วัน)
```

---

## การตั้งค่าขั้นสูง

### Custom Prompt

สามารถเขียน prompt เองได้ โดยใช้ placeholders:

| Placeholder | แทนที่ด้วย |
|-------------|-----------|
| `{keyword}` | Keyword จาก URL |
| `{language}` | ชื่อภาษา (เช่น "Thai (ภาษาไทย)") |
| `{min_words}` | จำนวนคำขั้นต่ำ |

### Blocked Keywords

ใส่คำที่ไม่ต้องการให้สร้างเนื้อหา ทีละบรรทัด:

```
casino
gambling
adult
```

- Case-insensitive
- Partial match (ถ้า keyword มีคำนี้อยู่จะถูก block)

### Blocked URL Patterns

ใส่ pattern ทีละบรรทัด รองรับ wildcard:

```
wp-*
*.php
test*page
```

- `*` = match ตัวอักษรอะไรก็ได้ (0 ตัวขึ้นไป)
- `?` = match ตัวอักษรเดียว

### Featured Image Styles

| Style | คำอธิบาย |
|-------|---------|
| `professional` | สไตล์ corporate สะอาด สีสงบ |
| `illustration` | ดิจิตอลอาร์ต สร้างสรรค์ |
| `realistic` | แนว photo-realistic |
| `minimalist` | เรียบง่าย สีน้อย |
| `vibrant` | สีสด สดใส พลังงานสูง |

---

## WordPress Hooks

### Action Hooks ที่ใช้

| Hook | คำอธิบาย |
|------|---------|
| `plugins_loaded` | เริ่มต้น Plugin |
| `admin_menu` | เพิ่มเมนู Settings |
| `admin_init` | ลงทะเบียน settings |
| `admin_enqueue_scripts` | โหลด admin CSS/JS |
| `template_redirect` | ดักจับ 404 |
| `wp_ajax_aiapg_generate_post` | AJAX สร้าง Post (authenticated) |
| `wp_ajax_nopriv_aiapg_generate_post` | AJAX สร้าง Post (public) |
| `wp_ajax_aiapg_test_api` | AJAX ทดสอบ API |
| `wp_ajax_aiapg_test_intercept` | AJAX ทดสอบ interception |

### Custom Hooks

```php
do_action('aiapg_post_created', int $post_id, string $keyword, array $content);
```

เรียกหลังจากสร้าง Post สำเร็จ — สามารถใช้ hook นี้เพื่อ:
- ส่ง notification
- บันทึก log
- เชื่อมต่อกับระบบอื่น
- Trigger social media sharing

### Filter Hooks ที่ใช้

| Filter | คำอธิบาย |
|--------|---------|
| `plugin_action_links_*` | เพิ่มลิงก์ Settings ในหน้า Plugins |

---

## ความเข้ากันได้กับ SEO Plugins

Plugin สามารถบันทึก SEO metadata ให้ compatible กับ SEO plugins ยอดนิยม:

| SEO Plugin | Meta Fields ที่บันทึก |
|------------|----------------------|
| **Yoast SEO** | `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_focuskw` |
| **Rank Math** | `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`, `rank_math_robots` |
| **All in One SEO** | `_aioseo_title`, `_aioseo_description`, `_aioseo_keyphrases` (JSON) |

เพิ่มเติม: บันทึก Schema.org Article JSON-LD ใน `_aiapg_schema_data`

---

## ระบบความปลอดภัย

### Input Validation

| เทคนิค | ใช้กับ |
|--------|-------|
| `sanitize_text_field()` | Text inputs |
| `sanitize_textarea_field()` | Textarea inputs |
| `absint()` | Numbers |
| `wp_kses_post()` | HTML content (custom prompts) |
| Nonce verification | AJAX requests |
| `manage_options` capability | Admin-only actions |

### Keyword Filtering

- ตัวอักษรขั้นต่ำ 3 ตัว
- ต้องมีสระ (ป้องกัน random code)
- ตัวเลขต้องไม่มากกว่าตัวอักษร
- ลบ code patterns: MD5, SHA hash, Base64, Hex, URL encoded
- Block file extensions: `.php`, `.js`, `.css`, `.html`, `.xml`, `.json`, ฯลฯ
- Block WordPress paths: `wp-admin`, `wp-content`, `wp-includes`, ฯลฯ
- Block hex strings, numeric-only strings

### Rate Limiting

- จำกัดจำนวน Post ต่อวัน (ค่าเริ่มต้น: 50)
- ใช้ WordPress Transients (หมดอายุทุกวัน)

### Duplicate Prevention

- ตรวจสอบว่ามี Post ที่มี slug ตรงกับ keyword อยู่แล้วหรือไม่
- ลบ transient หลังใช้งาน ป้องกัน duplicate processing

---

## การแก้ปัญหา

### Plugin ไม่ทำงาน

1. ตรวจสอบว่า Master Switch เปิดอยู่
2. ตรวจสอบว่าใส่ API Key ถูกต้อง
3. กดปุ่ม "Test Connection" ในหน้า Settings
4. ใช้ "Test & Debug" section จำลองการสร้าง Post

### Post ไม่ถูกสร้าง

- ตรวจสอบ rate limit (ดูในส่วน Statistics)
- ตรวจสอบว่า keyword ไม่อยู่ใน blocked list
- ตรวจสอบว่าไม่มี Post ที่มี slug เดียวกันอยู่แล้ว
- ดู error log ของ WordPress (`wp-content/debug.log`)

### AI API Error

- ตรวจสอบ API Key ว่ายังใช้งานได้
- ตรวจสอบว่ามี credit/quota เหลือใน AI provider
- ตรวจสอบว่า model ที่เลือกยังพร้อมใช้งาน

### Featured Image ไม่แสดง

- ต้องใช้ OpenAI API Key (DALL-E 3) สำหรับสร้างรูปภาพ
- Provider อื่น (Claude, Gemini) ไม่รองรับสร้างรูป → ต้องมี OpenAI Key เป็น fallback
- ตรวจสอบว่า `enable_featured_image` เปิดอยู่

### Debug Log

Plugin จะเขียน debug log ไปที่ `error_log` ของ WordPress:
- `AIAPG Debug: intercept_404 called`
- `AIAPG Debug: plugin_enabled = ...`
- `AIAPG Debug: Extracted keyword = ...`
- `AIAPG: Keyword has less than 3 letters: ...`
- `AIAPG: Keyword blocked by user settings: ...`

เปิด debug mode ใน `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```
