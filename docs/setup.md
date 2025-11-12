# 環境設定與啟動指南

1.  **複製環境變數:** \`cp .env.example .env\`
2.  **啟動服務:** \`docker compose up -d --build\`
3.  **Laravel 初始化:** 進入 php-fpm 容器執行 \`php artisan key:generate\` 和 \`php artisan migrate\`。
4.  **DB 配置:** 執行 \`./scripts/init_all_dbs.sh\` 並手動完成 ProxySQL 和 MySQL 複製設定。
