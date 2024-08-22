<?php

declare(strict_types=1);

class LocationControl extends IPSModule
{
    public function Create() {
        parent::Create();

        $this->RegisterVariableBoolean('IsDay', 'Is Day');
    }
}
