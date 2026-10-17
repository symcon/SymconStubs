<?php

declare(strict_types=1);
class SanitizationSplitter extends IPSModule
{
    private $forwarded = [];

    public function ForwardData($JSONString)
    {
        $this->forwarded[] = $JSONString;
        return 'forwarded';
    }

    public function GetForwarded()
    {
        return $this->forwarded;
    }

    public function SendToChildren(string $JSONString)
    {
        return $this->SendDataToChildren($JSONString);
    }
}
