/**
 * KkyoAdmKit Gutenbergブロック定義
 * WordPress Gutenbergエディタ用のカスタムブロック
 */

(function(blocks, element, blockEditor, components, i18n) {
    // 必要な関数を取得
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls || blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var SelectControl = components.SelectControl;
    var __ = i18n.__;

    /**
     * ブロックスタイル定義
     */
    var blockStyle = {
        backgroundColor: '#f9f9f9',
        border: '2px dashed #667eea',
        borderRadius: '8px',
        padding: '20px',
        textAlign: 'center',
        minHeight: '100px',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        alignItems: 'center'
    };

    /**
     * 1. ユーザー登録フォームブロック
     */
    registerBlockType('kkyoadmkit/register-form', {
        title: __('ユーザー登録フォーム', 'wp-kkyoadmkit-ext'),
        description: __('新規ユーザーの登録フォームを表示します', 'wp-kkyoadmkit-ext'),
        icon: 'admin-users',
        category: 'widgets',
        keywords: ['register', 'user', '登録', 'ユーザー'],
        supports: {
            html: false,
            align: true
        },

        edit: function(props) {
            return el('div', { 
                className: props.className,
                style: blockStyle 
            },
                el('span', { 
                    className: 'dashicons dashicons-admin-users',
                    style: { fontSize: '48px', color: '#667eea' }
                }),
                el('h3', { style: { margin: '10px 0 5px', color: '#333' } }, 
                    __('ユーザー登録フォーム', 'wp-kkyoadmkit-ext')
                ),
                el('p', { style: { margin: 0, fontSize: '12px', color: '#666' } }, 
                    __('プレビューはフロントエンドで確認してください', 'wp-kkyoadmkit-ext')
                )
            );
        },

        save: function() {
            // サーバーサイドレンダリングを使用
            return null;
        }
    });

    /**
     * 2. レビューフォームブロック
     */
    registerBlockType('kkyoadmkit/review-form', {
        title: __('レビューフォーム', 'wp-kkyoadmkit-ext'),
        description: __('商品やサービスのレビューを投稿するフォームを表示します', 'wp-kkyoadmkit-ext'),
        icon: 'star-filled',
        category: 'widgets',
        keywords: ['review', 'rating', 'レビュー', '評価'],
        supports: {
            html: false,
            align: true
        },

        edit: function(props) {
            return el('div', { 
                className: props.className,
                style: blockStyle 
            },
                el('span', { 
                    className: 'dashicons dashicons-star-filled',
                    style: { fontSize: '48px', color: '#ffc107' }
                }),
                el('h3', { style: { margin: '10px 0 5px', color: '#333' } }, 
                    __('レビューフォーム', 'wp-kkyoadmkit-ext')
                ),
                el('p', { style: { margin: 0, fontSize: '12px', color: '#666' } }, 
                    __('プレビューはフロントエンドで確認してください', 'wp-kkyoadmkit-ext')
                )
            );
        },

        save: function() {
            // サーバーサイドレンダリングを使用
            return null;
        }
    });

    /**
     * 3. お問い合わせフォームブロック
     */
    registerBlockType('kkyoadmkit/contact-form', {
        title: __('お問い合わせフォーム', 'wp-kkyoadmkit-ext'),
        description: __('カスタマイズ可能なお問い合わせフォームを表示します', 'wp-kkyoadmkit-ext'),
        icon: 'email',
        category: 'widgets',
        keywords: ['contact', 'form', 'email', 'お問い合わせ', 'フォーム'],
        supports: {
            html: false,
            align: true
        },

        attributes: {
            formId: {
                type: 'string',
                default: 'default'
            },
            showCaptcha: {
                type: 'boolean',
                default: false
            },
            allowFileUpload: {
                type: 'boolean',
                default: false
            }
        },

        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            // インスペクターコントロール（サイドバー設定）
            var inspectorControls = el(InspectorControls, {},
                el(PanelBody, {
                    title: __('フォーム設定', 'wp-kkyoadmkit-ext'),
                    initialOpen: true
                },
                    el(TextControl, {
                        label: __('フォームID', 'wp-kkyoadmkit-ext'),
                        value: attributes.formId,
                        onChange: function(value) {
                            setAttributes({ formId: value });
                        },
                        help: __('フォームビルダーで作成したフォームIDを指定します', 'wp-kkyoadmkit-ext')
                    }),
                    el(ToggleControl, {
                        label: __('CAPTCHA表示', 'wp-kkyoadmkit-ext'),
                        checked: attributes.showCaptcha,
                        onChange: function(value) {
                            setAttributes({ showCaptcha: value });
                        }
                    }),
                    el(ToggleControl, {
                        label: __('ファイルアップロード許可', 'wp-kkyoadmkit-ext'),
                        checked: attributes.allowFileUpload,
                        onChange: function(value) {
                            setAttributes({ allowFileUpload: value });
                        }
                    })
                )
            );

            // ブロックプレビュー
            var blockPreview = el('div', { 
                className: props.className,
                style: blockStyle 
            },
                el('span', { 
                    className: 'dashicons dashicons-email',
                    style: { fontSize: '48px', color: '#667eea' }
                }),
                el('h3', { style: { margin: '10px 0 5px', color: '#333' } }, 
                    __('お問い合わせフォーム', 'wp-kkyoadmkit-ext')
                ),
                el('p', { style: { margin: '5px 0', fontSize: '12px', color: '#666' } }, 
                    __('フォームID: ', 'wp-kkyoadmkit-ext') + attributes.formId
                ),
                el('p', { style: { margin: 0, fontSize: '12px', color: '#999' } }, 
                    __('プレビューはフロントエンドで確認してください', 'wp-kkyoadmkit-ext')
                )
            );

            return el('div', {},
                inspectorControls,
                blockPreview
            );
        },

        save: function() {
            // サーバーサイドレンダリングを使用
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