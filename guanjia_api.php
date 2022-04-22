<?php
/**
 * @package 搜外内容管家
 * @version 1.0.0
 */
/*
Plugin Name: 搜外内容管家
Plugin URI: https://guanjia.seowhy.com/
Description: 搜外内容管家文章发布插件
Author: Sinclair
Version: 1.0.0
*/
defined('ABSPATH') || exit;

class GuanjiaApi
{
    private $page_url;

    public function __construct()
    {
        $this->page_url = network_admin_url(is_multisite() ? 'settings.php?page=guanjia' : 'options-general.php?page=guanjia');
    }

    public function init()
    {
        if (is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
            add_filter(sprintf('%splugin_action_links_%s', is_multisite() ? 'network_admin_' : '', plugin_basename(__FILE__)), function ($links) {
                return array_merge(
                    [sprintf('<a href="%s">%s</a>', $this->page_url, '设置')],
                    $links
                );
            });

            register_deactivation_hook(__FILE__, function () {
                delete_option("token");
                delete_option("app_version");
            });

            add_action(is_multisite() ? 'network_admin_menu' : 'admin_menu', function () {
                add_submenu_page(
                    is_multisite() ? 'settings.php' : 'options-general.php',
                    '内容管家',
                    '内容管家',
                    is_multisite() ? 'manage_network_options' : 'manage_options',
                    'guanjia',
                    [$this, 'options_page_html']
                );
            });
        }

        if (is_admin()) {
            add_action('admin_init', function () {
                register_setting('guanjia', 'token');
                register_setting('guanjia', 'app_version');
                register_setting('guanjia', 'app_url');

                add_settings_section(
                    'guanjia_section_main',
                    '该插件需要配合搜外内容管家使用。token获取请访问 <a href="https://guanjia.seowhy.com/" target="_blank">https://guanjia.seowhy.com/</a>',
                    '',
                    'guanjia'
                );

                add_settings_field(
                    'guanjia_field_select_token',
                    'Token',
                    [$this, 'field_token_cb'],
                    'guanjia',
                    'guanjia_section_main'
                );

                add_settings_field(
                    'guanjia_field_select_app_version',
                    '插件版本',
                    [$this, 'field_app_version_cb'],
                    'guanjia',
                    'guanjia_section_main'
                );

                add_settings_field(
                    'guanjia_field_select_app_url',
                    '插件地址',
                    [$this, 'field_app_url_cb'],
                    'guanjia',
                    'guanjia_section_main'
                );
            });

            add_filter('pre_http_request', function ($preempt, $r, $url) {
                if ((!stristr($url, 'api.wordpress.org') && !stristr($url, 'downloads.wordpress.org'))) {
                    return false;
                }
                $url = str_replace('api.wordpress.org', 'api.w.org.ibadboy.net', $url);
                $url = str_replace('downloads.wordpress.org', 'd.w.org.ibadboy.net', $url);

                $curl_version = '1.0.0';
                if (function_exists('curl_version')) {
                    $curl_version_array = curl_version();
                    if (is_array($curl_version_array) && key_exists('version', $curl_version_array)) {
                        $curl_version = $curl_version_array['version'];
                    }
                }

                if (version_compare($curl_version, '7.15.0', '<')) {
                    $url = str_replace('https://', 'http://', $url);
                }

                return wp_remote_request($url, $r);
            }, 10, 3);
        }
    }

    public function field_token_cb()
    {
        $config = get_option('guanjia_config');
        $token        = '';
        if ($config) {
            $config = json_decode($config, true);
            if (isset($config['token'])) {
                $token = $config['token'];
            }
        }
        ?>
        <input name="token" class='regular-text' type="text" id="guanjia-token" value="<?php echo $token; ?>" >

        <p class="description">
            内容管家对接的token
        </p>
        <?php
    }

    public function field_app_version_cb()
    {
        $config = get_option('guanjia_config');
        $version      = 'V1.0.0';
        if ($config) {
            $config = json_decode($config, true);
            if (isset($config['version'])) {
                $version = $config['version'];
            }
        }
        ?>
        <strong><?php echo $version; ?></strong>

        <p class="description">
            当前插件版本
        </p>
        <?php

    }

    public function field_app_url_cb()
    {
        $app_url = esc_url(home_url('/')) . 'wp-content/plugins/guanjia/guanjia.php?a=client';
        ?>
        <strong><?php echo $app_url; ?></strong>

        <p class="description">
            插件地址
        </p>
        <?php
    }

    public function options_page_html()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = array(
                'token'      => sanitize_text_field($_POST['token']),
                'admin_path' => esc_url(home_url('/')),
            );
            $config = get_option('guanjia_config');
            if (!empty($config)) {
                $config = json_decode($config, true);
                unset($config['token']);
                unset($config['admin_path']);
                $data = array_merge($data, $config);
            } else {
                $data['version'] = 'V1.0.0';
            }
            update_option("guanjia_config", json_encode($data));
            echo '<div class="notice notice-success settings-error is-dismissible"><p><strong>设置已保存</strong></p></div>';
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="<?php echo $this->page_url; ?>" method="post">
                <?php
                    settings_fields('guanjia');
                    do_settings_sections('guanjia');
                    submit_button('保存配置');
                ?>
            </form>
        </div>

        <?php
    }

    private function page_str_replace($replace_func, $param, $level)
    {
        if ($level == 3 && is_admin()) {
            return;
        } elseif ($level == 4 && !is_admin()) {
            return;
        }

        add_action('init', function () use ($replace_func, $param) {
            ob_start(function ($buffer) use ($replace_func, $param) {
                $param[] = $buffer;
                return call_user_func_array($replace_func, $param);
            });
        });
    }
}

(new GuanjiaApi)->init();