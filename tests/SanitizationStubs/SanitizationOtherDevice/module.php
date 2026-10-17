<?php

declare(strict_types=1);
class SanitizationOtherDevice extends IPSModule
{
    public function ReceiveData($JSONString)
    {
        return 'other';
    }
}
