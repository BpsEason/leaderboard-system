## API 文件

本文件描述高併發遊戲排行榜系統提供的 RESTful API。

### 基礎 URL
`http://localhost/api/v1`

### 認證
目前所有 API 端點不要求認證。**生產環境應實作 API Key 或 JWT 等認證機制**。

### 錯誤響應
所有錯誤響應均遵循以下 JSON 格式：

```json
{
  "message": "Error message details",
  "errors": {
    "field_name": [
      "Validation error message"
    ]
  }
}
```

---

## 1 分數上傳
**POST** `/scores`
上傳或更新玩家在特定遊戲中的分數。

### 請求範例

```http
POST /api/v1/scores HTTP/1.1
Host: localhost
Content-Type: application/json

{
  "player_id": 12345,
  "game_id": 1,
  "score": 1500
}
```

### 請求參數

| 參數名稱 | 類型 | 說明 | 是否必需 |
| --- | --- | --- | --- |
| player_id | integer | 玩家的唯一 ID | 是 |
| game_id | integer | 遊戲的唯一 ID | 是 |
| score | integer | 玩家獲得的分數 | 是 |

### 成功響應 201 Created

```json
{
  "message": "Score updated successfully",
  "player_score": {
    "player_id": 12345,
    "game_id": 1,
    "score": 1500,
    "updated_at": "2023-10-27T10:00:00.000000Z",
    "created_at": "2023-10-27T10:00:00.000000Z"
  }
}
```

### 錯誤響應範例 422 Unprocessable Entity

```json
{
  "message": "The game id field is required.",
  "errors": {
    "game_id": [
      "The game id field is required."
    ]
  }
}
```

---

## 2 排行榜查詢
**GET** `/leaderboards`
獲取特定遊戲的排行榜。

### 請求範例

```http
GET /api/v1/leaderboards?game_id=1&offset=0&limit=50 HTTP/1.1
Host: localhost
```

### 請求參數

| 參數名稱 | 類型 | 說明 | 是否必需 |
| --- | --- | --- | --- |
| game_id | integer | 遊戲的唯一 ID | 是 |
| offset | integer | 查詢起始位置，預設 0 | 否 |
| limit | integer | 返回記錄數量，預設 100，最大 1000 | 否 |

### 成功響應 200 OK

```json
{
  "game_id": 1,
  "leaderboard": [
    {
      "player_id": 98765,
      "score": 2500,
      "rank": 1
    },
    {
      "player_id": 12345,
      "score": 1500,
      "rank": 2
    },
    {
      "player_id": 11223,
      "score": 1200,
      "rank": 3
    }
  ],
  "offset": 0,
  "limit": 50
}
```

---

## 3 查詢玩家排名
**GET** `/leaderboards/{gameId}/player/{playerId}`
獲取特定玩家在特定遊戲排行榜中的排名和分數。

### 請求範例

```http
GET /api/v1/leaderboards/1/player/12345 HTTP/1.1
Host: localhost
```

### 路徑參數

| 參數名稱 | 類型 | 說明 |
| --- | --- | --- |
| gameId | integer | 遊戲的唯一 ID |
| playerId | integer | 玩家的唯一 ID |

### 成功響應 200 OK

```json
{
  "game_id": 1,
  "player_id": 12345,
  "rank": 2,
  "score": 1500
}
```

### 玩家未上榜響應

```json
{
  "game_id": 1,
  "player_id": 99999,
  "rank": null,
  "score": null
}
```

---

# docs/setup.md

## 環境設定與啟動指南

本文件提供在本地環境設定與啟動高併發遊戲排行榜系統的步驟。

### 1 前置條件
請先安裝下列軟體：

- **Docker Desktop**（包含 Docker Engine 與 Docker Compose）
- **Git**（用於克隆專案儲存庫）
- **PHP**（選用，若需在容器外執行 Composer）
- **Composer**（選用，若需在容器外管理 PHP 依賴）

### 2 克隆專案

```bash
git clone https://github.com/your-username/leaderboard-system.git
cd leaderboard-system
```

### 3 環境變數設定
複製範例環境變數檔並編輯：

```bash
cp .env.example .env
cp src/.env.example src/.env
```

請在 `.env` 與 `src/.env` 中填寫下列重要變數：

- **MYSQL_ROOT_PASSWORD**: MySQL root 密碼
- **MYSQL_DATABASE**: 預設資料庫名稱（例如 leaderboard_db）
- **MYSQL_APP_USER**: 應用程式連線使用者（例如 app_user）
- **MYSQL_APP_PASSWORD**: 應用程式連線密碼
- **REDIS_PASSWORD**: Redis 連線密碼
- **PROXYSQL_ADMIN_USER / PROXYSQL_ADMIN_PASSWORD**: ProxySQL 管理帳號密碼（預設 admin/admin）
- **src/.env 中的 APP_KEY**: 若為空，啟動後需執行 `php artisan key:generate`
- **DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD**: 指向 ProxySQL 服務的設定
- **REDIS_HOST / REDIS_PASSWORD / REDIS_PORT**: 指向 redis 服務的設定

### 4 構建 Docker 映像檔

```bash
docker-compose build --no-cache
```

`--no-cache` 可確保從頭構建映像，首次設定建議使用。

### 5 啟動所有服務

```bash
docker-compose up -d
```

檢查服務狀態：

```bash
docker-compose ps
```

確認服務包含 `nginx`、`php-fpm`、`mysql-master`、`mysql-slave`、`mysql-shard-1`、`mysql-shard-2`、`redis`、`proxysql` 等。

### 6 初始化資料庫與應用程式配置

執行初始化腳本以設定 MySQL 主從複製、ProxySQL 路由規則，並準備 Laravel 環境：

```bash
./scripts/init_all_dbs.sh
```

> 重要提示: 此腳本會等待資料庫與 ProxySQL 健康啟動，請耐心等待。

### 7 生成 Laravel 應用程式密鑰

若 `src/.env` 中 `APP_KEY` 為空，執行：

```bash
docker-compose exec php-fpm php artisan key:generate
```

### 8 測試 API
Nginx 監聽在 `localhost:80`，可使用 curl 測試：

**分數上傳**

```bash
curl -X POST -H "Content-Type: application/json" -d '{"player_id":1,"game_id":1,"score":100}' http://localhost/api/v1/scores
curl -X POST -H "Content-Type: application/json" -d '{"player_id":2,"game_id":1,"score":200}' http://localhost/api/v1/scores
curl -X POST -H "Content-Type: application/json" -d '{"player_id":3,"game_id":2,"score":300}' http://localhost/api/v1/scores
```

**查詢排行榜**

```bash
curl "http://localhost/api/v1/leaderboards?game_id=1"
```

**查詢玩家排名**

```bash
curl "http://localhost/api/v1/leaderboards/1/player/1"
```

### 9 執行壓力測試
使用專案內的壓力測試腳本：

```bash
./scripts/load_test.sh
```

> 注意: 此腳本可能依賴 `ab`（ApacheBench）或 `k6` 等工具，請先安裝其中之一。

### 10 停止與清理服務

```bash
docker-compose down
docker-compose down -v
```

**警告**：`docker-compose down -v` 會刪除所有資料卷，包含資料庫與 Redis 的資料，請謹慎使用。

### 11 檢查日誌

查看任一服務日誌：

```bash
docker-compose logs -f <service_name>
```

範例：

```bash
docker-compose logs -f php-fpm
docker-compose logs -f proxysql
```

---
