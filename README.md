# bids-rtc

BIDS WebRTC Backend

## プロジェクト構成

このプロジェクトは、WebRTC シグナリングサーバーとフロントエンドアプリケーションで構成されています。

### ディレクトリ構造

- `dev/signaling-api/` - バックエンド API
  - PHP ベースの Signaling API + 管理者用 API
  - `/admin/logs` - 管理者用ログ取得エンドポイント
- `dev/frontend/` - フロントエンドアプリケーション
- `spec/` - OpenAPI 仕様

## 起動方法

```bash
docker-compose up -d
```

サービスへのアクセス:

- フロントエンド: http://localhost
- バックエンド API: http://localhost:8080/signaling
- phpMyAdmin: http://localhost:8081
- Firebase Emulator: http://localhost:4000

## 管理者機能

### ログ閲覧

管理者権限を持つユーザーは、フロントエンドから以下の機能にアクセスできます：

1. **ログビューア** (`/admin/logs`)
   - サーバーログファイルの一覧表示
   - ログ内容の閲覧（最新 N 行を取得可能）
   - 対応ログファイル：
     - `app.log`, `slim-app.log` - アプリケーションログ
     - `access.log`, `error.log` - Apache ログ
     - `php-error.log` - PHP エラーログ
     - 日付付きログファイル（例: `app.2025-02-16.log`）

### 権限管理

- バックエンド: `MyAuthMiddleware::getIsAdminRole()` でロール確認
- フロントエンド: `useIsAdmin()` フックで管理者判定
- 管理者ロールは Firebase Auth の Custom Claims で `role: "admin"` を設定

## API 開発フロー

### PHP コードから OpenAPI 仕様を生成

```bash
cd dev/signaling-api
php generate-openapi-spec.php
```

生成された JSON 仕様は `spec/signaling-api/openapi.generated.json` に保存されます。

## 開発

### バックエンド

- 言語: PHP 8.x
- フレームワーク: Slim Framework
- 認証: Firebase Auth + JWT

### フロントエンド

- 言語: TypeScript
- フレームワーク: React + Vite
- UI: Material-UI
- 状態管理: カスタムフック

## 注意事項

- 管理者ロールの設定は Firebase Admin SDK で行います。
- ログファイルへのアクセスは管理者のみに制限されています（フロント・バック両方で制御）。
