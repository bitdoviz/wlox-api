<?php

namespace baibaratsky\WebMoney\Request;

abstract class XmlRequest extends AbstractRequest
{
    /**
     * @return string
     */
    abstract public function getData();

    /**
     * @param string $name
     * @param string|int|float $value
     *
     * @return string
     */
    protected static function xmlElement($name, $value)
    {
        return !empty($value) || is_numeric($value) ? '<' . $name . '>' . $value . '</' . $name . '>' : '';
    }
}
