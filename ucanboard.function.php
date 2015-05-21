<?php

require_once(_XE_PATH_."modules/UCAN-XE-Board/lib/htmlpurifier-4.6.0-standalone/HTMLPurifier.standalone.php");
require_once(_XE_PATH_."modules/UCAN-XE-Board/lib/libcurlemu-1.0.4/libcurlemu.inc.php");

function sanitize_user_html($html) {
    global $purifier;

    if ($purifier == NULL) {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', dirname(__FILE__).'/../../files/cache');
        $purifier = new HTMLPurifier($config);
    }

    return $purifier->purify($html);
}



function json_decode2($json) {
    $json = substr($json, strpos($json,'{')+1, strlen($json));
    $json = substr($json, 0, strrpos($json,'}'));
    $json = preg_replace('/(^|,)([\\s\\t]*)([^:]*) (([\\s\\t]*)):(([\\s\\t]*))/s', '$1"$3"$4:', trim($json));

    return json_decode('{'.$json.'}', true);
}


define('LIBCURLEMU_NATIVE', true);
if (!function_exists('http_response_code')) {
    function http_response_code($code = NULL) {
        if ($code !== NULL) {
            switch ($code) {
                case 100: $text = 'Continue'; break;
                case 101: $text = 'Switching Protocols'; break;
                case 200: $text = 'OK'; break;
                case 201: $text = 'Created'; break;
                case 202: $text = 'Accepted'; break;
                case 203: $text = 'Non-Authoritative Information'; break;
                case 204: $text = 'No Content'; break;
                case 205: $text = 'Reset Content'; break;
                case 206: $text = 'Partial Content'; break;
                case 300: $text = 'Multiple Choices'; break;
                case 301: $text = 'Moved Permanently'; break;
                case 302: $text = 'Moved Temporarily'; break;
                case 303: $text = 'See Other'; break;
                case 304: $text = 'Not Modified'; break;
                case 305: $text = 'Use Proxy'; break;
                case 400: $text = 'Bad Request'; break;
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 409: $text = 'Conflict'; break;
                case 410: $text = 'Gone'; break;
                case 411: $text = 'Length Required'; break;
                case 412: $text = 'Precondition Failed'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 414: $text = 'Request-URI Too Large'; break;
                case 415: $text = 'Unsupported Media Type'; break;
                case 500: $text = 'Internal Server Error'; break;
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                default:
                    exit('Unknown http status code "' . htmlentities($code) . '"');
                    break;
            }

            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            header($protocol . ' ' . $code . ' ' . $text);

            $GLOBALS['http_response_code'] = $code;
        } else {
            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
        }

        return $code;
    }
}



class ucanDummyCommentItem extends commentItem {
    var $sitecode;

    function __construct($sitecode, &$comment, $filter_html = false) {
        $this->sitecode = $sitecode;

        $this->comment_srl = $comment->id;
        $this->variables['nick_name'] = '['.$comment->university.'] '.$comment->name;
        $this->variables['user_name'] = '['.$comment->university.'] '.$comment->name;
        $this->variables['member_srl'] = $comment->user_id;
        $this->variables['user_id'] = $comment->user_id;
        $this->variables['site_code'] = $comment->site_code;

        if ($filter_html && function_exists('sanitize_user_html')) {
            $this->variables['content'] = sanitize_user_html($comment->body);
        } else {
            $this->variables['content'] = $comment->body;
        }
        $this->variables['module_srl'] = $this->variables['module_srl'];
        $this->variables['comment_srl'] = $comment->id;
        $this->variables['document_srl'] = $comment->post_id;
    }

    function isGranted() {
        $logged_info = Context::get('logged_info');
        if (!$logged_info) {
            return false;
        }

        return ($this->sitecode . "." . $logged_info->member_srl) == $this->variables['user_id'];
    }

    function getEditor() {
        $oEditorModel = getModel('editor');
        $options = new stdClass;
        $options->skin = 'xpresseditor';
        $options->allow_fileupload = false;
        $options->enable_default_component = true;
        $options->enable_component = false;
        $options->disable_html = false;
        $options->height = 100;
        $options->enable_autosave = false;
        $options->content_key_name = 'content';
        return $oEditorModel->getEditor(0, $options);
    }
}

class ucanDummyDocumentItem extends documentItem {
    var $comment_list = array();
    var $sitecode;

    function __construct($sitecode, &$post, &$logged_info, $filter_html = false) {
        $this->sitecode = $sitecode;
        $this->filter_html = $filter_html;

        $this->document_srl = $post->id;
        $this->lang_code = 'ko';
        $this->variables['user_name'] = '['.$post->university.'] '.$post->name;
        $this->variables['nick_name'] = '['.$post->university.'] '.$post->name;
        $this->variables['member_srl'] = NULL;
        $this->variables['user_id'] = $post->user_id;
        $this->variables['title'] = strip_tags($post->title);

        if ($filter_html && function_exists('sanitize_user_html')) {
            $this->variables['content'] = sanitize_user_html($post->body);
        } else {
            $this->variables['content'] = $post->body;
        }

        $this->variables['is_notice'] = $post->is_notice ? 'Y' : 'N';
        $this->variables['site_code'] = $post->site_code;
        $this->variables['module_srl'] = $this->module_srl;
        $this->variables['regdate'] = preg_replace('/[^0-9]/', '', $post->created_at);
        $this->variables['last_update'] = $this->variables['regdate'];
        $this->variables['comment_count'] = $post->comments_count;
        $this->variables['readed_count'] = $post->reads;
        $this->variables['status'] = 'PUBLIC';

        $this->exists = true;

        $this->_setComments($post);
    }

    function _setComments(&$post) {
        if (!$post->comments) {
            return;
        }

        foreach ($post->comments as $comment) {
            $item = new ucanDummyCommentItem($this->sitecode, $comment, $this->filter_html);
            $this->comment_list[$comment->id] = $item;
        }
    }

    function getComments() {
        if(!$this->getCommentCount()) return;
        return $this->comment_list;
    }

    function isEditable() {
        $logged_info = Context::get('logged_info');
        if (!$logged_info) {
            return false;
        }

        return ($this->sitecode . "." . $logged_info->member_srl) == $this->variables['user_id'];
    }

    function get($key) {
        if ($key == 'voted_count') {
            return 0;
        }
        return parent::get($key);
    }

    function setExists($exists) {
        $this->exists = $exists;
    }

    function isExists() {
        return $this->exists;
    }

    function allowComment() {
        return true;
    }

    function isEnableComment() {
        return true;
    }

    function getEditor() {
        $oEditorModel = getModel('editor');
        $options = new stdClass;
        $options->skin = 'xpresseditor';
        $options->content_style = 'default';
        $options->allow_fileupload = false;
        $options->enable_default_component = true;
        $options->enable_component = false;
        $options->disable_html = false;
        $options->height = 300;
        $options->enable_autosave = false;
        $options->content_key_name = 'content';
        return $oEditorModel->getEditor(0, $options);
    }

    function getCommentEditor() {
        $oEditorModel = getModel('editor');
        $options = new stdClass;
        $options->skin = 'xpresseditor';
        $options->allow_fileupload = false;
        $options->enable_default_component = true;
        $options->enable_component = false;
        $options->disable_html = false;
        $options->height = 100;
        $options->enable_autosave = false;
        $options->content_key_name = 'content';
        return $oEditorModel->getEditor(0, $options);
    }
}
