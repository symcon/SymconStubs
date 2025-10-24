<?php

declare(strict_types=1);
// This class is defined in the console when executing php from a form
class IPSList implements ArrayAccess, IteratorAggregate, JsonSerializable
{
    private $position = 0;
    private $selected = 0;
    private $array = [];
    private $default = [];

    public function __construct($value)
    {
        $this->position = 0;
        $decodedValue = json_decode($value, true);
        $this->array = $decodedValue['list'];
        $this->selected = $decodedValue['selected'];
        $this->default = $decodedValue['default'] ?? null;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->array);
    }

    public function offsetExists(mixed $i): bool
    {
        if (is_string($i)) {
            return isset($this->getRow($this->selected)[$i]);
        }
        else {
            return isset($this->array[$i]);
        }
    }

    public function offsetGet(mixed $i): mixed
    {
        if (is_string($i)) {
            return $this->getRow($this->selected)[$i];
        }
        else {
            return $this->array[$i];
        }
    }

    public function offsetSet(mixed $i, mixed $value): void
    {
        if (is_string($i)) {
            if (isset($this->array[$this->selected])) {
                $this->array[$this->selected][$i] = $value;
            }
            else {
                $this->default[$i] = $value;
            }
        }
        else {
            $this->array[$i] = $value;
        }
    }

    public function offsetUnset(mixed $i): void
    {
        if (is_string($i)) {
            if (isset($this->array[$this->selected])) {
                unset($this->array[$this->selected][$i]);
            }
            else {
                unset($this->default[$i]);
            }
        }
        else {
            unset($this->array[$i]);
        }
    }

    public function jsonSerialize(): mixed
    {
        return $this->getRow($this->selected);
    }

    private function getRow(mixed $i): ?array
    {
        if (isset($this->array[$i])) {
            return $this->array[$i];
        }
        else {
            return $this->default;
        }
    }
}