<?php
namespace Ratchet\Session\Serialize;

class PhpHandler implements HandlerInterface {
    private $strlen = 'strlen';
    private $substr = 'substr';
    private $strtolower = 'strtolower';

    public function __construct()
    {
        if(extension_loaded('mbstring'))
        {
            $this->strlen = 'mb_strlen';
            $this->substr = 'mb_substr';
            $this->strtolower = 'mb_strtolower';
        }
    }

    /**
     * Simply reverse behaviour of unserialize method.
     * {@inheritdoc}
     */
    function serialize(array $data) {
        $preSerialized = array();
        $serialized = '';

        if (count($data)) {
            foreach ($data as $bucket => $bucketData) {
                $preSerialized[] = $bucket . '|' . serialize($bucketData);
            }
            $serialized = implode('', $preSerialized);
        }

        return $serialized;
    }

    /**
     * The serialize method design is unfortunate as there is no separator used if there is more than 1 bucket.
     * Therefore we have to write our own implementation of unserialize to properly decode the input string.
     * {@inheritdoc}
     * @link http://ca2.php.net/manual/en/function.session-decode.php#108037 Code from this comment on php.net has a bug.
     * @throws \UnexpectedValueException If there is a problem parsing the data
     * 
     */
    public function unserialize($raw, $encoding = 'UTF-8') {
        $returnData = array();
        $tokens = $this->tokenize($raw, $encoding);
        $tokenCount = count($tokens);
        $varName = '';
        for($index = 0; $index < $tokenCount; $index++)
        {
            if($tokens[$index]->type == 'identifier' && isset($tokens[$index + 1]) && $tokens[$index + 1]-> type == 'pipe')
            {
                if($varName != '')
                {
                    $returnData[$varName] = unserialize($returnData[$varName]);
                    $varName = $tokens[$index]->value;
                    $returnData[$varName] = '';
                }
                else
                {
                    $varName = $tokens[$index]->value;
                    $returnData[$varName] = '';
                }
                continue;
            }
            if($tokens[$index]-> type == 'pipe')
            {
                continue;
            }

            $returnData[$varName] .= $tokens[$index]->value;
        }

        $returnData[$varName] = unserialize($returnData[$varName]);

        return $returnData;
    }

    function tokenize($string, $encoding)
    {
        $tokens = array();

        if(!is_string($string))
        {
            throw new \InvalidArgumentException("Invalid parameter type '" . gettype($string) . "'. Expected type is 'string'.");
        }

        $sl = $this->strlen;
        $ss = $this->substr;

        $charsCount = $sl($string, $encoding);

        if($charsCount == 0)
        {
            return $tokens;
        }

        for($i = 0; $i < $charsCount; $i++){
            $char = $ss($string, $i, 1, $encoding);
        
            if($char == '"' || $char == "'")
            {
                $tokens[] = $this->parseTokenString($char, $string, $i, $charsCount, $encoding);
            }
            else
            {
                $tokens[] = $this->parseToken($char, $string, $i, $charsCount, $encoding);
            }
        }

        return $tokens;
    }

    function parseTokenString($char, $string, &$i, $length, $encoding)
    {
        $result = new Token();
        $result->position = $i;
        $token = $char;
        ++$i;
        $isEscaped = false;
        $isInString = true;
        $sl = $this->strlen;
        $ss = $this->substr;

        for($i; $i < $length; $i++)
        {
            $next = $ss($string, $i, 1, $encoding);
            $token .= $next;
            if($next == $char)
            {
                if($isEscaped)
                {
                    continue;
                }

                $isInString = false;
                break;
            }
            if($next == "\\")
            {
                $isEscaped = !$isEscaped;
            }
        }

        if($isInString)
        {
            throw new \UnexpectedValueException("Invalid string literal" . $ss($string, $result->position, $length - $result->position, $encoding));
        }

        $result->length = strlen($token);
        $result->value = $token;
        $result->type = 'string_literal';

        return $result;
    }

    function parseToken($char, $string, &$i, $length, $encoding)
    {
        $result = new Token();
        $result->position = $i;
        $token = $char;
        $controlChars = array(':' => 'double_colon', '|' => 'pipe', '{' => 'curly_brace_open', '}' => 'curly_brace_close', ';' => 'semicolon');

        if(isset($controlChars[$token]))
        {
            $result->value = $token;
            $result->length = 1;
            $result->type = $controlChars[$token];

            return $result;
        }

        ++$i;
        $isNumber = $char >= '0' && $char <= '9';
        $ss = $this->substr;
        $stl = $this->strtolower;

        for($i; $i < $length; $i++)
        {
            $next = $ss($string, $i, 1, $encoding);

            if($next == '"' || $next == "'")
            {
                throw new \UnexpectedValueException("Invalid string literal ' . $next . ' at position " . $i . ".");
            }

            if(isset($controlChars[$next]))
            {
                --$i;
                break;
            }

            if($isNumber && ($next < '0' || $next > '9'))
            {
                $isNumber = false;
            }
            $token .= $next;
        }

        $result->length = strlen($token);
        $result->value = $token;
        if(!$isNumber)
        {
            $types = array('s' => 'string', 'i' => 'number', 'n' => 'null', 'a' => 'array', 'o' => 'object', 'e' => 'enum');
            $lower = $stl($token);
            $result->type = isset($types[$lower]) ? $types[$lower] : 'identifier';
        }
        else
        {
            $result->type = 'number_literal';
        }

        return $result;
    }
}
