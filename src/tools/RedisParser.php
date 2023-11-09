<?php

// phpcs:disable SlevomatCodingStandard.Operators.RequireOnlyStandaloneIncrementAndDecrementOperators.PreDecrementOperatorNotUsedAsStandalone

namespace Dogma\Debug;

use Exception;
use function array_pop;
use function count;
use function end;
use function is_int;
use function key;
use function strlen;
use function strpos;
use function substr;

/**
 * Modified parser from amphp/redis (https://github.com/amphp/redis/blob/master/src/RespParser.php)
 */
class RedisParser
{

    public const CRLF = "\r\n";
    public const TYPE_SIMPLE_STRING = '+';
    public const TYPE_ERROR = '-';
    public const TYPE_ARRAY = '*';
    public const TYPE_BULK_STRING = '$';
    public const TYPE_INTEGER = ':';

    /** @var string */
    private $buffer = '';

    /** @var mixed */
    private $currentResponse;

    /** @var mixed[] */
    private $arrayStack;

    /** @var int */
    private $currentSize;

    /** @var int[] */
    private $arraySizes;

    /**
     * @return mixed
     */
    public function parse(string $string)
    {
        $this->buffer .= $string;

        do {
            $type = $this->buffer[0];
            $pos = strpos($this->buffer, self::CRLF);

            if ($pos === false) {
                return null;
            }

            switch ($type) {
                case self::TYPE_SIMPLE_STRING:
                case self::TYPE_INTEGER:
                case self::TYPE_ARRAY:
                case self::TYPE_ERROR:
                    $payload = substr($this->buffer, 1, $pos - 1);
                    $remove = $pos + 2;
                    break;
                case self::TYPE_BULK_STRING:
                    $length = (int) substr($this->buffer, 1, $pos);

                    if ($length === -1) {
                        $payload = null;
                        $remove = $pos + 2;
                    } else {
                        if (strlen($this->buffer) < $pos + $length + 4) {
                            return null;
                        }

                        $payload = substr($this->buffer, $pos + 2, $length);
                        $remove = $pos + $length + 4;
                    }
                    break;
                default:
                    throw new Exception("Unknown resp data type: {$type}");
            }

            $this->buffer = substr($this->buffer, $remove);

            switch ($type) {
                case self::TYPE_INTEGER:
                case self::TYPE_ARRAY:
                    $payload = (int) $payload;
                    break;
                case self::TYPE_ERROR:
                    $payload = 'Invalid query: ' . $payload;
                    break;
                default:
                    break;
            }

            if ($this->currentResponse !== null) { // extend array response
                if ($type === self::TYPE_ARRAY) {
                    if (is_int($payload) && $payload >= 0) {
                        $this->arraySizes[] = $this->currentSize;
                        $this->arrayStack[] = &$this->currentResponse;
                        $this->currentSize = $payload + 1;
                        $this->currentResponse[] = [];
                        $this->currentResponse = &$this->currentResponse[count($this->currentResponse) - 1];
                    } else {
                        $this->currentResponse[] = null;
                    }
                } else {
                    $this->currentResponse[] = $payload;
                }

                while (--$this->currentSize === 0) {
                    if (count($this->arrayStack) === 0) {
                        $result = $this->currentResponse;

                        $this->currentResponse = null;

                        return $result;
                    }

                    // index does not start at 0 :(
                    end($this->arrayStack);
                    $key = key($this->arrayStack);
                    $this->currentResponse = &$this->arrayStack[$key];
                    $this->currentSize = array_pop($this->arraySizes);
                    unset($this->arrayStack[$key]);
                }
            } elseif ($type === self::TYPE_ARRAY) { // start new array response
                if (is_int($payload) && $payload > 0) {
                    $this->currentSize = $payload;
                    $this->arrayStack = $this->arraySizes = $this->currentResponse = [];
                } elseif ($payload === 0) {
                    return [];
                } else {
                    return null;
                }
            } else { // single data type response
                return $payload;
            }
        } while (isset($this->buffer[0]));

        return null;
    }

}
