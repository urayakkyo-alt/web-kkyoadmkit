<?php
/**
 * Plugin Name: WP-KkyoAdmKit Enhanced
 * Plugin URI: https://wp-kkyoadmkit.42web.io
 * Description: お問い合わせフォームをドラッグ & ドロップで構築 レビューフォーム アカウント登録フォームなどからNoimage簡単設定などがぎゅとつまったプラグイン
 * Version: 2.0.0
 * Author: Urayakkyo
 * Author URI: https://wp-kkyoadmkit.42web.io
 * Text Domain: wp-kkyoadmkit-ext
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// セキュリティ: 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * メインプラグインクラス
 * シングルトンパターンで実装
 */
class WP_KkyoAdmKit_Extension {
    
    /**
     * プラグインバージョン
     */
    const VERSION = '2.0.0';
    
    /**
     * シングルトンインスタンス
     * @var WP_KkyoAdmKit_Extension|null
     */
    private static $instance = null;
    
    /**
     * アップロード許可拡張子
     * @var array
     */
    private $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip');
    
    /**
     * 最大ファイルサイズ (5MB)
     * @var int
     */
    private $max_file_size = 5242880;
    
    /**
     * シングルトンインスタンスの取得
     * @return WP_KkyoAdmKit_Extension
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     * 各種フックを登録
     */
    private function __construct() {
        // 管理画面メニュー
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // スクリプトとスタイルの登録
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // カスタム投稿タイプの登録
        add_action('init', array($this, 'register_post_types'));
        
        // Gutenbergブロックの登録
        add_action('init', array($this, 'register_blocks'));
        
        // ショートコードの登録
        add_shortcode('kkyoadm_register_form', array($this, 'render_register_form'));
        add_shortcode('kkyoadm_review_form', array($this, 'render_review_form'));
        add_shortcode('kkyoadm_contact_form', array($this, 'render_contact_form'));
        add_shortcode('kkyoadm_language_switcher', array($this, 'render_language_switcher'));
        
        // AJAX処理（ログイン済みユーザー）
        add_action('wp_ajax_kkyoadm_register_user', array($this, 'ajax_register_user'));
        add_action('wp_ajax_kkyoadm_submit_review', array($this, 'ajax_submit_review'));
        add_action('wp_ajax_kkyoadm_submit_contact', array($this, 'ajax_submit_contact'));
        add_action('wp_ajax_kkyoadm_save_form_builder', array($this, 'ajax_save_form_builder'));
        add_action('wp_ajax_kkyoadm_comment_like', array($this, 'ajax_comment_like'));
        
        // AJAX処理（非ログインユーザー）
        add_action('wp_ajax_nopriv_kkyoadm_submit_review', array($this, 'ajax_submit_review'));
        add_action('wp_ajax_nopriv_kkyoadm_submit_contact', array($this, 'ajax_submit_contact'));
        add_action('wp_ajax_nopriv_kkyoadm_comment_like', array($this, 'ajax_comment_like'));
        
        // セキュリティ機能
        add_action('wp_login_failed', array($this, 'handle_login_failed'));
        add_filter('authenticate', array($this, 'check_ip_block'), 30, 3);
        
        // コメント表示のカスタマイズ
        add_filter('comment_text', array($this, 'add_like_button_to_comment'), 10, 2);
        
        // No Image設定
        add_filter('post_thumbnail_html', array($this, 'filter_post_thumbnail'), 10, 5);
        
        // プラグイン有効化時の処理
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // プラグイン無効化時の処理
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * プラグイン有効化時の処理
     */
    public function activate() {
        // カスタム投稿タイプを登録
        $this->register_post_types();
        
        // パーマリンクをフラッシュ
        flush_rewrite_rules();
        
        // 初期設定を保存
        if (!get_option('kkyoadmkit_font')) {
            update_option('kkyoadmkit_font', 'noto-sans-jp');
        }
        if (!get_option('kkyoadmkit_max_attempts')) {
            update_option('kkyoadmkit_max_attempts', 5);
        }
    }
    
    /**
     * プラグイン無効化時の処理
     */
    public function deactivate() {
        // パーマリンクをフラッシュ
        flush_rewrite_rules();
    }
    
    /**
     * 管理画面メニューの追加
     */
    public function add_admin_menu() {
        // メインメニュー
        add_menu_page(
            'KkyoAdmKit',
            'KkyoAdmKit',
            'manage_options',
            'kkyoadmkit-ext',
            array($this, 'page_main'),
            'dashicons-admin-generic',
            30
        );
        
        // サブメニュー: フォームビルダー
        add_submenu_page(
            'kkyoadmkit-ext',
            'フォームビルダー',
            'フォームビルダー',
            'manage_options',
            'kkyoadmkit-form-builder',
            array($this, 'page_form_builder')
        );
        
        // サブメニュー: フォーム一覧
        add_submenu_page(
            'kkyoadmkit-ext',
            'フォーム一覧',
            'フォーム一覧',
            'manage_options',
            'kkyoadmkit-forms',
            array($this, 'page_forms')
        );
        
        // サブメニュー: お問い合わせ
        add_submenu_page(
            'kkyoadmkit-ext',
            'お問い合わせ',
            'お問い合わせ',
            'manage_options',
            'kkyoadmkit-contact',
            array($this, 'page_contact')
        );
        
        // サブメニュー: セキュリティ
        add_submenu_page(
            'kkyoadmkit-ext',
            'セキュリティ',
            'セキュリティ',
            'manage_options',
            'kkyoadmkit-security',
            array($this, 'page_security')
        );
        
        // サブメニュー: No Image設定
        add_submenu_page(
            'kkyoadmkit-ext',
            'No Image設定',
            'No Image設定',
            'manage_options',
            'kkyoadmkit-noimage',
            array($this, 'page_noimage')
        );
    }
    
    /**
     * フロントエンドスクリプトとスタイルの読み込み
     */
    public function enqueue_scripts() {
        // jQuery
        wp_enqueue_script('jquery');
        
        // AJAX用変数の設定（nonceは含めない - セキュリティ強化）
        wp_add_inline_script(
            'jquery',
            'var kkyoadmkitAjax = {url: "' . esc_url(admin_url('admin-ajax.php')) . '"};',
            'before'
        );
        
        // Google Fontsの読み込み
        $font = get_option('kkyoadmkit_font', 'noto-sans-jp');
        $fonts = array(
            'noto-sans-jp' => 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&display=swap',
            'dotgothic16' => 'https://fonts.googleapis.com/css2?family=DotGothic16&display=swap',
            'mochiy-pop' => 'https://fonts.googleapis.com/css2?family=Mochiy+Pop+P+One&display=swap',
            'press-start' => 'https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap'
        );
        
        if (isset($fonts[$font])) {
            wp_enqueue_style('kkyoadmkit-font', $fonts[$font], array(), null);
        }
        
        // プラグイン用カスタムCSS
        wp_add_inline_style('wp-block-library', $this->get_frontend_css());
    }
    
    /**
     * 管理画面用スクリプトとスタイルの読み込み
     */
    public function enqueue_admin_scripts($hook) {
        // 管理画面CSS
        wp_add_inline_style('wp-admin', $this->get_admin_css());
        
        // フォームビルダーページの場合
        if ($hook === 'kkyoadmkit_page_kkyoadmkit-form-builder') {
            // jQuery UI Sortable
            wp_enqueue_script('jquery-ui-sortable');
            
            // カラーピッカー
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        }
    }
    
    /**
     * カスタム投稿タイプの登録
     */
    public function register_post_types() {
        // レビュー投稿タイプ
        register_post_type('kkyoadmkit_review', array(
            'label' => 'レビュー',
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'kkyoadmkit-ext',
            'menu_icon' => 'dashicons-star-filled',
            'supports' => array('title', 'editor', 'author', 'thumbnail'),
            'has_archive' => true,
            'rewrite' => array('slug' => 'reviews'),
            'labels' => array(
                'name' => 'レビュー',
                'singular_name' => 'レビュー',
                'add_new' => '新規追加',
                'add_new_item' => '新しいレビューを追加',
                'edit_item' => 'レビューを編集',
                'view_item' => 'レビューを表示',
                'all_items' => 'すべてのレビュー',
            ),
        ));
        
        // お問い合わせ投稿タイプ
        register_post_type('kkyoadmkit_contact', array(
            'label' => 'お問い合わせ',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'kkyoadmkit-ext',
            'menu_icon' => 'dashicons-email',
            'supports' => array('title', 'editor'),
            'capabilities' => array(
                'create_posts' => 'do_not_allow',
            ),
            'map_meta_cap' => true,
            'labels' => array(
                'name' => 'お問い合わせ',
                'singular_name' => 'お問い合わせ',
                'edit_item' => 'お問い合わせを表示',
                'view_item' => 'お問い合わせを表示',
                'all_items' => 'すべてのお問い合わせ',
            ),
        ));
    }
    
    /**
     * Gutenbergブロックの登録
     */
    public function register_blocks() {
        // Gutenberg未対応の場合は終了
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // ブロックエディタ用アセットの登録
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor'));
        
        // 各ブロックの登録
        register_block_type('kkyoadmkit/register-form', array(
            'render_callback' => array($this, 'render_register_form'),
            'editor_script' => 'kkyoadmkit-blocks',
        ));
        
        register_block_type('kkyoadmkit/review-form', array(
            'render_callback' => array($this, 'render_review_form'),
            'editor_script' => 'kkyoadmkit-blocks',
        ));
        
        register_block_type('kkyoadmkit/contact-form', array(
            'render_callback' => array($this, 'render_contact_form'),
            'editor_script' => 'kkyoadmkit-blocks',
            'attributes' => array(
                'formId' => array(
                    'type' => 'string',
                    'default' => 'default',
                ),
            ),
        ));
    }
    
    /**
     * ブロックエディタ用スクリプトの読み込み
     */
    public function enqueue_block_editor() {
        // ブロック用JavaScript（インライン出力）
        wp_enqueue_script(
            'kkyoadmkit-blocks',
            plugins_url('blocks.js', __FILE__),
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            self::VERSION,
            true
        );
        
        // blocks.jsが存在しない場合はインラインで出力
        if (!file_exists(plugin_dir_path(__FILE__) . 'blocks.js')) {
            wp_add_inline_script('kkyoadmkit-blocks', $this->get_blocks_js());
        }
    }
    
    /**
     * ユーザー登録フォームのレンダリング（同期フォーム）
     */
    public function render_register_form($atts) {
        // 既にログイン済みの場合
        if (is_user_logged_in()) {
            return '<p>既にログインしています。</p>';
        }
        
        ob_start();
        ?>
        <div class="kkyoadmkit-form kkyoadmkit-register-form" style="max-width:500px;margin:40px auto;padding:30px;background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1)">
            <h2 style="text-align:center;margin-bottom:20px;color:#333">アカウント登録</h2>
            
            <?php
            // フォーム送信処理
            if (isset($_POST['kkyoadm_register_submit'])) {
                // nonceチェック
                if (!isset($_POST['kkyoadm_register_nonce']) || !wp_verify_nonce($_POST['kkyoadm_register_nonce'], 'kkyoadm_register')) {
                    echo '<div class="kkyoadmkit-message error">不正なリクエストです。</div>';
                } else {
                    // ユーザー登録処理
                    $username = sanitize_user($_POST['username']);
                    $email = sanitize_email($_POST['email']);
                    $password = $_POST['password'];
                    
                    // バリデーション
                    if (empty($username) || empty($email) || empty($password)) {
                        echo '<div class="kkyoadmkit-message error">すべての項目を入力してください。</div>';
                    } elseif (!is_email($email)) {
                        echo '<div class="kkyoadmkit-message error">有効なメールアドレスを入力してください。</div>';
                    } else {
                        // ユーザー作成
                        $user_id = wp_create_user($username, $password, $email);
                        
                        if (is_wp_error($user_id)) {
                            echo '<div class="kkyoadmkit-message error">エラー: ' . esc_html($user_id->get_error_message()) . '</div>';
                        } else {
                            echo '<div class="kkyoadmkit-message success">登録完了しました！ログインしてください。</div>';
                        }
                    }
                }
            }
            ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('kkyoadm_register', 'kkyoadm_register_nonce'); ?>
                <p>
                    <label for="username">ユーザー名 *</label>
                    <input type="text" name="username" id="username" required style="width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px">
                </p>
                <p>
                    <label for="email">メールアドレス *</label>
                    <input type="email" name="email" id="email" required style="width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px">
                </p>
                <p>
                    <label for="password">パスワード *</label>
                    <input type="password" name="password" id="password" required style="width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px">
                </p>
                <p>
                    <button type="submit" name="kkyoadm_register_submit" style="width:100%;padding:12px;background:#667eea;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:16px">登録する</button>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * レビューフォームのレンダリング
     */
    public function render_review_form($atts) {
        ob_start();
        ?>
        <div class="kkyoadmkit-form kkyoadmkit-review-form" style="max-width:600px;margin:40px auto;padding:30px;background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1)">
            <h2 style="text-align:center;margin-bottom:20px;color:#333">レビュー投稿</h2>
            <form id="kkyoadm-review-form">
                <p>
                    <label for="review-title">タイトル *</label>
                    <input type="text" name="title" id="review-title" required style="width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px">
                </p>
                <p>
                    <label for="review-content">レビュー内容 *</label>
                    <textarea name="content" id="review-content" rows="5" required style="width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px"></textarea>
                </p>
                <p>
                    <label for="review-rating">評価 *</label>
                    <input type="number" name="rating" id="review-rating" min="1" max="5" value="5" required style="width:80px;padding:8px;border:1px solid #ddd;border-radius:4px">
                    <span style="margin-left:10px;color:#666">/ 5</span>
                </p>
                <p>
                    <button type="submit" style="width:100%;padding:12px;background:#667eea;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:16px">投稿する</button>
                </p>
            </form>
            <div class="kkyoadmkit-message" style="margin-top:15px;padding:10px;border-radius:4px;display:none"></div>
        </div>
        <script>
        jQuery(function($){
            // レビューフォーム送信処理
            $('#kkyoadm-review-form').on('submit', function(e){
                e.preventDefault();
                var $form = $(this);
                var $message = $('.kkyoadmkit-message');
                var $button = $form.find('button[type="submit"]');
                
                // ボタンを無効化
                $button.prop('disabled', true).text('送信中...');
                
                // AJAX送信
                $.post(kkyoadmkitAjax.url, {
                    action: 'kkyoadm_submit_review',
                    nonce: '<?php echo wp_create_nonce('kkyoadm_review_nonce'); ?>',
                    title: $('[name=title]').val(),
                    content: $('[name=content]').val(),
                    rating: $('[name=rating]').val()
                }, function(response){
                    // メッセージ表示
                    $message.show()
                        .removeClass('success error')
                        .addClass(response.success ? 'success' : 'error')
                        .text(response.success ? '投稿完了しました！承認後に公開されます。' : 'エラー: ' + (response.data || '投稿に失敗しました'));
                    
                    // 成功時はフォームをリセット
                    if(response.success) {
                        $form[0].reset();
                    }
                    
                    // ボタンを再有効化
                    $button.prop('disabled', false).text('投稿する');
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * お問い合わせフォームのレンダリング（ドラッグ&ドロップ対応）
     */
    public function render_contact_form($atts) {
        // 属性の取得
        $atts = shortcode_atts(array(
            'form_id' => 'default',
        ), $atts);
        
        // フォーム設定の取得
        $form_config = get_option('kkyoadmkit_form_config_' . $atts['form_id'], array());
        
        // デフォルトフォーム設定
        if (empty($form_config)) {
            $form_config = array(
                'fields' => array(
                    array('type' => 'text', 'name' => 'name', 'label' => 'お名前', 'required' => true),
                    array('type' => 'email', 'name' => 'email', 'label' => 'メールアドレス', 'required' => true),
                    array('type' => 'textarea', 'name' => 'message', 'label' => 'お問い合わせ内容', 'required' => true),
                    array('type' => 'checkbox', 'name' => 'agreement', 'label' => '個人情報の取り扱いに同意する', 'required' => true),
                ),
                'captcha' => array(
                    'enabled' => false,
                    'type' => 'recaptcha_v2',
                ),
                'file_upload' => array(
                    'enabled' => false,
                ),
            );
        }
        
        ob_start();
        ?>
        <div class="kkyoadmkit-form kkyoadmkit-contact-form" style="max-width:700px;margin:40px auto;padding:30px;background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1)">
            <h2 style="text-align:center;margin-bottom:20px;color:#333">お問い合わせ</h2>
            <form id="kkyoadm-contact-form" enctype="multipart/form-data">
                <?php
                // フォームフィールドをレンダリング
                foreach ($form_config['fields'] as $field) {
                    $this->render_form_field($field);
                }
                
                // ファイルアップロード
                if (!empty($form_config['file_upload']['enabled'])) {
                    echo '<p><label>添付ファイル</label><input type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.zip"></p>';
                }
                
                // CAPTCHA
                if (!empty($form_config['captcha']['enabled'])) {
                    $this->render_captcha($form_config['captcha']);
                }
                ?>
                
                <p>
                    <button type="submit" style="width:100%;padding:12px;background:#667eea;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:16px">送信する</button>
                </p>
            </form>
            <div class="kkyoadmkit-message" style="margin-top:15px;padding:10px;border-radius:4px;display:none"></div>
        </div>
        <script>
        jQuery(function($){
            // お問い合わせフォーム送信処理
            $('#kkyoadm-contact-form').on('submit', function(e){
                e.preventDefault();
                var $form = $(this);
                var $message = $('.kkyoadmkit-message');
                var $button = $form.find('button[type="submit"]');
                
                // ボタンを無効化
                $button.prop('disabled', true).text('送信中...');
                
                // FormDataオブジェクトを作成
                var formData = new FormData(this);
                formData.append('action', 'kkyoadm_submit_contact');
                formData.append('nonce', '<?php echo wp_create_nonce('kkyoadm_contact_nonce'); ?>');
                formData.append('form_id', '<?php echo esc_js($atts['form_id']); ?>');
                
                // AJAX送信
                $.ajax({
                    url: kkyoadmkitAjax.url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response){
                        // メッセージ表示
                        $message.show()
                            .removeClass('success error')
                            .addClass(response.success ? 'success' : 'error')
                            .text(response.success ? '送信完了しました！' : 'エラー: ' + (response.data || '送信に失敗しました'));
                        
                        // 成功時はフォームをリセット
                        if(response.success) {
                            $form[0].reset();
                        }
                        
                        // ボタンを再有効化
                        $button.prop('disabled', false).text('送信する');
                    },
                    error: function(){
                        $message.show()
                            .removeClass('success error')
                            .addClass('error')
                            .text('通信エラーが発生しました');
                        $button.prop('disabled', false).text('送信する');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * フォームフィールドのレンダリング
     * @param array $field フィールド設定
     */
    private function render_form_field($field) {
        $required = !empty($field['required']) ? 'required' : '';
        $label = esc_html($field['label']);
        $name = esc_attr($field['name']);
        
        echo '<p>';
        echo '<label>' . $label . ($required ? ' *' : '') . '</label>';
        
        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'tel':
            case 'url':
                echo '<input type="' . esc_attr($field['type']) . '" name="' . $name . '" ' . $required . ' style="width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px">';
                break;
            
            case 'textarea':
                echo '<textarea name="' . $name . '" rows="5" ' . $required . ' style="width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px"></textarea>';
                break;
            
            case 'select':
                echo '<select name="' . $name . '" ' . $required . ' style="width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px">';
                if (!empty($field['options'])) {
                    foreach ($field['options'] as $option) {
                        echo '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
                    }
                }
                echo '</select>';
                break;
            
            case 'checkbox':
                echo '<label style="display:inline-block"><input type="checkbox" name="' . $name . '" value="1" ' . $required . '> ' . $label . '</label>';
                break;
            
            case 'radio':
                if (!empty($field['options'])) {
                    foreach ($field['options'] as $option) {
                        echo '<label style="display:block;margin-bottom:5px"><input type="radio" name="' . $name . '" value="' . esc_attr($option) . '" ' . $required . '> ' . esc_html($option) . '</label>';
                    }
                }
                break;
            
            case 'checkbox_multi':
                if (!empty($field['options'])) {
                    foreach ($field['options'] as $option) {
                        echo '<label style="display:block;margin-bottom:5px"><input type="checkbox" name="' . $name . '[]" value="' . esc_attr($option) . '"> ' . esc_html($option) . '</label>';
                    }
                }
                break;
        }
        
        echo '</p>';
    }
    
    /**
     * CAPTCHAのレンダリング
     * @param array $captcha_config CAPTCHA設定
     */
    private function render_captcha($captcha_config) {
        $site_key = get_option('kkyoadmkit_captcha_site_key', '');
        
        if (empty($site_key)) {
            return;
        }
        
        echo '<p>';
        
        switch ($captcha_config['type']) {
            case 'recaptcha_v2':
                echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '"></div>';
                wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
                break;
            
            case 'recaptcha_v3':
                echo '<input type="hidden" name="recaptcha_token" id="recaptcha_token">';
                wp_enqueue_script('google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . $site_key, array(), null, true);
                wp_add_inline_script('google-recaptcha-v3', "
                    grecaptcha.ready(function() {
                        grecaptcha.execute('" . esc_js($site_key) . "', {action: 'submit'}).then(function(token) {
                            document.getElementById('recaptcha_token').value = token;
                        });
                    });
                ");
                break;
            
            case 'hcaptcha':
                echo '<div class="h-captcha" data-sitekey="' . esc_attr($site_key) . '"></div>';
                wp_enqueue_script('hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true);
                break;
            
            case 'turnstile':
                echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($site_key) . '"></div>';
                wp_enqueue_script('turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true);
                break;
        }
        
        echo '</p>';
    }
    
    /**
     * 言語切り替えウィジェット
     */
    public function render_language_switcher($atts) {
        return '<select class="kkyoadmkit-lang" style="padding:5px;border:1px solid #ddd;border-radius:4px"><option>🇯🇵 日本語</option><option>🇺🇸 English</option></select>';
    }
    
    /**
     * ユーザー登録AJAX処理（非推奨 - 同期フォームを使用）
     */
    public function ajax_register_user() {
        // nonceチェック
        check_ajax_referer('kkyoadm_register_nonce', 'nonce');
        
        // ユーザー作成
        $user_id = wp_create_user(
            sanitize_user($_POST['username']),
            $_POST['password'],
            sanitize_email($_POST['email'])
        );
        
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }
        
        wp_send_json_success('登録完了');
    }
    
    /**
     * レビュー投稿AJAX処理
     */
    public function ajax_submit_review() {
        // nonceチェック
        check_ajax_referer('kkyoadm_review_nonce', 'nonce');
        
        // レート制限チェック（1分間に1回まで）
        $ip = $this->get_client_ip();
        $transient_key = 'kkyoadm_review_' . md5($ip);
        
        if (get_transient($transient_key)) {
            wp_send_json_error('短時間に複数の投稿はできません');
        }
        
        // データのサニタイズ
        $title = sanitize_text_field($_POST['title']);
        $content = sanitize_textarea_field($_POST['content']);
        $rating = intval($_POST['rating']);
        
        // バリデーション
        if (empty($title) || empty($content) || $rating < 1 || $rating > 5) {
            wp_send_json_error('入力内容を確認してください');
        }
        
        // 投稿の作成
        $post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => 'kkyoadmkit_review',
            'post_status' => 'pending', // 承認待ち
            'meta_input' => array(
                'rating' => $rating,
                'reviewer_ip' => $ip,
            ),
        ));
        
        if (is_wp_error($post_id)) {
            wp_send_json_error('投稿に失敗しました');
        }
        
        // レート制限を設定（1分間）
        set_transient($transient_key, true, 60);
        
        wp_send_json_success('投稿完了');
    }
    
    /**
     * お問い合わせフォーム送信AJAX処理
     */
    public function ajax_submit_contact() {
        // nonceチェック
        check_ajax_referer('kkyoadm_contact_nonce', 'nonce');
        
        // レート制限チェック（5分間に1回まで）
        $ip = $this->get_client_ip();
        $transient_key = 'kkyoadm_contact_' . md5($ip);
        
        if (get_transient($transient_key)) {
            wp_send_json_error('短時間に複数の送信はできません');
        }
        
        // データのサニタイズ
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $message = sanitize_textarea_field($_POST['message']);
        
        // バリデーション
        if (empty($name) || empty($email) || empty($message)) {
            wp_send_json_error('すべての必須項目を入力してください');
        }
        
        if (!is_email($email)) {
            wp_send_json_error('有効なメールアドレスを入力してください');
        }
        
        // ファイルアップロード処理
        $attachment_id = 0;
        if (!empty($_FILES['attachment']['name'])) {
            $attachment_id = $this->handle_file_upload($_FILES['attachment']);
            
            if (is_wp_error($attachment_id)) {
                wp_send_json_error($attachment_id->get_error_message());
            }
        }
        
        // お問い合わせを投稿として保存
        $post_id = wp_insert_post(array(
            'post_title' => 'お問い合わせ: ' . $name,
            'post_content' => $message,
            'post_type' => 'kkyoadmkit_contact',
            'post_status' => 'publish',
            'meta_input' => array(
                'contact_name' => $name,
                'contact_email' => $email,
                'contact_ip' => $ip,
                'contact_date' => current_time('mysql'),
            ),
        ));
        
        if (is_wp_error($post_id)) {
            wp_send_json_error('送信に失敗しました');
        }
        
        // 添付ファイルを紐付け
        if ($attachment_id) {
            update_post_meta($post_id, 'contact_attachment', $attachment_id);
        }
        
        // 管理者にメール送信
        $admin_email = get_option('admin_email');
        $subject = '[' . get_bloginfo('name') . '] 新しいお問い合わせ';
        $body = sprintf(
            "新しいお問い合わせが届きました。\n\n名前: %s\nメール: %s\n\n内容:\n%s\n\n---\n管理画面で確認: %s",
            $name,
            $email,
            $message,
            admin_url('post.php?post=' . $post_id . '&action=edit')
        );
        
        wp_mail($admin_email, $subject, $body);
        
        // レート制限を設定（5分間）
        set_transient($transient_key, true, 300);
        
        wp_send_json_success('送信完了');
    }
    
    /**
     * ファイルアップロード処理
     * @param array $file $_FILESの配列
     * @return int|WP_Error 添付ファイルID またはエラー
     */
    private function handle_file_upload($file) {
        // ファイルが送信されているか確認
        if (empty($file['name'])) {
            return new WP_Error('no_file', 'ファイルが選択されていません');
        }
        
        // ファイルサイズチェック
        if ($file['size'] > $this->max_file_size) {
            return new WP_Error('file_too_large', 'ファイルサイズが大きすぎます（最大5MB）');
        }
        
        // 拡張子チェック
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $this->allowed_extensions)) {
            return new WP_Error('invalid_file_type', '許可されていないファイル形式です');
        }
        
        // WordPress標準のアップロード処理
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            return new WP_Error('upload_error', $upload['error']);
        }
        
        // メディアライブラリに追加
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name($file['name']),
            'post_content' => '',
            'post_status' => 'inherit',
        );
        
        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // メタデータを生成
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        return $attachment_id;
    }
    
    /**
     * フォームビルダー保存AJAX処理
     */
    public function ajax_save_form_builder() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        // nonceチェック
        check_ajax_referer('kkyoadm_form_builder', 'nonce');
        
        // データ取得
        $form_id = sanitize_text_field($_POST['form_id']);
        $form_config = json_decode(stripslashes($_POST['form_config']), true);
        
        // 保存
        update_option('kkyoadmkit_form_config_' . $form_id, $form_config);
        
        wp_send_json_success('保存しました');
    }
    
    /**
     * コメントいいね機能AJAX処理
     */
    public function ajax_comment_like() {
        // nonceチェック
        check_ajax_referer('kkyoadm_comment_like', 'nonce');
        
        $comment_id = intval($_POST['comment_id']);
        
        // コメントの存在確認
        $comment = get_comment($comment_id);
        if (!$comment) {
            wp_send_json_error('コメントが見つかりません');
        }
        
        // IPアドレスベースの重複チェック
        $ip = $this->get_client_ip();
        $liked_ips = get_comment_meta($comment_id, 'liked_ips', true);
        
        if (!is_array($liked_ips)) {
            $liked_ips = array();
        }
        
        // 既にいいね済みか確認
        if (in_array($ip, $liked_ips)) {
            wp_send_json_error('既にいいね済みです');
        }
        
        // いいね数を取得・更新
        $like_count = intval(get_comment_meta($comment_id, 'like_count', true));
        $like_count++;
        update_comment_meta($comment_id, 'like_count', $like_count);
        
        // IPを記録
        $liked_ips[] = $ip;
        update_comment_meta($comment_id, 'liked_ips', $liked_ips);
        
        wp_send_json_success(array('like_count' => $like_count));
    }
    
    /**
     * コメントにいいねボタンを追加
     */
    public function add_like_button_to_comment($comment_text, $comment) {
        $like_count = intval(get_comment_meta($comment->comment_ID, 'like_count', true));
        
        $button_html = sprintf(
            '<div class="kkyoadmkit-comment-like" style="margin-top:10px">
                <button class="kkyoadmkit-like-btn" data-comment-id="%d" style="background:#f0f0f0;border:1px solid #ddd;padding:5px 10px;border-radius:4px;cursor:pointer">
                    👍 いいね <span class="like-count">%d</span>
                </button>
            </div>',
            $comment->comment_ID,
            $like_count
        );
        
        // JavaScriptの追加（初回のみ）
        static $script_added = false;
        if (!$script_added) {
            $button_html .= "
            <script>
            jQuery(function($){
                $(document).on('click', '.kkyoadmkit-like-btn', function(){
                    var \$btn = $(this);
                    var commentId = \$btn.data('comment-id');
                    
                    \$btn.prop('disabled', true);
                    
                    $.post(kkyoadmkitAjax.url, {
                        action: 'kkyoadm_comment_like',
                        nonce: '" . wp_create_nonce('kkyoadm_comment_like') . "',
                        comment_id: commentId
                    }, function(response){
                        if(response.success) {
                            \$btn.find('.like-count').text(response.data.like_count);
                        } else {
                            alert(response.data || 'エラーが発生しました');
                            \$btn.prop('disabled', false);
                        }
                    });
                });
            });
            </script>
            ";
            $script_added = true;
        }
        
        return $comment_text . $button_html;
    }
    
    /**
     * No Image設定のフィルター
     */
    public function filter_post_thumbnail($html, $post_id, $post_thumbnail_id, $size, $attr) {
        // サムネイルがない場合のみ処理
        if (!empty($html)) {
            return $html;
        }
        
        // 投稿タイプが'post'の場合のみ
        if (get_post_type($post_id) !== 'post') {
            return $html;
        }
        
        // No Image画像のURL取得
        $noimage_url = get_option('kkyoadmkit_noimage_url', '');
        
        if (empty($noimage_url)) {
            return $html;
        }
        
        // No Image画像を出力
        $html = sprintf(
            '<img src="%s" alt="No Image" class="wp-post-image">',
            esc_url($noimage_url)
        );
        
        return $html;
    }
    
    /**
     * ログイン失敗時の処理
     */
    public function handle_login_failed($username) {
        $ip = $this->get_client_ip();
        $blocked = get_option('kkyoadmkit_blocked_ips', array());
        $max = intval(get_option('kkyoadmkit_max_attempts', 5));
        
        if (!isset($blocked[$ip])) {
            $blocked[$ip] = array('attempts' => 0);
        }
        
        $blocked[$ip]['attempts']++;
        $blocked[$ip]['last_attempt'] = time();
        
        // 最大試行回数を超えた場合
        if ($blocked[$ip]['attempts'] >= $max) {
            $blocked[$ip]['blocked_until'] = time() + 3600; // 1時間ブロック
        }
        
        update_option('kkyoadmkit_blocked_ips', $blocked);
    }
    
    /**
     * IPブロックチェック
     */
    public function check_ip_block($user, $username, $password) {
        // 空の認証情報は無視
        if (empty($username) && empty($password)) {
            return $user;
        }
        
        $ip = $this->get_client_ip();
        $blocked = get_option('kkyoadmkit_blocked_ips', array());
        
        // ブロックされているか確認
        if (isset($blocked[$ip]['blocked_until']) && time() < $blocked[$ip]['blocked_until']) {
            $remaining = ceil(($blocked[$ip]['blocked_until'] - time()) / 60);
            return new WP_Error('ip_blocked', sprintf('このIPアドレスはブロックされています（残り%d分）', $remaining));
        }
        
        return $user;
    }
    
    /**
     * クライアントIPアドレスの取得
     * @return string IPアドレス
     */
    private function get_client_ip() {
        $ip = '';
        
        // プロキシ経由の場合を考慮
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // IPアドレスのバリデーション
        $ip = filter_var($ip, FILTER_VALIDATE_IP);
        
        return $ip ? $ip : '0.0.0.0';
    }
    
    /**
     * メイン設定ページ
     */
    public function page_main() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        // 設定保存処理
        if (isset($_POST['kkyoadmkit_settings_submit'])) {
            check_admin_referer('kkyoadmkit_settings');
            
            update_option('kkyoadmkit_font', sanitize_text_field($_POST['kkyoadmkit_font']));
            update_option('kkyoadmkit_captcha_type', sanitize_text_field($_POST['kkyoadmkit_captcha_type']));
            update_option('kkyoadmkit_captcha_site_key', sanitize_text_field($_POST['kkyoadmkit_captcha_site_key']));
            update_option('kkyoadmkit_captcha_secret_key', sanitize_text_field($_POST['kkyoadmkit_captcha_secret_key']));
            
            echo '<div class="notice notice-success"><p>設定を保存しました</p></div>';
        }
        
        $font = get_option('kkyoadmkit_font', 'noto-sans-jp');
        $captcha_type = get_option('kkyoadmkit_captcha_type', 'recaptcha_v2');
        $site_key = get_option('kkyoadmkit_captcha_site_key', '');
        $secret_key = get_option('kkyoadmkit_captcha_secret_key', '');
        ?>
        <div class="wrap kkyoadmkit-admin">
            <h1>🎨 KkyoAdmKit Extension 設定</h1>
            <p class="description">プラグインの基本設定を行います。</p>
            
            <form method="post">
                <?php wp_nonce_field('kkyoadmkit_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="kkyoadmkit_font">サイトフォント</label></th>
                        <td>
                            <select name="kkyoadmkit_font" id="kkyoadmkit_font" class="regular-text">
                                <option value="noto-sans-jp" <?php selected($font, 'noto-sans-jp'); ?>>Noto Sans Japanese</option>
                                <option value="dotgothic16" <?php selected($font, 'dotgothic16'); ?>>DotGothic16</option>
                                <option value="mochiy-pop" <?php selected($font, 'mochiy-pop'); ?>>Mochiy Pop P One</option>
                                <option value="press-start" <?php selected($font, 'press-start'); ?>>Press Start 2P</option>
                            </select>
                            <p class="description">サイト全体で使用するフォントを選択します。</p>
                        </td>
                    </tr>
                </table>
                
                <h2>🔒 CAPTCHA設定</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="kkyoadmkit_captcha_type">CAPTCHAタイプ</label></th>
                        <td>
                            <select name="kkyoadmkit_captcha_type" id="kkyoadmkit_captcha_type" class="regular-text">
                                <option value="recaptcha_v2" <?php selected($captcha_type, 'recaptcha_v2'); ?>>reCAPTCHA v2</option>
                                <option value="recaptcha_v3" <?php selected($captcha_type, 'recaptcha_v3'); ?>>reCAPTCHA v3</option>
                                <option value="hcaptcha" <?php selected($captcha_type, 'hcaptcha'); ?>>hCaptcha</option>
                                <option value="turnstile" <?php selected($captcha_type, 'turnstile'); ?>>Cloudflare Turnstile</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="kkyoadmkit_captcha_site_key">サイトキー</label></th>
                        <td>
                            <input type="text" name="kkyoadmkit_captcha_site_key" id="kkyoadmkit_captcha_site_key" value="<?php echo esc_attr($site_key); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="kkyoadmkit_captcha_secret_key">シークレットキー</label></th>
                        <td>
                            <input type="text" name="kkyoadmkit_captcha_secret_key" id="kkyoadmkit_captcha_secret_key" value="<?php echo esc_attr($secret_key); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('設定を保存', 'primary', 'kkyoadmkit_settings_submit'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * フォームビルダーページ
     */
    public function page_form_builder() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        ?>
        <div class="wrap kkyoadmkit-admin">
            <h1>📝 フォームビルダー</h1>
            <p class="description">ドラッグ&ドロップでお問い合わせフォームを構築できます。</p>
            
            <div class="kkyoadmkit-form-builder" style="display:flex;gap:20px;margin-top:20px">
                <!-- 左側: フィールドパレット -->
                <div class="form-palette" style="flex:1;background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1)">
                    <h2>フィールド</h2>
                    <div class="field-items" id="field-palette">
                        <div class="field-item" draggable="true" data-type="text">
                            <span class="dashicons dashicons-edit"></span> テキスト
                        </div>
                        <div class="field-item" draggable="true" data-type="email">
                            <span class="dashicons dashicons-email"></span> メールアドレス
                        </div>
                        <div class="field-item" draggable="true" data-type="tel">
                            <span class="dashicons dashicons-phone"></span> 電話番号
                        </div>
                        <div class="field-item" draggable="true" data-type="textarea">
                            <span class="dashicons dashicons-text"></span> テキストエリア
                        </div>
                        <div class="field-item" draggable="true" data-type="select">
                            <span class="dashicons dashicons-menu"></span> セレクトボックス
                        </div>
                        <div class="field-item" draggable="true" data-type="checkbox">
                            <span class="dashicons dashicons-yes"></span> チェックボックス
                        </div>
                        <div class="field-item" draggable="true" data-type="checkbox_multi">
                            <span class="dashicons dashicons-yes-alt"></span> 複数選択
                        </div>
                        <div class="field-item" draggable="true" data-type="radio">
                            <span class="dashicons dashicons-marker"></span> ラジオボタン
                        </div>
                        <div class="field-item" draggable="true" data-type="file">
                            <span class="dashicons dashicons-upload"></span> ファイルアップロード
                        </div>
                    </div>
                </div>
                
                <!-- 右側: フォームプレビュー -->
                <div class="form-preview" style="flex:2;background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1)">
                    <h2>フォームプレビュー</h2>
                    <div id="form-canvas" class="form-canvas" style="min-height:400px;border:2px dashed #ddd;padding:20px">
                        <p style="text-align:center;color:#999">左からフィールドをドラッグ&ドロップしてください</p>
                    </div>
                    
                    <div style="margin-top:20px">
                        <button type="button" id="save-form" class="button button-primary">フォームを保存</button>
                        <button type="button" id="clear-form" class="button">すべてクリア</button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .field-item {
            padding: 12px;
            margin-bottom: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: move;
            transition: all 0.3s;
        }
        .field-item:hover {
            background: #e9e9e9;
            transform: translateX(5px);
        }
        .form-field {
            padding: 15px;
            margin-bottom: 10px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            position: relative;
        }
        .form-field:hover {
            border-color: #667eea;
        }
        .form-field .remove-field {
            position: absolute;
            top: 5px;
            right: 5px;
            cursor: pointer;
            color: #dc3545;
        }
        </style>
        
        <script>
        jQuery(function($){
            var formFields = [];
            
            // ドラッグ開始
            $(document).on('dragstart', '.field-item', function(e){
                e.originalEvent.dataTransfer.setData('fieldType', $(this).data('type'));
            });
            
            // ドロップゾーン設定
            $('#form-canvas').on('dragover', function(e){
                e.preventDefault();
            }).on('drop', function(e){
                e.preventDefault();
                var fieldType = e.originalEvent.dataTransfer.getData('fieldType');
                addField(fieldType);
            });
            
            // フィールド追加
            function addField(type) {
                var fieldId = 'field_' + Date.now();
                var field = {
                    id: fieldId,
                    type: type,
                    label: getFieldLabel(type),
                    name: 'field_' + formFields.length,
                    required: false,
                    options: type === 'select' || type === 'radio' || type === 'checkbox_multi' ? ['オプション1', 'オプション2'] : []
                };
                
                formFields.push(field);
                renderForm();
            }
            
            // フィールドラベル取得
            function getFieldLabel(type) {
                var labels = {
                    'text': 'テキスト',
                    'email': 'メールアドレス',
                    'tel': '電話番号',
                    'textarea': 'テキストエリア',
                    'select': 'セレクトボックス',
                    'checkbox': 'チェックボックス',
                    'checkbox_multi': '複数選択',
                    'radio': 'ラジオボタン',
                    'file': 'ファイルアップロード'
                };
                return labels[type] || type;
            }
            
            // フォームレンダリング
            function renderForm() {
                var canvas = $('#form-canvas');
                canvas.empty();
                
                if (formFields.length === 0) {
                    canvas.html('<p style="text-align:center;color:#999">左からフィールドをドラッグ&ドロップしてください</p>');
                    return;
                }
                
                $.each(formFields, function(index, field){
                    var fieldHtml = '<div class="form-field" data-field-id="' + field.id + '">';
                    fieldHtml += '<span class="dashicons dashicons-no remove-field"></span>';
                    fieldHtml += '<div style="margin-bottom:10px">';
                    fieldHtml += '<label>ラベル: <input type="text" class="field-label" value="' + field.label + '" style="width:200px"></label>';
                    fieldHtml += '<label style="margin-left:10px"><input type="checkbox" class="field-required" ' + (field.required ? 'checked' : '') + '> 必須</label>';
                    fieldHtml += '</div>';
                    
                    if (field.type === 'select' || field.type === 'radio' || field.type === 'checkbox_multi') {
                        fieldHtml += '<div><label>オプション（カンマ区切り）: <input type="text" class="field-options" value="' + field.options.join(',') + '" style="width:300px"></label></div>';
                    }
                    
                    fieldHtml += '</div>';
                    canvas.append(fieldHtml);
                });
                
                // Sortable有効化
                canvas.sortable({
                    update: function(event, ui) {
                        updateFieldOrder();
                    }
                });
            }
            
            // フィールド順序更新
            function updateFieldOrder() {
                var newOrder = [];
                $('#form-canvas .form-field').each(function(){
                    var fieldId = $(this).data('field-id');
                    var field = formFields.find(f => f.id === fieldId);
                    if (field) newOrder.push(field);
                });
                formFields = newOrder;
            }
            
            // フィールド削除
            $(document).on('click', '.remove-field', function(){
                var fieldId = $(this).closest('.form-field').data('field-id');
                formFields = formFields.filter(f => f.id !== fieldId);
                renderForm();
            });
            
            // フィールド設定変更
            $(document).on('change', '.field-label, .field-required, .field-options', function(){
                var $field = $(this).closest('.form-field');
                var fieldId = $field.data('field-id');
                var field = formFields.find(f => f.id === fieldId);
                
                if (field) {
                    field.label = $field.find('.field-label').val();
                    field.required = $field.find('.field-required').is(':checked');
                    
                    var options = $field.find('.field-options').val();
                    if (options) {
                        field.options = options.split(',').map(o => o.trim());
                    }
                }
            });
            
            // フォーム保存
            $('#save-form').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text('保存中...');
                
                $.post(ajaxurl, {
                    action: 'kkyoadm_save_form_builder',
                    nonce: '<?php echo wp_create_nonce('kkyoadm_form_builder'); ?>',
                    form_id: 'default',
                    form_config: JSON.stringify({
                        fields: formFields,
                        captcha: {enabled: false, type: 'recaptcha_v2'},
                        file_upload: {enabled: false}
                    })
                }, function(response){
                    alert(response.success ? '保存しました！' : 'エラーが発生しました');
                    $btn.prop('disabled', false).text('フォームを保存');
                });
            });
            
            // クリア
            $('#clear-form').on('click', function(){
                if (confirm('すべてのフィールドをクリアしますか？')) {
                    formFields = [];
                    renderForm();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * フォーム一覧ページ
     */
    public function page_forms() {
        ?>
        <div class="wrap kkyoadmkit-admin">
            <h1>📋 フォーム一覧</h1>
            <p class="description">利用可能なショートコードとブロックの一覧です。</p>
            
            <div class="kkyoadmkit-cards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;margin-top:20px">
                <!-- 登録フォーム -->
                <div class="kkyoadmkit-card">
                    <h3>👤 ユーザー登録フォーム</h3>
                    <p>新規ユーザーの登録を受け付けます。</p>
                    <code>[kkyoadm_register_form]</code>
                    <p class="description">または Gutenbergブロックから挿入</p>
                </div>
                
                <!-- レビューフォーム -->
                <div class="kkyoadmkit-card">
                    <h3>⭐ レビューフォーム</h3>
                    <p>商品やサービスのレビューを投稿できます。</p>
                    <code>[kkyoadm_review_form]</code>
                    <p class="description">または Gutenbergブロックから挿入</p>
                </div>
                
                <!-- お問い合わせフォーム -->
                <div class="kkyoadmkit-card">
                    <h3>✉️ お問い合わせフォーム</h3>
                    <p>カスタマイズ可能なお問い合わせフォームです。</p>
                    <code>[kkyoadm_contact_form]</code>
                    <p class="description">または Gutenbergブロックから挿入</p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * お問い合わせ一覧ページ
     */
    public function page_contact() {
        ?>
        <div class="wrap kkyoadmkit-admin">
            <h1>📬 お問い合わせ一覧</h1>
            
            <?php
            $contacts = get_posts(array(
                'post_type' => 'kkyoadmkit_contact',
                'posts_per_page' => 50,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            if ($contacts) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr>';
                echo '<th>日時</th>';
                echo '<th>名前</th>';
                echo '<th>メールアドレス</th>';
                echo '<th>内容</th>';
                echo '<th>操作</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                
                foreach ($contacts as $contact) {
                    $name = get_post_meta($contact->ID, 'contact_name', true);
                    $email = get_post_meta($contact->ID, 'contact_email', true);
                    $attachment_id = get_post_meta($contact->ID, 'contact_attachment', true);
                    
                    echo '<tr>';
                    echo '<td>' . esc_html(get_the_date('Y-m-d H:i', $contact)) . '</td>';
                    echo '<td>' . esc_html($name) . '</td>';
                    echo '<td><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></td>';
                    echo '<td>' . esc_html(wp_trim_words($contact->post_content, 15)) . '</td>';
                    echo '<td>';
                    echo '<a href="' . get_edit_post_link($contact->ID) . '" class="button button-small">詳細</a> ';
                    if ($attachment_id) {
                        echo '<a href="' . wp_get_attachment_url($attachment_id) . '" class="button button-small" target="_blank">📎</a>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            } else {
                echo '<p>お問い合わせはまだありません。</p>';
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * セキュリティ設定ページ
     */
    public function page_security() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        // 設定保存処理
        if (isset($_POST['kkyoadmkit_security_submit'])) {
            check_admin_referer('kkyoadmkit_security');
            
            update_option('kkyoadmkit_admin_slug', sanitize_text_field($_POST['kkyoadmkit_admin_slug']));
            update_option('kkyoadmkit_max_attempts', intval($_POST['kkyoadmkit_max_attempts']));
            
            echo '<div class="notice notice-success"><p>設定を保存しました</p></div>';
        }
        
        // IPブロック解除処理
        if (isset($_POST['kkyoadmkit_unblock_ip'])) {
            check_admin_referer('kkyoadmkit_unblock_ip');
            
            $ip = sanitize_text_field($_POST['ip_to_unblock']);
            $blocked = get_option('kkyoadmkit_blocked_ips', array());
            
            if (isset($blocked[$ip])) {
                unset($blocked[$ip]);
                update_option('kkyoadmkit_blocked_ips', $blocked);
                echo '<div class="notice notice-success"><p>IPアドレスのブロックを解除しました</p></div>';
            }
        }
        
        $slug = get_option('kkyoadmkit_admin_slug', '');
        $max = get_option('kkyoadmkit_max_attempts', 5);
        ?>
        <div class="wrap kkyoadmkit-admin">
            <h1>🔒 セキュリティ設定</h1>
            
            <form method="post">
                <?php wp_nonce_field('kkyoadmkit_security'); ?>
                
                <h2>ログイン保護</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="kkyoadmkit_max_attempts">最大試行回数</label></th>
                        <td>
                            <input type="number" name="kkyoadmkit_max_attempts" id="kkyoadmkit_max_attempts" value="<?php echo esc_attr($max); ?>" min="3" max="10" class="small-text">
                            <p class="description">指定回数ログインに失敗するとIPアドレスを1時間ブロックします。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="kkyoadmkit_admin_slug">管理画面URL変更</label></th>
                        <td>
                            <input type="text" name="kkyoadmkit_admin_slug" id="kkyoadmkit_admin_slug" value="<?php echo esc_attr($slug); ?>" placeholder="my-admin" class="regular-text">
                            <p class="description">カスタムスラッグを設定すると、管理画面URLが変更されます。（現在は未実装）</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('設定を保存', 'primary', 'kkyoadmkit_security_submit'); ?>
            </form>
            
            <hr>
            
            <h2>ブロックされたIPアドレス</h2>
            <?php
            $blocked = get_option('kkyoadmkit_blocked_ips', array());
            
            if (!empty($blocked)) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr>';
                echo '<th>IPアドレス</th>';
                echo '<th>試行回数</th>';
                echo '<th>ブロック期限</th>';
                echo '<th>操作</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                
                foreach ($blocked as $ip => $data) {
                    $blocked_until = isset($data['blocked_until']) ? $data['blocked_until'] : 0;
                    $is_blocked = time() < $blocked_until;
                    
                    echo '<tr>';
                    echo '<td>' . esc_html($ip) . '</td>';
                    echo '<td>' . esc_html($data['attempts']) . '</td>';
                    echo '<td>';
                    if ($is_blocked) {
                        echo date('Y-m-d H:i:s', $blocked_until);
                    } else {
                        echo '<span style="color:#999">期限切れ</span>';
                    }
                    echo '</td>';
                    echo '<td>';
                    echo '<form method="post" style="display:inline">';
                    wp_nonce_field('kkyoadmkit_unblock_ip');
                    echo '<input type="hidden" name="ip_to_unblock" value="' . esc_attr($ip) . '">';
                    echo '<button type="submit" name="kkyoadmkit_unblock_ip" class="button button-small">ブロック解除</button>';
                    echo '</form>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            } else {
                echo '<p>ブロックされているIPアドレスはありません。</p>';
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * No Image設定ページ
     */
    public function page_noimage() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        // 設定保存処理
        if (isset($_POST['kkyoadmkit_noimage_submit'])) {
            check_admin_referer('kkyoadmkit_noimage');
            
            update_option('kkyoadmkit_noimage_url', esc_url_raw($_POST['kkyoadmkit_noimage_url']));
            
            echo '<div class="notice notice-success"><p>設定を保存しました</p></div>';
        }
        
        $noimage_url = get_option('kkyoadmkit_noimage_url', '');
        ?>
        <div class="wrap kkyoadmkit-admin">
            <h1>🖼️ No Image設定</h1>
            <p class="description">投稿にアイキャッチ画像が設定されていない場合に表示するデフォルト画像を設定します。</p>
            
            <form method="post">
                <?php wp_nonce_field('kkyoadmkit_noimage'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="kkyoadmkit_noimage_url">No Image画像URL</label></th>
                        <td>
                            <input type="url" name="kkyoadmkit_noimage_url" id="kkyoadmkit_noimage_url" value="<?php echo esc_attr($noimage_url); ?>" class="large-text">
                            <button type="button" class="button" id="upload-noimage-btn">画像を選択</button>
                            <p class="description">メディアライブラリから画像を選択するか、URLを直接入力してください。</p>
                            
                            <?php if (!empty($noimage_url)): ?>
                            <div style="margin-top:10px">
                                <p><strong>プレビュー:</strong></p>
                                <img src="<?php echo esc_url($noimage_url); ?>" style="max-width:300px;height:auto;border:1px solid #ddd">
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('設定を保存', 'primary', 'kkyoadmkit_noimage_submit'); ?>
            </form>
        </div>
        
        <script>
        jQuery(function($){
            // メディアアップローダー
            $('#upload-noimage-btn').on('click', function(e){
                e.preventDefault();
                
                var mediaUploader = wp.media({
                    title: 'No Image画像を選択',
                    button: {text: '選択'},
                    multiple: false
                });
                
                mediaUploader.on('select', function(){
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#kkyoadmkit_noimage_url').val(attachment.url);
                });
                
                mediaUploader.open();
            });
        });
        </script>
        <?php
    }
    
    /**
     * フロントエンド用CSS
     */
    private function get_frontend_css() {
        return '
        .kkyoadmkit-form {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .kkyoadmkit-message {
            padding: 12px;
            border-radius: 4px;
            margin-top: 15px;
        }
        .kkyoadmkit-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .kkyoadmkit-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        ';
    }
    
    /**
     * 管理画面用CSS
     */
    private function get_admin_css() {
        return '
        .kkyoadmkit-admin h1 {
            margin-bottom: 10px;
        }
        .kkyoadmkit-cards {
            margin-top: 20px;
        }
        .kkyoadmkit-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .kkyoadmkit-card h3 {
            margin-top: 0;
            color: #667eea;
        }
        .kkyoadmkit-card code {
            display: block;
            background: #f5f5f5;
            padding: 8px;
            border-radius: 4px;
            margin: 10px 0;
        }
        ';
    }
    
    /**
     * Gutenbergブロック用JavaScript
     */
    private function get_blocks_js() {
        return "
        (function(blocks, element, editor, components, i18n) {
            var el = element.createElement;
            var registerBlockType = blocks.registerBlockType;
            var InspectorControls = editor.InspectorControls;
            var TextControl = components.TextControl;
            
            // 登録フォームブロック
            registerBlockType('kkyoadmkit/register-form', {
                title: 'ユーザー登録フォーム',
                icon: 'admin-users',
                category: 'widgets',
                edit: function() {
                    return el('div', {className: 'kkyoadmkit-block'},
                        el('p', {}, '👤 ユーザー登録フォーム'),
                        el('p', {style: {fontSize: '12px', color: '#666'}}, 'プレビューはフロントエンドで確認してください')
                    );
                },
                save: function() {
                    return null;
                }
            });
            
            // レビューフォームブロック
            registerBlockType('kkyoadmkit/review-form', {
                title: 'レビューフォーム',
                icon: 'star-filled',
                category: 'widgets',
                edit: function() {
                    return el('div', {className: 'kkyoadmkit-block'},
                        el('p', {}, '⭐ レビューフォーム'),
                        el('p', {style: {fontSize: '12px', color: '#666'}}, 'プレビューはフロントエンドで確認してください')
                    );
                },
                save: function() {
                    return null;
                }
            });
            
            // お問い合わせフォームブロック
            registerBlockType('kkyoadmkit/contact-form', {
                title: 'お問い合わせフォーム',
                icon: 'email',
                category: 'widgets',
                attributes: {
                    formId: {
                        type: 'string',
                        default: 'default'
                    }
                },
                edit: function(props) {
                    return el('div', {className: 'kkyoadmkit-block'},
                        el('p', {}, '✉️ お問い合わせフォーム'),
                        el('p', {style: {fontSize: '12px', color: '#666'}}, 'プレビューはフロントエンドで確認してください')
                    );
                },
                save: function() {
                    return null;
                }
            });
        })(
            window.wp.blocks,
            window.wp.element,
            window.wp.blockEditor || window.wp.editor,
            window.wp.components,
            window.wp.i18n
        );
        ";
    }
}

// プラグインを初期化

WP_KkyoAdmKit_Extension::get_instance();
