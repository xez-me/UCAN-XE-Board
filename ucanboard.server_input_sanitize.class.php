<?php


class ucanboardServerInputSanitize
{
    private $depth = 0;
    private $html_whitelist_property_key = array ('body', 'title');
    private $html_sanitize = true;


    /**
     * @info 기본 생성자 입니다.
     * @param bool $html_sanitize
     */
    public function __construct($html_sanitize = true)
    {
        $this->html_sanitize = $html_sanitize;
    }


    /**
     * @info 외부에서 호출하여 변수를 sanitize 하는 method 입니다.
     * @param $variable : server 에서 온 값입니다.,
     * @return mixed : sanitized 된 변수입니다.
     */
    public function sanitize($variable)
    {
        return $this->sanitizeRecursively($variable, '');
    }


    /**
     * @param $variable : sanitize할 변수
     * @param string $key : object 또는 array의 key값(존재하지 않을경우 empty string)
     * @return mixed : sanitized된 값
     */
    private function sanitizeRecursively(&$variable, $key = '')
    {
        // 20depth 이상 가지 못하도록 체크
        $this->depth++;
        if ($this->depth > 20)
        {
            $this->depth--;
            return '';
        }

        // 안전한 타입은 그대로 사용합니다.
        elseif (is_int($variable) === true ||
            is_float($variable) === true ||
            is_bool($variable) === true ||
            is_long($variable) === true ||
            is_null($variable) === true ||
            is_double($variable) === true
        )
        {
            $this->depth--;
            return $variable;
        }

        // string type의 경우 사전에 별도로 지정하지 않는한 html 인코딩을 합니다.
        elseif(is_string($variable) === true)
        {
            // string인 경우 사전에 지정된 key값(title, body) 인지 체크
            if(in_array($key, $this->html_whitelist_property_key) === true)
            {

                // 사전에 지정된 key값이라도 sanitize 할 여부를 확인.
                if($this->html_sanitize === true)
                {
                    $this->depth--;
                    return (string)sanitize_user_html($variable);
                }
                else
                {
                    $this->depth--;
                    return $variable;
                }
            }
            else
            {

                // 그 이외의 모든 string은 html 인코딩.
                $this->depth--;
                return htmlspecialchars($variable, ENT_COMPAT, 'UTF-8', false);
            }
        }

        //변수가 object이거나 array인 경우 recursive 하게 호출하여 primitive type이 나올때까지 진행한다.
        elseif(is_object($variable) === true || is_array($variable) === true)
        {
            foreach ($variable as $key => &$val)
            {
                $val = $this->sanitizeRecursively($val, $key);
            }
            $this->depth--;
            return $variable;
        }
        else
        {

            //object array string등이 아닌 수상한 type은 ''으로 초기화 한다.
            $this->depth--;
            return '';
        }

    }



}