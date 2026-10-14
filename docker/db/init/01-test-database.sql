-- Pest の RefreshDatabase が開発用 DB を消さないよう、テスト専用 DB を分ける。
-- init は新規ボリュームでしか実行されない。
CREATE DATABASE IF NOT EXISTS novel_submission_site_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON novel_submission_site_test.* TO 'novel_submission_site'@'%';
FLUSH PRIVILEGES;
