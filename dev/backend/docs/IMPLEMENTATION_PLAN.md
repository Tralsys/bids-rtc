# 不足実装の完成 — 作業計画

`feature/admin-api` ブランチで、`dev/backend/` の未実装・不整合をすべて潰し切るための計画。

---

## 0. 現状サマリ

`IMPLEMENTATION_SUMMARY.md` は完成扱いだが、以下が未実装/不整合のまま残っている。

| 区分 | 対象 | 状態 |
| --- | --- | --- |
| 機能未実装 | `SDPExchangeController` の 4 エンドポイント (`/offer`, `/answer`, `/answer/{sdpId}`, `/exchange/{sdpId}`) | 全て 501 を返すスタブ |
| 機能未実装 | `ClientManagementService::getClientAccessToken` | 501 を投げるスタブ |
| 設計不在 | `SDPExchangeService` / `SdpTableRepository` / `SDPEncryptAndDecrypt` / SDP 関連 DB モデル | クラス自体が無い |
| 既知バグ | `ClientTableRepository` が `refresh_token_hash` 列を参照 (DB の実カラムは `refresh_token`) | `selectOneRefreshToken` / `createNewClient` が DB エラーになる |
| 仕様乖離 | 現 `SDPExchangeController` の OA 注釈と `spec/signaling-api/` の OpenAPI 仕様が複数箇所で不一致 | 詳細 §2 |
| 仕様乖離 | `getClientAccessToken` が `getClientInfoList` 等と並列で `ClientManagementService` 共通プロパティ (`hashedUserId` 等) を内部で書き換えている | リクエスト跨ぎでは問題ないが、将来の単一 service 共有時に踏むので明示分離 |
| 配線不足 | `SDPExchangeService` の DI 登録、`routes.php` での DI 経由生成、Auth Middleware を SDP 系ルートに付ける配線 | 未着手 |
| 仕様確認 | フロントエンド (`dev/frontend/project/src/`) 側に TODO/未実装は無し (grep clean) | 対応不要 |

---

## 1. スコープと前提

### スコープ (このブランチでやる)

- バックエンド (PHP, `dev/backend/`) 側の上記未実装を全て埋める。
- DB スキーマ (`dev/db/init_sql/0_create_db.sql`) は現状維持。コードを DB に合わせる。
- 仕様 (`spec/signaling-api/`) を「真」とし、コントローラの OA 注釈と実装をそちらに揃える。
- DI 配線・ルート配線・Auth Middleware の付与までを完成として一連で行う。
- 動作確認は `composer phplint` / `composer generate-openapi` の通過まで。実 DB を立てた end-to-end テストはこの計画外。

### 非スコープ (別ブランチ)

- フロントエンド (`dev/frontend/project/`) の追加機能。
- `signaling-api-ts` への変更。
- 既存 `signaling-api-old/` の整理 (commit `03541a4` で `signaling-api` は既に削除済、`-old` は当面参照用に温存)。
- ユニットテスト・統合テストの新規追加 (型 + lint で回す)。
- 暗号鍵 (`config/dev/jwt-private.pem` 等) の生成手順整備 — 既存運用に依存。

### 前提 (確認済)

- `Lcobucci\JWT\Configuration` は `config/dependencies.php:90` で DI 済 → JWT 発行/検証はそのまま使える。
- `AuthMiddleware` は Firebase ID トークンを検証して `uid` / `userRole` を request 属性にセット (Bearer header)。一方 `getClientAccessToken` は **JOSE 形式 refresh token を body 直書き**、自前 JWT 検証に切り替え。混在が正しい設計 (旧 API も同じ)。
- SDP 系エンドポイントは Firebase Bearer ではなく、 **`/client_token` で発行された自前アクセストークン** で認可される想定。これは旧 API 仕様 + `MyAuthCheckResult::KEY_TYPE_ACCESS` の流れと一致。**新 backend の `AuthMiddleware` は Firebase 専用なので、自前 JWT を解釈できる第二の Middleware が必要**。これを `MyJwtAuthMiddleware` として新規追加する。
- 仕様 `ClientIdHeader` (`X-Client-Id`) は SDP 系で必須。アクセストークン内 claim と一致するか検証する。

---

## 2. 仕様乖離 — 何にどう揃えるか

`spec/signaling-api/` を正として現コードを書き換える。

### 2.1 `POST /offer`

| 項目 | 現コード (`SDPExchangeController` OA注釈) | 仕様 (`paths/signaling/offer.yaml`) | 採用 |
| --- | --- | --- | --- |
| ClientId 渡し方 | body 内 `offer_client_id` | header `X-Client-Id` | 仕様 (header) |
| body | `{offer_client_id, offer}` | `{role, offer, established_clients?}` (`PostSDPOfferInfoRequestBody`) | 仕様 |
| レスポンス | `{sdp_id}` のみ | `{registered_offer?: SDPOfferInfo, received_offers?: SDPOfferInfoArray}` (`PostSDPOfferInfoResponse`) | 仕様 |

→ `Model/SDPOfferInfo.php` を仕様 `SDPOfferInfo` (`sdp_id, offer_client_id, offer_client_role, created_at, offer`) に書き換え、`Model/PostSDPOfferInfoRequestBody.php` 新規作成、`Model/PostSDPOfferInfoResponse.php` を `{registered_offer, received_offers}` に書き換え。

### 2.2 `POST /answer`

| 項目 | 現コード | 仕様 | 採用 |
| --- | --- | --- | --- |
| ClientId | body 内 `answer_client_id` | header `X-Client-Id` | 仕様 |
| body | 単一 `{sdp_id, answer_client_id, answer}` | 配列 `SDPAnswerInfoArray` (各要素 `{sdp_id, answer}` ベース) | 仕様 (配列受け) |

→ `Model/SDPAnswerInfo.php` を `{sdp_id, answer_client_id?, answer}` に整える (answer_client_id は読み取り専用)。受信時は `[{sdp_id, answer}]` の配列を扱う。

### 2.3 `GET /answer/{sdpId}` / `DELETE /exchange/{sdpId}`

仕様通り。`X-Client-Id` ヘッダ必須。レスポンスは現 OA 注釈と仕様で一致しているのでそのまま。

### 2.4 認証

- Firebase Bearer 必須なエンドポイント: `/apps*`, `/clients*`, `/admin/*`
- 自前 JWT (KEY_TYPE_ACCESS) 必須なエンドポイント: `/offer`, `/answer`, `/answer/{sdpId}`, `/exchange/{sdpId}`
- どちらも要らない (raw refresh token 自前検証): `PUT /client_token`
- `/` (`getApiInfo`): 認証なし

---

## 3. 実装フェーズ

依存順に並べる。各フェーズで `composer phplint` / `composer generate-openapi` を通す。

### Phase 1 — 既知バグ修正と基盤整備 (短時間で潰す)

1. **`ClientTableRepository` のカラム名修正**
   - `refresh_token_hash` → `refresh_token` (DB 実カラムに合わせる)
   - 影響: `selectOneRefreshToken`, `createNewClient`
   - メソッド名 `selectOneRefreshToken` は維持 (戻り値はハッシュ済み文字列)
   - 引数名 `$refreshTokenHash` も維持 (意味的にハッシュ済みを受ける)

2. **`AuthMiddleware` の戻り値処理確認**
   - 現状でも `$role` の構造的取り扱いは OK。修正不要。

3. **`Constants` への追加**
   - `HEADER_CLIENT_ID = 'X-Client-Id'` 追加
   - `ATTR_NAME_CLIENT_ID` は既にある — そのまま使う

4. **`Utils` への追加**
   - `getClientIdFromHeaderOrNull(ServerRequestInterface): ?UuidInterface`
   - `withHeaderClientIdError(ResponseInterface): ResponseInterface` (400 / `Invalid X-Client-Id header`)
   - `dbDateStrToDateTime(string $s): \DateTime` (`DATETIME(6)` 対応含む。`SdpRecord` で必要)
   - `uuidFromBytesOrNull(?string $bytes): ?UuidInterface`

### Phase 2 — `getClientAccessToken` の本実装

`ClientManagementService::getClientAccessToken` を実装。

- DI 経由で `Lcobucci\JWT\Configuration` と `my-auth.issuer` を `ClientManagementService` のコンストラクタに注入できるように `dependencies.php` を修正。
  - `\BidsRtc\Backend\Service\ClientManagementService::class => DI\autowire()` を `DI\autowire()->constructorParameter('issuer', DI\get('my-auth.issuer'))` に変更。
- `Lcobucci\JWT\Configuration::setValidationConstraints(SignedWith, IssuedBy)` を初期化。
- フロー:
  1. body から refresh token (raw 文字列) を読む。
  2. `Configuration::parser()->parse()` で JWT パース。失敗→ 400。
  3. `Configuration::validator()->validate(...)` で署名 + issuer 検証。失敗→ 401。
  4. `iat` 期限と `typ === KEY_TYPE_REFRESH` を確認 (refresh token は無期限なので `exp` なし)。
  5. `sub`(uid) / `app_id` / `client_id` claim を取り出し UUID 化。
  6. `ClientTableRepository::selectOneRefreshToken(hashed_uid, client_id)` でハッシュ済み refresh token を取得。
  7. `password_verify($rawRefreshToken, $stored)` を確認。失敗→ 401。
  8. アクセストークン (`typ = access`, `exp = now + 1h`) を発行して `application/jose` で返す。

- 公開メソッド (新規): `Service/MyJwtUtil.php` を新規作成し、JWT パース/生成 (アクセス/リフレッシュ) をここに集約。
  - `parseAndValidate(string): MyJwtClaims` (失敗時 `RetValueOrError`)
  - `issueAccessToken(uid, appId, clientId): string`
  - `issueRefreshToken(uid, appId, clientId): string`
  - 旧 `MyAuthUtil` の責務を新名前空間に移植。Firebase 部分は持ち込まない。
- `ClientManagementService::registerClientInfo` の refresh token 生成も `MyJwtUtil::issueRefreshToken()` 経由に切り替える (現状 `bin2hex(random_bytes(32))` で署名なし — 旧 API 互換のため JWT に統一)。
- `MyJwtClaims` を value object として `Service/Auth/MyJwtClaims.php` に置く (`uid, appId, clientId, keyType: 'access'|'refresh', issuedAt`).

### Phase 3 — SDP 関連の Model 整備

仕様に揃えて model を追加/書き換える。`Model/Sdp/` サブディレクトリ運用も検討したが、既存 model がフラットなので踏襲。

| ファイル | 内容 | 種別 |
| --- | --- | --- |
| `Model/SDPOfferInfo.php` | 書き換え。`{sdp_id, offer_client_id, offer_client_role, created_at, offer}` に。`JsonSerializable`。 | 修正 |
| `Model/PostSDPOfferInfoRequestBody.php` | 新規。`{role, offer, established_clients?}`。 | 新規 |
| `Model/PostSDPOfferInfoResponse.php` | 書き換え。`{registered_offer?: SDPOfferInfo, received_offers?: SDPOfferInfo[]}`。 | 修正 |
| `Model/SDPAnswerInfo.php` | `{sdp_id, answer_client_id, answer}` のまま (仕様一致)。serializer 側で `answer_client_id` が GET 結果用、`{sdp_id, answer}` が POST 入力用と区別できるよう `from*` ファクトリを足す。 | 微修正 |
| `Model/DbSdpRecord.php` | 新規。DB 1 行を保持し、`toApiOfferInfo($encDec): SDPOfferInfo` を生成。`offer`/`answer` は復号後に格納。 | 新規 |
| `Model/DbSdpAnswer.php` | 新規。`{sdp_id, answer_client_id, protected_answer}`。 | 新規 |
| `Model/SdpIdAndAnswer.php` | 新規。register 入力 1 件分。 | 新規 |
| `Model/SdpRoles.php` | 新規 enum 風 `final class`。`PROVIDER='provider'`, `SUBSCRIBER='subscriber'`, `targetOf(string $role): string`。 | 新規 |

### Phase 4 — 暗号化ユーティリティ

`Service/SDPEncryptAndDecrypt.php` を新規作成。旧 `signaling-api-old/src/service/SDPEncryptAndDecrypt.php` をほぼそのまま移植 (AES-256-CBC, key = sha256(rawUserId) raw, IV ランダム 16 byte 先頭 prepend)。

- 名前空間 `BidsRtc\Backend\Service`
- `decrypt` 失敗時に `RetValueOrError(500, ...)` を投げず単に `null` 返却し、上位でログ + null 扱い (現行と同じ)。

### Phase 5 — `SdpTableRepository` 新規実装

`Repository/SdpTableRepository.php` を新規作成。旧 `SdpTableRepo.php` をベースに、新 backend のコーディング規約 (タブインデント・PSR-12・`UtcClock` 不使用) に合わせて移植。

メソッド一覧 (戻り値型まで明記):

- `selectOne(UuidInterface $sdpId, string $hashedUserId, UuidInterface $offerClientId): ?DbSdpRecord`
- `getAnswer(UuidInterface $sdpId, string $hashedUserId, UuidInterface $offerClientId): ?DbSdpAnswer`
- `insertOffer(string $hashedUserId, UuidInterface $offerClientId, string $role, string $protectedOffer): ?UuidInterface`
- `setOfferAsProcessing(string $hashedUserId, string $targetRole, UuidInterface $answerClientId, array $excludeOfferClientIds, int $checkMinutes): int`
- `unsetOfferAsProcessing(string $hashedUserId, UuidInterface $answerClientId): int`
- `getOfferListWithAnswerId(string $hashedUserId, UuidInterface $answerClientId): array`
- `setAnswer(string $hashedUserId, UuidInterface $sdpId, UuidInterface $answerClientId, string $protectedAnswer): int`
- `deleteRecord(string $hashedUserId, UuidInterface $sdpId, UuidInterface $clientId): int`
- `deleteRecordByClientId(string $hashedUserId, UuidInterface $clientId): int`

注意:
- `setOfferAsProcessing` の WHERE 句 `recent_answers` LEFT JOIN は旧 API のロジックそのまま (1 client につき同時 1 つしか processing にしない仕掛け)。
- 旧 `isOfferExistsInRange` は旧コードでも未使用 + SQL 構文壊れている — **移植しない**。
- `created_at` の `DATETIME(6)` は `Y-m-d H:i:s.u` フォーマットでパース。

### Phase 6 — `SDPExchangeService` 新規実装

`Service/SDPExchangeService.php` を新規作成。

- 状態保持メンバ: `rawUserId`, `hashedUserId`, `clientId`, `encryptAndDecrypt` (旧と同様、リクエストごとの DI コンテナで生成されるためインスタンス共有問題なし)。
- `setUserIdAndClientId(ServerRequestInterface, ResponseInterface): ?ResponseInterface`
  - `Utils::getUserIdOrNull()` で `uid` 取得 (なければ 401)。
  - `Utils::getClientIdFromHeaderOrNull()` で `X-Client-Id` 取得 (なければ 400)。
  - **加えて、`MyJwtAuthMiddleware` がセットしたアクセストークン内 `client_id` claim と header の一致を検証** (一致しない→ 403)。これは旧仕様にはなかったが、明示しないと「ヘッダ詐称」を許してしまうため追加。`Constants::ATTR_NAME_CLIENT_ID_FROM_TOKEN` を新設。
- 公開メソッド:
  - `registerOfferAndGetAnswerableOffers(string $role, string $rawOffer, array $establishedClients): PostSDPOfferInfoResponse`
  - `registerAnswer(SdpIdAndAnswer[] $answers): void`
  - `getAnswer(UuidInterface $sdpId): ?SDPAnswerInfo` (タイムアウト時 `RetValueOrError(204, ...)` 投げる仕様は維持)
  - `deleteSDPExchange(UuidInterface $sdpId): bool`
- 旧 SDPExchangeService のロジックを移植。トランザクション/finally の rollback も同様。
- `MAX_EXEC_TIME_SEC = 15`, `SLEEP_US = 1_000_000`, `OFFER_CHECK_WITHIN_MINUTE = 60` は維持。

### Phase 7 — `SDPExchangeController` 本実装

OA 注釈を仕様に揃えつつ、4 メソッドを実装。

- コンストラクタ: `(SDPExchangeService $service, LoggerInterface $logger)`
- 各メソッドで `setUserIdAndClientId` を最初に呼ぶ。
- バリデーション:
  - base64 長 ≤ 12000
  - `established_clients.length` ≤ 100
  - role は `provider|subscriber`
  - UUID validity チェック
- 旧 `SDPExchangeApi.php` をそのまま新規流儀に移植。

### Phase 8 — Middleware: 自前 JWT 用

`Middleware/MyJwtAuthMiddleware.php` 新規。

- Firebase 用 `AuthMiddleware` と同居する (パスごとに使い分ける)。
- `Authorization: Bearer <jwt>` を読み、`MyJwtUtil::parseAndValidate` で検証。
- 成功時:
  - `Constants::ATTR_NAME_UID` ← uid
  - `Constants::ATTR_NAME_CLIENT_ID_FROM_TOKEN` ← clientId UUID 文字列
  - `'appId'` ← appId
  - `'tokenType'` ← `'access'` または `'refresh'`
- 失敗時: 401 JSON。
- アクセス系エンドポイント以外で間違ったトークンを使った場合に弾けるよう、Service 側で `tokenType === 'access'` チェック。

### Phase 9 — DI / Routes / Middleware 配線

#### `config/dependencies.php`

追加するエントリ:

```php
\BidsRtc\Backend\Service\MyJwtUtil::class => DI\autowire()
    ->constructorParameter('issuer', DI\get('my-auth.issuer')),
\BidsRtc\Backend\Service\SDPExchangeService::class => DI\autowire(),
\BidsRtc\Backend\Middleware\MyJwtAuthMiddleware::class => DI\autowire(),
```

`ClientManagementService` の autowire は `MyJwtUtil` 注入のため variant を:

```php
\BidsRtc\Backend\Service\ClientManagementService::class => DI\autowire(),
```

(MyJwtUtil 自体が DI 解決されるのでパラメータ追加不要。)

#### `config/routes.php`

- `/` のみ middleware 無し。
- Firebase Auth 系: `/apps*`, `/clients` (一覧/登録/取得/削除), `/admin/*` には `AuthMiddleware` を `add()`。
- 自前 JWT 系: `/offer`, `/answer`, `/answer/{sdpId}`, `/exchange/{sdpId}` には `MyJwtAuthMiddleware` を `add()`。
- `/client_token` のみどちらも付けない。
- SDP 系の controller は **DI コンテナから取り出す形式に統一** (現在 `new Controller\SDPExchangeController(logger: ...)` で直接生成しているところを `$container->get(...)` 経由に揃える)。
- 旧 `/client/_token` という奇妙なパス指定 (`$app->group('/client', ...)->put('_token', ...)`) を `$app->put('/client_token', ...)` に直す (仕様は `/client_token`)。

### Phase 10 — OpenAPI 再生成 + lint

```bash
cd dev/backend
composer install   # 既に入っていれば不要
composer phplint
composer generate-openapi
```

- 生成された `docs/openapi.yaml` を一度 git diff で確認し、`spec/signaling-api/openapi.yaml` の `/offer` `/answer` 等と意味的に一致しているかチェック (path・required・型まで)。

---

## 4. ファイル変更サマリ (予測)

### 新規

```
dev/backend/src/Service/MyJwtUtil.php
dev/backend/src/Service/Auth/MyJwtClaims.php
dev/backend/src/Service/SDPEncryptAndDecrypt.php
dev/backend/src/Service/SDPExchangeService.php
dev/backend/src/Repository/SdpTableRepository.php
dev/backend/src/Middleware/MyJwtAuthMiddleware.php
dev/backend/src/Model/PostSDPOfferInfoRequestBody.php
dev/backend/src/Model/DbSdpRecord.php
dev/backend/src/Model/DbSdpAnswer.php
dev/backend/src/Model/SdpIdAndAnswer.php
dev/backend/src/Model/SdpRoles.php
```

### 修正

```
dev/backend/src/Constants.php                      (header名・属性名追加)
dev/backend/src/Utils.php                          (X-Client-Id 取得 + UUID/日時ヘルパ)
dev/backend/src/Repository/ClientTableRepository.php (refresh_token カラム名修正)
dev/backend/src/Service/ClientManagementService.php  (refresh token 生成を MyJwtUtil 経由 + access token 発行実装)
dev/backend/src/Controller/ClientManagementController.php (微修正のみ)
dev/backend/src/Controller/SDPExchangeController.php (4 メソッド実装、OA 注釈書き換え)
dev/backend/src/Model/SDPOfferInfo.php              (仕様に合わせて型書き換え)
dev/backend/src/Model/SDPAnswerInfo.php             (微修正)
dev/backend/src/Model/PostSDPOfferInfoResponse.php  (仕様に合わせて構造書き換え)
dev/backend/config/dependencies.php                (新サービス・新ミドルウェア登録)
dev/backend/config/routes.php                      (Middleware 配線・/client_token パス修正・SDP routes を DI 経由に)
dev/backend/IMPLEMENTATION_SUMMARY.md             (「残りの作業」を完了済みに更新)
```

---

## 5. リスクと対策

| リスク | 対策 |
| --- | --- |
| `setOfferAsProcessing` の SQL が複雑で MariaDB 8 と挙動差異が出る可能性 | 旧 API で稼働実績ある SQL をそのまま移植。差異が出たら EXPLAIN 確認。 |
| `ClientTableRepository::createNewClient` のカラム修正で既存 dev DB の data が壊れる懸念 | 修正は INSERT 列名のみ。既存 schema との整合は取れている。dev DB を `0_create_db.sql` で再構築する運用は既にあるので、そのまま流す。 |
| 自前 JWT issuer 鍵 (`my-auth.private_key` / `public_key`) が dev 環境に存在しない場合に DI 解決時に死ぬ | `config/dev/jwt-private.pem` `jwt-public.pem` の存在は前提条件として README に追記。鍵生成手順を `IMPLEMENTATION_PLAN.md` の §6 にメモ。`Configuration::forAsymmetricSigner` は鍵不在時に lazy では無く eager に失敗するため、`DI\factory` を `DI\factory(...)` のままにしておけば、`/client_token` `/offer` 等を呼んだ時にだけ落ちる。 |
| アクセストークンに `client_id` claim を入れる仕様。Header と claim の不一致を 403 にする変更は旧 API より厳格化 | 旧 API の挙動 (Header だけ信用) を踏襲したい場合のみ後で緩める。デフォルトは厳格 (今回の方針)。 |
| OA 注釈と spec の名前ずれ (`offer_client_role` vs `role`) | 仕様 `PostSDPOfferInfoRequestBody.role` を採用。response 側 SDPOfferInfo は `offer_client_role`。 |
| 並行リクエストで `setOfferAsProcessing` が同じ offer を複数 client に割り当てる懸念 | UPDATE WHERE `answer_client_id IS NULL` で「先勝ち」になる。InnoDB の行ロックで保護されるので OK。 |

---

## 6. 鍵生成手順 (dev 環境セットアップ)

`config/dev/jwt-private.pem` `jwt-public.pem` が無い場合:

```bash
cd dev/backend/config/dev
openssl genrsa -out jwt-private.pem 2048
openssl rsa -in jwt-private.pem -pubout -out jwt-public.pem
```

`.gitignore` 済の前提。コミット禁止。

---

## 7. 完了条件

- [ ] `composer phplint` が通る
- [ ] `composer generate-openapi` が通り、`docs/openapi.yaml` が `spec/signaling-api/openapi.yaml` と意味的に一致する (path / method / required / 型レベル)
- [ ] 既存の `/apps`, `/clients`, `/admin/logs` が `AuthMiddleware` 経由で動く (回帰なし)
- [ ] `/client_token` で発行 → `/offer` → `/answer` → `/answer/{sdpId}` → `/exchange/{sdpId}` の主要フローが、コードレビューレベルで仕様一致している
- [ ] `IMPLEMENTATION_SUMMARY.md` の「残りの作業 (オプショナル)」3 項のうち 2 項 (認証・SDP 交換) が「完了」に更新されている
- [ ] 新規追加クラスはすべて `declare(strict_types=1)` 付き、PSR-12 / 既存規約 (タブインデント) 準拠

---

## 8. 順序まとめ (実装ステップ)

1. Phase 1 (バグ修正・基盤) → コミット
2. Phase 2 (`MyJwtUtil` + `getClientAccessToken` + `registerClientInfo` の refresh token JWT 化) → コミット
3. Phase 3 (Model 整備) → 4 (暗号化) → 5 (`SdpTableRepository`) を一塊で実装 → コミット
4. Phase 6 (`SDPExchangeService`) → 7 (`SDPExchangeController`) → コミット
5. Phase 8 (`MyJwtAuthMiddleware`) → 9 (DI/Routes 配線) → コミット
6. Phase 10 (OpenAPI 再生成 + lint) → コミット
7. `IMPLEMENTATION_SUMMARY.md` 更新 → コミット
8. 最終 advisor レビュー → 必要なら修正コミット
