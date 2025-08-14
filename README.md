
# QA 測試進度追蹤系統

一個以 **HTML + TailwindCSS + SortableJS** 建構的看板工具，用來追蹤軟體測試進度；後端以 **PHP (PDO) + MySQL** 提供 REST 風格 API（`db.php`）。支援新增/編輯/刪除、跨欄拖曳排序、關鍵字搜尋、日期挑選，以及「已修正」標記。

> 本 README 由系統原始碼（`index.html`, `db.php`）分析整理而成。

---

## 功能特色

- 🧭 四欄看板：**尚未測試**、**正在進行**、**已完成測試**、**已完成複測**
- 🧲 **拖曳排序 / 跨欄移動**（使用 SortableJS）
- ➕ **新增卡片**、✏️ **編輯卡片**、🗑️ **刪除卡片**
- 🔎 **即時搜尋**（依描述/區塊文字）
- 📅 **日期欄位**（便於安排與篩選）
- ✅ **已修正 (fixed)** 勾選標記
- 🔢 各欄 **計數器**
- 🌐 已開啟 **CORS**（預設 `*`，開發時方便、正式環境請酌量調整）

---

## 系統架構

- 前端：`index.html`
  - [TailwindCSS CDN](https://cdn.tailwindcss.com)
  - [Font Awesome](https://cdnjs.com/libraries/font-awesome)
  - [SortableJS](https://sortablejs.github.io/Sortable/)
- 後端：`db.php`
  - PHP 7.4+（建議 8.x）
  - PDO MySQL 擴充
  - 提供 `GET / POST / PUT / PATCH / DELETE` 五種方法
- 資料庫：MySQL (`qa_system`)

---

## 快速開始

### 1) 需求

- PHP 7.4+（含 PDO MySQL）
- MySQL 5.7+ / MariaDB 10.3+
- 瀏覽器（Chrome/Edge/Firefox/Safari 皆可）

### 2) 下載/安裝

```bash
git clone https://github.com/Peter0731/qa_system.git
cd qa_system
```

### 3) 建立資料庫與資料表

```sql
CREATE DATABASE IF NOT EXISTS `qa_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `qa_system`;

CREATE TABLE `cards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section` VARCHAR(255) NOT NULL COMMENT '功能/模組/區塊',
  `description` TEXT NOT NULL COMMENT '描述/待驗證內容',
  `status` ENUM('not-started','in-progress','completed','retested') NOT NULL DEFAULT 'not-started' COMMENT '看板狀態',
  `date` DATE NULL COMMENT '日期（可選）',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '同欄位內的排序',
  `fixed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已修正',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> **說明**：`status` 對應前端四個欄位：`not-started`、`in-progress`、`completed`、`retested`；`sort_order` 由拖曳排序更新；`fixed` 由「已修正」勾選控制。

### 4) 設定資料庫帳密

打開 `db.php`，填入：

```php
$dsn  = 'mysql:host=localhost;dbname=qa_system;charset=utf8mb4';
$user = '你的帳號';
$pass = '你的密碼';
```

### 5) 啟動開發伺服器

直接用 PHP 內建伺服器（確保目前在專案目錄）：

```bash
php -S 0.0.0.0:8000
```

瀏覽器開啟 <http://localhost:8000> 即可。

---

## 使用說明

- 右上角點「新增」可建立卡片（需輸入 **測試章節**、**測試描述**、可選 **日期** 與 **測試狀態**，以及 **已修正**）
- 直接 **拖曳卡片** 可在同欄排序；放開後會自動呼叫 API 同步排序與狀態
- 可在搜尋框輸入關鍵字，立即濾出內容
- 點卡片可進入編輯模式；垃圾桶圖示可刪除

---

## REST API 說明（`db.php`）

所有請求/回應皆為 `application/json`。已開啟 CORS（`*`），並允許 `GET, POST, PUT, DELETE, PATCH, OPTIONS`。

### 1) 取得卡片列表

**GET** `/db.php`

**Response 200**
```json
[
  {
    "id": 1,
    "section": "登入/註冊",
    "description": "密碼錯誤訊息未對齊",
    "status": "in-progress",
    "date": "2025-08-14",
    "sort_order": 0,
    "fixed": false
  }
]
```

---

### 2) 新增卡片

**POST** `/db.php`

**Body**
```json
{
  "section": "訂單",
  "description": "金額四捨五入錯誤",
  "status": "not-started",
  "date": "2025-08-14",
  "fixed": false
}
```

**Response 200**
> 伺服器會補上 `id`、`created_at`，並將 `fixed` 轉為布林值。
```json
{
  "section": "訂單",
  "description": "金額四捨五入錯誤",
  "status": "not-started",
  "date": "2025-08-14",
  "fixed": false,
  "id": 5,
  "created_at": "2025-08-14 12:00:00"
}
```

---

### 3) 更新卡片

**PUT** `/db.php`

**Body**
```json
{
  "id": 5,
  "section": "訂單",
  "description": "金額四捨五入錯誤（邏輯修正後需回歸）",
  "status": "completed",
  "date": "2025-08-14",
  "fixed": true
}
```

**Response 200**
```json
{ "success": true }
```

---

### 4) 拖曳排序

**PATCH** `/db.php`

**Body**
```json
{
  "status": "in-progress",
  "order": [12, 5, 7, 3]
}
```

- `status`：當前欄的狀態值（四選一）
- `order`：該欄位內自上而下的卡片 `id` 陣列；伺服器會依索引更新 `sort_order`

**Response 200**
```json
{ "success": true }
```

**Response 200（缺參數）**
```json
{ "error": "缺少參數" }
```

---

### 5) 刪除卡片

**DELETE** `/db.php?id=5`

**Response 200**
```json
{ "success": true }
```

---

## cURL 範例

```bash
# 取得
curl -s http://localhost:8000/db.php | jq .

# 新增
curl -s -X POST http://localhost:8000/db.php \
  -H 'Content-Type: application/json' \
  -d '{"section":"帳號","description":"UI字串錯誤","status":"not-started","date":"2025-08-14","fixed":false}'

# 更新
curl -s -X PUT http://localhost:8000/db.php \
  -H 'Content-Type: application/json' \
  -d '{"id":1,"section":"帳號","description":"文案已調整","status":"completed","date":"2025-08-14","fixed":true}'

# 排序
curl -s -X PATCH http://localhost:8000/db.php \
  -H 'Content-Type: application/json' \
  -d '{"status":"in-progress","order":[3,2,1]}'

# 刪除
curl -s -X DELETE 'http://localhost:8000/db.php?id=1'
```

---

## 專案結構

```
qa_system/
├─ index.html    # 前端頁面（Tailwind + SortableJS）
└─ db.php        # PHP REST API（PDO MySQL）
```