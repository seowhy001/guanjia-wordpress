<?php
/**
 * @package 搜外内容管家
 * @version 1.0.0
 */

class Guanjia
{
    private $config;

    public function __construct()
    {
        // nothing to do
    }

    public function run() {
        $action = $_GET['a'];
        if ($action == 'client') {
            return $this->client();
        }

        $this->verifySign();

        $funcName = $action . "Action";
        if (method_exists($this, $funcName)) {
            return $this->{$funcName}();
        }
        
        $this->res(-1, '错误的入口');
    }

    private function client() {
        echo '<center>搜外内容管家接口</center>';
    }

    private function categoriesAction()
    {
        global $wpdb;
        $request = "SELECT $wpdb->terms.`term_id` as `id`, `name` as `title` ,$wpdb->term_taxonomy.`parent` as `parent_id` FROM $wpdb->terms ";
        $request .= " LEFT JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_id = $wpdb->terms.`term_id` ";
        $request .= " WHERE $wpdb->term_taxonomy.taxonomy = 'category' ";
        $request .= " ORDER BY $wpdb->terms.`term_id` asc";
        $categories = $wpdb->get_results($request);
        if (empty($categories)) {
            return [];
        }

        $categories = json_decode(json_encode($categories), true);
        foreach($categories as $key => $val) {
            $categories[$key]["id"] = intval($categories[$key]["id"]);
            $categories[$key]["parent_id"] = intval($categories[$key]["parent_id"]);
        }

        $this->res(1, "", $categories);
    }


    private function publishAction()
    {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $categoryId = $_POST['category_id'];

        $parameters = array(
            'post_title'    => trim($title),
            'post_content'  => trim($content),
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_category' => [$categoryId],
        );
        $articleId = wp_insert_post($parameters);
        
        if (!$articleId) {
            $this->res(-1, "文章发布失败");
        }
        $this->res(1, "发布成功", array(
            'url' => get_permalink($articleId),
        ));
    }

    private function upgradeAction() {
        // todo
    }

    private function getConfig()
    {
        if (empty($this->config)) {
            $this->config = wp_cache_get('guanjia_config');
            if (empty($this->config)) {
                $config = get_option('guanjia_config');
                $this->config = json_decode($config, true);
                wp_cache_add('guanjia_config', $this->config);
            }
        }
        return $this->config;
    }

    private function setConfig($data)
    {
        $config = get_option('guanjia_config');
        $this->config = empty($config) ? array() : json_decode($config, true);
        $this->config = array_merge($this->config, $data);
        update_option('guanjia_config', json_encode($this->config));
        wp_cache_add('guanjia_config', $this->config);
        return $this->config;
    }
    
    private function verifySign()
    {
        if (!isset($_GET['sign'])) {
            $this->res(-1, '未授权操作');
        }

        $sign      = $_GET['sign'];
        $checkTime  = $_GET['_t'];

        $config    = $this->getConfig();
        $signature = $this->signature($config['token'], $checkTime);
        if ($sign != $signature) {
            $this->res(-1, '签名不正确');
        }
        return $this;
    }

    private function signature($token, $_t)
    {
        $signature = md5($token . $_t);
        return $signature;
    }

    /**
     * json输出
     * @param      $code
     * @param null $msg
     * @param null $data
     * @param null $extra
     */
    public function res($code, $msg = null, $data = null, $extra = null)
    {
        @header('Content-Type:application/json;charset=UTF-8');
        if(is_array($msg)){
            $msg = implode(",", $msg);
        }
        $output = array(
            'code' => $code,
            'msg'  => $msg,
            'data' => $data
        );
        if (is_array($extra)) {
            foreach ($extra as $key => $val) {
                $output[$key] = $val;
            }
        }
        echo json_encode($output);
        die;
    }
}
