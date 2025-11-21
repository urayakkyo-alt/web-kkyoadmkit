=== WP-KkyoAdmKit Enhanced ===
Contributors: honkitamc
Tags: contact form, form builder, review, security, captcha
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

高機能お問い合わせフォームビルダー、レビューシステム、セキュリティ機能を搭載したWordPress拡張プラグイン

== Description ==

WP-KkyoAdmKit Enhancedは、WordPressサイトに包括的なフォーム管理とセキュリティ機能を追加するプラグインです。

### 主な機能

**📝 ドラッグ&ドロップフォームビルダー**
* 直感的なインターフェースでお問い合わせフォームを構築
* テキスト、メール、電話番号、テキストエリア、セレクトボックス、チェックボックス、ラジオボタン、ファイルアップロードに対応
* 各フィールドのラベル、必須設定、オプションをカスタマイズ可能

**🔒 多様なCAPTCHA対応**
* Google reCAPTCHA v2
* Google reCAPTCHA v3
* hCaptcha
* Cloudflare Turnstile

**⭐ レビューシステム**
* 5段階評価機能
* 承認制レビュー投稿
* カスタム投稿タイプで管理

**👤 ユーザー登録フォーム**
* セキュアなユーザー登録
* AJAX非使用の安全な実装

**💬 コメントいいね機能**
* AJAX対応のリアルタイムいいね
* IPアドレスベースの重複防止
* 簡単な統合

**🖼️ No Image設定**
* アイキャッチ画像未設定時のデフォルト画像
* 投稿タイプにのみ適用
* メディアライブラリから簡単選択

**🛡️ セキュリティ機能**
* ログイン試行回数制限
* IPアドレスベースの自動ブロック（1時間）
* ブロック解除機能
* レート制限機能

**📧 メール通知**
* お問い合わせ受信時に管理者へ自動通知
* カスタマイズ可能なメール内容

**📎 ファイルアップロード**
* セキュアなファイル検証
* 拡張子とファイルサイズチェック
* メディアライブラリへの自動保存
* 許可拡張子: jpg, jpeg, png, gif, pdf, doc, docx, zip

**🎨 Gutenbergブロック対応**
* エディタから簡単挿入
* ブロック設定パネル
* ショートコードとブロック両対応

== Installation ==

### 自動インストール

1. WordPress管理画面で「プラグイン」→「新規追加」
2. 「WP-KkyoAdmKit Enhanced」を検索
3. 「今すぐインストール」をクリック
4. 「有効化」をクリック

### 手動インストール

1. プラグインファイルをダウンロード
2. `/wp-content/plugins/wp-kkyoadmkit-enhanced/`ディレクトリにアップロード
3. WordPress管理画面の「プラグイン」メニューからプラグインを有効化

### 初期設定

1. 管理画面の「KkyoAdmKit」メニューを開く
2. CAPTCHA設定でサイトキーとシークレットキーを入力
3. フォームビルダーでお問い合わせフォームをカスタマイズ
4. 必要に応じてNo Image画像を設定

== Frequently Asked Questions ==

= フォームをページに追加するには？ =

以下の方法があります：

**ショートコード:**
* `[kkyoadm_register_form]` - ユーザー登録フォーム
* `[kkyoadm_review_form]` - レビューフォーム
* `[kkyoadm_contact_form]` - お問い合わせフォーム

**Gutenbergブロック:**
エディタでブロックを追加し、「KkyoAdmKit」カテゴリから選択

= CAPTCHAの設定方法は？ =

1. 各CAPTCHAサービスでサイトキーとシークレットキーを取得
   * reCAPTCHA: https://www.google.com/recaptcha/admin
   * hCaptcha: https://www.hcaptcha.com/
   * Turnstile: https://dash.cloudflare.com/
2. 管理画面「KkyoAdmKit」→「設定」でキーを入力
3. フォームビルダーでCAPTCHAを有効化

= ファイルアップロードのセキュリティは？ =

以下のセキュリティ対策を実施しています：
* 拡張子の厳格なチェック
* ファイルサイズ制限（最大5MB）
* MIMEタイプ検証
* WordPress標準のアップロード処理を使用
* メディアライブラリへの安全な保存

= お問い合わせ内容はどこで確認できますか？ =

管理画面の「KkyoAdmKit」→「お問い合わせ」から一覧表示できます。
また、投稿管理画面でも「お問い合わせ」として確認可能です。

= レビューの承認方法は？ =

投稿管理画面の「レビュー」から各レビューを編集し、
ステータスを「公開」に変更してください。

= IPブロックを解除するには？ =

管理画面の「KkyoAdmKit」→「セキュリティ」から
ブロックされたIPアドレス一覧で「ブロック解除」ボタンをクリックします。

== Screenshots ==

1. ドラッグ&ドロップフォームビルダー
2. お問い合わせフォーム一覧画面
3. セキュリティ設定画面
4. No Image設定画面
5. Gutenbergブロック挿入

== Changelog ==

= 2.0.0 =
* 新機能: ドラッグ&ドロップフォームビルダー
* 新機能: 多様なCAPTCHA対応（reCAPTCHA v2/v3, hCaptcha, Turnstile）
* 新機能: No Image設定機能
* 新機能: コメントいいね機能
* 改善: セキュアなファイルアップロード実装
* 改善: ユーザー登録フォームをAJAX非使用に変更
* 改善: お問い合わせ送信時の管理者メール通知
* 改善: 管理画面デザインの改善
* 改善: Gutenbergブロックの正式実装
* 改善: 詳細なコメント追加
* セキュリティ: レート制限の実装
* セキュリティ: 入力検証の強化
* セキュリティ: CSRF保護の強化

= 1.13 =
* 初版リリース
* 基本的なフォーム機能
* セキュリティ機能

== Upgrade Notice ==

= 2.0.0 =
メジャーアップデート。フォームビルダー、CAPTCHA対応、セキュリティ強化など多数の新機能を追加。

== Additional Info ==

**開発者向け情報**

このプラグインは単一のPHPファイルで構成されており、
シンプルな構造で拡張やカスタマイズが容易です。

**フィルターフック:**
* `kkyoadmkit_allowed_file_extensions` - アップロード許可拡張子
* `kkyoadmkit_max_file_size` - 最大ファイルサイズ

**アクションフック:**
* `kkyoadmkit_after_contact_submit` - お問い合わせ送信後
* `kkyoadmkit_after_review_submit` - レビュー投稿後

**サポート**

公式サイト: https://wp-kkyoadmkit.42web.io

**貢献**

バグ報告や機能リクエストは公式サイトまでお願いします。

**ライセンス**

このプラグインはGPL v2以降のライセンスで配布されています。

== Privacy Policy ==

このプラグインは以下のデータを収集・保存します：

* お問い合わせフォーム送信時: 名前、メールアドレス、メッセージ内容、IPアドレス
* レビュー投稿時: タイトル、内容、評価、IPアドレス
* ログイン失敗時: IPアドレス、試行回数
* コメントいいね時: IPアドレス

収集されたデータはWordPressデータベースに保存され、
サイト管理者のみがアクセス可能です。
第三者への提供は行いません。

ファイルアップロード機能を使用する場合、
アップロードされたファイルはメディアライブラリに保存されます。